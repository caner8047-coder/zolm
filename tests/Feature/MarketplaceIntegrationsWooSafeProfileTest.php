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

class MarketplaceIntegrationsWooSafeProfileTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql.host', 'mysql');
        config()->set('database.connections.mysql.port', '3306');
        config()->set('database.connections.mysql.database', 'zolm');
        config()->set('database.connections.mysql.username', 'sail');
        config()->set('database.connections.mysql.password', 'password');
        DB::purge('mysql');
        DB::reconnect('mysql');
        DB::setDefaultConnection('mysql');
    }

    public function test_it_can_apply_safe_defaults_for_existing_woocommerce_store(): void
    {
        $user = User::factory()->create();
        ['store' => $store] = $this->createWooStoreGraph($user, 'WOO-SAFE');

        IntegrationSyncProfile::query()->create([
            'store_id' => $store->id,
            'orders_poll_minutes' => 10,
            'finance_poll_minutes' => 15,
            'products_poll_minutes' => 60,
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
            'request_jitter_seconds' => 2,
        ]);

        $this->actingAs($user);

        Livewire::test(MarketplaceIntegrations::class)
            ->assertSet('selectedStoreId', $store->id)
            ->assertSee('WooCommerce güvenli profilinden sapma var')
            ->call('applyWooSafeProfile')
            ->assertSet('syncForm.ordersPollMinutes', 15)
            ->assertSet('syncForm.financePollMinutes', 360)
            ->assertSet('syncForm.productsPollMinutes', 720)
            ->assertSet('syncForm.backfillMode', '7_days')
            ->assertSet('syncForm.backfillCustomFrom', '')
            ->assertSet('syncForm.backfillCustomTo', '')
            ->assertSet('syncForm.webhookTopics', \App\Models\IntegrationSyncProfile::recommendedWooWebhookTopics())
            ->assertSet('syncForm.financeEnabled', false)
            ->assertSet('syncForm.pricePushEnabled', false)
            ->assertSet('syncForm.stockPushEnabled', false)
            ->assertSet('syncForm.maxParallelJobs', 1)
            ->assertSet('syncForm.requestJitterSeconds', 15)
            ->assertSet('flashMessageType', 'success')
            ->assertSee('WooCommerce için düşük etkili profil forma uygulandı');
    }

    public function test_it_forces_unsupported_woocommerce_finance_sync_off_when_profile_is_saved(): void
    {
        $user = User::factory()->create();
        ['store' => $store] = $this->createWooStoreGraph($user, 'WOO-CAP');

        $this->actingAs($user);

        Livewire::test(MarketplaceIntegrations::class)
            ->assertSet('selectedStoreId', $store->id)
            ->set('syncForm.ordersEnabled', true)
            ->set('syncForm.financeEnabled', true)
            ->set('syncForm.productsEnabled', true)
            ->set('syncForm.webhookEnabled', true)
            ->call('saveSyncProfile')
            ->assertSet('flashMessageType', 'warning')
            ->assertSee('Desteklenmeyen ayarlar pasife alındı')
            ->assertSee('Finans sync');

        $profile = IntegrationSyncProfile::query()->where('store_id', $store->id)->firstOrFail();

        $this->assertTrue($profile->orders_enabled);
        $this->assertFalse($profile->finance_enabled);
        $this->assertTrue($profile->products_enabled);
        $this->assertTrue($profile->webhook_enabled);
    }

    /**
     * @return array{store: MarketplaceStore, connection: IntegrationConnection}
     */
    protected function createWooStoreGraph(User $user, string $prefix): array
    {
        $suffix = (string) random_int(100000, 999999);

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Woo Ltd.',
            'tax_number' => '8' . $suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'woocommerce',
            'store_name' => 'WOO ' . $prefix,
            'store_code' => $prefix . '-' . $suffix,
            'seller_id' => $prefix . '-' . $suffix,
            'status' => 'configured',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $connection = IntegrationConnection::query()->create([
            'store_id' => $store->id,
            'provider' => 'woocommerce',
            'auth_type' => 'consumer_key_secret',
            'credentials_encrypted' => [
                'api_key' => 'ck_test',
                'api_secret' => 'cs_test',
                'store_url' => 'https://woo.example.com',
            ],
            'api_base_url' => 'https://woo.example.com',
            'status' => 'configured',
        ]);

        return compact('store', 'connection');
    }
}
