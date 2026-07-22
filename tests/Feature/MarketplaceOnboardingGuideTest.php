<?php

namespace Tests\Feature;

use App\Livewire\MarketplaceOverview;
use App\Models\ChannelListing;
use App\Models\ChannelOrder;
use App\Models\ChannelOrderItem;
use App\Models\IntegrationConnection;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\MpProduct;
use App\Models\OrderFinancialEvent;
use App\Models\OrderProfitSnapshot;
use App\Models\User;
use App\Services\Marketplace\MarketplaceOnboardingGuideService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class MarketplaceOnboardingGuideTest extends TestCase
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

    public function test_it_points_to_store_connection_when_company_exists_but_store_is_missing(): void
    {
        $user = User::factory()->create();
        $this->createLegalEntity($user, 'ONBOARD-STORE');

        $summary = app(MarketplaceOnboardingGuideService::class)->summaryForUser($user->id);
        $steps = collect($summary['steps'])->keyBy('key');

        $this->assertSame('store_connection', $summary['primary_action']['key']);
        $this->assertSame('completed', $steps['legal_entity']['status']);
        $this->assertSame('action', $steps['store_connection']['status']);
        $this->assertSame('waiting', $steps['product_sync']['status']);
    }

    public function test_it_points_to_product_sync_when_store_is_connected_but_products_are_missing(): void
    {
        $user = User::factory()->create();
        $legalEntity = $this->createLegalEntity($user, 'ONBOARD-PRODUCT');
        $this->createStoreGraph($user, $legalEntity, 'ONBOARD-PRODUCT');

        $summary = app(MarketplaceOnboardingGuideService::class)->summaryForUser($user->id);
        $steps = collect($summary['steps'])->keyBy('key');

        $this->assertSame('product_sync', $summary['primary_action']['key']);
        $this->assertSame('completed', $steps['store_connection']['status']);
        $this->assertSame('action', $steps['product_sync']['status']);
    }

    public function test_it_points_to_costs_when_product_costs_are_missing(): void
    {
        $user = User::factory()->create();
        $legalEntity = $this->createLegalEntity($user, 'ONBOARD-COST');
        $store = $this->createStoreGraph($user, $legalEntity, 'ONBOARD-COST');
        $product = $this->createProduct($user, 'ONBOARD-COST', 0, 0);
        $this->createListing($store, $product, 'ONBOARD-COST');
        $this->createOrderWithItem($store, $legalEntity, $product, 'ONBOARD-COST');

        $summary = app(MarketplaceOnboardingGuideService::class)->summaryForUser($user->id);
        $steps = collect($summary['steps'])->keyBy('key');

        $this->assertSame('costs', $summary['primary_action']['key']);
        $this->assertSame('completed', $steps['product_sync']['status']);
        $this->assertSame('action', $steps['costs']['status']);
        $this->assertSame(1, $summary['metrics']['missing_cost_lines']);
    }

    public function test_it_points_to_finance_when_orders_exist_without_financial_events(): void
    {
        $user = User::factory()->create();
        $legalEntity = $this->createLegalEntity($user, 'ONBOARD-FINANCE');
        $store = $this->createStoreGraph($user, $legalEntity, 'ONBOARD-FINANCE');
        $product = $this->createProduct($user, 'ONBOARD-FINANCE', 120, 15);
        $this->createListing($store, $product, 'ONBOARD-FINANCE');
        $this->createOrderWithItem($store, $legalEntity, $product, 'ONBOARD-FINANCE');

        $summary = app(MarketplaceOnboardingGuideService::class)->summaryForUser($user->id);
        $steps = collect($summary['steps'])->keyBy('key');

        $this->assertSame('finance', $summary['primary_action']['key']);
        $this->assertSame('completed', $steps['costs']['status']);
        $this->assertSame('completed', $steps['orders']['status']);
        $this->assertSame('action', $steps['finance']['status']);
    }

    public function test_it_marks_onboarding_complete_when_core_profit_inputs_are_ready(): void
    {
        $user = User::factory()->create();
        $legalEntity = $this->createLegalEntity($user, 'ONBOARD-DONE');
        $store = $this->createStoreGraph($user, $legalEntity, 'ONBOARD-DONE');
        $product = $this->createProduct($user, 'ONBOARD-DONE', 120, 15, 45, 6);
        $this->createListing($store, $product, 'ONBOARD-DONE');
        $order = $this->createOrderWithItem($store, $legalEntity, $product, 'ONBOARD-DONE');
        $this->createFinancialEvent($store, $legalEntity, $order, 'ONBOARD-DONE');
        $this->createProfitSnapshot($store, $order);

        $summary = app(MarketplaceOnboardingGuideService::class)->summaryForUser($user->id);

        $this->assertSame('completed', $summary['status']);
        $this->assertSame(100, $summary['readiness_percent']);
        $this->assertSame('profit_center', $summary['primary_action']['key']);

        $this->actingAs($user);

        Livewire::test(MarketplaceOverview::class)
            ->assertSee('Veri Hazırlık Rehberi')
            ->assertSee('Kâr Kokpitini aç');
    }

    protected function createLegalEntity(User $user, string $prefix): LegalEntity
    {
        $suffix = (string) random_int(100000, 999999);

        return LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem '.$prefix.' Ltd.',
            'tax_number' => '9'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);
    }

    protected function createStoreGraph(User $user, LegalEntity $legalEntity, string $prefix): MarketplaceStore
    {
        $suffix = (string) random_int(100000, 999999);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'trendyol',
            'store_name' => $prefix,
            'store_code' => $prefix.'-'.$suffix,
            'seller_id' => $prefix.'-'.$suffix,
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

        return $store;
    }

    protected function createProduct(
        User $user,
        string $prefix,
        float $cogs,
        float $packagingCost,
        float $cargoCost = 0,
        float $desi = 0,
    ): MpProduct {
        $suffix = (string) random_int(100000, 999999);

        return MpProduct::query()->create([
            'user_id' => $user->id,
            'barcode' => 'BC-'.$prefix.'-'.$suffix,
            'stock_code' => 'SKU-'.$prefix.'-'.$suffix,
            'product_name' => 'Onboarding Ürün '.$suffix,
            'cogs' => $cogs,
            'packaging_cost' => $packagingCost,
            'cargo_cost' => $cargoCost,
            'desi' => $desi,
            'vat_rate' => 20,
            'status' => 'active',
        ]);
    }

    protected function createListing(MarketplaceStore $store, MpProduct $product, string $prefix): ChannelListing
    {
        return ChannelListing::query()->create([
            'store_id' => $store->id,
            'mp_product_id' => $product->id,
            'listing_id' => 'LIST-'.$prefix.'-'.random_int(100000, 999999),
            'listing_status' => 'active',
            'sale_price' => 1000,
            'currency' => 'TRY',
            'stock_quantity' => 10,
        ]);
    }

    protected function createOrderWithItem(
        MarketplaceStore $store,
        LegalEntity $legalEntity,
        MpProduct $product,
        string $prefix,
    ): ChannelOrder {
        $suffix = (string) random_int(100000, 999999);

        $order = ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $legalEntity->id,
            'external_order_id' => 'EXT-'.$prefix.'-'.$suffix,
            'order_number' => 'ORD-'.$prefix.'-'.$suffix,
            'order_status' => 'Teslim Edildi',
            'ordered_at' => now()->subDay(),
        ]);

        ChannelOrderItem::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $order->id,
            'mp_product_id' => $product->id,
            'external_line_id' => 'LINE-'.$prefix.'-'.$suffix,
            'stock_code' => $product->stock_code,
            'barcode' => $product->barcode,
            'product_name' => $product->product_name,
            'quantity' => 1,
            'unit_price' => 1000,
            'gross_amount' => 1000,
            'billable_amount' => 1000,
            'commission_rate' => 20,
            'vat_rate' => 20,
            'line_status' => 'delivered',
            'is_matched' => true,
        ]);

        return $order;
    }

    protected function createFinancialEvent(
        MarketplaceStore $store,
        LegalEntity $legalEntity,
        ChannelOrder $order,
        string $prefix,
    ): void {
        OrderFinancialEvent::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $legalEntity->id,
            'channel_order_id' => $order->id,
            'event_source' => 'onboarding-test',
            'event_type' => 'seller_revenue',
            'external_event_id' => sha1($prefix.'-'.$order->id),
            'reference_number' => $order->order_number,
            'event_date' => now()->subHours(2),
            'settlement_date' => now()->subHour(),
            'amount' => 1000,
            'currency' => 'TRY',
            'direction' => 'credit',
            'status' => 'posted',
        ]);
    }

    protected function createProfitSnapshot(MarketplaceStore $store, ChannelOrder $order): void
    {
        OrderProfitSnapshot::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $order->id,
            'channel_order_item_id' => null,
            'profit_state' => 'confirmed',
            'gross_revenue' => 1000,
            'net_receivable' => 780,
            'commission_total' => 200,
            'cargo_total' => 45,
            'service_fee_total' => 0,
            'withholding_total' => 10,
            'packaging_cost' => 15,
            'own_cargo_cost' => 45,
            'cogs_cost' => 120,
            'return_effect' => 0,
            'vat_effect' => 0,
            'estimated_profit' => 600,
            'confirmed_profit' => 610,
            'margin_percent' => 61,
            'calculated_at' => now(),
            'version' => 1,
        ]);
    }
}
