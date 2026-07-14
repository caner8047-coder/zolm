<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Support\CustomerCareExperimentService;
use App\Services\Support\TenantContext;

class CustomerCareCompareReleaseCommand extends Command
{
    protected $signature = 'customer-care:compare-release
        {--store= : Store ID}
        {--current= : Mevcut artifact version ID}
        {--candidate= : Aday artifact version ID}
        {--dry-run : Karşılaştırma yapar ama kayıt oluşturmaz (varsayılan)}';

    protected $description = 'Aktif ve aday yayın paketi performansını çevrimdışı karşılaştırır';

    public function handle(): int
    {
        $storeId     = $this->option('store');
        $currentId   = $this->option('current');
        $candidateId = $this->option('candidate');

        if (!$storeId || !$currentId || !$candidateId) {
            $this->error('--store, --current ve --candidate parametreleri zorunludur.');
            return 1;
        }

        $systemUser = TenantContext::getSystemActor();
        $service    = app(CustomerCareExperimentService::class);

        try {
            $comparison = $service->compareRelease((int) $storeId, (int) $currentId, (int) $candidateId, $systemUser);

            $this->info("Karşılaştırma tamamlandı:");
            $this->info("  Mevcut ID     : {$comparison['current_id']}");
            $this->info("  Aday ID       : {$comparison['candidate_id']}");
            $this->info("  Winner Aday   : {$comparison['winner_candidate']}");
            $this->warn("  Öneri         : {$comparison['recommendation']}");
            $this->warn("  Otomatik Yayın: " . ($comparison['auto_publish'] ? 'EVET (YASAK!)' : 'HAYIR — manuel release gerekir'));
        } catch (\Throwable $e) {
            $this->error('HATA: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
