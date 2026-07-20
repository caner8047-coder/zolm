<?php

namespace App\Jobs;

use App\Models\MarketplaceStore;
use App\Services\Marketplace\MarketplaceCargoInvoiceSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncMarketplaceCargoInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800;
    public $tries = 3;

    public function __construct(
        public MarketplaceStore $store
    ) {
    }

    public function handle(MarketplaceCargoInvoiceSyncService $cargoInvoiceSyncService): void
    {
        if (!config('marketplace.trendyol.cargo_invoice_sync_enabled', true)) {
            return;
        }

        $cargoInvoiceSyncService->sync($this->store);
    }
}
