<?php

namespace App\Services\Marketplace;

use App\Jobs\SyncMarketplaceDataJob;
use App\Models\IntegrationSyncRun;
use App\Models\IntegrationWebhookEvent;
use App\Models\MarketplaceStore;
use Illuminate\Support\Str;

class MarketplaceWebhookDispatchService
{
    public function dispatchForEvent(MarketplaceStore $store, IntegrationWebhookEvent $event): ?IntegrationSyncRun
    {
        $store->loadMissing('syncProfile');

        if (!$store->syncProfile?->webhook_enabled) {
            return null;
        }

        if ($this->shouldIgnoreTopic($store, (string) $event->event_type)) {
            $event->forceFill([
                'status' => 'ignored',
                'processed_at' => now(),
                'error_message' => 'Webhook topic mağaza profilinde aktif olmadığı için işlenmedi.',
            ])->save();

            return null;
        }

        $syncType = $this->resolveSyncType($store, (string) $event->event_type);
        $debouncedBy = $this->findRecentActiveRun($store, $syncType);

        if ($debouncedBy) {
            $event->forceFill([
                'status' => 'debounced',
                'processed_at' => now(),
                'error_message' => "Aynı mağaza için aktif {$this->syncTypeLabel($syncType)} sync bulunduğu için webhook debounce edildi.",
            ])->save();

            return null;
        }

        $syncRun = IntegrationSyncRun::create([
            'store_id' => $store->id,
            'sync_type' => $syncType,
            'trigger_type' => 'webhook',
            'status' => 'queued',
            'started_at' => now(),
            'notes_json' => [
                'event_id' => $event->id,
                'event_type' => $event->event_type,
                'source' => 'webhook',
            ],
        ]);

        SyncMarketplaceDataJob::dispatch($syncRun->id);

        return $syncRun;
    }

    public function resolveSyncType(MarketplaceStore $store, string $eventType): string
    {
        $normalized = Str::lower(trim($eventType));

        if ($normalized === '') {
            return 'webhook_refresh';
        }

        $productKeywords = ['product', 'variation', 'inventory', 'listing'];
        $financeKeywords = ['transaction', 'payment', 'refund', 'settlement', 'payout', 'fee'];

        foreach ($productKeywords as $keyword) {
            if (str_contains($normalized, $keyword)) {
                return 'products';
            }
        }

        foreach ($financeKeywords as $keyword) {
            if (str_contains($normalized, $keyword)) {
                return 'finance';
            }
        }

        return 'webhook_refresh';
    }

    protected function findRecentActiveRun(MarketplaceStore $store, string $syncType): ?IntegrationSyncRun
    {
        $windowSeconds = max(0, (int) config(
            'marketplace.' . MarketplaceProviderRegistry::normalize($store->marketplace) . '.webhook_debounce_seconds',
            config('marketplace.webhook_debounce.default_seconds', 30)
        ));

        if ($windowSeconds === 0) {
            return null;
        }

        $activeStatuses = config('marketplace.webhook_debounce.active_statuses', ['queued', 'processing']);
        $syncTypes = $syncType === 'webhook_refresh'
            ? ['webhook_refresh', 'orders']
            : [$syncType];

        return IntegrationSyncRun::query()
            ->where('store_id', $store->id)
            ->whereIn('sync_type', $syncTypes)
            ->whereIn('status', $activeStatuses)
            ->where('trigger_type', '!=', 'smoke_test')
            ->where(function ($query) use ($windowSeconds) {
                $query->where('created_at', '>=', now()->subSeconds($windowSeconds))
                    ->orWhere('started_at', '>=', now()->subSeconds($windowSeconds));
            })
            ->latest('id')
            ->first();
    }

    protected function shouldIgnoreTopic(MarketplaceStore $store, string $eventType): bool
    {
        $configuredTopics = collect(data_get(
            $store->syncProfile?->extra_settings ?? [],
            'webhook_topics',
            data_get(
                \App\Models\IntegrationSyncProfile::defaultsForMarketplace($store->marketplace),
                'extra_settings.webhook_topics',
                []
            )
        ))
            ->filter(fn ($topic) => filled($topic))
            ->map(fn ($topic) => Str::lower((string) $topic))
            ->values();

        if ($configuredTopics->isEmpty()) {
            return false;
        }

        $normalizedEventType = Str::lower(trim($eventType));

        if ($normalizedEventType === '') {
            return false;
        }

        return !$configuredTopics->contains($normalizedEventType);
    }

    protected function syncTypeLabel(string $syncType): string
    {
        return match ($syncType) {
            'products' => 'ürün',
            'finance' => 'finans',
            default => 'sipariş',
        };
    }
}
