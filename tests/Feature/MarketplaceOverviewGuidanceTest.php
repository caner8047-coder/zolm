<?php

namespace Tests\Feature;

use App\Jobs\SyncMarketplaceDataJob;
use App\Livewire\MarketplaceOverview;
use App\Models\ChannelOrder;
use App\Models\IntegrationConnection;
use App\Models\IntegrationSyncProfile;
use App\Models\IntegrationSyncRun;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\MpOrder;
use App\Models\MpPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class MarketplaceOverviewGuidanceTest extends TestCase
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

    public function test_it_exposes_prioritized_diagnostics_guidance_for_overview(): void
    {
        $user = User::factory()->create();
        ['store' => $store] = $this->createStoreGraph($user, 'GUIDE');

        IntegrationSyncRun::query()->create([
            'store_id' => $store->id,
            'sync_type' => 'finance',
            'trigger_type' => 'smoke_test',
            'status' => 'completed',
            'items_received' => 5,
            'notes_json' => [
                'smoke_test' => true,
                'diagnostics' => [
                    'missing_amount_count' => 14,
                    'missing_settlement_date_count' => 3,
                    'warnings' => ['Tutar alanı gelmedi'],
                ],
            ],
        ]);

        $this->actingAs($user);

        $component = app(MarketplaceOverview::class);
        $guidance = $component->diagnosticsGuidance();

        $this->assertSame(1, $guidance['totals']['critical']);
        $this->assertSame('finance_mapping', $guidance['items'][0]['category']);
        $this->assertSame('critical', $guidance['items'][0]['severity']);
        $this->assertStringContainsString('/marketplace-finance', $component->guidanceRoute($guidance['items'][0]));
    }

    public function test_it_can_redirect_to_top_guidance_from_overview(): void
    {
        $user = User::factory()->create();
        ['store' => $store] = $this->createStoreGraph($user, 'OVERVIEW-FOCUS');

        IntegrationSyncRun::query()->create([
            'store_id' => $store->id,
            'sync_type' => 'finance',
            'trigger_type' => 'smoke_test',
            'status' => 'completed',
            'items_received' => 2,
            'notes_json' => [
                'smoke_test' => true,
                'diagnostics' => [
                    'missing_amount_count' => 8,
                ],
            ],
        ]);

        $this->actingAs($user);

        Livewire::test(MarketplaceOverview::class)
            ->call('focusTopGuidance')
            ->assertRedirect(route('mp.finance', ['storeFilter' => $store->id]));
    }

    public function test_it_can_queue_sync_from_overview_top_guidance(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        ['store' => $store] = $this->createStoreGraph($user, 'OVERVIEW-SYNC');

        IntegrationSyncRun::query()->create([
            'store_id' => $store->id,
            'sync_type' => 'products',
            'trigger_type' => 'smoke_test',
            'status' => 'completed',
            'items_received' => 4,
            'notes_json' => [
                'smoke_test' => true,
                'diagnostics' => [
                    'missing_stock_code_count' => 6,
                ],
            ],
        ]);

        $this->actingAs($user);

        Livewire::test(MarketplaceOverview::class)
            ->call('syncTopGuidance')
            ->assertSet('flashTone', 'success');

        $this->assertDatabaseHas('integration_sync_runs', [
            'store_id' => $store->id,
            'sync_type' => 'products',
            'trigger_type' => 'manual',
            'status' => 'queued',
        ]);

        Queue::assertPushed(SyncMarketplaceDataJob::class);
    }

    public function test_it_surfaces_woocommerce_safe_profile_guidance_in_overview(): void
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Woo Guidance Ltd.',
            'tax_number' => '7' . $suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'woocommerce',
            'store_name' => 'WOO GUIDE',
            'store_code' => 'WOO-' . $suffix,
            'seller_id' => 'WOO-' . $suffix,
            'status' => 'configured',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        IntegrationConnection::query()->create([
            'store_id' => $store->id,
            'provider' => 'woocommerce',
            'auth_type' => 'consumer_key_secret',
            'credentials_encrypted' => [
                'api_key' => 'ck_test',
                'api_secret' => 'cs_test',
            ],
            'api_base_url' => 'https://woo.example.com',
            'status' => 'configured',
        ]);

        IntegrationSyncProfile::query()->create([
            'store_id' => $store->id,
            'orders_poll_minutes' => 10,
            'finance_poll_minutes' => 15,
            'products_poll_minutes' => 30,
            'backfill_mode' => '30_days',
            'backfill_days' => 30,
            'orders_enabled' => true,
            'finance_enabled' => true,
            'products_enabled' => true,
            'webhook_enabled' => true,
            'price_push_enabled' => true,
            'stock_push_enabled' => true,
            'auto_match_enabled' => true,
            'barcode_fallback_enabled' => true,
            'strict_unique_match_enabled' => true,
            'nightly_repair_sync_enabled' => true,
            'max_parallel_jobs' => 3,
            'request_jitter_seconds' => 1,
        ]);

        $this->actingAs($user);

        $component = app(MarketplaceOverview::class);
        $guidance = $component->diagnosticsGuidance();

        $this->assertSame('woocommerce_safe_profile', $guidance['items'][0]['category']);
        $this->assertSame('critical', $guidance['items'][0]['severity']);
        $this->assertStringContainsString('/marketplace-integrations', $component->guidanceRoute($guidance['items'][0]));
    }

    public function test_it_surfaces_woocommerce_webhook_topic_guidance_in_overview(): void
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Woo Topic Overview Ltd.',
            'tax_number' => '9' . $suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'woocommerce',
            'store_name' => 'WOO TOPIC OVERVIEW',
            'store_code' => 'WOO-TOPIC-OV-' . $suffix,
            'seller_id' => 'WOO-TOPIC-OV-' . $suffix,
            'status' => 'configured',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        IntegrationConnection::query()->create([
            'store_id' => $store->id,
            'provider' => 'woocommerce',
            'auth_type' => 'consumer_key_secret',
            'credentials_encrypted' => [
                'api_key' => 'ck_test',
                'api_secret' => 'cs_test',
            ],
            'api_base_url' => 'https://woo.example.com',
            'webhook_secret' => 'woo-secret',
            'status' => 'configured',
        ]);

        IntegrationSyncProfile::query()->create([
            'store_id' => $store->id,
            'orders_poll_minutes' => 30,
            'finance_poll_minutes' => 360,
            'products_poll_minutes' => 720,
            'backfill_mode' => '7_days',
            'backfill_days' => 7,
            'orders_enabled' => true,
            'finance_enabled' => false,
            'products_enabled' => true,
            'webhook_enabled' => true,
            'price_push_enabled' => false,
            'stock_push_enabled' => false,
            'auto_match_enabled' => true,
            'barcode_fallback_enabled' => true,
            'strict_unique_match_enabled' => true,
            'nightly_repair_sync_enabled' => true,
            'max_parallel_jobs' => 1,
            'request_jitter_seconds' => 15,
            'extra_settings' => [
                'webhook_topics' => [],
            ],
        ]);

        $this->actingAs($user);

        $component = app(MarketplaceOverview::class);
        $guidance = $component->diagnosticsGuidance();

        $webhookGuidance = collect($guidance['items'])->firstWhere('category', 'woocommerce_webhook_topics');

        $this->assertNotNull($webhookGuidance);
        $this->assertSame('critical', $webhookGuidance['severity']);
        $this->assertStringContainsString('/marketplace-integrations', $component->guidanceRoute($webhookGuidance));
    }

    public function test_it_surfaces_shopify_webhook_topic_guidance_in_overview(): void
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Shopify Topic Overview Ltd.',
            'tax_number' => '1' . $suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'shopify',
            'store_name' => 'SHOPIFY TOPIC OVERVIEW',
            'store_code' => 'SHOP-OV-' . $suffix,
            'seller_id' => 'SHOP-OV-' . $suffix,
            'status' => 'configured',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        IntegrationConnection::query()->create([
            'store_id' => $store->id,
            'provider' => 'shopify',
            'auth_type' => 'access_token_app_secret',
            'credentials_encrypted' => [
                'api_key' => 'shpat_test_token',
                'api_secret' => 'shopify_app_secret',
                'store_url' => 'https://ornek.myshopify.com',
            ],
            'api_base_url' => 'https://ornek.myshopify.com',
            'webhook_secret' => 'shopify_app_secret',
            'status' => 'configured',
        ]);

        IntegrationSyncProfile::query()->create([
            'store_id' => $store->id,
            'orders_poll_minutes' => 15,
            'finance_poll_minutes' => 60,
            'products_poll_minutes' => 360,
            'backfill_mode' => '30_days',
            'backfill_days' => 30,
            'orders_enabled' => true,
            'finance_enabled' => true,
            'products_enabled' => true,
            'webhook_enabled' => true,
            'price_push_enabled' => false,
            'stock_push_enabled' => false,
            'auto_match_enabled' => true,
            'barcode_fallback_enabled' => true,
            'strict_unique_match_enabled' => true,
            'nightly_repair_sync_enabled' => true,
            'max_parallel_jobs' => 1,
            'request_jitter_seconds' => 5,
            'extra_settings' => [
                'webhook_topics' => [],
            ],
        ]);

        $this->actingAs($user);

        $component = app(MarketplaceOverview::class);
        $guidance = $component->diagnosticsGuidance();

        $webhookGuidance = collect($guidance['items'])->firstWhere('category', 'shopify_webhook_topics');

        $this->assertNotNull($webhookGuidance);
        $this->assertSame('critical', $webhookGuidance['severity']);
        $this->assertStringContainsString('/marketplace-integrations', $component->guidanceRoute($webhookGuidance));
    }

    public function test_it_surfaces_shopify_safe_profile_guidance_in_overview(): void
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Shopify Safe Overview Ltd.',
            'tax_number' => '8' . $suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'shopify',
            'store_name' => 'SHOPIFY SAFE OVERVIEW',
            'store_code' => 'SHOP-SAFE-OV-' . $suffix,
            'seller_id' => 'SHOP-SAFE-OV-' . $suffix,
            'status' => 'configured',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        IntegrationConnection::query()->create([
            'store_id' => $store->id,
            'provider' => 'shopify',
            'auth_type' => 'access_token_app_secret',
            'credentials_encrypted' => [
                'api_key' => 'shpat_test_token',
                'api_secret' => 'shopify_app_secret',
                'store_url' => 'https://ornek.myshopify.com',
            ],
            'api_base_url' => 'https://ornek.myshopify.com',
            'webhook_secret' => 'shopify_app_secret',
            'status' => 'configured',
        ]);

        IntegrationSyncProfile::query()->create([
            'store_id' => $store->id,
            'orders_poll_minutes' => 5,
            'finance_poll_minutes' => 60,
            'products_poll_minutes' => 120,
            'backfill_mode' => '30_days',
            'backfill_days' => 30,
            'orders_enabled' => true,
            'finance_enabled' => true,
            'products_enabled' => true,
            'webhook_enabled' => false,
            'price_push_enabled' => true,
            'stock_push_enabled' => true,
            'auto_match_enabled' => true,
            'barcode_fallback_enabled' => true,
            'strict_unique_match_enabled' => true,
            'nightly_repair_sync_enabled' => true,
            'max_parallel_jobs' => 3,
            'request_jitter_seconds' => 1,
            'extra_settings' => [
                'webhook_topics' => [],
            ],
        ]);

        $this->actingAs($user);

        $component = app(MarketplaceOverview::class);
        $guidance = $component->diagnosticsGuidance();

        $safeGuidance = collect($guidance['items'])->firstWhere('category', 'shopify_safe_profile');

        $this->assertNotNull($safeGuidance);
        $this->assertSame('critical', $safeGuidance['severity']);
        $this->assertStringContainsString('/marketplace-integrations', $component->guidanceRoute($safeGuidance));
    }

    public function test_it_redirects_to_orders_for_legacy_financial_projection_guidance(): void
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Overview Legacy Finance Ltd.',
            'tax_number' => '1' . $suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'OVERVIEW LEGACY FIN',
            'store_code' => 'OV-LEG-' . $suffix,
            'seller_id' => 'OV-LEG-' . $suffix,
            'status' => 'configured',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        IntegrationConnection::query()->create([
            'store_id' => $store->id,
            'provider' => 'trendyol',
            'auth_type' => 'api_key_secret',
            'credentials_encrypted' => [
                'api_key' => 'key',
                'api_secret' => 'secret',
            ],
            'api_base_url' => 'https://apigw.trendyol.com',
            'status' => 'configured',
        ]);

        IntegrationSyncProfile::query()->create(array_merge(
            ['store_id' => $store->id],
            IntegrationSyncProfile::defaultsForMarketplace('trendyol'),
        ));

        $period = MpPeriod::query()->create([
            'user_id' => $user->id,
            'seller_id' => $store->seller_id,
            'year' => (int) now()->year,
            'month' => (int) now()->month,
            'marketplace' => 'trendyol',
            'status' => 'completed',
        ]);

        $orderNumber = 'OVERVIEW-LEGACY-' . $suffix;

        ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $legalEntity->id,
            'external_order_id' => $orderNumber,
            'order_number' => $orderNumber,
            'order_status' => 'Teslim Edildi',
            'ordered_at' => now()->subDay(),
        ]);

        MpOrder::query()->create([
            'period_id' => $period->id,
            'order_number' => $orderNumber,
            'status' => 'Teslim Edildi',
            'order_date' => now()->subDay(),
            'payment_date' => now()->subHours(6)->toDateString(),
            'gross_amount' => 1000,
            'net_hakedis' => 800,
        ]);

        $this->actingAs($user);

        Livewire::test(MarketplaceOverview::class)
            ->assertSee('Legacy finans satirlari V2 ledger\'a tasinmamis')
            ->call('syncTopGuidance')
            ->assertRedirect(route('mp.orders', ['storeFilter' => $store->id]));
    }

    /**
     * @return array{store: MarketplaceStore, connection: IntegrationConnection}
     */
    protected function createStoreGraph(User $user, string $prefix): array
    {
        $suffix = (string) random_int(100000, 999999);

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem ' . $prefix . ' Ltd.',
            'tax_number' => '6' . $suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'ZEM ' . $prefix,
            'store_code' => $prefix . '-' . $suffix,
            'seller_id' => $prefix . '-' . $suffix,
            'status' => 'configured',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $connection = IntegrationConnection::query()->create([
            'store_id' => $store->id,
            'provider' => 'trendyol',
            'auth_type' => 'api_key_secret',
            'credentials_encrypted' => [
                'api_key' => 'key',
                'api_secret' => 'secret',
            ],
            'api_base_url' => 'https://apigw.trendyol.com/',
            'status' => 'configured',
        ]);

        return compact('store', 'connection');
    }
}
