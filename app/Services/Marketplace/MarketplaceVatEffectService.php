<?php

namespace App\Services\Marketplace;

use App\Models\ChannelOrder;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\Support\OrderLifecycleResolver;
use App\Services\MpSettingsService;

class MarketplaceVatEffectService
{
    public function __construct(
        protected OrderLifecycleResolver $lifecycleResolver,
    ) {
    }

    /**
     * @param  iterable<int, mixed>  $items
     * @return array{enabled: bool, sales_vat: float, expense_vat: float, net_vat: float, sales_vat_rate: float, expense_vat_rate: float}
     */
    public function calculate(
        MarketplaceStore $store,
        ChannelOrder $order,
        iterable $items,
        float $grossRevenue,
        float $commissionTotal,
        float $cargoTotal,
    ): array {
        $settings = new MpSettingsService((int) $store->user_id);

        if (! $settings->isKdvEnabled()) {
            return $this->emptyResult(false);
        }

        $expenseVatRate = $this->normalizeRate($settings->getExpenseVatRate());
        $salesVat = $this->salesVatApplies($order)
            ? $this->salesVatTotal($items, $grossRevenue, $settings)
            : 0.0;
        $expenseVat = round((abs($commissionTotal) + abs($cargoTotal)) * $expenseVatRate, 2);
        $netVat = round($salesVat - $expenseVat, 2);

        return [
            'enabled' => true,
            'sales_vat' => round($salesVat, 2),
            'expense_vat' => $expenseVat,
            'net_vat' => $netVat,
            'sales_vat_rate' => $this->dominantSalesVatRate($items, $settings),
            'expense_vat_rate' => $expenseVatRate,
        ];
    }

    /**
     * @param  iterable<int, mixed>  $items
     */
    protected function salesVatTotal(iterable $items, float $grossRevenue, MpSettingsService $settings): float
    {
        $total = 0.0;
        $itemGrossTotal = 0.0;

        foreach ($items as $item) {
            $lineGross = $this->lineGrossAmount($item);
            $itemGrossTotal += $lineGross;

            if ($lineGross <= 0) {
                continue;
            }

            $rate = $this->salesVatRateForItem($item, $settings);
            $total += $this->includedVat($lineGross, $rate);
        }

        if ($itemGrossTotal <= 0 && $grossRevenue > 0) {
            $total = $this->includedVat($grossRevenue, $this->normalizeRate($settings->getDefaultProductVatRate()));
        }

        return round($total, 2);
    }

    protected function lineGrossAmount(mixed $item): float
    {
        $quantity = max(1, (int) ($item->quantity ?? 1));

        return round((float) (
            $item->billable_amount
            ?: $item->gross_amount
            ?: ((float) ($item->unit_price ?? 0) * $quantity)
        ), 2);
    }

    protected function salesVatRateForItem(mixed $item, MpSettingsService $settings): float
    {
        foreach ([
            $item->vat_rate ?? null,
            $item->product?->vat_rate ?? null,
        ] as $rate) {
            if ($rate !== null && (float) $rate >= 0) {
                return $this->normalizeRate((float) $rate);
            }
        }

        return $this->normalizeRate($settings->getDefaultProductVatRate());
    }

    /**
     * @param  iterable<int, mixed>  $items
     */
    protected function dominantSalesVatRate(iterable $items, MpSettingsService $settings): float
    {
        $weightedRates = [];

        foreach ($items as $item) {
            $lineGross = $this->lineGrossAmount($item);

            if ($lineGross <= 0) {
                continue;
            }

            $rate = $this->salesVatRateForItem($item, $settings);
            $weightedRates[] = ['rate' => $rate, 'weight' => $lineGross];
        }

        if ($weightedRates === []) {
            return $this->normalizeRate($settings->getDefaultProductVatRate());
        }

        $totalWeight = array_sum(array_column($weightedRates, 'weight'));

        if ($totalWeight <= 0) {
            return $this->normalizeRate($settings->getDefaultProductVatRate());
        }

        $weighted = collect($weightedRates)
            ->sum(fn (array $row) => (float) $row['rate'] * (float) $row['weight']);

        return round($weighted / $totalWeight, 4);
    }

    protected function includedVat(float $grossAmount, float $rate): float
    {
        if ($grossAmount <= 0 || $rate <= 0) {
            return 0.0;
        }

        return round($grossAmount * $rate / (1 + $rate), 2);
    }

    protected function normalizeRate(float $rate): float
    {
        if ($rate >= 1) {
            return round($rate / 100, 4);
        }

        return round(max(0, $rate), 4);
    }

    protected function salesVatApplies(ChannelOrder $order): bool
    {
        return ! in_array($this->lifecycleResolver->resolve($order), [
            'cancelled_pre_ship',
            'cancelled_post_ship',
            'returned_sellable',
            'returned_damaged',
            'return_pending',
        ], true);
    }

    /**
     * @return array{enabled: bool, sales_vat: float, expense_vat: float, net_vat: float, sales_vat_rate: float, expense_vat_rate: float}
     */
    protected function emptyResult(bool $enabled): array
    {
        return [
            'enabled' => $enabled,
            'sales_vat' => 0.0,
            'expense_vat' => 0.0,
            'net_vat' => 0.0,
            'sales_vat_rate' => 0.0,
            'expense_vat_rate' => 0.0,
        ];
    }
}
