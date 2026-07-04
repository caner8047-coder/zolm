<?php

namespace App\Services\Marketplace;

use App\Models\TrendyolBoosterProduct;
use App\Models\TrendyolBoosterStockCheck;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TrendyolBoosterStockService
{
    public function __construct(
        protected TrendyolProductPageReader $reader,
        protected TrendyolBoosterActivityLogger $activityLogger,
        protected TrendyolBoosterNotificationService $notificationService,
        protected TrendyolCategoryDictionary $categoryDictionary,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{ok: bool, message: string, check: ?TrendyolBoosterStockCheck}
     */
    public function check(int $userId, array $input): array
    {
        $sourceUrl = $this->normalizeUrl((string) ($input['source_url'] ?? ''));
        $sourceHash = hash('sha256', $sourceUrl);
        $page = (array) ($input['page'] ?? []);
        $readerMessage = '';

        if (! $this->hasPageData($page)) {
            $result = $this->reader->fetch($sourceUrl);
            $page = $result['ok'] ? $result['data'] : $page;
            $readerMessage = (string) ($result['message'] ?? '');
        }

        $productId = $this->filledText($page['trendyol_product_id'] ?? null, $this->extractProductId($sourceUrl));
        $tracked = TrendyolBoosterProduct::query()
            ->where('user_id', $userId)
            ->where(function ($query) use ($productId, $sourceHash): void {
                if ($productId !== '') {
                    $query->where('trendyol_product_id', $productId);

                    return;
                }

                $query->where('source_url_hash', $sourceHash);
            })
            ->latest('updated_at')
            ->first();
        $previous = TrendyolBoosterStockCheck::query()
            ->with('sellers')
            ->where('user_id', $userId)
            ->where(function ($query) use ($productId, $sourceHash): void {
                if ($productId !== '') {
                    $query->where('trendyol_product_id', $productId);

                    return;
                }

                $query->where('source_url_hash', $sourceHash);
            })
            ->where('stock_status', '!=', 'unknown')
            ->latest('checked_at')
            ->first();
        $sellers = $this->normalizeSellers((array) ($input['sellers'] ?? []), $page, $input);
        $totalWasProvided = array_key_exists('total_stock', $input) && $input['total_stock'] !== null && $input['total_stock'] !== '';
        $pageTotalWasProvided = array_key_exists('total_stock', $page) && $page['total_stock'] !== null && $page['total_stock'] !== '';
        $totalStock = $totalWasProvided
            ? max(0, (int) $input['total_stock'])
            : ($pageTotalWasProvided ? max(0, (int) $page['total_stock']) : $sellers->sum('stock'));
        $hasStockSignal = $totalWasProvided
            || $pageTotalWasProvided
            || $sellers->isNotEmpty()
            || ($page['stock_status'] ?? '') === 'out_of_stock';

        if (! $hasStockSignal) {
            return [
                'ok' => false,
                'message' => $this->missingStockMessage($readerMessage),
                'check' => null,
            ];
        }

        $previousTotal = $previous?->total_stock;
        $stockDelta = $previousTotal !== null ? $totalStock - (int) $previousTotal : 0;
        $estimatedSales = $previousTotal !== null ? max(0, (int) $previousTotal - $totalStock) : 0;
        $stockStatus = $this->stockStatus($totalStock, $hasStockSignal);

        $check = TrendyolBoosterStockCheck::query()->create([
            'user_id' => $userId,
            'trendyol_booster_product_id' => $tracked?->id,
            'source_url' => $sourceUrl,
            'source_url_hash' => $sourceHash,
            'trendyol_product_id' => $productId,
            'barcode' => $this->filledText($input['barcode'] ?? null, $this->filledText($page['barcode'] ?? null, '')),
            'title' => $this->filledText($page['title'] ?? null, $tracked?->title ?: ''),
            'brand' => $this->filledText($page['brand'] ?? null, $tracked?->brand ?: ''),
            'image_url' => $this->filledText($page['image_url'] ?? null, ''),
            'total_stock' => $totalStock,
            'previous_total_stock' => $previousTotal,
            'stock_delta' => $stockDelta,
            'estimated_sales' => $estimatedSales,
            'seller_count' => $sellers->count(),
            'stock_status' => $stockStatus,
            'raw_payload' => [
                'page' => $page,
                'input' => Arr::except($input, ['page', 'sellers']),
            ],
            'checked_at' => now(),
        ]);

        $this->persistSellers($check, $sellers, $previous);

        $this->activityLogger->log(
            $userId,
            'stock_check',
            'Stok Sorgulama',
            $check->title ?: $sourceUrl,
            $sellers->count().' satıcıda toplam '.$totalStock.' stok okundu.',
            'stok',
            $totalStock,
            ['check_id' => $check->id, 'estimated_sales' => $estimatedSales],
            $tracked?->id,
        );
        $this->notificationService->notifyStockCheck($check->fresh(['trackedProduct']) ?: $check);

        return [
            'ok' => true,
            'message' => $previous
                ? 'Stok sorgusu kaydedildi; önceki sorguyla karşılaştırıldı.'
                : 'İlk stok sorgusu kaydedildi. Sonraki sorgularda satış düşüşü hesaplanacak.',
            'check' => $check->fresh(['sellers']) ?: $check,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(int $userId, ?int $selectedCheckId = null, array $filters = []): array
    {
        $base = TrendyolBoosterStockCheck::query()
            ->where('user_id', $userId)
            ->where('stock_status', '!=', 'unknown');
        $totalChecks = (clone $base)->count();
        $latest = (clone $base)->with(['sellers', 'trackedProduct'])->latest('checked_at')->limit(500)->get();
        $this->filterCheckSellers($latest);

        $selected = $selectedCheckId
            ? (clone $base)->with(['sellers', 'trackedProduct'])->whereKey($selectedCheckId)->first()
            : null;
        $selected ??= $latest->first();

        if ($selected) {
            $this->filterCheckSellers(collect([$selected]));
        }

        $allProductGroups = $this->productGroups($latest);
        $productGroups = $this->filterProductGroups($allProductGroups, $filters);

        return [
            'total_checks' => $totalChecks,
            'last_total_stock' => (int) ($selected?->total_stock ?? 0),
            'estimated_sales' => (int) ((clone $base)->sum('estimated_sales') ?? 0),
            'seller_count' => (int) ($selected?->seller_count ?? 0),
            'selected_check' => $selected,
            'selected_check_id' => $selected?->id,
            'trend' => $selected ? $this->trendForCheck($userId, $selected) : collect(),
            'product_groups' => $productGroups,
            'product_group_total' => $allProductGroups->count(),
            'product_group_count' => $productGroups->count(),
            'filtered_check_count' => (int) $productGroups->sum('query_count'),
            'stock_categories' => $allProductGroups
                ->pluck('category_name')
                ->filter()
                ->unique()
                ->sort(fn (string $left, string $right): int => strnatcasecmp($left, $right))
                ->values(),
            'history_truncated' => $totalChecks > $latest->count(),
            'latest_checks' => $latest,
        ];
    }

    /**
     * @param  Collection<int, TrendyolBoosterStockCheck>  $checks
     * @return Collection<int, array<string, mixed>>
     */
    protected function productGroups(Collection $checks): Collection
    {
        return $checks
            ->groupBy(fn (TrendyolBoosterStockCheck $check): string => $this->historyGroupKey($check))
            ->map(function (Collection $groupChecks, string $key): array {
                /** @var Collection<int, TrendyolBoosterStockCheck> $sorted */
                $sorted = $groupChecks
                    ->sortByDesc(fn (TrendyolBoosterStockCheck $check): int => $check->checked_at?->getTimestamp() ?? 0)
                    ->values();
                /** @var TrendyolBoosterStockCheck|null $latest */
                $latest = $sorted->first();
                $trend = $this->trendRowsFromChecks($sorted->sortBy('checked_at')->take(-14)->values());
                $trackedProduct = $latest?->trackedProduct;
                $categoryName = $sorted
                    ->map(function (TrendyolBoosterStockCheck $check): string {
                        $trackedCategory = $this->cleanCategoryName($check->trackedProduct?->category_name);

                        return $trackedCategory !== ''
                            ? $trackedCategory
                            : $this->cleanCategoryName(data_get($check->raw_payload, 'page.category_name'));
                    })
                    ->first(fn (string $category): bool => $category !== '') ?: '';
                $categoryName = $categoryName !== '' ? $categoryName : $this->inferCategoryName((string) $latest?->title);
                $favoriteCount = $latest ? data_get($latest->raw_payload, 'page.favorite_count') : null;

                return [
                    'key' => $key,
                    'key_hash' => sha1($key),
                    'latest_check' => $latest,
                    'checks' => $sorted->values(),
                    'trend' => $trend,
                    'category_name' => $categoryName,
                    'latest_favorite_count' => is_numeric($favoriteCount) ? (int) $favoriteCount : null,
                    'seller_names' => $latest?->sellers?->pluck('seller_name')->filter()->join(', ') ?: '',
                    'is_favorite' => (bool) ($trackedProduct?->is_favorite ?? false),
                    'is_tracking' => $trackedProduct?->tracking_status === 'active' && (bool) $trackedProduct?->watch_stock,
                    'stock_status' => (string) ($latest?->stock_status ?? ''),
                    'stock_delta' => (int) ($latest?->stock_delta ?? 0),
                    'query_count' => $sorted->count(),
                    'last_checked_at' => $latest?->checked_at,
                ];
            })
            ->sortByDesc(fn (array $group): int => data_get($group, 'last_checked_at')?->getTimestamp() ?? 0)
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $groups
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    protected function filterProductGroups(Collection $groups, array $filters): Collection
    {
        $search = Str::lower(trim((string) ($filters['search'] ?? '')));
        $category = trim((string) ($filters['category'] ?? 'all'));
        $status = (string) ($filters['status'] ?? 'all');
        $sort = (string) ($filters['sort'] ?? 'latest');

        $filtered = $groups->filter(function (array $group) use ($search, $category, $status): bool {
            /** @var TrendyolBoosterStockCheck|null $latest */
            $latest = $group['latest_check'] ?? null;
            $searchText = Str::lower(collect([
                $latest?->title,
                $latest?->brand,
                $latest?->trendyol_product_id,
                $group['category_name'] ?? null,
                $group['seller_names'] ?? null,
            ])->filter()->join(' '));

            if ($search !== '' && ! Str::contains($searchText, $search)) {
                return false;
            }

            if ($category !== 'all' && (string) ($group['category_name'] ?? '') !== $category) {
                return false;
            }

            return match ($status) {
                'favorites' => (bool) ($group['is_favorite'] ?? false),
                'tracking' => (bool) ($group['is_tracking'] ?? false),
                'declining' => (int) ($group['stock_delta'] ?? 0) < 0,
                'out_of_stock' => (int) ($latest?->total_stock ?? 0) === 0
                    || (string) ($group['stock_status'] ?? '') === 'out_of_stock',
                default => true,
            };
        });

        $sorted = match ($sort) {
            'name_asc' => $filtered->sortBy(fn (array $group): string => Str::lower((string) data_get($group, 'latest_check.title', ''))),
            'stock_asc' => $filtered->sortBy(fn (array $group): int => (int) data_get($group, 'latest_check.total_stock', 0)),
            'stock_desc' => $filtered->sortByDesc(fn (array $group): int => (int) data_get($group, 'latest_check.total_stock', 0)),
            'favorites_desc' => $filtered->sortByDesc(fn (array $group): int => (int) ($group['latest_favorite_count'] ?? -1)),
            'queries_desc' => $filtered->sortByDesc(fn (array $group): int => (int) ($group['query_count'] ?? 0)),
            default => $filtered->sortByDesc(fn (array $group): int => data_get($group, 'last_checked_at')?->getTimestamp() ?? 0),
        };

        return $sorted->values();
    }

    /**
     * @param  Collection<int, TrendyolBoosterStockCheck>  $checks
     */
    protected function filterCheckSellers(Collection $checks): void
    {
        $checks->each(function (TrendyolBoosterStockCheck $check): void {
            $page = (array) data_get($check->raw_payload, 'page', []);
            $validSellers = $check->sellers
                ->filter(fn ($seller): bool => $this->isCredibleSeller([
                    'seller_name' => $seller->seller_name,
                    'seller_id' => $seller->seller_id,
                    'stock' => $seller->stock,
                ], $page))
                ->values();

            $check->setRelation('sellers', $validSellers);
            $check->setAttribute('seller_count', $validSellers->count());
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function trendForCheck(int $userId, TrendyolBoosterStockCheck $check): Collection
    {
        $productId = trim((string) $check->trendyol_product_id);
        $sourceHash = trim((string) $check->source_url_hash);

        $checks = TrendyolBoosterStockCheck::query()
            ->with('sellers')
            ->where('user_id', $userId)
            ->where('stock_status', '!=', 'unknown')
            ->where(function ($query) use ($productId, $sourceHash): void {
                if ($productId !== '') {
                    $query->where('trendyol_product_id', $productId);

                    return;
                }

                $query->where('source_url_hash', $sourceHash);
            })
            ->latest('checked_at')
            ->limit(14)
            ->get()
            ->sortBy('checked_at')
            ->values();

        $this->filterCheckSellers($checks);

        return $this->trendRowsFromChecks($checks);
    }

    /**
     * @param  Collection<int, TrendyolBoosterStockCheck>  $checks
     * @return Collection<int, array<string, mixed>>
     */
    protected function trendRowsFromChecks(Collection $checks): Collection
    {
        return $checks->map(fn (TrendyolBoosterStockCheck $item): array => [
            'id' => $item->id,
            'checked_at' => $item->checked_at,
            'label' => $item->checked_at?->format('d.m H:i') ?: '-',
            'stock' => (int) $item->total_stock,
            'favorites' => data_get($item->raw_payload, 'page.favorite_count') !== null
                ? (int) data_get($item->raw_payload, 'page.favorite_count')
                : null,
            'seller_count' => (int) $item->seller_count,
        ]);
    }

    protected function historyGroupKey(TrendyolBoosterStockCheck $check): string
    {
        $productId = trim((string) $check->trendyol_product_id);

        return $productId !== ''
            ? 'product:'.$productId
            : 'url:'.trim((string) $check->source_url_hash);
    }

    protected function inferCategoryName(string $title): string
    {
        $normalizedTitle = trim(preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($title))) ?: '');
        $directCategory = match (true) {
            Str::contains($normalizedTitle, 'puf') => 'Puf',
            Str::contains($normalizedTitle, 'berjer') => 'Berjer',
            Str::contains($normalizedTitle, ['tisort', 't shirt', 'bisiklet yaka', 'gomlek', 'pantolon']) => match (true) {
                Str::contains($normalizedTitle, 'erkek') => 'Erkek Giyim',
                Str::contains($normalizedTitle, 'kadin') => 'Kadın Giyim',
                default => 'Giyim',
            },
            Str::contains($normalizedTitle, 'koltuk') => 'Koltuk',
            Str::contains($normalizedTitle, 'sehpa') => 'Sehpa',
            default => '',
        };
        if ($directCategory !== '') {
            return $directCategory;
        }

        $match = $this->categoryDictionary->resolve($title);
        if (! $match) {
            return '';
        }

        $productGroup = $this->cleanCategoryName($match['product_group'] ?? null);
        if ($productGroup !== '') {
            return $productGroup;
        }

        $matchedTerm = $this->cleanCategoryName($match['matched_term'] ?? null);
        $normalizedTerm = trim(preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($matchedTerm))) ?: '');
        $titleTokens = array_values(array_filter(explode(' ', $normalizedTitle)));
        if ($matchedTerm !== '' && ($normalizedTerm === $normalizedTitle || in_array($normalizedTerm, $titleTokens, true))) {
            return mb_convert_case($matchedTerm, MB_CASE_TITLE, 'UTF-8');
        }

        return '';
    }

    protected function cleanCategoryName(mixed $category): string
    {
        $value = trim((string) $category);

        if (mb_strlen($value) > 100 || substr_count($value, ',') > 2) {
            return '';
        }

        return $value;
    }

    /**
     * @param  array<int, mixed>  $sellers
     * @param  array<string, mixed>  $page
     * @param  array<string, mixed>  $input
     * @return Collection<int, array<string, mixed>>
     */
    protected function normalizeSellers(array $sellers, array $page, array $input): Collection
    {
        if ($sellers === [] && is_array($page['sellers'] ?? null)) {
            $sellers = $page['sellers'];
        }

        $normalized = collect($sellers)
            ->filter(fn (mixed $seller): bool => is_array($seller))
            ->map(function (array $seller): array {
                return [
                    'seller_name' => Str::limit($this->filledText($seller['seller_name'] ?? $seller['name'] ?? null, ''), 180, ''),
                    'seller_id' => Str::limit($this->filledText($seller['seller_id'] ?? null, ''), 80, ''),
                    'stock' => max(0, (int) ($seller['stock'] ?? 0)),
                    'sale_price' => $this->money($seller['sale_price'] ?? 0),
                    'seller_score' => $seller['seller_score'] ?? null,
                    'shipping_note' => Str::limit($this->filledText($seller['shipping_note'] ?? null, ''), 180, ''),
                ];
            })
            ->filter(fn (array $seller): bool => $this->isCredibleSeller($seller, $page))
            ->unique(fn (array $seller): string => $seller['seller_id'] !== ''
                ? 'id:'.$seller['seller_id']
                : 'name:'.Str::lower($seller['seller_name']))
            ->values();

        if ($normalized->isNotEmpty()) {
            return $normalized;
        }

        $totalStock = $input['total_stock'] ?? $page['total_stock'] ?? null;

        if ($totalStock !== null && $totalStock !== '') {
            return collect([[
                'seller_name' => $this->filledText(
                    $input['seller_name'] ?? $page['seller_name'] ?? null,
                    $this->filledText($page['brand'] ?? null, 'Ana satıcı'),
                ),
                'seller_id' => $this->filledText($page['seller_id'] ?? null, ''),
                'stock' => max(0, (int) $totalStock),
                'sale_price' => $this->money($page['sale_price'] ?? $input['sale_price'] ?? 0),
                'seller_score' => null,
                'shipping_note' => '',
            ]]);
        }

        return collect();
    }

    public function followCheck(int $userId, int $checkId): TrendyolBoosterProduct
    {
        $product = $this->productForCheck($userId, $checkId);
        $sources = collect((array) $product->tracking_sources)
            ->push('stock_query')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $product->forceFill([
            'tracking_status' => 'active',
            'tracking_sources' => $sources,
            'tracking_started_at' => $product->tracking_started_at ?: now(),
            'tracking_paused_at' => null,
            'watch_stock' => true,
            'analysis_auto_refresh_enabled' => true,
            'analysis_refresh_interval_minutes' => max(60, (int) config('marketplace.trendyol_booster.tracking.analysis_refresh_interval_minutes', 60)),
            'next_analysis_refresh_at' => now(),
        ])->save();

        return $product->refresh();
    }

    public function toggleCheckFavorite(int $userId, int $checkId): TrendyolBoosterProduct
    {
        $product = $this->productForCheck($userId, $checkId);
        $product->forceFill(['is_favorite' => ! $product->is_favorite])->save();

        return $product->refresh();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $sellers
     */
    protected function persistSellers(TrendyolBoosterStockCheck $check, Collection $sellers, ?TrendyolBoosterStockCheck $previous): void
    {
        $previousSellers = $previous?->sellers
            ? $previous->sellers->keyBy(fn ($seller) => $seller->seller_id ?: Str::lower($seller->seller_name))
            : collect();

        foreach ($sellers as $seller) {
            $key = $seller['seller_id'] ?: Str::lower($seller['seller_name']);
            $previousStock = $previousSellers->get($key)?->stock;
            $stockDelta = $previousStock !== null ? (int) $seller['stock'] - (int) $previousStock : 0;

            $check->sellers()->create([
                'user_id' => $check->user_id,
                'seller_name' => $seller['seller_name'],
                'seller_id' => $seller['seller_id'] ?: null,
                'stock' => $seller['stock'],
                'previous_stock' => $previousStock,
                'stock_delta' => $stockDelta,
                'estimated_sales' => $previousStock !== null ? max(0, (int) $previousStock - (int) $seller['stock']) : 0,
                'sale_price' => $seller['sale_price'],
                'seller_score' => is_numeric($seller['seller_score']) ? (float) $seller['seller_score'] : null,
                'shipping_note' => $seller['shipping_note'],
            ]);
        }
    }

    protected function productForCheck(int $userId, int $checkId): TrendyolBoosterProduct
    {
        $check = TrendyolBoosterStockCheck::query()
            ->where('user_id', $userId)
            ->findOrFail($checkId);
        $product = $check->trackedProduct;

        if (! $product && $check->trendyol_product_id) {
            $product = TrendyolBoosterProduct::query()
                ->where('user_id', $userId)
                ->where('trendyol_product_id', $check->trendyol_product_id)
                ->latest('updated_at')
                ->first();
        }

        $product ??= TrendyolBoosterProduct::query()->firstOrNew([
            'user_id' => $userId,
            'source_url_hash' => $check->source_url_hash,
        ]);

        if (! $product->exists) {
            $product->fill([
                'source_url' => $check->source_url,
                'trendyol_product_id' => $check->trendyol_product_id,
                'title' => $check->title,
                'brand' => $check->brand,
                'image_url' => $check->image_url,
                'sale_price' => $this->money(data_get($check->raw_payload, 'page.sale_price', 0)),
                'watch_price' => false,
                'watch_stock' => false,
                'tracking_status' => 'paused',
                'last_checked_at' => $check->checked_at,
            ]);
            $product->save();
        }

        if ((int) $check->trendyol_booster_product_id !== (int) $product->id) {
            $check->forceFill(['trendyol_booster_product_id' => $product->id])->save();
        }

        return $product;
    }

    /**
     * @param  array<string, mixed>  $seller
     * @param  array<string, mixed>  $page
     */
    protected function isCredibleSeller(array $seller, array $page): bool
    {
        $name = trim((string) ($seller['seller_name'] ?? ''));
        $sellerId = trim((string) ($seller['seller_id'] ?? ''));
        $stock = max(0, (int) ($seller['stock'] ?? 0));
        $primaryId = trim((string) ($page['seller_id'] ?? ''));
        $primaryName = trim((string) ($page['seller_name'] ?? ''));

        if ($name === '' || $this->isInvalidSellerName($name)) {
            return false;
        }

        return $stock > 0
            || $sellerId !== ''
            || ($primaryId !== '' && $sellerId === $primaryId)
            || ($primaryName !== '' && Str::lower($name) === Str::lower($primaryName));
    }

    protected function isInvalidSellerName(string $name): bool
    {
        $normalized = Str::lower(trim($name));

        return Str::contains($normalized, [
            'window[',
            '__envoy_',
            'bu tanımlama bilgileri',
            'trendyol\'da satış yap',
            'kampanyalı fiyat',
            'trendyol plus',
            'satıcı soruları',
            'soruları (',
            'takipçi',
            'sipariş verirsen',
        ]) || in_array($normalized, ['yarın', 'tahmini', 'ürün', 'satıcı', 'ana satıcı'], true);
    }

    protected function stockStatus(int $stock, bool $hasSignal): string
    {
        if (! $hasSignal) {
            return 'unknown';
        }

        return $stock > 0 ? 'in_stock' : 'out_of_stock';
    }

    protected function normalizeUrl(string $url): string
    {
        $url = trim($url);
        $url = preg_replace('/\s+/u', '', $url) ?: '';

        return Str::limit($url, 1000, '');
    }

    protected function hasPageData(array $page): bool
    {
        return trim((string) ($page['title'] ?? '')) !== ''
            || trim((string) ($page['trendyol_product_id'] ?? '')) !== ''
            || (float) ($page['sale_price'] ?? 0) > 0;
    }

    protected function missingStockMessage(string $readerMessage): string
    {
        $limited = Str::contains(Str::lower($readerMessage), ['erişimi sınırladı', '403', 'okunamadı']);

        return $limited
            ? 'Trendyol stok verisini sunucuya kapattı. Chrome eklentisiyle sorgulayın veya toplam stoku manuel girin; sıfır stok kaydı oluşturulmadı.'
            : 'Bu üründen doğrulanabilir stok adedi alınamadı. Chrome eklentisiyle sorgulayın veya toplam stoku manuel girin; sıfır stok kaydı oluşturulmadı.';
    }

    protected function extractProductId(string $url): string
    {
        return preg_match('/-p-(\d+)/iu', $url, $match) ? (string) $match[1] : '';
    }

    protected function filledText(mixed $value, string $fallback): string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? Str::limit($text, 1000, '') : $fallback;
    }

    protected function money(mixed $value): float
    {
        if (is_string($value)) {
            $value = $this->normalizeMoneyString($value);
        }

        return round(max(0, (float) $value), 2);
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
}
