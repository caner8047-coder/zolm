<?php

namespace Tests\Feature\CustomerCare;

use Tests\TestCase;
use App\Models\MarketplaceStore;
use App\Models\User;
use App\Models\SupportChannel;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\SupportDispatch;
use App\Models\SupportAgentAction;
use App\Services\Support\CustomerCarePilotMonitorService;
use App\Services\Support\SupportReplyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

class CanaryCircuitBreakerTest extends TestCase
{
    use RefreshDatabase, CustomerCareTestHelper;

    protected MarketplaceStore $store;
    protected User $user;
    protected SupportChannel $channel;
    protected SupportConversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupSystemActor();

        $this->user = User::factory()->create(['role' => 'admin']);

        $legalEntity = \App\Models\LegalEntity::create([
            'user_id' => $this->user->id,
            'name' => 'Canary Store Legal',
            'tax_number' => '1234567890',
            'is_active' => true,
        ]);

        $this->store = MarketplaceStore::create([
            'user_id' => $this->user->id,
            'legal_entity_id' => $legalEntity->id,
            'store_name' => 'Canary Store',
            'marketplace' => 'trendyol',
            'is_active' => true,
        ]);

        $this->channel = SupportChannel::create([
            'store_id' => $this->store->id,
            'channel_type' => 'trendyol',
            'key' => 'trendyol',
            'name' => 'Trendyol Test Channel',
            'is_enabled' => true,
            'credentials_json' => [],
        ]);

        $this->channel->capabilities()->create([
            'capability' => 'send_messages',
            'status' => 'available',
            'source' => 'adapter',
        ]);

        $this->conversation = SupportConversation::create([
            'support_channel_id' => $this->channel->id,
            'external_conversation_id' => 'CONV888',
            'external_customer_id' => 'CUST888',
            'store_id' => $this->store->id,
            'source_type' => 'trendyol',
            'status' => 'open',
            'ai_mode' => 'automatic',
            'ownership_status' => 'ai',
        ]);

        Config::set('customer-care.enabled', true);
        Config::set('customer-care.pilot_dashboard_enabled', true);
        Config::set('customer-care.auto_reply_enabled', true);
        Config::set('customer-care.circuit_breaker_enabled', true);
        Config::set('customer-care.reliability_enabled', true);
        Config::set('customer-care.pilot_store_allowlist', [$this->store->id]);
        Config::set('customer-care.auto_reply_max_per_hour', 100);
        Config::set('customer-care.business_hours_auto_reply_enabled', true);

        // Mock Channel Manager
        $mockAdapter = $this->createMock(\App\Services\Support\SupportChannelAdapterInterface::class);
        $mockAdapter->method('canReply')->willReturn(true);
        $mockAdapter->method('sendReply')->willReturn(['success' => true, 'channel_message_id' => 'ch_msg_ok']);
        $mockAdapter->method('getOutboundTargetStatus')->willReturn('sent');

        $mockManager = $this->createMock(\App\Services\Support\SupportChannelManager::class);
        $mockManager->method('resolveForChannel')->willReturn($mockAdapter);
        $mockManager->method('resolve')->willReturn($mockAdapter);
        $this->app->instance(\App\Services\Support\SupportChannelManager::class, $mockManager);

        // Seed a passed Golden evaluation run so the eval gate passes
        $this->seedGoldenEvalEvidence($this->store->id);
        $this->seedPassLanguageGate($this->store->id);
        $this->seedShadowEvidence($this->store->id);
    }

    public function test_monitor_calculates_correct_metrics()
    {
        $monitorService = app(CustomerCarePilotMonitorService::class);

        // 1. Add dispatch failure
        $msg = SupportMessage::create([
            'conversation_id' => $this->conversation->id,
            'direction' => 'outbound',
            'sender_type' => 'ai',
            'message_type' => 'text',
            'body_encrypted' => 'Test',
            'body_preview' => 'Test',
            'delivery_status' => 'failed',
        ]);
        SupportDispatch::create([
            'message_id' => $msg->id,
            'support_channel_id' => $this->channel->id,
            'conversation_id' => $this->conversation->id,
            'status' => 'failed',
            'attempts' => 1,
            'idempotency_key' => 'idemp_1',
            'error_message' => 'API Timeout',
        ]);

        // 2. Add policy block action
        SupportAgentAction::create([
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->user->id,
            'action' => 'policy_block',
            'details_json' => ['reason' => 'blocked keyword'],
        ]);

        $metrics = $monitorService->getStoreMetrics($this->store->id);

        $this->assertEquals(1, $metrics['dispatch_failures_15m']);
        $this->assertEquals(1, $metrics['policy_blocks_15m']);
        $this->assertEquals('closed', $metrics['circuit_breaker_status']);
    }

    public function test_circuit_breaker_trips_due_to_recent_dispatch_failures()
    {
        Config::set('customer-care.max_dispatch_failures_15m', 2);
        $replyService = app(SupportReplyService::class);

        // Record 2 failures in the last 15 minutes
        for ($i = 0; $i < 2; $i++) {
            $msg = SupportMessage::create([
                'conversation_id' => $this->conversation->id,
                'direction' => 'outbound',
                'sender_type' => 'ai',
                'message_type' => 'text',
                'body_encrypted' => 'Fail ' . $i,
                'body_preview' => 'Fail ' . $i,
                'delivery_status' => 'failed',
            ]);
            SupportDispatch::create([
                'message_id' => $msg->id,
                'support_channel_id' => $this->channel->id,
                'conversation_id' => $this->conversation->id,
                'status' => 'failed',
                'attempts' => 1,
                'idempotency_key' => 'idemp_fail_' . $i,
            ]);
        }

        // Try sending automated reply - should fail due to Circuit Breaker OPEN
        $res = $replyService->sendAiReply($this->conversation, 'Otomatik Cevap', 85);

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('Circuit Breaker OPEN', $res['message']);
        $this->assertStringContainsString('dispatch hata limiti aşıldı', $res['message']);
    }

    public function test_circuit_breaker_trips_due_to_policy_blocks()
    {
        Config::set('customer-care.max_policy_blocks_15m', 2);
        $replyService = app(SupportReplyService::class);

        // Record 2 policy blocks in the last 15 minutes
        for ($i = 0; $i < 2; $i++) {
            SupportAgentAction::create([
                'conversation_id' => $this->conversation->id,
                'user_id' => $this->user->id,
                'action' => 'policy_block',
                'details_json' => ['reason' => 'blocked keyword'],
            ]);
        }

        $res = $replyService->sendAiReply($this->conversation, 'Otomatik Cevap', 85);

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('Circuit Breaker OPEN', $res['message']);
        $this->assertStringContainsString('politika engelleme limiti aşıldı', $res['message']);
    }

    public function test_manual_override_blocks_automatic_reply_but_allows_manual_reply()
    {
        $replyService = app(SupportReplyService::class);

        // 1. Manually trip circuit breaker
        Cache::put("circuit_breaker_forced_open_{$this->store->id}", true);
        Config::set('customer-care.circuit_breaker_enabled', false);

        // Automatic reply should be blocked
        $res = $replyService->sendAiReply($this->conversation, 'Otomatik Cevap', 85);
        $this->assertFalse($res['success']);
        $this->assertStringContainsString('Manual Override', $res['message']);

        // Manual representative reply should still be allowed
        $resManual = $replyService->sendAgentReply($this->conversation, 'Temsilci Cevabı', $this->user->id);
        $this->assertTrue($resManual['success']);
    }

    public function test_circuit_breaker_rate_limiting_hourly()
    {
        Config::set('customer-care.auto_reply_max_per_hour', 2);
        $replyService = app(SupportReplyService::class);

        // Record 2 AI messages sent in the last hour
        for ($i = 0; $i < 2; $i++) {
            SupportMessage::create([
                'conversation_id' => $this->conversation->id,
                'direction' => 'outbound',
                'sender_type' => 'ai',
                'message_type' => 'text',
                'body_encrypted' => 'Sent ' . $i,
                'body_preview' => 'Sent ' . $i,
                'delivery_status' => 'sent',
            ]);
        }

        $res = $replyService->sendAiReply($this->conversation, 'Otomatik Cevap', 85);
        $this->assertFalse($res['success']);
        $this->assertStringContainsString('otomatik yanıt limitine', $res['message']);
    }

    public function test_artisan_commands_run_successfully()
    {
        Config::set('customer-care.governance_enabled', true);
        $this->assertEquals(0, Artisan::call('customer-care:pilot-monitor', ['--store' => $this->store->id]));

        $this->assertEquals(0, Artisan::call('customer-care:circuit-breaker', [
            '--store' => $this->store->id,
            '--enable' => true,
        ]));
        $this->assertTrue(Cache::has("circuit_breaker_forced_open_{$this->store->id}"));

        $this->assertEquals(1, Artisan::call('customer-care:circuit-breaker', [
            '--store' => $this->store->id,
            '--disable' => true,
        ]));
        $this->assertTrue(Cache::has("circuit_breaker_forced_open_{$this->store->id}"));

        $systemActor = \App\Services\Support\TenantContext::getSystemActor();
        \App\Models\SupportApprovalRequest::where('store_id', $this->store->id)
            ->where('action_type', 'circuit_breaker_disable_' . $this->store->id)
            ->where('status', 'pending')
            ->firstOrFail()
            ->update([
                'status' => 'approved',
                'approved_by' => $this->user->id,
                'approved_at' => now(),
            ]);

        $this->assertEquals(0, Artisan::call('customer-care:circuit-breaker', [
            '--store' => $this->store->id,
            '--disable' => true,
        ]));
        $this->assertFalse(Cache::has("circuit_breaker_forced_open_{$this->store->id}"));
        $this->assertDatabaseHas('support_agent_actions', [
            'user_id' => $systemActor->id,
            'action' => 'circuit_breaker_forced_closed',
        ]);
    }

    public function test_schedule_registration()
    {
        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
        $events = collect($schedule->events());

        $hasMonitor = $events->contains(function ($event) {
            return str_contains((string) $event->description, 'customer-care-pilot-monitor')
                || str_contains((string) $event->command, 'customer-care:pilot-monitor');
        });

        $this->assertTrue($hasMonitor, 'customer-care:pilot-monitor is not registered in Schedule.');
    }

    public function test_circuit_breaker_fail_closed_at_zero_limit(): void
    {
        Config::set('customer-care.auto_reply_max_per_hour', 0); // default/0 = fail-closed

        $replyService = app(SupportReplyService::class);
        $res = $replyService->sendAiReply($this->conversation, 'Otomatik Cevap', 85);

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('Rate Limit Fail-Closed', $res['message']);

        // Check no messages created
        $msgExists = SupportMessage::where('conversation_id', $this->conversation->id)->where('sender_type', 'ai')->exists();
        $this->assertFalse($msgExists);
    }

    public function test_circuit_breaker_allows_under_limit_and_blocks_above_limit(): void
    {
        Config::set('customer-care.auto_reply_max_per_hour', 2);

        $replyService = app(SupportReplyService::class);

        // 1. First reply -> success
        $res1 = $replyService->sendAiReply($this->conversation, 'Otomatik Cevap 1', 85);
        $this->assertTrue($res1['success']);

        // 2. Second reply -> success
        $conv2 = SupportConversation::create([
            'support_channel_id' => $this->channel->id,
            'external_conversation_id' => 'CONV999',
            'external_customer_id' => 'CUST999',
            'store_id' => $this->store->id,
            'source_type' => 'trendyol',
            'status' => 'open',
            'ai_mode' => 'automatic',
            'ownership_status' => 'ai',
        ]);
        $res2 = $replyService->sendAiReply($conv2, 'Otomatik Cevap 2', 85);
        $this->assertTrue($res2['success'], $res2['message'] ?? 'no message');

        // 3. Third reply -> blocked
        $conv3 = SupportConversation::create([
            'support_channel_id' => $this->channel->id,
            'external_conversation_id' => 'CONV000',
            'external_customer_id' => 'CUST000',
            'store_id' => $this->store->id,
            'source_type' => 'trendyol',
            'status' => 'open',
            'ai_mode' => 'automatic',
            'ownership_status' => 'ai',
        ]);
        $res3 = $replyService->sendAiReply($conv3, 'Otomatik Cevap 3', 85);
        $this->assertFalse($res3['success']);
        $this->assertStringContainsString('Rate Limit Exceeded', $res3['message']);
    }

    public function test_manual_reply_unaffected_by_zero_limit(): void
    {
        Config::set('customer-care.auto_reply_max_per_hour', 0); // 0 limit

        $replyService = app(SupportReplyService::class);
        $res = $replyService->sendAgentReply($this->conversation, 'Manuel Cevap', $this->user->id);

        $this->assertTrue($res['success']);
    }

    public function test_circuit_breaker_cancel_updates_message_status_and_logs_audit()
    {
        // 1. Create AI pending dispatch
        $aiMsg = SupportMessage::create([
            'conversation_id' => $this->conversation->id,
            'direction' => 'outbound',
            'sender_type' => 'ai',
            'message_type' => 'text',
            'body_encrypted' => 'AI Cevabı',
            'delivery_status' => 'pending',
        ]);
        $aiDispatch = SupportDispatch::create([
            'message_id' => $aiMsg->id,
            'support_channel_id' => $this->channel->id,
            'conversation_id' => $this->conversation->id,
            'status' => 'pending',
            'idempotency_key' => 'idemp_ai_cancel_test',
        ]);

        // 2. Create Agent pending dispatch (should NOT be cancelled)
        $agentMsg = SupportMessage::create([
            'conversation_id' => $this->conversation->id,
            'direction' => 'outbound',
            'sender_type' => 'agent',
            'message_type' => 'text',
            'body_encrypted' => 'Temsilci Cevabı',
            'delivery_status' => 'pending',
        ]);
        $agentDispatch = SupportDispatch::create([
            'message_id' => $agentMsg->id,
            'support_channel_id' => $this->channel->id,
            'conversation_id' => $this->conversation->id,
            'status' => 'pending',
            'idempotency_key' => 'idemp_agent_cancel_test',
        ]);

        // Create system actor user
        if (!\App\Models\User::where('email', 'system@zolm.com')->exists()) {
            \App\Models\User::factory()->create([
                'email' => 'system@zolm.com',
                'role' => 'admin',
                'is_active' => true,
            ]);
        }
        Config::set('customer-care.system_actor_email', 'system@zolm.com');

        // Run cancel
        $anonService = app(\App\Services\Support\CustomerCareAnonymizationService::class);
        $cancelledCount = $anonService->cancelPendingAiDispatches($this->store->id);

        $this->assertEquals(1, $cancelledCount);

        // Assert AI message and dispatch cancelled
        $this->assertEquals('cancelled', $aiDispatch->fresh()->status);
        $this->assertEquals('cancelled', $aiMsg->fresh()->delivery_status);

        // Assert Agent message and dispatch unaffected
        $this->assertEquals('pending', $agentDispatch->fresh()->status);
        $this->assertEquals('pending', $agentMsg->fresh()->delivery_status);

        // Assert circuit_breaker_cancel audit action written
        $this->assertTrue(
            SupportAgentAction::where('conversation_id', $this->conversation->id)
                ->where('action', 'circuit_breaker_cancel')
                ->exists()
        );
    }
}
