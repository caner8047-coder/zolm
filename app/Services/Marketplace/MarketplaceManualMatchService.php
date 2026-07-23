<?php

namespace App\Services\Marketplace;

use App\Models\ChannelOrderItem;
use App\Models\MpProduct;
use App\Models\ProductMatchIssue;
use App\Services\MpProductChangeLogger;
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

        // Listing bulunamadıysa (channel_listing_id = NULL olan issue'lar):
        // Ürünün kendi stock_code/barcode'u ile mağazadaki eşleşmemiş
        // sipariş satırlarını bulup doğrudan eşleştiriyoruz.
        if (!$listing) {
            return $this->manualMatchWithoutListing($issue, $product, $resolvedBy);
        }

        $channelProduct = $listing->channelProduct;
        $stockCode = trim((string) ($channelProduct?->stock_code ?: $listing->product?->stock_code));
        $barcode = trim((string) ($channelProduct?->barcode ?: $listing->product?->barcode));

        $updatedItems = 0;
        $impactedOrderIds = [];
        $listing->setRelation('store', $issue->store);
        $logger = app(MpProductChangeLogger::class);
        $beforeListingSnapshot = $logger->listingSnapshot($listing);

        DB::transaction(function () use (
            $issue,
            $product,
            $resolvedBy,
            $listing,
            $logger,
            $beforeListingSnapshot,
            $stockCode,
            $barcode,
            &$updatedItems,
            &$impactedOrderIds
        ) {
            $listing->forceFill([
                'mp_product_id' => $product->id,
            ])->save();
            $logger->logListingSnapshotChanges(
                $listing->fresh() ?: $listing,
                $beforeListingSnapshot,
                'manual_match',
                $resolvedBy,
                'Manuel hızlı eşleştirme'
            );

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

    /**
     * Kanal listelemesinden ana ürün oluşturur ve aynı işlemde eşleştirir.
     * Aynı kullanıcıda aynı barkod veya stok kodu zaten varsa yeni kayıt açmaz.
     *
     * @return array{product: MpProduct, created: bool, updated_items: int, impacted_orders: int}
     */
    public function createMasterProductFromListing(ProductMatchIssue $issue, ?int $resolvedBy = null): array
    {
        $issue->loadMissing([
            'store.syncProfile',
            'channelListing.channelProduct',
            'channelListing.product',
        ]);

        $listing = $issue->channelListing;
        $channelProduct = $listing?->channelProduct;

        if (! $listing || ! $channelProduct) {
            throw new \RuntimeException('Bu kayıt ana ürün oluşturmak için gerekli kanal bilgilerini içermiyor.');
        }

        if ($listing->mp_product_id) {
            throw new \RuntimeException('Bu kanal ürünü zaten bir ana ürüne bağlı.');
        }

        $stockCode = trim((string) $channelProduct->stock_code);
        $barcode = trim((string) $channelProduct->barcode);
        $existingProduct = null;

        if ($barcode !== '' || $stockCode !== '') {
            $existingProduct = MpProduct::query()
                ->where('user_id', $issue->store->user_id)
                ->where(function ($query) use ($stockCode, $barcode) {
                    if ($barcode !== '') {
                        $query->orWhere('barcode', $barcode);
                    }

                    if ($stockCode !== '') {
                        $query->orWhere('stock_code', $stockCode);
                    }
                })
                ->first();
        }

        if ($existingProduct) {
            return [
                'product' => $existingProduct,
                'created' => false,
                ...$this->manualMatch($issue, $existingProduct, $resolvedBy),
            ];
        }

        $creation = DB::transaction(function () use ($issue, $channelProduct, $listing, $resolvedBy, $stockCode, $barcode) {
            $imageUrls = $this->normalizedImageUrls($channelProduct->images);

            $product = MpProduct::query()->create([
                'user_id' => $issue->store->user_id,
                'barcode' => $barcode !== '' ? $barcode : null,
                'stock_code' => $stockCode !== '' ? $stockCode : null,
                'product_name' => $channelProduct->title ?: 'Pazaryeri ürünü',
                'brand' => $channelProduct->brand,
                'category_name' => $channelProduct->category_name,
                'description' => $channelProduct->description,
                'image_url' => $imageUrls[0] ?? null,
                'image_urls' => $imageUrls !== [] ? $imageUrls : null,
                'vat_rate' => $channelProduct->vat_rate ?? 10,
                'sale_price' => $listing->sale_price ?? 0,
                'market_price' => $listing->list_price ?? $listing->sale_price ?? 0,
                'stock_quantity' => $listing->stock_quantity ?? 0,
                'status' => 'active',
                'import_source' => 'marketplace_manual_match',
                'last_synced_at' => now(),
            ]);

            return [
                'product' => $product,
                'match' => $this->manualMatch($issue, $product, $resolvedBy),
            ];
        });

        return [
            'product' => $creation['product'],
            'created' => true,
            ...$creation['match'],
        ];
    }

    /** @return array<int, string> */
    protected function normalizedImageUrls(mixed $images): array
    {
        return collect(is_array($images) ? $images : [])
            ->map(function (mixed $image): ?string {
                if (is_string($image)) {
                    return trim($image);
                }

                if (!is_array($image)) {
                    return null;
                }

                return trim((string) ($image['url'] ?? $image['imageUrl'] ?? $image['secureUrl'] ?? $image['src'] ?? ''));
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Listing kaydı olmayan issue'lar için fallback eşleştirme.
     * Seçilen ürünün stock_code ve barcode'u ile mağazadaki tüm
     * eşleşmemiş sipariş satırlarını bulup doğrudan eşleştirir.
     *
     * @return array{updated_items: int, impacted_orders: int}
     */
    protected function manualMatchWithoutListing(ProductMatchIssue $issue, MpProduct $product, ?int $resolvedBy = null): array
    {
        $stockCode = trim((string) $product->stock_code);
        $barcode   = trim((string) $product->barcode);

        if ($stockCode === '' && $barcode === '') {
            throw new \RuntimeException('Seçilen ürünün stok kodu veya barkodu yok; eşleştirme yapılamaz.');
        }

        $updatedItems     = 0;
        $impactedOrderIds = [];

        DB::transaction(function () use (
            $issue,
            $product,
            $resolvedBy,
            $stockCode,
            $barcode,
            &$updatedItems,
            &$impactedOrderIds
        ) {
            // Aynı mağazada stock_code veya barcode eşleşen, henüz eşleşmemiş satırları bul.
            $items = ChannelOrderItem::query()
                ->where('store_id', $issue->store_id)
                ->where('is_matched', false)
                ->where(function ($query) use ($stockCode, $barcode) {
                    if ($stockCode !== '') {
                        $query->orWhere('stock_code', $stockCode);
                    }
                    if ($barcode !== '') {
                        $query->orWhere('barcode', $barcode);
                    }
                })
                ->get();

            foreach ($items as $item) {
                $item->forceFill([
                    'mp_product_id' => $product->id,
                    'is_matched'    => true,
                    'match_source'  => 'manual',
                ])->save();

                $updatedItems++;
                $impactedOrderIds[] = $item->channel_order_id;
            }

            // Bu issue'yu ve aynı mağazada listing'siz diğer bekleyen issue'ları kapat.
            ProductMatchIssue::query()
                ->where('store_id', $issue->store_id)
                ->whereNull('channel_listing_id')
                ->where('match_status', 'pending')
                ->update([
                    'match_status' => 'resolved',
                    'resolved_by'  => $resolvedBy,
                    'resolved_at'  => now(),
                ]);
        });

        $impactedOrderIds = array_values(array_unique(array_filter($impactedOrderIds)));
        if ($impactedOrderIds !== []) {
            $this->profitSnapshotService->recalculateForOrders($issue->store, $impactedOrderIds);
        }

        return [
            'updated_items'   => $updatedItems,
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
