<?php

namespace App\Modules\Hr\Core\Services;

use App\Models\HrHoliday;
use Carbon\Carbon;

class HrCalendarService
{
    public function isWorkingDay(Carbon $date): bool
    {
        if ($date->isWeekend()) {
            return false;
        }

        return !$this->isHoliday($date);
    }

    public function isHoliday(Carbon $date): bool
    {
        $tenantId = app(TenantContext::class)->getId();

        return HrHoliday::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->where('date', $date->toDateString())
            ->exists();
    }

    public function getWorkingDaysBetween(Carbon $start, Carbon $end): int
    {
        $days = 0;
        $current = $start->copy();

        while ($current->lte($end)) {
            if ($this->isWorkingDay($current)) {
                $days++;
            }
            $current->addDay();
        }

        return $days;
    }

    public function getHolidays(int $year, ?int $month = null): \Illuminate\Database\Eloquent\Collection
    {
        $tenantId = app(TenantContext::class)->getId();

        $query = HrHoliday::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->where('year', $year);

        if ($month) {
            $query->whereMonth('date', $month);
        }

        return $query->orderBy('date')->get();
    }
}
