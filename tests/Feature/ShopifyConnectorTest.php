<?php

namespace Tests\Feature;

use App\Models\ChannelListing;
use App\Models\ChannelProduct;
use App\Models\IntegrationConnection;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\Connectors\ShopifyConnector;
use App\Services\Marketplace\MarketplaceConnectorManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShopifyConnectorTest extends TestCase
{
    public function test_manager_resolves_shopify_connector_and_legacy_alias(): void
    {
        $manager = app(MarketplaceConnectorManager::class);

        $this->assertInstanceOf(ShopifyConnector::class, $manager->resolve('shopify'));
        $this->assertInstanceOf(ShopifyConnector::class, $manager->resolve('shoppy'));
        $this->assertTrue(app(ShopifyConnector::class)->capabilities()['webhooks']);
        $this->assertTrue(app(ShopifyConnector::class)->capabilities()['orders']);
        $this->assertTrue(app(ShopifyConnector::class)->capabilities()['products']);
        $this->assertTrue(app(ShopifyConnector::class)->capabilities()['finance']);
        $this->assertTrue(app(ShopifyConnector::class)->capabilities()['claims']);
        $this->assertTrue(app(ShopifyConnector::class)->capabilities()['price_push']);
        $this->assertTrue(app(ShopifyConnector::class)->capabilities()['stock_push']);
    }

    public function test_it_verifies_shopify_connection_with_graphql_probe(): void
    {
        Http::fake([
            'https://ornek.myshopify.com/admin/api/2026-07/graphql.json' => Http::response([
                'data' => [
                    'shop' => [
                        'name' => 'ZOLM Demo',
                        'myshopifyDomain' => 'ornek.myshopify.com',
                    ],
                ],
            ]),
        ]);

        $store = new MarketplaceStore([
            'marketplace' => 'shopify',
            'store_name' => 'Shopify Test',
            'seller_id' => 'shopify-demo',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'store_url' => 'https://ornek.myshopify.com',
        ]);

        $store->setRelation('connection', new IntegrationConnection([
            'provider' => 'shopify',
            'auth_type' => 'access_token_app_secret',
            'credentials_encrypted' => [
                'api_key' => 'shpat_test_token',
                'api_secret' => 'shopify_app_secret',
                'store_url' => 'https://ornek.myshopify.com',
            ],
            'api_base_url' => 'https://ornek.myshopify.com',
            'status' => 'configured',
        ]));

        $result = app(ShopifyConnector::class)->testConnection($store);

        $this->assertTrue($result['ok']);
        $this->assertSame('ZOLM Demo', $result['meta']['shop_name']);
        $this->assertSame('ornek.myshopify.com', $result['meta']['shop_domain']);
    }

    public function test_it_verifies_shopify_webhook_hmac(): void
    {
        $secret = 'shopify_app_secret';
        $payload = json_encode(['id' => 123, 'name' => 'Test'], JSON_THROW_ON_ERROR);
        $signature = base64_encode(hash_hmac('sha256', $payload, $secret, true));

        $request = Request::create('/webhook', 'POST', [], [], [], [], $payload);
        $request->headers->set('X-Shopify-Hmac-SHA256', $signature);
        $request->headers->set('X-Shopify-Topic', 'orders/create');
        $request->headers->set('X-Shopify-Webhook-Id', 'evt-1');
        $request->headers->set('X-Shopify-Shop-Domain', 'ornek.myshopify.com');

        $connection = new IntegrationConnection([
            'provider' => 'shopify',
            'credentials_encrypted' => [
                'api_secret' => $secret,
            ],
            'webhook_secret' => $secret,
        ]);

        $connector = app(ShopifyConnector::class);

        $this->assertTrue($connector->verifyWebhookSignature($request, $connection));
        $this->assertSame('orders/create', $connector->extractWebhookMetadata($request)['event_type']);
    }

    public function test_it_pulls_and_normalizes_shopify_orders(): void
    {
        Http::fake([
            'https://ornek.myshopify.com/admin/api/2026-07/graphql.json' => Http::response([
                'data' => [
                    'orders' => [
                        'pageInfo' => [
                            'hasNextPage' => false,
                            'endCursor' => null,
                        ],
                        'nodes' => [
                            [
                                'id' => 'gid://shopify/Order/1001',
                                'legacyResourceId' => '1001',
                                'name' => '#1001',
                                'email' => 'musteri@example.com',
                                'phone' => '5551112233',
                                'createdAt' => '2026-03-23T08:00:00Z',
                                'updatedAt' => '2026-03-23T09:00:00Z',
                                'cancelledAt' => null,
                                'closedAt' => null,
                                'displayFinancialStatus' => 'PAID',
                                'displayFulfillmentStatus' => 'UNFULFILLED',
                                'customer' => [
                                    'displayName' => 'Merve Test',
                                    'firstName' => 'Merve',
                                    'lastName' => 'Test',
                                    'email' => 'musteri@example.com',
                                    'phone' => '5551112233',
                                ],
                                'billingAddress' => [
                                    'name' => 'Merve Test',
                                    'company' => 'ZOLM LTD',
                                    'phone' => '5551112233',
                                    'city' => 'Ankara',
                                    'province' => 'Cankaya',
                                    'countryCodeV2' => 'TR',
                                ],
                                'shippingAddress' => [
                                    'name' => 'Merve Test',
                                    'company' => null,
                                    'phone' => '5551112233',
                                    'city' => 'Ankara',
                                    'province' => 'Cankaya',
                                    'countryCodeV2' => 'TR',
                                ],
                                'lineItems' => [
                                    'nodes' => [
                                        [
                                            'id' => 'gid://shopify/LineItem/9001',
                                            'sku' => 'STK-001',
                                            'name' => 'Test Koltuk',
                                            'quantity' => 2,
                                            'originalUnitPriceSet' => [
                                                'shopMoney' => [
                                                    'amount' => '1250.00',
                                                    'currencyCode' => 'TRY',
                                                ],
                                            ],
                                            'originalTotalSet' => [
                                                'shopMoney' => [
                                                    'amount' => '2500.00',
                                                    'currencyCode' => 'TRY',
                                                ],
                                            ],
                                            'discountedTotalSet' => [
                                                'shopMoney' => [
                                                    'amount' => '2300.00',
                                                    'currencyCode' => 'TRY',
                                                ],
                                            ],
                                            'totalDiscountSet' => [
                                                'shopMoney' => [
                                                    'amount' => '200.00',
                                                    'currencyCode' => 'TRY',
                                                ],
                                            ],
                                            'taxLines' => [
                                                ['rate' => 0.1],
                                            ],
                                            'variant' => [
                                                'id' => 'gid://shopify/ProductVariant/7001',
                                                'sku' => 'STK-001',
                                                'barcode' => '869000000001',
                                            ],
                                        ],
                                    ],
                                ],
                                'fulfillments' => [
                                    'nodes' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $result = app(ShopifyConnector::class)->pullOrders($this->makeStore());

        $this->assertCount(1, $result['items']);
        $this->assertSame('#1001', $result['items'][0]['order']['order_number']);
        $this->assertSame('gid://shopify/Order/1001', $result['items'][0]['package']['external_package_id']);
        $this->assertSame('STK-001', $result['items'][0]['items'][0]['stock_code']);
        $this->assertSame(10.0, $result['items'][0]['items'][0]['vat_rate']);
    }

    public function test_it_pulls_and_normalizes_shopify_products(): void
    {
        Http::fake([
            'https://ornek.myshopify.com/admin/api/2026-07/graphql.json' => Http::response([
                'data' => [
                    'products' => [
                        'pageInfo' => [
                            'hasNextPage' => false,
                            'endCursor' => null,
                        ],
                        'nodes' => [
                            [
                                'id' => 'gid://shopify/Product/501',
                                'legacyResourceId' => '501',
                                'title' => 'ZOLM Berjer',
                                'vendor' => 'ZOLM',
                                'productType' => 'Berjer',
                                'status' => 'ACTIVE',
                                'publishedAt' => '2026-03-22T08:00:00Z',
                                'updatedAt' => '2026-03-23T08:00:00Z',
                                'variants' => [
                                    'nodes' => [
                                        [
                                            'id' => 'gid://shopify/ProductVariant/701',
                                            'legacyResourceId' => '701',
                                            'title' => 'Ceviz',
                                            'sku' => 'STK-701',
                                            'barcode' => '869000000701',
                                            'price' => '3499.90',
                                            'compareAtPrice' => '3799.90',
                                            'inventoryQuantity' => 12,
                                            'taxable' => true,
                                            'inventoryItem' => [
                                                'id' => 'gid://shopify/InventoryItem/801',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $result = app(ShopifyConnector::class)->pullProducts($this->makeStore());

        $this->assertCount(1, $result['items']);
        $this->assertSame('STK-701', $result['items'][0]['product']['stock_code']);
        $this->assertSame('ZOLM Berjer / Ceviz', $result['items'][0]['product']['title']);
        $this->assertSame('active', $result['items'][0]['listing']['listing_status']);
        $this->assertSame(12, $result['items'][0]['listing']['stock_quantity']);
        $this->assertSame('gid://shopify/InventoryItem/801', data_get($result, 'items.0.product.raw_payload.variant.inventoryItem.id'));
    }

    public function test_it_pulls_and_normalizes_shopify_financial_events(): void
    {
        Http::fake([
            'https://ornek.myshopify.com/admin/api/2026-07/graphql.json' => Http::response([
                'data' => [
                    'orders' => [
                        'pageInfo' => [
                            'hasNextPage' => false,
                            'endCursor' => null,
                        ],
                        'nodes' => [
                            [
                                'id' => 'gid://shopify/Order/1001',
                                'legacyResourceId' => '1001',
                                'name' => '#1001',
                                'transactions' => [
                                    'nodes' => [
                                        [
                                            'id' => 'gid://shopify/OrderTransaction/1',
                                            'kind' => 'SALE',
                                            'status' => 'SUCCESS',
                                            'gateway' => 'shopify_payments',
                                            'formattedGateway' => 'Shopify Payments',
                                            'paymentId' => 'pay_001',
                                            'processedAt' => '2026-03-23T09:15:00Z',
                                            'createdAt' => '2026-03-23T09:10:00Z',
                                            'parentTransaction' => null,
                                            'amountSet' => [
                                                'shopMoney' => [
                                                    'amount' => '2300.00',
                                                    'currencyCode' => 'TRY',
                                                ],
                                            ],
                                            'fees' => [
                                                [
                                                    'amount' => [
                                                        'amount' => '69.00',
                                                        'currencyCode' => 'TRY',
                                                    ],
                                                ],
                                            ],
                                        ],
                                        [
                                            'id' => 'gid://shopify/OrderTransaction/2',
                                            'kind' => 'REFUND',
                                            'status' => 'SUCCESS',
                                            'gateway' => 'shopify_payments',
                                            'formattedGateway' => 'Shopify Payments',
                                            'paymentId' => 'pay_001',
                                            'processedAt' => '2026-03-24T09:15:00Z',
                                            'createdAt' => '2026-03-24T09:10:00Z',
                                            'parentTransaction' => [
                                                'id' => 'gid://shopify/OrderTransaction/1',
                                            ],
                                            'amountSet' => [
                                                'shopMoney' => [
                                                    'amount' => '300.00',
                                                    'currencyCode' => 'TRY',
                                                ],
                                            ],
                                            'fees' => [],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $result = app(ShopifyConnector::class)->pullFinancialEvents($this->makeStore());

        $this->assertCount(3, $result['items']);
        $this->assertSame('shopify_transaction', $result['items'][0]['event_source']);
        $this->assertSame('sale', $result['items'][0]['event_type']);
        $this->assertSame('credit', $result['items'][0]['direction']);
        $this->assertSame(2300.0, $result['items'][0]['amount']);
        $this->assertSame('#1001', $result['items'][0]['order_number']);
        $this->assertSame('pay_001', $result['items'][0]['reference_number']);
        $this->assertSame('shopify_fee', $result['items'][1]['event_source']);
        $this->assertSame('fee', $result['items'][1]['event_type']);
        $this->assertSame('debit', $result['items'][1]['direction']);
        $this->assertSame(69.0, $result['items'][1]['amount']);
        $this->assertSame('refund', $result['items'][2]['event_type']);
        $this->assertSame('debit', $result['items'][2]['direction']);
        $this->assertSame(300.0, $result['items'][2]['amount']);
        $this->assertSame('order_transactions', data_get($result, 'meta.finance_mode'));
    }

    public function test_it_pulls_and_normalizes_shopify_returns_as_claims(): void
    {
        Http::fake([
            'https://ornek.myshopify.com/admin/api/2026-07/graphql.json' => Http::response([
                'data' => [
                    'orders' => [
                        'pageInfo' => [
                            'hasNextPage' => false,
                            'endCursor' => null,
                        ],
                        'nodes' => [[
                            'id' => 'gid://shopify/Order/1001',
                            'legacyResourceId' => '1001',
                            'name' => '#1001',
                            'customer' => [
                                'displayName' => 'Merve Test',
                            ],
                            'fulfillments' => [
                                'nodes' => [[
                                    'trackingInfo' => [[
                                        'company' => 'Yurtiçi Kargo',
                                        'number' => 'YK123',
                                        'url' => 'https://example.test/YK123',
                                    ]],
                                ]],
                            ],
                            'returns' => [
                                'nodes' => [[
                                    'id' => 'gid://shopify/Return/501',
                                    'name' => '#R501',
                                    'status' => 'REQUESTED',
                                    'createdAt' => '2026-07-21T09:00:00Z',
                                    'closedAt' => null,
                                    'requestApprovedAt' => null,
                                    'totalQuantity' => 1,
                                    'returnLineItems' => [
                                        'nodes' => [[
                                            'id' => 'gid://shopify/ReturnLineItem/701',
                                            'quantity' => 1,
                                            'processableQuantity' => 1,
                                            'processedQuantity' => 0,
                                            'refundableQuantity' => 1,
                                            'refundedQuantity' => 0,
                                            'customerNote' => 'Bedeni olmadı',
                                            'returnReasonNote' => 'Beden uyumsuz',
                                            'fulfillmentLineItem' => [
                                                'id' => 'gid://shopify/FulfillmentLineItem/601',
                                                'lineItem' => [
                                                    'id' => 'gid://shopify/LineItem/9001',
                                                    'name' => 'Test Ürünü',
                                                    'sku' => 'STK-001',
                                                    'variant' => [
                                                        'id' => 'gid://shopify/ProductVariant/7001',
                                                        'sku' => 'STK-001',
                                                        'barcode' => '869000000001',
                                                    ],
                                                ],
                                            ],
                                        ]],
                                    ],
                                ]],
                            ],
                        ]],
                    ],
                ],
            ]),
        ]);

        $result = app(ShopifyConnector::class)->pullClaims($this->makeStore());

        $this->assertCount(1, $result['items']);
        $this->assertSame('gid://shopify/Return/501', data_get($result, 'items.0.external_claim_id'));
        $this->assertSame('#1001', data_get($result, 'items.0.order_number'));
        $this->assertSame('REQUESTED', data_get($result, 'items.0.status'));
        $this->assertSame('YK123', data_get($result, 'items.0.cargo_tracking_number'));
        $this->assertSame('STK-001', data_get($result, 'items.0.items.0.stock_code'));
        $this->assertSame('read_returns', data_get($result, 'meta.required_scope'));

        Http::assertSent(function ($request) {
            $query = (string) data_get($request->data(), 'query', '');

            return str_contains($query, 'query ZolmReturns')
                && str_contains($query, 'returns(first: 100)')
                && str_contains($query, 'returnLineItems(first: 100)');
        });
    }

    public function test_it_pushes_price_to_shopify_variant_mutation(): void
    {
        Http::fake([
            'https://ornek.myshopify.com/admin/api/2026-07/graphql.json' => Http::response([
                'data' => [
                    'productVariantsBulkUpdate' => [
                        'product' => ['id' => 'gid://shopify/Product/501'],
                        'productVariants' => [[
                            'id' => 'gid://shopify/ProductVariant/701',
                            'price' => '3999.90',
                            'compareAtPrice' => '4299.90',
                        ]],
                        'userErrors' => [],
                    ],
                ],
            ]),
        ]);

        $result = app(ShopifyConnector::class)->pushPrice($this->makeListing(), 3999.90, [
            'list_price' => 4299.90,
        ]);

        $this->assertSame('completed', $result['status']);
        $this->assertSame('gid://shopify/ProductVariant/701', $result['external_action_id']);

        Http::assertSent(function ($request) {
            $body = $request->data();
            $variables = $body['variables'] ?? [];

            return $request->url() === 'https://ornek.myshopify.com/admin/api/2026-07/graphql.json'
                && $request->hasHeader('X-Shopify-Access-Token', 'shpat_test_token')
                && ($variables['productId'] ?? null) === 'gid://shopify/Product/501'
                && data_get($variables, 'variants.0.id') === 'gid://shopify/ProductVariant/701'
                && data_get($variables, 'variants.0.price') === '3999.90'
                && data_get($variables, 'variants.0.compareAtPrice') === '4299.90';
        });
    }

    public function test_it_pushes_stock_to_shopify_inventory_mutation_with_primary_location_fallback(): void
    {
        Http::fake([
            'https://ornek.myshopify.com/admin/api/2026-07/graphql.json' => Http::sequence()
                ->push([
                    'data' => [
                        'location' => [
                            'id' => 'gid://shopify/Location/1',
                            'name' => 'Primary',
                        ],
                    ],
                ])
                ->push([
                    'data' => [
                        'inventoryAdjustQuantities' => [
                            'userErrors' => [],
                            'inventoryAdjustmentGroup' => [
                                'createdAt' => '2026-03-23T12:00:00Z',
                                'reason' => 'correction',
                                'referenceDocumentUri' => 'zolm://listing/1/stock-push',
                                'changes' => [[
                                    'name' => 'available',
                                    'delta' => 7,
                                ]],
                            ],
                        ],
                    ],
                ]),
        ]);

        $result = app(ShopifyConnector::class)->pushStock($this->makeListing(), 15);

        $this->assertSame('completed', $result['status']);
        $this->assertSame(7, $result['delta']);
        $this->assertSame('gid://shopify/Location/1', $result['location_id']);

        Http::assertSent(function ($request) {
            $body = $request->data();
            $query = (string) ($body['query'] ?? '');
            $variables = $body['variables'] ?? [];

            if (str_contains($query, 'query ZolmPrimaryLocation')) {
                return true;
            }

            return str_contains($query, 'mutation ZolmAdjustInventory')
                && data_get($variables, 'input.changes.0.inventoryItemId') === 'gid://shopify/InventoryItem/801'
                && data_get($variables, 'input.changes.0.locationId') === 'gid://shopify/Location/1'
                && data_get($variables, 'input.changes.0.delta') === 7;
        });
    }

    protected function makeStore(): MarketplaceStore
    {
        $store = new MarketplaceStore([
            'marketplace' => 'shopify',
            'store_name' => 'Shopify Test',
            'seller_id' => 'shopify-demo',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'store_url' => 'https://ornek.myshopify.com',
        ]);

        $store->setRelation('connection', new IntegrationConnection([
            'provider' => 'shopify',
            'auth_type' => 'access_token_app_secret',
            'credentials_encrypted' => [
                'api_key' => 'shpat_test_token',
                'api_secret' => 'shopify_app_secret',
                'store_url' => 'https://ornek.myshopify.com',
            ],
            'api_base_url' => 'https://ornek.myshopify.com',
            'status' => 'configured',
        ]));

        return $store;
    }

    protected function makeListing(): ChannelListing
    {
        $store = $this->makeStore();

        $channelProduct = new ChannelProduct([
            'store_id' => 1,
            'external_product_id' => 'gid://shopify/ProductVariant/701',
            'external_parent_id' => 'gid://shopify/Product/501',
            'stock_code' => 'STK-701',
            'barcode' => '869000000701',
            'title' => 'ZOLM Berjer / Ceviz',
            'raw_payload' => [
                'id' => 'gid://shopify/Product/501',
                'variant' => [
                    'id' => 'gid://shopify/ProductVariant/701',
                    'inventoryItem' => [
                        'id' => 'gid://shopify/InventoryItem/801',
                    ],
                ],
            ],
        ]);

        $listing = new ChannelListing([
            'store_id' => 1,
            'channel_product_id' => 1,
            'listing_id' => 'gid://shopify/ProductVariant/701',
            'listing_status' => 'active',
            'sale_price' => 3499.90,
            'list_price' => 3799.90,
            'stock_quantity' => 8,
            'currency' => 'TRY',
        ]);

        $listing->id = 1;
        $listing->setRelation('store', $store);
        $listing->setRelation('channelProduct', $channelProduct);

        return $listing;
    }
}
