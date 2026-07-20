<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceStore;
use App\Models\MpBuyboxListing;
use App\Models\MpPriceShadowEvaluation;
use App\Models\MpPriceShadowRecord;

class MarketplacePriceShadowService
{
    public function __construct(
        protected MarketplaceBuyboxRecommendationService $recommendationService,
    ) {
    }

    /**
     * Generate shadow record for a listing
     */
    public function recordShadowSimulation(MpBuyboxListing $listing): ?MpPriceShadowRecord
    {
        if (! config('marketplace.trendyol.shadow_mode_enabled', false)) {
            return null;
        }

        $rec = $this->recommendationService->generateForListing($listing);

        return MpPriceShadowRecord::create([
            'store_id' => $listing->store_id,
            'barcode' => $listing->barcode,
            'simulated_at' => now(),
            'current_price' => $rec->current_price,
            'buybox_price' => $rec->buybox_price,
            'recommended_price' => $rec->recommended_price,
            'minimum_safe_price' => $rec->minimum_safe_price,
            'expected_profit' => $rec->expected_profit,
            'expected_profit_margin' => $rec->expected_profit_margin,
            'recommendation_type' => $rec->recommendation_type,
            'risk_level' => $rec->risk_level,
            'is_actionable' => $rec->isActionable(),
            'blocking_reasons' => $rec->reason_codes,
            'buybox_snapshot' => [
                'seller_rank' => $listing->seller_rank,
                'second_price' => $listing->second_price,
            ],
            'cost_snapshot' => $rec->calculation_snapshot,
            'policy_snapshot' => [],
            'simulated_action_type' => 'price_change',
        ]);
    }

    /**
     * Evaluate recent shadow records against updated buybox data
     */
    public function evaluateShadowRecords(MarketplaceStore $store): int
    {
        $records = MpPriceShadowRecord::where('store_id', $store->id)
            ->whereDoesntHave('evaluations')
            ->where('simulated_at', '>=', now()->subHours(48))
            ->get();

        $count = 0;

        foreach ($records as $record) {
            $currentListing = MpBuyboxListing::where('store_id', $store->id)
                ->where('barcode', $record->barcode)
                ->first();

            if (! $currentListing) {
                continue;
            }

            $recPrice = (float) ($record->recommended_price ?? 0);
            $actualBuybox = (float) ($currentListing->buybox_price ?? 0);
            $rank = $currentListing->seller_rank;

            $wouldWin = $recPrice > 0 && $actualBuybox > 0 && $recPrice <= $actualBuybox;
            $wouldPreserveMargin = $recPrice >= (float) $record->minimum_safe_price;
            $unnecessaryDrop = $record->recommendation_type === 'LOWER_TO_WIN' && $rank === 1 && $actualBuybox >= (float) $record->current_price;
            $raiseCorrect = $record->recommendation_type === 'RAISE_WHILE_KEEPING_BUYBOX' && $rank === 1;

            MpPriceShadowEvaluation::create([
                'shadow_record_id' => $record->id,
                'store_id' => $store->id,
                'barcode' => $record->barcode,
                'evaluated_at' => now(),
                'actual_buybox_price_after' => $actualBuybox,
                'actual_seller_rank_after' => $rank,
                'would_win_buybox' => $wouldWin,
                'would_preserve_margin' => $wouldPreserveMargin,
                'was_unnecessary_drop' => $unnecessaryDrop,
                'was_raise_opportunity_correct' => $raiseCorrect,
                'price_deviation' => round(abs($recPrice - $actualBuybox), 2),
                'validity_duration_minutes' => (int) now()->diffInMinutes($record->simulated_at),
            ]);

            $count++;
        }

        return $count;
    }
}
