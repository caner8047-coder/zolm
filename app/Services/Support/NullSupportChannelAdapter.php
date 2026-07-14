<?php

namespace App\Services\Support;

use App\Models\SupportChannel;

class NullSupportChannelAdapter implements SupportChannelAdapterInterface
{
    public function key(): string { return 'unsupported'; }
    public function name(): string { return 'Desteklenmeyen Kanal'; }

    public function getCapabilities(?SupportChannel $channel = null): array
    {
        return [
            ['capability' => 'read_messages', 'status' => 'unavailable'],
            ['capability' => 'send_messages', 'status' => 'unavailable'],
            ['capability' => 'ai_suggestions', 'status' => 'unavailable'],
        ];
    }

    public function healthCheck(SupportChannel $channel): array
    {
        return ['status' => 'unsupported', 'message' => 'Bu kanal için mesaj API erişimi tanımlı değil.'];
    }

    public function syncConversations(SupportChannel $channel): array
    {
        return ['synced' => 0, 'message' => 'Desteklenmeyen kanal — sync yapılamaz'];
    }

    public function fetchMessages(SupportChannel $channel, string $conversationExternalId): array
    {
        return [];
    }

    public function canReply(SupportChannel $channel): bool
    {
        return false;
    }

    public function sendReply(SupportChannel $channel, string $conversationExternalId, string $message, ?string $idempotencyKey = null): array
    {
        return ['success' => false, 'message' => 'Bu kanal üzerinden mesaj gönderilemez'];
    }

    public function resolveOrderContext(SupportChannel $channel, string $externalConversationId): ?array
    {
        return null;
    }

    public function getOutboundTargetStatus(): string
    {
        return 'failed';
    }
}
