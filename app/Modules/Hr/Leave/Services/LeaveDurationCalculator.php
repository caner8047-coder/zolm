<?php

namespace App\Modules\Hr\Leave\Services;

use App\Modules\Hr\Core\Services\HrCalendarService;
use App\Modules\Hr\Leave\Enums\LeaveUnit;
use Carbon\Carbon;

class LeaveDurationCalculator
{
    public function __construct(private HrCalendarService $calendar) {}

    public function calculate(LeaveUnit $unit, Carbon $startDate, Carbon $endDate, ?string $startTime = null, ?string $endTime = null): float
    {
        abort_if($endDate->lt($startDate), 422, 'İzin bitiş tarihi başlangıç tarihinden önce olamaz.');

        if ($unit === LeaveUnit::Day) {
            return (float) $this->calendar->getWorkingDaysBetween($startDate->copy()->startOfDay(), $endDate->copy()->startOfDay());
        }

        abort_unless($startDate->isSameDay($endDate), 422, 'Saatlik izin aynı gün içinde olmalıdır.');
        abort_unless($startTime && $endTime, 422, 'Saatlik izin başlangıç ve bitiş saati gerektirir.');

        $start = Carbon::parse($startDate->toDateString() . ' ' . $startTime);
        $end = Carbon::parse($endDate->toDateString() . ' ' . $endTime);
        abort_if($end->lte($start), 422, 'Bitiş saati başlangıç saatinden sonra olmalıdır.');
        abort_unless($this->calendar->isWorkingDay($startDate), 422, 'Saatlik izin çalışma gününde olmalıdır.');

        return round($start->diffInMinutes($end) / 60, 2);
    }
}
