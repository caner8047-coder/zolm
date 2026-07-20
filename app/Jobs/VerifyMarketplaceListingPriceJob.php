<?php

namespace App\Jobs;

use App\Models\MpPriceAction;
use App\Services\Marketplace\MarketplaceListingPriceVerificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class VerifyMarketplaceListingPriceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function __construct(public int $priceActionId)
    {
        $this->onQueue((string) config('marketplace.queues.listing_push', 'default'));
    }

    public function handle(MarketplaceListingPriceVerificationService $verificationService): void
    {
        $action = MpPriceAction::find($this->priceActionId);

        if (! $action) {
            return;
        }

        $verificationService->verifyActionPrice($action);
    }
}
