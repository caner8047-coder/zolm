<?php

namespace Tests\Feature\Hr;

use App\Models\HrHoliday;
use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Attendance\Actions\RecordAttendanceEventAction;
use App\Modules\Hr\Attendance\Enums\AttendanceEventType;
use App\Modules\Hr\Attendance\Models\HrAttendanceAnomaly;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Leave\Enums\LeaveUnit;
use App\Modules\Hr\Leave\Models\HrLeaveRequest;
use App\Modules\Hr\Leave\Models\HrLeaveType;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Shift\Actions\AssignShiftAction;
use App\Modules\Hr\Shift\Actions\PublishShiftWeekAction;
use App\Modules\Hr\Shift\Models\HrShiftTemplate;
use App\Modules\Hr\Timesheet\Actions\CalculateTimesheetPeriodAction;
use App\Modules\Hr\Timesheet\Actions\CloseTimesheetPeriodAction;
use App\Modules\Hr\Timesheet\Actions\ConfirmTimesheetAction;
use App\Modules\Hr\Timesheet\Actions\CreateTimesheetCorrectionAction;
use App\Modules\Hr\Timesheet\Actions\CreateTimesheetPeriodAction;
use App\Modules\Hr\Timesheet\Enums\TimesheetPeriodStatus;
use App\Modules\Hr\Timesheet\Enums\TimesheetDayType;
use App\Modules\Hr\Timesheet\Enums\TimesheetStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TimesheetWorkflowTest extends TestCase
{
    use RefreshHrDatabase;

    private LegalEntity $tenant;
    private HrEmployee $employee;

    protected function setUp(): void
    {
        parent::setUp(); (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
        $roleId = DB::table('roles')->where('slug', 'hr_admin')->value('id'); $user = User::factory()->create(['role_id' => $roleId]); $this->actingAs($user);
        $this->tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Test', 'tax_number' => '4444444444', 'is_active' => true]); app(TenantContext::class)->set($this->tenant);
        $this->employee = HrEmployee::withoutGlobalScope('tenant')->create(['legal_entity_id' => $this->tenant->id, 'employee_number' => 'T001', 'national_id_encrypted' => 'enc', 'national_id_hash' => 'timesheet-h1', 'national_id_last_four' => '0001', 'first_name' => 'Test', 'last_name' => 'Çalışan', 'status' => 'active']);
    }

    public function test_daily_ledger_calculates_and_period_closes_immutably(): void
    {
        $date = now()->subWeek()->startOfWeek()->addDay();
        $template = HrShiftTemplate::create(['legal_entity_id' => $this->tenant->id, 'code' => 'GUN', 'name' => 'Gündüz', 'starts_at' => '08:00', 'ends_at' => '17:00', 'break_minutes' => 60]);
        app(AssignShiftAction::class)->execute($this->employee, $template, $date->toDateString());
        app(PublishShiftWeekAction::class)->execute($date->copy()->startOfWeek(), $date->copy()->endOfWeek());
        $events = app(RecordAttendanceEventAction::class);
        $events->execute($this->employee, AttendanceEventType::CheckIn, $date->copy()->setTime(8, 0), 'manual', 'ts-in', manualReason: 'Test');
        $events->execute($this->employee, AttendanceEventType::BreakStart, $date->copy()->setTime(12, 0), 'manual', 'ts-break-start', manualReason: 'Test');
        $events->execute($this->employee, AttendanceEventType::BreakEnd, $date->copy()->setTime(12, 30), 'manual', 'ts-break-end', manualReason: 'Test');
        $events->execute($this->employee, AttendanceEventType::CheckOut, $date->copy()->setTime(17, 0), 'manual', 'ts-out', manualReason: 'Test');
        $period = app(CreateTimesheetPeriodAction::class)->execute('Test Dönemi', $date->toDateString(), $date->toDateString());
        $this->assertSame(1, app(CalculateTimesheetPeriodAction::class)->execute($period));
        $row = $period->timesheets()->first();
        $this->assertSame(480, $row->scheduled_minutes);
        $this->assertSame(510, $row->worked_minutes);
        $this->assertSame(30, $row->overtime_minutes);
        app(ConfirmTimesheetAction::class)->execute($row);
        app(CloseTimesheetPeriodAction::class)->execute($period->fresh());
        $this->assertSame(TimesheetPeriodStatus::Closed, $period->fresh()->status);
        $this->assertSame(TimesheetStatus::Closed, $row->fresh()->status);
        $this->expectException(HttpException::class);
        app(CalculateTimesheetPeriodAction::class)->execute($period->fresh());
    }

    public function test_closed_timesheet_correction_creates_revision_without_mutating_base(): void
    {
        $date = now()->subDay();
        $period = app(CreateTimesheetPeriodAction::class)->execute('Düzeltme', $date->toDateString(), $date->toDateString());
        app(CalculateTimesheetPeriodAction::class)->execute($period);
        $row = $period->timesheets()->first();
        app(ConfirmTimesheetAction::class)->execute($row);
        app(CloseTimesheetPeriodAction::class)->execute($period->fresh());
        $correction = app(CreateTimesheetCorrectionAction::class)->execute($row->fresh(), ['worked_minutes' => 450, 'overtime_minutes' => 15], 'Turnike kesintisi doğrulandı');
        $this->assertSame(1, $correction->revision_number);
        $this->assertSame(0, $row->fresh()->worked_minutes);
        $this->assertSame(450, $row->fresh()->effective('worked_minutes'));
        $second = app(CreateTimesheetCorrectionAction::class)->execute($row->fresh(), ['worked_minutes' => 460], 'Ek kayıt bulundu');
        $this->assertSame(2, $second->revision_number);
        $this->assertSame(460, $row->fresh()->effective('worked_minutes'));
    }

    public function test_work_on_full_day_leave_does_not_create_false_overtime_and_blocks_confirmation(): void
    {
        $date = now()->subWeeks(3)->startOfWeek()->addDay();
        $this->assignPublishedShift($date);
        $this->recordDay($date, [
            [AttendanceEventType::CheckIn, '08:00'],
            [AttendanceEventType::BreakStart, '12:00'],
            [AttendanceEventType::BreakEnd, '13:00'],
            [AttendanceEventType::CheckOut, '17:00'],
        ], 'leave-work');
        $this->createApprovedLeave($date, LeaveUnit::Day);

        $period = app(CreateTimesheetPeriodAction::class)->execute('İzin Çakışması', $date->toDateString(), $date->toDateString());
        app(CalculateTimesheetPeriodAction::class)->execute($period);
        $row = $period->timesheets()->first();

        $this->assertSame(480, $row->worked_minutes);
        $this->assertSame(480, $row->requested_leave_minutes);
        $this->assertSame(0, $row->leave_minutes);
        $this->assertSame(0, $row->overtime_minutes);
        $this->assertSame(1, $row->anomaly_count);
        $this->assertTrue(HrAttendanceAnomaly::withoutGlobalScope('tenant')->where('employee_id', $this->employee->id)->whereDate('work_date', $date)->where('type', 'work_on_leave')->where('status', 'open')->exists());

        $this->expectException(HttpException::class);
        app(ConfirmTimesheetAction::class)->execute($row);
    }

    public function test_hourly_leave_only_credits_the_unworked_part_of_the_shift(): void
    {
        $date = now()->subWeeks(4)->startOfWeek()->addDay();
        $this->assignPublishedShift($date);
        $this->recordDay($date, [
            [AttendanceEventType::CheckIn, '08:00'],
            [AttendanceEventType::BreakStart, '12:00'],
            [AttendanceEventType::BreakEnd, '13:00'],
            [AttendanceEventType::CheckOut, '15:00'],
        ], 'hourly-leave');
        $this->createApprovedLeave($date, LeaveUnit::Hour, '15:00', '17:00');

        $period = app(CreateTimesheetPeriodAction::class)->execute('Saatlik İzin', $date->toDateString(), $date->toDateString());
        app(CalculateTimesheetPeriodAction::class)->execute($period);
        $row = $period->timesheets()->first();

        $this->assertSame(360, $row->worked_minutes);
        $this->assertSame(120, $row->requested_leave_minutes);
        $this->assertSame(120, $row->leave_minutes);
        $this->assertSame(0, $row->missing_minutes);
        $this->assertSame(0, $row->anomaly_count);
    }

    public function test_multiple_attendance_sessions_do_not_count_the_gap_as_work(): void
    {
        $date = now()->subWeeks(5)->startOfWeek()->addDay();
        $this->assignPublishedShift($date);
        $this->recordDay($date, [
            [AttendanceEventType::CheckIn, '08:00'],
            [AttendanceEventType::CheckOut, '12:00'],
            [AttendanceEventType::CheckIn, '14:00'],
            [AttendanceEventType::CheckOut, '17:00'],
        ], 'split-session');

        $period = app(CreateTimesheetPeriodAction::class)->execute('Çoklu Oturum', $date->toDateString(), $date->toDateString());
        app(CalculateTimesheetPeriodAction::class)->execute($period);
        $row = $period->timesheets()->first();

        $this->assertSame(420, $row->worked_minutes);
        $this->assertSame(60, $row->missing_minutes);
        $this->assertFalse(HrAttendanceAnomaly::withoutGlobalScope('tenant')->where('employee_id', $this->employee->id)->where('type', 'duplicate_check_in')->exists());
    }

    public function test_official_holiday_work_is_classified_separately_from_regular_overtime(): void
    {
        $date = now()->subWeeks(6)->startOfWeek()->addDay();
        HrHoliday::withoutGlobalScope('tenant')->create(['legal_entity_id' => $this->tenant->id, 'name' => 'Test Resmî Tatili', 'date' => $date->toDateString(), 'year' => $date->year, 'type' => 'national', 'is_recurring' => false]);
        $this->assignPublishedShift($date);
        $this->recordDay($date, [
            [AttendanceEventType::CheckIn, '08:00'],
            [AttendanceEventType::BreakStart, '12:00'],
            [AttendanceEventType::BreakEnd, '13:00'],
            [AttendanceEventType::CheckOut, '17:00'],
        ], 'holiday-work');

        $period = app(CreateTimesheetPeriodAction::class)->execute('Resmî Tatil', $date->toDateString(), $date->toDateString());
        app(CalculateTimesheetPeriodAction::class)->execute($period);
        $row = $period->timesheets()->first();

        $this->assertSame(TimesheetDayType::OfficialHoliday, $row->day_type);
        $this->assertSame(480, $row->holiday_work_minutes);
        $this->assertSame(0, $row->overtime_minutes);
    }

    private function assignPublishedShift($date): void
    {
        $template = HrShiftTemplate::create(['legal_entity_id' => $this->tenant->id, 'code' => 'GUN', 'name' => 'Gündüz', 'starts_at' => '08:00', 'ends_at' => '17:00', 'break_minutes' => 60]);
        app(AssignShiftAction::class)->execute($this->employee, $template, $date->toDateString());
        app(PublishShiftWeekAction::class)->execute($date->copy()->startOfWeek(), $date->copy()->endOfWeek());
    }

    private function recordDay($date, array $events, string $prefix): void
    {
        foreach ($events as $index => [$type, $time]) {
            app(RecordAttendanceEventAction::class)->execute($this->employee, $type, $date->copy()->setTimeFromTimeString($time), 'manual', "{$prefix}-{$index}", manualReason: 'Test');
        }
    }

    private function createApprovedLeave($date, LeaveUnit $unit, ?string $startTime = null, ?string $endTime = null): void
    {
        $type = HrLeaveType::create(['legal_entity_id' => $this->tenant->id, 'code' => 'TEST', 'name' => 'Test İzni', 'unit' => $unit->value, 'is_paid' => true, 'is_active' => true]);
        HrLeaveRequest::create([
            'legal_entity_id' => $this->tenant->id,
            'employee_id' => $this->employee->id,
            'leave_type_id' => $type->id,
            'status' => 'approved',
            'start_date' => $date->toDateString(),
            'end_date' => $date->toDateString(),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'requested_amount' => $unit === LeaveUnit::Day ? 1 : 2,
            'unit' => $unit->value,
        ]);
    }
}
