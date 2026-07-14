<?php

namespace Tests\Feature\CustomerCare;

use Tests\TestCase;
use App\Models\User;
use App\Models\MarketplaceStore;
use App\Models\SupportRoleAssignment;
use App\Models\SupportApprovalRequest;
use App\Services\Support\Security\SupportRbacService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CustomerCareGovernanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $operatorUser;
    protected MarketplaceStore $storeA;
    protected MarketplaceStore $storeB;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'customer-care.enabled' => true,
            'customer-care.governance_enabled' => true,
            'customer-care.compliance_enabled' => true,
            'customer-care.reliability_enabled' => true,
        ]);

        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@zolm.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->operatorUser = User::create([
            'name' => 'Operator User',
            'email' => 'op@zolm.com',
            'password' => bcrypt('password'),
            'role' => 'operator',
        ]);

        $le = \App\Models\LegalEntity::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Test Legal Entity Name',
            'company_name' => 'Test Holding',
            'tax_office' => 'Kadikoy',
            'tax_number' => '1234567890',
            'address' => 'Istanbul',
        ]);

        $this->storeA = MarketplaceStore::create([
            'legal_entity_id' => $le->id,
            'user_id' => $this->adminUser->id,
            'store_name' => 'Store A',
            'marketplace' => 'trendyol',
            'is_active' => true,
        ]);

        $this->storeB = MarketplaceStore::create([
            'legal_entity_id' => $le->id,
            'user_id' => $this->adminUser->id,
            'store_name' => 'Store B',
            'marketplace' => 'hepsiburada',
            'is_active' => true,
        ]);
    }

    public function test_governance_route_blocks_when_flag_off()
    {
        $this->actingAs($this->adminUser);
        config(['customer-care.governance_enabled' => false]);

        $response = $this->get('/customer-care/governance');
        $response->assertStatus(404);
    }

    public function test_risky_action_fails_closed_when_governance_is_disabled(): void
    {
        config(['customer-care.governance_enabled' => false]);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $this->expectExceptionMessage('iki aşamalı onay merkezi kapalıyken');

        app(SupportRbacService::class)->enforceApproval(
            $this->adminUser,
            $this->storeA->id,
            'dangerous_action'
        );
    }

    public function test_user_permissions_enforced_based_on_store_role()
    {
        $rbac = app(SupportRbacService::class);

        // Assign 'agent' role to operator in Store A
        SupportRoleAssignment::create([
            'user_id' => $this->operatorUser->id,
            'store_id' => $this->storeA->id,
            'role' => 'agent',
        ]);

        // Agent should be able to view inbox, but not rotate secrets
        $this->assertTrue($rbac->hasPermission($this->operatorUser, $this->storeA->id, 'inbox_view'));
        $this->assertFalse($rbac->hasPermission($this->operatorUser, $this->storeA->id, 'secret_rotate'));
    }

    public function test_rbac_rejects_foreign_store_and_inactive_users_before_role_resolution(): void
    {
        $foreignUser = User::create([
            'name' => 'Foreign Operator',
            'email' => 'foreign-operator@example.com',
            'password' => bcrypt('password'),
            'role' => 'operator',
            'is_active' => true,
        ]);
        $inactiveAdmin = User::create([
            'name' => 'Inactive Admin',
            'email' => 'inactive-admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => false,
        ]);
        $rbac = app(SupportRbacService::class);

        $this->assertFalse($rbac->hasPermission($foreignUser, $this->storeA->id, 'inbox_view'));
        $this->assertFalse($rbac->hasPermission($inactiveAdmin, $this->storeA->id, 'manage_roles'));
    }

    public function test_analyst_can_export_but_cannot_reply()
    {
        $rbac = app(SupportRbacService::class);

        SupportRoleAssignment::create([
            'user_id' => $this->operatorUser->id,
            'store_id' => $this->storeA->id,
            'role' => 'analyst',
        ]);

        $this->assertTrue($rbac->hasPermission($this->operatorUser, $this->storeA->id, 'analytics_export'));
        $this->assertFalse($rbac->hasPermission($this->operatorUser, $this->storeA->id, 'agent_reply_send'));
    }

    public function test_request_owner_self_approve_is_blocked()
    {
        $this->actingAs($this->operatorUser);
        $rbac = app(SupportRbacService::class);

        // Assign 'supervisor' role so operator has approval permission but cannot self-approve
        SupportRoleAssignment::create([
            'user_id' => $this->operatorUser->id,
            'store_id' => $this->storeA->id,
            'role' => 'supervisor',
        ]);

        // Try to trigger an approval request
        try {
            $rbac->enforceApproval($this->operatorUser, $this->storeA->id, 'toggle_public_auto_reply', ['status' => true]);
            $this->fail("Expected ApprovalRequiredException was not thrown.");
        } catch (\App\Exceptions\ApprovalRequiredException $e) {
            $this->assertStringContainsString('onay gerekiyor', $e->getMessage());
        }

        // Verify request was created in DB
        $req = SupportApprovalRequest::where('store_id', $this->storeA->id)->first();
        $this->assertNotNull($req);
        $this->assertEquals('pending', $req->status);

        // Try to self-approve via Livewire component simulation
        $component = \Livewire\Livewire::test(\App\Livewire\CustomerCare\Governance::class);
        $component->set('selectedStoreId', $this->storeA->id);
        $component->call('approveRequest', $req->id);

        $component->assertSet('errorMessage', 'Kendinize ait onay taleplerini onaylayamazsınız (Self-Approval Blocked).');
        $this->assertEquals('pending', $req->fresh()->status);
    }

    public function test_cross_store_approval_view_and_approve_is_prevented()
    {
        $this->actingAs($this->adminUser);

        // Create pending request for Store A
        $reqA = SupportApprovalRequest::create([
            'store_id' => $this->storeA->id,
            'requester_id' => $this->operatorUser->id,
            'action_type' => 'webhook_secret_rotate',
            'status' => 'pending',
        ]);

        // Access via Livewire on Store B
        $component = \Livewire\Livewire::test(\App\Livewire\CustomerCare\Governance::class);
        $component->set('selectedStoreId', $this->storeB->id);

        // Trying to approve Store A request on Store B component selection
        $component->call('approveRequest', $reqA->id);

        $component->assertSet('errorMessage', 'Onay talebi bulunamadı veya daha önce sonuçlandırıldı.');
        $this->assertEquals('pending', $reqA->fresh()->status);
    }

    public function test_operator_cannot_assign_roles()
    {
        $this->actingAs($this->operatorUser);

        // Assign 'agent' role assignment for store A to the operatorUser (so default role is not used)
        SupportRoleAssignment::create([
            'user_id' => $this->operatorUser->id,
            'store_id' => $this->storeA->id,
            'role' => 'agent',
        ]);

        $component = \Livewire\Livewire::test(\App\Livewire\CustomerCare\Governance::class);
        $component->set('selectedStoreId', $this->storeA->id);
        $component->set('newUserId', $this->adminUser->id);
        $component->set('newRole', 'supervisor');

        $component->call('assignRole');

        $component->assertSet('errorMessage', 'Bu işlem için yetkiniz bulunmamaktadır.');
        $this->assertDatabaseMissing('support_role_assignments', [
            'user_id' => $this->adminUser->id,
            'store_id' => $this->storeA->id,
            'role' => 'supervisor',
        ]);
    }

    public function test_admin_cannot_assign_store_role_to_user_outside_organization(): void
    {
        $outsider = User::create([
            'name' => 'Outside User',
            'email' => 'outside@example.com',
            'password' => bcrypt('password'),
            'role' => 'operator',
        ]);
        $this->actingAs($this->adminUser);

        $component = \Livewire\Livewire::test(\App\Livewire\CustomerCare\Governance::class)
            ->set('selectedStoreId', $this->storeA->id)
            ->set('newUserId', $outsider->id)
            ->set('newRole', 'agent')
            ->call('assignRole');

        $component->assertSet('errorMessage', 'Seçilen kullanıcı bu mağazanın organizasyonuna ait değil.');
        $this->assertDatabaseMissing('support_role_assignments', [
            'store_id' => $this->storeA->id,
            'user_id' => $outsider->id,
        ]);
    }

    public function test_unauthorized_user_cannot_approve_requests()
    {
        $this->actingAs($this->operatorUser);

        SupportRoleAssignment::create([
            'user_id' => $this->operatorUser->id,
            'store_id' => $this->storeA->id,
            'role' => 'agent', // agent cannot approve risk actions
        ]);

        $req = SupportApprovalRequest::create([
            'store_id' => $this->storeA->id,
            'requester_id' => $this->adminUser->id,
            'action_type' => 'webhook_secret_rotate',
            'status' => 'pending',
        ]);

        $component = \Livewire\Livewire::test(\App\Livewire\CustomerCare\Governance::class);
        $component->set('selectedStoreId', $this->storeA->id);
        $component->call('approveRequest', $req->id);

        $component->assertSet('errorMessage', 'Bu işlem için yetkiniz bulunmamaktadır.');
        $this->assertEquals('pending', $req->fresh()->status);
    }

    public function test_approval_is_not_deleted_after_consumption_and_cannot_be_reused()
    {
        $this->actingAs($this->adminUser);

        // Assign supervisor to operatorUser
        SupportRoleAssignment::create([
            'user_id' => $this->operatorUser->id,
            'store_id' => $this->storeA->id,
            'role' => 'supervisor',
        ]);

        // Create approved request by operatorUser
        $req = SupportApprovalRequest::create([
            'store_id' => $this->storeA->id,
            'requester_id' => $this->adminUser->id,
            'action_type' => 'test_action',
            'status' => 'approved',
            'approved_by' => $this->operatorUser->id,
            'approved_at' => now(),
        ]);

        $rbac = app(SupportRbacService::class);

        // 1. Consume the request
        $rbac->enforceApproval($this->adminUser, $this->storeA->id, 'test_action');

        // Assert record is not deleted
        $freshReq = $req->fresh();
        $this->assertNotNull($freshReq);
        $this->assertEquals('consumed', $freshReq->status);
        $this->assertNotNull($freshReq->consumed_at);
        $this->assertEquals($this->adminUser->id, $freshReq->consumed_by);

        // 2. Reuse should throw ApprovalRequiredException
        $this->expectException(\App\Exceptions\ApprovalRequiredException::class);
        $rbac->enforceApproval($this->adminUser, $this->storeA->id, 'test_action');
    }

    public function test_approval_cannot_be_consumed_for_different_action_details(): void
    {
        SupportRoleAssignment::create([
            'user_id' => $this->operatorUser->id,
            'store_id' => $this->storeA->id,
            'role' => 'supervisor',
        ]);

        $approved = SupportApprovalRequest::create([
            'store_id' => $this->storeA->id,
            'requester_id' => $this->adminUser->id,
            'action_type' => 'replay_deadletters',
            'details_json' => ['type' => 'dispatch'],
            'status' => 'approved',
            'approved_by' => $this->operatorUser->id,
            'approved_at' => now(),
        ]);

        try {
            app(SupportRbacService::class)->enforceApproval(
                $this->adminUser,
                $this->storeA->id,
                'replay_deadletters',
                ['type' => 'integration']
            );
            $this->fail('Farklı aksiyon detayına ait onay tüketilmemeliydi.');
        } catch (\App\Exceptions\ApprovalRequiredException) {
            $this->assertSame('approved', $approved->fresh()->status);
            $this->assertDatabaseHas('support_approval_requests', [
                'store_id' => $this->storeA->id,
                'requester_id' => $this->adminUser->id,
                'action_type' => 'replay_deadletters',
                'status' => 'pending',
            ]);
        }
    }

    public function test_completed_approval_decision_is_immutable(): void
    {
        $this->actingAs($this->adminUser);
        $request = SupportApprovalRequest::create([
            'store_id' => $this->storeA->id,
            'requester_id' => $this->operatorUser->id,
            'action_type' => 'immutable_action',
            'status' => 'approved',
            'approved_by' => $this->adminUser->id,
            'approved_at' => now(),
        ]);

        \Livewire\Livewire::test(\App\Livewire\CustomerCare\Governance::class)
            ->set('selectedStoreId', $this->storeA->id)
            ->call('rejectRequest', $request->id, 'Kararı değiştir')
            ->assertSet('errorMessage', 'Onay talebi bulunamadı veya daha önce sonuçlandırıldı.');

        $this->assertSame('approved', $request->fresh()->status);
    }

    public function test_expired_approval_cannot_be_consumed(): void
    {
        config(['customer-care.approval_max_age_minutes' => 60]);
        $expired = SupportApprovalRequest::create([
            'store_id' => $this->storeA->id,
            'requester_id' => $this->adminUser->id,
            'action_type' => 'expiring_action',
            'status' => 'approved',
            'approved_by' => $this->operatorUser->id,
            'approved_at' => now()->subMinutes(61),
        ]);

        try {
            app(SupportRbacService::class)->enforceApproval(
                $this->adminUser,
                $this->storeA->id,
                'expiring_action'
            );
            $this->fail('Süresi geçmiş onay tüketilmemeliydi.');
        } catch (\App\Exceptions\ApprovalRequiredException) {
            $this->assertSame('approved', $expired->fresh()->status);
        }
    }
}
