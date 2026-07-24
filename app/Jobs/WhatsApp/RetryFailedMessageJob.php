<?php

namespace App\Jobs\WhatsApp;

use App\Models\WaOutbox;
use App\Services\WhatsApp\OutboxService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RetryFailedMessageJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $uniqueFor = 86400;

    public function __construct()
    {
        $this->queue = config('whatsapp.queue.outbox', 'default');
    }

    public function uniqueId(): string
    {
        return 'whatsapp-retry-failed';
    }

    public function handle(OutboxService $outboxService): void
    {
        $failedOrStalePending = WaOutbox::query()
            ->where(function ($query) {
                $query->where('status', WaOutbox::STATUS_FAILED)
                    ->orWhere(function ($staleQuery) {
                        $staleQuery->where('status', WaOutbox::STATUS_PROCESSING)
                            ->where('updated_at', '<', now()->subMinutes(5));
                    });
            })
            ->whereColumn('retry_count', '<', 'max_retries')
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
