<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SupportReleasePackage;
use App\Services\Support\CustomerCareReleaseService;

class CustomerCareReleasePreflightCommand extends Command
{
    protected $signature = 'customer-care:release-preflight {--store= : Store ID} {--package= : Package ID}';
    protected $description = 'Yayın paketi için prompt injection ve PII sızıntısı taramaları (preflight) yapar.';

    public function handle()
    {
        $storeId = $this->option('store');
        $packageId = $this->option('package');

        if (!$storeId || !$packageId) {
            $this->error("Mağaza ID ve Paket ID belirtilmelidir.");
            return 1;
        }

        $package = SupportReleasePackage::where('store_id', $storeId)->find($packageId);
        if (!$package) {
            $this->error("Paket bulunamadı.");
            return 1;
        }

        $this->info("Release preflight kontrolü başlatılıyor... Store ID: {$storeId}, Paket ID: #{$packageId}");

        $service = app(CustomerCareReleaseService::class);
        $result = $service->preflightCheck($package);

        foreach ($result['checks'] as $key => $check) {
            $statusStr = strtoupper($check['status']);
            if ($check['status'] === 'passed') {
                $this->info("[PASSED] {$check['label']}: {$check['detail']}");
            } else {
                $this->error("[FAILED] {$check['label']}: {$check['detail']}");
            }
        }

        if ($result['allowed']) {
            $this->info("SONUÇ: Paket doğrulandı ve inceleme aşamasına alınmaya uygun.");
            $package->update(['status' => 'review']);
        } else {
            $this->warn("SONUÇ: Paket güvenlik kurallarını ihlal ediyor! Yayın engellendi.");
            $package->update(['status' => 'rejected']);
            return 1;
        }

        return 0;
    }
}
