<?php

namespace App\Services\Marketplace\Connectors;

use App\Models\ChannelListing;
use App\Models\IntegrationConnection;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\Contracts\PullsFinancials;
use App\Services\Marketplace\Contracts\PullsOrders;
use App\Services\Marketplace\Contracts\PullsProducts;
use App\Services\Marketplace\Contracts\PushesPrice;
use App\Services\Marketplace\Contracts\PushesStock;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ShopifyConnector extends AbstractMarketplaceConnector implements PullsFinancials, PullsOrders, PullsProducts, PushesPrice, PushesStock
{
    public function providerKey(): string
    {
        return 'shopify';
    }

    public function displayName(): string
    {
        return 'Shopify';
    }

    public function defaultApiBaseUrl(): ?string
    {
        return config('marketplace.shopify.base_url');
    }

    /**
     * @return array<string, bool>
     */
    public function capabilities(): array
    {
        return [
            'orders' => true,
            'products' => true,
            'finance' => true,
            'webhooks' => true,
            'price_push' => true,
            'stock_push' => true,
            'package_status' => false,
            'package_picking' => false,
            'package_invoiced' => false,
            'common_label' => false,
            'package_common_label_create' => false,
            'package_common_label_get' => false,
            'invoice_link' => false,
            'package_invoice_link' => false,
        ];
    }

    public function pullFinancialEvents(MarketplaceStore $store, array $options = []): array
    {
        $pageSize = min(50, max(1, (int) ($options['page_size'] ?? config('marketplace.shopify.finance_page_size', 25))));
        $items = [];
        $cursor = null;
        $hasNextPage = true;
        $query = $this->buildOrderSearchQuery($options);

        while ($hasNextPage) {
            $payload = $this->graphQl($store, <<<'GRAPHQL'
                query ZolmOrderTransactions($first: Int!, $after: String, $query: String) {
                  orders(first: $first, after: $after, sortKey: UPDATED_AT, reverse: true, query: $query) {
                    pageInfo {
                      hasNextPage
                      endCursor
                    }
                    nodes {
                      id
                      legacyResourceId
                      name
                      transactions(first: 50) {
                        nodes {
                          id
                          kind
                          status
                          gateway
                          formattedGateway
                          paymentId
                          processedAt
                          createdAt
                          parentTransaction {
                            id
                          }
                          amountSet {
                            shopMoney {
                              amount
                              currencyCode
                            }
                          }
                          fees {
                            amount {
                              amount
                              currencyCode
                            }
                          }
                        }
                      }
                    }
                  }
                }
            GRAPHQL, [
                'first' => $pageSize,
                'after' => $cursor,
                'query' => $query,
            ]);

            $connection = data_get($payload, 'data.orders', []);
            $nodes = collect(data_get($connection, 'nodes', []))
                ->filter(fn ($row) => is_array($row))
                ->values();

            foreach ($nodes as $node) {
                foreach ($this->normalizeFinancialEvents($node) as $event) {
                    $items[] = $event;
                }
            }

            $hasNextPage = (bool) data_get($connection, 'pageInfo.hasNextPage', false);
            $cursor = data_get($connection, 'pageInfo.endCursor');
        }

        return [
            'items' => $items,
            'meta' => [
                'items_received' => count($items),
                'cursor_after' => now()->toIso8601String(),
                'graphql_query' => $query,
                'finance_mode' => 'order_transactions',
            ],
        ];
    }

    public function pushPrice(ChannelListing $listing, float $price, array $context = []): array
    {
        $listing->loadMissing(['store.connection', 'channelProduct']);

        $productId = $this->resolveProductId($listing);
        $variantId = $this->resolveVariantId($listing);
        $compareAtPrice = data_get($context, 'list_price', $listing->list_price);

        $payload = $this->graphQl($listing->store, <<<'GRAPHQL'
            mutation ZolmUpdateVariantPrice($productId: ID!, $variants: [ProductVariantsBulkInput!]!) {
              productVariantsBulkUpdate(productId: $productId, variants: $variants) {
                product {
                  id
                }
                productVariants {
                  id
                  price
                  compareAtPrice
                }
                userErrors {
                  field
                  message
                }
              }
            }
        GRAPHQL, [
            'productId' => $productId,
            'variants' => [
                array_filter([
                    'id' => $variantId,
                    'price' => $this->decimalString($price),
                    'compareAtPrice' => $compareAtPrice !== null ? $this->decimalString((float) $compareAtPrice) : null,
                ], fn ($value) => $value !== null && $value !== ''),
            ],
        ]);

        $userErrors = collect(data_get($payload, 'data.productVariantsBulkUpdate.userErrors', []))
            ->filter(fn ($row) => is_array($row) && filled($row['message'] ?? null))
            ->values()
            ->all();

        if ($userErrors !== []) {
            throw new \RuntimeException('Shopify fiyat push userErrors: '.json_encode($userErrors, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        return [
            'status' => 'completed',
            'listing_id' => $listing->id,
            'provider' => $this->providerKey(),
            'product_id' => $productId,
            'variant_id' => $variantId,
            'price' => round($price, 2),
            'response' => $payload,
            'external_action_id' => (string) data_get($payload, 'data.productVariantsBulkUpdate.productVariants.0.id', $variantId),
        ];
    }

    public function pushStock(ChannelListing $listing, int $quantity, array $context = []): array
    {
        $listing->loadMissing(['store.connection', 'channelProduct']);

        $inventoryItemId = $this->resolveInventoryItemId($listing);
        $locationId = $this->resolveLocationId($listing->store, $context);
        $currentQuantity = (int) data_get($context, 'current_quantity', $listing->stock_quantity);
        $delta = $quantity - $currentQuantity;

        if ($delta === 0) {
            return [
                'status' => 'completed',
                'listing_id' => $listing->id,
                'provider' => $this->providerKey(),
                'inventory_item_id' => $inventoryItemId,
                'location_id' => $locationId,
                'quantity' => $quantity,
                'response' => ['skipped' => true, 'reason' => 'quantity_unchanged'],
                'external_action_id' => $inventoryItemId,
            ];
        }

        $payload = $this->graphQl($listing->store, <<<'GRAPHQL'
            mutation ZolmAdjustInventory($input: InventoryAdjustQuantitiesInput!) {
              inventoryAdjustQuantities(input: $input) {
                userErrors {
                  field
                  message
                }
                inventoryAdjustmentGroup {
                  createdAt
                  reason
                  referenceDocumentUri
                  changes {
                    name
                    delta
                  }
                }
              }
            }
        GRAPHQL, [
            'input' => [
                'reason' => 'correction',
                'name' => 'available',
                'referenceDocumentUri' => 'zolm://listing/'.$listing->id.'/stock-push',
                'changes' => [[
                    'delta' => $delta,
                    'inventoryItemId' => $inventoryItemId,
                    'locationId' => $locationId,
                ]],
            ],
        ]);

        $userErrors = collect(data_get($payload, 'data.inventoryAdjustQuantities.userErrors', []))
            ->filter(fn ($row) => is_array($row) && filled($row['message'] ?? null))
            ->values()
            ->all();

        if ($userErrors !== []) {
            throw new \RuntimeException('Shopify stok push userErrors: '.json_encode($userErrors, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        return [
            'status' => 'completed',
            'listing_id' => $listing->id,
            'provider' => $this->providerKey(),
            'inventory_item_id' => $inventoryItemId,
            'location_id' => $locationId,
            'quantity' => $quantity,
            'delta' => $delta,
            'response' => $payload,
            'external_action_id' => (string) data_get($payload, 'data.inventoryAdjustQuantities.inventoryAdjustmentGroup.createdAt', $inventoryItemId),
        ];
    }

    public function pullOrders(MarketplaceStore $store, array $options = []): array
    {
        $pageSize = min(100, max(1, (int) ($options['page_size'] ?? config('marketplace.shopify.order_page_size', 50))));
        $items = [];
        $cursor = null;
        $hasNextPage = true;
        $query = $this->buildOrderSearchQuery($options);

        while ($hasNextPage) {
            $payload = $this->graphQl($store, <<<'GRAPHQL'
                query ZolmOrders($first: Int!, $after: String, $query: String) {
                  orders(first: $first, after: $after, sortKey: UPDATED_AT, reverse: true, query: $query) {
                    pageInfo {
                      hasNextPage
                      endCursor
                    }
                    nodes {
                      id
                      legacyResourceId
                      name
                      email
                      phone
                      createdAt
                      updatedAt
                      cancelledAt
                      closedAt
                      displayFinancialStatus
                      displayFulfillmentStatus
                      customer {
                        displayName
                        firstName
                        lastName
                        email
                        phone
                      }
                      billingAddress {
                        name
                        company
                        phone
                        city
                        province
                        countryCodeV2
                      }
                      shippingAddress {
                        name
                        company
                        phone
                        city
                        province
                        countryCodeV2
                      }
                      lineItems(first: 100) {
                        nodes {
                          id
                          sku
                          name
                          quantity
                          originalUnitPriceSet {
                            shopMoney {
                              amount
                              currencyCode
                            }
                          }
                          originalTotalSet {
                            shopMoney {
                              amount
                              currencyCode
                            }
                          }
                          discountedTotalSet {
                            shopMoney {
                              amount
                              currencyCode
                            }
                          }
                          totalDiscountSet {
                            shopMoney {
                              amount
                              currencyCode
                            }
                          }
                          taxLines {
                            rate
                          }
                          variant {
                            id
                            sku
                            barcode
                          }
                        }
                      }
                      fulfillments(first: 1, reverse: true) {
                        nodes {
                          trackingInfo(first: 1) {
                            company
                            number
                            url
                          }
                          createdAt
                        }
                      }
                    }
                  }
                }
            GRAPHQL, [
                'first' => $pageSize,
                'after' => $cursor,
                'query' => $query,
            ]);

            $connection = data_get($payload, 'data.orders', []);
            $nodes = collect(data_get($connection, 'nodes', []))
                ->filter(fn ($row) => is_array($row))
                ->values();

            foreach ($nodes as $node) {
                $items[] = $this->normalizeOrder($node);
            }

            $hasNextPage = (bool) data_get($connection, 'pageInfo.hasNextPage', false);
            $cursor = data_get($connection, 'pageInfo.endCursor');
        }

        return [
            'items' => $items,
            'meta' => [
                'items_received' => count($items),
                'cursor_after' => now()->toIso8601String(),
                'graphql_query' => $query,
            ],
        ];
    }

    public function pullProducts(MarketplaceStore $store, array $options = []): array
    {
        $pageSize = min(100, max(1, (int) ($options['page_size'] ?? config('marketplace.shopify.product_page_size', 50))));
        $items = [];
        $cursor = null;
        $hasNextPage = true;
        $query = $this->buildProductSearchQuery($options);

        while ($hasNextPage) {
            $payload = $this->graphQl($store, <<<'GRAPHQL'
                query ZolmProducts($first: Int!, $after: String, $query: String) {
                  products(first: $first, after: $after, sortKey: UPDATED_AT, reverse: true, query: $query) {
                    pageInfo {
                      hasNextPage
                      endCursor
                    }
                    nodes {
                      id
                      legacyResourceId
                      title
                      vendor
                      productType
                      status
                      publishedAt
                      updatedAt
                      variants(first: 100) {
                        nodes {
                          id
                          legacyResourceId
                          title
                          sku
                          barcode
                          price
                          compareAtPrice
                          inventoryQuantity
                          taxable
                          inventoryItem {
                            id
                          }
                        }
                      }
                    }
                  }
                }
            GRAPHQL, [
                'first' => $pageSize,
                'after' => $cursor,
                'query' => $query,
            ]);

            $connection = data_get($payload, 'data.products', []);
            $nodes = collect(data_get($connection, 'nodes', []))
                ->filter(fn ($row) => is_array($row))
                ->values();

            foreach ($nodes as $node) {
                foreach ($this->normalizeProductGroup($node, $store) as $row) {
                    $items[] = $row;
                }
            }

            $hasNextPage = (bool) data_get($connection, 'pageInfo.hasNextPage', false);
            $cursor = data_get($connection, 'pageInfo.endCursor');
        }

        return [
            'items' => $items,
            'meta' => [
                'items_received' => count($items),
                'cursor_after' => now()->toIso8601String(),
                'graphql_query' => $query,
            ],
        ];
    }

    public function testConnection(MarketplaceStore $store): array
    {
        $response = Http::timeout((int) config('marketplace.shopify.request_timeout', 45))
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'X-Shopify-Access-Token' => $this->resolveAccessToken($store),
            ])
            ->post($this->graphQlEndpoint($store), [
                'query' => <<<'GRAPHQL'
                    query ZolmShopProbe {
                      shop {
                        name
                        myshopifyDomain
                      }
                    }
                GRAPHQL,
            ]);

        if ($response->failed()) {
            return [
                'ok' => false,
                'message' => 'Shopify bağlantısı doğrulanamadı.',
                'meta' => [
                    'provider' => $this->providerKey(),
                    'http_status' => $response->status(),
                    'response' => $response->json(),
                ],
            ];
        }

        $payload = $response->json();

        if (data_get($payload, 'errors') !== null || data_get($payload, 'data.shop') === null) {
            return [
                'ok' => false,
                'message' => 'Shopify bağlantısı yetkilendirme veya scope nedeniyle doğrulanamadı.',
                'meta' => [
                    'provider' => $this->providerKey(),
                    'response' => $payload,
                ],
            ];
        }

        return [
            'ok' => true,
            'message' => 'Shopify bağlantısı doğrulandı.',
            'meta' => [
                'provider' => $this->providerKey(),
                'shop_name' => (string) data_get($payload, 'data.shop.name', ''),
                'shop_domain' => (string) data_get($payload, 'data.shop.myshopifyDomain', ''),
                'mode' => 'graphql_probe',
            ],
        ];
    }

    public function verifyWebhookSignature(Request $request, ?IntegrationConnection $connection): bool
    {
        if (!$connection) {
            return false;
        }

        $sharedSecret = trim((string) ($connection->webhook_secret ?: data_get($connection->credentials_encrypted, 'api_secret')));

        if ($sharedSecret === '') {
            return false;
        }

        $providedSignature = (string) (
            $request->header('X-Shopify-Hmac-SHA256')
            ?: $request->header('X-Shopify-Hmac-Sha256')
            ?: $request->server('HTTP_X_SHOPIFY_HMAC_SHA256')
        );

        if ($providedSignature === '') {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', $request->getContent(), $sharedSecret, true));

        return hash_equals($expected, $providedSignature);
    }

    public function extractWebhookMetadata(Request $request): array
    {
        $payload = $request->json()->all();

        if ($payload === []) {
            $payload = $request->all();
        }

        return [
            'event_type' => $request->header('X-Shopify-Topic')
                ?: data_get($payload, 'topic')
                ?: data_get($payload, 'type'),
            'external_event_id' => (string) (
                $request->header('X-Shopify-Webhook-Id')
                ?: $request->header('X-Shopify-Event-Id')
                ?: data_get($payload, 'id')
                ?: ''
            ),
            'payload' => array_merge(
                is_array($payload) ? $payload : [],
                [
                    '_shop_domain' => (string) $request->header('X-Shopify-Shop-Domain'),
                    '_api_version' => (string) $request->header('X-Shopify-API-Version'),
                ]
            ),
        ];
    }

    protected function resolveAccessToken(MarketplaceStore $store): string
    {
        $credentials = $store->connection?->credentials_encrypted ?? [];
        $token = trim((string) ($credentials['access_token'] ?? $credentials['api_key'] ?? ''));

        if ($token === '') {
            throw new \RuntimeException('Shopify bağlantısı için Admin API access token zorunludur.');
        }

        return $token;
    }

    protected function graphQlEndpoint(MarketplaceStore $store): string
    {
        $base = trim((string) (
            $store->connection?->api_base_url
            ?: data_get($store->connection?->credentials_encrypted ?? [], 'store_url')
            ?: $store->store_url
            ?: config('marketplace.shopify.base_url')
        ));

        if ($base === '') {
            throw new \RuntimeException('Shopify bağlantısı için mağaza URL veya API base URL zorunludur.');
        }

        $base = rtrim($base, '/');

        if (Str::endsWith($base, '/graphql.json')) {
            return $base;
        }

        if (Str::contains($base, '/admin/api/')) {
            return $base.'/graphql.json';
        }

        $version = trim((string) config('marketplace.shopify.api_version', '2025-10'), '/');

        return $base.'/admin/api/'.$version.'/graphql.json';
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    protected function graphQl(MarketplaceStore $store, string $query, array $variables = []): array
    {
        $response = Http::timeout((int) config('marketplace.shopify.request_timeout', 45))
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'X-Shopify-Access-Token' => $this->resolveAccessToken($store),
            ])
            ->post($this->graphQlEndpoint($store), [
                'query' => $query,
                'variables' => $variables,
            ])
            ->throw()
            ->json();

        if (!is_array($response)) {
            throw new \RuntimeException('Shopify GraphQL cevabı beklenen formatta değil.');
        }

        if (data_get($response, 'errors') !== null) {
            throw new \RuntimeException('Shopify GraphQL hatası: '.json_encode(data_get($response, 'errors'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function buildOrderSearchQuery(array $options): string
    {
        $filters = [];
        $startDate = $this->normalizeDateFilter($options['start_date'] ?? null);
        $endDate = $this->normalizeDateFilter($options['end_date'] ?? null);
        $orderNumber = trim((string) ($options['order_number'] ?? ''));

        if ($startDate) {
            $filters[] = 'updated_at:>='.$startDate;
        }

        if ($endDate) {
            $filters[] = 'updated_at:<='.$endDate;
        }

        if ($orderNumber !== '') {
            $filters[] = 'name:'.$this->escapeSearchValue($orderNumber);
        }

        return implode(' ', $filters);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function buildProductSearchQuery(array $options): string
    {
        $filters = [];
        $startDate = $this->normalizeDateFilter($options['start_date'] ?? null);
        $endDate = $this->normalizeDateFilter($options['end_date'] ?? null);

        if ($startDate) {
            $filters[] = 'updated_at:>='.$startDate;
        }

        if ($endDate) {
            $filters[] = 'updated_at:<='.$endDate;
        }

        return implode(' ', $filters);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizeOrder(array $payload): array
    {
        $externalOrderId = (string) (data_get($payload, 'id') ?: data_get($payload, 'legacyResourceId'));
        $orderNumber = (string) (data_get($payload, 'name') ?: data_get($payload, 'legacyResourceId') ?: $externalOrderId);
        $lineItems = collect(data_get($payload, 'lineItems.nodes', []))
            ->filter(fn ($row) => is_array($row))
            ->values();
        $billing = data_get($payload, 'billingAddress', []);
        $shipping = data_get($payload, 'shippingAddress', []);
        $customerName = trim((string) collect([
            data_get($payload, 'customer.firstName'),
            data_get($payload, 'customer.lastName'),
        ])->filter()->implode(' '));
        $fulfillmentStatus = (string) data_get($payload, 'displayFulfillmentStatus', '');
        $financialStatus = (string) data_get($payload, 'displayFinancialStatus', '');
        $tracking = collect(data_get($payload, 'fulfillments.nodes.0.trackingInfo', []))
            ->filter(fn ($row) => is_array($row))
            ->first() ?: [];

        return [
            'order' => [
                'external_order_id' => $externalOrderId,
                'order_number' => $orderNumber,
                'order_status' => $this->normalizeOrderStatus($payload),
                'commercial_type' => filled(data_get($billing, 'company')) ? 'commercial' : 'individual',
                'customer_name' => $customerName !== '' ? $customerName : (data_get($payload, 'customer.displayName') ?: data_get($shipping, 'name') ?: data_get($billing, 'name')),
                'customer_email' => data_get($payload, 'customer.email') ?: data_get($payload, 'email'),
                'customer_phone' => data_get($payload, 'customer.phone') ?: data_get($payload, 'phone') ?: data_get($shipping, 'phone') ?: data_get($billing, 'phone'),
                'billing_name' => data_get($billing, 'company') ?: data_get($billing, 'name'),
                'billing_tax_number' => null,
                'shipment_country' => data_get($shipping, 'countryCodeV2') ?: data_get($billing, 'countryCodeV2') ?: 'TR',
                'shipment_city' => data_get($shipping, 'city') ?: data_get($billing, 'city'),
                'shipment_district' => data_get($shipping, 'province') ?: data_get($billing, 'province'),
                'ordered_at' => $this->normalizeDate(data_get($payload, 'createdAt')),
                'approved_at' => in_array(Str::lower($financialStatus), ['paid', 'partially_paid'], true) ? $this->normalizeDate(data_get($payload, 'updatedAt')) : null,
                'delivered_at' => Str::contains(Str::lower($fulfillmentStatus), 'delivered') ? $this->normalizeDate(data_get($payload, 'closedAt') ?: data_get($payload, 'updatedAt')) : null,
                'cancelled_at' => $this->normalizeDate(data_get($payload, 'cancelledAt')),
                'returned_at' => Str::contains(Str::lower($financialStatus), 'refunded') ? $this->normalizeDate(data_get($payload, 'updatedAt')) : null,
                'raw_payload' => $payload,
            ],
            'package' => [
                'external_package_id' => $externalOrderId,
                'package_number' => (string) (data_get($payload, 'legacyResourceId') ?: $orderNumber),
                'package_status' => $this->normalizeOrderStatus($payload),
                'cargo_company' => data_get($tracking, 'company'),
                'cargo_tracking_number' => data_get($tracking, 'number'),
                'cargo_barcode' => data_get($tracking, 'number'),
                'cargo_desi' => null,
                'shipment_provider' => data_get($tracking, 'company'),
                'shipped_at' => $this->normalizeDate(data_get($payload, 'fulfillments.nodes.0.createdAt')),
                'delivered_at' => Str::contains(Str::lower($fulfillmentStatus), 'delivered') ? $this->normalizeDate(data_get($payload, 'closedAt') ?: data_get($payload, 'updatedAt')) : null,
                'raw_payload' => [
                    'displayFinancialStatus' => $financialStatus,
                    'displayFulfillmentStatus' => $fulfillmentStatus,
                    'tracking' => $tracking,
                ],
            ],
            'items' => $lineItems
                ->map(fn (array $row, int $index) => $this->normalizeOrderLine($row, $externalOrderId, $index, $this->normalizeOrderStatus($payload)))
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeProductGroup(array $payload, MarketplaceStore $store): array
    {
        $variants = collect(data_get($payload, 'variants.nodes', []))
            ->filter(fn ($row) => is_array($row))
            ->values();
        $productId = (string) (data_get($payload, 'id') ?: data_get($payload, 'legacyResourceId'));
        $title = (string) data_get($payload, 'title', '');
        $productType = (string) data_get($payload, 'productType', '');
        $vendor = data_get($payload, 'vendor');
        $publishedAt = $this->normalizeDate(data_get($payload, 'publishedAt') ?: data_get($payload, 'updatedAt'));
        $status = Str::lower((string) data_get($payload, 'status', 'draft'));
        $defaultCurrency = $store->currency ?: 'TRY';

        return $variants
            ->map(function (array $variant) use ($payload, $productId, $title, $productType, $vendor, $publishedAt, $status, $defaultCurrency): array {
                $variantId = (string) (data_get($variant, 'id') ?: data_get($variant, 'legacyResourceId'));
                $variantTitle = trim((string) data_get($variant, 'title', ''));
                $normalizedTitle = $variantTitle !== '' && Str::lower($variantTitle) !== 'default title'
                    ? $title.' / '.$variantTitle
                    : $title;
                $stockCode = (string) (data_get($variant, 'sku') ?: data_get($variant, 'legacyResourceId') ?: $variantId);

                return [
                    'product' => [
                        'external_product_id' => $variantId,
                        'external_parent_id' => $productId,
                        'stock_code' => $stockCode,
                        'barcode' => data_get($variant, 'barcode'),
                        'title' => $normalizedTitle,
                        'brand' => $vendor,
                        'category_name' => $productType !== '' ? $productType : null,
                        'vat_rate' => data_get($variant, 'taxable') === true ? null : 0,
                        'raw_payload' => array_merge($payload, ['variant' => $variant]),
                    ],
                    'listing' => [
                        'listing_id' => $variantId,
                        'listing_status' => $status !== '' ? $status : 'draft',
                        'sale_price' => $this->moneyAmount(data_get($variant, 'price')),
                        'list_price' => $this->moneyAmount(data_get($variant, 'compareAtPrice') ?: data_get($variant, 'price')),
                        'currency' => $defaultCurrency,
                        'stock_quantity' => data_get($variant, 'inventoryQuantity') !== null ? (int) data_get($variant, 'inventoryQuantity') : null,
                        'published_at' => $publishedAt,
                    ],
                ];
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizeOrderLine(array $payload, string $externalOrderId, int $index, string $lineStatus): array
    {
        $quantity = max(1, (int) (data_get($payload, 'quantity') ?: 1));
        $unitPrice = $this->moneyAmount(data_get($payload, 'originalUnitPriceSet.shopMoney.amount'));
        $grossAmount = $this->moneyAmount(data_get($payload, 'originalTotalSet.shopMoney.amount'));
        $billableAmount = $this->moneyAmount(data_get($payload, 'discountedTotalSet.shopMoney.amount')) ?? $grossAmount;
        $discountAmount = $this->moneyAmount(data_get($payload, 'totalDiscountSet.shopMoney.amount'));
        $vatRate = collect(Arr::wrap(data_get($payload, 'taxLines', [])))
            ->filter(fn ($row) => is_array($row))
            ->map(fn (array $row) => data_get($row, 'rate'))
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value) => round(((float) $value) * 100, 2))
            ->first();

        return [
            'external_line_id' => (string) (data_get($payload, 'id') ?: sha1($externalOrderId.'|'.$index)),
            'stock_code' => (string) (data_get($payload, 'variant.sku') ?: data_get($payload, 'sku') ?: data_get($payload, 'variant.id') ?: ''),
            'barcode' => data_get($payload, 'variant.barcode'),
            'product_name' => data_get($payload, 'name'),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'gross_amount' => $grossAmount,
            'discount_amount' => $discountAmount,
            'marketplace_discount_amount' => null,
            'billable_amount' => $billableAmount,
            'commission_rate' => null,
            'vat_rate' => $vatRate,
            'line_status' => $lineStatus,
            'raw_payload' => $payload,
        ];
    }

    /**
     * @param  mixed  $value
     */
    protected function moneyAmount(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }

    protected function normalizeDate(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return CarbonImmutable::parse((string) $value)->toIso8601String();
    }

    protected function normalizeDateFilter(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return CarbonImmutable::parse((string) $value)->toDateTimeString();
    }

    protected function escapeSearchValue(string $value): string
    {
        return '"'.str_replace('"', '\"', trim($value)).'"';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function normalizeOrderStatus(array $payload): string
    {
        if (filled(data_get($payload, 'cancelledAt'))) {
            return 'cancelled';
        }

        $financial = Str::lower((string) data_get($payload, 'displayFinancialStatus', ''));
        $fulfillment = Str::lower((string) data_get($payload, 'displayFulfillmentStatus', ''));

        return match (true) {
            Str::contains($financial, 'refund') => 'returned',
            Str::contains($fulfillment, 'delivered') => 'delivered',
            Str::contains($fulfillment, 'fulfilled'),
            Str::contains($fulfillment, 'partial') => 'shipped',
            Str::contains($financial, 'paid') => 'approved',
            default => 'new',
        };
    }

    /**
     * @param  array<string, mixed>  $orderPayload
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeFinancialEvents(array $orderPayload): array
    {
        $orderNumber = (string) (data_get($orderPayload, 'name') ?: data_get($orderPayload, 'legacyResourceId') ?: data_get($orderPayload, 'id'));
        $externalPackageId = (string) (data_get($orderPayload, 'id') ?: data_get($orderPayload, 'legacyResourceId'));
        $transactions = collect(data_get($orderPayload, 'transactions.nodes', data_get($orderPayload, 'transactions', [])))
            ->filter(fn ($row) => is_array($row))
            ->values();
        $events = [];

        foreach ($transactions as $transaction) {
            $transactionId = (string) (data_get($transaction, 'id') ?: sha1(json_encode($transaction, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: Str::random()));
            $kind = Str::lower((string) data_get($transaction, 'kind', 'other'));
            $status = Str::lower((string) data_get($transaction, 'status', 'posted'));
            $amount = $this->moneyAmount(data_get($transaction, 'amountSet.shopMoney.amount'));
            $currency = (string) (data_get($transaction, 'amountSet.shopMoney.currencyCode') ?: 'TRY');
            $processedAt = $this->normalizeDate(data_get($transaction, 'processedAt') ?: data_get($transaction, 'createdAt'));

            if ($amount === null || round($amount, 2) === 0.0) {
                continue;
            }

            $events[] = [
                'event_source' => 'shopify_transaction',
                'external_event_id' => $transactionId,
                'order_number' => $orderNumber,
                'external_package_id' => $externalPackageId,
                'external_line_id' => null,
                'stock_code' => null,
                'barcode' => null,
                'event_type' => $this->mapTransactionKindToEventType($kind),
                'reference_number' => (string) (data_get($transaction, 'paymentId') ?: data_get($transaction, 'parentTransaction.id') ?: $transactionId),
                'event_date' => $processedAt,
                'due_date' => null,
                'settlement_date' => $processedAt,
                'amount' => $amount,
                'currency' => $currency,
                'direction' => $this->mapTransactionKindToDirection($kind),
                'status' => $status !== '' ? $status : 'posted',
                'notes' => $this->buildTransactionNote($transaction),
                'raw_payload' => $transaction,
            ];

            foreach (collect(data_get($transaction, 'fees', []))->filter(fn ($row) => is_array($row))->values() as $index => $fee) {
                $feeAmount = $this->moneyAmount(data_get($fee, 'amount.amount'));

                if ($feeAmount === null) {
                    continue;
                }

                $events[] = [
                    'event_source' => 'shopify_fee',
                    'external_event_id' => $transactionId.'|fee|'.$index,
                    'order_number' => $orderNumber,
                    'external_package_id' => $externalPackageId,
                    'external_line_id' => null,
                    'stock_code' => null,
                    'barcode' => null,
                    'event_type' => 'fee',
                    'reference_number' => $transactionId,
                    'event_date' => $processedAt,
                    'due_date' => null,
                    'settlement_date' => $processedAt,
                    'amount' => $feeAmount,
                    'currency' => (string) (data_get($fee, 'amount.currencyCode') ?: $currency),
                    'direction' => 'debit',
                    'status' => $status !== '' ? $status : 'posted',
                    'notes' => 'Shopify transaction fee',
                    'raw_payload' => [
                        'transaction' => $transaction,
                        'fee' => $fee,
                    ],
                ];
            }
        }

        return $events;
    }

    protected function mapTransactionKindToEventType(string $kind): string
    {
        return match ($kind) {
            'sale' => 'sale',
            'capture' => 'capture',
            'refund' => 'refund',
            'authorization' => 'authorization',
            'void' => 'void',
            default => $kind !== '' ? $kind : 'other',
        };
    }

    protected function mapTransactionKindToDirection(string $kind): string
    {
        return match ($kind) {
            'sale', 'capture' => 'credit',
            'authorization' => 'credit',
            'refund', 'void' => 'debit',
            default => 'debit',
        };
    }

    /**
     * @param  array<string, mixed>  $transaction
     */
    protected function buildTransactionNote(array $transaction): ?string
    {
        $parts = array_filter([
            data_get($transaction, 'formattedGateway') ?: data_get($transaction, 'gateway'),
            data_get($transaction, 'paymentId') ? 'paymentId: '.data_get($transaction, 'paymentId') : null,
        ]);

        return $parts !== [] ? implode(' | ', $parts) : null;
    }

    protected function decimalString(float $value): string
    {
        return number_format(round($value, 2), 2, '.', '');
    }

    protected function resolveVariantId(ChannelListing $listing): string
    {
        $variantId = trim((string) (
            $listing->listing_id
            ?: data_get($listing->channelProduct?->raw_payload, 'variant.id')
            ?: data_get($listing->channelProduct?->external_product_id)
        ));

        if ($variantId === '') {
            throw new \RuntimeException('Shopify fiyat/stok push için variant ID zorunludur.');
        }

        return $variantId;
    }

    protected function resolveProductId(ChannelListing $listing): string
    {
        $productId = trim((string) (
            ($listing->channelProduct?->external_parent_id)
            ?: data_get($listing->channelProduct?->raw_payload, 'id')
            ?: data_get($listing->channelProduct?->raw_payload, 'product.id')
        ));

        if ($productId === '') {
            throw new \RuntimeException('Shopify fiyat push için parent product ID zorunludur.');
        }

        return $productId;
    }

    protected function resolveInventoryItemId(ChannelListing $listing): string
    {
        $inventoryItemId = trim((string) (
            data_get($listing->channelProduct?->raw_payload, 'variant.inventoryItem.id')
            ?: data_get($listing->channelProduct?->raw_payload, 'inventoryItem.id')
        ));

        if ($inventoryItemId === '') {
            throw new \RuntimeException('Shopify stok push için inventory item ID zorunludur. Önce ürün sync çalıştırılmalıdır.');
        }

        return $inventoryItemId;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function resolveLocationId(MarketplaceStore $store, array $context = []): string
    {
        $credentials = $store->connection?->credentials_encrypted ?? [];
        $locationId = trim((string) (
            $context['location_id'] ?? null
            ?: $credentials['location_id'] ?? null
        ));

        if ($locationId !== '') {
            return $locationId;
        }

        $payload = $this->graphQl($store, <<<'GRAPHQL'
            query ZolmPrimaryLocation {
              location {
                id
                name
              }
            }
        GRAPHQL);

        $resolved = trim((string) data_get($payload, 'data.location.id', ''));

        if ($resolved === '') {
            throw new \RuntimeException('Shopify stok push için location ID çözümlenemedi. credentials içinde location_id tanımlayın.');
        }

        return $resolved;
    }
}
