<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Support\CustomerCareLaunchService;
use App\Services\Support\TenantContext;

class CustomerCareLaunchCheckCommand extends Command
{
    protected $signature = 'customer-care:launch-check {--store= : Store ID}';
    protected $description = 'Lansman öncesi pilot hazırlık analizi ve checklist kontrolleri yapar.';

    public function handle()
    {
        $storeId = $this->option('store');
        if (!$storeId) {
            $this->error("Mağaza ID belirtilmelidir.");
            return 1;
        }

        $this->info("Launch check başlatılıyor... Store ID: {$storeId}, Mod: Salt Okunur");

        try {
            $actor = TenantContext::getSystemActor();
            $checklist = app(CustomerCareLaunchService::class)->checkChecklist((int) $storeId, $actor);
        } catch (\Throwable $exception) {
            $this->error('Launch check çalıştırılamadı: ' . $exception->getMessage());

            return 1;
        }

        foreach ($checklist['checks'] as $key => $check) {
            $statusStr = strtoupper($check['status']);
            if ($check['status'] === 'passed') {
                $this->info("[PASSED] {$check['label']}: {$check['detail']}");
            } else {
                $this->error("[FAILED] {$check['label']}: {$check['detail']}");
            }
        }

        if ($checklist['allowed']) {
            $this->info("SONUÇ: Mağaza lansman açılışına uygundur.");
        } else {
            $this->warn("SONUÇ: Mağaza lansman açılışına uygun DEĞİLDİR (Fail-Closed).");
        }

        return $checklist['allowed'] ? 0 : 2;
    }
}
