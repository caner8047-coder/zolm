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

        // Active approval check
        $approval = \App\Models\MpPriceCanaryApproval::where('store_id', $store->id)
            ->where('status', 'approved')
            ->where('expires_at', '>=', now())
            ->first();

        if (! $approval) {
            Log::warning('[MarketplacePriceCanaryService] Canary is disabled because no active and valid approval exists.', ['store_id' => $store->id]);
            return 0;
        }

        $approvedBarcodes = $approval->approved_product_ids ?? [];
        if (empty($approvedBarcodes)) {
            return 0;
        }

        if ($approval->approval_scope === 'single_product') {
            $approvedBarcodes = array_slice($approvedBarcodes, 0, 1);

            // Single product canary limits: Max 1 auto action in 24 hours
            $last24hCount = MpPriceAction::where('store_id', $store->id)
                ->where('trigger_type', 'automatic')
                ->where('created_at', '>=', now()->subHours(24))
                ->count();
            if ($last24hCount >= 1) {
                Log::info('[MarketplacePriceCanaryService] Single product canary limited to 1 action per 24 hours.', ['store_id' => $store->id]);
                return 0;
            }

            // 12 hours cooldown since last action
            $lastAction = MpPriceAction::where('store_id', $store->id)
                ->where('trigger_type', 'automatic')
                ->orderBy('created_at', 'desc')
                ->first();
            if ($lastAction && Carbon::parse($lastAction->created_at)->addHours(12)->isFuture()) {
                Log::info('[MarketplacePriceCanaryService] Single product canary cooldown active.', ['store_id' => $store->id]);
                return 0;
            }
        } elseif ($approval->approval_scope === 'three_products') {
            $approvedBarcodes = array_slice($approvedBarcodes, 0, 3);
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
            ->whereIn('barcode', $approvedBarcodes)
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get();

        $dispatched = 0;
        $maxPerRun = min(3, 5 - $hourlyCount, 10 - $dailyCount);

        foreach ($recommendations as $rec) {
            if ($dispatched >= $maxPerRun) {
                break;
            }

            // Price change limit for single product (Max 1%)
            if ($approval->approval_scope === 'single_product') {
                $changePct = abs((float)$rec->recommended_price - (float)$rec->current_price) / (float)$rec->current_price;
                if ($changePct > 0.01) {
                    Log::info("[MarketplacePriceCanaryService] Single product price change {$changePct} exceeds 1% limit.", ['barcode' => $rec->barcode]);
                    continue;
                }
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

    /**
     * Store Canary Auto-Pause trigger
     */
    public function onStoreCanaryPause(int $storeId, string $reason): void
    {
        $affected = \App\Models\MpPriceCanaryApproval::where('store_id', $storeId)
            ->where('status', 'approved')
            ->update([
                'status' => 'revoked',
                'revoked_at' => now(),
                'revoked_by' => auth()->id() ?: 1,
                'approval_reason' => "Otomatik Durdurma (Pause): {$reason}",
            ]);

        if ($affected > 0) {
            Log::warning("[MarketplacePriceCanaryService] Canary modu otomatik olarak durduruldu (PAUSED). Gerekçe: {$reason}", [
                'store_id' => $storeId,
            ]);

            // Dispatch notification via notification service
            $notificationService = app(MarketplacePricePilotNotificationService::class);
            $notificationService->notifyCanaryAutoPaused($storeId, $reason);
        }
    }
}
