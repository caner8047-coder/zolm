<?php

namespace App\Services\Marketplace;

use App\Models\TrendyolBoosterStoreWatch;
use App\Models\TrendyolBoosterStoreWatchItem;
use App\Models\TrendyolBoosterStoreWatchSnapshot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TrendyolBoosterStoreWatchService
{
    public function __construct(
        protected TrendyolStorePageReader $reader,
        protected TrendyolBoosterActivityLogger $activityLogger,
        protected TrendyolBoosterNotificationService $notificationService,
    ) {
    }

    /**
     * @return array{ok: bool, message: string, watch: TrendyolBoosterStoreWatch}
     */
    public function scan(int $userId, string $storeUrl, array $payload = []): array
    {
        $startedAt = microtime(true);
        $storeUrl = $this->normalizeUrl($storeUrl);
        $result = empty($payload['items'] ?? [])
            ? $this->reader->fetch($storeUrl)
            : [
                'ok' => true,
                'message' => 'Rakip mağaza tarayıcıdan gelen verilerle tarandı.',
                'data' => array_merge($payload, [
                    'store_url' => $storeUrl,
                    'store_id' => $payload['store_id'] ?? '',
                    'store_name' => $payload['store_name'] ?? 'Rakip Mağaza',
                    'items' => $payload['items'] ?? [],
                    'total_products' => (int) ($payload['total_products'] ?? count((array) ($payload['items'] ?? []))),
                ]),
            ];
        $data = $result['data'];
        $items = collect((array) ($data['items'] ?? []))->values();
        $resolvedStoreId = $this->storeId(
            (string) ($data['store_id'] ?? ''),
            (string) ($data['store_url'] ?? ''),
            $storeUrl,
        );
        $resolvedStoreName = $this->filledText($data['store_name'] ?? null, 'Rakip Mağaza');
        $canonicalStoreUrl = $this->canonicalStoreUrl(
            $storeUrl,
            (string) ($data['store_url'] ?? ''),
            $resolvedStoreId,
            $resolvedStoreName,
        );
        $storeHash = hash('sha256', $canonicalStoreUrl);
        $matchingWatches = $this->matchingWatches($userId, $resolvedStoreId, $storeHash);
        $watch = $matchingWatches
            ->sortByDesc(fn (TrendyolBoosterStoreWatch $candidate): int => (int) ($candidate->items_count ?? 0))
            ->first()
            ?? new TrendyolBoosterStoreWatch(['user_id' => $userId]);

        if ($watch->exists && $matchingWatches->count() > 1) {
            $this->mergeDuplicateWatches($watch, $matchingWatches);
        }

        $data['store_url'] = $canonicalStoreUrl;
        $data['store_id'] = $resolvedStoreId;
        $data['store_name'] = $resolvedStoreName;
        $previousItems = $watch->exists
            ? $watch->items()->latest('checked_at')->get()->keyBy('trendyol_product_id')
            : collect();
        $previousSnapshot = $watch->exists
            ? $watch->snapshots()->where('status', 'ok')->latest('checked_at')->latest('id')->first()
            : null;
        $normalizedItems = $this->normalizeItems($items, $previousItems);

        if ($normalizedItems->isEmpty()) {
            return $this->persistEmptyScan(
                $userId,
                $watch,
                $data,
                $canonicalStoreUrl,
                $storeHash,
                $resolvedStoreId,
                $resolvedStoreName,
                $previousItems,
                (array) $result,
                $startedAt,
            );
        }

        if ($this->scanLooksSuspiciouslySmall($watch, $normalizedItems, $previousItems)) {
            $guardedResult = (array) $result;
            $guardedResult['ok'] = false;
            $guardedResult['message'] = 'Tarama kapsamı şüpheli düşük geldi; önceki rakip katalog korundu. Trendyol sayfası eksik yüklenmiş veya farklı ürün verisi dönmüş olabilir.';
            $guardedData = $data;
            $guardedData['items'] = [];
            $guardedData['rejected_item_count'] = $normalizedItems->count();
            $guardedData['rejected_reason'] = 'suspiciously_small_scan';

            return $this->persistEmptyScan(
                $userId,
                $watch,
                $guardedData,
                $canonicalStoreUrl,
                $storeHash,
                $resolvedStoreId,
                $resolvedStoreName,
                $previousItems,
                $guardedResult,
                $startedAt,
            );
        }

        $currentProductIds = $normalizedItems->pluck('trendyol_product_id')->filter()->all();
        $removedItems = collect();
        if ($watch->exists && count($currentProductIds) > 0) {
            foreach ($previousItems as $productId => $prevItem) {
                if (!in_array($productId, $currentProductIds)) {
                    $removedItems->push([
                        'trendyol_product_id' => $prevItem->trendyol_product_id,
                        'source_url' => $prevItem->source_url,
                        'image_url' => $prevItem->image_url,
                        'title' => $prevItem->title,
                        'brand' => $prevItem->brand,
                        'sale_price' => $prevItem->sale_price,
                        'original_price' => $prevItem->original_price,
                        'discount_rate' => $prevItem->discount_rate,
                        'previous_sale_price' => $prevItem->sale_price,
                        'price_delta' => 0,
                        'rating' => $prevItem->rating,
                        'previous_rating' => $prevItem->rating,
                        'review_count' => $prevItem->review_count,
                        'previous_review_count' => $prevItem->review_count,
                        'favorite_count' => $prevItem->favorite_count,
                        'review_delta' => 0,
                        'category_name' => $prevItem->category_name,
                        'seller_name' => $prevItem->seller_name,
                        'stock_status' => $prevItem->stock_status,
                        'stock_quantity' => $prevItem->stock_quantity,
                        'rank' => $prevItem->rank,
                        'is_new' => false,
                        'is_removed' => true,
                        'is_first_seller' => $prevItem->is_first_seller,
                        'campaign_badges' => $prevItem->campaign_badges,
                        'raw_payload' => $prevItem->raw_payload,
                    ]);
                }
            }
        }
        $normalizedItems = $normalizedItems->map(function ($item) {
            $item['is_removed'] = false;
            return $item;
        })->merge($removedItems);
        $activeItems = $normalizedItems->reject(fn (array $item): bool => (bool) ($item['is_removed'] ?? false));
        
        $brandDistribution = $activeItems
            ->groupBy(fn (array $item) => $item['brand'] ?: 'Bilinmeyen')
            ->map(fn (Collection $group) => $group->count())
            ->sortDesc()
            ->take(10)
            ->all();
        $categoryDistribution = $this->distributionFromNormalizedItems($activeItems, 'category_name');
        $priceChangeCount = $normalizedItems->filter(fn (array $item): bool => (float) $item['price_delta'] !== 0.0)->count();
        $newCount = $activeItems->where('is_new', true)->count();
        $topSellerCount = $activeItems->where('is_first_seller', true)->count();
        $campaignCount = $activeItems->filter(fn (array $item): bool => ! empty($item['campaign_badges']))->count();
        $prices = $activeItems->pluck('sale_price')->filter(fn ($p) => (float) $p > 0);
        $avgPrice = $prices->isNotEmpty() ? round($prices->avg(), 2) : null;
        $ratings = $activeItems->pluck('rating')->filter(fn ($r) => $r !== null && (float) $r > 0);
        $avgRating = $ratings->isNotEmpty() ? round($ratings->avg(), 1) : null;
        $totalReviews = (int) $activeItems->sum('review_count');
        $totalFavorites = (int) $activeItems->sum('favorite_count');

        $watch->forceFill([
            'store_url' => $canonicalStoreUrl,
            'store_url_hash' => $storeHash,
            'store_id' => $resolvedStoreId ?: null,
            'store_name' => $resolvedStoreName,
            'total_products' => (int) ($data['total_products'] ?? $activeItems->count()),
            'best_seller_count' => $activeItems->count(),
            'new_product_count' => $newCount,
            'price_change_count' => $priceChangeCount,
            'scan_count' => ((int) $watch->scan_count) + 1,
            'last_scan_duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'brand_distribution' => $brandDistribution,
            'store_rating' => $data['store_rating'] ?? null,
            'top_seller_count' => $topSellerCount,
            'campaign_count' => $campaignCount,
            'avg_price' => $avgPrice,
            'avg_rating' => $avgRating,
            'total_reviews' => $totalReviews,
            'category_distribution' => $categoryDistribution,
            'raw_payload' => $data,
            'is_active' => true,
            'last_checked_at' => now(),
        ])->save();

        $snapshot = $this->createSnapshot(
            $watch,
            $normalizedItems,
            $brandDistribution,
            $categoryDistribution,
            $previousSnapshot,
            (array) $result,
            (int) round((microtime(true) - $startedAt) * 1000),
            $totalFavorites,
        );

        foreach ($normalizedItems as $index => $itemData) {
            $itemModel = TrendyolBoosterStoreWatchItem::query()->updateOrCreate(
                [
                    'trendyol_booster_store_watch_id' => $watch->id,
                    'trendyol_product_id' => $itemData['trendyol_product_id'],
                ],
                $itemData + [
                    'user_id' => $userId,
                    'rank' => $index + 1,
                    'checked_at' => now(),
                ]
            );

            $itemModel->histories()->create([
                'trendyol_booster_store_watch_snapshot_id' => $snapshot->id,
                'sale_price' => $itemModel->sale_price,
                'rank' => $itemModel->rank,
                'review_count' => $itemModel->review_count,
                'favorite_count' => $itemModel->favorite_count,
                'stock_quantity' => $itemModel->stock_quantity,
                'stock_status' => $itemModel->stock_status,
                'rating' => $itemModel->rating,
                'is_campaign' => !empty($itemModel->campaign_badges),
            ]);
        }

        $this->activityLogger->log(
            $userId,
            'store_watch',
            'Rakip Takibi',
            $watch->store_name,
            $normalizedItems->count() . ' ürün, ' . $newCount . ' yeni ürün, ' . $priceChangeCount . ' fiyat değişimi.',
            'ürün',
            $normalizedItems->count(),
            ['store_watch_id' => $watch->id, 'ok' => $result['ok']],
        );

        if ($this->isFallbackMessage((string) ($result['message'] ?? '')) || ($result['ok'] && $normalizedItems->isEmpty())) {
            $this->activityLogger->log(
                $userId,
                'sync_fallback',
                'Rakip mağaza taraması fallback kullandı',
                $watch->store_name,
                (string) ($result['message'] ?? 'Ürün kartı yakalanamadı.'),
                'ürün',
                $normalizedItems->count(),
                ['store_watch_id' => $watch->id, 'source' => 'store_watch'],
            );
        }
        $this->notificationService->notifyStoreWatch($watch->fresh() ?: $watch);

        return [
            'ok' => $result['ok'],
            'message' => $watch->scan_count > 1
                ? $watch->store_name . ' mağazası güncellendi.'
                : $watch->store_name . ' için ilk takip kaydı alındı.',
            'watch' => $watch->fresh(['items']) ?: $watch,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(int $userId): array
    {
        $base = TrendyolBoosterStoreWatch::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->where(function (Builder $query): void {
                $query
                    ->where('total_products', '>', 0)
                    ->orWhereHas('items');
            });

        return [
            'total' => (clone $base)->count(),
            'new_products' => (int) ((clone $base)->sum('new_product_count') ?? 0),
            'price_changes' => (int) ((clone $base)->sum('price_change_count') ?? 0),
            'total_reviews' => (int) ((clone $base)->sum('total_reviews') ?? 0),
            'campaign_count' => (int) ((clone $base)->sum('campaign_count') ?? 0),
            'latest' => (clone $base)->with([
                'latestSnapshot',
                'items' => fn ($query) => $query->orderBy('rank')->limit(72),
            ])
                ->latest('last_checked_at')
                ->limit(10)
                ->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function storeDetail(int $userId, int $storeWatchId, string $sort = 'rank', string $direction = 'asc', string $filter = 'all'): array
    {
        $watch = TrendyolBoosterStoreWatch::query()
            ->where('user_id', $userId)
            ->findOrFail($storeWatchId);

        $itemsQuery = $watch->items()->with('histories')->orderBy($sort, $direction);

        if ($filter === 'new') {
            $itemsQuery->where('is_new', true);
        } elseif ($filter === 'price_changed') {
            $itemsQuery->where('price_delta', '!=', 0);
        } elseif ($filter === 'campaign') {
            $itemsQuery->whereNotNull('campaign_badges')->where('campaign_badges', '!=', '[]');
        } elseif ($filter === 'top_seller') {
            $itemsQuery->where('is_first_seller', true);
        } elseif ($filter === 'removed') {
            $itemsQuery->where('is_removed', true);
        }

        $items = $itemsQuery->get();
        $items->each(function (TrendyolBoosterStoreWatchItem $item): void {
            $item->setAttribute('brand', $this->normalizeItemBrand((string) $item->brand, (string) $item->title));

            if (trim((string) $item->category_name) === '') {
                $item->setAttribute('category_name', $this->inferCategoryName((string) $item->title));
            }

            $item->setAttribute('store_sales_signal', $this->storeSalesSignal($item));
        });

        $portfolioItems = $watch->items()->with('histories')->get();
        $portfolioItems->each(function (TrendyolBoosterStoreWatchItem $item): void {
            $item->setAttribute('brand', $this->normalizeItemBrand((string) $item->brand, (string) $item->title));

            if (trim((string) $item->category_name) === '') {
                $item->setAttribute('category_name', $this->inferCategoryName((string) $item->title));
            }

            $item->setAttribute('store_sales_signal', $this->storeSalesSignal($item));
        });

        $categoryDistribution = (array) ($watch->category_distribution ?? []);
        if ($categoryDistribution === []) {
            $categoryDistribution = $this->distributionFromStoreItems($items, 'category_name');
        }
        $snapshots = $watch->snapshots()
            ->latest('checked_at')
            ->latest('id')
            ->limit(12)
            ->get();
        $latestOkSnapshot = $snapshots->firstWhere('status', 'ok')
            ?: $watch->snapshots()->where('status', 'ok')->latest('checked_at')->latest('id')->first();

        return [
            'watch' => $watch,
            'items' => $items,
            'snapshots' => $snapshots,
            'latest_snapshot' => $snapshots->first(),
            'latest_ok_snapshot' => $latestOkSnapshot,
            'previous_snapshot' => $snapshots->skip(1)->first(),
            'brand_distribution' => $this->distributionLooksLikeProductTitles((array) ($watch->brand_distribution ?? []))
                ? $this->distributionFromStoreItems($items, 'brand')
                : ($watch->brand_distribution ?: $this->distributionFromStoreItems($items, 'brand')),
            'category_distribution' => $categoryDistribution,
            'portfolio' => app(TrendyolBoosterStorePortfolioService::class)->analyze($portfolioItems),
        ];
    }

    /**
     * @return array{processed: int, succeeded: int, failed: int, skipped: int, dry_run: bool}
     */
    public function refreshDue(int $limit = 25, ?int $userId = null, ?int $staleMinutes = null, bool $dryRun = false): array
    {
        $query = TrendyolBoosterStoreWatch::query()
            ->where('is_active', true)
            ->when($userId !== null, fn (Builder $query) => $query->where('user_id', $userId))
            ->when($staleMinutes !== null && $staleMinutes > 0, function (Builder $query) use ($staleMinutes): void {
                $query->where(function (Builder $staleQuery) use ($staleMinutes): void {
                    $staleQuery
                        ->whereNull('last_checked_at')
                        ->orWhere('last_checked_at', '<=', now()->subMinutes($staleMinutes));
                });
            })
            ->orderBy('last_checked_at')
            ->orderBy('id')
            ->limit(max(1, min(100, $limit)));

        $summary = [
            'processed' => 0,
            'succeeded' => 0,
            'failed' => 0,
            'skipped' => 0,
            'dry_run' => $dryRun,
        ];

        foreach ($query->get() as $watch) {
            $summary['processed']++;

            if ($dryRun) {
                $summary['skipped']++;

                continue;
            }

            try {
                $result = $this->scan((int) $watch->user_id, (string) $watch->store_url);
            } catch (\Throwable $exception) {
                $summary['failed']++;
                $this->activityLogger->log(
                    (int) $watch->user_id,
                    'sync_error',
                    'Rakip mağaza taraması hata verdi',
                    $watch->store_name,
                    Str::limit($exception->getMessage(), 600, ''),
                    'durum',
                    null,
                    [
                        'source' => 'store_watch',
                        'exception' => get_class($exception),
                        'store_watch_id' => $watch->id,
                    ],
                );

                continue;
            }

            $summary[$result['ok'] ? 'succeeded' : 'failed']++;
        }

        return $summary;
    }

    /**
     * @param  Collection<int, mixed>  $items
     * @param  Collection<string, TrendyolBoosterStoreWatchItem>  $previousItems
     * @return Collection<int, array<string, mixed>>
     */
    protected function normalizeItems(Collection $items, Collection $previousItems): Collection
    {
        return $items
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(function (array $item) use ($previousItems): array {
                $productId = trim((string) ($item['trendyol_product_id'] ?? ''));
                $previous = $productId !== '' ? $previousItems->get($productId) : null;
                $salePrice = $this->money($item['sale_price'] ?? 0);
                $previousPrice = $previous?->sale_price;
                $rating = isset($item['rating']) && is_numeric($item['rating']) ? round(max(0, min(5, (float) $item['rating'])), 1) : null;
                $previousRating = $previous?->rating;
                $reviewCount = isset($item['review_count']) && is_numeric($item['review_count']) ? max(0, (int) $item['review_count']) : null;
                $previousReviewCount = $previous?->review_count;
                $reviewDelta = ($reviewCount !== null && $previousReviewCount !== null) ? $reviewCount - $previousReviewCount : 0;
                $title = $this->filledText($item['title'] ?? null, 'Rakip ürün');
                $brand = $this->normalizeItemBrand($this->filledText($item['brand'] ?? null, ''), $title);
                $categoryName = $this->filledText($item['category_name'] ?? null, '');
                $categoryName = $categoryName !== '' ? $categoryName : $this->inferCategoryName($title);
                $stockQuantity = $this->nullableIntegerValue($item['stock_quantity'] ?? $item['total_stock'] ?? null);

                return [
                    'trendyol_product_id' => $productId ?: null,
                    'source_url' => $this->filledText($item['source_url'] ?? null, ''),
                    'image_url' => $this->filledText($item['image_url'] ?? null, ''),
                    'title' => $title,
                    'brand' => $brand,
                    'sale_price' => $salePrice,
                    'original_price' => isset($item['original_price']) && is_numeric($item['original_price']) ? round(max(0, (float) $item['original_price']), 2) : null,
                    'discount_rate' => isset($item['discount_rate']) && is_numeric($item['discount_rate']) ? round(max(0, (float) $item['discount_rate']), 2) : null,
                    'previous_sale_price' => $previousPrice,
                    'price_delta' => $previousPrice !== null ? round($salePrice - (float) $previousPrice, 2) : 0,
                    'rating' => $rating,
                    'previous_rating' => $previousRating !== null ? (float) $previousRating : null,
                    'review_count' => $reviewCount,
                    'previous_review_count' => $previousReviewCount !== null ? (int) $previousReviewCount : null,
                    'review_delta' => $reviewDelta,
                    'favorite_count' => isset($item['favorite_count']) && is_numeric($item['favorite_count']) ? max(0, (int) $item['favorite_count']) : null,
                    'campaign_badges' => $this->sanitizeCampaignBadges($item['campaign_badges'] ?? []),
                    'is_first_seller' => (bool) ($item['is_first_seller'] ?? false),
                    'category_name' => $categoryName,
                    'seller_name' => $this->filledText($item['seller_name'] ?? null, ''),
                    'stock_status' => $this->filledText($item['stock_status'] ?? null, ''),
                    'stock_quantity' => $stockQuantity,
                    'is_new' => $previous === null,
                    'raw_payload' => $item,
                ];
            })
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $normalizedItems
     * @param  Collection<string, TrendyolBoosterStoreWatchItem>  $previousItems
     */
    protected function scanLooksSuspiciouslySmall(
        TrendyolBoosterStoreWatch $watch,
        Collection $normalizedItems,
        Collection $previousItems,
    ): bool {
        if (! $watch->exists) {
            return false;
        }

        $previousCount = $previousItems
            ->reject(fn (TrendyolBoosterStoreWatchItem $item): bool => (bool) $item->is_removed)
            ->count();
        $incomingCount = $normalizedItems
            ->reject(fn (array $item): bool => (bool) ($item['is_removed'] ?? false))
            ->count();

        if ($previousCount < 20 || $incomingCount === 0) {
            return false;
        }

        $minimumReasonableCount = max(5, (int) floor($previousCount * 0.25));
        if ($incomingCount >= $minimumReasonableCount) {
            return false;
        }

        $incomingIds = $normalizedItems
            ->pluck('trendyol_product_id')
            ->filter()
            ->map(fn ($id): string => (string) $id)
            ->all();
        $previousIds = $previousItems
            ->reject(fn (TrendyolBoosterStoreWatchItem $item): bool => (bool) $item->is_removed)
            ->keys()
            ->map(fn ($id): string => (string) $id)
            ->all();
        $overlapCount = count(array_intersect($incomingIds, $previousIds));

        return $overlapCount === 0 || $incomingCount <= 3;
    }

    /**
     * @param  mixed  $badges
     * @return array<int, string>
     */
    protected function sanitizeCampaignBadges(mixed $badges): array
    {
        return collect(is_array($badges) ? $badges : [])
            ->map(fn ($badge): string => $this->normalizeCampaignBadge((string) $badge))
            ->filter()
            ->unique(fn (string $badge): string => Str::lower($badge))
            ->take(8)
            ->values()
            ->all();
    }

    protected function normalizeCampaignBadge(string $value): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $value));
        $text = str_replace('KUPONLUÜRÜN', 'Kuponlu Ürün', $text);
        $text = (string) preg_replace('/Sepette(?=\d)/iu', 'Sepette ', $text);

        if ($text === '' || mb_strlen($text) < 4) {
            return '';
        }

        if (preg_match("/Trendyol'da Satış Yap|Hakkımızda|Yardım\s*&\s*Destek|Ürün,\s*kategori|Giriş Yap|Sepete Ekle|Alışveriş Kredisi|Temel Kavr/iu", $text)) {
            return '';
        }

        if (preg_match('/^Sepette\s*[\d.,]+\s*TL(?:\s*[\d.,]+\s*TL)?$/iu', $text)) {
            return '';
        }

        $patterns = [
            "/\d+[\d.]*\s*TL'?ye\s+%\d+\s*İndirim/iu",
            "/\d+[\d.]*\s*TL'?ye\s+(?:%\d+\s*)?\d*[\d.]*\s*TL?\s*İndirim/iu",
            '/\d+[\d.]*\s*TL\s+ve\s+Üzeri\s+Kargo\s+Bedava/iu',
            '/\d+[\d.]*\s*TL\s+Kupon/iu',
            '/En\s+Çok\s+(?:Satan|Satılan|Ziyaret\s+Edilen)\s+#?\d+\.?\s*Ürün/iu',
            "/Trendyol\s+Plus'?a\s+Özel\s+Fiyat/iu",
            '/Son\s+\d+\s+Günün\s+En\s+Düşük\s+Fiyatı/iu',
            '/Flaş\s+Ürün/iu',
            '/Avantajlı\s+Ürün/iu',
            '/Kargo\s+Bedava/iu',
            '/Kupon\s+Fırsatı/iu',
            '/Kuponlu\s+Ürün/iu',
            '/Birlikte\s+Al\s+Kazan/iu',
            '/Çok\s+Al\s+Az\s+Öde/iu',
            '/Fenomen\s+Seçimi/iu',
            '/Yetkili\s+Satıcı/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return trim((string) preg_replace('/\s+/u', ' ', $matches[0]));
            }
        }

        return '';
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @param  array<string, int>  $brandDistribution
     * @param  array<string, int>  $categoryDistribution
     * @param  array<string, mixed>  $result
     */
    protected function createSnapshot(
        TrendyolBoosterStoreWatch $watch,
        Collection $items,
        array $brandDistribution,
        array $categoryDistribution,
        ?TrendyolBoosterStoreWatchSnapshot $previousSnapshot,
        array $result,
        int $durationMs,
        int $totalFavorites,
        string $status = 'ok',
    ): TrendyolBoosterStoreWatchSnapshot {
        $activeItems = $items->reject(fn (array $item): bool => (bool) ($item['is_removed'] ?? false));
        $prices = $activeItems->pluck('sale_price')->filter(fn ($value): bool => is_numeric($value) && (float) $value > 0);
        $ratings = $activeItems->pluck('rating')->filter(fn ($value): bool => is_numeric($value) && (float) $value > 0);
        $priceChanges = $items->filter(fn (array $item): bool => (float) ($item['price_delta'] ?? 0) !== 0.0);

        return $watch->snapshots()->create([
            'user_id' => (int) $watch->user_id,
            'scan_number' => max(1, (int) $watch->scan_count),
            'status' => $status,
            'message' => Str::limit((string) ($result['message'] ?? ''), 500, ''),
            'store_id' => $watch->store_id,
            'store_name' => $watch->store_name,
            'store_url' => $watch->store_url,
            'total_products' => (int) $watch->total_products,
            'active_product_count' => $activeItems->count(),
            'new_product_count' => (int) $watch->new_product_count,
            'removed_product_count' => $items->where('is_removed', true)->count(),
            'price_change_count' => (int) $watch->price_change_count,
            'campaign_count' => (int) $watch->campaign_count,
            'top_seller_count' => (int) $watch->top_seller_count,
            'total_reviews' => (int) $watch->total_reviews,
            'total_favorites' => $totalFavorites,
            'avg_price' => $prices->isNotEmpty() ? round((float) $prices->avg(), 2) : null,
            'min_price' => $prices->isNotEmpty() ? round((float) $prices->min(), 2) : null,
            'max_price' => $prices->isNotEmpty() ? round((float) $prices->max(), 2) : null,
            'avg_rating' => $ratings->isNotEmpty() ? round((float) $ratings->avg(), 1) : null,
            'store_rating' => $watch->store_rating,
            'brand_distribution' => $brandDistribution,
            'category_distribution' => $categoryDistribution,
            'price_summary' => [
                'increase_count' => $priceChanges->filter(fn (array $item): bool => (float) ($item['price_delta'] ?? 0) > 0)->count(),
                'decrease_count' => $priceChanges->filter(fn (array $item): bool => (float) ($item['price_delta'] ?? 0) < 0)->count(),
                'avg_changed_delta' => $priceChanges->isNotEmpty()
                    ? round((float) $priceChanges->avg(fn (array $item): float => (float) ($item['price_delta'] ?? 0)), 2)
                    : 0,
            ],
            'change_summary' => $this->snapshotChangeSummary($watch, $previousSnapshot, $totalFavorites),
            'raw_payload' => [
                'result_ok' => (bool) ($result['ok'] ?? true),
                'source' => $result['data']['source'] ?? data_get($result, 'source'),
            ],
            'scan_duration_ms' => $durationMs,
            'checked_at' => $watch->last_checked_at ?: now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function snapshotChangeSummary(
        TrendyolBoosterStoreWatch $watch,
        ?TrendyolBoosterStoreWatchSnapshot $previousSnapshot,
        int $totalFavorites,
    ): array {
        if ($previousSnapshot === null) {
            return [
                'baseline' => true,
                'message' => 'İlk tarama. Sonraki taramalarda değişim kıyası oluşacak.',
            ];
        }

        return [
            'baseline' => false,
            'compared_snapshot_id' => $previousSnapshot->id,
            'compared_checked_at' => $previousSnapshot->checked_at?->toIso8601String(),
            'active_product_delta' => (int) $watch->best_seller_count - (int) $previousSnapshot->active_product_count,
            'new_product_delta' => (int) $watch->new_product_count - (int) $previousSnapshot->new_product_count,
            'price_change_delta' => (int) $watch->price_change_count - (int) $previousSnapshot->price_change_count,
            'campaign_delta' => (int) $watch->campaign_count - (int) $previousSnapshot->campaign_count,
            'total_review_delta' => (int) $watch->total_reviews - (int) $previousSnapshot->total_reviews,
            'total_favorite_delta' => $totalFavorites - (int) $previousSnapshot->total_favorites,
            'avg_price_delta' => $this->nullableDelta($watch->avg_price, $previousSnapshot->avg_price),
            'avg_rating_delta' => $this->nullableDelta($watch->avg_rating, $previousSnapshot->avg_rating),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<string, int>
     */
    protected function distributionFromNormalizedItems(Collection $items, string $field): array
    {
        return $items
            ->map(fn (array $item): string => $this->filledText($item[$field] ?? '', ''))
            ->filter()
            ->countBy()
            ->sortDesc()
            ->take(10)
            ->all();
    }

    /**
     * @param  Collection<int, TrendyolBoosterStoreWatchItem>  $items
     * @return array<string, int>
     */
    protected function distributionFromStoreItems(Collection $items, string $field): array
    {
        return $items
            ->map(fn (TrendyolBoosterStoreWatchItem $item): string => $this->filledText($item->{$field} ?? '', ''))
            ->filter()
            ->countBy()
            ->sortDesc()
            ->take(10)
            ->all();
    }

    /** @param array<string, int> $distribution */
    protected function distributionLooksLikeProductTitles(array $distribution): bool
    {
        foreach (array_keys($distribution) as $label) {
            $label = (string) $label;

            if (mb_strlen($label) > 45 || str_contains($label, ',')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function storeSalesSignal(TrendyolBoosterStoreWatchItem $item): array
    {
        $histories = $item->histories
            ? $item->histories->sortBy('created_at')->values()
            : collect();
        $stockSamples = $histories
            ->filter(fn ($history): bool => $history->created_at !== null && is_numeric($history->stock_quantity))
            ->values();

        $stockDrop = null;
        $estimatedDailySales = null;
        $observedHours = null;
        if ($stockSamples->count() >= 2) {
            $drop = 0;
            foreach ($stockSamples->sliding(2) as $pair) {
                $before = (int) $pair->first()->stock_quantity;
                $after = (int) $pair->last()->stock_quantity;
                if ($after < $before) {
                    $drop += $before - $after;
                }
            }

            $first = $stockSamples->first();
            $last = $stockSamples->last();
            $observedHours = max(0.25, $first->created_at->floatDiffInHours($last->created_at));
            $stockDrop = $drop;
            $estimatedDailySales = $drop > 0 ? round(($drop / $observedHours) * 24, 2) : 0.0;
        }

        $favoriteSamples = $histories
            ->filter(fn ($history): bool => $history->created_at !== null && is_numeric($history->favorite_count))
            ->values();
        $favoriteDelta = $favoriteSamples->count() >= 2
            ? (int) $favoriteSamples->last()->favorite_count - (int) $favoriteSamples->first()->favorite_count
            : null;

        return [
            'estimated_daily_sales' => $estimatedDailySales,
            'stock_drop' => $stockDrop,
            'favorite_delta' => $favoriteDelta,
            'observed_hours' => $observedHours !== null ? round($observedHours, 2) : null,
            'sample_count' => max($stockSamples->count(), $favoriteSamples->count()),
            'method' => $estimatedDailySales !== null ? 'store_stock_drop' : ($favoriteDelta !== null ? 'favorite_growth' : 'waiting_for_history'),
        ];
    }

    protected function normalizeItemBrand(string $brand, string $title): string
    {
        $brand = $this->filledText($brand, '');
        $title = $this->filledText($title, '');
        $brandLower = Str::lower($brand);
        $titleLower = Str::lower($title);

        if ($brand === '' || mb_strlen($brand) > 45 || $brandLower === $titleLower || Str::startsWith($brandLower, Str::limit($titleLower, 35, ''))) {
            return $this->brandFromTitle($title);
        }

        return $brand;
    }

    protected function brandFromTitle(string $title): string
    {
        $title = trim($title);
        if ($title === '') {
            return '';
        }

        $firstToken = trim((string) Str::of($title)->before(' '));
        if (mb_strlen($firstToken) < 2 || mb_strlen($firstToken) > 32) {
            return '';
        }

        return Str::title($firstToken);
    }

    protected function inferCategoryName(string $title): string
    {
        $title = trim($title);
        if ($title === '') {
            return '';
        }

        $ruleMatch = $this->categoryFromTitleRules($title);
        if ($ruleMatch !== '') {
            return $ruleMatch;
        }

        try {
            $resolved = app(TrendyolCategoryDictionary::class)->resolve($title);
            $subCategory = trim((string) ($resolved['sub_category'] ?? ''));
            $productGroup = trim((string) ($resolved['product_group'] ?? ''));
            $category = trim((string) ($resolved['category'] ?? ''));

            if ($productGroup !== '' && mb_strlen($productGroup) <= 80) {
                return Str::title($productGroup);
            }

            if ($subCategory !== '' && mb_strlen($subCategory) <= 80) {
                return Str::title($subCategory);
            }

            if ($category !== '' && mb_strlen($category) <= 80) {
                return Str::title($category);
            }
        } catch (\Throwable) {
            // Yerel sözlük okunamazsa anahtar kelime eşleşmesine düş.
        }

        return '';
    }

    protected function categoryFromTitleRules(string $title): string
    {
        $normalized = Str::lower(Str::ascii($title));
        $rules = [
            'Puf & Bench' => ['puf', 'bench'],
            'Kanepe & Koltuk' => ['kanepe', 'koltuk', 'kose takimi', 'köşe takımı', 'berjer'],
            'Sandalye' => ['sandalye', 'sandalyesi'],
            'Sehpa' => ['sehpa', 'sehpasi', 'sehpaları'],
            'Çay Seti' => ['cay seti', 'çay seti'],
            'Masa' => ['masa', 'masasi'],
            'Kurabiye Kalıbı' => ['kurabiye kalibi', 'kurabiye kalıbı'],
            'Kozmetik' => ['kozmetik', 'makyaj', 'ruj', 'parfum', 'parfüm', 'krem'],
        ];

        foreach ($rules as $category => $terms) {
            foreach ($terms as $term) {
                if (str_contains($normalized, Str::lower(Str::ascii($term)))) {
                    return $category;
                }
            }
        }

        return '';
    }

    /**
     * @param  Collection<string, TrendyolBoosterStoreWatchItem>  $previousItems
     * @param  array<string, mixed>  $result
     * @return array{ok: bool, message: string, watch: TrendyolBoosterStoreWatch}
     */
    protected function persistEmptyScan(
        int $userId,
        TrendyolBoosterStoreWatch $watch,
        array $data,
        string $canonicalStoreUrl,
        string $storeHash,
        string $resolvedStoreId,
        string $resolvedStoreName,
        Collection $previousItems,
        array $result,
        float $startedAt,
    ): array {
        $hasPreviousItems = $watch->exists && $previousItems->isNotEmpty();
        $message = (string) ($result['message'] ?? 'Trendyol mağaza ürünleri okunamadı.');
        $scanCount = ((int) $watch->scan_count) + 1;
        $previousSnapshot = $watch->exists
            ? $watch->snapshots()->where('status', 'ok')->latest('checked_at')->latest('id')->first()
            : null;

        $watch->forceFill([
            'store_url' => $canonicalStoreUrl,
            'store_url_hash' => $storeHash,
            'store_id' => $resolvedStoreId ?: null,
            'store_name' => $resolvedStoreName,
            'total_products' => $hasPreviousItems ? max((int) $watch->total_products, $previousItems->count()) : 0,
            'best_seller_count' => $hasPreviousItems ? max((int) $watch->best_seller_count, $previousItems->count()) : 0,
            'new_product_count' => $hasPreviousItems ? (int) $watch->new_product_count : 0,
            'price_change_count' => $hasPreviousItems ? (int) $watch->price_change_count : 0,
            'scan_count' => $scanCount,
            'last_scan_duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'brand_distribution' => $hasPreviousItems ? ($watch->brand_distribution ?? []) : [],
            'store_rating' => $data['store_rating'] ?? $watch->store_rating,
            'top_seller_count' => $hasPreviousItems ? (int) $watch->top_seller_count : 0,
            'campaign_count' => $hasPreviousItems ? (int) $watch->campaign_count : 0,
            'avg_price' => $hasPreviousItems ? $watch->avg_price : null,
            'avg_rating' => $hasPreviousItems ? $watch->avg_rating : null,
            'total_reviews' => $hasPreviousItems ? (int) $watch->total_reviews : 0,
            'category_distribution' => $hasPreviousItems ? ($watch->category_distribution ?? []) : [],
            'raw_payload' => $data,
            'is_active' => $hasPreviousItems,
            'last_checked_at' => now(),
        ])->save();

        $watch->snapshots()->create([
            'user_id' => $userId,
            'scan_number' => $scanCount,
            'status' => 'failed',
            'message' => Str::limit($message, 500, ''),
            'store_id' => $watch->store_id,
            'store_name' => $watch->store_name,
            'store_url' => $watch->store_url,
            'total_products' => (int) $watch->total_products,
            'active_product_count' => $hasPreviousItems ? $previousItems->count() : 0,
            'new_product_count' => (int) $watch->new_product_count,
            'removed_product_count' => 0,
            'price_change_count' => (int) $watch->price_change_count,
            'campaign_count' => (int) $watch->campaign_count,
            'top_seller_count' => (int) $watch->top_seller_count,
            'total_reviews' => (int) $watch->total_reviews,
            'total_favorites' => (int) $previousItems->sum('favorite_count'),
            'avg_price' => $watch->avg_price,
            'avg_rating' => $watch->avg_rating,
            'store_rating' => $watch->store_rating,
            'brand_distribution' => $watch->brand_distribution ?? [],
            'category_distribution' => $watch->category_distribution ?? [],
            'price_summary' => [],
            'change_summary' => [
                'baseline' => $previousSnapshot === null,
                'compared_snapshot_id' => $previousSnapshot?->id,
                'message' => 'Tarama ürün yakalayamadı; karşılaştırma için son başarılı tarama korunur.',
            ],
            'raw_payload' => [
                'result_ok' => false,
                'source' => 'store_watch',
            ],
            'scan_duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'checked_at' => $watch->last_checked_at ?: now(),
        ]);

        $this->activityLogger->log(
            $userId,
            'sync_fallback',
            'Rakip mağaza taraması ürün yakalayamadı',
            $watch->store_name,
            $message,
            'ürün',
            null,
            ['store_watch_id' => $watch->id, 'source' => 'store_watch'],
        );

        return [
            'ok' => false,
            'message' => $hasPreviousItems
                ? $watch->store_name.' mağazası okunamadı; önceki ürün listesi korundu.'
                : $watch->store_name.' mağazasında ürün yakalanamadı; boş takip kaydı aktif listeye alınmadı.',
            'watch' => $watch->fresh(['items']) ?: $watch,
        ];
    }

    protected function normalizeUrl(string $url): string
    {
        $url = trim($url);
        $url = preg_replace('/\s+/u', '', $url) ?: '';

        return Str::limit($url, 1000, '');
    }

    protected function filledText(mixed $value, string $fallback): string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? Str::limit($text, 1000, '') : $fallback;
    }

    /**
     * @return Collection<int, TrendyolBoosterStoreWatch>
     */
    protected function matchingWatches(int $userId, string $storeId, string $storeHash): Collection
    {
        return TrendyolBoosterStoreWatch::query()
            ->where('user_id', $userId)
            ->where(function (Builder $query) use ($storeId, $storeHash): void {
                $query->where('store_url_hash', $storeHash);

                if ($storeId !== '') {
                    $query
                        ->orWhere('store_id', $storeId)
                        ->orWhere('store_url', 'like', '%-m-'.$storeId.'%')
                        ->orWhere('store_url', 'like', '%mid='.$storeId.'%')
                        ->orWhere('store_url', 'like', '%merchantId='.$storeId.'%');
                }
            })
            ->withCount('items')
            ->get();
    }

    /**
     * @param  Collection<int, TrendyolBoosterStoreWatch>  $matches
     */
    protected function mergeDuplicateWatches(TrendyolBoosterStoreWatch $primary, Collection $matches): void
    {
        foreach ($matches->where('id', '!=', $primary->id) as $duplicate) {
            foreach ($duplicate->items()->get() as $duplicateItem) {
                $existingItem = $primary->items()
                    ->where('trendyol_product_id', $duplicateItem->trendyol_product_id)
                    ->first();

                if ($existingItem) {
                    $duplicateItem->histories()->update([
                        'trendyol_booster_store_watch_item_id' => $existingItem->id,
                    ]);
                    $duplicateItem->delete();

                    continue;
                }

                $duplicateItem->forceFill([
                    'trendyol_booster_store_watch_id' => $primary->id,
                ])->save();
            }

            $duplicate->delete();
        }
    }

    protected function storeId(string ...$values): string
    {
        foreach ($values as $value) {
            if (preg_match('/-m-(\d+)/iu', $value, $match)) {
                return (string) $match[1];
            }

            $query = [];
            parse_str((string) parse_url($value, PHP_URL_QUERY), $query);
            foreach (['mid', 'merchantId'] as $key) {
                if (isset($query[$key]) && is_scalar($query[$key])) {
                    return preg_replace('/\D+/u', '', (string) $query[$key]);
                }
            }

            if (preg_match('/^\d+$/', trim($value))) {
                return trim($value);
            }
        }

        return '';
    }

    protected function canonicalStoreUrl(string $requestedUrl, string $resolvedUrl, string $storeId, string $storeName): string
    {
        if ($storeId !== '' && ! in_array(Str::lower($storeName), ['rakip mağaza', 'satici', 'satıcı', 'sr'], true)) {
            return 'https://www.trendyol.com/magaza/'.Str::slug($storeName).'-m-'.$storeId;
        }

        foreach ([$requestedUrl, $resolvedUrl] as $candidate) {
            $candidate = $this->normalizeUrl($candidate);
            if (str_contains($candidate, '/magaza/')) {
                return Str::before($candidate, '?');
            }
        }

        return $this->normalizeUrl($resolvedUrl ?: $requestedUrl);
    }

    protected function money(mixed $value): float
    {
        if (is_string($value)) {
            $value = $this->normalizeMoneyString($value);
        }

        $price = round(max(0, (float) $value), 2);

        return $price <= 9_999_999.99 ? $price : 0.0;
    }

    protected function nullableIntegerValue(mixed $value): ?int
    {
        return is_numeric($value) ? max(0, (int) $value) : null;
    }

    protected function nullableDelta(mixed $current, mixed $previous): ?float
    {
        if (! is_numeric($current) || ! is_numeric($previous)) {
            return null;
        }

        return round((float) $current - (float) $previous, 2);
    }

    protected function normalizeMoneyString(string $value): string
    {
        $value = preg_replace('/[^\d,.\-]/u', '', $value) ?: '0';
        $lastComma = strrpos($value, ',');
        $lastDot = strrpos($value, '.');

        if ($lastComma !== false && $lastDot !== false) {
            return $lastComma > $lastDot
                ? str_replace(',', '.', str_replace('.', '', $value))
                : str_replace(',', '', $value);
        }

        if ($lastComma !== false) {
            return str_replace(',', '.', $value);
        }

        if (substr_count($value, '.') > 1) {
            return str_replace('.', '', $value);
        }

        return $value;
    }

    protected function isFallbackMessage(string $message): bool
    {
        $message = Str::lower($message);

        return str_contains($message, 'erişimi sınırladı')
            || str_contains($message, 'fallback');
    }
}
