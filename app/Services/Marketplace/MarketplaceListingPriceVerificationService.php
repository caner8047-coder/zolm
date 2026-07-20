<?php

namespace App\Services\Marketplace;

use App\Models\ChannelListing;
use App\Models\MpPriceAction;
use Illuminate\Support\Facades\Log;

class MarketplaceListingPriceVerificationService
{
    /**
     * Verify actual listing price after Trendyol batch completion
     */
    public function verifyActionPrice(MpPriceAction $action): bool
    {
        if (! config('marketplace.trendyol.price_verification_enabled', false)) {
            $action->update(['verification_status' => 'verified_success', 'verified_at' => now()]);
            return true;
        }

        $action->loadMissing(['store']);
        $store = $action->store;

        if (! $store) {
            return false;
        }

        $listing = ChannelListing::where('store_id', $store->id)
            ->whereHas('channelProduct', fn ($q) => $q->where('barcode', $action->barcode))
            ->first();

        if (! $listing) {
            $action->update([
                'verification_status' => 'verification_failed',
                'verified_at' => now(),
            ]);
            return false;
        }

        $observedPrice = (float) ($listing->sale_price ?? 0);
        $requestedPrice = (float) $action->requested_price;

        $isVerified = abs($observedPrice - $requestedPrice) <= 0.01;

        $action->update([
            'verification_status' => $isVerified ? 'verified_success' : 'verification_failed',
            'observed_listing_price' => $observedPrice,
            'verified_at' => now(),
        ]);

        Log::info('[MarketplaceListingPriceVerificationService] Fiyat doğrulaması yapıldı', [
            'action_id' => $action->id,
            'requested' => $requestedPrice,
            'observed' => $observedPrice,
            'verified' => $isVerified,
        ]);

        return $isVerified;
    }
}
