<?php

namespace App\Services;

class ProfitabilityMetric
{
    public static function productCost(float $cogs, float $packagingCost = 0.0): float
    {
        return round(max(0.0, $cogs) + max(0.0, $packagingCost), 2);
    }

    public static function returnBase(float $netProfit, float $productCost): float
    {
        return round($netProfit + $productCost, 2);
    }

    public static function multiplier(?float $netProfit, ?float $productCost): ?float
    {
        $cost = (float) $productCost;

        if ($cost <= 0) {
            return null;
        }

        return round((((float) $netProfit) + $cost) / $cost, 4);
    }

    public static function multiplierOrZero(?float $netProfit, ?float $productCost): float
    {
        return self::multiplier($netProfit, $productCost) ?? 0.0;
    }

    public static function profitPercent(?float $netProfit, ?float $productCost): ?float
    {
        $cost = (float) $productCost;

        if ($cost <= 0) {
            return null;
        }

        return round((((float) $netProfit) / $cost) * 100, 1);
    }

    public static function profitPercentFromMultiplier(?float $multiplier): ?float
    {
        if ($multiplier === null) {
            return null;
        }

        return round((((float) $multiplier) - 1) * 100, 1);
    }

    public static function profitPercentFromMultiplierOrZero(?float $multiplier): float
    {
        return self::profitPercentFromMultiplier($multiplier) ?? 0.0;
    }
}
