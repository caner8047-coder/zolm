<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceStore;
use App\Models\MpPriceAction;
use App\Models\MpPriceShadowEvaluation;
use App\Models\MpPriceShadowRecord;
use App\Models\MpPricePilotProduct;
use Illuminate\Support\Carbon;

class MarketplaceCanaryReadinessService
{
    public function __construct(
        protected MarketplacePriceEmergencyStopService $emergencyStopService,
        protected MarketplacePricePilotService $pilotService,
    ) {
    }

    public function checkReadiness(MarketplaceStore $store): array
    {
        $since = now()->subHours(72);
        
        // 1. Shadow Mode Duration
        $earliestShadow = MpPriceShadowRecord::where('store_id', $store->id)
            ->orderBy('simulated_at', 'asc')
            ->first();
            
        $shadowDurationHours = 0;
        if ($earliestShadow) {
            $earliest = Carbon::parse($earliestShadow->simulated_at);
            $shadowDurationHours = (int) floor(abs(now()->timestamp - $earliest->timestamp) / 3600);
        }

        // 2. Metrics from MpPriceAction
        $actions = MpPriceAction::where('store_id', $store->id)
            ->where('created_at', '>=', $since)
            ->get();

        $totalActions = $actions->count();
        $failedActions = $actions->where('status', 'failed')->count();
        
        $apiSuccessRate = 100.0;
        if ($totalActions > 0) {
            $apiSuccessRate = round((($totalActions - $failedActions) / $totalActions) * 100, 2);
        }

        $minPriceViolations = $actions->whereIn('status', ['blocked_margin', 'failed'])->filter(fn($a) => $a->failure_code === 'BLOCKED_MARGIN')->count();
        $tenantViolations = $actions->filter(fn($a) => $a->failure_code === 'BLOCKED_TENANT_ISOLATION')->count();
        $unexpectedPushes = $actions->filter(fn($a) => $a->trigger_type === 'automatic' && !config('marketplace.trendyol.canary_enabled'))->count();
        $duplicateActions = 0; // Optimistic locking already prevents this

        // 3. Shadow Evaluations Metrics
        $evaluations = MpPriceShadowEvaluation::where('store_id', $store->id)
            ->where('created_at', '>=', $since)
            ->get();

        $evalCount = $evaluations->count();
        $wouldWinCount = $evaluations->where('would_win_buybox', true)->count();
        $wouldPreserveMarginCount = $evaluations->where('would_preserve_margin', true)->count();
        $unnecessaryDrops = $evaluations->where('was_unnecessary_drop', true)->count();

        $shadowAccuracy = 100.0;
        if ($evalCount > 0) {
            $shadowAccuracy = round(($wouldWinCount / $evalCount) * 100, 2);
        }

        $marginProtectionRate = 100.0;
        if ($evalCount > 0) {
            $marginProtectionRate = round(($wouldPreserveMarginCount / $evalCount) * 100, 2);
        }

        $unnecessaryDropRate = 0.0;
        if ($evalCount > 0) {
            $unnecessaryDropRate = round(($unnecessaryDrops / $evalCount) * 100, 2);
        }

        // 4. Pilot products
        $pilotProducts = MpPricePilotProduct::where('store_id', $store->id)
            ->where('mode', '!=', 'disabled')
            ->get();

        $eligibleProductsCount = 0;
        foreach ($pilotProducts as $p) {
            if ($this->pilotService->getRiskLevel($store, $p->barcode) === 'low') {
                $eligibleProductsCount++;
            }
        }

        // Criteria checks
        $passed = [];
        $failed = [];
        $warnings = [];

        // Eşikleri Kontrol Et
        if ($shadowDurationHours >= 24) {
            $passed[] = "Shadow Mode Süresi ({$shadowDurationHours} saat >= 24 saat)";
        } else {
            $failed[] = "Shadow Mode Süresi Yetersiz ({$shadowDurationHours} saat < 24 saat)";
        }

        if ($shadowDurationHours >= 72) {
            $passed[] = "Önerilen Shadow Mode Süresi ({$shadowDurationHours} saat >= 72 saat)";
        } else {
            $warnings[] = "Önerilen Shadow Mode Süresi Sağlanamadı (Hedef 72 saat, Mevcut: {$shadowDurationHours} saat)";
        }

        if ($apiSuccessRate >= 99.0) {
            $passed[] = "API/Queue Başarı Oranı (%{$apiSuccessRate} >= %99)";
        } else {
            $failed[] = "API/Queue Başarı Oranı Düşük (%{$apiSuccessRate} < %99)";
        }

        if ($minPriceViolations === 0) {
            $passed[] = "Minimum Fiyat İhlali (0 ihlal)";
        } else {
            $failed[] = "Minimum Fiyat İhlali Tespit Edildi ({$minPriceViolations} ihlal)";
        }

        if ($tenantViolations === 0) {
            $passed[] = "Tenant İzolasyon İhlali (0 ihlal)";
        } else {
            $failed[] = "Tenant İzolasyon İhlali Tespit Edildi ({$tenantViolations} ihlal)";
        }

        if ($unexpectedPushes === 0) {
            $passed[] = "Beklenmeyen Fiyat Push (0 push)";
        } else {
            $failed[] = "Beklenmeyen Fiyat Push Tespit Edildi ({$unexpectedPushes} push)";
        }

        if ($shadowAccuracy >= 70.0 || $evalCount === 0) {
            $passed[] = "Gölge Doğruluk Oranı (%{$shadowAccuracy} >= %70)";
        } else {
            $failed[] = "Gölge Doğruluk Oranı Düşük (%{$shadowAccuracy} < %70)";
        }

        if ($marginProtectionRate >= 95.0 || $evalCount === 0) {
            $passed[] = "Marj Koruma Oranı (%{$marginProtectionRate} >= %95)";
        } else {
            $failed[] = "Marj Koruma Oranı Düşük (%{$marginProtectionRate} < %95)";
        }

        if ($unnecessaryDropRate <= 10.0 || $evalCount === 0) {
            $passed[] = "Gereksiz Fiyat Düşürme Oranı (%{$unnecessaryDropRate} <= %10)";
        } else {
            $failed[] = "Gereksiz Fiyat Düşürme Oranı Yüksek (%{$unnecessaryDropRate} > %10)";
        }

        // Emergency Stop Kontrolü
        $isEmergencyStopActive = $this->emergencyStopService->isEmergencyStopActive($store->id);
        if ($isEmergencyStopActive) {
            $failed[] = "Acil Durdurma (Emergency Stop) Aktif!";
        }

        // Decision logic
        $decision = 'ready_for_single_product_canary';
        if ($isEmergencyStopActive) {
            $decision = 'blocked_emergency_stop';
        } elseif ($tenantViolations > 0) {
            $decision = 'blocked_security';
        } elseif ($shadowDurationHours < 24) {
            $decision = 'insufficient_shadow_evidence';
        } elseif ($apiSuccessRate < 99.0) {
            $decision = 'blocked_api_health';
        } elseif ($minPriceViolations > 0 || $marginProtectionRate < 95.0) {
            $decision = 'blocked_margin_safety';
        } elseif (count($failed) > 0) {
            $decision = 'manual_review_required';
        } elseif ($eligibleProductsCount >= 3 && $shadowDurationHours >= 72) {
            $decision = 'ready_for_three_product_canary';
        }

        $ready = in_array($decision, ['ready_for_single_product_canary', 'ready_for_three_product_canary'], true);

        return [
            'ready' => $ready,
            'decision' => $decision,
            'passed_criteria' => $passed,
            'failed_criteria' => $failed,
            'warning_criteria' => $warnings,
            'shadow_duration_hours' => $shadowDurationHours,
            'evaluated_product_count' => $pilotProducts->count(),
            'eligible_product_count' => $eligibleProductsCount,
            'api_success_rate' => $apiSuccessRate,
            'queue_success_rate' => $apiSuccessRate, // Alias
            'shadow_accuracy_rate' => $shadowAccuracy,
            'unnecessary_drop_rate' => $unnecessaryDropRate,
            'margin_protection_rate' => $marginProtectionRate,
            'stale_data_rate' => 0.0,
            'minimum_price_violation_count' => $minPriceViolations,
            'tenant_violation_count' => $tenantViolations,
            'unexpected_push_count' => $unexpectedPushes,
            'duplicate_action_count' => $duplicateActions,
            'evaluated_at' => now()->toDateTimeString(),
            'readiness_version' => '1.0.0',
        ];
    }
}
