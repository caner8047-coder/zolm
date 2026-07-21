<?php

namespace App\Modules\Hr\Leave\Enums;

enum LeaveRequestStatus: string
{
    case PendingManager = 'pending_manager';
    case PendingHr = 'pending_hr';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingManager => 'Yönetici Onayı Bekliyor',
            self::PendingHr => 'İK Onayı Bekliyor',
            self::Approved => 'Onaylandı',
            self::Rejected => 'Reddedildi',
            self::Cancelled => 'İptal Edildi',
        };
    }
}
