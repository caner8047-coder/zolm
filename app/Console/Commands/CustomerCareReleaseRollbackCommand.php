<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SupportReleasePackage;
use App\Services\Support\CustomerCareReleaseService;
use App\Services\Support\TenantContext;

class CustomerCareReleaseRollbackCommand extends Command
{
    protected $signature = 'customer-care:release-rollback {--store= : Store ID} {--package= : Package ID} {--execute : Persist rollback instead of dry-run}';
    protected $description = 'Yayınlanan paketi geri çekerek eski aktif sürümlere döner.';

    public function handle()
    {
        $storeId = $this->option('store');
        $packageId = $this->option('package');
        $execute = $this->option('execute');

        if (!$storeId || !$packageId) {
            $this->error("Mağaza ID ve Paket ID belirtilmelidir.");
            return 1;
        }

        $package = SupportReleasePackage::where('store_id', $storeId)->find($packageId);
        if (!$package) {
            $this->error("Paket bulunamadı.");
            return 1;
        }

        $this->info("Release rollback işlemi başlatılıyor... Store ID: {$storeId}, Paket ID: #{$packageId}, Mod: " . ($execute ? 'Uygula' : 'Dry-Run'));

        if ($execute) {
            try {
                $systemActor = TenantContext::getSystemActor();
            } catch (\Exception $e) {
                $this->error('Sistem aktörü (System Actor) bulunamadı. İşlem iptal edildi (Fail-Closed).');
                return 1;
            }

            $rbac = app(\App\Services\Support\Security\SupportRbacService::class);
            try {
                $rbac->enforcePermission($systemActor, $storeId, 'force_circuit_breaker');
            } catch (\Exception $e) {
                $this->error('Yetkilendirme hatası: ' . $e->getMessage());
                return 1;
            }

            $service = app(CustomerCareReleaseService::class);
            try {
                $service->rollbackPackage($package, $systemActor);
                $this->info("Geri alma (Rollback) başarıyla tamamlandı.");
            } catch (\Exception $e) {
                $this->error("Geri alma hatası: " . $e->getMessage());
                return 1;
            }
        } else {
            $this->info("[DRY-RUN] Paket #{$packageId} durumu 'rolled_back' olarak güncellenecekti ve talimatlar önceki versiyonlara çekilecekti.");
        }

        return 0;
    }
}
