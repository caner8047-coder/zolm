<?php

namespace Tests\Feature\CustomerCare;

use Tests\TestCase;
use App\Models\User;
use App\Models\SupportChannel;
use App\Models\SupportConversation;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Services\Support\SupportChannelManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SupportChannelAdapterContractTest extends TestCase
{
    use RefreshDatabase, CustomerCareTestHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupSystemActor();
    }

    protected function createStore(User $user, string $marketplace): MarketplaceStore
    {
        $legalEntity = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Store Legal ' . $marketplace,
            'tax_number' => (string) rand(1000000000, 9999999999),
            'is_active' => true,
        ]);

        return MarketplaceStore::create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => $marketplace,
            'store_name' => 'Store Name ' . $marketplace,
            'store_code' => 'ST_' . strtoupper($marketplace),
            'is_active' => true,
        ]);
    }

    /**
     * Tüm adapter'ların contract kurallarına uyduğunu doğrula.
     */
    public function test_all_adapters_satisfy_contract(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $manager = app(SupportChannelManager::class);
        $adapters = $manager->getAllAdapters();

        // En azından Trendyol, Hepsiburada, WhatsApp, N11 ve Null adapter'larının register edildiğini doğrula
        $this->assertArrayHasKey('trendyol', $adapters);
        $this->assertArrayHasKey('hepsiburada', $adapters);
        $this->assertArrayHasKey('whatsapp', $adapters);
        $this->assertArrayHasKey('n11', $adapters);

        foreach ($adapters as $key => $adapter) {
            // Null adapter hariç gerçek kanalları test edelim
            if ($key === 'null') continue;

            $store = $this->createStore($user, $key);
            $channel = SupportChannel::create([
                'store_id' => $store->id,
                'key' => $key,
                'name' => 'Test Channel ' . $key,
                'status' => 'active',
                'is_enabled' => true,
            ]);

            // Capabilities test
            $capabilities = $adapter->getCapabilities();
            $this->assertIsArray($capabilities);
            foreach ($capabilities as $cap) {
                $this->assertArrayHasKey('capability', $cap);
                $this->assertArrayHasKey('status', $cap);
            }

            // canReply test
            $this->assertIsBool($adapter->canReply($channel));

            // healthCheck test
            $health = $adapter->healthCheck($channel);
            $this->assertIsArray($health);
            $this->assertArrayHasKey('status', $health);

            // getOutboundTargetStatus test
            $this->assertIsString($adapter->getOutboundTargetStatus());
        }
    }

    /**
     * Devre dışı bırakılmış kanalın mesaj gönderimini engellediğini doğrula.
     */
    public function test_disabled_channel_blocks_reply(): void
    {
        \Illuminate\Support\Facades\Config::set('customer-care.enabled', true);
        $user = User::factory()->create(['is_active' => true]);
        $store = $this->createStore($user, 'trendyol');
        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'trendyol',
            'name' => 'Trendyol Soru-Cevap',
            'status' => 'active',
            'is_enabled' => false, // disabled
        ]);

        $conv = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'conv_disabled',
            'store_id' => $store->id,
            'source_type' => 'trendyol',
            'status' => 'open',
        ]);

        $replyService = app(\App\Services\Support\SupportReplyService::class);
        $res = $replyService->sendAgentReply($conv, 'Test mesajı', $user->id);

        $this->assertFalse($res['success']);
        $this->assertEquals('Kanal devre dışı bırakılmış', $res['message']);
    }

    /**
     * Gönderim yeteneği (capability) desteklenmeyen kanallarda mesaj gönderiminin engellendiğini doğrula.
     */
    public function test_unsupported_capability_blocks_reply(): void
    {
        \Illuminate\Support\Facades\Config::set('customer-care.enabled', true);
        $user = User::factory()->create(['is_active' => true]);
        $store = $this->createStore($user, 'n11');

        // N11 kanalında send_messages yeteneği yok (status = unavailable)
        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'n11',
            'name' => 'N11 Soru-Cevap',
            'status' => 'active',
            'is_enabled' => true,
        ]);

        // Capability refresh edelim ki veritabanına unavailable olarak işlensin
        app(\App\Services\Support\SupportCapabilityService::class)->refreshCapabilities($channel);

        $conv = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'conv_unsupported',
            'store_id' => $store->id,
            'source_type' => 'n11',
            'status' => 'open',
        ]);

        $replyService = app(\App\Services\Support\SupportReplyService::class);
        $res = $replyService->sendAgentReply($conv, 'Test mesajı', $user->id);

        $this->assertFalse($res['success']);
        $this->assertEquals('Bu kanalda mesaj gönderme yetkisi yok', $res['message']);
    }

    /**
     * N11 skeleton davranış testleri.
     */
    public function test_n11_skeleton_adapter_behavior(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $store = $this->createStore($user, 'n11');
        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'n11',
            'name' => 'N11 Soru-Cevap',
            'status' => 'active',
            'is_enabled' => true,
        ]);

        $adapter = app(SupportChannelManager::class)->resolveForChannel($channel);

        // N11 skeleton canReply false dönmeli
        $this->assertFalse($adapter->canReply($channel));

        // N11 skeleton sendReply success=false dönmeli
        $res = $adapter->sendReply($channel, 'conv_n11', 'Test mesajı');
        $this->assertFalse($res['success']);

        // N11 adapter getOutboundTargetStatus sent dönmeli
        $this->assertEquals('sent', $adapter->getOutboundTargetStatus());

        // N11 send_messages capability available olmamalı (unavailable olmalı)
        $capabilities = $adapter->getCapabilities();
        $sendMessageCap = collect($capabilities)->firstWhere('capability', 'send_messages');
        $this->assertNotNull($sendMessageCap);
        $this->assertEquals('unavailable', $sendMessageCap['status']);
    }

    /**
     * Hepsiburada skeleton davranış testleri.
     */
    public function test_hepsiburada_skeleton_adapter_behavior(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $store = $this->createStore($user, 'hepsiburada');
        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'hepsiburada',
            'name' => 'Hepsiburada Soru-Cevap',
            'status' => 'active',
            'is_enabled' => true,
        ]);

        $adapter = app(SupportChannelManager::class)->resolveForChannel($channel);

        // Hepsiburada skeleton canReply false dönmeli
        $this->assertFalse($adapter->canReply($channel));

        // Hepsiburada skeleton sendReply success=false dönmeli (gecersiz format veya unconfigured)
        $res = $adapter->sendReply($channel, 'conv_hb', 'Test mesajı');
        $this->assertFalse($res['success']);

        // Hepsiburada adapter getOutboundTargetStatus sent dönmeli
        $this->assertEquals('sent', $adapter->getOutboundTargetStatus());

        // Hepsiburada send_messages capability available olmamalı (unavailable olmalı)
        $capabilities = $adapter->getCapabilities();
        $sendMessageCap = collect($capabilities)->firstWhere('capability', 'send_messages');
        $this->assertNotNull($sendMessageCap);
        $this->assertEquals('unavailable', $sendMessageCap['status']);
    }

    public function test_hepsiburada_adapter_production_behavior(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $store = $this->createStore($user, 'hepsiburada');

        \App\Models\IntegrationConnection::create([
            'store_id' => $store->id,
            'provider' => 'hepsiburada',
            'auth_type' => 'api_key',
            'credentials_encrypted' => ['api_key' => 'hb-api-key', 'merchant_id' => 'hb-merchant-123'],
            'status' => 'configured',
        ]);

        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'hepsiburada',
            'name' => 'Hepsiburada Soru-Cevap',
            'status' => 'active',
            'is_enabled' => true,
        ]);
        app(\App\Services\Support\SupportCapabilityService::class)->refreshCapabilities($channel);

        $adapter = app(SupportChannelManager::class)->resolveForChannel($channel);

        $this->assertTrue($adapter->canReply($channel));
        $this->assertEquals(['status' => 'ok', 'message' => 'Hepsiburada bağlantısı aktif'], $adapter->healthCheck($channel));

        $capabilities = $adapter->getCapabilities($channel);
        $sendMessageCap = collect($capabilities)->firstWhere('capability', 'send_messages');
        $this->assertEquals('available', $sendMessageCap['status']);

        // Fake HTTP for Hepsiburada API response
        \Illuminate\Support\Facades\Http::fake([
            '*api-asktoseller-merchant-sit.hepsiburada.com*/issues/999/answer' => \Illuminate\Support\Facades\Http::response(['id' => 'HB-ANS-999'], 200),
        ]);

        $question = \App\Models\MarketplaceQuestion::query()->create([
            'store_id' => $store->id,
            'external_question_id' => '999',
            'status' => 'open',
            'product_name' => 'Test Product',
            'question_text' => 'Kargo ne zaman çıkar?',
            'asked_at' => now(),
        ]);

        // Success send
        $res = $adapter->sendReply($channel, 'hepsiburada_questions_' . $question->id, 'Yarın kargoda.', 'idemp-hb-1');
        $this->assertTrue($res['success']);
        $this->assertEquals('HB-ANS-999', $res['channel_message_id']);

        // Idempotency check
        $resIdemp = $adapter->sendReply($channel, 'hepsiburada_questions_' . $question->id, 'Yarın kargoda.', 'idemp-hb-1');
        $this->assertTrue($resIdemp['success']);
        $this->assertTrue($resIdemp['is_duplicate'] ?? false);

        // Malformed format check
        $resMalformed = $adapter->sendReply($channel, 'hepsiburada_questions_abc', 'Yarın kargoda.');
        $this->assertFalse($resMalformed['success']);

        // Cross-tenant IDOR protection check
        $otherUser = User::factory()->create();
        $otherStore = $this->createStore($otherUser, 'hepsiburada');
        $otherQuestion = \App\Models\MarketplaceQuestion::query()->create([
            'store_id' => $otherStore->id,
            'external_question_id' => '1000',
            'status' => 'open',
            'product_name' => 'Other Product',
            'question_text' => 'Fiyat nedir?',
            'asked_at' => now(),
        ]);

        $resIdor = $adapter->sendReply($channel, 'hepsiburada_questions_' . $otherQuestion->id, 'Yarın kargoda.');
        $this->assertFalse($resIdor['success']);
        $this->assertStringContainsString('bu mağazaya ait değil', $resIdor['message']);
    }

    public function test_n11_adapter_production_behavior(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $store = $this->createStore($user, 'n11');

        \App\Models\IntegrationConnection::create([
            'store_id' => $store->id,
            'provider' => 'n11',
            'auth_type' => 'api_key',
            'credentials_encrypted' => ['api_key' => 'n11-key', 'api_secret' => 'n11-secret'],
            'status' => 'configured',
        ]);

        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'n11',
            'name' => 'N11 Soru-Cevap',
            'status' => 'active',
            'is_enabled' => true,
        ]);
        app(\App\Services\Support\SupportCapabilityService::class)->refreshCapabilities($channel);

        $adapter = app(SupportChannelManager::class)->resolveForChannel($channel);

        $this->assertTrue($adapter->canReply($channel));
        $this->assertEquals(['status' => 'ok', 'message' => 'N11 bağlantısı aktif'], $adapter->healthCheck($channel));

        $capabilities = $adapter->getCapabilities($channel);
        $sendMessageCap = collect($capabilities)->firstWhere('capability', 'send_messages');
        $this->assertEquals('available', $sendMessageCap['status']);

        // Fake SOAP HTTP response
        \Illuminate\Support\Facades\Http::fake([
            '*productService*' => \Illuminate\Support\Facades\Http::response('<?xml version="1.0" encoding="UTF-8"?><soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"><soapenv:Body><SaveProductAnswerResponse><result><status>success</status></result><answerId>N11-A-888</answerId></SaveProductAnswerResponse></soapenv:Body></soapenv:Envelope>', 200),
        ]);

        $question = \App\Models\MarketplaceQuestion::query()->create([
            'store_id' => $store->id,
            'external_question_id' => '888',
            'status' => 'open',
            'product_name' => 'Test Product',
            'question_text' => 'Kargo ne zaman çıkar?',
            'asked_at' => now(),
        ]);

        // Success send
        $res = $adapter->sendReply($channel, 'n11_questions_' . $question->id, 'Yarın kargoda.', 'idemp-n11-1');
        $this->assertTrue($res['success']);
        $this->assertEquals('N11-A-888', $res['channel_message_id']);

        // Idempotency check
        $resIdemp = $adapter->sendReply($channel, 'n11_questions_' . $question->id, 'Yarın kargoda.', 'idemp-n11-1');
        $this->assertTrue($resIdemp['success']);
        $this->assertTrue($resIdemp['is_duplicate'] ?? false);

        // Malformed format check
        $resMalformed = $adapter->sendReply($channel, 'n11_questions_abc', 'Yarın kargoda.');
        $this->assertFalse($resMalformed['success']);

        // Cross-tenant IDOR protection check
        $otherUser = User::factory()->create();
        $otherStore = $this->createStore($otherUser, 'n11');
        $otherQuestion = \App\Models\MarketplaceQuestion::query()->create([
            'store_id' => $otherStore->id,
            'external_question_id' => '1001',
            'status' => 'open',
            'product_name' => 'Other Product',
            'question_text' => 'Fiyat nedir?',
            'asked_at' => now(),
        ]);

        $resIdor = $adapter->sendReply($channel, 'n11_questions_' . $otherQuestion->id, 'Yarın kargoda.');
        $this->assertFalse($resIdor['success']);
        $this->assertStringContainsString('bu mağazaya ait değil', $resIdor['message']);
    }
}
