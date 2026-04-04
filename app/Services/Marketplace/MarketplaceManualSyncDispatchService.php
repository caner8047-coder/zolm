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
     * @return array{created: bool, debounced: bool, reason: string|null, run: IntegrationSyncRun, debounce_seconds: int}
     */
    public function dispatch(MarketplaceStore $store, string $syncType, array $notes = []): array
    {
        $store->loadMissing('syncProfile');
        $this->guardSyncType($store, $syncType);

        $debounceSeconds = $this->debounceWindow($store);
        $activeRun = $this->findActiveRun($store, $syncType);

        if ($activeRun) {
            return [
                'created' => false,
                'debounced' => true,
                'reason' => 'active',
                'run' => $activeRun,
                'debounce_seconds' => $debounceSeconds,
            ];
        }

        $recentRun = $this->findRecentManualRun($store, $syncType, $debounceSeconds);

        if ($recentRun) {
            return [
                'created' => false,
                'debounced' => true,
                'reason' => 'recent',
                'run' => $recentRun,
                'debounce_seconds' => $debounceSeconds,
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

        SyncMarketplaceDataJob::dispatch($run->id);

        return [
            'created' => true,
            'debounced' => false,
            'reason' => null,
            'run' => $run,
            'debounce_seconds' => $debounceSeconds,
        ];
    }

    /**
     * @param  array{created: bool, debounced: bool, reason: string|null, run: IntegrationSyncRun, debounce_seconds: int}  $result
     * @return array{message: string, tone: string}
     */
    public function feedback(array $result, string $syncLabel, ?string $storeName = null): array
    {
        $prefix = filled($storeName) ? "{$storeName} için " : '';

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

    protected function debounceWindow(MarketplaceStore $store): int
    {
        $configured = (int) config('marketplace.manual_sync.debounce_seconds', 30);
        $profileJitter = (int) ($store->syncProfile?->request_jitter_seconds ?? 0);

        return max(1, $configured, $profileJitter);
    }

    protected function guardSyncType(MarketplaceStore $store, string $syncType): void
    {
        $connector = $this->connectorManager->resolve($store->marketplace);
        $capabilities = $connector->capabilities();

        if (!in_array($syncType, ['orders', 'products', 'finance'], true)) {
            throw new \RuntimeException('Geçersiz senkron tipi seçildi.');
        }

        if (!(bool) ($capabilities[$syncType] ?? false)) {
            throw new \RuntimeException('Bu kanal için ' . mb_strtolower($this->syncTypeLabel($syncType)) . ' sync desteklenmiyor.');
        }
    }

    protected function syncTypeLabel(string $syncType): string
    {
        return match ($syncType) {
            'orders' => 'Sipariş',
            'products' => 'Ürün',
            'finance' => 'Finans',
            default => $syncType,
        };
    }

    protected function findActiveRun(MarketplaceStore $store, string $syncType): ?IntegrationSyncRun
    {
        $activeStatuses = config('marketplace.manual_sync.active_statuses', ['queued', 'processing', 'retrying']);
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
