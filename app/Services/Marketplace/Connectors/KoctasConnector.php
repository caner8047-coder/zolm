<?php

namespace App\Services\Marketplace\Connectors;

use App\Models\ChannelListing;
use App\Models\MarketplaceQuestion;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\Connectors\Concerns\NormalizesCustomerQuestions;
use App\Services\Marketplace\Contracts\AnswersCustomerQuestions;
use App\Services\Marketplace\Contracts\ManagesClaims;
use App\Services\Marketplace\Contracts\PullsCustomerQuestions;
use App\Services\Marketplace\Contracts\PullsClaims;
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

class KoctasConnector extends AbstractMarketplaceConnector implements PullsOrders, PullsProducts, PullsCustomerQuestions, PullsClaims, ManagesClaims, AnswersCustomerQuestions, PushesPrice, PushesStock, TestsConnection
{
    use NormalizesCustomerQuestions;

    public function providerKey(): string
    {
        return 'koctas';
    }

    public function displayName(): string
    {
        return 'Koçtaş';
    }

    public function defaultApiBaseUrl(): ?string
    {
        return config('marketplace.koctas.base_url');
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
            ->get('api/account')
            ->throw()
            ->json();

        return [
            'ok' => true,
            'message' => 'Koçtaş bağlantısı doğrulandı.',
            'meta' => [
                'provider' => $this->providerKey(),
                'base_url' => $this->baseUrl($store),
                'shop_id' => data_get($response, 'shop.id')
                    ?: data_get($response, 'shop_id')
                    ?: data_get($response, 'id'),
                'shop_name' => data_get($response, 'shop.name')
                    ?: data_get($response, 'shop_name')
                    ?: data_get($response, 'name'),
            ],
        ];
    }

    public function pullOrders(MarketplaceStore $store, array $options = []): array
    {
        $limit = min(100, max(1, (int) ($options['page_size'] ?? config('marketplace.koctas.order_page_size', 50))));
        $maxPages = max(1, (int) config('marketplace.koctas.max_order_pages_per_sync', 20));
        $startDate = CarbonImmutable::parse($options['start_date'] ?? now()->subDay())->setTimezone('UTC');
        $endDate = CarbonImmutable::parse($options['end_date'] ?? now())->setTimezone('UTC');
        $items = [];
        $pageToken = null;
        $pages = 0;

        do {
            $query = $this->withShopSelection($store, array_filter([
                'limit' => $limit,
                'page_token' => $pageToken,
                'sort' => 'dateCreated',
                'order' => 'desc',
                'start_update_date' => $startDate->toIso8601String(),
                'end_update_date' => $endDate->toIso8601String(),
                'order_references' => $options['order_number'] ?? null,
                'order_state_codes' => $options['status'] ?? null,
            ], fn ($value) => $value !== null && $value !== ''));

            $response = $this->request($store)
                ->get('api/orders', $query)
                ->throw()
                ->json();

            $orders = collect(Arr::wrap(data_get($response, 'orders', [])))
                ->filter(fn ($row) => is_array($row))
                ->values();

            foreach ($orders as $orderPayload) {
                $items[] = $this->normalizeOrder($orderPayload);
            }

            $pageToken = data_get($response, 'next_page_token');
            $pages++;
        } while ($orders->isNotEmpty() && filled($pageToken) && $pages < $maxPages);

        return [
            'items' => $items,
            'meta' => [
                'items_received' => count($items),
                'pages_processed' => $pages,
                'cursor_after' => $endDate->toIso8601String(),
                'more_pages_available' => filled($pageToken),
            ],
        ];
    }

    public function pullProducts(MarketplaceStore $store, array $options = []): array
    {
        $max = min(100, max(1, (int) ($options['page_size'] ?? config('marketplace.koctas.product_page_size', 100))));
        $maxPages = max(1, (int) config('marketplace.koctas.max_product_pages_per_sync', 20));
        $items = [];
        $offset = 0;
        $totalCount = 0;
        $pages = 0;

        do {
            $query = $this->withShopSelection($store, array_filter([
                'max' => $max,
                'offset' => $offset,
                'sku' => $options['stock_code'] ?? null,
                'product_id' => $options['barcode'] ?? null,
                'offer_state_codes' => $options['status'] ?? null,
            ], fn ($value) => $value !== null && $value !== ''));

            $response = $this->request($store)
                ->get('api/offers', $query)
                ->throw()
                ->json();

            $offers = collect(Arr::wrap(data_get($response, 'offers', [])))
                ->filter(fn ($row) => is_array($row))
                ->values();

            foreach ($offers as $offerPayload) {
                $items[] = $this->normalizeProduct($offerPayload);
            }

            $totalCount = max($totalCount, (int) data_get($response, 'total_count', $offers->count()));
            $offset += $offers->count();
            $pages++;
        } while ($offers->isNotEmpty() && $offset < $totalCount && $pages < $maxPages);

        return [
            'items' => $items,
            'meta' => [
                'items_received' => count($items),
                'pages_processed' => $pages,
                'total_count' => $totalCount,
                'supports_incremental_window' => false,
                'more_pages_available' => $offset < $totalCount,
                'cursor_after' => now()->toIso8601String(),
            ],
        ];
    }

    public function pullCustomerQuestions(MarketplaceStore $store, array $options = []): array
    {
        $limit = min(100, max(1, (int) ($options['page_size'] ?? config('marketplace.koctas.question_page_size', 50))));
        $maxPages = max(1, (int) config('marketplace.koctas.max_question_pages_per_sync', 10));
        $startDate = CarbonImmutable::parse($options['start_date'] ?? now()->subDays(7))->setTimezone('UTC');
        $endDate = CarbonImmutable::parse($options['end_date'] ?? now())->setTimezone('UTC');
        $path = trim((string) config('marketplace.koctas.question_list_path', 'api/inbox/threads'), '/');
        $offset = 0;
        $pages = 0;
        $items = [];

        do {
            $response = $this->request($store)
                ->get($path, $this->withShopSelection($store, array_filter([
                    'limit' => $limit,
                    'max' => $limit,
                    'offset' => $offset,
                    'updated_since' => $startDate->toIso8601String(),
                    'updated_until' => $endDate->toIso8601String(),
                    'last_message_date_from' => $startDate->toIso8601String(),
                    'last_message_date_to' => $endDate->toIso8601String(),
                    'state' => $options['status'] ?? null,
                ], fn ($value) => $value !== null && $value !== '')))
                ->throw()
                ->json();

            $payload = is_array($response) ? $response : [];
            $rows = $this->questionRowsFromPayload($payload, ['threads', 'items', 'data']);

            foreach ($rows as $row) {
                $items[] = $this->normalizeKoctasQuestion($row);
            }

            $totalCount = (int) (
                data_get($payload, 'total_count')
                ?: data_get($payload, 'totalCount')
                ?: count($rows)
            );
            $offset += $limit;
            $pages++;
            $hasMore = $totalCount > 0 ? $offset < $totalCount : count($rows) === $limit;
        } while ($rows !== [] && $hasMore && $pages < $maxPages);

        return [
            'items' => $items,
            'meta' => [
                'items_received' => count($items),
                'cursor_after' => $endDate->toIso8601String(),
                'pages_processed' => $pages,
                'more_pages_available' => $hasMore ?? false,
                'endpoint' => $path,
            ],
        ];
    }

    public function pullClaims(MarketplaceStore $store, array $options = []): array
    {
        $limit = min(100, max(1, (int) ($options['page_size'] ?? config('marketplace.koctas.claim_page_size', 50))));
        $maxPages = max(1, (int) config('marketplace.koctas.max_order_pages_per_sync', 20));
        $startDate = CarbonImmutable::parse($options['start_date'] ?? now()->subDays(7))->setTimezone('UTC');
        $endDate = CarbonImmutable::parse($options['end_date'] ?? now())->setTimezone('UTC');
        $path = trim((string) config('marketplace.koctas.return_list_path', 'api/returns'), '/');
        $items = [];
        $offset = 0;
        $pages = 0;

        do {
            $response = $this->request($store)
                ->get($path, $this->withShopSelection($store, array_filter([
                    'limit' => $limit,
                    'max' => $limit,
                    'offset' => $offset,
                    'updated_since' => $startDate->toIso8601String(),
                    'updated_until' => $endDate->toIso8601String(),
                    'status' => $options['status'] ?? null,
                    'order_references' => $options['order_number'] ?? null,
                ], fn ($value) => $value !== null && $value !== '')))
                ->throw()
                ->json();

            $payload = is_array($response) ? $response : [];
            $rows = collect(Arr::wrap(
                data_get($payload, 'returns')
                ?: data_get($payload, 'items')
                ?: data_get($payload, 'data')
                ?: []
            ))
                ->filter(fn ($row) => is_array($row))
                ->values();

            foreach ($rows as $row) {
                $items[] = $this->normalizeClaim($row);
            }

            $totalCount = (int) (data_get($payload, 'total_count') ?: data_get($payload, 'totalCount') ?: $rows->count());
            $offset += $limit;
            $pages++;
            $hasMore = $totalCount > 0 ? $offset < $totalCount : $rows->count() === $limit;
        } while ($rows->isNotEmpty() && $hasMore && $pages < $maxPages);

        return [
            'items' => $items,
            'meta' => [
                'items_received' => count($items),
                'cursor_after' => $endDate->toIso8601String(),
                'pages_processed' => $pages,
                'endpoint' => $path,
            ],
        ];
    }

    public function approveClaim(MarketplaceStore $store, string $externalClaimId, array $context = []): array
    {
        $path = $this->claimActionPath('return_accept_path', 'api/returns/{return_id}/accept', $externalClaimId);
        $response = $this->request($store)
            ->withQueryParameters($this->withShopSelection($store, []))
            ->post($path, $context['payload'] ?? [])
            ->throw();

        return [
            'status' => 'approved',
            'message' => 'Koçtaş iade onayı gönderildi.',
            'response_status' => $response->status(),
            'response' => $response->json() ?: [],
        ];
    }

    public function rejectClaim(MarketplaceStore $store, string $externalClaimId, string $reason, array $context = []): array
    {
        $path = $this->claimActionPath('return_reject_path', 'api/returns/{return_id}/refuse', $externalClaimId);
        $response = $this->request($store)
            ->withQueryParameters($this->withShopSelection($store, []))
            ->post($path, array_replace([
                'reason' => $reason,
                'message' => $reason,
            ], $context['payload'] ?? []))
            ->throw();

        return [
            'status' => 'rejected',
            'message' => 'Koçtaş iade reddi gönderildi.',
            'response_status' => $response->status(),
            'response' => $response->json() ?: [],
        ];
    }

    public function answerCustomerQuestion(MarketplaceQuestion $question, string $answer): array
    {
        $question->loadMissing('store.connection');

        $path = str_replace(
            ['{thread_id}', '{question_id}', '{id}'],
            $question->external_question_id,
            trim((string) config('marketplace.koctas.question_answer_path', 'api/inbox/threads/{thread_id}/messages'), '/')
        );

        $response = $this->request($question->store)
            ->withQueryParameters($this->withShopSelection($question->store, []))
            ->post($path, [
                'body' => $answer,
                'message' => $answer,
            ])
            ->throw();

        $payload = $response->json();
        $payload = is_array($payload) ? $payload : [];

        return [
            'external_answer_id' => (string) (
                data_get($payload, 'message_id')
                ?: data_get($payload, 'id')
                ?: $question->external_question_id
            ),
            'response_status' => $response->status(),
            'response' => $payload,
        ];
    }

    public function pushPrice(ChannelListing $listing, float $price, array $context = []): array
    {
        $listing->loadMissing(['store.connection', 'channelProduct']);

        $response = $this->request($listing->store)
            ->withQueryParameters($this->withShopSelection($listing->store, []))
            ->attach('file', $this->buildPriceImportCsv($this->offerSku($listing), $price), 'koctas-price.csv')
            ->post('api/offers/pricing/imports')
            ->throw();

        $decoded = $response->json();

        return [
            'status' => 'queued',
            'listing_id' => $listing->id,
            'provider' => $this->providerKey(),
            'price' => round($price, 2),
            'batch_request_id' => (string) (data_get($decoded, 'import_id') ?: data_get($decoded, 'importId') ?: ''),
            'response_status' => $response->status(),
            'response' => $decoded,
            'context' => $context,
        ];
    }

    public function pushStock(ChannelListing $listing, int $quantity, array $context = []): array
    {
        $listing->loadMissing(['store.connection', 'channelProduct']);

        $response = $this->request($listing->store)
            ->withQueryParameters($this->withShopSelection($listing->store, []))
            ->attach('file', $this->buildStockImportCsv($this->offerSku($listing), $quantity), 'koctas-stock.csv')
            ->post('api/offers/stock/imports')
            ->throw();

        $decoded = $response->json();

        return [
            'status' => 'queued',
            'listing_id' => $listing->id,
            'provider' => $this->providerKey(),
            'quantity' => $quantity,
            'batch_request_id' => (string) (data_get($decoded, 'import_id') ?: data_get($decoded, 'importId') ?: ''),
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
        $orderId = (string) (data_get($payload, 'order_id') ?: data_get($payload, 'id'));
        $status = (string) (data_get($payload, 'order_state') ?: data_get($payload, 'order_state_code') ?: 'pending');
        $shippingAddress = data_get($payload, 'customer_shipping_address', []);
        $billingAddress = data_get($payload, 'customer_billing_address', []);
        $customer = data_get($payload, 'customer', []);
        $lineItems = collect(Arr::wrap(data_get($payload, 'order_lines', [])))
            ->filter(fn ($row) => is_array($row))
            ->values();

        $customerName = trim((string) collect([
            data_get($customer, 'firstname'),
            data_get($customer, 'lastname'),
            data_get($shippingAddress, 'firstname'),
            data_get($shippingAddress, 'lastname'),
        ])->filter()->implode(' '));

        return [
            'order' => [
                'external_order_id' => $orderId,
                'order_number' => (string) (
                    data_get($payload, 'order_reference_for_seller')
                    ?: data_get($payload, 'order_references_for_seller.0')
                    ?: data_get($payload, 'customer_order_id')
                    ?: $orderId
                ),
                'order_status' => $status,
                'commercial_type' => filled(data_get($billingAddress, 'company')) || filled(data_get($customer, 'company'))
                    ? 'commercial'
                    : 'individual',
                'customer_name' => $customerName,
                'customer_email' => data_get($customer, 'email') ?: data_get($payload, 'customer_email'),
                'customer_phone' => data_get($shippingAddress, 'phone') ?: data_get($billingAddress, 'phone'),
                'billing_name' => data_get($billingAddress, 'company') ?: trim((string) collect([
                    data_get($billingAddress, 'firstname'),
                    data_get($billingAddress, 'lastname'),
                ])->filter()->implode(' ')),
                'billing_tax_number' => (string) (
                    data_get($billingAddress, 'tax_identification_number')
                    ?: data_get($billingAddress, 'company_registration_number')
                    ?: data_get($billingAddress, 'company_identification_number')
                    ?: ''
                ),
                'shipment_country' => data_get($shippingAddress, 'country_iso_code') ?: data_get($shippingAddress, 'country') ?: 'TR',
                'shipment_city' => data_get($shippingAddress, 'city'),
                'shipment_district' => data_get($shippingAddress, 'state') ?: data_get($shippingAddress, 'district'),
                'ordered_at' => $this->normalizeDate(data_get($payload, 'created_date')),
                'approved_at' => $this->normalizeDate(data_get($payload, 'accepted_date') ?: data_get($payload, 'payment_date')),
                'delivered_at' => $this->normalizeDate(data_get($payload, 'delivery_date')),
                'cancelled_at' => $this->statusHas($status, ['cancel', 'refus'])
                    ? $this->normalizeDate(data_get($payload, 'updated_date') ?: data_get($payload, 'created_date'))
                    : null,
                'returned_at' => $this->statusHas($status, ['refund', 'return'])
                    ? $this->normalizeDate(data_get($payload, 'updated_date') ?: data_get($payload, 'created_date'))
                    : null,
                'raw_payload' => $payload,
            ],
            'package' => [
                'external_package_id' => (string) (data_get($payload, 'shipping_from.id') ?: data_get($payload, 'shipping_id') ?: $orderId),
                'package_number' => (string) (data_get($payload, 'shipping_from.id') ?: data_get($payload, 'shipping_id') ?: $orderId),
                'package_status' => $status,
                'cargo_company' => data_get($payload, 'shipping_type_label') ?: data_get($payload, 'carrier_code'),
                'cargo_tracking_number' => data_get($payload, 'tracking.tracking_number') ?: data_get($payload, 'tracking_number'),
                'cargo_barcode' => data_get($payload, 'tracking.tracking_number') ?: data_get($payload, 'tracking_number'),
                'cargo_desi' => null,
                'shipment_provider' => data_get($payload, 'shipping_type_code') ?: data_get($payload, 'shipping_type_label'),
                'shipped_at' => $this->normalizeDate(data_get($payload, 'shipping_date')),
                'delivered_at' => $this->normalizeDate(data_get($payload, 'delivery_date')),
                'raw_payload' => $payload,
            ],
            'items' => $lineItems
                ->map(fn (array $line, int $index) => $this->normalizeOrderLine($line, $payload, $index))
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $orderPayload
     * @return array<string, mixed>
     */
    protected function normalizeOrderLine(array $payload, array $orderPayload, int $index): array
    {
        $quantity = max(1, (int) (data_get($payload, 'quantity') ?: 1));
        $unitPrice = $this->toDecimal(data_get($payload, 'price_unit') ?: data_get($payload, 'unit_price'));
        $grossAmount = $this->toDecimal(data_get($payload, 'price') ?: data_get($payload, 'gross_price') ?: data_get($payload, 'total_price'));

        if ($grossAmount === null && $unitPrice !== null) {
            $grossAmount = round($unitPrice * $quantity, 2);
        }

        if ($unitPrice === null && $grossAmount !== null) {
            $unitPrice = round($grossAmount / $quantity, 2);
        }

        $billableAmount = $this->toDecimal(data_get($payload, 'total_price') ?: data_get($payload, 'price') ?: $grossAmount);
        $discountAmount = $this->toDecimal(data_get($payload, 'discount_amount'));

        if ($discountAmount === null && $grossAmount !== null && $billableAmount !== null && $grossAmount > $billableAmount) {
            $discountAmount = round($grossAmount - $billableAmount, 2);
        }

        return [
            'external_line_id' => (string) (data_get($payload, 'order_line_id') ?: sha1(($orderPayload['order_id'] ?? 'koctas').'|'.$index)),
            'stock_code' => (string) (
                data_get($payload, 'offer_sku')
                ?: data_get($payload, 'shop_sku')
                ?: data_get($payload, 'product_sku')
                ?: ''
            ),
            'barcode' => $this->barcodeFromReferences(Arr::wrap(data_get($payload, 'product_references', [])))
                ?: $this->referenceValueByType((string) data_get($payload, 'product_id_type'), data_get($payload, 'product_id')),
            'product_name' => data_get($payload, 'product_title') ?: data_get($payload, 'title'),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'gross_amount' => $grossAmount,
            'discount_amount' => $discountAmount,
            'marketplace_discount_amount' => null,
            'billable_amount' => $billableAmount,
            'commission_rate' => null,
            'vat_rate' => $this->toDecimal(data_get($payload, 'tax_rate')),
            'line_status' => (string) (data_get($payload, 'order_line_state') ?: data_get($payload, 'status') ?: data_get($orderPayload, 'order_state') ?: 'pending'),
            'raw_payload' => $payload,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizeProduct(array $payload): array
    {
        $references = Arr::wrap(data_get($payload, 'product_references', []));
        $stockCode = (string) (
            data_get($payload, 'shop_sku')
            ?: data_get($payload, 'offer_sku')
            ?: data_get($payload, 'sku')
            ?: data_get($payload, 'product_sku')
            ?: ''
        );
        $barcode = $this->barcodeFromReferences($references)
            ?: $this->referenceValueByType((string) data_get($payload, 'product_id_type'), data_get($payload, 'product_id'));
        $externalProductId = (string) (
            data_get($payload, 'product_sku')
            ?: data_get($payload, 'product_id')
            ?: $barcode
            ?: $stockCode
            ?: data_get($payload, 'offer_id')
            ?: ''
        );

        return [
            'product' => [
                'external_product_id' => $externalProductId,
                'external_parent_id' => (string) (data_get($payload, 'parent_product_id') ?: ''),
                'stock_code' => $stockCode !== '' ? $stockCode : null,
                'barcode' => $barcode,
                'title' => data_get($payload, 'product_title') ?: data_get($payload, 'title') ?: data_get($payload, 'description'),
                'brand' => data_get($payload, 'brand') ?: data_get($payload, 'brand_label'),
                'category_name' => data_get($payload, 'category_label') ?: data_get($payload, 'category_code'),
                'vat_rate' => $this->toDecimal(data_get($payload, 'vat_rate') ?: data_get($payload, 'tax_rate')),
                'raw_payload' => $payload,
            ],
            'listing' => array_merge([
                'listing_id' => (string) (data_get($payload, 'offer_id') ?: $stockCode ?: $externalProductId),
                'listing_status' => $this->normalizeListingStatus($payload),
                'sale_price' => $this->toDecimal(data_get($payload, 'discount_price') ?: data_get($payload, 'price')),
                'list_price' => $this->toDecimal(data_get($payload, 'price')),
                'commission_rate' => null,
                'commission_source' => 'product_fallback',
                'commission_authority' => 'product',
                'currency' => data_get($payload, 'currency_iso_code') ?: data_get($payload, 'currency') ?: 'TRY',
                'stock_quantity' => (int) (data_get($payload, 'quantity') ?: 0),
                'published_at' => $this->normalizeDate(data_get($payload, 'updated_date') ?: data_get($payload, 'created_date')),
            ], $this->catalogDeliveryTermData($payload)),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizeKoctasQuestion(array $payload): array
    {
        $lastMessage = data_get($payload, 'last_message')
            ?: data_get($payload, 'lastMessage')
            ?: collect(Arr::wrap(data_get($payload, 'messages', [])))->last();

        return $this->normalizeQuestionPayload($payload, [
            'external_question_id' => (string) (
                data_get($payload, 'thread_id')
                ?: data_get($payload, 'threadId')
                ?: data_get($payload, 'id')
            ),
            'question_type' => $this->koctasQuestionType($payload),
            'question_text' => $this->questionTextValue(
                data_get($payload, 'topic')
                ?: data_get($payload, 'subject')
                ?: data_get($payload, 'title')
                ?: (is_array($lastMessage) ? data_get($lastMessage, 'body') : null)
                ?: (is_array($lastMessage) ? data_get($lastMessage, 'message') : null)
            ),
            'status' => $this->normalizeQuestionStatus((string) (
                data_get($payload, 'state')
                ?: data_get($payload, 'status')
                ?: 'open'
            )),
            'product_name' => data_get($payload, 'entity.product.title')
                ?: data_get($payload, 'product_title')
                ?: data_get($payload, 'product.name'),
            'product_sku' => data_get($payload, 'offer_sku')
                ?: data_get($payload, 'shop_sku')
                ?: data_get($payload, 'entity.offer.sku'),
            'external_product_id' => data_get($payload, 'product_id')
                ?: data_get($payload, 'entity.product.id'),
            'listing_id' => data_get($payload, 'offer_id')
                ?: data_get($payload, 'entity.offer.id'),
            'order_number' => data_get($payload, 'order_reference_for_seller')
                ?: data_get($payload, 'order_references_for_seller.0')
                ?: data_get($payload, 'order_id')
                ?: data_get($payload, 'entity.order.id')
                ?: data_get($payload, 'entity.id'),
            'asked_at' => $this->questionDate(
                data_get($payload, 'created_date')
                ?: data_get($payload, 'createdDate')
                ?: data_get($payload, 'date_created')
            ),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function koctasQuestionType(array $payload): string
    {
        $entityType = Str::lower((string) (
            data_get($payload, 'entity.type')
            ?: data_get($payload, 'entity_type')
            ?: data_get($payload, 'entities.0.type')
            ?: data_get($payload, 'entity.kind')
        ));

        if (Str::contains($entityType, ['order'])) {
            return 'order';
        }

        if (filled(
            data_get($payload, 'order_reference_for_seller')
            ?: data_get($payload, 'order_references_for_seller.0')
            ?: data_get($payload, 'order_id')
            ?: data_get($payload, 'entity.order.id')
        )) {
            return 'order';
        }

        return 'product';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizeClaim(array $payload): array
    {
        $returnId = (string) (
            data_get($payload, 'return_id')
            ?: data_get($payload, 'returnId')
            ?: data_get($payload, 'id')
            ?: data_get($payload, 'order_id')
            ?: ''
        );

        return [
            'external_claim_id' => $returnId,
            'order_number' => data_get($payload, 'order_reference_for_seller')
                ?: data_get($payload, 'order_references_for_seller.0')
                ?: data_get($payload, 'order_id'),
            'cargo_tracking_number' => data_get($payload, 'tracking.tracking_number') ?: data_get($payload, 'tracking_number'),
            'cargo_provider' => data_get($payload, 'carrier_code') ?: data_get($payload, 'shipping_type_label'),
            'status' => data_get($payload, 'return_state') ?: data_get($payload, 'status') ?: data_get($payload, 'state'),
            'type' => 'return',
            'reason' => data_get($payload, 'reason') ?: data_get($payload, 'return_reason'),
            'reason_detail' => data_get($payload, 'reason_detail') ?: data_get($payload, 'message'),
            'customer_note' => data_get($payload, 'customer_message') ?: data_get($payload, 'message'),
            'customer_name' => trim((string) collect([
                data_get($payload, 'customer.firstname'),
                data_get($payload, 'customer.lastname'),
            ])->filter()->implode(' ')),
            'created_date' => data_get($payload, 'created_date') ?: data_get($payload, 'date_created'),
            'items' => collect(Arr::wrap(
                data_get($payload, 'return_lines')
                ?: data_get($payload, 'order_lines')
                ?: data_get($payload, 'items')
                ?: []
            ))
                ->filter(fn ($row) => is_array($row))
                ->values()
                ->all(),
            'raw_payload' => $payload,
        ];
    }

    protected function claimActionPath(string $configKey, string $defaultPath, string $claimId): string
    {
        $path = trim((string) config('marketplace.koctas.'.$configKey, $defaultPath), '/');

        return str_replace(
            ['{return_id}', '{claim_id}', '{id}'],
            $claimId,
            $path
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function normalizeListingStatus(array $payload): string
    {
        if (data_get($payload, 'active') === true) {
            return 'active';
        }

        if ((bool) data_get($payload, 'deleted')) {
            return 'deleted';
        }

        if (data_get($payload, 'active') === false) {
            return 'inactive';
        }

        return (string) (data_get($payload, 'state_code') ?: data_get($payload, 'status') ?: 'draft');
    }

    protected function request(MarketplaceStore $store): PendingRequest
    {
        return Http::baseUrl($this->baseUrl($store))
            ->acceptJson()
            ->timeout((int) config('marketplace.koctas.request_timeout', 45))
            ->withUserAgent('ZOLM Marketplace Integration')
            ->withHeaders([
                'Authorization' => $this->apiToken($store),
            ]);
    }

    protected function apiToken(MarketplaceStore $store): string
    {
        $credentials = $store->connection?->credentials_encrypted ?? [];
        $token = trim((string) ($credentials['api_key'] ?? $credentials['api_secret'] ?? ''));

        if ($token === '') {
            throw new \RuntimeException('Koçtaş bağlantısı için API anahtarı zorunludur.');
        }

        return $token;
    }

    protected function baseUrl(MarketplaceStore $store): string
    {
        $baseUrl = trim((string) ($store->connection?->api_base_url ?: config('marketplace.koctas.base_url')));

        if ($baseUrl === '') {
            throw new \RuntimeException('Koçtaş API base URL boş. Resmi Mirakl endpoint tanımlanmalıdır.');
        }

        return rtrim(Str::replaceEnd('/api', '', rtrim($baseUrl, '/')), '/');
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    protected function withShopSelection(MarketplaceStore $store, array $query): array
    {
        $shopId = $this->resolvedShopId($store);

        if ($shopId !== null) {
            $query['shop_id'] = $shopId;
        }

        return $query;
    }

    protected function resolvedShopId(MarketplaceStore $store): ?int
    {
        $sellerId = trim((string) $store->seller_id);

        return $sellerId !== '' && ctype_digit($sellerId)
            ? (int) $sellerId
            : null;
    }

    protected function offerSku(ChannelListing $listing): string
    {
        $sku = trim((string) (
            $listing->channelProduct?->stock_code
            ?: data_get($listing->channelProduct?->raw_payload, 'shop_sku')
            ?: data_get($listing->channelProduct?->raw_payload, 'offer_sku')
            ?: data_get($listing->channelProduct?->raw_payload, 'sku')
        ));

        if ($sku === '') {
            throw new \RuntimeException('Koçtaş fiyat/stok push için seller offer SKU zorunludur.');
        }

        return $sku;
    }

    protected function buildPriceImportCsv(string $offerSku, float $price): string
    {
        return $this->buildCsv(
            ['offer-sku', 'price', 'discount-price', 'discount-start-date', 'discount-end-date'],
            [[
                $offerSku,
                $this->decimalString($price),
                '',
                '',
                '',
            ]]
        );
    }

    protected function buildStockImportCsv(string $offerSku, int $quantity): string
    {
        return $this->buildCsv(
            ['offer-sku', 'quantity', 'warehouse-code', 'update-delete'],
            [[
                $offerSku,
                (string) max(0, $quantity),
                '',
                'update',
            ]]
        );
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, string>>  $rows
     */
    protected function buildCsv(array $headers, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new \RuntimeException('Koçtaş import CSV içeriği oluşturulamadı.');
        }

        fputcsv($handle, $headers, ';');

        foreach ($rows as $row) {
            fputcsv($handle, $row, ';');
        }

        rewind($handle);
        $content = stream_get_contents($handle) ?: '';
        fclose($handle);

        if ($content === '') {
            throw new \RuntimeException('Koçtaş import CSV içeriği boş üretildi.');
        }

        return $content;
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

    protected function statusHas(string $status, array $needles): bool
    {
        return Str::contains(Str::lower($status), array_map(fn (string $needle) => Str::lower($needle), $needles));
    }

    /**
     * @param  array<int, mixed>  $references
     */
    protected function barcodeFromReferences(array $references): ?string
    {
        foreach ($references as $reference) {
            if (!is_array($reference)) {
                continue;
            }

            $type = Str::upper((string) (data_get($reference, 'type') ?: data_get($reference, 'reference_type') ?: ''));
            $value = trim((string) (data_get($reference, 'value') ?: data_get($reference, 'reference') ?: ''));

            if ($value !== '' && in_array($type, ['EAN', 'UPC', 'GTIN'], true)) {
                return $value;
            }
        }

        return null;
    }

    protected function referenceValueByType(string $type, mixed $value): ?string
    {
        $normalizedType = Str::upper($type);
        $normalizedValue = trim((string) $value);

        if ($normalizedValue === '' || !in_array($normalizedType, ['EAN', 'UPC', 'GTIN'], true)) {
            return null;
        }

        return $normalizedValue;
    }
}
