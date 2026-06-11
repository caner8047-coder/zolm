<?php

namespace App\Jobs;

use App\Services\Marketplace\MarketplaceOrderActionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunMarketplaceOrderActionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 3;

    /**
     * @var array<int>
     */
    public array $backoff = [30, 120, 300];

    public function __construct(public int $actionRunId)
    {
        $this->onQueue((string) config('marketplace.queues.order_actions', 'default'));
    }

    public function handle(MarketplaceOrderActionService $orderActionService): void
    {
        $orderActionService->run($this->actionRunId, (int) $this->attempts());
    }
}
