<?php

namespace Tests\Feature\WhatsApp;

use App\Models\WaOutbox;

class OutboxStatusForwardOnlyTest extends WhatsAppTestCase
{
    public function test_status_cannot_go_backwards(): void
    {
        $store = $this->createStore();
        $contact = $this->createContact($store);

        $outbox = WaOutbox::create([
            'contact_id' => $contact->id,
            'store_id' => $store->id,
            'idempotency_key' => 'test-forward-only',
            'message_type' => 'template',
            'template_name' => 'test_template',
            'status' => WaOutbox::STATUS_READ,
        ]);

        // read → delivered düşmeyecek
        $this->assertFalse($outbox->canProgressTo(WaOutbox::STATUS_DELIVERED));
        // read → sent düşmeyecek (sent=read'den önce)
        $this->assertFalse($outbox->canProgressTo(WaOutbox::STATUS_SENT));
    }

    public function test_forward_progression_works(): void
    {
        $outbox = new WaOutbox();
        $outbox->status = WaOutbox::STATUS_SENT;

        $this->assertTrue($outbox->canProgressTo(WaOutbox::STATUS_DELIVERED));
        $this->assertTrue($outbox->canProgressTo(WaOutbox::STATUS_READ));
        $this->assertFalse($outbox->canProgressTo(WaOutbox::STATUS_QUEUED));
    }

    public function test_update_delivery_status_respects_forward_only(): void
    {
        $store = $this->createStore();
        $contact = $this->createContact($store);

        $outbox = WaOutbox::create([
            'contact_id' => $contact->id,
            'store_id' => $store->id,
            'idempotency_key' => 'test-forward-only-update',
            'message_type' => 'template',
            'template_name' => 'test_template',
            'status' => WaOutbox::STATUS_READ,
        ]);

        $service = new \App\Services\WhatsApp\OutboxService();
        $service->updateDeliveryStatus($outbox, WaOutbox::STATUS_DELIVERED);

        $outbox->refresh();
        $this->assertEquals(WaOutbox::STATUS_READ, $outbox->status);
    }
}
