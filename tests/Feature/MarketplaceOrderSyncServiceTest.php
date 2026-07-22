<?php

namespace Tests\Feature;

use App\Livewire\MarketplaceOrders;
use App\Models\ChannelListing;
use App\Models\ChannelOrder;
use App\Models\ChannelOrderItem;
use App\Models\ChannelOrderPackage;
use App\Models\ChannelProduct;
use App\Models\IntegrationSyncProfile;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\MpProduct;
use App\Models\User;
use App\Services\Marketplace\MarketplaceOrderSyncService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketplaceOrderSyncServiceTest extends TestCase
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

    public function test_it_normalizes_pazarama_approved_payloads_before_persisting(): void
    {
        $store = $this->createPazaramaStore();

        app(MarketplaceOrderSyncService::class)->sync($store, [[
            'order' => [
                'external_order_id' => '986089186',
                'order_number' => '986089186',
                'order_status' => 'Shipped',
                'ordered_at' => '2026-04-20 19:30:00',
                'raw_payload' => [
                    'orderStatus' => 3,
                ],
            ],
            'package' => [
                'external_package_id' => '20550',
                'package_number' => '20550',
                'package_status' => 'Shipped',
                'cargo_company' => 'Surat',
                'cargo_tracking_number' => null,
                'shipped_at' => '2026-04-24 19:00:00',
                'raw_payload' => [
                    'orderStatus' => 3,
                ],
            ],
            'items' => [[
                'external_line_id' => 'LINE-986089186-1',
                'product_name' => 'Liva Bohem Sandikli Puf',
                'quantity' => 1,
                'unit_price' => 1419.90,
                'gross_amount' => 1419.90,
                'line_status' => 'Shipped',
                'raw_payload' => [
                    'orderItemStatus' => 3,
                    'orderItemStatusName' => 'Siparişiniz Alındı',
                    'estimatedShippingDate' => '2026-04-24T19:00:00+03:00',
                    'trackingNumber' => null,
                ],
            ]],
        ]]);

        $order = ChannelOrder::query()->where('store_id', $store->id)->firstOrFail();
        $package = ChannelOrderPackage::query()->where('store_id', $store->id)->firstOrFail();
        $item = ChannelOrderItem::query()->where('store_id', $store->id)->firstOrFail();

        $this->assertSame('approved', $order->order_status);
        $this->assertSame('approved', $package->package_status);
        $this->assertSame('approved', $item->line_status);
        $this->assertNull($package->shipped_at);
        $this->assertSame('2026-04-24T19:00:00+03:00', data_get($package->raw_payload, 'estimatedShippingDate'));
        $this->assertSame(
            '2026-04-24 19:00:00',
            (new MarketplaceOrders)->packageShipmentAt($package, 'pazarama')?->format('Y-m-d H:i:s')
        );
    }

    public function test_it_keeps_pazarama_shipped_payloads_when_tracking_exists(): void
    {
        $store = $this->createPazaramaStore('2');

        app(MarketplaceOrderSyncService::class)->sync($store, [[
            'order' => [
                'external_order_id' => '629342353',
                'order_number' => '629342353',
                'order_status' => 'Shipped',
                'ordered_at' => '2026-04-18 23:23:00',
                'raw_payload' => [
                    'orderStatus' => 5,
                ],
            ],
            'package' => [
                'external_package_id' => '20549',
                'package_number' => '20549',
                'package_status' => 'Shipped',
                'cargo_company' => 'Surat',
                'cargo_tracking_number' => 'TRK-20549',
                'shipped_at' => '2026-04-22 19:00:00',
                'raw_payload' => [
                    'trackingNumber' => 'TRK-20549',
                ],
            ],
            'items' => [[
                'external_line_id' => 'LINE-629342353-1',
                'product_name' => 'Test Product',
                'quantity' => 1,
                'unit_price' => 2224.90,
                'gross_amount' => 2224.90,
                'line_status' => 'Shipped',
                'raw_payload' => [
                    'orderItemStatusName' => 'Kargoya Verildi',
                    'trackingNumber' => 'TRK-20549',
                ],
            ]],
        ]]);

        $order = ChannelOrder::query()->where('store_id', $store->id)->firstOrFail();
        $package = ChannelOrderPackage::query()->where('store_id', $store->id)->firstOrFail();
        $item = ChannelOrderItem::query()->where('store_id', $store->id)->firstOrFail();

        $this->assertSame('shipped', $order->order_status);
        $this->assertSame('shipped', $package->package_status);
        $this->assertSame('shipped', $item->line_status);
        $this->assertNotNull($package->shipped_at);
        $this->assertSame('2026-04-22 19:00:00', $package->shipped_at?->format('Y-m-d H:i:s'));
    }

    public function test_it_matches_woocommerce_order_items_from_variation_id_when_sku_is_missing(): void
    {
        [$store, $product, $listing] = $this->createWooCommerceListingGraph();

        app(MarketplaceOrderSyncService::class)->sync($store, [[
            'order' => [
                'external_order_id' => '16767',
                'order_number' => '16767',
                'order_status' => 'processing',
                'ordered_at' => '2026-04-27 10:19:00',
            ],
            'package' => [
                'external_package_id' => '16767',
                'package_number' => '16767',
                'package_status' => 'processing',
            ],
            'items' => [[
                'external_line_id' => '6974',
                'stock_code' => '13560',
                'product_name' => 'Zem Jarvis Bohem Sandıklı Orta Sehpa Puf - Kırık Beyaz',
                'quantity' => 1,
                'unit_price' => 1819.90,
                'gross_amount' => 1819.90,
                'billable_amount' => 1819.90,
                'line_status' => 'active',
                'raw_payload' => [
                    'id' => 6974,
                    'sku' => '',
                    'product_id' => 13560,
                    'variation_id' => 14134,
                    'name' => 'Zem Jarvis Bohem Sandıklı Orta Sehpa Puf - Kırık Beyaz',
                ],
            ]],
        ]]);

        $item = ChannelOrderItem::query()->where('store_id', $store->id)->firstOrFail();

        $this->assertSame($listing->id, $item->channel_listing_id);
        $this->assertSame($product->id, $item->mp_product_id);
        $this->assertTrue((bool) $item->is_matched);
        $this->assertSame('channel_listing', $item->match_source);
    }

    protected function createPazaramaStore(string $suffix = '1'): MarketplaceStore
    {
        $user = User::factory()->create();
        $token = $suffix.'-'.random_int(100000, 999999);

        $entity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Pazarama Test Entity '.$token,
            'tax_number' => '99'.str_pad(preg_replace('/\D+/', '', $token), 8, '0', STR_PAD_LEFT),
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        return MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $entity->id,
            'marketplace' => 'pazarama',
            'store_name' => 'Pazarama Test Store '.$token,
            'store_code' => 'PZR-'.$token,
            'seller_id' => 'PZR-SELLER-'.$token,
            'status' => 'connected',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);
    }

    /**
     * @return array{0: MarketplaceStore, 1: MpProduct, 2: ChannelListing}
     */
    protected function createWooCommerceListingGraph(): array
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $entity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Woo Test Entity '.$suffix,
            'tax_number' => '88'.str_pad($suffix, 8, '0', STR_PAD_LEFT),
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $entity->id,
            'marketplace' => 'woocommerce',
            'store_name' => 'Woo Test Store',
            'store_code' => 'WOO-'.$suffix,
            'seller_id' => 'WOO-'.$suffix,
            'status' => 'connected',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        IntegrationSyncProfile::query()->create([
            'store_id' => $store->id,
            ...IntegrationSyncProfile::defaultsForMarketplace('woocommerce'),
        ]);

        $product = MpProduct::query()->create([
            'user_id' => $user->id,
            'product_name' => 'Jarvis Bohem Sandıklı Orta Sehpa Puf, Kırık Beyaz',
            'stock_code' => '1PUFZEM00607',
            'barcode' => '869'.$suffix,
            'sale_price' => 1819.90,
            'cogs' => 600,
            'packaging_cost' => 40,
            'cargo_cost' => 80,
            'stock_quantity' => 5,
        ]);

        $channelProduct = ChannelProduct::query()->create([
            'store_id' => $store->id,
            'external_product_id' => '14134',
            'external_parent_id' => '13560',
            'stock_code' => '1PUFZEM00607',
            'title' => 'Zem Jarvis Bohem Sandıklı Orta Sehpa Puf - Kırık Beyaz',
            'raw_payload' => [
                'id' => 14134,
                'parent' => [
                    'id' => 13560,
                ],
            ],
        ]);

        $listing = ChannelListing::query()->create([
            'store_id' => $store->id,
            'channel_product_id' => $channelProduct->id,
            'mp_product_id' => $product->id,
            'listing_id' => '14134',
            'listing_status' => 'publish',
            'sale_price' => 1819.90,
            'stock_quantity' => 5,
            'currency' => 'TRY',
        ]);

        return [$store, $product, $listing];
    }
}
