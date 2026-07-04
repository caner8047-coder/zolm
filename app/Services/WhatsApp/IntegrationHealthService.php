<?php

namespace App\Services\WhatsApp;

use App\Models\WaExternalIntegration;
use App\Models\WaIntegrationSyncJob;

class IntegrationHealthService
{
    /**
     * Entegrasyon sağlık durumunu kontrol et
     */
    public function checkHealth(WaExternalIntegration $integration): array
    {
        $adapter = app(IntegrationAdapterRegistry::class)->resolve($integration->provider);

        $result = $adapter->healthCheck($integration);

        $integration->update([
            'last_health_check_at' => now(),
            'status' => $result['status'] ?? 'error',
        ]);

        return $result;
    }

    /**
     * Tüm aktif entegrasyonların sağlık durumunu kontrol et
     */
    public function checkAllHealth(?int $storeId = null): array
    {
        $query = WaExternalIntegration::active();

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        $integrations = $query->get();
        $results = [];

        foreach ($integrations as $integration) {
            try {
                $results[$integration->provider] = $this->checkHealth($integration);
            } catch (\Throwable $e) {
                $results[$integration->provider] = [
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Senkronizasyon işi oluştur
     */
    public function createSyncJob(WaExternalIntegration $integration, string $jobType, array $payload = []): WaIntegrationSyncJob
    {
        return WaIntegrationSyncJob::create([
            'integration_id' => $integration->id,
            'job_type' => $jobType,
            'status' => 'queued',
            'payload_json' => $payload,
        ]);
    }
}
