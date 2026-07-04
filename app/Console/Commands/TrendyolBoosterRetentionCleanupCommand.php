<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Marketplace\TrendyolBoosterRetentionCleanupService;
use App\Services\Marketplace\TrendyolBoosterRetentionReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use LogicException;

class TrendyolBoosterRetentionCleanupCommand extends Command
{
    protected $signature = 'marketplace:trendyol-booster-retention-cleanup
        {--user= : Temizlenecek kullanıcı ID; zorunludur}
        {--days= : Yalnızca dry-run için geçici retention günü}
        {--batch= : Silme batch boyutu}
        {--execute : Feature flag açıksa gerçek silmeyi çalıştır}
        {--json : JSON çıktı üret}';

    protected $description = 'Trendyol Booster geçmiş verilerini kullanıcı kapsamında ve güvenlik bariyerleriyle temizler.';

    public function handle(
        TrendyolBoosterRetentionReportService $reportService,
        TrendyolBoosterRetentionCleanupService $cleanupService,
    ): int {
        $userId = $this->positiveIntegerOption('user');

        if ($userId === null) {
            $this->components->error('--user seçeneği zorunludur. Tüm kullanıcıları topluca temizleme desteklenmez.');

            return self::FAILURE;
        }

        if (! User::query()->whereKey($userId)->exists()) {
            $this->components->error('Kullanıcı bulunamadı: #'.$userId);

            return self::FAILURE;
        }

        if (! $this->option('execute')) {
            $report = $reportService->report($userId, $this->positiveIntegerOption('days'));
            $this->outputDryRun($report);

            return self::SUCCESS;
        }

        if ($this->option('days') !== null) {
            $this->components->error('--days gerçek silmede kullanılamaz. Kalıcı retention ayarlarını config üzerinden değiştirin.');

            return self::FAILURE;
        }

        if (! (bool) config('marketplace.trendyol_booster.retention.cleanup_enabled', false)) {
            $this->components->error('Retention temizliği feature flag ile kapalı. Silme yapılmadı.');

            return self::FAILURE;
        }

        try {
            $result = $cleanupService->cleanup($userId, $this->positiveIntegerOption('batch'));
        } catch (LogicException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        Log::info('Trendyol Booster retention cleanup completed.', [
            'user_id' => $userId,
            'candidate_before' => data_get($result, 'summary.candidate_before', 0),
            'deleted_count' => data_get($result, 'summary.deleted_count', 0),
            'candidate_remaining' => data_get($result, 'summary.candidate_remaining', 0),
            'stopped_at_limit' => data_get($result, 'summary.stopped_at_limit', false),
        ]);
        $this->outputExecution($result);

        return self::SUCCESS;
    }

    protected function outputDryRun(array $report): void
    {
        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return;
        }

        $this->components->info('Retention cleanup dry-run tamamlandı. Silme yok.');
        $this->line(sprintf(
            'Kullanıcı #%d | Aday kayıt: %s | Execute flag: %s',
            $report['user_id'],
            number_format((int) data_get($report, 'summary.candidate_count', 0), 0, ',', '.'),
            config('marketplace.trendyol_booster.retention.cleanup_enabled', false) ? 'açık' : 'kapalı',
        ));
    }

    protected function outputExecution(array $result): void
    {
        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return;
        }

        $summary = $result['summary'];
        $this->components->info('Retention temizliği tamamlandı.');
        $this->line(sprintf(
            'Kullanıcı #%d | Aday: %s | Silinen: %s | Kalan: %s',
            $result['user_id'],
            number_format((int) $summary['candidate_before'], 0, ',', '.'),
            number_format((int) $summary['deleted_count'], 0, ',', '.'),
            number_format((int) $summary['candidate_remaining'], 0, ',', '.'),
        ));
        $this->table(['Alan', 'Aday', 'Silinen', 'Kalan', 'Durum'], array_map(
            fn (array $row): array => [
                $row['label'],
                number_format((int) $row['candidate_count'], 0, ',', '.'),
                number_format((int) $row['deleted_count'], 0, ',', '.'),
                number_format((int) $row['remaining_count'], 0, ',', '.'),
                $row['status'],
            ],
            $result['datasets'],
        ));

        if ($summary['stopped_at_limit']) {
            $this->components->warn('Koşu silme üst sınırında durdu. Kalan adaylar sonraki koşuda temizlenebilir.');
        }
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
