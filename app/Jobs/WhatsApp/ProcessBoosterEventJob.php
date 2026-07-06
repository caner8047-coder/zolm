<?php

namespace App\Jobs\WhatsApp;

use App\Models\WaWebhookEvent;
use App\Models\WaContactPreference;
use App\Models\WaConsentEvent;
use App\Models\WaCoupon;
use App\Models\WaStockWaitlist;
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
                'order.status_changed' => $this->handleOrderStatusChanged($payload, $contactResolver),
                'customer.created' => $this->handleCustomerCreated($payload, $contactResolver),
                'customer.phone_changed' => $this->handleCustomerPhoneChanged($payload, $contactResolver),
                'customer.consent_changed' => $this->handleConsentChanged($payload, $contactResolver),
                'order.communication_preferences_synced' => $this->handlePreferencesSync($payload, $contactResolver),
                'cart.updated' => $this->handleCartUpdated($payload, $contactResolver),
                'cart.contact_captured' => $this->handleCartContactCaptured($payload, $contactResolver),
                'stock.waitlist.created' => $this->handleStockWaitlistCreated($payload, $contactResolver),
                'coupon.created' => $this->handleCouponCreated($payload),
                'coupon.redeemed' => $this->handleCouponRedeemed($payload),
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

    private function handleOrderStatusChanged(array $payload, ContactResolver $contactResolver): void
    {
        $storeId = $payload['store_id'] ?? null;
        $phone = $payload['customer_phone'] ?? null;

        if (! $storeId || ! $phone) {
            return;
        }

        $contactResolver->resolveOrCreate((int) $storeId, $phone);
    }

    private function handleCustomerCreated(array $payload, ContactResolver $contactResolver): void
    {
        $storeId = $payload['store_id'] ?? null;
        $phone = $payload['customer_phone'] ?? $payload['phone'] ?? null;

        if (! $storeId || ! $phone) {
            return;
        }

        $contact = $contactResolver->resolveOrCreate(
            (int) $storeId,
            $phone,
            $payload['wc_customer_id'] ?? null,
            $payload['customer_name'] ?? null,
        );

        if (! $contact) {
            return;
        }

        $this->syncPurposePreferences($contact->id, (int) $storeId, $payload['communication_purposes'] ?? [], 'registration');
    }

    private function handleCustomerPhoneChanged(array $payload, ContactResolver $contactResolver): void
    {
        $storeId = $payload['store_id'] ?? null;
        $phone = $payload['new_phone'] ?? $payload['customer_phone'] ?? $payload['phone'] ?? null;

        if (! $storeId || ! $phone) {
            return;
        }

        $contactResolver->resolveOrCreate(
            (int) $storeId,
            $phone,
            $payload['wc_customer_id'] ?? null,
            $payload['customer_name'] ?? null,
        );
    }

    private function handleConsentChanged(array $payload, ContactResolver $contactResolver): void
    {
        $storeId = $payload['store_id'] ?? null;
        $phone = $payload['phone'] ?? $payload['customer_phone'] ?? null;

        if (! $storeId || ! $phone) {
            return;
        }

        $contact = $contactResolver->resolveOrCreate((int) $storeId, $phone, $payload['wc_customer_id'] ?? null);
        if (! $contact) {
            return;
        }

        $purposes = $payload['communication_purposes'] ?? $payload['purposes'] ?? [];
        if (isset($payload['order_updates_consent'])) {
            $purposes['order_updates'] = $payload['order_updates_consent'];
        }
        if (isset($payload['marketing_consent'])) {
            $purposes['marketing'] = $payload['marketing_consent'];
        }

        $this->syncPurposePreferences($contact->id, (int) $storeId, $purposes, $payload['consent_source'] ?? 'account_settings');
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

    private function handleStockWaitlistCreated(array $payload, ContactResolver $contactResolver): void
    {
        $storeId = (int) ($payload['store_id'] ?? 0);
        $phone = $payload['phone'] ?? null;
        $productId = (int) ($payload['product_id'] ?? 0);
        $variationId = (int) ($payload['variation_id'] ?? 0);

        if ($storeId <= 0 || empty($phone) || $productId <= 0) {
            return;
        }

        $contact = $contactResolver->resolveOrCreate($storeId, $phone);
        if (! $contact) {
            return;
        }

        if (($payload['stock_alert_consent'] ?? 'granted') === 'granted') {
            $this->syncPurposePreferences($contact->id, $storeId, ['stock_alert' => 'granted'], 'stock_notify_form');
        }

        WaStockWaitlist::firstOrCreate(
            [
                'store_id' => $storeId,
                'contact_id' => $contact->id,
                'wc_product_id' => $productId,
                'wc_variation_id' => $variationId ?: null,
                'status' => WaStockWaitlist::STATUS_WAITING,
            ],
            [
                'product_id' => $productId,
                'variation_id' => $variationId ?: null,
                'requested_at' => now(),
            ]
        );
    }

    private function handleCouponCreated(array $payload): void
    {
        $idempotencyKey = $payload['idempotency_key'] ?? null;
        if (! $idempotencyKey) {
            return;
        }

        $updates = array_filter([
            'wc_coupon_id' => $payload['coupon_id'] ?? null,
            'code' => $payload['coupon_code'] ?? $payload['code'] ?? null,
        ], fn ($value) => $value !== null);

        if ($updates !== []) {
            WaCoupon::where('idempotency_key', $idempotencyKey)->update($updates);
        }
    }

    private function handleCouponRedeemed(array $payload): void
    {
        $code = $payload['coupon_code'] ?? $payload['code'] ?? null;
        $storeId = $payload['store_id'] ?? null;

        if (! $code || ! $storeId) {
            return;
        }

        WaCoupon::where('store_id', $storeId)
            ->where('code', $code)
            ->whereNull('used_at')
            ->update([
                'used_at' => now(),
                'related_order_id' => $payload['related_order_id'] ?? null,
            ]);
    }

    private function syncPurposePreferences(int $contactId, int $storeId, array $purposes, string $source): void
    {
        foreach ($purposes as $purpose => $status) {
            if (! in_array($status, ['granted', 'withdrawn'], true)) {
                continue;
            }

            WaContactPreference::updateOrCreate(
                ['contact_id' => $contactId, 'purpose' => $purpose, 'store_id' => $storeId],
                ['status' => $status]
            );

            WaConsentEvent::create([
                'contact_id' => $contactId,
                'store_id' => $storeId,
                'purpose' => $purpose,
                'action' => $status,
                'source' => $source,
                'consent_timestamp' => now(),
            ]);
        }
    }
}
