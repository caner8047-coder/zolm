<?php

namespace App\Enums;

enum StoreTargetingType: string
{
    case Smart = 'smart';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Smart => 'Akıllı Hedefleme',
            self::Manual => 'Manuel Hedefleme',
        };
    }
}
