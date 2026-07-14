<?php

namespace Tests\Feature\CustomerCare;

use Tests\TestCase;
use App\Models\User;
use App\Models\MarketplaceStore;
use App\Models\LegalEntity;
use App\Models\SupportOrganizationMembership;
use App\Models\SupportOrganizationSetting;
use App\Models\SupportServiceAccount;
use App\Services\Support\CustomerCareOrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;

class CustomerCareOrganizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $otherUser;
    protected LegalEntity $org;
    protected LegalEntity $otherOrg;
    protected MarketplaceStore $store;
    protected MarketplaceStore $otherStore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create(['role' => 'admin', 'email' => 'admin@zolm.com', 'is_active' => true]);
        $this->otherUser = User::factory()->create(['role' => 'operator', 'email' => 'other@zolm.com', 'is_active' => true]);

        $this->org = LegalEntity::create([
            'user_id'      => $this->adminUser->id,
            'name'         => 'Test Org A',
            'company_name' => 'Co A',
            'tax_office'   => 'Kadikoy',
            'tax_number'   => '1234567890',
            'address'      => 'Istanbul',
        ]);

        $this->otherOrg = LegalEntity::create([
            'user_id'      => $this->otherUser->id,
            'name'         => 'Test Org B',
            'company_name' => 'Co B',
            'tax_office'   => 'Kadikoy',
            'tax_number'   => '1234567891',
            'address'      => 'Istanbul',
        ]);

        $this->store = MarketplaceStore::create([
            'store_name'      => 'Store A',
            'store_key'       => 'store_a',
            'user_id'         => $this->adminUser->id,
            'legal_entity_id' => $this->org->id,
            'marketplace'     => 'trendyol',
            'is_active'       => true,
        ]);

        $this->otherStore = MarketplaceStore::create([
            'store_name'      => 'Store B',
            'store_key'       => 'store_b',
            'user_id'         => $this->otherUser->id,
            'legal_entity_id' => $this->otherOrg->id,
            'marketplace'     => 'trendyol',
            'is_active'       => true,
        ]);

        Config::set('customer-care.enabled', true);
        Config::set('customer-care.org_center_enabled', true);
    }

    #[Test]
    public function org_route_blocks_when_flag_off(): void
    {
        Config::set('customer-care.org_center_enabled', false);
        $response = $this->actingAs($this->adminUser)->get('/customer-care/organization');
        $response->assertStatus(404);
    }

    #[Test]
    public function user_can_only_see_stores_within_their_organization(): void
    {
        // AdminUser global admin rolüne sahip, otherUser ise normal operator.
        // otherUser için organization membership kuralım
        SupportOrganizationMembership::create([
            'legal_entity_id' => $this->otherOrg->id,
            'user_id'         => $this->otherUser->id,
            'role'            => 'member',
        ]);

        // otherUser kendi organizasyonunun store'una erişebilir
        $this->assertTrue(CustomerCareOrganizationContext::validateStoreOrganizationAccess($this->otherStore->id, $this->otherUser));

        // otherUser yabancı organizasyonun store'una erişemez (fail-closed)
        $this->assertFalse(CustomerCareOrganizationContext::validateStoreOrganizationAccess($this->store->id, $this->otherUser));
    }

    #[Test]
    public function cross_organization_store_access_is_fail_closed(): void
    {
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        // otherUser'ın validateStoreOrganizationAccess = false vereceğinden enforce fırlatmalı
        CustomerCareOrganizationContext::enforceStoreOrganizationAccess($this->store->id, $this->otherUser);
    }

    #[Test]
    public function system_actor_cannot_be_used_outside_organization_scope(): void
    {
        // org için system actor ayarla
        SupportOrganizationSetting::create([
            'legal_entity_id'    => $this->org->id,
            'system_actor_email' => 'system@orga.com',
        ]);

        $systemUser = User::factory()->create([
            'email'     => 'system@orga.com',
            'role'      => 'admin',
            'is_active' => true,
        ]);

        $resolved = CustomerCareOrganizationContext::getSystemActor($this->org->id);
        $this->assertEquals($systemUser->id, $resolved->id);

        // Diğer org için ayarlanmamışsa, config'deki default system actor kontrol edilir
        Config::set('customer-care.system_actor_email', '');
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        CustomerCareOrganizationContext::getSystemActor($this->otherOrg->id);
    }

    #[Test]
    public function service_account_cannot_self_approve_governance(): void
    {
        Config::set('customer-care.governance_enabled', true);

        $saUser = User::factory()->create([
            'email'     => 'sa@orga.com',
            'role'      => 'operator',
            'is_active' => true,
        ]);

        SupportServiceAccount::create([
            'legal_entity_id' => $this->org->id,
            'name'            => 'SA Client',
            'email'           => $saUser->email,
            'is_active'       => true,
        ]);

        $this->assertTrue(CustomerCareOrganizationContext::isServiceAccount($saUser));

        // Governance yetki kontrolü: Servis hesabı approve_risk_action yetkisine sahip olamaz
        $rbacService = app(\App\Services\Support\Security\SupportRbacService::class);
        $this->assertFalse($rbacService->hasPermission($saUser, $this->store->id, 'approve_risk_action'));

        // enforcePermission istisna fırlatmalı
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $rbacService->enforcePermission($saUser, $this->store->id, 'approve_risk_action');
    }

    #[Test]
    public function organization_diagnostic_does_not_leak_pii_or_secrets(): void
    {
        $this->artisan("customer-care:org-diagnostics --organization={$this->org->id} --dry-run")
            ->assertExitCode(0)
            ->expectsOutputToContain('MASKELENDİ');
    }
}
