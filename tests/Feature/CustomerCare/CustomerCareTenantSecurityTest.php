<?php

namespace Tests\Feature\CustomerCare;

use App\Models\User;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\SupportChannel;
use App\Models\SupportConversation;
use App\Models\MarketplaceQuestion;
use App\Models\SupportOrganizationMembership;
use App\Services\Support\TenantContext;
use App\Services\Support\KnowledgeBaseService;
use App\Services\Support\BrandVoiceService;
use App\Services\Support\TrendyolSupportChannelAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class CustomerCareTenantSecurityTest extends TestCase
{
    use RefreshDatabase;
    use CustomerCareTestHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupSystemActor();
    }

    private function createStore(User $user, string $name, string $code): MarketplaceStore
    {
        $legalEntity = LegalEntity::create([
            'user_id' => $user->id,
            'name' => $name . ' Company',
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
     * Kullanıcının kendi mağazasına erişebildiğini, ancak başka kullanıcının mağazasına erişemediğini doğrula.
     */
    public function test_user_cannot_access_another_users_store(): void
    {
        $user1 = User::factory()->create(['is_active' => true]);
        $user2 = User::factory()->create(['is_active' => true]);

        $store1 = $this->createStore($user1, 'Store 1', 'ST1');
        $store2 = $this->createStore($user2, 'Store 2', 'ST2');

        // User 1 kendi mağazasına erişebilmeli
        $this->assertTrue(TenantContext::validateStoreAccess($store1->id, $user1));

        // User 1, User 2'nin mağazasına erişememeli (Negatif Test)
        $this->assertFalse(TenantContext::validateStoreAccess($store2->id, $user1));

        // enforceStoreAccess istisna fırlatmalı
        $this->expectException(AuthorizationException::class);
        TenantContext::enforceStoreAccess($store2->id, $user1);
    }

    public function test_organization_member_can_access_store_through_shared_tenant_context(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $member = User::factory()->create(['is_active' => true, 'role' => 'operator']);
        $store = $this->createStore($owner, 'Member Store', 'MEM');

        SupportOrganizationMembership::create([
            'legal_entity_id' => $store->legal_entity_id,
            'user_id' => $member->id,
            'role' => 'member',
        ]);

        $this->assertTrue(TenantContext::validateStoreAccess($store->id, $member));
    }

    /**
     * Kullanıcının başka bir mağazaya ait konuşmaya erişemediğini doğrula.
     */
    public function test_user_cannot_access_another_users_conversation(): void
    {
        $user1 = User::factory()->create(['is_active' => true]);
        $user2 = User::factory()->create(['is_active' => true]);

        $store1 = $this->createStore($user1, 'Store 1', 'ST1');
        $store2 = $this->createStore($user2, 'Store 2', 'ST2');

        $channel1 = SupportChannel::create([
            'store_id' => $store1->id,
            'key' => 'trendyol',
            'name' => 'Trendyol Channel 1',
            'status' => 'active',
            'is_enabled' => true,
        ]);

        $channel2 = SupportChannel::create([
            'store_id' => $store2->id,
            'key' => 'trendyol',
            'name' => 'Trendyol Channel 2',
            'status' => 'active',
            'is_enabled' => true,
        ]);

        $conversation1 = SupportConversation::create([
            'support_channel_id' => $channel1->id,
            'external_conversation_id' => 'trendyol_conv_1',
            'store_id' => $store1->id,
            'source_type' => 'trendyol',
            'status' => 'open',
        ]);

        $conversation2 = SupportConversation::create([
            'support_channel_id' => $channel2->id,
            'external_conversation_id' => 'trendyol_conv_2',
            'store_id' => $store2->id,
            'source_type' => 'trendyol',
            'status' => 'open',
        ]);

        // User 1 kendi konuşmasına erişebilmeli
        $this->assertTrue(TenantContext::validateConversationAccess($conversation1->id, $user1));

        // User 1, User 2'nin konuşmasına erişememeli (Negatif Test)
        $this->assertFalse(TenantContext::validateConversationAccess($conversation2->id, $user1));

        // enforceConversationAccess istisna fırlatmalı
        $this->expectException(AuthorizationException::class);
        TenantContext::enforceConversationAccess($conversation2->id, $user1);
    }

    /**
     * Trendyol adapter'ın cross-tenant question ID manipülasyonunu (IDOR) engellediğini doğrula.
     * Kötü niyetli bir kullanıcı, başka mağazanın soru ID'sini hedefleyen external ID göndermemelidir.
     */
    public function test_trendyol_adapter_blocks_cross_tenant_question_idor(): void
    {
        $user1 = User::factory()->create(['is_active' => true]);
        $user2 = User::factory()->create(['is_active' => true]);

        $store1 = $this->createStore($user1, 'Store 1', 'ST1');
        $store2 = $this->createStore($user2, 'Store 2', 'ST2');

        $channel1 = SupportChannel::create([
            'store_id' => $store1->id,
            'key' => 'trendyol',
            'name' => 'Trendyol Channel',
            'status' => 'active',
            'is_enabled' => true,
        ]);

        // Store 2'ye ait bir MarketplaceQuestion oluştur
        $question = MarketplaceQuestion::create([
            'store_id' => $store2->id,
            'marketplace' => 'trendyol',
            'external_question_id' => 'ty_q_999',
            'question_text' => 'Ürün kaliteli mi?',
            'status' => 'pending',
        ]);

        // Channel 1 (Store 1) üzerinden Store 2'nin sorusuna yanıt göndermeye çalış
        $adapter = new TrendyolSupportChannelAdapter();
        $result = $adapter->sendReply(
            $channel1,
            'trendyol_questions_' . $question->id,
            'Yanıt denemesi'
        );

        // Cross-tenant IDOR koruması: sendReply başarısız ve güvenli hata mesajı dönmeli
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('mağazaya ait değil', $result['message']);
    }

    /**
     * Manipüle edilmiş external_conversation_id formatının reddedildiğini doğrula.
     */
    public function test_trendyol_adapter_rejects_malformed_external_id(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $store = $this->createStore($user, 'Store', 'STR');

        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'trendyol',
            'name' => 'Trendyol',
            'status' => 'active',
            'is_enabled' => true,
        ]);

        $adapter = new TrendyolSupportChannelAdapter();

        // Path traversal ve injection denemeleri
        $malformedIds = [
            'trendyol_questions_../../../admin',
            'trendyol_questions_1; DROP TABLE marketplace_questions',
            '../../questions/1',
            'arbitrary_format',
        ];

        foreach ($malformedIds as $malformedId) {
            $result = $adapter->sendReply($channel, $malformedId, 'Test yanıtı');
            $this->assertFalse($result['success'], "ID '$malformedId' reddedilmeli ama kabul edildi.");
        }
    }

    /**
     * WhatsApp adapter'ın başka bir mağazaya ait konuşmaya yazmayı engellediğini doğrula (Cross-Tenant IDOR engeli).
     */
    public function test_whatsapp_adapter_blocks_cross_tenant_conversation_idor(): void
    {
        $user1 = User::factory()->create(['is_active' => true]);
        $user2 = User::factory()->create(['is_active' => true]);

        $store1 = $this->createStore($user1, 'Store 1', 'ST1');
        $store2 = $this->createStore($user2, 'Store 2', 'ST2');

        $channel1 = SupportChannel::create([
            'store_id' => $store1->id,
            'key' => 'whatsapp',
            'name' => 'WhatsApp Channel 1',
            'status' => 'active',
            'is_enabled' => true,
        ]);

        // Store 2'ye ait bir WaConversation ve WaContact oluştur
        $contact2 = \App\Models\WaContact::create([
            'store_id' => $store2->id,
            'phone_e164_encrypted' => '905554443322',
            'phone_hash' => \App\Models\WaContact::hashPhone('905554443322'),
            'first_name' => 'John',
            'is_active' => true,
        ]);

        $waConv2 = \App\Models\WaConversation::create([
            'store_id' => $store2->id,
            'contact_id' => $contact2->id,
            'external_conversation_id' => 'wa_conv_2',
            'status' => 'open',
        ]);

        $adapter = new \App\Services\Support\WhatsAppSupportChannelAdapter();

        // Channel 1 (Store 1) ile Store 2'nin WhatsApp konuşmasına yazmaya çalış (Cross-Tenant)
        $result = $adapter->sendReply(
            $channel1,
            'wa_' . $waConv2->id,
            'Yasaklı Mesaj'
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('bu mağazaya ait değil', $result['message']);
        $this->assertEquals(0, \App\Models\WaOutbox::count());
    }

    /**
     * User context'i bulunmayan ve store/channel çözülemeyen yetkisiz erişimlerin fail-closed olduğunu doğrula.
     */
    public function test_knowledge_and_brand_voice_fails_closed_without_actor(): void
    {
        $kbService = new KnowledgeBaseService();

        // Olmayan store_id için arama yapmayı dene (ve auth/user da yok, store sahibi de yok)
        $this->expectException(AuthorizationException::class);
        $kbService->searchArticles(9999, 'iade');
    }

    /**
     * Parent (Conversation/Store) silme girişimlerinin, child dispatch/AI run ledger kayıtları varken
     * veritabanı yabancı anahtar kısıtı (restrictOnDelete) ile engellendiğini doğrula (Audit Retention).
     */
    public function test_parent_deletion_fails_when_child_ledger_exists(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $store = $this->createStore($user, 'Store Test', 'STT');

        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'trendyol',
            'name' => 'Trendyol Soru-Cevap',
            'status' => 'active',
            'is_enabled' => true,
        ]);

        $conversation = SupportConversation::create([
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'trendyol_conv_restrict_test',
            'store_id' => $store->id,
            'source_type' => 'trendyol',
            'status' => 'open',
        ]);

        // support_ai_runs kaydı ekle (conversation_id'ye bağlı)
        \App\Models\SupportAiRun::create([
            'store_id' => $store->id,
            'conversation_id' => $conversation->id,
            'prompt_template_key' => 'test',
            'prompt_raw' => 'Soru',
            'response_raw' => 'Cevap',
            'status' => 'success',
        ]);

        // Restrict kontrolü: Conversation silinmeye çalışıldığında QueryException fırlatılmalı
        $this->expectException(\Illuminate\Database\QueryException::class);
        $conversation->delete();
    }

    /**
     * CLI/worker gibi explicit system actor bağlamında, config e-postasına sahip
     * kullanıcının tüm tenant verilerine yetkili olduğunu doğrula.
     */
    public function test_system_actor_resolves_via_config_and_has_full_tenant_access(): void
    {
        // 1. Config'i ayarla
        \Illuminate\Support\Facades\Config::set('customer-care.system_actor_email', 'system-job-worker@zolm.com');

        // Varsa eskiyi temizle
        User::where('email', 'system-job-worker@zolm.com')->delete();

        // System actor provision edilmemişse getSystemActor() istisna fırlatmalı (fail-closed)
        try {
            TenantContext::getSystemActor();
            $this->fail('Provision edilmemiş actor hata fırlatmalıydı.');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->assertStringContainsString('not provisioned or active', $e->getMessage());
        }

        // System actor'ı oluştur
        $systemUser = User::factory()->create([
            'email' => 'system-job-worker@zolm.com',
            'role' => 'admin',
            'is_active' => true,
        ]);

        // Artık başarıyla çözülebilmeli
        $resolvedActor = TenantContext::getSystemActor();
        $this->assertEquals($systemUser->id, $resolvedActor->id);

        // System actor herhangi bir store için erişime sahip olmalı (bypass check)
        $userForStore = User::factory()->create();
        $store = $this->createStore($userForStore, 'Tenant Store', 'TSX');

        $this->assertTrue(TenantContext::validateStoreAccess($store->id, $resolvedActor));
    }

    /**
     * PilotDashboard bileşenine yetkisiz bir mağaza seçilerek erişilmek istendiğinde
     * TenantContext ve Livewire render seviyesinde engellendiğini (negatif tenant erişimi) doğrula.
     */
    public function test_pilot_dashboard_rejects_unauthorized_store_selection(): void
    {
        $user1 = User::factory()->create(['is_active' => true]);
        $user2 = User::factory()->create(['is_active' => true]);

        $store1 = $this->createStore($user1, 'My Store', 'MYS');
        $store2 = $this->createStore($user2, 'Other Store', 'OTH');

        // user1 olarak giriş yap
        auth()->login($user1);

        Config::set('customer-care.enabled', true);
        Config::set('customer-care.pilot_dashboard_enabled', true);

        // Livewire testi: SelectedStoreId yetkisiz olarak set edildiğinde forbidden dönmeli
        \Livewire\Livewire::test(\App\Livewire\CustomerCare\PilotDashboard::class)
            ->set('selectedStoreId', $store2->id)
            ->assertForbidden();
    }
}
