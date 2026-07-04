<?php

namespace Tests\Feature\WhatsApp;

use App\Models\WaAutomationConfig;
use App\Models\WaCampaign;
use App\Models\WaCampaignAudience;
use App\Models\WaSegment;
use App\Services\WhatsApp\SegmentEngine;
use Illuminate\Support\Facades\Config;

class CampaignEligibilityTest extends WhatsAppTestCase
{
    public function test_wc_non_store_contact_excluded(): void
    {
        $store = $this->createStore('trendyol');
        $contact = $this->createContact($store, '+905325555555');

        $segment = WaSegment::create([
            'store_id' => $store->id, 'name' => 'Test',
            'status' => 'active', 'rules_json' => ['filters' => []],
            'created_by' => $store->user_id,
        ]);

        $engine = new SegmentEngine();
        $contactIds = $engine->evaluate($segment);

        $this->assertNotContains($contact->id, $contactIds);
    }

    public function test_marketing_consent_required(): void
    {
        Config::set('whatsapp.features.test_mode', false);
        $store = $this->createStore();
        $contact = $this->createContact($store, '+905326666666');

        $segment = WaSegment::create([
            'store_id' => $store->id, 'name' => 'No Consent',
            'status' => 'active', 'rules_json' => ['filters' => []],
            'created_by' => $store->user_id,
        ]);

        $engine = new SegmentEngine();
        $contactIds = $engine->evaluate($segment);

        $this->assertNotContains($contact->id, $contactIds);
    }

    public function test_order_updates_does_not_replace_marketing(): void
    {
        Config::set('whatsapp.features.test_mode', false);
        $store = $this->createStore();
        $contact = $this->createContact($store, '+905327777777');
        $this->giveConsent($contact, $store, 'order_updates', 'granted');

        $segment = WaSegment::create([
            'store_id' => $store->id, 'name' => 'Order Updates Only',
            'status' => 'active', 'rules_json' => ['filters' => []],
            'created_by' => $store->user_id,
        ]);

        $engine = new SegmentEngine();
        $contactIds = $engine->evaluate($segment);

        $this->assertNotContains($contact->id, $contactIds);
    }

    public function test_suppressed_contact_excluded(): void
    {
        Config::set('whatsapp.features.test_mode', false);
        $store = $this->createStore();
        $contact = $this->createContact($store, '+905328888888');
        $this->giveConsent($contact, $store, 'marketing', 'granted');

        \App\Models\WaSuppression::create([
            'contact_id' => $contact->id,
            'reason' => 'spam_complaint',
            'suppressed_at' => now(),
        ]);

        $segment = WaSegment::create([
            'store_id' => $store->id, 'name' => 'Suppressed',
            'status' => 'active', 'rules_json' => ['filters' => []],
            'created_by' => $store->user_id,
        ]);

        $engine = new SegmentEngine();
        $contactIds = $engine->evaluate($segment);

        $this->assertNotContains($contact->id, $contactIds);
    }

    public function test_active_cart_recovery_excluded(): void
    {
        Config::set('whatsapp.features.test_mode', false);
        $store = $this->createStore();
        $contact = $this->createContact($store, '+905329999999');
        $this->giveConsent($contact, $store, 'marketing', 'granted');

        \App\Models\WaAbandonedCart::create([
            'store_id' => $store->id,
            'contact_id' => $contact->id,
            'cart_key_hash' => hash('sha256', 'active-cart'),
            'cart_snapshot_json' => [['product_id' => 1]],
            'cart_total_snapshot' => 100,
            'status' => 'active',
            'last_activity_at' => now(),
            'first_detected_at' => now(),
        ]);

        $segment = WaSegment::create([
            'store_id' => $store->id, 'name' => 'Cart Recovery',
            'status' => 'active',
            'rules_json' => ['filters' => [['field' => 'has_cart_recovery_active', 'operator' => '=', 'value' => true]]],
            'created_by' => $store->user_id,
        ]);

        $engine = new SegmentEngine();
        $contactIds = $engine->evaluate($segment);

        $this->assertNotContains($contact->id, $contactIds);
    }
}
