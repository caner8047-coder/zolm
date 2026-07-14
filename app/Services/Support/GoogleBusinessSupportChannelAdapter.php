<?php

namespace App\Services\Support;

use App\Models\SupportChannel;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\SupportAgentAction;
use App\Models\IntegrationConnection;
use Illuminate\Support\Facades\Cache;

class GoogleBusinessSupportChannelAdapter implements SupportChannelAdapterInterface
{
    public function key(): string
    {
        return 'google_business';
    }

    public function name(): string
    {
        return 'Google Business Profile';
    }

    public function getCapabilities(?SupportChannel $channel = null): array
    {
        $enabled = config('customer-care.google_reviews_enabled', false);
        $sendStatus = 'unavailable';
        $readStatus = 'unavailable';

        if ($enabled && $channel) {
            $connection = $this->activeConnection($channel);
            $channelEnabled = $channel->is_enabled ?? false;
            $hasProvider = app()->bound(GoogleBusinessConnectorInterface::class);

            if ($connection && $channelEnabled) {
                $readStatus = 'available';
                if ($hasProvider) {
                    $sendStatus = 'available';
                }
            }
        }

        return [
            ['capability' => 'read_messages', 'status' => $readStatus], // maps to read_reviews
            ['capability' => 'send_messages', 'status' => $sendStatus], // maps to reply_reviews
            ['capability' => 'ai_suggestions', 'status' => 'available'],
            ['capability' => 'reputation_analytics', 'status' => $enabled ? 'available' : 'unavailable'],
        ];
    }

    public function healthCheck(SupportChannel $channel): array
    {
        if (!config('customer-care.google_reviews_enabled', false)) {
            return ['status' => 'error', 'message' => 'Google Business reviews özelliği devre dışı'];
        }

        $connection = IntegrationConnection::where('store_id', $channel->store_id)
            ->whereIn('provider', ['google_business', 'google', 'google_reviews'])
            ->whereIn('status', ['active', 'pending_verification', 'error'])
            ->latest('id')
            ->first();

        if (!$connection) {
            return ['status' => 'not_configured', 'message' => 'Google bağlantısı aktif değil'];
        }

        $connector = app(GoogleBusinessConnectorInterface::class);
        if (method_exists($connector, 'healthCheck')) {
            return $connector->healthCheck($connection);
        }

        return ['status' => 'ok', 'message' => 'Test connector bağlantısı aktif'];
    }

    public function syncConversations(SupportChannel $channel): array
    {
        return ['synced' => 0];
    }

    public function fetchMessages(SupportChannel $channel, string $conversationExternalId): array
    {
        return [];
    }

    public function canReply(SupportChannel $channel): bool
    {
        if (!config('customer-care.google_reviews_enabled', false)) {
            return false;
        }

        $connection = $this->activeConnection($channel);

        if (!$connection) {
            return false;
        }

        if (!app()->bound(GoogleBusinessConnectorInterface::class)) {
            return false;
        }

        return (bool) ($channel->is_enabled ?? false);
    }

    public function sendReply(SupportChannel $channel, string $conversationExternalId, string $message, ?string $idempotencyKey = null): array
    {
        // 1. Connection & Feature Flag Check (Fail-closed)
        if (!config('customer-care.google_reviews_enabled', false)) {
            return ['success' => false, 'message' => 'Google Business reviews özelliği devre dışı'];
        }

        if (!app()->bound(GoogleBusinessConnectorInterface::class)) {
            return ['success' => false, 'message' => 'Aktif Google GBP reply connector\'ü bulunamadı (fail-closed)'];
        }

        if (!preg_match('/^google_review_([A-Za-z0-9_-]+)$/', $conversationExternalId, $matches)) {
            return ['success' => false, 'message' => 'Geçersiz yorum formatı'];
        }

        $reviewId = $matches[1];

        $connection = $this->activeConnection($channel);

        if (!$connection) {
            return ['success' => false, 'message' => 'Aktif bir Google bağlantısı bulunamadı (fail-closed)'];
        }

        // Tenant Isolation Check
        $conversation = SupportConversation::where('external_conversation_id', $conversationExternalId)
            ->where('store_id', $channel->store_id)
            ->first();

        if (!$conversation) {
            return ['success' => false, 'message' => 'Yorum bulunamadı veya bu mağazaya ait değil'];
        }

        // Idempotency check ONLY after we verify the connector exists and we resolve it
        $lockKey = null;
        if ($idempotencyKey) {
            $lockKey = 'idemp_google_reply_' . md5($idempotencyKey);
            if (Cache::has($lockKey)) {
                return [
                    'success' => true,
                    'message' => 'Bu yanıt zaten gönderildi (Idempotent)',
                    'channel_message_id' => Cache::get($lockKey),
                    'is_duplicate' => true,
                ];
            }
        }

        // Resolve connector and send
        $connector = app(GoogleBusinessConnectorInterface::class);
        try {
            if (method_exists($connector, 'useConnection')) {
                $connector->useConnection($connection);
            }
            $channelMsgId = $connector->reply($reviewId, $message);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Google Business Profile API Gönderim Hatası: ' . $e->getMessage()];
        }

        if (empty($channelMsgId)) {
            return ['success' => false, 'message' => 'Google Business Profile API boş yanıt ID\'si döndü'];
        }

        if ($lockKey) {
            Cache::put($lockKey, $channelMsgId, now()->addHour());
        }

        // Log action only on actual successful delivery
        SupportAgentAction::create([
            'conversation_id' => $conversation->id,
            'user_id' => auth()->id() ?? \App\Services\Support\TenantContext::getSystemActor()?->id,
            'action' => 'google_review_replied',
            'details_json' => [
                'review_id' => $reviewId,
                'reply_message_id' => $channelMsgId,
            ]
        ]);

        return [
            'success' => true,
            'channel_message_id' => $channelMsgId,
            'message' => 'Yorum yanıtı yayınlandı'
        ];
    }

    public function resolveOrderContext(SupportChannel $channel, string $externalConversationId): ?array
    {
        return null;
    }

    public function getOutboundTargetStatus(): string
    {
        return 'sent';
    }

    private function activeConnection(SupportChannel $channel): ?IntegrationConnection
    {
        return IntegrationConnection::where('store_id', $channel->store_id)
            ->whereIn('provider', ['google_business', 'google', 'google_reviews'])
            ->where('status', 'active')
            ->first();
    }

    /**
     * Google yorumunu idempotent olarak projelendirir.
     * Raw payload support_message'a sızmaz.
     */
    public function projectReview(SupportChannel $channel, array $payload): array
    {
        if (!config('customer-care.google_reviews_enabled', false)) {
            return ['success' => false, 'message' => 'Google Business reviews özelliği devre dışı'];
        }

        if ((int)$channel->store_id !== (int)($payload['store_id'] ?? null)) {
            return ['success' => false, 'message' => 'Mağaza eşleşmesi başarısız (IDOR engeli)'];
        }

        $reviewId = $payload['review_id'] ?? null;
        if (!$reviewId) {
            return ['success' => false, 'message' => 'Missing review_id'];
        }

        $externalConversationId = 'google_review_' . $reviewId;
        $externalMessageId = 'google_msg_review_' . $reviewId;

        // Idempotency check
        $exists = SupportMessage::where('external_message_id', $externalMessageId)
            ->whereHas('conversation', fn ($query) => $query->where('support_channel_id', $channel->id)->where('store_id', $channel->store_id))
            ->exists();
        if ($exists) {
            return ['success' => true, 'message' => 'Mükerrer yorum (atlandı)', 'projected' => false];
        }

        $rating = (int)($payload['rating'] ?? 5);
        $reviewerName = $payload['reviewer_name'] ?? 'Google Kullanıcısı';
        $comment = $payload['comment'] ?? '';

        $conversation = SupportConversation::firstOrCreate(
            [
                'support_channel_id' => $channel->id,
                'external_conversation_id' => $externalConversationId,
            ],
            [
                'store_id' => $channel->store_id,
                'source_type' => 'google_business',
                'status' => 'open',
                'priority' => 'normal',
                'ai_mode' => 'manual',
                'last_message_at' => now(),
                'source_reference_json' => [
                    'rating' => $rating,
                    'reviewer_name' => $reviewerName,
                    'location_id' => $payload['location_id'] ?? null,
                ],
            ]
        );

        // Raw payload kesinlikle support_messages tablosuna sızmaz!
        $message = SupportMessage::create([
            'conversation_id' => $conversation->id,
            'external_message_id' => $externalMessageId,
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'message_type' => 'text',
            'body_encrypted' => $comment,
            'delivery_status' => 'received',
            'payload_json' => null, // DO NOT write raw payload here!
        ]);

        $conversation->update([
            'last_message_at' => now(),
            'last_inbound_at' => now()
        ]);

        return [
            'success' => true,
            'projected' => true,
            'conversation_id' => $conversation->id,
        ];
    }

    /**
     * Yorum itibar metriklerini (reputation analytics) hesaplar.
     * Veri yoksa sahte metrik uydurulmaz, empty array döner.
     */
    public function getReputationMetrics(int $storeId): array
    {
        if (!config('customer-care.google_reviews_enabled', false)) {
            return [];
        }

        $conversations = SupportConversation::where('store_id', $storeId)
            ->where('source_type', 'google_business')
            ->get();

        if ($conversations->isEmpty()) {
            return []; // empty state
        }

        $totalReviews = $conversations->count();
        $unansweredReviews = $conversations->where('status', 'open')->count();

        $ratings = [];
        $negativeReviewsCount = 0;
        $totalReplyTimeSeconds = 0;
        $repliedCount = 0;

        foreach ($conversations as $conv) {
            $ref = $conv->source_reference_json ?? [];
            $rating = (int)($ref['rating'] ?? 0);
            if ($rating > 0) {
                $ratings[] = $rating;
                if ($rating <= 2) {
                    $negativeReviewsCount++;
                }
            }

            $inbound = $conv->last_inbound_at;
            $outbound = $conv->last_outbound_at;
            if ($inbound && $outbound && $outbound->gt($inbound)) {
                $totalReplyTimeSeconds += $outbound->diffInSeconds($inbound);
                $repliedCount++;
            }
        }

        $avgRating = count($ratings) > 0 ? round(array_sum($ratings) / count($ratings), 1) : 0;
        $avgReplyTimeMinutes = $repliedCount > 0 ? round(($totalReplyTimeSeconds / $repliedCount) / 60, 1) : 0;

        return [
            'total_reviews' => $totalReviews,
            'unanswered_reviews' => $unansweredReviews,
            'average_rating' => $avgRating,
            'negative_reviews' => $negativeReviewsCount,
            'average_reply_time_minutes' => $avgReplyTimeMinutes,
        ];
    }
}
