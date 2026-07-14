<?php

namespace Tests\Feature\CustomerCare;

use Tests\TestCase;
use App\Models\User;
use App\Models\MarketplaceStore;
use App\Models\SupportChannel;
use App\Models\SupportIntegrationEvent;
use App\Models\SupportIntegrationDelivery;
use App\Services\Support\Integration\CustomerCareIntegrationHubService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class CustomerCareIntegrationHubTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected MarketplaceStore $store;
    protected SupportChannel $webhookChannel;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'customer-care.enabled' => true,
            'customer-care.integration_hub_enabled' => true,
            'customer-care.quality_center_enabled' => true,
            'customer-care.ops_center_enabled' => true,
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

        $this->webhookChannel = SupportChannel::create([
            'store_id' => $this->store->id,
            'key' => 'webhook_outbound',
            'channel_type' => 'webhook',
            'name' => 'Outbound Webhook Hub',
            'is_enabled' => true,
            'config_json' => [
                'webhook_url' => 'https://api.thirdparty.com/webhook-receiver',
                'webhook_secret' => \Illuminate\Support\Facades\Crypt::encryptString('super_secret_signing_key_123'),
            ],
        ]);
    }

    public function test_webhook_dispatches_with_hmac_and_pii_redacted()
    {
        Http::fake([
            'api.thirdparty.com/*' => Http::response(['status' => 'ok'], 200),
        ]);

        $data = [
            'customer_name' => 'Caner Ramazan Önal',
            'customer_phone' => '05554443322',
            'message_body' => 'Merhaba, bu siparişi iptal etmek istiyorum.',
        ];

        $service = app(CustomerCareIntegrationHubService::class);
        $event = $service->dispatchEvent($this->store->id, 'message.received', $data);
        Http::assertNothingSent();
        $this->assertDatabaseHas('support_integration_deliveries', [
            'support_integration_event_id' => $event->id,
            'status' => 'pending',
            'attempts' => 0,
        ]);
        $service->processPending();

        $this->assertNotNull($event);
        $this->assertDatabaseHas('support_integration_events', [
            'store_id' => $this->store->id,
            'event_type' => 'message.received',
        ]);

        // Verify PII is redacted in payload_json
        $payload = $event->payload_json;
        $this->assertStringNotContainsString('Caner Ramazan', json_encode($payload));
        $this->assertStringNotContainsString('05554443322', json_encode($payload));

        // Verify HTTP Headers and HMAC signature
        Http::assertSent(function ($request) use ($event) {
            $hasSignature = $request->hasHeader('X-Zolm-Signature');
            $hasTimestamp = $request->hasHeader('X-Zolm-Timestamp');
            $hasEventId = $request->hasHeader('X-Zolm-Event-Id');

            $computed = hash_hmac(
                'sha256',
                $request->header('X-Zolm-Timestamp')[0] . '.' . json_encode($event->payload_json),
                'super_secret_signing_key_123'
            );

            return $hasSignature && $hasTimestamp && $hasEventId && hash_equals($computed, $request->header('X-Zolm-Signature')[0]);
        });
    }

    public function test_webhook_retries_and_falls_to_dead_letter()
    {
        // Force fail delivery
        Http::fake([
            'api.thirdparty.com/*' => Http::response('Server Error', 500),
        ]);

        $service = app(CustomerCareIntegrationHubService::class);

        Log::shouldReceive('alert')->once(); // Expect DLQ terminal alert log

        $event = $service->dispatchEvent($this->store->id, 'message.received', ['test' => 'data']);

        // Olay önce kuyruğa alınır; HTTP çağrısı outbox işleyicisinde yapılır.
        $delivery = SupportIntegrationDelivery::where('support_integration_event_id', $event->id)->first();
        $this->assertNotNull($delivery);
        $this->assertEquals('pending', $delivery->status);
        $this->assertEquals(0, $delivery->attempts);

        $service->deliver($delivery, 'super_secret_signing_key_123'); // attempt 1
        $service->deliver($delivery, 'super_secret_signing_key_123'); // attempt 2
        $service->deliver($delivery, 'super_secret_signing_key_123'); // attempt 3

        $this->assertEquals('dead_letter', $delivery->fresh()->status);
        $this->assertEquals(3, $delivery->fresh()->attempts);
    }

    public function test_integration_route_blocks_when_flag_off()
    {
        $this->actingAs($this->adminUser);
        // 1. Flag off -> 404
        config(['customer-care.integration_hub_enabled' => false]);
        $response = $this->get('/customer-care/integrations');
        $response->assertStatus(404);

        // 2. Flag on, not admin -> 403
        config(['customer-care.integration_hub_enabled' => true]);
        $operator = User::create([
            'name' => 'Operator',
            'email' => 'op@zolm.com',
            'password' => bcrypt('password'),
            'role' => 'operator',
        ]);
        $this->actingAs($operator);
        $response = $this->get('/customer-care/integrations');
        $response->assertStatus(403);

        // 3. Flag on, admin -> 200
        $this->actingAs($this->adminUser);
        $response = $this->get('/customer-care/integrations');
        $response->assertStatus(200);
    }

    public function test_webhook_secret_is_stored_encrypted_in_channel_config()
    {
        $this->actingAs($this->adminUser);

        $component = \Livewire\Livewire::test(\App\Livewire\CustomerCare\Integrations::class);
        $component->set('selectedStoreId', $this->store->id);
        $component->set('webhookUrl', 'https://api.thirdparty.com/new-receiver');
        $component->set('webhookSecret', 'secret_key_to_be_encrypted_999');

        $component->call('saveWebhook');

        // Fetch channel from DB
        $channel = SupportChannel::where('store_id', $this->store->id)
            ->where('key', 'webhook_outbound')
            ->first();

        $this->assertNotNull($channel);
        $storedSecret = $channel->config_json['webhook_secret'];

        // Assert stored secret is NOT plain text
        $this->assertNotEquals('secret_key_to_be_encrypted_999', $storedSecret);

        // Assert stored secret can be decrypted to correct raw secret
        $decrypted = \Illuminate\Support\Facades\Crypt::decryptString($storedSecret);
        $this->assertEquals('secret_key_to_be_encrypted_999', $decrypted);
    }

    public function test_empty_secret_fails_closed_without_http_call()
    {
        Http::fake();

        // Save a webhook channel with empty secret config (simulating manually altered config)
        $this->webhookChannel->update([
            'config_json' => [
                'webhook_url' => 'https://api.thirdparty.com/receiver',
                'webhook_secret' => '', // empty
            ],
        ]);

        $service = app(CustomerCareIntegrationHubService::class);
        $event = $service->dispatchEvent($this->store->id, 'message.received', ['test' => 'data']);

        // Assert nothing was sent over HTTP
        Http::assertNothingSent();

        // Assert delivery status is failed
        $delivery = SupportIntegrationDelivery::where('support_integration_event_id', $event->id)->first();
        $this->assertNotNull($delivery);
        $this->assertEquals('failed', $delivery->status);
        $this->assertStringContainsString('eksik veya boş', $delivery->last_error);
    }

    public function test_retry_delivery_enforces_store_boundaries()
    {
        $this->actingAs($this->adminUser);

        // Setup Store B
        $le2 = \App\Models\LegalEntity::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Store B Corp',
            'tax_number' => '9876543210',
        ]);
        $storeB = MarketplaceStore::create([
            'user_id' => $this->adminUser->id,
            'legal_entity_id' => $le2->id,
            'marketplace' => 'trendyol',
            'store_name' => 'Store B',
            'store_code' => 'ST_B',
            'seller_id' => '1002',
            'status' => 'active',
            'is_active' => true,
        ]);
        $eventB = SupportIntegrationEvent::create([
            'store_id' => $storeB->id,
            'event_id' => (string) \Illuminate\Support\Str::uuid(),
            'event_type' => 'test',
            'payload_json' => [],
            'idempotency_key' => 'idemp_b',
        ]);
        $deliveryB = SupportIntegrationDelivery::create([
            'support_integration_event_id' => $eventB->id,
            'webhook_url' => 'https://api.thirdparty.com/receiver-b',
            'status' => 'failed',
        ]);

        $component = \Livewire\Livewire::test(\App\Livewire\CustomerCare\Integrations::class);
        $component->set('selectedStoreId', $this->store->id); // Store A selected

        // Retry Store B's delivery
        $component->call('retryDelivery', $deliveryB->id);

        $component->assertSet('errorMessage', 'Teslimat kaydı bulunamadı.');
    }

    public function test_db_idempotency_unique_index()
    {
        $uuid1 = (string) \Illuminate\Support\Str::uuid();
        $uuid2 = (string) \Illuminate\Support\Str::uuid();

        // 1. First event
        SupportIntegrationEvent::create([
            'store_id' => $this->store->id,
            'event_id' => $uuid1,
            'event_type' => 'test',
            'payload_json' => [],
            'idempotency_key' => 'same_key',
        ]);

        // 2. Second event with same store_id and same idempotency_key -> QueryException
        $this->expectException(\Illuminate\Database\QueryException::class);

        SupportIntegrationEvent::create([
            'store_id' => $this->store->id,
            'event_id' => $uuid2,
            'event_type' => 'test',
            'payload_json' => [],
            'idempotency_key' => 'same_key',
        ]);
    }

    public function test_plaintext_secret_fails_closed_without_http_call()
    {
        Http::fake();

        $this->webhookChannel->update([
            'config_json' => [
                'webhook_url' => 'https://api.thirdparty.com/receiver',
                'webhook_secret' => 'plaintext_secret_unencrypted',
            ],
        ]);

        $service = app(CustomerCareIntegrationHubService::class);
        $event = $service->dispatchEvent($this->store->id, 'message.received', ['test' => 'data']);

        Http::assertNothingSent();

        $delivery = SupportIntegrationDelivery::where('support_integration_event_id', $event->id)->first();
        $this->assertNotNull($delivery);
        $this->assertEquals('failed', $delivery->status);
        $this->assertStringContainsString('çözülemedi', $delivery->last_error);
    }

    public function test_webhook_delivery_rejects_private_network_destination(): void
    {
        Http::fake();
        $this->webhookChannel->update([
            'config_json' => [
                'webhook_url' => 'https://127.0.0.1/internal-hook',
                'webhook_secret' => \Illuminate\Support\Facades\Crypt::encryptString('private-network-secret'),
            ],
        ]);

        $event = app(CustomerCareIntegrationHubService::class)
            ->dispatchEvent($this->store->id, 'message.received', ['test' => 'data']);
        app(CustomerCareIntegrationHubService::class)->processPending();

        Http::assertNothingSent();
        $delivery = SupportIntegrationDelivery::where('support_integration_event_id', $event->id)->firstOrFail();
        $this->assertSame('failed', $delivery->status);
        $this->assertStringContainsString('Özel/rezerve IP', $delivery->last_error);
    }

    public function test_invalid_encrypted_secret_fails_closed_without_http_call()
    {
        Http::fake();

        $this->webhookChannel->update([
            'config_json' => [
                'webhook_url' => 'https://api.thirdparty.com/receiver',
                'webhook_secret' => 'eyJpdiI6IkFCRUNEM...', // invalid cipher text
            ],
        ]);

        $service = app(CustomerCareIntegrationHubService::class);
        $event = $service->dispatchEvent($this->store->id, 'message.received', ['test' => 'data']);

        Http::assertNothingSent();

        $delivery = SupportIntegrationDelivery::where('support_integration_event_id', $event->id)->first();
        $this->assertNotNull($delivery);
        $this->assertEquals('failed', $delivery->status);
        $this->assertStringContainsString('çözülemedi', $delivery->last_error);
    }

    public function test_webhook_save_prevents_double_encryption()
    {
        $this->actingAs($this->adminUser);

        // 1. Initial save
        $component = \Livewire\Livewire::test(\App\Livewire\CustomerCare\Integrations::class);
        $component->set('selectedStoreId', $this->store->id);
        $component->set('webhookUrl', 'https://api.thirdparty.com/new-receiver');
        $component->set('webhookSecret', 'secret_key_to_be_encrypted_999');
        $component->call('saveWebhook');

        // 2. Secondary save with empty secret (updating URL only)
        $component->set('webhookUrl', 'https://api.thirdparty.com/updated-receiver');
        $component->set('webhookSecret', '');
        $component->call('saveWebhook');

        // Fetch channel
        $channel = SupportChannel::where('store_id', $this->store->id)
            ->where('key', 'webhook_outbound')
            ->first();
        $this->assertNotNull($channel);
        $storedSecret = $channel->config_json['webhook_secret'];

        // Assert it is decryptable to original raw secret (no double encryption)
        $decrypted = \Illuminate\Support\Facades\Crypt::decryptString($storedSecret);
        $this->assertEquals('secret_key_to_be_encrypted_999', $decrypted);
    }

    public function test_retry_delivery_fails_closed_with_invalid_secret()
    {
        $this->actingAs($this->adminUser);

        $event = SupportIntegrationEvent::create([
            'store_id' => $this->store->id,
            'event_id' => (string) \Illuminate\Support\Str::uuid(),
            'event_type' => 'test',
            'payload_json' => [],
            'idempotency_key' => 'idemp_retry_test',
        ]);
        $delivery = SupportIntegrationDelivery::create([
            'support_integration_event_id' => $event->id,
            'webhook_url' => 'https://api.thirdparty.com/receiver',
            'status' => 'failed',
        ]);

        $this->webhookChannel->update([
            'config_json' => [
                'webhook_url' => 'https://api.thirdparty.com/receiver',
                'webhook_secret' => 'plaintext_secret_key', // invalid
            ],
        ]);

        Http::fake();

        $component = \Livewire\Livewire::test(\App\Livewire\CustomerCare\Integrations::class);
        $component->set('selectedStoreId', $this->store->id);
        $component->call('retryDelivery', $delivery->id);

        Http::assertNothingSent();
        $this->assertEquals('failed', $delivery->fresh()->status);
        $this->assertStringContainsString('çözülemedi', $delivery->fresh()->last_error);
    }
}
