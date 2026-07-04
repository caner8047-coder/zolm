<?php

namespace App\Jobs\WhatsApp;

use App\Models\WaWebhookEvent;
use App\Models\WaContactPreference;
use App\Models\WaConsentEvent;
use App\Services\WhatsApp\ContactResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessBoosterEventJob implements ShouldQueue
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
            $eventType = $payload['event_type'] ?? '';

            match ($eventType) {
                'order.created' => $this->handleOrderCreated($payload, $contactResolver),
                'order.communication_preferences_synced' => $this->handlePreferencesSync($payload, $contactResolver),
                'cart.updated' => $this->handleCartUpdated($payload, $contactResolver),
                'cart.contact_captured' => $this->handleCartContactCaptured($payload, $contactResolver),
                default => Log::info('Bilinmeyen Booster event tipi', ['type' => $eventType]),
            };

            $event->update([
                'status' => WaWebhookEvent::STATUS_PROCESSED,
                'processed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Booster event işleme hatası', [
                'webhook_event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);

            $event->update([
                'status' => WaWebhookEvent::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    private function handleOrderCreated(array $payload, ContactResolver $contactResolver): void
    {
        $storeId = $payload['store_id'] ?? null;
        $phone = $payload['customer_phone'] ?? null;

        if (!$storeId || !$phone) {
            return;
        }

        $contact = $contactResolver->resolveOrCreate(
            $storeId,
            $phone,
            $payload['wc_customer_id'] ?? null,
            $payload['customer_name'] ?? null,
        );

        if (!$contact) {
            return;
        }

        // Communication preferences kaydet
        $purposes = $payload['communication_purposes'] ?? [];

        foreach ($purposes as $purpose => $status) {
            if (!in_array($status, ['granted', 'withdrawn'], true)) {
                continue;
            }

            WaContactPreference::updateOrCreate(
                ['contact_id' => $contact->id, 'purpose' => $purpose, 'store_id' => $storeId],
                ['status' => $status]
            );

            WaConsentEvent::create([
                'contact_id' => $contact->id,
                'store_id' => $storeId,
                'purpose' => $purpose,
                'action' => $status,
                'source' => 'checkout',
                'consent_timestamp' => now(),
            ]);
        }
    }

    private function handlePreferencesSync(array $payload, ContactResolver $contactResolver): void
    {
        $storeId = $payload['store_id'] ?? null;
        $phone = $payload['phone'] ?? null;

        if (!$storeId || !$phone) {
            return;
        }

        $contact = $contactResolver->resolve($storeId, $phone);

        if (!$contact) {
            return;
        }

        $orderUpdatesConsent = $payload['order_updates_consent'] ?? null;

        if ($orderUpdatesConsent && in_array($orderUpdatesConsent, ['granted', 'withdrawn'], true)) {
            WaContactPreference::updateOrCreate(
                ['contact_id' => $contact->id, 'purpose' => 'order_updates', 'store_id' => $storeId],
                ['status' => $orderUpdatesConsent]
            );

            WaConsentEvent::create([
                'contact_id' => $contact->id,
                'store_id' => $storeId,
                'purpose' => 'order_updates',
                'action' => $orderUpdatesConsent,
                'source' => 'account_settings',
                'consent_timestamp' => now(),
            ]);
        }
    }

    private function handleCartUpdated(array $payload, ContactResolver $contactResolver): void
    {
        $storeId = (int) ($payload['store_id'] ?? 0);
        if ($storeId <= 0) {
            return;
        }

        $cartRecoveryService = app(\App\Services\WhatsApp\CartRecoveryService::class);
        $cartRecoveryService->onCartUpdated($payload);
    }

    private function handleCartContactCaptured(array $payload, ContactResolver $contactResolver): void
    {
        $storeId = (int) ($payload['store_id'] ?? 0);
        $phone = $payload['phone'] ?? null;

        if ($storeId <= 0 || empty($phone)) {
            return;
        }

        $contactResolver->resolveOrCreate(
            $storeId,
            $phone,
            $payload['wc_customer_id'] ?? null,
        );
    }
}
