<?php

namespace App\Modules\Hr\Expense\Enums;

enum ExpenseStatus: string
{
    case PendingManager = 'pending_manager';
    case PendingHr = 'pending_hr';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingManager => 'Yönetici bekliyor',
            self::PendingHr => 'İK bekliyor',
            self::Approved => 'Onaylandı',
            self::Rejected => 'Reddedildi',
            self::Paid => 'Ödendi',
            self::Cancelled => 'İptal edildi',
        };
    }
}
