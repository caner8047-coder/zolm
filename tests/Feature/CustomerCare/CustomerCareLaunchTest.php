<?php

namespace Tests\Feature\CustomerCare;

use Tests\TestCase;
use App\Models\User;
use App\Models\MarketplaceStore;
use App\Models\SupportLaunchPlan;
use App\Models\SupportLaunchPlanStep;
use App\Models\SupportLaunchEvent;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\SupportDispatch;
use App\Models\SupportChannel;
use App\Models\SupportApprovalRequest;
use App\Services\Support\CustomerCareLaunchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Livewire\Livewire;

class CustomerCareLaunchTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $operatorUser;
    protected MarketplaceStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::clear();

        $this->adminUser = User::factory()->create(['role' => 'admin', 'email' => 'admin@zolm.com']);
        $this->operatorUser = User::factory()->create(['role' => 'operator', 'email' => 'op@zolm.com']);

        $le = \App\Models\LegalEntity::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Test Legal Entity Name',
            'company_name' => 'Test Holding',
            'tax_office' => 'Kadikoy',
            'tax_number' => '1234567890',
            'address' => 'Istanbul',
        ]);

        $this->store = MarketplaceStore::create([
            'store_name' => 'Test Store',
            'store_key' => 'test_store',
            'user_id' => $this->adminUser->id,
            'legal_entity_id' => $le->id,
            'marketplace' => 'trendyol',
            'is_active' => true,
        ]);

        Config::set('customer-care.enabled', true);
        Config::set('customer-care.launch_center_enabled', true);
        Config::set('customer-care.reliability_enabled', true);
        Config::set('customer-care.pilot_store_allowlist', [$this->store->id]);
    }

    public function test_launch_route_blocks_when_flag_off()
    {
        Config::set('customer-care.launch_center_enabled', false);

        $response = $this->actingAs($this->adminUser)->get('/customer-care/launch');
        $response->assertStatus(404);
    }

    public function test_launch_center_does_not_expose_unowned_store_to_operator(): void
    {
        SupportLaunchPlan::create([
            'store_id' => $this->store->id,
            'status' => 'draft',
            'target_channels' => ['trendyol'],
            'initial_mode' => 'manual',
        ]);

        $this->actingAs($this->operatorUser);

        Livewire::test(\App\Livewire\CustomerCare\Launch::class)
            ->assertSet('selectedStoreId', 0)
            ->assertViewHas('stores', fn ($stores) => $stores->isEmpty())
            ->assertViewHas('plans', fn ($plans) => $plans->isEmpty());
    }

    public function test_readiness_failed_when_queue_health_unknown()
    {
        // Mock no queue data in database -> checks fail
        $service = app(CustomerCareLaunchService::class);
        $result = $service->checkChecklist($this->store->id, $this->adminUser);

        $this->assertFalse($result['allowed']);
        $this->assertEquals('failed', $result['checks']['queue_health']['status']);
    }

    public function test_launch_checklist_propagates_nested_pilot_readiness_failures(): void
    {
        Config::set('customer-care.pilot_store_allowlist', []);

        $result = app(CustomerCareLaunchService::class)->checkChecklist($this->store->id, $this->adminUser);

        $this->assertFalse($result['allowed']);
        $this->assertSame('failed', $result['checks']['pilot_readiness']['status']);
        $this->assertStringContainsString('Pilot Mağaza İzin Listesi', $result['checks']['pilot_readiness']['detail']);
    }

    public function test_cannot_transition_to_approved_without_governance_approval()
    {
        // Setup passing checks except golden eval is still failing
        // Let's check checklist status
        $plan = SupportLaunchPlan::create([
            'store_id' => $this->store->id,
            'status' => 'draft',
            'target_channels' => ['whatsapp'],
            'initial_mode' => 'manual',
        ]);

        $service = app(CustomerCareLaunchService::class);

        // Expect Exception because readiness checks will fail or require approval
        $this->expectException(\RuntimeException::class);
        $service->transitionTo($plan, 'approved', $this->adminUser);
    }

    public function test_launch_state_machine_rejects_skipped_or_unknown_transitions(): void
    {
        $plan = SupportLaunchPlan::create([
            'store_id' => $this->store->id,
            'status' => 'draft',
            'target_channels' => ['trendyol'],
            'initial_mode' => 'manual',
        ]);

        $this->expectException(\InvalidArgumentException::class);

        app(CustomerCareLaunchService::class)->transitionTo($plan, 'completed', $this->adminUser);
    }

    public function test_rollback_disables_auto_modes_and_cancels_pending_ai_dispatches()
    {
        $plan = SupportLaunchPlan::create([
            'store_id' => $this->store->id,
            'status' => 'canary',
            'target_channels' => ['whatsapp'],
            'initial_mode' => 'automatic',
        ]);

        $channel = SupportChannel::create([
            'store_id' => $this->store->id,
            'channel_type' => 'whatsapp',
            'key' => 'whatsapp_key',
            'name' => 'WhatsApp Channel',
            'is_enabled' => true,
            'config_json' => [
                'automation_settings' => [
                    'ai_mode' => 'automatic',
                    'auto_reply' => true,
                    'min_confidence' => 90,
                ],
            ],
        ]);

        $conv = SupportConversation::create([
            'store_id' => $this->store->id,
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'conv_123',
            'external_customer_id' => 'cust_123',
            'ai_mode' => 'automatic',
            'status' => 'open',
            'source_type' => 'chat',
        ]);

        $handoffConv = SupportConversation::create([
            'store_id' => $this->store->id,
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'conv_handoff_rollback',
            'external_customer_id' => 'cust_handoff',
            'ai_mode' => 'handoff',
            'ownership_status' => 'human',
            'status' => 'open',
            'source_type' => 'chat',
        ]);

        $msgAi = SupportMessage::create([
            'conversation_id' => $conv->id,
            'direction' => 'outbound',
            'sender_type' => 'ai',
            'message_type' => 'text',
            'body_encrypted' => 'Pending AI Reply',
            'delivery_status' => 'draft',
            'sent_at' => now(),
        ]);

        $msgAgent = SupportMessage::create([
            'conversation_id' => $conv->id,
            'direction' => 'outbound',
            'sender_type' => 'agent',
            'message_type' => 'text',
            'body_encrypted' => 'Pending Agent Reply',
            'delivery_status' => 'draft',
            'sent_at' => now(),
        ]);

        $dispatchAi = SupportDispatch::create([
            'conversation_id' => $conv->id,
            'message_id' => $msgAi->id,
            'support_channel_id' => $channel->id,
            'idempotency_key' => 'idemp_key_ai',
            'status' => 'pending',
        ]);

        $dispatchAgent = SupportDispatch::create([
            'conversation_id' => $conv->id,
            'message_id' => $msgAgent->id,
            'support_channel_id' => $channel->id,
            'idempotency_key' => 'idemp_key_agent',
            'status' => 'pending',
        ]);

        $service = app(CustomerCareLaunchService::class);
        $service->rollback($plan, $this->adminUser);

        // Assert plan marked rolled_back
        $this->assertEquals('rolled_back', $plan->fresh()->status);

        // Assert AI mode changed to suggestion_only
        $this->assertEquals('suggestion_only', $conv->fresh()->ai_mode);
        $this->assertEquals('handoff', $handoffConv->fresh()->ai_mode);

        // Kanal varsayılanı ve store kill-switch yeni otomatik mesajları da engeller.
        $automation = $channel->fresh()->config_json['automation_settings'];
        $this->assertSame('manual', $automation['ai_mode']);
        $this->assertFalse($automation['auto_reply']);
        $this->assertTrue(Cache::get("circuit_breaker_forced_open_{$this->store->id}"));

        // Assert AI dispatch is cancelled but Agent dispatch is preserved
        $this->assertEquals('cancelled', $dispatchAi->fresh()->status);
        $this->assertEquals('pending', $dispatchAgent->fresh()->status);
    }

    public function test_direct_rollback_call_rejects_actor_without_store_access(): void
    {
        $plan = SupportLaunchPlan::create([
            'store_id' => $this->store->id,
            'status' => 'canary',
            'target_channels' => ['whatsapp'],
            'initial_mode' => 'automatic',
        ]);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        app(CustomerCareLaunchService::class)->rollback($plan, $this->operatorUser);
    }

    public function test_cli_rollback_dry_run_does_not_mutate()
    {
        $plan = SupportLaunchPlan::create([
            'store_id' => $this->store->id,
            'status' => 'canary',
            'target_channels' => ['whatsapp'],
            'initial_mode' => 'automatic',
        ]);

        $this->artisan("customer-care:launch-rollback --store={$this->store->id} --plan={$plan->id}")
            ->assertExitCode(0);

        $this->assertEquals('canary', $plan->fresh()->status);
    }
}
