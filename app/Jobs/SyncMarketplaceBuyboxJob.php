<?php

namespace App\Jobs;

use App\Models\MarketplaceStore;
use App\Services\Marketplace\MarketplaceBuyboxSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncMarketplaceBuyboxJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 900;
    public $tries = 3;
    public int $uniqueFor = 86400;

    public function __construct(
        public MarketplaceStore $store
    ) {
        $this->onQueue((string) config('marketplace.queues.maintenance', 'default'));
    }

    public function uniqueId(): string
    {
        return 'marketplace-buybox:'.$this->store->getKey();
    }

    public function handle(MarketplaceBuyboxSyncService $buyboxSyncService): void
    {
        if (!config('marketplace.trendyol.buybox_sync_enabled', true)) {
            return;
        }

        $buyboxSyncService->sync($this->store);
    }
}
