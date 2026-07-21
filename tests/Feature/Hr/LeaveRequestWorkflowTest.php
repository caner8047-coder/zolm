<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Leave\Actions\ApproveLeaveRequestAction;
use App\Modules\Hr\Leave\Actions\CreateLeaveRequestAction;
use App\Modules\Hr\Leave\Actions\CancelLeaveRequestAction;
use App\Modules\Hr\Leave\Enums\LeaveRequestStatus;
use App\Modules\Hr\Leave\Enums\LeaveTransactionType;
use App\Modules\Hr\Leave\Models\HrLeavePolicy;
use App\Modules\Hr\Leave\Models\HrLeaveType;
use App\Modules\Hr\Leave\Services\LeaveBalanceService;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Personnel\Models\HrEmploymentRecord;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LeaveRequestWorkflowTest extends TestCase
{
    use RefreshHrDatabase;

    private LegalEntity $tenant;
    private HrEmployee $employee;
    private HrLeaveType $leaveType;

    protected function setUp(): void
    {
        parent::setUp();
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
        $roleId = DB::table('roles')->where('slug', 'hr_admin')->value('id');
        $user = User::factory()->create(['role_id' => $roleId]);
        $this->actingAs($user);
        $this->tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Test', 'tax_number' => '1111111111', 'is_active' => true]);
        app(TenantContext::class)->set($this->tenant);
        $this->employee = HrEmployee::withoutGlobalScope('tenant')->create(['legal_entity_id' => $this->tenant->id, 'employee_number' => 'E001', 'national_id_encrypted' => 'enc', 'national_id_hash' => 'hash-1', 'national_id_last_four' => '0001', 'first_name' => 'Test', 'last_name' => 'User', 'status' => 'active']);
        HrEmploymentRecord::withoutGlobalScope('tenant')->create(['legal_entity_id' => $this->tenant->id, 'employee_id' => $this->employee->id, 'employment_type' => 'full_time', 'start_date' => now()->subYear(), 'status' => 'active']);
        $this->leaveType = HrLeaveType::create(['legal_entity_id' => $this->tenant->id, 'code' => 'ANNUAL', 'name' => 'Yıllık İzin', 'unit' => 'day', 'is_paid' => true]);
        HrLeavePolicy::create(['legal_entity_id' => $this->tenant->id, 'leave_type_id' => $this->leaveType->id, 'scope' => 'company', 'annual_entitlement' => 14, 'effective_from' => now()->subYear(), 'is_active' => true]);
        app(LeaveBalanceService::class)->record($this->employee, $this->leaveType, LeaveTransactionType::Accrual, 14, 'initial_entitlement', $this->employee->id, now()->year);
    }

    public function test_hr_approval_consumes_balance_once(): void
    {
        $date = now()->addWeekdays(3)->toDateString();
        $request = app(CreateLeaveRequestAction::class)->execute($this->employee, $this->leaveType, ['start_date' => $date, 'end_date' => $date, 'reason' => 'Dinlenme']);

        $this->assertSame(LeaveRequestStatus::PendingHr, $request->status);
        $approved = app(ApproveLeaveRequestAction::class)->execute($request, 'Uygun');
        $balance = app(LeaveBalanceService::class)->balanceFor($this->employee, $this->leaveType, now()->year);

        $this->assertSame(LeaveRequestStatus::Approved, $approved->status);
        $this->assertSame('13.00', $balance->remaining_amount);
        $this->assertSame(1, $approved->approvalSteps()->where('status', 'approved')->count());
    }

    public function test_overlapping_request_is_rejected(): void
    {
        $date = now()->addWeekdays(3)->toDateString();
        app(CreateLeaveRequestAction::class)->execute($this->employee, $this->leaveType, ['start_date' => $date, 'end_date' => $date]);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(CreateLeaveRequestAction::class)->execute($this->employee, $this->leaveType, ['start_date' => $date, 'end_date' => $date]);
    }

    public function test_cancelling_approved_request_returns_balance_once(): void
    {
        $date = now()->addWeekdays(3)->toDateString();
        $request = app(CreateLeaveRequestAction::class)->execute($this->employee, $this->leaveType, ['start_date' => $date, 'end_date' => $date]);
        app(ApproveLeaveRequestAction::class)->execute($request);
        $cancelled = app(CancelLeaveRequestAction::class)->execute($request, 'Plan değişti');
        $balance = app(LeaveBalanceService::class)->balanceFor($this->employee, $this->leaveType, now()->year);

        $this->assertSame(LeaveRequestStatus::Cancelled, $cancelled->status);
        $this->assertSame('14.00', $balance->remaining_amount);
    }
}
