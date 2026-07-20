<?php

namespace App\Console\Commands;

use App\Models\IntegrationSyncRun;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\Contracts\PullsClaims;
use App\Services\Marketplace\Contracts\PullsCustomerQuestions;
use App\Services\Marketplace\Contracts\PullsFinancials;
use App\Services\Marketplace\Contracts\PullsOrders;
use App\Services\Marketplace\Contracts\PullsProducts;
use App\Services\Marketplace\Contracts\TestsConnection;
use App\Services\Marketplace\MarketplaceConnectionReadinessService;
use App\Services\Marketplace\MarketplaceConnectorManager;
use App\Services\Marketplace\MarketplacePayloadDiagnosticsService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Throwable;

class MarketplaceSmokeTestCommand extends Command
{
    protected $signature = 'marketplace:smoke-test
        {store : Test edilecek mağaza ID}
        {--type=all : orders, products, finance, questions, claims veya all}
        {--hours=24 : Geriye dönük pencere (saat)}
        {--preview=2 : Her veri tipinden gösterilecek örnek kayıt sayısı}
        {--order-number= : Belirli bir sipariş numarasına odaklan}
        {--skip-connection : Bağlantı doğrulamayı atla}
        {--skip-readiness : Hazırlık kontrolünü atla}
        {--persist : Sonuçları sync geçmişine smoke_test olarak kaydet}';

    protected $description = 'Mağaza bağlantısını ve normalize edilen payload mapping kalitesini veri yazmadan test eder.';

    public function __construct(
        protected MarketplaceConnectionReadinessService $connectionReadiness,
        protected MarketplaceConnectorManager $connectorManager,
        protected MarketplacePayloadDiagnosticsService $diagnostics,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $store = MarketplaceStore::query()
            ->with(['connection', 'syncProfile', 'legalEntity'])
            ->findOrFail((int) $this->argument('store'));

        $connector = $this->connectorManager->resolveForStore($store);
        $requestedType = (string) $this->option('type');
        $supportedTypes = $this->supportedTypes($connector);

        try {
            $types = $this->resolveRequestedTypes($requestedType, $supportedTypes);
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $hours = max(1, (int) $this->option('hours'));
        $preview = max(1, min(5, (int) $this->option('preview')));
        $persist = (bool) $this->option('persist');
        $endDate = CarbonImmutable::now();
        $startDate = $endDate->subHours($hours);

        $this->components->info('Pazaryeri smoke test baslatildi');
        $this->newLine();

        $this->table(
            ['Alan', 'Deger'],
            [
                ['Magaza', $store->store_name],
                ['Kanal', $store->marketplace],
                ['Firma', $store->legalEntity?->name ?: '-'],
                ['Baglanti durumu', $store->connection?->status ?: 'yok'],
                ['Periyot', $startDate->format('d.m.Y H:i') . ' - ' . $endDate->format('d.m.Y H:i')],
                ['Siparis filtresi', (string) ($this->option('order-number') ?: '-')],
                ['Kayit modu', $persist ? 'sync gecmisine yaz' : 'yalnizca terminal onizleme'],
            ]
        );

        if (trim(strtolower($requestedType)) === 'all') {
            $skippedTypes = array_values(array_diff(['orders', 'products', 'finance', 'questions', 'claims'], $types));

            if ($skippedTypes !== []) {
                $this->warn('Desteklenmeyen veri tipleri atlandı: ' . implode(', ', $skippedTypes));
            }
        }

        if (!$this->option('skip-readiness')) {
            $readiness = $this->connectionReadiness->inspect($store);
            $this->renderReadiness($readiness);

            if ($readiness['failures'] !== []) {
                $this->components->error('Hazırlık kontrolü başarısız. --skip-readiness ile devam etmeyi zorlayabilirsiniz.');

                return self::FAILURE;
            }
        }

        if (!$this->option('skip-connection')) {
            $this->runConnectionCheck($connector, $store);
        }

        foreach ($types as $type) {
            $type = trim($type);

            if (!in_array($type, ['orders', 'products', 'finance', 'questions', 'claims'], true)) {
                $this->components->error('Gecersiz type: ' . $type);

                return self::FAILURE;
            }

            $this->runPullPreview($connector, $store, $type, [
                'start_date' => $startDate->toIso8601String(),
                'end_date' => $endDate->toIso8601String(),
                'order_number' => $this->option('order-number'),
            ], $preview, $persist);
        }

        $this->newLine();
        $this->components->info('Smoke test tamamlandi.');

        return self::SUCCESS;
    }

    protected function supportedTypes(object $connector): array
    {
        $capabilities = method_exists($connector, 'capabilities')
            ? (array) $connector->capabilities()
            : [];

        return array_values(array_filter([
            'orders' => method_exists($connector, 'pullOrders') ? 'orders' : null,
            'products' => method_exists($connector, 'pullProducts') ? 'products' : null,
            'finance' => method_exists($connector, 'pullFinancials') ? 'finance' : null,
            'questions' => method_exists($connector, 'pullCustomerQuestions') ? 'questions' : null,
            'claims' => method_exists($connector, 'pullClaims') ? 'claims' : null,
            'buybox' => method_exists($connector, 'checkBuyboxRank') ? 'buybox' : null,
            'brands' => method_exists($connector, 'getBrands') ? 'brands' : null,
            'categories' => method_exists($connector, 'getCategories') ? 'categories' : null,
            'claim_reasons' => method_exists($connector, 'getClaimIssueReasons') ? 'claim_reasons' : null,
            'cargo_invoices' => method_exists($connector, 'pullCargoInvoices') ? 'cargo_invoices' : null,
            'batch_requests' => method_exists($connector, 'checkBatchRequestResult') ? 'batch_requests' : null,
        ]));
    }

    /**
     * @param  array<int, string>  $supportedTypes
     * @return array<int, string>
     */
    protected function resolveRequestedTypes(string $requestedType, array $supportedTypes): array
    {
        $normalizedType = strtolower(trim($requestedType));

        if ($normalizedType === 'all') {
            if ($supportedTypes === []) {
                throw new \RuntimeException('Bu baglayici smoke test icin desteklenen bir veri tipi sunmuyor.');
            }

            return $supportedTypes;
        }

        if (!in_array($normalizedType, ['orders', 'products', 'finance', 'questions', 'claims', 'buybox', 'brands', 'categories', 'claim_reasons', 'cargo_invoices', 'batch_requests'], true)) {
            throw new \RuntimeException('Gecersiz type: ' . $requestedType);
        }

        if (!in_array($normalizedType, $supportedTypes, true)) {
            $supportedLabel = $supportedTypes === [] ? 'yok' : implode(', ', $supportedTypes);

            throw new \RuntimeException("Bu baglayici {$normalizedType} cekimini desteklemiyor. Desteklenen tipler: {$supportedLabel}.");
        }

        return [$normalizedType];
    }

    protected function runConnectionCheck(object $connector, MarketplaceStore $store): void
    {
        $this->newLine();
        $this->components->info('Baglanti dogrulamasi');

        if (!$connector instanceof TestsConnection) {
            $this->line('- Bu baglayici icin testConnection tanimli degil.');

            return;
        }

        try {
            $result = $connector->testConnection($store);
            $this->table(
                ['Kontrol', 'Deger'],
                [
                    ['Sonuc', ($result['ok'] ?? false) ? 'basarili' : 'basarisiz'],
                    ['Mesaj', (string) ($result['message'] ?? '-')],
                    ['Meta', json_encode($result['meta'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
                ]
            );
        } catch (Throwable $exception) {
            $this->components->error('Baglanti testi basarisiz: ' . $exception->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $readiness
     */
    protected function renderReadiness(array $readiness): void
    {
        $this->newLine();
        $this->components->info('Hazirlik kontrolu');

        $rows = collect($readiness['checks'] ?? [])
            ->map(fn (array $check) => [
                $check['label'],
                $check['state'] === 'ok' ? 'tamam' : 'eksik',
                $check['message'],
            ])
            ->all();

        $this->table(['Kontrol', 'Durum', 'Mesaj'], $rows);

        foreach (($readiness['warnings'] ?? []) as $warning) {
            $this->warn('- ' . $warning);
        }

        foreach (($readiness['failures'] ?? []) as $failure) {
            $this->error('- ' . $failure);
        }
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function runPullPreview(object $connector, MarketplaceStore $store, string $type, array $options, int $preview, bool $persist): void
    {
        $this->newLine();
        $this->components->info(strtoupper($type) . ' onizleme');
        $startedAt = CarbonImmutable::now();

        try {
            $response = match ($type) {
                'orders' => $this->pullOrders($connector, $store, $options),
                'products' => $this->pullProducts($connector, $store, $options),
                'finance' => $this->pullFinancials($connector, $store, $options),
                'questions' => $this->pullQuestions($connector, $store, $options),
                'claims' => $this->pullClaims($connector, $store, $options),
                'buybox' => ['items' => method_exists($connector, 'checkBuyboxRank') ? $connector->checkBuyboxRank($store, []) : [], 'meta' => []],
                'brands' => ['items' => method_exists($connector, 'getBrands') ? $connector->getBrands($store, 0, $preview) : [], 'meta' => []],
                'categories' => ['items' => method_exists($connector, 'getCategories') ? $connector->getCategories($store) : [], 'meta' => []],
                'claim_reasons' => ['items' => method_exists($connector, 'getClaimIssueReasons') ? $connector->getClaimIssueReasons($store) : [], 'meta' => []],
                'cargo_invoices' => method_exists($connector, 'pullCargoInvoices') ? $connector->pullCargoInvoices($store, $options) : ['items' => [], 'meta' => []],
                'batch_requests' => ['items' => [], 'meta' => ['status' => 'skipped (requires batch ID)']],
            };
        } catch (Throwable $exception) {
            if ($persist) {
                $this->persistSmokeRun($store, $type, $options, [
                    'status' => 'failed',
                    'started_at' => $startedAt,
                    'finished_at' => CarbonImmutable::now(),
                    'items_received' => 0,
                    'diagnostics' => [],
                    'notes' => [],
                    'last_error' => $exception->getMessage(),
                ]);
            }

            $this->components->error($type . ' cekimi basarisiz: ' . $exception->getMessage());

            return;
        }

        $items = Arr::wrap($response['items'] ?? []);
        $diagnostics = match ($type) {
            'orders' => $this->diagnostics->analyzeOrders($items),
            'products' => $this->diagnostics->analyzeProducts($items),
            'finance' => $this->diagnostics->analyzeFinancialEvents($items),
            'questions' => $this->analyzeQuestionPayloads($items),
            'claims' => $this->analyzeClaimPayloads($items),
        };

        $this->renderDiagnosticsTable($type, $diagnostics);

        foreach (Arr::wrap($diagnostics['warnings'] ?? []) as $warning) {
            $this->line('- ' . $warning);
        }

        $previewRows = array_slice($items, 0, $preview);

        if ($previewRows !== []) {
            $this->newLine();
            $this->line('Ornek payload:');
            $this->line(json_encode($previewRows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        if ($persist) {
            $this->persistSmokeRun($store, $type, $options, [
                'status' => 'completed',
                'started_at' => $startedAt,
                'finished_at' => CarbonImmutable::now(),
                'items_received' => $response['meta']['items_received'] ?? count($items),
                'diagnostics' => $diagnostics,
                'notes' => $response['meta'] ?? [],
            ]);

            $this->line('Smoke test sonucu sync geçmişine kaydedildi.');
        }
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function pullOrders(object $connector, MarketplaceStore $store, array $options): array
    {
        if (!$connector instanceof PullsOrders) {
            throw new \RuntimeException('Bu baglayici orders cekimini desteklemiyor.');
        }

        return $connector->pullOrders($store, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function pullProducts(object $connector, MarketplaceStore $store, array $options): array
    {
        if (!$connector instanceof PullsProducts) {
            throw new \RuntimeException('Bu baglayici products cekimini desteklemiyor.');
        }

        return $connector->pullProducts($store, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function pullFinancials(object $connector, MarketplaceStore $store, array $options): array
    {
        if (!$connector instanceof PullsFinancials) {
            throw new \RuntimeException('Bu baglayici finance cekimini desteklemiyor.');
        }

        return $connector->pullFinancialEvents($store, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function pullQuestions(object $connector, MarketplaceStore $store, array $options): array
    {
        if (!$connector instanceof PullsCustomerQuestions) {
            throw new \RuntimeException('Bu baglayici questions cekimini desteklemiyor.');
        }

        return $connector->pullCustomerQuestions($store, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function pullClaims(object $connector, MarketplaceStore $store, array $options): array
    {
        if (!$connector instanceof PullsClaims) {
            throw new \RuntimeException('Bu baglayici claims cekimini desteklemiyor.');
        }

        return $connector->pullClaims($store, $options);
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<string, mixed>
     */
    protected function analyzeQuestionPayloads(array $items): array
    {
        $questionCount = count($items);
        $missingIdCount = 0;
        $missingTextCount = 0;
        $missingAskedAtCount = 0;

        foreach ($items as $item) {
            if (!is_array($item)) {
                $missingIdCount++;
                $missingTextCount++;
                $missingAskedAtCount++;
                continue;
            }

            if (blank(data_get($item, 'external_question_id') ?: data_get($item, 'questionId') ?: data_get($item, 'id') ?: data_get($item, 'question_id'))) {
                $missingIdCount++;
            }

            if (blank(data_get($item, 'question_text') ?: data_get($item, 'questionText') ?: data_get($item, 'question') ?: data_get($item, 'text') ?: data_get($item, 'message') ?: data_get($item, 'content'))) {
                $missingTextCount++;
            }

            if (blank(data_get($item, 'asked_at') ?: data_get($item, 'askedAt') ?: data_get($item, 'created_at') ?: data_get($item, 'createdAt') ?: data_get($item, 'createdDate') ?: data_get($item, 'date'))) {
                $missingAskedAtCount++;
            }
        }

        return [
            'question_count' => $questionCount,
            'missing_question_id_count' => $missingIdCount,
            'missing_question_text_count' => $missingTextCount,
            'missing_asked_at_count' => $missingAskedAtCount,
            'warnings' => array_values(array_filter([
                $missingIdCount > 0 ? "{$missingIdCount} soru kaydında soru ID eksik." : null,
                $missingTextCount > 0 ? "{$missingTextCount} soru kaydında soru metni eksik." : null,
            ])),
        ];
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<string, mixed>
     */
    protected function analyzeClaimPayloads(array $items): array
    {
        $claimCount = count($items);
        $missingIdCount = 0;
        $missingOrderNumberCount = 0;

        foreach ($items as $item) {
            if (!is_array($item)) {
                $missingIdCount++;
                $missingOrderNumberCount++;
                continue;
            }

            if (blank(data_get($item, 'external_claim_id') ?: data_get($item, 'claim_id') ?: data_get($item, 'return_id') ?: data_get($item, 'id'))) {
                $missingIdCount++;
            }

            if (blank(data_get($item, 'order_number') ?: data_get($item, 'orderNumber') ?: data_get($item, 'order.id') ?: data_get($item, 'orderId'))) {
                $missingOrderNumberCount++;
            }
        }

        return [
            'claim_count' => $claimCount,
            'missing_claim_id_count' => $missingIdCount,
            'missing_order_number_count' => $missingOrderNumberCount,
            'warnings' => array_values(array_filter([
                $missingIdCount > 0 ? "{$missingIdCount} iade kaydında claim/return ID eksik." : null,
            ])),
        ];
    }

    /**
     * @param  array<string, mixed>  $diagnostics
     */
    protected function renderDiagnosticsTable(string $type, array $diagnostics): void
    {
        $rows = match ($type) {
            'orders' => [
                ['Paket', (string) ($diagnostics['package_count'] ?? 0)],
                ['Siparis', (string) ($diagnostics['order_count'] ?? 0)],
                ['Satir', (string) ($diagnostics['item_count'] ?? 0)],
                ['Eksik order no', (string) ($diagnostics['missing_order_number_count'] ?? 0)],
                ['Eksik package id', (string) ($diagnostics['missing_package_id_count'] ?? 0)],
                ['Eksik line id', (string) ($diagnostics['missing_item_line_id_count'] ?? 0)],
                ['Eksik stok kodu', (string) ($diagnostics['missing_stock_code_count'] ?? 0)],
                ['Eksik barkod', (string) ($diagnostics['missing_barcode_count'] ?? 0)],
            ],
            'products' => [
                ['Urun', (string) ($diagnostics['product_count'] ?? 0)],
                ['Eksik product id', (string) ($diagnostics['missing_external_product_id_count'] ?? 0)],
                ['Eksik listing id', (string) ($diagnostics['missing_listing_id_count'] ?? 0)],
                ['Eksik stok kodu', (string) ($diagnostics['missing_stock_code_count'] ?? 0)],
                ['Eksik barkod', (string) ($diagnostics['missing_barcode_count'] ?? 0)],
                ['Eksik satis fiyati', (string) ($diagnostics['missing_sale_price_count'] ?? 0)],
                ['Eksik stok miktari', (string) ($diagnostics['missing_stock_quantity_count'] ?? 0)],
            ],
            'finance' => [
                ['Kayit', (string) ($diagnostics['event_count'] ?? 0)],
                ['Eksik event id', (string) ($diagnostics['missing_event_id_count'] ?? 0)],
                ['Eksik order no', (string) ($diagnostics['missing_order_number_count'] ?? 0)],
                ['Eksik package id', (string) ($diagnostics['missing_package_id_count'] ?? 0)],
                ['Eksik line id', (string) ($diagnostics['missing_line_id_count'] ?? 0)],
                ['Eksik tutar', (string) ($diagnostics['missing_amount_count'] ?? 0)],
                ['Eksik odeme tarihi', (string) ($diagnostics['missing_settlement_date_count'] ?? 0)],
            ],
            'questions' => [
                ['Soru', (string) ($diagnostics['question_count'] ?? 0)],
                ['Eksik soru id', (string) ($diagnostics['missing_question_id_count'] ?? 0)],
                ['Eksik soru metni', (string) ($diagnostics['missing_question_text_count'] ?? 0)],
                ['Eksik soru tarihi', (string) ($diagnostics['missing_asked_at_count'] ?? 0)],
            ],
            'claims' => [
                ['Iade', (string) ($diagnostics['claim_count'] ?? 0)],
                ['Eksik claim id', (string) ($diagnostics['missing_claim_id_count'] ?? 0)],
                ['Eksik order no', (string) ($diagnostics['missing_order_number_count'] ?? 0)],
            ],
        };

        $this->table(['Kontrol', 'Deger'], $rows);
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $payload
     */
    protected function persistSmokeRun(MarketplaceStore $store, string $type, array $options, array $payload): void
    {
        $startedAt = CarbonImmutable::parse($payload['started_at']);
        $finishedAt = CarbonImmutable::parse($payload['finished_at']);

        IntegrationSyncRun::query()->create([
            'store_id' => $store->id,
            'sync_type' => $type,
            'trigger_type' => 'smoke_test',
            'status' => (string) ($payload['status'] ?? 'completed'),
            'cursor_before' => (string) ($options['start_date'] ?? ''),
            'cursor_after' => (string) ($options['end_date'] ?? ''),
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'duration_ms' => $startedAt->diffInMilliseconds($finishedAt),
            'items_received' => (int) ($payload['items_received'] ?? 0),
            'items_created' => 0,
            'items_updated' => 0,
            'items_skipped' => 0,
            'error_count' => (($payload['status'] ?? 'completed') === 'failed') ? 1 : 0,
            'notes_json' => array_filter([
                'options' => $options,
                'meta' => $payload['notes'] ?? [],
                'diagnostics' => $payload['diagnostics'] ?? [],
                'smoke_test' => true,
                'last_error' => $payload['last_error'] ?? null,
            ], fn ($value) => $value !== null && $value !== []),
        ]);
    }
}
