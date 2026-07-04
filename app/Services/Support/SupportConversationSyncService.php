<?php

namespace App\Services\Support;

use App\Models\SupportChannel;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\SupportSyncCursor;

class SupportConversationSyncService
{
    /**
     * Tek bir kanalın konuşmalarını senkronize et
     */
    public function syncChannel(SupportChannel $channel): array
    {
        $adapter = app(SupportChannelManager::class)->resolveForChannel($channel);

        if (!$channel->hasCapability('read_messages')) {
            return ['synced' => 0, 'message' => 'Read messages capability mevcut değil'];
        }

        $result = $adapter->syncConversations($channel);

        // Cursor güncelle
        SupportSyncCursor::updateOrCreate(
            ['support_channel_id' => $channel->id, 'sync_type' => 'conversations'],
            ['last_success_at' => now(), 'cursor_value' => $result['cursor'] ?? null]
        );

        $channel->update(['last_sync_at' => now()]);

        return $result;
    }

    /**
     * Tüm aktif kanalları senkronize et
     */
    public function syncAllActiveChannels(): array
    {
        $channels = SupportChannel::active()->get();
        $results = [];

        foreach ($channels as $channel) {
            try {
                $results[$channel->key] = $this->syncChannel($channel);
            } catch (\Throwable $e) {
                $channel->update(['status' => 'error']);
                $results[$channel->key] = ['error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Tek bir konuşmanın mesajlarını çek
     */
    public function fetchMessages(SupportConversation $conversation): array
    {
        $adapter = app(SupportChannelManager::class)->resolveForChannel($conversation->channel);

        if (!$conversation->channel->hasCapability('read_messages')) {
            return [];
        }

        return $adapter->fetchMessages($conversation->channel, $conversation->external_conversation_id);
    }
}
