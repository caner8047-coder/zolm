<?php

namespace App\Livewire;

use App\Models\AIConversation;
use App\Models\OptimizationReport;
use App\Models\OptimizationReportItem;
use App\Services\CampaignAnalysisService;
use App\Services\FlashProductsService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

class FlashProducts extends Component
{
    use WithFileUploads;

    public int $step = 1;
    public string $message = '';
    public string $messageType = 'info';
    public bool $isProcessing = false;
    public string $activeTab = 'analyze';

    public $excelFile;
    public string $reportName = '';

    public ?int $activeReportId = null;
    public array $selectedItems = [];

    // AI
    public array $suggestedPrices = [];
    public bool $showChat = false;
    public ?int $chatConversationId = null;
    public string $chatMessage = '';
    public bool $isChatting = false;

    // Filters
    public string $searchQuery = '';
    public string $statusFilter = 'all';
    public string $sortField = 'extra_profit';
    public string $sortDirection = 'desc';
    public array $visibleColumns = [
        'costs' => true,
        'current' => true,
        'flash24' => true,
        'flash3' => true,
        'price_action' => true,
    ];
    public array $sortableColumns = [
        'product_name' => 'Ürün',
        'current_price' => 'Mevcut fiyat',
        'current_net_profit' => 'Mevcut kâr',
        'extra_profit' => 'Ek kâr',
        'total_cost' => 'Toplam maliyet',
        'suggested_net_profit' => 'Flaş kârı',
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
        if (!$this->activeReportId) return null;
        return OptimizationReport::with('items')
            ->where('user_id', auth()->id())
            ->ofType('flash')
            ->find($this->activeReportId);
    }

    #[Computed]
    public function reports()
    {
        return OptimizationReport::where('user_id', auth()->id())
            ->ofType('flash')
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

        $query = trim(Str::lower($this->searchQuery));
        $items = $report->items;

        if ($query !== '') {
            $items = $items->filter(function ($item) use ($query) {
                return Str::contains(Str::lower((string) $item->product_name), $query)
                    || Str::contains(Str::lower((string) $item->stock_code), $query)
                    || Str::contains(Str::lower((string) $item->barcode), $query);
            });
        }

        $items = $items->filter(function ($item) {
            return match ($this->statusFilter) {
                'opportunity' => $item->action === 'update',
                'risk' => $item->action === 'warning' || (float) $item->current_net_profit < 0,
                'selected' => in_array($item->id, $this->selectedItems, true) || $item->selected_tariff_index !== null || $item->custom_price !== null,
                'missing_cost' => $item->totalCost() <= 0,
                'unmatched' => !((bool) data_get($item->campaign_data, 'matched', true)),
                'kept' => $item->action === 'keep',
                'flash24' => $this->bestScenarioIndex($item) === 1,
                'flash3' => $this->bestScenarioIndex($item) === 2,
                default => true,
            };
        });

        $sorter = function ($item) {
            return match ($this->sortField) {
                'product_name' => Str::lower((string) ($item->product_name ?: $item->stock_code)),
                'current_price' => (float) $item->current_price,
                'current_net_profit' => (float) $item->current_net_profit,
                'total_cost' => $item->totalCost(),
                'suggested_net_profit' => (float) ($item->suggested_net_profit ?? $item->current_net_profit),
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
                'risk_count' => 0,
                'selected_count' => 0,
                'selected_impact' => 0,
                'cost_coverage' => 0,
                'unmatched_count' => 0,
                'top_opportunity' => null,
                'worst_loss' => null,
                'visible_extra_profit' => 0,
                'scenario_counts' => [0 => 0, 1 => 0, 2 => 0],
            ];
        }

        $items = $report->items;
        $selectedItems = $items->filter(fn ($item) => in_array($item->id, $this->selectedItems, true));
        $selectedImpact = $selectedItems->sum(fn ($item) => $this->projectedNetProfit($item) - (float) $item->current_net_profit);
        $costReadyCount = $items->filter(fn ($item) => $item->totalCost() > 0)->count();
        $totalCount = max(1, $items->count());
        $scenarioCounts = [0 => 0, 1 => 0, 2 => 0];

        foreach ($items as $item) {
            $scenarioCounts[$this->bestScenarioIndex($item)]++;
        }

        return [
            'filtered_count' => $this->filteredItems->count(),
            'risk_count' => $items->filter(fn ($item) => $item->action === 'warning' || (float) $item->current_net_profit < 0)->count(),
            'selected_count' => $selectedItems->count(),
            'selected_impact' => round($selectedImpact, 2),
            'cost_coverage' => round(($costReadyCount / $totalCount) * 100, 1),
            'unmatched_count' => $items->filter(fn ($item) => !((bool) data_get($item->campaign_data, 'matched', true)))->count(),
            'top_opportunity' => $items->where('action', 'update')->sortByDesc('extra_profit')->first(),
            'worst_loss' => $items->filter(fn ($item) => (float) $item->current_net_profit < 0)->sortBy('current_net_profit')->first(),
            'visible_extra_profit' => round($this->filteredItems->sum(fn ($item) => max(0, (float) $item->extra_profit)), 2),
            'scenario_counts' => $scenarioCounts,
        ];
    }

    public function analyze()
    {
        $this->validate(['excelFile' => 'required|file|mimes:xlsx,xls|max:10240']);
        $this->step = 2;
        $this->isProcessing = true;

        $service = app(FlashProductsService::class);
        $result = $service->analyze($this->excelFile, $this->reportName ?: null);

        $this->isProcessing = false;

        if ($result['success']) {
            $this->activeReportId = $result['report_id'];
            $this->step = 3;
            $this->message = $result['message'];
            $this->messageType = 'success';
        } else {
            $this->step = 1;
            $this->message = $result['message'];
            $this->messageType = 'error';
        }

        $this->reset('excelFile', 'reportName');
    }

    public function selectTariff(int $itemId, int $tariffIndex)
    {
        $item = OptimizationReportItem::find($itemId);
        if (!$item || !isset($item->scenario_details[$tariffIndex])) return;

        $selected = $item->scenario_details[$tariffIndex];
        $totalCost = $item->totalCost();
        $revenue = $selected['price'] * (1 - $selected['commission'] / 100);
        $netProfit = round($revenue - $totalCost, 2);

        $item->update([
            'selected_tariff_index' => $tariffIndex,
            'suggested_tariff' => $selected['name'],
            'suggested_price' => $selected['price'],
            'suggested_commission' => $selected['commission'],
            'suggested_net_profit' => $netProfit,
            'extra_profit' => round($netProfit - (float) $item->current_net_profit, 2),
            'action' => $netProfit > (float) $item->current_net_profit ? 'update' : ($netProfit < 0 ? 'warning' : 'keep'),
            'is_selected' => true,
        ]);

        if (!in_array($itemId, $this->selectedItems)) {
            $this->selectedItems[] = $itemId;
        }
    }

    public function updateCustomPrice(int $itemId, $newPrice)
    {
        $item = OptimizationReportItem::find($itemId);
        if (!$item) return;

        $newPrice = (float) $newPrice;
        $totalCost = $item->totalCost();
        // Flaş komisyonu kullan
        $flashScenario = $item->scenario_details[1] ?? null;
        $commission = $flashScenario ? $flashScenario['commission'] : $item->current_commission;
        $revenue = $newPrice * (1 - $commission / 100);
        $netProfit = round($revenue - $totalCost, 2);

        $item->update([
            'custom_price' => $newPrice,
            'suggested_price' => $newPrice,
            'suggested_commission' => $commission,
            'suggested_net_profit' => $netProfit,
            'extra_profit' => round($netProfit - (float) $item->current_net_profit, 2),
            'action' => $netProfit < 0 ? 'warning' : ($netProfit > $item->current_net_profit ? 'update' : 'keep'),
            'is_selected' => true,
        ]);

        if (!in_array($itemId, $this->selectedItems)) {
            $this->selectedItems[] = $itemId;
        }
    }

    public function selectAllOpportunities()
    {
        $report = $this->activeReport;
        if (!$report) return;
        $this->selectedItems = $report->items
            ->filter(fn($item) => $item->action === 'update' || $item->selected_tariff_index !== null)
            ->pluck('id')->toArray();
    }

    public function selectFilteredOpportunities()
    {
        $this->selectedItems = $this->filteredItems
            ->filter(fn ($item) => $item->action === 'update' || $item->selected_tariff_index !== null || $item->custom_price !== null)
            ->pluck('id')
            ->values()
            ->toArray();

        if (empty($this->selectedItems)) {
            $this->message = 'Görünen ürünler içinde seçilecek flaş fırsatı bulunamadı.';
            $this->messageType = 'info';
        }
    }

    public function deselectAll()
    {
        $this->selectedItems = [];
    }

    public function toggleItem(int $itemId)
    {
        if (in_array($itemId, $this->selectedItems, true)) {
            $this->selectedItems = array_values(array_diff($this->selectedItems, [$itemId]));
            return;
        }

        $this->selectedItems[] = $itemId;
    }

    public function setStatusFilter(string $filter)
    {
        if (!in_array($filter, ['all', 'opportunity', 'risk', 'selected', 'missing_cost', 'unmatched', 'kept', 'flash24', 'flash3'], true)) {
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
        if (!$this->activeReportId) return;

        $exportIds = $this->selectedItems;
        if (empty($exportIds)) {
            $report = $this->activeReport;
            if ($report) {
                $exportIds = $report->items
                    ->filter(fn($item) => $item->selected_tariff_index !== null || $item->custom_price !== null || $item->action === 'update')
                    ->pluck('id')->toArray();
            }
        }

        if (empty($exportIds)) {
            $this->message = 'Lütfen önce en az bir ürün için senaryo seçin.';
            $this->messageType = 'error';
            return;
        }

        $service = app(FlashProductsService::class);
        $filePath = $service->generateExport($this->activeReportId, $exportIds);

        if ($filePath && file_exists($filePath)) {
            $this->message = count($exportIds) . ' ürün için export dosyası hazırlandı.';
            $this->messageType = 'success';
            return response()->download($filePath, basename($filePath));
        }

        $this->message = 'Export dosyası oluşturulamadı.';
        $this->messageType = 'error';
    }

    public function viewReport(int $reportId)
    {
        $this->activeReportId = $reportId;
        $this->activeTab = 'analyze';
        $this->step = 3;
        $this->selectedItems = [];
        $this->searchQuery = '';
        $this->statusFilter = 'all';
        $this->sortField = 'extra_profit';
        $this->sortDirection = 'desc';
        $this->message = '';
    }

    public function deleteReport(int $reportId)
    {
        $report = OptimizationReport::where('user_id', auth()->id())
            ->ofType('flash')
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

    // ===============================================
    // AI FEATURES
    // ===============================================

    public function generateAIAnalysis()
    {
        $report = $this->activeReport;
        if (!$report) return;
        $this->isProcessing = true;
        $this->message = 'Flaş kampanya analizi yapılıyor...';
        try {
            $service = app(\App\Services\AIService::class);
            $analysis = $service->analyzeFlashCampaign($report);
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
        if (!$report) return;
        $this->isProcessing = true;
        try {
            $service = app(\App\Services\AIService::class);
            $analysis = $service->analyzeLosses($report);
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
                ->where('optimization_report_id', $this->activeReportId)->latest()->first();
            if (!$conversation) {
                $conversation = AIConversation::create([
                    'user_id' => auth()->id(),
                    'optimization_report_id' => $this->activeReportId,
                    'messages' => [['role' => 'system', 'content' => 'Merhaba! Flaş kampanya raporunuz hakkında sorularınızı yanıtlayabilirim.', 'timestamp' => now()->toISOString()]]
                ]);
            }
            $this->chatConversationId = $conversation->id;
        }
    }

    #[Computed]
    public function chatConversation()
    {
        if (!$this->chatConversationId) return null;
        return AIConversation::find($this->chatConversationId);
    }

    public function sendMessage()
    {
        $this->validate(['chatMessage' => 'required|string|max:1000']);

        if (!$this->chatConversationId && $this->activeReportId) {
            $this->toggleChat();
        }

        if (!$this->chatConversationId) return;
        $conversation = AIConversation::find($this->chatConversationId);
        if (!$conversation) return;
        $userMsg = $this->chatMessage;
        $this->chatMessage = '';
        $this->isChatting = true;
        $conversation->addMessage('user', $userMsg);
        try {
            $service = app(\App\Services\AIService::class);
            $response = $service->chatWithReport($conversation, $userMsg);
            $conversation->addMessage('assistant', $response);
        } catch (\Exception $e) {
            $conversation->addMessage('assistant', 'Hata: ' . $e->getMessage());
        }
        $this->isChatting = false;
    }

    public function getAiPriceSuggestion($itemId)
    {
        $item = OptimizationReportItem::find($itemId);
        if (!$item) return;
        $this->suggestedPrices[$itemId] = ['loading' => true];
        try {
            $service = app(\App\Services\AIService::class);
            $totalCost = $item->totalCost();
            $suggestion = $service->suggestPrice($item->product_name, $totalCost, $item->current_price, [
                'current_commission' => (float) $item->current_commission,
                'current_net_profit' => (float) $item->current_net_profit,
                'scenarios' => $item->scenario_details ?? [],
            ]);
            $this->suggestedPrices[$itemId] = ['price' => $suggestion['suggested_price'], 'reason' => $suggestion['reason'], 'loading' => false];
        } catch (\Exception $e) {
            $this->suggestedPrices[$itemId] = ['error' => $e->getMessage(), 'loading' => false];
        }
    }

    protected function projectedNetProfit(OptimizationReportItem $item): float
    {
        $price = (float) ($item->custom_price ?: $item->suggested_price ?: $item->current_price);
        $commission = (float) ($item->suggested_commission ?: $item->current_commission);

        if ($item->selected_tariff_index !== null && isset($item->scenario_details[$item->selected_tariff_index])) {
            $scenario = $item->scenario_details[$item->selected_tariff_index];
            $price = (float) ($item->custom_price ?: ($scenario['price'] ?? $price));
            $commission = (float) ($scenario['commission'] ?? $commission);
        }

        return round(($price * (1 - ($commission / 100))) - $item->totalCost(), 2);
    }

    protected function bestScenarioIndex(OptimizationReportItem $item): int
    {
        foreach (($item->scenario_details ?? []) as $index => $scenario) {
            if ($scenario['is_best'] ?? false) {
                return (int) $index;
            }
        }

        return 0;
    }

    public function applySuggestedPrice($itemId)
    {
        if (isset($this->suggestedPrices[$itemId]['price'])) {
            $this->updateCustomPrice($itemId, $this->suggestedPrices[$itemId]['price']);
            unset($this->suggestedPrices[$itemId]);
        }
    }

    public function clearAiSuggestion($itemId)
    {
        if (isset($this->suggestedPrices[$itemId])) {
            unset($this->suggestedPrices[$itemId]);
        }
    }

    public function render()
    {
        return view('livewire.flash-products')
            ->layout('layouts.app', ['title' => 'Flaş Ürünler']);
    }
}
