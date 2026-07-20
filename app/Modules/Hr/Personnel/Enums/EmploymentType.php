<?php

namespace App\Modules\Hr\Personnel\Enums;

enum EmploymentType: string
{
    case FullTime = 'full_time';
    case PartTime = 'part_time';
    case Contract = 'contract';
    case Intern = 'intern';
    case Temporary = 'temporary';

    public function label(): string
    {
        return match ($this) {
            self::FullTime => 'Tam Zamanlı',
            self::PartTime => 'Yarım Zamanlı',
            self::Contract => 'Sözleşmeli',
            self::Intern => 'Stajyer',
            self::Temporary => 'Geçici',
        };
    }
}
