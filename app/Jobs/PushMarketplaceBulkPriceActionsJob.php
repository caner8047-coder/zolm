<?php

namespace App\Jobs;

use App\Models\MpPriceAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PushMarketplaceBulkPriceActionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    /**
     * @param array<int> $priceActionIds
     */
    public function __construct(public array $priceActionIds)
    {
        $this->onQueue((string) config('marketplace.queues.listing_push', 'default'));
    }

    public function handle(): void
    {
        // Guard feature flag
        if (! config('marketplace.trendyol.bulk_price_actions_enabled', false)) {
            Log::warning('[PushMarketplaceBulkPriceActionsJob] Bulk price actions flag disabled.');
            return;
        }

        $actions = MpPriceAction::whereIn('id', $this->priceActionIds)
            ->whereIn('status', ['pending', 'queued'])
            ->get();

        Log::info('[PushMarketplaceBulkPriceActionsJob] Toplu fiyat aksiyonu başlatılıyor', [
            'count' => $actions->count(),
            'action_ids' => $actions->pluck('id')->all(),
        ]);

        foreach ($actions as $action) {
            PushMarketplacePriceActionJob::dispatch($action->id);
        }
    }
}
