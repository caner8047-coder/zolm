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
        $capabilities = $adapter->getCapabilities();

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

        $channel->update(['last_health_check_at' => now()]);

        return $result;
    }
}
