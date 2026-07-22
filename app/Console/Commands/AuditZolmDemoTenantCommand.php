<?php

namespace App\Console\Commands;

use App\Services\Demo\ZolmDemoTenantAuditor;
use Illuminate\Console\Command;

class AuditZolmDemoTenantCommand extends Command
{
    protected $signature = 'zolm:demo:audit
        {--email=mockdata1@zolm.test : Denetlenecek demo kullanıcı e-postası}';

    protected $description = 'ZOLM demo tenant veri grafiğini, tenant bağlarını ve outbound güvenlik ayarlarını denetler.';

    public function handle(ZolmDemoTenantAuditor $auditor): int
    {
        $audit = $auditor->audit((string) $this->option('email'));

        $this->table(
            ['Alan', 'Durum', 'Detay'],
            collect($audit['findings'])->map(fn (array $finding): array => [
                $finding['area'],
                strtoupper($finding['status']),
                $finding['detail'],
            ])->all()
        );

        if (! $audit['healthy']) {
            $this->error('Demo tenant denetiminde başarısız kontroller var.');

            return self::FAILURE;
        }

        $this->info('Demo tenant uygulama-içi sağlık denetimi başarılı.');
        $this->warn('Bu sonuç gerçek dış servis credential veya sandbox API bağlantısını doğrulamaz.');

        return self::SUCCESS;
    }
}
