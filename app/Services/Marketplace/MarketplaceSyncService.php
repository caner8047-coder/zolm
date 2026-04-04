<?php

namespace App\Services\Marketplace;

use App\Models\IntegrationConnection;
use App\Models\IntegrationSyncRun;
use App\Services\Marketplace\Contracts\PullsFinancials;
use App\Services\Marketplace\Contracts\PullsOrders;
use App\Services\Marketplace\Contracts\PullsProducts;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Throwable;

class MarketplaceSyncService
{
    public function __construct(
        protected MarketplaceCatalogSyncService $catalogSyncService,
        protected MarketplaceConnectorManager $connectorManager,
        protected MarketplacePayloadDiagnosticsService $payloadDiagnosticsService,
        protected MarketplaceFinancialSyncService $financialSyncService,
        protected MarketplaceOrderSyncService $orderSyncService,
        protected MarketplaceProfitSnapshotService $profitSnapshotService,
    ) {
    }

    public function run(int $syncRunId): void
    {
        $run = IntegrationSyncRun::query()
            ->with(['store.connection', 'store.syncProfile'])
            ->findOrFail($syncRunId);

        $store = $run->store;
        $connection = $store->connection;

        $run->forceFill([
            'status' => 'processing',
            'started_at' => $run->started_at ?: now(),
        ])->save();

        try {
            $connector = $this->connectorManager->resolve($store->marketplace);
            $syncType = $this->normalizeSyncType($run->sync_type);
            $options = $this->buildOptions($run);

            $run->forceFill([
                'cursor_before' => (string) $options['start_date'],
            ])->save();

            $result = match ($syncType) {
                'orders' => $this->syncOrders($connector, $store, $options),
                'products' => $this->syncProducts($connector, $store, $options),
                'finance' => $this->syncFinancials($connector, $store, $options),
                default => throw new \RuntimeException("Bilinmeyen sync tipi: {$run->sync_type}"),
            };

            $impactedOrderIds = $result['impacted_order_ids'] ?? [];
            $this->profitSnapshotService->recalculateForOrders($store, $impactedOrderIds);

            $this->markSuccessful($run, $connection, $result, $options);
        } catch (Throwable $exception) {
            $this->markFailed($run, $connection, $exception);

            throw $exception;
        }
    }

    /**
     * @param  object  $connector
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function syncOrders(object $connector, $store, array $options): array
    {
        if (!$connector instanceof PullsOrders) {
            throw new \RuntimeException('Bu bağlayıcı sipariş çekmeyi desteklemiyor.');
        }

        $response = $connector->pullOrders($store, $options);
        $items = $response['items'] ?? [];
        $diagnostics = $this->payloadDiagnosticsService->analyzeOrders($items);
        $sync = $this->orderSyncService->sync($store, $items);

        return array_merge($sync, [
            'items_received' => $response['meta']['items_received'] ?? count($items),
            'cursor_after' => $response['meta']['cursor_after'] ?? ($options['end_date'] ?? null),
            'notes' => $response['meta'] ?? [],
            'diagnostics' => $diagnostics,
        ]);
    }

    /**
     * @param  object  $connector
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function syncProducts(object $connector, $store, array $options): array
    {
        if (!$connector instanceof PullsProducts) {
            throw new \RuntimeException('Bu bağlayıcı ürün çekmeyi desteklemiyor.');
        }

        $response = $connector->pullProducts($store, $options);
        $items = $response['items'] ?? [];
        $diagnostics = $this->payloadDiagnosticsService->analyzeProducts($items);
        $sync = $this->catalogSyncService->sync($store, $items);

        return array_merge($sync, [
            'impacted_order_ids' => [],
            'items_received' => $response['meta']['items_received'] ?? count($items),
            'cursor_after' => $response['meta']['cursor_after'] ?? ($options['end_date'] ?? null),
            'notes' => $response['meta'] ?? [],
            'diagnostics' => $diagnostics,
        ]);
    }

    /**
     * @param  object  $connector
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function syncFinancials(object $connector, $store, array $options): array
    {
        if (!$connector instanceof PullsFinancials) {
            throw new \RuntimeException('Bu bağlayıcı finans verisi çekmeyi desteklemiyor.');
        }

        $response = $connector->pullFinancialEvents($store, $options);
        $items = $response['items'] ?? [];
        $diagnostics = $this->payloadDiagnosticsService->analyzeFinancialEvents($items);
        $sync = $this->financialSyncService->sync($store, $items);

        return array_merge($sync, [
            'items_received' => $response['meta']['items_received'] ?? count($items),
            'cursor_after' => $response['meta']['cursor_after'] ?? ($options['end_date'] ?? null),
            'notes' => $response['meta'] ?? [],
            'diagnostics' => $diagnostics,
        ]);
    }

    protected function normalizeSyncType(string $syncType): string
    {
        return $syncType === 'webhook_refresh' ? 'orders' : $syncType;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildOptions(IntegrationSyncRun $run): array
    {
        $store = $run->store;
        $profile = $store->syncProfile;
        $notes = $run->notes_json ?? [];
        $now = CarbonImmutable::now();
        $endDate = Arr::get($notes, 'options.end_date')
            ? CarbonImmutable::parse(Arr::get($notes, 'options.end_date'))
            : $now;

        $startDate = Arr::get($notes, 'options.start_date')
            ? CarbonImmutable::parse(Arr::get($notes, 'options.start_date'))
            : null;

        if (!$startDate) {
            $lastCompletedRun = IntegrationSyncRun::query()
                ->where('store_id', $store->id)
                ->where('sync_type', $run->sync_type)
                ->where('status', 'completed')
                ->whereNotNull('finished_at')
                ->latest('finished_at')
                ->first();

            if ($lastCompletedRun?->finished_at) {
                $startDate = CarbonImmutable::parse($lastCompletedRun->finished_at)->subMinutes(5);
            } else {
                $startDate = $this->resolveBackfillStart($run);
            }
        }

        return [
            'start_date' => $startDate->toIso8601String(),
            'end_date' => $endDate->toIso8601String(),
            'page_size' => Arr::get($notes, 'options.page_size'),
            'order_number' => Arr::get($notes, 'options.order_number'),
            'shipment_package_ids' => Arr::get($notes, 'options.shipment_package_ids', []),
            'trigger_type' => $run->trigger_type,
            'sync_type' => $run->sync_type,
            'request_jitter_seconds' => $profile?->request_jitter_seconds ?? 5,
            'max_parallel_jobs' => $profile?->max_parallel_jobs ?? 1,
            'backfill_mode' => $profile?->backfill_mode,
        ];
    }

    protected function resolveBackfillStart(IntegrationSyncRun $run): CarbonImmutable
    {
        $profile = $run->store->syncProfile;
        $now = CarbonImmutable::now();

        $startDate = match ($profile?->backfill_mode) {
            '7_days' => $now->subDays(7),
            '90_days' => $now->subDays(90),
            '180_days' => $now->subDays(180),
            'max_allowed' => $run->sync_type === 'orders' ? $now->subMonths(3) : $now->subDays(180),
            'custom' => $profile?->backfill_custom_from
                ? CarbonImmutable::parse($profile->backfill_custom_from)
                : $now->subDays(30),
            default => $now->subDays($profile?->backfill_days ?? 30),
        };

        if ($run->sync_type !== 'orders' || $run->store->marketplace !== 'trendyol') {
            return $startDate;
        }

        $historyLimitDays = max(1, (int) config('marketplace.trendyol.order_history_limit_days', 30));
        $trendyolMinimumStartDate = $now->subDays($historyLimitDays);

        return $startDate->lessThan($trendyolMinimumStartDate)
            ? $trendyolMinimumStartDate
            : $startDate;
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $options
     */
    protected function markSuccessful(IntegrationSyncRun $run, ?IntegrationConnection $connection, array $result, array $options): void
    {
        $finishedAt = now();
        $durationMs = $run->started_at
            ? (int) round(CarbonImmutable::parse($run->started_at)->diffInMilliseconds(CarbonImmutable::parse($finishedAt)))
            : null;

        $run->forceFill([
            'status' => 'completed',
            'finished_at' => $finishedAt,
            'duration_ms' => $durationMs,
            'items_received' => $result['items_received'] ?? 0,
            'items_created' => $result['created'] ?? 0,
            'items_updated' => $result['updated'] ?? 0,
            'items_skipped' => $result['skipped'] ?? 0,
            'cursor_after' => (string) ($result['cursor_after'] ?? $options['end_date']),
            'notes_json' => array_filter([
                'options' => $options,
                'meta' => $result['notes'] ?? [],
                'diagnostics' => $result['diagnostics'] ?? [],
            ]),
        ])->save();

        $run->store->forceFill([
            'last_synced_at' => now(),
            'status' => 'connected',
        ])->save();

        if ($connection) {
            $connection->forceFill([
                'status' => 'configured',
                'last_verified_at' => now(),
                'last_error' => null,
            ])->save();
        }
    }

    protected function markFailed(IntegrationSyncRun $run, ?IntegrationConnection $connection, Throwable $exception): void
    {
        $run->forceFill([
            'status' => 'failed',
            'finished_at' => now(),
            'error_count' => (int) $run->error_count + 1,
            'notes_json' => array_merge($run->notes_json ?? [], [
                'last_error' => $exception->getMessage(),
            ]),
        ])->save();

        if ($connection) {
            $connection->forceFill([
                'status' => 'error',
                'last_error' => $exception->getMessage(),
            ])->save();
        }
    }
}
