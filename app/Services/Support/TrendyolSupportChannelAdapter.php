<?php

namespace App\Services\Support;

use App\Models\MarketplaceStore;
use App\Models\SupportChannel;
use App\Models\SupportChannelCapability;

class TrendyolSupportChannelAdapter implements SupportChannelAdapterInterface
{
    public function key(): string { return 'trendyol'; }
    public function name(): string { return 'Trendyol'; }

    public function getCapabilities(): array
    {
        // Trendyol için resmi mesaj/soru API mevcut — connector'dan doğrula
        return [
            ['capability' => 'read_messages', 'status' => 'available'],
            ['capability' => 'send_messages', 'status' => 'available'],
            ['capability' => 'sync_orders', 'status' => 'available'],
            ['capability' => 'sync_products', 'status' => 'available'],
            ['capability' => 'webhooks', 'status' => 'available'],
            ['capability' => 'attachments', 'status' => 'unavailable'],
            ['capability' => 'ai_suggestions', 'status' => 'available'],
        ];
    }

    public function healthCheck(SupportChannel $channel): array
    {
        $store = $channel->store;
        if (!$store || $store->marketplace !== 'trendyol') {
            return ['status' => 'error', 'message' => 'Trendyol mağazası bulunamadı'];
        }

        $connection = $store->connection;
        if (!$connection || $connection->status !== 'configured') {
            return ['status' => 'not_configured', 'message' => 'Trendyol bağlantısı tanımlı değil'];
        }

        return ['status' => 'ok', 'message' => 'Trendyol bağlantısı aktif'];
    }

    public function syncConversations(SupportChannel $channel): array
    {
        // Trendyol soru sistemi connector üzerinden senkronize edilir
        // Mevcut MarketplaceQuestionSyncService kullanılır
        return ['synced' => 0, 'message' => 'Trendyol sync mevcut connector üzerinden yönetilir'];
    }

    public function fetchMessages(SupportChannel $channel, string $conversationExternalId): array
    {
        // Trendyol soru cevaplama mevcut connector'da var
        return [];
    }

    public function canReply(SupportChannel $channel): bool
    {
        return $channel->hasCapability('send_messages');
    }

    public function sendReply(SupportChannel $channel, string $conversationExternalId, string $message): array
    {
        // Mevcut MarketplaceQuestionAnswerService kullanılır
        return ['success' => false, 'message' => 'Trendyol yanıtı mevcut akış üzerinden'];
    }

    public function resolveOrderContext(SupportChannel $channel, string $externalConversationId): ?array
    {
        return [
            'channel' => 'trendyol',
            'external_conversation_id' => $externalConversationId,
        ];
    }
}
