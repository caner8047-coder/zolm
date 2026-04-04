<?php

namespace Tests\Feature;

use App\Models\ChannelListing;
use App\Models\ChannelProduct;
use App\Models\IntegrationConnection;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\Connectors\N11Connector;
use App\Services\Marketplace\MarketplaceConnectorManager;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class N11ConnectorTest extends TestCase
{
    public function test_manager_resolves_n11_connector(): void
    {
        $manager = app(MarketplaceConnectorManager::class);
        $connector = $manager->resolve('n11');

        $this->assertInstanceOf(N11Connector::class, $connector);
        $this->assertSame('N11', $connector->displayName());
        $this->assertTrue($connector->capabilities()['orders']);
        $this->assertTrue($connector->capabilities()['products']);
        $this->assertFalse($connector->capabilities()['finance']);
        $this->assertTrue($connector->capabilities()['price_push']);
        $this->assertTrue($connector->capabilities()['stock_push']);
    }

    public function test_it_uses_product_query_for_n11_connection_test(): void
    {
        Http::fake([
            'https://api.n11.com/ms/product-query*' => Http::response([
                'page' => 0,
                'totalPages' => 1,
                'content' => [],
            ], 200),
        ]);

        $result = app(N11Connector::class)->testConnection($this->makeStore());

        $this->assertTrue($result['ok']);
        $this->assertSame(1, data_get($result, 'meta.total_pages'));
        $this->assertSame(0, data_get($result, 'meta.items_returned'));

        Http::assertSent(fn ($request) => str_contains($request->url(), '/ms/product-query?page=0&size=1')
            && $request->hasHeader('appkey', 'n11-key')
            && $request->hasHeader('appsecret', 'n11-secret'));
    }

    public function test_it_normalizes_n11_products_from_product_query(): void
    {
        Http::fake([
            'https://api.n11.com/ms/product-query*' => Http::response([
                'page' => 0,
                'totalPages' => 1,
                'content' => [[
                    'n11ProductId' => 123456789,
                    'stockCode' => 'N11-SKU-1',
                    'title' => 'N11 Test Ürünü',
                    'description' => 'Açıklama',
                    'categoryId' => 1000476,
                    'productMainId' => 'GRUP-1',
                    'status' => 'Active',
                    'saleStatus' => 'On_Sale',
                    'barcode' => '869000000001',
                    'currencyType' => 'TL',
                    'salePrice' => 899.90,
                    'listPrice' => 999.90,
                    'quantity' => 4,
                    'brandName' => 'n11 Brand',
                ]],
            ], 200),
        ]);

        $result = app(N11Connector::class)->pullProducts($this->makeStore(), [
            'stock_code' => 'N11-SKU-1',
        ]);

        $this->assertCount(1, $result['items']);
        $this->assertSame('123456789', data_get($result, 'items.0.product.external_product_id'));
        $this->assertSame('N11-SKU-1', data_get($result, 'items.0.product.stock_code'));
        $this->assertSame('869000000001', data_get($result, 'items.0.product.barcode'));
        $this->assertSame('active', data_get($result, 'items.0.listing.listing_status'));
        $this->assertSame(899.90, data_get($result, 'items.0.listing.sale_price'));
        $this->assertSame(4, data_get($result, 'items.0.listing.stock_quantity'));
    }

    public function test_it_normalizes_n11_shipment_packages_as_orders(): void
    {
        Http::fake([
            'https://api.n11.com/rest/delivery/v1/shipmentPackages*' => Http::response([
                'pageCount' => 1,
                'totalPages' => 1,
                'page' => 0,
                'size' => 100,
                'content' => [[
                    'billingAddress' => [
                        'city' => 'İstanbul',
                        'district' => 'Sarıyer',
                        'fullName' => 'n11 müşteri',
                        'invoiceType' => 1,
                    ],
                    'shippingAddress' => [
                        'city' => 'İstanbul',
                        'district' => 'Sarıyer',
                        'fullName' => 'n11 müşteri',
                        'gsm' => '5xxxxxxxxx',
                    ],
                    'orderNumber' => '203872347637',
                    'id' => '112999455244259',
                    'customerEmail' => 'n11@n11.com',
                    'customerfullName' => 'n11 müşteri',
                    'cargoSenderNumber' => 'TRACK-1',
                    'cargoTrackingNumber' => 'BARCODE-1',
                    'cargoProviderName' => 'MNG Kargo',
                    'shipmentCompanyId' => 342,
                    'lines' => [[
                        'quantity' => 2,
                        'productId' => 123456789,
                        'productName' => 'Erkek Spor Ayakkabı Bordo 45',
                        'stockCode' => '20242024',
                        'price' => 292.8,
                        'sellerDiscount' => 2.9,
                        'sellerInvoiceAmount' => 579.8,
                        'totalMallDiscountPrice' => 43.6,
                        'orderLineId' => 415490391,
                        'orderItemLineItemStatusName' => 'Picking',
                        'totalSellerDiscountPrice' => 5.8,
                        'vatRate' => 10,
                        'commissionRate' => 9,
                        'sellerCampaignCommissionRate' => 0,
                        'barcode' => '8684811136999',
                    ]],
                    'lastModifiedDate' => 1724323386203,
                    'agreedDeliveryDate' => 1725310828346,
                    'packageHistories' => [
                        [
                            'createdDate' => 1724274492082,
                            'status' => 'Shipped',
                        ],
                        [
                            'createdDate' => 1724396400000,
                            'status' => 'Delivered',
                        ],
                    ],
                    'shipmentPackageStatus' => 'Delivered',
                ]],
            ], 200),
        ]);

        $result = app(N11Connector::class)->pullOrders($this->makeStore(), [
            'start_date' => '2026-04-01T00:00:00+03:00',
            'end_date' => '2026-04-04T00:00:00+03:00',
        ]);

        $this->assertCount(1, $result['items']);
        $this->assertSame('203872347637', data_get($result, 'items.0.order.order_number'));
        $this->assertSame('n11 müşteri', data_get($result, 'items.0.order.customer_name'));
        $this->assertSame('112999455244259', data_get($result, 'items.0.package.external_package_id'));
        $this->assertSame('20242024', data_get($result, 'items.0.items.0.stock_code'));
        $this->assertSame(292.80, data_get($result, 'items.0.items.0.unit_price'));
        $this->assertSame(585.60, data_get($result, 'items.0.items.0.gross_amount'));
        $this->assertSame(579.80, data_get($result, 'items.0.items.0.billable_amount'));
    }

    public function test_it_pushes_n11_price_update_as_task(): void
    {
        Http::fake([
            'https://api.n11.com/ms/product/tasks/price-stock-update' => Http::response([
                'id' => 1092,
                'type' => 'SKU_UPDATE',
                'status' => 'IN_QUEUE',
            ], 200),
        ]);

        $result = app(N11Connector::class)->pushPrice($this->makeListing(), 1600.00);

        $this->assertSame('queued', $result['status']);
        $this->assertSame('1092', $result['batch_request_id']);

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return str_contains($request->url(), '/ms/product/tasks/price-stock-update')
                && $request->hasHeader('appkey', 'n11-key')
                && $request->hasHeader('appsecret', 'n11-secret')
                && data_get($payload, 'payload.skus.0.stockCode') === 'N11-SKU-1'
                && (float) data_get($payload, 'payload.skus.0.salePrice') === 1600.0
                && (float) data_get($payload, 'payload.skus.0.listPrice') === 2000.0;
        });
    }

    public function test_it_pushes_n11_stock_update_as_task(): void
    {
        Http::fake([
            'https://api.n11.com/ms/product/tasks/price-stock-update' => Http::response([
                'id' => 1093,
                'type' => 'SKU_UPDATE',
                'status' => 'IN_QUEUE',
            ], 200),
        ]);

        $result = app(N11Connector::class)->pushStock($this->makeListing(), 9);

        $this->assertSame('queued', $result['status']);
        $this->assertSame('1093', $result['batch_request_id']);

        Http::assertSent(fn ($request) => data_get($request->data(), 'payload.skus.0.stockCode') === 'N11-SKU-1'
            && (int) data_get($request->data(), 'payload.skus.0.quantity') === 9);
    }

    protected function makeStore(): MarketplaceStore
    {
        $store = new MarketplaceStore([
            'marketplace' => 'n11',
            'store_name' => 'N11 Test',
            'seller_id' => 'n11-demo',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
        ]);

        $store->setRelation('connection', new IntegrationConnection([
            'provider' => 'n11',
            'auth_type' => 'api_key_secret',
            'credentials_encrypted' => [
                'api_key' => 'n11-key',
                'api_secret' => 'n11-secret',
            ],
            'api_base_url' => '',
            'status' => 'configured',
        ]));

        return $store;
    }

    protected function makeListing(): ChannelListing
    {
        $store = $this->makeStore();
        $product = new ChannelProduct([
            'external_product_id' => '123456789',
            'stock_code' => 'N11-SKU-1',
            'barcode' => '869000000001',
            'raw_payload' => [
                'stockCode' => 'N11-SKU-1',
            ],
        ]);

        $listing = new ChannelListing([
            'listing_id' => '123456789',
            'list_price' => 2000,
            'sale_price' => 1800,
            'stock_quantity' => 6,
            'currency' => 'TRY',
        ]);

        $listing->id = 777;
        $listing->setRelation('store', $store);
        $listing->setRelation('channelProduct', $product);

        return $listing;
    }
}
