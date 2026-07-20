<?php

namespace App\Services\Demo;

use App\Models\AdAccount;
use App\Models\AdCampaign;
use App\Models\CargoCarrierAccount;
use App\Models\ChannelOrder;
use App\Models\CrmContact;
use App\Models\IntegrationConnection;
use App\Models\IntegrationSyncProfile;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\Material;
use App\Models\Profile;
use App\Models\Recipe;
use App\Models\ReturnIntakeBatch;
use App\Models\Shipment;
use App\Models\SupportArtifactVersion;
use App\Models\SupportChannel;
use App\Models\TrendyolBoosterProduct;
use App\Models\User;
use App\Models\WaAccount;
use App\Models\WaKnowledgeArticle;
use App\Services\Marketplace\MarketplaceProviderRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class ZolmDemoTenantAuditor
{
    /**
     * @return array{
     *   healthy: bool,
     *   user_id: int|null,
     *   findings: array<int, array{area: string, status: string, detail: string}>
     * }
     */
    public function audit(string $email, ?string $expectedPassword = null): array
    {
        $findings = [];
        $user = User::where('email', $email)->first();

        if (! $user) {
            return [
                'healthy' => false,
                'user_id' => null,
                'findings' => [[
                    'area' => 'Kimlik',
                    'status' => 'fail',
                    'detail' => "{$email} kullanıcısı bulunamadı.",
                ]],
            ];
        }

        $this->add(
            $findings,
            'Kimlik',
            $user->roleSlug() === 'admin' && $user->is_active ? 'pass' : 'fail',
            "Kullanıcı #{$user->id}; rol={$user->roleSlug()}; aktif=".($user->is_active ? 'evet' : 'hayır').'.'
        );

        if ($expectedPassword !== null) {
            $this->add(
                $findings,
                'Kimlik',
                Hash::check($expectedPassword, (string) $user->password) ? 'pass' : 'fail',
                'Demo parola doğrulaması.'
            );
        }

        $otherUsers = User::where('id', '!=', $user->id)->count();
        $this->add(
            $findings,
            'Ortam izolasyonu',
            $otherUsers === 0 ? 'pass' : 'warn',
            $otherUsers === 0
                ? 'Demo kullanıcı ayrı veritabanında tek başına çalışıyor.'
                : "Veritabanında {$otherUsers} başka kullanıcı var. Admin ekranları tenant izolasyon testi için güvenilir değildir."
        );

        $legalEntities = LegalEntity::where('user_id', $user->id)->get();
        $this->add(
            $findings,
            'Firma',
            $legalEntities->isNotEmpty() ? 'pass' : 'fail',
            'Kullanıcıya bağlı firma sayısı: '.$legalEntities->count().'.'
        );

        $legalEntityIds = $legalEntities->pluck('id');
        $stores = MarketplaceStore::where('user_id', $user->id)->get();
        $expectedStoreCount = count(MarketplaceProviderRegistry::providers());
        $this->add(
            $findings,
            'Pazaryeri mağazaları',
            $stores->count() === $expectedStoreCount ? 'pass' : 'fail',
            "Beklenen {$expectedStoreCount}, bulunan {$stores->count()} mağazadır."
        );

        $crossTenantStoreCount = $stores
            ->filter(fn (MarketplaceStore $store): bool => ! $legalEntityIds->contains($store->legal_entity_id))
            ->count();
        $this->add(
            $findings,
            'Tenant zinciri',
            $crossTenantStoreCount === 0 ? 'pass' : 'fail',
            $crossTenantStoreCount === 0
                ? 'User -> LegalEntity -> MarketplaceStore zinciri tutarlı.'
                : "{$crossTenantStoreCount} mağazada cross-tenant firma bağlantısı var."
        );

        $storeIds = $stores->pluck('id');
        $connections = IntegrationConnection::whereIn('store_id', $storeIds)->get();
        $nonDemoConnections = $connections->where('status', '!=', 'demo')->count();
        $this->add(
            $findings,
            'Mock connector güvenliği',
            $connections->count() === $stores->count() && $nonDemoConnections === 0 ? 'pass' : 'fail',
            "Bağlantı={$connections->count()}, demo olmayan={$nonDemoConnections}."
        );

        $profiles = IntegrationSyncProfile::whereIn('store_id', $storeIds)->get();
        $unsafeProfiles = $profiles->filter(function (IntegrationSyncProfile $profile): bool {
            return (bool) $profile->orders_enabled
                || (bool) $profile->finance_enabled
                || (bool) $profile->products_enabled
                || (bool) $profile->claims_enabled
                || (bool) $profile->questions_enabled
                || (bool) $profile->webhook_enabled
                || (bool) $profile->price_push_enabled
                || (bool) $profile->stock_push_enabled
                || (bool) $profile->nightly_repair_sync_enabled;
        })->count();
        $this->add(
            $findings,
            'Senkronizasyon güvenliği',
            $profiles->count() === $stores->count() && $unsafeProfiles === 0 ? 'pass' : 'fail',
            "Sync profili={$profiles->count()}, otomasyonu açık profil={$unsafeProfiles}."
        );

        $orderCount = ChannelOrder::whereIn('store_id', $storeIds)->count();
        $this->add(
            $findings,
            'Pazaryeri veri grafiği',
            $orderCount >= $expectedStoreCount ? 'pass' : 'fail',
            "Kanal siparişi={$orderCount}; ürün=".$this->countWhereIn('channel_products', 'store_id', $storeIds->all())
                .'; finans olayı='.$this->countWhereIn('order_financial_events', 'store_id', $storeIds->all())
                .'; iade talebi='.$this->countWhereIn('channel_claims', 'store_id', $storeIds->all())
                .'; soru='.$this->countWhereIn('marketplace_questions', 'store_id', $storeIds->all()).'.'
        );

        $accountCount = $this->countForUser('accounts', $user->id);
        $partyCount = $this->countForUser('parties', $user->id);
        $warehouseCount = $this->countForUser('warehouses', $user->id);
        $this->add(
            $findings,
            'Muhasebe / ERP',
            $accountCount > 0 && $partyCount > 0 && $warehouseCount > 0 ? 'pass' : 'fail',
            "Hesap={$accountCount}; cari={$partyCount}; depo={$warehouseCount}."
        );

        $engineProfiles = Profile::where('user_id', $user->id)->whereIn('type', ['production', 'operation'])->count();
        $materialCount = Material::where('user_id', $user->id)->count();
        $recipeCount = Recipe::where('user_id', $user->id)->count();
        $this->add(
            $findings,
            'Üretim / Operasyon',
            $engineProfiles >= 2 && $materialCount > 0 && $recipeCount > 0 ? 'pass' : 'fail',
            "Motor profili={$engineProfiles}; malzeme={$materialCount}; reçete={$recipeCount}."
        );

        $crmCount = CrmContact::where('user_id', $user->id)->count();
        $this->add($findings, 'CRM', $crmCount > 0 ? 'pass' : 'fail', "CRM kişi sayısı={$crmCount}.");

        $returnCount = ReturnIntakeBatch::where('user_id', $user->id)->count();
        $this->add($findings, 'İade', $returnCount > 0 ? 'pass' : 'fail', "İade intake batch sayısı={$returnCount}.");

        $demoCargoAccounts = CargoCarrierAccount::where('user_id', $user->id)->where('carrier_code', 'demo')->get();
        $unsafeCargoAccounts = $demoCargoAccounts->where('is_active', true)->count();
        $terminalShipments = Shipment::where('user_id', $user->id)->whereIn('status', Shipment::TERMINAL_STATUSES)->count();
        $this->add(
            $findings,
            'Kargo',
            $demoCargoAccounts->isNotEmpty() && $unsafeCargoAccounts === 0 && $terminalShipments > 0 ? 'pass' : 'fail',
            "Pasif demo hesap={$demoCargoAccounts->count()}; aktif demo hesap={$unsafeCargoAccounts}; terminal gönderi={$terminalShipments}."
        );

        $adAccounts = AdAccount::where('user_id', $user->id)->get();
        $activeAdAccounts = $adAccounts->where('is_active', true)->count();
        $activeCampaigns = AdCampaign::where('user_id', $user->id)->whereIn('status', ['active', 'running', 'scheduled'])->count();
        $this->add(
            $findings,
            'Reklam',
            $adAccounts->isNotEmpty() && $activeAdAccounts === 0 && $activeCampaigns === 0 ? 'pass' : 'fail',
            "Reklam hesabı={$adAccounts->count()}; aktif hesap={$activeAdAccounts}; aktif/scheduled kampanya={$activeCampaigns}."
        );

        $boosterProducts = TrendyolBoosterProduct::where('user_id', $user->id)->get();
        $unsafeBooster = $boosterProducts->filter(fn (TrendyolBoosterProduct $product): bool => (bool) $product->watch_price
            || (bool) $product->watch_stock
            || (bool) $product->watch_keyword
            || (bool) $product->analysis_auto_refresh_enabled
        )->count();
        $this->add(
            $findings,
            'Booster',
            $boosterProducts->isNotEmpty() && $unsafeBooster === 0 ? 'pass' : 'fail',
            "Booster ürünü={$boosterProducts->count()}; otomatik takip açık={$unsafeBooster}."
        );

        $waAccounts = WaAccount::whereIn('store_id', $storeIds)->get();
        $activeWaAccounts = $waAccounts->where('is_active', true)->count();
        $queuedOutbox = $this->countWhereInWithStatus('wa_outbox', 'store_id', $storeIds->all(), ['queued', 'processing', 'retry']);
        $this->add(
            $findings,
            'WhatsApp',
            $waAccounts->isNotEmpty() && $activeWaAccounts === 0 && $queuedOutbox === 0 ? 'pass' : 'fail',
            "WA hesabı={$waAccounts->count()}; aktif hesap={$activeWaAccounts}; gönderilebilir outbox={$queuedOutbox}."
        );

        $supportChannels = SupportChannel::whereIn('store_id', $storeIds)->get();
        $enabledSupportChannels = $supportChannels->where('is_enabled', true)->count();
        $knowledgeArticles = WaKnowledgeArticle::published()->whereIn('store_id', $storeIds)->count();
        $currentKnowledgeVersions = SupportArtifactVersion::whereIn('store_id', $storeIds)
            ->where('artifact_type', 'knowledge_article')
            ->where('is_current', true)
            ->whereHas('releasePackage', fn ($query) => $query->where('status', 'published'))
            ->count();
        $pendingSupportDispatches = $this->countWhereInWithStatus(
            'support_dispatches',
            'support_channel_id',
            $supportChannels->pluck('id')->all(),
            ['pending', 'sending']
        );
        $this->add(
            $findings,
            'Customer Care',
            $supportChannels->count() === $stores->count()
                && $enabledSupportChannels === 0
                && $knowledgeArticles > 0
                && $currentKnowledgeVersions > 0
                && $pendingSupportDispatches === 0 ? 'pass' : 'fail',
            "Destek kanalı={$supportChannels->count()}; dış aksiyona açık kanal={$enabledSupportChannels}; "
                ."yayında makale={$knowledgeArticles}; yayın paketine bağlı güncel sentetik sürüm={$currentKnowledgeVersions}; "
                ."gönderilebilir dispatch={$pendingSupportDispatches}."
        );

        $this->add(
            $findings,
            'Gerçek entegrasyon kanıtı',
            'warn',
            'Bu rapor uygulama-içi mock grafiği ve güvenlik bariyerini doğrular; gerçek API credential/sandbox bağlantısını doğrulamaz.'
        );
        $this->add(
            $findings,
            'Bilinen tenant boşluğu',
            'warn',
            'SupplyOrder user_id taşımıyor; ProductionRevenueEntry work_date benzersizliği global. Bu iki alan paylaşılan DB tenant testi dışında tutuldu.'
        );

        return [
            'healthy' => collect($findings)->where('status', 'fail')->isEmpty(),
            'user_id' => (int) $user->id,
            'findings' => $findings,
        ];
    }

    /**
     * @param  array<int, array{area: string, status: string, detail: string}>  $findings
     */
    private function add(array &$findings, string $area, string $status, string $detail): void
    {
        $findings[] = compact('area', 'status', 'detail');
    }

    private function countForUser(string $table, int $userId): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'user_id')) {
            return 0;
        }

        return DB::table($table)->where('user_id', $userId)->count();
    }

    /** @param array<int, int> $ids */
    private function countWhereIn(string $table, string $column, array $ids): int
    {
        if (! Schema::hasTable($table) || $ids === []) {
            return 0;
        }

        return DB::table($table)->whereIn($column, $ids)->count();
    }

    /**
     * @param  array<int, int>  $ids
     * @param  array<int, string>  $statuses
     */
    private function countWhereInWithStatus(string $table, string $column, array $ids, array $statuses): int
    {
        if (! Schema::hasTable($table) || $ids === []) {
            return 0;
        }

        return DB::table($table)->whereIn($column, $ids)->whereIn('status', $statuses)->count();
    }
}
