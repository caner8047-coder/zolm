<?php

namespace App\Services\Support;

use App\Models\SupportChannel;
use App\Models\SupportChannelCapability;

class SupportCapabilityService
{
    /**
     * Kanal yeteneklerini güncelle
     */
    public function refreshCapabilities(SupportChannel $channel): array
    {
        $adapter = app(SupportChannelManager::class)->resolveForChannel($channel);
        $capabilities = $adapter->getCapabilities($channel);

        $updated = 0;
        foreach ($capabilities as $cap) {
            SupportChannelCapability::updateOrCreate(
                ['support_channel_id' => $channel->id, 'capability' => $cap['capability']],
                [
                    'status' => $cap['status'],
                    'source' => 'adapter',
                    'checked_at' => now(),
                ]
            );
            $updated++;
        }

        return ['updated' => $updated];
    }

    /**
     * Kanal health check
     */
    public function healthCheck(SupportChannel $channel): array
    {
        $adapter = app(SupportChannelManager::class)->resolveForChannel($channel);
        $result = $adapter->healthCheck($channel);

        $status = (string) ($result['status'] ?? 'unknown');
        $channel->update([
            'last_health_check_at' => now(),
            'last_health_status' => $status,
            'last_health_error' => $status === 'ok'
                ? null
                : mb_substr((string) ($result['message'] ?? 'Bilinmeyen kanal sağlık hatası'), 0, 500),
        ]);

        return $result;
    }
}
