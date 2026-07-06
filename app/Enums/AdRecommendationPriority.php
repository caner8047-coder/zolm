<?php

namespace App\Enums;

enum AdRecommendationPriority: string
{
    case Critical = 'critical';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    public function label(): string
    {
        return match ($this) {
            self::Critical => 'Kritik',
            self::High => 'Yüksek',
            self::Medium => 'Orta',
            self::Low => 'Düşük',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Critical => 'red',
            self::High => 'orange',
            self::Medium => 'yellow',
            self::Low => 'slate',
        };
    }
}
