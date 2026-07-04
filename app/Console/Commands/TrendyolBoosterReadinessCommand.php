<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Marketplace\TrendyolBoosterReadinessService;
use Illuminate\Console\Command;

class TrendyolBoosterReadinessCommand extends Command
{
    protected $signature = 'marketplace:trendyol-booster-readiness
        {--user= : Belirtilen kullanıcı için scheduler kapsamını doğrula}
        {--json : JSON çıktı üret}';

    protected $description = 'Trendyol Booster canlıya geçiş hazırlığını veri yazmadan denetler.';

    public function handle(TrendyolBoosterReadinessService $service): int
    {
        $userId = $this->positiveIntegerOption('user');

        if ($this->option('user') !== null && $userId === null) {
            $this->components->error('--user pozitif bir tam sayı olmalıdır.');

            return self::FAILURE;
        }

        if ($userId !== null && ! User::query()->whereKey($userId)->exists()) {
            $this->components->error('Kullanıcı bulunamadı: #'.$userId);

            return self::FAILURE;
        }

        $report = $service->audit($userId);

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return $report['ready'] ? self::SUCCESS : self::FAILURE;
        }

        $this->line($report['label']);
        $this->line(sprintf(
            'Kontrol: %d | Geçti: %d | Uyarı: %d | Engel: %d',
            $report['summary']['check_count'],
            $report['summary']['pass_count'],
            $report['summary']['warning_count'],
            $report['summary']['blocking_count'],
        ));
        $this->newLine();
        $this->table(['Grup', 'Kontrol', 'Durum', 'Detay', 'Aksiyon'], array_map(
            fn (array $check): array => [
                $check['group'],
                $check['label'],
                match ($check['status']) {
                    'pass' => 'Geçti',
                    'warning' => 'Uyarı',
                    default => 'Engel',
                },
                $check['detail'],
                $check['status'] === 'pass' ? '-' : $check['action'],
            ],
            $report['checks'],
        ));

        return $report['ready'] ? self::SUCCESS : self::FAILURE;
    }

    protected function positiveIntegerOption(string $name): ?int
    {
        $value = $this->option($name);

        if ($value === null || $value === '') {
            return null;
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        return $integer === false ? null : (int) $integer;
    }
}
