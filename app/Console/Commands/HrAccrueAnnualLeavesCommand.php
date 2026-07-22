<?php

namespace App\Console\Commands;

use App\Models\LegalEntity;
use App\Modules\Hr\Leave\Services\HrAnnualLeaveAccrualService;
use Illuminate\Console\Command;

class HrAccrueAnnualLeavesCommand extends Command
{
    protected $signature = 'hr:accrue-annual-leaves {--legal-entity-id= : Target Legal Entity ID}';

    protected $description = '4857 SK m.53 uyarınca tüm aktif çalışanların kıdeme göre yıllık izin hak ediş bakiyelerini hesaplar ve günceller';

    public function handle(HrAnnualLeaveAccrualService $service): int
    {
        $tenantId = $this->option('legal-entity-id');

        if (! $tenantId) {
            $entities = LegalEntity::where('is_active', true)->get();
        } else {
            $entities = LegalEntity::where('id', (int) $tenantId)->get();
        }

        foreach ($entities as $entity) {
            $this->info("Kıdem izin hak edişleri hesaplanıyor: {$entity->name} (ID: {$entity->id})");
            $res = $service->accrueAllEmployees($entity->id);
            $this->info("✓ {$res['processed']} çalışan tarandı, {$res['updated']} çalışanın yıllık izin bakiyesi 4857 SK m.53 uyarınca güncellendi.");
        }

        return Command::SUCCESS;
    }
}
