<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Support\CustomerCareSecurityService;
use App\Services\Support\TenantContext;

class CustomerCareSecurityCommand extends Command
{
    protected $signature = 'customer-care:security-audit
        {--store= : Store ID}
        {--dry-run : Denetim çalıştırılır ama mutasyon yapılmaz (varsayılan)}
        {--execute : Bulgular veritabanına yazılır}';

    protected $description = 'Müşteri İletişim Merkezi için teknik güvenlik ve uyumluluk denetimi çalıştırır';

    public function handle(): int
    {
        $storeId = $this->option('store');
        $execute = $this->option('execute');
        $dryRun  = !$execute;

        if (!$storeId) {
            $this->error('--store=ID parametresi zorunludur.');
            return 1;
        }

        if ($dryRun) {
            $this->info('[DRY-RUN] Denetim çalıştırılır ama mutasyon yapılmaz.');
        }

        $systemUser = TenantContext::getSystemActor();
        $service    = app(CustomerCareSecurityService::class);

        try {
            $run = $service->runAudit((int) $storeId, $dryRun, $systemUser);

            $severity = strtoupper($run->overall_severity ?? 'unknown');
            $count    = $run->findings_count ?? 0;

            $this->info("Denetim tamamlandı — ID: {$run->id}");
            $this->info("Genel Seviye : {$severity}");
            $this->info("Bulgu Sayısı : {$count}");

            foreach ($run->findings as $finding) {
                $sev = strtoupper($finding->severity);
                if ($finding->severity === 'critical') {
                    $this->error("[{$sev}] {$finding->category}: {$finding->title}");
                } else {
                    $this->warn("[{$sev}] {$finding->category}: {$finding->title}");
                }
            }

            if ($run->hasCriticalFindings()) {
                $this->error('KRİTİK bulgular mevcut — sistem healthy olarak işaretlenemez.');
                return 1;
            }
        } catch (\Throwable $e) {
            $this->error('HATA: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
