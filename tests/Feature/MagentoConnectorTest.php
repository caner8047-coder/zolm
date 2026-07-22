<?php

namespace Tests\Feature;

use App\Models\ChannelListing;
use App\Models\ChannelProduct;
use App\Models\IntegrationConnection;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\Connectors\MagentoConnector;
use App\Services\Marketplace\MagentoRestGateway;
use App\Services\Marketplace\MarketplaceConnectorManager;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\TestCase;

class MagentoConnectorTest extends TestCase
{
    public function test_manager_resolves_magento_with_truthful_capabilities(): void
    {
        $connector = app(MarketplaceConnectorManager::class)->resolve('adobe_commerce');

        $this->assertInstanceOf(MagentoConnector::class, $connector);
        $this->assertTrue($connector->capabilities()['orders']);
        $this->assertTrue($connector->capabilities()['products']);
        $this->assertTrue($connector->capabilities()['finance']);
        $this->assertTrue($connector->capabilities()['claims']);
        $this->assertTrue($connector->capabilities()['price_push']);
        $this->assertTrue($connector->capabilities()['stock_push']);
        $this->assertFalse($connector->capabilities()['webhooks']);
        $this->assertFalse($connector->capabilities()['questions']);
    }

    public function test_gateway_uses_store_view_and_bearer_access_token(): void
    {
        Http::fake([
            'https://magento.example/rest/all/V1/products*' => Http::response([
                'items' => [['id' => 10, 'sku' => 'MASA']],
                'total_count' => 1,
                'search_criteria' => [],
            ]),
        ]);

        $payload = app(MagentoRestGateway::class)->call($this->makeStore(), 'GET', 'products', [
            'searchCriteria' => ['pageSize' => 1, 'currentPage' => 1],
        ]);

        $this->assertSame('MASA', data_get($payload, 'items.0.sku'));
        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://magento.example/rest/all/V1/products?')
            && $request->hasHeader('Authorization', 'Bearer integration-access-token')
            && data_get($request->data(), 'searchCriteria.pageSize') === 1);
    }

    public function test_it_normalizes_orders_products_invoices_and_credit_memos(): void
    {
        $calls = [];
        $this->mock(MagentoRestGateway::class, function (MockInterface $mock) use (&$calls): void {
            $mock->shouldReceive('call')->andReturnUsing(function ($store, string $method, string $path, array $query = [], ?array $json = null) use (&$calls): array {
                $calls[$path][] = compact('method', 'query', 'json');

                return match ($path) {
                    'orders' => $this->collection([$this->orderPayload()]),
                    'products' => $this->collection([$this->productPayload()]),
                    'invoices' => $this->collection([$this->invoicePayload()]),
                    'creditmemos' => $this->collection([$this->creditMemoPayload()]),
                    default => $this->collection([]),
                };
            });
        });

        $connector = app(MagentoConnector::class);
        $store = $this->makeStore();
        $options = ['page_size' => 100, 'max_pages' => 1, 'start_date' => '2026-07-20', 'end_date' => '2026-07-22'];
        $orders = $connector->pullOrders($store, $options);
        $products = $connector->pullProducts($store, $options);
        $finance = $connector->pullFinancialEvents($store, $options);
        $claims = $connector->pullClaims($store, $options);

        $this->assertSame('000000123', data_get($orders, 'items.0.order.order_number'));
        $this->assertSame('picking', data_get($orders, 'items.0.order.order_status'));
        $this->assertSame('MASA-CEVIZ', data_get($orders, 'items.0.items.0.stock_code'));
        $this->assertSame(2799.90, data_get($orders, 'items.0.items.0.gross_amount'));
        $this->assertSame('MASA-CEVIZ', data_get($products, 'items.0.product.stock_code'));
        $this->assertSame('8690900', data_get($products, 'items.0.product.barcode'));
        $this->assertSame(12, data_get($products, 'items.0.listing.stock_quantity'));
        $this->assertSame('magento_invoice_summary', data_get($finance, 'items.0.event_source'));
        $this->assertSame('44', data_get($finance, 'items.0.order_number'));
        $this->assertSame('magento-creditmemo-77', data_get($claims, 'items.0.external_claim_id'));
        $this->assertSame('completed', data_get($claims, 'items.0.status'));
        $this->assertSame('MASA-CEVIZ', data_get($claims, 'items.0.items.0.stock_code'));
        $this->assertSame(100, data_get($calls, 'orders.0.query.searchCriteria.pageSize'));
        $this->assertSame('created_at', data_get($calls, 'orders.0.query.searchCriteria.filter_groups.0.filters.0.field'));
        $this->assertSame('2026-07-20 00:00:00', data_get($calls, 'orders.0.query.searchCriteria.filter_groups.0.filters.0.value'));
    }

    public function test_connection_check_uses_product_read_permission(): void
    {
        $this->mock(MagentoRestGateway::class, function (MockInterface $mock): void {
            $mock->shouldReceive('call')
                ->once()
                ->withArgs(fn ($store, $method, $path, $query) => $method === 'GET'
                    && $path === 'products'
                    && data_get($query, 'searchCriteria.pageSize') === 1)
                ->andReturn($this->collection([['id' => 10, 'sku' => 'MASA']]));
            $mock->shouldReceive('apiBaseUrl')->once()->andReturn('https://magento.example/rest/all/V1');
        });

        $result = app(MagentoConnector::class)->testConnection($this->makeStore());

        $this->assertTrue($result['ok']);
        $this->assertSame(1, data_get($result, 'meta.sample_count'));
    }

    public function test_price_and_msi_stock_writes_use_minimal_official_payloads(): void
    {
        $calls = [];
        $this->mock(MagentoRestGateway::class, function (MockInterface $mock) use (&$calls): void {
            $mock->shouldReceive('call')->twice()->andReturnUsing(function ($store, $method, $path, $query, $json) use (&$calls): array {
                $calls[] = compact('method', 'path', 'query', 'json');

                return [];
            });
            $mock->shouldReceive('sourceCode')->once()->andReturn('default');
        });

        $connector = app(MagentoConnector::class);
        $listing = $this->makeListing();
        $price = $connector->pushPrice($listing, 2799.90);
        $stock = $connector->pushStock($listing, 18);

        $this->assertSame('completed', $price['status']);
        $this->assertSame('products/base-prices', data_get($calls, '0.path'));
        $this->assertSame('MASA-CEVIZ', data_get($calls, '0.json.prices.0.sku'));
        $this->assertSame(2799.90, data_get($calls, '0.json.prices.0.price'));
        $this->assertSame('inventory/source-items', data_get($calls, '1.path'));
        $this->assertSame('default', data_get($calls, '1.json.sourceItems.0.source_code'));
        $this->assertSame(18, data_get($calls, '1.json.sourceItems.0.quantity'));
        $this->assertSame(1, data_get($calls, '1.json.sourceItems.0.status'));
        $this->assertSame('MASA-CEVIZ@default', $stock['external_action_id']);
    }

    public function test_gateway_rejects_cloud_service_url_that_requires_ims_adapter(): void
    {
        $store = $this->makeStore('https://server.api.commerce.adobe.com');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('IMS kimlik doğrulaması');

        app(MagentoRestGateway::class)->apiBaseUrl($store);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    protected function collection(array $items): array
    {
        return [
            'items' => $items,
            'total_count' => count($items),
            'search_criteria' => [],
        ];
    }

    protected function makeStore(string $url = 'https://magento.example'): MarketplaceStore
    {
        $store = new MarketplaceStore([
            'marketplace' => 'magento',
            'store_name' => 'Magento Test',
            'seller_id' => 'magento-test',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
        ]);
        $connection = new IntegrationConnection([
            'provider' => 'magento',
            'auth_type' => 'access_token',
            'credentials_encrypted' => [
                'api_secret' => 'integration-access-token',
                'store_url' => $url,
                'store_front_code' => 'all',
                'extra_user' => 'default',
            ],
            'api_base_url' => $url,
            'status' => 'configured',
        ]);
        $connection->id = 510;
        $store->setRelation('connection', $connection);

        return $store;
    }

    protected function makeListing(): ChannelListing
    {
        $product = new ChannelProduct([
            'external_product_id' => '10',
            'stock_code' => 'MASA-CEVIZ',
            'raw_payload' => $this->productPayload(),
        ]);
        $listing = new ChannelListing([
            'listing_id' => 'MASA-CEVIZ',
            'currency' => 'TRY',
        ]);
        $listing->id = 1;
        $listing->setRelation('store', $this->makeStore());
        $listing->setRelation('channelProduct', $product);

        return $listing;
    }

    /** @return array<string, mixed> */
    protected function orderPayload(): array
    {
        return [
            'entity_id' => 44,
            'increment_id' => '000000123',
            'status' => 'processing',
            'state' => 'processing',
            'created_at' => '2026-07-21 10:00:00',
            'updated_at' => '2026-07-21 11:00:00',
            'order_currency_code' => 'TRY',
            'grand_total' => 2799.90,
            'customer_firstname' => 'Ayşe',
            'customer_lastname' => 'Test',
            'customer_email' => 'ayse@example.com',
            'billing_address' => [
                'firstname' => 'Ayşe',
                'lastname' => 'Test',
                'telephone' => '5551112233',
                'city' => 'İstanbul',
                'country_id' => 'TR',
            ],
            'extension_attributes' => [
                'shipping_assignments' => [[
                    'shipping' => [
                        'method' => 'yurtici_standard',
                        'address' => ['telephone' => '5551112233', 'city' => 'İstanbul', 'region' => 'Kadıköy', 'country_id' => 'TR'],
                    ],
                ]],
            ],
            'items' => [
                [
                    'item_id' => 100,
                    'sku' => 'MASA',
                    'name' => 'Masa Ceviz',
                    'product_type' => 'configurable',
                    'qty_ordered' => 1,
                    'price_incl_tax' => 2799.90,
                    'row_total_incl_tax' => 2799.90,
                    'discount_amount' => 0,
                    'tax_percent' => 20,
                ],
                [
                    'item_id' => 101,
                    'parent_item_id' => 100,
                    'sku' => 'MASA-CEVIZ',
                    'name' => 'Masa Ceviz',
                    'product_type' => 'simple',
                    'qty_ordered' => 1,
                    'price' => 0,
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    protected function productPayload(): array
    {
        return [
            'id' => 10,
            'sku' => 'MASA-CEVIZ',
            'name' => 'Masa Ceviz',
            'attribute_set_id' => 4,
            'price' => 2799.90,
            'status' => 1,
            'visibility' => 4,
            'type_id' => 'simple',
            'created_at' => '2026-07-01 10:00:00',
            'updated_at' => '2026-07-21 10:00:00',
            'custom_attributes' => [
                ['attribute_code' => 'barcode', 'value' => '8690900'],
                ['attribute_code' => 'description', 'value' => 'Ahşap masa'],
                ['attribute_code' => 'manufacturer', 'value' => 'ZOLM'],
            ],
            'extension_attributes' => [
                'stock_item' => ['qty' => 12, 'is_in_stock' => true],
                'website_ids' => [1],
                'category_links' => [['category_id' => '5', 'position' => 1]],
            ],
            'media_gallery_entries' => [['id' => 1, 'file' => '/m/a/masa.jpg', 'media_type' => 'image']],
        ];
    }

    /** @return array<string, mixed> */
    protected function invoicePayload(): array
    {
        return [
            'entity_id' => 66,
            'increment_id' => '000000066',
            'order_id' => 44,
            'state' => 2,
            'grand_total' => 2799.90,
            'order_currency_code' => 'TRY',
            'created_at' => '2026-07-21 11:00:00',
            'updated_at' => '2026-07-21 11:05:00',
        ];
    }

    /** @return array<string, mixed> */
    protected function creditMemoPayload(): array
    {
        return [
            'entity_id' => 77,
            'increment_id' => '000000077',
            'order_id' => 44,
            'state' => 2,
            'grand_total' => 2799.90,
            'customer_note' => 'Müşteri iadesi',
            'created_at' => '2026-07-22 10:00:00',
            'items' => [[
                'entity_id' => 78,
                'order_item_id' => 100,
                'sku' => 'MASA-CEVIZ',
                'name' => 'Masa Ceviz',
                'qty' => 1,
            ]],
        ];
    }
}
