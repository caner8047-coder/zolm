<?php

namespace App\Services\Marketplace\Connectors;

use App\Models\ChannelListing;
use App\Models\MarketplaceQuestion;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\Connectors\Concerns\NormalizesCustomerQuestions;
use App\Services\Marketplace\Contracts\AnswersCustomerQuestions;
use App\Services\Marketplace\Contracts\ManagesClaims;
use App\Services\Marketplace\Contracts\PullsClaims;
use App\Services\Marketplace\Contracts\PullsCustomerQuestions;
use App\Services\Marketplace\Contracts\PullsFinancials;
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

class N11Connector extends AbstractMarketplaceConnector implements AnswersCustomerQuestions, ManagesClaims, PullsClaims, PullsCustomerQuestions, PullsFinancials, PullsOrders, PullsProducts, PushesPrice, PushesStock, TestsConnection
{
    use NormalizesCustomerQuestions;

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
            'finance' => true,
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
            'questions' => true,
            'question_answer' => true,
            'claims' => true,
            'claim_approve' => true,
            'claim_reject' => true,
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

    public function pullFinancialEvents(MarketplaceStore $store, array $options = []): array
    {
        $page = 0;
        $pageSize = min(100, max(1, (int) ($options['page_size'] ?? config('marketplace.n11.finance_page_size', 100))));
        $maxPages = max(1, (int) config('marketplace.n11.max_finance_pages_per_sync', 20));
        $startDate = CarbonImmutable::parse($options['start_date'] ?? now()->subDays(7))->setTimezone('Europe/Istanbul');
        $endDate = CarbonImmutable::parse($options['end_date'] ?? now())->setTimezone('Europe/Istanbul');
        $items = [];

        do {
            $response = $this->settlementSoapCall($store, 'GetSettlementList', [
                'startDate' => $startDate->format('d/m/Y'),
                'endDate' => $endDate->format('d/m/Y'),
                'pagingData' => [
                    'currentPage' => $page,
                    'pageSize' => $pageSize,
                ],
            ]);

            $rows = $this->settlementRowsFromPayload($response);

            foreach ($rows as $row) {
                $items[] = $this->normalizeSettlementEvent($row);
            }

            $totalPages = (int) (
                data_get($response, 'pagingData.pageCount')
                ?: data_get($response, 'pagingData.totalPages')
                ?: data_get($response, 'pageCount')
                ?: 1
            );
            $page++;
        } while ($rows !== [] && $page < min(max(1, $totalPages), $maxPages));

        return [
            'items' => $items,
            'meta' => [
                'items_received' => count($items),
                'cursor_after' => $endDate->toIso8601String(),
                'pages_processed' => $page,
                'more_pages_available' => $page < ($totalPages ?? 1),
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

    public function pullCustomerQuestions(MarketplaceStore $store, array $options = []): array
    {
        $page = 0;
        $pageSize = min(100, max(1, (int) ($options['page_size'] ?? config('marketplace.n11.question_page_size', 50))));
        $maxPages = max(1, (int) config('marketplace.n11.max_question_pages_per_sync', 10));
        $startDate = CarbonImmutable::parse($options['start_date'] ?? now()->subDays(7))->setTimezone('Europe/Istanbul');
        $endDate = CarbonImmutable::parse($options['end_date'] ?? now())->setTimezone('Europe/Istanbul');
        $items = [];

        do {
            $response = $this->soapCall($store, 'GetProductQuestionList', [
                'productQuestionSearch' => array_filter([
                    'status' => $this->n11QuestionStatus($options['status'] ?? 'OPEN'),
                    'startDate' => $startDate->format('d/m/Y'),
                    'endDate' => $endDate->format('d/m/Y'),
                    'productId' => $options['product_id'] ?? null,
                    'subject' => $options['subject'] ?? null,
                    'buyerEmail' => $options['buyer_email'] ?? null,
                ], fn ($value) => $value !== null && $value !== ''),
                'pagingData' => [
                    'currentPage' => $page,
                    'pageSize' => $pageSize,
                ],
            ]);

            $rows = $this->questionRowsFromPayload($response, [
                'productQuestions.productQuestion',
                'productQuestionList.productQuestion',
                'questions.question',
            ]);

            foreach ($rows as $row) {
                $items[] = $this->normalizeN11Question($row);
            }

            $totalPages = (int) (
                data_get($response, 'pagingData.pageCount')
                ?: data_get($response, 'pagingData.totalPages')
                ?: data_get($response, 'pageCount')
                ?: 1
            );
            $page++;
        } while ($rows !== [] && $page < min(max(1, $totalPages), $maxPages));

        return [
            'items' => $items,
            'meta' => [
                'items_received' => count($items),
                'cursor_after' => $endDate->toIso8601String(),
                'pages_processed' => $page,
                'more_pages_available' => $page < ($totalPages ?? 1),
            ],
        ];
    }

    public function pullClaims(MarketplaceStore $store, array $options = []): array
    {
        $page = 0;
        $pageSize = min(100, max(1, (int) ($options['page_size'] ?? config('marketplace.n11.claim_page_size', 50))));
        $maxPages = max(1, (int) config('marketplace.n11.max_claim_pages_per_sync', 20));
        $startDate = CarbonImmutable::parse($options['start_date'] ?? now()->subDays(7))->setTimezone('Europe/Istanbul');
        $endDate = CarbonImmutable::parse($options['end_date'] ?? now())->setTimezone('Europe/Istanbul');
        $operation = (string) config('marketplace.n11.claim_list_operation', 'ClaimReturnList');
        $items = [];

        do {
            $response = $this->returnSoapCall($store, $operation, [
                'claimReturnSearch' => array_filter([
                    'status' => $options['status'] ?? null,
                    'startDate' => $startDate->format('d/m/Y'),
                    'endDate' => $endDate->format('d/m/Y'),
                    'orderNumber' => $options['order_number'] ?? null,
                ], fn ($value) => $value !== null && $value !== ''),
                'pagingData' => [
                    'currentPage' => $page,
                    'pageSize' => $pageSize,
                ],
            ]);

            $rows = $this->claimRowsFromPayload($response);

            foreach ($rows as $row) {
                $items[] = $this->normalizeClaim($row);
            }

            $totalPages = (int) (
                data_get($response, 'pagingData.pageCount')
                ?: data_get($response, 'pagingData.totalPages')
                ?: data_get($response, 'pageCount')
                ?: 1
            );
            $page++;
        } while ($rows !== [] && $page < min(max(1, $totalPages), $maxPages));

        return [
            'items' => $items,
            'meta' => [
                'items_received' => count($items),
                'cursor_after' => $endDate->toIso8601String(),
                'pages_processed' => $page,
                'more_pages_available' => $page < ($totalPages ?? 1),
            ],
        ];
    }

    public function approveClaim(MarketplaceStore $store, string $externalClaimId, array $context = []): array
    {
        $operation = (string) config('marketplace.n11.claim_approve_operation', 'ClaimReturnApprove');
        $response = $this->returnSoapCall($store, $operation, $this->claimActionPayload($externalClaimId, $context));

        return [
            'status' => 'approved',
            'message' => 'N11 iade onayı gönderildi.',
            'response' => $response,
        ];
    }

    public function rejectClaim(MarketplaceStore $store, string $externalClaimId, string $reason, array $context = []): array
    {
        $operation = (string) config('marketplace.n11.claim_reject_operation', 'ClaimReturnDeny');
        $response = $this->returnSoapCall($store, $operation, $this->claimActionPayload($externalClaimId, $context + [
            'reason' => $reason,
            'description' => $reason,
        ]));

        return [
            'status' => 'rejected',
            'message' => 'N11 iade reddi gönderildi.',
            'response' => $response,
        ];
    }

    public function answerCustomerQuestion(MarketplaceQuestion $question, string $answer): array
    {
        $question->loadMissing('store.connection');

        $response = $this->soapCall($question->store, 'SaveProductAnswer', [
            'productQuestionId' => $question->external_question_id,
            'answer' => $answer,
        ]);

        $status = Str::lower((string) (
            data_get($response, 'result.status')
            ?: data_get($response, 'status')
            ?: 'success'
        ));

        if (Str::contains($status, ['fail', 'error', 'rejected'])) {
            throw new \RuntimeException((string) (
                data_get($response, 'result.errorMessage')
                ?: data_get($response, 'errorMessage')
                ?: 'N11 soru cevabı gönderilemedi.'
            ));
        }

        return [
            'external_answer_id' => (string) (
                data_get($response, 'answerId')
                ?: data_get($response, 'productQuestionId')
                ?: $question->external_question_id
            ),
            'response' => $response,
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
                'ordered_at' => $this->resolveOrderCreatedAt($payload),
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
            'listing' => array_merge([
                'listing_id' => $externalProductId !== '' ? $externalProductId : $stockCode,
                'listing_status' => $this->normalizeListingStatus($payload),
                'sale_price' => $this->toDecimal(data_get($payload, 'salePrice')),
                'list_price' => $this->toDecimal(data_get($payload, 'listPrice')),
                'commission_rate' => $this->toDecimal(data_get($payload, 'commissionRate') ?: data_get($payload, 'commission_rate') ?: data_get($payload, 'commission')),
                'commission_source' => 'catalog',
                'currency' => $this->normalizeCurrency((string) (data_get($payload, 'currencyType') ?: 'TL')),
                'stock_quantity' => (int) (data_get($payload, 'quantity') ?: 0),
                'published_at' => $this->normalizeDate(data_get($payload, 'lastModifiedDate') ?: data_get($payload, 'createDate')),
            ], $this->catalogDeliveryTermData($payload)),
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

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    protected function soapCall(MarketplaceStore $store, string $operation, array $body): array
    {
        $xml = $this->buildSoapEnvelope($operation, [
            'auth' => [
                'appKey' => $this->appKey($store),
                'appSecret' => $this->appSecret($store),
            ],
            ...$body,
        ]);

        $response = Http::timeout((int) config('marketplace.n11.request_timeout', 45))
            ->withHeaders(['Content-Type' => 'text/xml; charset=utf-8'])
            ->withBody($xml, 'text/xml')
            ->post($this->soapUrl($store))
            ->throw();

        return $this->parseSoapResponse($response->body(), $operation);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    protected function settlementSoapCall(MarketplaceStore $store, string $operation, array $body): array
    {
        $xml = $this->buildSoapEnvelope($operation, [
            'auth' => [
                'appKey' => $this->appKey($store),
                'appSecret' => $this->appSecret($store),
            ],
            ...$body,
        ]);

        $response = Http::timeout((int) config('marketplace.n11.request_timeout', 45))
            ->withHeaders(['Content-Type' => 'text/xml; charset=utf-8'])
            ->withBody($xml, 'text/xml')
            ->post($this->settlementSoapUrl($store))
            ->throw();

        return $this->parseSoapResponse($response->body(), $operation);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    protected function returnSoapCall(MarketplaceStore $store, string $operation, array $body): array
    {
        $xml = $this->buildSoapEnvelope($operation, [
            'auth' => [
                'appKey' => $this->appKey($store),
                'appSecret' => $this->appSecret($store),
            ],
            ...$body,
        ]);

        $response = Http::timeout((int) config('marketplace.n11.request_timeout', 45))
            ->withHeaders(['Content-Type' => 'text/xml; charset=utf-8'])
            ->withBody($xml, 'text/xml')
            ->post($this->returnSoapUrl($store))
            ->throw();

        return $this->parseSoapResponse($response->body(), $operation);
    }

    protected function soapUrl(MarketplaceStore $store): string
    {
        $credentials = $store->connection?->credentials_encrypted ?? [];
        $url = trim((string) ($credentials['soap_url'] ?? config('marketplace.n11.soap_url', 'https://api.n11.com/ws/productService/')));

        if ($url === '') {
            throw new \RuntimeException('N11 soru servisi SOAP URL boş.');
        }

        return $url;
    }

    protected function settlementSoapUrl(MarketplaceStore $store): string
    {
        $credentials = $store->connection?->credentials_encrypted ?? [];
        $url = trim((string) ($credentials['settlement_soap_url'] ?? config('marketplace.n11.settlement_soap_url', 'https://api.n11.com/ws/SettlementService/')));

        if ($url === '') {
            throw new \RuntimeException('N11 mutabakat servisi SOAP URL boş.');
        }

        return $url;
    }

    protected function returnSoapUrl(MarketplaceStore $store): string
    {
        $credentials = $store->connection?->credentials_encrypted ?? [];
        $url = trim((string) ($credentials['return_soap_url'] ?? config('marketplace.n11.return_soap_url', 'https://api.n11.com/ws/ReturnService/')));

        if ($url === '') {
            throw new \RuntimeException('N11 iade servisi SOAP URL boş.');
        }

        return $url;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function buildSoapEnvelope(string $operation, array $payload): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:sch="http://www.n11.com/ws/schemas">'
            .'<soapenv:Header/>'
            .'<soapenv:Body>'
            .'<sch:'.$operation.'Request>'
            .$this->arrayToXml($payload)
            .'</sch:'.$operation.'Request>'
            .'</soapenv:Body>'
            .'</soapenv:Envelope>';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function arrayToXml(array $payload): string
    {
        $xml = '';

        foreach ($payload as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (is_array($value)) {
                if (array_is_list($value)) {
                    foreach ($value as $row) {
                        $xml .= '<'.$key.'>';
                        $xml .= is_array($row)
                            ? $this->arrayToXml($row)
                            : $this->xmlValue($row);
                        $xml .= '</'.$key.'>';
                    }

                    continue;
                }

                $xml .= '<'.$key.'>'.$this->arrayToXml($value).'</'.$key.'>';

                continue;
            }

            $xml .= '<'.$key.'>'.$this->xmlValue($value).'</'.$key.'>';
        }

        return $xml;
    }

    protected function xmlValue(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    /**
     * @return array<string, mixed>
     */
    protected function parseSoapResponse(string $body, string $operation): array
    {
        $cleaned = trim(preg_replace('/(<\/?)([A-Za-z0-9_]+):/', '$1', $body) ?: $body);
        $xml = simplexml_load_string($cleaned, 'SimpleXMLElement', LIBXML_NOCDATA);

        if ($xml === false) {
            throw new \RuntimeException('N11 SOAP cevabı okunamadı.');
        }

        $array = json_decode(json_encode($xml, JSON_UNESCAPED_UNICODE) ?: '[]', true) ?: [];
        $response = data_get($array, 'Body.'.$operation.'Response')
            ?: data_get($array, 'Body.'.$operation.'Result')
            ?: data_get($array, 'Body')
            ?: $array;

        return is_array($response) ? $response : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizeN11Question(array $payload): array
    {
        return $this->normalizeQuestionPayload($payload, [
            'external_question_id' => (string) (
                data_get($payload, 'productQuestionId')
                ?: data_get($payload, 'id')
                ?: data_get($payload, 'questionId')
            ),
            'question_type' => 'product',
            'question_text' => $this->questionTextValue(
                data_get($payload, 'question')
                ?: data_get($payload, 'subject')
                ?: data_get($payload, 'questionText')
            ),
            'status' => $this->normalizeQuestionStatus((string) (
                data_get($payload, 'status')
                ?: data_get($payload, 'questionStatus')
                ?: 'open'
            )),
            'product_name' => data_get($payload, 'product.productTitle')
                ?: data_get($payload, 'product.title')
                ?: data_get($payload, 'productName'),
            'external_product_id' => data_get($payload, 'product.id')
                ?: data_get($payload, 'productId'),
            'product_sku' => data_get($payload, 'product.stockCode')
                ?: data_get($payload, 'stockCode'),
            'asked_at' => $this->questionDate(
                data_get($payload, 'questionDate')
                ?: data_get($payload, 'createdDate')
            ),
        ]);
    }

    protected function n11QuestionStatus(mixed $status): string
    {
        $normalized = Str::of((string) $status)
            ->trim()
            ->upper()
            ->replace([' ', '-'], '_')
            ->toString();

        return match ($normalized) {
            '', 'OPEN', 'PENDING', 'WAITING', 'WAITING_FOR_ANSWER', 'UNANSWERED' => 'OPEN',
            'ANSWERED', 'CLOSED', 'CLOSE' => 'CLOSED',
            default => $normalized,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    protected function claimRowsFromPayload(array $payload): array
    {
        foreach ([
            'claimReturns.claimReturn',
            'claimReturnList.claimReturn',
            'returnList.return',
            'returns.return',
            'items.item',
            'items',
            'data',
        ] as $path) {
            $rows = data_get($payload, $path);

            if (is_array($rows) && $rows !== []) {
                return array_is_list($rows) ? $rows : [$rows];
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    protected function settlementRowsFromPayload(array $payload): array
    {
        foreach ([
            'settlementList.settlement',
            'settlements.settlement',
            'items.item',
            'items',
            'data',
        ] as $path) {
            $rows = data_get($payload, $path);

            if (is_array($rows) && $rows !== []) {
                return array_is_list($rows) ? $rows : [$rows];
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizeSettlementEvent(array $payload): array
    {
        $orderNumber = (string) (data_get($payload, 'order.id') ?: data_get($payload, 'orderNumber') ?: '');
        $date = $this->normalizeDate(data_get($payload, 'date') ?: data_get($payload, 'settlementDate'));
        $amount = $this->toDecimal(data_get($payload, 'amount') ?: data_get($payload, 'sellerHakedis'));
        $commission = $this->toDecimal(data_get($payload, 'commission') ?: data_get($payload, 'commissionAmount'));

        // Return event instead of mutating items directly
        return [
            'order_number' => $orderNumber,
            'event_source' => 'settlements',
            'external_event_id' => (string) (data_get($payload, 'id') ?: md5($orderNumber.'|'.($amount ?? 0))),
            'event_type' => 'settlement',
            'reference_number' => (string) data_get($payload, 'id'),
            'settlement_date' => $date,
            'amount' => abs((float) $amount),
            'currency' => 'TRY',
            'direction' => ((float) $amount < 0) ? 'debit' : 'credit',
            'status' => 'posted',
            'notes' => 'N11 Hakediş',
            'raw_payload' => $payload,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizeClaim(array $payload): array
    {
        $claimId = (string) (
            data_get($payload, 'claimReturnId')
            ?: data_get($payload, 'claimId')
            ?: data_get($payload, 'id')
            ?: data_get($payload, 'returnId')
            ?: ''
        );

        return [
            'external_claim_id' => $claimId,
            'order_number' => data_get($payload, 'orderNumber'),
            'cargo_tracking_number' => data_get($payload, 'cargoTrackingNumber') ?: data_get($payload, 'cargoSenderNumber'),
            'cargo_provider' => data_get($payload, 'cargoCompany') ?: data_get($payload, 'cargoProviderName'),
            'status' => data_get($payload, 'status') ?: data_get($payload, 'claimStatus'),
            'type' => data_get($payload, 'claimType') ?: 'return',
            'reason' => data_get($payload, 'reason') ?: data_get($payload, 'claimReason'),
            'reason_detail' => data_get($payload, 'description') ?: data_get($payload, 'reasonDetail'),
            'customer_note' => data_get($payload, 'customerNote'),
            'customer_name' => data_get($payload, 'buyerName') ?: data_get($payload, 'customerName'),
            'created_date' => data_get($payload, 'claimDate') ?: data_get($payload, 'createdDate'),
            'items' => collect(Arr::wrap(
                data_get($payload, 'items.item')
                ?: data_get($payload, 'items')
                ?: data_get($payload, 'claimReturnItems.claimReturnItem')
                ?: data_get($payload, 'claimReturnItems')
                ?: []
            ))
                ->filter(fn ($row) => is_array($row))
                ->values()
                ->all(),
            'raw_payload' => $payload,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function claimActionPayload(string $externalClaimId, array $context): array
    {
        $itemIds = collect(Arr::wrap($context['claim_item_ids'] ?? $context['external_item_ids'] ?? []))
            ->filter(fn ($value) => filled($value))
            ->values()
            ->all();

        return array_filter([
            'claimReturnId' => $externalClaimId,
            'claimId' => $externalClaimId,
            'claimReturnItemIdList' => $itemIds !== [] ? ['claimReturnItemId' => $itemIds] : null,
            'reason' => $context['reason'] ?? null,
            'description' => $context['description'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
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

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function resolveOrderCreatedAt(array $payload): ?string
    {
        $directDate = data_get($payload, 'orderDate')
            ?: data_get($payload, 'createdDate')
            ?: data_get($payload, 'createDate')
            ?: data_get($payload, 'orderCreatedDate');

        if (filled($directDate)) {
            return $this->normalizeDate($directDate);
        }

        return $this->historyDate($payload, 'Created')
            ?: $this->normalizeDate(data_get($payload, 'lastModifiedDate'));
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

        $dateValue = trim((string) $value);

        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dateValue) === 1) {
            return CarbonImmutable::createFromFormat('!d/m/Y', $dateValue, 'Europe/Istanbul')->toIso8601String();
        }

        if (preg_match('/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}:\d{2}$/', $dateValue) === 1) {
            return CarbonImmutable::createFromFormat('!d/m/Y H:i:s', $dateValue, 'Europe/Istanbul')->toIso8601String();
        }

        return CarbonImmutable::parse($dateValue)->toIso8601String();
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
