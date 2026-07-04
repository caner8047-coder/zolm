<?php

namespace App\Console\Commands;

use App\Services\WhatsApp\RetentionCleanupService;
use Illuminate\Console\Command;

class WhatsAppRetentionCleanupExtendedCommand extends Command
{
    protected $signature = 'whatsapp:retention-cleanup-extended {--store=} {--dry-run}';

    protected $description = 'Genişletilmiş retention cleanup — AI, campaign, support verilerini temizler.';

    public function handle(RetentionCleanupService $service): int
    {
        $storeId = $this->option('store') ? (int) $this->option('store') : null;
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->info('DRY RUN — Silme yapılmayacak.');
        }

        $results = $service->run($storeId);

        $totalDeleted = array_sum($results);

        foreach ($results as $table => $count) {
            if ($count > 0) {
                $this->line("  {$table}: {$count} kayıt");
            }
        }

        $this->info("Toplam: {$totalDeleted} kayıt işlendi.");

        return self::SUCCESS;
    }
}
