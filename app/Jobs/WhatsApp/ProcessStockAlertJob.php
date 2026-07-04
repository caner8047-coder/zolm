<?php

namespace App\Jobs\WhatsApp;

use App\Models\MpProduct;
use App\Services\WhatsApp\StockAlertService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessStockAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public int $mpProductId,
        public int $stockQuantity,
    ) {
        $this->queue = config('whatsapp.queue.outbox', 'default');
    }

    public function handle(StockAlertService $service): void
    {
        $product = MpProduct::find($this->mpProductId);
        if (!$product) {
            return;
        }

        $service->onStockAvailable($product, $this->stockQuantity);
    }
}
