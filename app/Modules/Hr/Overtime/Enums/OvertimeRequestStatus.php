<?php

namespace App\Modules\Hr\Overtime\Enums;

enum OvertimeRequestStatus: string
{
    case PendingManager = 'pending_manager';
    case PendingHr = 'pending_hr';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    public function label(): string { return match ($this) { self::PendingManager => 'Yönetici bekliyor', self::PendingHr => 'İK bekliyor', self::Approved => 'Onaylandı', self::Rejected => 'Reddedildi', self::Cancelled => 'İptal' }; }
}
