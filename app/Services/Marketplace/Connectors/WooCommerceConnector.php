<?php

namespace App\Services\Marketplace\Connectors;

use App\Models\ChannelListing;
use App\Models\IntegrationPushRun;
use App\Models\MarketplaceStore;
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

class WooCommerceConnector extends AbstractMarketplaceConnector implements PullsOrders, PullsProducts, PushesPrice, PushesStock, TestsConnection
{
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
        $startDate = CarbonImmutable::parse($options['start_date'] ?? now()->subDays(7))->setTimezone('Europe/Istanbul');
        $rows = $this->fetchPaginated(
            $store,
            'products',
            [
                'orderby' => 'date',
                'order' => 'desc',
                'after' => $startDate->toIso8601String(),
                '_fields' => $this->resourceFields('product_fields'),
            ],
            min(100, max(1, (int) ($options['page_size'] ?? config('marketplace.woocommerce.product_page_size', 25))))
        );

        return [
            'items' => collect($rows)
                ->map(fn (array $payload) => $this->normalizeProduct($payload))
                ->values()
                ->all(),
            'meta' => [
                'items_received' => count($rows),
                'cursor_after' => now()->toIso8601String(),
            ],
        ];
    }

    public function pushPrice(ChannelListing $listing, float $price, array $context = []): array
    {
        $listing->loadMissing('store');

        $productId = $this->productId($listing);

        $response = $this->request($listing->store)
            ->put('products/'.$productId, array_filter([
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

        $response = $this->request($listing->store)
            ->put('products/'.$productId, [
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

    protected function baseUrlFor(MarketplaceStore $store): string
    {
        $base = trim((string) ($store->connection?->api_base_url ?: $store->store_url ?: config('marketplace.woocommerce.base_url')));

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

        return [
            'external_line_id' => (string) (data_get($payload, 'id') ?: sha1($orderId.'|'.$index)),
            'stock_code' => (string) (data_get($payload, 'sku') ?: data_get($payload, 'product_id') ?: ''),
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
        $stockCode = (string) (data_get($payload, 'sku') ?: $productId);
        $manageStock = (bool) data_get($payload, 'manage_stock');

        return [
            'product' => [
                'external_product_id' => $productId,
                'external_parent_id' => (string) (data_get($payload, 'parent_id') ?: ''),
                'stock_code' => $stockCode,
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
            'listing' => [
                'listing_id' => $productId,
                'listing_status' => data_get($payload, 'status') ?: 'draft',
                'sale_price' => $this->toDecimal(data_get($payload, 'price') ?: data_get($payload, 'regular_price')),
                'list_price' => $this->toDecimal(data_get($payload, 'regular_price')),
                'currency' => data_get($payload, 'currency') ?: 'TRY',
                'stock_quantity' => $manageStock ? (int) (data_get($payload, 'stock_quantity') ?: 0) : 0,
                'published_at' => $this->normalizeDate(data_get($payload, 'date_created_gmt') ?: data_get($payload, 'date_created')),
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
