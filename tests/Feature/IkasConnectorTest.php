<?php

namespace Tests\Feature;

use App\Models\ChannelListing;
use App\Models\ChannelProduct;
use App\Models\IntegrationConnection;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\Connectors\IkasConnector;
use App\Services\Marketplace\MarketplaceConnectorManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IkasConnectorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_manager_resolves_ikas_connector_with_truthful_capabilities(): void
    {
        $connector = app(MarketplaceConnectorManager::class)->resolve('ikas');

        $this->assertInstanceOf(IkasConnector::class, $connector);
        $this->assertTrue($connector->capabilities()['orders']);
        $this->assertTrue($connector->capabilities()['products']);
        $this->assertTrue($connector->capabilities()['finance']);
        $this->assertTrue($connector->capabilities()['webhooks']);
        $this->assertTrue($connector->capabilities()['price_push']);
        $this->assertTrue($connector->capabilities()['stock_push']);
        $this->assertTrue($connector->capabilities()['claims']);
        $this->assertFalse($connector->capabilities()['questions']);
    }

    public function test_it_uses_client_credentials_and_verifies_merchant(): void
    {
        Http::fake([
            'https://api.myikas.com/api/admin/oauth/token' => Http::response([
                'access_token' => 'ikas-access-token',
                'token_type' => 'Bearer',
                'expires_in' => 14400,
            ]),
            'https://api.myikas.com/api/v2/admin/graphql' => Http::response([
                'data' => [
                    'getMerchant' => [
                        'id' => 'merchant-1',
                        'merchantName' => 'ZOLM Mobilya',
                        'storeName' => 'ZOLM',
                        'email' => 'test@example.com',
                        'phoneNumber' => '5551112233',
                        'region' => 'TR',
                    ],
                ],
            ]),
        ]);

        $result = app(IkasConnector::class)->testConnection($this->makeStore());

        $this->assertTrue($result['ok']);
        $this->assertSame('merchant-1', data_get($result, 'meta.merchant_id'));
        $this->assertSame('ZOLM Mobilya', data_get($result, 'meta.merchant_name'));

        Http::assertSent(function ($request): bool {
            if ($request->url() === 'https://api.myikas.com/api/admin/oauth/token') {
                return data_get($request->data(), 'grant_type') === 'client_credentials'
                    && data_get($request->data(), 'client_id') === 'client-id'
                    && data_get($request->data(), 'client_secret') === 'client-secret';
            }

            return $request->url() === 'https://api.myikas.com/api/v2/admin/graphql'
                && $request->hasHeader('Authorization', 'Bearer ikas-access-token')
                && str_contains((string) data_get($request->data(), 'query'), 'getMerchant');
        });
    }

    public function test_it_pulls_orders_with_customer_payment_invoice_package_and_line_payloads(): void
    {
        Http::fake([
            'https://api.myikas.com/api/admin/oauth/token' => Http::response(['access_token' => 'token']),
            'https://api.myikas.com/api/v2/admin/graphql' => Http::response([
                'data' => [
                    'listOrder' => [
                        'count' => 1,
                        'hasNext' => false,
                        'limit' => 100,
                        'page' => 1,
                        'data' => [[
                            'id' => 'order-1',
                            'orderNumber' => 'IKS-1001',
                            'status' => 'CREATED',
                            'orderPaymentStatus' => 'PAID',
                            'orderPackageStatus' => 'FULFILLED',
                            'orderedAt' => 1784671200000,
                            'updatedAt' => 1784674800000,
                            'currencyCode' => 'TRY',
                            'totalPrice' => 2500,
                            'totalFinalPrice' => 2300,
                            'customer' => [
                                'id' => 'customer-1',
                                'fullName' => 'Merve Test',
                                'email' => 'merve@example.com',
                                'phone' => '5551112233',
                            ],
                            'billingAddress' => [
                                'firstName' => 'Merve',
                                'lastName' => 'Test',
                                'company' => 'ZOLM LTD',
                                'taxNumber' => '1234567890',
                            ],
                            'shippingAddress' => [
                                'phone' => '5551112233',
                                'city' => ['name' => 'Ankara'],
                                'district' => ['name' => 'Çankaya'],
                                'country' => ['iso2' => 'TR'],
                            ],
                            'paymentMethods' => [[
                                'type' => 'CREDIT_CARD',
                                'price' => 2300,
                                'paymentGatewayName' => 'Pay with ikas',
                            ]],
                            'invoices' => [[
                                'id' => 'invoice-1',
                                'invoiceNumber' => 'FTR-1',
                                'hasPdf' => true,
                            ]],
                            'orderAdjustments' => [[
                                'name' => 'Kampanya',
                                'amount' => 200,
                                'type' => 'DECREMENT',
                            ]],
                            'orderLineItems' => [[
                                'id' => 'line-1',
                                'quantity' => 2,
                                'price' => 2500,
                                'unitPrice' => 1250,
                                'finalPrice' => 2300,
                                'taxValue' => 20,
                                'status' => 'CREATED',
                                'variant' => [
                                    'id' => 'variant-1',
                                    'productId' => 'product-1',
                                    'sku' => 'STK-001',
                                    'name' => 'ZOLM Berjer',
                                    'barcodeList' => ['869000000001'],
                                ],
                            ]],
                            'orderPackages' => [[
                                'id' => 'package-1',
                                'orderPackageNumber' => 'PKT-1',
                                'orderLineItemIds' => ['line-1'],
                                'orderPackageFulfillStatus' => 'FULFILLED',
                                'updatedAt' => 1784674800000,
                                'deleted' => false,
                                'trackingInfo' => [
                                    'cargoCompany' => 'Yurtiçi Kargo',
                                    'trackingNumber' => 'YK123',
                                    'barcode' => 'BC123',
                                ],
                            ]],
                        ]],
                    ],
                ],
            ]),
        ]);

        $result = app(IkasConnector::class)->pullOrders($this->makeStore(), [
            'start_date' => '2026-07-20 00:00:00',
        ]);

        $this->assertCount(1, $result['items']);
        $this->assertSame('IKS-1001', data_get($result, 'items.0.order.order_number'));
        $this->assertSame('shipped', data_get($result, 'items.0.order.order_status'));
        $this->assertSame('1234567890', data_get($result, 'items.0.order.billing_tax_number'));
        $this->assertSame('package-1', data_get($result, 'items.0.package.external_package_id'));
        $this->assertSame('YK123', data_get($result, 'items.0.package.cargo_tracking_number'));
        $this->assertSame('STK-001', data_get($result, 'items.0.items.0.stock_code'));
        $this->assertSame(200.0, data_get($result, 'items.0.items.0.discount_amount'));
        $this->assertSame('FTR-1', data_get($result, 'items.0.order.raw_payload.invoices.0.invoiceNumber'));

        Http::assertSent(function ($request): bool {
            $query = (string) data_get($request->data(), 'query', '');

            return ! str_contains($query, 'ZolmIkasOrders') || (
                str_contains($query, 'paymentMethods')
                && str_contains($query, 'invoices')
                && str_contains($query, 'orderAdjustments')
                && is_int(data_get($request->data(), 'variables.updatedAt.gte'))
            );
        });
    }

    public function test_it_pulls_products_with_variants_prices_stocks_images_attributes_and_metadata(): void
    {
        Http::fake([
            'https://api.myikas.com/api/admin/oauth/token' => Http::response(['access_token' => 'token']),
            'https://api.myikas.com/api/v2/admin/graphql' => Http::response([
                'data' => [
                    'listProduct' => [
                        'count' => 1,
                        'hasNext' => false,
                        'limit' => 100,
                        'page' => 1,
                        'data' => [[
                            'id' => 'product-1',
                            'name' => 'ZOLM Berjer',
                            'description' => '<p>Masif berjer</p>',
                            'createdAt' => 1784671200000,
                            'deleted' => false,
                            'brand' => ['id' => 'brand-1', 'name' => 'ZOLM'],
                            'categories' => [['id' => 'cat-1', 'name' => 'Berjer']],
                            'attributes' => [['productAttributeId' => 'attr-1', 'value' => 'Masif']],
                            'translations' => [['locale' => 'tr', 'name' => 'ZOLM Berjer']],
                            'metaData' => ['pageTitle' => 'ZOLM Berjer'],
                            'salesChannels' => [['id' => 'channel-1', 'status' => 'ACTIVE']],
                            'variants' => [[
                                'id' => 'variant-1',
                                'sku' => 'STK-001',
                                'barcodeList' => ['869000000001'],
                                'isActive' => true,
                                'deleted' => false,
                                'taxValue' => 20,
                                'images' => [['imageId' => 'image-1', 'fileName' => 'berjer.jpg', 'isMain' => true]],
                                'prices' => [[
                                    'priceListId' => 'price-list-1',
                                    'sellPrice' => 3799.90,
                                    'discountPrice' => 3499.90,
                                    'currencyCode' => 'TRY',
                                ]],
                                'stocks' => [
                                    ['stockLocationId' => 'location-1', 'stockCount' => 8, 'deleted' => false],
                                    ['stockLocationId' => 'location-2', 'stockCount' => 4, 'deleted' => false],
                                ],
                                'variantValueIds' => [[
                                    'variantTypeId' => 'type-1',
                                    'variantValueId' => 'value-1',
                                    'variantValueName' => 'Ceviz',
                                ]],
                            ]],
                        ]],
                    ],
                ],
            ]),
        ]);

        $result = app(IkasConnector::class)->pullProducts($this->makeStore());

        $this->assertCount(1, $result['items']);
        $this->assertSame('variant-1', data_get($result, 'items.0.product.external_product_id'));
        $this->assertSame('product-1', data_get($result, 'items.0.product.external_parent_id'));
        $this->assertSame('ZOLM Berjer / Ceviz', data_get($result, 'items.0.product.title'));
        $this->assertSame('<p>Masif berjer</p>', data_get($result, 'items.0.product.description'));
        $this->assertSame('image-1', data_get($result, 'items.0.product.images.0.imageId'));
        $this->assertSame('ZOLM Berjer', data_get($result, 'items.0.product.attributes.metadata.pageTitle'));
        $this->assertSame(3499.90, data_get($result, 'items.0.listing.sale_price'));
        $this->assertSame(12, data_get($result, 'items.0.listing.stock_quantity'));
        $this->assertSame('location-1', data_get($result, 'items.0.product.raw_payload.variant.stocks.0.stockLocationId'));
    }

    public function test_it_pulls_order_transactions_as_financial_events(): void
    {
        Http::fake([
            'https://api.myikas.com/api/admin/oauth/token' => Http::response(['access_token' => 'token']),
            'https://api.myikas.com/api/v2/admin/graphql' => Http::sequence()
                ->push([
                    'data' => [
                        'listOrder' => [
                            'hasNext' => false,
                            'data' => [['id' => 'order-1', 'orderNumber' => 'IKS-1001']],
                        ],
                    ],
                ])
                ->push([
                    'data' => [
                        'listOrderTransactions' => [[
                            'id' => 'transaction-1',
                            'orderId' => 'order-1',
                            'amount' => 2300,
                            'currencyCode' => 'TRY',
                            'type' => 'SALE',
                            'status' => 'SUCCESS',
                            'paymentMethod' => 'CREDIT_CARD',
                            'paymentGatewayName' => 'Pay with ikas',
                            'gatewayReferenceId' => 'gateway-1',
                            'processedAt' => 1784674800000,
                            'lineItems' => [[
                                'id' => 'line-1',
                                'quantity' => 2,
                                'finalPrice' => 2300,
                                'variant' => ['id' => 'variant-1', 'sku' => 'STK-001'],
                            ]],
                        ]],
                    ],
                ]),
        ]);

        $result = app(IkasConnector::class)->pullFinancialEvents($this->makeStore());

        $this->assertCount(1, $result['items']);
        $this->assertSame('ikas_order_transaction', data_get($result, 'items.0.event_source'));
        $this->assertSame('IKS-1001', data_get($result, 'items.0.order_number'));
        $this->assertSame('sale', data_get($result, 'items.0.event_type'));
        $this->assertSame('credit', data_get($result, 'items.0.direction'));
        $this->assertSame('STK-001', data_get($result, 'items.0.raw_payload.lineItems.0.variant.sku'));
        $this->assertSame(1, data_get($result, 'meta.orders_scanned'));
    }

    public function test_it_derives_claims_from_ikas_refund_order_states_without_losing_lines(): void
    {
        Http::fake([
            'https://api.myikas.com/api/admin/oauth/token' => Http::response(['access_token' => 'token']),
            'https://api.myikas.com/api/v2/admin/graphql' => Http::response([
                'data' => [
                    'listOrder' => [
                        'hasNext' => false,
                        'data' => [[
                            'id' => 'order-1',
                            'orderNumber' => 'IKS-1001',
                            'status' => 'REFUND_REQUESTED',
                            'updatedAt' => 1784674800000,
                            'note' => 'Ürün hasarlı geldi',
                            'customer' => ['fullName' => 'Merve Test'],
                            'orderLineItems' => [[
                                'id' => 'line-1',
                                'quantity' => 1,
                                'variant' => [
                                    'id' => 'variant-1',
                                    'sku' => 'STK-001',
                                    'name' => 'ZOLM Berjer',
                                    'barcodeList' => ['869000000001'],
                                ],
                            ]],
                            'orderPackages' => [[
                                'id' => 'package-1',
                                'orderLineItemIds' => ['line-1'],
                                'refundReasonId' => 'DAMAGED',
                                'deleted' => false,
                                'trackingInfo' => [
                                    'cargoCompany' => 'Yurtiçi Kargo',
                                    'trackingNumber' => 'YK123',
                                ],
                            ]],
                        ]],
                    ],
                ],
            ]),
        ]);

        $result = app(IkasConnector::class)->pullClaims($this->makeStore());

        $this->assertCount(1, $result['items']);
        $this->assertSame('order-1|REFUND_REQUESTED', data_get($result, 'items.0.external_claim_id'));
        $this->assertSame('refund_requested', data_get($result, 'items.0.status'));
        $this->assertSame('DAMAGED', data_get($result, 'items.0.reason_detail'));
        $this->assertSame('STK-001', data_get($result, 'items.0.items.0.stock_code'));
        $this->assertSame('order_refund_status', data_get($result, 'meta.mode'));

        Http::assertSent(function ($request): bool {
            $query = (string) data_get($request->data(), 'query', '');

            return ! str_contains($query, 'ZolmIkasOrders')
                || in_array('REFUND_REQUESTED', data_get($request->data(), 'variables.status.in', []), true);
        });
    }

    public function test_it_pushes_price_and_stock_with_current_ikas_mutations(): void
    {
        Http::fake(function ($request) {
            if ($request->url() === 'https://api.myikas.com/api/admin/oauth/token') {
                return Http::response(['access_token' => 'token']);
            }

            $query = (string) data_get($request->data(), 'query', '');

            if (str_contains($query, 'ZolmIkasUpdateVariantPrices')) {
                return Http::response(['data' => ['updateVariantPrices' => ['isSuccess' => true, 'errorInputs' => []]]]);
            }

            return Http::response(['data' => ['saveVariantStocks' => ['isSuccess' => true, 'errorInputs' => []]]]);
        });

        $listing = $this->makeListing();
        $priceResult = app(IkasConnector::class)->pushPrice($listing, 3999.90);
        $stockResult = app(IkasConnector::class)->pushStock($listing, 15);

        $this->assertSame('completed', $priceResult['status']);
        $this->assertSame(3999.90, $priceResult['price']);
        $this->assertSame('completed', $stockResult['status']);
        $this->assertSame('location-1', $stockResult['stock_location_id']);
        $this->assertSame(15, $stockResult['quantity']);
        Http::assertSentCount(3);

        Http::assertSent(function ($request): bool {
            $query = (string) data_get($request->data(), 'query', '');

            if (str_contains($query, 'ZolmIkasUpdateVariantPrices')) {
                return data_get($request->data(), 'variables.input.variantPriceInputs.0.productId') === 'product-1'
                    && data_get($request->data(), 'variables.input.variantPriceInputs.0.variantId') === 'variant-1'
                    && data_get($request->data(), 'variables.input.variantPriceInputs.0.price.sellPrice') === 3999.90;
            }

            if (str_contains($query, 'ZolmIkasSaveVariantStocks')) {
                return data_get($request->data(), 'variables.input.stockInputs.0.stockLocationId') === 'location-1'
                    && data_get($request->data(), 'variables.input.stockInputs.0.stockCount') === 15;
            }

            return true;
        });
    }

    public function test_it_verifies_and_extracts_ikas_webhook_envelope(): void
    {
        $data = json_encode(['id' => 'order-1', 'orderNumber' => 'IKS-1001'], JSON_THROW_ON_ERROR);
        $payload = [
            'id' => 'webhook-1',
            'createdAt' => '2026-07-22T07:00:00Z',
            'scope' => 'store/order/created',
            'merchantId' => 'merchant-1',
            'data' => $data,
            'signature' => hash_hmac('sha256', $data, 'client-secret'),
            'authorizedAppId' => 'app-1',
        ];
        $request = Request::create('/webhook', 'POST', [], [], [], [], json_encode($payload, JSON_THROW_ON_ERROR));
        $request->headers->set('Content-Type', 'application/json');
        $connector = app(IkasConnector::class);
        $connection = $this->makeStore()->connection;
        $connection->webhook_secret = 'auto-generated-different-secret';

        $this->assertTrue($connector->verifyWebhookSignature($request, $connection));

        $metadata = $connector->extractWebhookMetadata($request);

        $this->assertSame('store/order/created', $metadata['event_type']);
        $this->assertSame('webhook-1', $metadata['external_event_id']);
        $this->assertSame('IKS-1001', data_get($metadata, 'payload.data.orderNumber'));
        $this->assertArrayNotHasKey('signature', data_get($metadata, 'payload.envelope'));
    }

    protected function makeStore(): MarketplaceStore
    {
        $store = new MarketplaceStore([
            'marketplace' => 'ikas',
            'store_name' => 'ikas Test',
            'seller_id' => 'merchant-1',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
        ]);
        $connection = new IntegrationConnection([
            'provider' => 'ikas',
            'auth_type' => 'client_credentials',
            'credentials_encrypted' => [
                'api_key' => 'client-id',
                'api_secret' => 'client-secret',
            ],
            'webhook_secret' => 'client-secret',
            'api_base_url' => 'https://api.myikas.com/api/v2/admin/graphql',
            'status' => 'configured',
        ]);
        $connection->id = 10;
        $store->setRelation('connection', $connection);

        return $store;
    }

    protected function makeListing(): ChannelListing
    {
        $store = $this->makeStore();
        $product = new ChannelProduct([
            'external_product_id' => 'variant-1',
            'external_parent_id' => 'product-1',
            'stock_code' => 'STK-001',
            'raw_payload' => [
                'id' => 'product-1',
                'variant' => [
                    'id' => 'variant-1',
                    'prices' => [['priceListId' => 'price-list-1']],
                    'stocks' => [['stockLocationId' => 'location-1']],
                ],
            ],
        ]);
        $listing = new ChannelListing([
            'listing_id' => 'variant-1',
            'sale_price' => 3499.90,
            'stock_quantity' => 8,
            'currency' => 'TRY',
        ]);
        $listing->id = 1;
        $listing->setRelation('store', $store);
        $listing->setRelation('channelProduct', $product);

        return $listing;
    }
}
