<?php

namespace App\Modules\Hr\Personnel\Enums;

enum EmployeeStatus: string
{
    case Active = 'active';
    case OnLeave = 'on_leave';
    case Suspended = 'suspended';
    case Terminated = 'terminated';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::OnLeave => 'İzinli',
            self::Suspended => 'Askıda',
            self::Terminated => 'Ayrılmış',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::OnLeave => 'yellow',
            self::Suspended => 'orange',
            self::Terminated => 'red',
        };
    }
}
