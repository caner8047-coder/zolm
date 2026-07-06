<?php

namespace App\Enums;

enum ReconciliationStatus: string
{
    case Compatible = 'compatible';
    case CheckNeeded = 'check_needed';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Compatible => 'Uyumlu',
            self::CheckNeeded => 'Kontrol Edilmeli',
            self::Critical => 'Kritik Fark',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Compatible => 'bg-emerald-100 text-emerald-700',
            self::CheckNeeded => 'bg-amber-100 text-amber-700',
            self::Critical => 'bg-rose-100 text-rose-700',
        };
    }
}
