<?php

namespace Tests\Feature;

use App\Models\ChannelOrder;
use App\Models\IntegrationSyncProfile;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\MpOperationalOrder;
use App\Models\MpOperationalOrderItem;
use App\Models\MpProduct;
use App\Models\User;
use App\Services\Marketplace\LegacyOperationalProjectionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LegacyOperationalProjectionServiceTest extends TestCase
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

    public function test_it_projects_legacy_operational_orders_into_channel_projection(): void
    {
        [$store, $operationalOrder, $product] = $this->createGraph();

        $result = app(LegacyOperationalProjectionService::class)->projectOperationalOrders(
            $store,
            MpOperationalOrder::query()->with('items.product')->whereKey($operationalOrder->id)->get(),
        );

        $this->assertSame(1, $result['projected_orders']);
        $this->assertNotEmpty($result['impacted_order_ids']);

        $channelOrder = ChannelOrder::query()
            ->with(['packages', 'items', 'profitSnapshots'])
            ->where('store_id', $store->id)
            ->where('order_number', $operationalOrder->order_number)
            ->first();

        $this->assertNotNull($channelOrder);
        $this->assertSame('legacy_excel', $channelOrder->commercial_type);
        $this->assertCount(1, $channelOrder->packages);
        $this->assertCount(1, $channelOrder->items);
        $this->assertCount(1, $channelOrder->profitSnapshots);
        $this->assertSame($product->id, $channelOrder->items->first()->mp_product_id);
        $this->assertTrue((bool) $channelOrder->items->first()->is_matched);

        $operationalOrder->refresh();
        $this->assertSame($store->id, $operationalOrder->store_id);
        $this->assertSame($store->legal_entity_id, $operationalOrder->legal_entity_id);
        $this->assertNotNull($operationalOrder->projected_at);
    }

    /**
     * @return array{0: MarketplaceStore, 1: MpOperationalOrder, 2: MpProduct}
     */
    protected function createGraph(): array
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $entity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Legacy Projection Ltd.',
            'tax_number' => '4' . $suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $entity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'LEGACY PROJECTION',
            'store_code' => 'LEG-' . $suffix,
            'seller_id' => 'LEGACY-' . $suffix,
            'status' => 'configured',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        IntegrationSyncProfile::query()->create(array_merge(
            ['store_id' => $store->id],
            IntegrationSyncProfile::defaultsForMarketplace('trendyol'),
        ));

        $product = MpProduct::query()->create([
            'user_id' => $user->id,
            'barcode' => 'LEGACY-BARCODE-' . $suffix,
            'stock_code' => 'LEGACY-STOCK-' . $suffix,
            'product_name' => 'Legacy Ürün',
            'cogs' => 500,
            'packaging_cost' => 25,
            'cargo_cost' => 80,
            'vat_rate' => 20,
            'status' => 'active',
        ]);

        $order = MpOperationalOrder::query()->create([
            'order_number' => 'ORD-' . $suffix,
            'package_number' => 'PKT-' . $suffix,
            'order_date' => now()->subHour(),
            'delivery_date' => now(),
            'customer_name' => 'Test Müşteri',
            'customer_city' => 'İstanbul',
            'customer_district' => 'Kadıköy',
            'customer_phone' => '5551234567',
            'billing_name' => 'Test Müşteri',
            'cargo_company' => 'Trendyol Express',
            'tracking_number' => 'TRK-' . $suffix,
            'cargo_code' => 'CRG-' . $suffix,
            'status' => 'Teslim Edildi',
            'total_gross_amount' => 2199.90,
            'total_discount' => 100.00,
        ]);

        MpOperationalOrderItem::query()->create([
            'operational_order_id' => $order->id,
            'order_number' => $order->order_number,
            'barcode' => $product->barcode,
            'stock_code' => $product->stock_code,
            'product_name' => 'Legacy Ürün',
            'quantity' => 1,
            'unit_price' => 2299.90,
            'sale_price' => 2199.90,
            'discount_amount' => 100.00,
            'trendyol_discount' => 0,
            'billable_amount' => 2099.90,
            'commission_rate' => 10,
            'synced_vat_rate' => 20,
        ]);

        return [$store, $order, $product];
    }
}
