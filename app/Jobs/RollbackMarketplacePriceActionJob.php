<?php

namespace App\Jobs;

use App\Models\MpPriceAction;
use App\Services\Marketplace\MarketplaceBuyboxRecommendationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RollbackMarketplacePriceActionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(public int $originalPriceActionId, public ?int $userId = null)
    {
        $this->onQueue((string) config('marketplace.queues.listing_push', 'default'));
    }

    public function handle(): void
    {
        if (! config('marketplace.trendyol.price_rollback_enabled', false)) {
            Log::warning('[RollbackMarketplacePriceActionJob] Rollback feature flag disabled.');
            return;
        }

        $originalAction = MpPriceAction::with(['store', 'recommendation'])->find($this->originalPriceActionId);

        if (! $originalAction || ! $originalAction->canRollback()) {
            Log::warning('[RollbackMarketplacePriceActionJob] Action cannot be rolled back', [
                'action_id' => $this->originalPriceActionId,
            ]);
            return;
        }

        $rollbackPrice = (float) $originalAction->old_price;

        if ($rollbackPrice <= 0) {
            Log::warning('[RollbackMarketplacePriceActionJob] Invalid old_price for rollback', [
                'action_id' => $originalAction->id,
            ]);
            return;
        }

        // Create new rollback MpPriceAction
        $rollbackAction = MpPriceAction::create([
            'store_id' => $originalAction->store_id,
            'recommendation_id' => $originalAction->recommendation_id,
            'barcode' => $originalAction->barcode,
            'old_price' => $originalAction->confirmed_price ?? $originalAction->requested_price,
            'requested_price' => $rollbackPrice,
            'action_type' => 'rollback',
            'trigger_type' => 'manual',
            'approved_by' => $this->userId ?: $originalAction->approved_by,
            'approved_at' => now(),
            'status' => 'pending',
            'request_payload' => [
                'original_action_id' => $originalAction->id,
                'rollback_price' => $rollbackPrice,
            ],
        ]);

        $originalAction->update(['rolled_back_at' => now()]);

        Log::info('[RollbackMarketplacePriceActionJob] Rollback aksiyonu oluşturuldu ve push sırasına alındı', [
            'original_action_id' => $originalAction->id,
            'rollback_action_id' => $rollbackAction->id,
            'rollback_price' => $rollbackPrice,
        ]);

        PushMarketplacePriceActionJob::dispatch($rollbackAction->id);
    }
}
