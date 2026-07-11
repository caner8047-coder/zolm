<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Accounting\AccountingPilotMonitoringService;

class AccountingPilotMonitoringReportCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'accounting:pilot-monitoring-report {--user= : Pilot user id} {--json : JSON çıktı verir}';

    /**
     * The console command description.
     */
    protected $description = 'Pilot release sonrası ilk kullanım döngüsünü izleme ve operasyonel karar raporu üretme';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userId = $this->option('user') ? (int) $this->option('user') : null;
        $service = app(AccountingPilotMonitoringService::class);

        $summary = $service->summary($userId);
        $breakdown = $service->feedbackBreakdown($userId);
        $trend = $service->healthTrend($userId);
        $decision = $service->decision($userId);

        $report = [
            'summary' => $summary,
            'feedback_breakdown' => $breakdown,
            'health_trend' => $trend,
            'decision' => $decision,
        ];

        if ($this->option('json')) {
            $this->output->write(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            return $decision['status'] === 'blocked' ? 1 : 0;
        }

        $this->info('==================================================');
        $this->info('       ZOLM ERP PILOT MONITORING REPORT           ');
        $this->info('==================================================');
        $this->line('');

        // Summary table
        $this->comment('--- GENEL ÖZET ---');
        $headersSummary = ['Metrik', 'Değer'];
        $rowsSummary = [
            ['Açık Geri Bildirim', $summary['open_feedback_count']],
            ['Çözülen Geri Bildirim', $summary['resolved_feedback_count']],
            ['Kritik Geri Bildirim', $summary['critical_feedback_count']],
            ['Yüksek Öncelikli', $summary['high_feedback_count']],
            ['Son Sağlık Durumu', $summary['latest_health_status']],
            ['Son Sağlık Skoru', $summary['latest_health_score'] . '/100'],
            ['Son Başarısız Taramalar', $summary['latest_failed_count']],
            ['Son Taramadaki Uyarılar', $summary['latest_warning_count']],
        ];
        $this->table($headersSummary, $rowsSummary);
        $this->line('');

        // Feedback Severity Breakdown
        $this->comment('--- GERİ BİLDİRİM DERECELERİ ---');
        $headersBreakdown = ['Derece', 'Açık / Toplam Sayı'];
        $rowsBreakdown = [
            ['Kritik (Critical)', $breakdown['severity']['critical']],
            ['Yüksek (High)', $breakdown['severity']['high']],
            ['Orta (Medium)', $breakdown['severity']['medium']],
            ['Düşük (Low)', $breakdown['severity']['low']],
        ];
        $this->table($headersBreakdown, $rowsBreakdown);
        $this->line('');

        // Health Trend
        $this->comment('--- SON SAĞLIK GÜNCELLEMELERİ TRENDİ ---');
        $headersTrend = ['Tarih', 'Durum', 'Skor', 'Hata', 'Uyarı'];
        $rowsTrend = [];
        foreach ($trend as $t) {
            $rowsTrend[] = [
                $t['checked_at'],
                $t['status'],
                $t['score'] . '/100',
                $t['failed_count'],
                $t['warning_count'],
            ];
        }
        $this->table($headersTrend, $rowsTrend);
        $this->line('');

        // Decision section
        $this->comment('--- OPERASYONEL PİLOT KARARI ---');
        $this->line("KARAR: " . strtoupper($decision['status']));
        $this->line("Açıklama: " . $decision['label']);
        foreach ($decision['reasons'] as $reason) {
            $this->line("- " . $reason);
        }
        $this->line('');

        if ($decision['status'] === 'blocked') {
            $this->error('DİKKAT: Pilot aşaması bloklanmış durumdadır! Hatalar veya geri bildirimler giderilmelidir.');
            return 1;
        }

        if ($decision['status'] === 'proceed_with_fixes') {
            $this->comment('DİKKAT: Pilot düzeltme sprinti (proceed_with_fixes) ile devam edebilir.');
            return 0;
        }

        $this->info('Tebrikler! Pilot sorunsuz devam edebilir.');
        return 0;
    }
}
