<?php

namespace App\Jobs;

use App\Models\MarketplaceStore;
use App\Services\Marketplace\MarketplacePriceCanaryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunTrendyolPriceCanaryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct()
    {
        $this->onQueue((string) config('marketplace.queues.listing_push', 'default'));
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('trendyol-price-canary-cycle'))
                ->releaseAfter(60)
                ->expireAfter(600),
        ];
    }

    public function handle(MarketplacePriceCanaryService $canaryService): void
    {
        if (! config('marketplace.trendyol.automatic_price_actions_enabled', false)
            || ! config('marketplace.trendyol.canary_enabled', false)) {
            return;
        }

        $stores = MarketplaceStore::where('marketplace', 'trendyol')
            ->where('is_active', true)
            ->get();

        Log::info('[RunTrendyolPriceCanaryJob] Auto-Pricing Canary Cycle tetiklendi', [
            'stores_count' => $stores->count(),
        ]);

        foreach ($stores as $store) {
            $canaryService->runCanaryCycle($store);
        }
    }
}
