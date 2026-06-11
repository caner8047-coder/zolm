<?php

namespace App\Services\Marketplace;

use App\Models\ChannelOrder;
use App\Models\MarketplaceStore;
use App\Models\OrderFinancialEvent;
use App\Models\OrderProfitSnapshot;
use App\Models\Shipment;
use App\Services\MpSettingsService;
use App\Services\Marketplace\Support\OrderLifecycleResolver;
use App\Services\ProfitabilityMetric;
use App\Services\ProductCompositionResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class MarketplaceProfitSnapshotService
{
    protected const SELLER_REVENUE_EVENT_TYPES = ['seller_revenue', 'sale', 'capture', 'refund', 'void'];

    protected const SERVICE_FEE_EVENT_TYPES = ['service_fee', 'deduction_invoice', 'fee'];

    protected const CONFIRMING_EVENT_TYPES = ['seller_revenue', 'sale', 'capture', 'refund', 'void', 'commission', 'cargo', 'service_fee', 'deduction_invoice', 'withholding', 'fee'];

    public function __construct(
        protected OrderLifecycleResolver $lifecycleResolver,
        protected ProductCompositionResolver $compositionResolver,
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

        $commissionTotal = $this->costTotal($financialEvents, ['commission']);
        $cargoTotal = $this->costTotal($financialEvents, ['cargo']);
        $serviceFeeTotal = $this->costTotal($financialEvents, self::SERVICE_FEE_EVENT_TYPES);
        $withholdingTotal = $this->costTotal($financialEvents, ['withholding']);
        $sellerRevenueNet = $this->netAmount($financialEvents, self::SELLER_REVENUE_EVENT_TYPES);
        $hasFinancials = $financialEvents->whereIn('event_type', self::CONFIRMING_EVENT_TYPES)->isNotEmpty();

        $estimatedNetReceivable = round($grossRevenue - $estimatedCommission, 2);
        $netReceivable = $hasFinancials
            ? round($sellerRevenueNet - $cargoTotal - $serviceFeeTotal - $withholdingTotal, 2)
            : $estimatedNetReceivable;
        $estimatedProfit = round($estimatedNetReceivable - $cogsCost - $packagingCost - $ownCargoCost, 2);
        $confirmedProfit = $hasFinancials
            ? round($netReceivable - $cogsCost - $packagingCost - $ownCargoCost, 2)
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
            'gross_revenue' => $grossRevenue,
            'net_receivable' => $netReceivable,
            'commission_total' => $commissionTotal > 0 ? $commissionTotal : $estimatedCommission,
            'cargo_total' => $cargoTotal,
            'service_fee_total' => $serviceFeeTotal,
            'withholding_total' => $withholdingTotal,
            'packaging_cost' => $packagingCost,
            'own_cargo_cost' => $ownCargoCost,
            'cogs_cost' => $cogsCost,
            'return_effect' => $returnEffect,
            'vat_effect' => 0,
            'estimated_profit' => $estimatedProfit,
            'confirmed_profit' => $confirmedProfit,
            'margin_percent' => $marginPercent,
            'calculated_at' => now(),
            'version' => ((int) $snapshot->version) + 1,
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

    /**
     * @param  Collection<int, OrderFinancialEvent>  $events
     * @param  array<int, string>  $types
     */
    protected function netAmount(Collection $events, array $types): float
    {
        return round((float) $events
            ->whereIn('event_type', $types)
            ->sum(fn (OrderFinancialEvent $event) => $this->signedAmount($event)), 2);
    }

    /**
     * @param  Collection<int, OrderFinancialEvent>  $events
     * @param  array<int, string>  $types
     */
    protected function costTotal(Collection $events, array $types): float
    {
        $net = $this->netAmount($events, $types);

        return round(abs(min($net, 0)), 2);
    }

    protected function signedAmount(OrderFinancialEvent $event): float
    {
        $amount = abs((float) $event->amount);

        return $event->direction === 'credit' ? $amount : -$amount;
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
