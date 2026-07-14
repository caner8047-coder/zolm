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
use App\Services\Support\GoogleBusinessSupportChannelAdapter;
use App\Services\Support\GoogleBusinessConnectorInterface;
use App\Services\Support\SupportOutboxService;
use App\Services\Support\AI\CustomerCareAutomationGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

class GoogleBusinessSupportChannelAdapterTest extends TestCase
{
    use RefreshDatabase, CustomerCareTestHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupSystemActor();
        Config::set('customer-care.enabled', true);
        Config::set('customer-care.google_reviews_enabled', true);
        Config::set('customer-care.auto_reply_enabled', true);

        // Bind mock Google Business Connector by default
        $mock = $this->createMock(GoogleBusinessConnectorInterface::class);
        $mock->method('reply')->willReturn('google_reply_test_id');
        $this->app->instance(GoogleBusinessConnectorInterface::class, $mock);
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
            'store_name' => 'GBP Store ' . uniqid(),
            'marketplace' => 'trendyol',
            'is_active' => true,
        ]);

        $connection = IntegrationConnection::create([
            'store_id' => $store->id,
            'provider' => 'google_business',
            'auth_type' => 'oauth',
            'status' => $status,
            'credentials_encrypted' => ['token' => 'google_token_999'],
        ]);

        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'google_business',
            'name' => 'Google Business Reviews',
            'status' => 'active',
            'is_enabled' => true,
        ]);

        return [$store, $connection, $channel];
    }

    // ──────────────────────────────────────────────
    // 1. Review Projection & Idempotency
    // ──────────────────────────────────────────────

    public function test_review_projection_is_idempotent()
    {
        $user = User::factory()->create();
        [$store, $connection, $channel] = $this->createStoreWithConnection($user, 'active');

        $payload = [
            'store_id' => $store->id,
            'review_id' => 'review_abc_123',
            'rating' => 5,
            'reviewer_name' => 'Canan Yılmaz',
            'comment' => 'Harika bir hizmet, bayıldım!',
            'location_id' => 'loc_999',
        ];

        $adapter = new GoogleBusinessSupportChannelAdapter();

        $result1 = $adapter->projectReview($channel, $payload);
        $this->assertTrue($result1['success']);
        $this->assertTrue($result1['projected']);

        $result2 = $adapter->projectReview($channel, $payload);
        $this->assertTrue($result2['success']);
        $this->assertFalse($result2['projected']); // Idempotent block
    }

    public function test_cross_store_review_idor_is_blocked()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        [$store1, $conn1, $channel1] = $this->createStoreWithConnection($user1, 'active');
        [$store2, $conn2, $channel2] = $this->createStoreWithConnection($user2, 'active');

        $payload = [
            'store_id' => $store2->id, // Store 2 payload
            'review_id' => 'review_cross_123',
            'rating' => 4,
            'reviewer_name' => 'Veli Demir',
            'comment' => 'Güzel servis.',
        ];

        $adapter = new GoogleBusinessSupportChannelAdapter();
        // Trying to project payload of store 2 into channel 1 of store 1
        $result = $adapter->projectReview($channel1, $payload);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Mağaza eşleşmesi başarısız', $result['message']);
    }

    // ──────────────────────────────────────────────
    // 2. Policy Engine Reputation Response Guard
    // ──────────────────────────────────────────────

    public function test_google_reviews_policy_restricts_outside_redirects_and_aggressive_defense()
    {
        $policyEngine = app(\App\Services\Support\Policy\SupportPolicyEngine::class);

        // Blocks link sharing
        $resLink = $policyEngine->validate('Bize sitemizden ulaşın: www.zolm.com', 'google_business');
        $this->assertFalse($resLink['allowed']);

        // Blocks "bize DM atın" redirection keyword
        $resRedirect = $policyEngine->validate('Yaşanan aksaklık için özür dileriz. Lütfen Instagramdan bize direkt mesaj / dm atın.', 'google_business');
        $this->assertFalse($resRedirect['allowed']);
        $this->assertStringContainsString('dm atin', $resRedirect['reason']);

        // Blocks "hata bizde degil" aggressive excuses
        $resExcuse = $policyEngine->validate('Bu olayda hata bizde değil, kargo firmasında.', 'google_business');
        $this->assertFalse($resExcuse['allowed']);
        $this->assertStringContainsString('hata bizde degil', $resExcuse['reason']);
    }

    // ──────────────────────────────────────────────
    // 3. Automation Gate Rules for GBP Yorumları
    // ──────────────────────────────────────────────

    public function test_low_rating_auto_replies_are_blocked()
    {
        $user = User::factory()->create();
        [$store, $connection, $channel] = $this->createStoreWithConnection($user, 'active');

        Config::set('customer-care.pilot_store_allowlist', [$store->id]);
        Config::set('customer-care.auto_reply_max_per_hour', 10);
        Config::set('customer-care.business_hours_auto_reply_enabled', true);
        Config::set('customer-care.reliability_enabled', true);
        Config::set('customer-care.circuit_breaker_enabled', true);
        $this->seedPassEval($store->id);

        // Create a conversation representing 2-star GBP review
        $conversation = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'store_id' => $store->id,
            'external_conversation_id' => 'google_review_bad',
            'source_type' => 'google_business',
            'status' => 'open',
            'ai_mode' => 'automatic',
            'source_reference_json' => ['rating' => 2, 'reviewer_name' => 'Bad Reviewer'],
        ]);

        $gate = app(CustomerCareAutomationGate::class);
        $result = $gate->canAutomate($conversation, 90);

        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('Düşük yıldızlı', $result['reason']);
    }

    public function test_high_rating_auto_replies_only_allowed_via_specific_config()
    {
        $user = User::factory()->create();
        [$store, $connection, $channel] = $this->createStoreWithConnection($user, 'active');

        // Create a conversation representing 5-star GBP review
        $conversation = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'store_id' => $store->id,
            'external_conversation_id' => 'google_review_good',
            'source_type' => 'google_business',
            'status' => 'open',
            'ai_mode' => 'automatic',
            'source_reference_json' => ['rating' => 5, 'reviewer_name' => 'Good Reviewer'],
        ]);

        $gate = app(CustomerCareAutomationGate::class);

        // 1. When config google_reviews_auto_reply_enabled is false
        Config::set('customer-care.google_reviews_auto_reply_enabled', false);
        $result1 = $gate->canAutomate($conversation, 90);
        $this->assertFalse($result1['allowed']);

        // 2. When config is enabled, but other pilot thresholds check (e.g. pilot list check)
        Config::set('customer-care.google_reviews_auto_reply_enabled', true);
        Config::set('customer-care.pilot_store_allowlist', [$store->id]);
        Config::set('customer-care.auto_reply_max_per_hour', 10);
        Config::set('customer-care.business_hours_auto_reply_enabled', true);
        Config::set('customer-care.reliability_enabled', true);
        Config::set('customer-care.circuit_breaker_enabled', true);
        $this->seedPassEval($store->id);

        $result2 = $gate->canAutomate($conversation, 90);
        $this->assertTrue($result2['allowed'], $result2['reason'] ?? '');
    }

    // ──────────────────────────────────────────────
    // 4. Reputation Metrics Calculations & Empty State
    // ──────────────────────────────────────────────

    public function test_reputation_metrics_returns_empty_when_no_data()
    {
        $adapter = new GoogleBusinessSupportChannelAdapter();
        $metrics = $adapter->getReputationMetrics(9999);
        $this->assertEmpty($metrics, 'Must return empty array on empty state to prevent fake metrics creation');
    }

    public function test_reputation_metrics_calculates_correctly_from_data()
    {
        $user = User::factory()->create();
        [$store, $connection, $channel] = $this->createStoreWithConnection($user, 'active');

        $adapter = new GoogleBusinessSupportChannelAdapter();

        // Project 3 reviews (5 stars, 4 stars, 1 star)
        $adapter->projectReview($channel, [
            'store_id' => $store->id,
            'review_id' => 'rev_1',
            'rating' => 5,
            'reviewer_name' => 'User A',
            'comment' => 'Mükemmel!',
        ]);

        $adapter->projectReview($channel, [
            'store_id' => $store->id,
            'review_id' => 'rev_2',
            'rating' => 4,
            'reviewer_name' => 'User B',
            'comment' => 'Güzel.',
        ]);

        $adapter->projectReview($channel, [
            'store_id' => $store->id,
            'review_id' => 'rev_3',
            'rating' => 1,
            'reviewer_name' => 'User C',
            'comment' => 'Kötü.',
        ]);

        $metrics = $adapter->getReputationMetrics($store->id);

        $this->assertEquals(3, $metrics['total_reviews']);
        $this->assertEquals(3.3, $metrics['average_rating']);
        $this->assertEquals(1, $metrics['negative_reviews']);
        $this->assertEquals(3, $metrics['unanswered_reviews']);
    }

    // ──────────────────────────────────────────────
    // 5. Revision Requisitions (P0-2)
    // ──────────────────────────────────────────────

    public function test_send_reply_fails_closed_when_no_connector_bound()
    {
        // Unbind the mock provider
        $this->app->offsetUnset(GoogleBusinessConnectorInterface::class);

        $user = User::factory()->create();
        [$store, $connection, $channel] = $this->createStoreWithConnection($user, 'active');

        $conversation = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'google_review_r1',
            'store_id' => $store->id,
            'source_type' => 'google_business',
            'status' => 'open',
        ]);

        $adapter = new GoogleBusinessSupportChannelAdapter();
        $result = $adapter->sendReply($channel, 'google_review_r1', 'Yanıt');

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
        $this->app->offsetUnset(GoogleBusinessConnectorInterface::class);

        $user = User::factory()->create();
        [$store, $connection, $channel] = $this->createStoreWithConnection($user, 'active');

        $conversation = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'google_review_r2',
            'store_id' => $store->id,
            'source_type' => 'google_business',
            'status' => 'open',
        ]);

        $message = SupportMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'sender_type' => 'agent',
            'message_type' => 'text',
            'body_encrypted' => 'Yanıt',
            'delivery_status' => 'pending',
        ]);

        $outboxService = app(SupportOutboxService::class);
        $dispatch = $outboxService->enqueue($message);

        // Process dispatch
        $outboxService->sendDispatch($dispatch);

        $this->assertEquals('failed', $dispatch->fresh()->status);
        $this->assertEquals('pending', $message->fresh()->delivery_status);
        $this->assertStringContainsString('connector\'ü bulunamadı', $dispatch->fresh()->last_error);

        // Check no action was logged
        $this->assertFalse(SupportAgentAction::where('conversation_id', $conversation->id)->exists());
    }
}
