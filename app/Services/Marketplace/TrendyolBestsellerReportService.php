<?php

namespace App\Services\Marketplace;

use App\Models\TrendyolBestsellerReport;
use App\Models\TrendyolBestsellerReportItem;
use App\Models\TrendyolBestsellerReportRun;
use App\Models\TrendyolBoosterProduct;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TrendyolBestsellerReportService
{
    /**
     * @param  array{query: string, matched_label?: string|null, source_url?: string|null, min_price?: mixed, max_price?: mixed, source?: string|null}  $context
     * @param  array<int, array<string, mixed>>  $items
     * @return array{report: TrendyolBestsellerReport, run: TrendyolBestsellerReportRun, created: bool}
     */
    public function storeRun(int $userId, array $context, array $items): array
    {
        $query = $this->cleanText($context['query'] ?? '', 180);
        if (mb_strlen($query) < 2) {
            throw new \InvalidArgumentException('Rapor için geçerli bir kategori veya anahtar kelime gerekir.');
        }

        $normalizedQuery = $this->normalizeQuery($query);
        $minPrice = $this->nullableMoney($context['min_price'] ?? null);
        $maxPrice = $this->nullableMoney($context['max_price'] ?? null);
        $fingerprint = $this->fingerprint($query, $minPrice, $maxPrice);
        $capturedAt = now();
        $cleanItems = collect($items)
            ->filter(fn ($item): bool => is_array($item) && $this->productId($item) !== '')
            ->unique(fn (array $item): string => $this->productId($item))
            ->take(100)
            ->values();

        if ($cleanItems->isEmpty()) {
            throw new \InvalidArgumentException('Kaydedilecek çok satan ürün bulunamadı.');
        }

        return DB::transaction(function () use (
            $userId,
            $context,
            $query,
            $normalizedQuery,
            $minPrice,
            $maxPrice,
            $fingerprint,
            $capturedAt,
            $cleanItems,
        ): array {
            $report = TrendyolBestsellerReport::query()
                ->where('user_id', $userId)
                ->where('fingerprint', $fingerprint)
                ->lockForUpdate()
                ->first();
            $created = $report === null;

            if (! $report) {
                $report = TrendyolBestsellerReport::query()->create([
                    'user_id' => $userId,
                    'name' => Str::limit($query.' Çok Satanlar', 180, ''),
                    'query' => $query,
                    'normalized_query' => $normalizedQuery,
                    'matched_label' => $this->nullableText($context['matched_label'] ?? null, 180),
                    'source_url' => $this->safeUrl($context['source_url'] ?? null),
                    'min_price' => $minPrice,
                    'max_price' => $maxPrice,
                    'fingerprint' => $fingerprint,
                    'status' => 'active',
                    'first_captured_at' => $capturedAt,
                ]);
            }

            $previousRun = $report->runs()
                ->with('items:id,trendyol_bestseller_report_run_id,trendyol_product_id,rank_position,price,stock_quantity,campaign_count')
                ->latest('captured_at')
                ->latest('id')
                ->first();
            $previousItems = $previousRun?->items->keyBy('trendyol_product_id') ?? collect();
            $trackedProducts = TrendyolBoosterProduct::query()
                ->where('user_id', $userId)
                ->whereIn('trendyol_product_id', $cleanItems->map(fn (array $item): string => $this->productId($item)))
                ->get(['id', 'trendyol_product_id'])
                ->keyBy('trendyol_product_id');

            $normalizedItems = $cleanItems->map(function (array $item, int $index) use (
                $report,
                $userId,
                $capturedAt,
                $previousItems,
                $trackedProducts,
            ): array {
                $productId = $this->productId($item);
                $rank = max(1, (int) ($item['rank'] ?? $item['rank_position'] ?? $index + 1));
                $previous = $previousItems->get($productId);
                $seller = $this->seller($item);
                $campaigns = $this->campaigns($item);
                $stockQuantity = $this->nullableInteger($item['stock_quantity'] ?? $item['total_stock'] ?? null);
                $campaignCount = $this->nullableInteger($item['campaign_count'] ?? null) ?? count($campaigns);

                return [
                    'trendyol_bestseller_report_id' => $report->id,
                    'trendyol_booster_product_id' => $trackedProducts->get($productId)?->id,
                    'user_id' => $userId,
                    'trendyol_product_id' => $productId,
                    'source_url' => $this->safeUrl($item['source_url'] ?? $item['url'] ?? null),
                    'title' => $this->nullableText($item['title'] ?? $item['name'] ?? null, 255),
                    'brand' => $this->nullableText($item['brand'] ?? null, 120),
                    'image_url' => $this->safeUrl($item['image_url'] ?? null),
                    'rank_position' => $rank,
                    'previous_rank' => $previous?->rank_position,
                    'rank_delta' => $previous ? (int) $previous->rank_position - $rank : null,
                    'price' => $this->nullableMoney($item['price'] ?? $item['sale_price'] ?? null),
                    'seller_name' => $this->nullableText($item['seller_name'] ?? $seller['seller_name'] ?? null, 180),
                    'seller_id' => $this->nullableText($item['seller_id'] ?? $seller['seller_id'] ?? null, 80),
                    'seller_score' => $this->nullableDecimal($item['seller_score'] ?? $seller['seller_score'] ?? null, 10),
                    'stock_quantity' => $stockQuantity,
                    'stock_status' => $this->stockStatus($item['stock_status'] ?? null, $stockQuantity),
                    'campaign_count' => max(0, $campaignCount),
                    'campaigns_json' => $campaigns ?: null,
                    'estimated_sales_3d' => $this->nullableInteger($item['estimated_sales_3d'] ?? null),
                    'estimated_revenue_3d' => $this->nullableMoney($item['estimated_revenue_3d'] ?? null),
                    'rating' => $this->nullableDecimal($item['rating'] ?? null, 5),
                    'rating_count' => $this->nullableInteger($item['rating_count'] ?? null) ?? 0,
                    'favorite_count' => $this->nullableInteger($item['favorite_count'] ?? null),
                    'basket_count' => $this->nullableInteger($item['basket_count'] ?? null),
                    'view_count_24h' => $this->nullableInteger($item['view_count_24h'] ?? null),
                    'data_quality_score' => $this->dataQualityScore($item, $seller, $stockQuantity, $campaigns),
                    'raw_payload' => $item,
                    'captured_at' => $capturedAt,
                ];
            });

            $prices = $normalizedItems->pluck('price')->filter(fn ($price): bool => is_numeric($price) && (float) $price > 0);
            $run = $report->runs()->create([
                'user_id' => $userId,
                'source' => in_array($context['source'] ?? null, ['browser_companion', 'server_reader', 'manual'], true)
                    ? $context['source']
                    : 'browser_companion',
                'source_url' => $this->safeUrl($context['source_url'] ?? $report->source_url),
                'item_count' => $normalizedItems->count(),
                'priced_item_count' => $prices->count(),
                'in_stock_item_count' => $normalizedItems->filter(fn (array $item): bool => $item['stock_quantity'] > 0 || $item['stock_status'] === 'in_stock')->count(),
                'campaign_item_count' => $normalizedItems->where('campaign_count', '>', 0)->count(),
                'average_price' => $prices->isNotEmpty() ? round((float) $prices->average(), 2) : null,
                'metadata_json' => [
                    'query' => $query,
                    'matched_label' => $context['matched_label'] ?? null,
                    'min_price' => $minPrice,
                    'max_price' => $maxPrice,
                    'previous_run_id' => $previousRun?->id,
                    'schema_version' => 1,
                ],
                'captured_at' => $capturedAt,
            ]);

            $run->items()->createMany($normalizedItems->all());
            $report->forceFill([
                'query' => $query,
                'normalized_query' => $normalizedQuery,
                'matched_label' => $this->nullableText($context['matched_label'] ?? $report->matched_label, 180),
                'source_url' => $this->safeUrl($context['source_url'] ?? $report->source_url),
                'min_price' => $minPrice,
                'max_price' => $maxPrice,
                'run_count' => (int) $report->run_count + 1,
                'latest_product_count' => $normalizedItems->count(),
                'first_captured_at' => $report->first_captured_at ?: $capturedAt,
                'last_captured_at' => $capturedAt,
            ])->save();

            return [
                'report' => $report->fresh() ?: $report,
                'run' => $run->fresh('items') ?: $run,
                'created' => $created,
            ];
        });
    }

    /** @return array<string, mixed> */
    public function dashboard(int $userId, ?int $selectedReportId = null): array
    {
        $reports = TrendyolBestsellerReport::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->with('latestRun')
            ->orderByDesc('last_captured_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $selected = $reports->firstWhere('id', $selectedReportId) ?? $reports->first();
        if (! $selected) {
            return $this->emptyDashboard();
        }

        $runs = $selected->runs()
            ->with(['items' => fn ($query) => $query->orderBy('rank_position')])
            ->latest('captured_at')
            ->latest('id')
            ->limit(31)
            ->get()
            ->sortBy(fn (TrendyolBestsellerReportRun $run): string => sprintf(
                '%s-%012d',
                $run->captured_at?->format('YmdHisv') ?? '00000000000000000',
                (int) $run->id,
            ))
            ->values();

        return [
            'reports' => $reports->map(fn (TrendyolBestsellerReport $report): array => $this->presentReport($report))->all(),
            'selected' => $this->presentReport($selected),
            'analysis' => $this->analyzeRuns($runs),
        ];
    }

    public function findForQuery(int $userId, string $query, mixed $minPrice = null, mixed $maxPrice = null): ?TrendyolBestsellerReport
    {
        return TrendyolBestsellerReport::query()
            ->where('user_id', $userId)
            ->where('fingerprint', $this->fingerprint($query, $this->nullableMoney($minPrice), $this->nullableMoney($maxPrice)))
            ->first();
    }

    public function fingerprint(string $query, ?float $minPrice = null, ?float $maxPrice = null): string
    {
        return hash('sha256', implode('|', [
            $this->normalizeQuery($query),
            $minPrice !== null ? number_format($minPrice, 2, '.', '') : '*',
            $maxPrice !== null ? number_format($maxPrice, 2, '.', '') : '*',
        ]));
    }

    /** @param Collection<int, TrendyolBestsellerReportRun> $runs */
    protected function analyzeRuns(Collection $runs): array
    {
        if ($runs->isEmpty()) {
            return $this->emptyAnalysis();
        }

        $runCount = $runs->count();
        $latestRun = $runs->last();
        $previousRun = $runCount > 1 ? $runs->get($runCount - 2) : null;
        $latestItems = $latestRun->items->keyBy('trendyol_product_id');
        $previousItems = $previousRun?->items->keyBy('trendyol_product_id') ?? collect();
        $allItems = $runs->flatMap->items;
        $runLabels = $runs->map(fn (TrendyolBestsellerReportRun $run): string => $run->captured_at?->format('d.m') ?? '-')->all();
        $groups = $allItems->groupBy('trendyol_product_id');

        $products = $groups->map(function (Collection $history, string $productId) use (
            $runs,
            $runCount,
            $latestItems,
            $previousItems,
        ): array {
            $history = $history->sortBy(fn (TrendyolBestsellerReportItem $item): string => sprintf(
                '%s-%012d-%012d',
                $item->captured_at?->format('YmdHisv') ?? '00000000000000000',
                (int) $item->trendyol_bestseller_report_run_id,
                (int) $item->id,
            ))->values();
            $current = $latestItems->get($productId) ?? $history->last();
            $previous = $previousItems->get($productId);
            $ranks = $history->pluck('rank_position')->map(fn ($rank): int => (int) $rank);
            $points = $runs->map(function (TrendyolBestsellerReportRun $run) use ($productId): ?int {
                $item = $run->items->firstWhere('trendyol_product_id', $productId);

                return $item ? (int) $item->rank_position : null;
            })->all();

            return [
                'trendyol_product_id' => $productId,
                'title' => (string) $current->title,
                'brand' => (string) $current->brand,
                'image_url' => (string) $current->image_url,
                'source_url' => (string) $current->source_url,
                'current_rank' => $latestItems->has($productId) ? (int) $current->rank_position : null,
                'previous_rank' => $previous?->rank_position,
                'rank_delta' => $latestItems->has($productId) ? $current->rank_delta : null,
                'best_rank' => $ranks->min(),
                'average_rank' => round((float) $ranks->average(), 1),
                'appearances' => $history->count(),
                'persistence_percent' => (int) round(($history->count() / max(1, $runCount)) * 100),
                'price' => $current->price !== null ? (float) $current->price : null,
                'price_delta' => $this->difference($current->price, $previous?->price),
                'stock_quantity' => $current->stock_quantity,
                'stock_delta' => $this->difference($current->stock_quantity, $previous?->stock_quantity),
                'campaign_count' => (int) $current->campaign_count,
                'campaign_delta' => $this->difference($current->campaign_count, $previous?->campaign_count),
                'cause' => $this->possibleCause($current, $previous),
                'rank_points' => $points,
            ];
        })->sort(function (array $left, array $right): int {
            $leftRank = $left['current_rank'] ?? 999;
            $rightRank = $right['current_rank'] ?? 999;

            return [$leftRank, -$left['appearances']] <=> [$rightRank, -$right['appearances']];
        })->values();

        $latestPresented = $latestRun->items
            ->sortBy('rank_position')
            ->map(function (TrendyolBestsellerReportItem $item) use ($previousItems): array {
                $previous = $previousItems->get($item->trendyol_product_id);

                return $this->presentItem($item) + [
                    'price_delta' => $this->difference($item->price, $previous?->price),
                    'stock_delta' => $this->difference($item->stock_quantity, $previous?->stock_quantity),
                    'campaign_delta' => $this->difference($item->campaign_count, $previous?->campaign_count),
                    'cause' => $this->possibleCause($item, $previous),
                ];
            })->values();

        $latestIds = $latestItems->keys();
        $previousIds = $previousItems->keys();

        return [
            'summary' => [
                'run_count' => $runCount,
                'unique_product_count' => $groups->count(),
                'rising_count' => $latestPresented->where('rank_delta', '>', 0)->count(),
                'falling_count' => $latestPresented->where('rank_delta', '<', 0)->count(),
                'new_entry_count' => $previousRun ? $latestIds->diff($previousIds)->count() : 0,
                'exit_count' => $previousRun ? $previousIds->diff($latestIds)->count() : 0,
                'persistent_count' => $products->where('persistence_percent', 100)->count(),
                'first_captured_at' => $runs->first()?->captured_at?->toIso8601String(),
                'last_captured_at' => $latestRun->captured_at?->toIso8601String(),
            ],
            'run_labels' => $runLabels,
            'runs' => $runs->map(fn (TrendyolBestsellerReportRun $run): array => [
                'id' => $run->id,
                'captured_at' => $run->captured_at?->toIso8601String(),
                'label' => $run->captured_at?->format('d.m.Y H:i'),
                'item_count' => (int) $run->item_count,
                'priced_item_count' => (int) $run->priced_item_count,
                'in_stock_item_count' => (int) $run->in_stock_item_count,
                'campaign_item_count' => (int) $run->campaign_item_count,
                'average_price' => $run->average_price !== null ? (float) $run->average_price : null,
            ])->all(),
            'products' => $products->take(12)->all(),
            'latest_items' => $latestPresented->all(),
        ];
    }

    protected function possibleCause(TrendyolBestsellerReportItem $current, ?TrendyolBestsellerReportItem $previous): array
    {
        if (! $previous) {
            return ['tone' => 'info', 'label' => 'İlk ölçüm', 'detail' => 'Karşılaştırma için ikinci kayıt bekleniyor.'];
        }

        $rankDelta = (int) ($current->rank_delta ?? 0);
        $priceDelta = $this->difference($current->price, $previous->price);
        $stockDelta = $this->difference($current->stock_quantity, $previous->stock_quantity);
        $campaignDelta = $this->difference($current->campaign_count, $previous->campaign_count);

        if ($rankDelta > 0 && $campaignDelta > 0) {
            return ['tone' => 'positive', 'label' => 'Kampanya desteği', 'detail' => 'Yeni kampanya ile sıralama artışı aynı dönemde görüldü.'];
        }
        if ($rankDelta > 0 && $priceDelta !== null && $priceDelta < 0) {
            return ['tone' => 'positive', 'label' => 'Fiyat avantajı', 'detail' => 'Fiyat düşüşü ile sıralama artışı aynı dönemde görüldü.'];
        }
        if ($rankDelta < 0 && ($current->stock_status === 'out_of_stock' || $current->stock_quantity === 0)) {
            return ['tone' => 'negative', 'label' => 'Stok baskısı', 'detail' => 'Görünen stok tükendi ve sıralama geriledi.'];
        }
        if ($rankDelta < 0 && $campaignDelta < 0) {
            return ['tone' => 'negative', 'label' => 'Kampanya kaybı', 'detail' => 'Kampanya azalması ile sıralama düşüşü aynı dönemde görüldü.'];
        }
        if ($rankDelta < 0 && $priceDelta !== null && $priceDelta > 0) {
            return ['tone' => 'negative', 'label' => 'Fiyat baskısı', 'detail' => 'Fiyat artışı ile sıralama düşüşü aynı dönemde görüldü.'];
        }
        if ($rankDelta > 0 && $stockDelta !== null && $stockDelta > 0) {
            return ['tone' => 'positive', 'label' => 'Stok güçlenmesi', 'detail' => 'Görünen stok artarken sıralama da yükseldi.'];
        }
        if ($rankDelta > 0) {
            return ['tone' => 'positive', 'label' => 'Talep ivmesi', 'detail' => 'Ölçülen fiyat, stok veya kampanya dışında olumlu talep sinyali olabilir.'];
        }
        if ($rankDelta < 0) {
            return ['tone' => 'negative', 'label' => 'Rekabet baskısı', 'detail' => 'Ölçülen alanlar açıklamıyorsa rakip hareketi veya talep değişimi etkili olabilir.'];
        }

        return ['tone' => 'neutral', 'label' => 'Dengeli seyir', 'detail' => 'Sıralamada anlamlı bir değişim görülmedi.'];
    }

    protected function presentReport(TrendyolBestsellerReport $report): array
    {
        return [
            'id' => $report->id,
            'name' => $report->name,
            'query' => $report->query,
            'matched_label' => $report->matched_label,
            'source_url' => $report->source_url,
            'min_price' => $report->min_price !== null ? (float) $report->min_price : null,
            'max_price' => $report->max_price !== null ? (float) $report->max_price : null,
            'run_count' => (int) $report->run_count,
            'latest_product_count' => (int) $report->latest_product_count,
            'first_captured_at' => $report->first_captured_at?->toIso8601String(),
            'last_captured_at' => $report->last_captured_at?->toIso8601String(),
            'last_captured_label' => $report->last_captured_at?->format('d.m.Y H:i') ?? '-',
            'latest_priced_count' => (int) ($report->latestRun?->priced_item_count ?? 0),
            'latest_campaign_count' => (int) ($report->latestRun?->campaign_item_count ?? 0),
        ];
    }

    protected function presentItem(TrendyolBestsellerReportItem $item): array
    {
        return [
            'id' => $item->id,
            'trendyol_product_id' => $item->trendyol_product_id,
            'tracked_product_id' => $item->trendyol_booster_product_id,
            'source_url' => $item->source_url,
            'title' => $item->title,
            'brand' => $item->brand,
            'image_url' => $item->image_url,
            'rank' => (int) $item->rank_position,
            'previous_rank' => $item->previous_rank,
            'rank_delta' => $item->rank_delta,
            'price' => $item->price !== null ? (float) $item->price : null,
            'seller_name' => $item->seller_name,
            'seller_score' => $item->seller_score !== null ? (float) $item->seller_score : null,
            'stock_quantity' => $item->stock_quantity,
            'stock_status' => $item->stock_status,
            'campaign_count' => (int) $item->campaign_count,
            'campaigns' => array_values((array) $item->campaigns_json),
            'sold_text' => (string) data_get($item->raw_payload, 'sold_text', ''),
            'estimated_sales_3d' => $item->estimated_sales_3d,
            'estimated_revenue_3d' => $item->estimated_revenue_3d !== null ? (float) $item->estimated_revenue_3d : null,
            'rating' => $item->rating !== null ? (float) $item->rating : null,
            'rating_count' => (int) $item->rating_count,
            'favorite_count' => $item->favorite_count,
            'data_quality_score' => (int) $item->data_quality_score,
            'captured_at' => $item->captured_at?->toIso8601String(),
        ];
    }

    /** @param array<string, mixed> $item */
    protected function seller(array $item): array
    {
        $seller = collect((array) ($item['sellers'] ?? []))->first(fn ($value): bool => is_array($value));

        return is_array($seller) ? $seller : [];
    }

    /** @param array<string, mixed> $item */
    protected function campaigns(array $item): array
    {
        return collect((array) ($item['campaigns'] ?? $item['promotions'] ?? []))
            ->map(fn ($campaign): string => $this->cleanText(is_array($campaign) ? ($campaign['name'] ?? $campaign['title'] ?? '') : $campaign, 240))
            ->filter()
            ->unique()
            ->take(20)
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $item */
    protected function dataQualityScore(array $item, array $seller, ?int $stockQuantity, array $campaigns): int
    {
        $score = 0;
        $score += $this->nullableMoney($item['price'] ?? $item['sale_price'] ?? null) !== null ? 20 : 0;
        $score += $this->cleanText($item['seller_name'] ?? $seller['seller_name'] ?? '', 180) !== '' ? 15 : 0;
        $score += $stockQuantity !== null ? 20 : 0;
        $score += array_key_exists('campaign_count', $item) || $campaigns !== [] ? 10 : 0;
        $score += $this->nullableInteger($item['estimated_sales_3d'] ?? null) !== null ? 15 : 0;
        $score += $this->nullableDecimal($item['rating'] ?? null, 5) !== null ? 10 : 0;
        $score += $this->nullableInteger($item['favorite_count'] ?? null) !== null ? 5 : 0;
        $score += $this->safeUrl($item['image_url'] ?? null) !== null ? 5 : 0;

        return min(100, $score);
    }

    protected function difference(mixed $current, mixed $previous): int|float|null
    {
        if (! is_numeric($current) || ! is_numeric($previous)) {
            return null;
        }

        return round((float) $current - (float) $previous, 2);
    }

    protected function normalizeQuery(string $query): string
    {
        return Str::lower(Str::ascii($this->cleanText($query, 180)));
    }

    /** @param array<string, mixed> $item */
    protected function productId(array $item): string
    {
        return preg_replace('/\D+/', '', (string) ($item['trendyol_product_id'] ?? $item['id'] ?? '')) ?: '';
    }

    protected function nullableMoney(mixed $value): ?float
    {
        if (! is_numeric($value) || (float) $value <= 0) {
            return null;
        }

        return round((float) $value, 2);
    }

    protected function nullableDecimal(mixed $value, float $maximum): ?float
    {
        return is_numeric($value) ? round(max(0, min($maximum, (float) $value)), 2) : null;
    }

    protected function nullableInteger(mixed $value): ?int
    {
        return is_numeric($value) ? max(0, (int) $value) : null;
    }

    protected function stockStatus(mixed $value, ?int $stock): string
    {
        $status = (string) $value;
        if (in_array($status, ['in_stock', 'out_of_stock', 'unknown'], true)) {
            return $status;
        }

        return $stock === null ? 'unknown' : ($stock > 0 ? 'in_stock' : 'out_of_stock');
    }

    protected function safeUrl(mixed $value): ?string
    {
        $url = trim((string) $value);
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL) || ! Str::startsWith($url, ['http://', 'https://'])) {
            return null;
        }

        return Str::limit($url, 1000, '');
    }

    protected function nullableText(mixed $value, int $limit): ?string
    {
        $value = $this->cleanText($value, $limit);

        return $value !== '' ? $value : null;
    }

    protected function cleanText(mixed $value, int $limit): string
    {
        $value = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?: '';
        $value = preg_replace('/\s+/u', ' ', $value) ?: '';

        return Str::limit(trim($value), $limit, '');
    }

    protected function emptyDashboard(): array
    {
        return [
            'reports' => [],
            'selected' => null,
            'analysis' => $this->emptyAnalysis(),
        ];
    }

    protected function emptyAnalysis(): array
    {
        return [
            'summary' => [
                'run_count' => 0,
                'unique_product_count' => 0,
                'rising_count' => 0,
                'falling_count' => 0,
                'new_entry_count' => 0,
                'exit_count' => 0,
                'persistent_count' => 0,
                'first_captured_at' => null,
                'last_captured_at' => null,
            ],
            'run_labels' => [],
            'runs' => [],
            'products' => [],
            'latest_items' => [],
        ];
    }
}
