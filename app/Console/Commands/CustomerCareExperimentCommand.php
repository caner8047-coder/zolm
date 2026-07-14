<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Support\CustomerCareExperimentService;
use App\Services\Support\TenantContext;

class CustomerCareExperimentCommand extends Command
{
    protected $signature = 'customer-care:run-experiment
        {--store= : Store ID}
        {--experiment= : Deney ID}
        {--dry-run : Simülasyon yapar ama sonuç kaydetmez (varsayılan)}
        {--execute : Sonuçlar kaydedilir}';

    protected $description = 'AI prompt/policy deneyinin ön kontrolünü veya bağlı ölçüm kanıtı aktarımını çalıştırır';

    public function handle(): int
    {
        $storeId      = $this->option('store');
        $experimentId = $this->option('experiment');
        $execute      = $this->option('execute');
        $dryRun       = !$execute;

        if (!$storeId || !$experimentId) {
            $this->error('--store ve --experiment parametreleri zorunludur.');
            return 1;
        }

        if ($dryRun) {
            $this->info('[DRY-RUN] Simülasyon yapılacak ama veritabanına yazılmayacak.');
        }

        $systemUser = TenantContext::getSystemActor();
        $service    = app(CustomerCareExperimentService::class);

        try {
            $results = $service->runExperiment((int) $storeId, (int) $experimentId, $systemUser, $dryRun);
            foreach ($results as $result) {
                $this->info("Varyant [{$result['variant']}]: " . ($result['dry_run'] ?? false ? 'DRY-RUN tamamlandı.' : "Run ID={$result['run_id']} tamamlandı."));
            }
        } catch (\Throwable $e) {
            $this->error('HATA: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
