<?php

namespace App\Services\Marketplace;

class MarketplacePricingSimulationService
{
    public const CALCULATION_VERSION = 2;

    public function __construct(
        protected MarketplaceProfitCalculationService $profitCalculationService,
    ) {
    }

    /**
     * Hedef fiyat araması yapmadan kanonik birim ekonomi sonucunu üretir.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function calculateOnly(array $input): array
    {
        $normalized = $this->normalizeInput($input);

        return $this->calculate($normalized) + ['input' => $normalized];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function simulate(array $input): array
    {
        $normalized = $this->normalizeInput($input);
        $result = $this->calculate($normalized);
        $breakEvenPrice = $this->targetPrice($normalized, 'amount', 0.0);
        $targetMode = $normalized['target_mode'];
        $targetValue = $targetMode === 'margin'
            ? $normalized['target_margin_percent']
            : $normalized['target_profit_amount'];
        $targetPrice = $this->targetPrice($normalized, $targetMode, $targetValue);

        return $result + [
            'break_even_price' => $breakEvenPrice,
            'target_price' => $targetPrice,
            'target_mode' => $targetMode,
            'target_value' => $targetValue,
            'price_gap_to_target' => $targetPrice !== null
                ? round($targetPrice - $normalized['sale_price'], 2)
                : null,
            'input' => $normalized,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function calculate(array $input): array
    {
        $salePrice = $input['sale_price'];
        $commission = round($salePrice * $input['commission_rate_decimal'], 2);
        $serviceFee = round(
            $input['service_fee_fixed'] + ($salePrice * $input['service_fee_rate_decimal']),
            2
        );
        $advertising = round($salePrice * $input['advertising_rate_decimal'], 2);
        $extraPercentageCost = round($salePrice * $input['extra_cost_rate_decimal'], 2);
        $returnReserve = round(
            ($salePrice * $input['return_rate_decimal'])
            + ($input['return_cargo_cost'] * $input['return_rate_decimal']),
            2
        );
        $withholdingBase = $input['micro_export']
            ? $salePrice
            : $salePrice / (1 + $input['vat_rate_decimal']);
        $withholding = $input['withholding_enabled']
            ? round($withholdingBase * $input['withholding_rate_decimal'], 2)
            : 0.0;

        $salesVat = $input['vat_enabled'] && ! $input['micro_export']
            ? $this->includedVat($salePrice, $input['vat_rate_decimal'])
            : 0.0;
        $purchaseVat = $input['vat_enabled']
            ? $this->includedVat(
                $input['cogs'] + $input['packaging_cost'],
                $input['cost_vat_rate_decimal']
            )
            : 0.0;
        $expenseVat = $input['vat_enabled']
            ? $this->includedVat(
                $commission
                    + $input['cargo_cost']
                    + $serviceFee
                    + $advertising
                    + ($input['extra_cost_vat_eligible']
                        ? $input['extra_cost_fixed'] + $extraPercentageCost
                        : 0),
                $input['expense_vat_rate_decimal']
            )
            : 0.0;
        $inputVatCredit = round($purchaseVat + $expenseVat, 2);
        $netVatBeforeFloor = round($salesVat - $inputVatCredit, 2);
        // Devreden KDV ürün kârını yapay biçimde artırmaz; yalnızca ödenecek KDV sıfırlanır.
        $netVat = max(0.0, $netVatBeforeFloor);
        $vatCreditCarryforward = abs(min(0.0, $netVatBeforeFloor));

        $formula = $this->profitCalculationService->calculate([
            'gross_revenue' => $salePrice,
            'cogs' => $input['cogs'],
            'packaging_cost' => $input['packaging_cost'],
            'commission' => $commission,
            'marketplace_cargo' => $input['cargo_cost'],
            'service_fee' => $serviceFee,
            'advertising' => $advertising,
            'return_reserve' => $returnReserve,
            'extra_operational_cost' => $input['extra_cost_fixed'] + $extraPercentageCost,
            'withholding' => $withholding,
            'net_vat' => $netVat,
        ]);
        $productCost = $formula['product_cost'];
        $operationalCosts = $formula['operational_costs'];
        $totalDeductions = round(
            $commission
            + $operationalCosts
            + $withholding
            + max(0, $netVat),
            2
        );
        $netReceivable = $formula['net_receivable'];
        $accountingProfit = $formula['accounting_profit'];
        // Operasyon ekranlarının ana metriği nakit hakediş etkisini gösterir.
        // Stopaj ayrıca mahsup edilebilir vergi alacağı olarak raporlanır.
        $cashProfit = $formula['cash_profit'];
        $netProfit = $cashProfit;
        $marginPercent = $formula['sales_margin_percent'];
        $roiPercent = $formula['roi_percent'];
        $status = match (true) {
            $salePrice <= 0 || $netProfit < 0 => 'loss',
            $marginPercent < 10 => 'warning',
            default => 'healthy',
        };

        $warnings = [];

        if ($productCost <= 0) {
            $warnings[] = 'Ürün maliyeti tanımlı değil; sonuç güvenilir değildir.';
        }

        if ($netProfit < 0) {
            $warnings[] = 'Bu senaryo ürün başına zarar üretiyor.';
        }

        if ($input['micro_export']) {
            $warnings[] = 'Mikro ihracat senaryosunda satış KDV’si sıfır kabul edildi; belge şartlarını mali müşavirinizle doğrulayın.';
        }

        if (
            $input['commission_rate_decimal']
            + $input['service_fee_rate_decimal']
            + $input['advertising_rate_decimal']
            + $input['return_rate_decimal']
            >= 0.90
        ) {
            $warnings[] = 'Oransal kesintiler satış fiyatının çok büyük bölümünü tüketiyor.';
        }

        return [
            'calculation_version' => self::CALCULATION_VERSION,
            'sale_price' => round($salePrice, 2),
            'net_receivable' => $netReceivable,
            'net_profit' => $netProfit,
            'cash_profit' => $cashProfit,
            'accounting_profit' => $accountingProfit,
            'withholding_tax_credit' => $withholding,
            'profit_margin_percent' => $marginPercent,
            'roi_percent' => $roiPercent,
            'status' => $status,
            'warnings' => $warnings,
            'breakdown' => [
                'cogs' => round($input['cogs'], 2),
                'packaging_cost' => round($input['packaging_cost'], 2),
                'commission' => $commission,
                'cargo' => round($input['cargo_cost'], 2),
                'service_fee' => $serviceFee,
                'advertising' => $advertising,
                'return_reserve' => $returnReserve,
                'extra_cost' => round($input['extra_cost_fixed'], 2),
                'extra_percentage_cost' => $extraPercentageCost,
                'withholding' => $withholding,
                'withholding_base' => round($withholdingBase, 2),
                'sales_vat' => $salesVat,
                'purchase_vat_credit' => $purchaseVat,
                'expense_vat_credit' => $expenseVat,
                'net_vat' => $netVat,
                'vat_credit_carryforward' => $vatCreditCarryforward,
                'product_cost' => $productCost,
                'operational_costs' => $operationalCosts,
                'total_deductions' => $totalDeductions,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function normalizeInput(array $input): array
    {
        $requestedTargetMode = (string) ($input['target_mode'] ?? 'margin');
        $targetMode = in_array($requestedTargetMode, ['amount', 'margin'], true)
            ? $requestedTargetMode
            : 'margin';

        return [
            'marketplace' => strtolower(trim((string) ($input['marketplace'] ?? 'trendyol'))),
            'delivery_type' => strtolower(trim((string) ($input['delivery_type'] ?? 'standard'))),
            'sale_price' => $this->money($input['sale_price'] ?? 0),
            'cogs' => $this->money($input['cogs'] ?? 0),
            'packaging_cost' => $this->money($input['packaging_cost'] ?? 0),
            'cargo_cost' => $this->money($input['cargo_cost'] ?? 0),
            'return_cargo_cost' => $this->money($input['return_cargo_cost'] ?? 0),
            'service_fee_fixed' => $this->money($input['service_fee_fixed'] ?? 0),
            'extra_cost_fixed' => $this->money($input['extra_cost_fixed'] ?? 0),
            'extra_cost_rate' => $this->percentValue($input['extra_cost_rate'] ?? 0),
            'extra_cost_rate_decimal' => $this->rate($input['extra_cost_rate'] ?? 0),
            'commission_rate' => $this->percentValue($input['commission_rate'] ?? 0),
            'commission_rate_decimal' => $this->rate($input['commission_rate'] ?? 0),
            'service_fee_rate' => $this->percentValue($input['service_fee_rate'] ?? 0),
            'service_fee_rate_decimal' => $this->rate($input['service_fee_rate'] ?? 0),
            'advertising_rate' => $this->percentValue($input['advertising_rate'] ?? 0),
            'advertising_rate_decimal' => $this->rate($input['advertising_rate'] ?? 0),
            'return_rate' => $this->percentValue($input['return_rate'] ?? 0),
            'return_rate_decimal' => $this->rate($input['return_rate'] ?? 0),
            'vat_rate' => $this->percentValue($input['vat_rate'] ?? 0),
            'vat_rate_decimal' => $this->rate($input['vat_rate'] ?? 0),
            'cost_vat_rate' => $this->percentValue($input['cost_vat_rate'] ?? 0),
            'cost_vat_rate_decimal' => $this->rate($input['cost_vat_rate'] ?? 0),
            'expense_vat_rate' => $this->percentValue($input['expense_vat_rate'] ?? 20),
            'expense_vat_rate_decimal' => $this->rate($input['expense_vat_rate'] ?? 20),
            'withholding_rate' => $this->percentValue($input['withholding_rate'] ?? 1),
            'withholding_rate_decimal' => $this->rate($input['withholding_rate'] ?? 1),
            'vat_enabled' => (bool) ($input['vat_enabled'] ?? false),
            'withholding_enabled' => (bool) ($input['withholding_enabled'] ?? false),
            'extra_cost_vat_eligible' => (bool) ($input['extra_cost_vat_eligible'] ?? false),
            'micro_export' => (bool) ($input['micro_export'] ?? false),
            'target_mode' => $targetMode,
            'target_profit_amount' => $this->money($input['target_profit_amount'] ?? 0),
            'target_margin_percent' => $this->percentValue($input['target_margin_percent'] ?? 20),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    protected function targetPrice(array $input, string $mode, float $target): ?float
    {
        $fixedCostTotal = $input['cogs']
            + $input['packaging_cost']
            + $input['cargo_cost']
            + $input['service_fee_fixed']
            + $input['extra_cost_fixed'];

        if ($fixedCostTotal <= 0 && $target <= 0) {
            return 0.0;
        }

        $targetRate = $mode === 'margin' ? $this->rate($target) : 0.0;
        $matches = function (float $price) use ($input, $mode, $target, $targetRate): bool {
            $result = $this->calculate(array_merge($input, ['sale_price' => $price]));
            $requiredProfit = $mode === 'margin' ? $price * $targetRate : $target;

            return (float) $result['net_profit'] >= $requiredProfit;
        };

        $low = 0.0;
        $high = max(1.0, $input['sale_price'], ($input['cogs'] + $input['packaging_cost'] + $input['cargo_cost']) * 2);

        for ($attempt = 0; $attempt < 40 && ! $matches($high); $attempt++) {
            $high *= 2;

            if ($high > 100000000) {
                return null;
            }
        }

        if (! $matches($high)) {
            return null;
        }

        for ($iteration = 0; $iteration < 70; $iteration++) {
            $mid = ($low + $high) / 2;

            if ($matches($mid)) {
                $high = $mid;
            } else {
                $low = $mid;
            }
        }

        return round($high, 2);
    }

    protected function includedVat(float $grossAmount, float $rate): float
    {
        if ($grossAmount <= 0 || $rate <= 0) {
            return 0.0;
        }

        return round($grossAmount * $rate / (1 + $rate), 2);
    }

    protected function money(mixed $value): float
    {
        return round(max(0, (float) $value), 2);
    }

    protected function rate(mixed $value): float
    {
        $rate = max(0, (float) $value);

        if ($rate >= 1) {
            return round(min(100, $rate) / 100, 6);
        }

        return round(min(1, $rate), 6);
    }

    protected function percentValue(mixed $value): float
    {
        $rate = max(0, (float) $value);

        return round($rate < 1 ? $rate * 100 : min(100, $rate), 4);
    }
}
