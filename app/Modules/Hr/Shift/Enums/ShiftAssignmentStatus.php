<?php

namespace App\Modules\Hr\Shift\Enums;

enum ShiftAssignmentStatus: string
{
    case Planned = 'planned';
    case Published = 'published';
    case Cancelled = 'cancelled';

    public function label(): string { return match ($this) { self::Planned => 'Planlandı', self::Published => 'Yayımlandı', self::Cancelled => 'İptal', }; }
}
