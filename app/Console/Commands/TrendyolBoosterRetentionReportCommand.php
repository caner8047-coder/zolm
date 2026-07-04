<?php

namespace App\Console\Commands;

use App\Services\Marketplace\TrendyolBoosterRetentionReportService;
use Illuminate\Console\Command;

class TrendyolBoosterRetentionReportCommand extends Command
{
    protected $signature = 'marketplace:trendyol-booster-retention-report
        {--user= : Yalnızca belirtilen kullanıcı ID için raporla}
        {--days= : Tüm retention pencereleri için geçici gün override}
        {--json : JSON çıktı üret}';

    protected $description = 'Trendyol Booster geçmiş tabloları için silme yapmadan retention aday raporu üretir.';

    public function handle(TrendyolBoosterRetentionReportService $service): int
    {
        $report = $service->report(
            $this->positiveIntegerOption('user'),
            $this->positiveIntegerOption('days'),
        );

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $summary = $report['summary'];
        $this->components->info('Trendyol Booster retention dry-run raporu hazırlandı. Silme yok.');
        $this->line(sprintf(
            'Kapsam: %s | Üretim zamanı: %s',
            $summary['scope'] === 'user' ? 'Kullanıcı #'.$report['user_id'] : 'Tüm kullanıcılar',
            $report['generated_at'],
        ));

        $this->newLine();
        $this->table(['Metrik', 'Değer'], [
            ['Toplam dataset', $summary['dataset_count']],
            ['Hazır dataset', $summary['available_dataset_count']],
            ['Eksik dataset', $summary['missing_dataset_count']],
            ['Toplam kayıt', number_format((int) $summary['total_count'], 0, ',', '.')],
            ['Retention adayı', number_format((int) $summary['candidate_count'], 0, ',', '.')],
        ]);

        $this->table(['Alan', 'Saklama', 'Toplam', 'Aday', 'Oran', 'En eski', 'Son kayıt', 'Durum'], array_map(
            fn (array $row): array => [
                $row['label'],
                $row['retention_days'].' gün',
                number_format((int) $row['total_count'], 0, ',', '.'),
                number_format((int) $row['candidate_count'], 0, ',', '.'),
                number_format((float) $row['candidate_ratio'], 2, ',', '.').'%',
                $row['oldest_at'] ?? '-',
                $row['latest_at'] ?? '-',
                $row['available'] ? 'Hazır' : $row['missing_reason'],
            ],
            $report['datasets'],
        ));

        return self::SUCCESS;
    }

    protected function positiveIntegerOption(string $name): ?int
    {
        $value = $this->option($name);

        if ($value === null || $value === '') {
            return null;
        }

        return max(1, (int) $value);
    }
}
