<?php

namespace Tests\Feature\WhatsApp;

use App\Models\WaMessageDelivery;
use App\Models\WaOutbox;

class DeliveryProviderEventKeyTest extends WhatsAppTestCase
{
    public function test_duplicate_provider_event_key_not_allowed(): void
    {
        $store = $this->createStore();
        $contact = $this->createContact($store);

        $outbox = WaOutbox::create([
            'contact_id' => $contact->id,
            'store_id' => $store->id,
            'idempotency_key' => 'test-delivery-dup',
            'message_type' => 'template',
            'status' => WaOutbox::STATUS_SENT,
        ]);

        WaMessageDelivery::create([
            'outbox_id' => $outbox->id,
            'meta_message_id' => 'msg_test_dup',
            'provider_event_key' => 'provider_key_001',
            'status' => 'sent',
        ]);

        try {
            WaMessageDelivery::create([
                'outbox_id' => $outbox->id,
                'meta_message_id' => 'msg_test_dup',
                'provider_event_key' => 'provider_key_001',
                'status' => 'delivered',
            ]);
            $this->fail('Duplicate provider_event_key başarısız olmalı');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertStringContainsString('23000', $e->errorInfo[0] ?? '');
        }
    }
}
