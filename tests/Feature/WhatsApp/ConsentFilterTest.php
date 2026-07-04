<?php

namespace Tests\Feature\WhatsApp;

use Illuminate\Support\Facades\Config;
use App\Models\WaOutbox;
use App\Models\WaContactPreference;

class ConsentFilterTest extends WhatsAppTestCase
{
    public function test_contact_without_order_updates_consent_cannot_receive_shipping_notification(): void
    {
        Config::set('whatsapp.features.test_mode', false);

        $store = $this->createStore();
        $contact = $this->createContact($store, '+905329998877');

        $service = new \App\Services\WhatsApp\EligibilityService();

        $this->assertFalse($service->hasGrantedPreference($contact, 'order_updates'));
    }

    public function test_contact_with_order_updates_consent_is_eligible(): void
    {
        Config::set('whatsapp.features.test_mode', false);

        $store = $this->createStore();
        $contact = $this->createContact($store, '+905329998877');
        $this->giveConsent($contact, $store, 'order_updates', 'granted');

        $service = new \App\Services\WhatsApp\EligibilityService();

        $this->assertTrue($service->hasGrantedPreference($contact, 'order_updates'));
        $this->assertTrue($service->isEligibleForMessaging($contact, 'order_updates'));
    }

    public function test_suppressed_cannot_receive_messages(): void
    {
        $store = $this->createStore();
        $contact = $this->createContact($store, '+905329998877');
        $this->giveConsent($contact, $store, 'order_updates', 'granted');

        \App\Models\WaSuppression::create([
            'contact_id' => $contact->id,
            'reason' => 'opted_out',
            'suppressed_at' => now(),
        ]);

        $service = new \App\Services\WhatsApp\EligibilityService();

        $this->assertTrue($service->isSuppressed($contact));
        $this->assertFalse($service->isEligibleForMessaging($contact, 'order_updates'));
    }

    public function test_marketing_consent_does_not_grant_order_updates(): void
    {
        $store = $this->createStore();
        $contact = $this->createContact($store, '+905329998877');
        $this->giveConsent($contact, $store, 'marketing', 'granted');

        $service = new \App\Services\WhatsApp\EligibilityService();

        $this->assertTrue($service->hasGrantedPreference($contact, 'marketing'));
        $this->assertFalse($service->hasGrantedPreference($contact, 'order_updates'));
        $this->assertFalse($service->isEligibleForMessaging($contact, 'order_updates'));
    }
}
