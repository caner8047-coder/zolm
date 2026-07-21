<?php

namespace App\Modules\Hr\Document\Enums;

enum VerificationStatus: string
{
    case NotRequired = 'not_required';
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::NotRequired => 'Gerekli Değil',
            self::Pending => 'Doğrulanıyor',
            self::Verified => 'Doğrulandı',
            self::Rejected => 'Reddedildi',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::NotRequired => 'gray',
            self::Pending => 'yellow',
            self::Verified => 'green',
            self::Rejected => 'red',
        };
    }
}
