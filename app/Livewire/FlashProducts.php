<?php

namespace App\Livewire;

use App\Models\AIConversation;
use App\Models\OptimizationReport;
use App\Models\OptimizationReportItem;
use App\Services\CampaignAnalysisService;
use App\Services\FlashProductsService;
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
        return OptimizationReport::with('items')->find($this->activeReportId);
    }

    #[Computed]
    public function reports()
    {
        return OptimizationReport::where('user_id', auth()->id())
            ->ofType('flash')
            ->orderByDesc('created_at')
            ->get();
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

    public function deselectAll()
    {
        $this->selectedItems = [];
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
        $this->message = '';
    }

    public function deleteReport(int $reportId)
    {
        $report = OptimizationReport::where('user_id', auth()->id())->find($reportId);
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
        $this->reset(['activeReportId', 'selectedItems', 'message', 'messageType']);
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
            $suggestion = $service->suggestPrice($item->product_name, $totalCost, $item->current_price);
            $this->suggestedPrices[$itemId] = ['price' => $suggestion['suggested_price'], 'reason' => $suggestion['reason'], 'loading' => false];
        } catch (\Exception $e) {
            $this->suggestedPrices[$itemId] = ['error' => $e->getMessage(), 'loading' => false];
        }
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
