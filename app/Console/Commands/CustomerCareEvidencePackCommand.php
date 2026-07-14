<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Support\CustomerCareSecurityService;
use App\Services\Support\TenantContext;

class CustomerCareEvidencePackCommand extends Command
{
    protected $signature = 'customer-care:evidence-pack
        {--store= : Store ID}
        {--format=markdown : Çıktı formatı (markdown)}';

    protected $description = 'Denetçiler için PII ve gizli verilerden arındırılmış güvenlik kanıt raporu üretir';

    public function handle(): int
    {
        $storeId = $this->option('store');

        if (!$storeId) {
            $this->error('--store=ID parametresi zorunludur.');
            return 1;
        }

        $systemUser = TenantContext::getSystemActor();
        $service    = app(CustomerCareSecurityService::class);

        try {
            $pack = $service->generateEvidencePack((int) $storeId, $systemUser);
            $this->line($pack);
        } catch (\Throwable $e) {
            $this->error('HATA: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
