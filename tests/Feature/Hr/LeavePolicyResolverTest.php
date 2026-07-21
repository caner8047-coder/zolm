<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Leave\Models\HrLeavePolicy;
use App\Modules\Hr\Leave\Models\HrLeaveType;
use App\Modules\Hr\Leave\Services\LeavePolicyResolver;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Personnel\Models\HrEmploymentRecord;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LeavePolicyResolverTest extends TestCase
{
    use RefreshHrDatabase;

    public function test_most_specific_active_policy_wins_for_employee(): void
    {
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
        $roleId = DB::table('roles')->where('slug', 'hr_admin')->value('id');
        $user = User::factory()->create(['role_id' => $roleId]);
        $this->actingAs($user);

        $tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Test', 'tax_number' => '1111111111', 'is_active' => true]);
        app(TenantContext::class)->set($tenant);
        $employee = HrEmployee::withoutGlobalScope('tenant')->create(['legal_entity_id' => $tenant->id, 'employee_number' => 'E001', 'national_id_encrypted' => 'enc', 'national_id_hash' => 'hash-1', 'national_id_last_four' => '0001', 'first_name' => 'Test', 'last_name' => 'User', 'status' => 'active']);
        HrEmploymentRecord::withoutGlobalScope('tenant')->create(['legal_entity_id' => $tenant->id, 'employee_id' => $employee->id, 'employment_type' => 'full_time', 'start_date' => now()->subYear(), 'status' => 'active']);
        $type = HrLeaveType::create(['legal_entity_id' => $tenant->id, 'code' => 'ANNUAL', 'name' => 'Yıllık İzin', 'unit' => 'day', 'is_paid' => true]);

        HrLeavePolicy::create(['legal_entity_id' => $tenant->id, 'leave_type_id' => $type->id, 'scope' => 'company', 'annual_entitlement' => 14, 'effective_from' => now()->subMonth(), 'is_active' => true]);
        $specific = HrLeavePolicy::create(['legal_entity_id' => $tenant->id, 'leave_type_id' => $type->id, 'scope' => 'employment_type', 'employment_type' => 'full_time', 'annual_entitlement' => 20, 'effective_from' => now()->subMonth(), 'is_active' => true]);

        $resolved = app(LeavePolicyResolver::class)->resolve($employee->fresh(), $type);

        $this->assertNotNull($resolved);
        $this->assertSame($specific->id, $resolved->id);
        $this->assertSame('20.00', $resolved->annual_entitlement);
    }

    public function test_inactive_or_expired_policy_is_not_resolved(): void
    {
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
        $roleId = DB::table('roles')->where('slug', 'hr_admin')->value('id');
        $user = User::factory()->create(['role_id' => $roleId]);
        $this->actingAs($user);
        $tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Test', 'tax_number' => '1111111111', 'is_active' => true]);
        app(TenantContext::class)->set($tenant);
        $employee = HrEmployee::withoutGlobalScope('tenant')->create(['legal_entity_id' => $tenant->id, 'employee_number' => 'E001', 'national_id_encrypted' => 'enc', 'national_id_hash' => 'hash-1', 'national_id_last_four' => '0001', 'first_name' => 'Test', 'last_name' => 'User', 'status' => 'active']);
        HrEmploymentRecord::withoutGlobalScope('tenant')->create(['legal_entity_id' => $tenant->id, 'employee_id' => $employee->id, 'employment_type' => 'full_time', 'start_date' => now()->subYear(), 'status' => 'active']);
        $type = HrLeaveType::create(['legal_entity_id' => $tenant->id, 'code' => 'ANNUAL', 'name' => 'Yıllık İzin', 'unit' => 'day', 'is_paid' => true]);
        HrLeavePolicy::create(['legal_entity_id' => $tenant->id, 'leave_type_id' => $type->id, 'scope' => 'company', 'annual_entitlement' => 14, 'effective_from' => now()->subYear(), 'effective_until' => now()->subDay(), 'is_active' => true]);

        $this->assertNull(app(LeavePolicyResolver::class)->resolve($employee->fresh(), $type));
    }
}
