<?php

namespace App\Services\Marketplace\Connectors;

use App\Models\ChannelListing;
use App\Models\ChannelOrderPackage;
use App\Models\IntegrationConnection;
use App\Models\MarketplaceQuestion;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\Contracts\AnswersCustomerQuestions;
use App\Services\Marketplace\Contracts\ManagesClaims;
use App\Services\Marketplace\Contracts\PullsFinancials;
use App\Services\Marketplace\Contracts\PullsCustomerQuestions;
use App\Services\Marketplace\Contracts\PullsClaims;
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

class TrendyolConnector extends AbstractMarketplaceConnector implements PullsOrders, PullsProducts, PullsFinancials, PullsCustomerQuestions, PullsClaims, ManagesClaims, AnswersCustomerQuestions, PushesPrice, PushesStock, TestsConnection, UpdatesPackageStatus, ManagesCommonLabels, SendsInvoiceLinks
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
            'questions' => true,
            'question_answer' => true,
            'claims' => true,
            'claim_approve' => true,
            'claim_reject' => true,
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
        $size = min((int) ($options['page_size'] ?? config('marketplace.trendyol.page_size', 500)), 500); // Stream API supports up to 500
        $requestedStartDate = CarbonImmutable::parse($options['start_date'])->setTimezone('UTC');
        $endDate = CarbonImmutable::parse($options['end_date'])->setTimezone('UTC');
        [$effectiveStartDate, $orderWindowMeta] = $this->resolveOrderWindow($requestedStartDate, $endDate);
        
        $requestedPackageIds = collect(Arr::wrap($options['shipment_package_ids'] ?? []))
            ->map(fn ($packageId) => trim((string) $packageId))
            ->filter()
            ->values()
            ->all();

        $cursor = $options['cursor'] ?? null;
        $pagesProcessed = 0;
        
        $response = $this->request($store)
            ->get("integration/order/sellers/{$this->sellerId($store)}/orders/stream", array_filter([
                'startDate' => $effectiveStartDate->valueOf(),
                'endDate' => $endDate->valueOf(),
                'cursor' => $cursor,
                'size' => $size,
                'status' => $options['status'] ?? null,
                'orderNumber' => $options['order_number'] ?? null,
            ], fn ($value) => $value !== null && $value !== ''))
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

        $hasMore = (bool) Arr::get($response, 'hasMore', false);
        $nextCursor = (string) Arr::get($response, 'nextCursor');
        $pagesProcessed++;

        return [
            'items' => $items,
            'meta' => array_merge([
                'items_received' => count($items),
                'cursor_after' => $hasMore && filled($nextCursor) ? $nextCursor : null,
                'has_more' => $hasMore,
                'pages_processed' => $pagesProcessed,
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
                ->get($this->approvedProductsV2Path($store), $query)
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

    public function pullCustomerQuestions(MarketplaceStore $store, array $options = []): array
    {
        $items = [];
        $size = min((int) ($options['page_size'] ?? 50), 50);
        $startDate = CarbonImmutable::parse($options['start_date'])->setTimezone('UTC');
        $endDate = CarbonImmutable::parse($options['end_date'])->setTimezone('UTC');
        $status = $this->trendyolQuestionStatus($options['status'] ?? 'WAITING_FOR_ANSWER');
        $pagesProcessed = 0;

        foreach ($this->trendyolQuestionDateWindows($startDate, $endDate) as [$windowStart, $windowEnd]) {
            $page = 0;

            do {
                $response = $this->request($store)
                    ->get("integration/qna/sellers/{$this->sellerId($store)}/questions/filter", array_filter([
                        'startDate' => $windowStart->valueOf(),
                        'endDate' => $windowEnd->valueOf(),
                        'page' => $page,
                        'size' => $size,
                        'status' => $status,
                        'orderByField' => $options['order_by_field'] ?? 'LastModifiedDate',
                        'orderByDirection' => $options['order_by_direction'] ?? 'DESC',
                    ], fn ($value) => $value !== null && $value !== ''))
                    ->throw()
                    ->json();

                $content = Arr::get($response, 'content', Arr::get($response, 'questions', []));

                foreach ($content as $questionPayload) {
                    if (is_array($questionPayload)) {
                        $items[] = $this->normalizeCustomerQuestion($questionPayload);
                    }
                }

                $totalPages = (int) Arr::get($response, 'totalPages', 1);
                $page++;
                $pagesProcessed++;
            } while ($page < $totalPages);
        }

        $items = collect($items)
            ->unique(fn (array $item): string => (string) (data_get($item, 'external_question_id') ?: json_encode($item)))
            ->values()
            ->all();

        return [
            'items' => $items,
            'meta' => [
                'items_received' => count($items),
                'cursor_after' => $endDate->toIso8601String(),
                'pages_processed' => $pagesProcessed,
            ],
        ];
    }

    public function pullClaims(MarketplaceStore $store, array $options = []): array
    {
        $items = [];
        $page = 0;
        $size = min(50, max(1, (int) ($options['page_size'] ?? config('marketplace.trendyol.claims_page_size', 50))));
        $maxPages = max(1, (int) config('marketplace.trendyol.max_claim_pages_per_sync', 20));
        $startDate = CarbonImmutable::parse($options['start_date'] ?? now()->subDays(7))->setTimezone('UTC');
        $endDate = CarbonImmutable::parse($options['end_date'] ?? now())->setTimezone('UTC');

        do {
            $response = $this->request($store)
                ->get("integration/order/sellers/{$this->sellerId($store)}/claims", array_filter([
                    'startDate' => $startDate->valueOf(),
                    'endDate' => $endDate->valueOf(),
                    'page' => $page,
                    'size' => $size,
                    'status' => $options['status'] ?? null,
                    'orderNumber' => $options['order_number'] ?? null,
                    'orderByField' => $options['order_by_field'] ?? 'ClaimDate',
                    'orderByDirection' => $options['order_by_direction'] ?? 'DESC',
                ], fn ($value) => $value !== null && $value !== ''))
                ->throw()
                ->json();

            $content = Arr::get($response, 'content', Arr::get($response, 'claims', []));

            foreach (Arr::wrap($content) as $claimPayload) {
                if (is_array($claimPayload)) {
                    $items[] = $this->normalizeClaim($claimPayload);
                }
            }

            $totalPages = (int) Arr::get($response, 'totalPages', 1);
            $page++;
        } while ($page < min(max(1, $totalPages), $maxPages));

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
        $claimLineItemIds = $this->claimLineItemIdsFromContext($context);

        if ($claimLineItemIds === []) {
            throw new \RuntimeException('Trendyol iade onayı için claimLineItemId listesi zorunludur.');
        }

        $response = $this->request($store)
            ->put("integration/order/sellers/{$this->sellerId($store)}/claims/{$externalClaimId}/items/approve", [
                'claimLineItemIdList' => $claimLineItemIds,
            ])
            ->throw();

        return [
            'status' => 'approved',
            'message' => 'Trendyol iade onayı gönderildi.',
            'response_status' => $response->status(),
            'response' => $this->decodeResponse($response),
        ];
    }

    public function rejectClaim(MarketplaceStore $store, string $externalClaimId, string $reason, array $context = []): array
    {
        $claimLineItemIds = $this->claimLineItemIdsFromContext($context);

        if ($claimLineItemIds === []) {
            throw new \RuntimeException('Trendyol iade red/analiz işlemi için claimLineItemId listesi zorunludur.');
        }

        $response = $this->request($store)
            ->withQueryParameters([
                'claimIssueReasonId' => (int) ($context['claim_issue_reason_id'] ?? config('marketplace.trendyol.claim_issue_reason_id', 451)),
                'claimItemIdList' => implode(',', $claimLineItemIds),
                'description' => $reason,
            ])
            ->post("integration/order/sellers/{$this->sellerId($store)}/claims/{$externalClaimId}/issue")
            ->throw();

        return [
            'status' => (string) ($context['status_after_reject'] ?? 'unresolved'),
            'message' => 'Trendyol iade analize gönderildi.',
            'response_status' => $response->status(),
            'response' => $this->decodeResponse($response),
        ];
    }

    public function answerCustomerQuestion(MarketplaceQuestion $question, string $answer): array
    {
        $answer = trim($answer);

        if (mb_strlen($answer) < 10 || mb_strlen($answer) > 2000) {
            throw new \RuntimeException('Trendyol soru cevabı 10 ile 2000 karakter arasında olmalıdır.');
        }

        $question->loadMissing('store.connection');
        $store = $question->store;

        $response = $this->request($store)
            ->post("integration/qna/sellers/{$this->sellerId($store)}/questions/{$question->external_question_id}/answers", [
                'text' => $answer,
            ])
            ->throw()
            ->json();

        return [
            'external_answer_id' => Arr::get($response, 'id') ?: Arr::get($response, 'answerId'),
            'raw_response' => $response,
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
            ->get($this->approvedProductsV2Path($store), [
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
        $cargoBarcode = $this->commonLabelCargoBarcode($package);

        if ($cargoBarcode === '') {
            throw new \RuntimeException('Ortak barkod talebi için Trendyol kargo barkodu zorunludur.');
        }

        $package->loadMissing('store.connection');

        $payload = array_filter([
            'cargoTrackingNumber' => $cargoBarcode,
            'format' => (string) ($context['format'] ?? 'ZPL'),
            'boxQuantity' => isset($context['box_quantity']) ? (int) $context['box_quantity'] : null,
            'volumetricHeight' => isset($context['volumetric_weight']) ? (float) $context['volumetric_weight'] : null,
        ], fn ($value) => $value !== null && $value !== '');

        $response = $this->request($package->store)
            ->post("integration/sellers/{$this->sellerId($package->store)}/common-label/{$cargoBarcode}", $payload)
            ->throw();

        return [
            'status' => 'completed',
            'provider' => $this->providerKey(),
            'package_id' => $package->id,
            'package_external_id' => $package->external_package_id,
            'tracking_number' => $cargoBarcode,
            'cargo_barcode' => $cargoBarcode,
            'label_format' => $payload['format'] ?? 'ZPL',
            'response_status' => $response->status(),
            'response' => $this->decodeResponse($response),
            'external_action_id' => (string) ($response->header('X-Request-Id') ?: ''),
        ];
    }

    public function getCommonLabel(ChannelOrderPackage $package, array $context = []): array
    {
        $cargoBarcode = $this->commonLabelCargoBarcode($package);

        if ($cargoBarcode === '') {
            throw new \RuntimeException('Ortak barkod çekmek için Trendyol kargo barkodu zorunludur.');
        }

        $package->loadMissing('store.connection');

        $response = $this->request($package->store)
            ->get("integration/sellers/{$this->sellerId($package->store)}/common-label/{$cargoBarcode}")
            ->throw();

        $decoded = $this->decodeResponse($response);

        return [
            'status' => 'completed',
            'provider' => $this->providerKey(),
            'package_id' => $package->id,
            'package_external_id' => $package->external_package_id,
            'tracking_number' => $cargoBarcode,
            'cargo_barcode' => $cargoBarcode,
            'label_format' => (string) ($context['format'] ?? 'ZPL'),
            'response' => $decoded,
            'label_count' => is_array(data_get($decoded, 'data')) ? count(data_get($decoded, 'data')) : null,
            'external_action_id' => (string) ($response->header('X-Request-Id') ?: ''),
        ];
    }

    protected function commonLabelCargoBarcode(ChannelOrderPackage $package): string
    {
        return (string) $this->firstFilledString([
            $package->cargo_barcode,
            data_get($package->raw_payload ?? [], 'cargoTrackingNumber'),
            $package->cargo_tracking_number,
        ]);
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

    protected function approvedProductsV2Path(MarketplaceStore $store): string
    {
        return "integration/product/sellers/{$this->sellerId($store)}/products/approved";
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizeOrderPackage(array $payload): array
    {
        $orderNumber = (string) (data_get($payload, 'orderNumber') ?: data_get($payload, 'order.number') ?: data_get($payload, 'orderId'));
        $packageId = $this->packageIdFromPayload($payload);
        $status = (string) (data_get($payload, 'status') ?: data_get($payload, 'shipmentPackageStatus') ?: 'new');
        $cargoTrackingNumber = $this->firstFilledString([
            data_get($payload, 'cargoSenderNumber'),
            data_get($payload, 'trackingNumber'),
        ]);
        $cargoBarcode = $this->firstFilledString([
            data_get($payload, 'cargoTrackingNumber'),
            data_get($payload, 'cargoBarcode'),
        ]);
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
                'cargo_tracking_number' => $cargoTrackingNumber,
                'cargo_barcode' => $cargoBarcode,
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
     * @param  array<int, mixed>  $values
     */
    protected function firstFilledString(array $values): ?string
    {
        foreach ($values as $value) {
            if (is_array($value) || is_object($value)) {
                continue;
            }

            $value = trim((string) $value);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
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
        $discountAmount = $this->lineSellerDiscount($line, $quantity);
        $marketplaceDiscount = $this->lineMarketplaceDiscount($line, $quantity);

        if ($discountAmount === null && $marketplaceDiscount === null) {
            $discountAmount = $this->lineTotalDiscount($line, null, null, $quantity);
            $marketplaceDiscount = 0.0;
        }

        $totalDiscount = $this->lineTotalDiscount($line, $discountAmount, $marketplaceDiscount, $quantity);
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
            'billable_amount' => $this->lineBillableAmount($line, $grossAmount, $totalDiscount),
            'commission_rate' => $this->toDecimal($this->firstPresent(
                data_get($line, 'commissionRate'),
                data_get($line, 'commission'),
            )),
            'vat_rate' => $this->lineVatRate($line),
            'line_status' => $lineStatus,
            'raw_payload' => $line,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizeCustomerQuestion(array $payload): array
    {
        $answer = data_get($payload, 'answer');
        $answerText = is_array($answer)
            ? (data_get($answer, 'text') ?: data_get($answer, 'answerText'))
            : data_get($payload, 'answerText');

        $messages = [[
            'external_message_id' => (string) (data_get($payload, 'id') ?: data_get($payload, 'questionId')),
            'direction' => 'customer',
            'body' => data_get($payload, 'text') ?: data_get($payload, 'questionText'),
            'sent_at' => $this->normalizeDate(data_get($payload, 'creationDate') ?: data_get($payload, 'createdDate')),
            'raw_payload' => $payload,
        ]];

        if (filled($answerText)) {
            $messages[] = [
                'external_message_id' => (string) (data_get($answer, 'id') ?: data_get($payload, 'answerId') ?: (data_get($payload, 'id') . '-answer')),
                'direction' => 'seller',
                'body' => $answerText,
                'sent_at' => $this->normalizeDate(data_get($answer, 'creationDate') ?: data_get($payload, 'answeredDate')),
                'raw_payload' => is_array($answer) ? $answer : $payload,
            ];
        }

        return [
            'external_question_id' => (string) (data_get($payload, 'id') ?: data_get($payload, 'questionId')),
            'question_type' => 'product',
            'status' => data_get($payload, 'status') ?: (filled($answerText) ? 'answered' : 'open'),
            'customer_name' => data_get($payload, 'customerFullName') ?: data_get($payload, 'customerName') ?: data_get($payload, 'userName'),
            'customer_external_id' => data_get($payload, 'customerId'),
            'product_name' => data_get($payload, 'productName'),
            'product_sku' => data_get($payload, 'merchantSku') ?: data_get($payload, 'stockCode'),
            'product_barcode' => data_get($payload, 'barcode'),
            'external_product_id' => data_get($payload, 'productMainId') ?: data_get($payload, 'productId'),
            'product_url' => data_get($payload, 'webUrl') ?: data_get($payload, 'productUrl'),
            'question_text' => data_get($payload, 'text') ?: data_get($payload, 'questionText'),
            'answer_text' => $answerText,
            'answered_at' => $this->normalizeDate(data_get($answer, 'creationDate') ?: data_get($payload, 'answeredDate')),
            'asked_at' => $this->normalizeDate(data_get($payload, 'creationDate') ?: data_get($payload, 'createdDate')),
            'raw_payload' => $payload,
            'messages' => $messages,
        ];
    }

    protected function trendyolQuestionStatus(mixed $status): ?string
    {
        $status = trim((string) $status);

        if ($status === '') {
            return 'WAITING_FOR_ANSWER';
        }

        $normalized = Str::of($status)
            ->lower()
            ->ascii()
            ->replace([' ', '-', '.', '/'], '_')
            ->value();

        return match ($normalized) {
            'all', 'any', '*' => null,
            'open', 'pending', 'waiting', 'unanswered', 'waiting_for_answer', 'cevap_bekliyor', 'bekleyen' => 'WAITING_FOR_ANSWER',
            'waiting_for_approve', 'waiting_for_approval', 'approval_pending', 'onay_bekliyor' => 'WAITING_FOR_APPROVE',
            'answered', 'answered_by_seller', 'closed', 'cevaplandi', 'cevaplanmis' => 'ANSWERED',
            'rejected', 'reject', 'closed_without_answer', 'expired', 'spam' => 'REJECTED',
            'reported', 'report', 'complained' => 'REPORTED',
            default => Str::upper($status),
        };
    }

    /**
     * @return array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    protected function trendyolQuestionDateWindows(CarbonImmutable $startDate, CarbonImmutable $endDate): array
    {
        if ($startDate->greaterThan($endDate)) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $windows = [];
        $cursor = $startDate;

        // Trendyol question filters are limited to a maximum two-week range.
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

    /**
     * @return array<string, mixed>
     */
    protected function normalizeClaim(array $payload): array
    {
        $claimId = (string) (
            data_get($payload, 'id')
            ?: data_get($payload, 'claimId')
            ?: data_get($payload, 'claimNumber')
            ?: ''
        );

        return [
            'external_claim_id' => $claimId,
            'claim_id' => $claimId,
            'order_number' => data_get($payload, 'orderNumber'),
            'cargo_tracking_number' => data_get($payload, 'cargoTrackingNumber'),
            'cargo_provider' => data_get($payload, 'cargoProviderName') ?: data_get($payload, 'cargoCompany'),
            'status' => data_get($payload, 'claimStatus') ?: data_get($payload, 'status'),
            'type' => data_get($payload, 'claimType') ?: 'return',
            'reason' => data_get($payload, 'claimReason') ?: data_get($payload, 'reason'),
            'reason_detail' => data_get($payload, 'description') ?: data_get($payload, 'reasonDetail'),
            'customer_note' => data_get($payload, 'customerNote'),
            'customer_name' => data_get($payload, 'customerName'),
            'created_date' => data_get($payload, 'claimDate') ?: data_get($payload, 'createdDate') ?: data_get($payload, 'creationDate'),
            'items' => collect(Arr::wrap(
                data_get($payload, 'items')
                ?: data_get($payload, 'claimItems')
                ?: data_get($payload, 'claimLineItems')
                ?: []
            ))
                ->filter(fn ($row) => is_array($row))
                ->values()
                ->map(function (array $row): array {
                    return [
                        'external_item_id' => (string) (
                            data_get($row, 'claimLineItemId')
                            ?: data_get($row, 'claimItemId')
                            ?: data_get($row, 'id')
                            ?: ''
                        ),
                        'external_order_line_id' => data_get($row, 'orderLineId') ?: data_get($row, 'lineItemId'),
                        'product_name' => data_get($row, 'productName'),
                        'barcode' => data_get($row, 'barcode'),
                        'stock_code' => data_get($row, 'stockCode') ?: data_get($row, 'merchantSku'),
                        'quantity' => data_get($row, 'quantity') ?: data_get($row, 'claimQuantity') ?: 1,
                        'price' => data_get($row, 'price') ?: data_get($row, 'unitPrice'),
                        'status' => data_get($row, 'status') ?: data_get($row, 'claimItemStatus'),
                        'raw_payload' => $row,
                    ];
                })
                ->all(),
            'raw_payload' => $payload,
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
        $onSale = (bool) $this->firstPresent(data_get($payload, 'onSale'), data_get($payload, 'onsale'));
        $approved = (bool) data_get($payload, 'approved');
        $status = (string) (data_get($payload, 'status') ?: ($onSale ? 'active' : ($approved ? 'approved' : 'inactive')));

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
            'listing' => array_merge([
                'listing_id' => $listingId,
                'listing_status' => $status,
                'sale_price' => $this->toDecimal(data_get($payload, 'salePrice')),
                'list_price' => $this->toDecimal(data_get($payload, 'listPrice')),
                'commission_rate' => $this->toDecimal($this->firstPresent(
                    data_get($payload, 'commissionRate'),
                    data_get($payload, 'commission.rate'),
                    data_get($payload, 'commission'),
                )),
                'commission_source' => 'catalog',
                'currency' => data_get($payload, 'currencyType') ?: 'TRY',
                'stock_quantity' => (int) (data_get($payload, 'quantity') ?: 0),
                'published_at' => $this->normalizeDate(data_get($payload, 'approvedDate') ?: data_get($payload, 'createDate')),
            ], $this->catalogDeliveryTermData($payload)),
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
            $normalized = $this->normalizeProduct([
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
                'commissionRate' => $this->trendyolCatalogCommissionRate($payload),
                'quantity' => data_get($payload, 'stock.quantity') ?: data_get($payload, 'quantity'),
                'approvedDate' => data_get($payload, 'approvedDate'),
                'createDate' => data_get($payload, 'creationDate') ?: data_get($payload, 'createDate'),
            ]);

            $normalized['listing'] = array_merge($normalized['listing'], $this->catalogDeliveryTermData($payload));

            return [$normalized];
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
                    'listing' => array_merge([
                        'listing_id' => $variantId,
                        'listing_status' => $this->approvedVariantStatus($variant),
                        'sale_price' => $this->toDecimal(data_get($variant, 'price.salePrice') ?: data_get($payload, 'price.salePrice') ?: data_get($payload, 'salePrice')),
                        'list_price' => $this->toDecimal(data_get($variant, 'price.listPrice') ?: data_get($payload, 'price.listPrice') ?: data_get($payload, 'listPrice')),
                        'commission_rate' => $this->trendyolCatalogCommissionRate($variant, $payload),
                        'commission_source' => 'catalog',
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
                    ], $this->catalogDeliveryTermData($variant, $payload)),
                ];
            })
            ->all();
    }

    protected function trendyolCatalogCommissionRate(array ...$payloads): ?float
    {
        foreach ($payloads as $payload) {
            $rate = $this->toDecimal($this->firstPresent(
                data_get($payload, 'commissionRate'),
                data_get($payload, 'commission.rate'),
                data_get($payload, 'commission'),
                data_get($payload, 'category.commissionRate'),
                data_get($payload, 'category.commission.rate'),
                data_get($payload, 'categoryCommissionRate'),
                data_get($payload, 'commissionRate.value')
            ));

            if ($rate !== null) {
                return $rate;
            }
        }

        return null;
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
            $timestamp = abs($timestamp) > 9999999999
                ? (int) floor($timestamp / 1000)
                : $timestamp;

            // Trendyol numeric dates arrive as Turkey local time encoded in Unix milliseconds.
            return CarbonImmutable::createFromTimestampUTC($timestamp)
                ->subSeconds(app(\App\Services\MpSettingsService::class)->getTrendyolTimestampOffsetSeconds())
                ->setTimezone(config('app.timezone', 'Europe/Istanbul'))
                ->toIso8601String();
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
        $discountDetailsGross = $this->lineDiscountDetailsGrossAmount($line);

        if ($discountDetailsGross !== null) {
            return $discountDetailsGross;
        }

        $grossAmount = $this->toDecimal($this->firstPresent(
            data_get($line, 'lineGrossAmount'),
            data_get($line, 'amount'),
        ));

        if ($grossAmount !== null) {
            return $this->lineAmountsLookPerUnit($line, $quantity, $unitPrice, $grossAmount)
                ? round($grossAmount * $quantity, 2)
                : $grossAmount;
        }

        return round($unitPrice * $quantity, 2);
    }

    protected function lineSellerDiscount(array $line, int $quantity): ?float
    {
        return $this->lineDiscountDetailsSum($line, 'lineItemSellerDiscount')
            ?? $this->lineQuantityAwareAmount($line, $quantity, $this->toDecimal($this->firstPresent(
                data_get($line, 'lineSellerDiscount'),
                data_get($line, 'discount'),
            )));
    }

    protected function lineMarketplaceDiscount(array $line, int $quantity): ?float
    {
        return $this->lineDiscountDetailsSum($line, 'lineItemTyDiscount')
            ?? $this->lineQuantityAwareAmount($line, $quantity, $this->toDecimal($this->firstPresent(
                data_get($line, 'lineTyDiscount'),
                data_get($line, 'tyDiscount'),
                data_get($line, 'platformDiscount'),
            )));
    }

    protected function lineTotalDiscount(array $line, ?float $sellerDiscount, ?float $marketplaceDiscount, int $quantity): float
    {
        $discountDetailsTotal = $this->lineDiscountDetailsSum($line, 'lineItemDiscount');

        if ($discountDetailsTotal !== null) {
            return $discountDetailsTotal;
        }

        $lineTotalDiscount = $this->toDecimal(data_get($line, 'lineTotalDiscount'));

        return $this->lineQuantityAwareAmount($line, $quantity, $lineTotalDiscount)
            ?? round(($sellerDiscount ?? 0) + ($marketplaceDiscount ?? 0), 2);
    }

    protected function lineBillableAmount(array $line, float $grossAmount, float $totalDiscount): float
    {
        return $this->lineDiscountDetailsSum($line, 'lineItemPrice')
            ?? round(max(0, $grossAmount - $totalDiscount), 2);
    }

    protected function lineQuantityAwareAmount(array $line, int $quantity, ?float $amount): ?float
    {
        if ($amount === null) {
            return null;
        }

        if ($this->lineAmountsLookPerUnit($line, $quantity, $this->lineUnitPrice($line))) {
            return round($amount * $quantity, 2);
        }

        return $amount;
    }

    protected function lineAmountsLookPerUnit(array $line, int $quantity, float $unitPrice, ?float $grossAmount = null): bool
    {
        if ($quantity <= 1 || $unitPrice <= 0) {
            return false;
        }

        $grossAmount ??= $this->toDecimal($this->firstPresent(
            data_get($line, 'lineGrossAmount'),
            data_get($line, 'amount'),
        ));

        if ($grossAmount === null) {
            return false;
        }

        $totalDiscount = $this->toDecimal(data_get($line, 'lineTotalDiscount'));

        return $this->amountsMatch($grossAmount, $unitPrice)
            || ($totalDiscount !== null && $this->amountsMatch($grossAmount - $totalDiscount, $unitPrice));
    }

    protected function amountsMatch(float $left, float $right): bool
    {
        return abs(round($left, 2) - round($right, 2)) < 0.01;
    }

    protected function lineDiscountDetailsGrossAmount(array $line): ?float
    {
        $details = $this->lineDiscountDetails($line);

        if ($details === []) {
            return null;
        }

        $hasPrice = false;
        $grossAmount = array_sum(array_map(function (array $detail) use (&$hasPrice): float {
            $rawPrice = data_get($detail, 'lineItemPrice');
            $hasPrice = $hasPrice || $rawPrice !== null;
            $price = $this->toDecimal($rawPrice) ?? 0.0;
            $discount = $this->toDecimal(data_get($detail, 'lineItemDiscount')) ?? 0.0;

            return $price + $discount;
        }, $details));

        return $hasPrice ? round($grossAmount, 2) : null;
    }

    protected function lineDiscountDetailsSum(array $line, string $key): ?float
    {
        $details = $this->lineDiscountDetails($line);

        if ($details === []) {
            return null;
        }

        $hasValue = false;
        $total = array_sum(array_map(function (array $detail) use ($key, &$hasValue): float {
            $rawValue = data_get($detail, $key);
            $hasValue = $hasValue || $rawValue !== null;

            return $this->toDecimal($rawValue) ?? 0.0;
        }, $details));

        return $hasValue ? round($total, 2) : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function lineDiscountDetails(array $line): array
    {
        $details = data_get($line, 'discountDetails', []);

        if (!is_array($details)) {
            return [];
        }

        return array_values(array_filter($details, fn ($detail) => is_array($detail)));
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
     * @param  array<string, mixed>  $context
     * @return array<int, mixed>
     */
    protected function claimLineItemIdsFromContext(array $context): array
    {
        $ids = collect(Arr::wrap($context['claim_item_ids'] ?? $context['external_item_ids'] ?? []));

        foreach (Arr::wrap($context['items'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $ids->push(
                data_get($item, 'claimLineItemId')
                ?: data_get($item, 'claimItemId')
                ?: data_get($item, 'external_item_id')
            );
        }

        return $ids
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value) => is_numeric($value) ? (int) $value : (string) $value)
            ->unique()
            ->values()
            ->all();
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
                'Trendyol Siparis Paketlerini Cekme servisi 5 Mart 2026 itibariyla klasik API\'de 30 gün limiti getirmişti. Stream API 90 güne kadar desteklese de bu mağaza yapılandırması son %d günü baz alıyor. %s bitiş tarihli sorgu bu limitin dışında kalıyor.',
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

    /**
     * @param  array<int, string>  $barcodes
     * @return array<string, mixed>
     */
    public function checkBuyboxRank(MarketplaceStore $store, array $barcodes): array
    {
        if (count($barcodes) > 10) {
            throw new \InvalidArgumentException('Trendyol Buybox servisi tek seferde en fazla 10 barkod sorgulanmasına izin verir.');
        }

        if ($barcodes === []) {
            return [];
        }

        $response = $this->request($store)
            ->post("integration/product/sellers/{$this->sellerId($store)}/products/buybox", [
                'barcodeList' => array_values($barcodes),
            ])
            ->throw()
            ->json();

        return Arr::get($response, 'content', $response);
    }

    public function pullCargoInvoices(MarketplaceStore $store, array $options = []): array
    {
        $items = [];
        $startDate = CarbonImmutable::parse($options['start_date'])->setTimezone('UTC');
        $endDate = CarbonImmutable::parse($options['end_date'])->setTimezone('UTC');

        foreach ($this->dateWindows($startDate, $endDate, 14) as [$windowStart, $windowEnd]) {
            $page = 0;
            $totalPages = 1;

            do {
                $response = $this->request($store)
                    ->get("integration/finance/che/sellers/{$this->sellerId($store)}/cargo-invoices", array_filter([
                        'invoiceDateStart' => $windowStart->valueOf(),
                        'invoiceDateEnd' => $windowEnd->valueOf(),
                        'page' => $page,
                        'size' => 500,
                    ]))
                    ->throw()
                    ->json();

                foreach (Arr::get($response, 'content', []) as $invoicePayload) {
                    $items[] = $invoicePayload;
                }

                $totalPages = (int) Arr::get($response, 'totalPages', 1);
                $page++;
            } while ($page < $totalPages);
        }

        return [
            'items' => $items,
            'meta' => [
                'items_received' => count($items),
                'cursor_after' => $endDate->toIso8601String(),
            ]
        ];
    }

    public function getClaimIssueReasons(MarketplaceStore $store): array
    {
        $response = $this->request($store)
            ->get("integration/order/sellers/{$this->sellerId($store)}/claim-issue-reasons")
            ->throw()
            ->json();

        return Arr::get($response, 'content', $response);
    }

    public function checkBatchRequestResult(MarketplaceStore $store, string $batchRequestId): array
    {
        $response = $this->request($store)
            ->get("integration/product/sellers/{$this->sellerId($store)}/products/batch-requests/{$batchRequestId}")
            ->throw()
            ->json();

        return is_array($response) ? $response : [];
    }

    public function getBrands(MarketplaceStore $store, int $page = 0, int $size = 500): array
    {
        $response = $this->request($store)
            ->get("integration/product/brands", [
                'page' => $page,
                'size' => $size,
            ])
            ->throw()
            ->json();

        return Arr::get($response, 'brands', Arr::get($response, 'content', []));
    }

    public function getCategories(MarketplaceStore $store): array
    {
        $response = $this->request($store)
            ->get("integration/product/product-categories")
            ->throw()
            ->json();

        return Arr::get($response, 'categories', Arr::get($response, 'content', []));
    }
}
