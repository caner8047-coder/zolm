<?php

namespace Tests\Feature;

use App\Models\ChannelOrderPackage;
use App\Models\IntegrationConnection;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\Connectors\TrendyolConnector;
use App\Services\Marketplace\MarketplaceConnectorManager;
use App\Models\User;
use App\Services\MpSettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TrendyolConnectorTest extends TestCase
{
    use RefreshDatabase;
    public function test_manager_resolves_trendyol_connector(): void
    {
        $connector = app(MarketplaceConnectorManager::class)->resolve('trendyol');

        $this->assertInstanceOf(TrendyolConnector::class, $connector);
        $this->assertTrue($connector->capabilities()['orders']);
        $this->assertTrue($connector->capabilities()['products']);
        $this->assertTrue($connector->capabilities()['finance']);
        $this->assertTrue($connector->capabilities()['webhooks']);
        $this->assertTrue($connector->capabilities()['package_common_label_create']);
        $this->assertTrue($connector->capabilities()['package_common_label_get']);
        $this->assertTrue($connector->capabilities()['package_invoiced']);
        $this->assertTrue($connector->capabilities()['package_invoice_link']);
        $this->assertTrue($connector->capabilities()['claims']);
        $this->assertTrue($connector->capabilities()['claim_approve']);
        $this->assertTrue($connector->capabilities()['claim_reject']);
    }

    public function test_it_normalizes_order_packages_with_new_trendyol_field_names(): void
    {
        Http::fake([
            'https://apigw.trendyol.com/integration/order/sellers/123456/orders*' => Http::response([
                'content' => [[
                    'orderNumber' => 'TY-ORD-1',
                    'shipmentPackageId' => 987654,
                    'status' => 'Cancelled',
                    'customerFirstName' => 'Ayse',
                    'customerLastName' => 'Demir',
                    'customerEmail' => 'ayse@example.com',
                    'customerPhone' => '05550000000',
                    'cancelledBy' => 'CUSTOMER',
                    'cancelReason' => 'Vazgecti',
                    'cancelReasonCode' => 'ORDER_CANCELLED',
                    'shipmentAddress' => [
                        'city' => 'Istanbul',
                        'district' => 'Kadikoy',
                        'phone' => '05550000000',
                    ],
                    'invoiceAddress' => [
                        'fullName' => 'Ayse Demir',
                        'taxNumber' => '1234567890',
                    ],
                    'createdDate' => '2026-03-20T10:00:00+03:00',
                    'lastModifiedDate' => '2026-03-21T12:00:00+03:00',
                    'cargoProviderName' => 'Yurtici',
                    'cargoSenderNumber' => 'TRACK-1',
                    'cargoTrackingNumber' => 'BARCODE-1',
                    'lines' => [[
                        'lineId' => 11,
                        'stockCode' => 'TY-STK-1',
                        'barcode' => '869000000001',
                        'productName' => 'Deneme Urun',
                        'quantity' => 2,
                        'lineUnitPrice' => 120.50,
                        'lineGrossAmount' => 241.00,
                        'lineSellerDiscount' => 10.00,
                        'lineTyDiscount' => 5.50,
                        'lineTotalDiscount' => 15.50,
                        'vatRate' => 20,
                        'commissionRate' => 18,
                    ]],
                ]],
                'totalPages' => 1,
            ], 200),
        ]);

        $store = $this->makeStore();
        $endDate = CarbonImmutable::now('Europe/Istanbul')->subDay();
        $startDate = $endDate->subDays(2);

        $result = app(TrendyolConnector::class)->pullOrders($store, [
            'start_date' => $startDate->toIso8601String(),
            'end_date' => $endDate->toIso8601String(),
        ]);

        $this->assertCount(1, $result['items']);
        $this->assertSame('TY-ORD-1', data_get($result, 'items.0.order.order_number'));
        $this->assertSame('987654', data_get($result, 'items.0.package.external_package_id'));
        $this->assertSame('TRACK-1', data_get($result, 'items.0.package.cargo_tracking_number'));
        $this->assertSame('BARCODE-1', data_get($result, 'items.0.package.cargo_barcode'));
        $this->assertSame('Ayse Demir', data_get($result, 'items.0.order.customer_name'));
        $this->assertNotNull(data_get($result, 'items.0.order.cancelled_at'));
        $this->assertSame('11', data_get($result, 'items.0.items.0.external_line_id'));
        $this->assertSame('TY-STK-1', data_get($result, 'items.0.items.0.stock_code'));
        $this->assertSame(120.50, data_get($result, 'items.0.items.0.unit_price'));
        $this->assertSame(241.00, data_get($result, 'items.0.items.0.gross_amount'));
        $this->assertSame(10.00, data_get($result, 'items.0.items.0.discount_amount'));
        $this->assertSame(5.50, data_get($result, 'items.0.items.0.marketplace_discount_amount'));
        $this->assertSame(225.50, data_get($result, 'items.0.items.0.billable_amount'));
        $this->assertSame(20.00, data_get($result, 'items.0.items.0.vat_rate'));
    }

    public function test_it_totals_multi_quantity_trendyol_lines_from_discount_details(): void
    {
        Http::fake([
            'https://apigw.trendyol.com/integration/order/sellers/123456/orders*' => Http::response([
                'content' => [[
                    'orderNumber' => 'TY-QTY-2',
                    'shipmentPackageId' => 123456,
                    'status' => 'Shipped',
                    'grossAmount' => 2439.80,
                    'totalPrice' => 2439.80,
                    'packageGrossAmount' => 2439.80,
                    'packageTotalPrice' => 2439.80,
                    'lines' => [[
                        'lineId' => 5446578370,
                        'stockCode' => '1PUFZEM00390',
                        'barcode' => '87874848484848484',
                        'productName' => 'Lines Puf',
                        'quantity' => 2,
                        'lineUnitPrice' => 1219.90,
                        'lineGrossAmount' => 1219.90,
                        'lineSellerDiscount' => 0,
                        'lineTyDiscount' => 0,
                        'lineTotalDiscount' => 0,
                        'discountDetails' => [
                            [
                                'lineItemPrice' => 1219.90,
                                'lineItemDiscount' => 0,
                                'lineItemTyDiscount' => 0,
                                'lineItemSellerDiscount' => 0,
                            ],
                            [
                                'lineItemPrice' => 1219.90,
                                'lineItemDiscount' => 0,
                                'lineItemTyDiscount' => 0,
                                'lineItemSellerDiscount' => 0,
                            ],
                        ],
                    ]],
                ]],
                'totalPages' => 1,
            ], 200),
        ]);

        $endDate = CarbonImmutable::now('Europe/Istanbul')->subDay();

        $result = app(TrendyolConnector::class)->pullOrders($this->makeStore(), [
            'start_date' => $endDate->subDays(2)->toIso8601String(),
            'end_date' => $endDate->toIso8601String(),
        ]);

        $this->assertSame(2, data_get($result, 'items.0.items.0.quantity'));
        $this->assertSame(1219.90, data_get($result, 'items.0.items.0.unit_price'));
        $this->assertSame(2439.80, data_get($result, 'items.0.items.0.gross_amount'));
        $this->assertSame(0.00, data_get($result, 'items.0.items.0.discount_amount'));
        $this->assertSame(0.00, data_get($result, 'items.0.items.0.marketplace_discount_amount'));
        $this->assertSame(2439.80, data_get($result, 'items.0.items.0.billable_amount'));
    }

    public function test_it_falls_back_to_legacy_trendyol_order_fields(): void
    {
        Http::fake([
            'https://apigw.trendyol.com/integration/order/sellers/123456/orders*' => Http::response([
                'content' => [[
                    'orderNumber' => 'TY-LEG-1',
                    'id' => 555,
                    'status' => 'Created',
                    'customerName' => 'Legacy Musteri',
                    'lines' => [[
                        'id' => 22,
                        'merchantSku' => 'TY-LEG-STK-1',
                        'barcode' => '869000000002',
                        'productName' => 'Legacy Urun',
                        'quantity' => 3,
                        'price' => 50.00,
                        'amount' => 150.00,
                        'discount' => 12.50,
                        'tyDiscount' => 7.50,
                        'vatBaseAmount' => 10,
                    ]],
                ]],
                'totalPages' => 1,
            ], 200),
        ]);

        $store = $this->makeStore();
        $endDate = CarbonImmutable::now('Europe/Istanbul')->subDay();
        $startDate = $endDate->subDays(2);

        $result = app(TrendyolConnector::class)->pullOrders($store, [
            'start_date' => $startDate->toIso8601String(),
            'end_date' => $endDate->toIso8601String(),
        ]);

        $this->assertCount(1, $result['items']);
        $this->assertSame('555', data_get($result, 'items.0.package.external_package_id'));
        $this->assertSame('22', data_get($result, 'items.0.items.0.external_line_id'));
        $this->assertSame('TY-LEG-STK-1', data_get($result, 'items.0.items.0.stock_code'));
        $this->assertSame(50.00, data_get($result, 'items.0.items.0.unit_price'));
        $this->assertSame(150.00, data_get($result, 'items.0.items.0.gross_amount'));
        $this->assertSame(12.50, data_get($result, 'items.0.items.0.discount_amount'));
        $this->assertSame(7.50, data_get($result, 'items.0.items.0.marketplace_discount_amount'));
        $this->assertSame(130.00, data_get($result, 'items.0.items.0.billable_amount'));
        $this->assertSame(10.00, data_get($result, 'items.0.items.0.vat_rate'));
    }

    public function test_it_falls_back_to_v1_product_filter_when_approved_products_endpoint_is_not_found(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/integration/product/sellers/123456/products/approved')) {
                return Http::response([
                    'path' => '/sellers/123456/products/approved',
                    'exception' => 'TrendyolNotFoundException',
                ], 404);
            }

            if (str_contains($request->url(), '/integration/product/sellers/123456/products')) {
                return Http::response([
                    'content' => [[
                        'id' => 'TY-PROD-1',
                        'approved' => true,
                        'onSale' => true,
                        'barcode' => '869000000003',
                        'stockCode' => 'TY-STK-3',
                        'productMainId' => 'MAIN-3',
                        'title' => 'Fallback Ürün',
                        'brand' => 'Zem',
                        'categoryName' => 'Mobilya',
                        'quantity' => 7,
                        'salePrice' => 1200,
                        'listPrice' => 1300,
                        'vatRate' => 20,
                    ]],
                    'totalPages' => 1,
                    'totalElements' => 1,
                    'page' => 0,
                ], 200);
            }

            return Http::response([], 500);
        });

        $endDate = CarbonImmutable::now('Europe/Istanbul')->subDay();

        $result = app(TrendyolConnector::class)->pullProducts($this->makeStore(), [
            'start_date' => $endDate->subDays(2)->toIso8601String(),
            'end_date' => $endDate->toIso8601String(),
        ]);

        $this->assertCount(1, $result['items']);
        $this->assertSame('869000000003', data_get($result, 'items.0.product.barcode'));
        $this->assertSame('TY-STK-3', data_get($result, 'items.0.product.stock_code'));
        $this->assertSame('active', data_get($result, 'items.0.listing.listing_status'));

        Http::assertSent(function ($request) {
            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $request->method() === 'GET'
                && str_contains($request->url(), '/integration/product/sellers/123456/products?')
                && !str_contains($request->url(), '/products/approved')
                && (string) ($query['approved'] ?? '') === '1'
                && ($query['dateQueryType'] ?? null) === 'LAST_MODIFIED_DATE';
        });
    }

    public function test_connection_check_falls_back_to_v1_product_filter(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/integration/product/sellers/123456/products/approved')) {
                return Http::response([
                    'path' => '/sellers/123456/products/approved',
                    'exception' => 'TrendyolNotFoundException',
                ], 404);
            }

            if (str_contains($request->url(), '/integration/product/sellers/123456/products')) {
                return Http::response([
                    'content' => [],
                    'totalPages' => 1,
                    'totalElements' => 0,
                    'page' => 0,
                ], 200);
            }

            return Http::response([], 500);
        });

        $result = app(TrendyolConnector::class)->testConnection($this->makeStore());

        $this->assertTrue((bool) $result['ok']);
        $this->assertSame(0, data_get($result, 'meta.total_elements'));

        Http::assertSent(function ($request) {
            return $request->method() === 'GET'
                && str_contains($request->url(), '/integration/product/sellers/123456/products?')
                && !str_contains($request->url(), '/products/approved');
        });
    }

    public function test_it_normalizes_approved_product_variant_price_and_commission(): void
    {
        Http::fake([
            'https://apigw.trendyol.com/integration/product/sellers/123456/products/approved*' => Http::response([
                'content' => [[
                    'contentId' => 'CONTENT-1',
                    'productMainId' => 'MAIN-1',
                    'title' => 'Approved Ürün',
                    'brand' => ['name' => 'Zem'],
                    'category' => [
                        'name' => 'Mobilya',
                        'commissionRate' => 18,
                    ],
                    'price' => [
                        'salePrice' => 1500,
                        'listPrice' => 1800,
                        'currencyType' => 'TRY',
                    ],
                    'variants' => [[
                        'variantId' => 'VARIANT-1',
                        'barcode' => '8690000001111',
                        'stockCode' => 'TY-VAR-1',
                        'status' => 'onSale',
                        'price' => [
                            'salePrice' => 1200,
                            'listPrice' => 1400,
                            'currencyType' => 'TRY',
                        ],
                        'commissionRate' => 20,
                        'stock' => ['quantity' => 9],
                    ]],
                ]],
                'totalPages' => 1,
                'totalElements' => 1,
                'page' => 0,
            ], 200),
        ]);

        $endDate = CarbonImmutable::now('Europe/Istanbul')->subDay();

        $result = app(TrendyolConnector::class)->pullProducts($this->makeStore(), [
            'start_date' => $endDate->subDays(2)->toIso8601String(),
            'end_date' => $endDate->toIso8601String(),
        ]);

        $this->assertCount(1, $result['items']);
        $this->assertSame('TY-VAR-1', data_get($result, 'items.0.product.stock_code'));
        $this->assertSame(1200.0, data_get($result, 'items.0.listing.sale_price'));
        $this->assertSame(1400.0, data_get($result, 'items.0.listing.list_price'));
        $this->assertSame(20.0, data_get($result, 'items.0.listing.commission_rate'));
        $this->assertSame('catalog', data_get($result, 'items.0.listing.commission_source'));
        $this->assertSame(9, data_get($result, 'items.0.listing.stock_quantity'));
    }

    public function test_it_uses_trendyol_cargo_barcode_for_common_label_requests(): void
    {
        Http::fake([
            'https://apigw.trendyol.com/integration/sellers/123456/common-label/BARCODE-1' => Http::response([
                'data' => [['label' => 'ZPL']],
            ], 200),
        ]);

        $package = new ChannelOrderPackage([
            'external_package_id' => 'PKG-1',
            'cargo_tracking_number' => 'TRACK-1',
            'cargo_barcode' => 'BARCODE-1',
        ]);
        $package->setRelation('store', $this->makeStore());

        $result = app(TrendyolConnector::class)->getCommonLabel($package);

        $this->assertSame('BARCODE-1', $result['tracking_number']);
        $this->assertSame('BARCODE-1', $result['cargo_barcode']);

        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && str_contains($request->url(), '/common-label/BARCODE-1'));
    }

    public function test_it_clamps_trendyol_order_queries_to_last_30_days(): void
    {
        CarbonImmutable::setTestNow('2026-04-03T12:00:00+03:00');

        try {
            Http::fake([
                'https://apigw.trendyol.com/integration/order/sellers/123456/orders*' => Http::response([
                    'content' => [],
                    'totalPages' => 1,
                ], 200),
            ]);

            $store = $this->makeStore();

            $result = app(TrendyolConnector::class)->pullOrders($store, [
                'start_date' => '2026-02-20T00:00:00+03:00',
                'end_date' => '2026-04-02T00:00:00+03:00',
            ]);

            $this->assertTrue((bool) data_get($result, 'meta.window_clamped'));
            $this->assertSame('2026-02-19T21:00:00+00:00', data_get($result, 'meta.requested_start_date'));
            $this->assertSame('2026-03-04T09:00:00+00:00', data_get($result, 'meta.effective_start_date'));
            $this->assertSame(30, data_get($result, 'meta.history_limit_days'));

            Http::assertSent(function ($request) {
                $query = [];
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

                return (int) ($query['startDate'] ?? 0) === (int) CarbonImmutable::parse('2026-03-04T09:00:00+00:00')->valueOf()
                    && (int) ($query['endDate'] ?? 0) === (int) CarbonImmutable::parse('2026-04-01T21:00:00+00:00')->valueOf();
            });
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_it_rejects_trendyol_order_queries_when_end_date_is_older_than_30_days(): void
    {
        CarbonImmutable::setTestNow('2026-04-03T12:00:00+03:00');
        Http::fake();
        $store = $this->makeStore();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('5 Mart 2026');

        try {
            app(TrendyolConnector::class)->pullOrders($store, [
                'start_date' => '2026-02-01T00:00:00+03:00',
                'end_date' => '2026-02-15T00:00:00+03:00',
            ]);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_it_extracts_webhook_metadata_from_new_shipment_package_fields(): void
    {
        $payload = [
            'eventType' => 'order_created',
            'id' => 'legacy-event-id',
            'shipmentPackageId' => 'TY-PKG-100',
            'shipmentPackage' => [
                'shipmentPackageId' => 'TY-PKG-101',
            ],
        ];

        $request = Request::create(
            '/webhook',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload, JSON_THROW_ON_ERROR)
        );

        $metadata = app(TrendyolConnector::class)->extractWebhookMetadata($request);

        $this->assertSame('order_created', $metadata['event_type']);
        $this->assertSame('TY-PKG-100', $metadata['external_event_id']);
    }

    public function test_it_pulls_trendyol_claims(): void
    {
        Http::fake([
            'https://apigw.trendyol.com/integration/order/sellers/123456/claims*' => Http::response([
                'content' => [[
                    'id' => 'CLM-1',
                    'orderNumber' => 'TY-ORDER-1',
                    'claimStatus' => 'Delivered',
                    'claimReason' => 'Ürün hasarlı',
                    'claimDate' => '2026-04-20T10:00:00+03:00',
                    'items' => [[
                        'claimLineItemId' => 55,
                        'orderLineId' => 11,
                        'productName' => 'Deneme Ürün',
                    ]],
                ]],
                'totalPages' => 1,
            ], 200),
        ]);

        $result = app(TrendyolConnector::class)->pullClaims($this->makeStore(), [
            'start_date' => '2026-04-20T00:00:00+03:00',
            'end_date' => '2026-04-21T00:00:00+03:00',
        ]);

        $this->assertCount(1, $result['items']);
        $this->assertSame('CLM-1', data_get($result, 'items.0.external_claim_id'));
        $this->assertSame('55', data_get($result, 'items.0.items.0.external_item_id'));
    }

    public function test_it_sends_trendyol_claim_approval(): void
    {
        Http::fake([
            'https://apigw.trendyol.com/integration/order/sellers/123456/claims/CLM-1/items/approve' => Http::response([], 200),
        ]);

        $result = app(TrendyolConnector::class)->approveClaim($this->makeStore(), 'CLM-1', [
            'claim_item_ids' => [55],
        ]);

        $this->assertSame('approved', $result['status']);

        Http::assertSent(function ($request) {
            return $request->method() === 'PUT'
                && str_contains($request->url(), '/claims/CLM-1/items/approve')
                && data_get($request->data(), 'claimLineItemIdList.0') === 55;
        });
    }

    protected function makeStore(): MarketplaceStore
    {
        $store = new MarketplaceStore([
            'marketplace' => 'trendyol',
            'store_name' => 'Trendyol Test',
            'seller_id' => '123456',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
        ]);

        $connection = new IntegrationConnection([
            'provider' => 'trendyol',
            'auth_type' => 'api_key_secret',
            'credentials_encrypted' => [
                'seller_id' => '123456',
                'api_key' => 'test-key',
                'api_secret' => 'test-secret',
            ],
            'api_base_url' => 'https://apigw.trendyol.com/',
            'status' => 'configured',
        ]);

        $store->setRelation('connection', $connection);

        return $store;
    }

    public function test_pull_orders_uses_default_offset_for_numeric_dates(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $baseTime = CarbonImmutable::now('Europe/Istanbul')->subHours(5)->startOfMinute();

        Http::fake([
            'https://apigw.trendyol.com/integration/order/sellers/123456/orders*' => Http::response([
                'content' => [[
                    'orderNumber' => 'TY-OFF-DEFAULT',
                    'shipmentPackageId' => 111111,
                    'status' => 'Approved',
                    'grossAmount' => 100,
                    'totalPrice' => 100,
                    'packageGrossAmount' => 100,
                    'packageTotalPrice' => 100,
                    'customerFirstName' => 'Test',
                    'customerLastName' => 'User',
                    'customerPhone' => '05550000000',
                    'createdDate' => $baseTime->timestamp,
                    'lines' => [[
                        'lineId' => 1,
                        'stockCode' => 'DEF-STK',
                        'barcode' => '8690000000099',
                        'productName' => 'Default Offset Urun',
                        'quantity' => 1,
                        'lineUnitPrice' => 100,
                        'lineGrossAmount' => 100,
                        'lineSellerDiscount' => 0,
                        'lineTyDiscount' => 0,
                        'lineTotalDiscount' => 0,
                    ]],
                ]],
                'totalPages' => 1,
            ], 200),
        ]);

        $result = app(TrendyolConnector::class)->pullOrders($this->makeStore(), [
            'start_date' => $baseTime->subDay()->toIso8601String(),
            'end_date' => $baseTime->addDay()->toIso8601String(),
        ]);

        $orderedAt = data_get($result, 'items.0.order.ordered_at');

        $this->assertNotNull($orderedAt);
        $this->assertSame(
            $baseTime->subSeconds(10800)->format('Y-m-d\TH:i'),
            \Carbon\Carbon::parse($orderedAt)->format('Y-m-d\TH:i')
        );
    }

    public function test_pull_orders_uses_custom_offset_for_numeric_dates(): void
    {
        $user = User::factory()->create();

        (new MpSettingsService($user->id))->set('orders.trendyol_timestamp_offset_seconds', 7200);

        $this->actingAs($user);

        $baseTime = CarbonImmutable::now('Europe/Istanbul')->subHours(5)->startOfMinute();

        Http::fake([
            'https://apigw.trendyol.com/integration/order/sellers/123456/orders*' => Http::response([
                'content' => [[
                    'orderNumber' => 'TY-OFF-CUSTOM',
                    'shipmentPackageId' => 222222,
                    'status' => 'Approved',
                    'grossAmount' => 200,
                    'totalPrice' => 200,
                    'packageGrossAmount' => 200,
                    'packageTotalPrice' => 200,
                    'customerFirstName' => 'Test',
                    'customerLastName' => 'Custom',
                    'customerPhone' => '05550000001',
                    'createdDate' => $baseTime->timestamp,
                    'lines' => [[
                        'lineId' => 2,
                        'stockCode' => 'CUS-STK',
                        'barcode' => '8690000000088',
                        'productName' => 'Custom Offset Urun',
                        'quantity' => 1,
                        'lineUnitPrice' => 200,
                        'lineGrossAmount' => 200,
                        'lineSellerDiscount' => 0,
                        'lineTyDiscount' => 0,
                        'lineTotalDiscount' => 0,
                    ]],
                ]],
                'totalPages' => 1,
            ], 200),
        ]);

        $result = app(TrendyolConnector::class)->pullOrders($this->makeStore(), [
            'start_date' => $baseTime->subDay()->toIso8601String(),
            'end_date' => $baseTime->addDay()->toIso8601String(),
        ]);

        $orderedAt = data_get($result, 'items.0.order.ordered_at');

        $this->assertNotNull($orderedAt);
        $this->assertSame(
            $baseTime->subSeconds(7200)->format('Y-m-d\TH:i'),
            \Carbon\Carbon::parse($orderedAt)->format('Y-m-d\TH:i')
        );
    }
}
