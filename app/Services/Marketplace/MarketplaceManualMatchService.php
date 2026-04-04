<?php

namespace App\Services\Marketplace;

use App\Models\ChannelOrderItem;
use App\Models\MpProduct;
use App\Models\ProductMatchIssue;
use Illuminate\Support\Facades\DB;

class MarketplaceManualMatchService
{
    public function __construct(
        protected MarketplaceProfitSnapshotService $profitSnapshotService,
    ) {
    }

    /**
     * @return array{updated_items: int, impacted_orders: int}
     */
    public function manualMatch(ProductMatchIssue $issue, MpProduct $product, ?int $resolvedBy = null): array
    {
        $issue->loadMissing([
            'store.syncProfile',
            'channelListing.channelProduct',
            'channelListing.product',
        ]);

        if ($product->user_id !== $issue->store->user_id) {
            throw new \RuntimeException('Seçilen ürün bu kullanıcıya ait değil.');
        }

        $listing = $issue->channelListing;
        if (!$listing) {
            throw new \RuntimeException('Bu kayıt doğrudan bir listing ile ilişkili değil. Şimdilik sadece listing bazlı issue kayıtları manuel eşleştirilebilir.');
        }

        $channelProduct = $listing->channelProduct;
        $stockCode = trim((string) ($channelProduct?->stock_code ?: $listing->product?->stock_code));
        $barcode = trim((string) ($channelProduct?->barcode ?: $listing->product?->barcode));

        $updatedItems = 0;
        $impactedOrderIds = [];

        DB::transaction(function () use (
            $issue,
            $product,
            $resolvedBy,
            $listing,
            $stockCode,
            $barcode,
            &$updatedItems,
            &$impactedOrderIds
        ) {
            $listing->forceFill([
                'mp_product_id' => $product->id,
            ])->save();

            $itemsQuery = ChannelOrderItem::query()
                ->where('store_id', $issue->store_id)
                ->where(function ($query) use ($listing, $stockCode, $barcode) {
                    $query->where('channel_listing_id', $listing->id);

                    if ($stockCode !== '') {
                        $query->orWhere('stock_code', $stockCode);
                    }

                    if ($barcode !== '') {
                        $query->orWhere('barcode', $barcode);
                    }
                });

            $items = $itemsQuery->get();

            foreach ($items as $item) {
                $item->forceFill([
                    'channel_listing_id' => $listing->id,
                    'mp_product_id' => $product->id,
                    'is_matched' => true,
                    'match_source' => 'manual',
                ])->save();

                $updatedItems++;
                $impactedOrderIds[] = $item->channel_order_id;
            }

            ProductMatchIssue::query()
                ->where('store_id', $issue->store_id)
                ->where('channel_listing_id', $listing->id)
                ->where('match_status', 'pending')
                ->update([
                    'match_status' => 'resolved',
                    'resolved_by' => $resolvedBy,
                    'resolved_at' => now(),
                ]);
        });

        $impactedOrderIds = array_values(array_unique(array_filter($impactedOrderIds)));
        if ($impactedOrderIds !== []) {
            $this->profitSnapshotService->recalculateForOrders($issue->store, $impactedOrderIds);
        }

        return [
            'updated_items' => $updatedItems,
            'impacted_orders' => count($impactedOrderIds),
        ];
    }

    public function ignore(ProductMatchIssue $issue, ?int $resolvedBy = null): void
    {
        $issue->update([
            'match_status' => 'ignored',
            'resolved_by' => $resolvedBy,
            'resolved_at' => now(),
        ]);
    }

    public function reopen(ProductMatchIssue $issue): void
    {
        $issue->update([
            'match_status' => 'pending',
            'resolved_by' => null,
            'resolved_at' => null,
        ]);
    }
}
