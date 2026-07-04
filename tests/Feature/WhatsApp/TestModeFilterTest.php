<?php

namespace Tests\Feature\WhatsApp;

use Illuminate\Support\Facades\Config;
use App\Services\WhatsApp\EligibilityService;

class TestModeFilterTest extends WhatsAppTestCase
{
    public function test_test_mode_only_allows_test_numbers(): void
    {
        Config::set('whatsapp.features.test_mode', true);
        Config::set('whatsapp.features.test_phone_numbers', ['+905321112233']);

        $store = $this->createStore();
        $service = new EligibilityService();

        // Test numarası
        $testContact = $this->createContact($store, '+905321112233');
        $this->giveConsent($testContact, $store, 'order_updates', 'granted');
        $this->assertTrue($service->isEligibleForMessaging($testContact, 'order_updates'));

        // Normal numara
        $normalContact = $this->createContact($store, '+905329998877');
        $this->giveConsent($normalContact, $store, 'order_updates', 'granted');
        $this->assertFalse($service->isEligibleForMessaging($normalContact, 'order_updates'));
    }

    public function test_production_mode_allows_all_numbers(): void
    {
        Config::set('whatsapp.features.test_mode', false);

        $store = $this->createStore();
        $service = new EligibilityService();

        $contact = $this->createContact($store, '+905329998877');
        $this->giveConsent($contact, $store, 'order_updates', 'granted');
        $this->assertTrue($service->isEligibleForMessaging($contact, 'order_updates'));
    }
}
