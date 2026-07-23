<?php

namespace App\Services\Marketplace;

use App\Models\ChannelClaimItem;
use App\Models\ChannelOrderItem;
use App\Models\MarketplaceStore;
use App\Models\MpProduct;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class MarketplaceProductReturnRateService
{
    /**
     * Sipariş satırları iade metriklerinin paydası, onaylanmış/teslim edilmiş
     * iade talepleri ise payıdır. Aynı sipariş satırı iki kaynaktan gelse bile
     * en fazla sipariş miktarı kadar iade sayılır.
     *
     * @param  array<int, int|string>  $productIds
     * @return array<int, array{ordered_quantity: int, returned_quantity: int, return_rate: float|null}>
     */
    public function recalculateForProducts(array $productIds, ?int $userId = null): array
    {
        $productIds = collect($productIds)
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($productIds->isEmpty()) {
            return [];
        }

        $items = ChannelOrderItem::query()
            ->with('order:id,order_number,order_status,returned_at')
            ->whereIn('mp_product_id', $productIds)
            ->when($userId !== null, fn ($query) => $query->whereHas('store', fn ($store) => $store->where('user_id', $userId)))
            ->get();

        $claimQuantities = $this->approvedClaimQuantities($items);
        $metrics = [];

        foreach ($items as $item) {
            $productId = (int) $item->mp_product_id;
            $quantity = max(1, (int) $item->quantity);
            $key = $this->lineKey($item->store_id, $item->external_line_id);
            $claimQuantity = $key !== null ? (int) ($claimQuantities[$key] ?? 0) : 0;
            $returnedQuantity = max(
                $this->isOrderReturn($item) ? $quantity : 0,
                min($quantity, $claimQuantity),
            );

            $metrics[$productId] ??= ['ordered_quantity' => 0, 'returned_quantity' => 0];
            $metrics[$productId]['ordered_quantity'] += $quantity;
            $metrics[$productId]['returned_quantity'] += $returnedQuantity;
        }

        $result = [];

        foreach ($productIds as $productId) {
            $metric = $metrics[$productId] ?? ['ordered_quantity' => 0, 'returned_quantity' => 0];
            $ordered = $metric['ordered_quantity'];
            $returned = min($ordered, $metric['returned_quantity']);
            $rate = $ordered > 0 ? round(($returned / $ordered) * 100, 2) : null;

            $result[$productId] = [
                'ordered_quantity' => $ordered,
                'returned_quantity' => $returned,
                'return_rate' => $rate,
            ];
        }

        foreach ($result as $productId => $metric) {
            $updates = [
                'return_rate' => $metric['return_rate'],
                'return_rate_source' => $metric['ordered_quantity'] > 0 ? 'orders_and_returns' : null,
                'return_rate_calculated_at' => now(),
            ];

            // Eski bir şema kısa süreliğine migrate edilmeden çalışıyorsa ana hesap
            // akışı bozulmasın; migration sonrası adetler de kalıcı olur.
            if (Schema::hasColumn('mp_products', 'return_order_quantity')) {
                $updates['return_order_quantity'] = $metric['ordered_quantity'];
            }

            if (Schema::hasColumn('mp_products', 'return_claim_quantity')) {
                $updates['return_claim_quantity'] = $metric['returned_quantity'];
            }

            MpProduct::query()
                ->whereKey($productId)
                ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
                ->update($updates);
        }

        return $result;
    }

    public function recalculateForStore(MarketplaceStore $store): array
    {
        $productIds = ChannelOrderItem::query()
            ->where('store_id', $store->id)
            ->whereNotNull('mp_product_id')
            ->pluck('mp_product_id')
            ->all();

        return $this->recalculateForProducts($productIds, (int) $store->user_id);
    }

    /** @return array<string, int> */
    protected function approvedClaimQuantities(Collection $orderItems): array
    {
        $storeIds = $orderItems->pluck('store_id')->filter()->unique()->values();
        $lineIds = $orderItems->pluck('external_line_id')->filter()->map(static fn ($id) => (string) $id)->unique()->values();

        if ($storeIds->isEmpty() || $lineIds->isEmpty()) {
            return [];
        }

        $claims = ChannelClaimItem::query()
            ->with('claim:id,store_id,status,type')
            ->whereIn('external_order_line_id', $lineIds->all())
            ->whereHas('claim', function ($query) use ($storeIds) {
                $query->whereIn('store_id', $storeIds->all())
                    ->whereIn('status', ['delivered', 'approved'])
                    ->where(function ($typeQuery) {
                        $typeQuery->whereNull('type')->orWhereIn('type', ['return', 'refund']);
                    });
            })
            ->get();

        return $claims->reduce(function (array $carry, ChannelClaimItem $claimItem): array {
            $key = $this->lineKey((int) $claimItem->claim?->store_id, $claimItem->external_order_line_id);

            if ($key !== null) {
                $carry[$key] = ($carry[$key] ?? 0) + max(1, (int) $claimItem->quantity);
            }

            return $carry;
        }, []);
    }

    protected function isOrderReturn(ChannelOrderItem $item): bool
    {
        $status = mb_strtolower((string) ($item->line_status ?: $item->order?->order_status));

        return $item->order?->returned_at !== null
            || str_contains($status, 'return')
            || str_contains($status, 'iade');
    }

    protected function lineKey(int $storeId, mixed $externalLineId): ?string
    {
        $lineId = trim((string) $externalLineId);

        return $storeId > 0 && $lineId !== '' ? $storeId.'|'.$lineId : null;
    }
}
