<?php

namespace App\Services\Marketplace\Connectors;

use App\Models\ChannelListing;
use App\Models\IntegrationConnection;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\Contracts\PullsClaims;
use App\Services\Marketplace\Contracts\PullsFinancials;
use App\Services\Marketplace\Contracts\PullsOrders;
use App\Services\Marketplace\Contracts\PullsProducts;
use App\Services\Marketplace\Contracts\PushesPrice;
use App\Services\Marketplace\Contracts\PushesStock;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class IdeaSoftConnector extends AbstractMarketplaceConnector implements PullsClaims, PullsFinancials, PullsOrders, PullsProducts, PushesPrice, PushesStock
{
    public function providerKey(): string
    {
        return 'ideasoft';
    }

    public function displayName(): string
    {
        return 'IdeaSoft';
    }

    public function defaultApiBaseUrl(): ?string
    {
        return config('marketplace.ideasoft.base_url') ?: null;
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
            'questions' => false,
            'question_answer' => false,
            'claims' => true,
            'claim_approve' => false,
            'claim_reject' => false,
        ];
    }

    public function testConnection(MarketplaceStore $store): array
    {
        try {
            $payload = $this->send($store, 'GET', 'admin-api/orders', ['limit' => 1, 'page' => 1, 'sort' => '-id'])->json();
            $orders = $this->collectionFromPayload($payload);

            return [
                'ok' => true,
                'message' => 'IdeaSoft Admin API bağlantısı ve sipariş okuma yetkisi doğrulandı.',
                'meta' => [
                    'sample_count' => count($orders),
                    'store_url' => $this->storeBaseUrl($store),
                ],
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'message' => 'IdeaSoft bağlantısı doğrulanamadı: '.$exception->getMessage(),
            ];
        }
    }

    public function pullOrders(MarketplaceStore $store, array $options = []): array
    {
        $query = array_filter([
            'sort' => '-id',
            'status' => $options['status'] ?? null,
            'paymentStatus' => $options['payment_status'] ?? null,
            'startCreatedAt' => $this->dateOnly($options['start_date'] ?? $options['start_at'] ?? null),
            'endCreatedAt' => $this->dateOnly($options['end_date'] ?? $options['end_at'] ?? null),
            'sinceId' => $options['since_id'] ?? null,
            's' => $options['search'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        $result = $this->pullPaginated($store, 'admin-api/orders', $query, $options, 'order_page_size');

        return [
            'items' => collect($result['items'])
                ->filter(fn ($row) => is_array($row))
                ->map(fn (array $row) => $this->normalizeOrder($row))
                ->values()
                ->all(),
            'meta' => $this->syncMeta($result),
        ];
    }

    public function pullProducts(MarketplaceStore $store, array $options = []): array
    {
        $query = array_filter([
            'sort' => '-id',
            'status' => isset($options['status']) ? (string) $options['status'] : null,
            'startUpdatedAt' => $this->dateTime($options['updated_after'] ?? $options['start_date'] ?? null),
            'endUpdatedAt' => $this->dateOnly($options['updated_before'] ?? $options['end_date'] ?? null),
            'sinceId' => $options['since_id'] ?? null,
            's' => $options['search'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        $result = $this->pullPaginated($store, 'admin-api/products', $query, $options, 'product_page_size');
        $items = [];

        foreach ($result['items'] as $product) {
            if (! is_array($product)) {
                continue;
            }

            foreach ($this->normalizeProduct($product, $store) as $normalized) {
                $items[] = $normalized;
            }
        }

        return [
            'items' => $items,
            'meta' => $this->syncMeta($result),
        ];
    }

    public function pullFinancialEvents(MarketplaceStore $store, array $options = []): array
    {
        $query = array_filter([
            'sort' => '-id',
            'status' => $options['status'] ?? null,
            'startCreatedAt' => $this->dateOnly($options['start_date'] ?? $options['start_at'] ?? null),
            'endCreatedAt' => $this->dateOnly($options['end_date'] ?? $options['end_at'] ?? null),
            'sinceId' => $options['since_id'] ?? null,
            'transactionId' => $options['transaction_id'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        $result = $this->pullPaginated($store, 'admin-api/payments', $query, $options, 'finance_page_size');
        $items = collect($result['items'])
            ->filter(fn ($row) => is_array($row))
            ->map(fn (array $row) => $this->normalizeFinancialEvent($row))
            ->filter()
            ->values()
            ->all();

        return [
            'items' => $items,
            'meta' => array_merge($this->syncMeta($result), ['finance_mode' => 'payments']),
        ];
    }

    public function pullClaims(MarketplaceStore $store, array $options = []): array
    {
        $query = array_filter([
            'sort' => '-id',
            'status' => $options['status'] ?? null,
            'sinceId' => $options['since_id'] ?? null,
            'order' => $options['order_id'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        $result = $this->pullPaginated($store, 'admin-api/order_refund_requests', $query, $options, 'claim_page_size');
        $items = collect($result['items'])
            ->filter(fn ($row) => is_array($row))
            ->map(fn (array $row) => $this->normalizeClaim($row))
            ->values()
            ->all();

        return [
            'items' => $items,
            'meta' => $this->syncMeta($result),
        ];
    }

    public function pushPrice(ChannelListing $listing, float $price, array $context = []): array
    {
        $listing->loadMissing(['store.connection', 'channelProduct']);
        $productId = $this->resolveProductId($listing);
        $payload = array_filter([
            'price1' => round($price, 2),
            'buyingPrice' => isset($context['buying_price']) ? round((float) $context['buying_price'], 2) : null,
            'marketPriceDetail' => $context['market_price_detail'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
        $response = $this->send($listing->store, 'PUT', 'admin-api/products/'.$productId, [], $payload)->json();

        return [
            'status' => 'completed',
            'provider' => $this->providerKey(),
            'listing_id' => $listing->id,
            'product_id' => $productId,
            'price' => round($price, 2),
            'external_action_id' => $productId,
            'response' => $response,
        ];
    }

    public function pushStock(ChannelListing $listing, int $quantity, array $context = []): array
    {
        $listing->loadMissing(['store.connection', 'channelProduct']);
        $productId = $this->resolveProductId($listing);
        $payload = ['stockAmount' => max(0, $quantity)];
        $response = $this->send($listing->store, 'PUT', 'admin-api/products/'.$productId, [], $payload)->json();

        return [
            'status' => 'completed',
            'provider' => $this->providerKey(),
            'listing_id' => $listing->id,
            'product_id' => $productId,
            'quantity' => max(0, $quantity),
            'external_action_id' => $productId,
            'response' => $response,
        ];
    }

    public function verifyWebhookSignature(Request $request, ?IntegrationConnection $connection): bool
    {
        if (! $connection) {
            return false;
        }

        $signature = trim((string) (
            $request->header('X-Ideashop-Hmac-Sha256')
            ?: $request->server('HTTP_X_IDEASHOP_HMAC_SHA256')
        ));
        $secret = trim((string) data_get($connection->credentials_encrypted, 'api_secret'));

        if ($signature === '' || $secret === '') {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', $request->getContent(), $secret, true));

        return hash_equals($expected, $signature);
    }

    public function extractWebhookMetadata(Request $request): array
    {
        $payload = $request->json()->all();

        if ($payload === []) {
            $payload = $request->all();
        }

        $eventType = $request->header('X-Ideashop-Topic')
            ?: data_get($payload, 'topic')
            ?: data_get($payload, 'event')
            ?: data_get($payload, 'type');
        $externalId = data_get($payload, 'id')
            ?: data_get($payload, 'data.id')
            ?: data_get($payload, 'resource.id');

        return [
            'event_type' => filled($eventType) ? (string) $eventType : null,
            'external_event_id' => filled($externalId) ? (string) $externalId : null,
            'payload' => is_array($payload) ? $payload : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $options
     * @return array{items: array<int, mixed>, pages_processed: int, last_page: int, more_pages_available: bool}
     */
    protected function pullPaginated(MarketplaceStore $store, string $path, array $query, array $options, string $pageSizeConfig): array
    {
        $pageSize = min(100, max(1, (int) ($options['page_size'] ?? config('marketplace.ideasoft.'.$pageSizeConfig, 100))));
        $maxPages = max(1, (int) ($options['max_pages'] ?? config('marketplace.ideasoft.max_pages_per_sync', 50)));
        $page = max(1, (int) ($options['page'] ?? 1));
        $items = [];
        $pagesProcessed = 0;
        $more = false;

        do {
            $payload = $this->send($store, 'GET', $path, array_merge($query, [
                'limit' => $pageSize,
                'page' => $page,
            ]))->json();
            $rows = $this->collectionFromPayload($payload);

            foreach ($rows as $row) {
                $items[] = $row;
            }

            $pagesProcessed++;
            $more = count($rows) >= $pageSize;
            $page++;
        } while ($more && $pagesProcessed < $maxPages);

        return [
            'items' => $items,
            'pages_processed' => $pagesProcessed,
            'last_page' => $page - 1,
            'more_pages_available' => $more,
        ];
    }

    /**
     * @return array<int, mixed>
     */
    protected function collectionFromPayload(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        if (array_is_list($payload)) {
            return $payload;
        }

        foreach (['data', 'items', 'results', 'content'] as $key) {
            $candidate = data_get($payload, $key);

            if (is_array($candidate) && array_is_list($candidate)) {
                return $candidate;
            }
        }

        return filled($payload['id'] ?? null) ? [$payload] : [];
    }

    protected function send(MarketplaceStore $store, string $method, string $path, array $query = [], array $json = []): Response
    {
        $store->loadMissing('connection');
        $connection = $store->connection;

        if (! $connection) {
            throw new \RuntimeException('IdeaSoft bağlantı kaydı bulunamadı.');
        }

        $response = $this->sendWithToken($store, $method, $path, $query, $json, $this->accessToken($connection));

        if ($response->status() === 401 && filled(data_get($connection->credentials_encrypted, 'refresh_token'))) {
            $response = $this->sendWithToken($store, $method, $path, $query, $json, $this->refreshAccessToken($connection));
        }

        return $response->throw();
    }

    protected function sendWithToken(MarketplaceStore $store, string $method, string $path, array $query, array $json, string $token): Response
    {
        $options = [];

        if ($query !== []) {
            $options['query'] = array_filter($query, fn ($value) => $value !== null && $value !== '');
        }

        if ($json !== []) {
            $options['json'] = $json;
        }

        return Http::acceptJson()
            ->withToken($token)
            ->timeout((int) config('marketplace.ideasoft.timeout_seconds', 30))
            ->send(Str::upper($method), $this->storeBaseUrl($store).'/'.ltrim($path, '/'), $options);
    }

    protected function accessToken(IntegrationConnection $connection): string
    {
        $credentials = $connection->credentials_encrypted ?? [];
        $token = trim((string) ($credentials['access_token'] ?? ''));
        $expiresAt = data_get($credentials, 'token_expires_at');

        if ($token !== '' && (! filled($expiresAt) || CarbonImmutable::parse($expiresAt)->isAfter(now()->addMinute()))) {
            return $token;
        }

        if (filled($credentials['refresh_token'] ?? null)) {
            return $this->refreshAccessToken($connection);
        }

        throw new \RuntimeException('IdeaSoft mağazası henüz OAuth ile yetkilendirilmedi. Bağlantı ekranındaki “IdeaSoft’ta Yetkilendir” adımını tamamlayın.');
    }

    protected function refreshAccessToken(IntegrationConnection $connection): string
    {
        $credentials = $connection->credentials_encrypted ?? [];
        $clientId = trim((string) ($credentials['api_key'] ?? ''));
        $clientSecret = trim((string) ($credentials['api_secret'] ?? ''));
        $refreshToken = trim((string) ($credentials['refresh_token'] ?? ''));
        $baseUrl = $this->baseUrlFromConnection($connection);

        if ($clientId === '' || $clientSecret === '' || $refreshToken === '') {
            throw new \RuntimeException('IdeaSoft token yenilemek için Client ID, Client Secret ve Refresh Token zorunludur.');
        }

        $response = Http::asForm()
            ->acceptJson()
            ->timeout((int) config('marketplace.ideasoft.timeout_seconds', 30))
            ->post($baseUrl.'/oauth/v2/token', [
                'grant_type' => 'refresh_token',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'refresh_token' => $refreshToken,
            ])
            ->throw();
        $payload = $response->json();
        $accessToken = trim((string) data_get($payload, 'access_token'));

        if ($accessToken === '') {
            throw new \RuntimeException('IdeaSoft OAuth yenileme cevabında access_token bulunamadı.');
        }

        $expiresIn = max(120, (int) data_get($payload, 'expires_in', 86400));
        $connection->forceFill([
            'credentials_encrypted' => array_merge($credentials, [
                'access_token' => $accessToken,
                'refresh_token' => (string) (data_get($payload, 'refresh_token') ?: $refreshToken),
                'token_expires_at' => now()->addSeconds($expiresIn)->toIso8601String(),
                'oauth_scope' => data_get($payload, 'scope'),
            ]),
        ])->save();

        return $accessToken;
    }

    protected function storeBaseUrl(MarketplaceStore $store): string
    {
        $store->loadMissing('connection');

        if (! $store->connection) {
            throw new \RuntimeException('IdeaSoft bağlantı kaydı bulunamadı.');
        }

        return $this->baseUrlFromConnection($store->connection);
    }

    protected function baseUrlFromConnection(IntegrationConnection $connection): string
    {
        $credentials = $connection->credentials_encrypted ?? [];
        $baseUrl = trim((string) ($connection->api_base_url ?: ($credentials['store_url'] ?? '') ?: $this->defaultApiBaseUrl()));

        if ($baseUrl === '') {
            throw new \RuntimeException('IdeaSoft mağaza URL zorunludur.');
        }

        $parts = parse_url($baseUrl);

        if (($parts['scheme'] ?? null) !== 'https' || blank($parts['host'] ?? null)) {
            throw new \RuntimeException('IdeaSoft mağaza URL geçerli bir HTTPS adresi olmalıdır.');
        }

        return rtrim($baseUrl, '/');
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizeOrder(array $payload): array
    {
        $orderId = (string) data_get($payload, 'id');
        $status = $this->normalizeOrderStatus((string) data_get($payload, 'status'));
        $shipping = Arr::wrap(data_get($payload, 'shippingAddress'));
        $billing = Arr::wrap(data_get($payload, 'billingAddress'));
        $customerName = trim((string) (data_get($payload, 'customerFirstname').' '.data_get($payload, 'customerSurname')));
        $commercial = filled(data_get($billing, 'taxNo')) || ! in_array(Str::lower((string) data_get($billing, 'invoiceType')), ['', 'individual', 'bireysel'], true);

        return [
            'order' => [
                'external_order_id' => $orderId,
                'order_number' => $orderId,
                'order_status' => $status,
                'commercial_type' => $commercial ? 'commercial' : 'individual',
                'currency' => Str::upper((string) (data_get($payload, 'currency') ?: 'TRY')),
                'exchange_rate' => 1,
                'customer_name' => $customerName,
                'customer_email' => data_get($payload, 'customerEmail'),
                'customer_phone' => data_get($payload, 'customerPhone') ?: data_get($shipping, 'mobilePhoneNumber') ?: data_get($shipping, 'phoneNumber'),
                'billing_name' => trim((string) (data_get($billing, 'firstname').' '.data_get($billing, 'surname'))),
                'billing_tax_number' => data_get($billing, 'taxNo') ?: data_get($billing, 'identityRegistrationNumber'),
                'shipment_country' => data_get($shipping, 'country'),
                'shipment_city' => data_get($shipping, 'location'),
                'shipment_district' => data_get($shipping, 'subLocation'),
                'ordered_at' => $this->dateTime(data_get($payload, 'createdAt')),
                'approved_at' => in_array($status, ['approved', 'picking', 'shipped', 'delivered'], true) ? $this->dateTime(data_get($payload, 'updatedAt')) : null,
                'delivered_at' => $status === 'delivered' ? $this->dateTime(data_get($payload, 'updatedAt')) : null,
                'cancelled_at' => $status === 'cancelled' ? $this->dateTime(data_get($payload, 'updatedAt')) : null,
                'returned_at' => $status === 'returned' ? $this->dateTime(data_get($payload, 'updatedAt')) : null,
                'raw_payload' => $payload,
            ],
            'package' => [
                'external_package_id' => $orderId,
                'package_number' => $orderId,
                'package_status' => $status,
                'cargo_company' => data_get($payload, 'shippingCompanyName') ?: data_get($payload, 'shippingProviderName'),
                'cargo_tracking_number' => data_get($payload, 'shippingTrackingCode'),
                'shipment_provider' => data_get($payload, 'shippingProviderCode') ?: data_get($payload, 'shippingProviderName'),
                'shipped_at' => in_array($status, ['shipped', 'delivered'], true) ? $this->dateTime(data_get($payload, 'updatedAt')) : null,
                'delivered_at' => $status === 'delivered' ? $this->dateTime(data_get($payload, 'updatedAt')) : null,
                'raw_payload' => [
                    'order_id' => $orderId,
                    'shipping_provider_code' => data_get($payload, 'shippingProviderCode'),
                    'shipping_tracking_code' => data_get($payload, 'shippingTrackingCode'),
                ],
            ],
            'items' => collect(Arr::wrap(data_get($payload, 'orderItems')))
                ->filter(fn ($row) => is_array($row))
                ->values()
                ->map(fn (array $row, int $index) => $this->normalizeOrderLine($row, $orderId, $status, $index))
                ->all(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeProduct(array $payload, MarketplaceStore $store): array
    {
        $parentId = (string) data_get($payload, 'id');
        $children = collect(Arr::wrap(data_get($payload, 'children')))->filter(fn ($row) => is_array($row));
        $variants = $children->isEmpty() ? collect([$payload]) : $children;

        return $variants->map(function (array $variant) use ($payload, $parentId, $store, $children): array {
            $variantId = (string) data_get($variant, 'id');
            $isActive = (int) data_get($variant, 'status', data_get($payload, 'status', 0)) === 1;
            $currency = data_get($variant, 'currency.abbr')
                ?: data_get($payload, 'currency.abbr')
                ?: data_get($variant, 'currency')
                ?: $store->currency
                ?: 'TRY';

            return [
                'product' => [
                    'external_product_id' => $variantId,
                    'external_parent_id' => $children->isEmpty() ? null : $parentId,
                    'stock_code' => (string) (data_get($variant, 'sku') ?: $variantId),
                    'barcode' => data_get($variant, 'barcode'),
                    'title' => data_get($variant, 'fullName') ?: data_get($variant, 'name') ?: data_get($payload, 'fullName') ?: data_get($payload, 'name'),
                    'brand' => data_get($variant, 'brand.name') ?: data_get($payload, 'brand.name'),
                    'category_name' => collect(Arr::wrap(data_get($payload, 'categories')))->pluck('name')->filter()->implode(' > ') ?: null,
                    'vat_rate' => data_get($variant, 'tax') ?? data_get($payload, 'tax'),
                    'description' => data_get($variant, 'detail.details') ?: data_get($payload, 'detail.details') ?: data_get($payload, 'shortDetails'),
                    'images' => data_get($variant, 'images') ?: data_get($payload, 'images') ?: [],
                    'attributes' => [
                        'option_groups' => data_get($payload, 'optionGroups', []),
                        'spec_group' => data_get($variant, 'specGroup') ?: data_get($payload, 'specGroup'),
                        'extra_infos' => data_get($variant, 'extraInfos') ?: data_get($payload, 'extraInfos', []),
                        'labels' => data_get($variant, 'labels') ?: data_get($payload, 'labels', []),
                    ],
                    'approval_status' => $isActive ? 'approved' : 'passive',
                    'is_catalog_product' => true,
                    'raw_payload' => array_merge($payload, ['variant' => $variant]),
                ],
                'listing' => array_merge([
                    'listing_id' => $variantId,
                    'listing_status' => $isActive ? 'active' : 'passive',
                    'sale_price' => $this->money(data_get($variant, 'price1') ?? data_get($payload, 'price1')),
                    'list_price' => $this->money(data_get($variant, 'price1') ?? data_get($payload, 'price1')),
                    'currency' => Str::upper((string) $currency),
                    'stock_quantity' => (int) round((float) (data_get($variant, 'stockAmount') ?? data_get($payload, 'stockAmount', 0))),
                    'published_at' => $this->dateTime(data_get($variant, 'createdAt') ?: data_get($payload, 'createdAt')),
                ], $this->catalogDeliveryTermData($variant, $payload)),
            ];
        })->values()->all();
    }

    protected function normalizeOrderLine(array $payload, string $orderId, string $status, int $index): array
    {
        $quantity = max(1, (int) round((float) data_get($payload, 'productQuantity', 1)));
        $unitPrice = $this->money(data_get($payload, 'productPrice'));
        $gross = $unitPrice !== null ? round($unitPrice * $quantity, 2) : null;
        $discount = $this->money(data_get($payload, 'discount') ?? data_get($payload, 'productDiscount'));

        return [
            'external_line_id' => (string) (data_get($payload, 'id') ?: sha1($orderId.'|'.$index)),
            'stock_code' => (string) data_get($payload, 'productSku'),
            'barcode' => data_get($payload, 'productBarcode'),
            'product_name' => data_get($payload, 'productName'),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'gross_amount' => $gross,
            'discount_amount' => $discount,
            'marketplace_discount_amount' => null,
            'billable_amount' => $gross !== null ? max(0, round($gross - ($discount ?? 0), 2)) : null,
            'commission_rate' => null,
            'vat_rate' => data_get($payload, 'productTax'),
            'line_status' => $status,
            'raw_payload' => $payload,
        ];
    }

    protected function normalizeFinancialEvent(array $payload): ?array
    {
        $amount = $this->money(data_get($payload, 'finalAmount') ?? data_get($payload, 'amount'));

        if ($amount === null) {
            return null;
        }

        $status = Str::lower((string) data_get($payload, 'status', 'posted'));
        $eventDate = $this->dateTime(data_get($payload, 'updatedAt') ?: data_get($payload, 'createdAt'));

        return [
            'event_source' => 'ideasoft_payment',
            'external_event_id' => (string) data_get($payload, 'id'),
            'order_number' => (string) (data_get($payload, 'transactionId') ?: data_get($payload, 'id')),
            'external_package_id' => null,
            'external_line_id' => null,
            'stock_code' => null,
            'barcode' => null,
            'event_type' => Str::contains($status, ['refund', 'cancel', 'delete']) ? 'refund' : 'payment',
            'reference_number' => (string) (data_get($payload, 'transactionId') ?: data_get($payload, 'id')),
            'event_date' => $eventDate,
            'due_date' => null,
            'settlement_date' => $eventDate,
            'amount' => abs($amount),
            'currency' => Str::upper((string) (data_get($payload, 'currency') ?: 'TRY')),
            'direction' => Str::contains($status, ['refund', 'cancel', 'delete']) ? 'debit' : 'credit',
            'status' => $status,
            'notes' => collect([
                data_get($payload, 'paymentTypeName'),
                data_get($payload, 'paymentProviderName'),
                data_get($payload, 'paymentGatewayName'),
                data_get($payload, 'bankName'),
                data_get($payload, 'errorMessage'),
            ])->filter()->implode(' | ') ?: null,
            'raw_payload' => $payload,
        ];
    }

    protected function normalizeClaim(array $payload): array
    {
        $order = Arr::wrap(data_get($payload, 'order'));
        $items = collect(Arr::wrap(data_get($payload, 'refundRequestItems')))
            ->filter(fn ($row) => is_array($row))
            ->map(fn (array $row) => [
                'external_item_id' => (string) (data_get($row, 'id') ?: data_get($row, 'orderItem.id')),
                'external_order_line_id' => (string) data_get($row, 'orderItem.id'),
                'product_name' => data_get($row, 'orderItem.productName') ?: data_get($row, 'productName'),
                'stock_code' => data_get($row, 'orderItem.productSku') ?: data_get($row, 'productSku'),
                'barcode' => data_get($row, 'orderItem.productBarcode') ?: data_get($row, 'productBarcode'),
                'quantity' => (int) (data_get($row, 'quantity') ?: 1),
                'reason' => data_get($payload, 'cancellationReason'),
                'customer_note' => data_get($row, 'note'),
                'raw_payload' => $row,
            ])->values()->all();

        return [
            'external_claim_id' => (string) data_get($payload, 'id'),
            'order_number' => (string) (data_get($order, 'id') ?: data_get($payload, 'code')),
            'cargo_tracking_number' => data_get($order, 'shippingTrackingCode'),
            'cargo_provider' => data_get($order, 'shippingCompanyName') ?: data_get($order, 'shippingProviderName'),
            'status' => Str::lower((string) data_get($payload, 'status', 'waiting_for_approval')),
            'type' => 'return',
            'reason' => data_get($payload, 'cancellationReason'),
            'reason_detail' => data_get($payload, 'code'),
            'customer_note' => null,
            'customer_name' => trim((string) (data_get($order, 'customerFirstname').' '.data_get($order, 'customerSurname'))),
            'created_date' => $this->dateTime(data_get($payload, 'createdAt')),
            'items' => $items,
            'raw_payload' => $payload,
        ];
    }

    protected function normalizeOrderStatus(string $status): string
    {
        return match (Str::lower(trim($status))) {
            'approved' => 'approved',
            'being_prepared', 'on_accumulation' => 'picking',
            'fulfilled' => 'shipped',
            'delivered' => 'delivered',
            'cancelled' => 'cancelled',
            'refunded' => 'returned',
            'waiting_for_payment', 'waiting_for_approval' => 'created',
            default => Str::lower(trim($status)) ?: 'created',
        };
    }

    protected function resolveProductId(ChannelListing $listing): string
    {
        $id = (string) (
            $listing->channelProduct?->external_product_id
            ?: data_get($listing->channelProduct?->raw_payload, 'variant.id')
            ?: $listing->listing_id
        );

        if ($id === '' || ! ctype_digit($id)) {
            throw new \RuntimeException('IdeaSoft fiyat/stok gönderimi için sayısal ürün veya varyant ID bulunamadı. Önce ürün senkronunu çalıştırın.');
        }

        return $id;
    }

    protected function dateOnly(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function dateTime(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function money(mixed $value): ?float
    {
        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    /**
     * @param  array{items: array<int, mixed>, pages_processed: int, last_page: int, more_pages_available: bool}  $result
     * @return array<string, mixed>
     */
    protected function syncMeta(array $result): array
    {
        return [
            'items_received' => count($result['items']),
            'pages_processed' => $result['pages_processed'],
            'page' => $result['last_page'],
            'more_pages_available' => $result['more_pages_available'],
            'cursor_after' => now()->toIso8601String(),
        ];
    }
}
