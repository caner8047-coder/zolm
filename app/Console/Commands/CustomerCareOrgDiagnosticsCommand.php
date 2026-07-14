<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\SupportOrganizationSetting;
use App\Models\SupportOrganizationMembership;
use App\Models\SupportServiceAccount;
use App\Services\Support\CustomerCareOrganizationContext;

class CustomerCareOrgDiagnosticsCommand extends Command
{
    protected $signature = 'customer-care:org-diagnostics
        {--organization= : Organization/Legal Entity ID}
        {--store= : Store ID}
        {--dry-run : Raporlama yapar ama herhangi bir güvenlik durumunu değiştirmez}';

    protected $description = 'Organizasyon ve mağaza tenant sınırlarını denetler ve teşhis raporu üretir';

    public function handle(): int
    {
        $orgId = $this->option('organization');
        $storeId = $this->option('store');

        $this->info("ZOLM Organizasyon Sınır Teşhisi Başlatılıyor...");

        if ($orgId) {
            $org = LegalEntity::find($orgId);
            if (!$org) {
                $this->error("Organizasyon bulunamadı: ID={$orgId}");
                return 1;
            }
            $this->diagnoseOrganization($org);
            return 0;
        }

        if ($storeId) {
            $store = MarketplaceStore::find($storeId);
            if (!$store) {
                $this->error("Mağaza bulunamadı: ID={$storeId}");
                return 1;
            }
            $this->diagnoseStore($store);
            return 0;
        }

        $this->error("--organization=ID veya --store=ID seçilmelidir.");
        return 1;
    }

    private function diagnoseOrganization(LegalEntity $org): void
    {
        $this->info("Teşhis ediliyor: Organizasyon '{$org->name}' [ID: MASKELENDİ]");

        $membershipsCount = SupportOrganizationMembership::where('legal_entity_id', $org->id)->count();
        $this->line("  Üye sayısı: {$membershipsCount}");

        $serviceAccountsCount = SupportServiceAccount::where('legal_entity_id', $org->id)->count();
        $this->line("  Servis hesabı sayısı: {$serviceAccountsCount}");

        try {
            $actor = CustomerCareOrganizationContext::getSystemActor($org->id);
            $this->info("  System Actor: Yapılandırılmış [Email MASKELENDİ]");
        } catch (\Throwable $e) {
            $this->error("  System Actor HATA: " . $e->getMessage());
        }

        $stores = MarketplaceStore::where('legal_entity_id', $org->id)->get();
        $this->line("  Bağlı Mağazalar:");
        foreach ($stores as $store) {
            $this->line("    - {$store->store_name} (ID: {$store->id})");
        }
    }

    private function diagnoseStore(MarketplaceStore $store): void
    {
        $this->info("Teşhis ediliyor: Mağaza '{$store->store_name}' [ID: {$store->id}]");

        if (!$store->legal_entity_id) {
            $this->error("  UYARI: Mağaza herhangi bir organizasyona bağlı değil! (Tenant v1 modunda)");
            return;
        }

        $org = $store->legalEntity;
        if (!$org) {
            $this->error("  HATA: Bağlı organizasyon veritabanında bulunamadı.");
            return;
        }

        $this->info("  Bağlı Organizasyon: '{$org->name}' [ID: MASKELENDİ]");
    }
}
