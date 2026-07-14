<?php

namespace Tests\Feature\CustomerCare;

use App\Models\User;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\SupportChannel;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\SupportDispatch;
use App\Models\SupportDispatchAttempt;
use App\Models\WaConversation;
use App\Models\WaContact;
use App\Models\WaOutbox;
use App\Services\Support\SupportChannelManager;
use App\Services\Support\SupportChannelAdapterInterface;
use App\Services\Support\SupportOutboxService;
use App\Services\Support\SupportReplyService;
use App\Services\Support\WhatsAppSupportChannelAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportOutboxTest extends TestCase
{
    use RefreshDatabase;
    use CustomerCareTestHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupSystemActor();
        \Illuminate\Support\Facades\Config::set('customer-care.auto_reply_max_per_hour', 100);
    }

    private function createStore(User $user): MarketplaceStore
    {
        $legalEntity = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Test Company',
            'tax_number' => '1234567890',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'Test Store',
            'store_code' => 'TST',
            'is_active' => true,
        ]);

        $this->seedPassEval($store->id);

        return $store;
    }

    private function createMockChannelManager(bool $shouldSucceed, ?string $errorMessage = null): void
    {
        $mockAdapter = new class($shouldSucceed, $errorMessage) implements SupportChannelAdapterInterface {
            private bool $shouldSucceed;
            private ?string $errorMessage;

            public function __construct(bool $shouldSucceed, ?string $errorMessage)
            {
                $this->shouldSucceed = $shouldSucceed;
                $this->errorMessage = $errorMessage;
            }

            public function key(): string { return 'trendyol'; }
            public function name(): string { return 'Trendyol'; }
            public function getCapabilities(?SupportChannel $channel = null): array { return []; }
            public function healthCheck(SupportChannel $channel): array { return []; }
            public function syncConversations(SupportChannel $channel): array { return []; }
            public function fetchMessages(SupportChannel $channel, string $conversationExternalId): array { return []; }
            public function canReply(SupportChannel $channel): bool { return true; }

            public function sendReply(SupportChannel $channel, string $conversationExternalId, string $message, ?string $idempotencyKey = null): array
            {
                if ($this->shouldSucceed) {
                    return ['success' => true, 'channel_message_id' => 'external-msg-999'];
                }

                return ['success' => false, 'message' => $this->errorMessage ?? 'Failed mock transmission'];
            }

            public function resolveOrderContext(SupportChannel $channel, string $externalConversationId): ?array { return null; }

            public function getOutboundTargetStatus(): string
            {
                return 'sent';
            }
        };

        $mockManager = $this->createMock(SupportChannelManager::class);
        $mockManager->method('resolveForChannel')->willReturn($mockAdapter);
        $mockManager->method('resolve')->willReturn($mockAdapter);

        $this->app->instance(SupportChannelManager::class, $mockManager);
    }

    /**
     * Mesajın başarıyla enqueued edildiğini ve idempotency korumasını doğrula.
     */
    public function test_outbox_enqueue_and_idempotency(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $store = $this->createStore($user);

        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'trendyol',
            'name' => 'Trendyol Soru-Cevap',
            'status' => 'active',
            'is_enabled' => true,
        ]);

        $conversation = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'trendyol_conv_123',
            'store_id' => $store->id,
            'source_type' => 'trendyol',
            'status' => 'open',
        ]);

        $message = SupportMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'sender_type' => 'agent',
            'message_type' => 'text',
            'body_encrypted' => 'Merhaba, bu bir test mesajıdır.',
            'delivery_status' => 'sending',
        ]);

        $outboxService = new SupportOutboxService();

        // 1. Enqueue işlemi
        $dispatch1 = $outboxService->enqueue($message, 'idemp-key-111');
        $this->assertNotNull($dispatch1);
        $this->assertEquals('pending', $dispatch1->status);
        $this->assertEquals('idemp-key-111', $dispatch1->idempotency_key);

        // 2. İkinci Enqueue işlemi (Aynı idempotency key ile) - Yeni kayıt oluşturmamalı
        $dispatch2 = $outboxService->enqueue($message, 'idemp-key-111');
        $this->assertEquals($dispatch1->id, $dispatch2->id);
        $this->assertEquals(1, SupportDispatch::count());
    }

    /**
     * Başarılı bir gönderim ve durum güncellemelerini doğrula.
     */
    public function test_outbox_successful_dispatch(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $store = $this->createStore($user);

        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'trendyol',
            'name' => 'Trendyol Soru-Cevap',
            'status' => 'active',
            'is_enabled' => true,
        ]);

        $conversation = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'trendyol_conv_123',
            'store_id' => $store->id,
            'source_type' => 'trendyol',
            'status' => 'open',
        ]);

        $message = SupportMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'sender_type' => 'agent',
            'message_type' => 'text',
            'body_encrypted' => 'Merhaba',
            'delivery_status' => 'sending',
        ]);

        // Kill switch açık (gönderim testlerinde enabled olmalı)
        \Illuminate\Support\Facades\Config::set('customer-care.enabled', true);

        // Başarılı adapter mocklayalım
        $this->createMockChannelManager(true);

        $outboxService = new SupportOutboxService();
        $dispatch = $outboxService->enqueue($message);

        $success = $outboxService->sendDispatch($dispatch);

        $this->assertTrue($success);
        $this->assertEquals('sent', $dispatch->fresh()->status);
        $this->assertEquals('sent', $message->fresh()->delivery_status);
        $this->assertEquals('external-msg-999', $dispatch->fresh()->channel_message_id);

        // Deneme kaydı (attempt) oluşturulmuş mu?
        $this->assertEquals(1, SupportDispatchAttempt::count());
        $this->assertEquals('success', SupportDispatchAttempt::first()->status);
    }

    /**
     * Hatalı gönderim durumunda exponential backoff planlamasını ve limitleri doğrula.
     */
    public function test_outbox_failed_dispatch_retry_and_backoff(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $store = $this->createStore($user);

        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'trendyol',
            'name' => 'Trendyol Soru-Cevap',
            'status' => 'active',
            'is_enabled' => true,
        ]);

        $conversation = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'trendyol_conv_123',
            'store_id' => $store->id,
            'source_type' => 'trendyol',
            'status' => 'open',
        ]);

        $message = SupportMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'sender_type' => 'agent',
            'message_type' => 'text',
            'body_encrypted' => 'Merhaba',
            'delivery_status' => 'sending',
        ]);

        // Kill switch açık (gönderim testlerinde enabled olmalı)
        \Illuminate\Support\Facades\Config::set('customer-care.enabled', true);

        // Hata alan adapter mocklayalım
        $this->createMockChannelManager(false, 'API Gateway Timeout');

        $outboxService = new SupportOutboxService();
        $dispatch = $outboxService->enqueue($message);

        // 1. Deneme
        $success = $outboxService->sendDispatch($dispatch);

        $this->assertFalse($success);
        $dispatch = $dispatch->fresh();
        $this->assertEquals('failed', $dispatch->status);
        $this->assertEquals(1, $dispatch->attempt_count);
        $this->assertNotNull($dispatch->retry_at);
        $this->assertEquals('API Gateway Timeout', $dispatch->last_error);

        // Retry süresinin 2^1 * 5 = 10 saniye eklenerek ayarlandığını doğrula
        $this->assertGreaterThan(now()->addSeconds(8), $dispatch->retry_at);
        $this->assertLessThan(now()->addSeconds(12), $dispatch->retry_at);

        // Attempts tablosunda kayıt doğrula
        $this->assertEquals(1, SupportDispatchAttempt::count());
        $this->assertEquals('failed', SupportDispatchAttempt::first()->status);
        $this->assertEquals('API Gateway Timeout', SupportDispatchAttempt::first()->error_message);
    }

    /**
     * Eşzamanlı worker çakışma korumasını (atomic claim) doğrula.
     */
    public function test_outbox_concurrency_locks_prevent_double_processing(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $store = $this->createStore($user);

        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'trendyol',
            'name' => 'Trendyol Soru-Cevap',
            'status' => 'active',
            'is_enabled' => true,
        ]);

        $conversation = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'trendyol_conv_1',
            'store_id' => $store->id,
            'source_type' => 'trendyol',
            'status' => 'open',
        ]);

        $message = SupportMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'sender_type' => 'agent',
            'message_type' => 'text',
            'body_encrypted' => 'Merhaba',
            'delivery_status' => 'sending',
        ]);

        $outboxService = new SupportOutboxService();
        $dispatch = $outboxService->enqueue($message, 'idemp-concurrency-1');

        // İki farklı worker'ın aynı anda select and claim yapmasını simüle edelim.
        $id = $dispatch->id;

        // Worker 1 claim etmeye çalışır
        $affected1 = SupportDispatch::where('id', $id)
            ->whereIn('status', ['pending', 'failed'])
            ->update(['status' => 'sending']);

        // Worker 2 aynı anda claim etmeye çalışır
        $affected2 = SupportDispatch::where('id', $id)
            ->whereIn('status', ['pending', 'failed'])
            ->update(['status' => 'sending']);

        $this->assertEquals(1, $affected1);
        $this->assertEquals(0, $affected2); // Worker 2 eli boş dönmeli (Atomik Claim Başarılı)
    }

    /**
     * 5. denemeden sonra terminal exhausted durumuna geçildiğini doğrula.
     */
    public function test_outbox_terminal_exhausted_stops_retrying(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $store = $this->createStore($user);

        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'trendyol',
            'name' => 'Trendyol Soru-Cevap',
            'status' => 'active',
            'is_enabled' => true,
        ]);

        $conversation = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'trendyol_conv_1',
            'store_id' => $store->id,
            'source_type' => 'trendyol',
            'status' => 'open',
        ]);

        $message = SupportMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'sender_type' => 'agent',
            'message_type' => 'text',
            'body_encrypted' => 'Merhaba',
            'delivery_status' => 'sending',
        ]);

        \Illuminate\Support\Facades\Config::set('customer-care.enabled', true);
        $this->createMockChannelManager(false, 'Fatal API Failure');

        $outboxService = new SupportOutboxService();
        $dispatch = $outboxService->enqueue($message, 'idemp-terminal-test');

        // 5 kez başarısız deneme yaptıralım
        for ($i = 0; $i < 5; $i++) {
            $outboxService->sendDispatch($dispatch);
            $dispatch = $dispatch->fresh();
        }

        $this->assertEquals('exhausted', $dispatch->status);
        $this->assertEquals(5, $dispatch->attempt_count);
        $this->assertNull($dispatch->retry_at);
        $this->assertEquals('failed', $message->fresh()->delivery_status);

        // processPendingDispatches artık bu kaydı tekrar seçmemeli (Sonsuz döngü engellendi)
        $outboxService->processPendingDispatches();
        $this->assertEquals('exhausted', $dispatch->fresh()->status);
    }

    /**
     * Takılı kalan stale sending kayıtlarının otomatik kurtarılmasını doğrula.
     */
    public function test_outbox_stale_sending_recovery(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $store = $this->createStore($user);

        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'trendyol',
            'name' => 'Trendyol Soru-Cevap',
            'status' => 'active',
            'is_enabled' => true,
        ]);

        $conversation = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'trendyol_conv_1',
            'store_id' => $store->id,
            'source_type' => 'trendyol',
            'status' => 'open',
        ]);

        $message = SupportMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'sender_type' => 'agent',
            'message_type' => 'text',
            'body_encrypted' => 'Merhaba',
            'delivery_status' => 'sending',
        ]);

        $outboxService = new SupportOutboxService();
        $dispatch = $outboxService->enqueue($message, 'idemp-stale-test');

        // Durumu el ile 'sending' yapıp güncellenme tarihini 10 dakika geriye çekelim
        $dispatch->status = 'sending';
        $dispatch->updated_at = now()->subMinutes(10);
        $dispatch->save(['timestamps' => false]);

        // processPendingDispatches stale'i kurtarmalı
        $outboxService->processPendingDispatches();

        // Stale kaydı kurtarılıp yeniden denendiği (ve default mock olmadığı için başarısız olup failed durumuna geçtiği) için status 'failed' olmalı
        $this->assertEquals('failed', $dispatch->fresh()->status);
    }

    /**
     * WhatsApp gerçek WaOutbox şeması ile entegrasyon testi.
     */
    public function test_whatsapp_adapter_real_wa_outbox_integration(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $store = $this->createStore($user);

        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'whatsapp',
            'name' => 'WhatsApp',
            'status' => 'active',
            'is_enabled' => true,
        ]);

        // Gerekli WhatsApp ön koşul modelleri
        $contact = WaContact::create([
            'store_id' => $store->id,
            'phone_e164_encrypted' => '+905321112233',
            'phone_hash' => WaContact::hashPhone('+905321112233'),
            'first_name' => 'Ahmet',
        ]);

        $waConv = WaConversation::create([
            'contact_id' => $contact->id,
            'store_id' => $store->id,
            'status' => 'open',
        ]);

        // Dalga S: Consent zorunlu — consent event ekle
        \App\Models\WaConsentEvent::create([
            'contact_id' => $contact->id,
            'store_id' => $store->id,
            'purpose' => 'support',
            'action' => 'granted',
            'source' => 'test',
            'consent_timestamp' => now(),
        ]);

        $conversation = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'wa_' . $waConv->id,
            'store_id' => $store->id,
            'source_type' => 'whatsapp',
            'status' => 'open',
        ]);

        $adapter = new WhatsAppSupportChannelAdapter();

        // 1. Gönderim yapalım
        $result = $adapter->sendReply($channel, 'wa_' . $waConv->id, 'Merhaba Ahmet', 'test-wa-idemp-1');

        $this->assertTrue($result['success']);
        $this->assertStringStartsWith('wa_outbox_', $result['channel_message_id']);

        // wa_outbox tablosuna gerçekten kayıt yazılmış mı?
        $this->assertEquals(1, WaOutbox::count());
        $waOutbox = WaOutbox::first();
        $this->assertEquals('Merhaba Ahmet', $waOutbox->body_text);
        $this->assertEquals('test-wa-idemp-1', $waOutbox->idempotency_key);
        $this->assertEquals('accepted', $adapter->getOutboundTargetStatus());

        // 2. İkinci kez aynı idempotency_key ile gönderim (Çift kayıt engellenmeli)
        $result2 = $adapter->sendReply($channel, 'wa_' . $waConv->id, 'Merhaba Ahmet', 'test-wa-idemp-1');
        $this->assertTrue($result2['success']);
        $this->assertEquals(1, WaOutbox::count()); // Kayıt sayısı artmamalı
    }

    /**
     * Dispatch, conversation, channel ve message arasındaki tenant/mağaza uyumsuzluklarının (IDOR)
     * worker aşamasında tespit edilip engellendiğini (fail-closed) doğrula.
     */
    public function test_worker_detects_and_blocks_integrity_mismatch_idor(): void
    {
        $user1 = User::factory()->create(['is_active' => true]);
        $user2 = User::factory()->create(['is_active' => true]);

        $store1 = $this->createStore($user1);
        $store2 = $this->createStore($user2);

        $channel1 = SupportChannel::create([
            'store_id' => $store1->id,
            'key' => 'trendyol',
            'name' => 'Store 1 Channel',
            'status' => 'active',
            'is_enabled' => true,
        ]);

        // Manipüle edilmiş veri: Conversation Store 2'ye ait ama channel1 (Store 1) bağlı!
        $conversation = SupportConversation::create([
            'support_channel_id' => $channel1->id,
            'external_conversation_id' => 'trendyol_conv_integrity',
            'store_id' => $store2->id, // Mismatch!
            'source_type' => 'trendyol',
            'status' => 'open',
        ]);

        $message = SupportMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'sender_type' => 'agent',
            'message_type' => 'text',
            'body_encrypted' => 'Sızma Denemesi',
            'delivery_status' => 'sending',
        ]);

        \Illuminate\Support\Facades\Config::set('customer-care.enabled', true);
        $outboxService = new SupportOutboxService();

        // Hatalı mağaza kimlikleriyle dispatch kuyruğuna ekle
        $dispatch = SupportDispatch::create([
            'support_channel_id' => $channel1->id,
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'idempotency_key' => 'mismatch-key-' . uniqid(),
            'status' => 'pending',
            'attempt_count' => 0,
            'retry_at' => now(),
        ]);

        $result = $outboxService->sendDispatch($dispatch);

        $this->assertFalse($result);
        $this->assertEquals('failed', $dispatch->fresh()->status);
        $this->assertStringContainsString('Integrity breach', $dispatch->fresh()->last_error);
    }

    /**
     * Zaten başarıyla gönderilmiş (sent/accepted) dispatch'lerin yeniden gönderilmesinin engellendiğini doğrula.
     */
    public function test_outbox_prevents_retransmission_of_final_states(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $store = $this->createStore($user);
        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'trendyol',
            'name' => 'Trendyol Channel',
            'status' => 'active',
            'is_enabled' => true,
        ]);

        $conversation = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'trendyol_conv_retransmit',
            'store_id' => $store->id,
            'source_type' => 'trendyol',
            'status' => 'open',
        ]);

        $message = SupportMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'sender_type' => 'agent',
            'message_type' => 'text',
            'body_encrypted' => 'Test',
            'delivery_status' => 'sent',
        ]);

        $dispatch = SupportDispatch::create([
            'support_channel_id' => $channel->id,
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'idempotency_key' => 'retransmit-key-' . uniqid(),
            'status' => 'sent', // Zaten nihai gönderildi durumu
            'attempt_count' => 1,
        ]);

        \Illuminate\Support\Facades\Config::set('customer-care.enabled', true);
        $outboxService = new SupportOutboxService();

        $result = $outboxService->sendDispatch($dispatch);

        $this->assertFalse($result, 'Zaten gönderilmiş dispatch yeniden işlenmemeli.');
        $this->assertEquals('sent', $dispatch->fresh()->status); // Durumu değişmemeli
    }

    public function test_outbox_gate_block_marks_ai_message_failed_or_cancelled(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $store = $this->createStore($user);
        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'trendyol',
            'name' => 'Trendyol Channel',
            'status' => 'active',
            'is_enabled' => true,
        ]);

        $conversation = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'trendyol_conv_gate_fail',
            'store_id' => $store->id,
            'source_type' => 'trendyol',
            'status' => 'open',
            'ai_mode' => 'automatic',
            'ownership_status' => 'ai',
        ]);

        $message = SupportMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'sender_type' => 'ai',
            'message_type' => 'text',
            'body_encrypted' => 'Merhaba, nasılsınız?',
            'delivery_status' => 'sending',
        ]);

        \App\Models\SupportAiRun::create([
            'store_id' => $store->id,
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'confidence_score' => 90,
            'sources_used_json' => [[
                'type' => 'policy_validation', 'name' => 'Test policy',
                'record_id' => 'policy:test', 'version' => 'test',
                'freshness_at' => now()->toIso8601String(), 'is_stale' => false,
            ]],
            'status' => 'automatic_approved',
        ]);

        $dispatch = SupportDispatch::create([
            'support_channel_id' => $channel->id,
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'idempotency_key' => 'gate-fail-key-' . uniqid(),
            'status' => 'pending',
            'attempt_count' => 0,
        ]);

        // Force fail-closed by setting limit = 0
        \Illuminate\Support\Facades\Config::set('customer-care.enabled', true);
        \Illuminate\Support\Facades\Config::set('customer-care.auto_reply_enabled', true);
        \Illuminate\Support\Facades\Config::set('customer-care.auto_reply_max_per_hour', 0);
        \Illuminate\Support\Facades\Config::set('customer-care.pilot_store_allowlist', [$store->id]);
        $this->seedPassEval($store->id);

        $outboxService = new SupportOutboxService();
        $result = $outboxService->sendDispatch($dispatch);

        $this->assertFalse($result);
        $this->assertEquals('failed', $dispatch->fresh()->status);
        $this->assertEquals('failed', $message->fresh()->delivery_status);
        $this->assertStringContainsString('Rate Limit Fail-Closed', $dispatch->fresh()->last_error);
    }

    public function test_outbox_gate_uses_original_ai_confidence_or_decision_context(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $store = $this->createStore($user);
        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'trendyol',
            'name' => 'Trendyol Channel',
            'status' => 'active',
            'is_enabled' => true,
        ]);

        $conversation = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'trendyol_conv_confidence_gate',
            'store_id' => $store->id,
            'source_type' => 'trendyol',
            'status' => 'open',
            'ai_mode' => 'automatic',
            'ownership_status' => 'ai',
        ]);

        $message = SupportMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'sender_type' => 'ai',
            'message_type' => 'text',
            'body_encrypted' => 'Merhaba, düşük güven skoru testi.',
            'delivery_status' => 'sending',
        ]);

        // Create corresponding AI run with score = 75 (which is below automation threshold 80)
        \App\Models\SupportAiRun::create([
            'store_id' => $store->id,
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'confidence_score' => 75,
            'sources_used_json' => [[
                'type' => 'policy_validation', 'name' => 'Test policy',
                'record_id' => 'policy:test', 'version' => 'test',
                'freshness_at' => now()->toIso8601String(), 'is_stale' => false,
            ]],
            'status' => 'completed',
        ]);

        $dispatch = SupportDispatch::create([
            'support_channel_id' => $channel->id,
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'idempotency_key' => 'conf-gate-key-' . uniqid(),
            'status' => 'pending',
            'attempt_count' => 0,
        ]);

        \Illuminate\Support\Facades\Config::set('customer-care.enabled', true);
        \Illuminate\Support\Facades\Config::set('customer-care.auto_reply_enabled', true);
        \Illuminate\Support\Facades\Config::set('customer-care.auto_reply_max_per_hour', 100);
        \Illuminate\Support\Facades\Config::set('customer-care.pilot_store_allowlist', [$store->id]);
        $this->seedPassEval($store->id);

        $outboxService = new SupportOutboxService();
        $result = $outboxService->sendDispatch($dispatch);

        $this->assertFalse($result);
        $this->assertEquals('failed', $dispatch->fresh()->status);
        $this->assertEquals('failed', $message->fresh()->delivery_status);
        $this->assertStringContainsString('Confidence Threshold', $dispatch->fresh()->last_error);
    }
}
