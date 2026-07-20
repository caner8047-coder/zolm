<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmployeePermissionTest extends TestCase
{
    use RefreshDatabase;
    use HasHrPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
    }

    public function test_normal_admin_cannot_bypass_sensitive_hr_permission(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->assertFalse($user->hasHrPermission('hr.salary.view'));
        $this->assertFalse($user->hasHrPermission('hr.employees.view_identity'));
        $this->assertFalse($user->hasHrPermission('hr.employees.view_bank'));
        $this->assertFalse($user->hasHrPermission('hr.employees.terminate'));
    }

    public function test_hr_admin_has_all_permissions(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->assignHrAdminRole($user);

        $this->assertTrue($user->hasHrPermission('hr.employees.view'));
        $this->assertTrue($user->hasHrPermission('hr.employees.create'));
        $this->assertTrue($user->hasHrPermission('hr.employees.update'));
        $this->assertTrue($user->hasHrPermission('hr.employees.terminate'));
        $this->assertTrue($user->hasHrPermission('hr.employees.view_identity'));
        $this->assertTrue($user->hasHrPermission('hr.employees.view_bank'));
        $this->assertTrue($user->hasHrPermission('hr.employees.export'));
        $this->assertTrue($user->hasHrPermission('hr.org_structure.view'));
        $this->assertTrue($user->hasHrPermission('hr.org_structure.manage'));
    }

    public function test_operator_without_hr_role_cannot_access(): void
    {
        $user = User::factory()->create(['role' => 'operator']);
        $tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Test', 'tax_number' => '1111111111', 'is_active' => true]);

        $this->actingAs($user);
        app(TenantContext::class)->set($tenant);

        $response = $this->get('/hr/personnel');
        $response->assertStatus(403);
    }
}
