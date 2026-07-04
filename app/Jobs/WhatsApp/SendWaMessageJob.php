<?php

namespace App\Jobs\WhatsApp;

use App\Models\WaOutbox;
use App\Models\WaMessageDelivery;
use App\Services\WhatsApp\MetaCloudApiService;
use App\Services\WhatsApp\OutboxService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWaMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $backoff = 60;

    public function __construct(
        public int $outboxId,
    ) {
        $this->queue = config('whatsapp.queue.outbox', 'default');
    }

    public function handle(
        MetaCloudApiService $metaApi,
        OutboxService $outboxService,
    ): void {
        $outbox = WaOutbox::find($this->outboxId);

        if (!$outbox || $outbox->status !== WaOutbox::STATUS_QUEUED) {
            return;
        }

        if (!$outboxService->claimForProcessing($outbox)) {
            return;
        }

        $account = $outbox->store->connection?->waAccount;
        if (!$account || !$account->is_active) {
            $outboxService->markFailed($outbox, 'WhatsApp hesabı bulunamadı veya pasif', 'account_inactive', false);
            return;
        }

        try {
            $phoneNumber = $outbox->contact->phone_e164_encrypted;

            if ($outbox->message_type === 'template' && $outbox->template_name) {
                $result = $metaApi->sendTemplateMessage(
                    $account,
                    $phoneNumber,
                    $outbox->template_name,
                    $outbox->template_language ?? 'tr',
                    $outbox->template_params_json ?? [],
                );
            } else {
                // Sprint 1'de sadece template mesajları desteklenir
                $outboxService->markFailed($outbox, 'Sprint 1de sadece template mesajları desteklenir', 'unsupported_message_type', false);
                return;
            }

            $metaMessageId = $result['messages'][0]['id'] ?? null;

            if ($metaMessageId) {
                $outboxService->markSent($outbox, $metaMessageId);

                WaMessageDelivery::create([
                    'outbox_id' => $outbox->id,
                    'meta_message_id' => $metaMessageId,
                    'provider_event_key' => $metaMessageId,
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
            } else {
                $outboxService->markFailed($outbox, 'Meta yanıtında message_id bulunamadı', 'no_message_id');
            }
        } catch (\RuntimeException $e) {
            $errorCode = $this->classifyMetaError($e->getMessage());
            $shouldRetry = !in_array($errorCode, ['template_rejected', 'invalid_phone', 'unsupported_message_type']);

            Log::warning('WhatsApp mesaj gönderim hatası', [
                'outbox_id' => $outbox->id,
                'error' => $e->getMessage(),
                'error_code' => $errorCode,
                'should_retry' => $shouldRetry,
            ]);

            $outboxService->markFailed($outbox, $e->getMessage(), $errorCode, $shouldRetry);
        }
    }

    private function classifyMetaError(string $message): string
    {
        $message = strtolower($message);

        if (str_contains($message, 'rate limit') || str_contains($message, '429')) {
            return 'rate_limit';
        }
        if (str_contains($message, 'template') && str_contains($message, 'reject')) {
            return 'template_rejected';
        }
        if (str_contains($message, 'invalid') && str_contains($message, 'phone')) {
            return 'invalid_phone';
        }
        if (str_contains($message, 'unsupported')) {
            return 'unsupported_message_type';
        }

        return 'unknown';
    }
}
