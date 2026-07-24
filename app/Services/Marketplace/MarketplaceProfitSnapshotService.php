<?php

namespace App\Services\Marketplace;

use App\Models\ChannelOrder;
use App\Models\MarketplaceStore;
use App\Models\OrderFinancialEvent;
use App\Models\OrderProfitSnapshot;
use App\Models\Shipment;
use App\Services\MpSettingsService;
use App\Services\Marketplace\Support\FinancialEventClassifier;
use App\Services\Marketplace\Support\OrderLifecycleResolver;
use App\Services\ProfitabilityMetric;
use App\Services\ProductCompositionResolver;
use App\Services\CurrencyExchangeService;
use Illuminate\Support\Facades\Schema;

class MarketplaceProfitSnapshotService
{
    public function __construct(
        protected OrderLifecycleResolver $lifecycleResolver,
        protected ProductCompositionResolver $compositionResolver,
        protected MarketplaceVatEffectService $vatEffectService,
        protected MarketplaceWithholdingEffectService $withholdingEffectService,
        protected MarketplaceCostBreakdownService $costBreakdownService,
        protected MarketplaceProfitCalculationService $profitCalculationService,
        protected CurrencyExchangeService $exchangeService,
    ) {
    }

    /**
     * @param  array<int>  $orderIds
     */
    public function recalculateForOrders(MarketplaceStore $store, array $orderIds): void
    {
        if ($orderIds === []) {
            return;
        }

        ChannelOrder::query()
            ->with(['items.product.productSet.items.componentProduct', 'items.listing', 'financialEvents', 'packages'])
            ->where('store_id', $store->id)
            ->whereIn('id', $orderIds)
            ->get()
            ->each(fn (ChannelOrder $order) => $this->recalculateOrder($store, $order));
    }

    public function recalculateOrder(MarketplaceStore $store, ChannelOrder $order): void
    {
        $items = $order->items;
        $financialEvents = $order->financialEvents;

        $currency = strtoupper((string) ($order->currency ?: 'TRY'));
        $exchangeRate = (float) ($order->exchange_rate ?: 1.0);
        
        // Ensure exchange rate is updated if missing
        if ($currency !== 'TRY' && $exchangeRate <= 1.0) {
            $date = $order->ordered_at?->format('Y-m-d') ?: now()->format('Y-m-d');
            $exchangeRate = $this->exchangeService->getExchangeRate($currency, 'TRY', $date);
            $order->update(['exchange_rate' => $exchangeRate]);
        }

        $grossRevenue = round((float) $items->sum(fn ($item) => (float) ($item->billable_amount ?: $item->gross_amount ?: ((float) $item->unit_price * (int) $item->quantity))), 2);
        $estimatedCommission = round((float) $items->sum(function ($item) use ($store) {
            $baseAmount = (float) ($item->billable_amount ?: $item->gross_amount ?: ((float) $item->unit_price * (int) $item->quantity));
            $rate = $this->estimatedCommissionRate($store, $item);

            return $baseAmount * $rate / 100;
        }), 2);

        $compositionTotals = $this->compositionResolver->totalsForOrderItems($items);
        $cogsCost = (float) $compositionTotals['cogs_cost'];
        $packagingCost = (float) $compositionTotals['packaging_cost'];
        $estimatedOwnCargoCost = (float) $compositionTotals['own_cargo_cost'];
        $actualOwnCargoCost = $this->actualOwnCargoCost($order);
        $ownCargoCost = $actualOwnCargoCost > 0 ? $actualOwnCargoCost : $estimatedOwnCargoCost;
        $settledFinancialEvents = $financialEvents
            ->filter(fn (OrderFinancialEvent $event) => FinancialEventClassifier::isSettledConfirmingEvent($event));
        $costBreakdown = $this->costBreakdownService->summarize($settledFinancialEvents, false);
        $commissionTotal = $this->costBreakdownService->costTotal($costBreakdown, 'commission');
        $cargoTotal = $this->costBreakdownService->costTotal($costBreakdown, 'cargo');
        $serviceFeeOnly = $this->costBreakdownService->costTotal($costBreakdown, 'service_fee');
        $advertisingTotal = $this->costBreakdownService->costTotal($costBreakdown, 'advertising');
        $penaltyTotal = $this->costBreakdownService->costTotal($costBreakdown, 'penalty');
        $earlyPaymentTotal = $this->costBreakdownService->costTotal($costBreakdown, 'early_payment');
        $discountTotal = $this->costBreakdownService->costTotal($costBreakdown, 'discount');
        $otherCostTotal = $this->costBreakdownService->costTotal($costBreakdown, 'other');
        // Backward compatible: service_fee_total toplam overhead olarak kalır
        $serviceFeeTotal = round($serviceFeeOnly + $advertisingTotal + $penaltyTotal + $earlyPaymentTotal + $discountTotal + $otherCostTotal, 2);
        $actualWithholdingTotal = $this->costBreakdownService->costTotal($costBreakdown, 'withholding');
        $withholding = $this->withholdingEffectService->calculate(
            store: $store,
            order: $order,
            items: $items,
            grossRevenue: $grossRevenue,
            actualWithholdingTotal: $actualWithholdingTotal,
        );
        $withholdingTotal = (float) $withholding['amount'];
        $sellerRevenueNet = (float) $costBreakdown['seller_revenue_net'];
        $hasFinancials = $settledFinancialEvents->isNotEmpty();
        $sellerRevenueAlreadyExcludesCommission = strtolower((string) $store->marketplace) === 'trendyol'
            && $settledFinancialEvents->contains(fn (OrderFinancialEvent $event) => (
                strtolower((string) $event->event_source) === 'settlements'
                && strtolower((string) $event->event_type) === 'seller_revenue'
            ));
        $confirmedCommissionDeduction = $sellerRevenueAlreadyExcludesCommission ? 0.0 : $commissionTotal;
        $settings = new MpSettingsService((int) $store->user_id);
        $estimatedServiceFee = $settings->getEstimatedPlatformServiceFee((string) $store->marketplace);
        $hasActualServiceFee = (int) data_get(
            $costBreakdown,
            'categories.service_fee.event_count',
            0,
        ) > 0;
        $effectiveServiceFeeOnly = $hasActualServiceFee
            ? $serviceFeeOnly
            : $estimatedServiceFee;
        $effectiveServiceFeeTotal = round(
            $effectiveServiceFeeOnly
                + $advertisingTotal
                + $penaltyTotal
                + $earlyPaymentTotal
                + $discountTotal
                + $otherCostTotal,
            2,
        );

        $effectiveCommissionTotal = $commissionTotal > 0
            ? $commissionTotal
            : $estimatedCommission;
        $vat = $this->vatEffectService->calculate(
            store: $store,
            order: $order,
            items: $items,
            grossRevenue: $grossRevenue,
            commissionTotal: $effectiveCommissionTotal,
            cargoTotal: $hasFinancials ? $cargoTotal : 0.0,
            serviceFeeTotal: $effectiveServiceFeeTotal,
        );
        $vatEffect = (float) $vat['net_vat'];

        $estimatedCalculation = $this->profitCalculationService->calculate([
            'gross_revenue' => $grossRevenue,
            'cogs' => $cogsCost,
            'packaging_cost' => $packagingCost,
            'commission' => $estimatedCommission,
            'own_cargo' => $ownCargoCost,
            'service_fee' => $estimatedServiceFee,
            'withholding' => $withholdingTotal,
            'net_vat' => $vatEffect,
        ]);
        $estimatedNetReceivable = $estimatedCalculation['net_receivable'];
        $estimatedProfit = $estimatedCalculation['cash_profit'];

        // Finans kaynakları satıcı gelirini farklı seviyelerde (brüt veya komisyon
        // sonrası) verebilir. Önce kanonik brüt bazı yeniden kur, sonra tek formülü uygula.
        $confirmedGrossBasis = round(
            $sellerRevenueNet - $confirmedCommissionDeduction + $effectiveCommissionTotal,
            2,
        );
        $confirmedCalculation = $this->profitCalculationService->calculate([
            'gross_revenue' => $confirmedGrossBasis,
            'cogs' => $cogsCost,
            'packaging_cost' => $packagingCost,
            'commission' => $effectiveCommissionTotal,
            'marketplace_cargo' => $cargoTotal,
            'own_cargo' => $ownCargoCost,
            'service_fee' => $effectiveServiceFeeOnly,
            'advertising' => $advertisingTotal,
            'other_marketplace_deductions' => $penaltyTotal
                + $earlyPaymentTotal
                + $discountTotal
                + $otherCostTotal,
            'withholding' => $withholdingTotal,
            'net_vat' => $vatEffect,
        ]);
        $netReceivable = $hasFinancials
            ? $confirmedCalculation['net_receivable']
            : $estimatedNetReceivable;
        $confirmedProfit = $hasFinancials
            ? $confirmedCalculation['cash_profit']
            : $estimatedProfit;

        // OrderLifecycleResolver ile iade/iptal zarar hesaplaması
        $returnLoss = $this->lifecycleResolver->calculateReturnLoss(
            order: $order,
            cargoTotal: $cargoTotal,
            ownCargoCost: $ownCargoCost,
            packagingCost: $packagingCost,
        );

        $returnEffect = $returnLoss['loss_amount'];

        // Profit state'i lifecycle'a göre belirle
        $profitState = $this->lifecycleResolver->profitState($order, $hasFinancials);

        $profitValue = $hasFinancials ? $confirmedProfit : $estimatedProfit;
        $marginPercent = ProfitabilityMetric::multiplierOrZero(
            $profitValue,
            ProfitabilityMetric::productCost($cogsCost, $packagingCost),
        );

        $snapshot = OrderProfitSnapshot::query()->firstOrNew([
            'store_id' => $store->id,
            'channel_order_id' => $order->id,
            'channel_order_item_id' => null,
        ]);

        $snapshot->fill([
            'profit_state' => $profitState,
            'gross_revenue' => $grossRevenue * $exchangeRate,
            'net_receivable' => $netReceivable * $exchangeRate,
            'commission_total' => $effectiveCommissionTotal * $exchangeRate,
            'cargo_total' => $cargoTotal * $exchangeRate,
            'service_fee_total' => $effectiveServiceFeeTotal * $exchangeRate,
            'advertising_total' => $advertisingTotal * $exchangeRate,
            'penalty_total' => $penaltyTotal * $exchangeRate,
            'early_payment_total' => $earlyPaymentTotal * $exchangeRate,
            'discount_total' => $discountTotal * $exchangeRate,
            'other_cost_total' => $otherCostTotal * $exchangeRate,
            'withholding_total' => $withholdingTotal * $exchangeRate,
            'packaging_cost' => $packagingCost * $exchangeRate,
            'own_cargo_cost' => $ownCargoCost * $exchangeRate,
            'cogs_cost' => $cogsCost * $exchangeRate,
            'return_effect' => $returnEffect * $exchangeRate,
            'vat_effect' => $vatEffect * $exchangeRate,
            'estimated_profit' => $estimatedProfit * $exchangeRate,
            'confirmed_profit' => $confirmedProfit * $exchangeRate,
            'margin_percent' => $marginPercent,
            'calculated_at' => now(),
            'version' => ((int) $snapshot->version) + 1,
            'currency' => $currency,
            'exchange_rate' => $exchangeRate,
        ]);
        $snapshot->save();
    }

    protected function estimatedCommissionRate(MarketplaceStore $store, mixed $item): float
    {
        if (strtolower((string) $store->marketplace) === 'koctas') {
            return (new MpSettingsService((int) $store->user_id))->getProductProfitKoctasCommissionRate();
        }

        return round((float) (
            $item->commission_rate
            ?? $item->listing?->commission_rate
            ?? $item->product?->commission_rate
            ?? 0
        ), 2);
    }

    protected function actualOwnCargoCost(ChannelOrder $order): float
    {
        if (!Schema::hasTable('shipments')) {
            return 0.0;
        }

        $total = Shipment::query()
            ->where('store_id', $order->store_id)
            ->where('carrier_code', 'surat')
            ->whereIn('flow_type', ['order', 'return', 'exchange', 'part'])
            ->where(function ($query) use ($order) {
                $query->where('channel_order_id', $order->id);

                if (filled($order->order_number)) {
                    $query->orWhere('order_number', $order->order_number);
                }
            })
            ->selectRaw('COALESCE(SUM(CASE WHEN actual_cost > 0 THEN actual_cost WHEN invoice_cost > 0 THEN invoice_cost ELSE 0 END), 0) as total')
            ->value('total');

        return round((float) $total, 2);
    }
}
