<?php

namespace App\Services\Marketplace\Connectors;

use App\Models\ChannelListing;
use App\Models\ChannelOrderPackage;
use App\Models\IntegrationConnection;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\Contracts\PullsFinancials;
use App\Services\Marketplace\Contracts\PullsOrders;
use App\Services\Marketplace\Contracts\PullsProducts;
use App\Services\Marketplace\Contracts\ManagesCommonLabels;
use App\Services\Marketplace\Contracts\PushesPrice;
use App\Services\Marketplace\Contracts\PushesStock;
use App\Services\Marketplace\Contracts\SendsInvoiceLinks;
use App\Services\Marketplace\Contracts\TestsConnection;
use App\Services\Marketplace\Contracts\UpdatesPackageStatus;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TrendyolConnector extends AbstractMarketplaceConnector implements PullsOrders, PullsProducts, PullsFinancials, PushesPrice, PushesStock, TestsConnection, UpdatesPackageStatus, ManagesCommonLabels, SendsInvoiceLinks
{
    public function providerKey(): string
    {
        return 'trendyol';
    }

    public function displayName(): string
    {
        return 'Trendyol';
    }

    public function defaultApiBaseUrl(): ?string
    {
        return config('marketplace.trendyol.base_url');
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
            'package_status' => true,
            'package_picking' => true,
            'package_invoiced' => true,
            'common_label' => true,
            'package_common_label_create' => true,
            'package_common_label_get' => true,
            'invoice_link' => true,
            'package_invoice_link' => true,
        ];
    }

    public function verifyWebhookSignature(Request $request, ?IntegrationConnection $connection): bool
    {
        $providedSignature = (string) (
            $request->header('X-Trendyol-Signature')
            ?: $request->header('X-Webhook-Signature')
            ?: $request->header('X-Signature')
        );

        if ($providedSignature !== '' && $connection?->webhook_secret) {
            $payload = $request->getContent();
            $expectedSha256 = hash_hmac('sha256', $payload, $connection->webhook_secret);

            if (hash_equals($expectedSha256, $providedSignature)) {
                return true;
            }
        }

        return parent::verifyWebhookSignature($request, $connection);
    }

    public function extractWebhookMetadata(Request $request): array
    {
        $payload = $request->json()->all();

        if ($payload === []) {
            $payload = $request->all();
        }

        return [
            'event_type' => $request->header('X-Webhook-Event')
                ?: $request->header('X-Event-Type')
                ?: data_get($payload, 'eventType')
                ?: data_get($payload, 'type'),
            'external_event_id' => (string) (
                $request->header('X-Webhook-Id')
                ?: $request->header('X-Event-Id')
                ?: data_get($payload, 'shipmentPackageId')
                ?: data_get($payload, 'shipmentPackage.shipmentPackageId')
                ?: data_get($payload, 'eventId')
                ?: data_get($payload, 'id')
                ?: data_get($payload, 'shipmentPackage.id')
                ?: data_get($payload, 'orderNumber')
            ),
            'payload' => is_array($payload) ? $payload : [],
        ];
    }

    public function pullOrders(MarketplaceStore $store, array $options = []): array
    {
        $items = [];
        $page = 0;
        $size = min((int) ($options['page_size'] ?? config('marketplace.trendyol.page_size', 200)), 200);
        $requestedStartDate = CarbonImmutable::parse($options['start_date'])->setTimezone('UTC');
        $endDate = CarbonImmutable::parse($options['end_date'])->setTimezone('UTC');
        [$startDate, $orderWindowMeta] = $this->resolveOrderWindow($requestedStartDate, $endDate);
        $requestedPackageIds = collect(Arr::wrap($options['shipment_package_ids'] ?? []))
            ->map(fn ($packageId) => trim((string) $packageId))
            ->filter()
            ->values()
            ->all();

        do {
            $response = $this->request($store)
                ->get("integration/order/sellers/{$this->sellerId($store)}/orders", array_filter([
                    'startDate' => $startDate->valueOf(),
                    'endDate' => $endDate->valueOf(),
                    'page' => $page,
                    'size' => $size,
                    'orderByField' => 'PackageLastModifiedDate',
                    'orderByDirection' => 'DESC',
                    'status' => $options['status'] ?? null,
                    'orderNumber' => $options['order_number'] ?? null,
                ]))
                ->throw()
                ->json();

            $content = Arr::get($response, 'content', []);

            foreach ($content as $packagePayload) {
                $normalizedPackage = $this->normalizeOrderPackage($packagePayload);

                if (
                    $requestedPackageIds !== []
                    && !in_array((string) data_get($normalizedPackage, 'package.external_package_id'), $requestedPackageIds, true)
                ) {
                    continue;
                }

                $items[] = $normalizedPackage;
            }

            $totalPages = (int) Arr::get($response, 'totalPages', 1);
            $page++;
        } while ($page < $totalPages);

        return [
            'items' => $items,
            'meta' => array_merge([
                'items_received' => count($items),
                'cursor_after' => $endDate->toIso8601String(),
            ], $orderWindowMeta),
        ];
    }

    public function pullProducts(MarketplaceStore $store, array $options = []): array
    {
        $items = [];
        $page = 0;
        $size = min((int) ($options['page_size'] ?? config('marketplace.trendyol.product_page_size', 100)), 100);
        $startDate = CarbonImmutable::parse($options['start_date'])->setTimezone('UTC');
        $endDate = CarbonImmutable::parse($options['end_date'])->setTimezone('UTC');
        $nextPageToken = null;
        $seenPageTokens = [];
        $status = $options['status'] ?? null;
        $maxPageCount = max(1, (int) ceil(10000 / max(1, $size)));
        $usingNextPageToken = false;

        if ($status === null && ($options['on_sale'] ?? null) === true) {
            $status = 'onSale';
        }

        while (true) {
            $query = array_filter([
                'startDate' => $startDate->valueOf(),
                'endDate' => $endDate->valueOf(),
                'page' => $usingNextPageToken ? null : $page,
                'size' => $size,
                'dateQueryType' => $options['date_query_type'] ?? 'CONTENT_MODIFIED_DATE',
                'supplierId' => $this->sellerId($store),
                'barcode' => $options['barcode'] ?? null,
                'stockCode' => $options['stock_code'] ?? null,
                'productMainId' => $options['product_main_id'] ?? null,
                'status' => $status,
                'nextPageToken' => $nextPageToken,
            ], fn ($value) => $value !== null && $value !== '');

            $response = $this->request($store)
                ->get("integration/product/sellers/{$this->sellerId($store)}/products/approved", $query)
                ->throw()
                ->json();

            $content = Arr::get($response, 'content', []);

            foreach ($content as $productPayload) {
                $items = array_merge($items, $this->normalizeApprovedProductContent($productPayload));
            }

            $returnedToken = Arr::get($response, 'nextPageToken');
            $totalPages = (int) Arr::get($response, 'totalPages', 1);
            $totalElements = (int) Arr::get($response, 'totalElements', 0);

            if ($usingNextPageToken) {
                if (
                    $totalElements > 10000
                    && filled($returnedToken)
                    && !isset($seenPageTokens[$returnedToken])
                ) {
                    $seenPageTokens[$returnedToken] = true;
                    $nextPageToken = (string) $returnedToken;

                    continue;
                }

                break;
            }

            $page++;

            if ($page < min($totalPages, $maxPageCount)) {
                continue;
            }

            if (
                $totalElements > 10000
                && filled($returnedToken)
                && !isset($seenPageTokens[$returnedToken])
            ) {
                $seenPageTokens[$returnedToken] = true;
                $nextPageToken = (string) $returnedToken;
                $usingNextPageToken = true;

                continue;
            }

            break;
        }

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
        $items = [];
        $startDate = CarbonImmutable::parse($options['start_date'])->setTimezone('UTC');
        $endDate = CarbonImmutable::parse($options['end_date'])->setTimezone('UTC');
        $requestedOrderNumber = trim((string) ($options['order_number'] ?? ''));

        foreach ($this->dateWindows($startDate, $endDate, 14) as [$windowStart, $windowEnd]) {
            foreach (config('marketplace.trendyol.settlement_transaction_types', []) as $transactionType) {
                $page = 0;
                $totalPages = 1;

                do {
                    $response = $this->request($store)
                        ->get("integration/finance/che/sellers/{$this->sellerId($store)}/settlements", array_filter([
                            'startDate' => $windowStart->valueOf(),
                            'endDate' => $windowEnd->valueOf(),
                            'transactionType' => $transactionType,
                            'page' => $page,
                            'size' => config('marketplace.trendyol.finance_page_size', 500),
                        ]))
                        ->throw()
                        ->json();

                    foreach (Arr::get($response, 'content', []) as $financialPayload) {
                        foreach ($this->normalizeSettlementEvents($financialPayload) as $event) {
                            if (!$this->matchesRequestedOrderNumber($requestedOrderNumber, $event)) {
                                continue;
                            }

                            $items[] = $event;
                        }
                    }

                    $totalPages = (int) Arr::get($response, 'totalPages', 1);
                    $page++;
                } while ($page < $totalPages);
            }

            foreach (config('marketplace.trendyol.other_financial_transaction_types', []) as $transactionType) {
                $page = 0;
                $totalPages = 1;

                do {
                    $response = $this->request($store)
                        ->get("integration/finance/che/sellers/{$this->sellerId($store)}/otherfinancials", array_filter([
                            'startDate' => $windowStart->valueOf(),
                            'endDate' => $windowEnd->valueOf(),
                            'transactionType' => $transactionType,
                            'page' => $page,
                            'size' => config('marketplace.trendyol.finance_page_size', 500),
                        ]))
                        ->throw()
                        ->json();

                    foreach (Arr::get($response, 'content', []) as $financialPayload) {
                        foreach ($this->normalizeOtherFinancialEvents($financialPayload) as $event) {
                            if (!$this->matchesRequestedOrderNumber($requestedOrderNumber, $event)) {
                                continue;
                            }

                            $items[] = $event;
                        }
                    }

                    $totalPages = (int) Arr::get($response, 'totalPages', 1);
                    $page++;
                } while ($page < $totalPages);
            }
        }

        return [
            'items' => $items,
            'meta' => [
                'items_received' => count($items),
                'cursor_after' => $endDate->toIso8601String(),
            ],
        ];
    }

    public function pushPrice(ChannelListing $listing, float $price, array $context = []): array
    {
        $listing->loadMissing(['store.connection', 'channelProduct']);

        $barcode = $listing->channelProduct?->barcode;

        if (!$barcode) {
            throw new \RuntimeException('Fiyat guncellemesi icin listing barkodu zorunludur.');
        }

        $payload = [
            'items' => [[
                'barcode' => $barcode,
                'salePrice' => round($price, 2),
                'listPrice' => round((float) ($context['list_price'] ?? $listing->list_price ?? $price), 2),
                'quantity' => (int) ($context['quantity'] ?? $listing->stock_quantity ?? 0),
            ]],
        ];

        $response = $this->request($listing->store)
            ->post("integration/inventory/sellers/{$this->sellerId($listing->store)}/products/price-and-inventory", $payload)
            ->throw()
            ->json();

        return [
            'status' => 'queued',
            'listing_id' => $listing->id,
            'provider' => $this->providerKey(),
            'price' => $price,
            'batch_request_id' => data_get($response, 'batchRequestId'),
            'context' => $context,
        ];
    }

    public function pushStock(ChannelListing $listing, int $quantity, array $context = []): array
    {
        $listing->loadMissing(['store.connection', 'channelProduct']);

        $barcode = $listing->channelProduct?->barcode;

        if (!$barcode) {
            throw new \RuntimeException('Stok guncellemesi icin listing barkodu zorunludur.');
        }

        $payload = [
            'items' => [[
                'barcode' => $barcode,
                'quantity' => $quantity,
                'salePrice' => round((float) ($context['sale_price'] ?? $listing->sale_price ?? 0), 2),
                'listPrice' => round((float) ($context['list_price'] ?? $listing->list_price ?? $listing->sale_price ?? 0), 2),
            ]],
        ];

        $response = $this->request($listing->store)
            ->post("integration/inventory/sellers/{$this->sellerId($listing->store)}/products/price-and-inventory", $payload)
            ->throw()
            ->json();

        return [
            'status' => 'queued',
            'listing_id' => $listing->id,
            'provider' => $this->providerKey(),
            'quantity' => $quantity,
            'batch_request_id' => data_get($response, 'batchRequestId'),
            'context' => $context,
        ];
    }

    public function testConnection(MarketplaceStore $store): array
    {
        $response = $this->request($store)
            ->get("integration/product/sellers/{$this->sellerId($store)}/products/approved", [
                'page' => 0,
                'size' => 1,
                'dateQueryType' => 'CONTENT_MODIFIED_DATE',
                'supplierId' => $this->sellerId($store),
                'startDate' => now()->subDays(30)->valueOf(),
                'endDate' => now()->valueOf(),
            ])
            ->throw()
            ->json();

        return [
            'ok' => true,
            'message' => 'Trendyol bağlantısı doğrulandı.',
            'meta' => [
                'total_elements' => Arr::get($response, 'totalElements'),
                'page' => Arr::get($response, 'page'),
            ],
        ];
    }

    public function notifyPackagePicking(ChannelOrderPackage $package, array $context = []): array
    {
        return $this->updatePackageStatus($package, 'Picking', $context);
    }

    public function notifyPackageInvoiced(ChannelOrderPackage $package, array $context = []): array
    {
        return $this->updatePackageStatus($package, 'Invoiced', $context);
    }

    public function createCommonLabel(ChannelOrderPackage $package, array $context = []): array
    {
        $trackingNumber = trim((string) ($package->cargo_tracking_number ?? ''));

        if ($trackingNumber === '') {
            throw new \RuntimeException('Ortak barkod talebi için kargo takip numarası zorunludur.');
        }

        $package->loadMissing('store.connection');

        $payload = array_filter([
            'cargoTrackingNumber' => $trackingNumber,
            'format' => (string) ($context['format'] ?? 'ZPL'),
            'boxQuantity' => isset($context['box_quantity']) ? (int) $context['box_quantity'] : null,
            'volumetricHeight' => isset($context['volumetric_weight']) ? (float) $context['volumetric_weight'] : null,
        ], fn ($value) => $value !== null && $value !== '');

        $response = $this->request($package->store)
            ->post("integration/sellers/{$this->sellerId($package->store)}/common-label/{$trackingNumber}", $payload)
            ->throw();

        return [
            'status' => 'completed',
            'provider' => $this->providerKey(),
            'package_id' => $package->id,
            'package_external_id' => $package->external_package_id,
            'tracking_number' => $trackingNumber,
            'label_format' => $payload['format'] ?? 'ZPL',
            'response_status' => $response->status(),
            'response' => $this->decodeResponse($response),
            'external_action_id' => (string) ($response->header('X-Request-Id') ?: ''),
        ];
    }

    public function getCommonLabel(ChannelOrderPackage $package, array $context = []): array
    {
        $trackingNumber = trim((string) ($package->cargo_tracking_number ?? ''));

        if ($trackingNumber === '') {
            throw new \RuntimeException('Ortak barkod çekmek için kargo takip numarası zorunludur.');
        }

        $package->loadMissing('store.connection');

        $response = $this->request($package->store)
            ->get("integration/sellers/{$this->sellerId($package->store)}/common-label/{$trackingNumber}")
            ->throw();

        $decoded = $this->decodeResponse($response);

        return [
            'status' => 'completed',
            'provider' => $this->providerKey(),
            'package_id' => $package->id,
            'package_external_id' => $package->external_package_id,
            'tracking_number' => $trackingNumber,
            'label_format' => (string) ($context['format'] ?? 'ZPL'),
            'response' => $decoded,
            'label_count' => is_array(data_get($decoded, 'data')) ? count(data_get($decoded, 'data')) : null,
            'external_action_id' => (string) ($response->header('X-Request-Id') ?: ''),
        ];
    }

    public function sendInvoiceLink(ChannelOrderPackage $package, string $invoiceLink, array $context = []): array
    {
        $package->loadMissing('store.connection');

        $payload = [
            'invoiceLink' => $invoiceLink,
            'shipmentPackageId' => is_numeric($package->external_package_id)
                ? (int) $package->external_package_id
                : $package->external_package_id,
        ];

        $response = $this->request($package->store)
            ->post("integration/sellers/{$this->sellerId($package->store)}/seller-invoice-links", $payload)
            ->throw();

        return [
            'status' => 'completed',
            'provider' => $this->providerKey(),
            'package_id' => $package->id,
            'package_external_id' => $package->external_package_id,
            'invoice_link' => $invoiceLink,
            'response_status' => $response->status(),
            'response' => $this->decodeResponse($response),
            'external_action_id' => (string) ($response->header('X-Request-Id') ?: ''),
        ];
    }

    protected function request(MarketplaceStore $store): PendingRequest
    {
        $connection = $store->connection;
        $credentials = $connection?->credentials_encrypted ?? [];
        $apiKey = (string) ($credentials['api_key'] ?? '');
        $apiSecret = (string) ($credentials['api_secret'] ?? '');
        $storeFrontCode = (string) ($credentials['store_front_code'] ?? $credentials['storefront_code'] ?? '');

        if ($apiKey === '' || $apiSecret === '') {
            throw new \RuntimeException('Trendyol baglantisi icin API key ve API secret zorunludur.');
        }

        return Http::baseUrl(rtrim((string) ($connection?->api_base_url ?: $this->defaultApiBaseUrl()), '/').'/')
            ->timeout((int) config('marketplace.trendyol.request_timeout', 30))
            ->acceptJson()
            ->withBasicAuth($apiKey, $apiSecret)
            ->withHeaders(array_filter([
                'User-Agent' => $this->sellerId($store).' - '.config('marketplace.trendyol.user_agent_suffix'),
                'storeFrontCode' => $storeFrontCode !== '' ? $storeFrontCode : null,
            ]));
    }

    protected function sellerId(MarketplaceStore $store): string
    {
        $sellerId = (string) ($store->seller_id ?: data_get($store->connection?->credentials_encrypted, 'seller_id'));

        if ($sellerId === '') {
            throw new \RuntimeException('Trendyol baglantisi icin seller ID zorunludur.');
        }

        return $sellerId;
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizeOrderPackage(array $payload): array
    {
        $orderNumber = (string) (data_get($payload, 'orderNumber') ?: data_get($payload, 'order.number') ?: data_get($payload, 'orderId'));
        $packageId = $this->packageIdFromPayload($payload);
        $status = (string) (data_get($payload, 'status') ?: data_get($payload, 'shipmentPackageStatus') ?: 'new');
        $isCancelled = $this->statusHas($status, ['cancel', 'iptal'])
            || filled(data_get($payload, 'cancelledBy'))
            || filled(data_get($payload, 'cancelReason'))
            || filled(data_get($payload, 'cancelReasonCode'));

        $customerName = trim((string) collect([
            data_get($payload, 'customerFirstName'),
            data_get($payload, 'customerLastName'),
        ])->filter()->implode(' '));

        return [
            'order' => [
                'external_order_id' => $orderNumber,
                'order_number' => $orderNumber,
                'order_status' => $status,
                'commercial_type' => data_get($payload, 'commercial') ? 'commercial' : 'individual',
                'customer_name' => $customerName ?: data_get($payload, 'customerName'),
                'customer_email' => data_get($payload, 'customerEmail'),
                'customer_phone' => data_get($payload, 'customerPhone') ?: data_get($payload, 'shipmentAddress.phone'),
                'billing_name' => data_get($payload, 'invoiceAddress.fullName') ?: data_get($payload, 'invoiceAddress.company'),
                'billing_tax_number' => data_get($payload, 'invoiceAddress.taxNumber'),
                'shipment_country' => data_get($payload, 'shipmentAddress.countryCode') ?: data_get($payload, 'shipmentAddress.country'),
                'shipment_city' => data_get($payload, 'shipmentAddress.city'),
                'shipment_district' => data_get($payload, 'shipmentAddress.district'),
                'ordered_at' => $this->normalizeDate(data_get($payload, 'orderDate') ?: data_get($payload, 'createdDate')),
                'approved_at' => $this->normalizeDate(data_get($payload, 'agreedDeliveryDateStart') ?: data_get($payload, 'lastModifiedDate')),
                'delivered_at' => $this->normalizeDate(data_get($payload, 'deliveredDate')),
                'cancelled_at' => $isCancelled ? $this->normalizeDate(data_get($payload, 'lastModifiedDate')) : null,
                'returned_at' => $this->statusHas($status, ['return', 'iade']) ? $this->normalizeDate(data_get($payload, 'lastModifiedDate')) : null,
                'raw_payload' => $payload,
            ],
            'package' => [
                'external_package_id' => $packageId,
                'package_number' => $packageId,
                'package_status' => $status,
                'cargo_company' => data_get($payload, 'cargoProviderName') ?: data_get($payload, 'cargoCompany'),
                'cargo_tracking_number' => data_get($payload, 'cargoTrackingNumber'),
                'cargo_barcode' => data_get($payload, 'cargoTrackingNumber'),
                'cargo_desi' => data_get($payload, 'cargoDeci'),
                'shipment_provider' => data_get($payload, 'shipmentProviderName') ?: data_get($payload, 'cargoProviderName'),
                'shipped_at' => $this->normalizeDate(data_get($payload, 'shippedDate')),
                'delivered_at' => $this->normalizeDate(data_get($payload, 'deliveredDate')),
                'raw_payload' => $payload,
            ],
            'items' => collect(data_get($payload, 'lines', []))
                ->values()
                ->map(fn (array $line, int $index) => $this->normalizeOrderLine($line, $payload, $index))
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizeOrderLine(array $line, array $packagePayload, int $index = 0): array
    {
        $quantity = (int) (data_get($line, 'quantity') ?: 1);
        $lineStatus = (string) (data_get($line, 'status') ?: data_get($packagePayload, 'status') ?: 'new');
        $unitPrice = $this->lineUnitPrice($line);
        $grossAmount = $this->lineGrossAmount($line, $quantity, $unitPrice);
        $discountAmount = $this->lineSellerDiscount($line);
        $marketplaceDiscount = $this->lineMarketplaceDiscount($line);

        if ($discountAmount === null && $marketplaceDiscount === null) {
            $discountAmount = $this->toDecimal(data_get($line, 'lineTotalDiscount'));
            $marketplaceDiscount = 0.0;
        }

        $totalDiscount = $this->lineTotalDiscount($line, $discountAmount, $marketplaceDiscount);
        $fallbackLineId = sha1(implode('|', array_filter([
            $this->packageIdFromPayload($packagePayload),
            (string) (data_get($packagePayload, 'orderNumber') ?: data_get($packagePayload, 'orderId')),
            $this->stockCodeFromPayload($line),
            (string) data_get($line, 'barcode'),
            (string) $quantity,
            (string) $index,
        ], fn ($value) => $value !== '')));

        return [
            'external_line_id' => $this->lineIdFromPayload($line, $fallbackLineId),
            'stock_code' => $this->stockCodeFromPayload($line),
            'barcode' => data_get($line, 'barcode'),
            'product_name' => data_get($line, 'productName'),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'gross_amount' => $grossAmount,
            'discount_amount' => $discountAmount,
            'marketplace_discount_amount' => $marketplaceDiscount,
            'billable_amount' => round(max(0, $grossAmount - $totalDiscount), 2),
            'commission_rate' => $this->toDecimal(data_get($line, 'commissionRate')),
            'vat_rate' => $this->lineVatRate($line),
            'line_status' => $lineStatus,
            'raw_payload' => $line,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizeProduct(array $payload): array
    {
        $stockCode = $this->stockCodeFromPayload($payload);
        $barcode = data_get($payload, 'barcode');
        $externalProductId = (string) (data_get($payload, 'stockId') ?: data_get($payload, 'id') ?: $barcode ?: $stockCode);
        $listingId = (string) (data_get($payload, 'platformListingId') ?: data_get($payload, 'listingId') ?: $externalProductId);
        $status = (string) (data_get($payload, 'status') ?: (data_get($payload, 'onSale') ? 'active' : 'inactive'));

        return [
            'product' => [
                'external_product_id' => $externalProductId,
                'external_parent_id' => (string) (data_get($payload, 'productMainId') ?: data_get($payload, 'mainProductId') ?: ''),
                'stock_code' => $stockCode,
                'barcode' => $barcode,
                'title' => data_get($payload, 'title'),
                'brand' => data_get($payload, 'brand'),
                'category_name' => data_get($payload, 'categoryName'),
                'vat_rate' => $this->toDecimal($this->firstPresent(
                    data_get($payload, 'vatRate'),
                    data_get($payload, 'vatBaseAmount'),
                )),
                'raw_payload' => $payload,
            ],
            'listing' => [
                'listing_id' => $listingId,
                'listing_status' => $status,
                'sale_price' => $this->toDecimal(data_get($payload, 'salePrice')),
                'list_price' => $this->toDecimal(data_get($payload, 'listPrice')),
                'currency' => data_get($payload, 'currencyType') ?: 'TRY',
                'stock_quantity' => (int) (data_get($payload, 'quantity') ?: 0),
                'published_at' => $this->normalizeDate(data_get($payload, 'approvedDate') ?: data_get($payload, 'createDate')),
            ],
        ];
    }

    /**
     * Approved Product v2 cevabi content bazlidir; ZOLM katalog modeli varyant bazli
     * calistigi icin her varyanti ayri satir olarak normalize ediyoruz.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeApprovedProductContent(array $payload): array
    {
        $contentId = (string) (data_get($payload, 'contentId') ?: data_get($payload, 'id') ?: '');
        $variants = collect(Arr::wrap(data_get($payload, 'variants', [])))
            ->filter(fn ($variant) => is_array($variant))
            ->values();

        if ($variants->isEmpty()) {
            return [$this->normalizeProduct([
                'id' => $contentId,
                'productMainId' => data_get($payload, 'productMainId'),
                'brand' => data_get($payload, 'brand.name') ?: data_get($payload, 'brand'),
                'categoryName' => data_get($payload, 'category.name') ?: data_get($payload, 'categoryName'),
                'title' => data_get($payload, 'title'),
                'vatRate' => data_get($payload, 'vatRate'),
                'barcode' => data_get($payload, 'barcode'),
                'stockCode' => $this->stockCodeFromPayload($payload),
                'status' => data_get($payload, 'status'),
                'salePrice' => data_get($payload, 'price.salePrice') ?: data_get($payload, 'salePrice'),
                'listPrice' => data_get($payload, 'price.listPrice') ?: data_get($payload, 'listPrice'),
                'quantity' => data_get($payload, 'stock.quantity') ?: data_get($payload, 'quantity'),
                'approvedDate' => data_get($payload, 'approvedDate'),
                'createDate' => data_get($payload, 'creationDate') ?: data_get($payload, 'createDate'),
            ])];
        }

        return $variants
            ->map(function (array $variant) use ($payload, $contentId) {
                $stockCode = (string) ($this->stockCodeFromPayload($variant) ?: $this->stockCodeFromPayload($payload) ?: '');
                $barcode = data_get($variant, 'barcode') ?: data_get($payload, 'barcode');
                $variantId = (string) (
                    data_get($variant, 'variantId')
                    ?: data_get($variant, 'listingId')
                    ?: data_get($variant, 'id')
                    ?: $barcode
                    ?: $stockCode
                    ?: $contentId
                );

                return [
                    'product' => [
                        'external_product_id' => $variantId,
                        'external_parent_id' => $contentId !== '' ? $contentId : (string) (data_get($payload, 'productMainId') ?: ''),
                        'stock_code' => $stockCode !== '' ? $stockCode : null,
                        'barcode' => $barcode,
                        'title' => data_get($payload, 'title'),
                        'brand' => data_get($payload, 'brand.name') ?: data_get($payload, 'brand'),
                        'category_name' => data_get($payload, 'category.name') ?: data_get($payload, 'categoryName'),
                        'vat_rate' => $this->toDecimal($this->firstPresent(
                            data_get($variant, 'vatRate'),
                            data_get($payload, 'vatRate')
                        )),
                        'raw_payload' => [
                            'content' => $payload,
                            'variant' => $variant,
                        ],
                    ],
                    'listing' => [
                        'listing_id' => $variantId,
                        'listing_status' => $this->approvedVariantStatus($variant),
                        'sale_price' => $this->toDecimal(data_get($variant, 'price.salePrice') ?: data_get($payload, 'price.salePrice') ?: data_get($payload, 'salePrice')),
                        'list_price' => $this->toDecimal(data_get($variant, 'price.listPrice') ?: data_get($payload, 'price.listPrice') ?: data_get($payload, 'listPrice')),
                        'currency' => data_get($variant, 'price.currencyType')
                            ?: data_get($payload, 'price.currencyType')
                            ?: data_get($payload, 'currencyType')
                            ?: 'TRY',
                        'stock_quantity' => $this->firstPresent(
                            data_get($variant, 'stock.quantity'),
                            data_get($variant, 'quantity'),
                            data_get($variant, 'availableStock')
                        ),
                        'published_at' => $this->normalizeDate($this->firstPresent(
                            data_get($variant, 'sellerCreatedDate'),
                            data_get($payload, 'approvedDate'),
                            data_get($payload, 'creationDate')
                        )),
                    ],
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeSettlementEvents(array $payload): array
    {
        $events = [];
        $baseMeta = [
            'order_number' => (string) (data_get($payload, 'orderNumber') ?: ''),
            'external_package_id' => $this->packageIdFromPayload($payload),
            'external_line_id' => (string) (data_get($payload, 'lineItemId') ?: data_get($payload, 'lineId') ?: ''),
            'stock_code' => $this->stockCodeFromPayload($payload),
            'barcode' => data_get($payload, 'barcode'),
            'event_date' => $this->normalizeDate(data_get($payload, 'transactionDate') ?: data_get($payload, 'date')),
            'settlement_date' => $this->normalizeDate(data_get($payload, 'paymentDate')),
            'currency' => data_get($payload, 'currency') ?: 'TRY',
            'reference_number' => (string) (data_get($payload, 'paymentOrderId') ?: data_get($payload, 'orderNumber') ?: ''),
            'raw_payload' => $payload,
        ];

        $events = array_merge($events, $this->buildFinancialEvent(
            eventSource: 'settlements',
            eventType: 'seller_revenue',
            uniqueSeed: (string) (
                data_get($payload, 'id')
                ?: $this->packageIdFromPayload($payload)
                ?: data_get($payload, 'orderNumber')
            ),
            amount: $this->toDecimal(data_get($payload, 'sellerRevenue')),
            notes: (string) (data_get($payload, 'transactionType') ?: 'settlement'),
            baseMeta: $baseMeta,
        ));

        $events = array_merge($events, $this->buildFinancialEvent(
            eventSource: 'settlements',
            eventType: 'commission',
            uniqueSeed: (string) (
                data_get($payload, 'id')
                ?: $this->packageIdFromPayload($payload)
                ?: data_get($payload, 'orderNumber')
            ),
            amount: $this->toDecimal(data_get($payload, 'commissionAmount')),
            notes: 'Komisyon',
            baseMeta: $baseMeta,
        ));

        return $events;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeOtherFinancialEvents(array $payload): array
    {
        $transactionType = (string) (data_get($payload, 'transactionType') ?: data_get($payload, 'type') ?: 'otherfinancial');
        $normalizedType = match (Str::lower($transactionType)) {
            'stoppage' => 'withholding',
            'paymentorder' => 'payment_order',
            default => Str::contains(Str::lower($transactionType), 'cargo') ? 'cargo' : 'service_fee',
        };

        return $this->buildFinancialEvent(
            eventSource: 'otherfinancials',
            eventType: $normalizedType,
            uniqueSeed: (string) (data_get($payload, 'id') ?: data_get($payload, 'serialNumber') ?: data_get($payload, 'paymentOrderId') ?: $transactionType),
            amount: $this->toDecimal(data_get($payload, 'amount') ?: data_get($payload, 'totalAmount')),
            notes: $transactionType,
            baseMeta: [
                'order_number' => (string) (data_get($payload, 'orderNumber') ?: ''),
                'external_package_id' => (string) (
                    $this->packageIdFromPayload($payload)
                    ?: data_get($payload, 'parcelUniqueId')
                    ?: ''
                ),
                'external_line_id' => (string) (data_get($payload, 'lineItemId') ?: data_get($payload, 'lineId') ?: ''),
                'stock_code' => $this->stockCodeFromPayload($payload),
                'barcode' => data_get($payload, 'barcode'),
                'event_date' => $this->normalizeDate(data_get($payload, 'transactionDate') ?: data_get($payload, 'issueDate')),
                'due_date' => $this->normalizeDate(data_get($payload, 'dueDate')),
                'settlement_date' => $this->normalizeDate(data_get($payload, 'paymentDate')),
                'currency' => data_get($payload, 'currency') ?: 'TRY',
                'reference_number' => (string) (data_get($payload, 'serialNumber') ?: data_get($payload, 'paymentOrderId') ?: ''),
                'raw_payload' => $payload,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $baseMeta
     * @return array<int, array<string, mixed>>
     */
    protected function buildFinancialEvent(
        string $eventSource,
        string $eventType,
        string $uniqueSeed,
        ?float $amount,
        string $notes,
        array $baseMeta,
    ): array {
        if ($amount === null || round($amount, 2) == 0.0) {
            return [];
        }

        $direction = $amount >= 0 ? 'credit' : 'debit';

        if (in_array($eventType, ['commission', 'cargo', 'service_fee', 'withholding', 'deduction_invoice'], true)) {
            $direction = $amount >= 0 ? 'debit' : 'credit';
        }

        return [[
            'event_source' => $eventSource,
            'event_type' => $eventType,
            'external_event_id' => sha1($eventSource.'|'.$uniqueSeed.'|'.$eventType.'|'.$notes.'|'.($baseMeta['order_number'] ?? '').'|'.($baseMeta['external_package_id'] ?? '').'|'.($baseMeta['reference_number'] ?? '')),
            'amount' => abs($amount),
            'direction' => $direction,
            'status' => 'posted',
            'notes' => $notes,
        ] + $baseMeta];
    }

    /**
     * @return array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    protected function dateWindows(CarbonImmutable $startDate, CarbonImmutable $endDate, int $windowDays): array
    {
        $windows = [];
        $cursor = $startDate;

        while ($cursor->lessThanOrEqualTo($endDate)) {
            $windowEnd = $cursor->addDays($windowDays - 1);

            if ($windowEnd->greaterThan($endDate)) {
                $windowEnd = $endDate;
            }

            $windows[] = [$cursor, $windowEnd];
            $cursor = $windowEnd->addDay();
        }

        return $windows;
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

        return CarbonImmutable::parse((string) $value)->toIso8601String();
    }

    protected function toDecimal(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }

    protected function packageIdFromPayload(array $payload): string
    {
        return (string) ($this->firstPresent(
            data_get($payload, 'shipmentPackageId'),
            data_get($payload, 'shipmentPackage.shipmentPackageId'),
            data_get($payload, 'id'),
            data_get($payload, 'shipmentPackage.id'),
        ) ?? '');
    }

    protected function lineIdFromPayload(array $payload, ?string $fallback = null): string
    {
        return (string) ($this->firstPresent(
            data_get($payload, 'lineId'),
            data_get($payload, 'id'),
            data_get($payload, 'lineItemId'),
            $fallback,
        ) ?? '');
    }

    protected function stockCodeFromPayload(array $payload): ?string
    {
        $stockCode = $this->firstPresent(
            data_get($payload, 'stockCode'),
            data_get($payload, 'merchantSku'),
        );

        return $stockCode !== null ? (string) $stockCode : null;
    }

    protected function lineUnitPrice(array $line): float
    {
        return $this->toDecimal($this->firstPresent(
            data_get($line, 'lineUnitPrice'),
            data_get($line, 'unitPrice'),
            data_get($line, 'price'),
        )) ?? 0.0;
    }

    protected function lineGrossAmount(array $line, int $quantity, float $unitPrice): float
    {
        return $this->toDecimal($this->firstPresent(
            data_get($line, 'lineGrossAmount'),
            data_get($line, 'amount'),
        )) ?? round($unitPrice * $quantity, 2);
    }

    protected function lineSellerDiscount(array $line): ?float
    {
        return $this->toDecimal($this->firstPresent(
            data_get($line, 'lineSellerDiscount'),
            data_get($line, 'discount'),
        ));
    }

    protected function lineMarketplaceDiscount(array $line): ?float
    {
        return $this->toDecimal($this->firstPresent(
            data_get($line, 'lineTyDiscount'),
            data_get($line, 'tyDiscount'),
            data_get($line, 'platformDiscount'),
        ));
    }

    protected function lineTotalDiscount(array $line, ?float $sellerDiscount, ?float $marketplaceDiscount): float
    {
        return $this->toDecimal(data_get($line, 'lineTotalDiscount'))
            ?? round(($sellerDiscount ?? 0) + ($marketplaceDiscount ?? 0), 2);
    }

    protected function lineVatRate(array $line): ?float
    {
        return $this->toDecimal($this->firstPresent(
            data_get($line, 'vatRate'),
            data_get($line, 'vatBaseAmount'),
        ));
    }

    protected function firstPresent(mixed ...$values): mixed
    {
        foreach ($values as $value) {
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $needles
     */
    protected function statusHas(string $status, array $needles): bool
    {
        $status = Str::lower($status);

        foreach ($needles as $needle) {
            if (Str::contains($status, Str::lower($needle))) {
                return true;
            }
        }

        return false;
    }

    protected function approvedVariantStatus(array $variant): string
    {
        if ((bool) data_get($variant, 'archived')) {
            return 'archived';
        }

        if ((bool) data_get($variant, 'blacklisted')) {
            return 'blacklisted';
        }

        if ((bool) data_get($variant, 'locked')) {
            return 'locked';
        }

        if ((bool) data_get($variant, 'onSale')) {
            return 'onSale';
        }

        return 'inactive';
    }

    /**
     * Trendyol 5 Mart 2026 itibariyla siparis paketlerinde son 30 gun limitine gecti.
     *
     * @return array{0: CarbonImmutable, 1: array<string, mixed>}
     */
    protected function resolveOrderWindow(CarbonImmutable $requestedStartDate, CarbonImmutable $endDate): array
    {
        $historyLimitDays = max(1, (int) config('marketplace.trendyol.order_history_limit_days', 30));
        $minAllowedStartDate = CarbonImmutable::now('UTC')->subDays($historyLimitDays);

        if ($endDate->lessThan($minAllowedStartDate)) {
            throw new \RuntimeException(sprintf(
                'Trendyol Siparis Paketlerini Cekme servisi 5 Mart 2026 itibariyla yalnizca son %d gunu destekliyor. %s bitis tarihli sorgu bu limitin disinda kaliyor.',
                $historyLimitDays,
                $endDate->toIso8601String()
            ));
        }

        $effectiveStartDate = $requestedStartDate->lessThan($minAllowedStartDate)
            ? $minAllowedStartDate
            : $requestedStartDate;

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

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function updatePackageStatus(ChannelOrderPackage $package, string $status, array $context = []): array
    {
        $package->loadMissing([
            'store.connection',
            'items:id,channel_order_package_id,external_line_id,quantity',
        ]);

        if (blank($package->external_package_id)) {
            throw new \RuntimeException('Paket statü güncellemesi için harici paket ID zorunludur.');
        }

        $lines = $package->items
            ->map(function ($item) {
                $lineId = trim((string) $item->external_line_id);

                return array_filter([
                    'lineId' => ctype_digit($lineId) ? (int) $lineId : $lineId,
                    'quantity' => (int) $item->quantity,
                ], fn ($value) => $value !== null && $value !== '');
            })
            ->filter(fn ($line) => !blank($line['lineId'] ?? null))
            ->values()
            ->all();

        if ($lines === []) {
            throw new \RuntimeException('Paket statü güncellemesi için en az bir satır gerekir.');
        }

        $payload = [
            'lines' => $lines,
            'params' => array_filter([
                'invoiceNumber' => (string) ($context['invoice_number'] ?? ''),
                'invoiceDate' => isset($context['invoice_date']) ? $this->normalizeDate($context['invoice_date']) : null,
            ], fn ($value) => $value !== null && $value !== ''),
            'status' => $status,
        ];

        $response = $this->request($package->store)
            ->put("integration/order/sellers/{$this->sellerId($package->store)}/shipment-packages/{$package->external_package_id}", $payload)
            ->throw();

        return [
            'status' => 'completed',
            'provider' => $this->providerKey(),
            'package_id' => $package->id,
            'package_external_id' => $package->external_package_id,
            'package_status' => $status,
            'line_count' => count($lines),
            'response_status' => $response->status(),
            'response' => $this->decodeResponse($response),
            'external_action_id' => (string) ($response->header('X-Request-Id') ?: ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeResponse(Response $response): array
    {
        $body = trim($response->body());

        if ($body === '') {
            return [];
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : ['raw' => $body];
    }

    /**
     * @param  array<string, mixed>  $event
     */
    protected function matchesRequestedOrderNumber(string $requestedOrderNumber, array $event): bool
    {
        if ($requestedOrderNumber === '') {
            return true;
        }

        return trim((string) ($event['order_number'] ?? '')) === $requestedOrderNumber;
    }
}
