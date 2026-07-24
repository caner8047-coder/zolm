<?php

namespace App\Console\Commands;

use App\Jobs\SyncMarketplaceDataJob;
use App\Models\IntegrationSyncProfile;
use App\Models\IntegrationSyncRun;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\MarketplaceConnectionReadinessService;
use App\Services\Marketplace\MarketplaceConnectorManager;
use App\Services\Marketplace\MarketplaceSyncService;
use App\Services\Marketplace\Support\CircuitBreaker;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class DispatchDueMarketplaceSyncsCommand extends Command
{
    protected $signature = 'marketplace:dispatch-due-syncs
        {--type= : Sadece orders|products|finance|questions|claims çalıştır}
        {--store= : Sadece belirli mağaza ID}
        {--skip-orders : Otomatik scheduler çalışmasında sipariş sync tipini atla}
        {--force : Zaman penceresini beklemeden dispatch et}';

    protected $description = 'Aktif mağazalar için profil ayarlarına göre zamanı gelmiş sync işlerini kuyruğa alır.';

    public function __construct(
        protected MarketplaceConnectionReadinessService $connectionReadiness,
        protected MarketplaceConnectorManager $connectorManager,
        protected MarketplaceSyncService $syncService,
        protected CircuitBreaker $circuitBreaker,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $requestedType = $this->option('type');

        if ($requestedType && !in_array($requestedType, ['orders', 'products', 'finance', 'questions', 'claims'], true)) {
            $this->error('Geçersiz type. orders, products, finance, questions veya claims kullanın.');

            return self::FAILURE;
        }

        $stores = MarketplaceStore::query()
            ->with(['connection', 'syncProfile'])
            ->where('is_active', true)
            ->when($this->option('store'), fn ($query, $storeId) => $query->where('id', $storeId))
            ->get();

        $queuedCount = 0;
        $processedOrdersCount = 0;
        $failedOrdersCount = 0;

        foreach ($stores as $store) {
            if (!$store->connection || $store->connection->status === 'draft') {
                continue;
            }

            $capabilities = $this->connectorManager->resolveForStore($store)->capabilities();
            $readiness = $this->connectionReadiness->inspect($store);

            if ($readiness['failures'] !== []) {
                $this->warn("Skipped store #{$store->id} ({$store->store_name}): ".$readiness['failures'][0]);

                continue;
            }

            foreach ($this->syncDefinitions($store, $capabilities) as $syncType => $definition) {
                if ($syncType === 'orders' && $this->option('skip-orders')) {
                    continue;
                }

                if ($requestedType && $requestedType !== $syncType) {
                    continue;
                }

                if (!$definition['enabled']) {
                    continue;
                }

                if (!$this->option('force') && !$this->isDue($store->id, $syncType, (int) $definition['minutes'])) {
                    continue;
                }

                if ($syncType === 'orders') {
                    $result = $this->syncOrdersInline($store);

                    if ($result === 'completed') {
                        $processedOrdersCount++;
                    } elseif ($result === 'failed') {
                        $failedOrdersCount++;
                    }

                    continue;
                }

                if ($this->hasPendingRun($store->id, $syncType)) {
                    continue;
                }

                $run = $this->createRun($store->id, $syncType);

                SyncMarketplaceDataJob::dispatch($run->id);
                $queuedCount++;
                $this->line("Queued {$syncType} for store #{$store->id} ({$store->store_name})");
            }
        }

        $this->info("Toplam {$queuedCount} sync işi kuyruğa alındı. {$processedOrdersCount} sipariş sync'i doğrudan çalıştırıldı.");

        return $failedOrdersCount > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array<string, bool>  $capabilities
     * @return array<string, array{enabled: bool, minutes: int}>
     */
    protected function syncDefinitions(MarketplaceStore $store, array $capabilities): array
    {
        $profile = $store->syncProfile;

        return [
            'orders' => [
                'enabled' => (bool) $profile?->orders_enabled && (bool) ($capabilities['orders'] ?? false),
                'minutes' => $this->effectiveOrdersPollMinutes($profile),
            ],
            'finance' => [
                'enabled' => (bool) $profile?->finance_enabled && (bool) ($capabilities['finance'] ?? false),
                'minutes' => (int) ($profile?->finance_poll_minutes ?: 60),
            ],
            'products' => [
                'enabled' => (bool) $profile?->products_enabled && (bool) ($capabilities['products'] ?? false),
                'minutes' => (int) ($profile?->products_poll_minutes ?: 360),
            ],
            'questions' => [
                'enabled' => (bool) $profile?->questions_enabled && (bool) ($capabilities['questions'] ?? false),
                'minutes' => (int) ($profile?->questions_poll_minutes ?: 15),
            ],
            'claims' => [
                'enabled' => (bool) $profile?->claims_enabled && (bool) ($capabilities['claims'] ?? false),
                'minutes' => (int) ($profile?->claims_poll_minutes ?: 30),
            ],
        ];
    }

    protected function effectiveOrdersPollMinutes(?IntegrationSyncProfile $profile): int
    {
        $globalInterval = (int) config('marketplace.orders_auto_sync.interval_minutes', 15);

        if ($globalInterval > 0) {
            return max(1, $globalInterval);
        }

        return max(1, (int) ($profile?->orders_poll_minutes ?: 15));
    }

    protected function isDue(int $storeId, string $syncType, int $minutes): bool
    {
        $lastSuccessfulRun = IntegrationSyncRun::query()
            ->where('store_id', $storeId)
            ->where('sync_type', $syncType)
            ->where('status', 'completed')
            ->latest('finished_at')
            ->first();

        if (!$lastSuccessfulRun?->finished_at) {
            return true;
        }

        $dueGraceSeconds = 90;

        return CarbonImmutable::parse($lastSuccessfulRun->finished_at)
            ->addMinutes($minutes)
            ->subSeconds($dueGraceSeconds)
            ->lessThanOrEqualTo(now());
    }

    /**
     * Worker geçici olarak devre dışı kaldığında eski bir queued run'ın
     * arkasına her dakika yeni run eklenmesini engeller. Queued kayıt ancak
     * işlendiğinde veya açıkça uzlaştırıldığında terminal duruma geçmelidir.
     */
    protected function hasPendingRun(int $storeId, string $syncType): bool
    {
        return IntegrationSyncRun::query()
            ->where('store_id', $storeId)
            ->where('sync_type', $syncType)
            ->whereIn('status', ['queued', 'processing'])
            ->exists();
    }

    protected function createRun(int $storeId, string $syncType): IntegrationSyncRun
    {
        return IntegrationSyncRun::create([
            'store_id' => $storeId,
            'sync_type' => $syncType,
            'trigger_type' => 'schedule',
            'status' => 'queued',
            'notes_json' => [
                'options' => [],
                'source' => 'dispatch_due_syncs',
            ],
        ]);
    }

    /**
     * @return 'completed'|'failed'|'skipped'
     */
    protected function syncOrdersInline(MarketplaceStore $store): string
    {
        $this->closeStaleProcessingRuns($store->id, 'orders');

        $processingRun = $this->freshProcessingRun($store->id, 'orders');

        if ($processingRun) {
            $this->warn("Skipped orders for store #{$store->id} ({$store->store_name}): run #{$processingRun->id} hâlâ processing.");

            return 'skipped';
        }

        $run = $this->findQueuedRun($store->id, 'orders') ?? $this->createRun($store->id, 'orders');
        $state = $this->circuitBreaker->state($store->id, 'orders');

        if ($state === 'open') {
            $inspection = $this->circuitBreaker->inspect($store->id, 'orders');

            $run->forceFill([
                'status' => 'skipped',
                'finished_at' => now(),
                'notes_json' => array_merge($run->notes_json ?? [], [
                    'skipped_reason' => 'circuit_breaker_open',
                    'circuit_state' => $state,
                    'last_circuit_error' => $inspection['last_error'],
                ]),
            ])->save();

            $this->warn("Skipped orders for store #{$store->id} ({$store->store_name}): circuit breaker açık.");

            return 'skipped';
        }

        try {
            $this->syncService->run($run->id);
            $this->circuitBreaker->recordSuccess($store->id, 'orders');
            $run->refresh();

            $this->line(
                "Synced orders for store #{$store->id} ({$store->store_name}) run #{$run->id}: "
                ."received={$run->items_received}, created={$run->items_created}, updated={$run->items_updated}"
            );

            $this->skipDuplicateQueuedRuns($store->id, 'orders', $run->id);

            return 'completed';
        } catch (Throwable $exception) {
            $this->circuitBreaker->recordFailure($store->id, 'orders', $exception->getMessage());
            $this->error("Failed orders for store #{$store->id} ({$store->store_name}): {$exception->getMessage()}");
            report($exception);

            return 'failed';
        }
    }

    protected function findQueuedRun(int $storeId, string $syncType): ?IntegrationSyncRun
    {
        return IntegrationSyncRun::query()
            ->where('store_id', $storeId)
            ->where('sync_type', $syncType)
            ->where('status', 'queued')
            ->orderBy('id')
            ->first();
    }

    protected function freshProcessingRun(int $storeId, string $syncType): ?IntegrationSyncRun
    {
        return IntegrationSyncRun::query()
            ->where('store_id', $storeId)
            ->where('sync_type', $syncType)
            ->where('status', 'processing')
            ->where('updated_at', '>=', now()->subMinutes(30))
            ->latest('updated_at')
            ->first();
    }

    protected function closeStaleProcessingRuns(int $storeId, string $syncType): void
    {
        IntegrationSyncRun::query()
            ->where('store_id', $storeId)
            ->where('sync_type', $syncType)
            ->where('status', 'processing')
            ->where('updated_at', '<', now()->subMinutes(30))
            ->get()
            ->each(function (IntegrationSyncRun $run): void {
                $run->forceFill([
                    'status' => 'failed',
                    'finished_at' => now(),
                    'error_count' => max(1, (int) $run->error_count + 1),
                    'notes_json' => array_merge($run->notes_json ?? [], [
                        'last_error' => 'Stale processing run otomatik sipariş sync tarafından kapatıldı.',
                        'stale_closed_by' => 'marketplace:dispatch-due-syncs',
                    ]),
                ])->save();
            });
    }

    protected function skipDuplicateQueuedRuns(int $storeId, string $syncType, int $exceptRunId): void
    {
        IntegrationSyncRun::query()
            ->where('store_id', $storeId)
            ->where('sync_type', $syncType)
            ->where('status', 'queued')
            ->whereKeyNot($exceptRunId)
            ->get()
            ->each(function (IntegrationSyncRun $run): void {
                $run->forceFill([
                    'status' => 'skipped',
                    'finished_at' => now(),
                    'notes_json' => array_merge($run->notes_json ?? [], [
                        'skipped_reason' => 'duplicate_inline_orders_sync',
                    ]),
                ])->save();
            });
    }
}
