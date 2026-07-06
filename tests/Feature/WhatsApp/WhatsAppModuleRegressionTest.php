<?php

namespace Tests\Feature\WhatsApp;

use App\Jobs\WhatsApp\SendWaMessageJob;
use App\Models\WaContact;
use App\Models\WaContactPreference;
use App\Models\WaInboundMessage;
use App\Models\WaMessageDelivery;
use App\Models\WaOutbox;
use App\Models\WaStockWaitlist;
use App\Models\WaSuppression;
use App\Models\WaTrackingLink;
use App\Services\WhatsApp\OutboxService;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsAppModuleRegressionTest extends WhatsAppTestCase
{
    public function test_send_job_uses_direct_store_whatsapp_account_relation(): void
    {
        $store = $this->createStore();
        $this->createAccount($store);
        $contact = $this->createContact($store);

        $outbox = WaOutbox::create([
            'contact_id' => $contact->id,
            'store_id' => $store->id,
            'idempotency_key' => 'send-job-account-relation-test',
            'message_type' => 'template',
            'template_name' => 'order_confirmation',
            'template_language' => 'tr',
            'template_params_json' => ['Test Müşteri'],
            'priority' => 'high',
            'status' => WaOutbox::STATUS_QUEUED,
        ]);

        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.test.sent']],
            ], 200),
        ]);

        SendWaMessageJob::dispatchSync($outbox->id);

        $outbox->refresh();
        $this->assertEquals(WaOutbox::STATUS_SENT, $outbox->status);
        $this->assertEquals('wamid.test.sent', $outbox->meta_message_id);
        $this->assertTrue(WaMessageDelivery::where('meta_message_id', 'wamid.test.sent')->exists());
    }

    public function test_outbox_service_rejects_non_woocommerce_stores(): void
    {
        $store = $this->createStore('trendyol');
        $contact = $this->createContact($store);

        $this->expectException(RuntimeException::class);

        app(OutboxService::class)->enqueue(
            contact: $contact,
            messageType: 'template',
            templateName: 'order_confirmation',
            automationKey: 'order_confirmation',
        );
    }

    public function test_meta_inbound_keeps_metadata_and_stop_keyword_withdraws_preferences(): void
    {
        $store = $this->createStore();
        $this->createAccount($store);

        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'metadata' => ['phone_number_id' => 'test-phone-number-id'],
                                'contacts' => [
                                    ['profile' => ['name' => 'Ayşe Yılmaz']],
                                ],
                                'messages' => [
                                    [
                                        'id' => 'wamid.inbound.stop',
                                        'from' => '905321112233',
                                        'type' => 'text',
                                        'text' => ['body' => 'DUR'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $rawBody = json_encode($payload);
        $signature = 'sha256=' . hash_hmac('sha256', $rawBody, 'test-app-secret-key');

        $this->postJson('/api/whatsapp/webhook', $payload, [
            'X-Hub-Signature-256' => $signature,
        ])->assertOk();

        $contact = WaContact::where('store_id', $store->id)->first();
        $this->assertNotNull($contact);
        $this->assertEquals('Ayşe Yılmaz', $contact->first_name);

        $inbound = WaInboundMessage::where('meta_message_id', 'wamid.inbound.stop')->first();
        $this->assertNotNull($inbound);
        $this->assertEquals('test-phone-number-id', data_get($inbound->payload_json, 'metadata.phone_number_id'));

        $this->assertTrue(WaSuppression::where('contact_id', $contact->id)->where('reason', 'opted_out')->exists());

        foreach (['marketing', 'cart_recovery', 'stock_alert', 'birthday'] as $purpose) {
            $this->assertEquals(
                'withdrawn',
                WaContactPreference::where('contact_id', $contact->id)->where('purpose', $purpose)->value('status')
            );
        }
    }

    public function test_booster_stock_waitlist_event_creates_contact_preference_and_entry(): void
    {
        $store = $this->createStore();
        $payload = [
            'event_type' => 'stock.waitlist.created',
            'store_id' => $store->id,
            'product_id' => 987,
            'variation_id' => 0,
            'phone' => '+905321112233',
            'stock_alert_consent' => 'granted',
        ];
        $rawBody = json_encode($payload);
        $timestamp = (string) time();
        $signature = 'sha256=' . hash_hmac('sha256', $rawBody, config('app.key'));

        $this->postJson('/api/whatsapp/booster/event', $payload, [
            'X-ZOLM-Event-ID' => 'stock-waitlist-test-1',
            'X-ZOLM-Event-Type' => 'stock.waitlist.created',
            'X-ZOLM-Timestamp' => $timestamp,
            'X-ZOLM-Signature' => $signature,
            'X-ZOLM-Store-ID' => (string) $store->id,
            'X-ZOLM-Version' => '1.0',
        ])->assertOk();

        $contact = WaContact::where('store_id', $store->id)->first();

        $this->assertNotNull($contact);
        $this->assertDatabaseHas('wa_contact_preferences', [
            'contact_id' => $contact->id,
            'store_id' => $store->id,
            'purpose' => 'stock_alert',
            'status' => 'granted',
        ]);
        $this->assertDatabaseHas('wa_stock_waitlists', [
            'store_id' => $store->id,
            'contact_id' => $contact->id,
            'wc_product_id' => 987,
            'status' => WaStockWaitlist::STATUS_WAITING,
        ]);
    }

    public function test_recovery_tracking_route_increments_click_count_and_redirects(): void
    {
        $token = WaTrackingLink::generateToken();
        $link = WaTrackingLink::create([
            'destination_url' => 'https://example.test/cart',
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDay(),
            'click_count' => 0,
        ]);

        $this->get(route('whatsapp.recovery.track', ['token' => $token]))
            ->assertRedirect('https://example.test/cart');

        $link->refresh();
        $this->assertEquals(1, $link->click_count);
        $this->assertNotNull($link->clicked_at);
    }
}
