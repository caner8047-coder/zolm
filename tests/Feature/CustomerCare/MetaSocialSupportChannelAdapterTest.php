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
use App\Models\SupportDispatch;
use App\Models\IntegrationConnection;
use App\Services\Support\MetaSocialSupportChannelAdapter;
use App\Services\Support\MetaSocialConnectorInterface;
use App\Services\Support\SupportOutboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

class MetaSocialSupportChannelAdapterTest extends TestCase
{
    use RefreshDatabase, CustomerCareTestHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupSystemActor();
        Config::set('customer-care.enabled', true);
        Config::set('customer-care.meta_social_enabled', true);

        // Bind mock Meta Social Connector by default
        $mock = $this->createMock(MetaSocialConnectorInterface::class);
        $mock->method('send')->willReturn('meta_msg_test_id');
        $this->app->instance(MetaSocialConnectorInterface::class, $mock);
    }

    protected function createStoreWithConnection(User $user, string $provider = 'instagram', string $status = 'active'): array
    {
        $legal = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Legal ' . uniqid(),
            'tax_number' => (string) rand(1000000000, 9999999999),
            'is_active' => true,
        ]);

        $store = MarketplaceStore::create([
            'user_id' => $user->id,
            'legal_entity_id' => $legal->id,
            'store_name' => 'Meta Store ' . uniqid(),
            'marketplace' => 'trendyol',
            'is_active' => true,
        ]);

        $connection = IntegrationConnection::create([
            'store_id' => $store->id,
            'provider' => $provider,
            'auth_type' => 'oauth',
            'status' => $status,
            'credentials_encrypted' => ['token' => 'secure_token_123'],
        ]);

        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => $provider,
            'name' => ucfirst($provider) . ' Channel',
            'status' => 'active',
            'is_enabled' => true,
        ]);

        return [$store, $connection, $channel];
    }

    // ──────────────────────────────────────────────
    // 1. Feature Flag & Connection Check
    // ──────────────────────────────────────────────

    public function test_meta_social_returns_unavailable_when_feature_flag_disabled()
    {
        Config::set('customer-care.meta_social_enabled', false);

        $user = User::factory()->create();
        [$store, $connection, $channel] = $this->createStoreWithConnection($user, 'instagram', 'active');

        $adapter = new MetaSocialSupportChannelAdapter('instagram');
        $caps = $adapter->getCapabilities($channel);

        $sendCap = collect($caps)->firstWhere('capability', 'send_messages');
        $this->assertEquals('unavailable', $sendCap['status']);
    }

    public function test_meta_social_returns_unavailable_when_connection_inactive()
    {
        $user = User::factory()->create();
        [$store, $connection, $channel] = $this->createStoreWithConnection($user, 'instagram', 'inactive');

        $adapter = new MetaSocialSupportChannelAdapter('instagram');
        $caps = $adapter->getCapabilities($channel);

        $sendCap = collect($caps)->firstWhere('capability', 'send_messages');
        $this->assertEquals('unavailable', $sendCap['status']);
    }

    public function test_meta_social_returns_available_when_enabled_and_active()
    {
        $user = User::factory()->create();
        [$store, $connection, $channel] = $this->createStoreWithConnection($user, 'instagram', 'active');

        $adapter = new MetaSocialSupportChannelAdapter('instagram');
        $caps = $adapter->getCapabilities($channel);

        $sendCap = collect($caps)->firstWhere('capability', 'send_messages');
        $this->assertEquals('available', $sendCap['status']);
    }

    // ──────────────────────────────────────────────
    // 2. Inbound Webhook Projection & Idempotency
    // ──────────────────────────────────────────────

    public function test_inbound_webhook_projection_is_idempotent()
    {
        $user = User::factory()->create();
        [$store, $connection, $channel] = $this->createStoreWithConnection($user, 'instagram', 'active');

        $payload = [
            'store_id' => $store->id,
            'event_id' => 'evt_1001',
            'thread_id' => 'thread_abc',
            'thread_type' => 'dm',
            'sender_id' => 'meta_user_99',
            'body' => 'Merhaba, siparişimi ne zaman kargolarsınız?',
        ];

        $adapter = new MetaSocialSupportChannelAdapter('instagram');

        // First projection
        $result1 = $adapter->projectInboundWebhook($channel, $payload);
        $this->assertTrue($result1['success']);
        $this->assertTrue($result1['projected']);

        // Duplicate event projection
        $result2 = $adapter->projectInboundWebhook($channel, $payload);
        $this->assertTrue($result2['success']);
        $this->assertFalse($result2['projected']); // Ignored
    }

    public function test_raw_webhook_payload_does_not_leak_to_support_message()
    {
        $user = User::factory()->create();
        [$store, $connection, $channel] = $this->createStoreWithConnection($user, 'facebook', 'active');

        $payload = [
            'store_id' => $store->id,
            'event_id' => 'evt_2002',
            'thread_id' => 'thread_xyz',
            'thread_type' => 'comment',
            'sender_id' => 'meta_user_88',
            'body' => 'Ürünün fiyatı nedir?',
            'raw_metadata_leak' => 'extremely_sensitive_pii_token_and_details',
        ];

        $adapter = new MetaSocialSupportChannelAdapter('facebook');
        $result = $adapter->projectInboundWebhook($channel, $payload);
        $this->assertTrue($result['success']);

        $message = SupportMessage::find($result['message_id']);
        $this->assertNotNull($message);
        $this->assertNull($message->metadata_json, 'Raw metadata must not leak into metadata_json');
    }

    // ──────────────────────────────────────────────
    // 3. Outbox sendReply and Tenant Isolation
    // ──────────────────────────────────────────────

    public function test_send_reply_fail_closed_if_connection_missing()
    {
        $user = User::factory()->create();
        [$store, $connection, $channel] = $this->createStoreWithConnection($user, 'instagram', 'inactive'); // inactive connection

        // Create a conversation for this store
        $conversation = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'instagram_thread_t1',
            'store_id' => $store->id,
            'source_type' => 'instagram',
            'status' => 'open',
        ]);

        $adapter = new MetaSocialSupportChannelAdapter('instagram');
        $result = $adapter->sendReply($channel, 'instagram_thread_t1', 'Merhaba');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('bağlantısı bulunamadı', $result['message']);
    }

    public function test_send_reply_blocks_cross_store_idor()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        [$store1, $conn1, $channel1] = $this->createStoreWithConnection($user1, 'instagram', 'active');
        [$store2, $conn2, $channel2] = $this->createStoreWithConnection($user2, 'instagram', 'active');

        // Create conversation for store 2
        $conversation2 = SupportConversation::create([
            'support_channel_id' => $channel2->id,
            'external_conversation_id' => 'instagram_thread_shared',
            'store_id' => $store2->id,
            'source_type' => 'instagram',
            'status' => 'open',
        ]);

        $adapter = new MetaSocialSupportChannelAdapter('instagram');
        // channel 1 tries to send reply to conversation of store 2
        $result = $adapter->sendReply($channel1, 'instagram_thread_shared', 'Merhaba');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('bu mağazaya ait değil', $result['message'] ?? '');
    }

    // ──────────────────────────────────────────────
    // 4. Policy Engine Constraints
    // ──────────────────────────────────────────────

    public function test_instagram_comment_policy_blocks_links_and_pii()
    {
        $policyEngine = app(\App\Services\Support\Policy\SupportPolicyEngine::class);

        // Instagram comment must block phone number
        $result = $policyEngine->validate('Bize 0532 111 22 33 numaralı telefondan ulaşın.', 'instagram_comment');
        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('telefon numarası', $result['reason']);

        // Instagram comment must block order id keyword
        $result2 = $policyEngine->validate('Sipariş no bilginizi yazar mısınız?', 'instagram_comment');
        $this->assertFalse($result2['allowed']);
        $this->assertStringContainsString('siparis no', $result2['reason']);
    }

    // ──────────────────────────────────────────────
    // 5. Revision Requisitions (P0-1 & P1-2)
    // ──────────────────────────────────────────────

    public function test_send_reply_fails_closed_when_no_connector_bound()
    {
        // Unbind the mock provider
        $this->app->offsetUnset(MetaSocialConnectorInterface::class);

        $user = User::factory()->create();
        [$store, $connection, $channel] = $this->createStoreWithConnection($user, 'instagram', 'active');

        $conversation = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'instagram_thread_t2',
            'store_id' => $store->id,
            'source_type' => 'instagram',
            'status' => 'open',
        ]);

        $adapter = new MetaSocialSupportChannelAdapter('instagram');
        $result = $adapter->sendReply($channel, 'instagram_thread_t2', 'Merhaba');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('connector\'ü bulunamadı', $result['message']);

        // Assert getCapabilities returns unavailable for send_messages
        $caps = $adapter->getCapabilities($channel);
        $sendCap = collect($caps)->firstWhere('capability', 'send_messages');
        $this->assertEquals('unavailable', $sendCap['status']);
    }

    public function test_outbox_dispatch_does_not_succeed_without_connector()
    {
        // Unbind mock
        $this->app->offsetUnset(MetaSocialConnectorInterface::class);

        $user = User::factory()->create();
        [$store, $connection, $channel] = $this->createStoreWithConnection($user, 'instagram', 'active');

        $conversation = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'instagram_thread_t3',
            'store_id' => $store->id,
            'source_type' => 'instagram',
            'status' => 'open',
        ]);

        $message = SupportMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'sender_type' => 'agent',
            'message_type' => 'text',
            'body_encrypted' => 'Merhaba',
            'delivery_status' => 'pending',
        ]);

        $outboxService = app(SupportOutboxService::class);
        $dispatch = $outboxService->enqueue($message);

        // Process dispatch
        $outboxService->sendDispatch($dispatch);

        $this->assertEquals('failed', $dispatch->fresh()->status);
        $this->assertEquals('pending', $message->fresh()->delivery_status);
        $this->assertStringContainsString('connector\'ü bulunamadı', $dispatch->fresh()->last_error);

        // Check no agent action was created
        $this->assertFalse(SupportAgentAction::where('conversation_id', $conversation->id)->exists());
    }
}
