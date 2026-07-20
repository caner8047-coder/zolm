<?php

namespace App\Jobs;

use App\Models\IntegrationPushRun;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\MarketplaceConnectorManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class TrackMarketplaceBatchRequestsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;

    public function handle(MarketplaceConnectorManager $connectorManager): void
    {
        if (!config('marketplace.trendyol.batch_tracking_enabled', true)) {
            return;
        }

        $pendingRuns = IntegrationPushRun::whereNotNull('external_batch_id')
            ->whereIn('status', ['pending', 'processing'])
            ->get();

        foreach ($pendingRuns as $run) {
            if ($run->attempt_count > 10) {
                $run->update(['status' => 'expired']);
                continue;
            }

            $store = MarketplaceStore::find($run->store_id);
            if (!$store) continue;

            $connector = $connectorManager->resolveForStore($store);
            if (!method_exists($connector, 'checkBatchRequestResult')) {
                continue;
            }

            try {
                $result = $connector->checkBatchRequestResult($store, $run->external_batch_id);
                $status = data_get($result, 'status') ?? data_get($result, 'batchRequestStatus');

                if ($status === 'COMPLETED' || $status === 'SUCCESS' || $status === 'DONE') {
                    $run->update([
                        'status' => 'success',
                        'response_json' => $result,
                        'finished_at' => Carbon::now(),
                    ]);
                } elseif ($status === 'FAILED') {
                    $run->update([
                        'status' => 'failed',
                        'error_message' => 'Batch failed remotely',
                        'response_json' => $result,
                        'finished_at' => Carbon::now(),
                    ]);
                } else {
                    $run->increment('attempt_count');
                }
            } catch (\Throwable $e) {
                $run->increment('attempt_count');
                report($e);
            }
        }
    }
}
