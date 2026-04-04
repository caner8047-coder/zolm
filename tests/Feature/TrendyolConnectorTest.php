<?php

namespace Tests\Feature;

use App\Models\IntegrationConnection;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\Connectors\TrendyolConnector;
use App\Services\Marketplace\MarketplaceConnectorManager;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TrendyolConnectorTest extends TestCase
{
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
                    'cargoTrackingNumber' => 'TRK-1',
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

        $result = app(TrendyolConnector::class)->pullOrders($store, [
            'start_date' => '2026-03-20T00:00:00+03:00',
            'end_date' => '2026-03-22T00:00:00+03:00',
        ]);

        $this->assertCount(1, $result['items']);
        $this->assertSame('TY-ORD-1', data_get($result, 'items.0.order.order_number'));
        $this->assertSame('987654', data_get($result, 'items.0.package.external_package_id'));
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

        $result = app(TrendyolConnector::class)->pullOrders($store, [
            'start_date' => '2026-03-20T00:00:00+03:00',
            'end_date' => '2026-03-22T00:00:00+03:00',
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
}
