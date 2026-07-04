<?php

namespace Tests\Feature\WhatsApp;

use App\Services\WhatsApp\OutboxService;
use App\Models\WaOutbox;

class OutboxRaceConditionTest extends WhatsAppTestCase
{
    public function test_claim_for_processing_uses_atomic_update(): void
    {
        $store = $this->createStore();
        $contact = $this->createContact($store);

        $outbox = WaOutbox::create([
            'contact_id' => $contact->id,
            'store_id' => $store->id,
            'idempotency_key' => 'test-race-condition',
            'message_type' => 'template',
            'status' => WaOutbox::STATUS_QUEUED,
        ]);

        $service = new OutboxService();

        // İlk claim başarılı olmalı
        $result1 = $service->claimForProcessing($outbox);
        $this->assertTrue($result1);

        // İkinci claim başarısız olmalı (zaten processing)
        $result2 = $service->claimForProcessing($outbox);
        $this->assertFalse($result2);

        $outbox->refresh();
        $this->assertEquals(WaOutbox::STATUS_PROCESSING, $outbox->status);
    }
}
