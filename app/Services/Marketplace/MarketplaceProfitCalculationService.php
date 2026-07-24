<?php

namespace App\Services\Marketplace;

/**
 * ZOLM genelindeki tutar bazlı kanonik kârlılık aritmetiği.
 *
 * Veri kaynakları (tahmin, finans hareketi veya legacy import) kalemleri
 * çözümler; bu servis yalnızca aynı kalemlerden aynı sonucu üretir.
 */
class MarketplaceProfitCalculationService
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, float>
     */
    public function calculate(array $input): array
    {
        $grossRevenue = $this->money($input['gross_revenue'] ?? 0);
        $cogs = $this->money($input['cogs'] ?? 0);
        $packagingCost = $this->money($input['packaging_cost'] ?? 0);
        $commission = $this->money($input['commission'] ?? 0);
        $marketplaceCargo = $this->money($input['marketplace_cargo'] ?? 0);
        $ownCargo = $this->money($input['own_cargo'] ?? 0);
        $serviceFee = $this->money($input['service_fee'] ?? 0);
        $advertising = $this->money($input['advertising'] ?? 0);
        $returnReserve = $this->money($input['return_reserve'] ?? 0);
        $otherMarketplaceDeductions = $this->money($input['other_marketplace_deductions'] ?? 0);
        $extraOperationalCost = $this->money($input['extra_operational_cost'] ?? 0);
        $withholding = $this->money($input['withholding'] ?? 0);
        $netVat = $this->money($input['net_vat'] ?? 0);

        $productCost = round($cogs + $packagingCost, 2);
        $operationalCosts = round(
            $marketplaceCargo
                + $ownCargo
                + $serviceFee
                + $advertising
                + $returnReserve
                + $otherMarketplaceDeductions
                + $extraOperationalCost,
            2,
        );
        $marketplaceDeductions = round(
            $commission
                + $marketplaceCargo
                + $serviceFee
                + $advertising
                + $returnReserve
                + $otherMarketplaceDeductions
                + $withholding,
            2,
        );
        $netReceivable = round($grossRevenue - $marketplaceDeductions, 2);
        $accountingProfit = round(
            $grossRevenue
                - $commission
                - $productCost
                - $operationalCosts
                - $netVat,
            2,
        );
        $cashProfit = round($accountingProfit - $withholding, 2);

        return [
            'gross_revenue' => $grossRevenue,
            'product_cost' => $productCost,
            'operational_costs' => $operationalCosts,
            'marketplace_deductions' => $marketplaceDeductions,
            'net_receivable' => $netReceivable,
            'accounting_profit' => $accountingProfit,
            'cash_profit' => $cashProfit,
            'sales_margin_percent' => $grossRevenue > 0
                ? round(($cashProfit / $grossRevenue) * 100, 2)
                : 0.0,
            'roi_percent' => $productCost > 0
                ? round(($cashProfit / $productCost) * 100, 2)
                : 0.0,
        ];
    }

    protected function money(mixed $value): float
    {
        return round(max(0, (float) $value), 2);
    }
}
