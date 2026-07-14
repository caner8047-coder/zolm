<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MarketplaceStore;
use App\Models\SupportCommercialSubscription;
use App\Services\Support\CustomerCareEntitlementService;

class CustomerCareEntitlementAuditCommand extends Command
{
    protected $signature = 'customer-care:entitlement-audit
        {--store= : Store ID}
        {--dry-run : Denetimi çalıştırır ancak abonelik/durum güncellemesi yapmaz}';

    protected $description = 'Belirli bir mağaza için ticari paket ve özellik yetki (entitlement) durumunu denetler';

    public function handle(): int
    {
        $storeId = $this->option('store');
        $dryRun = $this->option('dry-run');

        if (!$storeId) {
            $this->error('--store=ID parametresi zorunludur.');
            return 1;
        }

        $store = MarketplaceStore::find($storeId);
        if (!$store) {
            $this->error("Mağaza bulunamadı: ID={$storeId}");
            return 1;
        }

        $this->info("Mağaza '{$store->store_name}' için Entitlement Denetimi Başlatılıyor...");

        $subscription = SupportCommercialSubscription::where('store_id', $store->id)
            ->where('status', 'active')
            ->with('plan')
            ->first();

        if (!$subscription) {
            $this->warn("Aktif bir abonelik bulunamadı. Tüm özellikler fail-closed durumdadır.");
            return 0;
        }

        $plan = $subscription->plan;
        $this->info("Aktif Plan: " . strtoupper($plan->name) . " (Slug: {$plan->slug})");
        $this->line("Abonelik Durumu: " . strtoupper($subscription->status));

        $entitlements = $plan->entitlements ?? [];
        $this->info("Özellik İzinleri:");
        foreach ($entitlements as $feature => $allowed) {
            $status = $allowed ? 'ALLOWED' : 'BLOCKED';
            $this->line("  - {$feature}: {$status}");
        }

        return 0;
    }
}
