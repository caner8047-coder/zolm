<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Organization\Models\HrDepartment;
use App\Modules\Hr\Organization\Models\HrSgkWorkplace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
    }

    public function test_tenant_a_cannot_see_tenant_b_departments(): void
    {
        $userA = User::factory()->create(['role' => 'admin']);
        $userB = User::factory()->create(['role' => 'admin']);

        $tenantA = LegalEntity::create(['user_id' => $userA->id, 'name' => 'Şirket A', 'tax_number' => '1111111111', 'is_active' => true]);
        $tenantB = LegalEntity::create(['user_id' => $userB->id, 'name' => 'Şirket B', 'tax_number' => '2222222222', 'is_active' => true]);

        HrDepartment::create(['legal_entity_id' => $tenantA->id, 'name' => 'Üretim', 'code' => 'URT']);
        HrDepartment::create(['legal_entity_id' => $tenantB->id, 'name' => 'Finans', 'code' => 'FIN']);

        app(TenantContext::class)->set($tenantA);
        $departments = HrDepartment::forCurrentTenant()->get();
        $this->assertCount(1, $departments);
        $this->assertEquals('Üretim', $departments->first()->name);
    }

    public function test_parent_from_different_tenant_cannot_be_selected(): void
    {
        $userA = User::factory()->create(['role' => 'admin']);
        $tenantA = LegalEntity::create(['user_id' => $userA->id, 'name' => 'Şirket A', 'tax_number' => '3333333333', 'is_active' => true]);
        $tenantB = LegalEntity::create(['user_id' => User::factory()->create(['role' => 'admin'])->id, 'name' => 'Şirket B', 'tax_number' => '4444444444', 'is_active' => true]);

        $deptB = HrDepartment::create(['legal_entity_id' => $tenantB->id, 'name' => 'Finans', 'code' => 'FIN']);

        app(TenantContext::class)->set($tenantA);

        // Tenant B departmanını parent olarak atamamalı
        $deptA = HrDepartment::create(['legal_entity_id' => $tenantA->id, 'name' => 'Üretim', 'code' => 'URT']);

        // Global scope nedeniyle DeptB bulunamaz
        $found = HrDepartment::find($deptB->id);
        $this->assertNull($found);
    }

    public function test_code_unique_per_tenant(): void
    {
        $userA = User::factory()->create(['role' => 'admin']);
        $tenantA = LegalEntity::create(['user_id' => $userA->id, 'name' => 'Şirket A', 'tax_number' => '5555555555', 'is_active' => true]);
        $tenantB = LegalEntity::create(['user_id' => User::factory()->create(['role' => 'admin'])->id, 'name' => 'Şirket B', 'tax_number' => '6666666666', 'is_active' => true]);

        HrDepartment::create(['legal_entity_id' => $tenantA->id, 'name' => 'Üretim', 'code' => 'URT']);

        // Aynı code ile farklı tenant'ta oluşturulabilir
        $dept = HrDepartment::create(['legal_entity_id' => $tenantB->id, 'name' => 'Üretim', 'code' => 'URT']);
        $this->assertNotNull($dept);

        // Aynı tenant'ta aynı code ile oluşturulamaz
        $this->expectException(\Illuminate\Database\QueryException::class);
        HrDepartment::create(['legal_entity_id' => $tenantA->id, 'name' => 'Üretim 2', 'code' => 'URT']);
    }
}
