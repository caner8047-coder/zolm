<?php

namespace App\Modules\Hr\Timesheet\Enums;

enum TimesheetDayType: string
{
    case Workday = 'workday';
    case WeeklyRest = 'weekly_rest';
    case OfficialHoliday = 'official_holiday';

    public function label(): string
    {
        return match ($this) {
            self::Workday => 'İş günü',
            self::WeeklyRest => 'Hafta tatili',
            self::OfficialHoliday => 'Resmî tatil',
        };
    }
}
