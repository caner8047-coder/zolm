<?php

namespace App\Enums;

enum CalculationStatus: string
{
    case Complete = 'complete';
    case Partial = 'partial';
    case InsufficientData = 'insufficient_data';

    public function label(): string
    {
        return match ($this) {
            self::Complete => 'Kesin Hesaplama',
            self::Partial => 'Eksik Veri ile Hesaplandı',
            self::InsufficientData => 'Yetersiz Veri',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Complete => 'bg-emerald-100 text-emerald-700',
            self::Partial => 'bg-amber-100 text-amber-700',
            self::InsufficientData => 'bg-rose-100 text-rose-700',
        };
    }
}
