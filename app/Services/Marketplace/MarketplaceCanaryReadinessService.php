<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceStore;
use App\Models\MpPriceAction;
use App\Models\MpPriceShadowEvaluation;
use App\Models\MpPriceShadowRecord;
use App\Models\MpPricePilotProduct;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

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

        // 2. Metrics from API / IntegrationPushRun
        $apiSampleCount = \App\Models\IntegrationPushRun::where('store_id', $store->id)
            ->where('created_at', '>=', $since)
            ->count();
        $apiFailedCount = \App\Models\IntegrationPushRun::where('store_id', $store->id)
            ->where('created_at', '>=', $since)
            ->where('status', 'failed')
            ->count();
        $apiSuccessRate = $apiSampleCount > 0 ? round((($apiSampleCount - $apiFailedCount) / $apiSampleCount) * 100, 2) : null;

        // 3. Queue metrics from MpPriceAction
        $queueSampleCount = MpPriceAction::where('store_id', $store->id)
            ->where('created_at', '>=', $since)
            ->count();
        $queueFailedCount = MpPriceAction::where('store_id', $store->id)
            ->where('created_at', '>=', $since)
            ->where('status', 'failed')
            ->count();
        $queueSuccessRate = $queueSampleCount > 0 ? round((($queueSampleCount - $queueFailedCount) / $queueSampleCount) * 100, 2) : null;

        $actionsForViolations = MpPriceAction::where('store_id', $store->id)
            ->where('created_at', '>=', $since)
            ->get();

        $minPriceViolations = $actionsForViolations->whereIn('status', ['blocked_margin', 'failed'])->filter(fn($a) => $a->failure_code === 'BLOCKED_MARGIN')->count();
        $tenantViolations = $actionsForViolations->filter(fn($a) => $a->failure_code === 'BLOCKED_TENANT_ISOLATION')->count();
        
        // 4. Duplicate actions count (calculated from real data)
        $duplicateActionCount = MpPriceAction::where('store_id', $store->id)
            ->where('created_at', '>=', $since)
            ->whereIn('status', ['pending', 'processing'])
            ->get()
            ->groupBy(fn($a) => $a->barcode . '_' . $a->requested_price)
            ->filter(fn($g) => $g->count() > 1)
            ->map(fn($g) => $g->count() - 1)
            ->sum();

        if ($duplicateActionCount > 0) {
            Log::warning("[MarketplaceCanaryReadinessService] Duplicate price action attempt detected!", [
                'store_id' => $store->id,
                'duplicate_count' => $duplicateActionCount,
            ]);
        }

        // 5. Unexpected Push detection
        $unexpectedPushes = 0;
        foreach ($actionsForViolations as $act) {
            if ($act->trigger_type === 'automatic' && !config('marketplace.trendyol.canary_enabled')) {
                $unexpectedPushes++;
                continue;
            }

            $inPilot = MpPricePilotProduct::where('store_id', $store->id)
                ->where('barcode', $act->barcode)
                ->where('mode', '!=', 'disabled')
                ->exists();
            if (!$inPilot) {
                $unexpectedPushes++;
                continue;
            }

            $isLocked = app(MarketplacePriceLockService::class)->isLocked($store->id, $act->barcode);
            if ($isLocked) {
                $unexpectedPushes++;
                continue;
            }

            $isStopActive = \App\Models\MpPriceEmergencyStop::where('store_id', $store->id)
                ->where('is_active', true)
                ->exists();
            if ($isStopActive && $act->status !== 'failed' && $act->failure_code !== 'EMERGENCY_STOP') {
                $unexpectedPushes++;
                continue;
            }
        }

        // 6. Shadow Evaluations & Accuracy Formula
        $evaluations = MpPriceShadowEvaluation::where('mp_price_shadow_evaluations.store_id', $store->id)
            ->where('mp_price_shadow_evaluations.created_at', '>=', $since)
            ->join('mp_price_shadow_records', 'mp_price_shadow_evaluations.shadow_record_id', '=', 'mp_price_shadow_records.id')
            ->select('mp_price_shadow_evaluations.*', 'mp_price_shadow_records.recommendation_type')
            ->get();

        $totalEvaluations = $evaluations->count();
        $eligibleEvals = $evaluations->filter(fn($e) => $e->actual_buybox_price_after !== null && $e->actual_seller_rank_after !== null);
        $eligibleCount = $eligibleEvals->count();

        $correctCount = 0;
        $preservedMarginCount = 0;
        $unnecessaryDrops = 0;

        foreach ($eligibleEvals as $e) {
            $type = $e->recommendation_type;
            if (in_array($type, ['LOWER_TO_WIN', 'MATCH_BUYBOX'], true)) {
                $isCorrect = $e->would_win_buybox && $e->would_preserve_margin && !$e->was_unnecessary_drop;
            } elseif (in_array($type, ['KEEP_PRICE', 'PROTECT_MARGIN', 'NO_SAFE_PRICE'], true)) {
                $isCorrect = $e->would_preserve_margin;
            } elseif ($type === 'RAISE_WHILE_KEEPING_BUYBOX') {
                $isCorrect = $e->was_raise_opportunity_correct && $e->would_win_buybox;
            } else {
                $isCorrect = $e->would_preserve_margin;
            }

            if ($isCorrect) {
                $correctCount++;
            }
            if ($e->would_preserve_margin) {
                $preservedMarginCount++;
            }
            if ($e->was_unnecessary_drop) {
                $unnecessaryDrops++;
            }
        }

        $shadowAccuracy = $eligibleCount > 0 ? round(($correctCount / $eligibleCount) * 100, 2) : null;
        $marginProtectionRate = $eligibleCount > 0 ? round(($preservedMarginCount / $eligibleCount) * 100, 2) : null;
        $unnecessaryDropRate = $eligibleCount > 0 ? round(($unnecessaryDrops / $eligibleCount) * 100, 2) : null;

        // 7. Pilot products & observations checking
        $pilotProducts = MpPricePilotProduct::where('store_id', $store->id)
            ->where('mode', '!=', 'disabled')
            ->get();

        $eligibleProductsCount = 0;
        $minProductObservationsOk = true;

        foreach ($pilotProducts as $p) {
            if ($this->pilotService->getRiskLevel($store, $p->barcode) === 'low') {
                $eligibleProductsCount++;
            }
            $prodEvals = MpPriceShadowEvaluation::where('store_id', $store->id)
                ->where('barcode', $p->barcode)
                ->where('created_at', '>=', $since)
                ->count();
            if ($prodEvals < 5) {
                $minProductObservationsOk = false;
            }
        }

        $totalShadowRecords = MpPriceShadowRecord::where('store_id', $store->id)
            ->where('created_at', '>=', $since)
            ->count();

        // Distinct buybox sync cycles
        $buyboxCycles = MpPriceShadowRecord::where('store_id', $store->id)
            ->where('created_at', '>=', $since)
            ->distinct('simulated_at')
            ->count('simulated_at');

        // Criteria checks
        $passed = [];
        $failed = [];
        $warnings = [];

        // Threshold evaluation
        if ($shadowDurationHours >= 24) {
            $passed[] = "Shadow Mode Süresi ({$shadowDurationHours} saat >= 24 saat)";
        } else {
            $failed[] = "Shadow Mode Süresi Yetersiz ({$shadowDurationHours} saat < 24 saat)";
        }

        if ($totalShadowRecords >= 20) {
            $passed[] = "Toplam Gölge Öneri Sayısı ({$totalShadowRecords} >= 20)";
        } else {
            $failed[] = "Toplam Gölge Öneri Sayısı Yetersiz ({$totalShadowRecords} < 20)";
        }

        if ($totalEvaluations >= 20) {
            $passed[] = "Toplam Gölge Değerlendirme Sayısı ({$totalEvaluations} >= 20)";
        } else {
            $failed[] = "Toplam Gölge Değerlendirme Sayısı Yetersiz ({$totalEvaluations} < 20)";
        }

        if ($minProductObservationsOk) {
            $passed[] = "Ürün Başına Minimum Gözlem Sınırı (Her ürün için >= 5)";
        } else {
            $failed[] = "Ürün Başına Gözlem Sayısı Yetersiz (En az bir ürün için < 5)";
        }

        if ($buyboxCycles >= 3) {
            $passed[] = "Buybox Senkronizasyon Döngüsü ({$buyboxCycles} >= 3)";
        } else {
            $failed[] = "Buybox Senkronizasyon Döngüsü Yetersiz ({$buyboxCycles} < 3)";
        }

        if ($apiSampleCount >= 20) {
            $passed[] = "API Örneklem Sayısı ({$apiSampleCount} >= 20)";
        } else {
            $failed[] = "API Örneklem Sayısı Yetersiz ({$apiSampleCount} < 20)";
        }

        if ($queueSampleCount >= 20) {
            $passed[] = "Kuyruk Örneklem Sayısı ({$queueSampleCount} >= 20)";
        } else {
            $failed[] = "Kuyruk Örneklem Sayısı Yetersiz ({$queueSampleCount} < 20)";
        }

        if ($apiSuccessRate !== null && $apiSuccessRate >= 99.0) {
            $passed[] = "API Başarı Oranı (%{$apiSuccessRate} >= %99)";
        } elseif ($apiSuccessRate === null) {
            $failed[] = "API Başarı Verisi Bulunmuyor";
        } else {
            $failed[] = "API Başarı Oranı Düşük (%{$apiSuccessRate} < %99)";
        }

        if ($queueSuccessRate !== null && $queueSuccessRate >= 99.0) {
            $passed[] = "Kuyruk Başarı Oranı (%{$queueSuccessRate} >= %99)";
        } elseif ($queueSuccessRate === null) {
            $failed[] = "Kuyruk Başarı Verisi Bulunmuyor";
        } else {
            $failed[] = "Kuyruk Başarı Oranı Düşük (%{$queueSuccessRate} < %99)";
        }

        if ($minPriceViolations === 0) {
            $passed[] = "Minimum Fiyat İhlali (0 ihlal)";
        } else {
            $failed[] = "Minimum Fiyat İhlali Tespit Edildi ({$minPriceViolations} ihlal)";
        }

        if ($tenantViolations === 0 && $unexpectedPushes === 0 && $duplicateActionCount === 0) {
            $passed[] = "Güvenlik İzolasyon Kriterleri (Geçti)";
        } else {
            $failed[] = "Güvenlik veya Duplicate İhlali Tespit Edildi";
        }

        // Emergency Stop Kontrolü
        $isEmergencyStopActive = $this->emergencyStopService->isEmergencyStopActive($store->id);
        if ($isEmergencyStopActive) {
            $failed[] = "Acil Durdurma (Emergency Stop) Aktif!";
        }

        // Decision logic with hardened priority
        $decision = 'ready_for_single_product_canary';
        if ($isEmergencyStopActive) {
            $decision = 'blocked_emergency_stop';
        } elseif ($tenantViolations > 0 || $unexpectedPushes > 0 || $duplicateActionCount > 0) {
            $decision = 'blocked_security';
        } elseif ($shadowDurationHours < 24) {
            $decision = 'insufficient_shadow_evidence';
        } elseif ($totalShadowRecords < 20) {
            $decision = 'insufficient_shadow_records';
        } elseif ($totalEvaluations < 20) {
            $decision = 'insufficient_shadow_evaluations';
        } elseif (!$minProductObservationsOk) {
            $decision = 'insufficient_product_observations';
        } elseif ($buyboxCycles < 3) {
            $decision = 'insufficient_buybox_cycles';
        } elseif ($apiSampleCount === 0 || $apiSampleCount < 20) {
            $decision = 'insufficient_api_samples';
        } elseif ($queueSampleCount === 0 || $queueSampleCount < 20) {
            $decision = 'insufficient_queue_samples';
        } elseif ($apiSuccessRate === null || $apiSuccessRate < 99.0 || $queueSuccessRate === null || $queueSuccessRate < 99.0) {
            $decision = 'blocked_api_health';
        } elseif ($minPriceViolations > 0 || $marginProtectionRate === null || $marginProtectionRate < 95.0) {
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
            'queue_success_rate' => $queueSuccessRate,
            'shadow_accuracy_rate' => $shadowAccuracy,
            'unnecessary_drop_rate' => $unnecessaryDropRate,
            'margin_protection_rate' => $marginProtectionRate,
            'stale_data_rate' => 0.0,
            'minimum_price_violation_count' => $minPriceViolations,
            'tenant_violation_count' => $tenantViolations,
            'unexpected_push_count' => $unexpectedPushes,
            'duplicate_action_count' => $duplicateActionCount,
            'evaluated_at' => now()->toDateTimeString(),
            'readiness_version' => '1.1.0',
        ];
    }

    public function generateReadinessHash(array $readiness): string
    {
        $payload = [
            'decision' => $readiness['decision'],
            'shadow_duration_hours' => $readiness['shadow_duration_hours'],
            'evaluated_product_count' => $readiness['evaluated_product_count'],
            'eligible_product_count' => $readiness['eligible_product_count'],
            'api_success_rate' => $readiness['api_success_rate'],
            'queue_success_rate' => $readiness['queue_success_rate'],
            'shadow_accuracy_rate' => $readiness['shadow_accuracy_rate'],
            'margin_protection_rate' => $readiness['margin_protection_rate'],
            'minimum_price_violation_count' => $readiness['minimum_price_violation_count'],
            'tenant_violation_count' => $readiness['tenant_violation_count'],
            'unexpected_push_count' => $readiness['unexpected_push_count'],
            'duplicate_action_count' => $readiness['duplicate_action_count'],
        ];

        return hash('sha256', json_encode($payload));
    }
}
