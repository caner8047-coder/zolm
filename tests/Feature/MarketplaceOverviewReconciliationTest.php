<?php

namespace Tests\Feature;

use App\Livewire\MarketplaceOverview;
use App\Models\ChannelOrder;
use App\Models\ChannelOrderItem;
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
use Livewire\Livewire;
use Tests\TestCase;

class MarketplaceOverviewReconciliationTest extends TestCase
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

    public function test_it_builds_reconciliation_risk_stats_for_overview(): void
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Overview Ltd.',
            'tax_number' => '7' . $suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'ZEM OVERVIEW',
            'store_code' => 'ZEM-OVR-' . $suffix,
            'seller_id' => 'OVR-' . $suffix,
            'status' => 'active',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $this->createOrder($store, $legalEntity, 'OVR-MAT-' . $suffix, 1000, 10, [
            'gross_revenue' => 1000,
            'commission_total' => 100,
            'cargo_total' => 50,
            'service_fee_total' => 20,
            'withholding_total' => 10,
            'estimated_profit' => 400,
            'confirmed_profit' => 250,
        ], true);

        $this->createOrder($store, $legalEntity, 'OVR-WAIT-' . $suffix, 300, 10, [
            'gross_revenue' => 300,
            'commission_total' => 30,
            'cargo_total' => 0,
            'service_fee_total' => 0,
            'withholding_total' => 0,
            'estimated_profit' => 120,
            'confirmed_profit' => 0,
        ], false, 'estimated');

        $this->createOrder($store, $legalEntity, 'OVR-MISS-' . $suffix, 700, 12, null, true);

        $this->actingAs($user);

        $component = app(MarketplaceOverview::class);
        $stats = $component->reconciliationStats();

        $this->assertSame(3, $stats['total_orders']);
        $this->assertSame(1, $stats['material_orders']);
        $this->assertSame(1, $stats['waiting_orders']);
        $this->assertSame(1, $stats['snapshot_missing_orders']);
        $this->assertGreaterThan(0, $stats['total_profit_delta_abs']);
        $this->assertGreaterThan(0, $stats['total_deduction_delta_abs']);
    }

    public function test_it_shows_legacy_projection_effect_summary_in_overview(): void
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Overview Legacy Ltd.',
            'tax_number' => '5' . $suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'OVERVIEW LEGACY',
            'store_code' => 'OVR-LEG-' . $suffix,
            'seller_id' => 'OVR-LEG-' . $suffix,
            'status' => 'configured',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $period = MpPeriod::query()->create([
            'user_id' => $user->id,
            'seller_id' => $store->seller_id,
            'year' => (int) now()->year,
            'month' => (int) now()->month,
            'marketplace' => 'trendyol',
            'status' => 'completed',
        ]);

        $pendingOrderNumber = 'OVR-LEG-PEND-' . $suffix;
        $confirmedOrderNumber = 'OVR-LEG-CONF-' . $suffix;

        ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $legalEntity->id,
            'external_order_id' => $pendingOrderNumber,
            'order_number' => $pendingOrderNumber,
            'order_status' => 'Teslim Edildi',
            'ordered_at' => now()->subDay(),
        ]);

        $confirmedOrder = ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $legalEntity->id,
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
            'legal_entity_id' => $legalEntity->id,
            'source_marketplace' => 'trendyol',
            'order_number' => $confirmedOrderNumber,
            'status' => 'Teslim Edildi',
            'order_date' => now()->subDay(),
            'payment_date' => now()->subHours(6)->toDateString(),
            'gross_amount' => 900,
            'net_hakedis' => 720,
            'projected_at' => now()->subMinutes(12),
        ]);

        OrderFinancialEvent::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $legalEntity->id,
            'channel_order_id' => $confirmedOrder->id,
            'event_source' => 'legacy_mp_order',
            'event_type' => 'seller_revenue',
            'external_event_id' => sha1('overview-legacy-' . $suffix),
            'reference_number' => $confirmedOrderNumber,
            'event_date' => now()->subHours(6),
            'settlement_date' => now()->subHours(6),
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
            'net_receivable' => 720,
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

        Livewire::test(MarketplaceOverview::class)
            ->assertSee('Legacy Projection Etkisi')
            ->assertSee('Bekleyen')
            ->assertSee('Tamamlanan')
            ->assertSee('Kesin Sipariş')
            ->assertSee('1');
    }

    public function test_it_builds_store_level_legacy_projection_breakdown_for_overview(): void
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Overview Store Breakdown Ltd.',
            'tax_number' => '6' . $suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $storeA = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'LEGACY BREAKDOWN A',
            'store_code' => 'LEG-BRK-A-' . $suffix,
            'seller_id' => 'LEG-BRK-A-' . $suffix,
            'status' => 'configured',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $storeB = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'shopify',
            'store_name' => 'LEGACY BREAKDOWN B',
            'store_code' => 'LEG-BRK-B-' . $suffix,
            'seller_id' => 'LEG-BRK-B-' . $suffix,
            'status' => 'configured',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $periodA = MpPeriod::query()->create([
            'user_id' => $user->id,
            'seller_id' => $storeA->seller_id,
            'year' => (int) now()->year,
            'month' => (int) now()->month,
            'marketplace' => 'trendyol',
            'status' => 'completed',
        ]);

        $periodB = MpPeriod::query()->create([
            'user_id' => $user->id,
            'seller_id' => $storeB->seller_id,
            'year' => (int) now()->year,
            'month' => (int) now()->month,
            'marketplace' => 'shopify',
            'status' => 'completed',
        ]);

        $pendingAOne = 'LEG-A-PEND-1-' . $suffix;
        $pendingATwo = 'LEG-A-PEND-2-' . $suffix;
        $confirmedB = 'LEG-B-CONF-1-' . $suffix;

        ChannelOrder::query()->create([
            'store_id' => $storeA->id,
            'legal_entity_id' => $legalEntity->id,
            'external_order_id' => $pendingAOne,
            'order_number' => $pendingAOne,
            'order_status' => 'Teslim Edildi',
            'ordered_at' => now()->subDay(),
        ]);

        ChannelOrder::query()->create([
            'store_id' => $storeA->id,
            'legal_entity_id' => $legalEntity->id,
            'external_order_id' => $pendingATwo,
            'order_number' => $pendingATwo,
            'order_status' => 'Teslim Edildi',
            'ordered_at' => now()->subDay(),
        ]);

        $confirmedBOrder = ChannelOrder::query()->create([
            'store_id' => $storeB->id,
            'legal_entity_id' => $legalEntity->id,
            'external_order_id' => $confirmedB,
            'order_number' => $confirmedB,
            'order_status' => 'Delivered',
            'ordered_at' => now()->subDay(),
        ]);

        MpOrder::query()->create([
            'period_id' => $periodA->id,
            'order_number' => $pendingAOne,
            'status' => 'Teslim Edildi',
            'order_date' => now()->subDay(),
            'payment_date' => now()->subHours(6)->toDateString(),
            'gross_amount' => 1000,
            'net_hakedis' => 780,
            'projected_at' => null,
        ]);

        MpOrder::query()->create([
            'period_id' => $periodA->id,
            'order_number' => $pendingATwo,
            'status' => 'Teslim Edildi',
            'order_date' => now()->subDay(),
            'payment_date' => now()->subHours(6)->toDateString(),
            'gross_amount' => 900,
            'net_hakedis' => 700,
            'projected_at' => null,
        ]);

        MpOrder::query()->create([
            'period_id' => $periodB->id,
            'store_id' => $storeB->id,
            'legal_entity_id' => $legalEntity->id,
            'source_marketplace' => 'shopify',
            'order_number' => $confirmedB,
            'status' => 'Delivered',
            'order_date' => now()->subDay(),
            'payment_date' => now()->subHours(4)->toDateString(),
            'gross_amount' => 850,
            'net_hakedis' => 680,
            'projected_at' => now()->subMinutes(20),
        ]);

        OrderFinancialEvent::query()->create([
            'store_id' => $storeB->id,
            'legal_entity_id' => $legalEntity->id,
            'channel_order_id' => $confirmedBOrder->id,
            'event_source' => 'legacy_mp_order',
            'event_type' => 'seller_revenue',
            'external_event_id' => sha1('overview-store-breakdown-' . $suffix),
            'reference_number' => $confirmedB,
            'event_date' => now()->subHours(4),
            'settlement_date' => now()->subHours(4),
            'amount' => 850,
            'currency' => 'TRY',
            'direction' => 'credit',
            'status' => 'posted',
        ]);

        OrderProfitSnapshot::query()->create([
            'store_id' => $storeB->id,
            'channel_order_id' => $confirmedBOrder->id,
            'channel_order_item_id' => null,
            'profit_state' => 'confirmed',
            'gross_revenue' => 850,
            'net_receivable' => 680,
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
            'confirmed_profit' => 160,
            'margin_percent' => 18.82,
            'calculated_at' => now(),
            'version' => 1,
        ]);

        $this->actingAs($user);

        $component = app(MarketplaceOverview::class);
        $rows = $component->legacyProjectionStoreRows();

        $this->assertCount(2, $rows);
        $this->assertSame('LEGACY BREAKDOWN A', $rows[0]['store_name']);
        $this->assertSame(2, $rows[0]['pending_rows']);
        $this->assertSame('LEGACY BREAKDOWN B', $rows[1]['store_name']);
        $this->assertSame(1, $rows[1]['confirmed_orders']);

        Livewire::test(MarketplaceOverview::class)
            ->assertSee('Mağaza kırılımı')
            ->assertSee('LEGACY BREAKDOWN A')
            ->assertSee('LEGACY BREAKDOWN B');
    }

    public function test_it_builds_legacy_projection_routes_for_backlog_and_confirmed_focus(): void
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Overview Route Ltd.',
            'tax_number' => '8' . $suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'shopify',
            'store_name' => 'LEGACY ROUTE STORE',
            'store_code' => 'LEG-ROUTE-' . $suffix,
            'seller_id' => 'LEG-ROUTE-' . $suffix,
            'status' => 'configured',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $component = app(MarketplaceOverview::class);

        $row = [
            'store_id' => $store->id,
            'marketplace' => 'shopify',
        ];

        $this->assertSame(
            route('mp.orders', ['storeFilter' => $store->id]),
            $component->legacyProjectionOrdersRoute($row)
        );

        $this->assertSame(
            route('mp.finance', [
                'storeFilter' => $store->id,
                'marketplaceFilter' => 'shopify',
                'legacyProjectionFilter' => 'backlog',
            ]),
            $component->legacyProjectionFinanceRoute($row, 'backlog')
        );

        $this->assertSame(
            route('mp.finance', [
                'storeFilter' => $store->id,
                'marketplaceFilter' => 'shopify',
                'legacyProjectionFilter' => 'confirmed',
                'financialStateFilter' => 'ready',
            ]),
            $component->legacyProjectionFinanceRoute($row, 'confirmed')
        );
    }

    public function test_it_can_preview_legacy_projection_from_overview(): void
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Overview Preview Ltd.',
            'tax_number' => '9' . $suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'LEGACY PREVIEW STORE',
            'store_code' => 'LEG-PREV-' . $suffix,
            'seller_id' => 'LEG-PREV-' . $suffix,
            'status' => 'configured',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $period = MpPeriod::query()->create([
            'user_id' => $user->id,
            'seller_id' => $store->seller_id,
            'year' => (int) now()->year,
            'month' => (int) now()->month,
            'marketplace' => 'trendyol',
            'status' => 'completed',
        ]);

        $orderNumber = 'OVR-PREV-' . $suffix;

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
            'net_hakedis' => 760,
            'projected_at' => null,
        ]);

        $this->actingAs($user);

        Livewire::test(MarketplaceOverview::class)
            ->call('previewLegacyProjection', $store->id)
            ->assertSet('flashTone', 'success')
            ->assertSet("legacyProjectionPreviews.{$store->id}.projected_rows", 1)
            ->assertSet("legacyProjectionPreviews.{$store->id}.store_name", 'LEGACY PREVIEW STORE');
    }

    public function test_it_builds_pilot_rollout_rows_for_overview(): void
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Pilot Rollout Ltd.',
            'tax_number' => '4' . $suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'woocommerce',
            'store_name' => 'PILOT ROLLOUT STORE',
            'store_code' => 'PILOT-' . $suffix,
            'seller_id' => 'PILOT-' . $suffix,
            'status' => 'configured',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        IntegrationConnection::query()->create([
            'store_id' => $store->id,
            'provider' => 'woocommerce',
            'auth_type' => 'basic',
            'api_base_url' => 'https://pilot.example.com/wp-json/wc/v3',
            'webhook_secret' => 'pilot-secret',
            'credentials_encrypted' => [
                'api_key' => 'ck_test',
                'api_secret' => 'cs_test',
            ],
            'status' => 'active',
        ]);

        IntegrationSyncProfile::query()->create(array_merge(
            IntegrationSyncProfile::defaultsForMarketplace('woocommerce'),
            ['store_id' => $store->id]
        ));

        IntegrationSyncRun::query()->create([
            'store_id' => $store->id,
            'sync_type' => 'orders',
            'trigger_type' => 'smoke_test',
            'status' => 'completed',
            'items_received' => 12,
            'items_created' => 12,
            'items_updated' => 0,
            'notes_json' => [
                'diagnostics' => [
                    'warning_count' => 0,
                ],
            ],
        ]);

        $period = MpPeriod::query()->create([
            'user_id' => $user->id,
            'seller_id' => $store->seller_id,
            'year' => (int) now()->year,
            'month' => (int) now()->month,
            'marketplace' => 'woocommerce',
            'status' => 'completed',
        ]);

        $legacyOrderNumber = 'PILOT-LEGACY-' . $suffix;

        ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $legalEntity->id,
            'external_order_id' => $legacyOrderNumber,
            'order_number' => $legacyOrderNumber,
            'order_status' => 'completed',
            'ordered_at' => now()->subDay(),
        ]);

        MpOrder::query()->create([
            'period_id' => $period->id,
            'order_number' => $legacyOrderNumber,
            'status' => 'completed',
            'order_date' => now()->subDay(),
            'payment_date' => now()->subHours(4)->toDateString(),
            'gross_amount' => 1000,
            'net_hakedis' => 780,
            'projected_at' => null,
        ]);

        $this->actingAs($user);

        $component = app(MarketplaceOverview::class);
        $rows = $component->pilotRolloutRows();

        $this->assertCount(1, $rows);
        $this->assertSame('PILOT ROLLOUT STORE', $rows[0]['store_name']);
        $this->assertSame('legacy_projection', $rows[0]['stage']);
        $this->assertSame(1, $rows[0]['legacy_pending_rows']);
        $this->assertSame('Tamamlandı · temiz', $component->pilotRolloutSmokeLabel($rows[0]));

        Livewire::test(MarketplaceOverview::class)
            ->assertSee('Pilot Canlıya Geçiş')
            ->assertSee('PILOT ROLLOUT STORE')
            ->assertSee('Aktar');
    }

    public function test_it_can_run_legacy_projection_from_overview(): void
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Overview Execute Ltd.',
            'tax_number' => '1' . $suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'LEGACY EXEC STORE',
            'store_code' => 'LEG-EXEC-' . $suffix,
            'seller_id' => 'LEG-EXEC-' . $suffix,
            'status' => 'configured',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $period = MpPeriod::query()->create([
            'user_id' => $user->id,
            'seller_id' => $store->seller_id,
            'year' => (int) now()->year,
            'month' => (int) now()->month,
            'marketplace' => 'trendyol',
            'status' => 'completed',
        ]);

        $orderNumber = 'OVR-EXEC-' . $suffix;

        $channelOrder = ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $legalEntity->id,
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

        Livewire::test(MarketplaceOverview::class)
            ->call('runLegacyProjection', $store->id)
            ->assertSet('flashTone', 'success')
            ->assertSet("legacyProjectionPreviews.{$store->id}.executed", true);

        $this->assertDatabaseHas('mp_orders', [
            'id' => $mpOrder->id,
            'store_id' => $store->id,
            'legal_entity_id' => $legalEntity->id,
            'source_marketplace' => 'trendyol',
        ]);

        $this->assertDatabaseHas('order_financial_events', [
            'store_id' => $store->id,
            'legal_entity_id' => $legalEntity->id,
            'channel_order_id' => $channelOrder->id,
            'event_source' => 'legacy_mp_order',
            'reference_number' => $orderNumber,
        ]);
    }

    protected function createOrder(
        MarketplaceStore $store,
        LegalEntity $legalEntity,
        string $orderNumber,
        float $grossAmount,
        float $commissionRate,
        ?array $snapshot,
        bool $withFinance,
        string $profitState = 'confirmed'
    ): void {
        $order = ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $legalEntity->id,
            'external_order_id' => $orderNumber,
            'order_number' => $orderNumber,
            'order_status' => 'Delivered',
            'customer_name' => 'Overview Test',
            'ordered_at' => now(),
        ]);

        ChannelOrderItem::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $order->id,
            'external_line_id' => $orderNumber . '-LINE',
            'stock_code' => 'SKU-' . $orderNumber,
            'barcode' => 'BAR-' . $orderNumber,
            'product_name' => 'Overview Test Ürünü',
            'quantity' => 1,
            'unit_price' => $grossAmount,
            'gross_amount' => $grossAmount,
            'billable_amount' => $grossAmount,
            'commission_rate' => $commissionRate,
            'line_status' => 'Delivered',
            'is_matched' => true,
            'match_source' => 'manual',
        ]);

        if ($snapshot !== null) {
            OrderProfitSnapshot::query()->create(array_merge([
                'store_id' => $store->id,
                'channel_order_id' => $order->id,
                'channel_order_item_id' => null,
                'profit_state' => $profitState,
                'net_receivable' => 0,
                'packaging_cost' => 0,
                'own_cargo_cost' => 0,
                'cogs_cost' => 0,
                'return_effect' => 0,
                'vat_effect' => 0,
                'margin_percent' => 0,
                'calculated_at' => now(),
                'version' => 1,
            ], $snapshot));
        }

        if ($withFinance) {
            OrderFinancialEvent::query()->create([
                'store_id' => $store->id,
                'legal_entity_id' => $legalEntity->id,
                'channel_order_id' => $order->id,
                'event_source' => 'sync',
                'event_type' => 'seller_revenue',
                'external_event_id' => $orderNumber . '-REV',
                'reference_number' => $orderNumber . '-REV',
                'event_date' => now(),
                'settlement_date' => now(),
                'amount' => $grossAmount,
                'currency' => 'TRY',
                'direction' => 'credit',
                'status' => 'settled',
            ]);
        }
    }
}
