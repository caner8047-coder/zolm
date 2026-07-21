<?php

namespace App\Services\Marketplace;

use App\Jobs\SyncMarketplaceDataJob;
use App\Models\IntegrationSyncRun;
use App\Models\MarketplaceStore;

class MarketplaceManualSyncDispatchService
{
    public function __construct(
        protected MarketplaceConnectorManager $connectorManager,
    ) {
    }

    /**
     * @param  array<string, mixed>  $notes
     * @return array{
     *     created: bool,
     *     debounced: bool,
     *     reason: string|null,
     *     run: IntegrationSyncRun,
     *     debounce_seconds: int,
     *     executed_inline: bool,
     *     inline_error: string|null
     * }
     */
    public function dispatch(MarketplaceStore $store, string $syncType, array $notes = []): array
    {
        $store->loadMissing('syncProfile');
        $this->guardSyncType($store, $syncType);

        $debounceSeconds = $this->debounceWindow($store);
        $willRunInline = $this->shouldRunInline($notes);
        $activeStatuses = $willRunInline && (bool) data_get($notes, 'ignore_queued_active', false)
            ? ['processing', 'retrying']
            : null;
        $activeRun = $this->findActiveRun($store, $syncType, $activeStatuses);

        if ($activeRun) {
            return [
                'created' => false,
                'debounced' => true,
                'reason' => 'active',
                'run' => $activeRun,
                'debounce_seconds' => $debounceSeconds,
                'executed_inline' => false,
                'inline_error' => null,
            ];
        }

        $recentRun = $willRunInline && (bool) data_get($notes, 'bypass_recent', false)
            ? null
            : $this->findRecentManualRun($store, $syncType, $debounceSeconds);

        if ($recentRun) {
            return [
                'created' => false,
                'debounced' => true,
                'reason' => 'recent',
                'run' => $recentRun,
                'debounce_seconds' => $debounceSeconds,
                'executed_inline' => false,
                'inline_error' => null,
            ];
        }

        $run = IntegrationSyncRun::create([
            'store_id' => $store->id,
            'sync_type' => $syncType,
            'trigger_type' => 'manual',
            'status' => 'queued',
            'notes_json' => array_replace([
                'options' => [],
            ], $notes),
        ]);

        if ($willRunInline) {
            $inlineError = null;

            try {
                SyncMarketplaceDataJob::dispatchSync($run->id);
            } catch (\Throwable $exception) {
                $inlineError = $exception->getMessage();
            }

            return [
                'created' => true,
                'debounced' => false,
                'reason' => null,
                'run' => $run->fresh() ?? $run,
                'debounce_seconds' => $debounceSeconds,
                'executed_inline' => true,
                'inline_error' => $inlineError,
            ];
        }

        SyncMarketplaceDataJob::dispatch($run->id);

        return [
            'created' => true,
            'debounced' => false,
            'reason' => null,
            'run' => $run,
            'debounce_seconds' => $debounceSeconds,
            'executed_inline' => false,
            'inline_error' => null,
        ];
    }

    /**
     * @param  array{
     *     created: bool,
     *     debounced: bool,
     *     reason: string|null,
     *     run: IntegrationSyncRun,
     *     debounce_seconds: int,
     *     executed_inline: bool,
     *     inline_error: string|null
     * }  $result
     * @return array{message: string, tone: string}
     */
    public function feedback(array $result, string $syncLabel, ?string $storeName = null): array
    {
        $prefix = filled($storeName) ? "{$storeName} için " : '';

        if ($result['executed_inline']) {
            if (filled($result['inline_error'])) {
                return [
                    'message' => "{$prefix}{$syncLabel} sync çalışırken hata verdi: {$result['inline_error']}",
                    'tone' => 'error',
                ];
            }

            $run = $result['run'];
            $summary = collect([
                is_numeric($run->items_received) ? ((int) $run->items_received . ' kayıt alındı') : null,
                (int) $run->items_created > 0 ? ((int) $run->items_created . ' yeni') : null,
                (int) $run->items_updated > 0 ? ((int) $run->items_updated . ' güncellendi') : null,
                (int) $run->items_skipped > 0 ? ((int) $run->items_skipped . ' atlandı') : null,
            ])->filter()->implode(' · ');

            $message = match ($run->status) {
                'completed' => "{$prefix}{$syncLabel} sync tamamlandı.",
                'skipped' => "{$prefix}{$syncLabel} sync atlandı.",
                'failed' => "{$prefix}{$syncLabel} sync başarısız oldu.",
                default => "{$prefix}{$syncLabel} sync işlendi.",
            };

            $tone = match ($run->status) {
                'completed' => 'success',
                'failed' => 'error',
                'skipped' => 'warning',
                default => 'info',
            };

            return [
                'message' => $summary !== '' ? $message . ' ' . $summary : $message,
                'tone' => $tone,
            ];
        }

        if ($result['created']) {
            return [
                'message' => "{$prefix}{$syncLabel} sync kuyruğa alındı.",
                'tone' => 'success',
            ];
        }

        if (($result['reason'] ?? null) === 'active') {
            return [
                'message' => "{$prefix}{$syncLabel} sync zaten çalışıyor. Mevcut kayıt #{$result['run']->id} kullanılacak.",
                'tone' => 'info',
            ];
        }

        return [
            'message' => "{$prefix}{$syncLabel} sync az önce kuyruğa alındı. {$result['debounce_seconds']} sn içinde yeni kayıt açılmadı (#{$result['run']->id}).",
            'tone' => 'info',
        ];
    }

    protected function shouldRunInline(array $notes = []): bool
    {
        if ((bool) data_get($notes, 'force_queue', false)) {
            return false;
        }

        if ((bool) data_get($notes, 'force_inline', false) && !app()->runningUnitTests()) {
            return true;
        }

        return (bool) config('marketplace.manual_sync.inline_on_local', true)
            && app()->environment('local')
            && !app()->runningUnitTests();
    }

    protected function debounceWindow(MarketplaceStore $store): int
    {
        $configured = (int) config('marketplace.manual_sync.debounce_seconds', 30);
        $profileJitter = (int) ($store->syncProfile?->request_jitter_seconds ?? 0);

        return max(1, $configured, $profileJitter);
    }

    protected function guardSyncType(MarketplaceStore $store, string $syncType): void
    {
        $connector = $this->connectorManager->resolveForStore($store);
        $capabilities = $connector->capabilities();

        if (!in_array($syncType, ['orders', 'products', 'catalog_products', 'finance', 'questions', 'claims'], true)) {
            throw new \RuntimeException('Geçersiz senkron tipi seçildi.');
        }

        $capKey = $syncType === 'catalog_products' ? 'catalog_products_pull' : $syncType;

        if (!(bool) ($capabilities[$capKey] ?? false)) {
            throw new \RuntimeException('Bu kanal için ' . mb_strtolower($this->syncTypeLabel($syncType)) . ' sync desteklenmiyor.');
        }
    }

    protected function syncTypeLabel(string $syncType): string
    {
        return match ($syncType) {
            'orders' => 'Sipariş',
            'products' => 'Ürün',
            'catalog_products' => 'Katalog Ürün',
            'finance' => 'Finans',
            'questions' => 'Soru',
            'claims' => 'İade',
            default => $syncType,
        };
    }

    /**
     * @param  array<int, string>|null  $activeStatuses
     */
    protected function findActiveRun(MarketplaceStore $store, string $syncType, ?array $activeStatuses = null): ?IntegrationSyncRun
    {
        $activeStatuses ??= config('marketplace.manual_sync.active_statuses', ['queued', 'processing', 'retrying']);
        $activeWindowSeconds = (int) config('marketplace.manual_sync.active_run_block_seconds', 900);

        return IntegrationSyncRun::query()
            ->where('store_id', $store->id)
            ->where('sync_type', $syncType)
            ->whereIn('status', $activeStatuses)
            ->where('created_at', '>=', now()->subSeconds(max(1, $activeWindowSeconds)))
            ->where('trigger_type', '!=', 'smoke_test')
            ->latest('created_at')
            ->first();
    }

    protected function findRecentManualRun(MarketplaceStore $store, string $syncType, int $debounceSeconds): ?IntegrationSyncRun
    {
        return IntegrationSyncRun::query()
            ->where('store_id', $store->id)
            ->where('sync_type', $syncType)
            ->where('trigger_type', 'manual')
            ->where('status', '!=', 'failed')
            ->where('created_at', '>=', now()->subSeconds($debounceSeconds))
            ->latest('created_at')
            ->first();
    }
}
