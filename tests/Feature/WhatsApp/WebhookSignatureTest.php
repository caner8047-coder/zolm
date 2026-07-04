<?php

namespace Tests\Feature\WhatsApp;

use Illuminate\Support\Facades\Hash;

class WebhookSignatureTest extends WhatsAppTestCase
{
    public function test_valid_hmac_signature_is_accepted(): void
    {
        $payload = '{"entry":[{"changes":[{"value":{"metadata":{"phone_number_id":"test-phone-number-id"},"statuses":[]}}]}]}';
        $signature = 'sha256=' . hash_hmac('sha256', $payload, 'test-app-secret-key');

        $response = $this->postJson('/api/whatsapp/webhook',
            json_decode($payload, true),
            ['X-Hub-Signature-256' => $signature]
        );

        $response->assertOk();
    }

    public function test_invalid_hmac_signature_returns_403(): void
    {
        $payload = '{"entry":[{"changes":[{"value":{"statuses":[]}}]}]}';
        $signature = 'sha256=invalid-signature-value';

        $response = $this->postJson('/api/whatsapp/webhook',
            json_decode($payload, true),
            ['X-Hub-Signature-256' => $signature]
        );

        $response->assertStatus(403);
    }

    public function test_missing_signature_returns_403_when_secret_configured(): void
    {
        $payload = '{"entry":[{"changes":[{"value":{"statuses":[]}}]}]}';

        $response = $this->postJson('/api/whatsapp/webhook',
            json_decode($payload, true),
            []
        );

        $response->assertStatus(403);
    }
}
