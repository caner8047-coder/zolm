<?php

namespace App\Services\Marketplace;

use App\Models\ChannelOrder;
use App\Models\MarketplaceStore;
use App\Models\MpOrder;
use Illuminate\Database\Eloquent\Builder;

class LegacyFinancialProjectionBacklogService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function summaryForUser(int $userId): array
    {
        return MarketplaceStore::query()
            ->where('user_id', $userId)
            ->orderBy('store_name')
            ->get()
            ->map(function (MarketplaceStore $store): array {
                $pendingRows = $this->pendingRowsForStore($store);

                return [
                    'store_id' => $store->id,
                    'store_name' => $store->store_name,
                    'marketplace' => $store->marketplace,
                    'pending_rows' => $pendingRows,
                ];
            })
            ->filter(fn (array $row) => $row['pending_rows'] > 0)
            ->values()
            ->all();
    }

    public function pendingRowsForStore(MarketplaceStore $store): int
    {
        $channelOrderNumbers = ChannelOrder::query()
            ->where('store_id', $store->id)
            ->pluck('order_number')
            ->filter()
            ->unique()
            ->values();

        if ($channelOrderNumbers->isEmpty()) {
            return 0;
        }

        return MpOrder::query()
            ->whereHas('period', fn (Builder $builder) => $builder->where('user_id', $store->user_id))
            ->whereIn('order_number', $channelOrderNumbers)
            ->where(function (Builder $builder) use ($store) {
                $builder->where('store_id', $store->id)
                    ->orWhereNull('store_id');
            })
            ->whereNull('projected_at')
            ->count();
    }
}
