<?php

namespace App\Modules\Hr\Personnel\Enums;

enum MaritalStatus: string
{
    case Single = 'single';
    case Married = 'married';
    case Divorced = 'divorced';
    case Widowed = 'widowed';

    public function label(): string
    {
        return match ($this) {
            self::Single => 'Bekar',
            self::Married => 'Evli',
            self::Divorced => 'Boşanmış',
            self::Widowed => 'Dul',
        };
    }
}
