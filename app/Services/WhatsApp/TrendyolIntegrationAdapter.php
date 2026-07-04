<?php

namespace App\Services\WhatsApp;

use App\Models\WaExternalIntegration;

class TrendyolIntegrationAdapter implements IntegrationAdapterInterface
{
    public function key(): string { return 'trendyol'; }
    public function name(): string { return 'Trendyol'; }

    public function healthCheck(?WaExternalIntegration $integration): array
    {
        $config = $integration->config_json ?? [];

        if (empty($config['api_key'])) {
            return ['status' => 'error', 'message' => 'API key tanımlı değil'];
        }

        return ['status' => 'ok', 'message' => 'Trendyol entegrasyonu yapılandırıldı'];
    }

    public function sync(WaExternalIntegration $integration, array $payload): array
    {
        return ['synced' => 0, 'message' => 'Trendyol sync mevcut connector üzerinden yönetilir'];
    }

    public function canSend(): bool
    {
        return false; // Sprint 8'de sadece okuma
    }
}
