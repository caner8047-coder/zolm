<?php

namespace App\Listeners\WhatsApp;

use App\Events\ProductStockChanged;
use App\Services\WhatsApp\StockAlertService;

class ProcessStockAlertListener
{
    public function __construct(
        protected StockAlertService $stockAlertService,
    ) {}

    public function handle(ProductStockChanged $event): void
    {
        // 0'dan pozitif değere geçiş → stok geldi
        if ($event->oldQuantity <= 0 && $event->newQuantity > 0) {
            $this->stockAlertService->onStockAvailable(
                $event->product,
                $event->newQuantity
            );
        }
    }
}
