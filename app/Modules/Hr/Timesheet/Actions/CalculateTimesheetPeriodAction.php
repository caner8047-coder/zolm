<?php

namespace App\Modules\Hr\Timesheet\Actions;

use App\Models\HrHoliday;
use App\Modules\Hr\Attendance\Models\HrAttendanceEvent;
use App\Modules\Hr\Attendance\Models\HrAttendanceAnomaly;
use App\Modules\Hr\Attendance\Services\AttendanceAnomalyService;
use App\Modules\Hr\Attendance\Services\AttendanceIntervalCalculator;
use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Leave\Enums\LeaveRequestStatus;
use App\Modules\Hr\Leave\Enums\LeaveUnit;
use App\Modules\Hr\Leave\Models\HrLeaveRequest;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Shift\Enums\ShiftAssignmentStatus;
use App\Modules\Hr\Shift\Models\HrShiftAssignment;
use App\Modules\Hr\Timesheet\Enums\TimesheetPeriodStatus;
use App\Modules\Hr\Timesheet\Enums\TimesheetDayType;
use App\Modules\Hr\Timesheet\Enums\TimesheetStatus;
use App\Modules\Hr\Timesheet\Models\HrTimesheet;
use App\Modules\Hr\Timesheet\Models\HrTimesheetPeriod;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class CalculateTimesheetPeriodAction
{
    public function __construct(
        private HrAuditService $audit,
        private AttendanceIntervalCalculator $intervals,
        private AttendanceAnomalyService $anomalies,
    ) {}

    public function execute(HrTimesheetPeriod $period): int
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.timesheet.confirm'), 403);
        $tenantId = app(TenantContext::class)->getId();
        abort_unless($period->legal_entity_id === $tenantId, 404);
        abort_if($period->status === TimesheetPeriodStatus::Closed, 422, 'Kapanmış puantaj dönemi yeniden hesaplanamaz.');

        $employees = HrEmployee::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->active()->get();
        $count = DB::transaction(function () use ($period, $employees, $tenantId) {
            $count = 0;
            foreach ($employees as $employee) {
                foreach (CarbonPeriod::create($period->starts_on, $period->ends_on) as $date) {
                    $existing = HrTimesheet::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('employee_id', $employee->id)->whereDate('work_date', $date)->lockForUpdate()->first();
                    if ($existing && $existing->status !== TimesheetStatus::Draft) continue;
                    $values = $this->calculateDay($employee, Carbon::instance($date), $period);
                    if ($existing) $existing->update($values + ['source_revision' => $existing->source_revision + 1]);
                    else HrTimesheet::create($values + ['legal_entity_id' => $tenantId, 'timesheet_period_id' => $period->id, 'employee_id' => $employee->id, 'work_date' => $date->toDateString(), 'status' => TimesheetStatus::Draft, 'source_revision' => 1]);
                    $count++;
                }
            }
            $period->update(['status' => TimesheetPeriodStatus::Calculated, 'calculated_at' => now(), 'calculated_by' => auth()->id()]);
            return $count;
        });

        $this->audit->log('timesheet_period_calculated', $period, null, ['row_count' => $count]);
        return $count;
    }

    private function calculateDay(HrEmployee $employee, Carbon $date, HrTimesheetPeriod $period): array
    {
        $assignment = HrShiftAssignment::withoutGlobalScope('tenant')->where('legal_entity_id', $employee->legal_entity_id)->where('employee_id', $employee->id)->whereDate('shift_date', $date)->where('status', ShiftAssignmentStatus::Published->value)->with('template')->first();
        $start = $date->copy()->startOfDay(); $end = $date->copy()->endOfDay(); $scheduled = 0; $shiftStart = null; $shiftEnd = null;
        if ($assignment?->template) {
            $shiftStart = Carbon::parse($date->toDateString().' '.$assignment->template->starts_at);
            $shiftEnd = Carbon::parse($date->toDateString().' '.$assignment->template->ends_at);
            if ($assignment->template->crosses_midnight || $shiftEnd->lte($shiftStart)) $shiftEnd->addDay();
            $scheduled = max(0, $shiftStart->diffInMinutes($shiftEnd) - $assignment->template->break_minutes);
            $start = $shiftStart->copy()->subHours(4); $end = $shiftEnd->copy()->addHours(4);
        }
        $events = HrAttendanceEvent::withoutGlobalScope('tenant')->where('legal_entity_id', $employee->legal_entity_id)->where('employee_id', $employee->id)->whereBetween('occurred_at', [$start, $end])->orderBy('occurred_at')->get();
        $attendance = $this->intervals->calculate($events);
        $leaves = HrLeaveRequest::withoutGlobalScope('tenant')->where('legal_entity_id', $employee->legal_entity_id)->where('employee_id', $employee->id)->where('status', LeaveRequestStatus::Approved->value)->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)->with('leaveType')->get();
        $leave = $this->leaveSummary($leaves, $date, $shiftStart, $shiftEnd, $scheduled, $attendance['worked_minutes'], $attendance['work_intervals']);
        $holiday = HrHoliday::withoutGlobalScope('tenant')->where('legal_entity_id', $employee->legal_entity_id)->whereDate('date', $date)->first();
        $dayType = $holiday ? TimesheetDayType::OfficialHoliday : ($date->isWeekend() ? TimesheetDayType::WeeklyRest : TimesheetDayType::Workday);
        $credited = min($scheduled, $attendance['worked_minutes'] + $leave['applied_minutes']);
        $flags = $attendance['flags'];
        if ($leave['overlap_minutes'] > 0) $flags[] = 'leave_work_overlap';
        if ($dayType === TimesheetDayType::OfficialHoliday && $attendance['worked_minutes'] > 0) $flags[] = 'official_holiday_work';
        if ($dayType === TimesheetDayType::WeeklyRest && $attendance['worked_minutes'] > 0) $flags[] = 'weekly_rest_work';

        $this->anomalies->evaluateDay($employee, $date);
        $anomalyCount = HrAttendanceAnomaly::withoutGlobalScope('tenant')->where('legal_entity_id', $employee->legal_entity_id)->where('employee_id', $employee->id)->whereDate('work_date', $date)->where('status', 'open')->count();

        return [
            'timesheet_period_id' => $period->id,
            'shift_assignment_id' => $assignment?->id,
            'day_type' => $dayType->value,
            'holiday_id' => $holiday?->id,
            'scheduled_minutes' => $scheduled,
            'worked_minutes' => $attendance['worked_minutes'],
            'break_minutes' => $attendance['break_minutes'],
            'leave_minutes' => $leave['applied_minutes'],
            'requested_leave_minutes' => $leave['requested_minutes'],
            'overtime_minutes' => $dayType === TimesheetDayType::Workday ? max(0, $attendance['worked_minutes'] - $scheduled) : 0,
            'holiday_work_minutes' => $dayType === TimesheetDayType::OfficialHoliday ? $attendance['worked_minutes'] : 0,
            'weekly_rest_work_minutes' => $dayType === TimesheetDayType::WeeklyRest ? $attendance['worked_minutes'] : 0,
            'missing_minutes' => max(0, $scheduled - $credited),
            'anomaly_count' => $anomalyCount,
            'first_in_at' => $attendance['first_in_at'],
            'last_out_at' => $attendance['last_out_at'],
            'leave_request_ids' => $leave['request_ids'],
            'attendance_event_ids' => $attendance['event_ids'],
            'calculation_flags' => array_values(array_unique($flags)),
            'calculation_version' => 2,
            'calculated_at' => now(),
        ];
    }

    private function leaveSummary($leaves, Carbon $date, ?Carbon $shiftStart, ?Carbon $shiftEnd, int $scheduled, int $worked, array $workIntervals): array
    {
        $requested = 0;
        $overlap = 0;
        foreach ($leaves as $leave) {
            if ($leave->unit === LeaveUnit::Day) {
                $requested += $scheduled;
                $overlap += $worked;
                continue;
            }
            if (! $shiftStart || ! $shiftEnd || ! $leave->start_time || ! $leave->end_time) {
                continue;
            }
            $leaveStart = Carbon::parse($date->toDateString().' '.$leave->start_time);
            $leaveEnd = Carbon::parse($date->toDateString().' '.$leave->end_time);
            $overlapStart = $leaveStart->greaterThan($shiftStart) ? $leaveStart : $shiftStart;
            $overlapEnd = $leaveEnd->lessThan($shiftEnd) ? $leaveEnd : $shiftEnd;
            if ($overlapEnd->gt($overlapStart)) $requested += $overlapStart->diffInMinutes($overlapEnd);
            foreach ($workIntervals as $interval) {
                $workStart = Carbon::parse($interval['starts_at']);
                $workEnd = Carbon::parse($interval['ends_at']);
                $workOverlapStart = $workStart->greaterThan($leaveStart) ? $workStart : $leaveStart;
                $workOverlapEnd = $workEnd->lessThan($leaveEnd) ? $workEnd : $leaveEnd;
                if ($workOverlapEnd->gt($workOverlapStart)) $overlap += $workOverlapStart->diffInMinutes($workOverlapEnd);
            }
        }
        $requested = min($scheduled, $requested);
        $availableCredit = max(0, $scheduled - min($scheduled, $worked));
        $applied = min($requested, $availableCredit);

        return [
            'requested_minutes' => $requested,
            'applied_minutes' => $applied,
            'overlap_minutes' => $overlap,
            'request_ids' => $leaves->pluck('id')->values()->all(),
        ];
    }
}
