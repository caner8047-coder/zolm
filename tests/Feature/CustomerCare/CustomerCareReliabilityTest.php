<?php

namespace Tests\Feature\CustomerCare;

use Tests\TestCase;
use App\Models\User;
use App\Models\MarketplaceStore;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\SupportDispatch;
use App\Models\SupportIntegrationDelivery;
use App\Models\SupportIntegrationEvent;
use App\Models\SupportChannel;
use App\Services\Support\Reliability\CustomerCareQueueHealthService;
use App\Services\Support\Reliability\CustomerCareRateLimiter;
use App\Services\Support\SupportOutboxService;
use App\Services\Support\SupportReplyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class CustomerCareReliabilityTest extends TestCase
{
    use RefreshDatabase, CustomerCareTestHelper;

    protected User $adminUser;
    protected MarketplaceStore $storeA;
    protected MarketplaceStore $storeB;
    protected SupportChannel $channel;
    protected SupportConversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'customer-care.enabled' => true,
            'customer-care.governance_enabled' => true,
            'customer-care.compliance_enabled' => true,
            'customer-care.reliability_enabled' => true,
            'customer-care.system_actor_email' => 'system@zolm.com',
        ]);

        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@zolm.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        User::create([
            'name' => 'System Actor',
            'email' => 'system@zolm.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $le = \App\Models\LegalEntity::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Test Legal Entity Name',
            'company_name' => 'Test Holding',
            'tax_office' => 'Kadikoy',
            'tax_number' => '1234567890',
            'address' => 'Istanbul',
        ]);

        $this->storeA = MarketplaceStore::create([
            'legal_entity_id' => $le->id,
            'user_id' => $this->adminUser->id,
            'store_name' => 'Store A',
            'marketplace' => 'trendyol',
            'is_active' => true,
        ]);

        $this->storeB = MarketplaceStore::create([
            'legal_entity_id' => $le->id,
            'user_id' => $this->adminUser->id,
            'store_name' => 'Store B',
            'marketplace' => 'hepsiburada',
            'is_active' => true,
        ]);
        \App\Models\WaAccount::create([
            'store_id' => $this->storeA->id,
            'waba_id' => 'waba_test_123',
            'phone_number_id' => 'phone_test_123',
            'display_phone_number' => '+905554443322',
            'access_token_encrypted' => 'token123',
            'is_active' => true,
            'status' => 'active',
        ]);
        $this->channel = SupportChannel::create([
            'store_id' => $this->storeA->id,
            'key' => 'whatsapp',
            'channel_type' => 'whatsapp',
            'name' => 'WA Main',
            'is_enabled' => true,
        ]);
        \App\Models\SupportChannelCapability::create([
            'support_channel_id' => $this->channel->id,
            'capability' => 'send_messages',
            'status' => 'available',
            'source' => 'system',
        ]);

        $this->conversation = SupportConversation::create([
            'store_id' => $this->storeA->id,
            'support_channel_id' => $this->channel->id,
            'external_conversation_id' => 'conv_xyz',
            'external_customer_id' => 'cust_123',
            'status' => 'active',
            'ai_mode' => 'automatic',
            'source_type' => 'chat',
            'last_inbound_at' => now(),
        ]);

        config([
            'customer-care.auto_reply_enabled' => true,
            'customer-care.pilot_store_allowlist' => [$this->storeA->id],
        ]);
    }

    public function test_reliability_route_blocks_when_flag_off()
    {
        $this->actingAs($this->adminUser);
        config(['customer-care.reliability_enabled' => false]);

        $response = $this->get('/customer-care/reliability');
        $response->assertStatus(404);
    }

    public function test_backpressure_blocks_auto_reply_but_allows_manual()
    {
        $this->actingAs($this->adminUser);
        config([
            'customer-care.business_hours_auto_reply_enabled' => true,
            'customer-care.circuit_breaker_enabled' => true,
            'customer-care.auto_reply_max_per_hour' => 100,
        ]);
        $this->seedPassEval($this->storeA->id);

        // Exceed pending dispatch threshold (create 55 pending dispatches)
        for ($i = 0; $i < 55; $i++) {
            $msg = SupportMessage::create([
                'conversation_id' => $this->conversation->id,
                'direction' => 'outbound',
                'sender_type' => 'ai',
                'message_type' => 'text',
                'body_encrypted' => "Spam msg {$i}",
                'sent_at' => now(),
            ]);
            SupportDispatch::create([
                'message_id' => $msg->id,
                'support_channel_id' => $this->channel->id,
                'conversation_id' => $this->conversation->id,
                'status' => 'pending',
                'idempotency_key' => 'key_' . $i,
                'attempt_count' => 0,
            ]);
        }

        $healthService = app(CustomerCareQueueHealthService::class);
        $backpressure = $healthService->checkBackpressure($this->storeA->id);
        $this->assertTrue($backpressure['backpressure']);

        // AutomationGate should block AI replies
        $gate = app(\App\Services\Support\AI\CustomerCareAutomationGate::class);
        $gateResult = $gate->canAutomate($this->conversation, 90);
        $this->assertFalse($gateResult['allowed']);
        $this->assertStringContainsString('Backpressure Active', $gateResult['reason']);

        // SupportReplyService should still allow manual reply
        $replyService = app(SupportReplyService::class);
        $response = $replyService->sendAgentReply($this->conversation, 'Manual response', $this->adminUser->id);
        $this->assertNotNull($response['message_id'] ?? null);
    }

    public function test_rate_limit_blocks_outbound_send()
    {
        config([
            'customer-care.rate_limits.whatsapp' => ['max_attempts' => 2, 'decay_seconds' => 3600],
        ]);

        $rateLimiter = app(CustomerCareRateLimiter::class);

        // 1st and 2nd attempts
        $this->assertTrue($rateLimiter->checkLimit($this->storeA->id, 'whatsapp'));

        // Create 2 dispatches in DB to trigger limit
        for ($i = 0; $i < 2; $i++) {
            $msg = SupportMessage::create([
                'conversation_id' => $this->conversation->id,
                'direction' => 'outbound',
                'sender_type' => 'agent',
                'message_type' => 'text',
                'body_encrypted' => "Limit msg {$i}",
                'sent_at' => now(),
            ]);
            SupportDispatch::create([
                'message_id' => $msg->id,
                'support_channel_id' => $this->channel->id,
                'conversation_id' => $this->conversation->id,
                'status' => 'sent',
                'idempotency_key' => 'idemp_' . $i,
                'attempt_count' => 1,
            ]);
        }

        // 3rd attempt should be blocked
        $this->assertFalse($rateLimiter->checkLimit($this->storeA->id, 'whatsapp'));

        // SupportOutboxService sendDispatch should fail-closed
        $msgBlocked = SupportMessage::create([
            'conversation_id' => $this->conversation->id,
            'direction' => 'outbound',
            'sender_type' => 'agent',
            'message_type' => 'text',
            'body_encrypted' => "Blocked msg",
            'sent_at' => now(),
        ]);
        $dispatchBlocked = SupportDispatch::create([
            'message_id' => $msgBlocked->id,
            'support_channel_id' => $this->channel->id,
            'conversation_id' => $this->conversation->id,
            'status' => 'pending',
            'idempotency_key' => 'blocked_idemp',
            'attempt_count' => 0,
        ]);

        $outboxService = app(SupportOutboxService::class);
        $success = $outboxService->sendDispatch($dispatchBlocked);

        $this->assertFalse($success);
        $this->assertEquals('failed', $dispatchBlocked->fresh()->status);
        $this->assertStringContainsString('Rate Limit Exceeded', $dispatchBlocked->fresh()->last_error);
    }

    public function test_rate_limiter_supports_hepsiburada_and_n11_channel_keys(): void
    {
        $hbChannel = SupportChannel::create([
            'store_id' => $this->storeA->id,
            'key' => 'hepsiburada',
            'name' => 'Hepsiburada',
            'is_enabled' => true,
        ]);
        $n11Channel = SupportChannel::create([
            'store_id' => $this->storeA->id,
            'key' => 'n11',
            'name' => 'N11',
            'is_enabled' => true,
        ]);

        $limiter = app(CustomerCareRateLimiter::class);

        $this->assertTrue($limiter->checkLimit($this->storeA->id, $hbChannel->key));
        $this->assertTrue($limiter->checkLimit($this->storeA->id, $n11Channel->key));
    }

    public function test_rate_limiter_counts_suffixed_channel_keys_and_report_uses_real_schema(): void
    {
        $this->channel->update(['key' => 'whatsapp_main']);
        config(['customer-care.rate_limits.whatsapp' => ['max_attempts' => 1, 'decay_seconds' => 3600]]);

        $message = SupportMessage::create([
            'conversation_id' => $this->conversation->id,
            'direction' => 'outbound',
            'sender_type' => 'agent',
            'message_type' => 'text',
            'body_encrypted' => 'Varyant kanal limiti',
            'sent_at' => now(),
        ]);
        SupportDispatch::create([
            'message_id' => $message->id,
            'support_channel_id' => $this->channel->id,
            'conversation_id' => $this->conversation->id,
            'status' => 'sent',
            'idempotency_key' => 'suffixed-channel-limit',
            'attempt_count' => 1,
        ]);

        $this->assertFalse(app(CustomerCareRateLimiter::class)->checkLimit($this->storeA->id, 'whatsapp_main'));
        $this->artisan('customer-care:rate-limit-report', ['--store' => $this->storeA->id])
            ->expectsOutputToContain('whatsapp')
            ->assertExitCode(0);
    }

    public function test_db_unique_constraint_blocks_duplicate_integration_event()
    {
        SupportIntegrationEvent::create([
            'store_id' => $this->storeA->id,
            'event_id' => 'evt_1',
            'event_type' => 'test',
            'payload_json' => [],
            'idempotency_key' => 'idemp_unique_123',
        ]);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        SupportIntegrationEvent::create([
            'store_id' => $this->storeA->id,
            'event_id' => 'evt_2',
            'event_type' => 'test',
            'payload_json' => [],
            'idempotency_key' => 'idemp_unique_123', // duplicate key
        ]);
    }

    public function test_unknown_channel_fail_closed_rate_limit()
    {
        $rateLimiter = app(CustomerCareRateLimiter::class);

        // Unknown channel key/type should return false (fail-closed)
        $this->assertFalse($rateLimiter->checkLimit($this->storeA->id, 'unknown_channel_key_123'));
    }

    public function test_unknown_backpressure_status_when_no_data()
    {
        // Make sure all dispatches, deliveries and runs are cleared for store A and B
        SupportDispatch::query()->delete();
        SupportIntegrationDelivery::query()->delete();
        \App\Models\SupportAiRun::query()->delete();

        $healthService = app(CustomerCareQueueHealthService::class);
        $status = $healthService->checkBackpressure($this->storeA->id);

        $this->assertFalse($status['backpressure']);
        $this->assertEquals('unknown', $status['status']);
        $this->assertStringContainsString('bulunmamaktadır', $status['reason']);
    }

    public function test_dead_letter_replay_cli_defaults_to_dry_run_and_enforces_execute_and_approval()
    {
        // 1. Run without --execute should dry-run (no database changes)
        $msg = SupportMessage::create([
            'conversation_id' => $this->conversation->id,
            'direction' => 'outbound',
            'sender_type' => 'agent',
            'message_type' => 'text',
            'body_encrypted' => "Test msg",
            'sent_at' => now(),
        ]);
        $dispatch = SupportDispatch::create([
            'message_id' => $msg->id,
            'support_channel_id' => $this->channel->id,
            'conversation_id' => $this->conversation->id,
            'status' => 'exhausted',
            'idempotency_key' => 'idemp_exhausted_123',
            'attempt_count' => 5,
        ]);

        $exitCode = \Illuminate\Support\Facades\Artisan::call('customer-care:replay-deadletters', [
            '--store' => $this->storeA->id,
            '--type' => 'dispatch',
        ]);

        // Output should mention DRY-RUN and exit code should be 0
        $output = \Illuminate\Support\Facades\Artisan::output();
        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('DRY-RUN', $output);
        $this->assertEquals('exhausted', $dispatch->fresh()->status); // unchanged

        // 2. Run with --execute but without governance approval should exit 1 (blocked)
        $exitCodeExecute = \Illuminate\Support\Facades\Artisan::call('customer-care:replay-deadletters', [
            '--store' => $this->storeA->id,
            '--type' => 'dispatch',
            '--execute' => true,
        ]);

        $outputExecute = \Illuminate\Support\Facades\Artisan::output();
        $this->assertEquals(1, $exitCodeExecute);
        $this->assertStringContainsString('onay gerekiyor', $outputExecute);
        $this->assertEquals('exhausted', $dispatch->fresh()->status); // still unchanged

        // Verify a pending approval request was opened
        $this->assertDatabaseHas('support_approval_requests', [
            'store_id' => $this->storeA->id,
            'action_type' => 'replay_deadletters',
            'status' => 'pending',
        ]);
    }
}
