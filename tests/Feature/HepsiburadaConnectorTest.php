<?php

namespace Tests\Feature;

use App\Models\IntegrationConnection;
use App\Models\ChannelListing;
use App\Models\ChannelOrderPackage;
use App\Models\ChannelProduct;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\Connectors\HepsiburadaConnector;
use App\Services\Marketplace\MarketplaceConnectorManager;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HepsiburadaConnectorTest extends TestCase
{
    public function test_manager_resolves_hepsiburada_connector(): void
    {
        $connector = app(MarketplaceConnectorManager::class)->resolve('hepsiburada');

        $this->assertInstanceOf(HepsiburadaConnector::class, $connector);
        $this->assertTrue($connector->capabilities()['orders']);
        $this->assertTrue($connector->capabilities()['products']);
        $this->assertTrue($connector->capabilities()['finance']);
        $this->assertTrue($connector->capabilities()['price_push']);
        $this->assertTrue($connector->capabilities()['stock_push']);
        $this->assertTrue($connector->capabilities()['package_common_label_create']);
        $this->assertTrue($connector->capabilities()['package_common_label_get']);
        $this->assertFalse($connector->capabilities()['package_picking']);
        $this->assertFalse($connector->capabilities()['package_invoiced']);
        $this->assertFalse($connector->capabilities()['package_invoice_link']);
    }

    public function test_it_uses_merchant_id_and_service_key_auth(): void
    {
        Http::fake([
            'https://listing-external.hepsiburada.com/*' => Http::response([
                'items' => [],
                'totalCount' => 0,
            ], 200),
        ]);

        $store = $this->makeStore([
            'api_key' => 'service-key',
            'extra_user' => 'zem_dev',
        ]);

        $result = app(HepsiburadaConnector::class)->testConnection($store);

        $this->assertTrue($result['ok']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://listing-external.hepsiburada.com/listings/merchantid/123456?limit=1&offset=0'
                && $request->hasHeader('User-Agent', 'zem_dev')
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('123456:service-key'));
        });
    }

    public function test_it_falls_back_to_legacy_basic_auth_when_service_key_is_missing(): void
    {
        Http::fake([
            'https://listing-external.hepsiburada.com/*' => Http::response([
                'items' => [],
                'totalCount' => 0,
            ], 200),
        ]);

        $store = $this->makeStore([
            'api_secret' => 'ignored-secret',
            'extra_user' => 'legacy_dev',
            'extra_password' => 'legacy-pass',
        ]);

        $result = app(HepsiburadaConnector::class)->testConnection($store);

        $this->assertTrue($result['ok']);

        Http::assertSent(function ($request) {
            return $request->hasHeader('User-Agent', 'legacy_dev')
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('legacy_dev:legacy-pass'));
        });
    }

    public function test_it_normalizes_listing_payloads(): void
    {
        Http::fake([
            'https://listing-external.hepsiburada.com/*' => Http::response([
                'items' => [[
                    'merchantSku' => 'HB-STK-1',
                    'hepsiburadaSku' => 'HB-LST-1',
                    'barcode' => '869000000001',
                    'productName' => 'Deneme Ürün',
                    'brand' => 'ZEM',
                    'categoryName' => 'Mobilya',
                    'vatRate' => 20,
                    'price' => [
                        'finalPrice' => 1299.90,
                        'listPrice' => 1499.90,
                        'currency' => 'TRY',
                    ],
                    'availableStock' => 12,
                    'dispatchTime' => '2 gün',
                    'deliveryType' => 'Standart',
                    'isActive' => true,
                    'createdAt' => '2026-03-20T10:00:00+03:00',
                ]],
                'totalCount' => 1,
            ], 200),
        ]);

        $store = $this->makeStore([
            'api_key' => 'service-key',
            'extra_user' => 'zem_dev',
        ]);

        $result = app(HepsiburadaConnector::class)->pullProducts($store);

        $this->assertCount(1, $result['items']);
        $this->assertSame('HB-STK-1', data_get($result, 'items.0.product.stock_code'));
        $this->assertSame('HB-LST-1', data_get($result, 'items.0.listing.listing_id'));
        $this->assertSame(1299.90, data_get($result, 'items.0.listing.sale_price'));
        $this->assertSame(12, data_get($result, 'items.0.listing.stock_quantity'));
        $this->assertSame(2, data_get($result, 'items.0.listing.shipping_days'));
        $this->assertSame('Standart', data_get($result, 'items.0.listing.shipping_type'));
        $this->assertSame('active', data_get($result, 'items.0.listing.listing_status'));
    }

    public function test_it_normalizes_package_based_orders(): void
    {
        Http::fake([
            'https://oms-external.hepsiburada.com/packages/merchantid/123456*' => Http::response([
                'items' => [[
                    'orderNumber' => 'HB-ORD-1',
                    'packageNumber' => 'HB-PKG-1',
                    'status' => 'Open',
                    'trackingNumber' => 'TRK-1',
                    'cargoCompany' => 'Yurtici',
                    'shippedDate' => '2026-03-20T10:00:00+03:00',
                ]],
                'totalCount' => 1,
            ], 200),
            'https://oms-external.hepsiburada.com/orders/merchantid/123456/ordernumber/HB-ORD-1' => Http::response([
                'orderNumber' => 'HB-ORD-1',
                'customerName' => 'Ayse Demir',
                'customerEmail' => 'ayse@example.com',
                'shippingAddress' => [
                    'city' => 'Ankara',
                    'town' => 'Cankaya',
                    'phone' => '05550000000',
                ],
                'items' => [[
                    'lineItemId' => 'LINE-1',
                    'merchantSku' => 'HB-STK-1',
                    'barcode' => '869000000001',
                    'productName' => 'Deneme Koltuk',
                    'quantity' => 2,
                    'price' => 499.95,
                    'vatRate' => 20,
                    'commissionRate' => 12,
                ]],
            ], 200),
            '*' => Http::response([
                'items' => [],
                'totalCount' => 0,
            ], 200),
        ]);

        $store = $this->makeStore([
            'api_key' => 'service-key',
            'extra_user' => 'zem_dev',
        ]);

        $result = app(HepsiburadaConnector::class)->pullOrders($store, [
            'start_date' => '2026-03-20T00:00:00+03:00',
            'end_date' => '2026-03-21T00:00:00+03:00',
        ]);

        $this->assertCount(1, $result['items']);
        $this->assertSame('HB-ORD-1', data_get($result, 'items.0.order.order_number'));
        $this->assertSame('HB-PKG-1', data_get($result, 'items.0.package.external_package_id'));
        $this->assertSame('Ayse Demir', data_get($result, 'items.0.order.customer_name'));
        $this->assertSame('HB-STK-1', data_get($result, 'items.0.items.0.stock_code'));
        $this->assertSame(999.90, data_get($result, 'items.0.items.0.gross_amount'));
    }

    public function test_it_normalizes_financial_transactions(): void
    {
        Http::fake([
            'https://mpfinance-external.hepsiburada.com/transactions/merchantid/123456*' => Http::response([
                'items' => [[
                    'id' => 'TRX-1',
                    'transactionType' => 'Commission',
                    'orderNumber' => 'HB-ORD-1',
                    'packageNumber' => 'HB-PKG-1',
                    'merchantSku' => 'HB-STK-1',
                    'amount' => 125.40,
                    'currency' => 'TRY',
                    'transactionDate' => '2026-03-20T11:00:00+03:00',
                ]],
                'totalCount' => 1,
            ], 200),
        ]);

        $store = $this->makeStore([
            'api_key' => 'service-key',
            'extra_user' => 'zem_dev',
        ]);

        $result = app(HepsiburadaConnector::class)->pullFinancialEvents($store, [
            'start_date' => '2026-03-20T00:00:00+03:00',
            'end_date' => '2026-03-21T00:00:00+03:00',
        ]);

        $this->assertCount(1, $result['items']);
        $this->assertSame('commission', data_get($result, 'items.0.event_type'));
        $this->assertSame('debit', data_get($result, 'items.0.direction'));
        $this->assertSame(125.40, data_get($result, 'items.0.amount'));
        $this->assertSame('HB-ORD-1', data_get($result, 'items.0.order_number'));
    }

    public function test_it_pushes_price_with_xml_payload(): void
    {
        Http::fake([
            'https://listing-external.hepsiburada.com/*' => Http::response(
                '<?xml version="1.0" encoding="utf-8"?><Result><Id>PR-123</Id></Result>',
                200,
                ['Content-Type' => 'application/xml']
            ),
        ]);

        $listing = $this->makeListing();

        $result = app(HepsiburadaConnector::class)->pushPrice($listing, 1499.90);

        $this->assertSame('PR-123', $result['batch_request_id']);
        $this->assertSame('queued', $result['status']);

        Http::assertSent(function ($request) {
            $body = $request->body();

            return $request->url() === 'https://listing-external.hepsiburada.com/listings/merchantid/123456/price-uploads'
                && $request->method() === 'POST'
                && $request->hasHeader('Content-Type', 'application/xml')
                && str_contains($body, '<MerchantSku>HB-STK-1</MerchantSku>')
                && str_contains($body, '<HepsiburadaSku>HB-LST-1</HepsiburadaSku>')
                && str_contains($body, '<Price>1499.90</Price>');
        });
    }

    public function test_it_pushes_stock_with_xml_payload(): void
    {
        Http::fake([
            'https://listing-external.hepsiburada.com/*' => Http::response(
                '<?xml version="1.0" encoding="utf-8"?><Result><Id>ST-456</Id></Result>',
                200,
                ['Content-Type' => 'application/xml']
            ),
        ]);

        $listing = $this->makeListing();

        $result = app(HepsiburadaConnector::class)->pushStock($listing, 27);

        $this->assertSame('ST-456', $result['batch_request_id']);
        $this->assertSame('queued', $result['status']);

        Http::assertSent(function ($request) {
            $body = $request->body();

            return $request->url() === 'https://listing-external.hepsiburada.com/listings/merchantid/123456/stock-uploads'
                && $request->method() === 'POST'
                && $request->hasHeader('Content-Type', 'application/xml')
                && str_contains($body, '<MerchantSku>HB-STK-1</MerchantSku>')
                && str_contains($body, '<HepsiburadaSku>HB-LST-1</HepsiburadaSku>')
                && str_contains($body, '<AvailableStock>27</AvailableStock>');
        });
    }

    public function test_it_fetches_common_label_with_safe_response_payload(): void
    {
        Http::fake([
            'https://oms-external.hepsiburada.com/packages/merchantid/123456/packagenumber/HB-PKG-1/labels' => Http::response(
                '^XA^FO50,50^ADN,36,20^FDZOLM TEST^FS^XZ',
                200,
                ['Content-Type' => 'text/plain']
            ),
        ]);

        $package = $this->makePackage();

        $result = app(HepsiburadaConnector::class)->getCommonLabel($package, [
            'format' => 'ZPL',
        ]);

        $this->assertSame('completed', $result['status']);
        $this->assertTrue($result['label_available']);
        $this->assertSame('text/plain', $result['label_content_type']);
        $this->assertStringContainsString('ZOLM TEST', data_get($result, 'response.text_preview', ''));

        Http::assertSent(function ($request) {
            return $request->url() === 'https://oms-external.hepsiburada.com/packages/merchantid/123456/packagenumber/HB-PKG-1/labels'
                && $request->method() === 'GET';
        });
    }

    public function test_it_uses_same_official_endpoint_for_common_label_create(): void
    {
        Http::fake([
            'https://oms-external.hepsiburada.com/packages/merchantid/123456/packagenumber/HB-PKG-1/labels' => Http::response(
                '%PDF-1.4 ZOLM',
                200,
                ['Content-Type' => 'application/pdf']
            ),
        ]);

        $package = $this->makePackage();

        $result = app(HepsiburadaConnector::class)->createCommonLabel($package, [
            'format' => 'PDF',
            'box_quantity' => 2,
        ]);

        $this->assertSame('create', $result['label_operation']);
        $this->assertTrue($result['label_available']);
        $this->assertSame('application/pdf', $result['label_content_type']);
        $this->assertSame('completed', $result['status']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://oms-external.hepsiburada.com/packages/merchantid/123456/packagenumber/HB-PKG-1/labels'
                && $request->method() === 'GET';
        });
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    protected function makeStore(array $credentials): MarketplaceStore
    {
        $store = new MarketplaceStore([
            'marketplace' => 'hepsiburada',
            'store_name' => 'HB Test',
            'seller_id' => '123456',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
        ]);

        $connection = new IntegrationConnection([
            'provider' => 'hepsiburada',
            'auth_type' => 'merchant_id_service_key',
            'credentials_encrypted' => $credentials,
            'api_base_url' => 'https://oms-external.hepsiburada.com/',
            'status' => 'configured',
        ]);

        $store->setRelation('connection', $connection);

        return $store;
    }

    protected function makeListing(): ChannelListing
    {
        $store = $this->makeStore([
            'api_key' => 'service-key',
            'extra_user' => 'zem_dev',
        ]);

        $channelProduct = new ChannelProduct([
            'store_id' => 1,
            'external_product_id' => 'HB-PROD-1',
            'stock_code' => 'HB-STK-1',
            'barcode' => '869000000001',
            'title' => 'Deneme Ürün',
            'raw_payload' => [
                'merchantSku' => 'HB-STK-1',
                'hepsiburadaSku' => 'HB-LST-1',
            ],
        ]);

        $listing = new ChannelListing([
            'store_id' => 1,
            'channel_product_id' => 1,
            'listing_id' => 'HB-LST-1',
            'listing_status' => 'active',
            'sale_price' => 1299.90,
            'stock_quantity' => 12,
            'currency' => 'TRY',
        ]);

        $listing->setRelation('store', $store);
        $listing->setRelation('channelProduct', $channelProduct);

        return $listing;
    }

    protected function makePackage(): ChannelOrderPackage
    {
        $store = $this->makeStore([
            'api_key' => 'service-key',
            'extra_user' => 'zem_dev',
        ]);

        $package = new ChannelOrderPackage([
            'store_id' => 1,
            'channel_order_id' => 1,
            'external_package_id' => 'HB-PKG-1',
            'package_number' => 'HB-PKG-1',
            'package_status' => 'Open',
        ]);

        $package->id = 1;
        $package->setRelation('store', $store);

        return $package;
    }
}
