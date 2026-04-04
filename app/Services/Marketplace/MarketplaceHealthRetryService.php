<?php

namespace App\Services\Marketplace;

use App\Jobs\PushMarketplaceListingUpdateJob;
use App\Jobs\SyncMarketplaceDataJob;
use App\Models\IntegrationOrderActionRun;
use App\Models\IntegrationPushRun;
use App\Models\IntegrationSyncRun;
use App\Models\IntegrationWebhookEvent;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class MarketplaceHealthRetryService
{
    public function __construct(
        protected MarketplaceOrderActionService $orderActionService,
        protected MarketplaceListingPushService $listingPushService,
    ) {
    }

    public function retrySync(IntegrationSyncRun $run): IntegrationSyncRun
    {
        $retryRun = IntegrationSyncRun::create([
            'store_id' => $run->store_id,
            'sync_type' => $run->sync_type,
            'trigger_type' => 'retry',
            'status' => 'queued',
            'notes_json' => array_merge($run->notes_json ?? [], [
                'retry_of' => $run->id,
                'retried_at' => now()->toIso8601String(),
            ]),
        ]);

        SyncMarketplaceDataJob::dispatch($retryRun->id);

        return $retryRun;
    }

    /**
     * @param  iterable<int, IntegrationSyncRun>  $runs
     * @return Collection<int, IntegrationSyncRun>
     */
    public function retrySyncBatch(iterable $runs): Collection
    {
        return collect($runs)
            ->map(fn (IntegrationSyncRun $run) => $this->retrySync($run))
            ->values();
    }

    public function retryPush(IntegrationPushRun $run, ?int $triggeredBy = null): IntegrationPushRun
    {
        return $this->retryPushDetailed($run, $triggeredBy)['push_run'];
    }

    /**
     * @return array{
     *     created: bool,
     *     coalesced: bool,
     *     busy: bool,
     *     recent: bool,
     *     push_run: IntegrationPushRun
     * }
     */
    public function retryPushDetailed(IntegrationPushRun $run, ?int $triggeredBy = null): array
    {
        $listing = $run->listing()->with(['store.syncProfile', 'channelProduct', 'product'])->first();
        $context = array_merge($run->request_context_json ?? [], [
            'retry_of' => $run->id,
            'retried_at' => now()->toIso8601String(),
        ]);

        if (!$listing) {
            $retryRun = IntegrationPushRun::create([
                'store_id' => $run->store_id,
                'channel_listing_id' => $run->channel_listing_id,
                'mp_product_id' => $run->mp_product_id,
                'triggered_by' => $triggeredBy,
                'push_type' => $run->push_type,
                'status' => 'queued',
                'target_price' => $run->target_price,
                'target_quantity' => $run->target_quantity,
                'currency' => $run->currency,
                'request_context_json' => $context,
                'attempt_count' => 0,
            ]);

            PushMarketplaceListingUpdateJob::dispatch($retryRun->id);

            return [
                'created' => true,
                'coalesced' => false,
                'busy' => false,
                'recent' => false,
                'push_run' => $retryRun,
            ];
        }

        $result = $run->push_type === 'price'
            ? $this->listingPushService->queuePricePush(
                $listing,
                (float) $run->target_price,
                $context,
                $triggeredBy
            )
            : $this->listingPushService->queueStockPush(
                $listing,
                (int) $run->target_quantity,
                $context,
                $triggeredBy
            );

        return [
            'created' => (bool) $result['created'],
            'coalesced' => (bool) $result['coalesced'],
            'busy' => (bool) $result['busy'],
            'recent' => (bool) $result['recent'],
            'push_run' => $result['push_run'],
        ];
    }

    /**
     * @param  iterable<int, IntegrationPushRun>  $runs
     * @return Collection<int, IntegrationPushRun>
     */
    public function retryPushBatch(iterable $runs, ?int $triggeredBy = null): Collection
    {
        return $this->retryPushBatchDetailed($runs, $triggeredBy)['runs'];
    }

    /**
     * @param  iterable<int, IntegrationPushRun>  $runs
     * @return array{
     *     runs: Collection<int, IntegrationPushRun>,
     *     created: int,
     *     coalesced: int,
     *     busy: int,
     *     recent: int
     * }
     */
    public function retryPushBatchDetailed(iterable $runs, ?int $triggeredBy = null): array
    {
        $results = collect($runs)
            ->map(fn (IntegrationPushRun $run) => $this->retryPushDetailed($run, $triggeredBy))
            ->values();

        return [
            'runs' => $results->pluck('push_run')->values(),
            'created' => $results->where('created', true)->count(),
            'coalesced' => $results->where('coalesced', true)->count(),
            'busy' => $results->where('busy', true)->count(),
            'recent' => $results->where('recent', true)->count(),
        ];
    }

    public function retryOrderAction(IntegrationOrderActionRun $run, ?int $triggeredBy = null): IntegrationOrderActionRun
    {
        $order = $run->order()->with('packages:id,channel_order_id,external_package_id')->firstOrFail();
        $package = $run->channel_order_package_id ? $run->package : null;
        $context = array_merge($run->request_context_json ?? [], [
            'retry_of' => $run->id,
            'retried_at' => now()->toIso8601String(),
        ]);

        return $this->retryOrderActionDetailed($run, $triggeredBy)['action_run'];
    }

    /**
     * @return array{
     *     created: bool,
     *     coalesced: bool,
     *     busy: bool,
     *     recent: bool,
     *     action_run: IntegrationOrderActionRun
     * }
     */
    public function retryOrderActionDetailed(IntegrationOrderActionRun $run, ?int $triggeredBy = null): array
    {
        $order = $run->order()->with('packages:id,channel_order_id,external_package_id')->firstOrFail();
        $package = $run->channel_order_package_id ? $run->package : null;
        $context = array_merge($run->request_context_json ?? [], [
            'retry_of' => $run->id,
            'retried_at' => now()->toIso8601String(),
        ]);

        $result = $this->orderActionService->dispatch(
            $order,
            $run->action_type,
            $context,
            $triggeredBy,
            $package
        );

        return [
            'created' => (bool) $result['created'],
            'coalesced' => (bool) $result['coalesced'],
            'busy' => (bool) $result['busy'],
            'recent' => (bool) $result['recent'],
            'action_run' => $result['action_run'],
        ];
    }

    /**
     * @param  iterable<int, IntegrationOrderActionRun>  $runs
     * @return Collection<int, IntegrationOrderActionRun>
     */
    public function retryOrderActionBatch(iterable $runs, ?int $triggeredBy = null): Collection
    {
        return $this->retryOrderActionBatchDetailed($runs, $triggeredBy)['runs'];
    }

    /**
     * @param  iterable<int, IntegrationOrderActionRun>  $runs
     * @return array{
     *     runs: Collection<int, IntegrationOrderActionRun>,
     *     created: int,
     *     coalesced: int,
     *     busy: int,
     *     recent: int
     * }
     */
    public function retryOrderActionBatchDetailed(iterable $runs, ?int $triggeredBy = null): array
    {
        $results = collect($runs)
            ->map(fn (IntegrationOrderActionRun $run) => $this->retryOrderActionDetailed($run, $triggeredBy))
            ->values();

        return [
            'runs' => $results->pluck('action_run')->values(),
            'created' => $results->where('created', true)->count(),
            'coalesced' => $results->where('coalesced', true)->count(),
            'busy' => $results->where('busy', true)->count(),
            'recent' => $results->where('recent', true)->count(),
        ];
    }

    public function replayWebhook(IntegrationWebhookEvent $event): IntegrationSyncRun
    {
        if (!$event->store_id) {
            throw new \RuntimeException('Bu webhook kaydı herhangi bir mağaza ile eşleşmediği için tekrar işlenemez.');
        }

        $payload = $event->payload_json ?? [];
        $orderNumber = trim((string) (
            data_get($payload, 'orderNumber')
            ?: data_get($payload, 'order.number')
            ?: data_get($payload, 'shipmentPackage.orderNumber')
        ));
        $shipmentPackageId = trim((string) (
            data_get($payload, 'shipmentPackageId')
            ?: data_get($payload, 'shipmentPackage.shipmentPackageId')
            ?: data_get($payload, 'shipmentPackage.id')
            ?: data_get($payload, 'id')
        ));

        $syncRun = IntegrationSyncRun::create([
            'store_id' => $event->store_id,
            'sync_type' => 'orders',
            'trigger_type' => 'webhook_replay',
            'status' => 'queued',
            'notes_json' => [
                'source' => 'webhook_replay',
                'replayed_webhook_event_id' => $event->id,
                'options' => array_filter([
                    'start_date' => optional($event->received_at)->subDays(30)?->toIso8601String(),
                    'end_date' => now()->addDay()->toIso8601String(),
                    'order_number' => $orderNumber !== '' ? $orderNumber : null,
                    'shipment_package_ids' => $shipmentPackageId !== '' ? [$shipmentPackageId] : [],
                ]),
            ],
        ]);

        SyncMarketplaceDataJob::dispatch($syncRun->id);

        $event->update([
            'status' => $event->status === 'failed' ? 'replayed' : $event->status,
            'processed_at' => now(),
        ]);

        return $syncRun;
    }

    /**
     * @param  iterable<int, IntegrationWebhookEvent>  $events
     * @return Collection<int, IntegrationSyncRun>
     */
    public function replayWebhookBatch(iterable $events): Collection
    {
        return collect($events)
            ->map(fn (IntegrationWebhookEvent $event) => $this->replayWebhook($event))
            ->values();
    }
}
