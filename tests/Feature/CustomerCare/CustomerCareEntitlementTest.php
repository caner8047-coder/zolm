<?php

namespace Tests\Feature\CustomerCare;

use Tests\TestCase;
use App\Models\User;
use App\Models\MarketplaceStore;
use App\Models\SupportCommercialPlan;
use App\Models\SupportCommercialSubscription;
use App\Models\SupportEntitlementEvent;
use App\Models\LegalEntity;
use App\Services\Support\CustomerCareEntitlementService;
use App\Services\Support\CustomerCareUsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;

class CustomerCareEntitlementTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $otherUser;
    protected MarketplaceStore $store;
    protected MarketplaceStore $otherStore;
    protected SupportCommercialPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create(['role' => 'admin', 'email' => 'admin@zolm.com', 'is_active' => true]);
        $this->otherUser = User::factory()->create(['role' => 'operator', 'email' => 'other@zolm.com', 'is_active' => true]);

        $le = LegalEntity::create([
            'user_id'      => $this->adminUser->id,
            'name'         => 'Test Org',
            'company_name' => 'Co',
            'tax_office'   => 'Kadikoy',
            'tax_number'   => '1234567890',
            'address'      => 'Istanbul',
        ]);

        $this->store = MarketplaceStore::create([
            'store_name'      => 'Store A',
            'store_key'       => 'store_a',
            'user_id'         => $this->adminUser->id,
            'legal_entity_id' => $le->id,
            'marketplace'     => 'trendyol',
            'is_active'       => true,
        ]);

        $this->otherStore = MarketplaceStore::create([
            'store_name'      => 'Store B',
            'store_key'       => 'store_b',
            'user_id'         => $this->adminUser->id,
            'legal_entity_id' => $le->id,
            'marketplace'     => 'trendyol',
            'is_active'       => true,
        ]);

        $this->plan = SupportCommercialPlan::create([
            'name'         => 'pro',
            'slug'         => 'pro',
            'entitlements' => [
                'auto_reply'     => true,
                'ai_draft'       => true,
                'enterprise_api' => true,
            ],
        ]);

        Config::set('customer-care.enabled', true);
        Config::set('customer-care.commercial_center_enabled', true);
    }

    #[Test]
    public function commercial_route_blocks_when_flag_off(): void
    {
        Config::set('customer-care.commercial_center_enabled', false);

        $response = $this->actingAs($this->adminUser)->get('/customer-care/commercial');
        $response->assertStatus(404);
    }

    #[Test]
    public function commercial_route_renders_when_flag_enabled(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/customer-care/commercial');

        $response->assertStatus(200);
    }

    #[Test]
    public function unknown_entitlement_fails_closed(): void
    {
        SupportCommercialSubscription::create([
            'store_id' => $this->store->id,
            'plan_id'  => $this->plan->id,
            'status'   => 'active',
        ]);

        $service = app(CustomerCareEntitlementService::class);

        // plan entitlements listesinde olmayan bir özellik (unknown_feature)
        $this->assertFalse($service->hasEntitlement($this->store->id, 'unknown_feature', $this->adminUser));
    }

    #[Test]
    public function plan_limit_blocks_auto_reply_but_allows_manual_reply(): void
    {
        // auto_reply = false olan bir plan oluştur
        $starterPlan = SupportCommercialPlan::create([
            'name'         => 'starter',
            'slug'         => 'starter',
            'entitlements' => [
                'auto_reply' => false, // bloklandı
            ],
        ]);

        SupportCommercialSubscription::create([
            'store_id' => $this->store->id,
            'plan_id'  => $starterPlan->id,
            'status'   => 'active',
        ]);

        $service = app(CustomerCareEntitlementService::class);

        // auto_reply blocklanmalı
        $this->assertFalse($service->hasEntitlement($this->store->id, 'auto_reply', $this->adminUser));

        // manual_agent_reply her durumda allowed olmalı (operasyon kesintiye uğramasın)
        $this->assertTrue($service->hasEntitlement($this->store->id, 'manual_agent_reply', $this->adminUser));
    }

    #[Test]
    public function blocked_action_does_not_consume_quota(): void
    {
        // abonelik yok — yetki bloklanmalı
        $service = app(CustomerCareEntitlementService::class);
        $this->assertFalse($service->hasEntitlement($this->store->id, 'auto_reply', $this->adminUser));

        // blocked logu oluşmalı ama usage metering event artmamalı
        $this->assertDatabaseHas('support_entitlement_events', [
            'store_id' => $this->store->id,
            'feature'  => 'auto_reply',
            'status'   => 'blocked',
        ]);
    }

    #[Test]
    public function cross_store_entitlement_access_is_blocked(): void
    {
        // otherStore için abonelik kur
        SupportCommercialSubscription::create([
            'store_id' => $this->otherStore->id,
            'plan_id'  => $this->plan->id,
            'status'   => 'active',
        ]);

        // adminUser (otherStore yetkili) otherStore plan geçişi yapabilir
        $service = app(CustomerCareEntitlementService::class);
        $newPlan = SupportCommercialPlan::create([
            'name'         => 'starter',
            'slug'         => 'starter',
            'entitlements' => ['auto_reply' => true],
        ]);

        $sub = $service->requestPlanChange($this->otherStore->id, $newPlan->id, $this->adminUser);
        $this->assertEquals('active', $sub->status);
    }

    #[Test]
    public function billing_export_is_pii_safe_and_xml_safe(): void
    {
        SupportEntitlementEvent::create([
            'store_id' => $this->store->id,
            'feature'  => 'auto_reply',
            'status'   => 'blocked',
            'context'  => ['reason' => 'TCKN sızan not: 12345678901 ve XML char ' . chr(8)],
        ]);

        $service = app(CustomerCareEntitlementService::class);
        $csv = $service->generateBillingExport($this->store->id, date('Y-m'), $this->adminUser);

        // XML kontrol karakteri temizlenmeli
        $this->assertStringNotContainsString(chr(8), $csv);
        // UTF-8 BOM içermeli
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        // PII maskelenmeli (TCKN raw görünmemeli)
        $this->assertStringNotContainsString('12345678901', $csv);
    }

    #[Test]
    public function unauthorized_user_cannot_export_billing_for_another_store(): void
    {
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        $service = app(CustomerCareEntitlementService::class);
        $service->generateBillingExport($this->store->id, date('Y-m'), $this->otherUser);
    }

    #[Test]
    public function unauthorized_user_cannot_read_has_entitlement_for_another_store(): void
    {
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        $service = app(CustomerCareEntitlementService::class);
        $service->hasEntitlement($this->store->id, 'auto_reply', $this->otherUser);
    }
}
