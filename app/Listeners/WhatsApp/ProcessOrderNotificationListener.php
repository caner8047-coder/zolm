<?php

namespace App\Listeners\WhatsApp;

use App\Events\OrderStatusChanged;
use App\Services\WhatsApp\OrderNotificationService;

class ProcessOrderNotificationListener
{
    public function __construct(
        protected OrderNotificationService $orderNotificationService,
    ) {}

    public function handle(OrderStatusChanged $event): void
    {
        $this->orderNotificationService->onOrderConfirmed(
            $event->order,
            $event->oldStatus,
            $event->newStatus,
        );
    }
}
