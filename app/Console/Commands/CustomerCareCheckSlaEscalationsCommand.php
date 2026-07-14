<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MarketplaceStore;
use App\Services\Support\CustomerCareRoutingService;

class CustomerCareCheckSlaEscalationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'customer-care:run-sla-escalations {--store= : Hedef mağaza ID\'si}';

    /**
     * The console command description.
     */
    protected $description = 'Müşteri İletişim Merkezi SLA ihlallerini tarar ve geciken konuşmaları eskale eder.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $storeId = $this->option('store');

        if (!$storeId) {
            $this->error('Lütfen mağaza ID\'sini belirtin: --store=ID');
            return 1;
        }

        $store = MarketplaceStore::find($storeId);
        if (!$store) {
            $this->error("ID'si {$storeId} olan mağaza bulunamadı.");
            return 1;
        }

        $routingService = app(CustomerCareRoutingService::class);
        $escalatedCount = $routingService->checkSlaEscalations((int)$storeId);

        if ($escalatedCount > 0) {
            $this->warn("⚠️  {$escalatedCount} adet konuşma gecikme nedeniyle eskale edildi.");
        } else {
            $this->info("✨ Denetim tamamlandı. Herhangi bir SLA gecikmesi saptanmadı.");
        }

        return 0;
    }
}
