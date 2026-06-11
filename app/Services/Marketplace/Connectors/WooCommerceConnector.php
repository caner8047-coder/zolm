<?php

namespace App\Services\Marketplace\Connectors;

use App\Models\ChannelListing;
use App\Models\IntegrationPushRun;
use App\Models\MarketplaceQuestion;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\Connectors\Concerns\NormalizesCustomerQuestions;
use App\Services\Marketplace\Contracts\AnswersCustomerQuestions;
use App\Services\Marketplace\Contracts\PullsCustomerQuestions;
use App\Services\Marketplace\Contracts\PullsClaims;
use App\Services\Marketplace\Contracts\PullsOrders;
use App\Services\Marketplace\Contracts\PullsProducts;
use App\Services\Marketplace\Contracts\PushesPrice;
use App\Services\Marketplace\Contracts\PushesStock;
use App\Services\Marketplace\Contracts\TestsConnection;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WooCommerceConnector extends AbstractMarketplaceConnector implements PullsOrders, PullsProducts, PullsCustomerQuestions, PullsClaims, AnswersCustomerQuestions, PushesPrice, PushesStock, TestsConnection
{
    use NormalizesCustomerQuestions;

    public function providerKey(): string
    {
        return 'woocommerce';
    }

    public function displayName(): string
    {
        return 'WooCommerce';
    }

    public function defaultApiBaseUrl(): ?string
    {
        return config('marketplace.woocommerce.base_url');
    }

    /**
     * @return array<string, bool>
     */
    public function capabilities(): array
    {
        return [
            'orders' => true,
            'products' => true,
            'finance' => false,
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
            'questions' => true,
            'question_answer' => true,
            'claims' => true,
            'claim_approve' => false,
            'claim_reject' => false,
        ];
    }

    public function pullOrders(MarketplaceStore $store, array $options = []): array
    {
        $startDate = CarbonImmutable::parse($options['start_date'] ?? now()->subDay())->setTimezone('Europe/Istanbul');
        $rows = $this->fetchPaginated(
            $store,
            'orders',
            [
                'orderby' => 'date',
                'order' => 'desc',
                'after' => $startDate->toIso8601String(),
                '_fields' => $this->resourceFields('order_fields'),
            ],
            min(100, max(1, (int) ($options['page_size'] ?? config('marketplace.woocommerce.order_page_size', 25))))
        );

        return [
            'items' => collect($rows)
                ->map(fn (array $payload) => $this->normalizeOrder($payload))
                ->values()
                ->all(),
            'meta' => [
                'items_received' => count($rows),
                'cursor_after' => now()->toIso8601String(),
            ],
        ];
    }

    public function pullProducts(MarketplaceStore $store, array $options = []): array
    {
        $startDate = CarbonImmutable::parse($options['start_date'] ?? now()->subDays(7))->setTimezone('UTC');
        $fullCatalogRefresh = (bool) ($options['full_catalog_refresh'] ?? false);
        $query = [
            'orderby' => 'modified',
            'order' => 'desc',
            'dates_are_gmt' => true,
            '_fields' => $this->resourceFields('product_fields'),
        ];

        if (!$fullCatalogRefresh) {
            $query['modified_after'] = $startDate->toIso8601String();
        }

        $rows = $this->fetchPaginated(
            $store,
            'products',
            $query,
            min(100, max(1, (int) ($options['page_size'] ?? config('marketplace.woocommerce.product_page_size', 25))))
        );
        $variationRows = $this->fetchVariationsForProducts($store, $rows, $query, $options);

        return [
            'items' => collect($rows)
                ->map(fn (array $payload) => $this->normalizeProduct($payload))
                ->merge(collect($variationRows)->map(
                    fn (array $row) => $this->normalizeVariation($row['variation'], $row['parent'])
                ))
                ->values()
                ->all(),
            'meta' => [
                'items_received' => count($rows) + count($variationRows),
                'parent_items_received' => count($rows),
                'variation_items_received' => count($variationRows),
                'cursor_after' => now()->toIso8601String(),
                'date_filter' => $fullCatalogRefresh ? 'full_catalog_refresh' : 'modified_after',
            ],
        ];
    }

    public function pullCustomerQuestions(MarketplaceStore $store, array $options = []): array
    {
        $startDate = CarbonImmutable::parse($options['start_date'] ?? now()->subDays(7))->setTimezone('UTC');
        $endDate = CarbonImmutable::parse($options['end_date'] ?? now())->setTimezone('UTC');
        $rows = $this->fetchPaginated(
            $store,
            'products/reviews',
            [
                'orderby' => 'date',
                'order' => 'desc',
                'after' => $startDate->toIso8601String(),
                'before' => $endDate->toIso8601String(),
            ],
            min(100, max(1, (int) ($options['page_size'] ?? config('marketplace.woocommerce.question_page_size', 50))))
        );

        return [
            'items' => collect($rows)
                ->map(fn (array $payload) => $this->normalizeWooCommerceQuestion($payload))
                ->values()
                ->all(),
            'meta' => [
                'items_received' => count($rows),
                'cursor_after' => $endDate->toIso8601String(),
                'source' => 'product_reviews',
            ],
        ];
    }

    public function pullClaims(MarketplaceStore $store, array $options = []): array
    {
        $startDate = CarbonImmutable::parse($options['start_date'] ?? now()->subDays(7))->setTimezone('UTC');
        $endDate = CarbonImmutable::parse($options['end_date'] ?? now())->setTimezone('UTC');
        $pageSize = min(100, max(1, (int) ($options['page_size'] ?? config('marketplace.woocommerce.claim_page_size', 25))));
        $orders = $this->fetchPaginated(
            $store,
            'orders',
            [
                'status' => 'refunded',
                'orderby' => 'modified',
                'order' => 'desc',
                'after' => $startDate->toIso8601String(),
                'before' => $endDate->toIso8601String(),
                '_fields' => $this->resourceFields('order_fields'),
            ],
            $pageSize
        );

        $items = [];

        foreach ($orders as $orderPayload) {
            $orderId = (string) data_get($orderPayload, 'id');

            if ($orderId === '') {
                continue;
            }

            $refunds = $this->fetchPaginated($store, 'orders/'.$orderId.'/refunds', [], $pageSize);

            if ($refunds === []) {
                $items[] = $this->normalizeRefundClaim($orderPayload, []);
                continue;
            }

            foreach ($refunds as $refundPayload) {
                $items[] = $this->normalizeRefundClaim($orderPayload, $refundPayload);
            }
        }

        return [
            'items' => $items,
            'meta' => [
                'items_received' => count($items),
                'cursor_after' => $endDate->toIso8601String(),
                'source' => 'order_refunds',
            ],
        ];
    }

    public function answerCustomerQuestion(MarketplaceQuestion $question, string $answer): array
    {
        $question->loadMissing('store.connection');

        $productId = (string) (
            data_get($question->raw_payload, 'product_id')
            ?: data_get($question->raw_payload, 'post')
            ?: data_get($question->raw_payload, 'external_product_id')
        );

        if ($productId === '') {
            throw new \RuntimeException('WooCommerce yorum cevabı için ürün ID bulunamadı.');
        }

        $response = $this->wordpressRequest($question->store)
            ->post('comments', [
                'post' => (int) $productId,
                'parent' => (int) $question->external_question_id,
                'content' => $answer,
            ])
            ->throw();

        $payload = $response->json();
        $payload = is_array($payload) ? $payload : [];

        return [
            'external_answer_id' => (string) (
                data_get($payload, 'id')
                ?: $question->external_question_id
            ),
            'response_status' => $response->status(),
            'response' => $payload,
        ];
    }

    public function pushPrice(ChannelListing $listing, float $price, array $context = []): array
    {
        $listing->loadMissing('store');

        $productId = $this->productId($listing);
        $endpoint = $this->productEndpoint($listing);

        $response = $this->request($listing->store)
            ->put($endpoint, array_filter([
                'regular_price' => $this->decimalString($price),
                'sale_price' => isset($context['sale_price']) ? $this->decimalString((float) $context['sale_price']) : null,
            ], fn ($value) => $value !== null && $value !== ''))
            ->throw()
            ->json();

        return [
            'status' => 'completed',
            'listing_id' => $listing->id,
            'provider' => $this->providerKey(),
            'product_id' => $productId,
            'price' => round($price, 2),
            'response' => is_array($response) ? $response : [],
            'external_action_id' => (string) (data_get($response, 'id') ?: $productId),
        ];
    }

    public function pushStock(ChannelListing $listing, int $quantity, array $context = []): array
    {
        $listing->loadMissing('store');

        $productId = $this->productId($listing);
        $endpoint = $this->productEndpoint($listing);

        $response = $this->request($listing->store)
            ->put($endpoint, [
                'manage_stock' => true,
                'stock_quantity' => $quantity,
                'stock_status' => $quantity > 0 ? 'instock' : 'outofstock',
            ])
            ->throw()
            ->json();

        return [
            'status' => 'completed',
            'listing_id' => $listing->id,
            'provider' => $this->providerKey(),
            'product_id' => $productId,
            'quantity' => $quantity,
            'response' => is_array($response) ? $response : [],
            'external_action_id' => (string) (data_get($response, 'id') ?: $productId),
        ];
    }

    public function testConnection(MarketplaceStore $store): array
    {
        $response = $this->request($store)
            ->get('products', [
                'per_page' => 1,
                'page' => 1,
                '_fields' => 'id',
            ])
            ->throw()
            ->json();

        return [
            'ok' => true,
            'message' => 'WooCommerce bağlantısı doğrulandı.',
            'meta' => [
                'items_received' => is_array($response) ? count($response) : 0,
            ],
        ];
    }

    /**
     * @param  iterable<IntegrationPushRun>  $pushRuns
     * @return array<string, mixed>
     */
    public function pushPriceBatch(MarketplaceStore $store, iterable $pushRuns): array
    {
        $batchRequestId = 'woo-price-'.Str::uuid()->toString();
        $runs = collect($pushRuns)->values();

        $updates = $runs
            ->map(function (IntegrationPushRun $pushRun): array {
                $pushRun->loadMissing('listing.channelProduct');
                $productId = $this->productId($pushRun->listing);

                return array_filter([
                    'id' => (int) $productId,
                    'regular_price' => $this->decimalString((float) $pushRun->target_price),
                    'sale_price' => filled(data_get($pushRun->request_context_json, 'sale_price'))
                        ? $this->decimalString((float) data_get($pushRun->request_context_json, 'sale_price'))
                        : null,
                ], fn ($value) => $value !== null && $value !== '');
            })
            ->values()
            ->all();

        $response = $this->request($store)
            ->post('products/batch', ['update' => $updates])
            ->throw()
            ->json();

        $updatedRows = collect(data_get($response, 'update', []))
            ->filter(fn ($row) => is_array($row))
            ->keyBy(fn (array $row) => (string) ($row['id'] ?? ''));

        return [
            'status' => 'completed',
            'batch_request_id' => $batchRequestId,
            'response' => is_array($response) ? $response : [],
            'items' => $runs->map(function (IntegrationPushRun $pushRun) use ($updatedRows): array {
                $productId = $this->productId($pushRun->listing);

                return [
                    'push_run_id' => $pushRun->id,
                    'product_id' => $productId,
                    'response' => $updatedRows->get((string) $productId, []),
                ];
            })->all(),
        ];
    }

    /**
     * @param  iterable<IntegrationPushRun>  $pushRuns
     * @return array<string, mixed>
     */
    public function pushStockBatch(MarketplaceStore $store, iterable $pushRuns): array
    {
        $batchRequestId = 'woo-stock-'.Str::uuid()->toString();
        $runs = collect($pushRuns)->values();

        $updates = $runs
            ->map(function (IntegrationPushRun $pushRun): array {
                $pushRun->loadMissing('listing.channelProduct');
                $productId = $this->productId($pushRun->listing);
                $quantity = (int) $pushRun->target_quantity;

                return [
                    'id' => (int) $productId,
                    'manage_stock' => true,
                    'stock_quantity' => $quantity,
                    'stock_status' => $quantity > 0 ? 'instock' : 'outofstock',
                ];
            })
            ->values()
            ->all();

        $response = $this->request($store)
            ->post('products/batch', ['update' => $updates])
            ->throw()
            ->json();

        $updatedRows = collect(data_get($response, 'update', []))
            ->filter(fn ($row) => is_array($row))
            ->keyBy(fn (array $row) => (string) ($row['id'] ?? ''));

        return [
            'status' => 'completed',
            'batch_request_id' => $batchRequestId,
            'response' => is_array($response) ? $response : [],
            'items' => $runs->map(function (IntegrationPushRun $pushRun) use ($updatedRows): array {
                $productId = $this->productId($pushRun->listing);

                return [
                    'push_run_id' => $pushRun->id,
                    'product_id' => $productId,
                    'response' => $updatedRows->get((string) $productId, []),
                ];
            })->all(),
        ];
    }

    public function verifyWebhookSignature(Request $request, ?\App\Models\IntegrationConnection $connection): bool
    {
        if (!$connection || blank($connection->webhook_secret)) {
            return false;
        }

        $providedSignature = (string) $request->header('X-WC-Webhook-Signature');

        if ($providedSignature === '') {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', $request->getContent(), $connection->webhook_secret, true));

        return hash_equals($expected, $providedSignature);
    }

    public function extractWebhookMetadata(Request $request): array
    {
        $payload = $request->json()->all();

        if ($payload === []) {
            $payload = $request->all();
        }

        return [
            'event_type' => $request->header('X-WC-Webhook-Topic')
                ?: data_get($payload, 'topic')
                ?: data_get($payload, 'status'),
            'external_event_id' => (string) (
                $request->header('X-WC-Webhook-Delivery-ID')
                ?: data_get($payload, 'id')
                ?: data_get($payload, 'order_key')
                ?: ''
            ),
            'payload' => is_array($payload) ? $payload : [],
        ];
    }

    protected function request(MarketplaceStore $store): PendingRequest
    {
        [$consumerKey, $consumerSecret] = $this->resolveAuth($store);

        return Http::baseUrl($this->baseUrlFor($store))
            ->timeout((int) config('marketplace.woocommerce.request_timeout', 45))
            ->acceptJson()
            ->asJson()
            ->withBasicAuth($consumerKey, $consumerSecret);
    }

    protected function wordpressRequest(MarketplaceStore $store): PendingRequest
    {
        [$username, $applicationPassword] = $this->resolveWordPressAuth($store);

        return Http::baseUrl($this->wordpressBaseUrlFor($store))
            ->timeout((int) config('marketplace.woocommerce.request_timeout', 45))
            ->acceptJson()
            ->asJson()
            ->withBasicAuth($username, $applicationPassword);
    }

    protected function baseUrlFor(MarketplaceStore $store): string
    {
        $credentials = $store->connection?->credentials_encrypted ?? [];
        $sellerId = trim((string) ($store->seller_id ?? ''));
        $legacyStoreUrl = filter_var($sellerId, FILTER_VALIDATE_URL) ? $sellerId : '';

        $candidates = [
            trim((string) ($store->connection?->api_base_url ?? '')),
            trim((string) ($credentials['store_url'] ?? '')),
            trim((string) ($store->store_url ?? '')),
            $legacyStoreUrl,
            trim((string) config('marketplace.woocommerce.base_url')),
        ];

        $base = collect($candidates)
            ->filter(fn ($value) => $value !== '')
            ->sortBy(fn (string $value) => $this->looksLikePlaceholderUrl($value) ? 1 : 0)
            ->first() ?? '';

        if ($base === '') {
            throw new \RuntimeException('WooCommerce bağlantısı için mağaza URL veya API base URL zorunludur.');
        }

        $base = rtrim($base, '/');
        $version = trim((string) config('marketplace.woocommerce.version', 'wc/v3'), '/');

        if (Str::contains($base, '/wp-json/')) {
            return $base.'/';
        }

        return $base.'/wp-json/'.$version.'/';
    }

    protected function wordpressBaseUrlFor(MarketplaceStore $store): string
    {
        $base = $this->baseUrlFor($store);

        if (Str::contains($base, '/wp-json/')) {
            $base = Str::before($base, '/wp-json/');
        }

        return rtrim($base, '/').'/wp-json/wp/v2/';
    }

    protected function looksLikePlaceholderUrl(string $url): bool
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $host = Str::lower($host);

        if ($host === '') {
            return false;
        }

        return in_array($host, ['example.com', 'www.example.com', 'example.org', 'localhost'], true);
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function resolveAuth(MarketplaceStore $store): array
    {
        $credentials = $store->connection?->credentials_encrypted ?? [];
        $consumerKey = trim((string) ($credentials['api_key'] ?? ''));
        $consumerSecret = trim((string) ($credentials['api_secret'] ?? ''));

        if ($consumerKey === '' || $consumerSecret === '') {
            throw new \RuntimeException('WooCommerce bağlantısı için consumer key ve consumer secret zorunludur.');
        }

        return [$consumerKey, $consumerSecret];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function resolveWordPressAuth(MarketplaceStore $store): array
    {
        $credentials = $store->connection?->credentials_encrypted ?? [];
        $username = trim((string) (
            $credentials['wp_username']
            ?? $credentials['wordpress_username']
            ?? $credentials['extra_user']
            ?? ''
        ));
        $applicationPassword = trim((string) (
            $credentials['wp_application_password']
            ?? $credentials['wordpress_application_password']
            ?? $credentials['extra_password']
            ?? ''
        ));

        if ($username === '' || $applicationPassword === '') {
            throw new \RuntimeException('WooCommerce soru cevabı için WordPress kullanıcı adı ve uygulama şifresi zorunludur.');
        }

        return [$username, $applicationPassword];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<int, array<string, mixed>>
     */
    protected function fetchPaginated(MarketplaceStore $store, string $resource, array $query, ?int $pageSize = null): array
    {
        $pageSize = min(100, max(1, (int) ($pageSize ?? config('marketplace.woocommerce.order_page_size', 25))));
        $maxPages = max(1, (int) config('marketplace.woocommerce.max_pages_per_sync', 10));
        $items = [];
        $page = 1;

        do {
            $response = $this->request($store)
                ->get($resource, array_filter($query + [
                    'per_page' => $pageSize,
                    'page' => $page,
                ], fn ($value) => $value !== null && $value !== ''))
                ->throw();

            $payload = $response->json();
            $batch = collect(is_array($payload) ? $payload : [])
                ->filter(fn ($row) => is_array($row))
                ->values();

            foreach ($batch as $row) {
                $items[] = $row;
            }

            $totalPages = (int) $response->header('X-WP-TotalPages', 1);
            $page++;
            $hasMore = $page <= min(max(1, $totalPages), $maxPages) && $batch->count() === $pageSize;
        } while ($hasMore);

        return $items;
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @param  array<string, mixed>  $baseQuery
     * @param  array<string, mixed>  $options
     * @return array<int, array{parent: array<string, mixed>, variation: array<string, mixed>}>
     */
    protected function fetchVariationsForProducts(MarketplaceStore $store, array $products, array $baseQuery, array $options): array
    {
        $pageSize = min(100, max(1, (int) ($options['page_size'] ?? config('marketplace.woocommerce.product_page_size', 25))));
        $query = array_replace($baseQuery, [
            '_fields' => $this->resourceFields('variation_fields'),
        ]);

        $rows = [];

        foreach ($products as $product) {
            if ((string) data_get($product, 'type') !== 'variable') {
                continue;
            }

            $parentId = trim((string) data_get($product, 'id'));

            if ($parentId === '') {
                continue;
            }

            foreach ($this->fetchPaginated($store, 'products/'.$parentId.'/variations', $query, $pageSize) as $variation) {
                $rows[] = [
                    'parent' => $product,
                    'variation' => $variation,
                ];
            }
        }

        return $rows;
    }

    protected function resourceFields(string $configKey): ?string
    {
        $fields = array_values(array_filter((array) config('marketplace.woocommerce.'.$configKey, [])));

        return $fields === [] ? null : implode(',', $fields);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizeOrder(array $payload): array
    {
        $orderId = (string) data_get($payload, 'id');
        $lineItems = collect(data_get($payload, 'line_items', []))
            ->filter(fn ($row) => is_array($row))
            ->values();
        $billing = data_get($payload, 'billing', []);
        $shipping = data_get($payload, 'shipping', []);

        return [
            'order' => [
                'external_order_id' => $orderId,
                'order_number' => (string) (data_get($payload, 'number') ?: $orderId),
                'order_status' => (string) (data_get($payload, 'status') ?: 'pending'),
                'commercial_type' => filled(data_get($billing, 'company')) ? 'commercial' : 'individual',
                'customer_name' => trim((string) collect([
                    data_get($shipping, 'first_name') ?: data_get($billing, 'first_name'),
                    data_get($shipping, 'last_name') ?: data_get($billing, 'last_name'),
                ])->filter()->implode(' ')),
                'customer_email' => data_get($billing, 'email'),
                'customer_phone' => data_get($billing, 'phone'),
                'billing_name' => data_get($billing, 'company') ?: trim((string) collect([
                    data_get($billing, 'first_name'),
                    data_get($billing, 'last_name'),
                ])->filter()->implode(' ')),
                'billing_tax_number' => (string) (
                    data_get($billing, 'vat')
                    ?: data_get($billing, 'tax_number')
                    ?: data_get($billing, 'meta_data.0.value')
                    ?: ''
                ),
                'shipment_country' => data_get($shipping, 'country') ?: data_get($billing, 'country') ?: 'TR',
                'shipment_city' => data_get($shipping, 'city') ?: data_get($billing, 'city'),
                'shipment_district' => data_get($shipping, 'state') ?: data_get($billing, 'state'),
                'ordered_at' => $this->normalizeDate(data_get($payload, 'date_created_gmt') ?: data_get($payload, 'date_created')),
                'approved_at' => null,
                'delivered_at' => null,
                'cancelled_at' => (string) data_get($payload, 'status') === 'cancelled'
                    ? $this->normalizeDate(data_get($payload, 'date_modified_gmt') ?: data_get($payload, 'date_modified'))
                    : null,
                'returned_at' => (string) data_get($payload, 'status') === 'refunded'
                    ? $this->normalizeDate(data_get($payload, 'date_modified_gmt') ?: data_get($payload, 'date_modified'))
                    : null,
                'raw_payload' => $payload,
            ],
            'package' => [
                'external_package_id' => $orderId,
                'package_number' => $orderId,
                'package_status' => (string) (data_get($payload, 'status') ?: 'pending'),
                'cargo_company' => data_get($payload, 'shipping_lines.0.method_title'),
                'cargo_tracking_number' => null,
                'cargo_barcode' => null,
                'cargo_desi' => null,
                'shipment_provider' => data_get($payload, 'shipping_lines.0.method_id'),
                'shipped_at' => null,
                'delivered_at' => null,
                'raw_payload' => [
                    'shipping_lines' => data_get($payload, 'shipping_lines', []),
                ],
            ],
            'items' => $lineItems
                ->map(fn (array $row, int $index) => $this->normalizeOrderLine($row, $orderId, $index))
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizeOrderLine(array $payload, string $orderId, int $index): array
    {
        $quantity = max(1, (int) (data_get($payload, 'quantity') ?: 1));
        $total = $this->toDecimal(data_get($payload, 'total'));
        $subtotal = $this->toDecimal(data_get($payload, 'subtotal')) ?: $total;
        $grossAmount = $subtotal !== null ? round($subtotal, 2) : null;
        $discountAmount = $grossAmount !== null && $total !== null ? round(max(0, $grossAmount - $total), 2) : null;
        $stockCode = trim((string) data_get($payload, 'sku'));

        return [
            'external_line_id' => (string) (data_get($payload, 'id') ?: sha1($orderId.'|'.$index)),
            'stock_code' => $stockCode !== '' ? $stockCode : null,
            'barcode' => null,
            'product_name' => data_get($payload, 'name'),
            'quantity' => $quantity,
            'unit_price' => $grossAmount !== null ? round($grossAmount / $quantity, 2) : null,
            'gross_amount' => $grossAmount,
            'discount_amount' => $discountAmount,
            'marketplace_discount_amount' => null,
            'billable_amount' => $total,
            'commission_rate' => null,
            'vat_rate' => null,
            'line_status' => 'active',
            'raw_payload' => $payload,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizeProduct(array $payload): array
    {
        $productId = (string) data_get($payload, 'id');
        $stockCode = trim((string) data_get($payload, 'sku'));
        $manageStock = (bool) data_get($payload, 'manage_stock');

        return [
            'product' => [
                'external_product_id' => $productId,
                'external_parent_id' => (string) (data_get($payload, 'parent_id') ?: ''),
                'stock_code' => $stockCode !== '' ? $stockCode : null,
                'barcode' => null,
                'title' => data_get($payload, 'name'),
                'brand' => $this->extractBrand($payload),
                'category_name' => collect(data_get($payload, 'categories', []))
                    ->filter(fn ($row) => is_array($row))
                    ->map(fn (array $row) => (string) ($row['name'] ?? ''))
                    ->filter()
                    ->implode(' / '),
                'vat_rate' => null,
                'raw_payload' => $payload,
            ],
            'listing' => array_merge([
                'listing_id' => $productId,
                'listing_status' => data_get($payload, 'status') ?: 'draft',
                'sale_price' => $this->toDecimal(data_get($payload, 'price') ?: data_get($payload, 'regular_price')),
                'list_price' => $this->toDecimal(data_get($payload, 'regular_price')),
                'commission_rate' => 0,
                'commission_source' => 'marketplace_default',
                'currency' => data_get($payload, 'currency') ?: 'TRY',
                'stock_quantity' => $manageStock ? (int) (data_get($payload, 'stock_quantity') ?: 0) : 0,
                'published_at' => $this->normalizeDate(data_get($payload, 'date_created_gmt') ?: data_get($payload, 'date_created')),
            ], $this->catalogDeliveryTermData($payload)),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $parentPayload
     * @return array<string, mixed>
     */
    protected function normalizeVariation(array $payload, array $parentPayload): array
    {
        $variationId = (string) data_get($payload, 'id');
        $parentId = (string) data_get($parentPayload, 'id');
        $stockCode = trim((string) data_get($payload, 'sku'));
        $manageStock = (bool) (data_get($payload, 'manage_stock') ?? data_get($parentPayload, 'manage_stock'));
        $variantLabel = collect(data_get($payload, 'attributes', []))
            ->filter(fn ($row) => is_array($row))
            ->map(fn (array $row) => (string) (($row['option'] ?? '') ?: ($row['name'] ?? '')))
            ->filter()
            ->implode(' / ');
        $parentTitle = trim((string) data_get($parentPayload, 'name'));

        return [
            'product' => [
                'external_product_id' => $variationId,
                'external_parent_id' => $parentId,
                'stock_code' => $stockCode !== '' ? $stockCode : null,
                'barcode' => null,
                'title' => trim($parentTitle . ($variantLabel !== '' ? ' - ' . $variantLabel : '')),
                'brand' => $this->extractBrand($parentPayload),
                'category_name' => collect(data_get($parentPayload, 'categories', []))
                    ->filter(fn ($row) => is_array($row))
                    ->map(fn (array $row) => (string) ($row['name'] ?? ''))
                    ->filter()
                    ->implode(' / '),
                'vat_rate' => null,
                'raw_payload' => array_replace($payload, [
                    'parent' => $parentPayload,
                    'type' => 'variation',
                ]),
            ],
            'listing' => array_merge([
                'listing_id' => $variationId,
                'listing_status' => data_get($payload, 'status') ?: data_get($parentPayload, 'status') ?: 'draft',
                'sale_price' => $this->toDecimal(data_get($payload, 'price') ?: data_get($parentPayload, 'price')),
                'list_price' => $this->toDecimal(data_get($payload, 'regular_price') ?: data_get($parentPayload, 'regular_price')),
                'commission_rate' => 0,
                'commission_source' => 'marketplace_default',
                'currency' => data_get($parentPayload, 'currency') ?: 'TRY',
                'stock_quantity' => $manageStock ? (int) (data_get($payload, 'stock_quantity') ?? data_get($parentPayload, 'stock_quantity') ?? 0) : 0,
                'published_at' => $this->normalizeDate(
                    data_get($payload, 'date_created_gmt')
                    ?: data_get($payload, 'date_created')
                    ?: data_get($parentPayload, 'date_created_gmt')
                    ?: data_get($parentPayload, 'date_created')
                ),
            ], $this->catalogDeliveryTermData($payload, $parentPayload)),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizeWooCommerceQuestion(array $payload): array
    {
        return $this->normalizeQuestionPayload($payload, [
            'external_question_id' => (string) data_get($payload, 'id'),
            'question_type' => 'review',
            'status' => (string) data_get($payload, 'status') === 'approved' ? 'open' : 'draft',
            'customer_name' => data_get($payload, 'reviewer'),
            'customer_external_id' => data_get($payload, 'reviewer_email'),
            'product_name' => data_get($payload, 'product_name'),
            'external_product_id' => data_get($payload, 'product_id'),
            'question_text' => $this->questionTextValue(data_get($payload, 'review')),
            'asked_at' => $this->questionDate(
                data_get($payload, 'date_created_gmt')
                ?: data_get($payload, 'date_created')
            ),
        ]);
    }

    /**
     * @param  array<string, mixed>  $orderPayload
     * @param  array<string, mixed>  $refundPayload
     * @return array<string, mixed>
     */
    protected function normalizeRefundClaim(array $orderPayload, array $refundPayload): array
    {
        $orderId = (string) data_get($orderPayload, 'id');
        $refundId = (string) (data_get($refundPayload, 'id') ?: 'order-'.$orderId);
        $billing = data_get($orderPayload, 'billing', []);

        return [
            'external_claim_id' => 'woo-'.$orderId.'-'.$refundId,
            'order_number' => (string) (data_get($orderPayload, 'number') ?: $orderId),
            'status' => 'approved',
            'type' => 'return',
            'reason' => data_get($refundPayload, 'reason'),
            'reason_detail' => data_get($refundPayload, 'reason'),
            'customer_name' => trim((string) collect([
                data_get($billing, 'first_name'),
                data_get($billing, 'last_name'),
            ])->filter()->implode(' ')),
            'created_date' => data_get($refundPayload, 'date_created_gmt')
                ?: data_get($refundPayload, 'date_created')
                ?: data_get($orderPayload, 'date_modified_gmt')
                ?: data_get($orderPayload, 'date_modified'),
            'items' => collect(data_get($refundPayload, 'line_items', data_get($orderPayload, 'line_items', [])))
                ->filter(fn ($row) => is_array($row))
                ->values()
                ->all(),
            'raw_payload' => [
                'order' => $orderPayload,
                'refund' => $refundPayload,
            ],
        ];
    }

    protected function productId(ChannelListing $listing): string
    {
        $productId = trim((string) (
            $listing->listing_id
            ?: data_get($listing->raw_payload, 'id')
            ?: data_get($listing->channelProduct?->raw_payload, 'id')
            ?: data_get($listing->channelProduct?->external_product_id)
        ));

        if ($productId === '') {
            throw new \RuntimeException('WooCommerce push icin product ID zorunludur.');
        }

        return $productId;
    }

    protected function productEndpoint(ChannelListing $listing): string
    {
        $listing->loadMissing('channelProduct');

        $productId = $this->productId($listing);
        $parentId = trim((string) $listing->channelProduct?->external_parent_id);

        if ($parentId !== '' && $parentId !== $productId) {
            return 'products/'.$parentId.'/variations/'.$productId;
        }

        return 'products/'.$productId;
    }

    protected function decimalString(float $value): string
    {
        return number_format(round($value, 2), 2, '.', '');
    }

    protected function normalizeDate(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return CarbonImmutable::parse((string) $value)->toIso8601String();
    }

    protected function toDecimal(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function extractBrand(array $payload): ?string
    {
        $brands = collect(data_get($payload, 'brands', []))
            ->filter(fn ($row) => is_array($row))
            ->map(fn (array $row) => (string) ($row['name'] ?? ''))
            ->filter();

        return $brands->first() ?: null;
    }
}
