<?php

namespace Tests\Feature;

use App\Models\ChannelListing;
use App\Models\ChannelProduct;
use App\Models\IntegrationConnection;
use App\Models\IntegrationPushRun;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\Connectors\WooCommerceConnector;
use App\Services\Marketplace\MarketplaceConnectorManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WooCommerceConnectorTest extends TestCase
{
    public function test_manager_resolves_woocommerce_connector(): void
    {
        $connector = app(MarketplaceConnectorManager::class)->resolve('woocommerce');

        $this->assertInstanceOf(WooCommerceConnector::class, $connector);
        $this->assertTrue($connector->capabilities()['orders']);
        $this->assertTrue($connector->capabilities()['products']);
        $this->assertTrue($connector->capabilities()['webhooks']);
        $this->assertTrue($connector->capabilities()['price_push']);
        $this->assertTrue($connector->capabilities()['stock_push']);
        $this->assertFalse($connector->capabilities()['finance']);
    }

    public function test_it_uses_consumer_key_and_secret_for_connection_test(): void
    {
        Http::fake([
            'https://shop.example.com/wp-json/wc/v3/products*' => Http::response([
                ['id' => 101, 'name' => 'Deneme'],
            ], 200, ['X-WP-TotalPages' => '1']),
        ]);

        $store = $this->makeStore();

        $result = app(WooCommerceConnector::class)->testConnection($store);

        $this->assertTrue($result['ok']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://shop.example.com/wp-json/wc/v3/products?per_page=1&page=1&_fields=id'
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('ck_test:cs_test'));
        });
    }

    public function test_it_normalizes_woocommerce_products(): void
    {
        Http::fake([
            'https://shop.example.com/wp-json/wc/v3/products*' => Http::response([
                [
                    'id' => 101,
                    'name' => 'ZEM Koltuk',
                    'sku' => 'WC-STK-1',
                    'status' => 'publish',
                    'price' => '1499.90',
                    'regular_price' => '1699.90',
                    'manage_stock' => true,
                    'stock_quantity' => 8,
                    'date_created_gmt' => '2026-03-20T10:00:00',
                    'categories' => [
                        ['name' => 'Mobilya'],
                        ['name' => 'Koltuk'],
                    ],
                ],
            ], 200, ['X-WP-TotalPages' => '1']),
        ]);

        $store = $this->makeStore();

        $result = app(WooCommerceConnector::class)->pullProducts($store);

        $this->assertCount(1, $result['items']);
        $this->assertSame('WC-STK-1', data_get($result, 'items.0.product.stock_code'));
        $this->assertSame('101', data_get($result, 'items.0.listing.listing_id'));
        $this->assertSame(1499.90, data_get($result, 'items.0.listing.sale_price'));
        $this->assertSame(8, data_get($result, 'items.0.listing.stock_quantity'));

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'per_page=25')
                && str_contains($request->url(), '_fields=')
                && str_contains($request->url(), 'after=');
        });
    }

    public function test_it_normalizes_woocommerce_orders(): void
    {
        Http::fake([
            'https://shop.example.com/wp-json/wc/v3/orders*' => Http::response([
                [
                    'id' => 901,
                    'number' => '901',
                    'status' => 'processing',
                    'date_created_gmt' => '2026-03-20T10:00:00',
                    'billing' => [
                        'first_name' => 'Ayse',
                        'last_name' => 'Demir',
                        'email' => 'ayse@example.com',
                        'phone' => '05550000000',
                        'company' => '',
                        'country' => 'TR',
                        'city' => 'Ankara',
                        'state' => 'Cankaya',
                    ],
                    'shipping' => [
                        'first_name' => 'Ayse',
                        'last_name' => 'Demir',
                        'country' => 'TR',
                        'city' => 'Ankara',
                        'state' => 'Cankaya',
                    ],
                    'shipping_lines' => [
                        ['method_title' => 'Yurtici', 'method_id' => 'flat_rate'],
                    ],
                    'line_items' => [
                        [
                            'id' => 3001,
                            'product_id' => 101,
                            'sku' => 'WC-STK-1',
                            'name' => 'ZEM Koltuk',
                            'quantity' => 2,
                            'subtotal' => '2000.00',
                            'total' => '1800.00',
                        ],
                    ],
                ],
            ], 200, ['X-WP-TotalPages' => '1']),
        ]);

        $store = $this->makeStore();

        $result = app(WooCommerceConnector::class)->pullOrders($store, [
            'start_date' => '2026-03-20T00:00:00+03:00',
        ]);

        $this->assertCount(1, $result['items']);
        $this->assertSame('901', data_get($result, 'items.0.order.order_number'));
        $this->assertSame('processing', data_get($result, 'items.0.package.package_status'));
        $this->assertSame('Ayse Demir', data_get($result, 'items.0.order.customer_name'));
        $this->assertSame('WC-STK-1', data_get($result, 'items.0.items.0.stock_code'));
        $this->assertSame(200.00, data_get($result, 'items.0.items.0.discount_amount'));

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'per_page=25')
                && str_contains($request->url(), '_fields=')
                && str_contains($request->url(), 'after=');
        });
    }

    public function test_it_pushes_price_and_stock_to_product_endpoint(): void
    {
        Http::fake([
            'https://shop.example.com/wp-json/wc/v3/products/101' => Http::response([
                'id' => 101,
                'regular_price' => '1599.90',
                'stock_quantity' => 11,
            ], 200),
        ]);

        $listing = $this->makeListing();
        $connector = app(WooCommerceConnector::class);

        $priceResult = $connector->pushPrice($listing, 1599.90);
        $stockResult = $connector->pushStock($listing, 11);

        $this->assertSame('completed', $priceResult['status']);
        $this->assertSame('completed', $stockResult['status']);

        Http::assertSentCount(2);
        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->url() === 'https://shop.example.com/wp-json/wc/v3/products/101'
                && $request->method() === 'PUT'
                && (($body['regular_price'] ?? null) === '1599.90'
                    || ($body['stock_quantity'] ?? null) === 11);
        });
    }

    public function test_it_verifies_woocommerce_webhook_signature(): void
    {
        $connector = app(WooCommerceConnector::class);
        $payload = json_encode(['id' => 901, 'status' => 'processing'], JSON_THROW_ON_ERROR);
        $secret = 'woo-secret';
        $signature = base64_encode(hash_hmac('sha256', $payload, $secret, true));

        $request = Request::create('/api/webhooks/woocommerce', 'POST', [], [], [], [], $payload);
        $request->headers->set('X-WC-Webhook-Signature', $signature);
        $request->headers->set('X-WC-Webhook-Topic', 'order.updated');
        $request->headers->set('Content-Type', 'application/json');

        $store = $this->makeStore();
        $connection = $store->connection;
        $connection->webhook_secret = $secret;

        $this->assertTrue($connector->verifyWebhookSignature($request, $connection));
        $this->assertSame('order.updated', data_get($connector->extractWebhookMetadata($request), 'event_type'));
    }

    public function test_it_pushes_price_in_small_batches(): void
    {
        Http::fake([
            'https://shop.example.com/wp-json/wc/v3/products/batch' => Http::response([
                'update' => [
                    ['id' => 101, 'regular_price' => '1599.90'],
                    ['id' => 102, 'regular_price' => '1699.90'],
                ],
            ], 200),
        ]);

        $connector = app(WooCommerceConnector::class);
        $store = $this->makeStore();

        $first = new IntegrationPushRun([
            'id' => 1,
            'push_type' => 'price',
            'target_price' => 1599.90,
        ]);
        $first->setRelation('listing', $this->makeListing(101));

        $second = new IntegrationPushRun([
            'id' => 2,
            'push_type' => 'price',
            'target_price' => 1699.90,
        ]);
        $second->setRelation('listing', $this->makeListing(102));

        $result = $connector->pushPriceBatch($store, [$first, $second]);

        $this->assertSame('completed', $result['status']);
        $this->assertCount(2, $result['items']);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->url() === 'https://shop.example.com/wp-json/wc/v3/products/batch'
                && $request->method() === 'POST'
                && count($body['update'] ?? []) === 2
                && data_get($body, 'update.0.id') === 101
                && data_get($body, 'update.1.id') === 102;
        });
    }

    public function test_it_pushes_stock_in_small_batches(): void
    {
        Http::fake([
            'https://shop.example.com/wp-json/wc/v3/products/batch' => Http::response([
                'update' => [
                    ['id' => 101, 'stock_quantity' => 11],
                    ['id' => 102, 'stock_quantity' => 5],
                ],
            ], 200),
        ]);

        $connector = app(WooCommerceConnector::class);
        $store = $this->makeStore();

        $first = new IntegrationPushRun([
            'id' => 1,
            'push_type' => 'stock',
            'target_quantity' => 11,
        ]);
        $first->setRelation('listing', $this->makeListing(101));

        $second = new IntegrationPushRun([
            'id' => 2,
            'push_type' => 'stock',
            'target_quantity' => 5,
        ]);
        $second->setRelation('listing', $this->makeListing(102));

        $result = $connector->pushStockBatch($store, [$first, $second]);

        $this->assertSame('completed', $result['status']);
        $this->assertCount(2, $result['items']);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->url() === 'https://shop.example.com/wp-json/wc/v3/products/batch'
                && $request->method() === 'POST'
                && count($body['update'] ?? []) === 2
                && data_get($body, 'update.0.stock_quantity') === 11
                && data_get($body, 'update.1.stock_quantity') === 5;
        });
    }

    protected function makeStore(): MarketplaceStore
    {
        $store = new MarketplaceStore([
            'marketplace' => 'woocommerce',
            'store_name' => 'Woo Test',
            'seller_id' => 'woo-test',
            'store_url' => 'https://shop.example.com',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
        ]);

        $connection = new IntegrationConnection([
            'provider' => 'woocommerce',
            'auth_type' => 'consumer_key_secret',
            'credentials_encrypted' => [
                'api_key' => 'ck_test',
                'api_secret' => 'cs_test',
            ],
            'api_base_url' => 'https://shop.example.com',
            'webhook_secret' => 'woo-secret',
            'status' => 'configured',
        ]);

        $store->setRelation('connection', $connection);

        return $store;
    }

    protected function makeListing(int $listingId = 101): ChannelListing
    {
        $store = $this->makeStore();

        $channelProduct = new ChannelProduct([
            'store_id' => 1,
            'external_product_id' => (string) $listingId,
            'stock_code' => 'WC-STK-'.$listingId,
            'title' => 'ZEM Koltuk',
            'raw_payload' => [
                'id' => $listingId,
            ],
        ]);

        $listing = new ChannelListing([
            'store_id' => 1,
            'channel_product_id' => 1,
            'listing_id' => (string) $listingId,
            'listing_status' => 'publish',
            'sale_price' => 1499.90,
            'stock_quantity' => 8,
            'currency' => 'TRY',
        ]);

        $listing->setRelation('store', $store);
        $listing->setRelation('channelProduct', $channelProduct);

        return $listing;
    }
}
