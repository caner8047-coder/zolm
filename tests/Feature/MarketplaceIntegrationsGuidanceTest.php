<?php

namespace Tests\Feature;

use App\Jobs\SyncMarketplaceDataJob;
use App\Livewire\MarketplaceIntegrations;
use App\Models\ChannelOrder;
use App\Models\IntegrationConnection;
use App\Models\IntegrationSyncProfile;
use App\Models\IntegrationSyncRun;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\MpOrder;
use App\Models\MpPeriod;
use App\Models\OrderFinancialEvent;
use App\Models\OrderProfitSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class MarketplaceIntegrationsGuidanceTest extends TestCase
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

    public function test_it_shows_diagnostics_guidance_in_store_cards_and_selected_store_panel(): void
    {
        $user = User::factory()->create();
        ['store' => $store] = $this->createStoreGraph($user, 'GUIDE-CARD');

        IntegrationSyncRun::query()->create([
            'store_id' => $store->id,
            'sync_type' => 'finance',
            'trigger_type' => 'smoke_test',
            'status' => 'completed',
            'items_received' => 3,
            'notes_json' => [
                'smoke_test' => true,
                'diagnostics' => [
                    'missing_amount_count' => 11,
                    'missing_settlement_date_count' => 2,
                    'warnings' => ['Tutar alanı gelmedi'],
                ],
            ],
        ]);

        $this->actingAs($user);

        Livewire::test(MarketplaceIntegrations::class)
            ->assertSet('selectedStoreId', $store->id)
            ->assertSee('İlk aksiyon')
            ->assertSee('Finans alanları eksik')
            ->assertSee('Tanı bazlı ilk aksiyonlar')
            ->assertSee('Tutar ve ödeme tarihi mapping alanlarını düzelt');
    }

    public function test_it_can_redirect_from_selected_store_guidance(): void
    {
        $user = User::factory()->create();
        ['store' => $store] = $this->createStoreGraph($user, 'GUIDE-FOCUS');

        IntegrationSyncRun::query()->create([
            'store_id' => $store->id,
            'sync_type' => 'finance',
            'trigger_type' => 'smoke_test',
            'status' => 'completed',
            'items_received' => 2,
            'notes_json' => [
                'smoke_test' => true,
                'diagnostics' => [
                    'missing_amount_count' => 5,
                ],
            ],
        ]);

        $this->actingAs($user);

        Livewire::test(MarketplaceIntegrations::class)
            ->call('focusSelectedStoreGuidance')
            ->assertRedirect(route('mp.finance', ['storeFilter' => $store->id]));
    }

    public function test_it_can_queue_sync_from_selected_store_guidance(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        ['store' => $store] = $this->createStoreGraph($user, 'GUIDE-SYNC');

        IntegrationSyncRun::query()->create([
            'store_id' => $store->id,
            'sync_type' => 'finance',
            'trigger_type' => 'smoke_test',
            'status' => 'completed',
            'items_received' => 2,
            'notes_json' => [
                'smoke_test' => true,
                'diagnostics' => [
                    'missing_amount_count' => 7,
                ],
            ],
        ]);

        $this->actingAs($user);

        Livewire::test(MarketplaceIntegrations::class)
            ->call('syncSelectedStoreGuidance')
            ->assertSet('flashMessageType', 'success');

        $this->assertDatabaseHas('integration_sync_runs', [
            'store_id' => $store->id,
            'sync_type' => 'finance',
            'trigger_type' => 'manual',
            'status' => 'queued',
        ]);

        Queue::assertPushed(SyncMarketplaceDataJob::class);
    }

    public function test_it_surfaces_woocommerce_safe_profile_guidance_in_integrations(): void
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Woo Integrations Ltd.',
            'tax_number' => '5'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'woocommerce',
            'store_name' => 'WOO SAFE GUIDE',
            'store_code' => 'WOO-SAFE-'.$suffix,
            'seller_id' => 'WOO-SAFE-'.$suffix,
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

        Livewire::test(MarketplaceIntegrations::class)
            ->assertSet('selectedStoreId', $store->id)
            ->assertSee('WooCommerce güvenli profilinden sapma var')
            ->assertSee('Entegrasyonlar ekranında güvenli WooCommerce profilini uygula');
    }

    public function test_it_shows_legacy_projection_backlog_in_store_cards_and_selected_store_panel(): void
    {
        $user = User::factory()->create();
        ['store' => $store] = $this->createStoreGraph($user, 'LEGACY-BACKLOG');

        $period = MpPeriod::query()->create([
            'user_id' => $user->id,
            'seller_id' => $store->seller_id,
            'year' => (int) now()->year,
            'month' => (int) now()->month,
            'marketplace' => 'trendyol',
            'status' => 'completed',
        ]);

        $pendingOrderNumber = 'INT-LEG-PEND-'.random_int(100000, 999999);
        $confirmedOrderNumber = 'INT-LEG-CONF-'.random_int(100000, 999999);

        ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'external_order_id' => $pendingOrderNumber,
            'order_number' => $pendingOrderNumber,
            'order_status' => 'Teslim Edildi',
            'ordered_at' => now()->subDay(),
        ]);

        $confirmedOrder = ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'external_order_id' => $confirmedOrderNumber,
            'order_number' => $confirmedOrderNumber,
            'order_status' => 'Teslim Edildi',
            'ordered_at' => now()->subDay(),
        ]);

        MpOrder::query()->create([
            'period_id' => $period->id,
            'order_number' => $pendingOrderNumber,
            'status' => 'Teslim Edildi',
            'order_date' => now()->subDay(),
            'payment_date' => now()->subHours(6)->toDateString(),
            'gross_amount' => 1000,
            'net_hakedis' => 760,
            'projected_at' => null,
        ]);

        MpOrder::query()->create([
            'period_id' => $period->id,
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'source_marketplace' => 'trendyol',
            'order_number' => $confirmedOrderNumber,
            'status' => 'Teslim Edildi',
            'order_date' => now()->subDay(),
            'payment_date' => now()->subHours(5)->toDateString(),
            'gross_amount' => 900,
            'net_hakedis' => 700,
            'projected_at' => now()->subMinutes(15),
        ]);

        OrderFinancialEvent::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'channel_order_id' => $confirmedOrder->id,
            'event_source' => 'legacy_mp_order',
            'event_type' => 'seller_revenue',
            'external_event_id' => sha1('int-legacy-'.$confirmedOrderNumber),
            'reference_number' => $confirmedOrderNumber,
            'event_date' => now()->subHours(5),
            'settlement_date' => now()->subHours(5),
            'amount' => 900,
            'currency' => 'TRY',
            'direction' => 'credit',
            'status' => 'posted',
        ]);

        OrderProfitSnapshot::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $confirmedOrder->id,
            'channel_order_item_id' => null,
            'profit_state' => 'confirmed',
            'gross_revenue' => 900,
            'net_receivable' => 700,
            'commission_total' => 0,
            'cargo_total' => 0,
            'service_fee_total' => 0,
            'withholding_total' => 0,
            'packaging_cost' => 0,
            'own_cargo_cost' => 0,
            'cogs_cost' => 0,
            'return_effect' => 0,
            'vat_effect' => 0,
            'estimated_profit' => 0,
            'confirmed_profit' => 180,
            'margin_percent' => 20,
            'calculated_at' => now(),
            'version' => 1,
        ]);

        $this->actingAs($user);

        Livewire::test(MarketplaceIntegrations::class)
            ->assertSet('selectedStoreId', $store->id)
            ->assertSee('Legacy backlog')
            ->assertSee('Backlog var')
            ->assertSee('Eski veri aktarım etkisi')
            ->assertSee('Kesine dönen')
            ->assertSee('Aktarım ekranına git')
            ->assertSee('marketplace:project-legacy-financials');
    }

    public function test_it_can_preview_selected_store_legacy_projection_from_integrations(): void
    {
        $user = User::factory()->create();
        ['store' => $store] = $this->createStoreGraph($user, 'LEGACY-PREVIEW');

        $period = MpPeriod::query()->create([
            'user_id' => $user->id,
            'seller_id' => $store->seller_id,
            'year' => (int) now()->year,
            'month' => (int) now()->month,
            'marketplace' => 'trendyol',
            'status' => 'completed',
        ]);

        $orderNumber = 'INT-LEG-PREV-'.random_int(100000, 999999);

        ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
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
            'net_hakedis' => 760,
            'projected_at' => null,
        ]);

        $this->actingAs($user);

        Livewire::test(MarketplaceIntegrations::class)
            ->assertSet('selectedStoreId', $store->id)
            ->call('previewSelectedStoreLegacyProjection')
            ->assertSet('flashMessageType', 'success')
            ->assertSet('legacyProjectionPreview.store_name', $store->store_name)
            ->assertSet('legacyProjectionPreview.projected_rows', 1)
            ->assertSet('legacyProjectionPreview.executed', false);
    }

    public function test_it_can_run_selected_store_legacy_projection_from_integrations(): void
    {
        $user = User::factory()->create();
        ['store' => $store] = $this->createStoreGraph($user, 'LEGACY-EXEC');

        $period = MpPeriod::query()->create([
            'user_id' => $user->id,
            'seller_id' => $store->seller_id,
            'year' => (int) now()->year,
            'month' => (int) now()->month,
            'marketplace' => 'trendyol',
            'status' => 'completed',
        ]);

        $orderNumber = 'INT-LEG-EXEC-'.random_int(100000, 999999);

        $channelOrder = ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'external_order_id' => $orderNumber,
            'order_number' => $orderNumber,
            'order_status' => 'Teslim Edildi',
            'ordered_at' => now()->subDay(),
        ]);

        $mpOrder = MpOrder::query()->create([
            'period_id' => $period->id,
            'order_number' => $orderNumber,
            'status' => 'Teslim Edildi',
            'order_date' => now()->subDay(),
            'payment_date' => now()->subHours(6)->toDateString(),
            'gross_amount' => 1000,
            'net_hakedis' => 760,
            'cargo_amount' => 25,
            'service_fee' => 10,
            'commission_amount' => 50,
            'withholding_tax' => 5,
            'projected_at' => null,
        ]);

        $this->actingAs($user);

        Livewire::test(MarketplaceIntegrations::class)
            ->assertSet('selectedStoreId', $store->id)
            ->call('runSelectedStoreLegacyProjection')
            ->assertSet('flashMessageType', 'success')
            ->assertSet('legacyProjectionPreview.executed', true)
            ->assertSet('legacyProjectionPreview.projected_rows', 1);

        $this->assertDatabaseHas('mp_orders', [
            'id' => $mpOrder->id,
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'source_marketplace' => 'trendyol',
        ]);

        $this->assertDatabaseHas('order_financial_events', [
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'channel_order_id' => $channelOrder->id,
            'event_source' => 'legacy_mp_order',
            'reference_number' => $orderNumber,
        ]);
    }

    public function test_it_surfaces_woocommerce_webhook_topic_guidance_in_integrations(): void
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Woo Topic Integrations Ltd.',
            'tax_number' => '6'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'woocommerce',
            'store_name' => 'WOO TOPIC INTEGRATIONS',
            'store_code' => 'WOO-TOPIC-INT-'.$suffix,
            'seller_id' => 'WOO-TOPIC-INT-'.$suffix,
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

        Livewire::test(MarketplaceIntegrations::class)
            ->assertSet('selectedStoreId', $store->id)
            ->assertSee('WooCommerce webhook topic seti güvenli değil')
            ->assertSee('önerilen WooCommerce webhook topic setini uygula');
    }

    public function test_it_surfaces_shopify_webhook_topic_guidance_in_integrations(): void
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Shopify Topic Integrations Ltd.',
            'tax_number' => '3'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'shopify',
            'store_name' => 'SHOPIFY TOPIC INTEGRATIONS',
            'store_code' => 'SHOP-INT-'.$suffix,
            'seller_id' => 'SHOP-INT-'.$suffix,
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

        Livewire::test(MarketplaceIntegrations::class)
            ->assertSet('selectedStoreId', $store->id)
            ->assertSee('Shopify webhook topic seti güvenli değil')
            ->assertSee('önerilen Shopify webhook topic setini uygula');
    }

    public function test_it_can_apply_recommended_shopify_webhook_topics_to_form(): void
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Shopify Topic Apply Ltd.',
            'tax_number' => '2'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'shopify',
            'store_name' => 'SHOPIFY TOPIC APPLY',
            'store_code' => 'SHOP-APPLY-'.$suffix,
            'seller_id' => 'SHOP-APPLY-'.$suffix,
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
            'webhook_enabled' => false,
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

        Livewire::test(MarketplaceIntegrations::class)
            ->assertSet('selectedStoreId', $store->id)
            ->call('applyRecommendedWebhookTopics')
            ->assertSet('syncForm.webhookEnabled', true)
            ->assertSet('syncForm.webhookTopics', IntegrationSyncProfile::recommendedShopifyWebhookTopics())
            ->assertSet('flashMessageType', 'success');
    }

    public function test_it_surfaces_shopify_safe_profile_guidance_in_integrations(): void
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Shopify Safe Integrations Ltd.',
            'tax_number' => '1'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'shopify',
            'store_name' => 'SHOPIFY SAFE INTEGRATIONS',
            'store_code' => 'SHOP-SAFE-INT-'.$suffix,
            'seller_id' => 'SHOP-SAFE-INT-'.$suffix,
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

        Livewire::test(MarketplaceIntegrations::class)
            ->assertSet('selectedStoreId', $store->id)
            ->assertSee('Shopify güvenli profilinden sapma var')
            ->assertSee('Entegrasyonlar ekranında güvenli Shopify profilini uygula');
    }

    public function test_it_can_apply_safe_defaults_for_existing_shopify_store(): void
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Shopify Apply Ltd.',
            'tax_number' => '5'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'shopify',
            'store_name' => 'SHOPIFY APPLY SAFE',
            'store_code' => 'SHOP-APPLY-SAFE-'.$suffix,
            'seller_id' => 'SHOP-APPLY-SAFE-'.$suffix,
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
            'backfill_mode' => 'custom',
            'backfill_custom_from' => now()->subDays(2),
            'backfill_custom_to' => now(),
            'orders_enabled' => true,
            'finance_enabled' => true,
            'products_enabled' => true,
            'webhook_enabled' => false,
            'price_push_enabled' => true,
            'stock_push_enabled' => true,
            'auto_match_enabled' => true,
            'barcode_fallback_enabled' => true,
            'strict_unique_match_enabled' => true,
            'nightly_repair_sync_enabled' => false,
            'max_parallel_jobs' => 4,
            'request_jitter_seconds' => 1,
            'extra_settings' => [
                'webhook_topics' => [],
            ],
        ]);

        $this->actingAs($user);

        Livewire::test(MarketplaceIntegrations::class)
            ->assertSet('selectedStoreId', $store->id)
            ->assertSee('Shopify güvenli profilinden sapma var')
            ->call('applyShopifySafeProfile')
            ->assertSet('syncForm.ordersPollMinutes', 20)
            ->assertSet('syncForm.financePollMinutes', 240)
            ->assertSet('syncForm.productsPollMinutes', 720)
            ->assertSet('syncForm.backfillMode', '7_days')
            ->assertSet('syncForm.webhookEnabled', true)
            ->assertSet('syncForm.webhookTopics', IntegrationSyncProfile::recommendedShopifyWebhookTopics())
            ->assertSet('syncForm.pricePushEnabled', false)
            ->assertSet('syncForm.stockPushEnabled', false)
            ->assertSet('syncForm.maxParallelJobs', 1)
            ->assertSet('syncForm.requestJitterSeconds', 10)
            ->assertSet('flashMessageType', 'success');
    }

    public function test_it_redirects_to_orders_for_legacy_financial_projection_guidance(): void
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Integrations Legacy Finance Ltd.',
            'tax_number' => '1'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'INTEGRATIONS LEGACY FIN',
            'store_code' => 'INT-LEG-'.$suffix,
            'seller_id' => 'INT-LEG-'.$suffix,
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

        $orderNumber = 'INT-LEGACY-'.$suffix;

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

        Livewire::test(MarketplaceIntegrations::class)
            ->assertSet('selectedStoreId', $store->id)
            ->assertSee('Legacy finans satirlari V2 ledger\'a tasinmamis')
            ->call('syncSelectedStoreGuidance')
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
            'name' => 'Zem Guidance Integrations Ltd.',
            'tax_number' => '4'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'ZEM '.$prefix,
            'store_code' => $prefix.'-'.$suffix,
            'seller_id' => $prefix.'-'.$suffix,
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
