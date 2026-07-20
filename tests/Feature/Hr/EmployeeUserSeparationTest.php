<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Personnel\Models\HrEmploymentRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeUserSeparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_without_user_can_exist(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Test', 'tax_number' => '1111111111', 'is_active' => true]);

        $employee = HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $tenant->id, 'employee_number' => 'EMP00001',
            'national_id_encrypted' => 'encrypted', 'national_id_hash' => 'hash1',
            'national_id_last_four' => '0001', 'first_name' => 'No', 'last_name' => 'User', 'status' => 'active',
        ]);

        $this->assertNull($employee->user_id);
        $this->assertNull($employee->user);
    }

    public function test_user_without_employee_can_exist(): void
    {
        $user = User::factory()->create(['role' => 'operator']);
        $this->assertNotNull($user);
        // Employee modelinde bu user'a ait kayıt yok
        $this->assertNull($user->employee ?? null);
    }

    public function test_employee_can_be_linked_to_user_later(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Test', 'tax_number' => '2222222222', 'is_active' => true]);

        $employee = HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $tenant->id, 'employee_number' => 'EMP00001',
            'national_id_encrypted' => 'encrypted', 'national_id_hash' => 'hash2',
            'national_id_last_four' => '0002', 'first_name' => 'Link', 'last_name' => 'Test', 'status' => 'active',
        ]);

        $this->assertNull($employee->user_id);

        // Sonradan bağla
        $newUser = User::factory()->create(['role' => 'operator']);
        $employee->update(['user_id' => $newUser->id]);

        $this->assertEquals($newUser->id, $employee->fresh()->user_id);
    }

    public function test_terminated_employee_history_preserved(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Test', 'tax_number' => '3333333333', 'is_active' => true]);
        app(TenantContext::class)->set($tenant);

        $employee = HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $tenant->id, 'employee_number' => 'EMP00001',
            'national_id_encrypted' => '33333333333', 'national_id_hash' => hash('sha256', '33333333333'),
            'national_id_last_four' => '3333', 'first_name' => 'Term', 'last_name' => 'Test', 'status' => 'active',
        ]);

        HrEmploymentRecord::withoutGlobalScope('tenant')->create([
            'employee_id' => $employee->id, 'legal_entity_id' => $tenant->id,
            'employment_type' => 'full_time', 'start_date' => '2024-01-01', 'status' => 'active',
        ]);

        // İşten çıkar (soft delete)
        $employee->update(['status' => 'terminated']);
        $employee->delete();

        // Soft delete ile hâlâ görünmeli
        $this->assertNull(HrEmployee::find($employee->id));
        $this->assertNotNull(HrEmployee::withoutGlobalScope('tenant')->withTrashed()->find($employee->id));
    }
}
