<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceStore;
use App\Models\MpBuyboxListing;
use App\Models\MpPriceAction;
use App\Models\MpPriceRecommendation;
use App\Models\MpProduct;
use Illuminate\Support\Carbon;

class MarketplaceAutomaticPriceEligibilityService
{
    public function __construct(
        protected MarketplacePricePolicyService $policyService,
        protected MarketplacePricePilotService $pilotService,
        protected MarketplacePriceLockService $lockService,
        protected MarketplacePriceEmergencyStopService $emergencyStopService,
    ) {
    }

    /**
     * Evaluate eligibility of a price recommendation for automated Canary execution.
     *
     * @return array<string, mixed>
     */
    public function evaluateEligibility(MpPriceRecommendation $recommendation): array
    {
        $recommendation->loadMissing(['store']);
        $store = $recommendation->store;

        $passedRules = [];
        $failedRules = [];

        // Rule 1: Automatic Feature Flag
        if (config('marketplace.trendyol.automatic_price_actions_enabled', false)) {
            $passedRules[] = 'RULE_AUTO_FLAG_ENABLED';
        } else {
            $failedRules[] = 'RULE_AUTO_FLAG_DISABLED';
        }

        // Rule 2: Store Canary Feature Flag
        if (config('marketplace.trendyol.canary_enabled', false)) {
            $passedRules[] = 'RULE_CANARY_FLAG_ENABLED';
        } else {
            $failedRules[] = 'RULE_CANARY_FLAG_DISABLED';
        }

        // Rule 3: Emergency Stop
        if (! $this->emergencyStopService->isEmergencyStopActive($store->id, $recommendation->barcode)) {
            $passedRules[] = 'RULE_EMERGENCY_STOP_INACTIVE';
        } else {
            $failedRules[] = 'RULE_EMERGENCY_STOP_ACTIVE';
        }

        // Rule 4: Pilot Whitelist
        if ($this->pilotService->isProductInPilot($store->id, $recommendation->barcode)) {
            $passedRules[] = 'RULE_PRODUCT_IN_PILOT';
        } else {
            $failedRules[] = 'RULE_PRODUCT_NOT_IN_PILOT';
        }

        // Rule 5: Manual Price Lock
        if (! $this->lockService->isLocked($store->id, $recommendation->barcode)) {
            $passedRules[] = 'RULE_NO_MANUAL_LOCK';
        } else {
            $failedRules[] = 'RULE_MANUAL_LOCK_ACTIVE';
        }

        // Rule 6: Fresh Buybox Data
        $policy = $this->policyService->getPolicy($store);
        $staleThreshold = now()->subMinutes((int) $policy['stale_threshold_minutes']);
        $buyboxListing = MpBuyboxListing::where('store_id', $store->id)
            ->where('barcode', $recommendation->barcode)
            ->first();

        if ($buyboxListing && $buyboxListing->retrieved_at && Carbon::parse($buyboxListing->retrieved_at)->gte($staleThreshold)) {
            $passedRules[] = 'RULE_BUYBOX_DATA_FRESH';
        } else {
            $failedRules[] = 'RULE_BUYBOX_DATA_STALE';
        }

        // Rule 7: Cost Completeness (COGS > 0)
        if ((float) $recommendation->unit_cost > 0) {
            $passedRules[] = 'RULE_COGS_PRESENT';
        } else {
            $failedRules[] = 'RULE_COGS_MISSING';
        }

        // Rule 8: Risk Level (Only 'low' risk allowed for Canary)
        if ($recommendation->risk_level === 'low') {
            $passedRules[] = 'RULE_RISK_LEVEL_LOW';
        } else {
            $failedRules[] = "RULE_RISK_LEVEL_{$recommendation->risk_level}";
        }

        // Rule 9: Minimum Safe Price Protection
        $targetPrice = (float) ($recommendation->recommended_price ?? 0);
        $minSafe = (float) $recommendation->minimum_safe_price;
        if ($targetPrice > 0 && $targetPrice >= $minSafe) {
            $passedRules[] = 'RULE_MIN_SAFE_PRICE_MAINTAINED';
        } else {
            $failedRules[] = 'RULE_MIN_SAFE_PRICE_VIOLATED';
        }

        // Rule 10: Canary Price Drop Percent Limit (Max drop 2%)
        $currentPrice = (float) $recommendation->current_price;
        if ($currentPrice > 0 && $targetPrice < $currentPrice) {
            $dropPct = (($currentPrice - $targetPrice) / $currentPrice) * 100;
            if ($dropPct <= 2.0) {
                $passedRules[] = 'RULE_PRICE_DROP_WITHIN_CANARY_LIMIT';
            } else {
                $failedRules[] = 'RULE_PRICE_DROP_EXCEEDS_CANARY_LIMIT';
            }
        } elseif ($currentPrice > 0 && $targetPrice > $currentPrice) {
            $raisePct = (($targetPrice - $currentPrice) / $currentPrice) * 100;
            if ($raisePct <= 3.0) {
                $passedRules[] = 'RULE_PRICE_RAISE_WITHIN_CANARY_LIMIT';
            } else {
                $failedRules[] = 'RULE_PRICE_RAISE_EXCEEDS_CANARY_LIMIT';
            }
        }

        // Rule 11: Cooldown (Min 6 hours per item)
        $lastAction = MpPriceAction::where('store_id', $store->id)
            ->where('barcode', $recommendation->barcode)
            ->where('created_at', '>=', now()->subHours(6))
            ->exists();

        if (! $lastAction) {
            $passedRules[] = 'RULE_COOLDOWN_PASSED';
        } else {
            $failedRules[] = 'RULE_COOLDOWN_ACTIVE';
        }

        // Rule 12: Pending Actions
        $pendingAction = MpPriceAction::where('store_id', $store->id)
            ->where('barcode', $recommendation->barcode)
            ->whereIn('status', ['pending', 'processing'])
            ->exists();

        if (! $pendingAction) {
            $passedRules[] = 'RULE_NO_PENDING_ACTION';
        } else {
            $failedRules[] = 'RULE_PENDING_ACTION_EXISTS';
        }

        $isEligible = empty($failedRules);

        return [
            'eligible' => $isEligible,
            'decision' => $isEligible ? 'APPROVED_FOR_CANARY' : 'REJECTED_FOR_CANARY',
            'reason_codes' => $isEligible ? $passedRules : $failedRules,
            'failed_rules' => $failedRules,
            'passed_rules' => $passedRules,
            'risk_level' => $recommendation->risk_level,
            'evaluated_at' => now()->toDateTimeString(),
            'rule_version' => '1.0.0-canary',
        ];
    }
}
