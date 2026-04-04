<?php

namespace App\Console\Commands;

use App\Models\IntegrationSyncRun;
use App\Models\MarketplaceStore;
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
        {--type=all : orders, products, finance veya all}
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

        $connector = $this->connectorManager->resolve($store->marketplace);
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

        if (trim(strtolower($requestedType)) === 'all' && count($types) < 3) {
            $skippedTypes = array_values(array_diff(['orders', 'products', 'finance'], $types));

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

            if (!in_array($type, ['orders', 'products', 'finance'], true)) {
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

    /**
     * @param  object  $connector
     * @return array<int, string>
     */
    protected function supportedTypes(object $connector): array
    {
        $capabilities = method_exists($connector, 'capabilities')
            ? (array) $connector->capabilities()
            : [];

        return array_values(array_filter([
            'orders' => $connector instanceof PullsOrders && (bool) ($capabilities['orders'] ?? false) ? 'orders' : null,
            'products' => $connector instanceof PullsProducts && (bool) ($capabilities['products'] ?? false) ? 'products' : null,
            'finance' => $connector instanceof PullsFinancials && (bool) ($capabilities['finance'] ?? false) ? 'finance' : null,
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

        if (!in_array($normalizedType, ['orders', 'products', 'finance'], true)) {
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
