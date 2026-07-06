<?php

namespace App\Jobs\WhatsApp;

use App\Models\WaWebhookEvent;
use App\Models\WaMessageDelivery;
use App\Models\WaOutbox;
use App\Models\WaConversation;
use App\Models\WaContact;
use App\Models\WaContactPreference;
use App\Models\WaConsentEvent;
use App\Models\WaInboundMessage;
use App\Models\WaSuppression;
use App\Services\WhatsApp\ContactResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessMetaWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public int $webhookEventId,
    ) {
        $this->queue = config('whatsapp.queue.webhook', 'default');
    }

    public function handle(ContactResolver $contactResolver): void
    {
        $event = WaWebhookEvent::find($this->webhookEventId);

        if (!$event || $event->status !== WaWebhookEvent::STATUS_PENDING) {
            return;
        }

        try {
            $payload = $event->payload;

            if ($event->event_type === 'status') {
                $this->handleStatusUpdate($payload);
            } elseif ($event->event_type === 'message') {
                $this->handleInboundMessage($payload, $contactResolver);
            }

            $event->update([
                'status' => WaWebhookEvent::STATUS_PROCESSED,
                'processed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Meta webhook işleme hatası', [
                'webhook_event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);

            $event->update([
                'status' => WaWebhookEvent::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    private function handleStatusUpdate(array $payload): void
    {
        $messageId = $payload['message_id'] ?? $payload['id'] ?? null;
        $newStatus = $payload['status'] ?? null;

        if (!$messageId || !$newStatus) {
            return;
        }

        $delivery = WaMessageDelivery::where('meta_message_id', $messageId)->first();

        if (!$delivery) {
            Log::info('Meta status update: eşleşen delivery bulunamadı', ['message_id' => $messageId]);
            return;
        }

        $newStatusMapped = match ($newStatus) {
            'sent' => 'sent',
            'delivered' => 'delivered',
            'read' => 'read',
            'failed' => 'failed',
            default => null,
        };

        if (!$newStatusMapped) {
            return;
        }

        $currentStatus = $delivery->status;
        $statusOrder = ['sent' => 0, 'delivered' => 1, 'read' => 2, 'failed' => -1];

        if (($statusOrder[$newStatusMapped] ?? 0) <= ($statusOrder[$currentStatus] ?? 0)) {
            // Status geriye düşmez (read → delivered olmaz)
            return;
        }

        $updates = ['status' => $newStatusMapped];

        if ($newStatusMapped === 'delivered') {
            $updates['delivered_at'] = now();
        } elseif ($newStatusMapped === 'read') {
            $updates['read_at'] = now();
        } elseif ($newStatusMapped === 'failed') {
            $updates['error_code'] = $payload['errors'][0]['code'] ?? null;
            $updates['error_message'] = $payload['errors'][0]['message'] ?? null;
            $updates['error_classification'] = $this->classifyDeliveryError($payload);
        }

        $delivery->update($updates);

        // Outbox status güncelle
        $outbox = $delivery->outbox;
        if ($outbox && $newStatusMapped !== 'failed') {
            $outboxService = app(\App\Services\WhatsApp\OutboxService::class);
            $outboxService->updateDeliveryStatus($outbox, $newStatusMapped);
        }
    }

    private function handleInboundMessage(array $payload, ContactResolver $contactResolver): void
    {
        $messageId = $payload['id'] ?? null;
        $from = $payload['from'] ?? null;

        if (!$messageId || !$from) {
            return;
        }

        // Meta'dan gelen phone_number_id ile account ve store çöz
        $phone_number_id = $payload['metadata']['phone_number_id'] ?? null;
        if (!$phone_number_id) {
            Log::warning('Meta inbound: phone_number_id bulunamadı');
            return;
        }

        $account = \App\Models\WaAccount::where('phone_number_id', $phone_number_id)
            ->where('is_active', true)
            ->first();

        if (!$account || !$account->store) {
            Log::warning('Meta inbound: bilinmeyen phone_number_id', ['phone_number_id' => $phone_number_id]);
            return;
        }

        $contact = $contactResolver->resolveOrCreate(
            $account->store_id,
            $from,
            null,
            data_get($payload, 'contacts.0.profile.name') ?? data_get($payload, 'contact.profile.name'),
        );

        if (!$contact) {
            return;
        }

        $conversation = WaConversation::firstOrCreate(
            ['contact_id' => $contact->id, 'store_id' => $account->store_id],
            ['status' => 'open']
        );

        $body = data_get($payload, 'text.body');

        WaInboundMessage::firstOrCreate(
            ['meta_message_id' => $messageId],
            [
                'conversation_id' => $conversation->id,
                'contact_id' => $contact->id,
                'message_type' => $payload['type'] ?? 'text',
                'body' => $body,
                'payload_json' => $payload,
                'received_at' => now(),
            ]
        );

        $conversation->update(['last_message_at' => now()]);
        $contact->update(['last_seen_at' => now()]);

        $this->handleOptOutKeyword($contact, $body);
    }

    private function handleOptOutKeyword(WaContact $contact, ?string $body): void
    {
        $keyword = mb_strtoupper(trim((string) $body), 'UTF-8');

        if (! in_array($keyword, ['STOP', 'DUR', 'IPTAL', 'İPTAL', 'VAZGEC', 'VAZGEÇ'], true)) {
            return;
        }

        $suppressionExists = WaSuppression::active()
            ->where('contact_id', $contact->id)
            ->where('reason', 'opted_out')
            ->exists();

        if (! $suppressionExists) {
            WaSuppression::create([
                'contact_id' => $contact->id,
                'reason' => 'opted_out',
                'details' => 'WhatsApp anahtar kelime ile abonelikten çıkıldı: ' . $keyword,
                'suppressed_at' => now(),
            ]);
        }

        foreach (['marketing', 'cart_recovery', 'stock_alert', 'birthday'] as $purpose) {
            WaContactPreference::updateOrCreate(
                ['contact_id' => $contact->id, 'store_id' => $contact->store_id, 'purpose' => $purpose],
                ['status' => 'withdrawn']
            );

            WaConsentEvent::create([
                'contact_id' => $contact->id,
                'store_id' => $contact->store_id,
                'purpose' => $purpose,
                'action' => 'withdrawn',
                'source' => 'whatsapp_keyword',
                'consent_timestamp' => now(),
            ]);
        }
    }

    private function classifyDeliveryError(array $payload): string
    {
        $errorCode = $payload['errors'][0]['code'] ?? 0;

        return match (true) {
            $errorCode === 131047 => 'rate_limit',
            $errorCode === 131051 => 'template_rejected',
            $errorCode === 131026 => 'invalid_phone',
            default => 'unknown',
        };
    }
}
