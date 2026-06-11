<?php

namespace App\Livewire;

use App\Models\AIConversation;
use App\Models\OptimizationReport;
use App\Models\OptimizationReportItem;
use App\Services\BasketDiscountCampaignService;
use App\Services\CampaignAnalysisService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

class BasketDiscountCampaign extends Component
{
    use WithFileUploads;

    public int $step = 1;
    public string $message = '';
    public string $messageType = 'info';
    public bool $isProcessing = false;
    public string $activeTab = 'analyze';

    public $excelFile;
    public string $reportName = '';
    public string $campaignTitle = '2000 TL Üzeri 150 TL İndirim';
    public $thresholdAmount = 2000;
    public $discountAmount = 150;
    public $sellerSharePercent = 60;
    public $targetMarginPercent = 15;

    public ?int $activeReportId = null;
    public array $selectedItems = [];

    public array $suggestedPrices = [];
    public bool $showChat = false;
    public ?int $chatConversationId = null;
    public string $chatMessage = '';
    public bool $isChatting = false;

    public string $searchQuery = '';
    public string $statusFilter = 'all';
    public string $sortField = 'suggested_net_profit';
    public string $sortDirection = 'desc';
    public array $visibleColumns = [
        'costs' => true,
        'current' => true,
        'campaign' => true,
        'discount' => true,
        'price_action' => true,
    ];
    public array $sortableColumns = [
        'product_name' => 'Ürün',
        'current_price' => 'Mevcut fiyat',
        'suggested_price' => 'Maksimum tutar',
        'current_net_profit' => 'Mevcut kâr',
        'suggested_net_profit' => 'Kampanya kârı',
        'extra_profit' => 'Fark',
        'total_cost' => 'Toplam maliyet',
    ];

    public function mount()
    {
        if (request()->has('report')) {
            $reportId = (int) request()->query('report');
            if ($reportId > 0) {
                $this->viewReport($reportId);
            }
        }
    }

    #[Computed]
    public function productCount(): int
    {
        return app(CampaignAnalysisService::class)->getProductCount(auth()->id());
    }

    #[Computed]
    public function costCount(): int
    {
        return app(CampaignAnalysisService::class)->getProductWithCostCount(auth()->id());
    }

    #[Computed]
    public function activeReport()
    {
        if (!$this->activeReportId) {
            return null;
        }

        return OptimizationReport::with('items')
            ->where('user_id', auth()->id())
            ->ofType('basket_discount')
            ->find($this->activeReportId);
    }

    #[Computed]
    public function reports()
    {
        return OptimizationReport::where('user_id', auth()->id())
            ->ofType('basket_discount')
            ->orderByDesc('created_at')
            ->get();
    }

    #[Computed]
    public function filteredItems(): Collection
    {
        $report = $this->activeReport;
        if (!$report) {
            return collect();
        }

        $items = $report->items;
        $query = trim(Str::lower($this->searchQuery));

        if ($query !== '') {
            $items = $items->filter(function ($item) use ($query) {
                return Str::contains(Str::lower((string) $item->product_name), $query)
                    || Str::contains(Str::lower((string) $item->stock_code), $query)
                    || Str::contains(Str::lower((string) $item->barcode), $query)
                    || Str::contains(Str::lower((string) data_get($item->campaign_data, 'listing_id')), $query);
            });
        }

        $items = $items->filter(function ($item) {
            return match ($this->statusFilter) {
                'opportunity' => $item->action === 'update',
                'risk' => $item->action === 'warning' || $this->projectedNetProfit($item) < 0,
                'selected' => in_array($item->id, $this->selectedItems, true) || (bool) $item->is_selected,
                'missing_cost' => $item->totalCost() <= 0,
                'unmatched' => !((bool) data_get($item->campaign_data, 'matched', true)),
                'negative_delta' => (float) $item->extra_profit < 0 && $item->action === 'update',
                'kept' => $item->action === 'keep',
                default => true,
            };
        });

        $sorter = function ($item) {
            return match ($this->sortField) {
                'product_name' => Str::lower((string) ($item->product_name ?: $item->stock_code)),
                'current_price' => (float) $item->current_price,
                'suggested_price' => (float) ($item->suggested_price ?? 0),
                'current_net_profit' => (float) $item->current_net_profit,
                'suggested_net_profit' => $this->projectedNetProfit($item),
                'total_cost' => $item->totalCost(),
                default => (float) $item->extra_profit,
            };
        };

        return ($this->sortDirection === 'asc'
            ? $items->sortBy($sorter, SORT_REGULAR)
            : $items->sortByDesc($sorter, SORT_REGULAR))
            ->values();
    }

    #[Computed]
    public function reportMetrics(): array
    {
        $report = $this->activeReport;
        if (!$report) {
            return [
                'filtered_count' => 0,
                'selected_count' => 0,
                'selected_impact' => 0,
                'selected_campaign_profit' => 0,
                'selected_seller_discount' => 0,
                'cost_coverage' => 0,
                'risk_count' => 0,
                'unmatched_count' => 0,
                'negative_delta_count' => 0,
                'top_opportunity' => null,
                'worst_loss' => null,
            ];
        }

        $items = $report->items;
        $selectedItems = $items->filter(fn ($item) => in_array($item->id, $this->selectedItems, true) || (bool) $item->is_selected);
        $costReadyCount = $items->filter(fn ($item) => $item->totalCost() > 0)->count();
        $totalCount = max(1, $items->count());

        return [
            'filtered_count' => $this->filteredItems->count(),
            'selected_count' => $selectedItems->count(),
            'selected_impact' => round($selectedItems->sum(fn ($item) => $this->projectedNetProfit($item) - (float) $item->current_net_profit), 2),
            'selected_campaign_profit' => round($selectedItems->sum(fn ($item) => $this->projectedNetProfit($item)), 2),
            'selected_seller_discount' => round($selectedItems->sum(fn ($item) => $this->campaignSellerDiscount($item)), 2),
            'cost_coverage' => round(($costReadyCount / $totalCount) * 100, 1),
            'risk_count' => $items->filter(fn ($item) => $item->action === 'warning' || $this->projectedNetProfit($item) < 0)->count(),
            'unmatched_count' => $items->filter(fn ($item) => !((bool) data_get($item->campaign_data, 'matched', true)))->count(),
            'negative_delta_count' => $items->filter(fn ($item) => (float) $item->extra_profit < 0 && $item->action === 'update')->count(),
            'top_opportunity' => $items->where('action', 'update')->sortByDesc(fn ($item) => $this->projectedNetProfit($item))->first(),
            'worst_loss' => $items->sortBy(fn ($item) => $this->projectedNetProfit($item))->first(),
        ];
    }

    public function analyze()
    {
        $this->validate([
            'excelFile' => 'required|file|mimes:xlsx,xls|max:10240',
            'thresholdAmount' => 'required|numeric|min:1',
            'discountAmount' => 'required|numeric|min:0',
            'sellerSharePercent' => 'required|numeric|min:0|max:100',
            'targetMarginPercent' => 'required|numeric|min:0|max:1000',
        ]);

        $this->step = 2;
        $this->isProcessing = true;

        $result = app(BasketDiscountCampaignService::class)->analyze($this->excelFile, $this->reportName ?: null, [
            'campaign_title' => $this->campaignTitle,
            'threshold_amount' => $this->thresholdAmount,
            'discount_amount' => $this->discountAmount,
            'seller_share_percent' => $this->sellerSharePercent,
            'target_margin_percent' => $this->targetMarginPercent,
        ]);

        $this->isProcessing = false;

        if ($result['success']) {
            $this->activeReportId = $result['report_id'];
            $this->step = 3;
            $this->message = $result['message'];
            $this->messageType = 'success';
            $this->selectedItems = [];
            $this->loadReportSettings();
        } else {
            $this->step = 1;
            $this->message = $result['message'];
            $this->messageType = 'error';
        }

        $this->reset('excelFile', 'reportName');
    }

    public function selectCampaignPrice(int $itemId)
    {
        $item = $this->reportItem($itemId);
        if (!$item || !isset($item->scenario_details[1])) {
            return;
        }

        $scenario = $item->scenario_details[1];
        $targetMargin = (float) data_get($item->campaign_data, 'target_profitability_ratio', 1 + ($this->targetMarginPercent / 100));
        $netProfit = (float) ($scenario['net_profit'] ?? 0);
        $margin = (float) ($scenario['margin_pct'] ?? 0);

        $item->update([
            'selected_tariff_index' => 1,
            'custom_price' => null,
            'suggested_tariff' => 'Maksimum Tutar',
            'suggested_price' => (float) ($scenario['price'] ?? $item->suggested_price),
            'suggested_commission' => (float) ($scenario['commission'] ?? $item->current_commission),
            'suggested_net_profit' => $netProfit,
            'extra_profit' => round($netProfit - (float) $item->current_net_profit, 2),
            'action' => $item->productCostForProfitability() > 0 && $netProfit > 0 && $margin >= $targetMargin ? 'update' : 'warning',
            'is_selected' => true,
        ]);

        if (!in_array($itemId, $this->selectedItems, true)) {
            $this->selectedItems[] = $itemId;
        }
    }

    public function keepCurrentPrice(int $itemId)
    {
        $item = $this->reportItem($itemId);
        if (!$item) {
            return;
        }

        $item->update([
            'selected_tariff_index' => null,
            'custom_price' => null,
            'suggested_tariff' => 'Mevcut',
            'suggested_price' => $item->current_price,
            'suggested_commission' => $item->current_commission,
            'suggested_net_profit' => $item->current_net_profit,
            'extra_profit' => 0,
            'action' => (float) $item->current_net_profit < 0 ? 'warning' : 'keep',
            'is_selected' => false,
        ]);

        $this->selectedItems = array_values(array_diff($this->selectedItems, [$itemId]));
    }

    public function updateCustomPrice(int $itemId, $newPrice)
    {
        $item = $this->reportItem($itemId);
        if (!$item) {
            return;
        }

        $price = $this->normalizeNumber($newPrice);
        $maxPrice = (float) data_get($item->campaign_data, 'max_price', 0);
        if ($maxPrice > 0) {
            $price = min($price, $maxPrice);
        }
        $price = round(max(0, $price), 2);

        $service = app(BasketDiscountCampaignService::class);
        $sellerDiscount = $service->calculateSellerDiscount(
            $price,
            (float) data_get($item->campaign_data, 'threshold_amount', 2000),
            (float) data_get($item->campaign_data, 'discount_amount', 150),
            (float) data_get($item->campaign_data, 'seller_share_percent', 60)
        );
        $commission = (float) ($item->suggested_commission ?: $item->current_commission);
        $netProfit = $service->calculateCampaignNetProfit($price, $commission, $item->totalCost(), $sellerDiscount);
        $margin = $service->calculateMarginPercent($netProfit, $item->productCostForProfitability());
        $targetMargin = (float) data_get($item->campaign_data, 'target_profitability_ratio', 1 + ($this->targetMarginPercent / 100));

        $scenarios = $item->scenario_details ?? [];
        $scenarios[1] = array_merge($scenarios[1] ?? [], [
            'name' => 'Özel Kampanya Fiyatı',
            'price' => $price,
            'commission' => $commission,
            'seller_discount' => $sellerDiscount,
            'net_profit' => $netProfit,
            'margin_pct' => $margin,
            'target_margin_pct' => $targetMargin,
            'is_best' => $item->productCostForProfitability() > 0 && $netProfit > 0 && $margin >= $targetMargin,
        ]);

        $item->update([
            'custom_price' => $price,
            'selected_tariff_index' => 1,
            'suggested_tariff' => 'Özel Kampanya Fiyatı',
            'suggested_price' => $price,
            'suggested_commission' => $commission,
            'suggested_net_profit' => $netProfit,
            'extra_profit' => round($netProfit - (float) $item->current_net_profit, 2),
            'action' => $item->productCostForProfitability() > 0 && $netProfit > 0 && $margin >= $targetMargin ? 'update' : 'warning',
            'is_selected' => true,
            'scenario_details' => $scenarios,
        ]);

        if (!in_array($itemId, $this->selectedItems, true)) {
            $this->selectedItems[] = $itemId;
        }
    }

    public function autoSelectProfitable()
    {
        $report = $this->activeReport;
        if (!$report) {
            return;
        }

        $ids = $report->items
            ->filter(fn ($item) => $this->meetsTargetMargin($item))
            ->pluck('id')
            ->values()
            ->toArray();

        $this->selectedItems = $ids;
        $report->items()->update(['is_selected' => false]);
        if (!empty($ids)) {
            OptimizationReportItem::whereIn('id', $ids)->update(['is_selected' => true, 'selected_tariff_index' => 1]);
        }

        if (empty($ids)) {
            $this->message = 'Hedef kârlılığı geçen ürün bulunamadı.';
            $this->messageType = 'info';
        } else {
            $this->message = count($ids) . ' ürün hedef kârlılığa göre seçildi.';
            $this->messageType = 'success';
        }
    }

    public function selectFilteredOpportunities()
    {
        $ids = $this->filteredItems
            ->filter(fn ($item) => $this->meetsTargetMargin($item))
            ->pluck('id')
            ->values()
            ->toArray();

        $this->selectedItems = $ids;
        $report = $this->activeReport;
        if ($report) {
            $report->items()->update(['is_selected' => false]);
        }
        if (!empty($ids)) {
            OptimizationReportItem::whereIn('id', $ids)->update(['is_selected' => true, 'selected_tariff_index' => 1]);
        }
    }

    public function deselectAll()
    {
        $report = $this->activeReport;
        if ($report) {
            $report->items()->update(['is_selected' => false]);
        }
        $this->selectedItems = [];
    }

    public function toggleItem(int $itemId)
    {
        $item = $this->reportItem($itemId);
        if (!$item) {
            return;
        }

        if (in_array($itemId, $this->selectedItems, true)) {
            $this->selectedItems = array_values(array_diff($this->selectedItems, [$itemId]));
            $item->update(['is_selected' => false]);
            return;
        }

        $this->selectedItems[] = $itemId;
        $item->update(['is_selected' => true, 'selected_tariff_index' => 1]);
    }

    public function setStatusFilter(string $filter)
    {
        if (!in_array($filter, ['all', 'opportunity', 'risk', 'selected', 'missing_cost', 'unmatched', 'negative_delta', 'kept'], true)) {
            return;
        }

        $this->statusFilter = $filter;
    }

    public function sortTable(string $field)
    {
        if (!array_key_exists($field, $this->sortableColumns)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
            return;
        }

        $this->sortField = $field;
        $this->sortDirection = $field === 'product_name' ? 'asc' : 'desc';
    }

    public function toggleColumn(string $column)
    {
        if (!array_key_exists($column, $this->visibleColumns)) {
            return;
        }

        $this->visibleColumns[$column] = !$this->visibleColumns[$column];
    }

    public function showColumn(string $column): bool
    {
        return (bool) ($this->visibleColumns[$column] ?? false);
    }

    public function clearTableFilters()
    {
        $this->searchQuery = '';
        $this->statusFilter = 'all';
    }

    public function exportSelected()
    {
        if (!$this->activeReportId) {
            return;
        }

        $exportIds = $this->selectedItems;
        if (empty($exportIds)) {
            $report = $this->activeReport;
            if ($report) {
                $exportIds = $report->items
                    ->filter(fn ($item) => (bool) $item->is_selected)
                    ->pluck('id')
                    ->values()
                    ->toArray();
            }
        }

        if (empty($exportIds)) {
            $this->message = 'Export için önce en az bir kârlı kampanya ürünü seçin.';
            $this->messageType = 'error';
            return;
        }

        $filePath = app(BasketDiscountCampaignService::class)->generateExport($this->activeReportId, $exportIds);

        if ($filePath && file_exists($filePath)) {
            $this->message = count($exportIds) . ' ürün için Trendyol yükleme Exceli hazırlandı.';
            $this->messageType = 'success';
            return response()->download($filePath, basename($filePath));
        }

        $this->message = 'Export dosyası oluşturulamadı.';
        $this->messageType = 'error';
    }

    public function viewReport(int $reportId)
    {
        $report = OptimizationReport::where('user_id', auth()->id())
            ->ofType('basket_discount')
            ->find($reportId);

        if (!$report) {
            return;
        }

        $this->activeReportId = $reportId;
        $this->activeTab = 'analyze';
        $this->step = 3;
        $this->selectedItems = $report->items()->where('is_selected', true)->pluck('id')->toArray();
        $this->searchQuery = '';
        $this->statusFilter = 'all';
        $this->sortField = 'suggested_net_profit';
        $this->sortDirection = 'desc';
        $this->message = '';
        $this->loadReportSettings();
    }

    public function deleteReport(int $reportId)
    {
        $report = OptimizationReport::where('user_id', auth()->id())
            ->ofType('basket_discount')
            ->find($reportId);

        if ($report) {
            $report->items()->delete();
            $report->delete();

            if ($this->activeReportId === $reportId) {
                $this->activeReportId = null;
                $this->step = 1;
            }
        }
    }

    public function resetAnalysis()
    {
        $this->reset(['activeReportId', 'selectedItems', 'message', 'messageType', 'searchQuery', 'statusFilter']);
        $this->step = 1;
    }

    public function switchTab(string $tab)
    {
        $this->activeTab = $tab;
    }

    public function generateAIAnalysis()
    {
        $report = $this->activeReport;
        if (!$report) {
            return;
        }

        $this->isProcessing = true;
        $this->message = 'AI kampanya stratejisi hazırlanıyor...';

        try {
            $analysis = app(\App\Services\AIService::class)->analyzeBasketDiscountCampaign($report);
            $report->update(['ai_analysis' => $analysis]);
            $this->message = 'AI analiz tamamlandı.';
            $this->messageType = 'success';
        } catch (\Exception $e) {
            $this->message = 'AI analiz hatası: ' . $e->getMessage();
            $this->messageType = 'error';
        }

        $this->isProcessing = false;
    }

    public function analyzeLosses()
    {
        $report = $this->activeReport;
        if (!$report) {
            return;
        }

        $this->isProcessing = true;

        try {
            $analysis = app(\App\Services\AIService::class)->analyzeLosses($report);
            $report->update(['loss_analysis' => $analysis]);
            $this->message = 'Zarar analizi tamamlandı.';
            $this->messageType = 'success';
        } catch (\Exception $e) {
            $this->message = 'Analiz hatası: ' . $e->getMessage();
            $this->messageType = 'error';
        }

        $this->isProcessing = false;
    }

    public function toggleChat()
    {
        $this->showChat = !$this->showChat;

        if ($this->showChat && !$this->chatConversationId && $this->activeReportId) {
            $conversation = AIConversation::where('user_id', auth()->id())
                ->where('optimization_report_id', $this->activeReportId)
                ->latest()
                ->first();

            if (!$conversation) {
                $conversation = AIConversation::create([
                    'user_id' => auth()->id(),
                    'optimization_report_id' => $this->activeReportId,
                    'messages' => [[
                        'role' => 'system',
                        'content' => 'Merhaba! Sepet indirimi kampanya raporunuzda maksimum tutar, satıcı indirim payı ve kârlılık eşiği üzerinden yardımcı olabilirim.',
                        'timestamp' => now()->toISOString(),
                    ]],
                ]);
            }

            $this->chatConversationId = $conversation->id;
        }
    }

    #[Computed]
    public function chatConversation()
    {
        if (!$this->chatConversationId) {
            return null;
        }

        return AIConversation::find($this->chatConversationId);
    }

    public function sendMessage()
    {
        $this->validate(['chatMessage' => 'required|string|max:1000']);

        if (!$this->chatConversationId && $this->activeReportId) {
            $this->toggleChat();
        }

        if (!$this->chatConversationId) {
            return;
        }

        $conversation = AIConversation::find($this->chatConversationId);
        if (!$conversation) {
            return;
        }

        $userMsg = $this->chatMessage;
        $this->chatMessage = '';
        $this->isChatting = true;
        $conversation->addMessage('user', $userMsg);

        try {
            $response = app(\App\Services\AIService::class)->chatWithReport($conversation, $userMsg);
            $conversation->addMessage('assistant', $response);
        } catch (\Exception $e) {
            $conversation->addMessage('assistant', 'Hata: ' . $e->getMessage());
        }

        $this->isChatting = false;
    }

    public function getAiPriceSuggestion(int $itemId)
    {
        $item = $this->reportItem($itemId);
        if (!$item) {
            return;
        }

        $this->suggestedPrices[$itemId] = ['loading' => true];

        try {
            $maxPrice = (float) data_get($item->campaign_data, 'max_price', $item->suggested_price);
            $suggestion = app(\App\Services\AIService::class)->suggestPrice($item->product_name, $item->totalCost(), (float) $item->current_price, [
                'current_commission' => (float) $item->current_commission,
                'current_net_profit' => (float) $item->current_net_profit,
                'campaign_type' => 'basket_discount',
                'max_price' => $maxPrice,
                'seller_discount_at_max' => $this->campaignSellerDiscount($item),
                'target_margin_percent' => (float) data_get($item->campaign_data, 'target_margin_percent', $this->targetMarginPercent),
                'scenarios' => $item->scenario_details ?? [],
            ]);

            $price = isset($suggestion['suggested_price']) ? min((float) $suggestion['suggested_price'], $maxPrice ?: (float) $suggestion['suggested_price']) : 0;
            $this->suggestedPrices[$itemId] = [
                'price' => round($price, 2),
                'reason' => $suggestion['reason'] ?? 'AI önerisi alındı.',
                'loading' => false,
            ];
        } catch (\Exception $e) {
            $this->suggestedPrices[$itemId] = ['error' => $e->getMessage(), 'loading' => false];
        }
    }

    public function applySuggestedPrice(int $itemId)
    {
        if (isset($this->suggestedPrices[$itemId]['price'])) {
            $this->updateCustomPrice($itemId, $this->suggestedPrices[$itemId]['price']);
            unset($this->suggestedPrices[$itemId]);
        }
    }

    public function clearAiSuggestion(int $itemId)
    {
        unset($this->suggestedPrices[$itemId]);
    }

    public function projectedNetProfit(OptimizationReportItem $item): float
    {
        if ($item->custom_price) {
            $service = app(BasketDiscountCampaignService::class);
            $sellerDiscount = $this->campaignSellerDiscount($item);
            $commission = (float) ($item->suggested_commission ?: $item->current_commission);

            return $service->calculateCampaignNetProfit((float) $item->custom_price, $commission, $item->totalCost(), $sellerDiscount);
        }

        return (float) ($item->suggested_net_profit ?? $item->current_net_profit);
    }

    public function campaignSellerDiscount(OptimizationReportItem $item): float
    {
        $price = (float) ($item->custom_price ?: $item->suggested_price ?: data_get($item->campaign_data, 'max_price', $item->current_price));

        return app(BasketDiscountCampaignService::class)->calculateSellerDiscount(
            $price,
            (float) data_get($item->campaign_data, 'threshold_amount', 2000),
            (float) data_get($item->campaign_data, 'discount_amount', 150),
            (float) data_get($item->campaign_data, 'seller_share_percent', 60)
        );
    }

    public function projectedMargin(OptimizationReportItem $item): float
    {
        return app(BasketDiscountCampaignService::class)->calculateMarginPercent($this->projectedNetProfit($item), $item->productCostForProfitability());
    }

    protected function loadReportSettings(): void
    {
        $report = $this->activeReport;
        $firstItem = $report?->items->first();
        if (!$firstItem) {
            return;
        }

        $this->campaignTitle = (string) data_get($firstItem->campaign_data, 'campaign_title', $this->campaignTitle);
        $this->thresholdAmount = (float) data_get($firstItem->campaign_data, 'threshold_amount', $this->thresholdAmount);
        $this->discountAmount = (float) data_get($firstItem->campaign_data, 'discount_amount', $this->discountAmount);
        $this->sellerSharePercent = (float) data_get($firstItem->campaign_data, 'seller_share_percent', $this->sellerSharePercent);
        $this->targetMarginPercent = (float) data_get($firstItem->campaign_data, 'target_margin_percent', $this->targetMarginPercent);
    }

    protected function reportItem(int $itemId): ?OptimizationReportItem
    {
        return OptimizationReportItem::whereHas('report', function ($query) {
            $query->where('user_id', auth()->id())->where('campaign_type', 'basket_discount');
        })->find($itemId);
    }

    protected function meetsTargetMargin(OptimizationReportItem $item): bool
    {
        $targetMargin = (float) data_get($item->campaign_data, 'target_profitability_ratio', 1 + ($this->targetMarginPercent / 100));

        return $item->productCostForProfitability() > 0
            && $this->projectedNetProfit($item) > 0
            && $this->projectedMargin($item) >= $targetMargin;
    }

    protected function normalizeNumber($value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $value = trim(str_replace(['₺', ' ', "\xc2\xa0"], '', (string) $value));
        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (str_contains($value, ',')) {
            $value = str_replace(',', '.', $value);
        }

        return (float) $value;
    }

    public function render()
    {
        return view('livewire.basket-discount-campaign')
            ->layout('layouts.app', ['title' => 'Sepet İndirimi Kampanyaları']);
    }
}
