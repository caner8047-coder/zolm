<?php

namespace App\Services\Support;

use App\Models\SupportAgentAction;
use App\Models\SupportConversation;
use Illuminate\Support\Facades\DB;

class CustomerCareHandoffService
{
    public function handoff(
        SupportConversation $conversation,
        string $reason,
        string $riskLevel,
        array $sources = [],
        ?string $aiSummary = null,
        ?int $aiRunId = null,
    ): SupportConversation {
        return DB::transaction(function () use ($conversation, $reason, $riskLevel, $sources, $aiSummary, $aiRunId) {
            $fresh = SupportConversation::query()->lockForUpdate()->findOrFail($conversation->id);

            if ($aiRunId && SupportAgentAction::where('conversation_id', $fresh->id)
                ->where('action', 'human_handoff')->where('details_json->ai_run_id', $aiRunId)->exists()) {
                return $fresh;
            }

            $previous = [
                'ownership_status' => $fresh->ownership_status,
                'ai_mode' => $fresh->ai_mode,
                'status' => $fresh->status,
            ];
            $fresh->update([
                'ownership_status' => 'human',
                'ai_mode' => 'handoff',
                'status' => $fresh->status === 'resolved' ? 'resolved' : 'pending',
                'priority' => in_array($riskLevel, ['high', 'critical'], true) ? 'high' : $fresh->priority,
                'version' => ((int) $fresh->version) + 1,
            ]);

            app(CustomerCareRoutingService::class)->route($fresh);

            $summary = app(Security\PiiRedactor::class)->maskPii(
                trim(strip_tags((string) ($aiSummary ?: 'AI yanıtı müşteriye gönderilmedi; temsilci incelemesi gerekiyor.')))
            );
            SupportAgentAction::create([
                'conversation_id' => $fresh->id,
                'action' => 'human_handoff',
                'details_json' => [
                    'reason' => mb_substr(trim($reason), 0, 1000),
                    'risk_level' => $riskLevel,
                    'sources' => $sources,
                    'ai_summary' => mb_substr($summary, 0, 2000),
                    'ai_run_id' => $aiRunId,
                    'previous_state' => $previous,
                    'lock' => 'human_owned',
                    'released_only_by_authorized_user' => true,
                ],
            ]);

            return $fresh;
        });
    }
}
