<?php

namespace Tests\Feature\CustomerCare;

use Tests\TestCase;
use App\Models\User;
use App\Models\MarketplaceStore;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\SupportChannel;
use App\Models\LegalEntity;
use App\Models\SupportApiClient;
use App\Models\SupportApiToken;
use App\Models\SupportCommercialPlan;
use App\Models\SupportCommercialSubscription;
use App\Models\SupportOrganizationMembership;
use App\Services\Support\CustomerCareEnterpriseApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;

class CustomerCareEnterpriseApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected MarketplaceStore $store;
    protected MarketplaceStore $otherStore;
    protected SupportApiClient $apiClient;
    protected SupportConversation $conversation;
    protected SupportChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create(['role' => 'admin', 'email' => 'admin@zolm.com', 'is_active' => true]);

        $le = LegalEntity::create([
            'user_id'      => $this->adminUser->id,
            'name'         => 'Test Org',
            'company_name' => 'Co',
            'tax_office'   => 'Kadikoy',
            'tax_number'   => '1234567890',
            'address'      => 'Istanbul',
        ]);

        $this->store = MarketplaceStore::create([
            'store_name'      => 'Store A',
            'store_key'       => 'store_a',
            'user_id'         => $this->adminUser->id,
            'legal_entity_id' => $le->id,
            'marketplace'     => 'trendyol',
            'is_active'       => true,
        ]);

        $this->otherStore = MarketplaceStore::create([
            'store_name'      => 'Store B',
            'store_key'       => 'store_b',
            'user_id'         => $this->adminUser->id,
            'legal_entity_id' => $le->id,
            'marketplace'     => 'trendyol',
            'is_active'       => true,
        ]);

        $this->channel = SupportChannel::create([
            'store_id'   => $this->store->id,
            'key'        => 'trendyol',
            'name'       => 'Trendyol Channel',
            'status'     => 'active',
            'is_enabled' => true,
        ]);

        \App\Models\IntegrationConnection::create([
            'store_id' => $this->store->id,
            'status'   => 'configured',
            'provider' => 'trendyol',
        ]);

        \App\Models\SupportChannelCapability::create([
            'support_channel_id' => $this->channel->id,
            'capability'         => 'send_messages',
            'status'             => 'available',
            'source'             => 'adapter',
        ]);

        $this->conversation = SupportConversation::create([
            'store_id'                 => $this->store->id,
            'support_channel_id'       => $this->channel->id,
            'external_conversation_id' => 'conv_123',
            'external_customer_id'     => 'cust_123',
            'source_type'              => 'trendyol',
            'status'                   => 'open',
            'ai_mode'                  => 'assisted',
            'version'                  => 1,
        ]);

        $this->apiClient = SupportApiClient::create([
            'legal_entity_id' => $le->id,
            'name'            => 'Test Client',
            'client_id'       => 'cli_test123',
            'is_active'       => true,
        ]);

        // Commercial Subscription (Dalga AS'de required entitlement var)
        $plan = SupportCommercialPlan::create([
            'name'         => 'pro',
            'slug'         => 'pro',
            'entitlements' => ['enterprise_api' => true],
        ]);

        SupportCommercialSubscription::create([
            'store_id' => $this->store->id,
            'plan_id'  => $plan->id,
            'status'   => 'active',
        ]);

        Config::set('customer-care.enabled', true);
        Config::set('customer-care.enterprise_api_enabled', true);
        Config::set('customer-care.commercial_center_enabled', true);
        Config::set('customer-care.system_actor_email', $this->adminUser->email);
    }

    #[Test]
    public function enterprise_api_route_blocks_when_flag_off(): void
    {
        Config::set('customer-care.enterprise_api_enabled', false);

        $response = $this->getJson(route('customer-care.api.conversations', ['store_id' => $this->store->id]));
        $response->assertStatus(404);
    }

    #[Test]
    public function token_hash_verification_works_and_plain_token_is_not_stored(): void
    {
        $service = app(CustomerCareEnterpriseApiService::class);
        $res = $service->createToken($this->apiClient->id, 'erp', ['conversations:read'], [$this->store->id]);

        $plain = $res['plain_token'];
        $token = $res['token'];

        $this->assertStringStartsWith('cc_erp_', $plain);
        $this->assertDatabaseMissing('support_api_tokens', ['token_hash' => $plain]); // plain token DB'de olmamalı
        $this->assertDatabaseHas('support_api_tokens', ['token_hash' => hash('sha256', $plain)]); // hash DB'de olmalı

        // Doğrulama
        $authenticated = $service->authenticateToken($plain);
        $this->assertNotNull($authenticated);
        $this->assertEquals($token->id, $authenticated->id);
    }

    #[Test]
    public function token_creation_rejects_store_scope_outside_client_organization(): void
    {
        $foreignOwner = User::factory()->create(['role' => 'operator', 'is_active' => true]);
        $foreignEntity = LegalEntity::create([
            'user_id'      => $foreignOwner->id,
            'name'         => 'Foreign Org',
            'company_name' => 'Foreign Co',
            'tax_office'   => 'Besiktas',
            'tax_number'   => '9999999999',
            'address'      => 'Istanbul',
        ]);

        $foreignStore = MarketplaceStore::create([
            'store_name'      => 'Foreign Store',
            'store_key'       => 'foreign_store',
            'user_id'         => $foreignOwner->id,
            'legal_entity_id' => $foreignEntity->id,
            'marketplace'     => 'trendyol',
            'is_active'       => true,
        ]);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        app(CustomerCareEnterpriseApiService::class)->createToken(
            $this->apiClient->id,
            'erp',
            ['conversations:read'],
            [$this->store->id, $foreignStore->id]
        );
    }

    #[Test]
    public function token_creation_requires_authorized_actor_and_allowlisted_scope(): void
    {
        $operator = User::factory()->create(['role' => 'operator', 'is_active' => true]);
        SupportOrganizationMembership::create([
            'legal_entity_id' => $this->apiClient->legal_entity_id,
            'user_id' => $operator->id,
            'role' => 'member',
        ]);
        $service = app(CustomerCareEnterpriseApiService::class);

        try {
            $service->createToken(
                $this->apiClient->id,
                'erp',
                ['conversations:read'],
                [$this->store->id],
                30,
                $operator
            );
            $this->fail('Yetkisiz organizasyon üyesi API token üretememeliydi.');
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
            $this->assertDatabaseCount('support_api_tokens', 0);
        }

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $service->createToken(
            $this->apiClient->id,
            'erp',
            ['root:all'],
            [$this->store->id],
            30,
            $this->adminUser
        );
    }

    #[Test]
    public function scope_missing_returns_forbidden(): void
    {
        $service = app(CustomerCareEnterpriseApiService::class);
        $res = $service->createToken($this->apiClient->id, 'erp', ['messages:read'], [$this->store->id]);

        // conversations:read scope'u eksik
        $response = $this->withToken($res['plain_token'])
            ->getJson(route('customer-care.api.conversations', ['store_id' => $this->store->id]));

        $response->assertStatus(403);
    }

    #[Test]
    public function unauthenticated_conversation_probe_is_rejected_before_resource_lookup(): void
    {
        $this->getJson(route('customer-care.api.messages', ['id' => 999999]))
            ->assertStatus(401);

        $this->postJson(route('customer-care.api.reply', ['id' => 999999]), ['body' => 'test'])
            ->assertStatus(401);
    }

    #[Test]
    public function cross_store_conversation_reading_is_blocked(): void
    {
        $service = app(CustomerCareEnterpriseApiService::class);
        // Sadece store->id yetkili token
        $res = $service->createToken($this->apiClient->id, 'erp', ['messages:read'], [$this->store->id]);

        // Yabancı mağazadaki conversation (otherStore'da kanal kurup conv yaratalım)
        $foreignChannel = SupportChannel::create([
            'store_id'   => $this->otherStore->id,
            'key'        => 'trendyol',
            'name'       => 'Trendyol Other Channel',
            'status'     => 'active',
            'is_enabled' => true,
        ]);
        $foreignConv = SupportConversation::create([
            'store_id'                 => $this->otherStore->id,
            'support_channel_id'       => $foreignChannel->id,
            'external_conversation_id' => 'conv_other',
            'external_customer_id'     => 'cust_other',
            'source_type'              => 'trendyol',
            'status'                   => 'open',
            'version'                  => 1,
        ]);

        $response = $this->withToken($res['plain_token'])
            ->getJson(route('customer-care.api.messages', ['id' => $foreignConv->id]));

        $response->assertStatus(404);
    }

    #[Test]
    public function reply_endpoint_policy_violation_does_not_create_dispatch(): void
    {
        $service = app(CustomerCareEnterpriseApiService::class);
        $res = $service->createToken($this->apiClient->id, 'erp', ['replies:create'], [$this->store->id]);

        // Politika ihlali yapan mesaj (trendyol'da yasaklı kelime 'havale' içeriyor)
        $response = $this->withToken($res['plain_token'])
            ->postJson(route('customer-care.api.reply', ['id' => $this->conversation->id]), [
                'body' => 'Lütfen ödemeyi havale ile yapın.',
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('support_dispatches', ['conversation_id' => $this->conversation->id]);
    }

    #[Test]
    public function revoked_token_cannot_access(): void
    {
        $service = app(CustomerCareEnterpriseApiService::class);
        $res = $service->createToken($this->apiClient->id, 'erp', ['conversations:read'], [$this->store->id]);

        // Token revoke et
        $res['token']->update(['revoked_at' => now()]);

        $response = $this->withToken($res['plain_token'])
            ->getJson(route('customer-care.api.conversations', ['store_id' => $this->store->id]));

        $response->assertStatus(401);
    }

    #[Test]
    public function api_access_logs_do_not_leak_pii_or_secrets(): void
    {
        $service = app(CustomerCareEnterpriseApiService::class);
        $res = $service->createToken($this->apiClient->id, 'erp', ['conversations:read'], [$this->store->id]);

        $this->withToken($res['plain_token'])
            ->getJson(route('customer-care.api.conversations', ['store_id' => $this->store->id, 'email' => 'sensitive@data.com']));

        $log = \App\Models\SupportApiAccessLog::first();
        $this->assertNotNull($log);
        $this->assertStringNotContainsString('sensitive@data.com', $log->request_payload_redacted);
        $this->assertStringContainsString('[REDACTED]', $log->request_payload_redacted);
    }

    #[Test]
    public function reply_endpoint_blocks_and_does_not_create_dispatch_when_channel_disabled(): void
    {
        $service = app(CustomerCareEnterpriseApiService::class);
        $res = $service->createToken($this->apiClient->id, 'erp', ['replies:create'], [$this->store->id]);

        // Kanalı deaktif et
        $this->channel->update(['is_enabled' => false]);

        $response = $this->withToken($res['plain_token'])
            ->postJson(route('customer-care.api.reply', ['id' => $this->conversation->id]), [
                'body' => 'Test body',
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('support_dispatches', ['conversation_id' => $this->conversation->id]);
    }

    #[Test]
    public function reply_endpoint_blocks_and_does_not_create_dispatch_when_capability_unavailable(): void
    {
        $service = app(CustomerCareEnterpriseApiService::class);
        $res = $service->createToken($this->apiClient->id, 'erp', ['replies:create'], [$this->store->id]);

        // Capability status'ünü unavailable yapalım
        $cap = \App\Models\SupportChannelCapability::where('support_channel_id', $this->channel->id)->first();
        if ($cap) {
            $cap->update(['status' => 'unavailable']);
        }

        $response = $this->withToken($res['plain_token'])
            ->postJson(route('customer-care.api.reply', ['id' => $this->conversation->id]), [
                'body' => 'Test body',
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('support_dispatches', ['conversation_id' => $this->conversation->id]);
    }

    #[Test]
    public function reply_endpoint_blocks_when_human_ownership_lock_active(): void
    {
        $service = app(CustomerCareEnterpriseApiService::class);
        $res = $service->createToken($this->apiClient->id, 'erp', ['replies:create'], [$this->store->id]);

        // Conversation ownership_status'ü human yapalım
        $this->conversation->update([
            'ownership_status' => 'human',
            'owner_id'         => $this->adminUser->id,
        ]);

        $response = $this->withToken($res['plain_token'])
            ->postJson(route('customer-care.api.reply', ['id' => $this->conversation->id]), [
                'body' => 'Test body',
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('support_dispatches', ['conversation_id' => $this->conversation->id]);
    }

    #[Test]
    public function reply_endpoint_blocks_when_master_kill_switch_disabled(): void
    {
        $service = app(CustomerCareEnterpriseApiService::class);
        $res = $service->createToken($this->apiClient->id, 'erp', ['replies:create'], [$this->store->id]);

        Config::set('customer-care.enabled', false);

        $response = $this->withToken($res['plain_token'])
            ->postJson(route('customer-care.api.reply', ['id' => $this->conversation->id]), [
                'body' => 'Test body',
            ]);

        $response->assertStatus(404);
        $this->assertDatabaseMissing('support_dispatches', ['conversation_id' => $this->conversation->id]);
    }

    #[Test]
    public function successful_reply_creates_exactly_one_message_and_one_dispatch(): void
    {
        $service = app(CustomerCareEnterpriseApiService::class);
        $res = $service->createToken($this->apiClient->id, 'erp', ['replies:create'], [$this->store->id]);

        // Veritabanını temiz bir durum için kontrol edelim
        $initialMessages = \App\Models\SupportMessage::count();
        $initialDispatches = \App\Models\SupportDispatch::count();

        $response = $this->withToken($res['plain_token'])
            ->postJson(route('customer-care.api.reply', ['id' => $this->conversation->id]), [
                'body' => 'Standard clear message',
            ]);

        $response->assertStatus(200);

        // Tam olarak 1 adet yeni mesaj ve 1 adet dispatch oluşturulmuş olmalı
        $this->assertEquals($initialMessages + 1, \App\Models\SupportMessage::count());
        $this->assertEquals($initialDispatches + 1, \App\Models\SupportDispatch::count());
    }

    #[Test]
    public function api_response_masks_pii_in_messages_list(): void
    {
        $service = app(CustomerCareEnterpriseApiService::class);
        $res = $service->createToken($this->apiClient->id, 'erp', ['messages:read'], [$this->store->id]);

        // Hassas bilgi içeren mesaj ekle (TCKN)
        SupportMessage::create([
            'conversation_id'          => $this->conversation->id,
            'direction'                => 'inbound',
            'sender_type'              => 'customer',
            'source_type'              => 'trendyol',
            'message_type'             => 'text',
            'body_encrypted'           => 'Merhaba, TCKN: 11223344556',
            'delivery_status'          => 'sent',
            'sent_at'                  => now(),
        ]);

        $response = $this->withToken($res['plain_token'])
            ->getJson(route('customer-care.api.messages', ['id' => $this->conversation->id]));

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertNotEmpty($data);

        // TCKN maskelenmiş olmalı
        $this->assertStringNotContainsString('11223344556', $data[0]['body']);
        $this->assertStringContainsString('11*******56', $data[0]['body']);
    }

    #[Test]
    public function api_response_masks_pii_in_conversations_list_customer_identifier(): void
    {
        $this->conversation->update([
            'external_customer_id' => 'musteri@example.com',
        ]);

        $service = app(CustomerCareEnterpriseApiService::class);
        $res = $service->createToken($this->apiClient->id, 'erp', ['conversations:read'], [$this->store->id]);

        $response = $this->withToken($res['plain_token'])
            ->getJson(route('customer-care.api.conversations', ['store_id' => $this->store->id]));

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertNotEmpty($data);
        $this->assertStringNotContainsString('musteri@example.com', $data[0]['external_customer_id']);
        $this->assertStringContainsString('example.com', $data[0]['external_customer_id']);
    }
}
