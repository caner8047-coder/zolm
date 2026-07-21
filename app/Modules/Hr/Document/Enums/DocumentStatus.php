<?php

namespace App\Modules\Hr\Document\Enums;

enum DocumentStatus: string
{
    case Requested = 'requested';
    case Uploaded = 'uploaded';
    case Active = 'active';
    case Expired = 'expired';
    case Archived = 'archived';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Talep Edildi',
            self::Uploaded => 'Yüklendi',
            self::Active => 'Aktif',
            self::Expired => 'Süresi Doldu',
            self::Archived => 'Arşivlendi',
            self::Rejected => 'Reddedildi',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Requested => 'blue',
            self::Uploaded => 'yellow',
            self::Active => 'green',
            self::Expired => 'red',
            self::Archived => 'gray',
            self::Rejected => 'red',
        };
    }
}
