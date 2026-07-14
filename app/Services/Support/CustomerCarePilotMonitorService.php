<?php

namespace App\Services\Support;

use App\Models\SupportDispatch;
use App\Models\SupportAgentAction;
use App\Models\SupportMessage;
use App\Models\SupportAiRun;
use App\Models\User;
use App\Services\Support\AI\CustomerCareGoldenEvalGateService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class CustomerCarePilotMonitorService
{
    /**
     * Store bazlı pilot otomasyon metriklerini ve circuit breaker durumunu döner.
     */
    public function getStoreMetrics(int $storeId, ?User $actor = null): array
    {
        $actor = $actor ?? Auth::user() ?? TenantContext::getSystemActor();
        TenantContext::enforceStoreAccess($storeId, $actor);

        $maxFailures = (int) config('customer-care.max_dispatch_failures_15m', 3);
        $maxPolicyBlocks = (int) config('customer-care.max_policy_blocks_15m', 5);
        $maxPerHour = (int) config('customer-care.auto_reply_max_per_hour', 0);

        // 1. Son 15 dk dispatch failures count
        $dispatchFailures15m = SupportDispatch::whereHas('conversation', function ($q) use ($storeId) {
            $q->where('store_id', $storeId);
        })
        ->where('status', 'failed')
        ->where('updated_at', '>=', now()->subMinutes(15))
        ->count();

        // 2. Son 15 dk policy block count
        $policyBlocks15m = SupportAgentAction::whereHas('conversation', function ($q) use ($storeId) {
            $q->where('store_id', $storeId);
        })
        ->where('action', 'policy_block')
        ->where('created_at', '>=', now()->subMinutes(15))
        ->count();

        // 3. Outbox backlog (pending ve failed olan dispatch'ler)
        $outboxBacklog = SupportDispatch::whereHas('conversation', function ($q) use ($storeId) {
            $q->where('store_id', $storeId);
        })
        ->whereIn('status', ['pending', 'failed'])
        ->count();

        // 4. AI handoff count (toplam)
        $handoffCount = SupportAiRun::where('store_id', $storeId)
            ->where('status', 'handoff')
            ->count();

        // 5. Automatic reply count (toplam giden ve draft olmayan AI mesajları)
        $autoReplyCount = SupportMessage::whereHas('conversation', function ($q) use ($storeId) {
            $q->where('store_id', $storeId);
        })
        ->where('sender_type', 'ai')
        ->where('direction', 'outbound')
        ->where('delivery_status', '!=', 'draft')
        ->count();

        $autoReplyCount1h = SupportMessage::whereHas('conversation', function ($q) use ($storeId) {
            $q->where('store_id', $storeId);
        })
        ->where('sender_type', 'ai')
        ->where('direction', 'outbound')
        ->where('delivery_status', 'sent')
        ->where('created_at', '>=', now()->subHour())
        ->count();

        // 7. Average confidence score of AI runs
        $averageConfidence = SupportAiRun::where('store_id', $storeId)
            ->where('status', 'draft')
            ->whereNotNull('confidence_score')
            ->avg('confidence_score');

        // 8. Latest golden evaluation
        $evalEvidence = app(CustomerCareGoldenEvalGateService::class)->evaluate($storeId, 'tr');
        $latestGoldenEval = $evalEvidence['run'] ? [
            'run_id' => $evalEvidence['run']->id,
            'average_score' => $evalEvidence['metrics']['average_score'],
            'passed_eval_gate' => $evalEvidence['passed'],
            'evidence_code' => $evalEvidence['code'],
            'detail' => $evalEvidence['detail'],
            'run_at' => $evalEvidence['run']->finished_at?->toIso8601String(),
        ] : null;

        // Circuit Breaker Durum Tespiti
        $forcedOpen = Cache::get("circuit_breaker_forced_open_{$storeId}", false);
        $circuitBreakerEnabled = config('customer-care.circuit_breaker_enabled', false);

        $isTripped = false;
        $tripReason = null;

        if ($forcedOpen) {
            $isTripped = true;
            $tripReason = 'Manual Override (Forced Open)';
        } elseif ($circuitBreakerEnabled) {
            if ($dispatchFailures15m >= $maxFailures) {
                $isTripped = true;
                $tripReason = 'Son 15 dk dispatch hata limiti aşıldı';
            } elseif ($policyBlocks15m >= $maxPolicyBlocks) {
                $isTripped = true;
                $tripReason = 'Son 15 dk politika engelleme limiti aşıldı';
            } elseif ($maxPerHour <= 0) {
                $isTripped = true;
                $tripReason = 'Saatlik otomatik yanıt limiti pozitif tanımlanmamış';
            } elseif ($autoReplyCount1h >= $maxPerHour) {
                $isTripped = true;
                $tripReason = 'Saatlik otomatik yanıt limiti aşıldı';
            }
        }

        $circuitBreakerStatus = $forcedOpen || $isTripped
            ? 'open'
            : ($circuitBreakerEnabled ? 'closed' : 'disabled');

        return [
            'store_id' => $storeId,
            'circuit_breaker_status' => $circuitBreakerStatus,
            'trip_reason' => $tripReason,
            'manual_override' => $forcedOpen,
            'dispatch_failures_15m' => $dispatchFailures15m,
            'max_dispatch_failures_15m' => $maxFailures,
            'policy_blocks_15m' => $policyBlocks15m,
            'max_policy_blocks_15m' => $maxPolicyBlocks,
            'outbox_backlog' => $outboxBacklog,
            'handoff_count' => $handoffCount,
            'auto_reply_count' => $autoReplyCount,
            'auto_reply_count_1h' => $autoReplyCount1h,
            'auto_reply_max_per_hour' => $maxPerHour,
            'average_confidence' => $averageConfidence === null ? null : (float) $averageConfidence,
            'latest_golden_eval' => $latestGoldenEval,
        ];
    }
}
