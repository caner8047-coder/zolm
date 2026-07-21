<?php

namespace App\Modules\Hr\Leave\Enums;

enum LeaveUnit: string
{
    case Day = 'day';
    case Hour = 'hour';

    public function label(): string
    {
        return $this === self::Day ? 'Gün' : 'Saat';
    }
}
