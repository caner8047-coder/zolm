<?php

namespace App\Services\Support;

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\SupportAgentAction;

class SupportReplyService
{
    /**
     * Temsilci yanıtı gönder
     */
    public function sendAgentReply(SupportConversation $conversation, string $message, int $userId): array
    {
        $adapter = app(SupportChannelManager::class)->resolveForChannel($conversation->channel);

        if (!$adapter->canReply($conversation->channel)) {
            return ['success' => false, 'message' => 'Bu kanalda mesaj gönderme yetkisi yok'];
        }

        // Mesajı kaydet
        $supportMessage = SupportMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'sender_type' => 'agent',
            'message_type' => 'text',
            'body_encrypted' => $message,
            'body_preview' => mb_substr($message, 0, 100),
            'sent_at' => now(),
            'delivery_status' => 'sending',
        ]);

        // Kanala gönder
        $result = $adapter->sendReply(
            $conversation->channel,
            $conversation->external_conversation_id,
            $message,
        );

        if ($result['success']) {
            $supportMessage->update(['delivery_status' => 'sent']);
            $conversation->update(['last_outbound_at' => now(), 'last_message_at' => now()]);

            SupportAgentAction::create([
                'conversation_id' => $conversation->id,
                'message_id' => $supportMessage->id,
                'user_id' => $userId,
                'action' => 'replied',
                'details_json' => ['length' => mb_strlen($message)],
            ]);

            return ['success' => true, 'message_id' => $supportMessage->id];
        }

        $supportMessage->update(['delivery_status' => 'failed']);

        return ['success' => false, 'message' => $result['message'] ?? 'Gönderim başarısız'];
    }
}
