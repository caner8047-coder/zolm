<?php

namespace App\Services\Marketplace;

use App\Models\MpPriceManualLock;

class MarketplacePriceLockService
{
    public function isLocked(int $storeId, string $barcode): bool
    {
        $lock = MpPriceManualLock::where('store_id', $storeId)
            ->where('barcode', $barcode)
            ->first();

        return $lock ? $lock->isCurrentlyLocked() : false;
    }

    public function lockProduct(int $storeId, string $barcode, string $reason, bool $isIndefinite = true): MpPriceManualLock
    {
        return MpPriceManualLock::updateOrCreate(
            ['store_id' => $storeId, 'barcode' => $barcode],
            [
                'is_locked' => true,
                'lock_reason' => $reason,
                'locked_by' => auth()->id(),
                'starts_at' => now(),
                'is_indefinite' => $isIndefinite,
            ]
        );
    }

    public function unlockProduct(int $storeId, string $barcode): bool
    {
        return MpPriceManualLock::where('store_id', $storeId)
            ->where('barcode', $barcode)
            ->update(['is_locked' => false]) > 0;
    }
}
