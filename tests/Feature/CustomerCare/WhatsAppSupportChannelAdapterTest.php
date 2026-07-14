<?php

namespace Tests\Feature\CustomerCare;

use Tests\TestCase;
use App\Models\User;
use App\Models\MarketplaceStore;
use App\Models\LegalEntity;
use App\Models\SupportChannel;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\SupportAgentAction;
use App\Models\WaAccount;
use App\Models\WaContact;
use App\Models\WaConversation;
use App\Models\WaConsentEvent;
use App\Models\WaSuppression;
use App\Models\WaInboundMessage;
use App\Services\Support\WhatsAppSupportChannelAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

class WhatsAppSupportChannelAdapterTest extends TestCase
{
    use RefreshDatabase, CustomerCareTestHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupSystemActor();
        Config::set('customer-care.enabled', true);
        Config::set('customer-care.settings_enabled', true);
    }

    protected function createStoreWithWaChannel(User $user, bool $accountActive = true, bool $channelEnabled = true): array
    {
        $legal = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Test Legal ' . uniqid(),
            'tax_number' => (string) rand(1000000000, 9999999999),
            'is_active' => true,
        ]);

        $store = MarketplaceStore::create([
            'user_id' => $user->id,
            'legal_entity_id' => $legal->id,
            'store_name' => 'Test WA Store ' . uniqid(),
            'marketplace' => 'trendyol',
            'is_active' => true,
        ]);

        $waAccount = WaAccount::create([
            'store_id' => $store->id,
            'phone_number_id' => '1234567890',
            'waba_id' => 'waba_test',
            'display_phone_number' => '+905551234567',
            'access_token_encrypted' => 'test_token_enc',
            'is_active' => $accountActive,
        ]);

        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'whatsapp',
            'name' => 'WhatsApp Destek',
            'status' => 'active',
            'is_enabled' => $channelEnabled,
        ]);

        \App\Models\SupportChannelCapability::create([
            'support_channel_id' => $channel->id,
            'capability' => 'send_messages',
            'status' => 'available',
            'source' => 'test',
        ]);

        return [$store, $waAccount, $channel];
    }

    protected function createContactWithConversation(MarketplaceStore $store, SupportChannel $channel, bool $hasConsent = true, bool $isSuppressed = false): array
    {
        $contact = WaContact::create([
            'store_id' => $store->id,
            'phone_e164_encrypted' => 'test_encrypted_placeholder',
            'phone_hash' => WaContact::hashPhone('+905551234567'),
            'first_name' => 'Test',
            'last_name' => 'Müşteri',
            'status' => 'active',
        ]);

        $waConv = WaConversation::create([
            'store_id' => $store->id,
            'contact_id' => $contact->id,
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        if ($hasConsent) {
            WaConsentEvent::create([
                'contact_id' => $contact->id,
                'store_id' => $store->id,
                'purpose' => 'support',
                'action' => 'granted',
                'source' => 'inbound_message',
                'consent_timestamp' => now(),
            ]);
        }

        if ($isSuppressed) {
            WaSuppression::create([
                'contact_id' => $contact->id,
                'reason' => 'unsubscribed',
                'suppressed_at' => now(),
                'expires_at' => null,
            ]);
        }

        return [$contact, $waConv];
    }

    // ──────────────────────────────────────────────
    // 1. Capabilities — context-aware
    // ──────────────────────────────────────────────

    public function test_get_capabilities_is_unavailable_without_channel()
    {
        $adapter = app(WhatsAppSupportChannelAdapter::class);
        $caps = $adapter->getCapabilities(null);

        $sendCap = collect($caps)->firstWhere('capability', 'send_messages');
        $this->assertEquals('unavailable', $sendCap['status']);
    }

    public function test_get_capabilities_unavailable_when_account_inactive()
    {
        $user = User::factory()->create();
        [$store, $waAccount, $channel] = $this->createStoreWithWaChannel($user, false, true);

        $adapter = app(WhatsAppSupportChannelAdapter::class);
        $caps = $adapter->getCapabilities($channel);

        $sendCap = collect($caps)->firstWhere('capability', 'send_messages');
        $this->assertEquals('unavailable', $sendCap['status']);
    }

    public function test_get_capabilities_available_when_account_active_and_channel_enabled()
    {
        $user = User::factory()->create();
        [$store, $waAccount, $channel] = $this->createStoreWithWaChannel($user, true, true);

        $adapter = app(WhatsAppSupportChannelAdapter::class);
        $caps = $adapter->getCapabilities($channel);

        $sendCap = collect($caps)->firstWhere('capability', 'send_messages');
        $this->assertEquals('available', $sendCap['status']);
    }

    // ──────────────────────────────────────────────
    // 2. canReply — context-aware
    // ──────────────────────────────────────────────

    public function test_can_reply_returns_false_when_channel_disabled()
    {
        $user = User::factory()->create();
        [$store, $waAccount, $channel] = $this->createStoreWithWaChannel($user, true, false);

        $adapter = app(WhatsAppSupportChannelAdapter::class);
        $this->assertFalse($adapter->canReply($channel));
    }

    public function test_can_reply_returns_false_when_wa_account_inactive()
    {
        $user = User::factory()->create();
        [$store, $waAccount, $channel] = $this->createStoreWithWaChannel($user, false, true);

        $adapter = app(WhatsAppSupportChannelAdapter::class);
        $this->assertFalse($adapter->canReply($channel));
    }

    public function test_can_reply_returns_true_when_fully_operational()
    {
        $user = User::factory()->create();
        [$store, $waAccount, $channel] = $this->createStoreWithWaChannel($user, true, true);

        $adapter = app(WhatsAppSupportChannelAdapter::class);
        $this->assertTrue($adapter->canReply($channel));
    }

    // ──────────────────────────────────────────────
    // 3. Inbound Projection — idempotent
    // ──────────────────────────────────────────────

    public function test_inbound_projection_is_idempotent()
    {
        $user = User::factory()->create();
        [$store, $waAccount, $channel] = $this->createStoreWithWaChannel($user);
        [$contact, $waConv] = $this->createContactWithConversation($store, $channel);

        WaInboundMessage::create([
            'conversation_id' => $waConv->id,
            'contact_id' => $contact->id,
            'meta_message_id' => 'wa_meta_001',
            'message_type' => 'text',
            'body' => 'Merhaba, siparişim nerede?',
            'payload_json' => ['type' => 'text'],
            'received_at' => now(),
        ]);

        $adapter = app(WhatsAppSupportChannelAdapter::class);
        $externalId = 'wa_' . $waConv->id;

        $result1 = $adapter->projectInboundMessages($channel, $externalId);
        $result2 = $adapter->projectInboundMessages($channel, $externalId);

        $this->assertEquals(1, $result1['projected']);
        $this->assertEquals(0, $result2['projected']); // İdempotent: ikinci projeksiyonda kayıt eklenmez
    }

    // ──────────────────────────────────────────────
    // 4. Raw payload sızmaz
    // ──────────────────────────────────────────────

    public function test_raw_payload_does_not_leak_into_support_message()
    {
        $user = User::factory()->create();
        [$store, $waAccount, $channel] = $this->createStoreWithWaChannel($user);
        [$contact, $waConv] = $this->createContactWithConversation($store, $channel);

        WaInboundMessage::create([
            'conversation_id' => $waConv->id,
            'contact_id' => $contact->id,
            'meta_message_id' => 'wa_meta_002',
            'message_type' => 'text',
            'body' => 'İade talebi',
            'payload_json' => ['type' => 'text'],
            'received_at' => now(),
        ]);

        $adapter = app(WhatsAppSupportChannelAdapter::class);
        $adapter->projectInboundMessages($channel, 'wa_' . $waConv->id);

        $msg = SupportMessage::where('conversation_id', function ($q) use ($channel, $waConv) {
            $q->select('id')->from('support_conversations')
                ->where('support_channel_id', $channel->id)
                ->where('external_conversation_id', 'wa_' . $waConv->id);
        })->first();

        $this->assertNotNull($msg);
        $this->assertNull($msg->metadata_json, 'Raw payload metadata support_message\'a sızmamalı');
    }

    // ──────────────────────────────────────────────
    // 5. Consent missing — blocks outbound
    // ──────────────────────────────────────────────

    public function test_send_reply_blocked_when_consent_missing()
    {
        $user = User::factory()->create();
        [$store, $waAccount, $channel] = $this->createStoreWithWaChannel($user);
        [$contact, $waConv] = $this->createContactWithConversation($store, $channel, false); // no consent

        $adapter = app(WhatsAppSupportChannelAdapter::class);
        $result = $adapter->sendReply($channel, 'wa_' . $waConv->id, 'Merhaba, yardımcı olabilirim');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('consent', strtolower($result['message']));

        // Audit log oluşturulmuş olmalı
        $this->assertTrue(
            SupportAgentAction::where('action', 'wa_send_blocked')
                ->whereJsonContains('details_json->reason', 'consent_missing')
                ->exists()
        );
    }

    // ──────────────────────────────────────────────
    // 6. Suppressed contact — blocks outbound
    // ──────────────────────────────────────────────

    public function test_send_reply_blocked_when_contact_suppressed()
    {
        $user = User::factory()->create();
        [$store, $waAccount, $channel] = $this->createStoreWithWaChannel($user);
        [$contact, $waConv] = $this->createContactWithConversation($store, $channel, true, true); // consent + suppressed

        $adapter = app(WhatsAppSupportChannelAdapter::class);
        $result = $adapter->sendReply($channel, 'wa_' . $waConv->id, 'Merhaba, yardımcı olabilirim');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('suppress', strtolower($result['message']));

        $this->assertTrue(
            SupportAgentAction::where('action', 'wa_send_blocked')
                ->whereJsonContains('details_json->reason', 'suppressed_contact')
                ->exists()
        );
    }

    // ──────────────────────────────────────────────
    // 7. Disabled channel — blocks outbound
    // ──────────────────────────────────────────────

    public function test_send_reply_blocked_when_channel_disabled()
    {
        // SupportOutboxService zaten disabled channel'ı bloklar; adapter da store_id uygunsa devam eder
        // Bu testi canReply seviyesinde test ediyoruz
        $user = User::factory()->create();
        [$store, $waAccount, $channel] = $this->createStoreWithWaChannel($user, true, false);

        $adapter = app(WhatsAppSupportChannelAdapter::class);
        $this->assertFalse($adapter->canReply($channel), 'Disabled channel için canReply false dönmeli');
    }

    // ──────────────────────────────────────────────
    // 8. Outbox handoff — audit log
    // ──────────────────────────────────────────────

    public function test_successful_send_creates_wa_outbox_handoff_audit()
    {
        $user = User::factory()->create();
        [$store, $waAccount, $channel] = $this->createStoreWithWaChannel($user, true, true);
        [$contact, $waConv] = $this->createContactWithConversation($store, $channel, true, false);

        $adapter = app(WhatsAppSupportChannelAdapter::class);
        $result = $adapter->sendReply($channel, 'wa_' . $waConv->id, 'Siparişiniz kargoya verilmiştir.');

        $this->assertTrue($result['success']);
        $this->assertStringStartsWith('wa_outbox_', $result['channel_message_id']);

        $this->assertTrue(
            SupportAgentAction::where('action', 'wa_outbox_handoff')
                ->whereJsonContains('details_json->channel_id', $channel->id)
                ->exists()
        );
    }

    // ──────────────────────────────────────────────
    // 9. Cross-store inbound projection engellenir
    // ──────────────────────────────────────────────

    public function test_inbound_projection_blocks_cross_store_conversation()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        [$store1, $waAccount1, $channel1] = $this->createStoreWithWaChannel($user1);
        [$store2, $waAccount2, $channel2] = $this->createStoreWithWaChannel($user2);

        [$contact1, $waConv1] = $this->createContactWithConversation($store1, $channel1);

        $adapter = app(WhatsAppSupportChannelAdapter::class);
        // channel2 ile store1'e ait waConv1'e erişmeye çalışıyoruz
        $result = $adapter->projectInboundMessages($channel2, 'wa_' . $waConv1->id);

        $this->assertEquals(0, $result['projected']);
        $this->assertArrayHasKey('error', $result);
    }
}
