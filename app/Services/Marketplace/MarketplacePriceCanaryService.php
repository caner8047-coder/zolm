<?php

namespace App\Services\Marketplace;

use App\Jobs\PushMarketplacePriceActionJob;
use App\Models\MarketplaceStore;
use App\Models\MpPriceAction;
use App\Models\MpPriceRecommendation;
use Illuminate\Support\Facades\Log;

class MarketplacePriceCanaryService
{
    public function __construct(
        protected MarketplaceAutomaticPriceEligibilityService $eligibilityService,
        protected MarketplacePriceEmergencyStopService $emergencyStopService,
    ) {
    }

    /**
     * Run canary auto-pricing cycle for a store
     * Respects limits: Max 3 items per run, Max 5/hr, Max 10/day.
     */
    public function runCanaryCycle(MarketplaceStore $store): int
    {
        if ($this->emergencyStopService->isEmergencyStopActive($store->id)) {
            Log::warning('[MarketplacePriceCanaryService] Emergency stop active for store.', ['store_id' => $store->id]);
            return 0;
        }

        if (! config('marketplace.trendyol.automatic_price_actions_enabled', false)
            || ! config('marketplace.trendyol.canary_enabled', false)) {
            return 0;
        }

        // Hourly limit check (Max 5/hr)
        $hourlyCount = MpPriceAction::where('store_id', $store->id)
            ->where('trigger_type', 'automatic')
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($hourlyCount >= 5) {
            Log::info('[MarketplacePriceCanaryService] Hourly canary limit reached.', ['store_id' => $store->id]);
            return 0;
        }

        // Daily limit check (Max 10/day)
        $dailyCount = MpPriceAction::where('store_id', $store->id)
            ->where('trigger_type', 'automatic')
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        if ($dailyCount >= 10) {
            Log::info('[MarketplacePriceCanaryService] Daily canary limit reached.', ['store_id' => $store->id]);
            return 0;
        }

        $recommendations = MpPriceRecommendation::where('store_id', $store->id)
            ->where('status', 'new')
            ->where('risk_level', 'low')
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get();

        $dispatched = 0;
        $maxPerRun = min(3, 5 - $hourlyCount, 10 - $dailyCount);

        foreach ($recommendations as $rec) {
            if ($dispatched >= $maxPerRun) {
                break;
            }

            $eligibility = $this->eligibilityService->evaluateEligibility($rec);

            if (! $eligibility['eligible']) {
                continue;
            }

            $action = MpPriceAction::create([
                'store_id' => $store->id,
                'recommendation_id' => $rec->id,
                'barcode' => $rec->barcode,
                'old_price' => $rec->current_price,
                'expected_current_price' => $rec->current_price,
                'requested_price' => $rec->recommended_price,
                'action_type' => 'price_change',
                'trigger_type' => 'automatic',
                'approved_at' => now(),
                'status' => 'pending',
                'request_payload' => [
                    'canary_eligibility' => $eligibility,
                ],
            ]);

            $rec->update(['status' => 'queued']);

            PushMarketplacePriceActionJob::dispatch($action->id);
            $dispatched++;
        }

        Log::info('[MarketplacePriceCanaryService] Canary döngüsü tamamlandı', [
            'store_id' => $store->id,
            'dispatched' => $dispatched,
        ]);

        return $dispatched;
    }
}
