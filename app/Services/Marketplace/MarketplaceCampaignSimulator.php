<?php

namespace App\Services\Marketplace;

use App\Models\MpProduct;
use App\Services\MpSettingsService;
use App\Services\ProfitabilityMetric;

class MarketplaceCampaignSimulator
{
    public function __construct(
        protected MarketplacePricingSimulationService $pricingSimulationService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function simulate(MpProduct $product, float $targetPrice, string $marketplace, array $options = []): array
    {
        $cogs = (float) $product->cogs;
        $packaging = (float) $product->packaging_cost;
        $settings = new MpSettingsService((int) $product->user_id);
        $commissionRate = (float) ($options['commission_rate'] ?? $product->commission_rate ?? 15);
        $cargoCost = (float) ($options['cargo_cost'] ?? $product->cargo_cost ?? 0);
        $serviceFee = (float) ($options['service_fee'] ?? $settings->getEstimatedPlatformServiceFee($marketplace));
        $vatRate = (float) ($options['vat_rate'] ?? $product->vat_rate ?? ($settings->getDefaultProductVatRate() * 100));
        $withholdingRate = (float) ($options['withholding_rate'] ?? ($settings->getStopajRate() * 100));
        $calculation = $this->pricingSimulationService->calculateOnly([
            'marketplace' => $marketplace,
            'sale_price' => $targetPrice,
            'cogs' => $cogs,
            'packaging_cost' => $packaging,
            'cargo_cost' => $cargoCost,
            'extra_cost_fixed' => (float) ($product->extra_cost_fixed ?? 0),
            'extra_cost_rate' => (float) ($product->extra_cost_percentage ?? 0),
            'commission_rate' => $commissionRate,
            'service_fee_fixed' => $serviceFee,
            'vat_rate' => $vatRate,
            'cost_vat_rate' => (float) ($product->cost_vat_rate ?? $vatRate),
            'expense_vat_rate' => $settings->getExpenseVatRate() * 100,
            'vat_enabled' => (bool) ($options['vat_enabled'] ?? $settings->isKdvEnabled()),
            'withholding_enabled' => (bool) ($options['withholding_enabled'] ?? $settings->shouldEstimateWithholdingForMarketplace($marketplace)),
            'withholding_rate' => $withholdingRate,
        ]);
        $breakdown = (array) $calculation['breakdown'];
        $commissionAmount = (float) $breakdown['commission'];
        $withholdingAmount = (float) $breakdown['withholding'];
        $vatEffect = (float) $breakdown['net_vat'];
        $totalDeductions = (float) $breakdown['total_deductions'];
        $netReceivable = (float) $calculation['net_receivable'];
        $profitValue = (float) $calculation['cash_profit'];
        $productCost = ProfitabilityMetric::productCost($cogs, $packaging);
        $marginMultiplier = ProfitabilityMetric::multiplierOrZero($profitValue, $productCost);
        $profitPercent = (float) $calculation['profit_margin_percent'];

        return [
            'calculation_version' => MarketplacePricingSimulationService::CALCULATION_VERSION,
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
            'accounting_profit' => (float) $calculation['accounting_profit'],
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
