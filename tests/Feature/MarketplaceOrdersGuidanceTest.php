<?php

namespace Tests\Feature;

use App\Jobs\SyncMarketplaceDataJob;
use App\Livewire\MarketplaceOrders;
use App\Models\ChannelOrder;
use App\Models\ChannelOrderItem;
use App\Models\ChannelOrderPackage;
use App\Models\IntegrationConnection;
use App\Models\IntegrationSyncProfile;
use App\Models\IntegrationSyncRun;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\MpOperationalOrder;
use App\Models\MpOperationalOrderItem;
use App\Models\MpOrder;
use App\Models\MpPeriod;
use App\Models\OrderFinancialEvent;
use App\Models\OrderProfitSnapshot;
use App\Models\User;
use App\Services\MpSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class MarketplaceOrdersGuidanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_hides_diagnostic_warning_band_and_keeps_sync_action_in_workspace(): void
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $entity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Orders Guidance Ltd.',
            'tax_number' => '3'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $entity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'ZEM ORDERS GUIDE',
            'store_code' => 'ORD-GUIDE-'.$suffix,
            'seller_id' => 'ORD-GUIDE-'.$suffix,
            'status' => 'configured',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        IntegrationSyncRun::query()->create([
            'store_id' => $store->id,
            'sync_type' => 'orders',
            'trigger_type' => 'smoke_test',
            'status' => 'completed',
            'items_received' => 4,
            'notes_json' => [
                'smoke_test' => true,
                'diagnostics' => [
                    'missing_stock_code_count' => 4,
                    'missing_barcode_count' => 2,
                    'warnings' => ['Stok kodu eksik'],
                ],
            ],
        ]);

        $this->actingAs($user);

        Livewire::test(MarketplaceOrders::class)
            ->assertSee('Sipariş senkronunu başlat')
            ->assertDontSee('Ürün eşleşme alanları eksik')
            ->assertDontSee('Kritik ve Uyarılar')
            ->assertDontSee('Eşleştirme Merkezi');
    }

    public function test_it_can_focus_orders_list_from_top_guidance(): void
    {
        [$user, $store] = $this->createStoreGraph('5');

        IntegrationSyncRun::query()->create([
            'store_id' => $store->id,
            'sync_type' => 'orders',
            'trigger_type' => 'smoke_test',
            'status' => 'completed',
            'items_received' => 4,
            'notes_json' => [
                'smoke_test' => true,
                'diagnostics' => [
                    'missing_stock_code_count' => 4,
                    'missing_barcode_count' => 2,
                ],
            ],
        ]);

        $this->actingAs($user);

        Livewire::test(MarketplaceOrders::class)
            ->call('focusTopGuidance')
            ->assertSet('marketplaceFilter', 'trendyol')
            ->assertSet('storeFilter', (string) $store->id)
            ->assertSet('matchStateFilter', 'needs_match');
    }

    public function test_it_can_queue_order_sync_from_workspace_action(): void
    {
        Queue::fake();

        [$user, $store] = $this->createStoreGraph('6');

        IntegrationSyncRun::query()->create([
            'store_id' => $store->id,
            'sync_type' => 'orders',
            'trigger_type' => 'smoke_test',
            'status' => 'completed',
            'items_received' => 2,
            'notes_json' => [
                'smoke_test' => true,
                'diagnostics' => [
                    'missing_order_number_count' => 2,
                ],
            ],
        ]);

        $this->actingAs($user);

        Livewire::test(MarketplaceOrders::class)
            ->call('syncOrders')
            ->assertSet('actionMessageTone', 'success');

        $this->assertDatabaseHas('integration_sync_runs', [
            'store_id' => $store->id,
            'sync_type' => 'orders',
            'trigger_type' => 'manual',
            'status' => 'queued',
        ]);

        Queue::assertPushed(SyncMarketplaceDataJob::class);
    }

    public function test_it_redirects_to_orders_route_for_legacy_financial_projection_guidance(): void
    {
        [$user, $store] = $this->createStoreGraph('8');

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

        $orderNumber = 'ORD-LEGACY-'.random_int(100000, 999999);

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
            'net_hakedis' => 800,
        ]);

        $this->actingAs($user);

        Livewire::test(MarketplaceOrders::class)
            ->assertDontSee('Legacy finans satirlari V2 ledger\'a tasinmamis')
            ->call('syncTopGuidance')
            ->assertRedirect(route('mp.orders', ['storeFilter' => $store->id]));
    }

    public function test_it_prefills_legacy_projection_store_and_shows_preview_for_filtered_store(): void
    {
        [$user, $store] = $this->createStoreGraph('9');

        $period = MpPeriod::query()->create([
            'user_id' => $user->id,
            'seller_id' => $store->seller_id,
            'year' => (int) now()->year,
            'month' => (int) now()->month,
            'marketplace' => 'trendyol',
            'status' => 'completed',
        ]);

        $orderNumber = 'ORD-PREVIEW-'.random_int(100000, 999999);

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
            'net_hakedis' => 800,
        ]);

        $this->actingAs($user);

        Livewire::withQueryParams(['storeFilter' => (string) $store->id])
            ->test(MarketplaceOrders::class)
            ->assertSet('legacyProjectionStoreId', (string) $store->id)
            ->assertSee('Aday')
            ->assertSee((string) $store->store_name)
            ->assertSee('1');
    }

    public function test_it_hides_legacy_projection_guidance_from_orders_workspace(): void
    {
        [$user, $store] = $this->createStoreGraph('10');

        $period = MpPeriod::query()->create([
            'user_id' => $user->id,
            'seller_id' => $store->seller_id,
            'year' => (int) now()->year,
            'month' => (int) now()->month,
            'marketplace' => 'trendyol',
            'status' => 'completed',
        ]);

        $pendingOrderNumber = 'ORD-LEG-CARD-PEND-'.random_int(100000, 999999);
        $confirmedOrderNumber = 'ORD-LEG-CARD-CONF-'.random_int(100000, 999999);

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
            'net_hakedis' => 780,
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
            'projected_at' => now()->subMinutes(10),
        ]);

        OrderFinancialEvent::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'channel_order_id' => $confirmedOrder->id,
            'event_source' => 'legacy_mp_order',
            'event_type' => 'seller_revenue',
            'external_event_id' => sha1('orders-legacy-card-'.$confirmedOrderNumber),
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
            'confirmed_profit' => 190,
            'margin_percent' => 21.11,
            'calculated_at' => now(),
            'version' => 1,
        ]);

        $this->actingAs($user);

        Livewire::test(MarketplaceOrders::class)
            ->assertSee('Sipariş senkronunu başlat')
            ->assertDontSee('Legacy finans backlogu mağaza bazında görünüyor')
            ->assertDontSee('Filtrele ve İncele')
            ->assertDontSee('Bekleyen 1')
            ->assertDontSee('Kesine dönen 1');
    }

    public function test_it_can_focus_legacy_projection_backlog_from_focus_card(): void
    {
        [$user, $store] = $this->createStoreGraph('11');

        $period = MpPeriod::query()->create([
            'user_id' => $user->id,
            'seller_id' => $store->seller_id,
            'year' => (int) now()->year,
            'month' => (int) now()->month,
            'marketplace' => 'trendyol',
            'status' => 'completed',
        ]);

        $orderNumber = 'ORD-LEG-FOCUS-'.random_int(100000, 999999);

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
            'net_hakedis' => 800,
            'projected_at' => null,
        ]);

        $this->actingAs($user);

        Livewire::test(MarketplaceOrders::class)
            ->call('focusLegacyProjectionCard')
            ->assertSet('marketplaceFilter', 'trendyol')
            ->assertSet('storeFilter', (string) $store->id)
            ->assertSet('legacyProjectionStoreId', (string) $store->id)
            ->assertSet('actionMessageTone', 'success');
    }

    public function test_it_can_preview_legacy_financial_projection_from_orders(): void
    {
        [$user, $store] = $this->createStoreGraph('12');

        $period = MpPeriod::query()->create([
            'user_id' => $user->id,
            'seller_id' => $store->seller_id,
            'year' => (int) now()->year,
            'month' => (int) now()->month,
            'marketplace' => 'trendyol',
            'status' => 'completed',
        ]);

        $orderNumber = 'ORD-LEG-PREVIEW-'.random_int(100000, 999999);

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
            'net_hakedis' => 800,
            'projected_at' => null,
        ]);

        $this->actingAs($user);

        Livewire::test(MarketplaceOrders::class)
            ->set('legacyProjectionStoreId', (string) $store->id)
            ->call('previewLegacyFinancials')
            ->assertSet('actionMessageTone', 'success')
            ->assertSet('legacyProjectionResult.executed', false)
            ->assertSet('legacyProjectionResult.projected_rows', 1)
            ->assertSee('marketplace:project-legacy-financials');
    }

    public function test_it_can_run_legacy_financial_projection_from_orders(): void
    {
        [$user, $store] = $this->createStoreGraph('13');

        $period = MpPeriod::query()->create([
            'user_id' => $user->id,
            'seller_id' => $store->seller_id,
            'year' => (int) now()->year,
            'month' => (int) now()->month,
            'marketplace' => 'trendyol',
            'status' => 'completed',
        ]);

        $orderNumber = 'ORD-LEG-RUN-'.random_int(100000, 999999);

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

        Livewire::test(MarketplaceOrders::class)
            ->set('legacyProjectionStoreId', (string) $store->id)
            ->call('projectLegacyFinancials')
            ->assertSet('actionMessageTone', 'success')
            ->assertSet('legacyProjectionResult.executed', true)
            ->assertSet('legacyProjectionResult.projected_rows', 1);

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

    public function test_it_preserves_legacy_order_table_density_and_detail_blocks(): void
    {
        [$user, $store] = $this->createStoreGraph('14');

        $period = MpPeriod::query()->create([
            'user_id' => $user->id,
            'seller_id' => $store->seller_id,
            'year' => (int) now()->year,
            'month' => (int) now()->month,
            'marketplace' => 'trendyol',
            'status' => 'completed',
        ]);

        $orderNumber = 'ORD-DETAIL-'.random_int(100000, 999999);

        $channelOrder = ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'external_order_id' => $orderNumber,
            'order_number' => $orderNumber,
            'order_status' => 'Teslim Edildi',
            'customer_name' => 'Merve Eskici',
            'customer_email' => 'merve@example.test',
            'customer_phone' => '05551112233',
            'billing_name' => 'Merve Eskici',
            'billing_tax_number' => '1234567890',
            'shipment_country' => 'Türkiye',
            'shipment_city' => 'Ankara',
            'shipment_district' => 'Çankaya',
            'ordered_at' => now()->subDay(),
            'delivered_at' => now()->subHours(2),
        ]);

        $package = ChannelOrderPackage::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $channelOrder->id,
            'external_package_id' => 'PKG-'.$orderNumber,
            'package_number' => 'PKT-'.$orderNumber,
            'package_status' => 'delivered',
            'cargo_company' => 'Aras Kargo',
            'cargo_tracking_number' => 'TRK-'.$orderNumber,
            'cargo_barcode' => 'BAR-'.$orderNumber,
            'cargo_desi' => 3.6,
            'shipped_at' => now()->subHours(20),
            'delivered_at' => now()->subHours(2),
        ]);

        ChannelOrderItem::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $channelOrder->id,
            'channel_order_package_id' => $package->id,
            'external_line_id' => 'LINE-'.$orderNumber,
            'stock_code' => 'ZEM-001',
            'barcode' => '1907584520',
            'product_name' => 'Lines Puf, Teddy Kumaş Sütlü Kahve',
            'quantity' => 2,
            'unit_price' => 999.90,
            'gross_amount' => 1999.80,
            'discount_amount' => 0,
            'marketplace_discount_amount' => 0,
            'billable_amount' => 1999.80,
            'commission_rate' => 14.5,
            'line_status' => 'delivered',
            'is_matched' => true,
            'match_source' => 'stock_code',
        ]);

        OrderProfitSnapshot::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $channelOrder->id,
            'channel_order_item_id' => null,
            'profit_state' => 'confirmed',
            'gross_revenue' => 1999.80,
            'net_receivable' => 1699.63,
            'commission_total' => 289.98,
            'cargo_total' => 0,
            'service_fee_total' => 0,
            'withholding_total' => 0,
            'packaging_cost' => 0,
            'own_cargo_cost' => 0,
            'cogs_cost' => 1034.76,
            'return_effect' => 0,
            'vat_effect' => 0,
            'estimated_profit' => 218.79,
            'confirmed_profit' => 218.79,
            'margin_percent' => 14.5,
            'calculated_at' => now(),
            'version' => 1,
        ]);

        $legacyOrder = MpOperationalOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'order_number' => $orderNumber,
            'package_number' => 'PKT:'.$orderNumber,
            'order_date' => now()->subDay(),
            'delivery_date' => now()->subHours(2),
            'source_marketplace' => 'trendyol',
            'deadline_date' => now()->subHours(6),
            'cargo_delivery_date' => now()->subHours(20),
            'invoice_date' => now()->subHours(4),
            'customer_name' => 'Merve Eskici',
            'customer_city' => 'Ankara',
            'customer_district' => 'Çankaya',
            'customer_phone' => '05551112233',
            'billing_name' => 'Merve Eskici',
            'email' => 'merve@example.test',
            'customer_age' => '30-34',
            'customer_gender' => 'Kadın',
            'customer_order_count' => '1.Sipariş',
            'country' => 'Türkiye',
            'company_name' => 'Zem Test',
            'tax_office' => 'Çankaya',
            'tax_number' => '1234567890',
            'cargo_company' => 'Aras Kargo',
            'tracking_number' => 'TRK-'.$orderNumber,
            'cargo_code' => 'CARGO-'.$orderNumber,
            'status' => 'Teslim Edildi',
            'invoice_number' => 'FTR-'.$orderNumber,
            'is_corporate_invoice' => 'Evet',
            'is_invoiced' => 'Evet',
            'total_gross_amount' => 1999.80,
            'total_discount' => 0,
        ]);

        MpOperationalOrderItem::query()->create([
            'operational_order_id' => $legacyOrder->id,
            'order_number' => $orderNumber,
            'barcode' => '1907584520',
            'stock_code' => '1PUFZEM00610',
            'product_name' => 'Lines Puf, Teddy Kumaş Sütlü Kahve Zemlines, One Size',
            'brand' => 'Zem',
            'quantity' => 2,
            'unit_price' => 999.90,
            'sale_price' => 1999.80,
            'discount_amount' => 0,
            'trendyol_discount' => 0,
            'billable_amount' => 1999.80,
            'commission_rate' => 14.5,
            'cargo_desi' => 36,
        ]);

        MpOrder::query()->create([
            'period_id' => $period->id,
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'order_number' => $orderNumber,
            'barcode' => '1907584520',
            'stock_code' => '1PUFZEM00610',
            'product_name' => 'Lines Puf, Teddy Kumaş Sütlü Kahve Zemlines, One Size',
            'quantity' => 2,
            'order_date' => now()->subDay(),
            'delivery_date' => now()->subHours(2),
            'payment_date' => now()->toDateString(),
            'status' => 'Teslim Edildi',
            'source_marketplace' => 'trendyol',
            'list_price' => 999.90,
            'sale_price' => 1999.80,
            'gross_amount' => 1999.80,
            'discount_amount' => 0,
            'campaign_discount' => 0,
            'commission_rate' => 14.5,
            'commission_amount' => 289.98,
            'commission_tax' => 0,
            'cargo_company' => 'Aras Kargo',
            'cargo_desi' => 36,
            'cargo_amount' => 0,
            'cargo_tax' => 0,
            'service_fee' => 0,
            'withholding_tax' => 0,
            'net_hakedis' => 1699.63,
            'cogs_at_time' => 1034.76,
            'packaging_cost_at_time' => 0,
            'own_cargo_cost_at_time' => 0,
            'calculated_net_profit' => 218.79,
        ]);

        $this->actingAs($user);

        Livewire::test(MarketplaceOrders::class)
            ->assertSee('Muhasebe')
            ->assertSee('Hakediş')
            ->assertSee('Hakediş hesabı')
            ->assertSee('Kârlılık hesabı')
            ->assertSee('Maliyet getirisi')
            ->assertSee('Müşteri Bilgileri')
            ->assertSee('Fatura Bilgileri')
            ->assertSee('Lojistik & Tarihler', false)
            ->assertSee('Muhasebe Modülü Verileri')
            ->assertSee('Siparişteki Ürünler');
    }

    public function test_it_renders_row_level_order_action_menu_items(): void
    {
        [$user, $store] = $this->createStoreGraph('11');

        $order = ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'external_order_id' => 'ORD-ACTION-'.random_int(100000, 999999),
            'order_number' => 'ORD-ACTION-'.random_int(100000, 999999),
            'order_status' => 'Created',
            'customer_name' => 'Aksiyon Test',
            'ordered_at' => now()->subHour(),
        ]);

        ChannelOrderPackage::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $order->id,
            'external_package_id' => 'PKG-ACTION-'.random_int(100000, 999999),
            'package_number' => 'PKG-ACTION-'.random_int(100000, 999999),
            'package_status' => 'Created',
        ]);

        $this->actingAs($user);

        Livewire::test(MarketplaceOrders::class)
            ->assertSee('Sipariş işlemleri')
            ->assertSee('Siparişi düzenle')
            ->assertSee('Detayı aç')
            ->assertSee('Siparişi çoğalt')
            ->assertSee('Siparişi sil')
            ->assertSee('Siparişi yenile')
            ->assertSee('Kargoyu yenile')
            ->assertSee('Finansı yenile')
            ->assertSee('Kârı hesapla');
    }

    public function test_it_can_open_and_save_order_edit_modal(): void
    {
        [$user, $store] = $this->createStoreGraph('12');

        $order = ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'external_order_id' => 'ORD-EDIT-'.random_int(100000, 999999),
            'order_number' => 'ORD-EDIT-'.random_int(100000, 999999),
            'order_status' => 'Created',
            'customer_name' => 'İlk Müşteri',
            'ordered_at' => now()->subHour(),
        ]);

        $item = ChannelOrderItem::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $order->id,
            'external_line_id' => 'LINE-EDIT-'.random_int(100000, 999999),
            'product_name' => 'Eski Ürün',
            'barcode' => '8690000000001',
            'stock_code' => 'SKU-OLD-1',
            'quantity' => 1,
            'unit_price' => 120,
            'gross_amount' => 120,
            'discount_amount' => 0,
            'billable_amount' => 120,
            'line_status' => 'Created',
        ]);

        $this->actingAs($user);

        Livewire::test(MarketplaceOrders::class)
            ->call('openEditOrder', $order->id)
            ->assertSet('showEditOrderModal', true)
            ->set('orderForm.customer_name', 'Güncel Müşteri')
            ->set('orderForm.order_status', 'Onaylandı')
            ->set('orderItemsForm.0.product_name', 'Güncel Ürün')
            ->set('orderItemsForm.0.barcode', '8690000009999')
            ->set('orderItemsForm.0.stock_code', 'SKU-NEW-1')
            ->set('orderItemsForm.0.quantity', 3)
            ->set('orderItemsForm.0.gross_amount', 360)
            ->call('saveOrderEdits')
            ->assertSet('showEditOrderModal', false)
            ->assertSet('actionMessageTone', 'success');

        $this->assertDatabaseHas('channel_orders', [
            'id' => $order->id,
            'customer_name' => 'Güncel Müşteri',
            'order_status' => 'Onaylandı',
        ]);

        $this->assertDatabaseHas('channel_order_items', [
            'id' => $item->id,
            'product_name' => 'Güncel Ürün',
            'barcode' => '8690000009999',
            'stock_code' => 'SKU-NEW-1',
            'quantity' => 3,
        ]);
    }

    public function test_it_can_duplicate_and_delete_order_from_row_actions(): void
    {
        [$user, $store] = $this->createStoreGraph('13');

        $order = ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'external_order_id' => 'ORD-CLONE-'.random_int(100000, 999999),
            'order_number' => 'ORD-CLONE-'.random_int(100000, 999999),
            'order_status' => 'Created',
            'customer_name' => 'Kopya Test',
            'ordered_at' => now()->subHour(),
        ]);

        $package = ChannelOrderPackage::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $order->id,
            'external_package_id' => 'PKG-CLONE-'.random_int(100000, 999999),
            'package_number' => 'PKG-CLONE-'.random_int(100000, 999999),
            'package_status' => 'Created',
        ]);

        ChannelOrderItem::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $order->id,
            'channel_order_package_id' => $package->id,
            'external_line_id' => 'LINE-CLONE-'.random_int(100000, 999999),
            'product_name' => 'Test Ürünü',
            'quantity' => 1,
            'line_status' => 'Created',
        ]);

        $this->actingAs($user);

        Livewire::test(MarketplaceOrders::class)
            ->call('duplicateOrder', $order->id)
            ->assertSet('actionMessageTone', 'success');

        $this->assertDatabaseCount('channel_orders', 2);
        $this->assertSame(2, ChannelOrderPackage::query()->count());
        $this->assertSame(2, ChannelOrderItem::query()->count());

        Livewire::test(MarketplaceOrders::class)
            ->call('deleteOrder', $order->id)
            ->assertSet('actionMessageTone', 'success');

        $this->assertDatabaseMissing('channel_orders', ['id' => $order->id]);
    }

    public function test_it_can_assign_filter_and_customize_order_color_labels(): void
    {
        [$user, $store] = $this->createStoreGraph('15');

        $labeledOrder = ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'external_order_id' => 'ORD-LABEL-'.random_int(100000, 999999),
            'order_number' => 'ORD-LABEL-'.random_int(100000, 999999),
            'order_status' => 'Created',
            'customer_name' => 'Etiketli Sipariş',
            'ordered_at' => now()->subHour(),
        ]);

        $plainOrder = ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'external_order_id' => 'ORD-PLAIN-'.random_int(100000, 999999),
            'order_number' => 'ORD-PLAIN-'.random_int(100000, 999999),
            'order_status' => 'Created',
            'customer_name' => 'Etiketsiz Sipariş',
            'ordered_at' => now()->subMinutes(30),
        ]);

        $this->actingAs($user);

        Livewire::test(MarketplaceOrders::class)
            ->call('assignOrderColorLabel', $labeledOrder->id, 'label_2')
            ->assertSet('actionMessageTone', 'success')
            ->assertSee('Renk Etiketi')
            ->assertSee('Etiketleri Yönet');

        $this->assertDatabaseHas('channel_orders', [
            'id' => $labeledOrder->id,
            'color_label_key' => 'label_2',
        ]);

        Livewire::test(MarketplaceOrders::class)
            ->set('labelFilter', 'label_2')
            ->assertSee($labeledOrder->order_number)
            ->assertDontSee($plainOrder->order_number)
            ->assertSee('Finans');

        Livewire::test(MarketplaceOrders::class)
            ->call('openOrderLabelManager')
            ->assertSet('showOrderLabelManager', true)
            ->set('orderLabelForm.label_2.name', 'VIP Finans')
            ->set('orderLabelForm.label_2.color', '#123456')
            ->call('saveOrderLabelSettings')
            ->assertSet('showOrderLabelManager', false)
            ->assertSet('actionMessageTone', 'success');

        $savedLabels = (new MpSettingsService($user->id))->getArray('marketplace_orders.v2.color_labels', []);

        $this->assertSame('VIP Finans', data_get($savedLabels, 'label_2.name'));
        $this->assertSame('#123456', data_get($savedLabels, 'label_2.color'));

        Livewire::test(MarketplaceOrders::class)
            ->assertSee('VIP Finans');
    }

    public function test_it_hides_help_tips_when_user_disables_them(): void
    {
        [$user] = $this->createStoreGraph('16');

        (new MpSettingsService($user->id))->set('ui.help_tips_enabled', false);

        $this->actingAs($user);

        Livewire::test(MarketplaceOrders::class)
            ->assertDontSee('Hazır olma oranı hakkında bilgi')
            ->assertDontSee('Canlı sipariş tablosu hakkında bilgi');
    }

    public function test_it_renders_the_productized_order_workspace_with_today_sales_trend(): void
    {
        [$user, $store] = $this->createStoreGraph('17');

        $morningOrder = ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'external_order_id' => 'ORD-TREND-MORNING',
            'order_number' => 'ORD-TREND-MORNING',
            'order_status' => 'Created',
            'ordered_at' => now()->startOfDay()->addHours(9)->addMinutes(15),
        ]);

        ChannelOrderItem::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $morningOrder->id,
            'external_line_id' => 'LINE-TREND-MORNING',
            'product_name' => 'Sabah Ürünü',
            'quantity' => 3,
            'line_status' => 'Created',
        ]);

        $afternoonOrder = ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'external_order_id' => 'ORD-TREND-AFTERNOON',
            'order_number' => 'ORD-TREND-AFTERNOON',
            'order_status' => 'Created',
            'ordered_at' => now()->startOfDay()->addHours(14)->addMinutes(40),
        ]);

        ChannelOrderItem::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $afternoonOrder->id,
            'external_line_id' => 'LINE-TREND-AFTERNOON',
            'product_name' => 'Öğleden Sonra Ürünü',
            'quantity' => 4,
            'line_status' => 'Created',
        ]);

        $cancelledOrder = ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'external_order_id' => 'ORD-TREND-CANCELLED',
            'order_number' => 'ORD-TREND-CANCELLED',
            'order_status' => 'Cancelled',
            'ordered_at' => now()->startOfDay()->addHours(16),
        ]);

        ChannelOrderItem::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $cancelledOrder->id,
            'external_line_id' => 'LINE-TREND-CANCELLED',
            'product_name' => 'İptal Edilen Ürün',
            'quantity' => 11,
            'line_status' => 'Cancelled',
        ]);

        $this->actingAs($user);

        Livewire::test(MarketplaceOrders::class)
            ->assertSee('Sipariş Çalışma Alanı')
            ->assertSee('Sipariş Yönetimi')
            ->assertSee('Pazaryeri siparişlerini, kargo hazırlığını ve finans durumunu tek çalışma alanında yönetin.')
            ->assertSee('Sipariş senkronunu başlat')
            ->assertSee('İçe Aktar')
            ->assertSee('Eşleştir')
            ->assertSee('Entegrasyonlar')
            ->assertSee('Çıktı Ayarları')
            ->assertSee('Bugün saatlere göre satılan ürün adedi grafiği')
            ->assertSee('7 ürün')
            ->assertSee('Siparişleri filtrele')
            ->assertDontSee('Finans kapsamı')
            ->assertDontSee('Eşleşme riski')
            ->assertDontSee('Hazır olma oranı')
            ->assertDontSee('Sipariş Ritim')
            ->assertDontSee('Çalışma Alanı Özeti')
            ->assertDontSee('Kritik ve Uyarılar')
            ->assertDontSee('Venture');
    }

    /**
     * @return array{0: User, 1: MarketplaceStore}
     */
    protected function createStoreGraph(string $prefix): array
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $entity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Orders Guidance Ltd.',
            'tax_number' => $prefix.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $entity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'ZEM ORDERS GUIDE',
            'store_code' => 'ORD-GUIDE-'.$suffix,
            'seller_id' => 'ORD-GUIDE-'.$suffix,
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
            'api_base_url' => 'https://apigw.trendyol.com/',
            'status' => 'configured',
        ]);

        return [$user, $store];
    }
}
