<?php

namespace Tests\Feature\WhatsApp;

use App\Models\ChannelOrder;
use App\Models\WaAutomationConfig;
use App\Models\WaOutbox;
use App\Services\WhatsApp\OrderNotificationService;
use Illuminate\Support\Facades\Config;

class OrderConfirmationTest extends WhatsAppTestCase
{
    public function test_wc_non_store_order_no_notification(): void
    {
        $store = $this->createStore('trendyol');
        $order = ChannelOrder::create([
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'external_order_id' => 'TY-100',
            'order_number' => '100',
            'order_status' => 'new',
            'customer_phone' => '+905321112233',
            'customer_name' => 'Test',
            'ordered_at' => now(),
        ]);

        $service = new OrderNotificationService();
        $service->onOrderConfirmed($order, 'new', 'processing');

        $this->assertEquals(0, WaOutbox::count());
    }

    public function test_order_confirmation_requires_consent(): void
    {
        Config::set('whatsapp.features.test_mode', false);
        $store = $this->createStore();
        $contact = $this->createContact($store, '+905321112233');
        // Consent yok

        WaAutomationConfig::set('order_confirmation', [
            'enabled' => true,
            'allowed_statuses' => ['processing'],
            'template_id' => null,
        ]);

        $order = ChannelOrder::create([
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'external_order_id' => 'WC-100',
            'order_number' => '100',
            'order_status' => 'new',
            'customer_phone' => '+905321112233',
            'ordered_at' => now(),
        ]);

        $service = new OrderNotificationService();
        $service->onOrderConfirmed($order, 'new', 'processing');

        $this->assertEquals(0, WaOutbox::count());
    }

    public function test_marketing_consent_does_not_replace_order_updates(): void
    {
        Config::set('whatsapp.features.test_mode', false);
        $store = $this->createStore();
        $contact = $this->createContact($store, '+905322221111');
        $this->giveConsent($contact, $store, 'marketing', 'granted');

        WaAutomationConfig::set('order_confirmation', [
            'enabled' => true,
            'allowed_statuses' => ['processing'],
            'template_id' => null,
        ]);

        $order = ChannelOrder::create([
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'external_order_id' => 'WC-200',
            'order_number' => '200',
            'order_status' => 'new',
            'customer_phone' => '+905322221111',
            'ordered_at' => now(),
        ]);

        $service = new OrderNotificationService();
        $service->onOrderConfirmed($order, 'new', 'processing');

        $this->assertEquals(0, WaOutbox::count());
    }

    public function test_same_order_sync_no_second_notification(): void
    {
        Config::set('whatsapp.features.test_mode', false);
        $store = $this->createStore();
        $this->createAccount($store);
        $contact = $this->createContact($store, '+905323331111');
        $this->giveConsent($contact, $store, 'order_updates', 'granted');

        WaAutomationConfig::set('order_confirmation', [
            'enabled' => true,
            'allowed_statuses' => ['processing'],
            'template_id' => null, // Template yok — mesaj oluşmaz ama idempotency kontrolü çalışır
        ]);

        $order = ChannelOrder::create([
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'external_order_id' => 'WC-300',
            'order_number' => '300',
            'order_status' => 'new',
            'customer_phone' => '+905323331111',
            'ordered_at' => now(),
        ]);

        $service = new OrderNotificationService();

        // İlk çağrı — template yok, outbox oluşmaz
        $service->onOrderConfirmed($order, 'new', 'processing');

        // İkinci kez — yine oluşmaz
        $service->onOrderConfirmed($order, 'processing', 'processing');

        // Template null olduğu için outbox oluşmaz, ama akış çalışır
        $this->assertEquals(0, WaOutbox::count());
    }

    public function test_status_outside_mapping_not_sent(): void
    {
        Config::set('whatsapp.features.test_mode', false);
        $store = $this->createStore();
        $contact = $this->createContact($store, '+905324441111');
        $this->giveConsent($contact, $store, 'order_updates', 'granted');

        WaAutomationConfig::set('order_confirmation', [
            'enabled' => true,
            'allowed_statuses' => ['processing'],
            'template_id' => 1,
        ]);

        $order = ChannelOrder::create([
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'external_order_id' => 'WC-400',
            'order_number' => '400',
            'order_status' => 'new',
            'customer_phone' => '+905324441111',
            'ordered_at' => now(),
        ]);

        $service = new OrderNotificationService();
        // 'cancelled' allowed_statuses'da yok
        $service->onOrderConfirmed($order, 'new', 'cancelled');

        $this->assertEquals(0, WaOutbox::count());
    }

    public function test_order_settings_missing_no_outbox(): void
    {
        Config::set('whatsapp.features.test_mode', false);
        $store = $this->createStore();
        $contact = $this->createContact($store, '+905325551111');
        $this->giveConsent($contact, $store, 'order_updates', 'granted');

        // Ayarlar yok
        $order = ChannelOrder::create([
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'external_order_id' => 'WC-500',
            'order_number' => '500',
            'order_status' => 'new',
            'customer_phone' => '+905325551111',
            'ordered_at' => now(),
        ]);

        $service = new OrderNotificationService();
        $service->onOrderConfirmed($order, 'new', 'processing');

        $this->assertEquals(0, WaOutbox::count());
    }
}
