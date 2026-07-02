<?php

namespace App\Services\Marketplace;

use App\Models\ChannelListing;
use App\Models\ChannelOrderItem;
use App\Models\ChannelProduct;
use App\Models\MarketplaceStore;
use App\Models\MpProduct;
use App\Models\ProductMatchIssue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class MarketplaceProductMatcher
{
    /**
     * @return array{product: ?MpProduct, source: string|null, reason: string|null, candidate_ids: array<int>}
     */
    public function resolve(
        MarketplaceStore $store,
        ?string $stockCode,
        ?string $barcode,
        ?string $title = null,
        ?string $brand = null,
        ?string $categoryName = null,
    ): array {
        if (!$store->syncProfile?->auto_match_enabled) {
            return [
                'product' => null,
                'source' => null,
                'reason' => 'auto_match_disabled',
                'candidate_ids' => $this->contextCandidateIds($store, $title, $brand, $categoryName),
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

        $candidateIds = $this->contextCandidateIds($store, $title, $brand, $categoryName);

        return [
            'product' => null,
            'source' => null,
            'reason' => $candidateIds !== [] ? 'candidate_found' : 'not_found',
            'candidate_ids' => $candidateIds,
        ];
    }

    public function applyToListing(ChannelListing $listing, ?string $stockCode, ?string $barcode): void
    {
        $listing->loadMissing(['store', 'channelProduct']);

        $result = $this->resolve(
            $listing->store,
            $stockCode,
            $barcode,
            $listing->channelProduct?->title,
            $listing->channelProduct?->brand,
            $listing->channelProduct?->category_name,
        );
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
        $item->loadMissing(['store.syncProfile', 'listing.channelProduct', 'listing.product', 'order']);

        $listing = $this->resolveListingFromOrderItemPayload($item)
            ?: $item->listing
            ?: $this->resolveListing($item->store_id, $stockCode, $barcode)
            ?: $this->ensureOrderItemListing($item, $stockCode, $barcode);

        if ($listing?->mp_product_id) {
            $item->forceFill([
                'mp_product_id' => $listing->mp_product_id,
                'channel_listing_id' => $listing->id,
                'is_matched' => true,
                'match_source' => 'channel_listing',
            ])->save();

            $this->closeIssue($item->store_id, $listing->id);
            $this->closeUnscopedIssueIfCovered($item->store_id);

            return;
        }

        $channelProduct = $listing?->channelProduct;
        $contextTitle = trim(implode(' ', array_filter([
            $channelProduct?->title,
            $item->product_name,
        ], fn ($value) => trim((string) $value) !== '')));
        $result = $this->resolve(
            $item->store,
            $stockCode,
            $barcode,
            $contextTitle !== '' ? $contextTitle : null,
            $channelProduct?->brand,
            $channelProduct?->category_name,
        );

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
                $this->closeUnscopedIssueIfCovered($item->store_id);
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

        if ($listing) {
            $this->closeUnscopedIssueIfCovered($item->store_id);
        }
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

    protected function ensureOrderItemListing(ChannelOrderItem $item, ?string $stockCode, ?string $barcode): ?ChannelListing
    {
        $identity = $this->orderItemListingIdentity($item, $stockCode, $barcode);

        if ($identity === null) {
            return null;
        }

        return DB::transaction(function () use ($item, $stockCode, $barcode, $identity): ChannelListing {
            $product = $this->resolveOrderItemChannelProduct($item, $identity['external_id'], $stockCode, $barcode);
            $product->fill([
                'store_id' => $item->store_id,
                'external_product_id' => $product->exists ? $product->external_product_id : $identity['external_id'],
                'stock_code' => $this->preferOrderItemValue($stockCode, $product->stock_code),
                'barcode' => $this->preferOrderItemValue($barcode, $product->barcode),
                'title' => $this->preferOrderItemValue($item->product_name, $product->title),
                'raw_payload' => $this->orderItemFallbackPayload($item, $identity),
                'last_synced_at' => $item->last_synced_at ?: now(),
            ])->save();

            $listing = ChannelListing::query()
                ->where('store_id', $item->store_id)
                ->where('listing_id', $identity['external_id'])
                ->first();

            if (!$listing) {
                $listing = ChannelListing::query()
                    ->where('store_id', $item->store_id)
                    ->where('channel_product_id', $product->id)
                    ->where('listing_id', 'like', 'order-item:%')
                    ->first() ?: new ChannelListing();
            }

            $listing->fill([
                'store_id' => $item->store_id,
                'channel_product_id' => $product->id,
                'listing_id' => $listing->exists ? $listing->listing_id : $identity['external_id'],
                'listing_status' => $listing->listing_status ?: 'draft',
                'sale_price' => $this->preferOrderItemValue($item->unit_price, $listing->sale_price),
                'list_price' => $this->preferOrderItemValue($item->gross_amount, $listing->list_price),
                'currency' => $listing->currency ?: 'TRY',
                'last_synced_at' => $item->last_synced_at ?: now(),
            ])->save();

            return $listing->fresh(['channelProduct', 'product']) ?: $listing;
        });
    }

    /**
     * @return array{type: string, value: string, external_id: string}|null
     */
    protected function orderItemListingIdentity(ChannelOrderItem $item, ?string $stockCode, ?string $barcode): ?array
    {
        foreach ([
            'stock' => $this->clean($stockCode),
            'barcode' => $this->clean($barcode),
            'line' => $this->clean($item->external_line_id),
        ] as $type => $value) {
            if ($value !== null) {
                $hash = substr(sha1($item->store_id.'|'.$type.'|'.$value), 0, 24);

                return [
                    'type' => $type,
                    'value' => $value,
                    'external_id' => 'order-item:'.$type.':'.$hash,
                ];
            }
        }

        return null;
    }

    protected function resolveOrderItemChannelProduct(
        ChannelOrderItem $item,
        string $externalProductId,
        ?string $stockCode,
        ?string $barcode,
    ): ChannelProduct {
        $product = ChannelProduct::query()
            ->where('store_id', $item->store_id)
            ->where('external_product_id', $externalProductId)
            ->first();

        if ($product) {
            return $product;
        }

        foreach ([
            ['barcode', $barcode],
            ['stock_code', $stockCode],
        ] as [$column, $value]) {
            $fallback = $this->uniqueProductFallback($item->store_id, $column, $value);

            if ($fallback) {
                return $fallback;
            }
        }

        return new ChannelProduct();
    }

    protected function uniqueProductFallback(int $storeId, string $column, mixed $value): ?ChannelProduct
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $matches = ChannelProduct::query()
            ->where('store_id', $storeId)
            ->where($column, $value)
            ->limit(2)
            ->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /**
     * @param  array{type: string, value: string, external_id: string}  $identity
     * @return array<string, mixed>
     */
    protected function orderItemFallbackPayload(ChannelOrderItem $item, array $identity): array
    {
        return [
            'source' => 'order_item_fallback',
            'identity' => $identity,
            'channel_order_item_id' => $item->id,
            'channel_order_id' => $item->channel_order_id,
            'order_number' => $item->order?->order_number,
            'external_line_id' => $item->external_line_id,
            'stock_code' => $item->stock_code,
            'barcode' => $item->barcode,
            'product_name' => $item->product_name,
        ];
    }

    protected function preferOrderItemValue(mixed $incoming, mixed $existing): mixed
    {
        if ($incoming === null) {
            return $existing;
        }

        if (is_string($incoming) && trim($incoming) === '') {
            return $existing;
        }

        return $incoming;
    }

    protected function closeUnscopedIssueIfCovered(int $storeId): void
    {
        $hasUnscopedUnmatchedItems = ChannelOrderItem::query()
            ->where('store_id', $storeId)
            ->whereNull('channel_listing_id')
            ->where(function (Builder $query) {
                $query->whereNull('mp_product_id')
                    ->orWhere('is_matched', false);
            })
            ->exists();

        if ($hasUnscopedUnmatchedItems) {
            return;
        }

        ProductMatchIssue::query()
            ->where('store_id', $storeId)
            ->whereNull('channel_listing_id')
            ->where('match_status', 'pending')
            ->update([
                'match_status' => 'resolved',
                'resolved_at' => now(),
            ]);
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
     * @return array<int>
     */
    protected function contextCandidateIds(
        MarketplaceStore $store,
        ?string $title,
        ?string $brand = null,
        ?string $categoryName = null,
    ): array {
        $modelVariants = $this->modelCodeVariantsFromText($title);
        $titleTokens = $this->candidateTextTokens($title);

        if ($modelVariants === [] && $titleTokens === []) {
            return [];
        }

        $modelMatches = collect();
        if ($modelVariants !== []) {
            $modelMatches = $this->candidateQuery($store)
                ->select(['id', 'product_name', 'stock_code', 'barcode', 'model_code', 'brand', 'category_name', 'sale_price'])
                ->where(function (Builder $builder) use ($modelVariants): void {
                    foreach ($modelVariants as $variant) {
                        $builder
                            ->orWhere('model_code', $variant)
                            ->orWhere('model_code', 'like', $variant . '%');
                    }
                })
                ->limit(40)
                ->get();
        }

        $tokenMatches = collect();
        if ($titleTokens !== []) {
            $tokenMatches = $this->candidateQuery($store)
                ->select(['id', 'product_name', 'stock_code', 'barcode', 'model_code', 'brand', 'category_name', 'sale_price'])
                ->where(function (Builder $builder) use ($titleTokens): void {
                    foreach (array_slice($titleTokens, 0, 6) as $token) {
                        $builder
                            ->orWhere('product_name', 'like', '%' . $token . '%')
                            ->orWhere('model_code', 'like', '%' . $token . '%');
                    }
                })
                ->limit(80)
                ->get();
        }

        return $modelMatches
            ->concat($tokenMatches)
            ->unique('id')
            ->map(function (MpProduct $product) use ($modelVariants, $titleTokens, $brand, $categoryName): array {
                return [
                    'id' => (int) $product->id,
                    'score' => $this->contextCandidateScore($product, $modelVariants, $titleTokens, $brand, $categoryName),
                    'sale_price' => (float) ($product->sale_price ?? 0),
                ];
            })
            ->filter(fn (array $row) => $row['score'] >= 10)
            ->sortByDesc(fn (array $row) => [$row['score'], $row['sale_price']])
            ->pluck('id')
            ->take(8)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $modelVariants
     * @param  array<int, string>  $titleTokens
     */
    protected function contextCandidateScore(
        MpProduct $product,
        array $modelVariants,
        array $titleTokens,
        ?string $brand,
        ?string $categoryName,
    ): int {
        $score = 0;
        $productModel = $this->normalizeCode($product->model_code);

        foreach ($modelVariants as $variant) {
            if ($productModel === '') {
                continue;
            }

            if ($productModel === $variant) {
                $score += 90;
                continue;
            }

            if (str_starts_with($productModel, $variant) || str_starts_with($variant, $productModel)) {
                $score += 70;
            }
        }

        $candidateTokens = array_unique(array_merge(
            $this->candidateTextTokens($product->product_name),
            $this->candidateTextTokens($product->model_code),
        ));
        $overlap = count(array_intersect($titleTokens, $candidateTokens));

        if ($overlap > 0) {
            $score += min(48, $overlap * 8);
        }

        if ($this->normalizeLookupText($brand) !== '' && $this->normalizeLookupText($brand) === $this->normalizeLookupText($product->brand)) {
            $score += 8;
        }

        if ($this->normalizeLookupText($categoryName) !== '' && $this->normalizeLookupText($categoryName) === $this->normalizeLookupText($product->category_name)) {
            $score += 6;
        }

        return $score;
    }

    /**
     * @return array<int, string>
     */
    protected function modelCodeVariantsFromText(?string $text): array
    {
        $upper = strtoupper((string) $text);
        preg_match_all('/ZEM[A-Z0-9]+/', $upper, $matches);

        $variants = [];

        foreach ($matches[0] ?? [] as $token) {
            $token = $this->normalizeCode($token);

            if ($token === '') {
                continue;
            }

            $variants[] = $token;
            $withoutDigits = preg_replace('/\d+$/', '', $token) ?: $token;
            $variants[] = $withoutDigits;

            while (strlen($withoutDigits) > 6) {
                $withoutDigits = substr($withoutDigits, 0, -1);
                $variants[] = $withoutDigits;
            }
        }

        return array_values(array_unique(array_filter($variants, fn (string $variant) => strlen($variant) >= 6)));
    }

    /**
     * @return array<int, string>
     */
    protected function candidateTextTokens(?string $text): array
    {
        $normalized = $this->normalizeLookupText($text);

        if ($normalized === '') {
            return [];
        }

        $stopWords = [
            'adet', 'one', 'size', 'olan', 'icin', 'için', 'ile', 've', 'bir', 'iki',
            'tak', 'takim', 'takimi', 'takımı', 'urun', 'ürün', 'seti',
        ];

        return collect(preg_split('/\s+/', $normalized) ?: [])
            ->map(fn ($token) => trim((string) $token))
            ->filter(fn ($token) => mb_strlen($token) >= 4)
            ->reject(fn ($token) => in_array($token, $stopWords, true))
            ->unique()
            ->take(10)
            ->values()
            ->all();
    }

    protected function normalizeCode(?string $value): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $value)) ?: '';
    }

    protected function normalizeLookupText(?string $value): string
    {
        $value = mb_strtolower((string) $value, 'UTF-8');
        $value = str_replace(['ı', 'İ'], ['i', 'i'], $value);
        $value = preg_replace('/[^[:alnum:]\s]+/u', ' ', $value) ?: '';
        $value = preg_replace('/\s+/u', ' ', $value) ?: '';

        return trim($value);
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
