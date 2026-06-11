<?php

namespace App\Jobs;

use App\Models\ChannelListing;
use App\Models\IntegrationPushRun;
use App\Services\Marketplace\Connectors\WooCommerceConnector;
use App\Services\Marketplace\Contracts\PushesPrice;
use App\Services\Marketplace\Contracts\PushesStock;
use App\Services\Marketplace\MarketplaceConnectorManager;
use App\Services\MpProductChangeLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class PushMarketplaceListingUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 3;

    /**
     * @var array<int>
     */
    public array $backoff = [15, 60, 180];

    public function __construct(public int $pushRunId)
    {
        $this->onQueue((string) config('marketplace.queues.listing_push', 'default'));
    }

    public function handle(MarketplaceConnectorManager $connectorManager): void
    {
        $pushRun = IntegrationPushRun::query()
            ->with(['store.connection', 'store.syncProfile', 'listing.store', 'listing.channelProduct', 'listing.product'])
            ->findOrFail($this->pushRunId);

        if (in_array($pushRun->status, ['completed', 'failed'], true)) {
            return;
        }

        if ($pushRun->status === 'processing' && filled($pushRun->external_batch_id)) {
            return;
        }

        $connector = $connectorManager->resolve($pushRun->store->marketplace);

        if ($this->shouldUseWooBatch($pushRun, $connector)) {
            $this->handleWooBatch($pushRun, $connector);

            return;
        }

        $pushRun->update([
            'status' => 'processing',
            'started_at' => $pushRun->started_at ?: now(),
            'attempt_count' => (int) $this->attempts(),
            'error_message' => null,
        ]);

        try {
            $listing = $pushRun->listing;
            if (!$listing instanceof ChannelListing) {
                throw new \RuntimeException('Push kaydına bağlı listing bulunamadı.');
            }

            $capabilities = $connector->capabilities();
            $response = [];
            $listing->loadMissing('store');
            $logger = app(MpProductChangeLogger::class);
            $beforeListingSnapshot = $logger->listingSnapshot($listing);

            if ($pushRun->push_type === 'price') {
                if (!($connector instanceof PushesPrice) || !($capabilities['price_push'] ?? false)) {
                    throw new \RuntimeException('Bu kanal için fiyat push desteklenmiyor.');
                }

                $response = $connector->pushPrice(
                    $listing,
                    (float) $pushRun->target_price,
                    $pushRun->request_context_json ?? []
                );

                $listing->update([
                    'sale_price' => (float) $pushRun->target_price,
                    'list_price' => (float) data_get($pushRun->request_context_json, 'list_price', $listing->list_price),
                    'last_price_sync_at' => now(),
                    'last_synced_at' => now(),
                ]);
                $logger->logListingSnapshotChanges(
                    $listing->fresh() ?: $listing,
                    $beforeListingSnapshot,
                    'price_push',
                    $pushRun->triggered_by,
                    'Pazaryerine fiyat gönderimi tamamlandı',
                    $pushRun->external_batch_id ?: null,
                    ['push_run_id' => $pushRun->id]
                );
            } elseif ($pushRun->push_type === 'stock') {
                if (!($connector instanceof PushesStock) || !($capabilities['stock_push'] ?? false)) {
                    throw new \RuntimeException('Bu kanal için stok push desteklenmiyor.');
                }

                $response = $connector->pushStock(
                    $listing,
                    (int) $pushRun->target_quantity,
                    $pushRun->request_context_json ?? []
                );

                $listing->update([
                    'stock_quantity' => (int) $pushRun->target_quantity,
                    'last_stock_sync_at' => now(),
                    'last_synced_at' => now(),
                ]);
                $logger->logListingSnapshotChanges(
                    $listing->fresh() ?: $listing,
                    $beforeListingSnapshot,
                    'stock_push',
                    $pushRun->triggered_by,
                    'Pazaryerine stok gönderimi tamamlandı',
                    $pushRun->external_batch_id ?: null,
                    ['push_run_id' => $pushRun->id]
                );

                $freshListing = $listing->fresh();

                if ($freshListing) {
                    app(\App\Services\NotificationCenterService::class)->syncListingStockAlert($freshListing);
                }
            } else {
                throw new \RuntimeException('Desteklenmeyen push tipi: ' . $pushRun->push_type);
            }

            $pushRun->update([
                'status' => 'completed',
                'response_json' => $response,
                'external_batch_id' => data_get($response, 'batch_request_id'),
                'finished_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $pushRun->update([
                'status' => $this->attempts() >= $this->tries ? 'failed' : 'retrying',
                'error_message' => $exception->getMessage(),
                'finished_at' => $this->attempts() >= $this->tries ? now() : null,
            ]);

            if ($this->attempts() >= $this->tries) {
                $freshRun = $pushRun->fresh();

                if ($freshRun) {
                    app(\App\Services\NotificationCenterService::class)->notifyPushFailure($freshRun, $exception);
                }
            }

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        $pushRun = IntegrationPushRun::query()
            ->with(['store', 'listing.channelProduct', 'listing.product'])
            ->whereKey($this->pushRunId)
            ->first();

        IntegrationPushRun::query()
            ->whereKey($this->pushRunId)
            ->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ]);

        if ($pushRun) {
            app(\App\Services\NotificationCenterService::class)->notifyPushFailure($pushRun, $exception);
        }
    }

    protected function shouldUseWooBatch(IntegrationPushRun $pushRun, object $connector): bool
    {
        return $connector instanceof WooCommerceConnector
            && (bool) config('marketplace.woocommerce.batch_push_enabled', true)
            && in_array($pushRun->push_type, ['price', 'stock'], true);
    }

    protected function handleWooBatch(IntegrationPushRun $pushRun, WooCommerceConnector $connector): void
    {
        $batchId = 'woo-batch-'.Str::uuid()->toString();
        $runs = $this->claimWooBatchRuns($pushRun, $batchId);

        if ($runs->isEmpty()) {
            return;
        }

        try {
            $response = $pushRun->push_type === 'price'
                ? $connector->pushPriceBatch($pushRun->store, $runs)
                : $connector->pushStockBatch($pushRun->store, $runs);

            $itemsByRunId = collect($response['items'] ?? [])
                ->filter(fn ($item) => is_array($item) && filled($item['push_run_id'] ?? null))
                ->keyBy(fn (array $item) => (int) $item['push_run_id']);

            foreach ($runs as $run) {
                $listing = $run->listing;

                if ($listing instanceof ChannelListing) {
                    $listing->loadMissing('store');
                    $logger = app(MpProductChangeLogger::class);
                    $beforeListingSnapshot = $logger->listingSnapshot($listing);

                    if ($run->push_type === 'price') {
                        $listing->update([
                            'sale_price' => (float) $run->target_price,
                            'list_price' => (float) data_get($run->request_context_json, 'list_price', $listing->list_price),
                            'last_price_sync_at' => now(),
                            'last_synced_at' => now(),
                        ]);
                        $logger->logListingSnapshotChanges(
                            $listing->fresh() ?: $listing,
                            $beforeListingSnapshot,
                            'price_push',
                            $run->triggered_by,
                            'WooCommerce toplu fiyat gönderimi tamamlandı',
                            $batchId,
                            ['push_run_id' => $run->id]
                        );
                    } else {
                        $listing->update([
                            'stock_quantity' => (int) $run->target_quantity,
                            'last_stock_sync_at' => now(),
                            'last_synced_at' => now(),
                        ]);
                        $logger->logListingSnapshotChanges(
                            $listing->fresh() ?: $listing,
                            $beforeListingSnapshot,
                            'stock_push',
                            $run->triggered_by,
                            'WooCommerce toplu stok gönderimi tamamlandı',
                            $batchId,
                            ['push_run_id' => $run->id]
                        );

                        $freshListing = $listing->fresh();

                        if ($freshListing) {
                            app(\App\Services\NotificationCenterService::class)->syncListingStockAlert($freshListing);
                        }
                    }
                }

                $itemResponse = $itemsByRunId->get((int) $run->id, []);

                $run->update([
                    'status' => 'completed',
                    'response_json' => array_filter([
                        'batch' => $response['response'] ?? [],
                        'item' => data_get($itemResponse, 'response', []),
                    ]),
                    'external_batch_id' => (string) ($response['batch_request_id'] ?? $batchId),
                    'finished_at' => now(),
                    'error_message' => null,
                ]);
            }
        } catch (Throwable $exception) {
            $status = $this->attempts() >= $this->tries ? 'failed' : 'retrying';

            IntegrationPushRun::query()
                ->whereIn('id', $runs->pluck('id'))
                ->update([
                    'status' => $status,
                    'error_message' => $exception->getMessage(),
                    'finished_at' => $status === 'failed' ? now() : null,
                ]);

            if ($status === 'failed') {
                foreach ($runs as $run) {
                    app(\App\Services\NotificationCenterService::class)->notifyPushFailure($run, $exception);
                }
            }

            throw $exception;
        }
    }

    protected function claimWooBatchRuns(IntegrationPushRun $pushRun, string $batchId): Collection
    {
        $limit = max(1, (int) config('marketplace.woocommerce.batch_size', 25));

        return DB::transaction(function () use ($pushRun, $batchId, $limit) {
            $selected = IntegrationPushRun::query()
                ->where('store_id', $pushRun->store_id)
                ->where('push_type', $pushRun->push_type)
                ->whereIn('status', ['queued', 'retrying'])
                ->orderBy('id')
                ->lockForUpdate()
                ->limit($limit)
                ->get();

            if ($selected->isEmpty()) {
                return collect();
            }

            $selectedIds = $selected->pluck('id')->all();

            IntegrationPushRun::query()
                ->whereIn('id', $selectedIds)
                ->update([
                    'status' => 'processing',
                    'started_at' => now(),
                    'attempt_count' => (int) $this->attempts(),
                    'error_message' => null,
                    'external_batch_id' => $batchId,
                ]);

            return IntegrationPushRun::query()
                ->with(['store.connection', 'store.syncProfile', 'listing.store', 'listing.channelProduct', 'listing.product'])
                ->whereIn('id', $selectedIds)
                ->orderBy('id')
                ->get();
        });
    }
}
