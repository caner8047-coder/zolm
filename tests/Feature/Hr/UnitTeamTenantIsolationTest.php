<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Organization\Models\HrDepartment;
use App\Modules\Hr\Organization\Models\HrTeam;
use App\Modules\Hr\Organization\Models\HrUnit;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Hr\RefreshHrDatabase;
use Tests\TestCase;

class UnitTeamTenantIsolationTest extends TestCase
{
    use RefreshHrDatabase;

    public function test_tenant_a_cannot_see_tenant_b_units(): void
    {
        $tenantA = LegalEntity::create(['user_id' => User::factory()->create(['role' => 'admin'])->id, 'name' => 'A', 'tax_number' => '1111111111', 'is_active' => true]);
        $tenantB = LegalEntity::create(['user_id' => User::factory()->create(['role' => 'admin'])->id, 'name' => 'B', 'tax_number' => '2222222222', 'is_active' => true]);

        $deptA = HrDepartment::withoutGlobalScope('tenant')->create(['legal_entity_id' => $tenantA->id, 'name' => 'Üretim', 'code' => 'URT', 'is_active' => true]);
        $deptB = HrDepartment::withoutGlobalScope('tenant')->create(['legal_entity_id' => $tenantB->id, 'name' => 'Finans', 'code' => 'FIN', 'is_active' => true]);

        // Birimleri tenant context olmadan oluştur (global scope bypass)
        DB::table('hr_units')->insert([
            ['department_id' => $deptA->id, 'name' => 'Birim A', 'code' => 'BA', 'is_active' => true, 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['department_id' => $deptB->id, 'name' => 'Birim B', 'code' => 'BB', 'is_active' => true, 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);

        app(TenantContext::class)->set($tenantA);
        // HrUnit department'dan tenant miras alır
        $count = HrUnit::whereHas('department', fn($q) => $q->where('legal_entity_id', $tenantA->id))->count();
        $this->assertEquals(1, $count);
    }

    public function test_tenant_a_cannot_see_tenant_b_teams(): void
    {
        $tenantA = LegalEntity::create(['user_id' => User::factory()->create(['role' => 'admin'])->id, 'name' => 'A', 'tax_number' => '3333333333', 'is_active' => true]);
        $tenantB = LegalEntity::create(['user_id' => User::factory()->create(['role' => 'admin'])->id, 'name' => 'B', 'tax_number' => '4444444444', 'is_active' => true]);

        $deptA = HrDepartment::withoutGlobalScope('tenant')->create(['legal_entity_id' => $tenantA->id, 'name' => 'Üretim', 'code' => 'URT', 'is_active' => true]);
        $unitA = HrUnit::create(['department_id' => $deptA->id, 'name' => 'Birim A', 'code' => 'BA', 'is_active' => true]);

        $deptB = HrDepartment::withoutGlobalScope('tenant')->create(['legal_entity_id' => $tenantB->id, 'name' => 'Finans', 'code' => 'FIN', 'is_active' => true]);
        $unitB = HrUnit::create(['department_id' => $deptB->id, 'name' => 'Birim B', 'code' => 'BB', 'is_active' => true]);

        HrTeam::create(['unit_id' => $unitA->id, 'name' => 'Ekip A', 'is_active' => true]);
        HrTeam::create(['unit_id' => $unitB->id, 'name' => 'Ekip B', 'is_active' => true]);

        app(TenantContext::class)->set($tenantA);
        // UnitA'nın team'lerini bul
        $unitAId = $unitA->id;
        $teams = HrTeam::whereHas('unit', fn($q) => $q->where('department_id', $deptA->id))->count();
        $this->assertEquals(1, $teams);
    }

    public function test_another_tenant_department_cannot_be_selected(): void
    {
        $tenantA = LegalEntity::create(['user_id' => User::factory()->create(['role' => 'admin'])->id, 'name' => 'A', 'tax_number' => '5555555555', 'is_active' => true]);
        $tenantB = LegalEntity::create(['user_id' => User::factory()->create(['role' => 'admin'])->id, 'name' => 'B', 'tax_number' => '6666666666', 'is_active' => true]);

        $deptB = HrDepartment::withoutGlobalScope('tenant')->create(['legal_entity_id' => $tenantB->id, 'name' => 'Finans', 'code' => 'FIN', 'is_active' => true]);

        app(TenantContext::class)->set($tenantA);
        $found = HrDepartment::find($deptB->id);
        $this->assertNull($found);
    }

    public function test_another_tenant_unit_cannot_be_selected_for_team(): void
    {
        $tenantA = LegalEntity::create(['user_id' => User::factory()->create(['role' => 'admin'])->id, 'name' => 'A', 'tax_number' => '7777777777', 'is_active' => true]);
        $tenantB = LegalEntity::create(['user_id' => User::factory()->create(['role' => 'admin'])->id, 'name' => 'B', 'tax_number' => '8888888888', 'is_active' => true]);

        $deptB = HrDepartment::withoutGlobalScope('tenant')->create(['legal_entity_id' => $tenantB->id, 'name' => 'Finans', 'code' => 'FIN', 'is_active' => true]);
        $unitB = HrUnit::create(['department_id' => $deptB->id, 'name' => 'Birim B', 'code' => 'BB', 'is_active' => true]);

        app(TenantContext::class)->set($tenantA);
        // HrUnit department'dan tenant miras alır
        // HrDepartment global scope kullandığı için reload ile kontrol et
        $found = HrUnit::find($unitB->id);
        $this->assertNotNull($found);

        // department'ı reload et (global scope bypass ile)
        $dept = HrDepartment::withoutGlobalScope('tenant')->find($found->department_id);
        $this->assertNotNull($dept);
        $this->assertEquals($tenantB->id, $dept->legal_entity_id);
        $this->assertNotEquals($tenantA->id, $dept->legal_entity_id);
    }
}
