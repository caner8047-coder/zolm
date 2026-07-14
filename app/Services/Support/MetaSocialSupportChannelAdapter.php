<?php

namespace App\Services\Support;

use App\Models\SupportChannel;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\SupportAgentAction;
use App\Models\IntegrationConnection;
use Illuminate\Support\Facades\Cache;

class MetaSocialSupportChannelAdapter implements SupportChannelAdapterInterface
{
    private string $key;
    private string $name;

    public function __construct(string $key = 'instagram')
    {
        $this->key = $key;
        $this->name = match ($key) {
            'instagram' => 'Instagram',
            'facebook' => 'Facebook',
            'meta_social' => 'Meta Social',
            default => 'Meta Social',
        };
    }

    public function key(): string
    {
        return $this->key;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function getCapabilities(?SupportChannel $channel = null): array
    {
        $enabled = config('customer-care.meta_social_enabled', false);
        $sendStatus = 'unavailable';
        $readStatus = 'unavailable';

        if ($enabled && $channel) {
            $connection = $this->activeConnection($channel);
            $channelEnabled = $channel->is_enabled ?? false;
            $hasProvider = app()->bound(MetaSocialConnectorInterface::class);

            if ($connection && $channelEnabled) {
                $readStatus = 'available';
                if ($hasProvider) {
                    $sendStatus = 'available';
                }
            }
        }

        return [
            ['capability' => 'read_messages', 'status' => $readStatus],
            ['capability' => 'send_messages', 'status' => $sendStatus],
            ['capability' => 'read_comments', 'status' => $readStatus],
            ['capability' => 'reply_comments', 'status' => $sendStatus],
            ['capability' => 'attachments', 'status' => 'unavailable'],
            ['capability' => 'ai_suggestions', 'status' => 'available'],
        ];
    }

    public function healthCheck(SupportChannel $channel): array
    {
        if (!config('customer-care.meta_social_enabled', false)) {
            return ['status' => 'error', 'message' => 'Meta Social özelliği devre dışı'];
        }

        $connection = IntegrationConnection::where('store_id', $channel->store_id)
            ->whereIn('provider', $this->providerAliases())
            ->whereIn('status', ['active', 'pending_verification', 'error'])
            ->latest('id')
            ->first();

        if (!$connection) {
            return ['status' => 'not_configured', 'message' => 'Meta bağlantısı aktif değil'];
        }

        $connector = app(MetaSocialConnectorInterface::class);
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
        if (!config('customer-care.meta_social_enabled', false)) {
            return false;
        }

        $connection = $this->activeConnection($channel);

        if (!$connection) {
            return false;
        }

        if (!app()->bound(MetaSocialConnectorInterface::class)) {
            return false;
        }

        return (bool) ($channel->is_enabled ?? false);
    }

    public function sendReply(SupportChannel $channel, string $conversationExternalId, string $message, ?string $idempotencyKey = null): array
    {
        // 1. Connection & Feature Flag Check (Fail-closed)
        if (!config('customer-care.meta_social_enabled', false)) {
            return ['success' => false, 'message' => 'Meta Social özelliği devre dışı'];
        }

        if (!app()->bound(MetaSocialConnectorInterface::class)) {
            return ['success' => false, 'message' => 'Aktif Meta gönderim connector\'ü bulunamadı (fail-closed)'];
        }

        if (!preg_match('/^' . $this->key . '_thread_([A-Za-z0-9_-]+)$/', $conversationExternalId, $matches)) {
            return ['success' => false, 'message' => 'Geçersiz konuşma formatı'];
        }

        $threadId = $matches[1];

        $connection = $this->activeConnection($channel);

        if (!$connection) {
            return ['success' => false, 'message' => 'Aktif bir Meta bağlantısı bulunamadı (fail-closed)'];
        }

        // Tenant Isolation Check
        $conversation = SupportConversation::where('external_conversation_id', $conversationExternalId)
            ->where('store_id', $channel->store_id)
            ->first();

        if (!$conversation) {
            return ['success' => false, 'message' => 'Konuşma bulunamadı veya bu mağazaya ait değil'];
        }

        // Idempotency check ONLY after we verify the connector exists and we resolve it
        $lockKey = null;
        if ($idempotencyKey) {
            $lockKey = 'idemp_meta_reply_' . md5($idempotencyKey);
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
        $connector = app(MetaSocialConnectorInterface::class);
        try {
            if (method_exists($connector, 'useConnection')) {
                $connector->useConnection($connection);
            }
            $channelMsgId = $connector->send($this->key, $threadId, $message);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Meta Social API Gönderim Hatası: ' . $e->getMessage()];
        }

        if (empty($channelMsgId)) {
            return ['success' => false, 'message' => 'Meta Social API boş mesaj ID\'si döndü'];
        }

        if ($lockKey) {
            Cache::put($lockKey, $channelMsgId, now()->addHour());
        }

        // Log the handoff only on actual successful delivery
        SupportAgentAction::create([
            'conversation_id' => $conversation->id,
            'user_id' => auth()->id() ?? \App\Services\Support\TenantContext::getSystemActor()?->id,
            'action' => 'meta_outbox_handoff',
            'details_json' => [
                'thread_id' => $threadId,
                'channel_message_id' => $channelMsgId,
                'channel_key' => $this->key,
                'thread_type' => $conversation->source_reference_json['thread_type'] ?? 'dm',
            ]
        ]);

        return [
            'success' => true,
            'channel_message_id' => $channelMsgId,
            'message' => 'Yanıt iletildi'
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
            ->whereIn('provider', $this->providerAliases())
            ->where('status', 'active')
            ->first();
    }

    /**
     * @return string[]
     */
    private function providerAliases(): array
    {
        return match ($this->key) {
            'meta_social' => ['meta_social', 'meta', 'instagram', 'facebook'],
            'instagram' => ['instagram', 'meta_social', 'meta'],
            'facebook' => ['facebook', 'meta_social', 'meta'],
            default => [$this->key],
        };
    }

    /**
     * Webhook payload'larını güvenli ve idempotent olarak projelendirir.
     * Raw webhook payload support_messages.metadata_json içerisine sızmaz.
     */
    public function projectInboundWebhook(SupportChannel $channel, array $payload): array
    {
        if (!config('customer-care.meta_social_enabled', false)) {
            return ['success' => false, 'message' => 'Meta Social özelliği devre dışı'];
        }

        if ((int)$channel->store_id !== (int)($payload['store_id'] ?? null)) {
            return ['success' => false, 'message' => 'Mağaza eşleşmesi başarısız (IDOR engeli)'];
        }

        $eventId = $payload['event_id'] ?? null;
        if (!$eventId) {
            return ['success' => false, 'message' => 'Missing event_id'];
        }

        $externalMessageId = $this->key . '_event_' . $eventId;
        $exists = SupportMessage::where('external_message_id', $externalMessageId)
            ->whereHas('conversation', fn ($query) => $query->where('support_channel_id', $channel->id)->where('store_id', $channel->store_id))
            ->exists();
        if ($exists) {
            return ['success' => true, 'message' => 'Mükerrer event (atlandı)', 'projected' => false];
        }

        $threadId = $payload['thread_id'] ?? null;
        $threadType = $payload['thread_type'] ?? 'dm'; // 'dm' veya 'comment'
        $senderId = $payload['sender_id'] ?? null;
        $body = $payload['body'] ?? '';

        if (!$threadId || !$senderId) {
            return ['success' => false, 'message' => 'Missing thread_id or sender_id'];
        }

        $externalConversationId = $this->key . '_thread_' . $threadId;
        $conversation = SupportConversation::firstOrCreate(
            [
                'support_channel_id' => $channel->id,
                'external_conversation_id' => $externalConversationId,
            ],
            [
                'store_id' => $channel->store_id,
                'source_type' => $this->key,
                'status' => 'open',
                'priority' => 'normal',
                'ai_mode' => 'manual',
                'last_message_at' => now(),
                'source_reference_json' => [
                    'thread_type' => $threadType,
                    'meta_sender_id' => $senderId,
                ],
            ]
        );

        // Raw webhook payload kesinlikle support_messages tablosuna sızmaz!
        $message = SupportMessage::create([
            'conversation_id' => $conversation->id,
            'external_message_id' => $externalMessageId,
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'message_type' => 'text',
            'body_encrypted' => $body,
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
            'message_id' => $message->id,
        ];
    }
}
