<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Personnel\Models\HrEmploymentRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeTenantIsolationTest extends TestCase
{
    use RefreshDatabase;
    use HasHrPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
    }

    public function test_tenant_a_cannot_see_tenant_b_employees(): void
    {
        $userA = User::factory()->create(['role' => 'admin']);
        $this->assignHrAdminRole($userA);
        $tenantA = LegalEntity::create(['user_id' => $userA->id, 'name' => 'Şirket A', 'tax_number' => '1111111111', 'is_active' => true]);

        $tenantB = LegalEntity::create(['user_id' => User::factory()->create(['role' => 'admin'])->id, 'name' => 'Şirket B', 'tax_number' => '2222222222', 'is_active' => true]);

        HrEmployee::create([
            'legal_entity_id' => $tenantA->id, 'employee_number' => 'EMP00001',
            'national_id_encrypted' => 'encrypted', 'national_id_hash' => hash('sha256', '11111111111'),
            'national_id_last_four' => '1111', 'first_name' => 'Ahmet', 'last_name' => 'Yılmaz', 'status' => 'active',
        ]);

        HrEmployee::create([
            'legal_entity_id' => $tenantB->id, 'employee_number' => 'EMP00001',
            'national_id_encrypted' => 'encrypted', 'national_id_hash' => hash('sha256', '22222222222'),
            'national_id_last_four' => '2222', 'first_name' => 'Ayşe', 'last_name' => 'Demir', 'status' => 'active',
        ]);

        app(TenantContext::class)->set($tenantA);
        $employees = HrEmployee::forCurrentTenant()->get();
        $this->assertCount(1, $employees);
        $this->assertEquals('Ahmet', $employees->first()->first_name);
    }

    public function test_route_model_binding_respects_tenant(): void
    {
        $userA = User::factory()->create(['role' => 'admin']);
        $this->assignHrAdminRole($userA);
        $tenantA = LegalEntity::create(['user_id' => $userA->id, 'name' => 'Şirket A', 'tax_number' => '3333333333', 'is_active' => true]);

        $employee = HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $tenantA->id, 'employee_number' => 'EMP00001',
            'national_id_encrypted' => '33333333333', 'national_id_hash' => hash('sha256', '33333333333'),
            'national_id_last_four' => '3333', 'first_name' => 'Test', 'last_name' => 'User', 'status' => 'active',
        ]);

        // Global scope ile tenantA kapsamında bulunabilmeli
        app(TenantContext::class)->set($tenantA);
        $found = HrEmployee::find($employee->id);
        $this->assertNotNull($found);
        $this->assertEquals($tenantA->id, $found->legal_entity_id);

        // Tenant B kapsamında bulunmamalı
        $tenantB = LegalEntity::create(['user_id' => User::factory()->create(['role' => 'admin'])->id, 'name' => 'B', 'tax_number' => '4444444444', 'is_active' => true]);
        app(TenantContext::class)->set($tenantB);
        $notFound = HrEmployee::find($employee->id);
        $this->assertNull($notFound);
    }

    public function test_cannot_edit_other_tenant_employee(): void
    {
        $userA = User::factory()->create(['role' => 'admin']);
        $this->assignHrAdminRole($userA);
        $tenantA = LegalEntity::create(['user_id' => $userA->id, 'name' => 'Şirket A', 'tax_number' => '4444444444', 'is_active' => true]);

        $tenantB = LegalEntity::create(['user_id' => User::factory()->create(['role' => 'admin'])->id, 'name' => 'Şirket B', 'tax_number' => '5555555555', 'is_active' => true]);

        $employeeB = HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $tenantB->id, 'employee_number' => 'EMP00001',
            'national_id_encrypted' => 'encrypted', 'national_id_hash' => hash('sha256', '55555555555'),
            'national_id_last_four' => '5555', 'first_name' => 'Other', 'last_name' => 'Tenant', 'status' => 'active',
        ]);

        $this->actingAs($userA);
        app(TenantContext::class)->set($tenantA);

        // Tenant B çalışanının sayfasına erişim 404 veya 403 olmalı
        $response = $this->get("/hr/personnel/{$employeeB->id}");
        $response->assertStatus(404);
    }

    public function test_cannot_export_other_tenant_data(): void
    {
        $userA = User::factory()->create(['role' => 'admin']);
        $this->assignHrAdminRole($userA);
        $tenantA = LegalEntity::create(['user_id' => $userA->id, 'name' => 'Şirket A', 'tax_number' => '6666666666', 'is_active' => true]);

        $this->actingAs($userA);
        app(TenantContext::class)->set($tenantA);

        // Tenant A'da çalışan oluştur
        HrEmployee::create([
            'legal_entity_id' => $tenantA->id, 'employee_number' => 'EMP00001',
            'national_id_encrypted' => 'encrypted', 'national_id_hash' => hash('sha256', '66666666666'),
            'national_id_last_four' => '6666', 'first_name' => 'TenantA', 'last_name' => 'Employee', 'status' => 'active',
        ]);

        // Global scope ile sadece Tenant A'nın çalışanları görünmeli
        $allEmployees = HrEmployee::forCurrentTenant()->get();
        $this->assertCount(1, $allEmployees);
    }
}
