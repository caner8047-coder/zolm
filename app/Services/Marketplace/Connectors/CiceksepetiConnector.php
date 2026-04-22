<?php

namespace App\Services\Marketplace\Connectors;

use App\Models\MarketplaceStore;
use App\Services\Marketplace\Contracts\PullsOrders;
use App\Services\Marketplace\Contracts\PullsProducts;
use App\Services\Marketplace\Contracts\TestsConnection;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CiceksepetiConnector extends AbstractMarketplaceConnector implements PullsOrders, PullsProducts, TestsConnection
{
    public function providerKey(): string
    {
        return 'ciceksepeti';
    }

    public function displayName(): string
    {
        return 'Çiçeksepeti';
    }

    public function defaultApiBaseUrl(): ?string
    {
        return config('marketplace.ciceksepeti.base_url');
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
            'webhooks' => false,
            'price_push' => false,
            'stock_push' => false,
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

    public function testConnection(MarketplaceStore $store): array
    {
        $endDate = CarbonImmutable::now('Europe/Istanbul');
        $startDate = $endDate->subDay();

        $response = $this->request($store)
            ->post('Order/GetOrders', [
                'startDate' => $this->formatOrderDate($startDate),
                'endDate' => $this->formatOrderDate($endDate),
                'pageSize' => 1,
                'page' => 0,
                'isOrderStatusActive' => true,
            ])
            ->throw()
            ->json();

        return [
            'ok' => true,
            'message' => 'Çiçeksepeti bağlantısı doğrulandı.',
            'meta' => [
                'provider' => $this->providerKey(),
                'base_url' => $this->baseUrl($store),
                'total_count' => (int) data_get($response, 'orderListCount', count(Arr::wrap(data_get($response, 'supplierOrderListWithBranch', [])))),
                'items_returned' => count(Arr::wrap(data_get($response, 'supplierOrderListWithBranch', []))),
                'user_agent' => $this->userAgent($store),
            ],
        ];
    }

    public function pullOrders(MarketplaceStore $store, array $options = []): array
    {
        $pageSize = min(100, max(1, (int) ($options['page_size'] ?? config('marketplace.ciceksepeti.order_page_size', 100))));
        $maxPages = max(1, (int) config('marketplace.ciceksepeti.max_order_pages_per_sync', 50));
        $startDate = CarbonImmutable::parse($options['start_date'] ?? now()->subDay())->setTimezone('Europe/Istanbul');
        $endDate = CarbonImmutable::parse($options['end_date'] ?? now())->setTimezone('Europe/Istanbul');
        $groupedRows = [];
        $pagesProcessed = 0;

        foreach ($this->orderWindows($startDate, $endDate) as [$windowStart, $windowEnd]) {
            $page = 0;

            do {
                $response = $this->request($store)
                    ->post('Order/GetOrders', array_filter([
                        'startDate' => filled($options['order_number'] ?? null) ? null : $this->formatOrderDate($windowStart),
                        'endDate' => filled($options['order_number'] ?? null) ? null : $this->formatOrderDate($windowEnd),
                        'pageSize' => $pageSize,
                        'page' => $page,
                        'orderNo' => filled($options['order_number'] ?? null) ? (int) $options['order_number'] : null,
                        'isOrderStatusActive' => true,
                    ], fn ($value) => $value !== null && $value !== ''))
                    ->throw()
                    ->json();

                $rows = collect(Arr::wrap(data_get($response, 'supplierOrderListWithBranch', [])))
                    ->filter(fn ($row) => is_array($row))
                    ->values();

                foreach ($rows as $row) {
                    $orderId = (string) data_get($row, 'orderId');
                    $packageId = (string) (
                        data_get($row, 'cargoNumber')
                        ?: data_get($row, 'partialNumber')
                        ?: data_get($row, 'orderId')
                    );
                    $key = $orderId.'|'.$packageId;

                    if (!isset($groupedRows[$key])) {
                        $groupedRows[$key] = [];
                    }

                    $groupedRows[$key][] = $row;
                }

                $page++;
                $pagesProcessed++;
            } while (
                $rows->count() === $pageSize
                && $pagesProcessed < $maxPages
            );

            if ($pagesProcessed >= $maxPages) {
                break;
            }
        }

        $items = collect($groupedRows)
            ->map(fn (array $rows) => $this->normalizeOrderPackage($rows))
            ->values()
            ->all();

        return [
            'items' => $items,
            'meta' => [
                'items_received' => count($items),
                'pages_processed' => $pagesProcessed,
                'cursor_after' => $endDate->toIso8601String(),
                'more_pages_available' => $pagesProcessed >= $maxPages,
            ],
        ];
    }

    public function pullProducts(MarketplaceStore $store, array $options = []): array
    {
        $pageSize = min(60, max(1, (int) ($options['page_size'] ?? config('marketplace.ciceksepeti.product_page_size', 60))));
        $maxPages = max(1, (int) config('marketplace.ciceksepeti.max_product_pages_per_sync', 50));
        $page = 1;
        $pagesProcessed = 0;
        $totalCount = 0;
        $items = [];

        do {
            $response = $this->getProductsPage($store, array_filter([
                    'ProductStatus' => $this->productStatusFilter($options['status'] ?? null),
                    'PageSize' => $pageSize,
                    'Page' => $page,
                    'StockCode' => $options['stock_code'] ?? null,
                    'variantName' => $options['variant_name'] ?? null,
                ], fn ($value) => $value !== null && $value !== ''));

            $products = collect(Arr::wrap(data_get($response, 'products', [])))
                ->filter(fn ($row) => is_array($row))
                ->values();

            foreach ($products as $productPayload) {
                $items[] = $this->normalizeProduct($productPayload);
            }

            $totalCount = max($totalCount, (int) data_get($response, 'totalCount', $products->count()));
            $page++;
            $pagesProcessed++;
        } while (
            $products->isNotEmpty()
            && count($items) < $totalCount
            && $pagesProcessed < $maxPages
        );

        return [
            'items' => $items,
            'meta' => [
                'items_received' => count($items),
                'pages_processed' => $pagesProcessed,
                'total_count' => $totalCount,
                'supports_incremental_window' => false,
                'cursor_after' => now()->toIso8601String(),
                'more_pages_available' => count($items) < $totalCount,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    protected function getProductsPage(MarketplaceStore $store, array $query): array
    {
        $attempt = 0;
        $maxAttempts = max(1, (int) config('marketplace.ciceksepeti.product_rate_limit_max_attempts', 3));

        while (true) {
            $response = $this->request($store)->get('Products', $query);

            if (!$this->isProductsRateLimited($response)) {
                return $response->throw()->json();
            }

            $attempt++;

            if ($attempt >= $maxAttempts) {
                return $response->throw()->json();
            }

            $this->waitForProductsRateLimitWindow($response);
        }
    }

    protected function request(MarketplaceStore $store): PendingRequest
    {
        return Http::baseUrl($this->baseUrl($store))
            ->acceptJson()
            ->timeout((int) config('marketplace.ciceksepeti.request_timeout', 45))
            ->withHeaders([
                'x-api-key' => $this->apiKey($store),
                'User-Agent' => $this->userAgent($store),
            ]);
    }

    protected function isProductsRateLimited(Response $response): bool
    {
        if ($response->status() !== 400) {
            return false;
        }

        $message = (string) (data_get($response->json(), 'Message') ?: $response->body());

        return Str::contains($message, 'Limit aşımı')
            && Str::contains($message, '5 saniyede 1 kez');
    }

    protected function waitForProductsRateLimitWindow(Response $response): void
    {
        $message = (string) (data_get($response->json(), 'Message') ?: $response->body());
        preg_match('/Kalan Süre:\s*(\d+)\s*saniye/ui', $message, $matches);

        $retryAfterSeconds = isset($matches[1]) ? (int) $matches[1] : 0;
        $graceSeconds = max(0, (int) config('marketplace.ciceksepeti.product_rate_limit_grace_seconds', 1));
        $waitSeconds = $retryAfterSeconds + $graceSeconds;

        if ($waitSeconds > 0) {
            sleep($waitSeconds);
        }
    }

    protected function baseUrl(MarketplaceStore $store): string
    {
        $baseUrl = trim((string) ($store->connection?->api_base_url ?: config('marketplace.ciceksepeti.base_url')));

        if ($baseUrl === '') {
            throw new \RuntimeException('Çiçeksepeti API base URL boş.');
        }

        return rtrim($baseUrl, '/');
    }

    protected function apiKey(MarketplaceStore $store): string
    {
        $apiKey = trim((string) data_get($store->connection?->credentials_encrypted, 'api_key'));

        if ($apiKey === '') {
            throw new \RuntimeException('Çiçeksepeti API key zorunludur.');
        }

        return $apiKey;
    }

    protected function userAgent(MarketplaceStore $store): string
    {
        $sellerId = trim((string) ($store->seller_id ?: data_get($store->connection?->credentials_encrypted, 'seller_id')));

        if ($sellerId === '') {
            throw new \RuntimeException('Çiçeksepeti user-agent için satıcı ID zorunludur.');
        }

        $integratorName = trim((string) (
            data_get($store->connection?->credentials_encrypted, 'extra_user')
            ?: data_get($store->connection?->credentials_encrypted, 'integrator_name')
        ));

        return $integratorName !== ''
            ? $sellerId.'-'.$integratorName
            : $sellerId;
    }

    protected function productStatusFilter(?string $status): ?int
    {
        $normalized = Str::lower(trim((string) $status));

        return match (true) {
            $normalized === 'active',
            str_contains($normalized, 'yayin'),
            str_contains($normalized, 'yayında') => 3,
            str_contains($normalized, 'onay') => 2,
            str_contains($normalized, 'red') => 4,
            str_contains($normalized, 'pasif') => 5,
            str_contains($normalized, 'stok') => 7,
            str_contains($normalized, 'kilit') => 8,
            default => null,
        };
    }

    /**
     * @return array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    protected function orderWindows(CarbonImmutable $startDate, CarbonImmutable $endDate): array
    {
        if ($endDate->lessThanOrEqualTo($startDate)) {
            return [[$startDate, $endDate]];
        }

        $windows = [];
        $cursor = $startDate;

        while ($cursor->lessThan($endDate)) {
            $windowEnd = $cursor->addDays(14);

            if ($windowEnd->greaterThan($endDate)) {
                $windowEnd = $endDate;
            }

            $windows[] = [$cursor, $windowEnd];
            $cursor = $windowEnd;
        }

        return $windows !== [] ? $windows : [[$startDate, $endDate]];
    }

    protected function formatOrderDate(CarbonImmutable $value): string
    {
        return $value->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    protected function normalizeOrderPackage(array $rows): array
    {
        $first = Arr::wrap($rows[0] ?? []);
        $lineItems = collect($rows)
            ->filter(fn ($row) => is_array($row))
            ->values();
        $packageStatus = $this->normalizeOrderStatus(
            (int) data_get($first, 'orderItemStatusId'),
            (string) data_get($first, 'orderProductStatus')
        );
        $orderedAt = $this->normalizeOrderDateTime(
            data_get($first, 'orderCreateDate'),
            data_get($first, 'orderCreateTime')
        );
        $modifiedAt = $this->normalizeOrderDateTime(
            data_get($first, 'orderModifyDate'),
            data_get($first, 'orderModifyTime')
        );
        $deliveredAt = $packageStatus === 'delivered'
            ? $this->normalizeFlexibleDate(data_get($first, 'deliveryDate'))
            : null;
        $packageId = (string) (
            data_get($first, 'cargoNumber')
            ?: data_get($first, 'partialNumber')
            ?: data_get($first, 'orderId')
        );

        return [
            'order' => [
                'external_order_id' => (string) data_get($first, 'orderId'),
                'order_number' => (string) data_get($first, 'orderId'),
                'order_status' => $packageStatus,
                'commercial_type' => filled(data_get($first, 'senderCompanyName')) || filled(data_get($first, 'senderTaxNumber'))
                    ? 'commercial'
                    : 'individual',
                'customer_name' => data_get($first, 'receiverName') ?: data_get($first, 'senderName'),
                'customer_email' => data_get($first, 'invoiceEmail'),
                'customer_phone' => data_get($first, 'receiverPhone'),
                'billing_name' => data_get($first, 'senderCompanyName') ?: data_get($first, 'senderName'),
                'billing_tax_number' => (string) (data_get($first, 'senderTaxNumber') ?: ''),
                'shipment_country' => 'TR',
                'shipment_city' => data_get($first, 'receiverCity'),
                'shipment_district' => data_get($first, 'receiverDistrict') ?: data_get($first, 'receiverRegion'),
                'ordered_at' => $orderedAt,
                'approved_at' => in_array($packageStatus, ['approved', 'packing', 'shipped', 'delivered'], true) ? $orderedAt : null,
                'delivered_at' => $deliveredAt,
                'cancelled_at' => $packageStatus === 'cancelled' ? ($modifiedAt ?: $orderedAt) : null,
                'returned_at' => filled(data_get($first, 'cancellationResult')) ? ($modifiedAt ?: $orderedAt) : null,
                'raw_payload' => $first,
            ],
            'package' => [
                'external_package_id' => $packageId,
                'package_number' => $packageId,
                'package_status' => $packageStatus,
                'cargo_company' => data_get($first, 'cargoCompany'),
                'cargo_tracking_number' => data_get($first, 'cargoNumber'),
                'cargo_barcode' => data_get($first, 'partialNumber'),
                'cargo_desi' => null,
                'shipment_provider' => data_get($first, 'cargoCompany') ?: data_get($first, 'deliveryType'),
                'shipped_at' => $packageStatus === 'shipped' ? ($modifiedAt ?: $orderedAt) : null,
                'delivered_at' => $deliveredAt,
                'raw_payload' => $first,
            ],
            'items' => $lineItems
                ->map(function (array $row): array {
                    $quantity = max(1, (int) (data_get($row, 'quantity') ?: 1));
                    $itemPrice = $this->toDecimal(data_get($row, 'itemPrice'));
                    $invoicePrice = $this->toDecimal(data_get($row, 'invoicePrice'));
                    $totalPrice = $this->toDecimal(data_get($row, 'totalPrice'));
                    $discountAmount = $this->toDecimal(data_get($row, 'discount')) ?? 0.0;

                    return [
                        'external_line_id' => (string) (data_get($row, 'orderItemId') ?: data_get($row, 'orderId')),
                        'stock_code' => data_get($row, 'code') ?: data_get($row, 'productCode'),
                        'barcode' => data_get($row, 'barcode'),
                        'product_name' => data_get($row, 'name'),
                        'quantity' => $quantity,
                        'unit_price' => $itemPrice ?? $invoicePrice ?? $totalPrice,
                        'gross_amount' => $itemPrice !== null ? round($itemPrice * $quantity, 2) : ($invoicePrice ?? $totalPrice),
                        'discount_amount' => $discountAmount,
                        'marketplace_discount_amount' => $this->toDecimal(data_get($row, 'csDiscountPart')),
                        'billable_amount' => $invoicePrice ?? $totalPrice,
                        'commission_rate' => null,
                        'vat_rate' => $this->toDecimal(data_get($row, 'tax')),
                        'line_status' => $this->normalizeOrderStatus(
                            (int) data_get($row, 'orderItemStatusId'),
                            (string) data_get($row, 'orderProductStatus')
                        ),
                        'raw_payload' => $row,
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizeProduct(array $payload): array
    {
        $productCode = (string) (data_get($payload, 'productCode') ?: data_get($payload, 'stockCode') ?: '');
        $stockCode = (string) (data_get($payload, 'stockCode') ?: $productCode);

        return [
            'product' => [
                'external_product_id' => $productCode !== '' ? $productCode : $stockCode,
                'external_parent_id' => (string) (data_get($payload, 'mainProductCode') ?: ''),
                'stock_code' => $stockCode !== '' ? $stockCode : null,
                'barcode' => data_get($payload, 'barcode'),
                'title' => data_get($payload, 'productName') ?: data_get($payload, 'variantName'),
                'brand' => data_get($payload, 'brandName'),
                'category_name' => data_get($payload, 'categoryName'),
                'vat_rate' => $this->toDecimal(data_get($payload, 'taxRate')),
                'raw_payload' => $payload,
            ],
            'listing' => [
                'listing_id' => $productCode !== '' ? $productCode : $stockCode,
                'listing_status' => $this->normalizeProductStatus((string) data_get($payload, 'productStatusType'), data_get($payload, 'isActive')),
                'sale_price' => $this->toDecimal(data_get($payload, 'totalPrice')),
                'list_price' => $this->toDecimal(data_get($payload, 'listPrice')),
                'currency' => 'TRY',
                'stock_quantity' => (int) (data_get($payload, 'stockQuantity') ?: 0),
                'published_at' => $this->normalizeFlexibleDate(
                    data_get($payload, 'updatedDate')
                    ?: data_get($payload, 'createdDate')
                ),
            ],
        ];
    }

    protected function normalizeOrderStatus(int $statusId, ?string $statusText = null): string
    {
        if ($statusId > 0) {
            return match ($statusId) {
                7 => 'delivered',
                5 => 'shipped',
                2 => 'packing',
                1, 11 => 'approved',
                default => $this->normalizeStatusText($statusText),
            };
        }

        return $this->normalizeStatusText($statusText);
    }

    protected function normalizeStatusText(?string $statusText): string
    {
        $normalized = Str::of(Str::ascii((string) $statusText))
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/u', ' ')
            ->squish()
            ->toString();

        return match (true) {
            $normalized === '' => 'new',
            str_contains($normalized, 'teslim') => 'delivered',
            str_contains($normalized, 'kargoya verildi') => 'shipped',
            str_contains($normalized, 'hazirlaniyor') => 'packing',
            str_contains($normalized, 'yeni') => 'approved',
            str_contains($normalized, 'iptal') => 'cancelled',
            str_contains($normalized, 'iade') => 'returned',
            default => (string) Str::snake($normalized),
        };
    }

    protected function normalizeProductStatus(?string $statusText, mixed $isActive): string
    {
        $normalized = Str::of(Str::ascii((string) $statusText))
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/u', ' ')
            ->squish()
            ->toString();

        return match (true) {
            $isActive === true,
            str_contains($normalized, 'yayinda') => 'active',
            str_contains($normalized, 'onay') => 'pending',
            str_contains($normalized, 'ret') => 'rejected',
            str_contains($normalized, 'pasif') => 'inactive',
            str_contains($normalized, 'stok') => 'out_of_stock',
            default => 'inactive',
        };
    }

    protected function normalizeOrderDateTime(mixed $date, mixed $time = null): ?string
    {
        $date = trim((string) $date);
        $time = trim((string) $time);

        if ($date === '') {
            return null;
        }

        $candidate = $time !== '' ? $date.' '.$time : $date;

        foreach (['d/m/Y H:i', 'd/m/Y', 'd-m-Y H:i', 'd-m-Y'] as $format) {
            $parsed = CarbonImmutable::createFromFormat($format, $candidate, 'Europe/Istanbul');

            if ($parsed !== false) {
                return $parsed->toIso8601String();
            }
        }

        return $this->normalizeFlexibleDate($candidate);
    }

    protected function normalizeFlexibleDate(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $stringValue = trim((string) $value);

        foreach (['d-m-Y H:i', 'd-m-Y', 'd/m/Y H:i', 'd/m/Y', 'Y-m-d H:i:s', 'Y-m-d H:i'] as $format) {
            $parsed = CarbonImmutable::createFromFormat($format, $stringValue, 'Europe/Istanbul');

            if ($parsed !== false) {
                return $parsed->toIso8601String();
            }
        }

        return CarbonImmutable::parse($stringValue)->toIso8601String();
    }

    protected function toDecimal(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        $normalized = preg_replace('/[^0-9,.-]/', '', (string) $value) ?? '';

        if ($normalized === '') {
            return null;
        }

        if (str_contains($normalized, ',') && !str_contains($normalized, '.')) {
            $normalized = str_replace(',', '.', $normalized);
        } else {
            $normalized = str_replace(',', '', $normalized);
        }

        return is_numeric($normalized) ? round((float) $normalized, 2) : null;
    }
}
