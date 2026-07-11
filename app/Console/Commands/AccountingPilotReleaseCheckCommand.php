<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Accounting\AccountingPilotReleaseCheckService;

class AccountingPilotReleaseCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'accounting:pilot-release-check {--user= : Pilot admin user id} {--json : JSON çıktı verir}';

    /**
     * The console command description.
     */
    protected $description = 'Çalışma ortamının pilot release için hazır olup olmadığını okur ve raporlar';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userId = $this->option('user') ? (int) $this->option('user') : null;
        $service = app(AccountingPilotReleaseCheckService::class);
        
        $result = $service->run($userId);

        if ($this->option('json')) {
            $this->output->write(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            return $result['failed_count'] > 0 ? 1 : 0;
        }

        $this->info('==================================================');
        $this->info('         ZOLM ERP PILOT RELEASE CHECKER           ');
        $this->info('==================================================');
        $this->line('');

        $headers = ['Kontrol Adı', 'Durum', 'Açıklama'];
        $rows = [];

        foreach ($result['checks'] as $key => $check) {
            $statusLabel = match ($check['status']) {
                'passed' => '✓ TAMAM',
                'warning' => '⚠ UYARI',
                'failed' => '✗ HATA',
                default => $check['status'],
            };
            $rows[] = [$check['title'], $statusLabel, $check['message']];
        }

        $this->table($headers, $rows);

        $this->line('');
        $this->info("Toplam Hata: {$result['failed_count']}");
        $this->info("Toplam Uyarı: {$result['warning_count']}");
        $this->line('');

        if ($result['status'] === 'failed') {
            $this->error('Sistem pilot release için hazır DEĞİL. Lütfen hataları giderin.');
            return 1;
        }

        if ($result['status'] === 'warning') {
            $this->comment('Sistem pilot release için hazır (MVP limit veya uyarılar mevcut).');
            return 0;
        }

        $this->info('Tebrikler! Sistem pilot release için %100 hazır.');
        return 0;
    }
}
