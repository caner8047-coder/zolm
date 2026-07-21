<?php

namespace App\Modules\Hr\Timesheet\Enums;

enum TimesheetStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Closed = 'closed';
    public function label(): string { return match ($this) { self::Draft => 'Taslak', self::Confirmed => 'Onaylandı', self::Closed => 'Kapandı' }; }
}
