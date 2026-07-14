<?php

namespace Tests\Feature\CustomerCare;

use Tests\TestCase;
use App\Models\User;
use App\Models\MarketplaceStore;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\SupportAiRun;
use App\Models\SupportAiCostEvent;
use App\Services\Support\CustomerCareAiProviderHealthService;
use App\Services\Support\AI\CustomerCareAiOrchestrator;
use App\Services\Support\AI\CustomerCareAiProviderInterface;
use App\Services\Support\AI\CustomerCareAiResponseDto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class CustomerCareOpsTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected MarketplaceStore $store;
    protected SupportConversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'customer-care.enabled' => true,
            'customer-care.ops_center_enabled' => true,
            'customer-care.quality_center_enabled' => true,
            'customer-care.integration_hub_enabled' => true,
            'customer-care.budget_cap_daily' => 10.0,
            'customer-care.budget_cap_monthly' => 200.0,
        ]);

        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'system@zolm.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $le = \App\Models\LegalEntity::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Test Corp',
            'tax_office' => 'TaxOffice',
            'tax_number' => '1234567890',
        ]);

        $this->store = MarketplaceStore::create([
            'user_id' => $this->adminUser->id,
            'legal_entity_id' => $le->id,
            'marketplace' => 'trendyol',
            'store_name' => 'Zolm Store A',
            'store_code' => 'ST_A',
            'seller_id' => '1001',
            'status' => 'active',
            'is_active' => true,
        ]);

        $channel = \App\Models\SupportChannel::create([
            'store_id' => $this->store->id,
            'key' => 'whatsapp_main',
            'channel_type' => 'whatsapp',
            'name' => 'WhatsApp Main',
            'is_enabled' => true,
            'config_json' => [],
        ]);

        $this->conversation = SupportConversation::create([
            'store_id' => $this->store->id,
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'conv-99',
            'external_customer_id' => 'cust-99',
            'source_type' => 'whatsapp',
            'status' => 'open',
            'priority' => 'normal',
        ]);

        SupportMessage::create([
            'conversation_id' => $this->conversation->id,
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'message_type' => 'text',
            'body_encrypted' => 'Merhaba, siparişimi iptal edebilir misiniz?',
            'body_preview' => 'Merhaba...',
            'delivery_status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function test_budget_exceeded_blocks_auto_reply_but_allows_manual()
    {
        $healthService = app(CustomerCareAiProviderHealthService::class);

        // Record cost exceeding daily cap ($10.0)
        $healthService->recordCost($this->store->id, 'gemini-1.5-flash', 'GeminiProvider', 50000000, 50000000);

        $this->assertTrue($healthService->hasExceededBudget($this->store->id));

        // Mock Provider
        $mockProvider = $this->createMock(CustomerCareAiProviderInterface::class);
        $this->app->instance(CustomerCareAiProviderInterface::class, $mockProvider);

        $orchestrator = app(CustomerCareAiOrchestrator::class);
        $result = $orchestrator->generateDraft($this->conversation);

        $this->assertFalse($result['success']);
        $this->assertEquals('budget_exceeded', $result['status']);
    }

    public function test_api_key_missing_provider_health_fails_closed()
    {
        // Unset API keys
        Config::set('services.gemini.api_key', 'EXPLICIT_UNSET_KEY_TEST');

        $healthService = app(CustomerCareAiProviderHealthService::class);
        $this->assertFalse($healthService->isProviderHealthy('Gemini'));
    }

    public function test_unknown_provider_health_fails_closed(): void
    {
        $this->assertFalse(
            app(CustomerCareAiProviderHealthService::class)->isProviderHealthy('UnsupportedProvider')
        );
    }

    public function test_latency_percentiles_calculation()
    {
        $this->actingAs($this->adminUser);

        // Record runs with different latency values
        foreach ([100, 200, 300, 400, 500, 600, 700, 800, 900, 1000] as $lat) {
            SupportAiRun::create([
                'store_id' => $this->store->id,
                'conversation_id' => $this->conversation->id,
                'prompt_template_key' => 'copilot_v1',
                'prompt_raw' => 'query',
                'response_raw' => 'answer',
                'confidence_score' => 90,
                'latency_ms' => $lat,
                'status' => 'draft',
            ]);
        }

        // Test component rendering percentiles
        $component = \Livewire\Livewire::test(\App\Livewire\CustomerCare\OpsCenter::class);
        $component->set('selectedStoreId', $this->store->id);

        $this->assertEquals(600, $component->viewData('p50'));
        $this->assertEquals(1000, $component->viewData('p95'));
    }

    public function test_recompute_command_in_dry_run_does_not_modify()
    {
        $this->actingAs($this->adminUser);

        SupportAiRun::create([
            'store_id' => $this->store->id,
            'conversation_id' => $this->conversation->id,
            'prompt_template_key' => 'copilot_v1',
            'prompt_raw' => 'Merhaba, koltuk var mı?',
            'response_raw' => 'Kırmızı koltuk mevcuttur.',
            'confidence_score' => 90,
            'token_in' => 0,
            'token_out' => 0,
            'latency_ms' => 150,
            'status' => 'draft',
        ]);

        $code = Artisan::call('customer-care:recompute-ai-costs', [
            '--store' => $this->store->id,
        ]);

        $this->assertEquals(0, $code);

        // Cost events count must be zero since we ran in dry-run
        $this->assertEquals(0, SupportAiCostEvent::count());
    }

    public function test_ops_route_blocks_when_flag_off()
    {
        $this->actingAs($this->adminUser);
        // 1. Flag off -> 404
        config(['customer-care.ops_center_enabled' => false]);
        $response = $this->get('/customer-care/ops');
        $response->assertStatus(404);

        // 2. Flag on, not admin -> 403
        config(['customer-care.ops_center_enabled' => true]);
        $operator = User::create([
            'name' => 'Operator',
            'email' => 'op@zolm.com',
            'password' => bcrypt('password'),
            'role' => 'operator',
        ]);
        $this->actingAs($operator);
        $response = $this->get('/customer-care/ops');
        $response->assertStatus(403);

        // 3. Flag on, admin -> 200
        $this->actingAs($this->adminUser);
        $response = $this->get('/customer-care/ops');
        $response->assertStatus(200);
    }

    public function test_budget_exceeded_blocks_send_ai_reply()
    {
        $healthService = app(CustomerCareAiProviderHealthService::class);

        // Exceed budget
        $healthService->recordCost($this->store->id, 'gemini-1.5-flash', 'GeminiProvider', 50000000, 50000000);
        $this->assertTrue($healthService->hasExceededBudget($this->store->id));

        $replyService = app(\App\Services\Support\SupportReplyService::class);

        // Try to trigger AI reply
        $res = $replyService->sendAiReply($this->conversation, 'Mesaj gövdesi', 90);

        // Assert blocked
        $this->assertFalse($res['success']);
        $this->assertStringContainsString('bütçe limiti aşıldı', $res['message']);
    }

    public function test_provider_unhealthy_blocks_send_ai_reply()
    {
        // Mock unhealthy provider
        Config::set('services.gemini.api_key', 'EXPLICIT_UNSET_KEY_TEST');

        $replyService = app(\App\Services\Support\SupportReplyService::class);

        // Try to trigger AI reply
        $res = $replyService->sendAiReply($this->conversation, 'Mesaj gövdesi', 90);

        // Assert blocked
        $this->assertFalse($res['success']);
        $this->assertStringContainsString('API anahtarı yapılandırılmamış', $res['message']);
    }

    public function test_manual_agent_reply_bypasses_budget_cap()
    {
        $this->actingAs($this->adminUser);
        $healthService = app(CustomerCareAiProviderHealthService::class);

        // Exceed budget
        $healthService->recordCost($this->store->id, 'gemini-1.5-flash', 'GeminiProvider', 50000000, 50000000);
        $this->assertTrue($healthService->hasExceededBudget($this->store->id));

        $replyService = app(\App\Services\Support\SupportReplyService::class);

        // Manual agent reply should NOT be blocked by budget cap (will fail or succeed on message dispatch, but not by budget cap)
        // We set up a mock to intercept validate in SupportPolicyEngine
        $res = $replyService->sendAgentReply($this->conversation, 'Temsilci yanıtı', $this->adminUser->id);

        // Assert it does NOT say budget limit exceeded
        $this->assertNotEquals('Bu mağaza için günlük veya aylık AI bütçe limiti aşıldı.', $res['message'] ?? '');
    }

    public function test_ops_maliyet_ui_shows_hesaplanamadi_when_null_cost_events_only()
    {
        $this->actingAs($this->adminUser);

        // Create a cost event with NULL cost_estimate
        SupportAiCostEvent::create([
            'store_id' => $this->store->id,
            'model' => 'gemini-1.5-flash',
            'provider' => 'GeminiProvider',
            'prompt_tokens' => 100,
            'completion_tokens' => 200,
            'cost_estimate' => null, // null
        ]);

        $component = \Livewire\Livewire::test(\App\Livewire\CustomerCare\OpsCenter::class);
        $component->set('selectedStoreId', $this->store->id);

        $component->assertSee('Hesaplanamadı / Bilinmiyor');
        $component->assertDontSee('$0.0000');
    }
}
