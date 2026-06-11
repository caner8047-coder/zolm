<?php

namespace App\Services\Marketplace\Connectors;

use App\Models\MarketplaceQuestion;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\Connectors\Concerns\NormalizesCustomerQuestions;
use App\Services\Marketplace\Contracts\AnswersCustomerQuestions;
use App\Services\Marketplace\Contracts\PullsCustomerQuestions;
use App\Services\Marketplace\Contracts\PullsClaims;
use App\Services\Marketplace\Contracts\PullsOrders;
use App\Services\Marketplace\Contracts\PullsProducts;
use App\Services\Marketplace\Contracts\TestsConnection;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CiceksepetiConnector extends AbstractMarketplaceConnector implements PullsOrders, PullsProducts, PullsCustomerQuestions, PullsClaims, AnswersCustomerQuestions, TestsConnection
{
    use NormalizesCustomerQuestions;

    public function providerKey(): string
    {
        return 'ciceksepeti';
    }

    public function displayName(): string
    {
        return 'Çiçeksepeti';
    }

    public function defaultApiBaseUrl(): ?string
    {
        return config('marketplace.ciceksepeti.base_url');
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
        $endDate = CarbonImmutable::now('Europe/Istanbul');
        $startDate = $endDate->subDay();

        $response = $this->postWithRateLimitRetry($store, 'Order/GetOrders', [
            'startDate' => $this->formatOrderDate($startDate),
            'endDate' => $this->formatOrderDate($endDate),
            'pageSize' => 1,
            'page' => 0,
            'isOrderStatusActive' => true,
        ]);

        return [
            'ok' => true,
            'message' => 'Çiçeksepeti bağlantısı doğrulandı.',
            'meta' => [
                'provider' => $this->providerKey(),
                'base_url' => $this->baseUrl($store),
                'total_count' => (int) data_get($response, 'orderListCount', count(Arr::wrap(data_get($response, 'supplierOrderListWithBranch', [])))),
                'items_returned' => count(Arr::wrap(data_get($response, 'supplierOrderListWithBranch', []))),
                'user_agent' => $this->userAgent($store),
            ],
        ];
    }

    public function pullOrders(MarketplaceStore $store, array $options = []): array
    {
        $pageSize = min(100, max(1, (int) ($options['page_size'] ?? config('marketplace.ciceksepeti.order_page_size', 100))));
        $maxPages = max(1, (int) config('marketplace.ciceksepeti.max_order_pages_per_sync', 50));
        $startDate = CarbonImmutable::parse($options['start_date'] ?? now()->subDay())->setTimezone('Europe/Istanbul');
        $endDate = CarbonImmutable::parse($options['end_date'] ?? now())->setTimezone('Europe/Istanbul');
        $groupedRows = [];
        $pagesProcessed = 0;

        foreach ($this->orderWindows($startDate, $endDate) as [$windowStart, $windowEnd]) {
            $page = 0;

            do {
                $response = $this->postWithRateLimitRetry(
                    $store,
                    'Order/GetOrders',
                    array_filter([
                        'startDate' => filled($options['order_number'] ?? null) ? null : $this->formatOrderDate($windowStart),
                        'endDate' => filled($options['order_number'] ?? null) ? null : $this->formatOrderDate($windowEnd),
                        'pageSize' => $pageSize,
                        'page' => $page,
                        'orderNo' => filled($options['order_number'] ?? null) ? (int) $options['order_number'] : null,
                        'isOrderStatusActive' => true,
                    ], fn ($value) => $value !== null && $value !== '')
                );

                $rows = collect(Arr::wrap(data_get($response, 'supplierOrderListWithBranch', [])))
                    ->filter(fn ($row) => is_array($row))
                    ->values();

                foreach ($rows as $row) {
                    $orderId = (string) data_get($row, 'orderId');
                    $packageId = (string) (
                        data_get($row, 'cargoNumber')
                        ?: data_get($row, 'partialNumber')
                        ?: data_get($row, 'orderId')
                    );
                    $key = $orderId.'|'.$packageId;

                    if (!isset($groupedRows[$key])) {
                        $groupedRows[$key] = [];
                    }

                    $groupedRows[$key][] = $row;
                }

                $page++;
                $pagesProcessed++;
            } while (
                $rows->count() === $pageSize
                && $pagesProcessed < $maxPages
            );

            if ($pagesProcessed >= $maxPages) {
                break;
            }
        }

        $items = collect($groupedRows)
            ->map(fn (array $rows) => $this->normalizeOrderPackage($rows))
            ->values()
            ->all();

        return [
            'items' => $items,
            'meta' => [
                'items_received' => count($items),
                'pages_processed' => $pagesProcessed,
                'cursor_after' => $endDate->toIso8601String(),
                'more_pages_available' => $pagesProcessed >= $maxPages,
            ],
        ];
    }

    public function pullProducts(MarketplaceStore $store, array $options = []): array
    {
        $pageSize = min(60, max(1, (int) ($options['page_size'] ?? config('marketplace.ciceksepeti.product_page_size', 60))));
        $maxPages = max(1, (int) config('marketplace.ciceksepeti.max_product_pages_per_sync', 50));
        $page = 1;
        $pagesProcessed = 0;
        $totalCount = 0;
        $items = [];

        do {
            $response = $this->getProductsPage($store, array_filter([
                    'ProductStatus' => $this->productStatusFilter($options['status'] ?? null),
                    'PageSize' => $pageSize,
                    'Page' => $page,
                    'StockCode' => $options['stock_code'] ?? null,
                    'variantName' => $options['variant_name'] ?? null,
                ], fn ($value) => $value !== null && $value !== ''));

            $products = collect(Arr::wrap(data_get($response, 'products', [])))
                ->filter(fn ($row) => is_array($row))
                ->values();

            foreach ($products as $productPayload) {
                $items[] = $this->normalizeProduct($productPayload);
            }

            $totalCount = max($totalCount, (int) data_get($response, 'totalCount', $products->count()));
            $page++;
            $pagesProcessed++;
        } while (
            $products->isNotEmpty()
            && count($items) < $totalCount
            && $pagesProcessed < $maxPages
        );

        return [
            'items' => $items,
            'meta' => [
                'items_received' => count($items),
                'pages_processed' => $pagesProcessed,
                'total_count' => $totalCount,
                'supports_incremental_window' => false,
                'cursor_after' => now()->toIso8601String(),
                'more_pages_available' => count($items) < $totalCount,
            ],
        ];
    }

    public function pullCustomerQuestions(MarketplaceStore $store, array $options = []): array
    {
        $pageSize = min(100, max(1, (int) ($options['page_size'] ?? config('marketplace.ciceksepeti.question_page_size', 100))));
        $maxPages = max(1, (int) config('marketplace.ciceksepeti.max_question_pages_per_sync', 10));
        $startDate = CarbonImmutable::parse($options['start_date'] ?? now()->subDays(7))->setTimezone('Europe/Istanbul');
        $endDate = CarbonImmutable::parse($options['end_date'] ?? now())->setTimezone('Europe/Istanbul');
        $path = trim((string) config('marketplace.ciceksepeti.question_list_path', 'sellerquestions'), '/');
        $method = Str::upper((string) config('marketplace.ciceksepeti.question_list_method', 'GET'));
        $usesSellerQuestions = $this->usesSellerQuestionsEndpoint($path);
        $items = [];
        $pagesProcessed = 0;
        $morePagesAvailable = false;

        foreach ($this->questionWindows($startDate, $endDate) as [$windowStart, $windowEnd]) {
            foreach ($this->questionBranchActionIds($options, $usesSellerQuestions) as $branchActionId) {
                $page = $usesSellerQuestions
                    ? max(0, (int) config('marketplace.ciceksepeti.question_first_page', 0))
                    : 0;

                do {
                    $payload = $usesSellerQuestions
                        ? $this->sellerQuestionsPayload($windowStart, $windowEnd, $page, $options, $branchActionId)
                        : $this->legacyQuestionsPayload($windowStart, $windowEnd, $page, $pageSize, $options);

                    try {
                        $decoded = $method === 'GET'
                            ? $this->getWithRateLimitRetry($store, $path, array_filter($payload, fn ($value) => $value !== null && $value !== ''))
                            : $this->postWithRateLimitRetry($store, $path, array_filter($payload, fn ($value) => $value !== null && $value !== ''));
                    } catch (RequestException $exception) {
                        if ($branchActionId !== null && $exception->response && $exception->response->status() === 400) {
                            break;
                        }

                        throw $exception;
                    }

                    $decoded = is_array($decoded) ? $decoded : [];
                    $rows = $this->questionRowsFromPayload($decoded, [
                        'questions',
                        'questionList',
                        'data',
                        'items',
                    ]);

                    foreach ($rows as $row) {
                        $items[] = $this->normalizeCiceksepetiQuestion($row);
                    }

                    $pagesProcessed++;
                    $page++;

                    $hasNextPage = filter_var(data_get($decoded, 'hasNextPage'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    $totalCount = (int) (
                        data_get($decoded, 'totalCount')
                        ?: data_get($decoded, 'questionListCount')
                        ?: data_get($decoded, 'count')
                        ?: count($rows)
                    );
                    $totalPages = max(1, (int) ceil($totalCount / $pageSize));
                    $morePagesAvailable = $usesSellerQuestions
                        ? (bool) ($hasNextPage ?? false)
                        : ($rows !== [] && $page < $totalPages);
                } while ($morePagesAvailable && $pagesProcessed < $maxPages);

                if ($pagesProcessed >= $maxPages) {
                    break;
                }
            }

            if ($pagesProcessed >= $maxPages) {
                break;
            }
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
                'more_pages_available' => $morePagesAvailable,
                'endpoint' => $path,
            ],
        ];
    }

    public function pullClaims(MarketplaceStore $store, array $options = []): array
    {
        $pageSize = min(100, max(1, (int) ($options['page_size'] ?? config('marketplace.ciceksepeti.claim_page_size', 100))));
        $maxPages = max(1, (int) config('marketplace.ciceksepeti.max_order_pages_per_sync', 20));
        $startDate = CarbonImmutable::parse($options['start_date'] ?? now()->subDays(7))->setTimezone('Europe/Istanbul');
        $endDate = CarbonImmutable::parse($options['end_date'] ?? now())->setTimezone('Europe/Istanbul');
        $groupedRows = [];
        $pagesProcessed = 0;

        foreach ($this->orderWindows($startDate, $endDate) as [$windowStart, $windowEnd]) {
            $page = 0;

            do {
                $response = $this->postWithRateLimitRetry(
                    $store,
                    'Order/GetOrders',
                    [
                        'startDate' => $this->formatOrderDate($windowStart),
                        'endDate' => $this->formatOrderDate($windowEnd),
                        'pageSize' => $pageSize,
                        'page' => $page,
                        'isOrderStatusActive' => true,
                    ]
                );

                $allRows = collect(Arr::wrap(data_get($response, 'supplierOrderListWithBranch', [])))
                    ->filter(fn ($row) => is_array($row))
                    ->values();
                $rows = $allRows
                    ->filter(fn ($row) => $this->hasReturnSignal($row))
                    ->values();

                foreach ($rows as $row) {
                    $orderId = (string) data_get($row, 'orderId');
                    $key = $orderId.'|'.(data_get($row, 'cancellationResult') ?: data_get($row, 'orderProductStatus') ?: 'return');
                    $groupedRows[$key] ??= [];
                    $groupedRows[$key][] = $row;
                }

                $page++;
                $pagesProcessed++;
            } while ($allRows->count() === $pageSize && $pagesProcessed < $maxPages);

            if ($pagesProcessed >= $maxPages) {
                break;
            }
        }

        $items = collect($groupedRows)
            ->map(fn (array $rows) => $this->normalizeClaimRows($rows))
            ->values()
            ->all();

        return [
            'items' => $items,
            'meta' => [
                'items_received' => count($items),
                'cursor_after' => $endDate->toIso8601String(),
                'pages_processed' => $pagesProcessed,
                'source' => 'order_return_fields',
            ],
        ];
    }

    public function answerCustomerQuestion(MarketplaceQuestion $question, string $answer): array
    {
        $question->loadMissing('store.connection');

        $path = trim((string) config('marketplace.ciceksepeti.question_answer_path', 'sellerquestions/{id}'), '/');
        $path = str_replace(['{id}', '{question_id}', '{questionId}'], $question->external_question_id, $path);
        $method = Str::upper((string) config('marketplace.ciceksepeti.question_answer_method', 'PUT'));
        $payload = $this->ciceksepetiAnswerPayload($question, $answer, $path);

        $response = $method === 'PUT'
            ? $this->putResponseWithRateLimitRetry($question->store, $path, $payload)
            : $this->postResponseWithRateLimitRetry($question->store, $path, $payload);
        $decoded = $response->json();
        $decoded = is_array($decoded) ? $decoded : [];

        if ((bool) data_get($decoded, 'isSuccess') === false && filled(data_get($decoded, 'message'))) {
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

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    protected function getProductsPage(MarketplaceStore $store, array $query): array
    {
        return $this->getWithRateLimitRetry($store, 'Products', $query);
    }

    protected function request(MarketplaceStore $store): PendingRequest
    {
        return Http::baseUrl($this->baseUrl($store))
            ->acceptJson()
            ->timeout((int) config('marketplace.ciceksepeti.request_timeout', 45))
            ->withHeaders([
                'x-api-key' => $this->apiKey($store),
                'User-Agent' => $this->userAgent($store),
            ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function postWithRateLimitRetry(MarketplaceStore $store, string $path, array $payload): array
    {
        $decoded = $this->postResponseWithRateLimitRetry($store, $path, $payload)->json();

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    protected function getWithRateLimitRetry(MarketplaceStore $store, string $path, array $query): array
    {
        $decoded = $this->getResponseWithRateLimitRetry($store, $path, $query)->json();

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function postResponseWithRateLimitRetry(MarketplaceStore $store, string $path, array $payload): Response
    {
        return $this->sendWithRateLimitRetry(
            fn (): Response => $this->request($store)->post($path, $payload)
        );
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function getResponseWithRateLimitRetry(MarketplaceStore $store, string $path, array $query): Response
    {
        return $this->sendWithRateLimitRetry(
            fn (): Response => $this->request($store)->get($path, $query)
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function putResponseWithRateLimitRetry(MarketplaceStore $store, string $path, array $payload): Response
    {
        return $this->sendWithRateLimitRetry(
            fn (): Response => $this->request($store)->put($path, $payload)
        );
    }

    /**
     * @param  callable(): Response  $send
     */
    protected function sendWithRateLimitRetry(callable $send): Response
    {
        $attempt = 0;
        $maxAttempts = max(1, (int) config('marketplace.ciceksepeti.product_rate_limit_max_attempts', 3));

        while (true) {
            $response = $send();

            if (!$this->isCiceksepetiRateLimited($response)) {
                return $response->throw();
            }

            $attempt++;

            if ($attempt >= $maxAttempts) {
                return $response->throw();
            }

            $this->waitForRateLimitWindow($response);
        }
    }

    protected function isCiceksepetiRateLimited(Response $response): bool
    {
        if ($response->status() !== 400) {
            return false;
        }

        $message = (string) (data_get($response->json(), 'Message') ?: $response->body());

        return Str::contains($message, 'Limit aşımı')
            && Str::contains($message, '5 saniyede 1 kez');
    }

    protected function waitForRateLimitWindow(Response $response): void
    {
        $message = (string) (data_get($response->json(), 'Message') ?: $response->body());
        preg_match('/Kalan Süre:\s*(\d+)\s*saniye/ui', $message, $matches);

        $retryAfterSeconds = isset($matches[1]) ? (int) $matches[1] : 0;
        $graceSeconds = max(0, (int) config('marketplace.ciceksepeti.product_rate_limit_grace_seconds', 1));
        $waitSeconds = $retryAfterSeconds + $graceSeconds;

        if ($waitSeconds > 0) {
            sleep($waitSeconds);
        }
    }

    protected function baseUrl(MarketplaceStore $store): string
    {
        $baseUrl = trim((string) ($store->connection?->api_base_url ?: config('marketplace.ciceksepeti.base_url')));

        if ($baseUrl === '') {
            throw new \RuntimeException('Çiçeksepeti API base URL boş.');
        }

        $baseUrl = rtrim($baseUrl, '/');
        $host = Str::lower((string) parse_url($baseUrl, PHP_URL_HOST));
        $path = trim((string) parse_url($baseUrl, PHP_URL_PATH), '/');

        if ($path === '' && in_array($host, ['apis.ciceksepeti.com', 'sandbox-apis.ciceksepeti.com'], true)) {
            return $baseUrl.'/api/v1';
        }

        return $baseUrl;
    }

    protected function apiKey(MarketplaceStore $store): string
    {
        $apiKey = trim((string) data_get($store->connection?->credentials_encrypted, 'api_key'));

        if ($apiKey === '') {
            throw new \RuntimeException('Çiçeksepeti API key zorunludur.');
        }

        return $apiKey;
    }

    protected function userAgent(MarketplaceStore $store): string
    {
        $sellerId = trim((string) ($store->seller_id ?: data_get($store->connection?->credentials_encrypted, 'seller_id')));

        if ($sellerId === '') {
            throw new \RuntimeException('Çiçeksepeti user-agent için satıcı ID zorunludur.');
        }

        $integratorName = trim((string) (
            data_get($store->connection?->credentials_encrypted, 'extra_user')
            ?: data_get($store->connection?->credentials_encrypted, 'integrator_name')
        ));

        return $integratorName !== ''
            ? $sellerId.'-'.$integratorName
            : $sellerId;
    }

    protected function productStatusFilter(?string $status): ?int
    {
        $normalized = Str::lower(trim((string) $status));

        return match (true) {
            $normalized === 'active',
            str_contains($normalized, 'yayin'),
            str_contains($normalized, 'yayında') => 3,
            str_contains($normalized, 'onay') => 2,
            str_contains($normalized, 'red') => 4,
            str_contains($normalized, 'pasif') => 5,
            str_contains($normalized, 'stok') => 7,
            str_contains($normalized, 'kilit') => 8,
            default => null,
        };
    }

    /**
     * @return array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    protected function orderWindows(CarbonImmutable $startDate, CarbonImmutable $endDate): array
    {
        if ($endDate->lessThanOrEqualTo($startDate)) {
            return [[$startDate, $endDate]];
        }

        $windows = [];
        $cursor = $startDate;

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

    protected function formatOrderDate(CarbonImmutable $value): string
    {
        return $value->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * @return array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    protected function questionWindows(CarbonImmutable $startDate, CarbonImmutable $endDate): array
    {
        if ($endDate->lessThanOrEqualTo($startDate)) {
            return [[$startDate, $endDate]];
        }

        $windows = [];
        $cursor = $startDate;

        while ($cursor->lessThan($endDate)) {
            $windowEnd = $cursor->addDays(31);

            if ($windowEnd->greaterThan($endDate)) {
                $windowEnd = $endDate;
            }

            $windows[] = [$cursor, $windowEnd];
            $cursor = $windowEnd;
        }

        return $windows !== [] ? $windows : [[$startDate, $endDate]];
    }

    protected function questionTimestamp(CarbonImmutable $value): int|string
    {
        $unit = Str::lower((string) config('marketplace.ciceksepeti.question_timestamp_unit', 'date'));

        return match ($unit) {
            'millisecond', 'milliseconds', 'ms' => $value->getTimestamp() * 1000,
            'iso', 'iso8601', 'datetime' => $value->toIso8601String(),
            'date', 'ymd', 'yyyy-mm-dd' => $value->toDateString(),
            default => $value->getTimestamp(),
        };
    }

    protected function usesSellerQuestionsEndpoint(string $path): bool
    {
        return Str::contains(Str::lower($path), 'sellerquestions');
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function sellerQuestionsPayload(CarbonImmutable $startDate, CarbonImmutable $endDate, int $page, array $options, mixed $branchActionId = null): array
    {
        return [
            'Id' => $options['question_id'] ?? null,
            'ProductCode' => $options['product_code'] ?? null,
            'BranchActionId' => $branchActionId,
            'Answered' => $this->ciceksepetiAnsweredFilter($options['status'] ?? null),
            'CreateStartDate' => filled($options['question_id'] ?? null) ? null : $this->questionTimestamp($startDate),
            'CreateEndDate' => filled($options['question_id'] ?? null) ? null : $this->questionTimestamp($endDate),
            'Page' => $page,
        ];
    }

    /**
     * @return array<int, mixed>
     */
    protected function questionBranchActionIds(array $options, bool $usesSellerQuestions): array
    {
        if (!$usesSellerQuestions) {
            return [null];
        }

        if (array_key_exists('branch_action_id', $options)) {
            return [$options['branch_action_id']];
        }

        $configured = config('marketplace.ciceksepeti.question_branch_action_ids', 'all');
        $values = is_string($configured)
            ? explode(',', $configured)
            : Arr::wrap($configured);

        $ids = collect($values)
            ->flatMap(function (mixed $value): array {
                $normalized = Str::lower(trim((string) $value));

                if (in_array($normalized, ['all', 'any', '*'], true)) {
                    return [1, 2, null];
                }

                if (in_array($normalized, ['null', 'none'], true)) {
                    return [null];
                }

                if ($normalized === '') {
                    return [];
                }

                return [is_numeric($value) ? (int) $value : $value];
            })
            ->unique(fn (mixed $value): string => $value === null ? 'null' : (string) $value)
            ->values()
            ->all();

        return $ids !== [] ? $ids : [1, 2, null];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function legacyQuestionsPayload(CarbonImmutable $startDate, CarbonImmutable $endDate, int $page, int $pageSize, array $options): array
    {
        return [
            'startDate' => $this->formatOrderDate($startDate),
            'endDate' => $this->formatOrderDate($endDate),
            'pageSize' => $pageSize,
            'page' => $page,
            'status' => $options['status'] ?? null,
        ];
    }

    protected function ciceksepetiAnsweredFilter(mixed $status): ?string
    {
        $normalized = Str::lower(trim((string) $status));

        return match ($normalized) {
            'answered', 'closed', 'cevaplandi', 'cevaplandı' => 'True',
            'open', 'draft', 'pending', 'bekliyor', 'taslak' => 'False',
            default => null,
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    protected function normalizeOrderPackage(array $rows): array
    {
        $first = Arr::wrap($rows[0] ?? []);
        $lineItems = collect($rows)
            ->filter(fn ($row) => is_array($row))
            ->values();
        $packageStatus = $this->normalizeOrderStatus(
            (int) data_get($first, 'orderItemStatusId'),
            (string) data_get($first, 'orderProductStatus')
        );
        $orderedAt = $this->normalizeOrderDateTime(
            data_get($first, 'orderCreateDate'),
            data_get($first, 'orderCreateTime')
        );
        $modifiedAt = $this->normalizeOrderDateTime(
            data_get($first, 'orderModifyDate'),
            data_get($first, 'orderModifyTime')
        );
        $deliveredAt = $packageStatus === 'delivered'
            ? $this->normalizeFlexibleDate(data_get($first, 'deliveryDate'))
            : null;
        $packageId = (string) (
            data_get($first, 'cargoNumber')
            ?: data_get($first, 'partialNumber')
            ?: data_get($first, 'orderId')
        );

        return [
            'order' => [
                'external_order_id' => (string) data_get($first, 'orderId'),
                'order_number' => (string) data_get($first, 'orderId'),
                'order_status' => $packageStatus,
                'commercial_type' => filled(data_get($first, 'senderCompanyName')) || filled(data_get($first, 'senderTaxNumber'))
                    ? 'commercial'
                    : 'individual',
                'customer_name' => data_get($first, 'receiverName') ?: data_get($first, 'senderName'),
                'customer_email' => data_get($first, 'invoiceEmail'),
                'customer_phone' => data_get($first, 'receiverPhone'),
                'billing_name' => data_get($first, 'senderCompanyName') ?: data_get($first, 'senderName'),
                'billing_tax_number' => (string) (data_get($first, 'senderTaxNumber') ?: ''),
                'shipment_country' => 'TR',
                'shipment_city' => data_get($first, 'receiverCity'),
                'shipment_district' => data_get($first, 'receiverDistrict') ?: data_get($first, 'receiverRegion'),
                'ordered_at' => $orderedAt,
                'approved_at' => in_array($packageStatus, ['approved', 'packing', 'shipped', 'delivered'], true) ? $orderedAt : null,
                'delivered_at' => $deliveredAt,
                'cancelled_at' => $packageStatus === 'cancelled' ? ($modifiedAt ?: $orderedAt) : null,
                'returned_at' => filled(data_get($first, 'cancellationResult')) ? ($modifiedAt ?: $orderedAt) : null,
                'raw_payload' => $first,
            ],
            'package' => [
                'external_package_id' => $packageId,
                'package_number' => $packageId,
                'package_status' => $packageStatus,
                'cargo_company' => data_get($first, 'cargoCompany'),
                'cargo_tracking_number' => data_get($first, 'cargoNumber'),
                'cargo_barcode' => data_get($first, 'partialNumber'),
                'cargo_desi' => null,
                'shipment_provider' => data_get($first, 'cargoCompany') ?: data_get($first, 'deliveryType'),
                'shipped_at' => $packageStatus === 'shipped' ? ($modifiedAt ?: $orderedAt) : null,
                'delivered_at' => $deliveredAt,
                'raw_payload' => $first,
            ],
            'items' => $lineItems
                ->map(function (array $row): array {
                    $quantity = max(1, (int) (data_get($row, 'quantity') ?: 1));
                    $itemPrice = $this->toDecimal(data_get($row, 'itemPrice'));
                    $invoicePrice = $this->toDecimal(data_get($row, 'invoicePrice'));
                    $totalPrice = $this->toDecimal(data_get($row, 'totalPrice'));
                    $discountAmount = $this->toDecimal(data_get($row, 'discount')) ?? 0.0;

                    return [
                        'external_line_id' => (string) (data_get($row, 'orderItemId') ?: data_get($row, 'orderId')),
                        'stock_code' => data_get($row, 'code') ?: data_get($row, 'productCode'),
                        'barcode' => data_get($row, 'barcode'),
                        'product_name' => data_get($row, 'name'),
                        'quantity' => $quantity,
                        'unit_price' => $itemPrice ?? $invoicePrice ?? $totalPrice,
                        'gross_amount' => $itemPrice !== null ? round($itemPrice * $quantity, 2) : ($invoicePrice ?? $totalPrice),
                        'discount_amount' => $discountAmount,
                        'marketplace_discount_amount' => $this->toDecimal(data_get($row, 'csDiscountPart')),
                        'billable_amount' => $invoicePrice ?? $totalPrice,
                        'commission_rate' => null,
                        'vat_rate' => $this->toDecimal(data_get($row, 'tax')),
                        'line_status' => $this->normalizeOrderStatus(
                            (int) data_get($row, 'orderItemStatusId'),
                            (string) data_get($row, 'orderProductStatus')
                        ),
                        'raw_payload' => $row,
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizeProduct(array $payload): array
    {
        $productCode = (string) (data_get($payload, 'productCode') ?: data_get($payload, 'stockCode') ?: '');
        $stockCode = (string) (data_get($payload, 'stockCode') ?: $productCode);

        return [
            'product' => [
                'external_product_id' => $productCode !== '' ? $productCode : $stockCode,
                'external_parent_id' => (string) (data_get($payload, 'mainProductCode') ?: ''),
                'stock_code' => $stockCode !== '' ? $stockCode : null,
                'barcode' => data_get($payload, 'barcode'),
                'title' => data_get($payload, 'productName') ?: data_get($payload, 'variantName'),
                'brand' => data_get($payload, 'brandName'),
                'category_name' => data_get($payload, 'categoryName'),
                'vat_rate' => $this->toDecimal(data_get($payload, 'taxRate')),
                'raw_payload' => $payload,
            ],
            'listing' => array_merge([
                'listing_id' => $productCode !== '' ? $productCode : $stockCode,
                'listing_status' => $this->normalizeProductStatus((string) data_get($payload, 'productStatusType'), data_get($payload, 'isActive')),
                'sale_price' => $this->toDecimal(data_get($payload, 'totalPrice')),
                'list_price' => $this->toDecimal(data_get($payload, 'listPrice')),
                'commission_rate' => $this->toDecimal(data_get($payload, 'commissionRate') ?: data_get($payload, 'commission_rate') ?: data_get($payload, 'commission')),
                'commission_source' => 'catalog',
                'currency' => 'TRY',
                'stock_quantity' => (int) (data_get($payload, 'stockQuantity') ?: 0),
                'published_at' => $this->normalizeFlexibleDate(
                    data_get($payload, 'updatedDate')
                    ?: data_get($payload, 'createdDate')
                ),
            ], $this->catalogDeliveryTermData($payload)),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizeCiceksepetiQuestion(array $payload): array
    {
        return $this->normalizeQuestionPayload($payload, [
            'external_question_id' => (string) (
                data_get($payload, 'questionId')
                ?: data_get($payload, 'QuestionId')
                ?: data_get($payload, 'id')
                ?: data_get($payload, 'Id')
                ?: data_get($payload, 'messageId')
                ?: data_get($payload, 'MessageId')
            ),
            'question_type' => $this->ciceksepetiQuestionType($payload),
            'status' => $this->ciceksepetiQuestionStatus($payload),
            'question_text' => $this->questionTextValue(
                data_get($payload, 'questionText')
                ?: data_get($payload, 'QuestionText')
                ?: data_get($payload, 'question')
                ?: data_get($payload, 'Question')
                ?: data_get($payload, 'message')
                ?: data_get($payload, 'Message')
            ),
            'product_name' => data_get($payload, 'productName')
                ?: data_get($payload, 'ProductName')
                ?: data_get($payload, 'product.name')
                ?: data_get($payload, 'product.Name')
                ?: data_get($payload, 'Product.Name')
                ?: data_get($payload, 'productTitle')
                ?: data_get($payload, 'ProductTitle'),
            'product_sku' => data_get($payload, 'stockCode')
                ?: data_get($payload, 'StockCode')
                ?: data_get($payload, 'product.stockCode')
                ?: data_get($payload, 'product.StockCode')
                ?: data_get($payload, 'Product.StockCode')
                ?: data_get($payload, 'product.code')
                ?: data_get($payload, 'product.Code')
                ?: data_get($payload, 'Product.Code')
                ?: data_get($payload, 'code'),
            'external_product_id' => data_get($payload, 'productCode')
                ?: data_get($payload, 'ProductCode')
                ?: data_get($payload, 'product.code')
                ?: data_get($payload, 'product.Code')
                ?: data_get($payload, 'Product.Code')
                ?: data_get($payload, 'product.id')
                ?: data_get($payload, 'Product.Id'),
            'listing_id' => data_get($payload, 'productCode')
                ?: data_get($payload, 'ProductCode')
                ?: data_get($payload, 'product.code')
                ?: data_get($payload, 'product.Code')
                ?: data_get($payload, 'Product.Code')
                ?: data_get($payload, 'product.id')
                ?: data_get($payload, 'Product.Id'),
            'product_barcode' => data_get($payload, 'barcode')
                ?: data_get($payload, 'Barcode')
                ?: data_get($payload, 'product.barcode')
                ?: data_get($payload, 'Product.Barcode'),
            'product_url' => data_get($payload, 'productUrl')
                ?: data_get($payload, 'ProductUrl')
                ?: data_get($payload, 'product.url')
                ?: data_get($payload, 'Product.Url'),
            'order_number' => data_get($payload, 'orderNumber')
                ?: data_get($payload, 'OrderNumber')
                ?: data_get($payload, 'orderNo')
                ?: data_get($payload, 'OrderNo')
                ?: data_get($payload, 'subOrderNumber')
                ?: data_get($payload, 'SubOrderNumber')
                ?: data_get($payload, 'subOrderNo')
                ?: data_get($payload, 'SubOrderNo')
                ?: data_get($payload, 'packageNumber')
                ?: data_get($payload, 'PackageNumber')
                ?: data_get($payload, 'order.id')
                ?: data_get($payload, 'Order.Id'),
            'asked_at' => $this->questionDate(
                data_get($payload, 'createdDate')
                ?: data_get($payload, 'CreatedDate')
                ?: data_get($payload, 'questionDate')
                ?: data_get($payload, 'QuestionDate')
                ?: data_get($payload, 'createDate')
                ?: data_get($payload, 'CreateDate')
            ),
            'answer_text' => $this->questionTextValue(
                data_get($payload, 'answerText')
                ?: data_get($payload, 'AnswerText')
                ?: data_get($payload, 'answer')
                ?: data_get($payload, 'Answer')
                ?: data_get($payload, 'sellerAnswer')
                ?: data_get($payload, 'SellerAnswer')
            ),
            'answered_at' => $this->questionDate(
                data_get($payload, 'answeredDate')
                ?: data_get($payload, 'AnsweredDate')
                ?: data_get($payload, 'answerDate')
                ?: data_get($payload, 'AnswerDate')
            ),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function ciceksepetiQuestionType(array $payload): string
    {
        if ((int) (data_get($payload, 'branchActionId') ?: data_get($payload, 'BranchActionId')) === 2) {
            return 'order';
        }

        if ((int) (data_get($payload, 'branchActionDetailId') ?: data_get($payload, 'BranchActionDetailId')) === 2) {
            return 'order';
        }

        foreach ([
            'orderNumber',
            'OrderNumber',
            'orderNo',
            'OrderNo',
            'subOrderNumber',
            'SubOrderNumber',
            'subOrderNo',
            'SubOrderNo',
            'packageNumber',
            'PackageNumber',
            'order.id',
        ] as $key) {
            if (filled(data_get($payload, $key))) {
                return 'order';
            }
        }

        return 'product';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function ciceksepetiQuestionStatus(array $payload): string
    {
        $answered = data_get($payload, 'answered') ?? data_get($payload, 'Answered');
        $answered = is_bool($answered)
            ? $answered
            : filter_var($answered, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($answered !== null) {
            return $answered ? 'answered' : 'open';
        }

        if (
            filled(data_get($payload, 'answer'))
            || filled(data_get($payload, 'Answer'))
            || filled(data_get($payload, 'answerText'))
            || filled(data_get($payload, 'AnswerText'))
        ) {
            return 'answered';
        }

        return $this->normalizeQuestionStatus((string) (
            data_get($payload, 'status')
            ?: data_get($payload, 'Status')
            ?: data_get($payload, 'questionStatus')
            ?: data_get($payload, 'QuestionStatus')
            ?: 'open'
        ));
    }

    /**
     * @return array<string, mixed>
     */
    protected function ciceksepetiAnswerPayload(MarketplaceQuestion $question, string $answer, string $path): array
    {
        if (!$this->usesSellerQuestionsEndpoint($path)) {
            return [
                'questionId' => $question->external_question_id,
                'id' => $question->external_question_id,
                'answer' => $answer,
                'answerText' => $answer,
            ];
        }

        $isOrderQuestion = $question->question_type === 'order';

        return array_filter([
            'answer' => $answer,
            'branchActionId' => $isOrderQuestion ? 2 : 1,
            'branchActionDetailId' => $isOrderQuestion ? 2 : null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function hasReturnSignal(array $payload): bool
    {
        $signal = Str::of(json_encode([
            data_get($payload, 'cancellationResult'),
            data_get($payload, 'orderProductStatus'),
            data_get($payload, 'orderStatus'),
            data_get($payload, 'returnReason'),
            data_get($payload, 'refundStatus'),
        ], JSON_UNESCAPED_UNICODE) ?: '')
            ->lower()
            ->ascii()
            ->toString();

        return str_contains($signal, 'iade')
            || str_contains($signal, 'return')
            || str_contains($signal, 'refund');
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    protected function normalizeClaimRows(array $rows): array
    {
        $first = Arr::wrap($rows[0] ?? []);
        $orderId = (string) data_get($first, 'orderId');

        return [
            'external_claim_id' => 'ciceksepeti-'.$orderId.'-'.sha1((string) data_get($first, 'cancellationResult')),
            'order_number' => $orderId,
            'cargo_tracking_number' => data_get($first, 'cargoNumber'),
            'cargo_provider' => data_get($first, 'cargoCompany'),
            'status' => data_get($first, 'cancellationResult') ?: data_get($first, 'orderProductStatus'),
            'type' => 'return',
            'reason' => data_get($first, 'cancellationResult') ?: data_get($first, 'returnReason'),
            'customer_name' => data_get($first, 'receiverName') ?: data_get($first, 'senderName'),
            'created_date' => $this->normalizeOrderDateTime(data_get($first, 'orderModifyDate'), data_get($first, 'orderModifyTime'))
                ?: $this->normalizeOrderDateTime(data_get($first, 'orderCreateDate'), data_get($first, 'orderCreateTime')),
            'items' => $rows,
            'raw_payload' => ['rows' => $rows],
        ];
    }

    protected function normalizeOrderStatus(int $statusId, ?string $statusText = null): string
    {
        if ($statusId > 0) {
            return match ($statusId) {
                7 => 'delivered',
                5 => 'shipped',
                2 => 'packing',
                1, 11 => 'approved',
                default => $this->normalizeStatusText($statusText),
            };
        }

        return $this->normalizeStatusText($statusText);
    }

    protected function normalizeStatusText(?string $statusText): string
    {
        $normalized = Str::of(Str::ascii((string) $statusText))
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/u', ' ')
            ->squish()
            ->toString();

        return match (true) {
            $normalized === '' => 'new',
            str_contains($normalized, 'teslim') => 'delivered',
            str_contains($normalized, 'kargoya verildi') => 'shipped',
            str_contains($normalized, 'hazirlaniyor') => 'packing',
            str_contains($normalized, 'yeni') => 'approved',
            str_contains($normalized, 'iptal') => 'cancelled',
            str_contains($normalized, 'iade') => 'returned',
            default => (string) Str::snake($normalized),
        };
    }

    protected function normalizeProductStatus(?string $statusText, mixed $isActive): string
    {
        $normalized = Str::of(Str::ascii((string) $statusText))
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/u', ' ')
            ->squish()
            ->toString();

        return match (true) {
            $isActive === true,
            str_contains($normalized, 'yayinda') => 'active',
            str_contains($normalized, 'onay') => 'pending',
            str_contains($normalized, 'ret') => 'rejected',
            str_contains($normalized, 'pasif') => 'inactive',
            str_contains($normalized, 'stok') => 'out_of_stock',
            default => 'inactive',
        };
    }

    protected function normalizeOrderDateTime(mixed $date, mixed $time = null): ?string
    {
        $date = trim((string) $date);
        $time = trim((string) $time);

        if ($date === '') {
            return null;
        }

        $candidate = $time !== '' ? $date.' '.$time : $date;

        foreach (['d/m/Y H:i', 'd/m/Y', 'd-m-Y H:i', 'd-m-Y'] as $format) {
            $parsed = CarbonImmutable::createFromFormat($format, $candidate, 'Europe/Istanbul');

            if ($parsed !== false) {
                return $parsed->toIso8601String();
            }
        }

        return $this->normalizeFlexibleDate($candidate);
    }

    protected function normalizeFlexibleDate(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $stringValue = trim((string) $value);

        foreach (['d-m-Y H:i', 'd-m-Y', 'd/m/Y H:i', 'd/m/Y', 'Y-m-d H:i:s', 'Y-m-d H:i'] as $format) {
            $parsed = CarbonImmutable::createFromFormat($format, $stringValue, 'Europe/Istanbul');

            if ($parsed !== false) {
                return $parsed->toIso8601String();
            }
        }

        return CarbonImmutable::parse($stringValue)->toIso8601String();
    }

    protected function toDecimal(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        $normalized = preg_replace('/[^0-9,.-]/', '', (string) $value) ?? '';

        if ($normalized === '') {
            return null;
        }

        if (str_contains($normalized, ',') && !str_contains($normalized, '.')) {
            $normalized = str_replace(',', '.', $normalized);
        } else {
            $normalized = str_replace(',', '', $normalized);
        }

        return is_numeric($normalized) ? round((float) $normalized, 2) : null;
    }
}
