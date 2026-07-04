<?php

namespace Tests\Feature\WhatsApp;

use App\Models\WaWebhookEvent;

class WebhookDuplicateTest extends WhatsAppTestCase
{
    public function test_duplicate_provider_event_key_increments_count(): void
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
                                    ['id' => 'msg_dup_test', 'status' => 'sent', 'timestamp' => '9999999999'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $rawBody = json_encode($payload);
        $signature = 'sha256=' . hash_hmac('sha256', $rawBody, 'test-app-secret-key');

        // İlk gönderim
        $this->postJson('/api/whatsapp/webhook', $payload, ['X-Hub-Signature-256' => $signature])->assertOk();
        $this->assertEquals(1, WaWebhookEvent::count());

        // Tekrar gönderim (aynı payload = aynı provider_event_key)
        $this->postJson('/api/whatsapp/webhook', $payload, ['X-Hub-Signature-256' => $signature])->assertOk();

        // Yeni satır oluşmamalı
        $this->assertEquals(1, WaWebhookEvent::count());
        $this->assertEquals(1, WaWebhookEvent::first()->duplicate_count);
    }

    public function test_duplicate_failed_event_triggers_redelivery(): void
    {
        $store = $this->createStore();
        $account = $this->createAccount($store);

        // Controller'ın ürettiği provider_event_key ile aynı olacak
        $messageId = 'test-redeliver-key';
        $status = 'sent';
        $timestamp = '1234567890';
        $providerEventKey = hash('sha256', $messageId . $status . $timestamp);

        $existingEvent = WaWebhookEvent::create([
            'event_type' => 'status',
            'provider_event_key' => $providerEventKey,
            'source' => 'meta',
            'payload' => ['test' => true],
            'status' => WaWebhookEvent::STATUS_FAILED,
        ]);

        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'metadata' => ['phone_number_id' => 'test-phone-number-id'],
                                'statuses' => [
                                    ['id' => $messageId, 'status' => $status, 'timestamp' => $timestamp],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $rawBody = json_encode($payload);
        $signature = 'sha256=' . hash_hmac('sha256', $rawBody, 'test-app-secret-key');

        $this->postJson('/api/whatsapp/webhook', $payload, ['X-Hub-Signature-256' => $signature])->assertOk();

        $existingEvent->refresh();
        // Sync queue olduğu için job hemen çalışır ve status processed olur
        $this->assertEquals(WaWebhookEvent::STATUS_PROCESSED, $existingEvent->status);
        $this->assertEquals(1, $existingEvent->duplicate_count);
    }
}
