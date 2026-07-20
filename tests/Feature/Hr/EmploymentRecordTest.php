<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Personnel\Models\HrEmploymentRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmploymentRecordTest extends TestCase
{
    use RefreshDatabase;

    private LegalEntity $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create(['role' => 'admin']);
        $this->tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Test', 'tax_number' => '1111111111', 'is_active' => true]);
        app(TenantContext::class)->set($this->tenant);
    }

    public function test_active_employment_record_can_be_created(): void
    {
        $employee = HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $this->tenant->id, 'employee_number' => 'EMP00001',
            'national_id_encrypted' => 'enc', 'national_id_hash' => 'hash1', 'national_id_last_four' => '0001',
            'first_name' => 'Test', 'last_name' => 'User', 'status' => 'active',
        ]);

        $record = HrEmploymentRecord::withoutGlobalScope('tenant')->create([
            'employee_id' => $employee->id, 'legal_entity_id' => $this->tenant->id,
            'employment_type' => 'full_time', 'start_date' => '2024-01-01', 'status' => 'active',
        ]);

        $this->assertNotNull($record);
        $this->assertEquals('active', $record->status);
    }

    public function test_termination_does_not_delete_record(): void
    {
        $employee = HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $this->tenant->id, 'employee_number' => 'EMP00001',
            'national_id_encrypted' => 'enc', 'national_id_hash' => 'hash2', 'national_id_last_four' => '0002',
            'first_name' => 'Term', 'last_name' => 'Test', 'status' => 'active',
        ]);

        $record = HrEmploymentRecord::withoutGlobalScope('tenant')->create([
            'employee_id' => $employee->id, 'legal_entity_id' => $this->tenant->id,
            'employment_type' => 'full_time', 'start_date' => '2024-01-01', 'status' => 'active',
        ]);

        // İşten ayrılma: record silinmez, sadece durum güncellenir
        $record->update(['status' => 'completed', 'end_date' => '2025-01-01']);

        $this->assertNotNull(HrEmploymentRecord::withoutGlobalScope('tenant')->find($record->id));
        $this->assertEquals('completed', $record->fresh()->status);
    }

    public function test_rehire_creates_new_record(): void
    {
        $employee = HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $this->tenant->id, 'employee_number' => 'EMP00001',
            'national_id_encrypted' => 'enc', 'national_id_hash' => 'hash3', 'national_id_last_four' => '0003',
            'first_name' => 'Rehire', 'last_name' => 'Test', 'status' => 'active',
        ]);

        // İlk kayıt
        HrEmploymentRecord::withoutGlobalScope('tenant')->create([
            'employee_id' => $employee->id, 'legal_entity_id' => $this->tenant->id,
            'employment_type' => 'full_time', 'start_date' => '2022-01-01', 'end_date' => '2023-01-01', 'status' => 'completed',
        ]);

        // Yeniden işe giriş: yeni kayıt
        $newRecord = HrEmploymentRecord::withoutGlobalScope('tenant')->create([
            'employee_id' => $employee->id, 'legal_entity_id' => $this->tenant->id,
            'employment_type' => 'full_time', 'start_date' => '2024-06-01', 'status' => 'active',
        ]);

        $records = HrEmploymentRecord::withoutGlobalScope('tenant')->where('employee_id', $employee->id)->get();
        $this->assertCount(2, $records);
    }
}
