<?php

namespace App\Services\Support;

use App\Models\SupportChannel;

interface SupportChannelAdapterInterface
{
    public function key(): string;
    public function name(): string;
    public function getCapabilities(): array;
    public function healthCheck(SupportChannel $channel): array;
    public function syncConversations(SupportChannel $channel): array;
    public function fetchMessages(SupportChannel $channel, string $conversationExternalId): array;
    public function canReply(SupportChannel $channel): bool;
    public function sendReply(SupportChannel $channel, string $conversationExternalId, string $message): array;
    public function resolveOrderContext(SupportChannel $channel, string $externalConversationId): ?array;
}
