<?php

namespace Tests\Feature\WhatsApp;

use App\Models\WaOutbox;
use App\Services\WhatsApp\OutboxService;

class ShippingIdempotencyTest extends WhatsAppTestCase
{
    public function test_same_order_and_stage_does_not_create_second_message(): void
    {
        $store = $this->createStore();
        $contact = $this->createContact($store);

        $service = new OutboxService();

        $idempotencyKey = 'shipping:1:100:shipped';

        $outbox1 = WaOutbox::create([
            'contact_id' => $contact->id,
            'store_id' => $store->id,
            'idempotency_key' => $idempotencyKey,
            'message_type' => 'template',
            'template_name' => 'kargoya_verildi',
            'status' => WaOutbox::STATUS_SENT,
        ]);

        // İkinci kez aynı key ile oluşturmaya çalış
        try {
            WaOutbox::create([
                'contact_id' => $contact->id,
                'store_id' => $store->id,
                'idempotency_key' => $idempotencyKey,
                'message_type' => 'template',
                'template_name' => 'kargoya_verildi',
                'status' => WaOutbox::STATUS_QUEUED,
            ]);
            $this->fail('Duplicate idempotency_key başarısız olmalı');
        } catch (\Illuminate\Database\QueryException $e) {
            // Unique constraint hatası bekleniyor
            $this->assertStringContainsString('23000', $e->errorInfo[0] ?? '');
        }

        $this->assertEquals(1, WaOutbox::where('idempotency_key', $idempotencyKey)->count());
    }

    public function test_different_stage_creates_new_message(): void
    {
        $store = $this->createStore();
        $contact = $this->createContact($store);

        WaOutbox::create([
            'contact_id' => $contact->id,
            'store_id' => $store->id,
            'idempotency_key' => 'shipping:1:100:shipped',
            'message_type' => 'template',
            'template_name' => 'kargoya_verildi',
            'status' => WaOutbox::STATUS_SENT,
        ]);

        WaOutbox::create([
            'contact_id' => $contact->id,
            'store_id' => $store->id,
            'idempotency_key' => 'shipping:1:100:delivered',
            'message_type' => 'template',
            'template_name' => 'teslim_edildi',
            'status' => WaOutbox::STATUS_QUEUED,
        ]);

        $this->assertEquals(2, WaOutbox::count());
    }
}
