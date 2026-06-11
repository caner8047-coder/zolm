<?php

namespace App\Services\Marketplace\Connectors;

use App\Models\IntegrationConnection;
use App\Services\Marketplace\Contracts\MarketplaceConnector;
use App\Services\Marketplace\Contracts\ReceivesWebhooks;
use App\Services\Marketplace\Contracts\TestsConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

abstract class AbstractMarketplaceConnector implements MarketplaceConnector, ReceivesWebhooks, TestsConnection
{
    public function defaultApiBaseUrl(): ?string
    {
        return null;
    }

    /**
     * @return array<string, bool>
     */
    public function capabilities(): array
    {
        return [
            'orders' => false,
            'products' => false,
            'finance' => false,
            'webhooks' => false,
            'price_push' => false,
            'stock_push' => false,
            'package_status' => false,
            'package_picking' => false,
            'package_invoiced' => false,
            'common_label' => false,
            'package_common_label_create' => false,
            'package_common_label_get' => false,
            'invoice_link' => false,
            'package_invoice_link' => false,
            'questions' => false,
            'question_answer' => false,
            'claims' => false,
            'claim_approve' => false,
            'claim_reject' => false,
        ];
    }

    public function verifyWebhookSignature(Request $request, ?IntegrationConnection $connection): bool
    {
        if (!$connection || blank($connection->webhook_secret)) {
            return false;
        }

        $providedSignature = (string) (
            $request->header('X-Webhook-Signature')
            ?: $request->header('X-Signature')
            ?: $request->input('signature')
        );

        if ($providedSignature === '') {
            return false;
        }

        $payload = $request->getContent();
        $expectedSha256 = hash_hmac('sha256', $payload, $connection->webhook_secret);
        $expectedSha1 = hash_hmac('sha1', $payload, $connection->webhook_secret);

        return hash_equals($expectedSha256, $providedSignature)
            || hash_equals($expectedSha1, $providedSignature);
    }

    public function extractWebhookMetadata(Request $request): array
    {
        $payload = $request->json()->all();

        if ($payload === []) {
            $payload = $request->all();
        }

        return [
            'event_type' => $request->header('X-Webhook-Event')
                ?: $request->header('X-Event-Type')
                ?: data_get($payload, 'eventType')
                ?: data_get($payload, 'type'),
            'external_event_id' => $request->header('X-Webhook-Id')
                ?: $request->header('X-Event-Id')
                ?: data_get($payload, 'id')
                ?: data_get($payload, 'eventId')
                ?: data_get($payload, 'shipmentPackageId'),
            'payload' => is_array($payload) ? $payload : [],
        ];
    }

    public function testConnection(\App\Models\MarketplaceStore $store): array
    {
        return [
            'ok' => false,
            'message' => 'Bu bağlayıcı için test bağlantısı henüz tanımlanmadı.',
        ];
    }

    /**
     * Pazaryeri katalog payload'larındaki termin bilgisini ortak listing alanlarına indirger.
     *
     * @param  array<int, array<string, mixed>>  $payloads
     * @return array<string, mixed>
     */
    protected function catalogDeliveryTermData(array ...$payloads): array
    {
        $days = $this->firstDeliveryPayloadValue($payloads, [
            'shipping_days',
            'shippingDays',
            'shipmentDays',
            'shipmentDay',
            'shippingDay',
            'deliveryDuration',
            'deliveryDurationDays',
            'delivery_duration',
            'delivery.duration',
            'delivery.durationDays',
            'deliveryTime',
            'deliveryTimeDays',
            'leadTime',
            'lead_time',
            'leadTimeToShip',
            'dispatchTime',
            'dispatchTimeDays',
            'preparationTime',
            'preparationDays',
            'cargoTime',
            'cargoDuration',
            'termin',
        ]);

        $shippingType = $this->firstDeliveryPayloadValue($payloads, [
            'shipping_type',
            'shippingType',
            'shipmentType',
            'shipment_type',
            'shipmentTemplateName',
            'shipmentTemplate.name',
            'deliveryType',
            'delivery.type',
            'deliveryOption',
            'deliveryOptionType',
            'cargoType',
        ]);

        $fastDeliveryType = $this->firstDeliveryPayloadValue($payloads, [
            'fast_delivery_type',
            'fastDeliveryType',
            'fastDeliveryOption',
            'fastDelivery',
            'delivery.fastDeliveryType',
            'isFastDelivery',
            'sameDayDelivery',
            'sameDayShipping',
        ]);

        return array_filter([
            'shipping_days' => $this->normalizeDeliveryDays($days),
            'shipping_type' => $this->normalizeDeliveryLabel($shippingType),
            'fast_delivery_type' => $this->normalizeDeliveryLabel($fastDeliveryType),
        ], static fn ($value) => $value !== null);
    }

    /**
     * @param  array<int, array<string, mixed>>  $payloads
     * @param  array<int, string>  $keys
     */
    protected function firstDeliveryPayloadValue(array $payloads, array $keys): mixed
    {
        foreach ($payloads as $payload) {
            foreach ($keys as $key) {
                $value = data_get($payload, $key);

                if ($value === null) {
                    continue;
                }

                if (is_string($value) && trim($value) === '') {
                    continue;
                }

                return $value;
            }
        }

        return null;
    }

    protected function normalizeDeliveryDays(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 0 : null;
        }

        if (is_numeric($value)) {
            $days = (int) ceil((float) $value);

            return $days >= 0 && $days <= 365 ? $days : null;
        }

        $text = Str::lower(trim((string) $value));

        if ($text === '') {
            return null;
        }

        if (Str::contains($text, ['same day', 'aynı gün', 'ayni gun', 'bugün', 'bugun'])) {
            return 0;
        }

        if (preg_match('/(\d+(?:[.,]\d+)?)/', $text, $match) !== 1) {
            return null;
        }

        $number = (float) str_replace(',', '.', $match[1]);
        $days = Str::contains($text, ['saat', 'hour'])
            ? (int) ceil($number / 24)
            : (int) ceil($number);

        return $days >= 0 && $days <= 365 ? $days : null;
    }

    protected function normalizeDeliveryLabel(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'Hızlı teslimat' : null;
        }

        $label = trim((string) $value);

        if ($label === '') {
            return null;
        }

        $normalized = Str::lower(str_replace(['_', '-'], ' ', $label));

        if (Str::contains($normalized, ['same day', 'aynı gün', 'ayni gun', 'bugün', 'bugun'])) {
            return 'Aynı gün';
        }

        if (Str::contains($normalized, ['fast', 'hızlı', 'hizli', 'express'])) {
            return 'Hızlı teslimat';
        }

        if (strlen($label) <= 80 && !Str::contains($label, ['_', '-'])) {
            return $label;
        }

        return Str::limit(Str::headline(str_replace(['_', '-'], ' ', $label)), 80, '');
    }
}
