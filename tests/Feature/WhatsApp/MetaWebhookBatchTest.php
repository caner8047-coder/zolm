<?php

namespace Tests\Feature\WhatsApp;

use App\Models\WaWebhookEvent;

class MetaWebhookBatchTest extends WhatsAppTestCase
{
    public function test_batch_with_two_status_events_creates_separate_webhook_records(): void
    {
        $store = $this->createStore();
        $account = $this->createAccount($store);

        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'metadata' => ['phone_number_id' => 'test-phone-number-id'],
                                'statuses' => [
                                    ['id' => 'msg_001', 'status' => 'sent', 'timestamp' => '1234567890'],
                                    ['id' => 'msg_002', 'status' => 'delivered', 'timestamp' => '1234567891'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $rawBody = json_encode($payload);
        $signature = 'sha256=' . hash_hmac('sha256', $rawBody, 'test-app-secret-key');

        $response = $this->postJson('/api/whatsapp/webhook', $payload, [
            'X-Hub-Signature-256' => $signature,
        ]);

        $response->assertOk();

        $this->assertCount(2, WaWebhookEvent::all());

        $events = WaWebhookEvent::all();
        $this->assertEquals('status', $events[0]->event_type);
        $this->assertEquals('status', $events[1]->event_type);
        $this->assertNotEquals($events[0]->provider_event_key, $events[1]->provider_event_key);
    }
}
