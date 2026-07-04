<?php

namespace Tests\Feature\WhatsApp;

use App\Models\WaSetting;
use App\Models\Shipment;
use App\Models\ChannelOrder;
use App\Events\ShipmentStatusChanged;
use App\Listeners\WhatsApp\SendShippingNotificationListener;
use Illuminate\Support\Facades\Config;

class ShippingSettingsTest extends WhatsAppTestCase
{
    public function test_shipping_disabled_prevents_outbox_creation(): void
    {
        Config::set('whatsapp.features.test_mode', false);

        WaSetting::set('shipping.enabled', false);

        $store = $this->createStore();
        $contact = $this->createContact($store, '+905321112233');
        $this->giveConsent($contact, $store, 'order_updates', 'granted');
        $this->createAccount($store);

        $order = ChannelOrder::create([
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'external_order_id' => 'WC-1001',
            'order_number' => '1001',
            'order_status' => 'Processing',
            'customer_phone' => '+905321112233',
            'customer_name' => 'Test',
            'ordered_at' => now(),
        ]);

        $shipment = Shipment::create([
            'user_id' => $store->user_id,
            'store_id' => $store->id,
            'channel_order_id' => $order->id,
            'shipment_no' => 'SHP-001',
            'status' => 'shipped',
            'tracking_number' => 'TRK123',
            'carrier_code' => 'surat',
        ]);

        $listener = app(SendShippingNotificationListener::class);
        $event = new ShipmentStatusChanged($shipment, 'draft', 'shipped');
        $listener->handle($event);

        $this->assertEquals(0, \App\Models\WaOutbox::count());
    }

    public function test_shipping_allowed_stages_controlled_by_settings(): void
    {
        Config::set('whatsapp.features.test_mode', false);

        WaSetting::set('shipping.enabled', true);
        WaSetting::set('shipping.stages', ['shipped']);

        $store = $this->createStore();
        $contact = $this->createContact($store, '+905325554433');
        $this->giveConsent($contact, $store, 'order_updates', 'granted');
        $this->createAccount($store);

        $order = ChannelOrder::create([
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'external_order_id' => 'WC-1002',
            'order_number' => '1002',
            'order_status' => 'Processing',
            'customer_phone' => '+905325554433',
            'customer_name' => 'Test 2',
            'ordered_at' => now(),
        ]);

        $shipment = Shipment::create([
            'user_id' => $store->user_id,
            'store_id' => $store->id,
            'channel_order_id' => $order->id,
            'shipment_no' => 'SHP-002',
            'status' => 'out_for_delivery',
            'tracking_number' => 'TRK456',
            'carrier_code' => 'surat',
        ]);

        $listener = app(SendShippingNotificationListener::class);
        $event = new ShipmentStatusChanged($shipment, 'shipped', 'out_for_delivery');
        $listener->handle($event);

        $this->assertEquals(0, \App\Models\WaOutbox::count());
    }

    public function test_shipping_settings_page_loads(): void
    {
        $store = $this->createStore();
        $this->actingAs($store->user);

        $response = $this->get(route('whatsapp.shipping'));
        $response->assertOk();
    }
}
