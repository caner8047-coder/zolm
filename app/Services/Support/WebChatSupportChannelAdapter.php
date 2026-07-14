<?php

namespace App\Services\Support;

use App\Models\SupportChannel;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\SupportAgentAction;
use App\Models\IntegrationConnection;
use Illuminate\Support\Facades\Cache;

class WebChatSupportChannelAdapter implements SupportChannelAdapterInterface
{
    public function key(): string
    {
        return 'web_chat';
    }

    public function name(): string
    {
        return 'Site Live Chat Widget';
    }

    public function getCapabilities(?SupportChannel $channel = null): array
    {
        $enabled = config('customer-care.web_chat_enabled', false);
        $sendStatus = 'unavailable';
        $readStatus = 'unavailable';

        if ($enabled && $channel) {
            $connection = IntegrationConnection::where('store_id', $channel->store_id)
                ->where('provider', 'web_chat')
                ->where('status', 'active')
                ->first();
            $channelEnabled = $channel->is_enabled ?? false;

            if ($connection && $channelEnabled) {
                $sendStatus = 'available';
                $readStatus = 'available';
            }
        }

        return [
            ['capability' => 'read_messages', 'status' => $readStatus],
            ['capability' => 'send_messages', 'status' => $sendStatus],
            ['capability' => 'ai_suggestions', 'status' => 'available'],
        ];
    }

    public function healthCheck(SupportChannel $channel): array
    {
        if (!config('customer-care.web_chat_enabled', false)) {
            return ['status' => 'error', 'message' => 'Web Chat özelliği devre dışı'];
        }

        $connection = IntegrationConnection::where('store_id', $channel->store_id)
            ->where('provider', 'web_chat')
            ->where('status', 'active')
            ->first();

        if (!$connection) {
            return ['status' => 'not_configured', 'message' => 'Web Chat bağlantısı aktif değil'];
        }

        return ['status' => 'ok', 'message' => 'Web Chat bağlantısı aktif'];
    }

    public function syncConversations(SupportChannel $channel): array
    {
        return ['synced' => 0];
    }

    public function fetchMessages(SupportChannel $channel, string $conversationExternalId): array
    {
        $conversation = SupportConversation::where('support_channel_id', $channel->id)
            ->where('store_id', $channel->store_id)
            ->where('external_conversation_id', $conversationExternalId)
            ->first();

        if (!$conversation) {
            return [];
        }

        return $conversation->messages()->orderBy('id')->get()->map(fn (SupportMessage $message) => [
            'id' => $message->id,
            'external_message_id' => $message->external_message_id,
            'direction' => $message->direction,
            'sender_type' => $message->sender_type,
            'message_type' => $message->message_type,
            'body' => $message->body_encrypted,
            'delivery_status' => $message->delivery_status,
            'created_at' => $message->created_at?->toIso8601String(),
        ])->all();
    }

    public function canReply(SupportChannel $channel): bool
    {
        if (!config('customer-care.web_chat_enabled', false)) {
            return false;
        }

        $connection = IntegrationConnection::where('store_id', $channel->store_id)
            ->where('provider', 'web_chat')
            ->where('status', 'active')
            ->first();

        if (!$connection) {
            return false;
        }

        return (bool) ($channel->is_enabled ?? false);
    }

    public function sendReply(SupportChannel $channel, string $conversationExternalId, string $message, ?string $idempotencyKey = null): array
    {
        // 1. Connection & Feature Flag Check (Fail-closed)
        if (!config('customer-care.web_chat_enabled', false)) {
            return ['success' => false, 'message' => 'Web Chat özelliği devre dışı'];
        }

        if (!preg_match('/^web_chat_session_(\w+)$/', $conversationExternalId, $matches)) {
            return ['success' => false, 'message' => 'Geçersiz konuşma formatı'];
        }

        $sessionHash = $matches[1];

        $connection = IntegrationConnection::where('store_id', $channel->store_id)
            ->where('provider', 'web_chat')
            ->where('status', 'active')
            ->first();

        if (!$connection) {
            return ['success' => false, 'message' => 'Aktif bir Web Chat bağlantısı bulunamadı (fail-closed)'];
        }

        // Tenant Isolation Check
        $conversation = SupportConversation::where('external_conversation_id', $conversationExternalId)
            ->where('store_id', $channel->store_id)
            ->first();

        if (!$conversation) {
            return ['success' => false, 'message' => 'Konuşma bulunamadı veya bu mağazaya ait değil'];
        }

        // Idempotency check
        $lockKey = null;
        if ($idempotencyKey) {
            $lockKey = 'idemp_webchat_reply_' . md5($idempotencyKey);
            if (Cache::has($lockKey)) {
                return [
                    'success' => true,
                    'message' => 'Bu yanıt zaten gönderildi (Idempotent)',
                    'channel_message_id' => Cache::get($lockKey),
                    'is_duplicate' => true,
                ];
            }
        }

        // Web widget polling ile teslim alır. Tarayıcı ACK vermeden sent/delivered denmez.
        $channelMsgId = 'web_chat_out_' . bin2hex(random_bytes(12));
        if ($lockKey) {
            Cache::put($lockKey, $channelMsgId, now()->addHour());
        }
        $isOnline = (bool) (($conversation->source_reference_json ?? [])['is_online'] ?? true);
        SupportAgentAction::create([
            'conversation_id' => $conversation->id,
            'user_id' => auth()->id() ?? \App\Services\Support\TenantContext::getSystemActor()?->id,
            'action' => $isOnline ? 'web_chat_outbox_handoff' : 'web_chat_queued',
            'details_json' => [
                'session_hash' => $sessionHash,
                'channel_message_id' => $channelMsgId,
                'status' => 'queued',
            ]
        ]);

        return [
            'success' => true,
            'channel_message_id' => $channelMsgId,
            'status' => 'queued',
            'message' => $isOnline
                ? 'Yanıt widget teslim kuyruğuna alındı.'
                : 'Müşteri çevrimdışı, yanıt teslim kuyruğunda bekliyor.'
        ];
    }

    public function resolveOrderContext(SupportChannel $channel, string $externalConversationId): ?array
    {
        return null;
    }

    public function getOutboundTargetStatus(): string
    {
        return 'queued';
    }

    /**
     * Signed widget token / HMAC verification.
     */
    public function verifySignature(string $payloadJson, string $signature, string $secret): bool
    {
        $calculated = hash_hmac('sha256', $payloadJson, $secret);
        return hash_equals($calculated, $signature);
    }

    /**
     * Web Chat gelen mesajını idempotent ve güvenli şekilde projelendirir.
     * IP ve User Agent raw loglanmaz.
     */
    public function projectMessage(SupportChannel $channel, array $payload): array
    {
        if (!config('customer-care.web_chat_enabled', false)) {
            return ['success' => false, 'message' => 'Web Chat özelliği devre dışı'];
        }

        if ((int)$channel->store_id !== (int)($payload['store_id'] ?? null)) {
            return ['success' => false, 'message' => 'Mağaza eşleşmesi başarısız (IDOR engeli)'];
        }

        // 1. Resolve Active Connection to retrieve webhook_secret (must not use payload's secret/salt)
        $connection = IntegrationConnection::where('store_id', $channel->store_id)
            ->where('provider', 'web_chat')
            ->where('status', 'active')
            ->first();

        if (!$connection || empty($connection->webhook_secret)) {
            return ['success' => false, 'message' => 'Aktif bir Web Chat entegrasyonu veya webhook secret bulunamadı (fail-closed)'];
        }

        // 2. Enforce Signature Verification
        $rawJson = $payload['raw_json'] ?? null;
        $signature = $payload['signature'] ?? null;

        if (!$rawJson || !$signature) {
            return ['success' => false, 'message' => 'Eksik imza doğrulaması (fail-closed)'];
        }

        if (!$this->verifySignature($rawJson, $signature, $connection->webhook_secret)) {
            return ['success' => false, 'message' => 'Geçersiz imza doğrulaması (fail-closed)'];
        }

        // 3. Decode signed raw_json
        $decoded = json_decode($rawJson, true);
        if (!$decoded || !is_array($decoded)) {
            return ['success' => false, 'message' => 'Geçersiz JSON payload (fail-closed)'];
        }

        // 4. Validate decoded store_id matches channel store_id (fail-closed)
        if ((int)$channel->store_id !== (int)($decoded['store_id'] ?? null)) {
            return ['success' => false, 'message' => 'İmzalı mağaza eşleşmesi başarısız (IDOR engeli)'];
        }

        // Idempotency key is mandatory
        $idempotencyKey = $decoded['idempotency_key'] ?? null;
        if (!$idempotencyKey) {
            return ['success' => false, 'message' => 'idempotency_key zorunludur'];
        }

        $sessionId = $decoded['session_id'] ?? null;
        if (!$sessionId) {
            return ['success' => false, 'message' => 'Missing session_id'];
        }

        // Hash guest session ID to redact raw session string using connection's secret
        $sessionHash = hash('sha256', $sessionId . $connection->webhook_secret);
        return $this->projectTrustedWidgetMessage(
            $channel,
            $sessionHash,
            (string) $idempotencyKey,
            (string) ($decoded['body'] ?? ''),
            (bool) ($decoded['is_online'] ?? true),
        );
    }

    /**
     * Controller tarafından token, origin, consent ve rate-limit doğrulandıktan sonra çağrılır.
     */
    public function projectTrustedWidgetMessage(
        SupportChannel $channel,
        string $sessionHash,
        string $idempotencyKey,
        string $body,
        bool $isOnline = true
    ): array {
        if (!config('customer-care.web_chat_enabled', false) || !$channel->is_enabled || $channel->key !== 'web_chat') {
            return ['success' => false, 'message' => 'Web Chat kanalı gönderime kapalıdır.'];
        }

        if (!preg_match('/^[a-f0-9]{64}$/', $sessionHash) || !preg_match('/^[A-Za-z0-9._:-]{8,100}$/', $idempotencyKey)) {
            return ['success' => false, 'message' => 'Geçersiz oturum veya idempotency anahtarı.'];
        }

        $body = trim(strip_tags(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $body) ?? ''));
        if ($body === '' || mb_strlen($body) > 2000) {
            return ['success' => false, 'message' => 'Mesaj 1-2000 karakter arasında olmalıdır.'];
        }

        $externalConversationId = 'web_chat_session_' . $sessionHash;
        $externalMessageId = 'web_chat_msg_' . hash('sha256', $sessionHash . ':' . $idempotencyKey);
        $existing = SupportMessage::where('external_message_id', $externalMessageId)
            ->whereHas('conversation', fn ($query) => $query->where('support_channel_id', $channel->id))
            ->first();
        if ($existing) {
            return [
                'success' => true,
                'message' => 'Mükerrer mesaj (atlandı)',
                'projected' => false,
                'conversation_id' => $existing->conversation_id,
                'message_id' => $existing->id,
            ];
        }

        $automation = $channel->config_json['automation_settings'] ?? [];
        $configuredMode = $automation['ai_mode'] ?? 'manual';
        $mode = in_array($configuredMode, ['manual', 'suggestion_only', 'automatic'], true)
            ? $configuredMode
            : 'manual';

        $conversation = SupportConversation::firstOrCreate(
            [
                'support_channel_id' => $channel->id,
                'external_conversation_id' => $externalConversationId,
            ],
            [
                'store_id' => $channel->store_id,
                'source_type' => 'web_chat',
                'status' => 'open',
                'priority' => 'normal',
                'ai_mode' => $mode,
                'last_message_at' => now(),
                'source_reference_json' => [
                    'session_hash' => $sessionHash,
                    'is_online' => $isOnline,
                    // Redact IP and User-agent to prevent leakage
                    'ip_redacted' => true,
                    'ua_redacted' => true,
                ],
            ]
        );

        $redactedBody = app(\App\Services\Support\Security\PiiRedactor::class)->maskPii($body);
        $message = SupportMessage::create([
            'conversation_id' => $conversation->id,
            'external_message_id' => $externalMessageId,
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'message_type' => 'text',
            'body_encrypted' => $redactedBody,
            'delivery_status' => 'received',
            'body_preview' => mb_substr($redactedBody, 0, 100),
            'payload_json' => null,
        ]);

        $conversation->update([
            'last_message_at' => now(),
            'last_inbound_at' => now(),
        ]);

        return [
            'success' => true,
            'projected' => true,
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
        ];
    }
}
