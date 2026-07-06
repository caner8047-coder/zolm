<?php

namespace App\Services\WhatsApp;

use App\Models\ChannelClaim;
use App\Models\ChannelOrder;
use App\Models\WaAutomationConfig;
use App\Models\WaOutbox;
use App\Models\WaTemplate;
use Illuminate\Support\Facades\Log;

class ReturnNotificationService
{
    private const STATUS_MAP = [
        'pending' => 'return_requested',
        'delivered' => 'return_received',
        'approved' => 'return_approved',
        'rejected' => 'return_rejected',
        'unresolved' => 'return_needs_info',
    ];

    /**
     * İade durumu değiştiğinde bildirim gönder
     */
    public function onReturnStatusChanged(ChannelClaim $claim, string $oldStatus, string $newStatus): void
    {
        // WC mağazası kontrolü
        if ($claim->store?->marketplace !== 'woocommerce') {
            return;
        }

        // Mapping kontrolü
        $stage = self::STATUS_MAP[$newStatus] ?? null;
        if (!$stage) {
            return;
        }

        // Ayarları kontrol et
        $config = WaAutomationConfig::get('returns', [
            'enabled' => false,
            'stages' => [
                'return_requested' => ['enabled' => true, 'template_id' => null],
                'return_received' => ['enabled' => true, 'template_id' => null],
                'return_approved' => ['enabled' => true, 'template_id' => null],
                'return_rejected' => ['enabled' => false, 'template_id' => null],
                'return_needs_info' => ['enabled' => false, 'template_id' => null],
            ],
        ]);

        if (empty($config['enabled'])) {
            return;
        }

        $stageConfig = $config['stages'][$stage] ?? null;
        if (!$stageConfig || empty($stageConfig['enabled'])) {
            return;
        }

        // İade siparişle ilişkili mi?
        $order = $this->findLinkedOrder($claim);
        if (!$order || $order->store?->marketplace !== 'woocommerce') {
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

        // Eligibility
        $eligibleService = app(EligibilityService::class);
        if (!$eligibleService->isEligibleForMessaging($contact, 'order_updates')) {
            return;
        }

        // Idempotency
        $idempotencyKey = "return:{$claim->store_id}:{$claim->id}:{$stage}";
        $existing = WaOutbox::where('idempotency_key', $idempotencyKey)->exists();
        if ($existing) {
            return;
        }

        // Template
        $templateId = $stageConfig['template_id'] ?? null;
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
        $stageLabel = match ($stage) {
            'return_requested' => 'Talebiniz alındı',
            'return_received' => 'Ürününüz teslim alındı',
            'return_approved' => 'İadeniz onaylandı',
            'return_rejected' => 'İadeniz değerlendirildi',
            'return_needs_info' => 'Ek bilgi gerekiyor',
            default => '',
        };

        $templateParams = [
            'customer_name' => $contact->first_name ?: 'Değerli müşterimiz',
            'order_number' => $order->order_number ?? $order->external_order_id,
            'status_text' => $stageLabel,
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
                automationKey: 'return_notification',
                relatedOrderId: $order->id,
                idempotencyKey: $idempotencyKey,
            );

            app(AuditLogService::class)->log(
                'return_notification_sent',
                'channel_claim',
                $claim->id,
                ['stage' => $stage, 'old_status' => $oldStatus, 'new_status' => $newStatus],
            );
        } catch (\Illuminate\Database\QueryException $e) {
            if (($e->errorInfo[1] ?? 0) === 1062) {
                return;
            }
            throw $e;
        }
    }

    /**
     * ChannelClaim'i ilgili ChannelOrder'a eşle
     */
    private function findLinkedOrder(ChannelClaim $claim): ?ChannelOrder
    {
        // ChannelClaim_items üzerinden sipariş eşleme
        $firstItem = $claim->items()->first();
        if (!$firstItem) {
            return null;
        }

        // external_order_id veya order_number ile eşle
        $externalOrderId = $firstItem->external_order_id ?? null;
        if (!$externalOrderId) {
            return null;
        }

        return ChannelOrder::where('store_id', $claim->store_id)
            ->where('external_order_id', $externalOrderId)
            ->first();
    }
}
