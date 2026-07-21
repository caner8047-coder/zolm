<?php

namespace App\Modules\Hr\Shift\Enums;

enum ShiftAvailabilityStatus: string
{
    case Available = 'available';
    case Preferred = 'preferred';
    case Unavailable = 'unavailable';

    public function label(): string
    {
        return match ($this) { self::Available => 'Müsait', self::Preferred => 'Tercihli Saat', self::Unavailable => 'Müsait Değil' };
    }
}
