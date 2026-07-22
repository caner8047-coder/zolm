<?php

namespace App\Modules\Hr\Attendance\Services;

use App\Modules\Hr\Attendance\Enums\AttendanceEventType;
use App\Modules\Hr\Attendance\Models\HrAttendanceAnomaly;
use App\Modules\Hr\Attendance\Models\HrAttendanceEvent;
use App\Modules\Hr\Leave\Enums\LeaveRequestStatus;
use App\Modules\Hr\Leave\Enums\LeaveUnit;
use App\Modules\Hr\Leave\Models\HrLeaveRequest;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Shift\Enums\ShiftAssignmentStatus;
use App\Modules\Hr\Shift\Models\HrShiftAssignment;
use Carbon\Carbon;

class AttendanceAnomalyService
{
    private const GRACE_MINUTES = 5;

    public function __construct(private AttendanceIntervalCalculator $intervals) {}

    public function evaluateDay(HrEmployee $employee, Carbon|string $workDate): void
    {
        $date = Carbon::parse($workDate)->startOfDay();
        $tenantId = $employee->legal_entity_id;
        $assignment = HrShiftAssignment::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->where('employee_id', $employee->id)
            ->whereDate('shift_date', $date)
            ->where('status', ShiftAssignmentStatus::Published->value)
            ->with('template')
            ->first();

        $windowStart = $date->copy();
        $windowEnd = $date->copy()->endOfDay();
        $shiftStart = null;
        $shiftEnd = null;

        if ($assignment?->template) {
            $shiftStart = Carbon::parse($date->toDateString().' '.$assignment->template->starts_at);
            $shiftEnd = Carbon::parse($date->toDateString().' '.$assignment->template->ends_at);
            if ($assignment->template->crosses_midnight || $shiftEnd->lte($shiftStart)) {
                $shiftEnd->addDay();
            }
            $windowStart = $shiftStart->copy()->subHours(4);
            $windowEnd = $shiftEnd->copy()->addHours(4);
        }

        $events = HrAttendanceEvent::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->where('employee_id', $employee->id)
            ->whereBetween('occurred_at', [$windowStart, $windowEnd])
            ->orderBy('occurred_at')
            ->get();

        $checkIns = $events->where('event_type', AttendanceEventType::CheckIn);
        $checkOuts = $events->where('event_type', AttendanceEventType::CheckOut);
        $attendance = $this->intervals->calculate($events);
        $leaves = HrLeaveRequest::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->where('employee_id', $employee->id)
            ->where('status', LeaveRequestStatus::Approved->value)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->get();
        $detected = [];

        if (in_array('duplicate_check_in', $attendance['flags'], true)) {
            $detected['duplicate_check_in'] = ['severity' => 'info', 'details' => ['count' => $checkIns->count()]];
        }
        if (in_array('unmatched_check_out', $attendance['flags'], true)) {
            $detected['check_out_without_check_in'] = ['severity' => 'warning', 'details' => ['first_check_out' => $checkOuts->first()->occurred_at->toIso8601String()]];
        }

        if ($shiftStart && $shiftEnd) {
            $firstIn = $checkIns->first()?->occurred_at;
            $lastOut = $checkOuts->last()?->occurred_at;
            $now = now();

            if (!$firstIn && $now->gt($shiftStart->copy()->addMinutes(self::GRACE_MINUTES)) && ! $this->leaveCovers($leaves, $date, $shiftStart, $shiftEnd)) {
                $detected['missing_check_in'] = ['severity' => 'critical', 'details' => ['expected_at' => $shiftStart->toIso8601String()]];
            } elseif ($firstIn && $firstIn->gt($shiftStart->copy()->addMinutes(self::GRACE_MINUTES)) && ! $this->leaveCovers($leaves, $date, $shiftStart, $firstIn)) {
                $detected['late_arrival'] = ['severity' => 'warning', 'details' => ['expected_at' => $shiftStart->toIso8601String(), 'actual_at' => $firstIn->toIso8601String(), 'minutes' => $shiftStart->diffInMinutes($firstIn)]];
            }

            if (!$lastOut && $now->gt($shiftEnd->copy()->addMinutes(self::GRACE_MINUTES)) && ! $this->leaveCovers($leaves, $date, $shiftStart, $shiftEnd)) {
                $detected['missing_check_out'] = ['severity' => 'critical', 'details' => ['expected_at' => $shiftEnd->toIso8601String()]];
            } elseif ($lastOut && $lastOut->lt($shiftEnd->copy()->subMinutes(self::GRACE_MINUTES)) && ! $this->leaveCovers($leaves, $date, $lastOut, $shiftEnd)) {
                $detected['early_departure'] = ['severity' => 'warning', 'details' => ['expected_at' => $shiftEnd->toIso8601String(), 'actual_at' => $lastOut->toIso8601String(), 'minutes' => $lastOut->diffInMinutes($shiftEnd)]];
            }
        }

        $leaveOverlap = $this->leaveWorkOverlap($leaves, $date, $attendance['work_intervals']);
        if ($leaveOverlap > 0) {
            $detected['work_on_leave'] = [
                'severity' => 'critical',
                'details' => [
                    'minutes' => $leaveOverlap,
                    'leave_request_ids' => $leaves->pluck('id')->values()->all(),
                ],
            ];
        }

        foreach ($detected as $type => $data) {
            $anomaly = HrAttendanceAnomaly::withoutGlobalScope('tenant')
                ->where('legal_entity_id', $tenantId)
                ->where('employee_id', $employee->id)
                ->whereDate('work_date', $date)
                ->where('type', $type)
                ->first();
            $values = ['severity' => $data['severity'], 'status' => 'open', 'details' => $data['details'], 'resolution_note' => null, 'resolved_by' => null, 'resolved_at' => null];
            $anomaly
                ? $anomaly->update($values)
                : HrAttendanceAnomaly::create($values + ['legal_entity_id' => $tenantId, 'employee_id' => $employee->id, 'work_date' => $date->toDateString(), 'type' => $type]);
        }

        HrAttendanceAnomaly::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $date)
            ->where('status', 'open')
            ->whereNotIn('type', array_keys($detected) ?: ['__none__'])
            ->update(['status' => 'auto_resolved', 'resolution_note' => 'Olay defteri yeniden değerlendirildi.', 'resolved_at' => now()]);
    }

    private function leaveWorkOverlap($leaves, Carbon $date, array $workIntervals): int
    {
        $minutes = 0;
        foreach ($leaves as $leave) {
            if ($leave->unit === LeaveUnit::Day) {
                foreach ($workIntervals as $interval) {
                    $minutes += Carbon::parse($interval['starts_at'])->diffInMinutes(Carbon::parse($interval['ends_at']));
                }
                continue;
            }
            if (! $leave->start_time || ! $leave->end_time) continue;
            $leaveStart = Carbon::parse($date->toDateString().' '.$leave->start_time);
            $leaveEnd = Carbon::parse($date->toDateString().' '.$leave->end_time);
            foreach ($workIntervals as $interval) {
                $workStart = Carbon::parse($interval['starts_at']);
                $workEnd = Carbon::parse($interval['ends_at']);
                $overlapStart = $workStart->greaterThan($leaveStart) ? $workStart : $leaveStart;
                $overlapEnd = $workEnd->lessThan($leaveEnd) ? $workEnd : $leaveEnd;
                if ($overlapEnd->gt($overlapStart)) $minutes += $overlapStart->diffInMinutes($overlapEnd);
            }
        }
        return $minutes;
    }

    private function leaveCovers($leaves, Carbon $date, Carbon $absenceStart, Carbon $absenceEnd): bool
    {
        $required = $absenceStart->diffInMinutes($absenceEnd);
        if ($required <= 0) return true;
        $covered = 0;
        foreach ($leaves as $leave) {
            if ($leave->unit === LeaveUnit::Day) return true;
            if (! $leave->start_time || ! $leave->end_time) continue;
            $leaveStart = Carbon::parse($date->toDateString().' '.$leave->start_time);
            $leaveEnd = Carbon::parse($date->toDateString().' '.$leave->end_time);
            $overlapStart = $leaveStart->greaterThan($absenceStart) ? $leaveStart : $absenceStart;
            $overlapEnd = $leaveEnd->lessThan($absenceEnd) ? $leaveEnd : $absenceEnd;
            if ($overlapEnd->gt($overlapStart)) $covered += $overlapStart->diffInMinutes($overlapEnd);
        }
        return $covered >= $required;
    }
}
