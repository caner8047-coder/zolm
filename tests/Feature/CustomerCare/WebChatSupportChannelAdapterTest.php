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
use App\Models\IntegrationConnection;
use App\Services\Support\WebChatSupportChannelAdapter;
use App\Services\Support\SupportOutboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

class WebChatSupportChannelAdapterTest extends TestCase
{
    use RefreshDatabase, CustomerCareTestHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupSystemActor();
        Config::set('customer-care.enabled', true);
        Config::set('customer-care.web_chat_enabled', true);
    }

    protected function createStoreWithConnection(User $user, string $status = 'active'): array
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
            'store_name' => 'WebChat Store ' . uniqid(),
            'marketplace' => 'trendyol',
            'is_active' => true,
        ]);

        $connection = IntegrationConnection::create([
            'store_id' => $store->id,
            'provider' => 'web_chat',
            'auth_type' => 'token',
            'status' => $status,
            'credentials_encrypted' => ['secret_key' => 'shhh_its_secret'],
            'webhook_secret' => 'webhook_secret_key',
        ]);

        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'web_chat',
            'name' => 'Site Live Chat',
            'status' => 'active',
            'is_enabled' => true,
        ]);

        return [$store, $connection, $channel];
    }

    protected function signPayload(array $payload, string $secret): array
    {
        $rawJson = json_encode($payload);
        $payload['raw_json'] = $rawJson;
        $payload['signature'] = hash_hmac('sha256', $rawJson, $secret);
        return $payload;
    }

    // ──────────────────────────────────────────────
    // 1. Signature/Token Verification
    // ──────────────────────────────────────────────

    public function test_web_chat_signature_verification_succeeds_with_valid_hmac()
    {
        $payloadJson = json_encode(['store_id' => 1, 'body' => 'Test']);
        $secret = 'my_secret';

        $signature = hash_hmac('sha256', $payloadJson, $secret);

        $adapter = new WebChatSupportChannelAdapter();
        $this->assertTrue($adapter->verifySignature($payloadJson, $signature, $secret));
        $this->assertFalse($adapter->verifySignature($payloadJson, 'invalid_signature', $secret));
    }

    // ──────────────────────────────────────────────
    // 2. Inbound Message Projection & Idempotency
    // ──────────────────────────────────────────────

    public function test_inbound_message_projection_is_idempotent()
    {
        $user = User::factory()->create();
        [$store, $connection, $channel] = $this->createStoreWithConnection($user, 'active');

        $payload = [
            'store_id' => $store->id,
            'session_id' => 'sess_user_1',
            'idempotency_key' => 'msg_key_1001',
            'body' => 'Lütfen bana yardım edin.',
            'is_online' => true,
        ];

        $signedPayload = $this->signPayload($payload, $connection->webhook_secret);

        $adapter = new WebChatSupportChannelAdapter();

        $result1 = $adapter->projectMessage($channel, $signedPayload);
        $this->assertTrue($result1['success']);
        $this->assertTrue($result1['projected']);

        $result2 = $adapter->projectMessage($channel, $signedPayload);
        $this->assertTrue($result2['success']);
        $this->assertFalse($result2['projected']); // Duplicate block
    }

    public function test_cross_store_webchat_mismatch_blocks_projection()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        [$store1, $conn1, $channel1] = $this->createStoreWithConnection($user1, 'active');
        [$store2, $conn2, $channel2] = $this->createStoreWithConnection($user2, 'active');

        $payload = [
            'store_id' => $store2->id, // store 2 ID
            'session_id' => 'sess_user_1',
            'idempotency_key' => 'msg_key_2002',
            'body' => 'İyi günler.',
        ];

        $signedPayload = $this->signPayload($payload, $conn2->webhook_secret);

        $adapter = new WebChatSupportChannelAdapter();
        // Trying to project payload of store 2 into channel 1 of store 1
        $result = $adapter->projectMessage($channel1, $signedPayload);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Mağaza eşleşmesi başarısız', $result['message']);
    }

    public function test_session_id_is_hashed_to_protect_pii()
    {
        $user = User::factory()->create();
        [$store, $connection, $channel] = $this->createStoreWithConnection($user, 'active');

        $sessionId = 'sensitive_guest_session_id_999';

        $payload = [
            'store_id' => $store->id,
            'session_id' => $sessionId,
            'idempotency_key' => 'msg_key_3003',
            'body' => 'Sipariş durumunu öğrenmek istiyorum.',
        ];

        $signedPayload = $this->signPayload($payload, $connection->webhook_secret);

        $adapter = new WebChatSupportChannelAdapter();
        $result = $adapter->projectMessage($channel, $signedPayload);
        $this->assertTrue($result['success']);

        $conversation = SupportConversation::find($result['conversation_id']);
        $this->assertNotNull($conversation);

        $sessionHash = hash('sha256', $sessionId . $connection->webhook_secret);
        $this->assertEquals('web_chat_session_' . $sessionHash, $conversation->external_conversation_id);
        $this->assertStringNotContainsString($sessionId, $conversation->external_conversation_id, 'Sensitive raw session ID must not be stored');
    }

    // ──────────────────────────────────────────────
    // 3. PII Redaction inside Inbound Message
    // ──────────────────────────────────────────────

    public function test_inbound_body_is_redacted_of_pii()
    {
        $user = User::factory()->create();
        [$store, $connection, $channel] = $this->createStoreWithConnection($user, 'active');

        $payload = [
            'store_id' => $store->id,
            'session_id' => 'sess_user_99',
            'idempotency_key' => 'msg_key_4004',
            'body' => 'Telefonum: 05321112233. Email adresim: test@zolm.com',
        ];

        $signedPayload = $this->signPayload($payload, $connection->webhook_secret);

        $adapter = new WebChatSupportChannelAdapter();
        $result = $adapter->projectMessage($channel, $signedPayload);
        $this->assertTrue($result['success']);

        $message = SupportMessage::where('conversation_id', $result['conversation_id'])->first();
        $this->assertNotNull($message);

        // Body must be redacted
        $this->assertStringNotContainsString('05321112233', $message->body_encrypted);
        $this->assertStringNotContainsString('test@zolm.com', $message->body_encrypted);
    }

    // ──────────────────────────────────────────────
    // 4. Offline/Online Delivery Checks (Fake Sent Prevention)
    // ──────────────────────────────────────────────

    public function test_send_reply_returns_queued_status_when_customer_offline()
    {
        $user = User::factory()->create();
        [$store, $connection, $channel] = $this->createStoreWithConnection($user, 'active');

        // Create session hash using connection secret
        $sessionHash = hash('sha256', 'sess_offline' . $connection->webhook_secret);
        $externalConversationId = 'web_chat_session_' . $sessionHash;

        // Create conversation marked as offline
        $conversation = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'store_id' => $store->id,
            'external_conversation_id' => $externalConversationId,
            'source_type' => 'web_chat',
            'status' => 'open',
            'source_reference_json' => [
                'session_hash' => $sessionHash,
                'is_online' => false, // Customer is offline
            ]
        ]);

        $adapter = new WebChatSupportChannelAdapter();
        $result = $adapter->sendReply($channel, $externalConversationId, 'Bizimle iletişime geçtiğiniz için teşekkürler.');

        $this->assertTrue($result['success']);
        $this->assertEquals('queued', $result['status'], 'Offline delivery status must be queued (no fake sent)');
        $this->assertStringContainsString('çevrimdışı', $result['message']);

        // Check SupportAgentAction was created with queued status
        $this->assertTrue(
            SupportAgentAction::where('conversation_id', $conversation->id)
                ->where('action', 'web_chat_queued')
                ->exists()
        );
    }

    public function test_send_reply_stays_queued_until_widget_ack_when_customer_online()
    {
        $user = User::factory()->create();
        [$store, $connection, $channel] = $this->createStoreWithConnection($user, 'active');

        $sessionHash = hash('sha256', 'sess_online' . $connection->webhook_secret);
        $externalConversationId = 'web_chat_session_' . $sessionHash;

        $conversation = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'store_id' => $store->id,
            'external_conversation_id' => $externalConversationId,
            'source_type' => 'web_chat',
            'status' => 'open',
            'source_reference_json' => [
                'session_hash' => $sessionHash,
                'is_online' => true, // Customer is online
            ]
        ]);

        $adapter = new WebChatSupportChannelAdapter();
        $result = $adapter->sendReply($channel, $externalConversationId, 'Yardımcı olayım.');

        $this->assertTrue($result['success']);
        $this->assertEquals('queued', $result['status']);

        $this->assertTrue(
            SupportAgentAction::where('conversation_id', $conversation->id)
                ->where('action', 'web_chat_outbox_handoff')
                ->exists()
        );
    }

    // ──────────────────────────────────────────────
    // 5. Revision Requisitions (P0-4 & P1-2)
    // ──────────────────────────────────────────────

    public function test_project_message_fails_closed_when_signature_missing()
    {
        $user = User::factory()->create();
        [$store, $connection, $channel] = $this->createStoreWithConnection($user, 'active');

        $payload = [
            'store_id' => $store->id,
            'session_id' => 'sess_user_1',
            'idempotency_key' => 'msg_key_1001',
            'body' => 'Lütfen bana yardım edin.',
        ];

        $adapter = new WebChatSupportChannelAdapter();
        $result = $adapter->projectMessage($channel, $payload); // Imza yok

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Eksik imza doğrulaması', $result['message']);
    }

    public function test_project_message_fails_closed_when_signature_invalid()
    {
        $user = User::factory()->create();
        [$store, $connection, $channel] = $this->createStoreWithConnection($user, 'active');

        $payload = [
            'store_id' => $store->id,
            'session_id' => 'sess_user_1',
            'idempotency_key' => 'msg_key_1001',
            'body' => 'Lütfen bana yardım edin.',
            'raw_json' => json_encode(['store_id' => $store->id]),
            'signature' => 'invalid_signature_hash',
        ];

        $adapter = new WebChatSupportChannelAdapter();
        $result = $adapter->projectMessage($channel, $payload);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Geçersiz imza doğrulaması', $result['message']);
    }

    public function test_outbox_dispatch_queued_status_when_customer_offline()
    {
        $user = User::factory()->create();
        [$store, $connection, $channel] = $this->createStoreWithConnection($user, 'active');

        $sessionHash = hash('sha256', 'sess_offline_outbox' . $connection->webhook_secret);
        $externalConversationId = 'web_chat_session_' . $sessionHash;

        $conversation = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'store_id' => $store->id,
            'external_conversation_id' => $externalConversationId,
            'source_type' => 'web_chat',
            'status' => 'open',
            'source_reference_json' => [
                'session_hash' => $sessionHash,
                'is_online' => false, // Customer is offline
            ]
        ]);

        $message = SupportMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'sender_type' => 'agent',
            'message_type' => 'text',
            'body_encrypted' => 'Offline yanıt',
            'delivery_status' => 'pending',
        ]);

        $outboxService = app(SupportOutboxService::class);
        $dispatch = $outboxService->enqueue($message);

        // Process dispatch
        $outboxService->sendDispatch($dispatch);

        $this->assertEquals('queued', $dispatch->fresh()->status, 'Offline outbox dispatch must remain queued');
        $this->assertEquals('queued', $message->fresh()->delivery_status, 'Offline message delivery status must remain queued');
    }

    public function test_project_message_uses_signed_raw_json_not_outer_payload_fields()
    {
        $user = User::factory()->create();
        [$store, $connection, $channel] = $this->createStoreWithConnection($user, 'active');

        // Signed payload A inside raw_json
        $signedData = [
            'store_id' => $store->id,
            'session_id' => 'sess_signed',
            'idempotency_key' => 'idemp_signed',
            'body' => 'İmzalı mesaj',
            'is_online' => true,
        ];
        $rawJson = json_encode($signedData);

        // Tampered outer payload B
        $payload = [
            'store_id' => $store->id,
            'session_id' => 'sess_manipulated',
            'idempotency_key' => 'idemp_manipulated',
            'body' => 'Manipüle mesaj',
            'raw_json' => $rawJson,
            'signature' => hash_hmac('sha256', $rawJson, $connection->webhook_secret),
        ];

        $adapter = new WebChatSupportChannelAdapter();
        $result = $adapter->projectMessage($channel, $payload);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['projected']);

        // 1. Database check: Verify body is "İmzalı mesaj" and NOT "Manipüle mesaj"
        $message = SupportMessage::where('conversation_id', $result['conversation_id'])->first();
        $this->assertNotNull($message);
        $this->assertEquals('İmzalı mesaj', $message->body_encrypted);

        // 2. Verify external_message_id is based on signed key, not manipulated key
        $sessionHash = hash('sha256', 'sess_signed' . $connection->webhook_secret);
        $expectedExternalMessageId = 'web_chat_msg_' . hash('sha256', $sessionHash . ':idemp_signed');
        $this->assertEquals($expectedExternalMessageId, $message->external_message_id);

        // 3. Verify conversation session is based on signed session, not manipulated session
        $conversation = SupportConversation::find($result['conversation_id']);
        $this->assertEquals('web_chat_session_' . $sessionHash, $conversation->external_conversation_id);
    }
}
