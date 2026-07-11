<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Accounting\AccountingPilotSmokeTestService;

class AccountingPilotSmokeTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'accounting:pilot-smoke-test {--user= : Pilot admin user id} {--json : JSON çıktı verir}';

    /**
     * The console command description.
     */
    protected $description = 'Uygulama içi route tanımlarının ve temel erişim kararlarının deploy sonrası hızlı doğrulanması';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userId = $this->option('user') ? (int) $this->option('user') : null;
        $service = app(AccountingPilotSmokeTestService::class);
        
        $result = $service->run($userId);

        if ($this->option('json')) {
            $this->output->write(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            return $result['failed_count'] > 0 ? 1 : 0;
        }

        $this->info('==================================================');
        $this->info('         ZOLM ERP PILOT SMOKE CHECKER             ');
        $this->info('==================================================');
        $this->line('');

        $headers = ['Kontrol / Route Adı', 'Durum', 'Açıklama'];
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
            $this->error('Smoke test başarısız! Sistem üzerinde eksik route veya yetki hatası mevcut.');
            return 1;
        }

        if ($result['status'] === 'warning') {
            $this->comment('Smoke test başarılı fakat bazı uyarılar (feature flags kapalı vb.) var.');
            return 0;
        }

        $this->info('Tebrikler! Tüm route ve flag tanımları eksiksiz render ediliyor.');
        return 0;
    }
}
