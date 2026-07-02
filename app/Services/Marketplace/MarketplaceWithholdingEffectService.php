<?php

namespace App\Services\Marketplace;

use App\Models\ChannelOrder;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\Support\OrderLifecycleResolver;
use App\Services\MpSettingsService;

class MarketplaceWithholdingEffectService
{
    public function __construct(
        protected OrderLifecycleResolver $lifecycleResolver,
    ) {
    }

    /**
     * @param  iterable<int, mixed>  $items
     * @return array{amount: float, source: string, taxable_base: float, rate: float}
     */
    public function calculate(
        MarketplaceStore $store,
        ChannelOrder $order,
        iterable $items,
        float $grossRevenue,
        float $actualWithholdingTotal,
    ): array {
        if ($actualWithholdingTotal > 0) {
            return $this->result($actualWithholdingTotal, 'actual', 0.0, 0.0);
        }

        $settings = new MpSettingsService((int) $store->user_id);

        if (! $settings->isEstimatedWithholdingEnabled() || ! $this->withholdingApplies($order)) {
            return $this->result(0.0, 'none', 0.0, 0.0);
        }

        $rate = $this->normalizeRate($settings->getStopajRate());
        $taxableBase = $this->taxableBase($items, $grossRevenue, $settings);

        return $this->result(round($taxableBase * $rate, 2), 'theoretical', $taxableBase, $rate);
    }

    /**
     * @param  iterable<int, mixed>  $items
     */
    protected function taxableBase(iterable $items, float $grossRevenue, MpSettingsService $settings): float
    {
        $base = 0.0;
        $lineGrossTotal = 0.0;

        foreach ($items as $item) {
            $lineGross = $this->lineGrossAmount($item);
            $lineGrossTotal += $lineGross;

            if ($lineGross <= 0) {
                continue;
            }

            $base += $lineGross / (1 + $this->salesVatRateForItem($item, $settings));
        }

        if ($lineGrossTotal <= 0 && $grossRevenue > 0) {
            $base = $grossRevenue / (1 + $this->normalizeRate($settings->getDefaultProductVatRate()));
        }

        return round($base, 2);
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

    protected function normalizeRate(float $rate): float
    {
        if ($rate >= 1) {
            return round($rate / 100, 4);
        }

        return round(max(0, $rate), 4);
    }

    protected function withholdingApplies(ChannelOrder $order): bool
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
     * @return array{amount: float, source: string, taxable_base: float, rate: float}
     */
    protected function result(float $amount, string $source, float $taxableBase, float $rate): array
    {
        return [
            'amount' => round($amount, 2),
            'source' => $source,
            'taxable_base' => round($taxableBase, 2),
            'rate' => $rate,
        ];
    }
}
