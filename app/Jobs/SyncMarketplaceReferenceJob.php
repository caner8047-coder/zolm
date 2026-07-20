<?php

namespace App\Jobs;

use App\Models\MarketplaceStore;
use App\Services\Marketplace\MarketplaceReferenceSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncMarketplaceReferenceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800;
    public $tries = 3;

    public function __construct(
        public MarketplaceStore $store
    ) {
    }

    public function handle(MarketplaceReferenceSyncService $referenceSyncService): void
    {
        if (!config('marketplace.trendyol.reference_sync_enabled', true)) {
            return;
        }

        $referenceSyncService->syncBrands($this->store);
        $referenceSyncService->syncCategories($this->store);
        $referenceSyncService->syncClaimReasons($this->store);
    }
}
