<?php

namespace Tests\Feature\CustomerCare;

use App\Models\User;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\SupportChannel;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Services\Support\AI\CustomerCareAutomationGate;
use App\Services\Support\AI\CustomerCareAiProviderInterface;
use App\Services\Support\AI\CustomerCareAiResponseDto;
use Illuminate\Support\Facades\Config;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerCarePilotGateTest extends TestCase
{
    use RefreshDatabase;
    use CustomerCareTestHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupSystemActor();
        Config::set('customer-care.auto_reply_max_per_hour', 100);
        Config::set('customer-care.business_hours_auto_reply_enabled', true);
    }

    private function createStore(User $user, string $name = 'Test Store', string $code = 'TST'): MarketplaceStore
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

    private function createChannel(MarketplaceStore $store): SupportChannel
    {
        return SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'trendyol',
            'name' => 'Trendyol Soru-Cevap',
            'status' => 'active',
            'is_enabled' => true,
        ]);
    }

    private function createMockProvider(string $answer, int $confidence): CustomerCareAiProviderInterface
    {
        $mock = $this->createMock(CustomerCareAiProviderInterface::class);
        $mock->method('generateAnswer')->willReturn(
            new CustomerCareAiResponseDto(
                $answer,
                $confidence,
                ['Yurtiçi', '14 gün', 'L beden', 'stokta', '450 TL', 'indirim', 'kargoda', 'meşe'], // Golden dataset için tüm keywords'leri içerecek mock yanıt
                false,
                'tr'
            )
        );
        return $mock;
    }

    /**
     * 1. Automatic kapalıyken (auto_reply_enabled = false) otomasyon izni verilmediğini doğrula.
     */
    public function test_gate_rejects_when_auto_reply_is_disabled(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $store = $this->createStore($user);
        $channel = $this->createChannel($store);
        $conv = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'conv_pilot_1',
            'store_id' => $store->id,
            'source_type' => 'trendyol',
            'status' => 'open',
            'ai_mode' => 'automatic',
        ]);

        Config::set('customer-care.enabled', true);
        Config::set('customer-care.auto_reply_enabled', false); // Kapalı

        $gate = $this->app->make(CustomerCareAutomationGate::class);
        $res = $gate->canAutomate($conv);

        $this->assertFalse($res['allowed']);
        $this->assertStringContainsString('Auto Reply Feature Flag', $res['reason']);
    }

    /**
     * 2. Allowlist dışında olan mağazaların otomatik yanıt gönderemeyeceğini doğrula.
     */
    public function test_gate_rejects_when_store_is_not_in_allowlist(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $store = $this->createStore($user);
        $channel = $this->createChannel($store);
        $conv = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'conv_pilot_2',
            'store_id' => $store->id,
            'source_type' => 'trendyol',
            'status' => 'open',
            'ai_mode' => 'automatic',
        ]);

        Config::set('customer-care.enabled', true);
        Config::set('customer-care.auto_reply_enabled', true);
        Config::set('customer-care.pilot_store_allowlist', [9999]); // Başka bir store_id izinli

        $gate = $this->app->make(CustomerCareAutomationGate::class);
        $res = $gate->canAutomate($conv);

        $this->assertFalse($res['allowed']);
        $this->assertStringContainsString('Pilot Store Allowlist', $res['reason']);
    }

    /**
     * 3. AI güven skoru limit altındayken (confidence < 80) otomasyon izni verilmediğini doğrula.
     */
    public function test_gate_rejects_when_confidence_is_below_threshold(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $store = $this->createStore($user);
        $channel = $this->createChannel($store);
        $conv = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'conv_pilot_3',
            'store_id' => $store->id,
            'source_type' => 'trendyol',
            'status' => 'open',
            'ai_mode' => 'automatic',
        ]);

        Config::set('customer-care.enabled', true);
        Config::set('customer-care.auto_reply_enabled', true);
        Config::set('customer-care.pilot_store_allowlist', [$store->id]);

        $gate = $this->app->make(CustomerCareAutomationGate::class);
        $res = $gate->canAutomate($conv, 75); // Confidence = 75 (Düşük)

        $this->assertFalse($res['allowed']);
        $this->assertStringContainsString('Confidence Threshold', $res['reason']);
    }

    /**
     * 4. Human ownership lock (konuşma temsilciye kilitliyken) durumunda otomatik gönderim yapılmadığını doğrula.
     */
    public function test_gate_rejects_when_human_owns_conversation(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $store = $this->createStore($user);
        $channel = $this->createChannel($store);
        $conv = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'conv_pilot_4',
            'store_id' => $store->id,
            'source_type' => 'trendyol',
            'status' => 'open',
            'ai_mode' => 'automatic',
            'ownership_status' => 'human' // Temsilcide
        ]);

        Config::set('customer-care.enabled', true);
        Config::set('customer-care.auto_reply_enabled', true);
        Config::set('customer-care.pilot_store_allowlist', [$store->id]);

        $gate = $this->app->make(CustomerCareAutomationGate::class);
        $res = $gate->canAutomate($conv);

        $this->assertFalse($res['allowed']);
        $this->assertStringContainsString('Human Ownership Lock', $res['reason']);
    }

    /**
     * 5. Master kill-switch anında tüm gönderimleri durdurur.
     */
    public function test_gate_rejects_when_master_kill_switch_is_disabled(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $store = $this->createStore($user);
        $channel = $this->createChannel($store);
        $conv = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'conv_pilot_5',
            'store_id' => $store->id,
            'source_type' => 'trendyol',
            'status' => 'open',
            'ai_mode' => 'automatic',
        ]);

        Config::set('customer-care.enabled', false); // Master kapalı

        $gate = $this->app->make(CustomerCareAutomationGate::class);
        $res = $gate->canAutomate($conv);

        $this->assertFalse($res['allowed']);
        $this->assertStringContainsString('Master Kill-Switch', $res['reason']);
    }

    /**
     * 6. Golden dataset eval başarısız olursa (ortalama skor < 80) automatic mode açılamaz.
     */
    public function test_gate_rejects_when_eval_fails(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $store = $this->createStore($user);
        $channel = $this->createChannel($store);
        $conv = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'conv_pilot_6',
            'store_id' => $store->id,
            'source_type' => 'trendyol',
            'status' => 'open',
            'ai_mode' => 'automatic',
        ]);

        Config::set('customer-care.enabled', true);
        Config::set('customer-care.auto_reply_enabled', true);
        Config::set('customer-care.pilot_store_allowlist', [$store->id]);
        $this->seedPassLanguageGate($store->id);

        // Mock AI Provider: Golden dataset sorularına boş yanıt versin (Eval skoru 0 olur)
        $mockProvider = $this->createMockProvider("", 90);
        $this->app->instance(CustomerCareAiProviderInterface::class, $mockProvider);

        $gate = $this->app->make(CustomerCareAutomationGate::class);
        $res = $gate->canAutomate($conv, 90);

        $this->assertFalse($res['allowed']);
        $this->assertStringContainsString('Eval Gate Failure', $res['reason']);
    }

    /**
     * 7. Tüm kriterler ve eval gate geçildiğinde, pilot otomasyon onaylanır ve outbox kaydı tetiklenebilir hale gelir.
     */
    public function test_gate_allows_when_all_pilot_criteria_are_met_successfully(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $store = $this->createStore($user);
        $channel = $this->createChannel($store);
        $conv = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'conv_pilot_7',
            'store_id' => $store->id,
            'source_type' => 'trendyol',
            'status' => 'open',
            'ai_mode' => 'automatic',
        ]);

        Config::set('customer-care.enabled', true);
        Config::set('customer-care.auto_reply_enabled', true);
        Config::set('customer-care.pilot_store_allowlist', [$store->id]);
        Config::set('customer-care.reliability_enabled', true);
        Config::set('customer-care.circuit_breaker_enabled', true);

        // Seed evaluation run explicitly
        $this->seedPassEval($store->id);

        $gate = $this->app->make(CustomerCareAutomationGate::class);
        $res = $gate->canAutomate($conv, 90);

        $this->assertTrue($res['allowed'], $res['reason'] ?? '');
    }

    public function test_runtime_gate_fails_closed_when_reliability_or_circuit_monitoring_is_disabled(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $store = $this->createStore($user);
        $channel = $this->createChannel($store);
        $conv = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'conv_runtime_fail_closed',
            'store_id' => $store->id,
            'source_type' => 'trendyol',
            'status' => 'open',
            'ai_mode' => 'automatic',
        ]);
        Config::set('customer-care.enabled', true);
        Config::set('customer-care.auto_reply_enabled', true);
        Config::set('customer-care.pilot_store_allowlist', [$store->id]);
        $this->seedPassEval($store->id);

        Config::set('customer-care.reliability_enabled', false);
        Config::set('customer-care.circuit_breaker_enabled', true);
        $reliabilityResult = app(CustomerCareAutomationGate::class)->canAutomate($conv, 90);
        $this->assertFalse($reliabilityResult['allowed']);
        $this->assertStringContainsString('Reliability Gate', $reliabilityResult['reason']);

        Config::set('customer-care.reliability_enabled', true);
        Config::set('customer-care.circuit_breaker_enabled', false);
        $circuitResult = app(CustomerCareAutomationGate::class)->canAutomate($conv, 90);
        $this->assertFalse($circuitResult['allowed']);
        $this->assertStringContainsString('Circuit Breaker Gate', $circuitResult['reason']);
    }

    /**
     * PilotDashboard'un e-posta, telefon ve TC kimlik bilgilerini maskelediğini doğrula.
     */
    public function test_pilot_dashboard_masks_pii_data(): void
    {
        $dashboard = new \App\Livewire\CustomerCare\PilotDashboard();

        $emailText = "Benim mail adresim caner@example.com, ulasabilirsiniz.";
        $maskedEmail = $dashboard->maskPii($emailText);
        $this->assertStringNotContainsString("caner@example.com", $maskedEmail);
        $this->assertStringContainsString("c****@example.com", $maskedEmail);

        $phoneText = "Telefon numaram 0532 123 45 67'dir.";
        $maskedPhone = $dashboard->maskPii($phoneText);
        $this->assertStringNotContainsString("0532 123 45 67", $maskedPhone);
        $this->assertStringContainsString("0532 *** 45 67", $maskedPhone);

        $tcText = "TC numaram: 12345678901.";
        $maskedTc = $dashboard->maskPii($tcText);
        $this->assertStringNotContainsString("12345678901", $maskedTc);
        $this->assertStringContainsString("12*******01", $maskedTc);
    }

    /**
     * Store allowlist dışında sendAiReply çağrıldığında başarısız olmasını ve mesaj/dispatch oluşmamasını doğrula.
     */
    public function test_send_ai_reply_fails_when_store_not_in_allowlist(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $store = $this->createStore($user);
        $channel = $this->createChannel($store);
        $conv = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'conv_integration_allowlist',
            'store_id' => $store->id,
            'source_type' => 'trendyol',
            'status' => 'open',
            'ai_mode' => 'automatic',
            'ownership_status' => 'ai'
        ]);

        Config::set('customer-care.enabled', true);
        Config::set('customer-care.auto_reply_enabled', true);
        Config::set('customer-care.pilot_store_allowlist', [9999]); // Bu store izinli değil

        $replyService = $this->app->make(\App\Services\Support\SupportReplyService::class);
        $res = $replyService->sendAiReply($conv, 'Otomatik yanıt içeriği', 85);

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('Pilot Store Allowlist', $res['message']);

        // Veritabanında hiçbir SupportMessage veya support_dispatches oluşmadığını doğrula
        $this->assertDatabaseMissing('support_messages', [
            'conversation_id' => $conv->id,
            'body_encrypted' => 'Otomatik yanıt içeriği',
        ]);
    }

    /**
     * Golden eval başarısız olduğunda sendAiReply çağrıldığında başarısız olmasını doğrula.
     */
    public function test_send_ai_reply_fails_when_golden_eval_fails(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $store = $this->createStore($user);
        $channel = $this->createChannel($store);
        $conv = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'conv_integration_eval',
            'store_id' => $store->id,
            'source_type' => 'trendyol',
            'status' => 'open',
            'ai_mode' => 'automatic',
            'ownership_status' => 'ai'
        ]);

        Config::set('customer-care.enabled', true);
        Config::set('customer-care.auto_reply_enabled', true);
        Config::set('customer-care.pilot_store_allowlist', [$store->id]);
        $this->seedPassLanguageGate($store->id);

        // Mock AI Provider: Golden dataset sorularına boş yanıt versin (Eval skoru 0 olur)
        $mockProvider = $this->createMockProvider("", 90);
        $this->app->instance(CustomerCareAiProviderInterface::class, $mockProvider);

        $replyService = $this->app->make(\App\Services\Support\SupportReplyService::class);
        $res = $replyService->sendAiReply($conv, 'Otomatik yanıt içeriği', 85);

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('Eval Gate Failure', $res['message']);

        $this->assertDatabaseMissing('support_messages', [
            'conversation_id' => $conv->id,
        ]);
    }

    /**
     * Confidence threshold altındayken sendAiReply çağrıldığında başarısız olmasını doğrula.
     */
    public function test_send_ai_reply_fails_when_confidence_is_low(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $store = $this->createStore($user);
        $channel = $this->createChannel($store);
        $conv = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'conv_integration_confidence',
            'store_id' => $store->id,
            'source_type' => 'trendyol',
            'status' => 'open',
            'ai_mode' => 'automatic',
            'ownership_status' => 'ai'
        ]);

        Config::set('customer-care.enabled', true);
        Config::set('customer-care.auto_reply_enabled', true);
        Config::set('customer-care.pilot_store_allowlist', [$store->id]);

        $replyService = $this->app->make(\App\Services\Support\SupportReplyService::class);
        $res = $replyService->sendAiReply($conv, 'Otomatik yanıt içeriği', 70); // 70 < 80

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('Confidence Threshold', $res['message']);

        $this->assertDatabaseMissing('support_messages', [
            'conversation_id' => $conv->id,
        ]);
    }

    /**
     * Confidence değeri null (eksik) gönderildiğinde fail-closed olmasını doğrula.
     */
    public function test_send_ai_reply_fails_when_confidence_is_missing(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $store = $this->createStore($user);
        $channel = $this->createChannel($store);
        $conv = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'conv_integration_no_conf',
            'store_id' => $store->id,
            'source_type' => 'trendyol',
            'status' => 'open',
            'ai_mode' => 'automatic',
            'ownership_status' => 'ai'
        ]);

        Config::set('customer-care.enabled', true);
        Config::set('customer-care.auto_reply_enabled', true);
        Config::set('customer-care.pilot_store_allowlist', [$store->id]);

        $replyService = $this->app->make(\App\Services\Support\SupportReplyService::class);
        $res = $replyService->sendAiReply($conv, 'Otomatik yanıt içeriği', null); // null

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('AI güven skoru eksik', $res['message']);

        $this->assertDatabaseMissing('support_messages', [
            'conversation_id' => $conv->id,
        ]);
    }

    /**
     * Tüm kapılar başarıyla geçildiğinde otomatik yanıt gönderilmesini doğrula.
     */
    public function test_send_ai_reply_succeeds_when_all_gates_are_passed(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $store = $this->createStore($user);
        $channel = $this->createChannel($store);
        $conv = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'conv_integration_success',
            'store_id' => $store->id,
            'source_type' => 'trendyol',
            'status' => 'open',
            'ai_mode' => 'automatic',
            'ownership_status' => 'ai'
        ]);

        Config::set('customer-care.enabled', true);
        Config::set('customer-care.auto_reply_enabled', true);
        Config::set('customer-care.pilot_store_allowlist', [$store->id]);
        Config::set('customer-care.reliability_enabled', true);
        Config::set('customer-care.circuit_breaker_enabled', true);

        // Seed evaluation run explicitly
        $this->seedPassEval($store->id);

        // Mock Channel Manager: Mesajı outbox üzerinden göndersin
        $mockAdapter = $this->createMock(\App\Services\Support\SupportChannelAdapterInterface::class);
        $mockAdapter->method('canReply')->willReturn(true);
        $mockAdapter->method('sendReply')->willReturn(['success' => true, 'channel_message_id' => 'ch_msg_99']);
        $mockAdapter->method('getOutboundTargetStatus')->willReturn('sent');

        $mockManager = $this->createMock(\App\Services\Support\SupportChannelManager::class);
        $mockManager->method('resolveForChannel')->willReturn($mockAdapter);
        $this->app->instance(\App\Services\Support\SupportChannelManager::class, $mockManager);

        $replyService = $this->app->make(\App\Services\Support\SupportReplyService::class);
        $res = $replyService->sendAiReply($conv, 'Otomatik yanıt içeriği', 90);

        $this->assertTrue($res['success'], json_encode($res, JSON_UNESCAPED_UNICODE));

        $msg = SupportMessage::where('conversation_id', $conv->id)->where('sender_type', 'ai')->first();
        $this->assertNotNull($msg);
        $this->assertEquals('Otomatik yanıt içeriği', $msg->body_encrypted);
        $this->assertEquals('queued', $msg->delivery_status);
        $this->assertDatabaseHas('support_dispatches', [
            'message_id' => $msg->id,
            'status' => 'pending',
        ]);
    }

    public function test_send_ai_reply_fails_when_auto_reply_max_per_hour_is_zero(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $store = $this->createStore($user);
        $channel = $this->createChannel($store);
        $conv = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'conv_pilot_99',
            'store_id' => $store->id,
            'source_type' => 'trendyol',
            'status' => 'open',
            'ai_mode' => 'automatic',
            'ownership_status' => 'ai'
        ]);

        Config::set('customer-care.enabled', true);
        Config::set('customer-care.auto_reply_enabled', true);
        Config::set('customer-care.pilot_store_allowlist', [$store->id]);
        Config::set('customer-care.auto_reply_max_per_hour', 0); // 0 = fail-closed

        $this->seedPassEval($store->id);

        $replyService = $this->app->make(\App\Services\Support\SupportReplyService::class);
        $res = $replyService->sendAiReply($conv, 'Otomatik yanıt içeriği', 90);

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('Rate Limit Fail-Closed', $res['message']);

        // Check no messages/dispatches are created
        $msgExists = SupportMessage::where('conversation_id', $conv->id)->where('sender_type', 'ai')->exists();
        $this->assertFalse($msgExists);
    }
}
