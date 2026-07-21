<?php

namespace App\Services\Marketplace\Connectors;

use App\Models\ChannelOrderPackage;
use App\Models\MpCategoryAttribute;
use App\Models\MpCategoryAttributeValue;
use App\Models\MarketplaceQuestion;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\Connectors\Concerns\NormalizesCustomerQuestions;
use App\Services\Marketplace\Contracts\AnswersCustomerQuestions;
use App\Services\Marketplace\Contracts\ManagesClaims;
use App\Services\Marketplace\Contracts\ManagesCommonLabels;
use App\Services\Marketplace\Contracts\PullsFinancials;
use App\Services\Marketplace\Contracts\PullsCustomerQuestions;
use App\Services\Marketplace\Contracts\PullsClaims;
use App\Services\Marketplace\Contracts\PullsOrders;
use App\Services\Marketplace\Contracts\PullsProducts;
use App\Services\Marketplace\Contracts\PullsBatchStatus;
use App\Services\Marketplace\Contracts\PullsCatalogProducts;
use App\Services\Marketplace\Contracts\PullsReferenceCategories;
use App\Services\Marketplace\Contracts\PushesPrice;
use App\Services\Marketplace\Contracts\PushesStock;
use App\Services\Marketplace\Contracts\TestsConnection;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class HepsiburadaConnector extends AbstractMarketplaceConnector implements
    PullsOrders,
    PullsProducts,
    PullsFinancials,
    PullsCustomerQuestions,
    PullsClaims,
    ManagesClaims,
    AnswersCustomerQuestions,
    PushesPrice,
    PushesStock,
    ManagesCommonLabels,
    TestsConnection,
    PullsReferenceCategories,
    PullsCatalogProducts,
    PullsBatchStatus
{
    use NormalizesCustomerQuestions;

    public function providerKey(): string
    {
        return 'hepsiburada';
    }

    public function displayName(): string
    {
        return 'Hepsiburada';
    }

    public function defaultApiBaseUrl(): ?string
    {
        return config('marketplace.hepsiburada.oms_base_url');
    }

    /**
     * @return array<string, bool>
     */
    public function capabilities(): array
    {
        return [
            'orders'                      => true,
            'products'                    => true,
            'finance'                     => true,
            'webhooks'                    => false,
            'price_push'                  => true,
            'stock_push'                  => true,
            'package_status'              => false,
            'package_picking'             => false,
            'package_invoiced'            => false,
            'common_label'                => true,
            'package_common_label_create' => true,
            'package_common_label_get'    => true,
            'invoice_link'                => false,
            'package_invoice_link'        => false,
            'questions'                   => true,
            'question_answer'             => true,
            'claims'                      => true,
            'claim_approve'               => true,
            'claim_reject'                => true,
            // P0 salt-okuma — endpoint URL'leri (Hepsiburada portal SPA)
            'reference_categories_pull'   => true,  // getCategories() uygulandı — resmi doğrulandı
            'reference_attributes_pull'   => true,  // getCategoryAttributes() uygulandı — resmi doğrulandı
            'reference_brands_pull'       => false, // Hepsiburada marka listesi API doğrulanamadı
            'catalog_products_pull'       => true,  // pullCatalogProducts() uygulandı — resmi doğrulandı
            'pending_orders_pull'         => false, // Hepsiburada unpaid/pending orders endpointi resmi olarak doğrulanamadı
            'batch_status_pull'           => true,  // polling endpoint connector kodundan kanıtlandı
        ];
    }

    public function pushPrice(\App\Models\ChannelListing $listing, float $price, array $context = []): array
    {
        $listing->loadMissing(['store.connection', 'channelProduct']);

        $merchantSku = $this->merchantSku($listing);
        $sku = $this->hepsiburadaSku($listing);

        if ($merchantSku === null && $sku === null) {
            throw new \RuntimeException('Hepsiburada fiyat push icin merchantSku veya hepsiburadaSku zorunludur.');
        }

        $xmlPayload = $this->buildListingUploadXml([
            array_filter([
                'HepsiburadaSku' => $sku,
                'MerchantSku' => $merchantSku,
                'Price' => $this->xmlDecimal($price),
            ], fn ($value) => $value !== null && $value !== ''),
        ]);

        $response = $this->request($listing->store, 'listing')
            ->withHeaders(['Content-Type' => 'application/xml'])
            ->withBody($xmlPayload, 'application/xml')
            ->post('listings/merchantid/'.$this->merchantId($listing->store).'/price-uploads')
            ->throw();

        $decoded = $this->decodeResponse($response);

        return [
            'status' => 'queued',
            'listing_id' => $listing->id,
            'provider' => $this->providerKey(),
            'price' => round($price, 2),
            'batch_request_id' => (string) ($decoded['Id'] ?? $decoded['id'] ?? ''),
            'response_status' => $response->status(),
            'response' => $decoded,
            'polling_endpoint' => 'listings/merchantid/'.$this->merchantId($listing->store).'/price-uploads/id/{id}',
            'context' => $context,
        ];
    }

    public function pushStock(\App\Models\ChannelListing $listing, int $quantity, array $context = []): array
    {
        $listing->loadMissing(['store.connection', 'channelProduct']);

        $merchantSku = $this->merchantSku($listing);
        $sku = $this->hepsiburadaSku($listing);

        if ($merchantSku === null && $sku === null) {
            throw new \RuntimeException('Hepsiburada stok push icin merchantSku veya hepsiburadaSku zorunludur.');
        }

        $xmlPayload = $this->buildListingUploadXml([
            array_filter([
                'HepsiburadaSku' => $sku,
                'MerchantSku' => $merchantSku,
                'AvailableStock' => $quantity,
            ], fn ($value) => $value !== null && $value !== ''),
        ]);

        $response = $this->request($listing->store, 'listing')
            ->withHeaders(['Content-Type' => 'application/xml'])
            ->withBody($xmlPayload, 'application/xml')
            ->post('listings/merchantid/'.$this->merchantId($listing->store).'/stock-uploads')
            ->throw();

        $decoded = $this->decodeResponse($response);

        return [
            'status' => 'queued',
            'listing_id' => $listing->id,
            'provider' => $this->providerKey(),
            'quantity' => $quantity,
            'batch_request_id' => (string) ($decoded['Id'] ?? $decoded['id'] ?? ''),
            'response_status' => $response->status(),
            'response' => $decoded,
            'polling_endpoint' => 'listings/merchantid/'.$this->merchantId($listing->store).'/stock-uploads/id/{id}',
            'context' => $context,
        ];
    }

    public function pullOrders(MarketplaceStore $store, array $options = []): array
    {
        $startDate = CarbonImmutable::parse($options['start_date'] ?? now()->subDay())->setTimezone('Europe/Istanbul');
        $endDate = CarbonImmutable::parse($options['end_date'] ?? now())->setTimezone('Europe/Istanbul');
        $packageSummaries = [];

        foreach ($this->packageEndpoints() as $endpoint) {
            $rows = $this->fetchPaginated(
                store: $store,
                service: 'oms',
                path: str_replace('{merchantId}', $this->merchantId($store), $endpoint['path']),
                query: $this->dateWindowQuery($startDate, $endDate),
                pageSize: (int) $endpoint['page_size'],
            );

            foreach ($rows as $row) {
                $orderNumber = trim((string) ($this->summaryOrderNumber($row) ?? ''));
                $packageId = trim((string) ($this->summaryPackageId($row) ?? ''));

                if ($orderNumber === '' || $packageId === '') {
                    continue;
                }

                $packageSummaries[$orderNumber.'|'.$packageId] = $row + [
                    '__status_hint' => $endpoint['status'],
                ];
            }
        }

        $normalized = [];
        $details = [];

        foreach ($packageSummaries as $summary) {
            $orderNumber = trim((string) ($this->summaryOrderNumber($summary) ?? ''));

            if ($orderNumber === '') {
                continue;
            }

            if (!array_key_exists($orderNumber, $details)) {
                $details[$orderNumber] = $this->fetchOrderDetailSafely($store, $orderNumber);
            }

            $normalized[] = $this->normalizeOrderPackage(
                detailPayload: is_array($details[$orderNumber]) ? $details[$orderNumber] : [],
                summaryPayload: $summary,
            );
        }

        return [
            'items' => $normalized,
            'meta' => [
                'items_received' => count($normalized),
                'cursor_after' => $endDate->toIso8601String(),
            ],
        ];
    }

    public function pullProducts(MarketplaceStore $store, array $options = []): array
    {
        $items = $this->fetchPaginated(
            store: $store,
            service: 'listing',
            path: 'listings/merchantid/'.$this->merchantId($store),
            query: [],
            pageSize: (int) config('marketplace.hepsiburada.product_page_size', 100),
        );

        return [
            'items' => collect($items)
                ->map(fn (array $payload) => $this->normalizeProduct($payload))
                ->values()
                ->all(),
            'meta' => [
                'items_received' => count($items),
                'cursor_after' => now()->toIso8601String(),
            ],
        ];
    }

    public function pullFinancialEvents(MarketplaceStore $store, array $options = []): array
    {
        $startDate = CarbonImmutable::parse($options['start_date'] ?? now()->subDays(30))->setTimezone('Europe/Istanbul');
        $endDate = CarbonImmutable::parse($options['end_date'] ?? now())->setTimezone('Europe/Istanbul');
        $rows = $this->fetchPaginated(
            store: $store,
            service: 'finance',
            path: 'transactions/merchantid/'.$this->merchantId($store),
            query: $this->dateWindowQuery($startDate, $endDate),
            pageSize: (int) config('marketplace.hepsiburada.finance_page_size', 100),
        );

        return [
            'items' => collect($rows)
                ->flatMap(fn (array $payload) => $this->normalizeFinancialEvents($payload))
                ->values()
                ->all(),
            'meta' => [
                'items_received' => count($rows),
                'cursor_after' => $endDate->toIso8601String(),
            ],
        ];
    }

    public function pullCustomerQuestions(MarketplaceStore $store, array $options = []): array
    {
        $startDate = CarbonImmutable::parse($options['start_date'] ?? now()->subDays(7))->setTimezone('UTC');
        $endDate = CarbonImmutable::parse($options['end_date'] ?? now())->setTimezone('UTC');
        $limit = min(100, max(1, (int) ($options['page_size'] ?? config('marketplace.hepsiburada.question_page_size', 100))));
        $maxPages = max(1, (int) config('marketplace.hepsiburada.max_question_pages_per_sync', 10));
        $offset = 0;
        $page = 0;
        $items = [];

        do {
            $response = $this->request($store, 'questions')
                ->get('issues', array_filter([
                    'minModifiedAt' => $startDate->toIso8601String(),
                    'maxModifiedAt' => $endDate->toIso8601String(),
                    'status' => $options['status'] ?? null,
                    'limit' => $limit,
                    'offset' => $offset,
                ], fn ($value) => $value !== null && $value !== ''))
                ->throw();

            $payload = $this->decodeResponse($response);
            $batch = $this->questionRowsFromPayload($payload, ['issues', 'items', 'data', 'content']);

            foreach ($batch as $row) {
                $items[] = $this->normalizeHepsiburadaQuestion($row);
            }

            $offset += $limit;
            $page++;
            $totalCount = $this->extractTotalCount($payload);
            $hasMore = $totalCount !== null
                ? $offset < $totalCount
                : count($batch) === $limit;
        } while ($hasMore && $page < $maxPages);

        return [
            'items' => $items,
            'meta' => [
                'items_received' => count($items),
                'cursor_after' => $endDate->toIso8601String(),
                'pages_processed' => $page,
                'more_pages_available' => $hasMore ?? false,
            ],
        ];
    }

    public function pullClaims(MarketplaceStore $store, array $options = []): array
    {
        $startDate = CarbonImmutable::parse($options['start_date'] ?? now()->subDays(7))->setTimezone('Europe/Istanbul');
        $endDate = CarbonImmutable::parse($options['end_date'] ?? now())->setTimezone('Europe/Istanbul');
        $path = str_replace('{merchantId}', $this->merchantId($store), trim((string) config('marketplace.hepsiburada.claim_list_path', 'claims/merchantid/{merchantId}'), '/'));

        $rows = $this->fetchPaginated(
            store: $store,
            service: 'claims',
            path: $path,
            query: $this->dateWindowQuery($startDate, $endDate) + array_filter([
                'status' => $options['status'] ?? null,
                'orderNumber' => $options['order_number'] ?? null,
            ], fn ($value) => $value !== null && $value !== ''),
            pageSize: (int) config('marketplace.hepsiburada.claim_page_size', 50),
        );

        return [
            'items' => collect($rows)
                ->map(fn (array $payload) => $this->normalizeClaim($payload))
                ->values()
                ->all(),
            'meta' => [
                'items_received' => count($rows),
                'cursor_after' => $endDate->toIso8601String(),
                'endpoint' => $path,
            ],
        ];
    }

    public function approveClaim(MarketplaceStore $store, string $externalClaimId, array $context = []): array
    {
        $path = $this->claimActionPath('claim_accept_path', 'claims/number/{claimNumber}/accept', $externalClaimId);
        $response = $this->request($store, 'claims')
            ->post($path, $context['payload'] ?? [])
            ->throw();

        return [
            'status' => 'approved',
            'message' => 'Hepsiburada iade onayı gönderildi.',
            'response_status' => $response->status(),
            'response' => $this->decodeResponse($response),
        ];
    }

    public function rejectClaim(MarketplaceStore $store, string $externalClaimId, string $reason, array $context = []): array
    {
        $path = $this->claimActionPath('claim_reject_path', 'claims/number/{claimNumber}/reject', $externalClaimId);
        $response = $this->request($store, 'claims')
            ->post($path, array_replace([
                'reason' => $reason,
                'description' => $reason,
            ], $context['payload'] ?? []))
            ->throw();

        return [
            'status' => 'rejected',
            'message' => 'Hepsiburada iade reddi gönderildi.',
            'response_status' => $response->status(),
            'response' => $this->decodeResponse($response),
        ];
    }

    public function answerCustomerQuestion(MarketplaceQuestion $question, string $answer): array
    {
        $question->loadMissing('store.connection');

        $response = $this->request($question->store, 'questions')
            ->asMultipart()
            ->post('issues/'.$question->external_question_id.'/answer', [
                ['name' => 'Answer', 'contents' => $answer],
            ])
            ->throw();

        $payload = $this->decodeResponse($response);

        return [
            'external_answer_id' => (string) (data_get($payload, 'id') ?: data_get($payload, 'answerId') ?: $question->external_question_id),
            'response_status' => $response->status(),
            'response' => $payload,
        ];
    }

    public function testConnection(MarketplaceStore $store): array
    {
        $response = $this->request($store, 'listing')
            ->get('listings/merchantid/'.$this->merchantId($store), [
                'limit' => 1,
                'offset' => 0,
            ])
            ->throw()
            ->json();

        return [
            'ok' => true,
            'message' => 'Hepsiburada bağlantısı doğrulandı.',
            'meta' => [
                'total_count' => $this->extractTotalCount(is_array($response) ? $response : []),
            ],
        ];
    }

    public function createCommonLabel(ChannelOrderPackage $package, array $context = []): array
    {
        return $this->fetchCommonLabel($package, $context, 'create');
    }

    public function getCommonLabel(ChannelOrderPackage $package, array $context = []): array
    {
        return $this->fetchCommonLabel($package, $context, 'get');
    }

    protected function request(MarketplaceStore $store, string $service = 'oms'): PendingRequest
    {
        [$username, $password, $userAgent] = $this->resolveAuth($store);

        return Http::baseUrl($this->baseUrlFor($store, $service))
            ->timeout((int) config('marketplace.hepsiburada.request_timeout', 45))
            ->acceptJson()
            ->asJson()
            ->withBasicAuth($username, $password)
            ->withHeaders([
                'User-Agent' => $userAgent,
            ]);
    }

    /**
     * Hepsiburada dokümanında ortak barkod servisi GET labels endpointi ile sunuluyor.
     * Bu yüzden "talep et" ve "getir" aksiyonlarını aynı güvenli endpoint üzerinden yürütüyoruz.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function fetchCommonLabel(ChannelOrderPackage $package, array $context, string $operation): array
    {
        $package->loadMissing('store.connection');

        $packageNumber = trim((string) ($package->package_number ?: $package->external_package_id));

        if ($packageNumber === '') {
            throw new \RuntimeException('Hepsiburada ortak barkod işlemi için paket numarası zorunludur.');
        }

        $response = $this->request($package->store, 'oms')
            ->withHeaders(['Accept' => '*/*'])
            ->get('packages/merchantid/'.$this->merchantId($package->store).'/packagenumber/'.$packageNumber.'/labels')
            ->throw();

        return $this->normalizeLabelResponse($package, $response, $context, $operation, $packageNumber);
    }

    protected function merchantId(MarketplaceStore $store): string
    {
        $merchantId = trim((string) ($store->seller_id ?: data_get($store->connection?->credentials_encrypted, 'merchant_id')));

        if ($merchantId === '') {
            throw new \RuntimeException('Hepsiburada bağlantısı için merchant ID zorunludur.');
        }

        return $merchantId;
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    protected function resolveAuth(MarketplaceStore $store): array
    {
        $credentials = $store->connection?->credentials_encrypted ?? [];
        $merchantId = $this->merchantId($store);
        $serviceKey = trim((string) ($credentials['api_key'] ?? ''));
        $legacyUser = trim((string) ($credentials['extra_user'] ?? ''));
        $legacyPassword = trim((string) ($credentials['extra_password'] ?? $credentials['api_secret'] ?? ''));

        if ($serviceKey !== '') {
            $userAgent = $legacyUser !== ''
                ? $legacyUser
                : $merchantId.' - '.config('marketplace.hepsiburada.user_agent_suffix', 'ZOLM Marketplace Integration');

            return [$merchantId, $serviceKey, $userAgent];
        }

        if ($legacyUser !== '' && $legacyPassword !== '') {
            return [$legacyUser, $legacyPassword, $legacyUser];
        }

        throw new \RuntimeException('Hepsiburada bağlantısı için service key veya legacy kullanıcı/şifre zorunludur.');
    }

    protected function baseUrlFor(MarketplaceStore $store, string $service): string
    {
        $connectionBaseUrl = trim((string) ($store->connection?->api_base_url ?? ''));

        $configured = match ($service) {
            'oms' => $connectionBaseUrl !== '' ? $connectionBaseUrl : config('marketplace.hepsiburada.oms_base_url'),
            'listing' => config('marketplace.hepsiburada.listing_base_url'),
            'finance' => config('marketplace.hepsiburada.finance_base_url'),
            'product' => config('marketplace.hepsiburada.product_base_url'),
            'questions' => config('marketplace.hepsiburada.question_base_url'),
            'claims' => config('marketplace.hepsiburada.claim_base_url'),
            default => config('marketplace.hepsiburada.oms_base_url'),
        };

        return rtrim((string) $configured, '/').'/';
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<int, array<string, mixed>>
     */
    protected function fetchPaginated(
        MarketplaceStore $store,
        string $service,
        string $path,
        array $query,
        int $pageSize,
        ?int $maxPagesOverride = null,
    ): array {
        $items = [];
        $offset = 0;
        $page = 0;
        $maxPages = $maxPagesOverride !== null ? $maxPagesOverride : (int) config('marketplace.hepsiburada.max_pages_per_request', 50);

        do {
            $response = $this->request($store, $service)
                ->get($path, array_filter($query + [
                    'limit' => $pageSize,
                    'offset' => $offset,
                ], fn ($value) => $value !== null && $value !== ''))
                ->throw();

            $payload = $this->decodeResponse($response);
            $batch = $this->extractItems($payload);

            foreach ($batch as $row) {
                if (is_array($row)) {
                    $items[] = $row;
                }
            }

            $offset += $pageSize;
            $page++;
            $totalCount = $this->extractTotalCount($payload);
            $hasMore = $totalCount !== null
                ? $offset < $totalCount
                : count($batch) === $pageSize;
        } while ($hasMore && $page < $maxPages);

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    protected function fetchOrderDetailSafely(MarketplaceStore $store, string $orderNumber): array
    {
        try {
            $response = $this->request($store, 'oms')
                ->get('orders/merchantid/'.$this->merchantId($store).'/ordernumber/'.$orderNumber)
                ->throw();

            return $this->decodeResponse($response);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, array{path: string, status: string, page_size: int}>
     */
    protected function packageEndpoints(): array
    {
        return [
            [
                'path' => 'packages/merchantid/{merchantId}',
                'status' => 'Open',
                'page_size' => (int) config('marketplace.hepsiburada.open_package_page_size', 10),
            ],
            [
                'path' => 'packages/merchantid/{merchantId}/shipped',
                'status' => 'Shipped',
                'page_size' => (int) config('marketplace.hepsiburada.package_page_size', 50),
            ],
            [
                'path' => 'packages/merchantid/{merchantId}/delivered',
                'status' => 'Delivered',
                'page_size' => (int) config('marketplace.hepsiburada.package_page_size', 50),
            ],
            [
                'path' => 'packages/merchantid/{merchantId}/undelivered',
                'status' => 'Undelivered',
                'page_size' => (int) config('marketplace.hepsiburada.package_page_size', 50),
            ],
            [
                'path' => 'packages/merchantid/{merchantId}/missing-invoice',
                'status' => 'MissingInvoice',
                'page_size' => (int) config('marketplace.hepsiburada.package_page_size', 50),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function dateWindowQuery(CarbonImmutable $startDate, CarbonImmutable $endDate): array
    {
        return [
            'beginDate' => $startDate->format('Y-m-d H:i:s'),
            'endDate' => $endDate->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param  array<string, mixed>  $detailPayload
     * @param  array<string, mixed>  $summaryPayload
     * @return array<string, mixed>
     */
    protected function normalizeOrderPackage(array $detailPayload, array $summaryPayload): array
    {
        $orderNumber = trim((string) ($this->summaryOrderNumber($summaryPayload) ?: data_get($detailPayload, 'orderNumber') ?: data_get($detailPayload, 'merchantOrderNumber') ?: ''));
        $packageId = trim((string) ($this->summaryPackageId($summaryPayload) ?: data_get($detailPayload, 'packageNumber') ?: data_get($detailPayload, 'packageId') ?: data_get($detailPayload, 'id') ?: $orderNumber));
        $status = (string) (data_get($summaryPayload, 'status') ?: data_get($detailPayload, 'status') ?: data_get($summaryPayload, '__status_hint') ?: 'Created');

        $detailItems = collect(
            data_get($detailPayload, 'items')
            ?: data_get($detailPayload, 'lineItems')
            ?: data_get($detailPayload, 'lines')
            ?: data_get($detailPayload, 'products')
            ?: []
        );

        return [
            'order' => [
                'external_order_id' => $orderNumber,
                'order_number' => $orderNumber,
                'order_status' => $status,
                'commercial_type' => filled(data_get($detailPayload, 'billingAddress.companyName')) ? 'commercial' : 'individual',
                'customer_name' => trim((string) (
                    data_get($detailPayload, 'customerName')
                    ?: collect([
                        data_get($detailPayload, 'customer.firstName'),
                        data_get($detailPayload, 'customer.lastName'),
                    ])->filter()->implode(' ')
                )),
                'customer_email' => data_get($detailPayload, 'customerEmail') ?: data_get($detailPayload, 'customer.email'),
                'customer_phone' => data_get($detailPayload, 'customerPhone') ?: data_get($detailPayload, 'shippingAddress.phone'),
                'billing_name' => data_get($detailPayload, 'billingAddress.companyName') ?: data_get($detailPayload, 'billingAddress.fullName'),
                'billing_tax_number' => data_get($detailPayload, 'billingAddress.taxNumber') ?: data_get($detailPayload, 'billingTaxNumber'),
                'shipment_country' => data_get($detailPayload, 'shippingAddress.country') ?: 'TR',
                'shipment_city' => data_get($detailPayload, 'shippingAddress.city'),
                'shipment_district' => data_get($detailPayload, 'shippingAddress.town') ?: data_get($detailPayload, 'shippingAddress.district'),
                'ordered_at' => $this->normalizeDate(data_get($detailPayload, 'orderDate') ?: data_get($summaryPayload, 'orderDate') ?: data_get($summaryPayload, 'createdDate')),
                'approved_at' => $this->normalizeDate(data_get($detailPayload, 'approvedDate') ?: data_get($summaryPayload, 'approvedDate')),
                'delivered_at' => $this->normalizeDate(data_get($summaryPayload, 'deliveredDate') ?: data_get($detailPayload, 'deliveredDate')),
                'cancelled_at' => Str::contains(Str::lower($status), 'cancel') ? $this->normalizeDate(data_get($summaryPayload, 'lastUpdateDate') ?: data_get($detailPayload, 'cancelledDate')) : null,
                'returned_at' => Str::contains(Str::lower($status), 'return') ? $this->normalizeDate(data_get($summaryPayload, 'lastUpdateDate') ?: data_get($detailPayload, 'returnedDate')) : null,
                'raw_payload' => [
                    'summary' => $summaryPayload,
                    'detail' => $detailPayload,
                ],
            ],
            'package' => [
                'external_package_id' => $packageId,
                'package_number' => $packageId,
                'package_status' => $status,
                'cargo_company' => data_get($summaryPayload, 'cargoCompany') ?: data_get($detailPayload, 'cargoCompany'),
                'cargo_tracking_number' => data_get($summaryPayload, 'trackingNumber') ?: data_get($detailPayload, 'trackingNumber'),
                'cargo_barcode' => data_get($summaryPayload, 'barcode') ?: data_get($detailPayload, 'barcode'),
                'cargo_desi' => $this->toDecimal(data_get($summaryPayload, 'desi') ?: data_get($detailPayload, 'desi')),
                'shipment_provider' => data_get($summaryPayload, 'shipmentMethod') ?: data_get($detailPayload, 'shipmentMethod'),
                'shipped_at' => $this->normalizeDate(data_get($summaryPayload, 'shippedDate') ?: data_get($detailPayload, 'shippedDate')),
                'delivered_at' => $this->normalizeDate(data_get($summaryPayload, 'deliveredDate') ?: data_get($detailPayload, 'deliveredDate')),
                'raw_payload' => $summaryPayload,
            ],
            'items' => $detailItems
                ->values()
                ->map(fn (array $row, int $index) => $this->normalizeOrderLine($row, $summaryPayload, $index))
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $summaryPayload
     * @return array<string, mixed>
     */
    protected function normalizeOrderLine(array $payload, array $summaryPayload, int $index): array
    {
        $quantity = (int) (data_get($payload, 'quantity') ?: data_get($payload, 'adet') ?: 1);
        $unitPrice = $this->toDecimal(
            data_get($payload, 'price')
            ?: data_get($payload, 'unitPrice')
            ?: data_get($payload, 'totalPrice.value')
        );
        $grossAmount = $this->toDecimal(data_get($payload, 'totalPrice.value') ?: data_get($payload, 'grossAmount')) ?: ($unitPrice ? round($unitPrice * $quantity, 2) : null);
        $discountAmount = $this->toDecimal(data_get($payload, 'discountAmount') ?: data_get($payload, 'merchantDiscount'));
        $marketplaceDiscount = $this->toDecimal(data_get($payload, 'hepsiburadaDiscount') ?: data_get($payload, 'hbDiscount'));

        $fallbackLineId = sha1(implode('|', [
            (string) ($this->summaryPackageId($summaryPayload) ?: 'HB'),
            (string) ($this->summaryOrderNumber($summaryPayload) ?: 'HB'),
            (string) (data_get($payload, 'merchantSku') ?: data_get($payload, 'sku') ?: $index),
            (string) (data_get($payload, 'barcode') ?: ''),
            (string) $quantity,
        ]));

        return [
            'external_line_id' => (string) (data_get($payload, 'lineItemId') ?: data_get($payload, 'itemId') ?: data_get($payload, 'id') ?: $fallbackLineId),
            'stock_code' => data_get($payload, 'merchantSku') ?: data_get($payload, 'stockCode') ?: data_get($payload, 'sku'),
            'barcode' => data_get($payload, 'barcode'),
            'product_name' => data_get($payload, 'productName') ?: data_get($payload, 'name'),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'gross_amount' => $grossAmount,
            'discount_amount' => $discountAmount,
            'marketplace_discount_amount' => $marketplaceDiscount,
            'billable_amount' => $grossAmount !== null
                ? round($grossAmount - ($discountAmount ?? 0) - ($marketplaceDiscount ?? 0), 2)
                : null,
            'commission_rate' => $this->toDecimal(data_get($payload, 'commissionRate')),
            'vat_rate' => $this->toDecimal(data_get($payload, 'vatRate') ?: data_get($payload, 'taxRate')),
            'line_status' => (string) (data_get($payload, 'status') ?: data_get($summaryPayload, 'status') ?: 'Created'),
            'raw_payload' => $payload,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizeProduct(array $payload): array
    {
        $stockCode = data_get($payload, 'merchantSku') ?: data_get($payload, 'stockCode') ?: data_get($payload, 'sku');
        $barcode = data_get($payload, 'barcode') ?: data_get($payload, 'gtin');
        $externalProductId = (string) (data_get($payload, 'merchantSku') ?: data_get($payload, 'hepsiburadaSku') ?: data_get($payload, 'sku') ?: $barcode ?: '');
        $listingId = (string) (data_get($payload, 'hepsiburadaSku') ?: data_get($payload, 'sku') ?: $externalProductId);
        $status = $this->normalizeListingStatus($payload);

        return [
            'product' => [
                'external_product_id' => $externalProductId,
                'external_parent_id' => (string) (data_get($payload, 'groupCode') ?: data_get($payload, 'variantGroupCode') ?: ''),
                'stock_code' => $stockCode,
                'barcode' => $barcode,
                'title' => data_get($payload, 'productName') ?: data_get($payload, 'name'),
                'brand' => data_get($payload, 'brand'),
                'category_name' => data_get($payload, 'categoryName'),
                'vat_rate' => $this->toDecimal(data_get($payload, 'vatRate') ?: data_get($payload, 'taxRate')),
                'raw_payload' => $payload,
            ],
            'listing' => array_merge([
                'listing_id' => $listingId,
                'listing_status' => $status,
                'sale_price' => $this->toDecimal(data_get($payload, 'price.finalPrice') ?: data_get($payload, 'finalPrice') ?: data_get($payload, 'salePrice') ?: data_get($payload, 'price')),
                'list_price' => $this->toDecimal(data_get($payload, 'price.listPrice') ?: data_get($payload, 'listPrice')),
                'commission_rate' => $this->toDecimal(data_get($payload, 'commissionRate') ?: data_get($payload, 'commission.rate') ?: data_get($payload, 'commission')),
                'commission_source' => 'catalog',
                'currency' => data_get($payload, 'price.currency') ?: data_get($payload, 'currency') ?: 'TRY',
                'stock_quantity' => (int) (data_get($payload, 'availableStock') ?: data_get($payload, 'stock') ?: 0),
                'published_at' => $this->normalizeDate(data_get($payload, 'createdAt') ?: data_get($payload, 'creationDate') ?: data_get($payload, 'lastModifiedDate')),
            ], $this->catalogDeliveryTermData($payload)),
        ];
    }

    protected function normalizeListingStatus(array $payload): string
    {
        if (data_get($payload, 'isActive') === true || data_get($payload, 'active') === true || data_get($payload, 'isSalable') === true) {
            return 'active';
        }

        if (data_get($payload, 'isDeleted') === true) {
            return 'deleted';
        }

        if (filled(data_get($payload, 'status'))) {
            return (string) data_get($payload, 'status');
        }

        return 'inactive';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeFinancialEvents(array $payload): array
    {
        $eventTypeRaw = (string) (data_get($payload, 'transactionType') ?: data_get($payload, 'type') ?: data_get($payload, 'eventType') ?: 'transaction');
        $eventType = $this->normalizeFinancialType($eventTypeRaw);
        $amount = $this->toDecimal(data_get($payload, 'amount') ?: data_get($payload, 'totalAmount') ?: data_get($payload, 'netAmount'));

        if ($amount === null || round($amount, 2) === 0.0) {
            return [];
        }

        $direction = in_array($eventType, ['commission', 'cargo', 'service_fee', 'withholding'], true)
            ? 'debit'
            : 'credit';

        return [[
            'event_source' => 'transactions',
            'event_type' => $eventType,
            'external_event_id' => (string) (
                data_get($payload, 'id')
                ?: data_get($payload, 'transactionId')
                ?: sha1(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $eventTypeRaw)
            ),
            'order_number' => (string) (data_get($payload, 'orderNumber') ?: data_get($payload, 'merchantOrderNumber') ?: ''),
            'external_package_id' => (string) (data_get($payload, 'packageNumber') ?: data_get($payload, 'packageId') ?: ''),
            'external_line_id' => (string) (data_get($payload, 'lineItemId') ?: data_get($payload, 'itemId') ?: ''),
            'stock_code' => data_get($payload, 'merchantSku') ?: data_get($payload, 'stockCode'),
            'barcode' => data_get($payload, 'barcode'),
            'reference_number' => (string) (data_get($payload, 'referenceNumber') ?: data_get($payload, 'invoiceNumber') ?: ''),
            'event_date' => $this->normalizeDate(data_get($payload, 'transactionDate') ?: data_get($payload, 'createdAt')),
            'due_date' => $this->normalizeDate(data_get($payload, 'dueDate')),
            'settlement_date' => $this->normalizeDate(data_get($payload, 'paymentDate') ?: data_get($payload, 'settlementDate')),
            'amount' => abs($amount),
            'currency' => data_get($payload, 'currency') ?: 'TRY',
            'direction' => $direction,
            'status' => (string) (data_get($payload, 'status') ?: 'posted'),
            'notes' => $eventTypeRaw,
            'raw_payload' => $payload,
        ]];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizeHepsiburadaQuestion(array $payload): array
    {
        return $this->normalizeQuestionPayload($payload, [
            'external_question_id' => (string) (
                data_get($payload, 'issueNumber')
                ?: data_get($payload, 'id')
                ?: data_get($payload, 'questionId')
            ),
            'question_type' => $this->hepsiburadaQuestionType($payload),
            'question_text' => $this->questionTextValue(
                data_get($payload, 'question')
                ?: data_get($payload, 'message')
                ?: data_get($payload, 'description')
                ?: data_get($payload, 'subject')
            ),
            'product_name' => data_get($payload, 'product.name')
                ?: data_get($payload, 'productName')
                ?: data_get($payload, 'productTitle'),
            'product_sku' => data_get($payload, 'merchantSku')
                ?: data_get($payload, 'sku')
                ?: data_get($payload, 'product.sku'),
            'product_url' => data_get($payload, 'product.url')
                ?: data_get($payload, 'productUrl'),
            'order_number' => data_get($payload, 'orderNumber')
                ?: data_get($payload, 'merchantOrderNumber')
                ?: data_get($payload, 'order.number')
                ?: data_get($payload, 'packageNumber')
                ?: data_get($payload, 'orderId'),
            'asked_at' => $this->questionDate(
                data_get($payload, 'createdAt')
                ?: data_get($payload, 'creationDate')
                ?: data_get($payload, 'createdDate')
            ),
            'expires_at' => $this->questionDate(data_get($payload, 'expireDate')),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function hepsiburadaQuestionType(array $payload): string
    {
        if (filled(
            data_get($payload, 'orderNumber')
            ?: data_get($payload, 'merchantOrderNumber')
            ?: data_get($payload, 'order.number')
            ?: data_get($payload, 'packageNumber')
            ?: data_get($payload, 'orderId')
        )) {
            return 'order';
        }

        $type = Str::lower((string) (
            data_get($payload, 'issueType')
            ?: data_get($payload, 'type')
            ?: data_get($payload, 'category')
        ));

        return Str::contains($type, ['order', 'sipariş', 'shipment', 'delivery', 'cargo', 'kargo'])
            ? 'order'
            : 'product';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizeClaim(array $payload): array
    {
        $claimNumber = (string) (
            data_get($payload, 'claimNumber')
            ?: data_get($payload, 'number')
            ?: data_get($payload, 'id')
            ?: data_get($payload, 'claimId')
            ?: ''
        );

        return [
            'external_claim_id' => $claimNumber,
            'claimNumber' => $claimNumber,
            'order_number' => data_get($payload, 'orderNumber') ?: data_get($payload, 'merchantOrderNumber'),
            'cargo_tracking_number' => data_get($payload, 'trackingNumber') ?: data_get($payload, 'cargoTrackingNumber'),
            'cargo_provider' => data_get($payload, 'cargoCompany') ?: data_get($payload, 'cargoProviderName'),
            'status' => data_get($payload, 'status') ?: data_get($payload, 'claimStatus') ?: data_get($payload, 'state'),
            'type' => data_get($payload, 'claimType') ?: data_get($payload, 'type') ?: 'return',
            'reason' => data_get($payload, 'reason') ?: data_get($payload, 'claimReason'),
            'reason_detail' => data_get($payload, 'reasonDetail') ?: data_get($payload, 'description'),
            'customer_note' => data_get($payload, 'customerNote') ?: data_get($payload, 'description'),
            'customer_name' => data_get($payload, 'customerName') ?: data_get($payload, 'customer.fullName'),
            'created_date' => data_get($payload, 'createdAt') ?: data_get($payload, 'createdDate') ?: data_get($payload, 'claimDate'),
            'items' => collect(Arr::wrap(
                data_get($payload, 'items')
                ?: data_get($payload, 'claimItems')
                ?: data_get($payload, 'products')
                ?: []
            ))
                ->filter(fn ($row) => is_array($row))
                ->values()
                ->all(),
            'raw_payload' => $payload,
        ];
    }

    protected function claimActionPath(string $configKey, string $defaultPath, string $claimNumber): string
    {
        $path = trim((string) config('marketplace.hepsiburada.'.$configKey, $defaultPath), '/');

        return str_replace(
            ['{claimNumber}', '{claim_number}', '{number}', '{id}'],
            $claimNumber,
            $path
        );
    }

    protected function normalizeFinancialType(string $type): string
    {
        $normalized = Str::lower($type);

        return match (true) {
            Str::contains($normalized, 'commission') => 'commission',
            Str::contains($normalized, ['cargo', 'shipping', 'shipment']) => 'cargo',
            Str::contains($normalized, ['withholding', 'stopaj']) => 'withholding',
            Str::contains($normalized, ['service', 'fee']) => 'service_fee',
            Str::contains($normalized, ['payment', 'payout', 'seller']) => 'seller_revenue',
            default => 'other',
        };
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function normalizeLabelResponse(
        ChannelOrderPackage $package,
        Response $response,
        array $context,
        string $operation,
        string $packageNumber,
    ): array {
        $contentType = Str::lower((string) $response->header('Content-Type', 'application/octet-stream'));
        $body = $response->body();

        $result = [
            'status' => 'completed',
            'provider' => $this->providerKey(),
            'package_id' => $package->id,
            'package_external_id' => $package->external_package_id,
            'package_number' => $packageNumber,
            'label_operation' => $operation,
            'label_format' => (string) ($context['format'] ?? 'AUTO'),
            'response_status' => $response->status(),
            'label_content_type' => $contentType,
            'label_size_bytes' => strlen($body),
            'label_available' => $body !== '',
            'external_action_id' => (string) ($response->header('X-Request-Id') ?: ''),
        ];

        if (Str::contains($contentType, ['json', 'xml'])) {
            $decoded = $this->decodeResponse($response);

            return $result + [
                'response' => $decoded,
                'label_count' => is_array(data_get($decoded, 'data')) ? count(data_get($decoded, 'data')) : null,
            ];
        }

        if (Str::contains($contentType, ['text/', 'zpl', 'plain'])) {
            $textPayload = trim($body);

            return $result + [
                'response' => [
                    'text_preview' => Str::limit($textPayload, 5000, '...'),
                ],
            ];
        }

        return $result + [
            'response' => [
                'binary_preview_base64' => base64_encode(substr($body, 0, 256)),
                'binary_truncated' => strlen($body) > 256,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, mixed>
     */
    protected function extractItems(array $payload): array
    {
        foreach (['items', 'data', 'content', 'listings', 'transactions', 'packages', 'orders', 'claims'] as $key) {
            $candidate = data_get($payload, $key);

            if (is_array($candidate) && array_is_list($candidate)) {
                return $candidate;
            }
        }

        return array_is_list($payload) ? $payload : [];
    }

    protected function extractTotalCount(array $payload): ?int
    {
        foreach (['totalCount', 'total_count', 'count', 'total'] as $key) {
            $value = data_get($payload, $key);

            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        return null;
    }

    protected function summaryOrderNumber(array $payload): ?string
    {
        return data_get($payload, 'orderNumber') ?: data_get($payload, 'merchantOrderNumber');
    }

    protected function summaryPackageId(array $payload): ?string
    {
        return data_get($payload, 'packageNumber')
            ?: data_get($payload, 'packageId')
            ?: data_get($payload, 'merchantPackageNumber')
            ?: data_get($payload, 'id');
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

        $decoded = json_decode($body, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        if (str_starts_with($body, '<')) {
            try {
                $xml = simplexml_load_string($body, \SimpleXMLElement::class, LIBXML_NOCDATA);

                if ($xml !== false) {
                    $array = json_decode(json_encode($xml, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), true);

                    return is_array($array) ? $array : ['raw' => $body];
                }
            } catch (\Throwable) {
                // XML cevabi parse edilemezse raw olarak don.
            }
        }

        return ['raw' => $body];
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

    protected function merchantSku(\App\Models\ChannelListing $listing): ?string
    {
        $merchantSku = trim((string) (
            $listing->channelProduct?->stock_code
            ?: data_get($listing->channelProduct?->raw_payload, 'merchantSku')
            ?: data_get($listing->channelProduct?->raw_payload, 'stockCode')
        ));

        return $merchantSku !== '' ? $merchantSku : null;
    }

    protected function hepsiburadaSku(\App\Models\ChannelListing $listing): ?string
    {
        $sku = trim((string) (
            $listing->listing_id
            ?: data_get($listing->channelProduct?->raw_payload, 'hepsiburadaSku')
            ?: data_get($listing->channelProduct?->raw_payload, 'sku')
        ));

        return $sku !== '' ? $sku : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function buildListingUploadXml(array $rows): string
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><listings></listings>');

        foreach ($rows as $row) {
            $listing = $xml->addChild('listing');

            foreach ($row as $key => $value) {
                $listing->addChild($key, htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
            }
        }

        $content = $xml->asXML();

        if ($content === false) {
            throw new \RuntimeException('Hepsiburada XML payload olusturulamadi.');
        }

        return $content;
    }

    protected function xmlDecimal(float $value): string
    {
        return number_format(round($value, 2), 2, '.', '');
    }

    // =========================================================================
    // P0 — Salt-Okuma Genişletme Metodları
    // NOT: Aşağıdaki endpoint URL'leri not_verified durumdadır.
    // Hepsiburada developer portal SPA olduğundan statik olarak taranamadı.
    // Gerçek URL doğrulaması için Hepsiburada hesabına erişim gerekir.
    // =========================================================================

    /**
     * Hepsiburada kategori ağacını getirir.
     *
     * Resmi doğrulanmış endpoint: product/api/categories/get-all-categories
     *
     * @param  array<string, mixed>  $options
     * @return array<int, array<string, mixed>>
     */
    public function getCategories(MarketplaceStore $store, array $options = []): array
    {
        if (!config('marketplace.hepsiburada.p0_reference_sync_enabled', false)) {
            throw new \RuntimeException('Hepsiburada reference sync is disabled (HEPSIBURADA_P0_REFERENCE_SYNC_ENABLED=false).');
        }

        $response = $this->request($store, 'product')
            ->get('product/api/categories/get-all-categories')
            ->throw();

        $payload = $this->decodeResponse($response);

        $items = $this->extractItems($payload);

        if (empty($items) && array_is_list($payload)) {
            $items = $payload;
        }

        return $items;
    }

    /**
     * Belirtilen kategori için attribute sözlüğünü getirir.
     *
     * Resmi doğrulanmış endpoint: product/api/categories/{id}/attributes
     *
     * @param  array<string, mixed>  $options
     * @return array{attributes: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function getCategoryAttributes(MarketplaceStore $store, string $categoryId, array $options = []): array
    {
        if (!config('marketplace.hepsiburada.p0_reference_sync_enabled', false)) {
            throw new \RuntimeException('Hepsiburada reference attributes sync is disabled (HEPSIBURADA_P0_REFERENCE_SYNC_ENABLED=false).');
        }

        if ($categoryId === '') {
            throw new \RuntimeException('Hepsiburada attribute çekimi için kategori ID zorununludur.');
        }

        $response = $this->request($store, 'product')
            ->get("product/api/categories/{$categoryId}/attributes")
            ->throw();

        $payload = $this->decodeResponse($response);
        $rawAttributes = $this->extractItems($payload);

        $attributes = [];

        foreach ($rawAttributes as $row) {
            if (!is_array($row)) {
                continue;
            }

            $attributeId = (string) (
                data_get($row, 'id')
                ?: data_get($row, 'attributeId')
                ?: data_get($row, 'attribute_id')
                ?: ''
            );

            if ($attributeId === '') {
                continue;
            }

            $values = collect(
                data_get($row, 'attributeValues')
                ?: data_get($row, 'values')
                ?: data_get($row, 'options')
                ?: []
            )
                ->filter(fn ($v) => is_array($v))
                ->map(fn (array $v) => [
                    'platform_value_id' => (string) (data_get($v, 'id') ?: data_get($v, 'valueId') ?: ''),
                    'name'              => (string) (data_get($v, 'name') ?: data_get($v, 'label') ?: ''),
                    'raw_payload'       => $v,
                ])
                ->filter(fn ($v) => $v['platform_value_id'] !== '')
                ->values()
                ->all();

            $attributes[] = [
                'platform_attribute_id' => $attributeId,
                'name'                  => (string) (data_get($row, 'name') ?: data_get($row, 'attributeName') ?: ''),
                'is_required'           => (bool) (data_get($row, 'mandatory') ?? data_get($row, 'required') ?? false),
                'is_variant'            => (bool) (data_get($row, 'varianter') ?? data_get($row, 'isVariant') ?? false),
                'is_multi_select'       => (bool) (data_get($row, 'multipleSelect') ?? data_get($row, 'isMultiSelect') ?? false),
                'data_type'             => (string) (data_get($row, 'allowedDataType') ?? data_get($row, 'dataType') ?? ''),
                'values'                => $values,
                'raw_payload'           => $row,
            ];
        }

        return [
            'attributes'        => $attributes,
            'meta'              => [
                'category_id'       => $categoryId,
                'attributes_count'  => count($attributes),
                'endpoint_verified' => true,
            ],
        ];
    }

    /**
     * Hepsiburada marka listesi API'si — doğrulanamadı.
     *
     * capabilities()['reference_brands_pull'] => false olarak işaretlenmiştir.
     * MarketplaceReferenceSyncService::syncBrands() bu metodu method_exists() ile
     * kontrol ettiğinden, capability false olduğu sürece çağrılmaz.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getBrands(MarketplaceStore $store, int $page = 0, int $size = 500): array
    {
        // not_available_via_api: Hepsiburada marka listesi endpoint'i
        // genel entegratör dokümanında yer almıyor veya doğrulanamadı.
        throw new \RuntimeException(
            "Hepsiburada marka listesi API'si mevcut değil veya doğrulanamadı. "
            . 'reference_brands_pull capability false olarak işaretlenmiştir.'
        );
    }

    /**
     * Hepsiburada tam katalog ürünlerini getirir (listing değil, katalog içeriği).
     *
     * pullProducts() satıcı listing endpoint'ini çekerken bu metod gerçek
     * katalog ürün içeriğini (açıklama, görseller, özellikler, onay durumu) getirir.
     *
     * Resmi doğrulanmış endpoint: product/api/products/all-products-of-merchant/{merchantId}
     *
     * @param  array<string, mixed>  $options
     * @return array{items: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function pullCatalogProducts(MarketplaceStore $store, array $options = []): array
    {
        if (!config('marketplace.hepsiburada.p0_catalog_sync_enabled', false)) {
            throw new \RuntimeException('Hepsiburada catalog sync is disabled (HEPSIBURADA_P0_CATALOG_SYNC_ENABLED=false).');
        }

        $isSmoke = (bool) ($options['smoke_mode'] ?? false);
        $maxItems = (int) ($options['max_items'] ?? 50);
        $pageSize = $isSmoke ? min($maxItems, 5) : (int) config('marketplace.hepsiburada.catalog_product_page_size', 50);
        $maxPages = $isSmoke ? 1 : (int) ($options['max_pages'] ?? config('marketplace.hepsiburada.max_pages_per_request', 50));

        $items = $this->fetchPaginated(
            store: $store,
            service: 'product',
            path: 'product/api/products/all-products-of-merchant/' . $this->merchantId($store),
            query: array_filter([
                'status' => $options['status'] ?? null,
            ], fn ($v) => $v !== null && $v !== ''),
            pageSize: $pageSize,
            maxPagesOverride: $maxPages,
        );

        return [
            'items' => collect($items)
                ->map(fn (array $payload) => $this->normalizeCatalogProduct($payload))
                ->values()
                ->all(),
            'meta'  => [
                'items_received'       => count($items),
                'more_items_available' => count($items) >= $pageSize,
                'cursor_after'         => now()->toIso8601String(),
                'endpoint_verified'    => true,
            ],
        ];
    }

    /**
     * Daha önce gönderilmiş bir batch işlemin sonucunu sorgular (salt-okuma).
     *
     * Endpoint connector kodundan kanıtlandı:
     * pushPrice/pushStock metodları içindeki polling_endpoint değerinden türetildi.
     *
     * @param  string  $operation  'price-uploads' | 'stock-uploads'
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function pullBatchStatus(
        MarketplaceStore $store,
        string $batchRequestId,
        string $operation,
        array $options = []
    ): array {
        if (!config('marketplace.hepsiburada.p0_batch_status_sync_enabled', false)) {
            throw new \RuntimeException('Hepsiburada batch status sync is disabled (HEPSIBURADA_P0_BATCH_STATUS_SYNC_ENABLED=false).');
        }

        if (!in_array($operation, ['price-uploads', 'stock-uploads'], true)) {
            throw new \InvalidArgumentException(
                "Geçersiz batch operation: {$operation}. Desteklenenler: price-uploads, stock-uploads"
            );
        }

        if ($batchRequestId === '') {
            throw new \RuntimeException('Batch request ID boş olamaz.');
        }

        // Endpoint kanıtı: pushPrice() içinde polling_endpoint olarak belgelenmiş:
        // listings/merchantid/{merchantId}/price-uploads/id/{id}
        $path = 'listings/merchantid/' . $this->merchantId($store) . "/{$operation}/id/{$batchRequestId}";

        $response = $this->request($store, 'listing')
            ->get($path)
            ->throw();

        $payload = $this->decodeResponse($response);

        return [
            'batch_request_id' => $batchRequestId,
            'operation'        => $operation,
            'status'           => (string) (
                data_get($payload, 'status')
                ?: data_get($payload, 'State')
                ?: data_get($payload, 'state')
                ?: 'unknown'
            ),
            'success_count'    => (int) (data_get($payload, 'successCount') ?? data_get($payload, 'successfulCount') ?? 0),
            'failure_count'    => (int) (data_get($payload, 'failureCount') ?? data_get($payload, 'failedCount') ?? 0),
            'items'            => collect(
                data_get($payload, 'items')
                ?: data_get($payload, 'results')
                ?: data_get($payload, 'errors')
                ?: []
            )->all(),
            'raw_payload'      => $payload,
        ];
    }

    /**
     * Katalog ürün payload'ını normalize eder.
     *
     * pullProducts() (listing) ile bu metod (katalog) arasındaki fark:
     * - Katalog: açıklama, görseller, özellikler, onay/red durumu, is_catalog_product=true
     * - Listing: fiyat, stok, aktiflik durumu, is_catalog_product=false
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizeCatalogProduct(array $payload): array
    {
        $stockCode = data_get($payload, 'merchantSku') ?: data_get($payload, 'stockCode') ?: data_get($payload, 'sku');
        $barcode   = data_get($payload, 'barcode') ?: data_get($payload, 'gtin');

        $images = collect(
            data_get($payload, 'images') ?: data_get($payload, 'productImages') ?: []
        )
            ->filter(fn ($v) => is_array($v) || is_string($v))
            ->map(fn ($v) => is_string($v) ? ['url' => $v] : $v)
            ->values()
            ->all();

        $attributes = collect(
            data_get($payload, 'attributes') ?: data_get($payload, 'productAttributes') ?: []
        )
            ->filter(fn ($v) => is_array($v))
            ->values()
            ->all();

        $rejectionReasons = collect(
            data_get($payload, 'rejectionReasonList') ?: data_get($payload, 'rejectionReasons') ?: []
        )
            ->filter()
            ->values()
            ->all();

        return [
            'external_product_id'   => (string) (
                data_get($payload, 'hepsiburadaSku')
                ?: data_get($payload, 'merchantSku')
                ?: data_get($payload, 'sku')
                ?: $barcode
                ?: ''
            ),
            'external_parent_id'    => (string) (
                data_get($payload, 'groupCode') ?: data_get($payload, 'variantGroupCode') ?: ''
            ),
            'stock_code'            => $stockCode,
            'barcode'               => $barcode,
            'title'                 => data_get($payload, 'productName') ?: data_get($payload, 'name'),
            'description'           => data_get($payload, 'description') ?: data_get($payload, 'productDescription'),
            'brand'                 => data_get($payload, 'brand'),
            'category_name'         => data_get($payload, 'categoryName'),
            'vat_rate'              => $this->toDecimal(data_get($payload, 'vatRate') ?: data_get($payload, 'taxRate')),
            'images'                => $images ?: null,
            'attributes'            => $attributes ?: null,
            'approval_status'       => (string) (
                data_get($payload, 'productStatus')
                ?: data_get($payload, 'catalogStatus')
                ?: data_get($payload, 'status')
                ?: ''
            ) ?: null,
            'rejection_reasons'     => $rejectionReasons ?: null,
            'import_tracking_id'    => (string) (
                data_get($payload, 'trackingId') ?: data_get($payload, 'importId') ?: ''
            ) ?: null,
            'is_catalog_product'    => true,
            'raw_payload'           => $payload,
        ];
    }
}
