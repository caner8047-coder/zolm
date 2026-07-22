<?php

namespace Tests\Feature;

use App\Livewire\MarketplaceIntegrations;
use App\Models\IntegrationConnection;
use App\Models\IntegrationSyncProfile;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\User;
use App\Services\MpSettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class MarketplaceIntegrationsConnectionSaveTest extends TestCase
{
    /** @var array<int, int> */
    protected array $createdStoreIds = [];

    /** @var array<int, int> */
    protected array $createdEntityIds = [];

    /** @var array<int, int> */
    protected array $createdUserIds = [];

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

    protected function tearDown(): void
    {
        if ($this->createdStoreIds !== []) {
            IntegrationConnection::query()->whereIn('store_id', $this->createdStoreIds)->delete();
            MarketplaceStore::query()->whereKey($this->createdStoreIds)->delete();
        }

        if ($this->createdEntityIds !== []) {
            LegalEntity::query()->whereKey($this->createdEntityIds)->delete();
        }

        if ($this->createdUserIds !== []) {
            User::query()->whereKey($this->createdUserIds)->delete();
        }

        parent::tearDown();
    }

    public function test_it_uses_store_url_as_api_base_url_for_woocommerce_when_api_url_is_blank(): void
    {
        $suffix = (string) random_int(100000, 999999);
        $user = User::factory()->create([
            'email' => 'woo-connection-'.$suffix.'@example.test',
        ]);
        $this->createdUserIds[] = $user->id;

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Woo Ltd.',
            'tax_number' => '9'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);
        $this->createdEntityIds[] = $legalEntity->id;

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'woocommerce',
            'store_name' => 'ZEM WOO',
            'store_code' => 'WOO-'.$suffix,
            'seller_id' => 'woo-'.$suffix,
            'status' => 'draft',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);
        $this->createdStoreIds[] = $store->id;

        $this->actingAs($user);

        Livewire::withQueryParams(['store' => $store->id])
            ->test(MarketplaceIntegrations::class)
            ->set('connectionForm.authType', 'api_key_secret')
            ->set('connectionForm.apiBaseUrl', '')
            ->set('connectionForm.webhookSecret', '')
            ->set('connectionForm.apiKey', 'ck_test')
            ->set('connectionForm.apiSecret', 'cs_test')
            ->set('connectionForm.zolmBoosterApiKey', 'zbt_test_key')
            ->set('connectionForm.storeFrontCode', '')
            ->set('connectionForm.extraUser', '')
            ->set('connectionForm.extraPassword', '')
            ->set('connectionForm.storeUrl', 'https://shop.example.com')
            ->call('saveConnection')
            ->assertHasNoErrors();

        $store->refresh();
        $connection = $store->connection()->firstOrFail();

        $this->assertSame('https://shop.example.com', $connection->api_base_url);
        $this->assertSame('https://shop.example.com', data_get($connection->credentials_encrypted, 'store_url'));
        $this->assertSame('zbt_test_key', data_get($connection->credentials_encrypted, 'zolm_booster_api_key'));
        $this->assertSame('configured', $connection->status);
    }

    public function test_successful_connection_verification_clears_previous_live_error_before_readiness_check(): void
    {
        [$user, $store, $connection] = $this->makeCiceksepetiStoreWithStaleError();

        Http::fake([
            'https://apis.ciceksepeti.com/api/v1/Order/GetOrders' => Http::response([
                'orderListCount' => 0,
                'supplierOrderListWithBranch' => [],
            ], 200),
        ]);

        $this->actingAs($user);

        Livewire::withQueryParams(['store' => $store->id])
            ->test(MarketplaceIntegrations::class)
            ->call('verifyConnection')
            ->assertSet('flashMessageType', 'success')
            ->assertSet('flashMessage', 'Çiçeksepeti bağlantısı doğrulandı.');

        $connection->refresh();

        $this->assertSame('configured', $connection->status);
        $this->assertNull($connection->last_error);
        $this->assertNotNull($connection->last_verified_at);
    }

    public function test_saving_connection_does_not_keep_previous_live_error_in_draft_readiness(): void
    {
        [$user, $store, $connection] = $this->makeCiceksepetiStoreWithStaleError();

        $this->actingAs($user);

        Livewire::withQueryParams(['store' => $store->id])
            ->test(MarketplaceIntegrations::class)
            ->set('connectionForm.authType', 'api_key_secret')
            ->set('connectionForm.apiBaseUrl', 'https://apis.ciceksepeti.com/api/v1')
            ->set('connectionForm.webhookSecret', '')
            ->set('connectionForm.apiKey', 'ciceksepeti-key')
            ->set('connectionForm.apiSecret', '')
            ->set('connectionForm.storeFrontCode', '')
            ->set('connectionForm.extraUser', 'ZOLM')
            ->set('connectionForm.extraPassword', '')
            ->set('connectionForm.storeUrl', '')
            ->call('saveConnection')
            ->assertHasNoErrors()
            ->assertSet('flashMessageType', 'success');

        $connection->refresh();
        $store->refresh();

        $this->assertSame('configured', $connection->status);
        $this->assertNull($connection->last_error);
        $this->assertNull($connection->last_verified_at);
        $this->assertSame('configured', $store->status);
    }

    /**
     * @return array{0: User, 1: MarketplaceStore, 2: IntegrationConnection}
     */
    protected function makeCiceksepetiStoreWithStaleError(): array
    {
        $suffix = (string) random_int(100000, 999999);
        $user = User::factory()->create([
            'email' => 'ciceksepeti-connection-'.$suffix.'@example.test',
        ]);
        $this->createdUserIds[] = $user->id;

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Çiçeksepeti Ltd.',
            'tax_number' => '8'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);
        $this->createdEntityIds[] = $legalEntity->id;

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'ciceksepeti',
            'store_name' => 'ZEM ÇİÇEKSEPETİ',
            'store_code' => 'CS-'.$suffix,
            'seller_id' => '1500041287-'.$suffix,
            'status' => 'error',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);
        $this->createdStoreIds[] = $store->id;

        $connection = IntegrationConnection::query()->create([
            'store_id' => $store->id,
            'provider' => 'ciceksepeti',
            'auth_type' => 'api_key_secret',
            'credentials_encrypted' => [
                'api_key' => 'ciceksepeti-key',
                'extra_user' => 'ZOLM',
            ],
            'api_base_url' => 'https://apis.ciceksepeti.com/api/v1',
            'status' => 'error',
            'last_verified_at' => now()->subHour(),
            'last_error' => 'HTTP request returned status code 404',
        ]);

        return [$user, $store, $connection];
    }

    protected function makeTrendyolStore(): array
    {
        $suffix = (string) random_int(100000, 999999);
        $user = User::factory()->create([
            'email' => 'trendyol-auto-match-'.$suffix.'@example.test',
        ]);
        $this->createdUserIds[] = $user->id;

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Trendyol Test Ltd.',
            'tax_number' => '9'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);
        $this->createdEntityIds[] = $legalEntity->id;

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'ZEM TRENDYOL TEST',
            'store_code' => 'TY-'.$suffix,
            'seller_id' => 'TY'.$suffix,
            'status' => 'active',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);
        $this->createdStoreIds[] = $store->id;

        $connection = IntegrationConnection::query()->create([
            'store_id' => $store->id,
            'provider' => 'trendyol',
            'auth_type' => 'api_key_secret',
            'credentials_encrypted' => [
                'seller_id' => 'TY'.$suffix,
                'api_key' => 'key',
                'api_secret' => 'secret',
            ],
            'api_base_url' => 'https://apigw.trendyol.com',
            'status' => 'configured',
        ]);

        return [$user, $store, $connection];
    }

    public function test_global_setting_applies_to_sync_form_when_no_profile(): void
    {
        [$user, $store, $connection] = $this->makeTrendyolStore();

        (new MpSettingsService($user->id))->set('matching.auto_run_on_sync', false);

        $this->actingAs($user);

        Livewire::test(MarketplaceIntegrations::class)
            ->call('selectStore', $store->id)
            ->assertSet('syncForm.autoMatchEnabled', false);
    }

    public function test_existing_sync_profile_preserves_own_value_over_global_setting(): void
    {
        [$user, $store, $connection] = $this->makeTrendyolStore();

        (new MpSettingsService($user->id))->set('matching.auto_run_on_sync', false);

        IntegrationSyncProfile::query()->create([
            'store_id' => $store->id,
            'auto_match_enabled' => true,
        ]);

        $this->actingAs($user);

        Livewire::test(MarketplaceIntegrations::class)
            ->call('selectStore', $store->id)
            ->assertSet('syncForm.autoMatchEnabled', true);
    }

    public function test_safe_profile_preset_applies_global_setting(): void
    {
        [$user, $store, $connection] = $this->makeTrendyolStore();

        (new MpSettingsService($user->id))->set('matching.auto_run_on_sync', false);

        $this->actingAs($user);

        Livewire::test(MarketplaceIntegrations::class)
            ->call('selectStore', $store->id)
            ->assertSet('syncForm.autoMatchEnabled', false)
            ->call('applySelectedStoreSafeProfile')
            ->assertSet('syncForm.autoMatchEnabled', false);
    }
}
