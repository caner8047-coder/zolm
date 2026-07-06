<?php

namespace App\Enums;

enum AdRecommendationCategory: string
{
    case Profitability = 'profitability';
    case Budget = 'budget';
    case DataQuality = 'data_quality';
    case Keyword = 'keyword';
    case Creator = 'creator';

    public function label(): string
    {
        return match ($this) {
            self::Profitability => 'Kârlılık',
            self::Budget => 'Bütçe',
            self::DataQuality => 'Veri Kalitesi',
            self::Keyword => 'Kelime',
            self::Creator => 'Creator',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Profitability => 'currency-dollar',
            self::Budget => 'banknotes',
            self::DataQuality => 'chart-bar',
            self::Keyword => 'magnifying-glass',
            self::Creator => 'user-group',
        };
    }
}
