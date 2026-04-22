<?php

namespace Tests\Feature;

use App\Models\IntegrationConnection;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\Connectors\CiceksepetiConnector;
use App\Services\Marketplace\MarketplaceConnectorManager;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CiceksepetiConnectorTest extends TestCase
{
    public function test_manager_resolves_ciceksepeti_connector(): void
    {
        $manager = app(MarketplaceConnectorManager::class);
        $connector = $manager->resolve('ciceksepeti');

        $this->assertInstanceOf(CiceksepetiConnector::class, $connector);
        $this->assertSame('Çiçeksepeti', $connector->displayName());
        $this->assertTrue($connector->capabilities()['orders']);
        $this->assertTrue($connector->capabilities()['products']);
        $this->assertFalse($connector->capabilities()['finance']);
    }

    public function test_it_uses_api_key_and_user_agent_for_ciceksepeti_connection_test(): void
    {
        Http::fake([
            'https://apis.ciceksepeti.com/api/v1/Order/GetOrders' => Http::response([
                'orderListCount' => 0,
                'supplierOrderListWithBranch' => [],
            ], 200),
        ]);

        $result = app(CiceksepetiConnector::class)->testConnection($this->makeStore());

        $this->assertTrue($result['ok']);

        Http::assertSent(fn ($request) => $request->url() === 'https://apis.ciceksepeti.com/api/v1/Order/GetOrders'
            && $request->method() === 'POST'
            && $request->hasHeader('x-api-key', 'ciceksepeti-key')
            && $request->hasHeader('User-Agent', '123456-ZOLM'));
    }

    public function test_it_normalizes_ciceksepeti_products(): void
    {
        Http::fake([
            'https://apis.ciceksepeti.com/api/v1/Products*' => Http::response([
                'totalCount' => 1,
                'products' => [[
                    'productName' => 'Gümüş Kolye',
                    'productCode' => 'kc565656',
                    'categoryName' => 'Takı',
                    'stockCode' => 'CSP-STK-1',
                    'mainProductCode' => 'CSP-MAIN-1',
                    'productStatusType' => 'YAYINDA',
                    'listPrice' => 1299.90,
                    'totalPrice' => 1199.90,
                    'barcode' => '869000000010',
                    'stockQuantity' => 6,
                    'isActive' => true,
                ]],
            ], 200),
        ]);

        $result = app(CiceksepetiConnector::class)->pullProducts($this->makeStore());

        $this->assertCount(1, $result['items']);
        $this->assertSame('kc565656', data_get($result, 'items.0.product.external_product_id'));
        $this->assertSame('CSP-STK-1', data_get($result, 'items.0.product.stock_code'));
        $this->assertSame('active', data_get($result, 'items.0.listing.listing_status'));
        $this->assertSame(1199.90, data_get($result, 'items.0.listing.sale_price'));
    }

    public function test_it_retries_products_when_ciceksepeti_returns_rate_limit_message(): void
    {
        config()->set('marketplace.ciceksepeti.product_rate_limit_grace_seconds', 0);

        Http::fakeSequence()
            ->push([
                'Message' => 'Limit aşımı! Bu endpointe farklı istekleri 5 saniyede 1 kez atabilirsiniz. Kalan Süre: 0 saniye',
            ], 400)
            ->push([
                'totalCount' => 1,
                'products' => [[
                    'productName' => 'Deneme Vazo',
                    'productCode' => 'kc777777',
                    'categoryName' => 'Dekor',
                    'stockCode' => 'CSP-STK-RETRY',
                    'mainProductCode' => 'CSP-MAIN-RETRY',
                    'productStatusType' => 'YAYINDA',
                    'listPrice' => 799.90,
                    'totalPrice' => 699.90,
                    'barcode' => '869000000011',
                    'stockQuantity' => 4,
                    'isActive' => true,
                ]],
            ], 200);

        $result = app(CiceksepetiConnector::class)->pullProducts($this->makeStore());

        $this->assertCount(1, $result['items']);
        $this->assertSame('CSP-STK-RETRY', data_get($result, 'items.0.product.stock_code'));
        Http::assertSentCount(2);
    }

    public function test_it_normalizes_ciceksepeti_orders(): void
    {
        Http::fake([
            'https://apis.ciceksepeti.com/api/v1/Order/GetOrders' => Http::response([
                'orderListCount' => 1,
                'supplierOrderListWithBranch' => [[
                    'orderId' => 986089186,
                    'orderItemId' => 111222333,
                    'orderCreateDate' => '20/04/2026',
                    'orderCreateTime' => '19:30',
                    'orderModifyDate' => '21/04/2026',
                    'orderModifyTime' => '13:40',
                    'orderItemStatusId' => 1,
                    'orderProductStatus' => 'Yeni',
                    'receiverName' => 'Büşra Türker Arat',
                    'receiverPhone' => '05369395639',
                    'receiverCity' => 'İstanbul',
                    'receiverDistrict' => 'Pendik',
                    'receiverRegion' => 'Kaynarca',
                    'cargoCompany' => 'Sürat',
                    'cargoNumber' => null,
                    'partialNumber' => '123456789-10',
                    'invoiceEmail' => 'bussraturker@gmail.com',
                    'senderName' => 'Büşra Türker Arat',
                    'code' => 'CSP-STK-1',
                    'productCode' => 'kc565656',
                    'barcode' => '869000000010',
                    'name' => 'Gümüş Kolye',
                    'quantity' => 1,
                    'itemPrice' => 1199.90,
                    'invoicePrice' => 1199.90,
                    'discount' => 0,
                    'tax' => 20,
                    'isOrderStatusActive' => true,
                ]],
            ], 200),
        ]);

        $result = app(CiceksepetiConnector::class)->pullOrders($this->makeStore(), [
            'start_date' => '2026-04-20T00:00:00+03:00',
            'end_date' => '2026-04-22T00:00:00+03:00',
        ]);

        $this->assertCount(1, $result['items']);
        $this->assertSame('986089186', data_get($result, 'items.0.order.order_number'));
        $this->assertSame('approved', data_get($result, 'items.0.package.package_status'));
        $this->assertSame('CSP-STK-1', data_get($result, 'items.0.items.0.stock_code'));
        $this->assertSame(1199.90, data_get($result, 'items.0.items.0.billable_amount'));
        $this->assertSame('Büşra Türker Arat', data_get($result, 'items.0.order.customer_name'));
    }

    protected function makeStore(): MarketplaceStore
    {
        $store = new MarketplaceStore([
            'marketplace' => 'ciceksepeti',
            'store_name' => 'Çiçeksepeti Test',
            'seller_id' => '123456',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
        ]);

        $store->setRelation('connection', new IntegrationConnection([
            'provider' => 'ciceksepeti',
            'auth_type' => 'api_key_secret',
            'credentials_encrypted' => [
                'api_key' => 'ciceksepeti-key',
                'extra_user' => 'ZOLM',
            ],
            'api_base_url' => '',
            'status' => 'configured',
        ]));

        return $store;
    }
}
