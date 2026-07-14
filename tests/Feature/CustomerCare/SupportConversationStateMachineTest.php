<?php

namespace Tests\Feature\CustomerCare;

use App\Models\User;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\SupportChannel;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\SupportDispatch;
use App\Models\SupportAgentAction;
use App\Services\Support\SupportConversationService;
use App\Services\Support\SupportOutboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\TestCase;

class SupportConversationStateMachineTest extends TestCase
{
    use RefreshDatabase;
    use CustomerCareTestHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupSystemActor();
    }

    private function createStore(User $user): MarketplaceStore
    {
        $legalEntity = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Test Company',
            'tax_number' => '1234567890',
            'is_active' => true,
        ]);

        return MarketplaceStore::create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'Test Store',
            'store_code' => 'TST',
            'is_active' => true,
        ]);
    }

    /**
     * Temsilcinin sahiplenme (claim), bırakma (releaseToAi) ve çözüldü (markAsResolved) geçişlerini doğrula.
     */
    public function test_state_transitions_work_correctly(): void
    {
        $user = User::factory()->create(['is_active' => true, 'role' => 'operator']);
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
            'ownership_status' => 'unassigned',
            'version' => 1,
        ]);

        $service = new SupportConversationService();

        // 1. Claim testi (Authorized)
        $this->actingAs($user);
        $successClaim = $service->claim($conversation, $user);
        $this->assertTrue($successClaim);
        $this->assertEquals('human', $conversation->fresh()->ownership_status);
        $this->assertEquals($user->id, $conversation->fresh()->assigned_user_id);
        $this->assertEquals(2, $conversation->fresh()->version);

        // Audit Log kontrolü
        $this->assertEquals(1, SupportAgentAction::where('action', 'claimed')->count());

        // 2. Release to AI testi
        $successRelease = $service->releaseToAi($conversation, $user);
        $this->assertTrue($successRelease);
        $this->assertEquals('ai', $conversation->fresh()->ownership_status);
        $this->assertNull($conversation->fresh()->assigned_user_id);
        $this->assertEquals(3, $conversation->fresh()->version);
        $this->assertEquals(1, SupportAgentAction::where('action', 'released_to_ai')->count());

        // 3. Mark as Resolved testi
        $successResolve = $service->markAsResolved($conversation, $user);
        $this->assertTrue($successResolve);
        $this->assertEquals('resolved', $conversation->fresh()->status);
        $this->assertEquals(4, $conversation->fresh()->version);
        $this->assertEquals(1, SupportAgentAction::where('action', 'resolved')->count());

        // 4. Reopen testi — fresh() ile yenileme gerekli
        $conversation = $conversation->fresh();
        $successReopen = $service->reopen($conversation, $user);
        $this->assertTrue($successReopen);
        $this->assertEquals('open', $conversation->fresh()->status);
        $this->assertEquals(1, SupportAgentAction::where('action', 'reopened')->count());
    }

    /**
     * Optimistic lock (çakışma önleme) korumasını doğrula.
     */
    public function test_optimistic_locking_prevents_concurrent_updates(): void
    {
        $user1 = User::factory()->create(['is_active' => true, 'role' => 'operator']);
        $user2 = User::factory()->create(['is_active' => true, 'role' => 'operator']);
        $store = $this->createStore($user1);

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
            'ownership_status' => 'unassigned',
            'version' => 1,
        ]);

        $service = new SupportConversationService();

        // Instance A tamamen ayrı bir model nesnesi — fresh()
        $instanceA = SupportConversation::find($conversation->id);
        // Instance B de ayrı bir model nesnesi (eşzamanlı ikinci worker)
        $instanceB = SupportConversation::find($conversation->id);

        // İlk model örneği (Instance A) başarılı şekilde sahiplenmeli
        $this->assertTrue($instanceA->claim($user1->id));

        // İkinci model örneği (Instance B) eski versiyonu referans aldığı için güncellenememeli (Lock fail)
        $this->assertFalse($instanceB->claim($user2->id));

        // Veritabanı sahipliği hala User 1'e ait kalmalı
        $this->assertEquals($user1->id, $conversation->fresh()->assigned_user_id);
    }

    /**
     * Yetkisiz kullanıcının claim işlemine engel olunduğunu doğrula (Tenant IDOR).
     */
    public function test_unauthorized_user_cannot_claim_conversation(): void
    {
        $user1 = User::factory()->create(['is_active' => true, 'role' => 'operator']);
        $user2 = User::factory()->create(['is_active' => true, 'role' => 'operator']); // Başka tenant/mağaza kullanıcısı
        $store = $this->createStore($user1);

        // user2'nin bu mağazaya yetkisi yok!
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
            'ownership_status' => 'unassigned',
            'version' => 1,
        ]);

        $service = new SupportConversationService();

        $this->expectException(AuthorizationException::class);
        $service->claim($conversation, $user2);
    }

    /**
     * İnsan kilitli (human ownership) konuşmalara AI mesaj gönderiminin engellendiğini doğrula.
     */
    public function test_human_locked_conversation_blocks_ai_outbound_dispatch(): void
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

        // Sahipliği 'human' (temsilci kilitli) olan konuşma
        $conversation = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'trendyol_conv_locked',
            'store_id' => $store->id,
            'source_type' => 'trendyol',
            'status' => 'open',
            'ownership_status' => 'human',
            'assigned_user_id' => $user->id,
            'version' => 1,
        ]);

        // Yapay zeka tarafından üretilen mesaj (sender_type = ai)
        $message = SupportMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'sender_type' => 'ai',
            'message_type' => 'text',
            'body_encrypted' => 'Yapay zeka yanıtı',
            'delivery_status' => 'sending',
        ]);

        $outboxService = new SupportOutboxService();
        $dispatch = $outboxService->enqueue($message, 'idemp-ai-lock-test');

        // Master bayrağı açık tutalım
        config()->set('customer-care.enabled', true);

        // Gönderimi tetikle
        $success = $outboxService->sendDispatch($dispatch);

        // Kilitli olduğu için gönderim başarısız olmalı ve dispatch iptal edilmeli
        $this->assertFalse($success);
        $this->assertEquals('cancelled', $dispatch->fresh()->status);
        $this->assertStringContainsString('Locked by a human agent', $dispatch->fresh()->last_error);
        $this->assertEquals('cancelled', $message->fresh()->delivery_status);
    }
}
