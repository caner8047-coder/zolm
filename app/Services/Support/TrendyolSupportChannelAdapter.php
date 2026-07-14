<?php

namespace App\Services\Support;

use App\Models\MarketplaceStore;
use App\Models\SupportChannel;
use App\Models\SupportChannelCapability;

class TrendyolSupportChannelAdapter implements SupportChannelAdapterInterface
{
    public function key(): string { return 'trendyol'; }
    public function name(): string { return 'Trendyol'; }

    public function getCapabilities(?SupportChannel $channel = null): array
    {
        $sendMessagesStatus = 'available';
        if ($channel) {
            $channel->load('store.connection');
            $store = $channel->store;
            $hasConnection = $store && $store->connection && $store->connection->status === 'configured';
            $isEnabled = (bool) $channel->is_enabled;
            if (!$hasConnection || !$isEnabled) {
                $sendMessagesStatus = 'unavailable';
            }
        }

        return [
            ['capability' => 'read_messages', 'status' => 'available'],
            ['capability' => 'send_messages', 'status' => $sendMessagesStatus],
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
        $channel->load('store.connection');
        $store = $channel->store;
        $hasConnection = $store && $store->connection && $store->connection->status === 'configured';
        $isEnabled = (bool) $channel->is_enabled;

        if (!$hasConnection || !$isEnabled) {
            return false;
        }

        return $channel->hasCapability('send_messages');
    }

    public function sendReply(SupportChannel $channel, string $conversationExternalId, string $message, ?string $idempotencyKey = null): array
    {
        // Sıkı biçim kontrolü
        if (!preg_match('/^trendyol_questions_(\d+)$/', $conversationExternalId, $matches)) {
            return ['success' => false, 'message' => 'Geçersiz konuşma formatı veya soru ID bulunamadı'];
        }

        $questionId = (int)$matches[1];

        // Tenant Isolation IDOR Protection
        $question = \App\Models\MarketplaceQuestion::where('id', $questionId)
            ->where('store_id', $channel->store_id)
            ->first();

        if (!$question) {
            return ['success' => false, 'message' => 'Konuşma veya Soru bulunamadı ya da bu mağazaya ait değil'];
        }

        // Idempotency check
        $lockKey = null;
        if ($idempotencyKey) {
            $lockKey = "idemp_trendyol_reply_" . md5($idempotencyKey);
            if (\Illuminate\Support\Facades\Cache::has($lockKey)) {
                return [
                    'success' => true,
                    'message' => 'Bu mesaj zaten gönderilmiş (Idempotent)',
                    'channel_message_id' => \Illuminate\Support\Facades\Cache::get($lockKey),
                    'is_duplicate' => true,
                ];
            }
        }

        $service = app(\App\Services\Marketplace\MarketplaceQuestionAnswerService::class);
        $user = auth()->user() ?? TenantContext::getSystemActor();
        $log = $service->sendAnswer($question, $message, $user, null, null, 'support_outbox');

        if ($log->status === 'sent') {
            $channelMsgId = (string)($log->external_answer_id ?? uniqid('trendyol_'));
            if ($lockKey) {
                \Illuminate\Support\Facades\Cache::put($lockKey, $channelMsgId, now()->addHour());
            }
            return ['success' => true, 'channel_message_id' => $channelMsgId];
        } else {
            return ['success' => false, 'message' => $log->error_message ?? 'Gönderim başarısız'];
        }
    }

    public function resolveOrderContext(SupportChannel $channel, string $externalConversationId): ?array
    {
        return [
            'channel' => 'trendyol',
            'external_conversation_id' => $externalConversationId,
        ];
    }

    public function getOutboundTargetStatus(): string
    {
        return 'sent';
    }
}
