<?php

namespace App\Services\Support;

use App\Models\MarketplaceStore;
use App\Models\SupportChannel;

class HepsiburadaSupportChannelAdapter implements SupportChannelAdapterInterface
{
    public function key(): string { return 'hepsiburada'; }
    public function name(): string { return 'Hepsiburada'; }

    public function getCapabilities(): array
    {
        return [
            ['capability' => 'read_messages', 'status' => 'available'],
            ['capability' => 'send_messages', 'status' => 'available'],
            ['capability' => 'sync_orders', 'status' => 'available'],
            ['capability' => 'sync_products', 'status' => 'available'],
            ['capability' => 'webhooks', 'status' => 'unknown'],
            ['capability' => 'attachments', 'status' => 'unavailable'],
            ['capability' => 'ai_suggestions', 'status' => 'available'],
        ];
    }

    public function healthCheck(SupportChannel $channel): array
    {
        $store = $channel->store;
        if (!$store || $store->marketplace !== 'hepsiburada') {
            return ['status' => 'error', 'message' => 'Hepsiburada mağazası bulunamadı'];
        }

        $connection = $store->connection;
        if (!$connection || $connection->status !== 'configured') {
            return ['status' => 'not_configured', 'message' => 'Hepsiburada bağlantısı tanımlı değil'];
        }

        return ['status' => 'ok', 'message' => 'Hepsiburada bağlantısı aktif'];
    }

    public function syncConversations(SupportChannel $channel): array
    {
        return ['synced' => 0, 'message' => 'Hepsiburada sync mevcut connector üzerinden yönetilir'];
    }

    public function fetchMessages(SupportChannel $channel, string $conversationExternalId): array
    {
        return [];
    }

    public function canReply(SupportChannel $channel): bool
    {
        return $channel->hasCapability('send_messages');
    }

    public function sendReply(SupportChannel $channel, string $conversationExternalId, string $message): array
    {
        return ['success' => false, 'message' => 'Hepsiburada yanıtı mevcut akış üzerinden'];
    }

    public function resolveOrderContext(SupportChannel $channel, string $externalConversationId): ?array
    {
        return [
            'channel' => 'hepsiburada',
            'external_conversation_id' => $externalConversationId,
        ];
    }
}
