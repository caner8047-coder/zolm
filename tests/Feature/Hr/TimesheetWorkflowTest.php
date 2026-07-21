<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Attendance\Actions\RecordAttendanceEventAction;
use App\Modules\Hr\Attendance\Enums\AttendanceEventType;
use App\Modules\Hr\Core\Services\TenantContext;
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
}
