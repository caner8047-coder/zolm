<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SupportLaunchPlan;
use App\Services\Support\CustomerCareLaunchService;
use App\Services\Support\TenantContext;

class CustomerCareLaunchRollbackCommand extends Command
{
    protected $signature = 'customer-care:launch-rollback {--store= : Store ID} {--plan= : Plan ID} {--execute : Execute rollback instead of dry-run}';
    protected $description = 'Acil durumda lansman planını geri çeker ve otomatik modları devre dışı bırakır.';

    public function handle()
    {
        $storeId = $this->option('store');
        $planId = $this->option('plan');
        $execute = $this->option('execute');

        if (!$storeId || !$planId) {
            $this->error("Mağaza ID ve Plan ID belirtilmelidir.");
            return 1;
        }

        $plan = SupportLaunchPlan::where('store_id', $storeId)->find($planId);
        if (!$plan) {
            $this->error("Lansman planı bulunamadı.");
            return 1;
        }

        $this->info("Rollback işlemi başlatılıyor... Store ID: {$storeId}, Plan: #{$planId}, Mod: " . ($execute ? 'Uygula' : 'Dry-Run (Veriler değiştirilmeyecek)'));

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

            $service = app(CustomerCareLaunchService::class);
            $service->rollback($plan, $systemActor);
            $this->info("Rollback başarıyla tamamlandı. Otomatik modlar kapatıldı ve pending AI kuyrukları iptal edildi.");
        } else {
            $this->info("[DRY-RUN] Lansman planı #{$planId} durumu 'rolled_back' olarak işaretlenecekti.");
            $this->info("[DRY-RUN] Mağaza AI modları manual/copilot moduna çekilecekti.");
            $this->info("[DRY-RUN] Pending AI gönderimleri iptal edilecekti.");
        }

        return 0;
    }
}
