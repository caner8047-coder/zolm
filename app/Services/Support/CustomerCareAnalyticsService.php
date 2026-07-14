<?php

namespace App\Services\Support;

use App\Models\MarketplaceStore;
use App\Models\SupportAiCostEvent;
use App\Models\SupportAiRun;
use App\Models\SupportAgentAction;
use App\Models\SupportAnswerError;
use App\Models\SupportConversation;
use App\Models\SupportDispatchAttempt;
use App\Models\SupportMessage;
use App\Models\SupportPilotBaseline;
use App\Models\SupportPolicyDecision;
use App\Models\SupportSalesAttribution;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class CustomerCareAnalyticsService
{
    private const MIN_RATE_SAMPLE = 20;

    public function getStoreMetrics(int $storeId, int $days = 30, ?User $actor = null): array
    {
        $actor = $actor ?? Auth::user() ?? TenantContext::getSystemActor();
        TenantContext::enforceStoreAccess($storeId, $actor);

        $days = max(1, min(366, $days));
        $start = now()->subDays($days);
        $store = MarketplaceStore::findOrFail($storeId);
        $conversations = SupportConversation::where('store_id', $storeId)
            ->where('created_at', '>=', $start)
            ->with(['messages' => fn ($query) => $query->orderBy('created_at')])
            ->get();
        $conversationIds = $conversations->pluck('id');
        $messages = SupportMessage::whereIn('conversation_id', $conversationIds)
            ->where('created_at', '>=', $start)->get();
        $runs = SupportAiRun::where('store_id', $storeId)->where('created_at', '>=', $start)->get();

        $firstResponseSamples = [];
        $insideSamples = [];
        $outsideSamples = [];
        $resolutionSamples = [];
        $resolvedBy = ['ai' => 0, 'copilot' => 0, 'human' => 0];
        $afterHoursInbound = 0;
        $afterHoursAiResponded = 0;
        $afterHoursAiResolved = 0;
        foreach ($conversations as $conversation) {
            $inbound = $conversation->messages->first(fn ($message) => $message->direction === 'inbound');
            $outbound = $conversation->messages->first(fn ($message) => $message->direction === 'outbound'
                && (!$inbound || $message->created_at->gte($inbound->created_at)));
            if ($inbound && $outbound) {
                $seconds = $inbound->created_at->diffInSeconds($outbound->created_at);
                $firstResponseSamples[] = $seconds;
                if ($this->isBusinessHours($inbound->created_at, $store->timezone)) {
                    $insideSamples[] = $seconds;
                } else {
                    $outsideSamples[] = $seconds;
                }
            }
            $outsideBusinessHours = $inbound && !$this->isBusinessHours($inbound->created_at, $store->timezone);
            if ($outsideBusinessHours) {
                $afterHoursInbound++;
                if ($outbound?->sender_type === 'ai') $afterHoursAiResponded++;
            }
            if ($conversation->status === 'resolved' && $inbound) {
                $resolutionSamples[] = $inbound->created_at->diffInSeconds($conversation->updated_at);
                $hasAgent = $conversation->messages->contains(fn ($message) => $message->direction === 'outbound' && $message->sender_type === 'agent');
                $hasAi = $conversation->messages->contains(fn ($message) => $message->direction === 'outbound' && $message->sender_type === 'ai');
                $hasShadow = $runs->where('conversation_id', $conversation->id)->whereNotNull('shadow_match_score')->isNotEmpty();
                $resolvedBy[$hasShadow ? 'copilot' : ($hasAi && !$hasAgent ? 'ai' : 'human')]++;
                if ($outsideBusinessHours && $hasAi && !$hasAgent) $afterHoursAiResolved++;
            }
        }

        $total = $conversations->count();
        $resolvedCount = $conversations->where('status', 'resolved')->count();
        $handoffs = $conversations->where('ownership_status', 'human')->count();
        $open = $conversations->where('status', 'open')->count();
        $pending = $conversations->whereIn('status', ['pending', 'waiting'])->count();
        $aiDraftCount = $messages->where('sender_type', 'ai')->where('delivery_status', 'draft')->count();
        $aiAutoCount = $messages->where('sender_type', 'ai')->where('direction', 'outbound')->whereNotIn('delivery_status', ['draft', 'cancelled', 'failed'])->count();
        $humanReplyCount = $messages->where('sender_type', 'agent')->where('direction', 'outbound')->count();

        $policyDecisions = SupportPolicyDecision::where('store_id', $storeId)->where('created_at', '>=', $start)->get();
        $legacyPolicyBlocks = SupportAgentAction::whereHas('conversation', fn ($query) => $query->where('store_id', $storeId))
            ->where('action', 'policy_block')->where('created_at', '>=', $start)->count();
        $policyBlocks = $policyDecisions->isNotEmpty()
            ? $policyDecisions->where('allowed', false)->count()
            : $legacyPolicyBlocks;
        $sourceLessRuns = $runs->filter(fn ($run) => empty($run->sources_used_json))->count();
        $criticalErrors = SupportAnswerError::where('store_id', $storeId)->where('severity', 'critical')->where('created_at', '>=', $start)->count();
        $entityErrors = SupportAnswerError::where('store_id', $storeId)->where('created_at', '>=', $start)
            ->get()->filter(function ($error) {
                $rootCause = mb_strtolower((string) $error->root_cause_encrypted);
                return preg_match('/wrong.entity|yanlış.müşteri|yanlis.musteri|yanlış.sipariş|yanlis.siparis|yanlış.ürün|yanlis.urun/u', $rootCause) === 1;
            })->count();
        $dispatchAttempts = SupportDispatchAttempt::whereHas('dispatch.conversation', fn ($query) => $query->where('store_id', $storeId))
            ->where('created_at', '>=', $start)->get();
        $costEvents = SupportAiCostEvent::where('store_id', $storeId)->where('created_at', '>=', $start)->get();

        $shadowRuns = $runs->whereNotNull('shadow_match_score');
        $copilotAccepted = $shadowRuns->where('shadow_match_score', '>=', 95)->count();
        $copilotEdited = $shadowRuns->where('shadow_match_score', '<', 95)->count();
        $intentCounts = $this->intentCounts($messages->where('direction', 'inbound'));
        $repeatCount = collect($intentCounts)->filter(fn ($count) => $count > 1)->sum();
        $inboundCount = array_sum($intentCounts);

        $topics = [];
        foreach ($runs->whereNotNull('prompt_template_key')->groupBy('prompt_template_key') as $key => $topicRuns) {
            $sample = $topicRuns->count();
            $success = $topicRuns->whereNotIn('status', ['handoff', 'failed'])->count();
            $topics[$key] = [
                'name' => ucfirst(str_replace('_', ' ', $key)),
                'success_rate' => $sample >= self::MIN_RATE_SAMPLE ? round($success / $sample * 100, 1) : null,
                'total_runs' => $sample,
                'reliable' => $sample >= self::MIN_RATE_SAMPLE,
                'numerator' => $success,
                'denominator' => $sample,
            ];
        }

        $waiting = $conversations->filter(fn ($conversation) => $conversation->status === 'open'
            && $conversation->last_inbound_at
            && (!$conversation->last_outbound_at || $conversation->last_inbound_at->gt($conversation->last_outbound_at)));
        $breachedFirst = $waiting->filter(fn ($conversation) => $conversation->last_inbound_at->lte(now()->subMinutes(30)))->count()
            + collect($firstResponseSamples)->filter(fn ($seconds) => $seconds > 1800)->count();
        $breachedResolution = $conversations->filter(fn ($conversation) => $conversation->status === 'open' && $conversation->created_at->lte(now()->subHours(24)))->count()
            + collect($resolutionSamples)->filter(fn ($seconds) => $seconds > 86400)->count();

        $attributions = SupportSalesAttribution::where('store_id', $storeId)
            ->whereNotNull('verified_at')->where('created_at', '>=', $start)->get();
        $baseline = SupportPilotBaseline::where('store_id', $storeId)->first();
        $currentHumanSeconds = $this->average($firstResponseSamples);

        $meta = [
            'handoff_rate' => $this->definition($handoffs, $total, 'Temsilci sahipliğindeki konuşma / toplam konuşma', $start),
            'resolution_rate' => $this->definition($resolvedCount, $total, 'Çözülen konuşma / toplam konuşma', $start),
            'policy_block_rate' => $this->definition($policyBlocks, $policyDecisions->count(), 'Engellenen politika kararı / tüm politika kararları', $start),
            'source_less_rate' => $this->definition($sourceLessRuns, $runs->count(), 'Kaynaksız AI çalışması / tüm AI çalışmaları', $start),
            'repeat_question_rate' => $this->definition($repeatCount, $inboundCount, 'Tekrarlanan intent mesajı / tüm gelen mesajlar', $start),
            'after_hours_ai_response_rate' => $this->definition($afterHoursAiResponded, $afterHoursInbound, 'Mesai dışı AI ilk yanıtı alan konuşma / mesai dışı gelen konuşma', $start),
        ];

        return [
            'store_id' => $storeId,
            'period' => ['days' => $days, 'start' => $start->toIso8601String(), 'end' => now()->toIso8601String()],
            'minimum_rate_sample' => self::MIN_RATE_SAMPLE,
            'metric_meta' => $meta,
            'total_conversations' => $total,
            'open_conversations' => $open,
            'pending_conversations' => $pending,
            'handoff_count' => $handoffs,
            'ai_draft_count' => $aiDraftCount,
            'ai_auto_count' => $aiAutoCount,
            'human_reply_count' => $humanReplyCount,
            'handoff_rate' => $this->rate($handoffs, $total),
            'policy_block_count' => $policyBlocks,
            'policy_block_rate' => $this->rate($policyBlocks, $policyDecisions->count()),
            'source_less_count' => $sourceLessRuns,
            'source_less_rate' => $this->rate($sourceLessRuns, $runs->count()),
            'critical_error_count' => $criticalErrors,
            'entity_mismatch_count' => $entityErrors,
            'avg_first_response_time' => $this->roundedAverage($firstResponseSamples),
            'avg_first_response_business_hours' => $this->roundedAverage($insideSamples),
            'avg_first_response_after_hours' => $this->roundedAverage($outsideSamples),
            'after_hours_inbound_count' => $afterHoursInbound,
            'after_hours_ai_responded_count' => $afterHoursAiResponded,
            'after_hours_ai_resolved_count' => $afterHoursAiResolved,
            'after_hours_ai_response_rate' => $this->rate($afterHoursAiResponded, $afterHoursInbound),
            'first_response_sample_size' => count($firstResponseSamples),
            'first_response_business_hours_sample_size' => count($insideSamples),
            'first_response_after_hours_sample_size' => count($outsideSamples),
            'avg_resolution_time' => $this->roundedAverage($resolutionSamples),
            'resolution_sample_size' => count($resolutionSamples),
            'resolution_rate' => $this->rate($resolvedCount, $total),
            'resolved_by' => $resolvedBy,
            'copilot' => [
                'accepted' => $copilotAccepted,
                'edited' => $copilotEdited,
                'rejected' => null,
                'rejected_available' => false,
                'sample_size' => $shadowRuns->count(),
                'average_edit_distance' => $shadowRuns->isEmpty() ? null : round(100 - $shadowRuns->avg('shadow_match_score'), 1),
            ],
            'repeat_question_rate' => $this->rate($repeatCount, $inboundCount),
            'intent_counts' => $intentCounts,
            'queue_api_error_rate' => $this->rate($dispatchAttempts->where('status', 'failed')->count(), $dispatchAttempts->count()),
            'queue_api_attempts' => $dispatchAttempts->count(),
            'ai_cost_total' => $costEvents->whereNotNull('cost_estimate')->isEmpty()
                ? null
                : round((float) $costEvents->whereNotNull('cost_estimate')->sum('cost_estimate'), 6),
            'ai_cost_known_event_count' => $costEvents->whereNotNull('cost_estimate')->count(),
            'ai_cost_unknown_event_count' => $costEvents->whereNull('cost_estimate')->count(),
            'ai_average_latency_ms' => $runs->whereNotNull('latency_ms')->isEmpty()
                ? null
                : (int) round((float) $runs->whereNotNull('latency_ms')->avg('latency_ms')),
            'verified_sales_attribution' => [
                'available' => $attributions->isNotEmpty(),
                'count' => $attributions->count(),
                'revenue' => round((float) $attributions->sum('order_amount'), 2),
                'currency' => $attributions->pluck('currency')->filter()->unique()->count() === 1 ? $attributions->pluck('currency')->filter()->first() : null,
            ],
            'human_time_change' => [
                'available' => $baseline !== null && count($firstResponseSamples) >= self::MIN_RATE_SAMPLE,
                'baseline_seconds' => $baseline?->average_human_handle_seconds,
                'current_seconds' => $currentHumanSeconds,
                'change_percent' => $baseline && $baseline->average_human_handle_seconds > 0 && count($firstResponseSamples) >= self::MIN_RATE_SAMPLE
                    ? round(($currentHumanSeconds - $baseline->average_human_handle_seconds) / $baseline->average_human_handle_seconds * 100, 1)
                    : null,
            ],
            'topics' => $topics,
            'breached_first_response_count' => $breachedFirst,
            'breached_resolution_count' => $breachedResolution,
            'breached_conversations' => $conversations->filter(fn ($conversation) =>
                ($conversation->status === 'open' && $conversation->last_inbound_at?->lte(now()->subMinutes(30)))
                || ($conversation->status === 'open' && $conversation->created_at->lte(now()->subHours(24)))),
        ];
    }

    private function definition(int $numerator, int $denominator, string $formula, Carbon $start): array
    {
        return [
            'formula' => $formula, 'numerator' => $numerator, 'denominator' => $denominator,
            'sample_size' => $denominator, 'minimum_sample' => self::MIN_RATE_SAMPLE,
            'reliable' => $denominator >= self::MIN_RATE_SAMPLE,
            'period_start' => $start->toIso8601String(), 'period_end' => now()->toIso8601String(),
        ];
    }

    private function rate(int $numerator, int $denominator): float
    {
        return $denominator > 0 ? round($numerator / $denominator * 100, 1) : 0.0;
    }

    private function average(array $values): ?float
    {
        return $values === [] ? null : array_sum($values) / count($values);
    }

    private function roundedAverage(array $values): ?int
    {
        $average = $this->average($values);

        return $average === null ? null : (int) round($average);
    }

    private function isBusinessHours(Carbon $time, ?string $timezone): bool
    {
        $local = $time->copy()->timezone($timezone ?: 'Europe/Istanbul');
        return !$local->isWeekend() && $local->hour >= 9 && $local->hour < 18;
    }

    private function intentCounts($messages): array
    {
        $counts = [];
        foreach ($messages as $message) {
            $text = mb_strtolower((string) $message->body_encrypted);
            $intent = match (true) {
                preg_match('/kargo|teslim|takip/u', $text) === 1 => 'kargo',
                preg_match('/beden|ölçü|olcu|uyum/u', $text) === 1 => 'beden_uyum',
                preg_match('/stok|fiyat|ürün|urun/u', $text) === 1 => 'urun_stok',
                preg_match('/iade|iptal/u', $text) === 1 => 'iade_iptal',
                default => 'diger',
            };
            $counts[$intent] = ($counts[$intent] ?? 0) + 1;
        }
        return $counts;
    }
}
