<?php

namespace Tests\Feature;

use App\Models\IntegrationConnection;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\Connectors\PazaramaConnector;
use App\Services\Marketplace\MarketplaceConnectorManager;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PazaramaConnectorTest extends TestCase
{
    public function test_manager_resolves_pazarama_connector(): void
    {
        $manager = app(MarketplaceConnectorManager::class);
        $connector = $manager->resolve('pazarama');

        $this->assertInstanceOf(PazaramaConnector::class, $connector);
        $this->assertSame('Pazarama', $connector->displayName());
        $this->assertTrue($connector->capabilities()['orders']);
        $this->assertTrue($connector->capabilities()['products']);
        $this->assertFalse($connector->capabilities()['finance']);
    }

    public function test_it_uses_client_credentials_flow_for_pazarama_connection_test(): void
    {
        Http::fake([
            'https://isortagimgiris.pazarama.com/connect/token' => Http::response([
                'success' => true,
                'data' => [
                    'accessToken' => 'pazarama-token',
                    'expiresIn' => 3600,
                    'tokenType' => 'Bearer',
                ],
            ], 200),
            'https://isortagimapi.pazarama.com/product/products*' => Http::response([
                'data' => [],
                'success' => true,
                'messageCode' => 'PRD0',
            ], 200),
        ]);

        $result = app(PazaramaConnector::class)->testConnection($this->makeStore());

        $this->assertTrue($result['ok']);
        $this->assertSame(0, data_get($result, 'meta.items_returned'));

        Http::assertSent(function ($request) {
            if ($request->url() === 'https://isortagimgiris.pazarama.com/connect/token') {
                return $request->method() === 'POST'
                    && $request->hasHeader('Authorization', 'Basic '.base64_encode('pazarama-key:pazarama-secret'))
                    && data_get($request->data(), 'grant_type') === 'client_credentials'
                    && data_get($request->data(), 'scope') === 'merchantgatewayapi.fullaccess';
            }

            return $request->url() === 'https://isortagimapi.pazarama.com/product/products?Approved=true&Size=1&Page=1'
                && $request->hasHeader('Authorization', 'Bearer pazarama-token');
        });
    }

    public function test_it_normalizes_pazarama_products(): void
    {
        Http::fake([
            'https://isortagimgiris.pazarama.com/connect/token' => Http::response([
                'success' => true,
                'data' => [
                    'accessToken' => 'pazarama-token',
                    'expiresIn' => 3600,
                    'tokenType' => 'Bearer',
                ],
            ], 200),
            'https://isortagimapi.pazarama.com/product/products*' => function ($request) {
                $approved = str_contains($request->url(), 'Approved=true');

                return Http::response([
                    'data' => $approved ? [[
                        'name' => 'Liva Puf',
                        'displayName' => 'Liva Puf',
                        'brandName' => 'Zem Home',
                        'code' => 'PZR-001',
                        'groupCode' => 'PZR-GRP-1',
                        'stockCount' => 5,
                        'stockCode' => 'LIVA-PUF',
                        'listPrice' => 1799.90,
                        'salePrice' => 1499.90,
                        'vatRate' => 20,
                        'categoryName' => 'Puf',
                        'state' => 3,
                        'stateDescription' => 'Onaylandı',
                    ]] : [[
                        'name' => 'Onay Bekleyen Ürün',
                        'displayName' => 'Onay Bekleyen Ürün',
                        'brandName' => 'Zem Home',
                        'code' => 'PZR-002',
                        'stockCount' => 1,
                        'listPrice' => 999.90,
                        'salePrice' => 899.90,
                        'vatRate' => 10,
                        'categoryName' => 'Dekor',
                        'state' => 1,
                        'status' => 'Onay Bekliyor-İlk Onay',
                    ]],
                    'success' => true,
                ], 200);
            },
        ]);

        $result = app(PazaramaConnector::class)->pullProducts($this->makeStore());

        $this->assertCount(2, $result['items']);
        $this->assertSame('PZR-001', data_get($result, 'items.0.product.external_product_id'));
        $this->assertSame('LIVA-PUF', data_get($result, 'items.0.product.stock_code'));
        $this->assertSame('active', data_get($result, 'items.0.listing.listing_status'));
        $this->assertSame('pending', data_get($result, 'items.1.listing.listing_status'));
    }

    public function test_it_normalizes_pazarama_orders(): void
    {
        Http::fake([
            'https://isortagimgiris.pazarama.com/connect/token' => Http::response([
                'success' => true,
                'data' => [
                    'accessToken' => 'pazarama-token',
                    'expiresIn' => 3600,
                    'tokenType' => 'Bearer',
                ],
            ], 200),
            'https://isortagimapi.pazarama.com/order/getOrdersForApi' => Http::response([
                'data' => [[
                    'orderId' => 'ord-1',
                    'orderNumber' => 986089186,
                    'orderDate' => '2026-04-20 19:30',
                    'orderAmount' => 1419.90,
                    'currency' => 'TRY',
                    'paymentType' => 1,
                    'orderStatus' => 3,
                    'customerName' => 'Büşra Türker Arat',
                    'customerEmail' => 'bussraturker@gmail.com',
                    'shipmentAddress' => [
                        'nameSurname' => 'Büşra Türker Arat',
                        'cityName' => 'İstanbul',
                        'districtName' => 'Pendik',
                        'phoneNumber' => '05369395639',
                    ],
                    'billingAddress' => [
                        'nameSurname' => 'Büşra Türker Arat',
                        'invoiceType' => 1,
                    ],
                    'items' => [[
                        'orderItemId' => 'line-1',
                        'orderItemStatus' => 3,
                        'estimatedShippingDate' => '2026-04-24 19:00',
                        'quantity' => 1,
                        'listPrice' => ['value' => 1419.90],
                        'salePrice' => ['value' => 1419.90],
                        'totalPrice' => ['value' => 1419.90],
                        'discountAmount' => ['value' => 0],
                        'cargo' => [
                            'companyName' => 'Sürat',
                            'trackingNumber' => null,
                            'trackingUrl' => 'https://suratkargo.com.tr',
                        ],
                        'product' => [
                            'productId' => 'prd-1',
                            'name' => 'Liva Bohem Sandıklı Puf',
                            'code' => 'PZR-001',
                            'stockCode' => 'LIVA-PUF',
                            'vatRate' => 20,
                        ],
                    ]],
                ]],
                'success' => true,
                'messageCode' => 'ORD0',
            ], 200),
        ]);

        $result = app(PazaramaConnector::class)->pullOrders($this->makeStore(), [
            'start_date' => '2026-04-20T00:00:00+03:00',
            'end_date' => '2026-04-21T00:00:00+03:00',
        ]);

        $this->assertCount(1, $result['items']);
        $this->assertSame('986089186', data_get($result, 'items.0.order.order_number'));
        $this->assertSame('Büşra Türker Arat', data_get($result, 'items.0.order.customer_name'));
        $this->assertSame('ord-1', data_get($result, 'items.0.package.external_package_id'));
        $this->assertSame('Siparişiniz Alındı', data_get($result, 'items.0.package.package_status'));
        $this->assertSame('LIVA-PUF', data_get($result, 'items.0.items.0.stock_code'));
        $this->assertSame(1419.90, data_get($result, 'items.0.items.0.billable_amount'));
        $this->assertSame('2026-04-24 19:00', data_get($result, 'items.0.items.0.raw_payload.estimatedShippingDate'));
    }

    protected function makeStore(): MarketplaceStore
    {
        $store = new MarketplaceStore([
            'marketplace' => 'pazarama',
            'store_name' => 'Pazarama Test',
            'seller_id' => 'pazarama-demo',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
        ]);

        $store->setRelation('connection', new IntegrationConnection([
            'provider' => 'pazarama',
            'auth_type' => 'api_key_secret',
            'credentials_encrypted' => [
                'api_key' => 'pazarama-key',
                'api_secret' => 'pazarama-secret',
            ],
            'api_base_url' => '',
            'status' => 'configured',
        ]));

        return $store;
    }
}
