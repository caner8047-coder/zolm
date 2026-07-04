<?php

namespace App\Services\WhatsApp;

use App\Models\Shipment;
use App\Models\WaContact;
use App\Models\WaTemplate;
use App\Models\WaOutbox;
use Illuminate\Support\Facades\Log;

class ShippingNotificationService
{
    private const STAGE_MAP = [
        'shipped' => 'shipped',
        'out_for_delivery' => 'out_for_delivery',
        'delivered' => 'delivered',
    ];

    public function onShipmentStatusChanged(
        Shipment $shipment,
        WaContact $contact,
        string $oldStatus,
        string $newStatus,
    ): void {
        $stage = self::STAGE_MAP[$newStatus] ?? null;

        if ($stage === null) {
            return;
        }

        // Kargo ayarlarını oku
        $enabled = (bool) \App\Models\WaSetting::get('shipping.enabled', config('whatsapp.shipping.enabled', true));
        if (!$enabled) {
            return;
        }

        $allowedStages = \App\Models\WaSetting::get('shipping.stages', config('whatsapp.shipping.stages', []));
        if (!in_array($stage, $allowedStages, true)) {
            return;
        }

        // Idempotency key: stage bazlı (tracking_number içermiyor)
        $idempotencyKey = "shipping:{$shipment->store_id}:{$shipment->channel_order_id}:{$stage}";

        // Zaten gönderilmiş mi kontrol
        $existing = WaOutbox::where('idempotency_key', $idempotencyKey)->exists();
        if ($existing) {
            return;
        }

        // Uygun template seç
        $template = $this->resolveTemplate($shipment, $stage);
        if (!$template) {
            Log::warning('Kargo bildirimi: uygun template bulunamadı', [
                'store_id' => $shipment->store_id,
                'stage' => $stage,
            ]);
            return;
        }

        // Takip bilgileri
        $trackingNumber = $shipment->tracking_number;
        $carrierName = $shipment->carrier_name ?? 'Kargo';
        $trackingLink = $this->buildTrackingLink($shipment);

        // Template parametreleri
        $templateParams = [
            'takip_no' => $trackingNumber ?? '',
            'kargo_firmasi' => $carrierName,
            'takip_linki' => $trackingLink,
        ];

        // Outbox'a ekle
        try {
            $outboxService = app(OutboxService::class);
            $outboxService->enqueue(
                contact: $contact,
                messageType: 'template',
                templateName: $template->name,
                templateLanguage: $template->language,
                templateParams: $templateParams,
                priority: 'high',
                automationKey: 'shipping_notification',
                relatedOrderId: $shipment->channel_order_id,
            );

            app(AuditLogService::class)->log(
                'shipping_notification_queued',
                'shipment',
                $shipment->id,
                ['stage' => $stage, 'template' => $template->name, 'tracking_number' => $trackingNumber],
            );
        } catch (\Illuminate\Database\QueryException $e) {
            // Duplicate idempotency_key — sessizce yut
            if ($e->errorInfo[1] ?? 0 === 1062) {
                return;
            }
            throw $e;
        }
    }

    private function resolveTemplate(Shipment $shipment, string $stage): ?WaTemplate
    {
        // WaSettings'den template ID'si oku
        $templateIds = \App\Models\WaSetting::get(
            'shipping.template_ids',
            config('whatsapp.shipping.template_ids', [])
        );

        $templateId = $templateIds[$stage] ?? null;

        if ($templateId) {
            return WaTemplate::where('id', $templateId)
                ->approved()
                ->first();
        }

        // Fallback: stage adına göre template ara
        $templateNameMap = [
            'shipped' => 'kargoya_verildi',
            'out_for_delivery' => 'dagitimda',
            'delivered' => 'teslim_edildi',
        ];

        $templateName = $templateNameMap[$stage] ?? null;

        if (!$templateName) {
            return null;
        }

        // Store'a ait aktif account'tan template bul
        $account = \App\Models\WaAccount::where('store_id', $shipment->store_id)
            ->active()
            ->first();

        if (!$account) {
            return null;
        }

        return WaTemplate::forAccount($account)
            ->approved()
            ->where('name', $templateName)
            ->first();
    }

    private function buildTrackingLink(Shipment $shipment): string
    {
        // Carrier config/service üzerinden (hard-code YOK)
        $carrierCode = $shipment->carrier_code ?? 'surat';
        $trackingNumber = $shipment->tracking_number ?? '';

        // Mevcut carrier connector pattern'ini kullan
        $trackingUrls = [
            'surat' => 'https://www.suratkargo.com.tr/siparis-takip?kodu=' . urlencode($trackingNumber),
            'mng' => 'https://www.mngkargo.com.tr/gonderi-sorgula?tracking=' . urlencode($trackingNumber),
            'yurtici' => 'https://www.yurticikargo.com/gonderi-sorgulama?code=' . urlencode($trackingNumber),
            'aras' => 'https://www.araskargo.com.tr/gonderi-sorgulama?code=' . urlencode($trackingNumber),
        ];

        return $trackingUrls[$carrierCode] ?? '#';
    }
}
