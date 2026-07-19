<?php

namespace App\Enums;

enum AdRecommendationCategory: string
{
    case Budget = 'budget';
    case Profitability = 'profitability';
    case Stock = 'stock';
    case Keyword = 'keyword';
    case Creator = 'creator';
    case DataQuality = 'data_quality';
}
