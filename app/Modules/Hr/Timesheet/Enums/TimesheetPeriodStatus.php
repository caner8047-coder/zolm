<?php

namespace App\Modules\Hr\Timesheet\Enums;

enum TimesheetPeriodStatus: string
{
    case Draft = 'draft';
    case Calculated = 'calculated';
    case Closed = 'closed';
    public function label(): string { return match ($this) { self::Draft => 'Taslak', self::Calculated => 'Hesaplandı', self::Closed => 'Kapandı' }; }
}
