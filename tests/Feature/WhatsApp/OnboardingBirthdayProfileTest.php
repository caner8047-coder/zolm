<?php

namespace Tests\Feature\WhatsApp;

use App\Models\WaContact;
use App\Models\WaBirthdayProfile;
use App\Models\WaCustomerProfile;
use App\Models\WaOnboardingFlow;
use App\Models\WaOnboardingStep;
use App\Models\WaOutbox;
use App\Models\WaWebhookEvent;
use App\Models\WaSetting;
use App\Services\WhatsApp\WelcomeOnboardingService;
use App\Services\WhatsApp\FirstPurchaseIncentiveService;
use App\Services\WhatsApp\BirthdayService;
use App\Services\WhatsApp\CustomerProfileService;
use App\Services\WhatsApp\RetentionCleanupService;
use Illuminate\Support\Facades\Config;

class OnboardingBirthdayProfileTest extends WhatsAppTestCase
{
    public function test_welcome_flow_creates_onboarding(): void
    {
        Config::set('whatsapp.features.test_mode', false);
        $store = $this->createStore();
        $contact = $this->createContact($store, '+905321111111');
        $this->giveConsent($contact, $store, 'order_updates', 'granted');

        WaSetting::set('onboarding.welcome', [
            'enabled' => true,
            'steps' => [
                ['name' => 'hoş_geldin', 'delay_type' => 'immediate', 'delay_value' => 0, 'template_key' => 'welcome_msg'],
            ],
        ]);

        $service = new WelcomeOnboardingService();
        $flow = $service->startWelcomeFlow($contact);

        $this->assertNotNull($flow);
        $this->assertEquals('welcome', $flow->flow_type);
        $this->assertEquals('active', $flow->status);
        $this->assertEquals(1, WaOnboardingStep::where('flow_id', $flow->id)->count());
    }

    public function test_welcome_flow_duplicate_prevented(): void
    {
        Config::set('whatsapp.features.test_mode', false);
        $store = $this->createStore();
        $contact = $this->createContact($store, '+905322222222');
        $this->giveConsent($contact, $store, 'order_updates', 'granted');

        WaSetting::set('onboarding.welcome', [
            'enabled' => true,
            'steps' => [['name' => 'hoş_geldin', 'delay_type' => 'immediate', 'template_key' => 'welcome_msg']],
        ]);

        $service = new WelcomeOnboardingService();
        $service->startWelcomeFlow($contact);
        $second = $service->startWelcomeFlow($contact);

        $this->assertNull($second);
        $this->assertEquals(1, WaOnboardingFlow::where('contact_id', $contact->id)->where('flow_type', 'welcome')->count());
    }

    public function test_welcome_flow_completed_on_order(): void
    {
        Config::set('whatsapp.features.test_mode', false);
        $store = $this->createStore();
        $contact = $this->createContact($store, '+905323333333');
        $this->giveConsent($contact, $store, 'order_updates', 'granted');

        WaSetting::set('onboarding.welcome', [
            'enabled' => true,
            'steps' => [['name' => 'hoş_geldin', 'delay_type' => 'immediate', 'template_key' => 'welcome_msg']],
        ]);

        $service = new WelcomeOnboardingService();
        $flow = $service->startWelcomeFlow($contact);

        $service->completeFlow($contact, 'order_placed');

        $flow->refresh();
        $this->assertEquals('completed', $flow->status);
        $this->assertNotNull($flow->completed_at);
    }

    public function test_first_purchase_incentive_creates_flow(): void
    {
        Config::set('whatsapp.features.test_mode', false);
        $store = $this->createStore();
        $contact = $this->createContact($store, '+905324444444');
        $this->giveConsent($contact, $store, 'order_updates', 'granted');

        WaSetting::set('onboarding.first_purchase', [
            'enabled' => true,
            'steps' => [
                ['name' => 'ürün_keşfi', 'delay_type' => 'days', 'delay_value' => 3, 'template_key' => 'fp_day3'],
                ['name' => 'hafif_teşvik', 'delay_type' => 'days', 'delay_value' => 7, 'template_key' => 'fp_day7'],
            ],
        ]);

        $service = new FirstPurchaseIncentiveService();
        $flow = $service->startIncentiveFlow($contact);

        $this->assertNotNull($flow);
        $this->assertEquals('first_purchase', $flow->flow_type);
        $this->assertEquals(2, WaOnboardingStep::where('flow_id', $flow->id)->count());
    }

    public function test_birthday_profile_created(): void
    {
        Config::set('whatsapp.features.test_mode', false);
        $store = $this->createStore();
        $contact = $this->createContact($store, '+905325555555');

        $service = new BirthdayService();
        $profile = $service->updateProfile($contact, '1990-07-04', true);

        $this->assertNotNull($profile);
        $this->assertTrue($profile->consent_granted);
        $this->assertEquals('1990-07-04', $profile->birth_date->format('Y-m-d'));
    }

    public function test_birthday_message_not_sent_without_consent(): void
    {
        Config::set('whatsapp.features.test_mode', false);
        $store = $this->createStore();
        $contact = $this->createContact($store, '+905326666666');

        $service = new BirthdayService();
        $service->updateProfile($contact, '1990-07-04', false);

        WaSetting::set('birthday', [
            'enabled' => true,
            'template_key' => 'birthday_msg',
            'coupon_enabled' => false,
        ]);

        $sent = $service->processBirthdayMessages();
        $this->assertEquals(0, $sent);
    }

    public function test_customer_profile_built_correctly(): void
    {
        Config::set('whatsapp.features.test_mode', false);
        $store = $this->createStore();
        $contact = $this->createContact($store, '+905327777777');
        $this->giveConsent($contact, $store, 'marketing', 'granted');

        $service = new CustomerProfileService();
        $profile = $service->buildProfile($contact);

        $this->assertNotNull($profile);
        $this->assertEquals(0, $profile->total_orders);
        $this->assertEquals('low', $profile->engagement_score);
    }

    public function test_customer_profile_page_data(): void
    {
        Config::set('whatsapp.features.test_mode', false);
        $store = $this->createStore();
        $contact = $this->createContact($store, '+905328888888');

        $service = new CustomerProfileService();
        $data = $service->getProfilePageData($contact->id, $store->id);

        $this->assertArrayHasKey('contact', $data);
        $this->assertArrayHasKey('profile', $data);
        $this->assertArrayHasKey('preferences', $data);
        $this->assertArrayHasKey('recent_messages', $data);
        $this->assertArrayHasKey('coupons', $data);
    }

    public function test_retention_cleanup_removes_old_data(): void
    {
        $service = new RetentionCleanupService();

        // Eski webhook event oluştur (DB ile doğrudan)
        \Illuminate\Support\Facades\DB::table('wa_webhook_events')->insert([
            'event_type' => 'test',
            'request_hash' => 'old_hash_value',
            'provider_event_key' => 'old_event_' . uniqid(),
            'source' => 'meta',
            'payload' => '{"test":true}',
            'status' => 'processed',
            'created_at' => now()->subDays(100)->toDateTimeString(),
            'updated_at' => now()->subDays(100)->toDateTimeString(),
        ]);

        $results = $service->run();

        $this->assertGreaterThan(0, $results['wa_webhook_events'] ?? 0);
    }
}
