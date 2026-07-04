<?php

namespace App\Services\WhatsApp\Tools;

use App\Models\WaConversation;
use App\Models\WaHandoff;

class HumanHandoffTool implements AiTool
{
    public function name(): string { return 'human_handoff'; }

    public function description(): string
    {
        return 'Konuşmayı destek ekibine devreder. Neden, intent ve özet kaydı oluşturur.';
    }

    public function execute(array $params, int $storeId, ?int $contactId = null): array
    {
        $conversationId = $params['conversation_id'] ?? null;
        $reason = $params['reason'] ?? 'customer_request';
        $summary = $params['summary'] ?? '';

        if (!$conversationId) {
            return ['success' => false, 'message' => 'Konuşma ID gerekli.'];
        }

        $conversation = WaConversation::find($conversationId);
        if (!$conversation) {
            return ['success' => false, 'message' => 'Konuşma bulunamadı.'];
        }

        // Zaten devredilmiş mi?
        $existing = WaHandoff::where('conversation_id', $conversationId)
            ->where('status', 'pending')
            ->exists();

        if ($existing) {
            return ['success' => false, 'message' => 'Konuşma zaten devredilmiş durumda.'];
        }

        $handoff = WaHandoff::create([
            'conversation_id' => $conversationId,
            'contact_id' => $conversation->contact_id,
            'store_id' => $conversation->store_id,
            'reason' => $reason,
            'summary' => $summary,
            'status' => 'pending',
        ]);

        // Conversation durumunu güncelle
        $conversation->update([
            'ai_status' => 'handed_off',
            'handoff_status' => 'pending',
        ]);

        return [
            'success' => true,
            'handoff_id' => $handoff->id,
            'message' => 'Konuşma destek ekibine devredildi.',
        ];
    }
}
