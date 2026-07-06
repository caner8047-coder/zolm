<?php

namespace App\Services\WhatsApp;

use App\Models\ChannelOrder;
use App\Models\MarketplaceStore;
use App\Models\WaAutomationConfig;
use App\Models\WaContact;
use App\Models\WaOutbox;
use App\Models\WaTemplate;
use Illuminate\Support\Facades\Log;

class OrderNotificationService
{
    /**
     * Sipariş onayı bildirimi gönder
     */
    public function onOrderConfirmed(ChannelOrder $order, string $oldStatus, string $newStatus): void
    {
        // WC mağazası kontrolü
        if ($order->store?->marketplace !== 'woocommerce') {
            return;
        }

        // Ayarları kontrol et
        $config = WaAutomationConfig::get('order_confirmation', [
            'enabled' => false,
            'allowed_statuses' => ['processing', 'completed', 'on-hold'],
            'template_id' => null,
            'include_order_link' => false,
        ]);

        if (empty($config['enabled'])) {
            return;
        }

        // Durum eşleme kontrolü
        $allowedStatuses = $config['allowed_statuses'] ?? [];
        if (!in_array($newStatus, $allowedStatuses, true)) {
            return;
        }

        // Müşteri telefonu
        $phone = $order->customer_phone;
        if (empty($phone)) {
            return;
        }

        // Contact bul
        $contactResolver = app(ContactResolver::class);
        $contact = $contactResolver->resolve($order->store_id, $phone);
        if (!$contact) {
            return;
        }

        // Eligibility kontrolü
        $eligibleService = app(EligibilityService::class);
        if (!$eligibleService->isEligibleForMessaging($contact, 'order_updates')) {
            return;
        }

        // Idempotency key
        $idempotencyKey = "order_confirmation:{$order->store_id}:{$order->id}";
        $existing = WaOutbox::where('idempotency_key', $idempotencyKey)->exists();
        if ($existing) {
            return;
        }

        // Template seçimi
        $templateId = $config['template_id'] ?? null;
        if (!$templateId) {
            return;
        }

        $template = WaTemplate::where('id', $templateId)->approved()->first();
        if (!$template) {
            return;
        }

        // Hesap
        $account = $order->store->waAccount ?? null;
        if (!$account || !$account->is_active) {
            return;
        }

        // Template parametreleri
        $templateParams = [
            'customer_name' => $contact->first_name ?: 'Değerli müşterimiz',
            'order_number' => $order->order_number ?? $order->external_order_id,
            'order_total' => $this->formatCurrency($order->raw_payload['total'] ?? 0, $order->currency),
            'order_date' => $order->ordered_at ? $order->ordered_at->format('d.m.Y') : '',
        ];

        // Outbox'a yaz
        try {
            app(OutboxService::class)->enqueue(
                contact: $contact,
                messageType: 'template',
                templateName: $template->name,
                templateLanguage: $template->language,
                templateParams: $templateParams,
                priority: 'high',
                automationKey: 'order_confirmation',
                relatedOrderId: $order->id,
                idempotencyKey: $idempotencyKey,
            );

            app(AuditLogService::class)->log(
                'order_confirmation_sent',
                'channel_order',
                $order->id,
                ['status' => $newStatus, 'contact_id' => $contact->id],
            );
        } catch (\Illuminate\Database\QueryException $e) {
            if (($e->errorInfo[1] ?? 0) === 1062) {
                return;
            }
            throw $e;
        }
    }

    private function formatCurrency(float $amount, ?string $currency): string
    {
        $symbol = match ($currency) {
            'TRY', 'TL' => '₺',
            'USD' => '$',
            'EUR' => '€',
            default => '',
        };

        return $symbol . number_format($amount, 2, ',', '.');
    }
}
