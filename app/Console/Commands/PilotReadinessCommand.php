<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Support\CustomerCarePilotReadinessService;
use App\Models\MarketplaceStore;
use App\Services\Support\TenantContext;

class PilotReadinessCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'customer-care:pilot-readiness {--store= : Kontrol edilecek mağaza (store_id) ID\'si}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Müşteri İletişim Merkezi pilot hazırlık (readiness) durumunu raporlar';

    /**
     * Execute the console command.
     */
    public function handle(CustomerCarePilotReadinessService $readinessService): int
    {
        $storeId = $this->option('store');

        if (!$storeId) {
            $this->error('Lütfen kontrol etmek için bir mağaza ID\'si belirtin: --store=ID');
            return 1;
        }

        $store = MarketplaceStore::find($storeId);
        if (!$store) {
            $this->error("Belirtilen ID ({$storeId}) ile eşleşen bir mağaza bulunamadı.");
            return 1;
        }

        $this->info("=== {$store->store_name} ({$store->marketplace}) Pilot Readiness Analizi ===");

        try {
            $actor = TenantContext::getSystemActor();
            $res = $readinessService->checkReadiness((int) $storeId, $actor);
        } catch (\Throwable $exception) {
            $this->error('Pilot readiness çalıştırılamadı: ' . $exception->getMessage());

            return 1;
        }

        $headers = ['Parametre', 'Detay', 'Durum'];
        $rows = [];

        foreach ($res['checks'] as $key => $check) {
            $rows[] = [
                $check['label'] . " [{$key}]",
                $check['detail'],
                strtoupper($check['status'])
            ];
        }

        $this->table($headers, $rows);

        if ($res['ready']) {
            $this->info("\n✅ MAĞAZA CANLI AI PİLOTUNA HAZIRDIR.");
        } else {
            $this->error("\n❌ MAĞAZA AI PİLOTUNA HAZIR DEĞİLDİR. Eksik parametreleri yukarıdaki listeden giderin.");
        }

        if (!empty($res['latest_errors'])) {
            $this->warn("\nSon outbox gönderim hataları:");
            foreach ($res['latest_errors'] as $error) {
                $this->line("- {$error}");
            }
        }

        return $res['ready'] ? 0 : 2;
    }
}
