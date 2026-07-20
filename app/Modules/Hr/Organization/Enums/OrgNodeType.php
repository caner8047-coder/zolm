<?php

namespace App\Modules\Hr\Organization\Enums;

enum OrgNodeType: string
{
    case SgkWorkplace = 'sgk_workplace';
    case Branch = 'branch';
    case Department = 'department';
    case Unit = 'unit';
    case Team = 'team';

    public function label(): string
    {
        return match ($this) {
            self::SgkWorkplace => 'SGK İşyeri',
            self::Branch => 'Şube',
            self::Department => 'Departman',
            self::Unit => 'Birim',
            self::Team => 'Ekip',
        };
    }
}
