<?php

namespace App\Services\Marketplace;

use App\Models\ChannelOrder;
use App\Models\MarketplaceStore;
use App\Models\OrderFinancialEvent;
use App\Models\OrderProfitSnapshot;
use App\Services\Marketplace\Support\OrderLifecycleResolver;
use Illuminate\Support\Collection;

class MarketplaceProfitSnapshotService
{
    protected const SELLER_REVENUE_EVENT_TYPES = ['seller_revenue', 'sale', 'capture', 'refund', 'void'];

    protected const SERVICE_FEE_EVENT_TYPES = ['service_fee', 'deduction_invoice', 'fee'];

    protected const CONFIRMING_EVENT_TYPES = ['seller_revenue', 'sale', 'capture', 'refund', 'void', 'commission', 'cargo', 'service_fee', 'deduction_invoice', 'withholding', 'fee'];

    public function __construct(
        protected OrderLifecycleResolver $lifecycleResolver,
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
            ->with(['items.product', 'financialEvents', 'packages'])
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
        $estimatedCommission = round((float) $items->sum(function ($item) {
            $baseAmount = (float) ($item->billable_amount ?: $item->gross_amount ?: ((float) $item->unit_price * (int) $item->quantity));
            $rate = (float) ($item->commission_rate ?: 0);

            return $baseAmount * $rate / 100;
        }), 2);

        $cogsCost = round((float) $items->sum(fn ($item) => ((float) ($item->product?->cogs ?? 0)) * (int) $item->quantity), 2);
        $packagingCost = round((float) $items->sum(fn ($item) => ((float) ($item->product?->packaging_cost ?? 0)) * (int) $item->quantity), 2);
        $ownCargoCost = round((float) $items->sum(fn ($item) => ((float) ($item->product?->cargo_cost ?? 0)) * (int) $item->quantity), 2);

        $commissionTotal = $this->costTotal($financialEvents, ['commission']);
        $cargoTotal = $this->costTotal($financialEvents, ['cargo']);
        $serviceFeeTotal = $this->costTotal($financialEvents, self::SERVICE_FEE_EVENT_TYPES);
        $withholdingTotal = $this->costTotal($financialEvents, ['withholding']);
        $sellerRevenueNet = $this->netAmount($financialEvents, self::SELLER_REVENUE_EVENT_TYPES);

        $netReceivable = round($sellerRevenueNet - $cargoTotal - $serviceFeeTotal - $withholdingTotal, 2);
        $estimatedProfit = round($grossRevenue - $estimatedCommission - $cogsCost - $packagingCost - $ownCargoCost, 2);
        $hasFinancials = $financialEvents->whereIn('event_type', self::CONFIRMING_EVENT_TYPES)->isNotEmpty();
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
        $marginPercent = $grossRevenue > 0
            ? round(($profitValue / $grossRevenue) * 100, 2)
            : 0;

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
}
