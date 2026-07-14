<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SupportReconciliationFinding;
use App\Services\Support\CustomerCareReconciliationService;
use App\Services\Support\TenantContext;

class CustomerCareRepairProjectionCommand extends Command
{
    protected $signature = 'customer-care:repair-projection {--finding= : Finding ID} {--execute : Persist changes instead of dry-run}';
    protected $description = 'Tespit edilen bir projeksiyon tutarsızlığını onarır.';

    public function handle()
    {
        $findingId = $this->option('finding');
        $execute = $this->option('execute');

        if (!$findingId) {
            $this->error("Bulgu ID belirtilmelidir.");
            return 1;
        }

        $finding = SupportReconciliationFinding::find($findingId);
        if (!$finding) {
            $this->error("Bulgu bulunamadı.");
            return 1;
        }

        $storeId = $finding->store_id;

        $this->info("Projeksiyon onarım işlemi başlatılıyor... Bulgu ID: {$findingId}, Tip: {$finding->finding_type}, Mod: " . ($execute ? 'Uygula' : 'Dry-Run'));

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

            $service = app(CustomerCareReconciliationService::class);
            try {
                $service->repairFinding($finding, $systemActor, true);
                $this->info("Bulgu başarıyla onarıldı.");
            } catch (\App\Exceptions\ApprovalRequiredException $e) {
                $this->error($e->getMessage() . " Lütfen Governance ekranından onaylayın.");
                return 1;
            } catch (\Exception $e) {
                $this->error("Onarım sırasında hata: " . $e->getMessage());
                return 1;
            }
        } else {
            $this->info("[DRY-RUN] Bulgu #{$findingId} durumu 'repaired' olarak işaretlenecekti ve projeksiyon düzeltilecekti.");
        }

        return 0;
    }
}
