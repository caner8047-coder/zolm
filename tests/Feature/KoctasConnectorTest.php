<?php

namespace Tests\Feature;

use App\Models\ChannelListing;
use App\Models\ChannelProduct;
use App\Models\IntegrationConnection;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\Connectors\KoctasConnector;
use App\Services\Marketplace\MarketplaceConnectorManager;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KoctasConnectorTest extends TestCase
{
    public function test_manager_resolves_koctas_connector(): void
    {
        $manager = app(MarketplaceConnectorManager::class);
        $connector = $manager->resolve('koctas');

        $this->assertInstanceOf(KoctasConnector::class, $connector);
        $this->assertSame('Koçtaş', $connector->displayName());
        $this->assertTrue($connector->capabilities()['orders']);
        $this->assertTrue($connector->capabilities()['products']);
        $this->assertFalse($connector->capabilities()['finance']);
        $this->assertTrue($connector->capabilities()['price_push']);
        $this->assertTrue($connector->capabilities()['stock_push']);
    }

    public function test_it_uses_mirakl_account_endpoint_for_koctas_connection_test(): void
    {
        Http::fake([
            'https://koctas.mirakl.net/api/account*' => Http::response([
                'shop_id' => 778,
                'shop_name' => 'Koçtaş Demo',
            ], 200),
        ]);

        $store = $this->makeStore('778');

        $result = app(KoctasConnector::class)->testConnection($store);

        $this->assertTrue($result['ok']);
        $this->assertSame(778, data_get($result, 'meta.shop_id'));
        $this->assertSame('Koçtaş Demo', data_get($result, 'meta.shop_name'));

        Http::assertSent(fn ($request) => $request->url() === 'https://koctas.mirakl.net/api/account'
            && $request->hasHeader('Authorization', 'koctas-key'));
    }

    public function test_it_normalizes_koctas_orders_from_mirakl_payload(): void
    {
        Http::fake([
            'https://koctas.mirakl.net/api/orders*' => Http::response([
                'orders' => [[
                    'order_id' => 'KOC-1001',
                    'order_reference_for_seller' => 'KOC-1001',
                    'order_state' => 'SHIPPING',
                    'created_date' => '2026-04-03T09:15:00+03:00',
                    'currency_iso_code' => 'TRY',
                    'customer' => [
                        'firstname' => 'Ayse',
                        'lastname' => 'Demir',
                        'email' => 'ayse@example.com',
                    ],
                    'customer_shipping_address' => [
                        'phone' => '05550000000',
                        'city' => 'Istanbul',
                        'state' => 'Kadikoy',
                        'country_iso_code' => 'TR',
                    ],
                    'customer_billing_address' => [
                        'firstname' => 'Ayse',
                        'lastname' => 'Demir',
                        'company' => 'Ayse Demir',
                        'tax_identification_number' => '1234567890',
                    ],
                    'shipping_type_label' => 'Kargo',
                    'order_lines' => [[
                        'order_line_id' => 'LINE-1',
                        'offer_sku' => 'KCT-SKU-1',
                        'product_sku' => '869000000001',
                        'product_title' => 'Koçtaş Masa',
                        'quantity' => 2,
                        'price_unit' => 150.25,
                        'price' => 300.50,
                        'total_price' => 300.50,
                        'tax_rate' => 20,
                        'order_line_state' => 'SHIPPING',
                    ]],
                ]],
                'total_count' => 1,
            ], 200),
        ]);

        $result = app(KoctasConnector::class)->pullOrders($this->makeStore('778'), [
            'start_date' => '2026-04-01T00:00:00+03:00',
            'end_date' => '2026-04-04T00:00:00+03:00',
        ]);

        $this->assertCount(1, $result['items']);
        $this->assertSame('KOC-1001', data_get($result, 'items.0.order.order_number'));
        $this->assertSame('Ayse Demir', data_get($result, 'items.0.order.customer_name'));
        $this->assertSame('KOC-1001', data_get($result, 'items.0.package.external_package_id'));
        $this->assertSame('KCT-SKU-1', data_get($result, 'items.0.items.0.stock_code'));
        $this->assertSame(150.25, data_get($result, 'items.0.items.0.unit_price'));
        $this->assertSame(300.50, data_get($result, 'items.0.items.0.gross_amount'));
    }

    public function test_it_normalizes_koctas_offers_as_products(): void
    {
        Http::fake([
            'https://koctas.mirakl.net/api/offers*' => Http::response([
                'offers' => [[
                    'offer_id' => 'OFF-1',
                    'shop_sku' => 'KCT-SKU-1',
                    'product_sku' => '869000000001',
                    'product_title' => 'Koçtaş Masa',
                    'description' => 'Koçtaş Masa',
                    'quantity' => 7,
                    'price' => 999.90,
                    'currency_iso_code' => 'TRY',
                    'active' => true,
                    'product_references' => [[
                        'type' => 'EAN',
                        'value' => '869000000001',
                    ]],
                    'brand' => 'Koçtaş Home',
                    'category_label' => 'Mobilya',
                    'updated_date' => '2026-04-03T10:00:00+03:00',
                ]],
                'total_count' => 1,
            ], 200),
        ]);

        $result = app(KoctasConnector::class)->pullProducts($this->makeStore('778'), [
            'start_date' => '2026-04-01T00:00:00+03:00',
            'end_date' => '2026-04-04T00:00:00+03:00',
        ]);

        $this->assertCount(1, $result['items']);
        $this->assertSame('869000000001', data_get($result, 'items.0.product.external_product_id'));
        $this->assertSame('KCT-SKU-1', data_get($result, 'items.0.product.stock_code'));
        $this->assertSame('869000000001', data_get($result, 'items.0.product.barcode'));
        $this->assertSame('OFF-1', data_get($result, 'items.0.listing.listing_id'));
        $this->assertSame('active', data_get($result, 'items.0.listing.listing_status'));
        $this->assertSame(999.90, data_get($result, 'items.0.listing.sale_price'));
    }

    public function test_it_pushes_koctas_price_via_price_import(): void
    {
        Http::fake([
            'https://koctas.mirakl.net/api/offers/pricing/imports*' => Http::response([
                'import_id' => 'price-import-1',
            ], 202),
        ]);

        $result = app(KoctasConnector::class)->pushPrice($this->makeListing(), 799.90);

        $this->assertSame('queued', $result['status']);
        $this->assertSame('price-import-1', $result['batch_request_id']);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/offers/pricing/imports')
            && $request->hasHeader('Authorization', 'koctas-key')
            && str_contains($request->body(), 'offer-sku')
            && str_contains($request->body(), 'KCT-SKU-1')
            && str_contains($request->body(), '799.90'));
    }

    public function test_it_pushes_koctas_stock_via_stock_import(): void
    {
        Http::fake([
            'https://koctas.mirakl.net/api/offers/stock/imports*' => Http::response([
                'import_id' => 'stock-import-1',
            ], 202),
        ]);

        $result = app(KoctasConnector::class)->pushStock($this->makeListing(), 12);

        $this->assertSame('queued', $result['status']);
        $this->assertSame('stock-import-1', $result['batch_request_id']);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/offers/stock/imports')
            && $request->hasHeader('Authorization', 'koctas-key')
            && str_contains($request->body(), 'offer-sku')
            && str_contains($request->body(), 'KCT-SKU-1')
            && str_contains($request->body(), '12')
            && str_contains($request->body(), 'update'));
    }

    protected function makeStore(?string $sellerId = '778'): MarketplaceStore
    {
        $store = new MarketplaceStore([
            'marketplace' => 'koctas',
            'store_name' => 'Koçtaş Test',
            'seller_id' => $sellerId,
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
        ]);

        $store->setRelation('connection', new IntegrationConnection([
            'provider' => 'koctas',
            'auth_type' => 'api_key_secret',
            'credentials_encrypted' => [
                'api_key' => 'koctas-key',
                'api_secret' => 'koctas-secret',
            ],
            'api_base_url' => '',
            'status' => 'configured',
        ]));

        return $store;
    }

    protected function makeListing(): ChannelListing
    {
        $store = $this->makeStore('778');
        $product = new ChannelProduct([
            'external_product_id' => '869000000001',
            'stock_code' => 'KCT-SKU-1',
            'barcode' => '869000000001',
            'raw_payload' => [
                'shop_sku' => 'KCT-SKU-1',
            ],
        ]);

        $listing = new ChannelListing([
            'listing_id' => 'OFF-1',
        ]);

        $listing->id = 901;
        $listing->setRelation('store', $store);
        $listing->setRelation('channelProduct', $product);

        return $listing;
    }
}
