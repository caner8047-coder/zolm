<?php

namespace App\Services\Marketplace;

use App\Models\MpPriceEmergencyStop;
use Illuminate\Support\Facades\Log;

class MarketplacePriceEmergencyStopService
{
    protected MarketplacePricePilotNotificationService $notificationService;

    public function __construct(MarketplacePricePilotNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function isEmergencyStopActive(?int $storeId = null, ?string $barcode = null): bool
    {
        // Config level check
        if (config('marketplace.trendyol.emergency_stop_enabled', false)) {
            return true;
        }

        // DB level check: Global emergency stop
        $globalStop = MpPriceEmergencyStop::where('scope', 'global')
            ->where('is_active', true)
            ->exists();

        if ($globalStop) {
            return true;
        }

        if ($storeId) {
            $storeStop = MpPriceEmergencyStop::where('scope', 'store')
                ->where('store_id', $storeId)
                ->where('is_active', true)
                ->exists();

            if ($storeStop) {
                return true;
            }
        }

        return false;
    }

    public function activateEmergencyStop(?int $storeId, string $reason): MpPriceEmergencyStop
    {
        $scope = $storeId ? 'store' : 'global';

        $stop = MpPriceEmergencyStop::create([
            'scope' => $scope,
            'store_id' => $storeId,
            'is_active' => true,
            'reason' => $reason,
            'stopped_by' => auth()->id(),
            'stopped_at' => now(),
        ]);

        Log::alert("[MarketplacePriceEmergencyStopService] Emergency Stop AKTİF EDİLDİ", [
            'scope' => $scope,
            'store_id' => $storeId,
            'reason' => $reason,
            'user_id' => auth()->id(),
        ]);

        if ($storeId) {
            $this->notificationService->notifyEmergencyStopActivated($storeId, $reason);
        }

        return $stop;
    }

    public function deactivateEmergencyStop(?int $storeId): int
    {
        $query = MpPriceEmergencyStop::where('is_active', true);

        if ($storeId) {
            $query->where('scope', 'store')->where('store_id', $storeId);
        } else {
            $query->where('scope', 'global');
        }

        $affected = $query->update([
            'is_active' => false,
            'resumed_at' => now(),
            'resumed_by' => auth()->id(),
        ]);

        Log::info("[MarketplacePriceEmergencyStopService] Emergency Stop DEVREDEN ÇIKARILDI", [
            'store_id' => $storeId,
            'user_id' => auth()->id(),
        ]);

        if ($storeId && $affected > 0) {
            $this->notificationService->notifyEmergencyStopDeactivated($storeId);
        }

        return $affected;
    }
}
