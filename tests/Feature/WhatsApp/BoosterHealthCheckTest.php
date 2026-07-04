<?php

namespace Tests\Feature\WhatsApp;

use App\Models\MarketplaceStore;

class BoosterHealthCheckTest extends WhatsAppTestCase
{
    private function signPayload(string $payload, string $secret): string
    {
        return 'sha256=' . hash_hmac('sha256', $payload, $secret);
    }

    private function sendHealthCheck(int $storeId, string $secret): \Illuminate\Testing\TestResponse
    {
        $payload = json_encode(['event_type' => 'health.check', 'store_id' => $storeId]);
        $timestamp = (string) time();
        $signature = $this->signPayload($payload, $secret);

        return $this->postJson('/api/whatsapp/booster/event', json_decode($payload, true), [
            'Content-Type' => 'application/json',
            'X-ZOLM-Event-ID' => 'health-' . $storeId . '-' . $timestamp,
            'X-ZOLM-Event-Type' => 'health.check',
            'X-ZOLM-Timestamp' => $timestamp,
            'X-ZOLM-Signature' => $signature,
            'X-ZOLM-Store-ID' => (string) $storeId,
            'X-ZOLM-Version' => '1.0',
        ]);
    }

    public function test_valid_signature_wc_store_returns_ok(): void
    {
        $store = $this->createStore('woocommerce');
        $secret = config('app.key');

        $response = $this->sendHealthCheck($store->id, $secret);

        $response->assertOk();
        $response->assertJson(['ok' => true, 'store_id' => $store->id]);
    }

    public function test_wrong_signature_returns_403(): void
    {
        $store = $this->createStore('woocommerce');
        $secret = config('app.key');

        $payload = json_encode(['event_type' => 'health.check', 'store_id' => $store->id]);
        $timestamp = (string) time();
        $wrongSig = 'sha256=' . hash_hmac('sha256', $payload, 'wrong-secret-key');

        $response = $this->postJson('/api/whatsapp/booster/event', json_decode($payload, true), [
            'Content-Type' => 'application/json',
            'X-ZOLM-Event-ID' => 'health-wrong-sig',
            'X-ZOLM-Event-Type' => 'health.check',
            'X-ZOLM-Timestamp' => $timestamp,
            'X-ZOLM-Signature' => $wrongSig,
            'X-ZOLM-Store-ID' => (string) $store->id,
            'X-ZOLM-Version' => '1.0',
        ]);

        $response->assertStatus(403);
    }

    public function test_store_id_zero_returns_400(): void
    {
        $secret = config('app.key');

        $response = $this->sendHealthCheck(0, $secret);

        $response->assertStatus(400);
    }

    public function test_nonexistent_store_returns_404(): void
    {
        $secret = config('app.key');

        $response = $this->sendHealthCheck(999999, $secret);

        $response->assertStatus(404);
    }

    public function test_non_wc_store_returns_404(): void
    {
        $store = $this->createStore('trendyol');
        $secret = config('app.key');

        $response = $this->sendHealthCheck($store->id, $secret);

        $response->assertStatus(404);
    }

    public function test_health_check_does_not_create_webhook_event(): void
    {
        $store = $this->createStore('woocommerce');
        $secret = config('app.key');

        $beforeCount = \App\Models\WaWebhookEvent::count();

        $this->sendHealthCheck($store->id, $secret);

        $this->assertEquals($beforeCount, \App\Models\WaWebhookEvent::count());
    }

    public function test_health_check_does_not_create_outbox(): void
    {
        $store = $this->createStore('woocommerce');
        $secret = config('app.key');

        $beforeCount = \App\Models\WaOutbox::count();

        $this->sendHealthCheck($store->id, $secret);

        $this->assertEquals($beforeCount, \App\Models\WaOutbox::count());
    }

    public function test_health_check_does_not_create_contacts(): void
    {
        $store = $this->createStore('woocommerce');
        $secret = config('app.key');

        $beforeCount = \App\Models\WaContact::count();

        $this->sendHealthCheck($store->id, $secret);

        $this->assertEquals($beforeCount, \App\Models\WaContact::count());
    }
}
