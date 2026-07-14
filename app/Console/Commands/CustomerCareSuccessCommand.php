<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MarketplaceStore;
use App\Services\Support\CustomerCareSuccessService;
use App\Services\Support\TenantContext;

class CustomerCareSuccessCommand extends Command
{
    protected $signature = 'customer-care:success-snapshot
        {--store= : Store ID}
        {--all-accessible : Erişilebilir tüm mağazalar için çalıştır}
        {--dry-run : Hesaplama yapar ama veritabanına yazmaz (varsayılan)}
        {--execute : Snapshot veritabanına yazılır}';

    protected $description = 'Lansman öncesi/sonrası başarı analizi ve checklist snapshot kontrolleri yapar';

    public function handle(): int
    {
        $storeId  = $this->option('store');
        $execute  = $this->option('execute');
        $dryRun   = !$execute;

        if ($dryRun) {
            $this->info('[DRY-RUN] Snapshot hesaplanır ancak veritabanına yazılmaz.');
        }

        if ($storeId) {
            return $this->processStore((int) $storeId, $dryRun);
        }

        if ($this->option('all-accessible')) {
            $this->info('Erişilebilir tüm mağazalar taranıyor...');
            $stores = MarketplaceStore::where('is_active', true)->get();
            foreach ($stores as $store) {
                $this->processStore($store->id, $dryRun);
            }
            return 0;
        }

        $this->error('--store=ID veya --all-accessible seçeneğinden biri gereklidir.');
        return 1;
    }

    private function processStore(int $storeId, bool $dryRun): int
    {
        $systemUser = TenantContext::getSystemActor();
        $service = app(CustomerCareSuccessService::class);

        try {
            if ($dryRun) {
                // Dry-run: snapshot hesaplanır ama persist edilmez
                $data  = $service->calculateSnapshotData($storeId, $systemUser);
                $label = $data['health_label'] ?? 'unknown';
                $score = $data['health_score'] ?? '?';
                $this->info("[Store {$storeId}] Snapshot hesaplandı (dry-run, kaydedilmedi) — Skor: {$score}, Durum: {$label}");
            } else {
                $snapshot = $service->computeSnapshot($storeId, $systemUser);
                $label = $snapshot->health_label ?? 'unknown';
                $score = $snapshot->health_score ?? '?';
                $this->info("[Store {$storeId}] Snapshot oluşturuldu — Skor: {$score}, Durum: {$label}");
            }
        } catch (\Throwable $e) {
            $this->error("[Store {$storeId}] HATA: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
