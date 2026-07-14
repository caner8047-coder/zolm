<?php

namespace Tests\Feature\CustomerCare;

use Tests\TestCase;
use App\Models\User;
use App\Models\SupportConversation;
use App\Models\SupportChannel;
use App\Models\SupportMessage;
use App\Models\SupportAgentAction;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use Illuminate\Support\Facades\Config;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\Support\AI\CustomerCareAiProviderInterface;
use App\Services\Support\AI\CustomerCareAiResponseDto;
use Tests\Feature\CustomerCare\CustomerCareTestHelper;

class SupportPolicyEngineTest extends TestCase
{
    use RefreshDatabase, CustomerCareTestHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupSystemActor();
        Config::set('customer-care.enabled', true);
        Config::set('customer-care.auto_reply_enabled', true);
        Config::set('customer-care.auto_reply_max_per_hour', 100);
    }

    protected function createStore(User $user, string $name = 'Test Store Policy', string $code = 'TST')
    {
        $legalEntity = LegalEntity::create([
            'user_id' => $user->id,
            'name' => $name . ' Legal',
            'tax_number' => '1234567890',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'trendyol',
            'store_name' => $name,
            'store_code' => $code,
            'is_active' => true,
        ]);

        $this->seedPassEval($store->id);

        return $store;
    }

    protected function createChannel($store)
    {
        return SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'trendyol',
            'name' => 'Trendyol Soru-Cevap',
            'status' => 'active',
            'is_enabled' => true,
        ]);
    }

    /**
     * AI telefon, e-posta veya link içeren cevapta engellenmeli.
     */
    public function test_ai_reply_with_links_or_contacts_blocked(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $store = $this->createStore($user);
        $channel = $this->createChannel($store);
        $conv = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'conv_policy_ai_1',
            'store_id' => $store->id,
            'source_type' => 'trendyol',
            'status' => 'open',
            'ai_mode' => 'automatic',
            'ownership_status' => 'ai'
        ]);

        Config::set('customer-care.enabled', true);
        Config::set('customer-care.auto_reply_enabled', true);
        Config::set('customer-care.pilot_store_allowlist', [$store->id]);

        // Mock Golden Eval to pass
        $mockProvider = $this->createMock(CustomerCareAiProviderInterface::class);
        $mockProvider->method('generateAnswer')->willReturn(
            new CustomerCareAiResponseDto(
                "Yurtiçi kargo teslimat 14 gün ücretsiz iade L beden kilo uygun stokta mevcut koltuk 450 TL fiyatı yüzde indirim kupon hazırlanıyor kargoda yolda meşe doğal gürgen",
                95,
                [],
                false,
                'tr'
            )
        );
        $this->app->instance(CustomerCareAiProviderInterface::class, $mockProvider);

        $replyService = $this->app->make(\App\Services\Support\SupportReplyService::class);

        // 1. Link İçeren Yanıt Engellenmeli
        $resLink = $replyService->sendAiReply($conv, 'Lütfen sitemizi ziyaret edin: www.example.com', 90);
        $this->assertFalse($resLink['success']);
        $this->assertStringContainsString('harici link', $resLink['message']);
        $this->assertDatabaseMissing('support_messages', [
            'conversation_id' => $conv->id,
        ]);
        $this->assertDatabaseMissing('support_dispatches', [
            'conversation_id' => $conv->id,
        ]);

        // 2. E-posta İçeren Yanıt Engellenmeli
        $resEmail = $replyService->sendAiReply($conv, 'Bize support@example.com üzerinden ulaşın.', 90);
        $this->assertFalse($resEmail['success']);
        $this->assertStringContainsString('e-posta adresi', $resEmail['message']);

        // 3. Telefon İçeren Yanıt Engellenmeli
        $resPhone = $replyService->sendAiReply($conv, 'Bizimle 0532 123 45 67 numarasından iletişime geçin.', 90);
        $this->assertFalse($resPhone['success']);
        $this->assertStringContainsString('telefon numarası', $resPhone['message']);
    }

    /**
     * AI karakter limitini aşarsa engellenmeli.
     */
    public function test_ai_reply_exceeding_character_limit_blocked(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $store = $this->createStore($user);

        // Hepsiburada kanalında limit 2000 karakter
        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'hepsiburada',
            'name' => 'Hepsiburada Soru-Cevap',
            'status' => 'active',
            'is_enabled' => true,
        ]);

        $conv = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'conv_policy_ai_limit',
            'store_id' => $store->id,
            'source_type' => 'hepsiburada',
            'status' => 'open',
            'ai_mode' => 'automatic',
            'ownership_status' => 'ai'
        ]);

        Config::set('customer-care.enabled', true);
        Config::set('customer-care.auto_reply_enabled', true);
        Config::set('customer-care.pilot_store_allowlist', [$store->id]);

        $mockProvider = $this->createMock(CustomerCareAiProviderInterface::class);
        $mockProvider->method('generateAnswer')->willReturn(
            new CustomerCareAiResponseDto(
                "Yurtiçi kargo teslimat 14 gün ücretsiz iade L beden kilo uygun stokta mevcut koltuk 450 TL fiyatı yüzde indirim kupon hazırlanıyor kargoda yolda meşe doğal gürgen",
                95,
                [],
                false,
                'tr'
            )
        );
        $this->app->instance(CustomerCareAiProviderInterface::class, $mockProvider);

        $replyService = $this->app->make(\App\Services\Support\SupportReplyService::class);

        // 2001 karakterli uzun mesaj üret
        $longMessage = str_repeat('a', 2001);

        $res = $replyService->sendAiReply($conv, $longMessage, 90);
        $this->assertFalse($res['success']);
        $this->assertStringContainsString('Karakter limiti aşıldı', $res['message']);
    }

    /**
     * İnsan cevabı policy ihlalinde dispatch oluşturmamalı ve engellenmeli.
     */
    public function test_human_reply_policy_violation_blocked_and_audited(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $store = $this->createStore($user);
        $channel = $this->createChannel($store);
        $conv = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'conv_policy_human',
            'store_id' => $store->id,
            'source_type' => 'trendyol',
            'status' => 'open',
        ]);

        Config::set('customer-care.enabled', true);

        $replyService = $this->app->make(\App\Services\Support\SupportReplyService::class);

        // Link içeren yasaklı mesaj
        $res = $replyService->sendAgentReply($conv, 'Lütfen linke tıklayın: www.google.com', $user->id);

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('harici link', $res['message']);

        // support_agent_actions tablosunda policy_block audit kaydının oluştuğunu doğrula
        $action = SupportAgentAction::where([
            'conversation_id' => $conv->id,
            'user_id' => $user->id,
            'action' => 'policy_block',
        ])->first();

        $this->assertNotNull($action);
        $this->assertIsArray($action->details_json);
        $this->assertArrayHasKey('reason', $action->details_json);
        $this->assertStringContainsString('harici link', $action->details_json['reason']);

        // Veritabanında mesaj veya outbox oluşmadığını doğrula
        $this->assertDatabaseMissing('support_messages', [
            'conversation_id' => $conv->id,
        ]);
        $this->assertDatabaseMissing('support_dispatches', [
            'conversation_id' => $conv->id,
        ]);
    }

    /**
     * Temiz cevap politika engelini geçip gönderilmelidir.
     */
    public function test_clean_reply_passes_successfully(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $store = $this->createStore($user);
        $channel = $this->createChannel($store);
        $conv = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'conv_policy_clean',
            'store_id' => $store->id,
            'source_type' => 'trendyol',
            'status' => 'open',
        ]);

        Config::set('customer-care.enabled', true);

        // Mock Channel Manager
        $mockAdapter = $this->createMock(\App\Services\Support\SupportChannelAdapterInterface::class);
        $mockAdapter->method('canReply')->willReturn(true);
        $mockAdapter->method('sendReply')->willReturn(['success' => true, 'channel_message_id' => 'ch_msg_policy_ok']);
        $mockAdapter->method('getOutboundTargetStatus')->willReturn('sent');

        $mockManager = $this->createMock(\App\Services\Support\SupportChannelManager::class);
        $mockManager->method('resolveForChannel')->willReturn($mockAdapter);
        $this->app->instance(\App\Services\Support\SupportChannelManager::class, $mockManager);

        $replyService = $this->app->make(\App\Services\Support\SupportReplyService::class);

        // Temiz mesaj gönderimi
        $res = $replyService->sendAgentReply($conv, 'Ürününüz bugün kargoya verilecektir, teşekkür ederiz.', $user->id);

        $this->assertTrue($res['success']);
        $this->assertDatabaseHas('support_messages', [
            'conversation_id' => $conv->id,
            'delivery_status' => 'queued',
        ]);
        $this->assertDatabaseHas('support_dispatches', [
            'conversation_id' => $conv->id,
            'status' => 'pending',
        ]);
    }

    /**
     * Türkçe normalizasyonun yasaklı kelimeleri ve taahhütleri doğru engellediğini doğrula.
     */
    public function test_turkish_normalization_blocks_forbidden_keywords_and_promises(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $store = $this->createStore($user);
        $channel = $this->createChannel($store);
        $conv = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'conv_policy_turkish',
            'store_id' => $store->id,
            'source_type' => 'trendyol',
            'status' => 'open',
        ]);

        Config::set('customer-care.enabled', true);
        $replyService = $this->app->make(\App\Services\Support\SupportReplyService::class);

        // 1. Türkçe karakterli yasaklı kelime engellenmeli ('kapıda ödeme' -> 'kapida odeme')
        $res1 = $replyService->sendAgentReply($conv, 'Ödemeyi kapıda ödeme olarak yapabilirsiniz.', $user->id);
        $this->assertFalse($res1['success']);
        $this->assertStringContainsString('yasaklı ifade', $res1['message']);

        // 2. Farklı Türkçe karakterli yasaklı kelime engellenmeli ('kapıda odeme')
        $res2 = $replyService->sendAgentReply($conv, 'Sadece kapıda odeme kabul ediyoruz.', $user->id);
        $this->assertFalse($res2['success']);

        // 3. Çoklu boşluk ve Türkçe karakterli yasaklı kelime engellenmeli ('kapida   ödeme')
        $res3 = $replyService->sendAgentReply($conv, 'Gonderimler kapida   ödeme seklindedir.', $user->id);
        $this->assertFalse($res3['success']);

        // 4. Kesin teslimat taahhüdü Türkçe karakterli engellenmeli ('yarın kapınızda')
        $res4 = $replyService->sendAgentReply($conv, 'Urununuz yarin kapinizda olur.', $user->id);
        $this->assertFalse($res4['success']);
        $this->assertStringContainsString('kesin teslimat', $res4['message']);

        $res5 = $replyService->sendAgentReply($conv, 'Urununuz yarın kapınızda olur.', $user->id);
        $this->assertFalse($res5['success']);
    }
}
