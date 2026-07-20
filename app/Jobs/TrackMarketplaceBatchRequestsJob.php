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
        if (!config('marketplace.trendyol.batch_tracking_enabled', false)) {
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
                $items = data_get($result, 'items', []);
                $failedItemCount = (int) data_get($result, 'failedItemCount', 0);
                $itemCount = (int) data_get($result, 'itemCount', count($items));
                
                $hasFailures = false;
                $hasSuccesses = false;
                $failedItems = [];

                foreach ($items as $item) {
                    $itemStatus = data_get($item, 'status');
                    if ($itemStatus === 'SUCCESS') {
                        $hasSuccesses = true;
                    } elseif ($itemStatus === 'FAILED' || !empty(data_get($item, 'failureReasons'))) {
                        $hasFailures = true;
                        $failedItems[] = [
                            'barcode' => data_get($item, 'requestItem.barcode') ?? data_get($item, 'barcode'),
                            'item_status' => $itemStatus ?? 'FAILED',
                            'failure_code' => data_get($item, 'failureReasons.0.errorCode') ?? data_get($item, 'failureReasons.0.code'),
                            'failure_message' => data_get($item, 'failureReasons.0.errorMessage') ?? data_get($item, 'failureReasons.0.message') ?? data_get($item, 'failureReason'),
                            'raw_item_payload' => $item
                        ];
                    }
                }

                if (empty($items) && $itemCount > 0) {
                    if ($failedItemCount === 0) {
                        $hasSuccesses = true;
                    } elseif ($failedItemCount === $itemCount) {
                        $hasFailures = true;
                    } else {
                        $hasSuccesses = true;
                        $hasFailures = true;
                    }
                }

                $isTerminal = in_array($status, ['COMPLETED', 'SUCCESS', 'DONE', 'FAILED']);
                
                if ($isTerminal || !empty($items)) {
                    $finalStatus = 'processing';
                    
                    if ($hasSuccesses && !$hasFailures) {
                        $finalStatus = 'success';
                    } elseif ($hasSuccesses && $hasFailures) {
                        $finalStatus = 'partial_success';
                    } elseif (!$hasSuccesses && $hasFailures) {
                        $finalStatus = 'failed';
                    } elseif ($status === 'FAILED') {
                        $finalStatus = 'failed';
                    } elseif (in_array($status, ['COMPLETED', 'SUCCESS', 'DONE'])) {
                        $finalStatus = 'success';
                    }
                    
                    if ($finalStatus !== 'processing') {
                        $run->update([
                            'status' => $finalStatus,
                            'response_json' => [
                                'raw_response' => $result,
                                'failed_items' => $failedItems
                            ],
                            'error_message' => empty($failedItems) ? ($finalStatus === 'failed' ? 'Batch tamamen başarısız.' : null) : 'Bazı kalemler başarısız oldu.',
                            'finished_at' => Carbon::now(),
                        ]);
                    } else {
                        $run->increment('attempt_count');
                    }
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
