<?php

namespace App\Jobs\WhatsApp;

use App\Services\WhatsApp\CartRecoveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessCartRecoveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct()
    {
        $this->queue = config('whatsapp.queue.outbox', 'default');
    }

    public function handle(CartRecoveryService $service): void
    {
        $processed = $service->processPendingRecoveries();

        if ($processed > 0) {
            \Illuminate\Support\Facades\Log::info("Cart recovery: {$processed} mesaj işlendi");
        }
    }
}
