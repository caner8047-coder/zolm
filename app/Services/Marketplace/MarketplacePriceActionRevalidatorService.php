<?php

namespace App\Services\Marketplace;

use App\Models\ChannelListing;
use App\Models\MarketplaceStore;
use App\Models\MpBuyboxListing;
use App\Models\MpPriceAction;
use App\Models\MpProduct;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class MarketplacePriceActionRevalidatorService
{
    public function __construct(
        protected MarketplaceBuyboxRecommendationService $recommendationService,
        protected MarketplacePricePolicyService $policyService,
        protected MarketplacePriceLockService $lockService,
        protected MarketplacePriceEmergencyStopService $emergencyStopService,
        protected MarketplacePricePilotNotificationService $notificationService,
    ) {
    }

    /**
     * Revalidate price action at execution time.
     * Returns true if safe to push to Trendyol, false if blocked.
     */
    public function revalidateAtExecution(MpPriceAction $action): bool
    {
        $action->loadMissing(['store', 'recommendation']);
        $store = $action->store;

        if (! $store || ! $store->is_active) {
            $this->blockAction($action, 'blocked_store_unhealthy', 'Mağaza bulunamadı veya pasif durumda.');
            return false;
        }

        // 0. Tenant Isolation Check
        $user = auth()->user();
        if ($user && $user->id !== $store->user_id && $user->role !== 'admin' && $user->role !== 'operator') {
            $this->notificationService->notifyTenantIsolationViolation($store->id, $user->id);
            $this->blockAction($action, 'blocked_tenant_isolation', 'Bu mağaza için işlem yapmaya yetkiniz yok.');
            return false;
        }

        // 1. Emergency Stop Check
        if ($this->emergencyStopService->isEmergencyStopActive($store->id, $action->barcode)) {
            $this->blockAction($action, 'blocked_emergency_stop', 'Acil Durdurma (Emergency Stop) aktif.');
            return false;
        }

        // 2. Feature Flag Check
        $isAuto = $action->trigger_type === 'automatic';
        $flagKey = $isAuto ? 'marketplace.trendyol.automatic_price_actions_enabled' : 'marketplace.trendyol.manual_price_actions_enabled';

        if (! config($flagKey, false)) {
            $this->blockAction($action, 'blocked_feature_disabled', 'Fiyat aksiyonu özelliği devre dışı.');
            return false;
        }

        // 3. Manual Price Lock Check
        if ($this->lockService->isLocked($store->id, $action->barcode)) {
            $this->blockAction($action, 'blocked_manual_lock', 'Ürün üzerinde manuel fiyat kilidi mevcut.');
            return false;
        }

        // 4. Listing & Current Price Check
        $listing = ChannelListing::where('store_id', $store->id)
            ->whereHas('channelProduct', fn ($q) => $q->where('barcode', $action->barcode))
            ->first();

        if (! $listing) {
            $this->blockAction($action, 'blocked_listing_not_found', 'Pazaryeri ilanı bulunamadı.');
            return false;
        }

        $actualCurrentPrice = (float) ($listing->sale_price ?? 0);
        $action->update(['actual_current_price_at_execution' => $actualCurrentPrice]);

        // 5. Optimistic Locking / Price Conflict Check
        if ($action->expected_current_price !== null && abs($actualCurrentPrice - (float) $action->expected_current_price) > 0.01) {
            $this->blockAction($action, 'conflict_price_changed', "Ürün fiyatı değişti (Beklenen: ₺{$action->expected_current_price}, Güncel: ₺{$actualCurrentPrice}). Fiyat çakışması.");
            return false;
        }

        // 6. Buybox Data Freshness Check
        $policy = $this->policyService->getPolicy($store);
        $buyboxListing = MpBuyboxListing::where('store_id', $store->id)
            ->where('barcode', $action->barcode)
            ->first();

        $staleThreshold = now()->subMinutes((int) $policy['stale_threshold_minutes']);
        if (! $buyboxListing || ($buyboxListing->retrieved_at && Carbon::parse($buyboxListing->retrieved_at)->lt($staleThreshold))) {
            $hoursStale = $buyboxListing && $buyboxListing->retrieved_at ? (int) now()->diffInHours(Carbon::parse($buyboxListing->retrieved_at)) : 24;
            $this->notificationService->notifyStaleBuyboxData($store->id, $hoursStale);
            $this->blockAction($action, 'blocked_stale_data', 'Buybox verisi bayat (stale). Lütfen veriyi yenileyin.');
            return false;
        }

        // 7. Fresh Recommendation & Minimum Safe Price Re-calculation
        $rec = $buyboxListing ? $this->recommendationService->generateForListing($buyboxListing) : null;

        if (! $rec || $rec->risk_level === 'blocked') {
            $this->blockAction($action, 'blocked_cost_changed', 'Ürün maliyet veya komisyon bilgisi eksik/geçersiz.');
            return false;
        }

        $minSafePrice = (float) $rec->minimum_safe_price;
        $requestedPrice = (float) $action->requested_price;

        if ($requestedPrice < $minSafePrice) {
            $this->notificationService->notifyMinimumPriceViolation($store->id, $action->barcode, $requestedPrice, $minSafePrice);
            $this->blockAction($action, 'blocked_margin', "İstenen fiyat (₺{$requestedPrice}) yenilenen minimum güvenli fiyatın (₺{$minSafePrice}) altındadır.");
            return false;
        }

        // 8. Daily Action Limit Check
        $dailyCount = MpPriceAction::where('store_id', $store->id)
            ->where('created_at', '>=', now()->startOfDay())
            ->whereIn('status', ['success', 'processing'])
            ->count();

        if ($dailyCount >= (int) $policy['daily_max_actions']) {
            $this->blockAction($action, 'blocked_daily_limit', 'Günlük maksimum aksiyon limitine ulaşıldı.');
            return false;
        }

        return true;
    }

    protected function blockAction(MpPriceAction $action, string $status, string $reason): void
    {
        $action->update([
            'status' => $status,
            'failure_code' => strtoupper($status),
            'failure_message' => $reason,
        ]);

        if ($action->recommendation) {
            $action->recommendation->update(['status' => 'cancelled']);
        }

        // Auto-pause Canary mode on critical revalidation failures
        if (in_array($status, ['blocked_margin', 'blocked_tenant_isolation', 'blocked_stale_data', 'blocked_store_unhealthy'], true)) {
            app(\App\Services\Marketplace\MarketplacePriceCanaryService::class)
                ->onStoreCanaryPause($action->store_id, "Revalidation failure ({$status}): {$reason}");
        }

        Log::warning("[MarketplacePriceActionRevalidatorService] Aksiyon engellendi: {$status}", [
            'action_id' => $action->id,
            'barcode' => $action->barcode,
            'reason' => $reason,
        ]);
    }
}
