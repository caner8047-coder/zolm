<?php

namespace App\Services\Marketplace;

use App\Models\ChannelListing;
use App\Models\MarketplaceStore;
use App\Models\MpBuyboxListing;
use App\Models\MpPricePilotProduct;
use App\Models\MpProduct;

class MarketplacePricePilotService
{
    /**
     * Check if a product is in pilot for a store.
     */
    public function isProductInPilot(int $storeId, string $barcode): bool
    {
        return MpPricePilotProduct::where('store_id', $storeId)
            ->where('barcode', $barcode)
            ->where('mode', '!=', 'disabled')
            ->exists();
    }

    /**
     * Add product to pilot whitelist
     */
    public function addProductToPilot(MarketplaceStore $store, string $barcode, string $mode = 'shadow', ?string $reason = null): MpPricePilotProduct
    {
        $maxLimit = $this->maxPilotProductLimit($store);
        $currentCount = MpPricePilotProduct::where('store_id', $store->id)->where('mode', '!=', 'disabled')->count();

        if ($currentCount >= $maxLimit) {
            throw new \RuntimeException("Pilot ürün limiti (maksimum {$maxLimit} ürün) aşılamaz.");
        }

        // Validate product suitability
        $exclusionReason = $this->checkExclusionCriteria($store, $barcode);
        if ($exclusionReason) {
            throw new \RuntimeException("Ürün pilot kapsama alınamaz: {$exclusionReason}");
        }

        return MpPricePilotProduct::updateOrCreate(
            ['store_id' => $store->id, 'barcode' => $barcode],
            [
                'mode' => $mode,
                'inclusion_reason' => $reason ?? 'MANUAL_SELECTION',
                'added_by' => auth()->id(),
                'added_at' => now(),
            ]
        );
    }

    /**
     * Remove product from pilot whitelist
     */
    public function removeProductFromPilot(int $storeId, string $barcode): bool
    {
        return MpPricePilotProduct::where('store_id', $storeId)
            ->where('barcode', $barcode)
            ->update(['mode' => 'disabled']) > 0;
    }

    /**
     * Max Pilot Products = min(10, 1% of total catalog)
     */
    public function maxPilotProductLimit(MarketplaceStore $store): int
    {
        $totalListings = MpBuyboxListing::where('store_id', $store->id)->count();
        $onePercent = (int) max(1, ceil($totalListings * 0.01));

        return min(10, $onePercent);
    }

    /**
     * Check exclusion criteria for pilot selection
     */
    public function checkExclusionCriteria(MarketplaceStore $store, string $barcode): ?string
    {
        $mpProduct = MpProduct::where('user_id', $store->user_id)
            ->where('barcode', $barcode)
            ->first();

        if (! $mpProduct || (float) $mpProduct->cogs <= 0) {
            return 'Maliyet (COGS) bilgisi eksik';
        }

        if ((int) $mpProduct->stock_quantity <= (int) ($mpProduct->critical_stock_threshold ?? 5)) {
            return 'Kritik stok seviyesinde';
        }

        if ((float) ($mpProduct->return_rate ?? 0) > 15.0) {
            return 'Yüksek iade oranlı ürün (> %15)';
        }

        return null;
    }
}
