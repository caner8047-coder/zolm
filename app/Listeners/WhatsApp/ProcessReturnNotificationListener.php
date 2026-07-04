<?php

namespace App\Listeners\WhatsApp;

use App\Events\ReturnStatusChanged;
use App\Services\WhatsApp\ReturnNotificationService;

class ProcessReturnNotificationListener
{
    public function __construct(
        protected ReturnNotificationService $returnNotificationService,
    ) {}

    public function handle(ReturnStatusChanged $event): void
    {
        $this->returnNotificationService->onReturnStatusChanged(
            $event->claim,
            $event->oldStatus,
            $event->newStatus,
        );
    }
}
