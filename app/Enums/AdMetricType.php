<?php

namespace App\Enums;

enum AdMetricType: string
{
    case PeriodTotal = 'period_total';
    case CumulativeSnapshot = 'cumulative_snapshot';
    case DerivedDelta = 'derived_delta';

    public function label(): string
    {
        return match ($this) {
            self::PeriodTotal => 'Dönem Toplamı',
            self::CumulativeSnapshot => 'Kümülatif',
            self::DerivedDelta => 'Tahmini Günlük',
        };
    }
}
