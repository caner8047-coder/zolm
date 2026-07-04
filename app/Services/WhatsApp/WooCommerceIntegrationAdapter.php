<?php

namespace App\Services\WhatsApp;

use App\Models\WaExternalIntegration;

class WooCommerceIntegrationAdapter implements IntegrationAdapterInterface
{
    public function key(): string { return 'woocommerce'; }
    public function name(): string { return 'WooCommerce'; }

    public function healthCheck(?WaExternalIntegration $integration): array
    {
        $config = $integration->config_json ?? [];
        $url = $config['url'] ?? '';

        if (empty($url)) {
            return ['status' => 'error', 'message' => 'URL tanımlı değil'];
        }

        // Gerçek health check: WooCommerce REST API ping
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(10)
                ->withBasicAuth($config['consumer_key'] ?? '', $config['consumer_secret'] ?? '')
                ->get($url . '/wp-json/wc/v3/system_status');

            if ($response->successful()) {
                return ['status' => 'ok', 'message' => 'WooCommerce bağlantısı aktif'];
            }

            return ['status' => 'error', 'message' => 'HTTP ' . $response->status()];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function sync(WaExternalIntegration $integration, array $payload): array
    {
        // Mevcut WooCommerce connector üzerinden sync
        return ['synced' => 0, 'message' => 'Sync mevcut connector üzerinden yönetilir'];
    }

    public function canSend(): bool
    {
        return true;
    }
}
