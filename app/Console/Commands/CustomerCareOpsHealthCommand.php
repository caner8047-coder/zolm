<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MarketplaceStore;
use App\Services\Support\CustomerCareAiProviderHealthService;

class CustomerCareOpsHealthCommand extends Command
{
    protected $signature = 'customer-care:ops-health {--store= : Store ID}';

    protected $description = 'AI Provider ve bütçe limit durumunu kontrol eder';

    public function handle(CustomerCareAiProviderHealthService $healthService): int
    {
        $storeId = $this->option('store');

        if (!$storeId) {
            $this->error('Lütfen mağaza ID belirtin: --store=ID');
            return 1;
        }

        $store = MarketplaceStore::find($storeId);
        if (!$store) {
            $this->error("Belirtilen ID ({$storeId}) ile eşleşen bir mağaza bulunamadı.");
            return 1;
        }

        $this->info("=== AI Operasyon Sağlık Durumu: {$store->store_name} ===");

        $geminiHealthy = $healthService->isProviderHealthy('Gemini') ? '🟢 AKTİF' : '🔴 PASİF (API ANAHTARI EKSİK)';
        $groqHealthy = $healthService->isProviderHealthy('Groq') ? '🟢 AKTİF' : '⚪ YAPILANDIRILMAMIŞ';

        $this->line("Gemini Provider: {$geminiHealthy}");
        $this->line("Groq Provider: {$groqHealthy}");

        $exceeded = $healthService->hasExceededBudget($storeId);
        if ($exceeded) {
            $this->error("Limit Durumu: BÜTÇE LİMİTİ AŞILDI (Auto-reply engellendi)");
        } else {
            $this->info("Limit Durumu: Bütçe sınırları dahilinde (Normal)");
        }

        return 0;
    }
}
