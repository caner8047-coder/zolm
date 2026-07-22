<?php

namespace Tests\Feature;

use App\Models\ChannelListing;
use App\Models\ChannelProduct;
use App\Models\IntegrationConnection;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\Connectors\TSoftConnector;
use App\Services\Marketplace\MarketplaceConnectorManager;
use App\Services\Marketplace\TSoftRestGateway;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\TestCase;

class TSoftConnectorTest extends TestCase
{
    public function test_manager_resolves_tsoft_with_truthful_capabilities(): void
    {
        $connector = app(MarketplaceConnectorManager::class)->resolve('tsoft');

        $this->assertInstanceOf(TSoftConnector::class, $connector);
        $this->assertTrue($connector->capabilities()['orders']);
        $this->assertTrue($connector->capabilities()['products']);
        $this->assertTrue($connector->capabilities()['finance']);
        $this->assertTrue($connector->capabilities()['claims']);
        $this->assertTrue($connector->capabilities()['price_push']);
        $this->assertTrue($connector->capabilities()['stock_push']);
        $this->assertFalse($connector->capabilities()['webhooks']);
        $this->assertFalse($connector->capabilities()['questions']);
    }

    public function test_gateway_logs_in_and_sends_rest1_token_as_form_data(): void
    {
        Http::fake([
            'https://magaza.example/rest1/auth/login/zolm-service' => Http::response([
                'success' => true,
                'data' => [[
                    'token' => 'tsoft-token',
                    'expirationTime' => now()->addHour()->format('d-m-Y H:i:s'),
                ]],
                'message' => [['code' => 'AUI001', 'text' => ['Giriş yapıldı!']]],
            ]),
            'https://magaza.example/rest1/product/get' => Http::response([
                'success' => true,
                'data' => [['ProductId' => 100]],
                'message' => [['code' => 'GNI001', 'text' => ['Başarılı!']]],
            ]),
        ]);

        $payload = app(TSoftRestGateway::class)->call($this->makeStore(), 'product/get', [
            'start' => 0,
            'limit' => 1,
        ]);

        $this->assertTrue($payload['success']);
        $this->assertSame(100, data_get($payload, 'data.0.ProductId'));
        Http::assertSent(fn ($request) => $request->url() === 'https://magaza.example/rest1/auth/login/zolm-service'
            && $request['pass'] === 'service-password');
        Http::assertSent(fn ($request) => $request->url() === 'https://magaza.example/rest1/product/get'
            && $request['token'] === 'tsoft-token'
            && $request['start'] === 0
            && $request['limit'] === 1);
    }

    public function test_it_normalizes_orders_variants_finance_and_return_claims(): void
    {
        $calls = [];
        $this->mock(TSoftRestGateway::class, function (MockInterface $mock) use (&$calls): void {
            $mock->shouldReceive('call')->andReturnUsing(function ($store, string $path, array $parameters) use (&$calls): array {
                $calls[$path][] = $parameters;

                return match ($path) {
                    'order/get' => $this->envelope([$this->orderPayload()]),
                    'product/get' => $this->envelope([$this->productPayload()]),
                    'subProduct/getSubProducts' => $this->envelope([$this->variantPayload()]),
                    default => $this->envelope([]),
                };
            });
        });

        $connector = app(TSoftConnector::class);
        $store = $this->makeStore();
        $orders = $connector->pullOrders($store, ['page_size' => 250, 'max_pages' => 1]);
        $products = $connector->pullProducts($store, ['page_size' => 250, 'max_pages' => 1]);
        $finance = $connector->pullFinancialEvents($store, ['page_size' => 250, 'max_pages' => 1]);
        $claims = $connector->pullClaims($store, ['page_size' => 250, 'max_pages' => 1]);

        $this->assertSame('returned', data_get($orders, 'items.0.order.order_status'));
        $this->assertSame('TRK-900', data_get($orders, 'items.0.package.cargo_tracking_number'));
        $this->assertSame('MASA-CEVIZ', data_get($orders, 'items.0.items.0.stock_code'));
        $this->assertSame('501', data_get($products, 'items.0.product.external_product_id'));
        $this->assertSame('100', data_get($products, 'items.0.product.external_parent_id'));
        $this->assertSame(12, data_get($products, 'items.0.listing.stock_quantity'));
        $this->assertSame('tsoft_order_payment_summary', data_get($finance, 'items.0.event_source'));
        $this->assertSame('debit', data_get($finance, 'items.0.direction'));
        $this->assertSame('return', data_get($claims, 'items.0.type'));
        $this->assertSame('completed', data_get($claims, 'items.0.status'));
        $this->assertTrue(data_get($calls, 'order/get.0.FetchProductData'));
        $this->assertTrue(data_get($calls, 'order/get.0.FetchInvoiceAddress'));
        $this->assertSame(250, data_get($calls, 'product/get.0.limit'));
        $this->assertSame('SubProductId ASC', data_get($calls, 'subProduct/getSubProducts.0.orderby'));
    }

    public function test_connection_check_uses_product_read_permission(): void
    {
        $this->mock(TSoftRestGateway::class, function (MockInterface $mock): void {
            $mock->shouldReceive('call')
                ->once()
                ->withArgs(fn ($store, $path, $parameters) => $path === 'product/get'
                    && data_get($parameters, 'start') === 0
                    && data_get($parameters, 'limit') === 1)
                ->andReturn($this->envelope([['ProductId' => 100]]));
            $mock->shouldReceive('baseUrl')->once()->andReturn('https://magaza.example');
        });

        $result = app(TSoftConnector::class)->testConnection($this->makeStore());

        $this->assertTrue($result['ok']);
        $this->assertSame(1, data_get($result, 'meta.sample_count'));
    }

    public function test_main_product_price_and_stock_writes_use_update_products(): void
    {
        $calls = [];
        $this->mock(TSoftRestGateway::class, function (MockInterface $mock) use (&$calls): void {
            $mock->shouldReceive('call')->twice()->andReturnUsing(function ($store, $path, $parameters) use (&$calls): array {
                $calls[] = compact('path', 'parameters');

                return $this->envelope([], 'PRI001');
            });
        });

        $connector = app(TSoftConnector::class);
        $listing = $this->makeListing();
        $price = $connector->pushPrice($listing, 2799.90);
        $stock = $connector->pushStock($listing, 18);
        $pricePayload = json_decode(data_get($calls, '0.parameters.data'), true, flags: JSON_THROW_ON_ERROR);
        $stockPayload = json_decode(data_get($calls, '1.parameters.data'), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('completed', $price['status']);
        $this->assertSame('completed', $stock['status']);
        $this->assertSame('product/updateProducts', data_get($calls, '0.path'));
        $this->assertSame('MASA', $pricePayload['ProductCode']);
        $this->assertSame(2799.90, $pricePayload['SellingPrice']);
        $this->assertSame('MASA', $stockPayload['ProductCode']);
        $this->assertSame(18, $stockPayload['Stock']);
    }

    public function test_variant_write_fails_closed_until_official_contract_is_verified(): void
    {
        $this->mock(TSoftRestGateway::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('call');
        });
        $listing = $this->makeListing();
        $listing->channelProduct->raw_payload = [
            'ProductCode' => 'MASA',
            'variant' => ['SubProductCode' => 'MASA-CEVIZ'],
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('alt ürün fiyat/stok yazma sözleşmesi doğrulanmadı');

        app(TSoftConnector::class)->pushPrice($listing, 2799.90);
    }

    /**
     * @param  array<int, array<string, mixed>>  $data
     * @return array<string, mixed>
     */
    protected function envelope(array $data, string $code = 'GNI001'): array
    {
        return [
            'success' => true,
            'data' => $data,
            'message' => [['code' => $code, 'text' => ['Başarılı!']]],
            'summary' => '',
        ];
    }

    protected function makeStore(): MarketplaceStore
    {
        $store = new MarketplaceStore([
            'marketplace' => 'tsoft',
            'store_name' => 'T-Soft Test',
            'seller_id' => 'tsoft-test',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
        ]);
        $connection = new IntegrationConnection([
            'provider' => 'tsoft',
            'auth_type' => 'username_password',
            'credentials_encrypted' => [
                'api_key' => 'zolm-service',
                'api_secret' => 'service-password',
                'store_url' => 'https://magaza.example',
            ],
            'api_base_url' => 'https://magaza.example',
            'status' => 'configured',
        ]);
        $connection->id = 410;
        $store->setRelation('connection', $connection);

        return $store;
    }

    protected function makeListing(): ChannelListing
    {
        $product = new ChannelProduct([
            'external_product_id' => '100',
            'stock_code' => 'MASA',
            'raw_payload' => ['ProductId' => 100, 'ProductCode' => 'MASA', 'variant' => null],
        ]);
        $listing = new ChannelListing([
            'listing_id' => '100',
            'currency' => 'TRY',
        ]);
        $listing->id = 1;
        $listing->setRelation('store', $this->makeStore());
        $listing->setRelation('channelProduct', $product);

        return $listing;
    }

    /**
     * @return array<string, mixed>
     */
    protected function orderPayload(): array
    {
        return [
            'OrderId' => 900,
            'OrderCode' => 'TS900',
            'OrderDate' => '2026-07-21T10:00:00+03:00',
            'UpdateDate' => '2026-07-21T12:00:00+03:00',
            'OrderStatusId' => 10,
            'OrderStatus' => 'İade Edildi',
            'OrderTotalPrice' => '2799.90',
            'OrderSubtotal' => 2799.90,
            'Currency' => 'TRY',
            'ExchangeRate' => 1,
            'CustomerName' => 'Ayşe Test',
            'CustomerUsername' => 'ayse@example.com',
            'CustomerPhone' => '5551112233',
            'PaymentType' => 'Kredi Kartı',
            'Bank' => 'Test Bankası',
            'Installment' => 3,
            'TransactionId' => 'TX-900',
            'Cargo' => 'Yurtiçi Kargo',
            'CargoCode' => 'YURTICI',
            'CargoTrackingCode' => 'TRK-900',
            'InvoiceType' => 'Person',
            'InvoiceName' => 'Ayşe Test',
            'InvoicePersonIdentityNumber' => '11111111111',
            'DeliveryCountry' => 'Türkiye',
            'DeliveryCity' => 'İstanbul',
            'DeliveryTown' => 'Kadıköy',
            'GeneralOrderNote' => 'Müşteri iadesi',
            'OrderDetails' => [[
                'OrderProductId' => 901,
                'ProductId' => 100,
                'SubProductId' => 501,
                'ProductCode' => 'MASA',
                'SubProductCode' => 'MASA-CEVIZ',
                'ProductName' => 'Masa Ceviz',
                'Quantity' => 1,
                'SellingPrice' => 2799.90,
                'Vat' => 20,
                'Barcode' => '8690900',
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function productPayload(): array
    {
        return [
            'ProductId' => 100,
            'ProductCode' => 'MASA',
            'ProductName' => 'Masa',
            'DefaultCategoryName' => 'Mobilya',
            'DefaultCategoryPath' => 'Ev > Mobilya',
            'Barcode' => '8690100',
            'Stock' => 30,
            'IsActive' => true,
            'HasSubProducts' => true,
            'Vat' => 20,
            'Currency' => 'TRY',
            'SellingPrice' => 2999.90,
            'DiscountedPrice' => 2799.90,
            'Brand' => 'ZOLM',
            'Details' => 'Ahşap masa',
            'ImageUrlCdn' => 'https://cdn.example/masa.jpg',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function variantPayload(): array
    {
        return [
            'SubProductId' => 501,
            'SubProductCode' => 'MASA-CEVIZ',
            'MainProductId' => 100,
            'MainProductCode' => 'MASA',
            'ProductName' => 'Masa Ceviz',
            'Barcode' => '8690900',
            'Property1' => 'Ceviz',
            'Stock' => 12,
            'SellingPrice' => 2999.90,
            'DiscountedSellingPrice' => 2799.90,
            'Currency' => 'TRY',
            'Vat' => 20,
            'IsActive' => true,
        ];
    }
}
