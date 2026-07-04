<?php

namespace App\Jobs\WhatsApp;

use App\Models\WaOutbox;
use App\Services\WhatsApp\OutboxService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RetryFailedMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct()
    {
        $this->queue = config('whatsapp.queue.outbox', 'default');
    }

    public function handle(OutboxService $outboxService): void
    {
        $failedOrStalePending = WaOutbox::query()
            ->where('status', WaOutbox::STATUS_FAILED)
            ->orWhere(function ($q) {
                $q->where('status', WaOutbox::STATUS_PENDING)
                    ->where('updated_at', '<', now()->subMinutes(5));
            })
            ->where('retry_count', '<', \DB::raw('max_retries'))
            ->where(function ($q) {
                $q->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            })
            ->limit(50)
            ->get();

        foreach ($failedOrStalePending as $outbox) {
            $outbox->update([
                'status' => WaOutbox::STATUS_QUEUED,
                'next_retry_at' => null,
                'error_message' => null,
            ]);

            SendWaMessageJob::dispatch($outbox->id);
        }
    }
}
