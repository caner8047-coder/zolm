<?php

namespace Tests\Feature\CustomerCare;

use Tests\TestCase;
use App\Models\User;
use App\Models\SupportConversation;
use App\Models\SupportChannel;
use App\Models\MarketplaceStore;
use App\Models\LegalEntity;
use Illuminate\Support\Facades\Config;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\Support\CustomerCarePilotReadinessService;

class CustomerCarePilotReadinessTest extends TestCase
{
    use RefreshDatabase, CustomerCareTestHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupSystemActor();
    }

    protected function createStore(User $user, string $name = 'Readiness Store', string $code = 'RDY'): MarketplaceStore
    {
        $legalEntity = LegalEntity::create([
            'user_id' => $user->id,
            'name' => $name . ' Legal',
            'tax_number' => '1234567890',
            'is_active' => true,
        ]);

        return MarketplaceStore::create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'trendyol',
            'store_name' => $name,
            'store_code' => $code,
            'is_active' => true,
        ]);
    }

    /**
     * Readiness fail durumlarını doğrula.
     */
    public function test_readiness_fails_when_requirements_are_missing(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $store = $this->createStore($user);

        // Varsayılan olarak master switch, allowlist vb. kapalı/eksik
        Config::set('customer-care.enabled', false);
        Config::set('customer-care.pilot_store_allowlist', []);

        $service = app(CustomerCarePilotReadinessService::class);
        $res = $service->checkReadiness($store->id);

        $this->assertFalse($res['ready']);
        $this->assertEquals('failed', $res['checks']['master_enabled']['status']);
        $this->assertEquals('failed', $res['checks']['in_allowlist']['status']);
    }

    /**
     * Tüm şartlar geçtiğinde ready sonucunu doğrula.
     */
    public function test_readiness_passes_when_all_requirements_are_met(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $store = $this->createStore($user);

        // Aktif kanal oluştur
        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'trendyol',
            'name' => 'Trendyol Soru-Cevap',
            'status' => 'active',
            'is_enabled' => true,
        ]);

        Config::set('customer-care.enabled', true);
        Config::set('customer-care.pilot_store_allowlist', [$store->id]);
        Config::set('customer-care.system_actor_email', 'system@zolm.com');

        // Mock Gemini API Key in config and env
        Config::set('services.gemini.api_key', 'test_key_ok');
        putenv('GEMINI_API_KEY=test_key_ok');

        // Seed doğrulanabilir Golden Eval kanıtı
        $this->seedGoldenEvalEvidence($store->id, 85);
        $this->seedPassLanguageGate($store->id);
        $this->seedShadowEvidence($store->id);
        $this->seedOnboardingEvidence($store->id);

        $service = app(CustomerCarePilotReadinessService::class);
        $res = $service->checkReadiness($store->id);

        $this->assertTrue($res['ready'], 'Tüm gereksinimler sağlandığında ready=true olmalıdır.');
        $this->assertEquals('passed', $res['checks']['master_enabled']['status']);
        $this->assertEquals('passed', $res['checks']['in_allowlist']['status']);
        $this->assertEquals('passed', $res['checks']['golden_eval']['status']);

        // Temizleme
        putenv('GEMINI_API_KEY=');
    }

    /**
     * Golden eval bulunamadığında veya başarısız olduğunda readiness ready=false olmalıdır.
     */
    public function test_readiness_fails_when_golden_eval_is_missing_or_failed(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $store = $this->createStore($user);

        // Aktif kanal oluştur
        SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'trendyol',
            'name' => 'Trendyol Soru-Cevap',
            'status' => 'active',
            'is_enabled' => true,
        ]);

        Config::set('customer-care.enabled', true);
        Config::set('customer-care.pilot_store_allowlist', [$store->id]);
        Config::set('customer-care.system_actor_email', 'system@zolm.com');
        Config::set('services.gemini.api_key', 'test_key_ok');

        $evalService = app(\App\Services\Support\AI\CustomerCareEvalService::class);
        $service = app(CustomerCarePilotReadinessService::class);

        // 1. Golden eval yokken: ready = false, status = failed
        $resMissing = $service->checkReadiness($store->id);
        $this->assertFalse($resMissing['ready']);
        $this->assertEquals('failed', $resMissing['checks']['golden_eval']['status']);
        $this->assertStringContainsString('Henüz değerlendirme yapılmadı', $resMissing['checks']['golden_eval']['detail']);

        // 2. Golden eval başarısızken (skor = 75): ready = false, status = failed
        $evalService->recordManualEvalResult($store->id, [
            'average_score' => 75,
            'passed_eval_gate' => false,
        ]);
        $resFailed = $service->checkReadiness($store->id);
        $this->assertFalse($resFailed['ready']);
        $this->assertEquals('failed', $resFailed['checks']['golden_eval']['status']);
        $this->assertStringContainsString('%75', $resFailed['checks']['golden_eval']['detail']);
    }

    public function test_readiness_rejects_insufficient_shadow_sample_count(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $store = $this->createStore($user, 'Shadow Store', 'SHADOW');
        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'trendyol',
            'name' => 'Trendyol',
            'status' => 'active',
            'is_enabled' => true,
        ]);
        Config::set('customer-care.enabled', true);
        Config::set('customer-care.pilot_store_allowlist', [$store->id]);
        Config::set('services.gemini.api_key', 'test_key_ok');
        Config::set('customer-care.shadow_min_samples', 20);
        $this->seedGoldenEvalEvidence($store->id);
        $this->seedPassLanguageGate($store->id);

        $conversation = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'shadow_insufficient',
            'store_id' => $store->id,
            'source_type' => 'shadow_evaluation',
            'status' => 'resolved',
            'priority' => 'normal',
            'ai_mode' => 'manual',
        ]);
        \App\Models\SupportAiRun::create([
            'store_id' => $store->id,
            'conversation_id' => $conversation->id,
            'prompt_template_key' => 'shadow_readiness',
            'confidence_score' => 90,
            'shadow_match_score' => 90,
            'status' => 'draft',
        ]);

        $result = app(CustomerCarePilotReadinessService::class)->checkReadiness($store->id);

        $this->assertFalse($result['ready']);
        $this->assertSame('failed', $result['checks']['shadow_match']['status']);
        $this->assertStringContainsString('1 örnek', $result['checks']['shadow_match']['detail']);
    }

    public function test_readiness_rejects_inactive_channel_and_excessive_backlog(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $store = $this->createStore($user, 'Backlog Store', 'BACKLOG');
        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'trendyol',
            'name' => 'Trendyol',
            'status' => 'inactive',
            'is_enabled' => true,
        ]);
        Config::set('customer-care.enabled', true);
        Config::set('customer-care.pilot_store_allowlist', [$store->id]);
        Config::set('services.gemini.api_key', 'test_key_ok');
        Config::set('customer-care.pilot_max_backlog', 1);

        $result = app(CustomerCarePilotReadinessService::class)->checkReadiness($store->id);

        $this->assertFalse($result['ready']);
        $this->assertSame('failed', $result['checks']['channels_status']['status']);

        $channel->update(['status' => 'active']);
        $conversation = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'backlog_limit',
            'store_id' => $store->id,
            'source_type' => 'trendyol',
            'status' => 'open',
            'priority' => 'normal',
            'ai_mode' => 'manual',
        ]);
        $message = \App\Models\SupportMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'sender_type' => 'agent',
            'message_type' => 'text',
            'body_encrypted' => 'Bekleyen yanıt',
            'delivery_status' => 'queued',
        ]);
        \App\Models\SupportDispatch::create([
            'support_channel_id' => $channel->id,
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'idempotency_key' => 'backlog-limit-test',
            'status' => 'pending',
            'payload_json' => [],
        ]);

        $backlogResult = app(CustomerCarePilotReadinessService::class)->checkReadiness($store->id);

        $this->assertFalse($backlogResult['ready']);
        $this->assertSame('failed', $backlogResult['checks']['outbox_backlog']['status']);
    }

    /**
     * Pilot dashboard kapalıyken erişilemez olmalıdır.
     */
    public function test_pilot_dashboard_route_is_blocked_when_feature_flag_is_disabled(): void
    {
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $this->actingAs($user);

        Config::set('customer-care.pilot_dashboard_enabled', false);

        $response = $this->get('/customer-care/pilot');
        $response->assertStatus(404);
    }
}
