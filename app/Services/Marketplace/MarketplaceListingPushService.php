<?php

namespace App\Services\Marketplace;

use App\Jobs\PushMarketplaceListingUpdateJob;
use App\Models\ChannelListing;
use App\Models\IntegrationPushRun;
use App\Services\Marketplace\Contracts\PushesPrice;
use App\Services\Marketplace\Contracts\PushesStock;
use Illuminate\Support\Facades\DB;

class MarketplaceListingPushService
{
    /**
     * @param  array<string, mixed>  $context
     * @return array{
     *     created: bool,
     *     coalesced: bool,
     *     busy: bool,
     *     recent: bool,
     *     reason: string|null,
     *     push_run: IntegrationPushRun,
     *     debounce_seconds: int
     * }
     */
    public function queuePricePush(ChannelListing $listing, float $price, array $context = [], ?int $triggeredBy = null): array
    {
        $listing->loadMissing(['store.syncProfile', 'channelProduct', 'product']);

        if (!$listing->product) {
            throw new \RuntimeException('Listing henüz bir master ürüne bağlı değil.');
        }

        if (!$listing->store->syncProfile?->price_push_enabled) {
            throw new \RuntimeException('Bu mağazada fiyat push özelliği kapalı.');
        }

        $connector = app(MarketplaceConnectorManager::class)->resolveForStore($listing->store);
        $capabilities = $connector->capabilities();

        if (!($connector instanceof PushesPrice) || !($capabilities['price_push'] ?? false)) {
            throw new \RuntimeException('Bu kanal için fiyat push henüz desteklenmiyor.');
        }

        if (!isset($context['price_action_id']) && !isset($context['write_context_type'])) {
            $actorId = $triggeredBy ?: auth()->id();
            if (!$actorId) {
                if (app()->runningInConsole()) {
                    $context = array_merge([
                        'write_context_type' => 'legacy_manual',
                        'actor_type' => 'system',
                        'actor_id' => 'system',
                        'permission' => 'update_price',
                        'store_id' => $listing->store_id,
                        'correlation_id' => 'manual-system-' . uniqid(),
                        'idempotency_key' => 'key-system-' . uniqid(),
                        'reason' => 'System console triggered update via MarketplaceListingPushService',
                    ], $context);
                } else {
                    throw new \RuntimeException('Fiyat push güncellemesi için yetkili kullanıcı (actor) bilgisi bulunamadı.');
                }
            } else {
                $context = array_merge([
                    'write_context_type' => 'legacy_manual',
                    'actor_type' => 'user',
                    'actor_id' => $actorId,
                    'permission' => 'update_price',
                    'store_id' => $listing->store_id,
                    'correlation_id' => 'manual-' . uniqid(),
                    'idempotency_key' => 'key-' . uniqid(),
                    'reason' => 'User manually triggered update via MarketplaceListingPushService',
                ], $context);
            }
        }

        return $this->queuePush(
            $listing,
            'price',
            round($price, 2),
            (int) data_get($context, 'quantity', $listing->stock_quantity),
            $context,
            $triggeredBy,
        );
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{
     *     created: bool,
     *     coalesced: bool,
     *     busy: bool,
     *     recent: bool,
     *     reason: string|null,
     *     push_run: IntegrationPushRun,
     *     debounce_seconds: int
     * }
     */
    public function queueStockPush(ChannelListing $listing, int $quantity, array $context = [], ?int $triggeredBy = null): array
    {
        $listing->loadMissing(['store.syncProfile', 'channelProduct', 'product']);

        if (!$listing->product) {
            throw new \RuntimeException('Listing henüz bir master ürüne bağlı değil.');
        }

        if (!$listing->store->syncProfile?->stock_push_enabled) {
            throw new \RuntimeException('Bu mağazada stok push özelliği kapalı.');
        }

        $connector = app(MarketplaceConnectorManager::class)->resolveForStore($listing->store);
        $capabilities = $connector->capabilities();

        if (!($connector instanceof PushesStock) || !($capabilities['stock_push'] ?? false)) {
            throw new \RuntimeException('Bu kanal için stok push henüz desteklenmiyor.');
        }

        if (!isset($context['write_context_type'])) {
            $actorId = $triggeredBy ?: auth()->id() ?: 'system';
            $actorType = is_numeric($actorId) ? 'user' : 'system';
            $context = array_merge([
                'write_context_type' => 'stock_update',
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'store_id' => $listing->store_id,
                'correlation_id' => 'stock-' . uniqid(),
                'idempotency_key' => 'stock-key-' . uniqid(),
                'reason' => 'Stock update via MarketplaceListingPushService',
                'sale_price' => $listing->sale_price,
                'list_price' => $listing->list_price ?? $listing->sale_price,
            ], $context);
        }

        return $this->queuePush(
            $listing,
            'stock',
            round((float) data_get($context, 'sale_price', $listing->sale_price), 2),
            $quantity,
            $context,
            $triggeredBy,
        );
    }

    /**
     * @param  array{
     *     created: bool,
     *     coalesced: bool,
     *     busy: bool,
     *     recent: bool,
     *     reason: string|null,
     *     push_run: IntegrationPushRun,
     *     debounce_seconds: int
     * }  $result
     * @return array{message: string, tone: string}
     */
    public function feedback(array $result, string $pushLabel, ?string $storeName = null): array
    {
        $prefix = filled($storeName) ? "{$storeName} için " : '';
        $pushRun = $result['push_run'];

        if ($result['created']) {
            return [
                'message' => "{$prefix}{$pushLabel} push kuyruğa alındı. İş no: #{$pushRun->id}",
                'tone' => 'success',
            ];
        }

        if ($result['coalesced']) {
            return [
                'message' => "{$prefix}{$pushLabel} push bekleyen kayıt üzerinde güncellendi. İş no: #{$pushRun->id}",
                'tone' => 'success',
            ];
        }

        if ($result['busy']) {
            return [
                'message' => "{$prefix}{$pushLabel} push zaten çalışıyor. Mevcut kayıt #{$pushRun->id} tamamlanınca tekrar deneyin.",
                'tone' => 'info',
            ];
        }

        return [
            'message' => "{$prefix}aynı {$pushLabel} push az önce işlendi. {$result['debounce_seconds']} sn içinde yeni kayıt açılmadı (#{$pushRun->id}).",
            'tone' => 'info',
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{
     *     created: bool,
     *     coalesced: bool,
     *     busy: bool,
     *     recent: bool,
     *     reason: string|null,
     *     push_run: IntegrationPushRun,
     *     debounce_seconds: int
     * }
     */
    protected function queuePush(
        ChannelListing $listing,
        string $pushType,
        float $targetPrice,
        int $targetQuantity,
        array $context,
        ?int $triggeredBy = null,
    ): array {
        $debounceSeconds = $this->debounceWindow($listing);
        $currency = $listing->currency ?: 'TRY';

        $result = DB::transaction(function () use (
            $listing,
            $pushType,
            $targetPrice,
            $targetQuantity,
            $context,
            $triggeredBy,
            $debounceSeconds,
            $currency,
        ) {
            $activeRun = $this->findActiveRun($listing, $pushType);

            if ($activeRun && $activeRun->status === 'processing') {
                return [
                    'created' => false,
                    'coalesced' => false,
                    'busy' => true,
                    'recent' => false,
                    'reason' => 'active',
                    'push_run' => $activeRun,
                    'debounce_seconds' => $debounceSeconds,
                ];
            }

            $mergeableRun = $this->findMergeableRun($listing, $pushType);

            if ($mergeableRun) {
                $existingContext = $mergeableRun->request_context_json ?? [];
                $mergeCount = (int) data_get($existingContext, '_merged_push_count', 0) + 1;

                $mergeableRun->update([
                    'triggered_by' => $triggeredBy ?? $mergeableRun->triggered_by,
                    'target_price' => $targetPrice,
                    'target_quantity' => $targetQuantity,
                    'currency' => $currency,
                    'request_context_json' => array_merge($existingContext, $context, [
                        '_coalesced_at' => now()->toIso8601String(),
                        '_merged_push_count' => $mergeCount,
                    ]),
                    'error_message' => null,
                ]);

                return [
                    'created' => false,
                    'coalesced' => true,
                    'busy' => false,
                    'recent' => false,
                    'reason' => 'queued',
                    'push_run' => $mergeableRun->fresh(),
                    'debounce_seconds' => $debounceSeconds,
                ];
            }

            $recentRun = $this->findRecentCompletedRun($listing, $pushType, $debounceSeconds, $targetPrice, $targetQuantity);

            if ($recentRun) {
                return [
                    'created' => false,
                    'coalesced' => false,
                    'busy' => false,
                    'recent' => true,
                    'reason' => 'recent',
                    'push_run' => $recentRun,
                    'debounce_seconds' => $debounceSeconds,
                ];
            }

            $pushRun = IntegrationPushRun::create([
                'store_id' => $listing->store_id,
                'channel_listing_id' => $listing->id,
                'mp_product_id' => $listing->mp_product_id,
                'triggered_by' => $triggeredBy,
                'push_type' => $pushType,
                'status' => 'queued',
                'target_price' => $targetPrice,
                'target_quantity' => $targetQuantity,
                'currency' => $currency,
                'request_context_json' => $context,
                'attempt_count' => 0,
            ]);

            return [
                'created' => true,
                'coalesced' => false,
                'busy' => false,
                'recent' => false,
                'reason' => null,
                'push_run' => $pushRun,
                'debounce_seconds' => $debounceSeconds,
            ];
        });

        if ($result['created']) {
            PushMarketplaceListingUpdateJob::dispatch($result['push_run']->id);
        }

        return $result;
    }

    protected function debounceWindow(ChannelListing $listing): int
    {
        $configured = (int) config('marketplace.listing_push.debounce_seconds', 45);
        $profileJitter = (int) ($listing->store->syncProfile?->request_jitter_seconds ?? 0);

        return max(1, $configured, $profileJitter);
    }

    protected function activeRunWindow(): int
    {
        return max(1, (int) config('marketplace.listing_push.active_run_block_seconds', 900));
    }

    protected function findActiveRun(ChannelListing $listing, string $pushType): ?IntegrationPushRun
    {
        return IntegrationPushRun::query()
            ->where('channel_listing_id', $listing->id)
            ->where('push_type', $pushType)
            ->whereIn('status', config('marketplace.listing_push.active_statuses', ['queued', 'processing', 'retrying']))
            ->where('created_at', '>=', now()->subSeconds($this->activeRunWindow()))
            ->latest('created_at')
            ->lockForUpdate()
            ->first();
    }

    protected function findMergeableRun(ChannelListing $listing, string $pushType): ?IntegrationPushRun
    {
        return IntegrationPushRun::query()
            ->where('channel_listing_id', $listing->id)
            ->where('push_type', $pushType)
            ->whereIn('status', config('marketplace.listing_push.merge_statuses', ['queued', 'retrying']))
            ->where('created_at', '>=', now()->subSeconds($this->activeRunWindow()))
            ->latest('created_at')
            ->lockForUpdate()
            ->first();
    }

    protected function findRecentCompletedRun(
        ChannelListing $listing,
        string $pushType,
        int $debounceSeconds,
        float $targetPrice,
        int $targetQuantity,
    ): ?IntegrationPushRun {
        return IntegrationPushRun::query()
            ->where('channel_listing_id', $listing->id)
            ->where('push_type', $pushType)
            ->where('status', 'completed')
            ->where('target_price', $targetPrice)
            ->where('target_quantity', $targetQuantity)
            ->where('created_at', '>=', now()->subSeconds($debounceSeconds))
            ->latest('created_at')
            ->lockForUpdate()
            ->first();
    }
}
