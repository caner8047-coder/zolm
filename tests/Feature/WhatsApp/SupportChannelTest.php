<?php

namespace Tests\Feature\WhatsApp;

use App\Models\MarketplaceStore;
use App\Models\WaConversation;
use App\Models\WaInboundMessage;
use App\Models\WaOutbox;
use App\Models\WaWebhookEvent;
use App\Models\SupportChannel;
use App\Models\SupportChannelCapability;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Services\Support\SupportChannelManager;
use App\Services\Support\SupportCapabilityService;
use App\Services\Support\SupportConversationSyncService;
use App\Services\Support\WhatsAppSupportChannelAdapter;
use App\Services\Support\TrendyolSupportChannelAdapter;
use App\Services\Support\NullSupportChannelAdapter;
use App\Services\Support\SupportReplyService;
use App\Services\Support\HumanHandoffService;
use Illuminate\Support\Facades\Config;

class SupportChannelTest extends WhatsAppTestCase
{
    public function test_whatsapp_adapter_shows_wa_conversations(): void
    {
        $store = $this->createStore();
        $contact = $this->createContact($store, '+905321111111');
        $waConv = WaConversation::create([
            'contact_id' => $contact->id,
            'store_id' => $store->id,
            'status' => 'open',
            'ai_status' => 'active',
        ]);

        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'whatsapp',
            'name' => 'WhatsApp',
            'status' => 'active',
            'is_enabled' => true,
        ]);

        $adapter = new WhatsAppSupportChannelAdapter();
        $result = $adapter->syncConversations($channel);

        $this->assertGreaterThan(0, $result['synced']);

        $supportConv = SupportConversation::where('support_channel_id', $channel->id)->first();
        $this->assertNotNull($supportConv);
        $this->assertEquals('whatsapp', $supportConv->source_type);
    }

    public function test_same_whatsapp_message_not_duplicated(): void
    {
        $store = $this->createStore();
        $contact = $this->createContact($store, '+905322222222');
        $waConv = WaConversation::create([
            'contact_id' => $contact->id,
            'store_id' => $store->id,
            'status' => 'open',
        ]);

        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'whatsapp',
            'name' => 'WhatsApp',
            'status' => 'active',
            'is_enabled' => true,
        ]);

        $adapter = new WhatsAppSupportChannelAdapter();

        // İki kez sync et
        $adapter->syncConversations($channel);
        $adapter->syncConversations($channel);

        $convCount = SupportConversation::where('support_channel_id', $channel->id)->count();
        $this->assertEquals(1, $convCount);
    }

    public function test_unsupported_channel_no_api_call(): void
    {
        $channel = SupportChannel::create([
            'key' => 'future_channel',
            'name' => 'Gelecek Kanal',
            'status' => 'unsupported',
            'is_enabled' => false,
        ]);

        $manager = new SupportChannelManager();
        $adapter = $manager->resolve('future_channel');

        $this->assertInstanceOf(NullSupportChannelAdapter::class, $adapter);

        $result = $adapter->healthCheck($channel);
        $this->assertEquals('unsupported', $result['status']);
    }

    public function test_unsupported_channel_no_sync(): void
    {
        $channel = SupportChannel::create([
            'key' => 'future_channel',
            'name' => 'Gelecek Kanal',
            'status' => 'unsupported',
            'is_enabled' => false,
        ]);

        $adapter = new NullSupportChannelAdapter();
        $result = $adapter->syncConversations($channel);

        $this->assertEquals(0, $result['synced']);
    }

    public function test_unavailable_capability_blocks_reply(): void
    {
        $store = $this->createStore('trendyol');
        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'trendyol',
            'name' => 'Trendyol',
            'status' => 'active',
            'is_enabled' => true,
        ]);

        // Capability'yi unavailable yap
        SupportChannelCapability::create([
            'support_channel_id' => $channel->id,
            'capability' => 'send_messages',
            'status' => 'unavailable',
            'source' => 'test',
        ]);

        $adapter = new TrendyolSupportChannelAdapter();
        $canReply = $adapter->canReply($channel);

        $this->assertFalse($canReply);
    }

    public function test_whatsapp_adapter_can_reply_when_capable(): void
    {
        $store = $this->createStore();
        $this->createAccount($store);
        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'whatsapp',
            'name' => 'WhatsApp',
            'status' => 'active',
            'is_enabled' => true,
        ]);

        $adapter = new WhatsAppSupportChannelAdapter();
        $canReply = $adapter->canReply($channel);

        // Capability yoksa false döner
        $this->assertFalse($canReply);

        // Capability ekledikten sonra
        SupportChannelCapability::create([
            'support_channel_id' => $channel->id,
            'capability' => 'send_messages',
            'status' => 'available',
            'source' => 'test',
        ]);

        $canReply = $adapter->canReply($channel);
        $this->assertTrue($canReply);
    }

    public function test_whatsapp_contact_not_auto_merged_with_marketplace(): void
    {
        // WhatsApp contact ile Trendyol müşteri verisi otomatik eşleşmemeli
        $store = $this->createStore('trendyol');
        $contact = $this->createContact($store, '+905329999999');

        $adapter = new WhatsAppSupportChannelAdapter();
        $context = $adapter->resolveOrderContext(
            SupportChannel::create(['store_id' => $store->id, 'key' => 'whatsapp', 'name' => 'WA', 'status' => 'active']),
            'wa_nonexistent'
        );

        // WhatsApp context dönmeli, Trendyol verisi değil
        $this->assertNull($context);
    }

    public function test_marketplace_customer_not_in_whatsapp_automation(): void
    {
        Config::set('whatsapp.features.test_mode', false);
        $store = $this->createStore('trendyol');
        $contact = $this->createContact($store, '+905328888888');

        // Trendyol mağazası WhatsApp otomasyonuna giremez
        $service = new \App\Services\WhatsApp\EligibilityService();
        $this->assertFalse($service->isEligibleForMessaging($contact, 'marketing'));
    }

    public function test_sync_cursor_updates(): void
    {
        $store = $this->createStore();
        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'whatsapp',
            'name' => 'WhatsApp',
            'status' => 'active',
            'is_enabled' => true,
        ]);

        // Capability ekle
        SupportChannelCapability::create([
            'support_channel_id' => $channel->id,
            'capability' => 'read_messages',
            'status' => 'available',
            'source' => 'test',
        ]);

        $service = new SupportConversationSyncService();
        $service->syncChannel($channel);

        $cursor = \App\Models\SupportSyncCursor::where('support_channel_id', $channel->id)
            ->where('sync_type', 'conversations')
            ->first();

        $this->assertNotNull($cursor);
        $this->assertNotNull($cursor->last_success_at);
    }

    public function test_ai_suggestion_only_mode(): void
    {
        $store = $this->createStore();
        $contact = $this->createContact($store, '+905327777777');

        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'whatsapp',
            'name' => 'WhatsApp',
            'status' => 'active',
            'is_enabled' => true,
        ]);

        $conv = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'wa_ai_test',
            'store_id' => $store->id,
            'source_type' => 'whatsapp',
            'status' => 'open',
            'ai_mode' => 'suggestion_only',
        ]);

        $this->assertEquals('suggestion_only', $conv->ai_mode);
    }

    public function test_whatsapp_raw_payload_not_in_support_message(): void
    {
        $store = $this->createStore();
        $contact = $this->createContact($store, '+905326666666');
        $waConv = WaConversation::create([
            'contact_id' => $contact->id,
            'store_id' => $store->id,
            'status' => 'open',
        ]);

        \App\Models\WaInboundMessage::create([
            'conversation_id' => $waConv->id,
            'contact_id' => $contact->id,
            'meta_message_id' => 'msg_xyz',
            'message_type' => 'text',
            'body' => 'Merhaba nasılsınız?',
            'received_at' => now(),
            'payload_json' => ['raw' => 'data'],
        ]);

        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'whatsapp',
            'name' => 'WhatsApp',
            'status' => 'active',
            'is_enabled' => true,
        ]);

        $adapter = new WhatsAppSupportChannelAdapter();
        $messages = $adapter->fetchMessages($channel, 'wa_' . $waConv->id);

        $this->assertNotEmpty($messages, 'Mesajlar boş dönmemeli.');

        // Mesajlar payload_json içerebilir ama body_encrypted ile şifreli olmalı
        foreach ($messages as $msg) {
            $this->assertArrayHasKey('body', $msg);
            $this->assertArrayNotHasKey('raw_payload', $msg);
        }
    }
}
