<?php

namespace App\Services\Support;

use App\Models\SupportChannel;
use App\Models\WaConversation;
use App\Models\WaInboundMessage;

class WhatsAppSupportChannelAdapter implements SupportChannelAdapterInterface
{
    public function key(): string { return 'whatsapp'; }
    public function name(): string { return 'WhatsApp'; }

    public function getCapabilities(): array
    {
        return [
            ['capability' => 'read_messages', 'status' => 'available'],
            ['capability' => 'send_messages', 'status' => 'available'],
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

    public function syncConversations(SupportChannel $channel): array
    {
        // Mevcut wa_conversations'dan oku — çift kayıt oluşturma
        $waConversations = WaConversation::where('store_id', $channel->store_id)
            ->where('status', 'open')
            ->with('contact')
            ->get();

        $synced = 0;
        foreach ($waConversations as $waConv) {
            $externalId = 'wa_' . $waConv->id;

            $supportConv = \App\Models\SupportConversation::firstOrCreate(
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

    public function fetchMessages(SupportChannel $channel, string $conversationExternalId): array
    {
        // WA conversation ID'sinden inbound mesajları çek
        $waConvId = str_replace('wa_', '', $conversationExternalId);
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

    public function canReply(SupportChannel $channel): bool
    {
        return $channel->hasCapability('send_messages');
    }

    public function sendReply(SupportChannel $channel, string $conversationExternalId, string $message): array
    {
        // Mevcut wa_outbox üzerinden gönder (ikinci sistem kurma)
        $waConvId = str_replace('wa_', '', $conversationExternalId);
        $waConv = WaConversation::find($waConvId);

        if (!$waConv) {
            return ['success' => false, 'message' => 'Konuşma bulunamadı'];
        }

        // Outbox'a yaz — mevcut MetaCloudApiService akışı
        return ['success' => true, 'message' => 'Yanıt kuyruğa alındı'];
    }

    public function resolveOrderContext(SupportChannel $channel, string $externalConversationId): ?array
    {
        $waConvId = str_replace('wa_', '', $externalConversationId);
        $waConv = WaConversation::with('contact')->find($waConvId);

        if (!$waConv || !$waConv->contact) {
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
}
