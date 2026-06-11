<?php

namespace App\Services\Marketplace;

use App\Models\ChannelListing;
use App\Models\ChannelProduct;
use App\Models\MarketplaceStore;
use App\Services\MpProductChangeLogger;
use Illuminate\Support\Facades\Schema;

class MarketplaceCatalogSyncService
{
    public function __construct(
        protected MarketplaceProductMatcher $matcher,
    ) {
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $context
     * @return array{created: int, updated: int, skipped: int}
     */
    public function sync(MarketplaceStore $store, array $items, array $context = []): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($items as $row) {
            $productPayload = $row['product'] ?? [];
            $listingPayload = $row['listing'] ?? [];
            $externalProductId = trim((string) ($productPayload['external_product_id'] ?? $listingPayload['listing_id'] ?? $productPayload['barcode'] ?? $productPayload['stock_code'] ?? ''));
            $listingId = trim((string) ($listingPayload['listing_id'] ?? $externalProductId));

            if ($externalProductId === '' || $listingId === '') {
                $skipped++;

                continue;
            }

            $product = $this->resolveProduct(
                $store,
                $externalProductId,
                $productPayload['stock_code'] ?? null,
                $productPayload['barcode'] ?? null,
            );

            $productDirty = !$product->exists;

            $product->fill([
                'store_id' => $store->id,
                'external_product_id' => $externalProductId,
                'external_parent_id' => $this->preferIncoming($productPayload['external_parent_id'] ?? null, $product->external_parent_id),
                'stock_code' => $this->preferIncomingStockCode($store, $productPayload['stock_code'] ?? null, $product->stock_code, $externalProductId),
                'barcode' => $this->preferIncoming($productPayload['barcode'] ?? null, $product->barcode),
                'title' => $this->preferIncoming($productPayload['title'] ?? null, $product->title),
                'brand' => $this->preferIncoming($productPayload['brand'] ?? null, $product->brand),
                'category_name' => $this->preferIncoming($productPayload['category_name'] ?? null, $product->category_name),
                'vat_rate' => $this->preferIncoming($productPayload['vat_rate'] ?? null, $product->vat_rate),
                'raw_payload' => $productPayload['raw_payload'] ?? $row,
                'last_synced_at' => now(),
            ]);

            $productChanged = $productDirty || $product->isDirty();
            $product->save();

            $listing = $this->resolveListing($store, $product, $listingId);

            $listingDirty = !$listing->exists;
            $listing->setRelation('store', $store);
            $logger = app(MpProductChangeLogger::class);
            $beforeListingSnapshot = $listingDirty ? [] : $logger->listingSnapshot($listing);

            $listingData = [
                'store_id' => $store->id,
                'channel_product_id' => $product->id,
                'listing_id' => $listingId,
                'listing_status' => $this->preferIncoming($listingPayload['listing_status'] ?? null, $listing->listing_status ?: 'draft'),
                'sale_price' => $this->preferIncoming($listingPayload['sale_price'] ?? null, $listing->sale_price),
                'list_price' => $this->preferIncoming($listingPayload['list_price'] ?? null, $listing->list_price),
                'currency' => $this->preferIncoming($listingPayload['currency'] ?? null, $listing->currency ?: 'TRY'),
                'stock_quantity' => $this->preferIncoming($listingPayload['stock_quantity'] ?? null, $listing->stock_quantity),
                'published_at' => $this->preferIncoming($listingPayload['published_at'] ?? null, $listing->published_at),
                'last_synced_at' => now(),
            ];

            $listingData = array_merge($listingData, $this->deliveryTermData($listingPayload, $listing));

            $usesProductCommission = ($listingPayload['commission_authority'] ?? null) === 'product'
                || ($listingPayload['commission_source'] ?? null) === 'product_fallback';
            $commissionRate = $this->normalizeCommissionRate($listingPayload['commission_rate'] ?? null);

            if ($usesProductCommission) {
                $listingData['commission_rate'] = null;
                $listingData['commission_source'] = 'product_fallback';
                $listingData['commission_synced_at'] = now();
            } elseif ($commissionRate !== null) {
                $listingData['commission_rate'] = $commissionRate;
                $listingData['commission_source'] = $listingPayload['commission_source'] ?? 'catalog';
                $listingData['commission_synced_at'] = now();
            } elseif ($this->shouldUseZeroCommissionDefault($store)) {
                $listingData['commission_rate'] = 0;
                $listingData['commission_source'] = 'marketplace_default';
                $listingData['commission_synced_at'] = now();
            }

            $listing->fill($listingData);

            $listingChanged = $listingDirty || $listing->isDirty();
            $listing->save();

            $this->matcher->applyToListing(
                $listing,
                $product->stock_code,
                $product->barcode,
            );

            $listing->refresh()->loadMissing(['store', 'product', 'channelProduct']);
            $logger->logListingSnapshotChanges(
                $listing,
                $beforeListingSnapshot,
                'catalog_sync',
                null,
                $listingDirty ? 'Pazaryeri ürün senkronu ile yeni kanal kaydı' : 'Pazaryeri ürün senkronu',
                null,
                [
                    'marketplace' => $store->marketplace,
                    'store_name' => $store->store_name,
                    'sync_type' => $context['sync_type'] ?? 'products',
                ]
            );
            app(\App\Services\NotificationCenterService::class)->syncListingStockAlert($listing);

            if ($productChanged || $listingChanged) {
                if ($productDirty || $listingDirty) {
                    $created++;
                } else {
                    $updated++;
                }
            } else {
                $skipped++;
            }
        }

        return compact('created', 'updated', 'skipped');
    }

    protected function resolveProduct(
        MarketplaceStore $store,
        string $externalProductId,
        ?string $stockCode,
        ?string $barcode,
    ): ChannelProduct {
        $product = ChannelProduct::query()
            ->where('store_id', $store->id)
            ->where('external_product_id', $externalProductId)
            ->first();

        if ($product) {
            return $product;
        }

        foreach ([
            ['barcode', $barcode],
            ['stock_code', $stockCode],
        ] as [$column, $value]) {
            $fallback = $this->uniqueProductFallback($store->id, $column, $value);

            if ($fallback) {
                return $fallback;
            }
        }

        return new ChannelProduct();
    }

    protected function resolveListing(MarketplaceStore $store, ChannelProduct $product, string $listingId): ChannelListing
    {
        $listing = ChannelListing::query()
            ->where('store_id', $store->id)
            ->where('listing_id', $listingId)
            ->first();

        if ($listing) {
            return $listing;
        }

        $fallbackListings = ChannelListing::query()
            ->where('store_id', $store->id)
            ->where('channel_product_id', $product->id)
            ->limit(2)
            ->get();

        if ($fallbackListings->count() === 1) {
            return $fallbackListings->first();
        }

        return new ChannelListing();
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

    protected function preferIncoming(mixed $incoming, mixed $existing): mixed
    {
        if ($incoming === null) {
            return $existing;
        }

        if (is_string($incoming) && trim($incoming) === '') {
            return $existing;
        }

        return $incoming;
    }

    protected function preferIncomingStockCode(MarketplaceStore $store, mixed $incoming, mixed $existing, string $externalProductId): mixed
    {
        if ($incoming !== null && (!is_string($incoming) || trim($incoming) !== '')) {
            return $incoming;
        }

        $existing = trim((string) $existing);

        if (
            strtolower((string) $store->marketplace) === 'woocommerce'
            && $existing !== ''
            && $existing === trim($externalProductId)
        ) {
            return null;
        }

        return $existing !== '' ? $existing : null;
    }

    protected function normalizeCommissionRate(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $rate = (float) str_replace(',', '.', (string) $value);

        if ($rate < 0 || $rate > 100) {
            return null;
        }

        return round($rate, 2);
    }

    protected function shouldUseZeroCommissionDefault(MarketplaceStore $store): bool
    {
        return strtolower((string) $store->marketplace) === 'woocommerce';
    }

    /**
     * @param  array<string, mixed>  $listingPayload
     * @return array<string, mixed>
     */
    protected function deliveryTermData(array $listingPayload, ChannelListing $listing): array
    {
        static $columns = null;

        $columns ??= [
            'shipping_days' => Schema::hasColumn('channel_listings', 'shipping_days'),
            'shipping_type' => Schema::hasColumn('channel_listings', 'shipping_type'),
            'fast_delivery_type' => Schema::hasColumn('channel_listings', 'fast_delivery_type'),
        ];

        $data = [];

        foreach (array_keys($columns) as $column) {
            if (!$columns[$column]) {
                continue;
            }

            $data[$column] = $this->preferIncoming($listingPayload[$column] ?? null, $listing->{$column});
        }

        return $data;
    }
}
