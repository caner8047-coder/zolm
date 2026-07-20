<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use Tests\Feature\Hr\RefreshHrDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HrPermissionTest extends TestCase
{
    use RefreshHrDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\Hr\HrPermissionSeeder::class);
    }

    public function test_permissions_are_seeded(): void
    {
        $count = DB::table('permissions')->count();
        $this->assertGreaterThan(0, $count);
    }

    public function test_hr_admin_role_has_all_permissions(): void
    {
        $adminRole = DB::table('roles')->where('slug', 'hr_admin')->first();
        $this->assertNotNull($adminRole);

        $permissionCount = DB::table('role_permission')
            ->where('role_id', $adminRole->id)
            ->count();

        $this->assertGreaterThan(0, $permissionCount);
    }

    public function test_normal_admin_without_hr_role_has_no_permission(): void
    {
        // Sistem admin'i ama hr_admin rolü atanmamış
        $user = User::factory()->create(['role' => 'admin']);

        // Blanket bypass yok, izinler açıkça atanmalı
        $this->assertFalse($user->hasHrPermission('hr.dashboard.view'));
        $this->assertFalse($user->hasHrPermission('hr.employees.view'));
        $this->assertFalse($user->hasHrPermission('hr.salary.view'));
        $this->assertFalse($user->hasHrPermission('hr.employees.view_identity'));
    }

    public function test_super_admin_with_hr_role_has_all_permissions(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        // hr_admin rolünü ata
        $adminRole = DB::table('roles')->where('slug', 'hr_admin')->first();
        DB::table('model_has_roles')->insert([
            'role_id' => $adminRole->id,
            'model_id' => $user->id,
            'model_type' => User::class,
        ]);

        $this->assertTrue($user->hasHrPermission('hr.dashboard.view'));
        $this->assertTrue($user->hasHrPermission('hr.employees.view'));
        $this->assertTrue($user->hasHrPermission('hr.salary.view'));
        $this->assertTrue($user->hasHrPermission('hr.employees.view_identity'));
        $this->assertTrue($user->hasHrPermission('hr.settings.manage'));
    }

    public function test_normal_admin_cannot_bypass_sensitive_hr_permission(): void
    {
        // Admin kullanıcısı ama hassas izinler atanmamış
        $user = User::factory()->create(['role' => 'admin']);

        $this->assertFalse($user->hasHrPermission('hr.salary.view'));
        $this->assertFalse($user->hasHrPermission('hr.salary.manage'));
        $this->assertFalse($user->hasHrPermission('hr.employees.view_identity'));
        $this->assertFalse($user->hasHrPermission('hr.employees.view_bank'));
        $this->assertFalse($user->hasHrPermission('hr.employees.terminate'));
        $this->assertFalse($user->hasHrPermission('hr.payroll.approve'));
        $this->assertFalse($user->hasHrPermission('hr.isg.view'));
    }

    public function test_user_without_role_does_not_have_permission(): void
    {
        $user = User::factory()->create(['role' => 'operator']);

        $this->assertFalse($user->hasHrPermission('hr.dashboard.view'));
    }

    public function test_user_with_role_permission_has_permission(): void
    {
        $user = User::factory()->create(['role' => 'manager']);

        $adminRole = DB::table('roles')->where('slug', 'hr_admin')->first();
        DB::table('model_has_roles')->insert([
            'role_id' => $adminRole->id,
            'model_id' => $user->id,
            'model_type' => User::class,
        ]);

        $this->assertTrue($user->hasHrPermission('hr.dashboard.view'));
    }

    public function test_user_with_direct_permission_has_permission(): void
    {
        $user = User::factory()->create(['role' => 'operator']);

        $permission = DB::table('permissions')->where('name', 'hr.dashboard.view')->first();

        DB::table('model_has_permissions')->insert([
            'permission_id' => $permission->id,
            'model_id' => $user->id,
            'model_type' => User::class,
        ]);

        $this->assertTrue($user->hasHrPermission('hr.dashboard.view'));
    }

    public function test_export_requires_permission(): void
    {
        $user = User::factory()->create(['role' => 'operator']);

        $this->actingAs($user);

        $tenant = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Test',
            'tax_number' => '1111111111',
            'is_active' => true,
        ]);

        app(TenantContext::class)->set($tenant);

        $response = $this->get('/hr');
        $response->assertStatus(403);
    }
}
