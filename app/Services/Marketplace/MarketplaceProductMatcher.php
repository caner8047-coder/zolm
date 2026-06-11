<?php

namespace App\Services\Marketplace;

use App\Models\ChannelListing;
use App\Models\ChannelOrderItem;
use App\Models\MarketplaceStore;
use App\Models\MpProduct;
use App\Models\ProductMatchIssue;
use Illuminate\Database\Eloquent\Builder;

class MarketplaceProductMatcher
{
    /**
     * @return array{product: ?MpProduct, source: string|null, reason: string|null, candidate_ids: array<int>}
     */
    public function resolve(MarketplaceStore $store, ?string $stockCode, ?string $barcode): array
    {
        if (!$store->syncProfile?->auto_match_enabled) {
            return [
                'product' => null,
                'source' => null,
                'reason' => 'auto_match_disabled',
                'candidate_ids' => [],
            ];
        }

        $stockCode = $this->clean($stockCode);
        $barcode = $this->clean($barcode);

        if ($stockCode) {
            $stockCandidates = $this->candidateQuery($store)
                ->where('stock_code', $stockCode)
                ->get();

            if ($stockCandidates->count() === 1) {
                return [
                    'product' => $stockCandidates->first(),
                    'source' => 'stock_code',
                    'reason' => null,
                    'candidate_ids' => $stockCandidates->pluck('id')->all(),
                ];
            }

            if ($stockCandidates->count() > 1) {
                return [
                    'product' => null,
                    'source' => null,
                    'reason' => 'ambiguous_stock_code',
                    'candidate_ids' => $stockCandidates->pluck('id')->all(),
                ];
            }
        }

        if ($barcode && $store->syncProfile?->barcode_fallback_enabled) {
            $barcodeCandidates = $this->candidateQuery($store)
                ->where('barcode', $barcode)
                ->get();

            if ($barcodeCandidates->count() === 1) {
                return [
                    'product' => $barcodeCandidates->first(),
                    'source' => 'barcode',
                    'reason' => null,
                    'candidate_ids' => $barcodeCandidates->pluck('id')->all(),
                ];
            }

            if ($barcodeCandidates->count() > 1) {
                return [
                    'product' => null,
                    'source' => null,
                    'reason' => 'ambiguous_barcode',
                    'candidate_ids' => $barcodeCandidates->pluck('id')->all(),
                ];
            }
        }

        return [
            'product' => null,
            'source' => null,
            'reason' => 'not_found',
            'candidate_ids' => [],
        ];
    }

    public function applyToListing(ChannelListing $listing, ?string $stockCode, ?string $barcode): void
    {
        $listing->loadMissing('store');

        $result = $this->resolve($listing->store, $stockCode, $barcode);
        $product = $result['product'];

        if ($product) {
            $listing->forceFill([
                'mp_product_id' => $product->id,
            ])->save();

            $this->closeIssue($listing->store_id, $listing->id);

            return;
        }

        $this->storeIssue(
            storeId: $listing->store_id,
            channelListingId: $listing->id,
            reason: $result['reason'],
            candidateIds: $result['candidate_ids'],
        );
    }

    public function applyToOrderItem(ChannelOrderItem $item, ?string $stockCode, ?string $barcode): void
    {
        $item->loadMissing(['store.syncProfile', 'listing.product']);

        $listing = $this->resolveListingFromOrderItemPayload($item)
            ?: $item->listing
            ?: $this->resolveListing($item->store_id, $stockCode, $barcode)
            ?: null;

        if ($listing?->mp_product_id) {
            $item->forceFill([
                'mp_product_id' => $listing->mp_product_id,
                'channel_listing_id' => $listing->id,
                'is_matched' => true,
                'match_source' => 'channel_listing',
            ])->save();

            $this->closeIssue($item->store_id, $listing->id);

            return;
        }

        $result = $this->resolve($item->store, $stockCode, $barcode);

        if ($result['product']) {
            $item->forceFill([
                'mp_product_id' => $result['product']->id,
                'channel_listing_id' => $listing?->id,
                'is_matched' => true,
                'match_source' => $result['source'],
            ])->save();

            if ($listing && !$listing->mp_product_id) {
                $listing->forceFill([
                    'mp_product_id' => $result['product']->id,
                ])->save();
            }

            if ($listing) {
                $this->closeIssue($item->store_id, $listing->id);
            }

            return;
        }

        $item->forceFill([
            'channel_listing_id' => $listing?->id,
            'mp_product_id' => null,
            'is_matched' => false,
            'match_source' => null,
        ])->save();

        $this->storeIssue(
            storeId: $item->store_id,
            channelListingId: $listing?->id,
            reason: $result['reason'],
            candidateIds: $result['candidate_ids'],
        );
    }

    public function resolveListing(int $storeId, ?string $stockCode, ?string $barcode): ?ChannelListing
    {
        $stockCode = $this->clean($stockCode);
        $barcode = $this->clean($barcode);

        if ($stockCode) {
            $listing = ChannelListing::query()
                ->where('store_id', $storeId)
                ->whereHas('channelProduct', fn (Builder $query) => $query->where('stock_code', $stockCode))
                ->first();

            if ($listing) {
                return $listing;
            }
        }

        if ($barcode) {
            return ChannelListing::query()
                ->where('store_id', $storeId)
                ->whereHas('channelProduct', fn (Builder $query) => $query->where('barcode', $barcode))
                ->first();
        }

        return null;
    }

    protected function resolveListingFromOrderItemPayload(ChannelOrderItem $item): ?ChannelListing
    {
        if ($this->normalizeMarketplace($item->store?->marketplace) !== 'woocommerce') {
            return null;
        }

        $variationId = $this->cleanExternalId(data_get($item->raw_payload ?? [], 'variation_id'));

        if ($variationId !== null) {
            $variationListing = $this->resolveListingByExternalProductId($item->store_id, $variationId);

            if ($variationListing) {
                return $variationListing;
            }
        }

        $productId = $this->cleanExternalId(data_get($item->raw_payload ?? [], 'product_id'));

        if ($productId === null) {
            return null;
        }

        $productListing = $this->resolveListingByExternalProductId($item->store_id, $productId);

        if ($productListing?->mp_product_id) {
            return $productListing;
        }

        $variationListings = ChannelListing::query()
            ->where('store_id', $item->store_id)
            ->whereNotNull('mp_product_id')
            ->whereHas('channelProduct', fn (Builder $query) => $query->where('external_parent_id', $productId))
            ->limit(2)
            ->get();

        return $variationListings->count() === 1 ? $variationListings->first() : null;
    }

    protected function resolveListingByExternalProductId(int $storeId, string $externalProductId): ?ChannelListing
    {
        return ChannelListing::query()
            ->where('store_id', $storeId)
            ->where(function (Builder $query) use ($externalProductId) {
                $query
                    ->where('listing_id', $externalProductId)
                    ->orWhereHas('channelProduct', fn (Builder $productQuery) => $productQuery->where('external_product_id', $externalProductId));
            })
            ->orderByRaw('CASE WHEN mp_product_id IS NULL THEN 1 ELSE 0 END')
            ->first();
    }

    protected function cleanExternalId(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' && $value !== '0' ? $value : null;
    }

    protected function normalizeMarketplace(?string $marketplace): string
    {
        return strtolower(trim((string) $marketplace));
    }

    /**
     * @return Builder<MpProduct>
     */
    protected function candidateQuery(MarketplaceStore $store): Builder
    {
        return MpProduct::query()->where('user_id', $store->user_id);
    }

    /**
     * @param  array<int>  $candidateIds
     */
    protected function storeIssue(int $storeId, ?int $channelListingId, ?string $reason, array $candidateIds = []): void
    {
        $issue = ProductMatchIssue::query()
            ->where('store_id', $storeId)
            ->when(
                $channelListingId,
                fn ($query) => $query->where('channel_listing_id', $channelListingId),
                fn ($query) => $query->whereNull('channel_listing_id')
            )
            ->where('match_status', 'pending')
            ->latest('id')
            ->first();

        $issue = $issue ?: new ProductMatchIssue();
        $wasRecentlyCreated = !$issue->exists;

        $issue->fill([
            'store_id' => $storeId,
            'channel_listing_id' => $channelListingId,
            'match_status' => 'pending',
            'match_reason' => $reason,
            'candidate_ids_json' => $candidateIds,
        ])->save();

        if ($wasRecentlyCreated) {
            app(\App\Services\NotificationCenterService::class)->notifyProductMatchIssue($issue);
        }
    }

    protected function closeIssue(int $storeId, ?int $channelListingId): void
    {
        if (!$channelListingId) {
            return;
        }

        ProductMatchIssue::query()
            ->where('store_id', $storeId)
            ->where('channel_listing_id', $channelListingId)
            ->where('match_status', 'pending')
            ->update([
                'match_status' => 'resolved',
                'resolved_at' => now(),
            ]);
    }

    protected function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
