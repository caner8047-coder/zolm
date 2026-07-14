<?php

namespace Tests\Feature\CustomerCare;

use Tests\TestCase;
use App\Models\User;
use App\Models\MarketplaceStore;
use App\Models\LegalEntity;
use App\Models\SupportChannel;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\SupportDispatch;
use App\Models\SupportUsage;
use App\Services\Support\CustomerCareUsageService;
use App\Services\Support\SupportReplyService;
use App\Services\Support\SupportOutboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Artisan;

class CustomerCareUsageTest extends TestCase
{
    use RefreshDatabase, CustomerCareTestHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupSystemActor();
        Config::set('customer-care.enabled', true);
        // Set small limits for tests
        Config::set('customer-care.plans.monthly_ai_drafts', 3);
        Config::set('customer-care.plans.monthly_auto_replies', 2);
        Config::set('customer-care.plans.knowledge_suggestions_per_day', 5);
    }

    protected function createStoreWithChannel(User $user, string $key = 'whatsapp'): array
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
            'store_name' => 'Store ' . uniqid(),
            'marketplace' => 'trendyol',
            'is_active' => true,
        ]);

        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => $key,
            'name' => 'Support Channel',
            'status' => 'active',
            'is_enabled' => true,
        ]);

        // Add capability to avoid block
        \App\Models\SupportChannelCapability::create([
            'support_channel_id' => $channel->id,
            'capability' => 'send_messages',
            'status' => 'available',
            'source' => 'test',
        ]);

        return [$store, $channel];
    }

    // ──────────────────────────────────────────────
    // 1. Quota Usage & Limits Increment
    // ──────────────────────────────────────────────

    public function test_usage_increments_on_ai_draft_success()
    {
        $user = User::factory()->create();
        [$store, $channel] = $this->createStoreWithChannel($user);

        $conversation = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'store_id' => $store->id,
            'external_conversation_id' => 'wa_1',
            'status' => 'open',
            'source_type' => 'whatsapp',
            'ai_mode' => 'automatic',
            'ownership_status' => 'ai',
            'last_inbound_at' => now(),
        ]);

        SupportMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'message_type' => 'text',
            'body_encrypted' => 'Merhaba',
            'delivery_status' => 'received',
        ]);

        // Inbound message to allow draft generation
        SupportMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'message_type' => 'text',
            'body_encrypted' => 'Merhaba',
        ]);

        Config::set('customer-care.demo_mode', true);

        $replyService = app(SupportReplyService::class);
        $result = $replyService->generateAiDraft($conversation);
        $this->assertTrue($result['success'] ?? false, json_encode($result, JSON_UNESCAPED_UNICODE));

        // Usage incremented
        $this->assertEquals(1, SupportUsage::where('store_id', $store->id)->where('metric', 'ai_drafts')->value('count'));
    }

    public function test_usage_increments_on_auto_reply_success_only()
    {
        $user = User::factory()->create();
        [$store, $channel] = $this->createStoreWithChannel($user);

        $conversation = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'store_id' => $store->id,
            'external_conversation_id' => 'wa_1',
            'status' => 'open',
            'source_type' => 'whatsapp',
            'ai_mode' => 'automatic',
            'ownership_status' => 'ai',
            'last_inbound_at' => now(),
        ]);

        SupportMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'message_type' => 'text',
            'body_encrypted' => 'Merhaba',
            'delivery_status' => 'received',
        ]);

        $message = SupportMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'sender_type' => 'ai', // AI auto reply
            'message_type' => 'text',
            'body_encrypted' => 'Merhaba!',
            'delivery_status' => 'draft',
        ]);

        $dispatch = SupportDispatch::create([
            'conversation_id' => $conversation->id,
            'support_channel_id' => $channel->id,
            'message_id' => $message->id,
            'status' => 'pending',
            'idempotency_key' => 'idemp_key_1',
        ]);

        // Mock WhatsApp environment requirements
        Config::set('customer-care.pilot_store_allowlist', [$store->id]);
        Config::set('customer-care.auto_reply_enabled', true);
        Config::set('customer-care.auto_reply_max_per_hour', 10);
        Config::set('customer-care.business_hours_auto_reply_enabled', true);
        Config::set('customer-care.reliability_enabled', true);
        Config::set('customer-care.circuit_breaker_enabled', true);
        $this->seedPassEval($store->id);
        \App\Models\SupportAiRun::create([
            'store_id' => $store->id,
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'prompt_template_key' => 'usage_test',
            'response_raw' => 'Merhaba!',
            'confidence_score' => 95,
            'sources_used_json' => [[
                'type' => 'policy_validation',
                'name' => 'Deterministik düşük risk/politika kontrolü',
                'record_id' => 'policy:' . \App\Services\Support\Policy\SupportPolicyEngine::VERSION,
                'version' => \App\Services\Support\Policy\SupportPolicyEngine::VERSION,
                'freshness_at' => now()->toIso8601String(),
                'is_stale' => false,
            ]],
            'status' => 'automatic_approved',
        ]);

        // WaContact and WaConversation for WhatsApp adapter logic
        $contact = \App\Models\WaContact::create([
            'store_id' => $store->id,
            'phone_e164_encrypted' => '+905321112233',
            'phone_hash' => \App\Models\WaContact::hashPhone('+905321112233'),
            'first_name' => 'Ali',
        ]);
        \App\Models\WaConversation::create([
            'contact_id' => $contact->id,
            'store_id' => $store->id,
            'status' => 'open',
        ]);
        \App\Models\WaConsentEvent::create([
            'contact_id' => $contact->id,
            'store_id' => $store->id,
            'purpose' => 'support',
            'action' => 'granted',
            'source' => 'test',
            'consent_timestamp' => now(),
        ]);

        $outboxService = app(SupportOutboxService::class);

        // Successful dispatch
        $outboxService->processPendingDispatches();

        $this->assertEquals('accepted', $message->fresh()->delivery_status, (string) $dispatch->fresh()->last_error);
        // usage incremented
        $this->assertEquals(1, SupportUsage::where('store_id', $store->id)->where('metric', 'auto_replies')->value('count'));
    }

    public function test_blocked_auto_reply_does_not_increment_usage()
    {
        $user = User::factory()->create();
        [$store, $channel] = $this->createStoreWithChannel($user);

        $conversation = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'store_id' => $store->id,
            'external_conversation_id' => 'wa_1',
            'status' => 'open',
            'source_type' => 'whatsapp',
        ]);

        $message = SupportMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'sender_type' => 'ai',
            'message_type' => 'text',
            'body_encrypted' => 'Yasaklı havale kelimesi.', // Policy violation
            'delivery_status' => 'draft',
        ]);

        $dispatch = SupportDispatch::create([
            'conversation_id' => $conversation->id,
            'support_channel_id' => $channel->id,
            'message_id' => $message->id,
            'status' => 'pending',
            'idempotency_key' => 'idemp_key_fail',
        ]);

        $outboxService = app(SupportOutboxService::class);
        $outboxService->processPendingDispatches();

        $this->assertEquals('cancelled', $message->fresh()->delivery_status);
        // usage not incremented
        $this->assertEquals(0, (int)SupportUsage::where('store_id', $store->id)->where('metric', 'auto_replies')->value('count'));
    }

    // ──────────────────────────────────────────────
    // 2. Limit Block & Enforcement
    // ──────────────────────────────────────────────

    public function test_ai_draft_blocked_when_quota_limit_reached()
    {
        $user = User::factory()->create();
        [$store, $channel] = $this->createStoreWithChannel($user);

        $conversation = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'store_id' => $store->id,
            'external_conversation_id' => 'wa_1',
            'status' => 'open',
            'source_type' => 'whatsapp',
        ]);

        SupportMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'message_type' => 'text',
            'body_encrypted' => 'Fiyat?',
        ]);

        // Force set usage count to the limit (limit is 3)
        SupportUsage::create([
            'store_id' => $store->id,
            'metric' => 'ai_drafts',
            'month' => now()->format('Y-m'),
            'count' => 3,
        ]);

        Config::set('customer-care.demo_mode', true);

        $replyService = app(SupportReplyService::class);
        $result = $replyService->generateAiDraft($conversation);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('limit aşımı', $result['message']);
    }

    public function test_manual_reply_not_blocked_by_auto_reply_quota()
    {
        $user = User::factory()->create();
        [$store, $channel] = $this->createStoreWithChannel($user);

        $conversation = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'store_id' => $store->id,
            'external_conversation_id' => 'wa_1',
            'status' => 'open',
            'source_type' => 'whatsapp',
            'last_inbound_at' => now(),
        ]);

        // Auto replies limit reached (limit is 2)
        SupportUsage::create([
            'store_id' => $store->id,
            'metric' => 'auto_replies',
            'month' => now()->format('Y-m'),
            'count' => 2,
        ]);

        // Agent/manual outbound message
        $message = SupportMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'sender_type' => 'agent', // Manual agent reply
            'message_type' => 'text',
            'body_encrypted' => 'Temsilci yanıtı.',
            'delivery_status' => 'draft',
        ]);

        $dispatch = SupportDispatch::create([
            'conversation_id' => $conversation->id,
            'support_channel_id' => $channel->id,
            'message_id' => $message->id,
            'status' => 'pending',
            'idempotency_key' => 'idemp_key_agent',
        ]);

        // WaContact & WaConversation
        $contact = \App\Models\WaContact::create([
            'store_id' => $store->id,
            'phone_e164_encrypted' => '+905321112233',
            'phone_hash' => \App\Models\WaContact::hashPhone('+905321112233'),
            'first_name' => 'Ali',
        ]);
        \App\Models\WaConversation::create([
            'contact_id' => $contact->id,
            'store_id' => $store->id,
            'status' => 'open',
        ]);
        \App\Models\WaConsentEvent::create([
            'contact_id' => $contact->id,
            'store_id' => $store->id,
            'purpose' => 'support',
            'action' => 'granted',
            'source' => 'test',
            'consent_timestamp' => now(),
        ]);

        $outboxService = app(SupportOutboxService::class);
        $outboxService->processPendingDispatches();

        $this->assertEquals('accepted', $message->fresh()->delivery_status);
        // agent_replies metric is incremented instead
        $this->assertEquals(1, SupportUsage::where('store_id', $store->id)->where('metric', 'agent_replies')->value('count'));
    }

    // ──────────────────────────────────────────────
    // 3. Cross-Store Isolation & Command
    // ──────────────────────────────────────────────

    public function test_cross_store_usage_isolation()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        [$store1, $chan1] = $this->createStoreWithChannel($user1);
        [$store2, $chan2] = $this->createStoreWithChannel($user2);

        $usageService = app(CustomerCareUsageService::class);

        // Store 1 usage incremented
        $usageService->incrementUsage($store1->id, 'ai_drafts', 2);

        $this->assertEquals(2, $usageService->checkLimit($store1->id, 'ai_drafts')['current']);
        $this->assertEquals(0, $usageService->checkLimit($store2->id, 'ai_drafts')['current']);
    }

    public function test_invalid_metric_throws_exception()
    {
        $user = User::factory()->create();
        [$store, $channel] = $this->createStoreWithChannel($user);

        $usageService = app(CustomerCareUsageService::class);

        $this->expectException(\InvalidArgumentException::class);
        $usageService->checkLimit($store->id, 'invalid_typo_metric');
    }

    public function test_successful_quota_usage_writes_append_only_event()
    {
        $user = User::factory()->create();
        [$store, $channel] = $this->createStoreWithChannel($user);

        $usageService = app(CustomerCareUsageService::class);

        $this->assertEquals(0, \App\Models\SupportUsageEvent::count());

        $usageService->incrementUsage($store->id, 'ai_drafts', 1);

        $this->assertEquals(1, \App\Models\SupportUsageEvent::count());
        $event = \App\Models\SupportUsageEvent::first();
        $this->assertEquals($store->id, $event->store_id);
        $this->assertEquals('ai_drafts', $event->metric);
        $this->assertNotNull($event->details_json['period_key'] ?? null);
    }

    public function test_blocked_auto_reply_does_not_write_event()
    {
        $user = User::factory()->create();
        [$store, $channel] = $this->createStoreWithChannel($user);

        $conversation = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'store_id' => $store->id,
            'external_conversation_id' => 'wa_1',
            'status' => 'open',
            'source_type' => 'whatsapp',
        ]);

        $message = SupportMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'sender_type' => 'ai',
            'message_type' => 'text',
            'body_encrypted' => 'Yasaklı havale kelimesi.', // Policy violation
            'delivery_status' => 'draft',
        ]);

        $dispatch = SupportDispatch::create([
            'conversation_id' => $conversation->id,
            'support_channel_id' => $channel->id,
            'message_id' => $message->id,
            'status' => 'pending',
            'idempotency_key' => 'idemp_key_fail',
        ]);

        $outboxService = app(SupportOutboxService::class);
        $outboxService->processPendingDispatches();

        $this->assertEquals('cancelled', $message->fresh()->delivery_status);

        // Assert no event was written for auto replies
        $this->assertEquals(0, \App\Models\SupportUsageEvent::where('metric', 'auto_replies')->count());
    }

    public function test_agent_replies_writes_event_and_is_unlimited()
    {
        $user = User::factory()->create();
        [$store, $channel] = $this->createStoreWithChannel($user);

        $usageService = app(CustomerCareUsageService::class);

        // Check limit of agent_replies is unlimited (PHP_INT_MAX)
        $limitCheck = $usageService->checkLimit($store->id, 'agent_replies');
        $this->assertTrue($limitCheck['allowed']);
        $this->assertEquals(PHP_INT_MAX, $limitCheck['limit']);

        // Increment agent replies and verify event is logged
        $usageService->incrementUsage($store->id, 'agent_replies', 1);

        $this->assertEquals(1, \App\Models\SupportUsageEvent::where('metric', 'agent_replies')->count());
    }

    public function test_usage_report_command_prints_table()
    {
        $user = User::factory()->create();
        [$store, $channel] = $this->createStoreWithChannel($user);

        $usageService = app(CustomerCareUsageService::class);
        $usageService->incrementUsage($store->id, 'ai_drafts', 1);

        $code = Artisan::call('customer-care:usage-report', [
            '--store' => $store->id,
        ]);

        $this->assertEquals(0, $code);
        $output = Artisan::output();
        $this->assertStringContainsString('Kullanım Raporu', $output);
        $this->assertStringContainsString($store->store_name, $output);
    }
}
