<?php

namespace Tests\Feature;

use App\Livewire\MarketplaceIntegrations;
use App\Models\IntegrationConnection;
use App\Models\IntegrationSyncProfile;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class MarketplaceIntegrationsMarketplaceSafeProfileTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql.host', 'mysql');
        config()->set('database.connections.mysql.port', '3306');
        config()->set('database.connections.mysql.database', $this->mysqlTestDatabaseName());
        config()->set('database.connections.mysql.username', 'sail');
        config()->set('database.connections.mysql.password', 'password');
        DB::purge('mysql');
        DB::reconnect('mysql');
        DB::setDefaultConnection('mysql');
    }

    public function test_it_can_apply_safe_defaults_for_existing_trendyol_store(): void
    {
        $user = User::factory()->create();
        ['store' => $store] = $this->createStoreGraph($user, 'trendyol', 'TY-SAFE');

        IntegrationSyncProfile::query()->create([
            'store_id' => $store->id,
            'orders_poll_minutes' => 5,
            'finance_poll_minutes' => 20,
            'products_poll_minutes' => 120,
            'backfill_mode' => 'custom',
            'backfill_custom_from' => now()->subDays(3),
            'backfill_custom_to' => now(),
            'orders_enabled' => true,
            'finance_enabled' => true,
            'products_enabled' => true,
            'webhook_enabled' => false,
            'price_push_enabled' => true,
            'stock_push_enabled' => true,
            'auto_match_enabled' => false,
            'barcode_fallback_enabled' => false,
            'strict_unique_match_enabled' => false,
            'nightly_repair_sync_enabled' => false,
            'max_parallel_jobs' => 4,
            'request_jitter_seconds' => 1,
        ]);

        $this->actingAs($user);

        Livewire::test(MarketplaceIntegrations::class)
            ->assertSet('selectedStoreId', $store->id)
            ->assertSee('Güvenli profil ile fark var')
            ->call('applySelectedStoreSafeProfile')
            ->assertSet('syncForm.ordersPollMinutes', 15)
            ->assertSet('syncForm.financePollMinutes', 60)
            ->assertSet('syncForm.productsPollMinutes', 720)
            ->assertSet('syncForm.webhookEnabled', true)
            ->assertSet('syncForm.pricePushEnabled', false)
            ->assertSet('syncForm.stockPushEnabled', false)
            ->assertSet('syncForm.maxParallelJobs', 1)
            ->assertSet('syncForm.requestJitterSeconds', 5)
            ->assertSet('flashMessageType', 'success')
            ->assertSee('Trendyol güvenli profil uygula')
            ->assertSee('Form güvenli profile uyumlu');
    }

    public function test_it_can_apply_safe_defaults_for_existing_hepsiburada_store(): void
    {
        $user = User::factory()->create();
        ['store' => $store] = $this->createStoreGraph($user, 'hepsiburada', 'HB-SAFE');

        IntegrationSyncProfile::query()->create([
            'store_id' => $store->id,
            'orders_poll_minutes' => 8,
            'finance_poll_minutes' => 30,
            'products_poll_minutes' => 240,
            'backfill_mode' => 'custom',
            'backfill_custom_from' => now()->subDays(3),
            'backfill_custom_to' => now(),
            'orders_enabled' => true,
            'finance_enabled' => true,
            'products_enabled' => true,
            'webhook_enabled' => true,
            'price_push_enabled' => true,
            'stock_push_enabled' => true,
            'auto_match_enabled' => false,
            'barcode_fallback_enabled' => false,
            'strict_unique_match_enabled' => false,
            'nightly_repair_sync_enabled' => false,
            'max_parallel_jobs' => 4,
            'request_jitter_seconds' => 1,
        ]);

        $this->actingAs($user);

        Livewire::test(MarketplaceIntegrations::class)
            ->assertSet('selectedStoreId', $store->id)
            ->assertSee('Güvenli profil ile fark var')
            ->call('applySelectedStoreSafeProfile')
            ->assertSet('syncForm.ordersPollMinutes', 20)
            ->assertSet('syncForm.financePollMinutes', 120)
            ->assertSet('syncForm.productsPollMinutes', 720)
            ->assertSet('syncForm.webhookEnabled', false)
            ->assertSet('syncForm.pricePushEnabled', false)
            ->assertSet('syncForm.stockPushEnabled', false)
            ->assertSet('syncForm.maxParallelJobs', 1)
            ->assertSet('syncForm.requestJitterSeconds', 10)
            ->assertSet('flashMessageType', 'success')
            ->assertSee('Hepsiburada güvenli profil uygula')
            ->assertSee('Form güvenli profile uyumlu');
    }

    public function test_it_forces_unsupported_hepsiburada_sync_flags_off_when_profile_is_saved(): void
    {
        $user = User::factory()->create();
        ['store' => $store] = $this->createStoreGraph($user, 'hepsiburada', 'HB-CAP');

        $this->actingAs($user);

        Livewire::test(MarketplaceIntegrations::class)
            ->assertSet('selectedStoreId', $store->id)
            ->set('syncForm.ordersEnabled', true)
            ->set('syncForm.financeEnabled', true)
            ->set('syncForm.productsEnabled', true)
            ->set('syncForm.webhookEnabled', true)
            ->set('syncForm.pricePushEnabled', true)
            ->set('syncForm.stockPushEnabled', true)
            ->call('saveSyncProfile')
            ->assertSet('flashMessageType', 'warning')
            ->assertSee('Desteklenmeyen ayarlar pasife alındı')
            ->assertSee('Webhook');

        $profile = IntegrationSyncProfile::query()->where('store_id', $store->id)->firstOrFail();

        $this->assertTrue($profile->orders_enabled);
        $this->assertTrue($profile->finance_enabled);
        $this->assertTrue($profile->products_enabled);
        $this->assertFalse($profile->webhook_enabled);
        $this->assertTrue($profile->price_push_enabled);
        $this->assertTrue($profile->stock_push_enabled);
    }

    /**
     * @return array{store: MarketplaceStore, connection: IntegrationConnection}
     */
    protected function createStoreGraph(User $user, string $marketplace, string $prefix): array
    {
        $suffix = (string) random_int(100000, 999999);

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Marketplace Safe Ltd.',
            'tax_number' => '8'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => $marketplace,
            'store_name' => strtoupper($prefix),
            'store_code' => $prefix.'-'.$suffix,
            'seller_id' => $prefix.'-'.$suffix,
            'status' => 'configured',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $connection = IntegrationConnection::query()->create([
            'store_id' => $store->id,
            'provider' => $marketplace,
            'auth_type' => 'api_key_secret',
            'credentials_encrypted' => [
                'api_key' => 'demo_key',
                'api_secret' => 'demo_secret',
            ],
            'api_base_url' => 'https://example.test',
            'status' => 'configured',
        ]);

        return compact('store', 'connection');
    }
}
