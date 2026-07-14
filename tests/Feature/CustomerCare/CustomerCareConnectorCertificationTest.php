<?php

namespace Tests\Feature\CustomerCare;

use Tests\TestCase;
use App\Models\User;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\SupportChannel;
use App\Models\SupportChannelCapability;
use App\Models\IntegrationConnection;
use App\Models\WaAccount;
use App\Services\Support\CustomerCareConnectorCertificationService;
use App\Services\Support\GoogleBusinessConnectorInterface;
use App\Services\Support\MetaSocialConnectorInterface;
use App\Services\Support\SupportChannelManager;
use App\Services\Support\TenantContext;
use Illuminate\Support\Facades\Config;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class CustomerCareConnectorCertificationTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private MarketplaceStore $store;
    private MarketplaceStore $otherStore;
    private SupportChannel $channel;
    private IntegrationConnection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create(['role' => 'admin', 'is_active' => true]);

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
            'key'        => 'web_chat',
            'name'       => 'Web Chat',
            'status'     => 'active',
            'is_enabled' => true,
        ]);

        $this->connection = IntegrationConnection::create([
            'store_id'       => $this->store->id,
            'status'         => 'active',
            'provider'       => 'web_chat',
            'webhook_secret' => 'super_secret_webhook_salt',
        ]);

        Config::set('customer-care.enabled', true);
        Config::set('customer-care.web_chat_enabled', true);
    }

    #[Test]
    public function certification_route_blocks_when_flag_off(): void
    {
        Config::set('customer-care.connector_certification_enabled', false);

        $response = $this->actingAs($this->adminUser)
            ->get(route('customer-care.certification'));

        $response->assertStatus(404);
    }

    #[Test]
    public function certification_route_renders_when_flag_on(): void
    {
        Config::set('customer-care.connector_certification_enabled', true);

        $response = $this->actingAs($this->adminUser)
            ->get(route('customer-care.certification'));

        $response->assertStatus(200);
    }

    #[Test]
    public function sandbox_web_chat_simulation_verifies_signature_fail_closed(): void
    {
        $service = app(CustomerCareConnectorCertificationService::class);

        // 1. İmzasız istek fail-closed olarak bloklanmalı
        $payloadNoSig = [
            'store_id'  => $this->store->id,
            'raw_json'  => json_encode(['store_id' => $this->store->id, 'idempotency_key' => 'idemp_1']),
        ];

        $res1 = $service->simulateWebhookEvent($this->store->id, 'web_chat', $payloadNoSig, $this->adminUser);
        $this->assertFalse($res1['success']);
        $this->assertStringContainsString('Eksik imza', $res1['message']);

        // 2. Geçersiz imza fail-closed olarak bloklanmalı
        $payloadBadSig = [
            'store_id'  => $this->store->id,
            'raw_json'  => json_encode(['store_id' => $this->store->id, 'idempotency_key' => 'idemp_2']),
            'signature' => 'invalid_signature_value',
        ];

        $res2 = $service->simulateWebhookEvent($this->store->id, 'web_chat', $payloadBadSig, $this->adminUser);
        $this->assertFalse($res2['success']);
        $this->assertStringContainsString('Geçersiz imza', $res2['message']);
    }

    #[Test]
    public function missing_connector_returns_unavailable_capabilities(): void
    {
        // otherStore için bağlantı (connection) yok
        $channelOther = SupportChannel::create([
            'store_id'   => $this->otherStore->id,
            'key'        => 'web_chat',
            'name'       => 'Web Chat B',
            'status'     => 'active',
            'is_enabled' => true,
        ]);

        $adapter = app(\App\Services\Support\WebChatSupportChannelAdapter::class);
        $caps = $adapter->getCapabilities($channelOther);

        // Connector bağlantısı (connection) olmadığı için read ve send capabilities "unavailable" dönmeli
        $sendCap = collect($caps)->firstWhere('capability', 'send_messages');
        $readCap = collect($caps)->firstWhere('capability', 'read_messages');

        $this->assertEquals('unavailable', $sendCap['status']);
        $this->assertEquals('unavailable', $readCap['status']);
    }

    #[Test]
    public function certification_details_mask_secrets_and_pii(): void
    {
        Config::set('customer-care.connector_certification_enabled', true);

        // Plain token içeren bir bağlantı oluşturalım
        $conn = IntegrationConnection::create([
            'store_id'       => $this->otherStore->id,
            'status'         => 'active',
            'provider'       => 'whatsapp',
            'webhook_secret' => 'cc_xyz_plain_token_secret_value_123',
        ]);

        $service = app(CustomerCareConnectorCertificationService::class);
        $run = $service->certifyChannel($this->otherStore->id, 'whatsapp', $this->adminUser);

        // Rapor detaylarında açık sırların maskelendiğini doğrula
        foreach ($run->checks as $check) {
            $this->assertStringNotContainsString('cc_xyz_plain_token_secret_value_123', $check->details);
        }
    }

    #[Test]
    public function inspection_runs_real_checks_without_persisting_certification_rows(): void
    {
        $inspection = app(CustomerCareConnectorCertificationService::class)
            ->inspectChannel($this->store->id, 'web_chat', $this->adminUser);

        $this->assertArrayHasKey('status', $inspection);
        $this->assertCount(6, $inspection['checks']);
        $this->assertSame('connector_health', $inspection['checks'][4]['name']);
        $this->assertDatabaseCount('support_connector_certification_runs', 0);
        $this->assertDatabaseCount('support_connector_certification_checks', 0);
        $this->assertNull($this->channel->fresh()->last_health_check_at);
    }

    #[Test]
    public function certification_accepts_google_business_provider_aliases(): void
    {
        Config::set('customer-care.google_reviews_enabled', true);

        $this->app->instance(GoogleBusinessConnectorInterface::class, new class implements GoogleBusinessConnectorInterface {
            public function reply(string $reviewId, string $message): string
            {
                return 'google-reply-test-id';
            }
        });

        $this->connection->update([
            'status' => 'active',
            'provider' => 'google',
            'auth_type' => 'oauth',
            'credentials_encrypted' => ['access_token' => 'encrypted-token'],
        ]);

        SupportChannel::create([
            'store_id' => $this->store->id,
            'key' => 'google_business',
            'name' => 'Google Business',
            'status' => 'active',
            'is_enabled' => true,
        ]);

        $run = app(CustomerCareConnectorCertificationService::class)
            ->certifyChannel($this->store->id, 'google_business', $this->adminUser);

        $checks = $run->checks()->pluck('status', 'check_name');

        $this->assertSame('pass', $checks['feature_flag_enabled']);
        $this->assertSame('pass', $checks['connector_bound']);
        $this->assertSame('pass', $checks['send_capability']);
        $this->assertSame('pass', $checks['connector_health']);
    }

    #[Test]
    public function meta_social_channel_resolves_and_certifies_with_meta_provider_alias(): void
    {
        Config::set('customer-care.meta_social_enabled', true);

        $this->app->instance(MetaSocialConnectorInterface::class, new class implements MetaSocialConnectorInterface {
            public function send(string $key, string $threadId, string $message): string
            {
                return 'meta-message-test-id';
            }
        });

        $this->connection->update([
            'status' => 'active',
            'provider' => 'meta',
            'auth_type' => 'oauth',
            'credentials_encrypted' => ['access_token' => 'encrypted-token'],
        ]);

        $channel = SupportChannel::create([
            'store_id' => $this->store->id,
            'key' => 'meta_social',
            'name' => 'Meta Social',
            'status' => 'active',
            'is_enabled' => true,
        ]);

        $adapter = app(SupportChannelManager::class)->resolveForChannel($channel);
        $this->assertSame('meta_social', $adapter->key());

        $run = app(CustomerCareConnectorCertificationService::class)
            ->certifyChannel($this->store->id, 'meta_social', $this->adminUser);

        $checks = $run->checks()->pluck('status', 'check_name');

        $this->assertSame('pass', $checks['feature_flag_enabled']);
        $this->assertSame('pass', $checks['connector_bound']);
        $this->assertSame('pass', $checks['send_capability']);
        $this->assertSame('pass', $checks['connector_health']);
    }

    #[Test]
    public function certification_accepts_active_whatsapp_account_as_connector_binding(): void
    {
        $channel = SupportChannel::create([
            'store_id' => $this->store->id,
            'key' => 'whatsapp',
            'name' => 'WhatsApp',
            'status' => 'active',
            'is_enabled' => true,
        ]);

        SupportChannelCapability::create([
            'support_channel_id' => $channel->id,
            'capability' => 'send_messages',
            'status' => 'available',
            'source' => 'test',
            'checked_at' => now(),
        ]);

        WaAccount::create([
            'store_id' => $this->store->id,
            'waba_id' => 'waba_cert_test',
            'phone_number_id' => 'phone_cert_test',
            'display_phone_number' => '+905551112233',
            'access_token_encrypted' => 'encrypted-token',
            'status' => 'active',
            'is_active' => true,
        ]);

        $run = app(CustomerCareConnectorCertificationService::class)
            ->certifyChannel($this->store->id, 'whatsapp', $this->adminUser);

        $checks = $run->checks()->pluck('status', 'check_name');

        $this->assertSame('pass', $checks['feature_flag_enabled']);
        $this->assertSame('pass', $checks['connector_bound']);
        $this->assertSame('pass', $checks['send_capability']);
        $this->assertSame('pass', $checks['connector_health']);
    }
}
