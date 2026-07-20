<?php

namespace App\Services\Marketplace;

use App\Models\IntegrationConnection;
use App\Models\IntegrationSyncRun;
use App\Services\Marketplace\Contracts\PullsClaims;
use App\Services\Marketplace\Contracts\PullsFinancials;
use App\Services\Marketplace\Contracts\PullsCustomerQuestions;
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
        protected MarketplaceClaimSyncService $claimSyncService,
        protected MarketplaceOrderSyncService $orderSyncService,
        protected MarketplaceQuestionSyncService $questionSyncService,
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
            $connector = $this->connectorManager->resolveForStore($store);
            $syncType = $this->normalizeSyncType($run->sync_type);
            $options = $this->buildOptions($run);

            $run->forceFill([
                'cursor_before' => (string) $options['start_date'],
            ])->save();

            $result = match ($syncType) {
                'orders' => $this->syncOrders($connector, $store, $options),
                'products' => $this->syncProducts($connector, $store, $options),
                'finance' => $this->syncFinancials($connector, $store, $options),
                'questions' => $this->syncQuestions($connector, $store, $options),
                'claims' => $this->syncClaims($connector, $store, $options),
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
        $sync = $this->orderSyncService->sync($store, $items, $options);

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
        $sync = $this->catalogSyncService->sync($store, $items, $options);

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

    /**
     * @param  object  $connector
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function syncQuestions(object $connector, $store, array $options): array
    {
        if (!$connector instanceof PullsCustomerQuestions) {
            throw new \RuntimeException('Bu bağlayıcı müşteri sorusu çekmeyi henüz desteklemiyor.');
        }

        $response = $connector->pullCustomerQuestions($store, $options);
        $items = $response['items'] ?? [];
        $sync = $this->questionSyncService->sync($store, $items, $options);

        return array_merge($sync, [
            'items_received' => $response['meta']['items_received'] ?? count($items),
            'cursor_after' => $response['meta']['cursor_after'] ?? ($options['end_date'] ?? null),
            'notes' => $response['meta'] ?? [],
            'diagnostics' => [],
        ]);
    }

    /**
     * @param  object  $connector
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function syncClaims(object $connector, $store, array $options): array
    {
        if (!$connector instanceof PullsClaims) {
            throw new \RuntimeException('Bu bağlayıcı iade talebi çekmeyi henüz desteklemiyor.');
        }

        $response = $connector->pullClaims($store, $options);
        $items = $response['items'] ?? [];
        $sync = $this->claimSyncService->sync($store, $items, $options);

        return array_merge($sync, [
            'items_received' => $response['meta']['items_received'] ?? count($items),
            'cursor_after' => $response['meta']['cursor_after'] ?? ($options['end_date'] ?? null),
            'notes' => $response['meta'] ?? [],
            'diagnostics' => [],
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

        if (!$startDate && $this->shouldUseWideManualQuestionWindow($run)) {
            $startDate = $now->subDays(31);
        }

        $cursor = null;

        if (!$startDate) {
            $lastCompletedRun = IntegrationSyncRun::query()
                ->where('store_id', $store->id)
                ->where('sync_type', $run->sync_type)
                ->whereIn('status', ['completed', 'failed']) // Pick up cursor even if failed previously to resume
                ->whereNotNull('finished_at')
                ->latest('finished_at')
                ->first();

            if ($lastCompletedRun?->cursor_after && $run->sync_type === 'orders') {
                $cursor = $lastCompletedRun->cursor_after;
                // Keep the original start_date of the window if we are continuing via cursor
                $startDate = $lastCompletedRun->cursor_before ? CarbonImmutable::parse($lastCompletedRun->cursor_before) : CarbonImmutable::parse($lastCompletedRun->started_at);
            } elseif ($lastCompletedRun?->finished_at) {
                $startDate = CarbonImmutable::parse($lastCompletedRun->finished_at)->subMinutes(5);
            } else {
                $startDate = $this->resolveBackfillStart($run);
            }
        }

        return [
            'start_date' => $startDate->toIso8601String(),
            'end_date' => $endDate->toIso8601String(),
            'cursor' => $cursor,
            'page_size' => Arr::get($notes, 'options.page_size'),
            'order_number' => Arr::get($notes, 'options.order_number'),
            'shipment_package_ids' => Arr::get($notes, 'options.shipment_package_ids', []),
            'stock_code' => Arr::get($notes, 'options.stock_code'),
            'barcode' => Arr::get($notes, 'options.barcode'),
            'product_main_id' => Arr::get($notes, 'options.product_main_id'),
            'external_product_id' => Arr::get($notes, 'options.external_product_id'),
            'variant_name' => Arr::get($notes, 'options.variant_name'),
            'status' => Arr::get($notes, 'options.status'),
            'on_sale' => Arr::get($notes, 'options.on_sale'),
            'date_query_type' => Arr::get($notes, 'options.date_query_type'),
            'full_catalog_refresh' => (bool) Arr::get($notes, 'options.full_catalog_refresh', false),
            'current_status_refresh' => (bool) Arr::get($notes, 'options.current_status_refresh', false),
            'refresh_commission_rates' => (bool) Arr::get($notes, 'options.refresh_commission_rates', false),
            'trigger_type' => $run->trigger_type,
            'sync_type' => $run->sync_type,
            'request_jitter_seconds' => $profile?->request_jitter_seconds ?? 5,
            'max_parallel_jobs' => $profile?->max_parallel_jobs ?? 1,
            'backfill_mode' => $profile?->backfill_mode,
        ];
    }

    protected function shouldUseWideManualQuestionWindow(IntegrationSyncRun $run): bool
    {
        return $run->sync_type === 'questions'
            && $run->trigger_type === 'manual'
            && MarketplaceProviderRegistry::normalize((string) $run->store->marketplace) === 'ciceksepeti';
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
                'status' => $connection->isDemo() ? IntegrationConnection::STATUS_DEMO : 'configured',
                'last_verified_at' => $connection->isDemo() ? $connection->last_verified_at : now(),
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
                'status' => $connection->isDemo() ? IntegrationConnection::STATUS_DEMO : 'error',
                'last_error' => $exception->getMessage(),
            ])->save();
        }

        app(\App\Services\NotificationCenterService::class)->notifyIntegrationFailure($run, $exception);
    }
}
