<?php

namespace App\Services\Marketplace\Connectors;

use App\Models\MarketplaceStore;
use App\Models\MarketplaceQuestion;
use App\Services\Marketplace\Connectors\Concerns\NormalizesCustomerQuestions;
use App\Services\Marketplace\Contracts\AnswersCustomerQuestions;
use App\Services\Marketplace\Contracts\PullsCustomerQuestions;
use App\Services\Marketplace\Contracts\PullsClaims;
use App\Services\Marketplace\Contracts\PullsFinancials;
use App\Services\Marketplace\Contracts\PullsOrders;
use App\Services\Marketplace\Contracts\PullsProducts;
use App\Services\Marketplace\Contracts\TestsConnection;
use App\Services\Marketplace\Support\PazaramaOrderStatusResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PazaramaConnector extends AbstractMarketplaceConnector implements PullsOrders, PullsProducts, PullsFinancials, PullsCustomerQuestions, PullsClaims, AnswersCustomerQuestions, TestsConnection
{
    use NormalizesCustomerQuestions;

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

    protected function tokenUrl(): string
    {
        return config('marketplace.pazarama.token_url', 'https://isortagimgiris.pazarama.com/connect/token');
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
            'questions' => true,
            'question_answer' => true,
            'claims' => true,
            'claim_approve' => false,
            'claim_reject' => false,
        ];
    }

    public function testConnection(MarketplaceStore $store): array
    {
        try {
            $token = $this->getAccessToken($store);
            $endDate = CarbonImmutable::now('UTC');
            $startDate = $endDate->subDay();
            $response = $this->request($store)
                ->post('order/getOrdersForApi', [
                    'startDate' => $startDate->format('Y-m-d\TH:i:s'),
                    'endDate' => $endDate->format('Y-m-d\TH:i:s'),
                    'pageSize' => 1,
                    'pageNumber' => 1,
                ])
                ->throw()
                ->json();

            return [
                'ok' => filled($token),
                'message' => filled($token) ? 'Bağlantı başarıyla doğrulandı.' : 'Token oluşturulamadı.',
                'meta' => [
                    'provider' => $this->providerKey(),
                    'store_id' => $store->id,
                    'items_returned' => count(Arr::wrap(data_get($response, 'data', []))),
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
        [$startDate, $endDate, $orderWindowMeta] = $this->resolveOrderWindow($requestedStartDate, $endDate);

        do {
            $response = $this->request($store)
                ->post('order/getOrdersForApi', [
                    'startDate' => $startDate->format('Y-m-d\TH:i:s'),
                    'endDate' => $endDate->format('Y-m-d\TH:i:s'),
                    'pageNumber' => $page,
                    'pageSize' => $size,
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
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: array<string, mixed>}
     */
    protected function resolveOrderWindow(CarbonImmutable $requestedStartDate, CarbonImmutable $endDate): array
    {
        $requestedEndDate = $endDate;
        if ($requestedStartDate->greaterThan($requestedEndDate)) {
            throw new \InvalidArgumentException('Pazarama sipariş başlangıç tarihi bitiş tarihinden sonra olamaz.');
        }

        $historyLimitDays = max(1, (int) config('marketplace.pazarama.order_history_limit_days', 90));
        $minAllowedStartDate = CarbonImmutable::now('UTC')->subDays($historyLimitDays);

        $effectiveStartDate = $requestedStartDate->lessThan($minAllowedStartDate)
            ? $minAllowedStartDate
            : $requestedStartDate;

        $endAdjusted = $endDate->lessThan($effectiveStartDate);
        if ($endAdjusted) {
            $endDate = $effectiveStartDate->addHour();
        }

        return [
            $effectiveStartDate,
            $endDate,
            [
                'history_limit_days' => $historyLimitDays,
                'requested_start_date' => $requestedStartDate->toIso8601String(),
                'requested_end_date' => $requestedEndDate->toIso8601String(),
                'effective_start_date' => $effectiveStartDate->toIso8601String(),
                'effective_end_date' => $endDate->toIso8601String(),
                'end_adjusted' => $endAdjusted,
                'window_clamped' => ! $effectiveStartDate->equalTo($requestedStartDate) || $endAdjusted,
            ],
        ];
    }

    public function pullProducts(MarketplaceStore $store, array $options = []): array
    {
        $items = [];
        $size = min((int) ($options['page_size'] ?? 100), 100);
        $endDate = CarbonImmutable::parse($options['end_date'] ?? now())->setTimezone('UTC');

        foreach ([true, false] as $approved) {
            $page = 1;

            do {
                $response = $this->request($store)
                    ->get('product/products', [
                        'Approved' => $approved ? 'true' : 'false',
                        'Size' => $size,
                        'Page' => $page,
                    ])
                    ->throw()
                    ->json();

                $content = Arr::wrap(Arr::get($response, 'data', []));

                foreach ($content as $productPayload) {
                    $productPayload['_zolm_approved'] = $approved;
                    $items[] = $this->normalizeProduct($productPayload);
                }

                // Pazarama uses totalCount for pagination
                $totalCount = (int) Arr::get($response, 'totalCount', count($content));
                $totalPages = max(1, (int) ceil($totalCount / $size));

                $page++;
            } while ($page <= $totalPages);
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

    public function pullCustomerQuestions(MarketplaceStore $store, array $options = []): array
    {
        $page = 1;
        $size = min(100, max(1, (int) ($options['page_size'] ?? config('marketplace.pazarama.question_page_size', 100))));
        $maxPages = max(1, (int) config('marketplace.pazarama.max_question_pages_per_sync', 10));
        $startDate = CarbonImmutable::parse($options['start_date'] ?? now()->subDays(7))->setTimezone('UTC');
        $endDate = CarbonImmutable::parse($options['end_date'] ?? now())->setTimezone('UTC');
        $path = trim((string) config('marketplace.pazarama.question_list_path', 'question/getQuestionsForApi'), '/');
        $method = Str::upper((string) config('marketplace.pazarama.question_list_method', 'POST'));
        $items = [];

        do {
            $payload = [
                'StartDate' => $startDate->format('Y-m-d\TH:i:s'),
                'EndDate' => $endDate->format('Y-m-d\TH:i:s'),
                'Page' => $page,
                'Size' => $size,
                'Status' => $options['status'] ?? null,
            ];

            $response = $method === 'GET'
                ? $this->request($store)->get($path, array_filter($payload, fn ($value) => $value !== null && $value !== ''))
                : $this->request($store)->post($path, array_filter($payload, fn ($value) => $value !== null && $value !== ''));

            $decoded = $response->throw()->json();
            $decoded = is_array($decoded) ? $decoded : [];
            $rows = $this->questionRowsFromPayload($decoded, ['data', 'items', 'questions', 'questionList']);

            foreach ($rows as $row) {
                $items[] = $this->normalizePazaramaQuestion($row);
            }

            $totalCount = (int) (data_get($decoded, 'totalCount') ?: data_get($decoded, 'data.totalCount') ?: count($rows));
            $totalPages = max(1, (int) ceil($totalCount / $size));
            $page++;
        } while ($rows !== [] && $page <= min($totalPages, $maxPages));

        return [
            'items' => $items,
            'meta' => [
                'items_received' => count($items),
                'cursor_after' => $endDate->toIso8601String(),
                'pages_processed' => $page - 1,
                'more_pages_available' => $page <= ($totalPages ?? 1),
                'endpoint' => $path,
            ],
        ];
    }

    public function pullClaims(MarketplaceStore $store, array $options = []): array
    {
        $items = [];
        $page = 1;
        $size = min(100, max(1, (int) ($options['page_size'] ?? config('marketplace.pazarama.claim_page_size', 100))));
        $maxPages = max(1, (int) config('marketplace.pazarama.max_order_pages_per_sync', 20));
        $startDate = CarbonImmutable::parse($options['start_date'] ?? now()->subDays(7))->setTimezone('UTC');
        $endDate = CarbonImmutable::parse($options['end_date'] ?? now())->setTimezone('UTC');

        do {
            $response = $this->request($store)
                ->post('order/getOrdersForApi', [
                    'startDate' => $startDate->format('Y-m-d\TH:i:s'),
                    'endDate' => $endDate->format('Y-m-d\TH:i:s'),
                    'pageNumber' => $page,
                    'pageSize' => $size,
                ])
                ->throw()
                ->json();

            $content = Arr::wrap(Arr::get($response, 'data', []));

            foreach ($content as $orderPayload) {
                if (is_array($orderPayload) && $this->hasReturnSignal($orderPayload)) {
                    $items[] = $this->normalizeClaimFromOrder($orderPayload);
                }
            }

            $totalCount = (int) Arr::get($response, 'totalCount', count($content));
            $totalPages = max(1, (int) ceil($totalCount / $size));
            $page++;
        } while ($page <= min($totalPages, $maxPages));

        return [
            'items' => $items,
            'meta' => [
                'items_received' => count($items),
                'cursor_after' => $endDate->toIso8601String(),
                'pages_processed' => $page - 1,
                'source' => 'order_refund_fields',
            ],
        ];
    }

    public function answerCustomerQuestion(MarketplaceQuestion $question, string $answer): array
    {
        $question->loadMissing('store.connection');

        $path = trim((string) config('marketplace.pazarama.question_answer_path', 'question/answerQuestionForApi'), '/');
        $payload = [
            'QuestionId' => $question->external_question_id,
            'Id' => $question->external_question_id,
            'Answer' => $answer,
            'AnswerText' => $answer,
        ];

        $response = $this->request($question->store)
            ->post($path, $payload)
            ->throw();

        $decoded = $response->json();
        $decoded = is_array($decoded) ? $decoded : [];

        if ((bool) data_get($decoded, 'success') === false && filled(data_get($decoded, 'message'))) {
            throw new \RuntimeException((string) data_get($decoded, 'message'));
        }

        return [
            'external_answer_id' => (string) (
                data_get($decoded, 'data.id')
                ?: data_get($decoded, 'id')
                ?: $question->external_question_id
            ),
            'response_status' => $response->status(),
            'response' => $decoded,
        ];
    }

    protected function request(MarketplaceStore $store): PendingRequest
    {
        $accessToken = $this->getAccessToken($store);

        return Http::baseUrl($this->apiBaseUrl($store).'/')
            ->timeout((int) config('marketplace.pazarama.request_timeout', 30))
            ->acceptJson()
            ->withToken($accessToken);
    }

    protected function apiBaseUrl(MarketplaceStore $store): string
    {
        $baseUrl = trim((string) ($store->connection?->api_base_url ?: $this->defaultApiBaseUrl()));

        if ($baseUrl === '') {
            throw new \RuntimeException('Pazarama API base URL boş.');
        }

        $host = Str::lower((string) parse_url($baseUrl, PHP_URL_HOST));

        if ($host === 'isortagimgiris.pazarama.com') {
            return rtrim((string) $this->defaultApiBaseUrl(), '/');
        }

        return rtrim($baseUrl, '/');
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
            $response = Http::withBasicAuth($apiKey, $apiSecret)
                ->asForm()
                ->post($this->tokenUrl(), [
                    'grant_type' => 'client_credentials',
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
        $packageId = (string) (data_get($payload, 'orderId') ?: data_get($payload, 'id') ?: $orderNumber);

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
        $stockCode = $this->stockCodeFromPayload($line, 'product.stockCode');
        if ($stockCode === '') {
            $stockCode = $this->stockCodeFromPayload($line, 'product.code');
        }

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
            'barcode' => data_get($line, 'product.barcode') ?: data_get($line, 'product.code'),
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
        $stockCode = $this->stockCodeFromPayload($payload, 'stockCode');
        if ($stockCode === '') {
            $stockCode = $this->stockCodeFromPayload($payload, 'code');
        }

        $barcode = data_get($payload, 'barcode');
        $externalProductId = (string) (data_get($payload, 'id') ?: data_get($payload, 'code') ?: $barcode ?: $stockCode);

        // Pazarama doesn't explicitly expose parent vs variant in standard basic GET but serves variants. 
        // We fallback parent to id logic.
        return [
            'product' => [
                'external_product_id' => $externalProductId,
                'external_parent_id' => (string) (data_get($payload, 'parentProductId') ?: data_get($payload, 'groupCode') ?: ''),
                'stock_code' => $stockCode,
                'barcode' => $barcode,
                'title' => data_get($payload, 'displayName') ?: data_get($payload, 'name'),
                'brand' => data_get($payload, 'brand.name') ?: data_get($payload, 'brandName'),
                'category_name' => data_get($payload, 'category.name') ?: data_get($payload, 'categoryName'),
                'vat_rate' => $this->toDecimal(data_get($payload, 'vatRate')),
                'raw_payload' => $payload,
            ],
            'listing' => array_merge([
                'listing_id' => $externalProductId,
                'listing_status' => $this->normalizeProductStatus($payload),
                'sale_price' => $this->toDecimal(data_get($payload, 'salePrice')),
                'list_price' => $this->toDecimal(data_get($payload, 'listPrice')),
                'commission_rate' => $this->toDecimal(data_get($payload, 'commissionRate') ?: data_get($payload, 'commissionRate.value') ?: data_get($payload, 'commission')),
                'commission_source' => 'catalog',
                'currency' => 'TRY',
                'stock_quantity' => (int) (data_get($payload, 'stockQuantity') ?? data_get($payload, 'stockCount', 0)),
                'published_at' => $this->normalizeDate(data_get($payload, 'createdDate')),
            ], $this->catalogDeliveryTermData($payload)),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizePazaramaQuestion(array $payload): array
    {
        return $this->normalizeQuestionPayload($payload, [
            'external_question_id' => (string) (
                data_get($payload, 'questionId')
                ?: data_get($payload, 'id')
                ?: data_get($payload, 'messageId')
            ),
            'question_type' => 'product',
            'question_text' => $this->questionTextValue(
                data_get($payload, 'questionText')
                ?: data_get($payload, 'question')
                ?: data_get($payload, 'message')
            ),
            'product_name' => data_get($payload, 'productName')
                ?: data_get($payload, 'product.name')
                ?: data_get($payload, 'title'),
            'product_sku' => data_get($payload, 'stockCode')
                ?: data_get($payload, 'product.stockCode')
                ?: data_get($payload, 'sku'),
            'product_barcode' => data_get($payload, 'barcode')
                ?: data_get($payload, 'product.barcode'),
            'asked_at' => $this->questionDate(
                data_get($payload, 'createdDate')
                ?: data_get($payload, 'createDate')
                ?: data_get($payload, 'questionDate')
            ),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function hasReturnSignal(array $payload): bool
    {
        $signal = Str::of(json_encode([
            data_get($payload, 'refundStatusName'),
            data_get($payload, 'refundStatus'),
            data_get($payload, 'refundStatusId'),
            data_get($payload, 'refundDate'),
            data_get($payload, 'returnStatus'),
            data_get($payload, 'returnReason'),
            data_get($payload, 'orderStatusName'),
            data_get($payload, 'items'),
        ], JSON_UNESCAPED_UNICODE) ?: '')
            ->lower()
            ->ascii()
            ->toString();

        return str_contains($signal, 'refund')
            || str_contains($signal, 'return')
            || str_contains($signal, 'iade')
            || filled(data_get($payload, 'refundDate'));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizeClaimFromOrder(array $payload): array
    {
        $orderNumber = (string) data_get($payload, 'orderNumber');
        $claimId = (string) (
            data_get($payload, 'refundId')
            ?: data_get($payload, 'returnId')
            ?: data_get($payload, 'orderId')
            ?: data_get($payload, 'id')
            ?: $orderNumber
        );

        return [
            'external_claim_id' => 'pazarama-'.$claimId,
            'order_number' => $orderNumber,
            'cargo_tracking_number' => data_get($payload, 'items.0.cargo.trackingNumber') ?: data_get($payload, 'cargoTrackingNumber'),
            'cargo_provider' => data_get($payload, 'items.0.cargo.companyName') ?: data_get($payload, 'cargoCompany.name'),
            'status' => data_get($payload, 'refundStatusName') ?: data_get($payload, 'refundStatus') ?: data_get($payload, 'returnStatus') ?: data_get($payload, 'orderStatusName'),
            'type' => 'return',
            'reason' => data_get($payload, 'returnReason') ?: data_get($payload, 'refundReason'),
            'reason_detail' => data_get($payload, 'refundStatusDescription'),
            'customer_name' => data_get($payload, 'customerName') ?: data_get($payload, 'buyer.fullName'),
            'created_date' => data_get($payload, 'refundDate') ?: data_get($payload, 'updatedDate') ?: data_get($payload, 'orderDate'),
            'items' => collect(Arr::wrap(data_get($payload, 'items', data_get($payload, 'itemList', []))))
                ->filter(fn ($row) => is_array($row))
                ->values()
                ->all(),
            'raw_payload' => $payload,
        ];
    }

    protected function normalizeProductStatus(array $payload): string
    {
        $state = data_get($payload, 'state');
        $statusText = Str::of((string) (data_get($payload, 'stateDescription') ?: data_get($payload, 'status')))
            ->lower()
            ->ascii()
            ->toString();

        if ((string) $state === '3' || str_contains($statusText, 'onaylandi') || data_get($payload, '_zolm_approved') === true) {
            return 'active';
        }

        if (str_contains($statusText, 'bekliyor') || str_contains($statusText, 'onay')) {
            return 'pending';
        }

        if (data_get($payload, 'active') === true) {
            return 'active';
        }

        return 'inactive';
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
        if (is_array($value)) {
            $value = data_get($value, 'value');
        }

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
