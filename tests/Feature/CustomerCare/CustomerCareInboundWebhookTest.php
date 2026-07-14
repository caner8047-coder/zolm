<?php

namespace Tests\Feature\CustomerCare;

use App\Models\IntegrationConnection;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\SupportChannel;
use App\Models\SupportInboundWebhookReceipt;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerCareInboundWebhookTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'customer-care.enabled' => true,
            'customer-care.meta_social_enabled' => true,
            'customer-care.google_reviews_enabled' => true,
            'customer-care.integration_hub_enabled' => true,
        ]);
        $user = User::factory()->create();
        $legal = LegalEntity::create(['user_id' => $user->id, 'name' => 'Webhook Legal', 'tax_number' => '8080808080']);
        $this->store = MarketplaceStore::create([
            'user_id' => $user->id, 'legal_entity_id' => $legal->id, 'marketplace' => 'trendyol',
            'store_name' => 'Webhook Store', 'status' => 'active', 'is_active' => true,
        ]);
    }

    public function test_meta_webhook_requires_signature_rejects_replay_window_and_projects_once(): void
    {
        IntegrationConnection::create([
            'store_id' => $this->store->id, 'provider' => 'meta_social', 'status' => 'active',
            'credentials_encrypted' => ['app_secret' => 'meta-app-secret', 'verify_token' => 'verify-me'],
        ]);
        SupportChannel::create([
            'store_id' => $this->store->id, 'key' => 'instagram', 'name' => 'Instagram',
            'status' => 'active', 'is_enabled' => true,
        ]);
        $this->get('/api/customer-care/webhooks/meta/' . $this->store->id . '?hub_mode=subscribe&hub_verify_token=verify-me&hub_challenge=12345')
            ->assertOk()->assertSee('12345');

        $payload = [
            'object' => 'instagram',
            'entry' => [[
                'time' => now()->timestamp,
                'messaging' => [[
                    'timestamp' => now()->timestamp * 1000,
                    'sender' => ['id' => 'sender_77'],
                    'recipient' => ['id' => 'page_1'],
                    'message' => ['mid' => 'meta-mid-77', 'text' => 'Kargom nerede?'],
                ]],
            ]],
        ];
        $raw = json_encode($payload);
        $url = '/api/customer-care/webhooks/meta/' . $this->store->id;
        $this->withHeader('Content-Type', 'application/json')->postJson($url, $payload)->assertUnauthorized();
        $headers = ['Content-Type' => 'application/json', 'X-Hub-Signature-256' => 'sha256=' . hash_hmac('sha256', $raw, 'meta-app-secret')];
        $this->withHeaders($headers)->postJson($url, $payload)->assertStatus(202);
        $this->withHeaders($headers)->postJson($url, $payload)->assertOk()->assertJsonPath('duplicate', true);
        $this->assertSame(1, SupportMessage::count());
        $this->assertSame(1, SupportInboundWebhookReceipt::count());
        $this->assertNull(SupportMessage::first()->payload_json);

        $stale = $payload;
        $stale['entry'][0]['time'] = now()->subHour()->timestamp;
        $stale['entry'][0]['messaging'][0]['timestamp'] = now()->subHour()->timestamp * 1000;
        $staleRaw = json_encode($stale);
        $this->withHeaders(['Content-Type' => 'application/json', 'X-Hub-Signature-256' => 'sha256=' . hash_hmac('sha256', $staleRaw, 'meta-app-secret')])
            ->postJson($url, $stale)->assertStatus(409);
    }

    public function test_generic_inbound_contract_is_hmac_timestamp_and_idempotency_protected(): void
    {
        IntegrationConnection::create([
            'store_id' => $this->store->id, 'provider' => 'crm', 'status' => 'active',
            'webhook_secret' => 'crm-webhook-secret', 'credentials_encrypted' => ['access_token' => 'token'],
            'api_base_url' => 'https://crm.example.test',
        ]);
        $payload = ['event_id' => 'crm-event-100', 'type' => 'contact.updated', 'email' => 'person@example.test'];
        $raw = json_encode($payload);
        $timestamp = now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp . '.' . $raw, 'crm-webhook-secret');
        $url = '/api/customer-care/webhooks/crm/' . $this->store->id;
        $headers = ['Content-Type' => 'application/json', 'X-Zolm-Timestamp' => (string) $timestamp, 'X-Zolm-Signature' => $signature];
        $this->withHeaders($headers)->postJson($url, $payload)->assertStatus(202);
        $this->withHeaders($headers)->postJson($url, $payload)->assertOk()->assertJsonPath('duplicate', true);
        $this->assertDatabaseHas('support_integration_events', ['store_id' => $this->store->id, 'event_type' => 'crm.inbound']);
        $eventPayload = \App\Models\SupportIntegrationEvent::firstOrFail()->payload_json;
        $this->assertStringNotContainsString('person@example.test', json_encode($eventPayload));
    }

    public function test_inbound_webhook_does_not_process_when_master_feature_is_disabled(): void
    {
        IntegrationConnection::create([
            'store_id' => $this->store->id,
            'provider' => 'crm',
            'status' => 'active',
            'webhook_secret' => 'crm-webhook-secret',
        ]);
        config(['customer-care.enabled' => false]);

        $payload = ['event_id' => 'disabled-event'];
        $raw = json_encode($payload);
        $timestamp = now()->timestamp;

        $this->withHeaders([
            'Content-Type' => 'application/json',
            'X-Zolm-Timestamp' => (string) $timestamp,
            'X-Zolm-Signature' => hash_hmac('sha256', $timestamp . '.' . $raw, 'crm-webhook-secret'),
        ])->postJson('/api/customer-care/webhooks/crm/' . $this->store->id, $payload)
            ->assertNotFound();

        $this->assertDatabaseCount('support_inbound_webhook_receipts', 0);
    }
}
