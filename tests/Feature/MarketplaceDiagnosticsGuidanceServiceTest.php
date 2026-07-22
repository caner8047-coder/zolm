<?php

namespace Tests\Feature;

use App\Models\ChannelOrder;
use App\Models\IntegrationConnection;
use App\Models\IntegrationSyncProfile;
use App\Models\IntegrationSyncRun;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\MpOrder;
use App\Models\MpPeriod;
use App\Models\User;
use App\Services\Marketplace\MarketplaceDiagnosticsGuidanceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketplaceDiagnosticsGuidanceServiceTest extends TestCase
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

    public function test_it_builds_prioritized_guidance_from_diagnostics(): void
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Guidance Ltd.',
            'tax_number' => '5'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'ZEM GUIDE',
            'store_code' => 'GUIDE-'.$suffix,
            'seller_id' => 'GUIDE-'.$suffix,
            'status' => 'configured',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        IntegrationSyncRun::query()->create([
            'store_id' => $store->id,
            'sync_type' => 'finance',
            'trigger_type' => 'smoke_test',
            'status' => 'completed',
            'notes_json' => [
                'smoke_test' => true,
                'diagnostics' => [
                    'event_count' => 10,
                    'missing_amount_count' => 12,
                    'missing_settlement_date_count' => 4,
                    'warnings' => ['Bazı finans kayıtlarında order number eksik.'],
                ],
            ],
        ]);

        IntegrationSyncRun::query()->create([
            'store_id' => $store->id,
            'sync_type' => 'orders',
            'trigger_type' => 'manual',
            'status' => 'completed',
            'notes_json' => [
                'diagnostics' => [
                    'item_count' => 20,
                    'missing_stock_code_count' => 3,
                    'missing_barcode_count' => 2,
                    'warnings' => ['Bazı sipariş satırlarında hem stok kodu hem barkod eksik.'],
                ],
            ],
        ]);

        $guidance = app(MarketplaceDiagnosticsGuidanceService::class)->guidanceForUser($user->id, [
            'hours' => 168,
            'limit' => 50,
        ]);

        $this->assertGreaterThanOrEqual(2, $guidance['totals']['items']);
        $this->assertGreaterThanOrEqual(1, $guidance['totals']['critical']);
        $this->assertSame('finance_mapping', $guidance['items'][0]['category']);
        $this->assertSame('critical', $guidance['items'][0]['severity']);
    }

    public function test_it_adds_woocommerce_webhook_topic_guidance_when_topic_set_is_empty(): void
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Woo Topic Guidance Ltd.',
            'tax_number' => '4'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'woocommerce',
            'store_name' => 'WOO TOPIC GUIDE',
            'store_code' => 'WOO-TOPIC-'.$suffix,
            'seller_id' => 'WOO-TOPIC-'.$suffix,
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
                'store_url' => 'https://woo.example.com',
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

        $guidance = app(MarketplaceDiagnosticsGuidanceService::class)->guidanceForUser($user->id, [
            'hours' => 168,
            'limit' => 50,
        ]);

        $webhookGuidance = collect($guidance['items'])->firstWhere('category', 'woocommerce_webhook_topics');

        $this->assertNotNull($webhookGuidance);
        $this->assertSame('critical', $webhookGuidance['severity']);
        $this->assertSame('mp.integrations', $webhookGuidance['route']);
    }

    public function test_it_adds_shopify_webhook_topic_guidance_when_topic_set_is_empty(): void
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Shopify Topic Guidance Ltd.',
            'tax_number' => '2'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'shopify',
            'store_name' => 'SHOPIFY TOPIC GUIDE',
            'store_code' => 'SHOP-TOPIC-'.$suffix,
            'seller_id' => 'SHOP-TOPIC-'.$suffix,
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

        $guidance = app(MarketplaceDiagnosticsGuidanceService::class)->guidanceForUser($user->id, [
            'hours' => 168,
            'limit' => 50,
        ]);

        $webhookGuidance = collect($guidance['items'])->firstWhere('category', 'shopify_webhook_topics');

        $this->assertNotNull($webhookGuidance);
        $this->assertSame('critical', $webhookGuidance['severity']);
        $this->assertSame('mp.integrations', $webhookGuidance['route']);
    }

    public function test_it_adds_trendyol_safe_profile_guidance_when_profile_is_too_aggressive(): void
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Trendyol Safe Guidance Ltd.',
            'tax_number' => '7'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'TRENDYOL SAFE GUIDE',
            'store_code' => 'TY-SAFE-'.$suffix,
            'seller_id' => 'TY-SAFE-'.$suffix,
            'status' => 'configured',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        IntegrationSyncProfile::query()->create([
            'store_id' => $store->id,
            'orders_poll_minutes' => 5,
            'finance_poll_minutes' => 20,
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
        ]);

        $guidance = app(MarketplaceDiagnosticsGuidanceService::class)->guidanceForUser($user->id, [
            'hours' => 168,
            'limit' => 50,
        ]);

        $safeGuidance = collect($guidance['items'])->firstWhere('category', 'trendyol_safe_profile');

        $this->assertNotNull($safeGuidance);
        $this->assertSame('critical', $safeGuidance['severity']);
        $this->assertSame('mp.integrations', $safeGuidance['route']);
    }

    public function test_it_adds_hepsiburada_safe_profile_guidance_when_profile_is_too_aggressive(): void
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Hepsiburada Safe Guidance Ltd.',
            'tax_number' => '4'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'hepsiburada',
            'store_name' => 'HEPSIBURADA SAFE GUIDE',
            'store_code' => 'HB-SAFE-'.$suffix,
            'seller_id' => 'HB-SAFE-'.$suffix,
            'status' => 'configured',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        IntegrationSyncProfile::query()->create([
            'store_id' => $store->id,
            'orders_poll_minutes' => 8,
            'finance_poll_minutes' => 30,
            'products_poll_minutes' => 240,
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
            'request_jitter_seconds' => 2,
        ]);

        $guidance = app(MarketplaceDiagnosticsGuidanceService::class)->guidanceForUser($user->id, [
            'hours' => 168,
            'limit' => 50,
        ]);

        $safeGuidance = collect($guidance['items'])->firstWhere('category', 'hepsiburada_safe_profile');

        $this->assertNotNull($safeGuidance);
        $this->assertSame('critical', $safeGuidance['severity']);
        $this->assertSame('mp.integrations', $safeGuidance['route']);
    }

    public function test_it_adds_legacy_financial_projection_guidance_when_unprojected_legacy_financial_rows_exist(): void
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Legacy Finance Guidance Ltd.',
            'tax_number' => '3'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'LEGACY FIN GUIDE',
            'store_code' => 'LEG-FIN-'.$suffix,
            'seller_id' => 'LEG-FIN-'.$suffix,
            'status' => 'configured',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
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

        $orderNumber = 'LEGACY-FIN-GUIDE-'.$suffix;

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

        $guidance = app(MarketplaceDiagnosticsGuidanceService::class)->guidanceForUser($user->id, [
            'hours' => 168,
            'limit' => 50,
        ]);

        $legacyGuidance = collect($guidance['items'])->firstWhere('category', 'legacy_financial_projection');

        $this->assertNotNull($legacyGuidance);
        $this->assertSame('warning', $legacyGuidance['severity']);
        $this->assertSame('mp.orders', $legacyGuidance['route']);
        $this->assertSame(1, $legacyGuidance['impact_count']);
    }
}
