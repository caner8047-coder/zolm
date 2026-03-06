<?php

namespace App\Livewire;

use App\Models\OptimizationReport;
use App\Models\OptimizationReportItem;
use App\Services\CampaignAnalysisService;
use App\Services\TariffOptimizerService;
use App\Models\AIConversation;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Str;

class TariffOptimizer extends Component
{
    use WithFileUploads;

    // UI State
    public string $activeTab = 'analyze';   // 'analyze' | 'history'
    public int $step = 1;                   // 1=Setup, 2=Processing, 3=Results
    public string $message = '';
    public string $messageType = 'info';

    // File uploads
    public $tariffFile;
    public string $reportName = '';

    // Results
    public ?int $activeReportId = null;
    
    // Tab View (Products vs Categories)
    public string $tabView = 'products'; // products, categories
    public array $selectedItems = [];

    // Karlılık Filtresi
    public ?float $profitabilityMin = null;
    public ?float $profitabilityMax = null;
    public ?int $profitabilityTariffIndex = null; // 0=1.Tarife, 1=2.Tarife, 2=3.Tarife, 3=4.Tarife

    // Processing animation
    public bool $isProcessing = false;

    // Chat State
    public bool $showChat = false;
    public string $chatMessage = '';
    public $chatConversationId = null;
    public bool $isChatting = false;

    // AI Price Suggestions
    public array $suggestedPrices = []; // [itemId => ['price' => 100, 'reason' => '...']]



    /**
     * MpProduct'ta maliyeti tanımlı ürün sayısı
     */
    #[Computed]
    public function costCount(): int
    {
        return app(CampaignAnalysisService::class)->getProductWithCostCount(auth()->id());
    }

    /**
     * MpProduct toplam ürün sayısı
     */
    #[Computed]
    public function productCount(): int
    {
        return app(CampaignAnalysisService::class)->getProductCount(auth()->id());
    }

    /**
     * Aktif rapor (yeni analiz veya geçmişten seçilen)
     */
    #[Computed]
    public function activeReport(): ?OptimizationReport
    {
        if (!$this->activeReportId) return null;
        return OptimizationReport::with('items')->find($this->activeReportId);
    }

    /**
     * Geçmiş raporlar listesi
     */
    #[Computed]
    public function reports()
    {
        return OptimizationReport::where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->get();
    }

    #[Computed]
    public function currentConversation()
    {
        if (!$this->chatConversationId) return null;
        return AIConversation::find($this->chatConversationId);
    }

    #[Computed]
    public function categoryReport()
    {
        if (!$this->activeReport) return [];

        $items = $this->activeReport->items;
        $categories = [];

        foreach ($items as $item) {
            // Kategori tahmini: İlk kelime
            $nameParts = explode(' ', trim((string) $item->product_name));
            $category = $nameParts[0] ?: 'Diğer';
            $category = Str::title($category); // Baş harfi büyüt

            // Basit normalizasyon
            $category = str_replace(['İ', 'ş', 'ğ', 'ü', 'ö', 'ç'], ['I', 's', 'g', 'u', 'o', 'c'], $category);
            $category = Str::upper($category);

            if (!isset($categories[$category])) {
                $categories[$category] = [
                    'name' => $category,
                    'count' => 0,
                    'cost' => 0,
                    'revenue' => 0,
                    'profit' => 0,
                ];
            }

            // Fiyat ve Maliyet hesabı
            $cost = $item->production_cost + $item->shipping_cost;
            
            // Satış fiyatı: Custom > Selected Tariff > Current
            $price = $item->custom_price;
            if (!$price) {
                if ($item->selected_tariff_index !== null && isset($item->scenario_details[$item->selected_tariff_index])) {
                    $price = $item->scenario_details[$item->selected_tariff_index]['price'];
                } else {
                    $price = $item->current_price;
                }
            }

            // Net Kar (Yaklaşık: Fiyat - Maliyet - %21 Komisyon)
            $commissionRate = 21; // Default
            if (isset($item->scenario_details[0]['commission'])) {
                 $commissionRate = $item->scenario_details[0]['commission'];
            }

            $commissionAmount = $price * ($commissionRate / 100);
            $netProfit = $price - $commissionAmount - $cost;

            $categories[$category]['count']++;
            $categories[$category]['cost'] += $cost;
            $categories[$category]['revenue'] += $price;
            $categories[$category]['profit'] += $netProfit;
        }

        // Sıralama: Toplam Kara göre azalan
        usort($categories, fn($a, $b) => $b['profit'] <=> $a['profit']);

        return $categories;
    }

    // ===============================================
    // TARİFE ANALİZ
    // ===============================================

    public function analyze()
    {
        $this->validate([
            'tariffFile' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        $this->isProcessing = true;
        $this->step = 2; // Processing screen
        $this->message = '';

        $service = app(TariffOptimizerService::class);
        $result = $service->analyze(
            $this->tariffFile,
            $this->reportName ?: null
        );

        $this->isProcessing = false;

        if ($result['success']) {
            $this->activeReportId = $result['report_id'];
            $this->step = 3; // Results screen
            $this->message = $result['message'];
            $this->messageType = 'success';
        } else {
            $this->step = 1; // Back to setup
            $this->message = $result['message'];
            $this->messageType = 'error';
        }

        $this->reset('tariffFile', 'reportName');
    }

    public function generateAIAnalysis()
    {
        $report = $this->activeReport;
        if (!$report) return;

        $this->isProcessing = true;
        $this->message = 'Yapay zeka raporunuzu inceliyor...';
        
        try {
            $service = app(\App\Services\AIService::class);
            $analysis = $service->analyzeOptimizationReport($report);

            $report->update(['ai_analysis' => $analysis]);
            
            $this->message = 'Yapay zeka analizi tamamlandı.';
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
        $this->message = 'Finansal denetçi zarar analizini yapıyor...';
        
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

    // ===============================================
    // RAPOR GEÇMİŞİ
    // ===============================================

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
            $report->delete();
            $this->message = 'Rapor silindi.';
            $this->messageType = 'info';

            // Silinen rapor aktifse state'i temizle
            if ($this->activeReportId === $reportId) {
                $this->activeReportId = null;
                $this->step = 1;
            }
        }
    }

    // ===============================================
    // SEÇME & EXPORT
    // ===============================================

    public function toggleItem(int $itemId)
    {
        if (in_array($itemId, $this->selectedItems)) {
            $this->selectedItems = array_values(array_diff($this->selectedItems, [$itemId]));
        } else {
            $this->selectedItems[] = $itemId;
        }
    }

    public function selectAllOpportunities()
    {
        $report = $this->activeReport;
        if ($report) {
            // action=update VEYA tarife seçilmiş/özel fiyat girilmiş tüm ürünleri seç
            $this->selectedItems = $report->items
                ->filter(function ($item) {
                    return $item->action === 'update' 
                        || $item->selected_tariff_index !== null 
                        || $item->custom_price !== null;
                })
                ->pluck('id')
                ->toArray();

            // Hiç seçilebilecek ürün yoksa tümünü seç
            if (empty($this->selectedItems)) {
                $this->selectedItems = $report->items->pluck('id')->toArray();
            }
        }
    }

    public function deselectAll()
    {
        $this->selectedItems = [];
    }

    /**
     * Karlılık filtresi ayarla (hedef tarife + min-max yüzde aralığı)
     */
    public function setProfitabilityFilter($tariffIndex, $min, $max)
    {
        $this->profitabilityTariffIndex = (int) $tariffIndex;
        $this->profitabilityMin = (float) $min;
        $this->profitabilityMax = $max !== null ? (float) $max : 999;
    }

    public function clearProfitabilityFilter()
    {
        $this->profitabilityTariffIndex = null;
        $this->profitabilityMin = null;
        $this->profitabilityMax = null;
    }

    /**
     * Satır için tarife seç (0=Mevcut, 1=Tarife 2, 2=Tarife 3, 3=Tarife 4)
     * Seçilen tarifenin bilgileri suggested_* alanlarına kaydedilir
     */
    public function selectTariff(int $itemId, int $tariffIndex)
    {
        $item = OptimizationReportItem::find($itemId);
        if (!$item) return;

        $scenarios = $item->scenario_details;
        if (!$scenarios || !isset($scenarios[$tariffIndex])) return;

        $selected = $scenarios[$tariffIndex];
        $totalCost = (float) $item->production_cost + (float) $item->shipping_cost;

        // Net kâr: (Fiyat × (1 - Komisyon%/100)) - Toplam Maliyet
        $revenue = $selected['price'] * (1 - $selected['commission'] / 100);
        $netProfit = round($revenue - $totalCost, 2);

        $item->update([
            'selected_tariff_index' => $tariffIndex,
            'suggested_tariff'      => $selected['name'] ?? "Tarife " . ($tariffIndex + 1),
            'suggested_price'       => $selected['price'],
            'suggested_commission'  => $selected['commission'],
            'suggested_net_profit'  => $netProfit,
            'extra_profit'          => round($netProfit - (float) $item->current_net_profit, 2),
            'action'                => $netProfit > (float) $item->current_net_profit ? 'update' : ($netProfit < 0 ? 'warning' : 'keep'),
            'is_selected'           => true,
        ]);

        // Seçilen ürünleri otomatik olarak selectedItems'a ekle
        if (!in_array($itemId, $this->selectedItems)) {
            $this->selectedItems[] = $itemId;
        }
    }

    /**
     * Satır için özel fiyat gir — fiyat hangi tarife aralığına düşüyorsa otomatik seç
     */
    public function updateCustomPrice(int $itemId, $newPrice)
    {
        $item = OptimizationReportItem::find($itemId);
        if (!$item) return;

        $newPrice = (float) $newPrice;
        if ($newPrice <= 0) return;

        $scenarios = $item->scenario_details;
        $totalCost = (float) $item->production_cost + (float) $item->shipping_cost;

        // Fiyatın hangi tarife aralığına düştüğünü bul
        // Tarife fiyatları büyükten küçüğe sıralıdır: Tarife 1 > 2 > 3 > 4
        // Girilen fiyat >= tarifenin fiyatı ise o tarifeye girer
        $matchedIndex = 0; // varsayılan: mevcut tarife
        $commission = (float) $item->current_commission;

        if ($scenarios && is_array($scenarios)) {
            for ($i = count($scenarios) - 1; $i >= 0; $i--) {
                if (isset($scenarios[$i]) && $newPrice <= (float) $scenarios[$i]['price']) {
                    $matchedIndex = $i;
                    $commission = (float) $scenarios[$i]['commission'];
                    break;
                }
            }
            // Fiyat en yüksek tarifeden de büyükse → 1. tarife (index 0)
            if ($newPrice > (float) ($scenarios[0]['price'] ?? 0)) {
                $matchedIndex = 0;
                $commission = (float) ($scenarios[0]['commission'] ?? $item->current_commission);
            }
        }

        $revenue = $newPrice * (1 - $commission / 100);
        $netProfit = round($revenue - $totalCost, 2);

        $item->update([
            'custom_price'          => $newPrice,
            'suggested_price'       => $newPrice,
            'suggested_commission'  => $commission,
            'suggested_tariff'      => $matchedIndex === 0 ? 'Mevcut' : ($matchedIndex + 1) . '. Tarife',
            'selected_tariff_index' => $matchedIndex,
            'suggested_net_profit'  => $netProfit,
            'extra_profit'          => round($netProfit - (float) $item->current_net_profit, 2),
            'action'                => $netProfit > (float) $item->current_net_profit ? 'update' : ($netProfit < 0 ? 'warning' : 'keep'),
            'is_selected'           => true,
        ]);

        // selectedItems'a ekle
        if (!in_array($itemId, $this->selectedItems)) {
            $this->selectedItems[] = $itemId;
        }
    }

    public function exportSelected()
    {
        if (!$this->activeReportId) return;

        // Hiç ürün seçilmemişse, tarife seçilmiş/düzenlenmiş tüm ürünleri otomatik dahil et
        $exportIds = $this->selectedItems;
        if (empty($exportIds)) {
            $report = $this->activeReport;
            if ($report) {
                $exportIds = $report->items
                    ->filter(function ($item) {
                        return $item->selected_tariff_index !== null 
                            || $item->custom_price !== null
                            || $item->action === 'update';
                    })
                    ->pluck('id')
                    ->toArray();
            }
        }

        if (empty($exportIds)) {
            $this->message = 'Lütfen önce en az bir ürün için tarife seçin veya fiyat girin.';
            $this->messageType = 'error';
            return;
        }

        $service = app(TariffOptimizerService::class);
        $filePath = $service->generateExport(
            $this->activeReportId,
            $exportIds
        );

        if ($filePath && file_exists($filePath)) {
            $this->message = count($exportIds) . ' ürün için export dosyası hazırlandı.';
            $this->messageType = 'success';

            return response()->download($filePath, basename($filePath), [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        }

        $this->message = 'Export dosyası oluşturulamadı. Lütfen tekrar deneyin.';
        $this->messageType = 'error';
    }

    // ===============================================
    // RESET & NAVIGATION
    // ===============================================

    public function resetAnalysis()
    {
        $this->step = 1;
        $this->activeReportId = null;
        $this->selectedItems = [];
        $this->message = '';
        $this->reset('tariffFile', 'reportName');
    }

    public function switchTab(string $tab)
    {
        $this->activeTab = $tab;
        $this->message = '';
    }

    // ===============================================
    // CHAT ACTIONS
    // ===============================================

    public function toggleChat()
    {
        $this->showChat = !$this->showChat;
        if ($this->showChat && !$this->chatConversationId && $this->activeReportId) {
            // Mevcut rapor için konuşma bul veya oluştur
            $reportId = $this->activeReportId;
            $conversation = AIConversation::where('user_id', auth()->id())
                ->where('optimization_report_id', $reportId)
                ->latest()
                ->first();
                
            if (!$conversation) {
                $conversation = AIConversation::create([
                    'user_id' => auth()->id(),
                    'optimization_report_id' => $reportId,
                    'messages' => [
                        [
                            'role' => 'system', 
                            'content' => 'Merhaba! Ben Kâr Motoru asistanıyım. Bu raporla ilgili bana soru sorabilirsiniz.',
                            'timestamp' => now()->toISOString()
                        ]
                    ]
                ]);
            }
            
            $this->chatConversationId = $conversation->id;
        }
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

        // Kullanıcı mesajını kaydet
        $conversation->addMessage('user', $userMsg);

        try {
            $service = app(\App\Services\AIService::class);
            $response = $service->chatWithReport($conversation, $userMsg);
            
            // AI yanıtını kaydet
            $conversation->addMessage('assistant', $response);
            
        } catch (\Exception $e) {
            $conversation->addMessage('assistant', 'Üzgünüm, bir hata oluştu: ' . $e->getMessage());
        }
        
        $this->isChatting = false;
    }



    // ===============================================
    // AI PRICE PREDICTION
    // ===============================================

    public function getAiPriceSuggestion($itemId)
    {
        $item = OptimizationReportItem::find($itemId);
        if (!$item) return;

        // Loading state için
        $this->suggestedPrices[$itemId] = ['loading' => true];

        try {
            $service = app(\App\Services\AIService::class);
            $totalCost = $item->production_cost + $item->shipping_cost;
            
            $suggestion = $service->suggestPrice($item->product_name, $totalCost, $item->current_price);
            
            $this->suggestedPrices[$itemId] = [
                'price' => $suggestion['suggested_price'],
                'reason' => $suggestion['reason'],
                'loading' => false
            ];
        } catch (\Exception $e) {
            $this->suggestedPrices[$itemId] = [
                'error' => 'Hata: ' . $e->getMessage(),
                'loading' => false
            ];
        }
    }

    public function applySuggestedPrice($itemId)
    {
        if (isset($this->suggestedPrices[$itemId]['price'])) {
            $this->updateCustomPrice($itemId, $this->suggestedPrices[$itemId]['price']);
            unset($this->suggestedPrices[$itemId]); // Öneri uygulandıktan sonra temizle
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
        return view('livewire.tariff-optimizer')
            ->layout('layouts.app');
    }
}
