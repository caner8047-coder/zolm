<?php

namespace App\Console\Commands;

use App\Services\Marketplace\TrendyolBoosterObservabilityService;
use Illuminate\Console\Command;

class TrendyolBoosterHealthCommand extends Command
{
    protected $signature = 'marketplace:trendyol-booster-health {--user=} {--minutes=60} {--json}';

    protected $description = 'Trendyol Booster companion sağlık metriklerini raporlar';

    public function handle(TrendyolBoosterObservabilityService $service): int
    {
        $userId = $this->option('user') ? (int) $this->option('user') : null;
        $health = $service->dashboard($userId, (int) $this->option('minutes'));

        if ($this->option('json')) {
            $this->line(json_encode($health, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->info((string) $health['label']);
            $this->table(['Halka', 'İstek', 'Hata', 'Hata %', 'P95 ms'], [[
                strtoupper((string) $health['release_ring']),
                $health['request_count'],
                $health['error_count'],
                number_format((float) $health['error_rate'], 2, ',', '.'),
                $health['p95_duration_ms'],
            ]]);
        }

        return ($health['available'] ?? false) && ($health['healthy'] ?? false) ? self::SUCCESS : self::FAILURE;
    }
}
