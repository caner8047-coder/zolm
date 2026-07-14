<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Support\CustomerCareSuggestionService;
use App\Models\MarketplaceStore;
use App\Services\Support\TenantContext;

class GenerateKnowledgeSuggestionsCommand extends Command
{
    protected $signature = 'customer-care:generate-knowledge-suggestions {--store= : Belirli bir mağaza ID\'si}';
    protected $description = 'AI destekli bilgi bankası önerilerini konuşma geçmişlerinden analiz eder ve kaydeder.';

    public function handle(): int
    {
        if (!config('customer-care.enabled', false) || !config('customer-care.knowledge_enabled', false)) {
            $this->error('Müşteri iletişim merkezi veya bilgi önerileri özelliği devre dışı.');
            return self::FAILURE;
        }

        $storeId = $this->option('store');

        $service = app(CustomerCareSuggestionService::class);
        try {
            $systemActor = TenantContext::getSystemActor();
            $stores = $storeId
                ? MarketplaceStore::whereKey((int) $storeId)->get()
                : MarketplaceStore::where('is_active', true)->get();
            if ($stores->isEmpty()) {
                $this->error('Analiz edilecek mağaza bulunamadı.');
                return 1;
            }
            $count = 0;
            foreach ($stores as $store) {
                $this->info("Mağaza {$store->store_name} için öneri analizi başlatılıyor...");
                $count += $service->generateSuggestions((int) $store->id, $systemActor);
            }
            $this->info("Tamamlandı! Toplam {$count} adet yeni bilgi makalesi önerisi üretildi.");
            return 0;
        } catch (\Throwable $e) {
            $this->error("Öneri Analizi Başarısız: " . $e->getMessage());
            return 1;
        }
    }
}
