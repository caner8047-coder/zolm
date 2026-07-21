<?php

namespace App\Modules\Hr\Timesheet\Actions;

use App\Modules\Hr\Attendance\Enums\AttendanceEventType;
use App\Modules\Hr\Attendance\Models\HrAttendanceEvent;
use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Leave\Enums\LeaveRequestStatus;
use App\Modules\Hr\Leave\Models\HrLeaveRequest;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Shift\Enums\ShiftAssignmentStatus;
use App\Modules\Hr\Shift\Models\HrShiftAssignment;
use App\Modules\Hr\Timesheet\Enums\TimesheetPeriodStatus;
use App\Modules\Hr\Timesheet\Enums\TimesheetStatus;
use App\Modules\Hr\Timesheet\Models\HrTimesheet;
use App\Modules\Hr\Timesheet\Models\HrTimesheetPeriod;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class CalculateTimesheetPeriodAction
{
    public function __construct(private HrAuditService $audit) {}

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
        $start = $date->copy()->startOfDay(); $end = $date->copy()->endOfDay(); $scheduled = 0;
        if ($assignment?->template) {
            $shiftStart = Carbon::parse($date->toDateString().' '.$assignment->template->starts_at);
            $shiftEnd = Carbon::parse($date->toDateString().' '.$assignment->template->ends_at);
            if ($assignment->template->crosses_midnight || $shiftEnd->lte($shiftStart)) $shiftEnd->addDay();
            $scheduled = max(0, $shiftStart->diffInMinutes($shiftEnd) - $assignment->template->break_minutes);
            $start = $shiftStart->copy()->subHours(4); $end = $shiftEnd->copy()->addHours(4);
        }
        $events = HrAttendanceEvent::withoutGlobalScope('tenant')->where('legal_entity_id', $employee->legal_entity_id)->where('employee_id', $employee->id)->whereBetween('occurred_at', [$start, $end])->orderBy('occurred_at')->get();
        $firstIn = $events->firstWhere('event_type', AttendanceEventType::CheckIn)?->occurred_at;
        $lastOut = $events->where('event_type', AttendanceEventType::CheckOut)->last()?->occurred_at;
        $break = $this->breakMinutes($events);
        $worked = $firstIn && $lastOut && $lastOut->gt($firstIn) ? max(0, $firstIn->diffInMinutes($lastOut) - $break) : 0;
        $onLeave = HrLeaveRequest::withoutGlobalScope('tenant')->where('legal_entity_id', $employee->legal_entity_id)->where('employee_id', $employee->id)->where('status', LeaveRequestStatus::Approved->value)->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)->exists();
        $leave = $onLeave ? $scheduled : 0;
        $credited = $worked + $leave;
        return ['timesheet_period_id' => $period->id, 'shift_assignment_id' => $assignment?->id, 'scheduled_minutes' => $scheduled, 'worked_minutes' => $worked, 'break_minutes' => $break, 'leave_minutes' => $leave, 'overtime_minutes' => max(0, $credited - $scheduled), 'missing_minutes' => max(0, $scheduled - $credited), 'first_in_at' => $firstIn, 'last_out_at' => $lastOut, 'calculated_at' => now()];
    }

    private function breakMinutes($events): int
    {
        $start = null; $minutes = 0;
        foreach ($events as $event) {
            if ($event->event_type === AttendanceEventType::BreakStart) $start = $event->occurred_at;
            elseif ($event->event_type === AttendanceEventType::BreakEnd && $start && $event->occurred_at->gt($start)) { $minutes += $start->diffInMinutes($event->occurred_at); $start = null; }
        }
        return $minutes;
    }
}
