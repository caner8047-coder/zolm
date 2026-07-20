<?php

namespace App\Modules\Hr\Core\Actions;

use App\Modules\Hr\Core\Services\HrCalendarService;
use Carbon\Carbon;

class CalculateWorkingDaysAction
{
    public function __construct(
        private HrCalendarService $calendar
    ) {}

    public function execute(Carbon $start, Carbon $end): int
    {
        return $this->calendar->getWorkingDaysBetween($start, $end);
    }

    public function executeForEmployee(\App\Models\HrEmployee $employee, Carbon $start, Carbon $end): int
    {
        return $this->execute($start, $end);
    }
}
