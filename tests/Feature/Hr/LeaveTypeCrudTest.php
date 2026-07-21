<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Leave\Models\HrLeaveType;
use Illuminate\Database\QueryException;
use Tests\TestCase;

class LeaveTypeCrudTest extends TestCase
{
    use RefreshHrDatabase;

    private LegalEntity $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $this->tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Test', 'tax_number' => '1111111111', 'is_active' => true]);
        app(TenantContext::class)->set($this->tenant);
    }

    public function test_code_is_unique_per_tenant(): void
    {
        HrLeaveType::create(['legal_entity_id' => $this->tenant->id, 'code' => 'ANNUAL', 'name' => 'Yıllık İzin', 'unit' => 'day']);
        $this->expectException(QueryException::class);
        HrLeaveType::create(['legal_entity_id' => $this->tenant->id, 'code' => 'ANNUAL', 'name' => 'Başka', 'unit' => 'day']);
    }

    public function test_same_code_is_allowed_for_another_tenant(): void
    {
        HrLeaveType::create(['legal_entity_id' => $this->tenant->id, 'code' => 'ANNUAL', 'name' => 'Yıllık İzin', 'unit' => 'day']);
        $other = LegalEntity::create(['user_id' => User::factory()->create()->id, 'name' => 'Other', 'tax_number' => '2222222222', 'is_active' => true]);
        $type = HrLeaveType::withoutGlobalScope('tenant')->create(['legal_entity_id' => $other->id, 'code' => 'ANNUAL', 'name' => 'Yıllık İzin', 'unit' => 'day']);
        $this->assertSame($other->id, $type->legal_entity_id);
    }

    public function test_type_is_deactivated_instead_of_deleted(): void
    {
        $type = HrLeaveType::create(['legal_entity_id' => $this->tenant->id, 'code' => 'ANNUAL', 'name' => 'Yıllık İzin', 'unit' => 'day']);
        $type->update(['is_active' => false]);
        $this->assertDatabaseHas('hr_leave_types', ['id' => $type->id, 'is_active' => false]);
    }
}
