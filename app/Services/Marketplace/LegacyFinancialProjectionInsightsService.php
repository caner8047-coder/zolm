<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceStore;
use App\Models\MpOrder;
use App\Models\OrderFinancialEvent;
use App\Models\OrderProfitSnapshot;
use Illuminate\Database\Eloquent\Builder;

class LegacyFinancialProjectionInsightsService
{
    /**
     * @return array{
     *     pending_rows:int,
     *     projected_rows:int,
     *     legacy_event_orders:int,
     *     confirmed_orders:int,
     *     last_projected_at:?string
     * }
     */
    public function summaryForUser(int $userId, ?int $storeId = null, ?int $legalEntityId = null): array
    {
        $storeIds = MarketplaceStore::query()
            ->where('user_id', $userId)
            ->when($storeId, fn (Builder $query) => $query->whereKey($storeId))
            ->when($legalEntityId, fn (Builder $query) => $query->where('legal_entity_id', $legalEntityId))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($storeIds === []) {
            return [
                'pending_rows' => 0,
                'projected_rows' => 0,
                'legacy_event_orders' => 0,
                'confirmed_orders' => 0,
                'last_projected_at' => null,
            ];
        }

        $mpOrdersBase = MpOrder::query()
            ->whereHas('period', fn (Builder $query) => $query->where('user_id', $userId))
            ->where(function (Builder $query) use ($storeIds) {
                $query->whereIn('store_id', $storeIds)
                    ->orWhereNull('store_id');
            })
            ->whereExists(function ($query) use ($storeIds) {
                $query->selectRaw('1')
                    ->from('channel_orders')
                    ->whereColumn('channel_orders.order_number', 'mp_orders.order_number')
                    ->whereIn('channel_orders.store_id', $storeIds);
            });

        $pendingRows = (clone $mpOrdersBase)->whereNull('projected_at')->count();
        $projectedRows = (clone $mpOrdersBase)->whereNotNull('projected_at')->count();
        $lastProjectedAt = (clone $mpOrdersBase)->max('projected_at');

        $legacyEventOrders = OrderFinancialEvent::query()
            ->whereIn('store_id', $storeIds)
            ->where('event_source', 'legacy_mp_order')
            ->distinct()
            ->count('channel_order_id');

        $confirmedOrders = OrderProfitSnapshot::query()
            ->whereIn('store_id', $storeIds)
            ->whereNull('channel_order_item_id')
            ->where('profit_state', 'confirmed')
            ->whereExists(function ($query) use ($storeIds) {
                $query->selectRaw('1')
                    ->from('order_financial_events')
                    ->whereColumn('order_financial_events.channel_order_id', 'order_profit_snapshots.channel_order_id')
                    ->where('order_financial_events.event_source', 'legacy_mp_order')
                    ->whereIn('order_financial_events.store_id', $storeIds);
            })
            ->distinct()
            ->count('channel_order_id');

        return [
            'pending_rows' => (int) $pendingRows,
            'projected_rows' => (int) $projectedRows,
            'legacy_event_orders' => (int) $legacyEventOrders,
            'confirmed_orders' => (int) $confirmedOrders,
            'last_projected_at' => $lastProjectedAt ? (string) $lastProjectedAt : null,
        ];
    }
}
