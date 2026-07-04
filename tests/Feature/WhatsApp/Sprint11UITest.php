<?php

namespace Tests\Feature\WhatsApp;

use App\Models\WaContact;
use App\Models\WaAuditLog;
use App\Models\WaAutomationConfig;
use App\Models\WaCampaign;
use App\Models\WaCampaignDailyMetric;
use App\Services\WhatsApp\CustomerProfileService;
use Livewire\Livewire;
use Illuminate\Support\Facades\Config;

class Sprint11UITest extends WhatsAppTestCase
{
    public function test_customer_profile_page_loads(): void
    {
        $store = $this->createStore();
        $contact = $this->createContact($store, '+905321111111');

        $response = $this->actingAs($store->user)->get("/whatsapp/customer/{$contact->id}");
        $response->assertOk();
    }

    public function test_customer_profile_service_builds_profile(): void
    {
        $store = $this->createStore();
        $contact = $this->createContact($store, '+905322222222');
        $this->giveConsent($contact, $store, 'marketing', 'granted');

        $service = new CustomerProfileService();
        $profile = $service->getProfilePageData($contact->id, $store->id);

        $this->assertArrayHasKey('contact', $profile);
        $this->assertArrayHasKey('profile', $profile);
        $this->assertArrayHasKey('preferences', $profile);
        $this->assertArrayHasKey('recent_messages', $profile);
        $this->assertArrayHasKey('coupons', $profile);
    }

    public function test_campaign_detail_page_loads(): void
    {
        $store = $this->createStore();
        $campaign = WaCampaign::create([
            'store_id' => $store->id,
            'wa_account_id' => $this->createAccount($store)->id,
            'name' => 'Detail Test',
            'status' => 'completed',
            'created_by' => $store->user_id,
            'total_recipients' => 100,
            'total_sent' => 90,
        ]);

        $response = $this->actingAs($store->user)->get("/whatsapp/campaigns/{$campaign->id}");
        $response->assertOk();
    }

    public function test_audit_logs_page_loads(): void
    {
        $store = $this->createStore();
        WaAuditLog::create([
            'user_id' => $store->user_id,
            'action' => 'test_action',
            'entity_type' => 'test',
        ]);

        $response = $this->actingAs($store->user)->get('/whatsapp/audit-logs');
        $response->assertOk();
    }

    public function test_automation_settings_page_loads(): void
    {
        $store = $this->createStore();
        $response = $this->actingAs($store->user)->get('/whatsapp/automation');
        $response->assertOk();
    }

    public function test_automation_settings_save(): void
    {
        Config::set('whatsapp.features.test_mode', false);
        $store = $this->createStore();

        Livewire::actingAs($store->user)
            ->test(\App\Livewire\WhatsApp\WhatsAppAutomationSettings::class)
            ->set('cartRecovery', ['enabled' => true])
            ->call('saveSettings');

        $saved = WaAutomationConfig::get('cart_recovery', []);
        $this->assertTrue($saved['enabled'] ?? false);
    }
}
