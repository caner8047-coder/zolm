<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Leave\Enums\LeaveRequestStatus;
use App\Modules\Hr\Leave\Models\HrLeaveRequest;
use App\Modules\Hr\Leave\Models\HrLeaveType;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Shift\Actions\AssignShiftAction;
use App\Modules\Hr\Shift\Models\HrShiftTemplate;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShiftAssignmentTest extends TestCase
{
    use RefreshHrDatabase;

    private LegalEntity $tenant;
    private HrEmployee $employee;
    private HrShiftTemplate $template;

    protected function setUp(): void
    {
        parent::setUp(); (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
        $roleId = DB::table('roles')->where('slug', 'hr_admin')->value('id'); $user = User::factory()->create(['role_id' => $roleId]); $this->actingAs($user);
        $this->tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Test', 'tax_number' => '1111111111', 'is_active' => true]); app(TenantContext::class)->set($this->tenant);
        $this->employee = HrEmployee::withoutGlobalScope('tenant')->create(['legal_entity_id' => $this->tenant->id, 'employee_number' => 'E001', 'national_id_encrypted' => 'enc', 'national_id_hash' => 'h1', 'national_id_last_four' => '0001', 'first_name' => 'Test', 'last_name' => 'User', 'status' => 'active']);
        $this->template = HrShiftTemplate::create(['legal_entity_id' => $this->tenant->id, 'code' => 'SABAH', 'name' => 'Sabah', 'starts_at' => '08:00', 'ends_at' => '17:00', 'break_minutes' => 60]);
    }

    public function test_assignment_is_tenant_scoped_and_replaces_same_day_plan(): void
    {
        $first = app(AssignShiftAction::class)->execute($this->employee, $this->template, now()->addDay()->toDateString());
        $second = app(AssignShiftAction::class)->execute($this->employee, $this->template, now()->addDay()->toDateString(), 'Güncellendi');
        $this->assertSame($first->id, $second->id); $this->assertSame(1, $this->employee->fresh()->shiftAssignments()->count()); $this->assertSame('Güncellendi', $second->note);
    }

    public function test_approved_leave_blocks_shift_assignment(): void
    {
        $date = now()->addDay()->toDateString();
        $type = HrLeaveType::create(['legal_entity_id' => $this->tenant->id, 'code' => 'ANNUAL', 'name' => 'Yıllık İzin', 'unit' => 'day', 'is_paid' => true]);
        HrLeaveRequest::create(['legal_entity_id' => $this->tenant->id, 'employee_id' => $this->employee->id, 'leave_type_id' => $type->id, 'status' => LeaveRequestStatus::Approved, 'start_date' => $date, 'end_date' => $date, 'requested_amount' => 1, 'unit' => 'day']);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(AssignShiftAction::class)->execute($this->employee, $this->template, $date);
    }
}
