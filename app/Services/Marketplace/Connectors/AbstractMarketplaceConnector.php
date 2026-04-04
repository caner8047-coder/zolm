<?php

namespace App\Services\Marketplace\Connectors;

use App\Models\IntegrationConnection;
use App\Services\Marketplace\Contracts\MarketplaceConnector;
use App\Services\Marketplace\Contracts\ReceivesWebhooks;
use App\Services\Marketplace\Contracts\TestsConnection;
use Illuminate\Http\Request;

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
}
