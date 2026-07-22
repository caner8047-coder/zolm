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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class IkasConnector extends AbstractMarketplaceConnector implements PullsClaims, PullsFinancials, PullsOrders, PullsProducts, PushesPrice, PushesStock
{
    public function providerKey(): string
    {
        return 'ikas';
    }

    public function displayName(): string
    {
        return 'ikas';
    }

    public function defaultApiBaseUrl(): ?string
    {
        return config('marketplace.ikas.base_url');
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
            $payload = $this->graphQl($store, <<<'GRAPHQL'
                query ZolmIkasMerchant {
                  getMerchant {
                    id
                    merchantName
                    storeName
                    email
                    phoneNumber
                    region
                  }
                }
            GRAPHQL);

            $merchant = data_get($payload, 'data.getMerchant');

            if (! is_array($merchant) || blank($merchant['id'] ?? null)) {
                return [
                    'ok' => false,
                    'message' => 'ikas mağaza bilgisi doğrulanamadı.',
                ];
            }

            return [
                'ok' => true,
                'message' => 'ikas Admin API bağlantısı doğrulandı.',
                'meta' => [
                    'merchant_id' => $merchant['id'],
                    'merchant_name' => $merchant['merchantName'] ?? null,
                    'store_name' => $merchant['storeName'] ?? null,
                    'region' => $merchant['region'] ?? null,
                ],
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'message' => 'ikas bağlantısı doğrulanamadı: '.$exception->getMessage(),
            ];
        }
    }

    public function pullOrders(MarketplaceStore $store, array $options = []): array
    {
        $pageSize = min(200, max(1, (int) ($options['page_size'] ?? config('marketplace.ikas.order_page_size', 100))));
        $maxPages = max(1, (int) ($options['max_pages'] ?? config('marketplace.ikas.max_pages_per_sync', 50)));
        $page = max(1, (int) ($options['page'] ?? 1));
        $items = [];
        $lastPage = $page;
        $pagesFetched = 0;

        do {
            $payload = $this->graphQl($store, $this->ordersQuery(), [
                'pagination' => ['limit' => $pageSize, 'page' => $page],
                'updatedAt' => $this->dateFilter($options),
                'orderNumber' => filled($options['order_number'] ?? null)
                    ? ['eq' => trim((string) $options['order_number'])]
                    : null,
                'status' => $this->statusFilter($options),
                'sort' => '-updatedAt',
            ]);

            $connection = data_get($payload, 'data.listOrder', []);
            $orders = collect(data_get($connection, 'data', []))
                ->filter(fn ($row) => is_array($row))
                ->values();

            foreach ($orders as $order) {
                foreach ($this->normalizeOrder($order) as $normalized) {
                    $items[] = $normalized;
                }
            }

            $hasNext = (bool) data_get($connection, 'hasNext', false);
            $lastPage = $page;
            $pagesFetched++;
            $page++;
        } while ($hasNext && $pagesFetched < $maxPages);

        return [
            'items' => $items,
            'meta' => [
                'items_received' => count($items),
                'page' => $lastPage,
                'cursor_after' => now()->toIso8601String(),
                'page_size' => $pageSize,
            ],
        ];
    }

    public function pullProducts(MarketplaceStore $store, array $options = []): array
    {
        $pageSize = min(200, max(1, (int) ($options['page_size'] ?? config('marketplace.ikas.product_page_size', 100))));
        $maxPages = max(1, (int) ($options['max_pages'] ?? config('marketplace.ikas.max_pages_per_sync', 50)));
        $page = max(1, (int) ($options['page'] ?? 1));
        $items = [];
        $lastPage = $page;
        $pagesFetched = 0;

        do {
            $payload = $this->graphQl($store, $this->productsQuery(), [
                'pagination' => ['limit' => $pageSize, 'page' => $page],
                'search' => filled($options['search'] ?? null) ? trim((string) $options['search']) : null,
                'sku' => filled($options['sku'] ?? null) ? ['eq' => trim((string) $options['sku'])] : null,
                'includeDeleted' => (bool) ($options['include_deleted'] ?? false),
                'sort' => '-updatedAt',
            ]);

            $connection = data_get($payload, 'data.listProduct', []);
            $products = collect(data_get($connection, 'data', []))
                ->filter(fn ($row) => is_array($row))
                ->values();

            foreach ($products as $product) {
                foreach ($this->normalizeProduct($product, $store) as $normalized) {
                    $items[] = $normalized;
                }
            }

            $hasNext = (bool) data_get($connection, 'hasNext', false);
            $lastPage = $page;
            $pagesFetched++;
            $page++;
        } while ($hasNext && $pagesFetched < $maxPages);

        return [
            'items' => $items,
            'meta' => [
                'items_received' => count($items),
                'page' => $lastPage,
                'cursor_after' => now()->toIso8601String(),
                'page_size' => $pageSize,
            ],
        ];
    }

    public function pullFinancialEvents(MarketplaceStore $store, array $options = []): array
    {
        $orderLimit = max(1, (int) ($options['order_limit'] ?? config('marketplace.ikas.finance_order_limit', 100)));
        $orderReferences = $this->pullOrderReferences($store, $options, $orderLimit);
        $items = [];

        foreach ($orderReferences as $reference) {
            $payload = $this->graphQl($store, <<<'GRAPHQL'
                query ZolmIkasOrderTransactions($orderId: String!, $includeAll: Boolean) {
                  listOrderTransactions(orderId: $orderId, includeAll: $includeAll) {
                    id
                    orderId
                    checkoutId
                    customerId
                    amount
                    currencyCode
                    currencySymbol
                    type
                    status
                    paymentMethod
                    paymentGatewayId
                    paymentGatewayCode
                    paymentGatewayName
                    gatewayReferenceId
                    authCode
                    refundReason
                    createdAt
                    processedAt
                    updatedAt
                    deleted
                    error { code declineCode message }
                    paymentMethodDetail {
                      bankName
                      binNumber
                      cardAssociation
                      cardFamily
                      cardType
                      lastFourDigits
                      paymentMethodName
                      threeDSecure
                      installment { installmentCount installmentPrice originalRate rate totalPrice }
                    }
                    lineItems {
                      id
                      quantity
                      price
                      finalPrice
                      taxValue
                      variant { id productId sku name slug mainImageId type }
                    }
                  }
                }
            GRAPHQL, [
                'orderId' => $reference['id'],
                'includeAll' => true,
            ]);

            foreach (collect(data_get($payload, 'data.listOrderTransactions', []))->filter(fn ($row) => is_array($row)) as $transaction) {
                $event = $this->normalizeFinancialEvent($transaction, $reference);

                if ($event !== null) {
                    $items[] = $event;
                }
            }
        }

        return [
            'items' => $items,
            'meta' => [
                'items_received' => count($items),
                'orders_scanned' => count($orderReferences),
                'cursor_after' => now()->toIso8601String(),
                'finance_mode' => 'order_transactions',
            ],
        ];
    }

    public function pullClaims(MarketplaceStore $store, array $options = []): array
    {
        $result = $this->pullOrders($store, array_merge($options, [
            'status_in' => $options['status_in'] ?? [
                'REFUND_REQUESTED',
                'PARTIALLY_REFUNDED',
                'REFUNDED',
                'REFUND_REJECTED',
            ],
        ]));

        $claims = collect($result['items'])
            ->map(fn (array $item) => data_get($item, 'order.raw_payload'))
            ->filter(fn ($row) => is_array($row))
            ->unique(fn (array $row) => (string) data_get($row, 'id'))
            ->map(fn (array $order) => $this->normalizeClaim($order))
            ->values()
            ->all();

        return [
            'items' => $claims,
            'meta' => [
                'items_received' => count($claims),
                'cursor_after' => data_get($result, 'meta.cursor_after'),
                'mode' => 'order_refund_status',
            ],
        ];
    }

    public function pushPrice(ChannelListing $listing, float $price, array $context = []): array
    {
        $listing->loadMissing(['store.connection', 'channelProduct']);
        $productId = $this->resolveProductId($listing);
        $variantId = $this->resolveVariantId($listing);
        $priceListId = $context['price_list_id']
            ?? data_get($listing->channelProduct?->raw_payload, 'variant.prices.0.priceListId');
        $currency = strtoupper((string) ($context['currency'] ?? $listing->currency ?? $listing->store?->currency ?? 'TRY'));
        $variables = [
            'input' => [
                'priceListId' => $priceListId,
                'variantPriceInputs' => [[
                    'deleted' => false,
                    'productId' => $productId,
                    'variantId' => $variantId,
                    'price' => array_filter([
                        'sellPrice' => round($price, 2),
                        'discountPrice' => isset($context['discount_price']) ? round((float) $context['discount_price'], 2) : null,
                        'currency' => $currency,
                    ], fn ($value) => $value !== null && $value !== ''),
                ]],
            ],
        ];

        try {
            $payload = $this->graphQl($listing->store, <<<'GRAPHQL'
                mutation ZolmIkasUpdateVariantPrices($input: UpdateVariantPricesInput!) {
                  updateVariantPrices(input: $input) {
                    isSuccess
                    errorInputs { priceListId productId variantId }
                  }
                }
            GRAPHQL, $variables);
            $success = (bool) data_get($payload, 'data.updateVariantPrices.isSuccess');
            $errors = data_get($payload, 'data.updateVariantPrices.errorInputs', []);
        } catch (\RuntimeException $exception) {
            if (! Str::contains($exception->getMessage(), ['updateVariantPrices', 'UpdateVariantPricesInput'])) {
                throw $exception;
            }

            $legacyVariables = $variables;
            unset($legacyVariables['input']['variantPriceInputs'][0]['price']['currency']);
            $payload = $this->graphQl($listing->store, <<<'GRAPHQL'
                mutation ZolmIkasSaveVariantPrices($input: SaveVariantPricesInput!) {
                  saveVariantPrices(input: $input)
                }
            GRAPHQL, $legacyVariables);
            $success = (bool) data_get($payload, 'data.saveVariantPrices');
            $errors = [];
        }

        if (! $success || $errors !== []) {
            throw new \RuntimeException('ikas fiyat güncellemesi kabul edilmedi: '.json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        return [
            'status' => 'completed',
            'provider' => $this->providerKey(),
            'listing_id' => $listing->id,
            'product_id' => $productId,
            'variant_id' => $variantId,
            'price' => round($price, 2),
            'external_action_id' => $variantId,
            'response' => $payload,
        ];
    }

    public function pushStock(ChannelListing $listing, int $quantity, array $context = []): array
    {
        $listing->loadMissing(['store.connection', 'channelProduct']);
        $productId = $this->resolveProductId($listing);
        $variantId = $this->resolveVariantId($listing);
        $stockLocationId = trim((string) (
            $context['stock_location_id']
            ?? data_get($listing->channelProduct?->raw_payload, 'variant.stocks.0.stockLocationId')
        ));

        if ($stockLocationId === '') {
            throw new \RuntimeException('ikas stok güncellemesi için stok lokasyonu zorunludur. Önce ürün senkronu çalıştırın veya stock_location_id gönderin.');
        }

        $variables = [
            'input' => [
                'stockInputs' => [[
                    'deleted' => false,
                    'productId' => $productId,
                    'variantId' => $variantId,
                    'stockLocationId' => $stockLocationId,
                    'stockCount' => max(0, $quantity),
                ]],
            ],
        ];

        try {
            $payload = $this->graphQl($listing->store, <<<'GRAPHQL'
                mutation ZolmIkasSaveVariantStocks($input: SaveVariantStocksInput!) {
                  saveVariantStocks(input: $input) {
                    isSuccess
                    errorInputs { variantId productId }
                  }
                }
            GRAPHQL, $variables);
            $success = (bool) data_get($payload, 'data.saveVariantStocks.isSuccess');
            $errors = data_get($payload, 'data.saveVariantStocks.errorInputs', []);
        } catch (\RuntimeException $exception) {
            if (! Str::contains($exception->getMessage(), ['saveVariantStocks', 'SaveVariantStocksInput'])) {
                throw $exception;
            }

            $payload = $this->graphQl($listing->store, <<<'GRAPHQL'
                mutation ZolmIkasSaveProductStockLocations($input: SaveStockLocationsInput!) {
                  saveProductStockLocations(input: $input)
                }
            GRAPHQL, [
                'input' => [
                    'productStockLocationInputs' => $variables['input']['stockInputs'],
                ],
            ]);
            $success = (bool) data_get($payload, 'data.saveProductStockLocations');
            $errors = [];
        }

        if (! $success || $errors !== []) {
            throw new \RuntimeException('ikas stok güncellemesi kabul edilmedi: '.json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        return [
            'status' => 'completed',
            'provider' => $this->providerKey(),
            'listing_id' => $listing->id,
            'product_id' => $productId,
            'variant_id' => $variantId,
            'stock_location_id' => $stockLocationId,
            'quantity' => max(0, $quantity),
            'external_action_id' => $variantId.'|'.$stockLocationId,
            'response' => $payload,
        ];
    }

    public function verifyWebhookSignature(Request $request, ?IntegrationConnection $connection): bool
    {
        if (! $connection) {
            return false;
        }

        $payload = $request->json()->all();
        $data = data_get($payload, 'data');
        $signature = data_get($payload, 'signature');
        $credentials = $connection->credentials_encrypted ?? [];
        // ikas resmi webhook imzası özel uygulamanın Client Secret değeriyle üretilir.
        $secret = (string) (($credentials['client_secret'] ?? null) ?: ($credentials['api_secret'] ?? null) ?: $connection->webhook_secret);

        if (! is_string($data) || $data === '' || ! is_string($signature) || $signature === '' || $secret === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $data, $secret), $signature);
    }

    public function extractWebhookMetadata(Request $request): array
    {
        $envelope = $request->json()->all();
        $decoded = json_decode((string) data_get($envelope, 'data', ''), true);

        return [
            'event_type' => data_get($envelope, 'scope'),
            'external_event_id' => data_get($envelope, 'id'),
            'payload' => [
                'envelope' => Arr::except($envelope, ['signature']),
                'data' => is_array($decoded) ? $decoded : [],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    protected function graphQl(MarketplaceStore $store, string $query, array $variables = []): array
    {
        $store->loadMissing('connection');
        $connection = $store->connection;

        if (! $connection) {
            throw new \RuntimeException('ikas bağlantı kaydı bulunamadı.');
        }

        $tokenKey = $this->tokenCacheKey($connection);
        $response = $this->sendGraphQl($connection, $this->accessToken($connection), $query, $variables);

        if ($response->status() === 401) {
            Cache::forget($tokenKey);
            $response = $this->sendGraphQl($connection, $this->accessToken($connection), $query, $variables);
        }

        $response->throw();
        $payload = $response->json();

        if (! is_array($payload)) {
            throw new \RuntimeException('ikas GraphQL cevabı beklenen formatta değil.');
        }

        if (filled(data_get($payload, 'errors'))) {
            throw new \RuntimeException('ikas GraphQL hatası: '.json_encode(data_get($payload, 'errors'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        return $payload;
    }

    protected function sendGraphQl(IntegrationConnection $connection, string $token, string $query, array $variables): Response
    {
        $url = rtrim((string) ($connection->api_base_url ?: $this->defaultApiBaseUrl()), '/');

        return Http::acceptJson()
            ->withToken($token)
            ->timeout((int) config('marketplace.ikas.timeout_seconds', 30))
            ->post($url, [
                'query' => $query,
                'variables' => array_filter($variables, fn ($value) => $value !== null),
            ]);
    }

    protected function accessToken(IntegrationConnection $connection): string
    {
        $credentials = $connection->credentials_encrypted ?? [];
        $clientId = trim((string) ($credentials['client_id'] ?? $credentials['api_key'] ?? ''));
        $clientSecret = trim((string) ($credentials['client_secret'] ?? $credentials['api_secret'] ?? ''));

        if ($clientId === '' || $clientSecret === '') {
            throw new \RuntimeException('ikas Client ID ve Client Secret zorunludur.');
        }

        $cacheKey = $this->tokenCacheKey($connection);
        $cachedToken = Cache::get($cacheKey);

        if (is_string($cachedToken) && $cachedToken !== '') {
            return $cachedToken;
        }

        $response = Http::asForm()
            ->acceptJson()
            ->timeout((int) config('marketplace.ikas.timeout_seconds', 30))
            ->post((string) config('marketplace.ikas.oauth_url'), [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);

        $response->throw();
        $token = trim((string) data_get($response->json(), 'access_token'));

        if ($token === '') {
            throw new \RuntimeException('ikas OAuth cevabında access_token bulunamadı.');
        }

        $expiresIn = max(120, (int) data_get($response->json(), 'expires_in', 14400));
        Cache::put($cacheKey, $token, now()->addSeconds($expiresIn - 60));

        return $token;
    }

    protected function tokenCacheKey(IntegrationConnection $connection): string
    {
        $credentials = $connection->credentials_encrypted ?? [];
        $clientId = (string) ($credentials['client_id'] ?? $credentials['api_key'] ?? '');

        return 'marketplace:ikas:token:'.sha1(($connection->id ?: 'draft').'|'.$clientId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeOrder(array $payload): array
    {
        $packages = collect(Arr::wrap(data_get($payload, 'orderPackages')))
            ->filter(fn ($row) => is_array($row) && ! data_get($row, 'deleted', false))
            ->values();

        if ($packages->isEmpty()) {
            $packages = collect([[]]);
        }

        return $packages->map(function (array $package, int $packageIndex) use ($payload): array {
            $externalOrderId = (string) data_get($payload, 'id');
            $orderNumber = (string) (data_get($payload, 'orderNumber') ?: $externalOrderId);
            $packageId = (string) (data_get($package, 'id') ?: $externalOrderId);
            $packageLineIds = collect(Arr::wrap(data_get($package, 'orderLineItemIds')))
                ->map(fn ($id) => (string) $id)
                ->filter()
                ->all();
            $allLines = collect(Arr::wrap(data_get($payload, 'orderLineItems')))
                ->filter(fn ($row) => is_array($row));
            $lines = $packageLineIds !== []
                ? $allLines->filter(fn (array $line) => in_array((string) data_get($line, 'id'), $packageLineIds, true))
                : ($packageIndex === 0 ? $allLines : collect());
            $shipping = data_get($payload, 'shippingAddress', []);
            $billing = data_get($payload, 'billingAddress', []);
            $tracking = data_get($package, 'trackingInfo', []);
            $status = $this->normalizeOrderStatus($payload, $package);

            return [
                'order' => [
                    'external_order_id' => $externalOrderId,
                    'order_number' => $orderNumber,
                    'order_status' => $status,
                    'commercial_type' => filled(data_get($billing, 'company')) || filled(data_get($billing, 'taxNumber')) ? 'commercial' : 'individual',
                    'currency' => strtoupper((string) (data_get($payload, 'currencyCode') ?: 'TRY')),
                    'exchange_rate' => data_get($payload, 'currencyRates.0.rate') ?: 1,
                    'customer_name' => data_get($payload, 'customer.fullName') ?: trim((string) (data_get($payload, 'customer.firstName').' '.data_get($payload, 'customer.lastName'))),
                    'customer_email' => data_get($payload, 'customer.email'),
                    'customer_phone' => data_get($payload, 'customer.phone') ?: data_get($shipping, 'phone') ?: data_get($billing, 'phone'),
                    'billing_name' => data_get($billing, 'company') ?: trim((string) (data_get($billing, 'firstName').' '.data_get($billing, 'lastName'))),
                    'billing_tax_number' => data_get($billing, 'taxNumber') ?: data_get($billing, 'identityNumber'),
                    'shipment_country' => data_get($shipping, 'country.iso2') ?: data_get($shipping, 'country.code'),
                    'shipment_city' => data_get($shipping, 'city.name') ?: data_get($shipping, 'state.name'),
                    'shipment_district' => data_get($shipping, 'district.name'),
                    'ordered_at' => $this->normalizeDate(data_get($payload, 'orderedAt') ?: data_get($payload, 'createdAt')),
                    'approved_at' => Str::contains(Str::lower((string) data_get($payload, 'orderPaymentStatus')), 'paid') ? $this->normalizeDate(data_get($payload, 'updatedAt')) : null,
                    'delivered_at' => $status === 'delivered' ? $this->normalizeDate(data_get($package, 'updatedAt') ?: data_get($payload, 'updatedAt')) : null,
                    'cancelled_at' => $status === 'cancelled' ? $this->normalizeDate(data_get($payload, 'cancelledAt') ?: data_get($payload, 'updatedAt')) : null,
                    'returned_at' => $status === 'returned' ? $this->normalizeDate(data_get($payload, 'updatedAt')) : null,
                    'raw_payload' => $payload,
                ],
                'package' => [
                    'external_package_id' => $packageId,
                    'package_number' => (string) (data_get($package, 'orderPackageNumber') ?: $packageId),
                    'package_status' => $status,
                    'cargo_company' => data_get($tracking, 'cargoCompany'),
                    'cargo_tracking_number' => data_get($tracking, 'trackingNumber'),
                    'cargo_barcode' => data_get($tracking, 'barcode'),
                    'shipment_provider' => data_get($tracking, 'cargoCompany'),
                    'shipped_at' => in_array($status, ['shipped', 'delivered'], true) ? $this->normalizeDate(data_get($package, 'updatedAt')) : null,
                    'delivered_at' => $status === 'delivered' ? $this->normalizeDate(data_get($package, 'updatedAt')) : null,
                    'raw_payload' => $package !== [] ? $package : ['order_id' => $externalOrderId],
                ],
                'items' => $lines
                    ->values()
                    ->map(fn (array $line, int $index) => $this->normalizeOrderLine($line, $externalOrderId, $index, $status))
                    ->all(),
            ];
        })->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeProduct(array $payload, MarketplaceStore $store): array
    {
        $productId = (string) data_get($payload, 'id');
        $productName = (string) data_get($payload, 'name');
        $categories = collect(Arr::wrap(data_get($payload, 'categories')))->pluck('name')->filter()->values();
        $images = collect(Arr::wrap(data_get($payload, 'variants')))
            ->flatMap(fn ($variant) => Arr::wrap(data_get($variant, 'images')))
            ->filter(fn ($image) => is_array($image))
            ->unique('imageId')
            ->values()
            ->all();

        return collect(Arr::wrap(data_get($payload, 'variants')))
            ->filter(fn ($row) => is_array($row) && ! data_get($row, 'deleted', false))
            ->map(function (array $variant) use ($payload, $store, $productId, $productName, $categories, $images): array {
                $variantId = (string) data_get($variant, 'id');
                $variantValues = collect(Arr::wrap(data_get($variant, 'variantValueIds')))
                    ->map(fn ($row) => is_array($row) ? (data_get($row, 'variantValueName') ?: data_get($row, 'variantValueId')) : $row)
                    ->filter()
                    ->implode(' / ');
                $title = $variantValues !== '' ? $productName.' / '.$variantValues : $productName;
                $prices = collect(Arr::wrap(data_get($variant, 'prices')))->filter(fn ($row) => is_array($row));
                $price = $prices->first() ?: [];
                $stock = collect(Arr::wrap(data_get($variant, 'stocks')))
                    ->filter(fn ($row) => is_array($row) && ! data_get($row, 'deleted', false))
                    ->sum(fn ($row) => (float) data_get($row, 'stockCount', 0));
                $salesChannelStatuses = collect(Arr::wrap(data_get($payload, 'salesChannels')))->pluck('status')->filter();
                $isActive = (bool) data_get($variant, 'isActive', false)
                    && ! (bool) data_get($payload, 'deleted', false)
                    && ! $salesChannelStatuses->contains(fn ($status) => Str::upper((string) $status) === 'PASSIVE');

                return [
                    'product' => [
                        'external_product_id' => $variantId,
                        'external_parent_id' => $productId,
                        'stock_code' => (string) (data_get($variant, 'sku') ?: $variantId),
                        'barcode' => collect(Arr::wrap(data_get($variant, 'barcodeList')))->filter()->first(),
                        'title' => $title,
                        'brand' => data_get($payload, 'brand.name'),
                        'category_name' => $categories->implode(' > ') ?: null,
                        'vat_rate' => data_get($variant, 'taxValue'),
                        'description' => data_get($payload, 'description'),
                        'images' => $images,
                        'attributes' => [
                            'product_attributes' => data_get($payload, 'attributes', []),
                            'variant_attributes' => data_get($variant, 'attributes', []),
                            'variant_values' => data_get($variant, 'variantValueIds', []),
                            'translations' => data_get($payload, 'translations', []),
                            'metadata' => data_get($payload, 'metaData'),
                        ],
                        'approval_status' => $isActive ? 'approved' : 'passive',
                        'is_catalog_product' => true,
                        'raw_payload' => array_merge($payload, ['variant' => $variant]),
                    ],
                    'listing' => [
                        'listing_id' => $variantId,
                        'listing_status' => $isActive ? 'active' : 'passive',
                        'sale_price' => $this->money(data_get($price, 'discountPrice') ?? data_get($price, 'sellPrice')),
                        'list_price' => $this->money(data_get($price, 'sellPrice')),
                        'currency' => strtoupper((string) (data_get($price, 'currencyCode') ?: data_get($price, 'currency') ?: $store->currency ?: 'TRY')),
                        'stock_quantity' => (int) round($stock),
                        'published_at' => $this->normalizeDate(data_get($payload, 'releaseDate') ?: data_get($payload, 'createdAt')),
                    ],
                ];
            })
            ->values()
            ->all();
    }

    protected function normalizeOrderLine(array $payload, string $orderId, int $index, string $status): array
    {
        $quantity = max(1, (int) data_get($payload, 'quantity', 1));
        $unitPrice = $this->money(data_get($payload, 'unitPrice') ?? data_get($payload, 'price'));
        $gross = $this->money(data_get($payload, 'price'));
        $final = $this->money(data_get($payload, 'finalPrice')) ?? $gross;

        return [
            'external_line_id' => (string) (data_get($payload, 'id') ?: sha1($orderId.'|'.$index)),
            'stock_code' => (string) (data_get($payload, 'variant.sku') ?: data_get($payload, 'variant.id') ?: ''),
            'barcode' => collect(Arr::wrap(data_get($payload, 'variant.barcodeList')))->filter()->first(),
            'product_name' => data_get($payload, 'variant.name'),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'gross_amount' => $gross,
            'discount_amount' => $gross !== null && $final !== null ? max(0, round($gross - $final, 2)) : $this->money(data_get($payload, 'discountPrice')),
            'marketplace_discount_amount' => null,
            'billable_amount' => $final,
            'commission_rate' => null,
            'vat_rate' => data_get($payload, 'taxValue'),
            'line_status' => Str::lower((string) (data_get($payload, 'status') ?: $status)),
            'raw_payload' => $payload,
        ];
    }

    protected function normalizeClaim(array $order): array
    {
        $status = (string) data_get($order, 'status', 'REFUND_REQUESTED');
        $tracking = collect(Arr::wrap(data_get($order, 'orderPackages')))
            ->pluck('trackingInfo')
            ->filter(fn ($row) => is_array($row))
            ->first() ?: [];

        return [
            'external_claim_id' => (string) data_get($order, 'id').'|'.$status,
            'order_number' => (string) (data_get($order, 'orderNumber') ?: data_get($order, 'id')),
            'cargo_tracking_number' => data_get($tracking, 'trackingNumber'),
            'cargo_provider' => data_get($tracking, 'cargoCompany'),
            'status' => Str::lower($status),
            'type' => 'return',
            'reason' => data_get($order, 'cancelReason'),
            'reason_detail' => collect(Arr::wrap(data_get($order, 'orderPackages')))->pluck('refundReasonId')->filter()->unique()->implode(', ') ?: null,
            'customer_note' => data_get($order, 'note'),
            'customer_name' => data_get($order, 'customer.fullName'),
            'created_date' => $this->normalizeDate(data_get($order, 'updatedAt') ?: data_get($order, 'createdAt')),
            'items' => collect(Arr::wrap(data_get($order, 'orderLineItems')))
                ->filter(fn ($row) => is_array($row))
                ->map(fn (array $row) => [
                    'external_item_id' => (string) data_get($row, 'id'),
                    'external_order_line_id' => (string) data_get($row, 'id'),
                    'product_name' => data_get($row, 'variant.name'),
                    'stock_code' => data_get($row, 'variant.sku'),
                    'barcode' => collect(Arr::wrap(data_get($row, 'variant.barcodeList')))->filter()->first(),
                    'quantity' => (int) data_get($row, 'quantity', 1),
                    'reason' => data_get($order, 'cancelReason'),
                    'customer_note' => data_get($order, 'note'),
                    'raw_payload' => $row,
                ])->values()->all(),
            'raw_payload' => $order,
        ];
    }

    /**
     * @param  array<string, mixed>  $reference
     * @return array<string, mixed>|null
     */
    protected function normalizeFinancialEvent(array $transaction, array $reference): ?array
    {
        $amount = $this->money(data_get($transaction, 'amount'));

        if ($amount === null || $amount === 0.0) {
            return null;
        }

        $type = Str::lower((string) data_get($transaction, 'type', 'payment'));
        $processedAt = $this->normalizeDate(data_get($transaction, 'processedAt') ?: data_get($transaction, 'createdAt'));

        return [
            'event_source' => 'ikas_order_transaction',
            'external_event_id' => (string) data_get($transaction, 'id'),
            'order_number' => (string) $reference['order_number'],
            'external_package_id' => (string) $reference['id'],
            'external_line_id' => null,
            'stock_code' => null,
            'barcode' => null,
            'event_type' => $type !== '' ? $type : 'payment',
            'reference_number' => (string) (data_get($transaction, 'gatewayReferenceId') ?: data_get($transaction, 'authCode') ?: data_get($transaction, 'id')),
            'event_date' => $processedAt,
            'due_date' => null,
            'settlement_date' => $processedAt,
            'amount' => abs($amount),
            'currency' => strtoupper((string) (data_get($transaction, 'currencyCode') ?: 'TRY')),
            'direction' => Str::contains($type, ['refund', 'void', 'cancel']) ? 'debit' : 'credit',
            'status' => Str::lower((string) (data_get($transaction, 'status') ?: 'posted')),
            'notes' => collect([
                data_get($transaction, 'paymentGatewayName'),
                data_get($transaction, 'paymentMethod'),
                data_get($transaction, 'refundReason'),
                data_get($transaction, 'error.message'),
            ])->filter()->implode(' | ') ?: null,
            'raw_payload' => $transaction,
        ];
    }

    /**
     * @return array<int, array{id: string, order_number: string}>
     */
    protected function pullOrderReferences(MarketplaceStore $store, array $options, int $limit): array
    {
        $pageSize = min(200, $limit);
        $references = [];
        $page = 1;

        do {
            $payload = $this->graphQl($store, <<<'GRAPHQL'
                query ZolmIkasFinanceOrders($pagination: PaginationInput, $updatedAt: DateFilterInput, $orderNumber: StringFilterInput, $sort: String) {
                  listOrder(pagination: $pagination, updatedAt: $updatedAt, orderNumber: $orderNumber, sort: $sort) {
                    hasNext
                    data { id orderNumber }
                  }
                }
            GRAPHQL, [
                'pagination' => ['limit' => min($pageSize, $limit - count($references)), 'page' => $page],
                'updatedAt' => $this->dateFilter($options),
                'orderNumber' => filled($options['order_number'] ?? null) ? ['eq' => trim((string) $options['order_number'])] : null,
                'sort' => '-updatedAt',
            ]);

            $connection = data_get($payload, 'data.listOrder', []);

            foreach (collect(data_get($connection, 'data', []))->filter(fn ($row) => is_array($row)) as $row) {
                $id = trim((string) data_get($row, 'id'));

                if ($id !== '') {
                    $references[] = [
                        'id' => $id,
                        'order_number' => (string) (data_get($row, 'orderNumber') ?: $id),
                    ];
                }

                if (count($references) >= $limit) {
                    break 2;
                }
            }

            $hasNext = (bool) data_get($connection, 'hasNext', false);
            $page++;
        } while ($hasNext);

        return $references;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function dateFilter(array $options): ?array
    {
        $filter = array_filter([
            'gte' => $this->timestampInput($options['start_date'] ?? null),
            'lte' => $this->timestampInput($options['end_date'] ?? null),
        ], fn ($value) => $value !== null);

        return $filter !== [] ? $filter : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function statusFilter(array $options): ?array
    {
        if (filled($options['status'] ?? null)) {
            return ['eq' => Str::upper(trim((string) $options['status']))];
        }

        $statuses = collect(Arr::wrap($options['status_in'] ?? []))
            ->map(fn ($status) => Str::upper(trim((string) $status)))
            ->filter()
            ->values()
            ->all();

        return $statuses !== [] ? ['in' => $statuses] : null;
    }

    protected function normalizeOrderStatus(array $order, array $package = []): string
    {
        $orderStatus = Str::upper((string) data_get($order, 'status'));
        $packageStatus = Str::upper((string) (data_get($package, 'orderPackageFulfillStatus') ?: data_get($order, 'orderPackageStatus')));

        return match (true) {
            Str::contains($orderStatus, 'REFUND') => 'returned',
            Str::contains($orderStatus, 'CANCEL') => 'cancelled',
            Str::contains($packageStatus, 'DELIVER') => 'delivered',
            Str::contains($packageStatus, ['SHIP', 'FULFILL']) => 'shipped',
            Str::contains(Str::upper((string) data_get($order, 'orderPaymentStatus')), 'PAID') => 'approved',
            default => 'new',
        };
    }

    protected function timestampInput(mixed $value): ?int
    {
        if (blank($value)) {
            return null;
        }

        return CarbonImmutable::parse((string) $value)->getTimestampMs();
    }

    protected function normalizeDate(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        if (is_numeric($value)) {
            $numeric = (float) $value;
            $seconds = $numeric > 9999999999 ? $numeric / 1000 : $numeric;

            return CarbonImmutable::createFromTimestamp($seconds)->toIso8601String();
        }

        return CarbonImmutable::parse((string) $value)->toIso8601String();
    }

    protected function money(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) str_replace(',', '.', (string) $value), 2);
    }

    protected function resolveProductId(ChannelListing $listing): string
    {
        $productId = trim((string) (
            data_get($listing->channelProduct?->raw_payload, 'id')
            ?: data_get($listing->channelProduct?->raw_payload, 'variant.productId')
            ?: $listing->channelProduct?->external_parent_id
        ));

        if ($productId === '') {
            throw new \RuntimeException('ikas fiyat/stok güncellemesi için product ID zorunludur.');
        }

        return $productId;
    }

    protected function resolveVariantId(ChannelListing $listing): string
    {
        $variantId = trim((string) (
            data_get($listing->channelProduct?->raw_payload, 'variant.id')
            ?: $listing->listing_id
            ?: $listing->channelProduct?->external_product_id
        ));

        if ($variantId === '') {
            throw new \RuntimeException('ikas fiyat/stok güncellemesi için variant ID zorunludur.');
        }

        return $variantId;
    }

    protected function ordersQuery(): string
    {
        return <<<'GRAPHQL'
            query ZolmIkasOrders($pagination: PaginationInput, $updatedAt: DateFilterInput, $orderNumber: StringFilterInput, $status: OrderStatusEnumInputFilter, $sort: String) {
              listOrder(pagination: $pagination, updatedAt: $updatedAt, orderNumber: $orderNumber, status: $status, sort: $sort) {
                count
                hasNext
                limit
                page
                data {
                  id
                  orderNumber
                  orderSequence
                  status
                  orderPaymentStatus
                  orderPackageStatus
                  orderedAt
                  createdAt
                  updatedAt
                  cancelledAt
                  dueDate
                  totalPrice
                  totalFinalPrice
                  netTotalFinalPrice
                  currencyCode
                  currencySymbol
                  currencyRates { code originalRate rate }
                  note
                  couponCode
                  itemCount
                  cancelReason
                  archived
                  edited
                  sourceId
                  salesChannel { id name type }
                  stockLocation { id name }
                  customer {
                    id firstName lastName fullName email phone preferredLanguage
                    isGuestCheckout notificationsAccepted
                  }
                  billingAddress {
                    id firstName lastName company phone identityNumber taxNumber taxOffice postalCode
                    addressLine1 addressLine2
                    city { id code name }
                    district { id code name }
                    state { id code name }
                    country { id code iso2 iso3 name }
                  }
                  shippingAddress {
                    id firstName lastName company phone identityNumber taxNumber taxOffice postalCode
                    addressLine1 addressLine2
                    city { id code name }
                    district { id code name }
                    state { id code name }
                    country { id code iso2 iso3 name }
                  }
                  paymentMethods {
                    type price paymentGatewayId paymentGatewayCode paymentGatewayName isAlternativeGateway
                  }
                  taxLines { price rate }
                  shippingLines {
                    title price finalPrice taxValue isRefunded cargoCompanyId paymentMethod
                    shippingSettingsId shippingZoneRateId transactionId
                  }
                  invoices { id invoiceNumber type appId appName storeAppId createdAt hasPdf invoiceData }
                  orderAdjustments {
                    name amount amountType type order transactionId campaignId campaignType couponId createdFor usedLoyaltyPoints
                    appliedOrderLines { orderLineId amount appliedQuantity isAutoCreated }
                  }
                  orderLineItems {
                    id createdAt updatedAt deleted status statusUpdatedAt quantity
                    price unitPrice finalPrice finalUnitPrice discountPrice taxValue
                    currencyCode currencySymbol stockLocationId sourceId edited originalOrderLineItemId
                    discount { amount amountType reason maxApplicableQuantity campaignOfferId campaignOfferProductId productVolumeDiscountId }
                    variant {
                      id productId sku name slug hsCode mainImageId type weight taxValue
                      barcodeList
                      brand { id name }
                      categories { id name categoryPath { id name } }
                      prices { buyPrice sellPrice discountPrice unitPrice currency currencySymbol priceListId }
                      tags { id name }
                      variantValues { order variantTypeId variantTypeName variantValueId variantValueName }
                    }
                  }
                  orderPackages {
                    id orderPackageNumber orderLineItemIds orderPackageFulfillStatus previousOrderPackageFulfillStatus
                    stockLocationId sourceId appId note errorMessage refundReasonId returnShippingMethod
                    createdAt updatedAt deleted
                    trackingInfo { barcode cargoCompany cargoCompanyId trackingNumber trackingLink shippingLabelImage isSendNotification }
                  }
                }
              }
            }
        GRAPHQL;
    }

    protected function productsQuery(): string
    {
        return <<<'GRAPHQL'
            query ZolmIkasProducts($pagination: PaginationInput, $search: String, $sku: StringFilterInput, $includeDeleted: Boolean, $sort: String) {
              listProduct(pagination: $pagination, search: $search, sku: $sku, includeDeleted: $includeDeleted, sort: $sort) {
                count
                hasNext
                limit
                page
                data {
                  id name description shortDescription type createdAt updatedAt releaseDate deleted
                  brandId brand { id name createdAt updatedAt deleted }
                  categoryIds categories { id name parentId createdAt updatedAt deleted }
                  tagIds tags { id name createdAt updatedAt deleted }
                  vendorId googleTaxonomyId maxQuantityPerCart totalStock weight
                  salesChannelIds hiddenSalesChannelIds dynamicPriceListIds
                  salesChannels { id status minQuantityPerCart maxQuantityPerCart quantitySettings productVolumeDiscountId }
                  attributes { productAttributeId productAttributeOptionId value imageIds }
                  translations { locale name description }
                  productVariantTypes { order variantTypeId variantValueIds }
                  metaData {
                    id pageTitle description slug redirectTo targetId targetType disableIndex canonicals
                    translations { locale pageTitle description slug }
                    metadataOverrides { language storefrontId storefrontRegionId pageTitle description }
                  }
                  variants {
                    id sku barcodeList isActive sellIfOutOfStock hsCode fileId weight
                    createdAt updatedAt deleted subscriptionPlanId
                    images { imageId fileName isMain isVideo order }
                    attributes { productAttributeId productAttributeOptionId value imageIds }
                    prices { priceListId buyPrice sellPrice discountPrice currency currencyCode currencySymbol }
                    stocks { id productId variantId stockLocationId stockCount createdAt updatedAt deleted }
                    variantValueIds { variantTypeId variantValueId }
                    unit { amount type }
                  }
                }
              }
            }
        GRAPHQL;
    }
}
