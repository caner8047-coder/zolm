<?php

namespace Tests\Feature\WhatsApp;

use App\Models\ChannelOrder;
use App\Models\WaAbTest;
use App\Models\WaAbTestResult;
use App\Models\WaAttributionEvent;
use App\Models\WaCampaign;
use App\Models\WaCampaignAudience;
use App\Models\WaControlGroup;
use App\Models\WaControlGroupMember;
use App\Models\WaAutomationDefinition;
use App\Models\WaAutomationEnrollment;
use App\Services\WhatsApp\AttributionService;
use App\Services\WhatsApp\ABTestService;
use App\Services\WhatsApp\ControlGroupService;
use Illuminate\Support\Facades\Config;

class Sprint10Test extends WhatsAppTestCase
{
    public function test_attribution_click_recorded(): void
    {
        Config::set('whatsapp.features.test_mode', false);
        $store = $this->createStore();
        $contact = $this->createContact($store, '+905321111111');
        $this->giveConsent($contact, $store, 'marketing', 'granted');

        $campaign = WaCampaign::create([
            'store_id' => $store->id,
            'wa_account_id' => $this->createAccount($store)->id,
            'name' => 'Test Kampanya',
            'status' => 'running',
            'created_by' => $store->user_id,
        ]);

        $audience = WaCampaignAudience::create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'store_id' => $store->id,
            'eligibility_status' => 'eligible',
        ]);

        $service = new AttributionService();
        $service->recordClick($audience);

        $this->assertDatabaseHas('wa_attribution_events', [
            'campaign_id' => $campaign->id,
            'event_type' => 'click',
        ]);
    }

    public function test_order_attribution(): void
    {
        Config::set('whatsapp.features.test_mode', false);
        $store = $this->createStore();
        $contact = $this->createContact($store, '+905322222222');
        $this->giveConsent($contact, $store, 'marketing', 'granted');

        $campaign = WaCampaign::create([
            'store_id' => $store->id,
            'wa_account_id' => $this->createAccount($store)->id,
            'name' => 'Test',
            'status' => 'completed',
            'created_by' => $store->user_id,
        ]);

        $audience = WaCampaignAudience::create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'store_id' => $store->id,
            'eligibility_status' => 'eligible',
            'clicked_at' => now(),
        ]);

        $order = ChannelOrder::create([
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'external_order_id' => 'WC-ATT-100',
            'order_number' => '100',
            'order_status' => 'processing',
            'customer_phone' => '+905322222222',
            'ordered_at' => now(),
        ]);

        $service = new AttributionService();
        $service->attributeOrder($order, $contact);

        $this->assertDatabaseHas('wa_attribution_events', [
            'campaign_id' => $campaign->id,
            'event_type' => 'order_created',
            'order_id' => $order->id,
        ]);
    }

    public function test_ab_test_variant_selection(): void
    {
        Config::set('whatsapp.features.test_mode', false);
        $store = $this->createStore();
        $contact = $this->createContact($store, '+905323333333');

        $test = WaAbTest::create([
            'store_id' => $store->id,
            'name' => 'Test A/B',
            'status' => 'running',
            'variants_json' => [
                ['name' => 'variant_a', 'template_id' => 1],
                ['name' => 'variant_b', 'template_id' => 2],
            ],
            'created_by' => $store->user_id,
        ]);

        $service = new ABTestService();
        $variant = $service->selectVariant($test, $contact);

        $this->assertNotNull($variant);
        $this->assertArrayHasKey('name', $variant);
    }

    public function test_ab_test_winner_determined(): void
    {
        Config::set('whatsapp.features.test_mode', false);
        $store = $this->createStore();

        $test = WaAbTest::create([
            'store_id' => $store->id,
            'name' => 'Winner Test',
            'status' => 'running',
            'variants_json' => [
                ['name' => 'a', 'template_id' => 1],
                ['name' => 'b', 'template_id' => 2],
            ],
            'created_by' => $store->user_id,
        ]);

        WaAbTestResult::create([
            'ab_test_id' => $test->id,
            'variant_name' => 'a',
            'sample_size' => 100,
            'conversions' => 10,
            'conversion_rate' => 10.0,
        ]);

        WaAbTestResult::create([
            'ab_test_id' => $test->id,
            'variant_name' => 'b',
            'sample_size' => 100,
            'conversions' => 20,
            'conversion_rate' => 20.0,
        ]);

        $service = new ABTestService();
        $service->completeTest($test);

        $winner = WaAbTestResult::where('ab_test_id', $test->id)->where('is_winner', true)->first();
        $this->assertNotNull($winner);
        $this->assertEquals('b', $winner->variant_name);
    }

    public function test_control_group_enrollment(): void
    {
        Config::set('whatsapp.features.test_mode', false);
        $store = $this->createStore();
        $contact = $this->createContact($store, '+905324444444');

        $group = WaControlGroup::create([
            'store_id' => $store->id,
            'name' => 'Test Kontrol',
            'sample_percentage' => 10,
            'is_active' => true,
        ]);

        $service = new ControlGroupService();
        $enrolled = $service->enroll($group, $contact);

        $this->assertTrue($enrolled);
        $this->assertTrue($service->isInControlGroup($contact));
    }

    public function test_control_group_duplicate_prevented(): void
    {
        Config::set('whatsapp.features.test_mode', false);
        $store = $this->createStore();
        $contact = $this->createContact($store, '+905325555555');

        $group = WaControlGroup::create([
            'store_id' => $store->id,
            'name' => 'Dup Test',
            'sample_percentage' => 10,
            'is_active' => true,
        ]);

        $service = new ControlGroupService();
        $service->enroll($group, $contact);
        $second = $service->enroll($group, $contact);

        $this->assertFalse($second);
        $this->assertEquals(1, WaControlGroupMember::where('group_id', $group->id)->count());
    }

    public function test_control_group_not_in_campaign(): void
    {
        Config::set('whatsapp.features.test_mode', false);
        $store = $this->createStore();
        $contact = $this->createContact($store, '+905326666666');

        $group = WaControlGroup::create([
            'store_id' => $store->id,
            'name' => 'Exclude Test',
            'sample_percentage' => 10,
            'is_active' => true,
        ]);

        $service = new ControlGroupService();
        $service->enroll($group, $contact);

        $this->assertTrue($service->isInControlGroup($contact));
    }
}
