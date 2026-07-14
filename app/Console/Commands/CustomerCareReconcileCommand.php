<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MarketplaceStore;
use App\Services\Support\CustomerCareReconciliationService;

class CustomerCareReconcileCommand extends Command
{
    protected $signature = 'customer-care:reconcile-projections {--store= : Store ID} {--all : Tüm aktif mağazalar} {--channel= : Channel Key} {--execute : Persist analysis run}';
    protected $description = 'Kanal veri projeksiyonlarını tarar ve drift tespit olaylarını kaydeder.';

    public function handle()
    {
        $storeId = $this->option('store');
        $allStores = (bool) $this->option('all');
        $execute = $this->option('execute');

        if ((!$storeId && !$allStores) || ($storeId && $allStores)) {
            $this->error("Tam olarak bir hedef belirtin: --store=ID veya --all.");
            return 1;
        }

        $storeIds = $allStores
            ? MarketplaceStore::where('is_active', true)->pluck('id')
            : collect([(int) $storeId]);

        if ($storeIds->isEmpty()) {
            $this->warn('Reconciliation için aktif mağaza bulunamadı.');
            return 0;
        }

        $this->info("Reconciliation analizi başlatılıyor... Mağaza sayısı: {$storeIds->count()}, Mod: " . ($execute ? 'Uygula' : 'Dry-Run'));

        $failed = 0;
        foreach ($storeIds as $targetStoreId) {
            if (!$execute) {
                $this->info("[DRY-RUN] Store #{$targetStoreId} projeksiyonları taranacaktı.");
                continue;
            }

            try {
                $run = app(CustomerCareReconciliationService::class)->runReconciliation((int) $targetStoreId);
                $this->info("Store #{$targetStoreId}: Rapor #{$run->id}, bulgu " . ($run->summary_json['findings_count'] ?? 0));
            } catch (\Throwable $e) {
                $failed++;
                $this->error("Store #{$targetStoreId}: " . $e->getMessage());
            }
        }

        return $failed === 0 ? 0 : 1;
    }
}
