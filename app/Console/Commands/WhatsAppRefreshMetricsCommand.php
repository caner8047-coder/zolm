<?php

namespace App\Console\Commands;

use App\Services\WhatsApp\AnalyticsService;
use Illuminate\Console\Command;

class WhatsAppRefreshMetricsCommand extends Command
{
    protected $signature = 'whatsapp:refresh-metrics {--store=}';

    protected $description = 'Günlük WhatsApp metriklerini hesaplar ve kaydeder.';

    public function handle(AnalyticsService $service): int
    {
        $storeId = $this->option('store') ? (int) $this->option('store') : null;

        $service->calculateDailyMetrics($storeId);

        $this->info('Günlük metrikler hesaplandı.');

        return self::SUCCESS;
    }
}
