<?php

namespace App\Modules\Hr\Personnel\Enums;

enum Gender: string
{
    case Male = 'male';
    case Female = 'female';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Erkek',
            self::Female => 'Kadın',
            self::Other => 'Diğer',
        };
    }
}
