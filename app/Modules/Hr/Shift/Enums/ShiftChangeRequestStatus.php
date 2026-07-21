<?php

namespace App\Modules\Hr\Shift\Enums;

enum ShiftChangeRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string { return match ($this) { self::Pending => 'Onay Bekliyor', self::Approved => 'Onaylandı', self::Rejected => 'Reddedildi', self::Cancelled => 'İptal' }; }
}
