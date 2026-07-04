<?php

namespace App\Services\WhatsApp;

use App\Models\WaConversation;
use App\Models\WaHandoff;

class HumanHandoffService
{
    /**
     * Temsilci devri başlat
     */
    public function initiateHandoff(WaConversation $conversation, string $reason, ?string $summary = null, ?int $triggeredByAiRunId = null): WaHandoff
    {
        $handoff = WaHandoff::create([
            'conversation_id' => $conversation->id,
            'contact_id' => $conversation->contact_id,
            'store_id' => $conversation->store_id,
            'triggered_by_ai_run_id' => $triggeredByAiRunId,
            'reason' => $reason,
            'summary' => $summary,
            'status' => 'pending',
        ]);

        $conversation->update([
            'ai_status' => 'handed_off',
            'handoff_status' => 'pending',
        ]);

        app(AuditLogService::class)->log(
            'handoff_initiated',
            'wa_handoff',
            $handoff->id,
            ['reason' => $reason, 'conversation_id' => $conversation->id],
        );

        return $handoff;
    }

    /**
     * Temsilciyi ata
     */
    public function assign(WaHandoff $handoff, int $userId): void
    {
        $handoff->update([
            'assigned_user_id' => $userId,
            'assigned_at' => now(),
            'status' => 'assigned',
        ]);

        $handoff->conversation->update(['assigned_user_id' => $userId]);

        app(AuditLogService::class)->log(
            'handoff_assigned',
            'wa_handoff',
            $handoff->id,
            ['user_id' => $userId],
        );
    }

    /**
     * Konuşmayı çöz ve AI'a geri bırak
     */
    public function resolve(WaHandoff $handoff, string $resolution, ?int $userId = null): void
    {
        $handoff->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolution' => $resolution,
        ]);

        $handoff->conversation->update([
            'ai_status' => 'active',
            'handoff_status' => null,
            'assigned_user_id' => null,
        ]);

        app(AuditLogService::class)->log(
            'handoff_resolved',
            'wa_handoff',
            $handoff->id,
            ['resolution' => $resolution],
        );
    }

    /**
     * Bekleyen devirleri listele
     */
    public function getPending(?int $storeId = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = WaHandoff::where('status', 'pending')
            ->with('contact', 'conversation')
            ->orderByDesc('created_at');

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        return $query->get();
    }
}
