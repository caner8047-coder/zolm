<?php

namespace App\Services\Support;

use App\Models\MarketplaceStore;
use App\Models\SupportChannel;
use App\Models\MarketplaceQuestion;
use App\Services\Support\TenantContext;

class HepsiburadaSupportChannelAdapter implements SupportChannelAdapterInterface
{
    public function key(): string { return 'hepsiburada'; }
    public function name(): string { return 'Hepsiburada'; }

    public function getCapabilities(?SupportChannel $channel = null): array
    {
        $sendMessagesStatus = 'unavailable';
        if ($channel) {
            $channel->load('store.connection');
            $store = $channel->store;
            $hasConnection = $store && $store->connection && $store->connection->status === 'configured';
            $isEnabled = (bool) $channel->is_enabled;
            $isHepsiburada = $store && $store->marketplace === 'hepsiburada';

            if ($hasConnection && $isEnabled && $isHepsiburada) {
                $sendMessagesStatus = 'available';
            }
        }

        return [
            ['capability' => 'read_messages', 'status' => 'available'],
            ['capability' => 'send_messages', 'status' => $sendMessagesStatus],
            ['capability' => 'sync_orders', 'status' => 'available'],
            ['capability' => 'sync_products', 'status' => 'available'],
            ['capability' => 'webhooks', 'status' => 'unavailable'],
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
        $channel->load('store.connection');
        $store = $channel->store;
        $hasConnection = $store && $store->connection && $store->connection->status === 'configured';
        $isEnabled = (bool) $channel->is_enabled;
        $isHepsiburada = $store && $store->marketplace === 'hepsiburada';

        if (!$hasConnection || !$isEnabled || !$isHepsiburada) {
            return false;
        }

        return $channel->hasCapability('send_messages');
    }

    public function sendReply(SupportChannel $channel, string $conversationExternalId, string $message, ?string $idempotencyKey = null): array
    {
        // Sıkı biçim kontrolü
        if (!preg_match('/^hepsiburada_questions_(\d+)$/', $conversationExternalId, $matches)) {
            return ['success' => false, 'message' => 'Geçersiz konuşma formatı veya soru ID bulunamadı'];
        }

        $questionId = (int)$matches[1];

        // Tenant Isolation IDOR Protection
        $question = MarketplaceQuestion::where('id', $questionId)
            ->where('store_id', $channel->store_id)
            ->first();

        if (!$question) {
            return ['success' => false, 'message' => 'Konuşma veya Soru bulunamadı ya da bu mağazaya ait değil'];
        }

        // Idempotency check
        $lockKey = null;
        if ($idempotencyKey) {
            $lockKey = "idemp_hepsiburada_reply_" . md5($idempotencyKey);
            if (\Illuminate\Support\Facades\Cache::has($lockKey)) {
                return [
                    'success' => true,
                    'message' => 'Bu mesaj zaten gönderilmiş (Idempotent)',
                    'channel_message_id' => \Illuminate\Support\Facades\Cache::get($lockKey),
                    'is_duplicate' => true,
                ];
            }
        }

        try {
            $service = app(\App\Services\Marketplace\MarketplaceQuestionAnswerService::class);
            $user = auth()->user() ?? TenantContext::getSystemActor();
            $log = $service->sendAnswer($question, $message, $user, null, null, 'support_outbox');

            if ($log->status === 'sent') {
                $channelMsgId = (string)($log->external_answer_id ?? uniqid('hepsiburada_'));
                if ($lockKey) {
                    \Illuminate\Support\Facades\Cache::put($lockKey, $channelMsgId, now()->addHour());
                }
                return ['success' => true, 'channel_message_id' => $channelMsgId];
            } else {
                return ['success' => false, 'message' => $log->error_message ?? 'Gönderim başarısız'];
            }
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function resolveOrderContext(SupportChannel $channel, string $externalConversationId): ?array
    {
        return [
            'channel' => 'hepsiburada',
            'external_conversation_id' => $externalConversationId,
        ];
    }

    public function getOutboundTargetStatus(): string
    {
        return 'sent';
    }
}
