<?php

namespace App\Services\Marketplace;

class TrendyolBoosterOperationalAlertService
{
    /**
     * @return array<string, mixed>
     */
    public function dashboard(int $userId): array
    {
        return $this->summarize(
            app(TrendyolBoosterSyncHealthService::class)->dashboard($userId),
            app(TrendyolBoosterRetentionReportService::class)->report($userId),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function summarize(array $sync, array $retention): array
    {
        $issues = collect()
            ->merge($this->schedulerIssues($sync))
            ->merge($this->backlogIssues($sync))
            ->merge($this->retentionIssues($retention))
            ->sortBy(fn (array $issue): int => $this->severityRank($issue['severity']))
            ->values();
        $primary = $issues->first();
        $severity = $primary['severity'] ?? ($sync['tracked_total'] > 0 ? 'healthy' : 'idle');

        return [
            'severity' => $severity,
            'tone' => $this->tone($severity),
            'label' => $this->label($severity, $issues->count()),
            'summary' => $this->summary($severity, $sync, $primary),
            'issue_count' => $issues->count(),
            'primary_issue' => $primary,
            'issues' => $issues->all(),
            'sync' => [
                'tracked_total' => (int) ($sync['tracked_total'] ?? 0),
                'due_total' => (int) ($sync['due_total'] ?? 0),
                'never_checked_total' => (int) ($sync['never_checked_total'] ?? 0),
                'last_run_age_minutes' => $sync['last_run_age_minutes'] ?? null,
            ],
            'retention' => [
                'candidate_count' => (int) data_get($retention, 'summary.candidate_count', 0),
                'total_count' => (int) data_get($retention, 'summary.total_count', 0),
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function schedulerIssues(array $sync): array
    {
        $trackedTotal = (int) ($sync['tracked_total'] ?? 0);

        if ($trackedTotal === 0 || (bool) ($sync['healthy'] ?? false)) {
            return [];
        }

        $lastRunAge = $sync['last_run_age_minutes'] ?? null;
        $recentMinutes = max(1, (int) ($sync['recent_minutes'] ?? 15));
        $criticalAfter = $recentMinutes * max(2, (int) config('marketplace.trendyol_booster.alerts.scheduler_critical_multiplier', 3));
        $severity = is_numeric($lastRunAge) && (int) $lastRunAge >= $criticalAfter ? 'critical' : 'warning';

        return [[
            'key' => is_numeric($lastRunAge) ? 'scheduler_stale' : 'scheduler_missing',
            'severity' => $severity,
            'label' => is_numeric($lastRunAge) ? 'Scheduler gecikti' : 'Scheduler çalışması görünmüyor',
            'detail' => is_numeric($lastRunAge)
                ? 'Son otomatik tarama '.(int) $lastRunAge.' dakika önce göründü.'
                : 'Aktif takip var ama son scheduler çalışma izi yok.',
            'action' => 'Cron ve queue worker durumunu kontrol et.',
            'metric' => is_numeric($lastRunAge) ? (int) $lastRunAge.' dk' : null,
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function backlogIssues(array $sync): array
    {
        $trackedTotal = (int) ($sync['tracked_total'] ?? 0);
        $dueTotal = (int) ($sync['due_total'] ?? 0);
        $neverCheckedTotal = (int) ($sync['never_checked_total'] ?? 0);

        if ($trackedTotal === 0) {
            return [];
        }

        $dueRatio = $trackedTotal > 0 ? ($dueTotal / $trackedTotal) * 100 : 0.0;
        $issues = [];
        $criticalMin = max(1, (int) config('marketplace.trendyol_booster.alerts.backlog_critical_min', 50));
        $criticalRatio = max(1.0, (float) config('marketplace.trendyol_booster.alerts.backlog_critical_ratio', 80));
        $warningMin = max(1, (int) config('marketplace.trendyol_booster.alerts.backlog_warning_min', 10));
        $warningRatio = max(1.0, (float) config('marketplace.trendyol_booster.alerts.backlog_warning_ratio', 50));

        if ($dueTotal >= $criticalMin && $dueRatio >= $criticalRatio) {
            $issues[] = [
                'key' => 'sync_backlog_critical',
                'severity' => 'critical',
                'label' => 'Takip kuyruğu kritik',
                'detail' => number_format($dueRatio, 1, ',', '.').'% takip alanı bekliyor.',
                'action' => 'Sync komutunu dry-run ile kontrol edip worker kapasitesini gözden geçir.',
                'metric' => $dueTotal.'/'.$trackedTotal,
            ];
        } elseif ($dueTotal >= $warningMin && $dueRatio >= $warningRatio) {
            $issues[] = [
                'key' => 'sync_backlog_warning',
                'severity' => 'warning',
                'label' => 'Takip kuyruğu birikiyor',
                'detail' => number_format($dueRatio, 1, ',', '.').'% takip alanı bekliyor.',
                'action' => 'Otomatik takip komutunun düzenli çalıştığını kontrol et.',
                'metric' => $dueTotal.'/'.$trackedTotal,
            ];
        }

        $neverCheckedMin = max(1, (int) config('marketplace.trendyol_booster.alerts.never_checked_warning_min', 10));
        if ($neverCheckedTotal >= $neverCheckedMin) {
            $issues[] = [
                'key' => 'first_scan_backlog',
                'severity' => 'warning',
                'label' => 'İlk tarama birikiyor',
                'detail' => $neverCheckedTotal.' takip alanı hiç kontrol edilmemiş.',
                'action' => 'İlk tarama bekleyen ürün/kelime/mağaza kayıtlarını önceliklendir.',
                'metric' => (string) $neverCheckedTotal,
            ];
        }

        return $issues;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function retentionIssues(array $retention): array
    {
        $candidateCount = (int) data_get($retention, 'summary.candidate_count', 0);
        $totalCount = (int) data_get($retention, 'summary.total_count', 0);
        $ratio = $totalCount > 0 ? ($candidateCount / $totalCount) * 100 : 0.0;
        $warningMin = max(1, (int) config('marketplace.trendyol_booster.alerts.retention_warning_min', 1000));
        $warningRatio = max(1.0, (float) config('marketplace.trendyol_booster.alerts.retention_warning_ratio', 25));

        if ($candidateCount < $warningMin || $ratio < $warningRatio) {
            return [];
        }

        return [[
            'key' => 'retention_backlog',
            'severity' => 'warning',
            'label' => 'Geçmiş veri yükü büyüyor',
            'detail' => number_format($candidateCount, 0, ',', '.').' kayıt retention adayında.',
            'action' => 'Retention dry-run raporunu incele; gerçek temizlik için ayrıca onay planla.',
            'metric' => number_format($ratio, 1, ',', '.').'%',
        ]];
    }

    protected function severityRank(string $severity): int
    {
        return [
            'critical' => 0,
            'warning' => 1,
            'info' => 2,
            'healthy' => 3,
            'idle' => 4,
        ][$severity] ?? 5;
    }

    protected function tone(string $severity): string
    {
        return [
            'critical' => 'rose',
            'warning' => 'amber',
            'info' => 'sky',
            'healthy' => 'emerald',
            'idle' => 'slate',
        ][$severity] ?? 'slate';
    }

    protected function label(string $severity, int $issueCount): string
    {
        return match ($severity) {
            'critical' => 'Operasyon alarmı kritik',
            'warning' => $issueCount > 1 ? "{$issueCount} operasyon uyarısı" : 'Operasyon uyarısı var',
            'healthy' => 'Operasyon sakin',
            'idle' => 'Takip bekliyor',
            default => 'Operasyon bilgisi',
        };
    }

    protected function summary(string $severity, array $sync, ?array $primary): string
    {
        if ($primary) {
            return $primary['detail'];
        }

        if ($severity === 'idle') {
            return 'Aktif takip başlayınca alarm kalitesi burada izlenecek.';
        }

        return 'Scheduler, backlog ve veri yükü normal görünüyor.';
    }
}
