<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceStore;
use App\Models\MpProduct;
use App\Services\ProfitabilityMetric;

class MarketplaceCampaignSimulator
{
    public function __construct(
        protected MarketplaceVatEffectService $vatEffectService
    ) {
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function simulate(MpProduct $product, float $targetPrice, string $marketplace, array $options = []): array
    {
        // Extract costs
        $cogs = (float) $product->cogs;
        $packaging = (float) $product->packaging_cost;
        
        // Defaults or overrides
        $commissionRate = (float) ($options['commission_rate'] ?? $product->commission_rate ?? 15);
        $cargoCost = (float) ($options['cargo_cost'] ?? 50); // Default assumed cargo
        $serviceFee = (float) ($options['service_fee'] ?? 0);
        $vatRate = (float) ($options['vat_rate'] ?? 20);
        $withholdingRate = (float) ($options['withholding_rate'] ?? 0); // Default withholding if applied
        
        // Calculate
        $grossRevenue = $targetPrice;
        
        // Commission
        $commissionAmount = round($grossRevenue * ($commissionRate / 100), 2);
        
        // Withholding
        $withholdingAmount = round($grossRevenue * ($withholdingRate / 100), 2);

        // KDV calculation (Internal standard logic)
        $vatEffect = round(($grossRevenue - ($grossRevenue / (1 + ($vatRate / 100)))), 2);
        
        // Total Deductions
        $totalDeductions = $commissionAmount + $cargoCost + $serviceFee + $withholdingAmount;
        
        // Net Receivable
        $netReceivable = round($grossRevenue - $totalDeductions, 2);
        
        // Estimated Profit
        $profitValue = round($netReceivable - $cogs - $packaging - $vatEffect, 2);
        
        // Margins
        $productCost = ProfitabilityMetric::productCost($cogs, $packaging);
        $marginMultiplier = ProfitabilityMetric::multiplierOrZero($profitValue, $productCost);
        $profitPercent = $grossRevenue > 0 ? round(($profitValue / $grossRevenue) * 100, 1) : 0;

        return [
            'marketplace' => strtolower($marketplace),
            'target_price' => $targetPrice,
            'cogs' => $cogs,
            'packaging' => $packaging,
            'commission_rate' => $commissionRate,
            'commission_amount' => $commissionAmount,
            'cargo_cost' => $cargoCost,
            'service_fee' => $serviceFee,
            'vat_effect' => $vatEffect,
            'withholding_amount' => $withholdingAmount,
            'total_deductions' => $totalDeductions,
            'net_receivable' => $netReceivable,
            'profit_value' => $profitValue,
            'profit_margin_percent' => $profitPercent,
            'margin_multiplier' => $marginMultiplier,
            'is_profitable' => $profitValue > 0,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, MpProduct>  $products
     * @param  float  $priceChangePercent
     * @param  string  $marketplace
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function simulatePortfolio($products, float $priceChangePercent, string $marketplace, array $options = []): array
    {
        $currentTotalProfit = 0;
        $simulatedTotalProfit = 0;
        $unprofitableCount = 0;
        $totalProducts = 0;
        
        $currentTotalRevenue = 0;
        $simulatedTotalRevenue = 0;

        foreach ($products as $product) {
            $basePrice = (float) $product->sale_price;
            if ($basePrice <= 0) {
                $basePrice = 100; // fallback for calculation if zero
            }

            $baseCommission = $product->commission_rate ?? 15;

            // Current state simulation
            $currentSim = $this->simulate($product, $basePrice, $marketplace, array_merge($options, [
                'commission_rate' => $baseCommission,
            ]));
            
            // Target state simulation
            $discount = (float)($options['commission_discount'] ?? 0);
            $effectiveCommission = max(0, $baseCommission - $discount);
            
            $targetPrice = $basePrice * (1 + ($priceChangePercent / 100));
            $targetSim = $this->simulate($product, $targetPrice, $marketplace, array_merge($options, [
                'commission_rate' => $effectiveCommission,
            ]));

            $currentTotalProfit += $currentSim['profit_value'];
            $simulatedTotalProfit += $targetSim['profit_value'];
            
            $currentTotalRevenue += $currentSim['target_price'];
            $simulatedTotalRevenue += $targetSim['target_price'];

            if (!$targetSim['is_profitable']) {
                $unprofitableCount++;
            }
            
            $totalProducts++;
        }

        $profitDifference = $simulatedTotalProfit - $currentTotalProfit;
        $currentMargin = $currentTotalRevenue > 0 ? round(($currentTotalProfit / $currentTotalRevenue) * 100, 1) : 0;
        $simulatedMargin = $simulatedTotalRevenue > 0 ? round(($simulatedTotalProfit / $simulatedTotalRevenue) * 100, 1) : 0;

        return [
            'marketplace' => strtolower($marketplace),
            'total_products' => $totalProducts,
            'current_total_profit' => $currentTotalProfit,
            'simulated_total_profit' => $simulatedTotalProfit,
            'profit_difference' => $profitDifference,
            'current_margin_percent' => $currentMargin,
            'simulated_margin_percent' => $simulatedMargin,
            'unprofitable_count' => $unprofitableCount,
            'is_profitable_overall' => $simulatedTotalProfit > 0,
        ];
    }
}
