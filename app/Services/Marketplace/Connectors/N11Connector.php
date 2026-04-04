<?php

namespace App\Services\Marketplace\Connectors;

use App\Models\ChannelListing;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\Contracts\PullsOrders;
use App\Services\Marketplace\Contracts\PullsProducts;
use App\Services\Marketplace\Contracts\PushesPrice;
use App\Services\Marketplace\Contracts\PushesStock;
use App\Services\Marketplace\Contracts\TestsConnection;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class N11Connector extends AbstractMarketplaceConnector implements PullsOrders, PullsProducts, PushesPrice, PushesStock, TestsConnection
{
    public function providerKey(): string
    {
        return 'n11';
    }

    public function displayName(): string
    {
        return 'N11';
    }

    public function defaultApiBaseUrl(): ?string
    {
        return config('marketplace.n11.base_url');
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

    public function testConnection(MarketplaceStore $store): array
    {
        $response = $this->request($store)
            ->get('ms/product-query', [
                'page' => 0,
                'size' => 1,
            ])
            ->throw()
            ->json();

        return [
            'ok' => true,
            'message' => 'N11 bağlantısı doğrulandı.',
            'meta' => [
                'provider' => $this->providerKey(),
                'base_url' => $this->baseUrl($store),
                'page' => (int) data_get($response, 'page', 0),
                'total_pages' => (int) data_get($response, 'totalPages', 0),
                'items_returned' => count(Arr::wrap(data_get($response, 'content', []))),
            ],
        ];
    }

    public function pullOrders(MarketplaceStore $store, array $options = []): array
    {
        $page = 0;
        $size = min(100, max(1, (int) ($options['page_size'] ?? config('marketplace.n11.order_page_size', 100))));
        $startDate = CarbonImmutable::parse($options['start_date'] ?? now()->subDay())->setTimezone('Europe/Istanbul');
        $endDate = CarbonImmutable::parse($options['end_date'] ?? now())->setTimezone('Europe/Istanbul');
        $items = [];

        do {
            $response = $this->request($store)
                ->get('rest/delivery/v1/shipmentPackages', array_filter([
                    'startDate' => $startDate->valueOf(),
                    'endDate' => $endDate->valueOf(),
                    'page' => $page,
                    'size' => $size,
                    'status' => $options['status'] ?? null,
                    'orderNumber' => $options['order_number'] ?? null,
                    'orderByField' => 'true',
                    'orderByDirection' => 'DESC',
                ], fn ($value) => $value !== null && $value !== ''))
                ->throw()
                ->json();

            $packages = collect(Arr::wrap(data_get($response, 'content', [])))
                ->filter(fn ($row) => is_array($row))
                ->values();

            foreach ($packages as $packagePayload) {
                $items[] = $this->normalizeOrder($packagePayload);
            }

            $totalPages = (int) data_get($response, 'totalPages', 1);
            $page++;
        } while ($packages->isNotEmpty() && $page < $totalPages);

        return [
            'items' => $items,
            'meta' => [
                'items_received' => count($items),
                'cursor_after' => $endDate->toIso8601String(),
            ],
        ];
    }

    public function pullProducts(MarketplaceStore $store, array $options = []): array
    {
        $page = 0;
        $size = min(100, max(1, (int) ($options['page_size'] ?? config('marketplace.n11.product_page_size', 20))));
        $items = [];

        do {
            $response = $this->request($store)
                ->get('ms/product-query', array_filter([
                    'page' => $page,
                    'size' => $size,
                    'stockCode' => $options['stock_code'] ?? null,
                    'productMainId' => $options['product_main_id'] ?? null,
                    'saleStatus' => $options['status'] ?? null,
                ], fn ($value) => $value !== null && $value !== ''))
                ->throw()
                ->json();

            $products = collect(Arr::wrap(data_get($response, 'content', [])))
                ->filter(fn ($row) => is_array($row))
                ->values();

            foreach ($products as $productPayload) {
                $items[] = $this->normalizeProduct($productPayload);
            }

            $totalPages = (int) data_get($response, 'totalPages', 1);
            $page++;
        } while ($products->isNotEmpty() && $page < $totalPages);

        return [
            'items' => $items,
            'meta' => [
                'items_received' => count($items),
                'supports_incremental_window' => false,
                'cursor_after' => now()->toIso8601String(),
            ],
        ];
    }

    public function pushPrice(ChannelListing $listing, float $price, array $context = []): array
    {
        return $this->pushSkuUpdate($listing, [
            'salePrice' => round($price, 2),
            'listPrice' => $listing->list_price !== null ? round((float) $listing->list_price, 2) : null,
            'currencyType' => $this->currencyType((string) ($listing->currency ?: $listing->store?->currency ?: 'TRY')),
        ], $context + ['operation' => 'price']);
    }

    public function pushStock(ChannelListing $listing, int $quantity, array $context = []): array
    {
        return $this->pushSkuUpdate($listing, [
            'quantity' => max(0, $quantity),
        ], $context + ['operation' => 'stock']);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function pushSkuUpdate(ChannelListing $listing, array $payload, array $context): array
    {
        $listing->loadMissing(['store.connection', 'channelProduct']);

        $body = [
            'payload' => [
                'integrator' => config('app.name', 'ZOLM'),
                'skus' => [[
                    'stockCode' => $this->stockCode($listing),
                    ...array_filter($payload, fn ($value) => $value !== null && $value !== ''),
                ]],
            ],
        ];

        $response = $this->request($listing->store)
            ->asJson()
            ->post('ms/product/tasks/price-stock-update', $body)
            ->throw();

        $decoded = $response->json();

        return [
            'status' => 'queued',
            'listing_id' => $listing->id,
            'provider' => $this->providerKey(),
            'batch_request_id' => (string) (data_get($decoded, 'id') ?: ''),
            'response_status' => $response->status(),
            'response' => $decoded,
            'context' => $context,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizeOrder(array $payload): array
    {
        $status = (string) (data_get($payload, 'shipmentPackageStatus') ?: data_get($payload, 'status') ?: 'Created');
        $billingAddress = data_get($payload, 'billingAddress', []);
        $shippingAddress = data_get($payload, 'shippingAddress', []);
        $lines = collect(Arr::wrap(data_get($payload, 'lines', [])))
            ->filter(fn ($row) => is_array($row))
            ->values();

        return [
            'order' => [
                'external_order_id' => (string) (data_get($payload, 'orderNumber') ?: data_get($payload, 'id') ?: ''),
                'order_number' => (string) (data_get($payload, 'orderNumber') ?: data_get($payload, 'id') ?: ''),
                'order_status' => $status,
                'commercial_type' => (int) data_get($billingAddress, 'invoiceType') === 2 ? 'commercial' : 'individual',
                'customer_name' => data_get($payload, 'customerfullName') ?: data_get($shippingAddress, 'fullName'),
                'customer_email' => data_get($payload, 'customerEmail'),
                'customer_phone' => data_get($shippingAddress, 'gsm') ?: data_get($billingAddress, 'gsm'),
                'billing_name' => data_get($billingAddress, 'fullName') ?: data_get($payload, 'customerfullName'),
                'billing_tax_number' => (string) (
                    data_get($payload, 'taxId')
                    ?: data_get($payload, 'tcIdentityNumber')
                    ?: data_get($billingAddress, 'taxId')
                    ?: data_get($billingAddress, 'tcId')
                    ?: ''
                ),
                'shipment_country' => 'TR',
                'shipment_city' => data_get($shippingAddress, 'city'),
                'shipment_district' => data_get($shippingAddress, 'district'),
                'ordered_at' => $this->normalizeDate(data_get($payload, 'lastModifiedDate')),
                'approved_at' => $this->normalizeDate(data_get($payload, 'agreedDeliveryDate')),
                'delivered_at' => $this->historyDate($payload, 'Delivered'),
                'cancelled_at' => $this->statusHas($status, ['cancel']) ? $this->normalizeDate(data_get($payload, 'lastModifiedDate')) : null,
                'returned_at' => $this->statusHas($status, ['return', 'refund']) ? $this->normalizeDate(data_get($payload, 'lastModifiedDate')) : null,
                'raw_payload' => $payload,
            ],
            'package' => [
                'external_package_id' => (string) (data_get($payload, 'id') ?: data_get($payload, 'orderNumber') ?: ''),
                'package_number' => (string) (data_get($payload, 'id') ?: data_get($payload, 'orderNumber') ?: ''),
                'package_status' => $status,
                'cargo_company' => data_get($payload, 'cargoProviderName'),
                'cargo_tracking_number' => data_get($payload, 'cargoSenderNumber') ?: data_get($payload, 'cargoTrackingNumber'),
                'cargo_barcode' => data_get($payload, 'cargoTrackingNumber'),
                'cargo_desi' => null,
                'shipment_provider' => data_get($payload, 'shipmentCompanyId') ?: data_get($payload, 'shipmentMethod'),
                'shipped_at' => $this->historyDate($payload, 'Shipped'),
                'delivered_at' => $this->historyDate($payload, 'Delivered'),
                'raw_payload' => $payload,
            ],
            'items' => $lines
                ->map(fn (array $line, int $index) => $this->normalizeOrderLine($line, $payload, $index))
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  array<string, mixed>  $packagePayload
     * @return array<string, mixed>
     */
    protected function normalizeOrderLine(array $line, array $packagePayload, int $index): array
    {
        $quantity = max(1, (int) (data_get($line, 'quantity') ?: 1));
        $unitPrice = $this->toDecimal(data_get($line, 'price'));
        $grossAmount = $unitPrice !== null ? round($unitPrice * $quantity, 2) : null;
        $discountAmount = $this->toDecimal(data_get($line, 'totalSellerDiscountPrice') ?: data_get($line, 'sellerDiscount'));
        $marketplaceDiscount = $this->toDecimal(data_get($line, 'totalMallDiscountPrice') ?: data_get($line, 'mallDiscount'));
        $billableAmount = $this->toDecimal(data_get($line, 'sellerInvoiceAmount') ?: data_get($line, 'dueAmount'));

        return [
            'external_line_id' => (string) (data_get($line, 'orderLineId') ?: sha1((data_get($packagePayload, 'orderNumber') ?: 'n11').'|'.$index)),
            'stock_code' => data_get($line, 'stockCode'),
            'barcode' => data_get($line, 'barcode'),
            'product_name' => data_get($line, 'productName'),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'gross_amount' => $grossAmount,
            'discount_amount' => $discountAmount,
            'marketplace_discount_amount' => $marketplaceDiscount,
            'billable_amount' => $billableAmount,
            'commission_rate' => $this->effectiveCommissionRate($line),
            'vat_rate' => $this->toDecimal(data_get($line, 'vatRate')),
            'line_status' => (string) (data_get($line, 'orderItemLineItemStatusName') ?: data_get($packagePayload, 'shipmentPackageStatus') ?: 'Created'),
            'raw_payload' => $line,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizeProduct(array $payload): array
    {
        $stockCode = (string) (data_get($payload, 'stockCode') ?: data_get($payload, 'sellerStockCode') ?: '');
        $externalProductId = (string) (data_get($payload, 'n11ProductId') ?: data_get($payload, 'catalogId') ?: $stockCode);

        return [
            'product' => [
                'external_product_id' => $externalProductId,
                'external_parent_id' => (string) (data_get($payload, 'productMainId') ?: data_get($payload, 'groupId') ?: ''),
                'stock_code' => $stockCode,
                'barcode' => data_get($payload, 'barcode'),
                'title' => data_get($payload, 'title'),
                'brand' => data_get($payload, 'brandName'),
                'category_name' => data_get($payload, 'categoryName') ?: (data_get($payload, 'categoryId') ? 'Kategori #'.data_get($payload, 'categoryId') : null),
                'vat_rate' => $this->toDecimal(data_get($payload, 'vatRate')),
                'raw_payload' => $payload,
            ],
            'listing' => [
                'listing_id' => $externalProductId !== '' ? $externalProductId : $stockCode,
                'listing_status' => $this->normalizeListingStatus($payload),
                'sale_price' => $this->toDecimal(data_get($payload, 'salePrice')),
                'list_price' => $this->toDecimal(data_get($payload, 'listPrice')),
                'currency' => $this->normalizeCurrency((string) (data_get($payload, 'currencyType') ?: 'TL')),
                'stock_quantity' => (int) (data_get($payload, 'quantity') ?: 0),
                'published_at' => $this->normalizeDate(data_get($payload, 'lastModifiedDate') ?: data_get($payload, 'createDate')),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function normalizeListingStatus(array $payload): string
    {
        $saleStatus = Str::upper((string) data_get($payload, 'saleStatus'));
        $status = Str::upper((string) data_get($payload, 'status'));

        if (in_array($saleStatus, ['ON_SALE', 'ONSALE'], true) || $status === 'ACTIVE') {
            return 'active';
        }

        if ($status === 'DELETED') {
            return 'deleted';
        }

        return data_get($payload, 'saleStatus') ?: data_get($payload, 'status') ?: 'draft';
    }

    protected function request(MarketplaceStore $store): PendingRequest
    {
        return Http::baseUrl($this->baseUrl($store))
            ->acceptJson()
            ->timeout((int) config('marketplace.n11.request_timeout', 45))
            ->withHeaders([
                'appkey' => $this->appKey($store),
                'appsecret' => $this->appSecret($store),
            ]);
    }

    protected function appKey(MarketplaceStore $store): string
    {
        $key = trim((string) data_get($store->connection?->credentials_encrypted, 'api_key'));

        if ($key === '') {
            throw new \RuntimeException('N11 bağlantısı için API key zorunludur.');
        }

        return $key;
    }

    protected function appSecret(MarketplaceStore $store): string
    {
        $secret = trim((string) data_get($store->connection?->credentials_encrypted, 'api_secret'));

        if ($secret === '') {
            throw new \RuntimeException('N11 bağlantısı için API secret zorunludur.');
        }

        return $secret;
    }

    protected function baseUrl(MarketplaceStore $store): string
    {
        $baseUrl = trim((string) ($store->connection?->api_base_url ?: config('marketplace.n11.base_url')));

        if ($baseUrl === '') {
            throw new \RuntimeException('N11 API base URL boş.');
        }

        return rtrim($baseUrl, '/');
    }

    protected function stockCode(ChannelListing $listing): string
    {
        $stockCode = trim((string) (
            $listing->channelProduct?->stock_code
            ?: data_get($listing->channelProduct?->raw_payload, 'stockCode')
            ?: data_get($listing->channelProduct?->raw_payload, 'sellerStockCode')
        ));

        if ($stockCode === '') {
            throw new \RuntimeException('N11 fiyat/stok push için stockCode zorunludur.');
        }

        return $stockCode;
    }

    /**
     * @param  array<string, mixed>  $line
     */
    protected function effectiveCommissionRate(array $line): ?float
    {
        $commissionRate = $this->toDecimal(data_get($line, 'commissionRate'));
        $campaignDiscountRate = $this->toDecimal(data_get($line, 'sellerCampaignCommissionRate')) ?: 0.0;

        if ($commissionRate === null) {
            return null;
        }

        return round(max(0, $commissionRate - $campaignDiscountRate), 2);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function historyDate(array $payload, string $status): ?string
    {
        $history = collect(Arr::wrap(data_get($payload, 'packageHistories', [])))
            ->filter(fn ($row) => is_array($row))
            ->first(fn (array $row) => Str::lower(trim((string) data_get($row, 'status'))) === Str::lower($status));

        return $history ? $this->normalizeDate(data_get($history, 'createdDate')) : null;
    }

    protected function normalizeDate(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        if (is_numeric($value)) {
            $timestamp = (int) $value;

            if (abs($timestamp) > 9999999999) {
                $timestamp = (int) floor($timestamp / 1000);
            }

            return CarbonImmutable::createFromTimestampUTC($timestamp)->toIso8601String();
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

    protected function normalizeCurrency(string $currency): string
    {
        return match (Str::upper($currency)) {
            'TL', 'TRY' => 'TRY',
            default => Str::upper($currency),
        };
    }

    protected function currencyType(string $currency): string
    {
        return match (Str::upper($currency)) {
            'TRY', 'TL' => 'TL',
            default => Str::upper($currency),
        };
    }

    protected function statusHas(string $status, array $needles): bool
    {
        return Str::contains(Str::lower($status), array_map(fn (string $needle) => Str::lower($needle), $needles));
    }
}
