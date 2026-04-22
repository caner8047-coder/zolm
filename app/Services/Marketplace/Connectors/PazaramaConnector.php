<?php

namespace App\Services\Marketplace\Connectors;

use App\Models\MarketplaceStore;
use App\Services\Marketplace\Contracts\PullsFinancials;
use App\Services\Marketplace\Contracts\PullsOrders;
use App\Services\Marketplace\Contracts\PullsProducts;
use App\Services\Marketplace\Contracts\TestsConnection;
use App\Services\Marketplace\Support\PazaramaOrderStatusResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PazaramaConnector extends AbstractMarketplaceConnector implements PullsOrders, PullsProducts, PullsFinancials, TestsConnection
{
    public function providerKey(): string
    {
        return 'pazarama';
    }

    public function displayName(): string
    {
        return 'Pazarama';
    }

    public function defaultApiBaseUrl(): ?string
    {
        return config('marketplace.pazarama.base_url', 'https://isortagimapi.pazarama.com/');
    }

    protected function authBaseUrl(): string
    {
        return config('marketplace.pazarama.auth_url', 'https://isortagimgiris.pazarama.com/');
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
            'webhooks' => false,
            // CRITICAL: User strictly requested price push to be DISABLED.
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
        try {
            $token = $this->getAccessToken($store);

            return [
                'ok' => filled($token),
                'message' => filled($token) ? 'Bağlantı başarıyla doğrulandı.' : 'Token oluşturulamadı.',
                'meta' => [
                    'provider' => $this->providerKey(),
                    'store_id' => $store->id,
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Bağlantı hatası: ' . $e->getMessage(),
                'meta' => [
                    'provider' => $this->providerKey(),
                    'store_id' => $store->id,
                ],
            ];
        }
    }

    public function pullOrders(MarketplaceStore $store, array $options = []): array
    {
        $items = [];
        $page = 1;
        $size = min((int) ($options['page_size'] ?? 100), 100);
        
        $requestedStartDate = CarbonImmutable::parse($options['start_date'])->setTimezone('UTC');
        $endDate = CarbonImmutable::parse($options['end_date'])->setTimezone('UTC');
        [$startDate, $orderWindowMeta] = $this->resolveOrderWindow($requestedStartDate, $endDate);

        do {
            $response = $this->request($store)
                ->post('order/getOrdersForApi', [
                    'StartDate' => $startDate->format('Y-m-d\TH:i:s'),
                    'EndDate' => $endDate->format('Y-m-d\TH:i:s'),
                    'Page' => $page,
                    'Size' => $size,
                ])
                ->throw()
                ->json();

            $content = Arr::get($response, 'data', []);

            foreach ($content as $packagePayload) {
                // Her sipariş payload'ını standart formata normalize ediyoruz
                $items[] = $this->normalizeOrderPackage($packagePayload);
            }

            $totalCount = (int) Arr::get($response, 'totalCount', 0);
            $totalPages = (int) ceil($totalCount / $size);

            $page++;
        } while ($page <= $totalPages);

        return [
            'items' => $items,
            'meta' => array_merge([
                'items_received' => count($items),
                'cursor_after' => $endDate->toIso8601String(),
            ], $orderWindowMeta),
        ];
    }

    /**
     * @return array{0: CarbonImmutable, 1: array<string, mixed>}
     */
    protected function resolveOrderWindow(CarbonImmutable $requestedStartDate, CarbonImmutable $endDate): array
    {
        $historyLimitDays = max(1, (int) config('marketplace.pazarama.order_history_limit_days', 90));
        $minAllowedStartDate = CarbonImmutable::now('UTC')->subDays($historyLimitDays);

        $effectiveStartDate = $requestedStartDate->lessThan($minAllowedStartDate)
            ? $minAllowedStartDate
            : $requestedStartDate;

        if ($endDate->lessThan($effectiveStartDate)) {
            $endDate = $effectiveStartDate->addHour();
        }

        return [
            $effectiveStartDate,
            [
                'history_limit_days' => $historyLimitDays,
                'requested_start_date' => $requestedStartDate->toIso8601String(),
                'effective_start_date' => $effectiveStartDate->toIso8601String(),
                'window_clamped' => !$effectiveStartDate->equalTo($requestedStartDate),
            ],
        ];
    }

    public function pullProducts(MarketplaceStore $store, array $options = []): array
    {
        $items = [];
        $page = 1;
        $size = min((int) ($options['page_size'] ?? 100), 100);
        $endDate = CarbonImmutable::parse($options['end_date'] ?? now())->setTimezone('UTC');

        do {
            $response = $this->request($store)
                ->get('product/getProducts', [
                    'Page' => $page,
                    'Size' => $size,
                    'Approved' => 'true',
                ])
                ->throw()
                ->json();

            $content = Arr::get($response, 'data', []);

            foreach ($content as $productPayload) {
                $items[] = $this->normalizeProduct($productPayload);
            }

            // Pazarama uses totalCount for pagination
            $totalCount = (int) Arr::get($response, 'totalCount', 0);
            $totalPages = (int) ceil($totalCount / $size);

            $page++;
        } while ($page <= $totalPages);

        return [
            'items' => $items,
            'meta' => [
                'items_received' => count($items),
                'cursor_after' => $endDate->toIso8601String(),
            ],
        ];
    }

    public function pullFinancialEvents(MarketplaceStore $store, array $options = []): array
    {
        // Pazarama does not provide public endpoints for settlement lines currently.
        // We return empty events to satisfy the structural mapping without failures.
        $endDate = CarbonImmutable::parse($options['end_date'])->setTimezone('UTC');

        return [
            'items' => [],
            'meta' => [
                'items_received' => 0,
                'cursor_after' => $endDate->toIso8601String(),
            ],
        ];
    }

    protected function request(MarketplaceStore $store): PendingRequest
    {
        $accessToken = $this->getAccessToken($store);

        return Http::baseUrl(rtrim((string) ($store->connection?->api_base_url ?: $this->defaultApiBaseUrl()), '/').'/')
            ->timeout((int) config('marketplace.pazarama.request_timeout', 30))
            ->acceptJson()
            ->withToken($accessToken);
    }

    protected function sellerId(MarketplaceStore $store): string
    {
        $sellerId = (string) ($store->seller_id ?: data_get($store->connection?->credentials_encrypted, 'seller_id'));

        if ($sellerId === '') {
            throw new \RuntimeException('Pazarama baglantisi icin Satici ID (seller_id) zorunludur.');
        }

        return $sellerId;
    }

    protected function getAccessToken(MarketplaceStore $store): string
    {
        $connection = $store->connection;
        $credentials = $connection?->credentials_encrypted ?? [];
        $apiKey = (string) ($credentials['api_key'] ?? '');
        $apiSecret = (string) ($credentials['api_secret'] ?? '');

        if ($apiKey === '' || $apiSecret === '') {
            throw new \RuntimeException('Pazarama baglantisi icin API Key ve API Secret zorunludur.');
        }

        $cacheKey = "pazarama_access_token_{$store->id}";

        return Cache::remember($cacheKey, 3500, function () use ($apiKey, $apiSecret) {
            $response = Http::baseUrl($this->authBaseUrl())
                ->asForm()
                ->post('connect/token', [
                    'grant_type' => 'client_credentials',
                    'client_id' => $apiKey,
                    'client_secret' => $apiSecret,
                    'scope' => 'merchantgatewayapi.fullaccess',
                ]);

            if ($response->failed()) {
                throw new \RuntimeException('Pazarama auth hatasi: ' . $response->status() . ' ' . $response->body());
            }

            $token = $response->json('data.accessToken');
            
            if (!$token) {
                throw new \RuntimeException('Pazarama auth token alinamadi (Response: ' . $response->body() . ')');
            }
            
            return $token;
        });
    }

    protected function mapPazaramaOrderStatus(mixed $orderStatus, ?string $itemStatusName = null): string
    {
        return $this->pazaramaStatusResolver()->resolveStatus($orderStatus, $itemStatusName);
    }

    protected function normalizeOrderPackage(array $payload): array
    {
        $orderNumber = (string) data_get($payload, 'orderNumber');
        $packageId = (string) data_get($payload, 'id', $orderNumber);
        
        $status = $this->pazaramaStatusResolver()->resolveOrderStatus($payload);
        $timeline = $this->pazaramaStatusResolver()->resolvePackageTimeline($payload, $status);
        
        $customerName = trim((string) collect([
            data_get($payload, 'customerName'),
            data_get($payload, 'buyer.firstName'),
            data_get($payload, 'buyer.lastName'),
        ])->filter()->implode(' '));

        $cargoCompany = data_get($payload, 'items.0.cargo.companyName') ?: data_get($payload, 'cargoCompany.name');
        $trackingNumber = data_get($payload, 'items.0.cargo.trackingNumber') ?: data_get($payload, 'cargoTrackingNumber');
        $approvedAt = in_array($status, ['Approved', 'Processing', 'Shipped', 'Delivered'], true)
            ? $this->normalizeDate(data_get($payload, 'approvedDate') ?: data_get($payload, 'orderDate') ?: data_get($payload, 'createdDate'))
            : null;

        return [
            'order' => [
                'external_order_id' => $orderNumber,
                'order_number' => $orderNumber,
                'order_status' => $status,
                'customer_name' => $customerName ?: data_get($payload, 'buyer.fullName'),
                'customer_email' => data_get($payload, 'customerEmail') ?: data_get($payload, 'buyer.email'),
                'customer_phone' => data_get($payload, 'shipmentAddress.phoneNumber') ?: data_get($payload, 'billingAddress.phoneNumber'),
                'shipment_city' => data_get($payload, 'shipmentAddress.cityName') ?: data_get($payload, 'shippingAddress.city'),
                'shipment_district' => data_get($payload, 'shipmentAddress.districtName') ?: data_get($payload, 'shippingAddress.district'),
                'billing_name' => data_get($payload, 'billingAddress.nameSurname') ?: data_get($payload, 'billingAddress.companyName') ?: data_get($payload, 'invoiceAddress.fullName'),
                'billing_tax_number' => data_get($payload, 'billingAddress.taxNumber') ?: data_get($payload, 'invoiceAddress.taxIdentityNumber'),
                'ordered_at' => $this->normalizeDate(data_get($payload, 'orderDate') ?: data_get($payload, 'createdDate')),
                'approved_at' => $approvedAt,
                'delivered_at' => $timeline['delivered_at'],
                'raw_payload' => $payload,
            ],
            'package' => [
                'external_package_id' => $packageId,
                'package_number' => $packageId,
                'package_status' => $status,
                'cargo_company' => $cargoCompany,
                'cargo_tracking_number' => $trackingNumber,
                'cargo_barcode' => $trackingNumber,
                'shipped_at' => $timeline['shipped_at'],
                'delivered_at' => $timeline['delivered_at'],
                'raw_payload' => $payload,
            ],
            'items' => collect(data_get($payload, 'items', data_get($payload, 'itemList', [])))
                ->map(fn (array $line, int $index) => $this->normalizeOrderLine($line, $payload, $index))
                ->all(),
        ];
    }

    protected function normalizeOrderLine(array $line, array $packagePayload, int $index = 0): array
    {
        $quantity = (int) (data_get($line, 'quantity') ?: 1);
        $unitPrice = $this->toDecimal(data_get($line, 'salePrice.value') ?: data_get($line, 'price')) ?? 0.0;
        $grossAmount = $this->toDecimal(data_get($line, 'totalPrice.value')) ?? ($quantity * $unitPrice);
        $discountAmount = $this->toDecimal(data_get($line, 'discountAmount.value', 0));
        
        $orderNumber = (string) data_get($packagePayload, 'orderNumber');
        $stockCode = $this->stockCodeFromPayload($line, 'product.code');
        
        $fallbackLineId = sha1(implode('|', array_filter([
            $orderNumber,
            $stockCode,
            (string) data_get($line, 'product.barcode'),
            (string) $quantity,
            (string) $index,
        ], fn ($value) => $value !== '')));

        return [
            'external_line_id' => $this->lineIdFromPayload($line, $fallbackLineId, 'orderItemId'),
            'stock_code' => $stockCode,
            'barcode' => data_get($line, 'product.barcode') ?: data_get($line, 'product.stockCode'),
            'product_name' => data_get($line, 'product.name'),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'gross_amount' => $grossAmount,
            'discount_amount' => $discountAmount,
            'billable_amount' => round(max(0, $grossAmount - $discountAmount), 2),
            'commission_rate' => $this->toDecimal(data_get($line, 'commissionRate') ?: data_get($line, 'commissionRate.value')),
            'vat_rate' => $this->toDecimal(data_get($line, 'product.vatRate')) ?? 0.0,
            'line_status' => $this->pazaramaStatusResolver()->resolveLineStatus(
                $line,
                data_get($packagePayload, 'orderStatus')
            ),
            'raw_payload' => $line,
        ];
    }

    protected function pazaramaStatusResolver(): PazaramaOrderStatusResolver
    {
        return app(PazaramaOrderStatusResolver::class);
    }

    protected function normalizeProduct(array $payload): array
    {
        $stockCode = $this->stockCodeFromPayload($payload, 'code');
        $barcode = data_get($payload, 'barcode');
        $externalProductId = (string) (data_get($payload, 'id') ?: $barcode ?: $stockCode);

        // Pazarama doesn't explicitly expose parent vs variant in standard basic GET but serves variants. 
        // We fallback parent to id logic.
        return [
            'product' => [
                'external_product_id' => $externalProductId,
                'external_parent_id' => (string) data_get($payload, 'parentProductId', ''),
                'stock_code' => $stockCode,
                'barcode' => $barcode,
                'title' => data_get($payload, 'name'),
                'brand' => data_get($payload, 'brand.name'),
                'category_name' => data_get($payload, 'category.name'),
                'vat_rate' => $this->toDecimal(data_get($payload, 'vatRate')),
                'raw_payload' => $payload,
            ],
            'listing' => [
                'listing_id' => $externalProductId,
                'listing_status' => data_get($payload, 'active') ? 'active' : 'inactive',
                'sale_price' => $this->toDecimal(data_get($payload, 'salePrice')),
                'list_price' => $this->toDecimal(data_get($payload, 'listPrice')),
                'currency' => 'TRY',
                'stock_quantity' => (int) data_get($payload, 'stockQuantity', 0),
                'published_at' => $this->normalizeDate(data_get($payload, 'createdDate')),
            ],
        ];
    }

    protected function normalizeDate(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        if (is_numeric($value)) {
            $timestamp = (int) $value;
            if ($timestamp > 9999999999) {
                return CarbonImmutable::createFromTimestampMs($timestamp)->toIso8601String();
            }
            return CarbonImmutable::createFromTimestamp($timestamp)->toIso8601String();
        }

        try {
            return CarbonImmutable::parse((string) $value)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function toDecimal(mixed $value): ?float
    {
        if (blank($value)) {
            return null;
        }
        return round((float) $value, 2);
    }

    protected function lineUnitPrice(array $line, string $key = 'price'): float
    {
        return $this->toDecimal(data_get($line, $key)) ?? 0.0;
    }

    protected function lineGrossAmount(array $line, int $quantity, float $unitPrice): float
    {
        $amount = $this->toDecimal(data_get($line, 'grossAmount')) ?? ($quantity * $unitPrice);
        return round($amount, 2);
    }

    protected function stockCodeFromPayload(array $payload, string $key = 'code'): string
    {
        return (string) data_get($payload, $key, '');
    }

    protected function lineIdFromPayload(array $payload, string $fallback, string $key = 'id'): string
    {
        return (string) (data_get($payload, $key) ?: $fallback);
    }

    protected function lineVatRate(array $line, string $key = 'vatRate'): float
    {
        return $this->toDecimal(data_get($line, $key)) ?? 0.0;
    }
}
