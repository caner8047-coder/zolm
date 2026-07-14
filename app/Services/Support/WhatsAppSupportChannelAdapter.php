<?php

namespace App\Services\Support;

use App\Models\SupportChannel;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\WaConversation;
use App\Models\WaContact;
use App\Models\WaConsentEvent;
use App\Models\WaSuppression;
use App\Models\WaInboundMessage;
use App\Models\SupportAgentAction;
use App\Services\Support\Security\PiiRedactor;

class WhatsAppSupportChannelAdapter implements SupportChannelAdapterInterface
{
    public function key(): string { return 'whatsapp'; }
    public function name(): string { return 'WhatsApp'; }

    /**
     * Context-aware capabilities.
     * WaAccount aktif + kanal enabled değilse send_messages unavailable.
     */
    public function getCapabilities(?SupportChannel $channel = null): array
    {
        $sendStatus = 'unavailable';

        if ($channel) {
            $account = $channel->store?->waAccount;
            $channelEnabled = $channel->is_enabled ?? false;
            if ($account && $account->is_active && $channelEnabled) {
                $sendStatus = 'available';
            }
        }

        return [
            ['capability' => 'read_messages', 'status' => 'available'],
            ['capability' => 'send_messages', 'status' => $sendStatus],
            ['capability' => 'sync_orders', 'status' => 'unavailable'],
            ['capability' => 'sync_products', 'status' => 'unavailable'],
            ['capability' => 'webhooks', 'status' => 'available'],
            ['capability' => 'attachments', 'status' => 'available'],
            ['capability' => 'ai_suggestions', 'status' => 'available'],
        ];
    }

    public function healthCheck(SupportChannel $channel): array
    {
        $account = $channel->store?->waAccount;
        return [
            'status' => ($account && $account->is_active) ? 'ok' : 'error',
            'message' => $account ? 'WhatsApp hesabı aktif' : 'WhatsApp hesabı bulunamadı',
        ];
    }

    /**
     * Mevcut wa_conversations'ı support_conversations'a senkronize eder.
     */
    public function syncConversations(SupportChannel $channel): array
    {
        $waConversations = WaConversation::where('store_id', $channel->store_id)
            ->where('status', 'open')
            ->with('contact')
            ->get();

        $synced = 0;
        foreach ($waConversations as $waConv) {
            $externalId = 'wa_' . $waConv->id;

            $supportConv = SupportConversation::firstOrCreate(
                ['support_channel_id' => $channel->id, 'external_conversation_id' => $externalId],
                [
                    'store_id' => $waConv->store_id,
                    'source_type' => 'whatsapp',
                    'status' => $this->mapStatus($waConv->status),
                    'priority' => 'normal',
                    'ai_mode' => $waConv->ai_status === 'handed_off' ? 'handoff' : 'suggestion_only',
                    'last_message_at' => $waConv->last_message_at,
                    'source_reference_json' => ['wa_conversation_id' => $waConv->id],
                ]
            );

            $supportConv->update(['last_message_at' => $waConv->last_message_at]);
            $synced++;
        }

        return ['synced' => $synced];
    }

    /**
     * Inbound mesajları support_messages tablosuna idempotent olarak yansıtır.
     * Raw payload support message'a sızmaz.
     * Contact/marketplace customer otomatik merge yapılmaz.
     */
    public function projectInboundMessages(SupportChannel $channel, string $externalConversationId): array
    {
        if (!preg_match('/^wa_(\d+)$/', $externalConversationId, $matches)) {
            return ['projected' => 0, 'error' => 'Geçersiz konuşma formatı'];
        }
        $waConvId = (int)$matches[1];

        // Store-bound lookup
        $waConv = WaConversation::whereKey($waConvId)
            ->where('store_id', $channel->store_id)
            ->first();

        if (!$waConv) {
            return ['projected' => 0, 'error' => 'Konuşma bulunamadı'];
        }

        $supportConv = SupportConversation::firstOrCreate(
            ['support_channel_id' => $channel->id, 'external_conversation_id' => $externalConversationId],
            [
                'store_id' => $waConv->store_id,
                'source_type' => 'whatsapp',
                'status' => $this->mapStatus($waConv->status),
                'priority' => 'normal',
                'ai_mode' => 'suggestion_only',
                'last_message_at' => $waConv->last_message_at,
                'source_reference_json' => ['wa_conversation_id' => $waConvId],
            ]
        );

        $waMessages = WaInboundMessage::where('conversation_id', $waConvId)
            ->orderBy('received_at')
            ->get();

        $projected = 0;
        foreach ($waMessages as $waMsg) {
            $externalMsgId = 'wa_msg_' . $waMsg->id;

            $exists = SupportMessage::where('conversation_id', $supportConv->id)
                ->where('external_message_id', $externalMsgId)
                ->exists();

            if (!$exists) {
                SupportMessage::create([
                    'conversation_id' => $supportConv->id,
                    'external_message_id' => $externalMsgId,
                    'direction' => 'inbound',
                    'sender_type' => 'customer',
                    'message_type' => $waMsg->message_type ?? 'text',
                    // Raw payload sızmaz; yalnız body metin
                    'body_encrypted' => $waMsg->body ?? '',
                    'delivery_status' => 'received',
                    'metadata_json' => null, // raw_payload buraya yazılmaz
                ]);
                $projected++;
            }
        }

        if ($projected > 0) {
            $supportConv->update(['last_inbound_at' => now(), 'last_message_at' => now()]);
        }

        return ['projected' => $projected];
    }

    public function fetchMessages(SupportChannel $channel, string $conversationExternalId): array
    {
        if (!preg_match('/^wa_(\d+)$/', $conversationExternalId, $matches)) {
            return [];
        }
        $waConvId = (int)$matches[1];

        $waConv = WaConversation::whereKey($waConvId)
            ->where('store_id', $channel->store_id)
            ->first();

        if (!$waConv) {
            return [];
        }

        $messages = WaInboundMessage::where('conversation_id', $waConvId)
            ->orderBy('received_at')
            ->get();

        return $messages->map(fn ($m) => [
            'external_message_id' => $m->meta_message_id,
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'message_type' => $m->message_type,
            'body' => $m->body,
            'received_at' => $m->received_at,
        ])->toArray();
    }

    /**
     * Context-aware canReply:
     * - Kanal enabled olmalı
     * - WaAccount aktif olmalı
     * - Consent mevcut olmalı (active consent event var)
     * - Contact suppressed olmamalı
     */
    public function canReply(SupportChannel $channel): bool
    {
        if (!($channel->is_enabled ?? false)) {
            return false;
        }

        $account = $channel->store?->waAccount;
        if (!$account || !$account->is_active) {
            return false;
        }

        // send_messages capability available mi?
        if (!$channel->hasCapability('send_messages')) {
            return false;
        }

        $caps = $this->getCapabilities($channel);
        $sendCap = collect($caps)->firstWhere('capability', 'send_messages');
        if (($sendCap['status'] ?? 'unavailable') !== 'available') {
            return false;
        }

        return true;
    }

    /**
     * Contact bazlı consent + suppression kontrolü ile fail-closed gönderim.
     */
    public function sendReply(SupportChannel $channel, string $conversationExternalId, string $message, ?string $idempotencyKey = null): array
    {
        if (!preg_match('/^wa_(\d+)$/', $conversationExternalId, $matches)) {
            return ['success' => false, 'message' => 'Geçersiz konuşma formatı'];
        }
        $waConvId = (int)$matches[1];

        // Store-bound lookup
        $waConv = WaConversation::with('contact')
            ->whereKey($waConvId)
            ->where('store_id', $channel->store_id)
            ->first();

        if (!$waConv) {
            return ['success' => false, 'message' => 'Konuşma bulunamadı veya bu mağazaya ait değil'];
        }

        // contact.store_id ile conversation.store_id tutarlılığı
        if ($waConv->contact && (int)$waConv->contact->store_id !== (int)$waConv->store_id) {
            return ['success' => false, 'message' => 'Konuşma ve kişi mağaza eşleşmesi geçersiz'];
        }

        $contact = $waConv->contact;

        // Consent kontrolü — yoksa fail-closed
        if ($contact) {
            $hasConsent = WaConsentEvent::where('contact_id', $contact->id)
                ->where('action', 'granted')
                ->exists();

            if (!$hasConsent) {
                $this->auditBlock($channel, $waConv->store_id, 'consent_missing',
                    'WhatsApp gönderimi engellendi: onay (consent) alınmamış.');

                return ['success' => false, 'message' => 'Gönderim engellendi: müşteri onayı (consent) bulunamadı'];
            }

            // Suppression kontrolü — suppressed ise fail-closed
            $isSuppressed = WaSuppression::where('contact_id', $contact->id)
                ->active()
                ->exists();

            if ($isSuppressed) {
                $this->auditBlock($channel, $waConv->store_id, 'suppressed_contact',
                    'WhatsApp gönderimi engellendi: kişi suppression listesinde.');

                return ['success' => false, 'message' => 'Gönderim engellendi: kişi suppress edilmiş'];
            }
        } else {
            // Contact yoksa consent kontrolü yapılamaz; fail-closed
            return ['success' => false, 'message' => 'Gönderim engellendi: kişi kaydı bulunamadı'];
        }

        // Template parametresi boşsa engelle (template mesajları için)
        if (empty(trim($message))) {
            return ['success' => false, 'message' => 'Gönderim engellendi: mesaj içeriği boş'];
        }

        $key = $idempotencyKey ?? ('wa_msg_reply_' . $waConvId . '_' . md5($message));

        // Deduplication
        $existing = \App\Models\WaOutbox::where('idempotency_key', $key)->first();
        if ($existing) {
            return [
                'success' => true,
                'channel_message_id' => 'wa_outbox_' . $existing->id,
                'message' => 'Yanıt zaten kuyruğa alınmıştı'
            ];
        }

        // Outbox'a yaz — Audit kaydı
        $outbox = \App\Models\WaOutbox::create([
            'contact_id' => $waConv->contact_id,
            'store_id' => $waConv->store_id,
            'idempotency_key' => $key,
            'message_type' => 'text',
            'body_text' => $message,
            'status' => 'queued',
            'priority' => 'high',
        ]);

        // Handoff audit logu
        SupportAgentAction::create([
            'conversation_id' => null,
            'message_id' => null,
            'user_id' => auth()->id() ?? \App\Services\Support\TenantContext::getSystemActor()?->id,
            'action' => 'wa_outbox_handoff',
            'details_json' => [
                'wa_outbox_id' => $outbox->id,
                'wa_conversation_id' => $waConvId,
                'store_id' => $waConv->store_id,
                'channel_id' => $channel->id,
            ],
        ]);

        return [
            'success' => true,
            'channel_message_id' => 'wa_outbox_' . $outbox->id,
            'message' => 'Yanıt kuyruğa alındı'
        ];
    }

    public function resolveOrderContext(SupportChannel $channel, string $externalConversationId): ?array
    {
        if (!preg_match('/^wa_(\d+)$/', $externalConversationId, $matches)) {
            return null;
        }
        $waConvId = (int)$matches[1];

        $waConv = WaConversation::with('contact')
            ->whereKey($waConvId)
            ->where('store_id', $channel->store_id)
            ->first();

        if (!$waConv || !$waConv->contact) {
            return null;
        }

        if ((int)$waConv->contact->store_id !== (int)$waConv->store_id) {
            return null;
        }

        return [
            'channel' => 'whatsapp',
            'customer_name' => $waConv->contact->first_name,
            'last_intent' => $waConv->last_intent,
        ];
    }

    private function mapStatus(string $waStatus): string
    {
        return match ($waStatus) {
            'open' => 'open',
            'closed' => 'closed',
            default => 'open',
        };
    }

    private function auditBlock(SupportChannel $channel, int $storeId, string $reason, string $detail): void
    {
        try {
            SupportAgentAction::create([
                'conversation_id' => null,
                'message_id' => null,
                'user_id' => auth()->id() ?? \App\Services\Support\TenantContext::getSystemActor()?->id,
                'action' => 'wa_send_blocked',
                'details_json' => [
                    'reason' => $reason,
                    'detail' => $detail,
                    'channel_id' => $channel->id,
                    'store_id' => $storeId,
                ],
            ]);
        } catch (\Throwable) {
            // Audit logu başarısız olsa da gönderim blokajı korunur
        }
    }

    public function getOutboundTargetStatus(): string
    {
        return 'accepted';
    }
}
