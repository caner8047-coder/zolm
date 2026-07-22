<?php

namespace Tests\Feature\Hr;

use App\Models\HrLicense;
use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Database\Seeders\Hr\HrPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmployeeSelfServiceTenantResolutionTest extends TestCase
{
    use RefreshHrDatabase;

    public function test_linked_employee_resolves_tenant_and_can_open_self_service(): void
    {
        $this->seed(HrPermissionSeeder::class);
        $owner = User::factory()->create(['role' => 'admin']);
        $employeeUser = User::factory()->create(['role' => 'operator']);
        $tenant = LegalEntity::create([
            'user_id' => $owner->id,
            'name' => 'Self Service Tenant',
            'tax_number' => '8888888888',
            'is_active' => true,
        ]);
        HrLicense::create([
            'legal_entity_id' => $tenant->id,
            'module_key' => 'zimmet',
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addYear(),
        ]);
        HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $tenant->id,
            'user_id' => $employeeUser->id,
            'employee_number' => 'SELF00001',
            'national_id_encrypted' => '38888888888',
            'national_id_hash' => hash('sha256', '38888888888'.config('app.key')),
            'national_id_last_four' => '8888',
            'first_name' => 'Self',
            'last_name' => 'Service',
            'status' => 'active',
        ]);
        $employeeRoleId = DB::table('roles')->where('slug', 'hr_employee')->value('id');
        $employeeUser->syncHrRoles([$employeeRoleId]);

        $this->actingAs($employeeUser)
            ->get('/hr/my/assets')
            ->assertOk();
    }
}
