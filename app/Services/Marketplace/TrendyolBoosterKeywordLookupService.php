<?php

namespace App\Services\Marketplace;

use App\Models\TrendyolBoosterKeywordLookup;
use Illuminate\Support\Str;

class TrendyolBoosterKeywordLookupService
{
    public function __construct(
        protected TrendyolSearchResultReader $searchReader,
        protected TrendyolBoosterActivityLogger $activityLogger,
        protected TrendyolKeywordIntelligenceService $keywordIntelligence,
    ) {}

    /**
     * @return array{ok: bool, message: string, lookup: TrendyolBoosterKeywordLookup}
     */
    public function search(int $userId, string $keyword): array
    {
        $keyword = $this->normalizeKeyword($keyword);
        $result = $this->searchReader->fetch($keyword);

        return $this->persistResult(
            $userId,
            $keyword,
            (array) ($result['data'] ?? []),
            (bool) ($result['ok'] ?? false),
            (string) ($result['message'] ?? ''),
            'server_reader',
        );
    }

    /**
     * Chrome Companion tarafından okunan arama sonucunu güvenli sınırlarla kaydeder.
     *
     * @param  array<string, mixed>  $data
     * @return array{ok: bool, message: string, lookup: TrendyolBoosterKeywordLookup}
     */
    public function storeBrowserResult(int $userId, string $keyword, array $data): array
    {
        return $this->persistResult(
            $userId,
            $this->normalizeKeyword($keyword),
            $data,
            true,
            'Anahtar kelime araması tarayıcıdan kaydedildi.',
            'browser_companion',
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{ok: bool, message: string, lookup: TrendyolBoosterKeywordLookup}
     */
    protected function persistResult(
        int $userId,
        string $keyword,
        array $data,
        bool $ok,
        string $message,
        string $source,
    ): array {
        $data = $this->normalizeResultData($keyword, $data, $source);
        $data['intelligence'] = $this->keywordIntelligence->analyze(
            $keyword,
            (array) $data['top_products'],
            (int) $data['result_count'],
        );

        $lookup = TrendyolBoosterKeywordLookup::query()->create([
            'user_id' => $userId,
            'keyword' => $keyword,
            'keyword_hash' => hash('sha256', Str::lower($keyword)),
            'source_url' => $data['source_url'] ?: null,
            'result_count' => (int) ($data['result_count'] ?? 0),
            'top_products' => array_values((array) ($data['top_products'] ?? [])),
            'raw_payload' => $data,
            'searched_at' => now(),
        ]);

        $this->activityLogger->log(
            $userId,
            'keyword_lookup',
            'Anahtar Kelime Aratma',
            $keyword,
            (int) $lookup->result_count.' ürün sonucu okundu.',
            'sonuç',
            $lookup->result_count,
            ['lookup_id' => $lookup->id, 'ok' => $ok, 'source' => $source],
        );

        return [
            'ok' => $ok,
            'message' => $ok ? 'Anahtar kelime araması kaydedildi.' : $message,
            'lookup' => $lookup,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(int $userId): array
    {
        $base = TrendyolBoosterKeywordLookup::query()->where('user_id', $userId);
        $latest = (clone $base)->latest('searched_at')->limit(10)->get();
        $current = $latest->first(fn (TrendyolBoosterKeywordLookup $lookup): bool => count((array) $lookup->top_products) > 0);
        $intelligence = [];

        if ($current) {
            $cached = (array) data_get($current->raw_payload, 'intelligence', []);
            $intelligence = (int) ($cached['version'] ?? 0) === TrendyolKeywordIntelligenceService::VERSION
                ? $cached
                : $this->keywordIntelligence->analyze(
                    (string) $current->keyword,
                    (array) $current->top_products,
                    (int) $current->result_count,
                );
        }

        return [
            'total' => (clone $base)->count(),
            'successful_total' => (clone $base)->where('result_count', '>', 0)->count(),
            'last_result_count' => (int) ($current?->result_count ?? 0),
            'latest' => $latest,
            'unique_keywords' => (clone $base)->distinct('keyword_hash')->count('keyword_hash'),
            'current' => $current,
            'intelligence' => $intelligence,
        ];
    }

    protected function normalizeKeyword(string $keyword): string
    {
        return $this->cleanText($keyword, 180);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeResultData(string $keyword, array $data, string $source): array
    {
        $topProducts = collect((array) ($data['top_products'] ?? []))
            ->filter(fn (mixed $product): bool => is_array($product))
            ->map(function (array $product, int $index): array {
                $productId = preg_replace('/\D+/', '', (string) ($product['trendyol_product_id'] ?? $product['id'] ?? '')) ?: '';
                $sourceUrl = $this->normalizeTrendyolUrl((string) ($product['source_url'] ?? $product['url'] ?? ''));
                $title = $this->cleanText((string) ($product['title'] ?? $product['name'] ?? ''), 500);
                $brand = $this->brandFromTrendyolUrl($sourceUrl)
                    ?: $this->normalizeProvidedBrand((string) ($product['brand'] ?? ''), $title);

                return [
                    'trendyol_product_id' => Str::limit($productId, 30, ''),
                    'source_url' => $sourceUrl,
                    'title' => $title,
                    'brand' => $brand,
                    'rank' => max(1, min(500, (int) ($product['rank'] ?? $index + 1))),
                ];
            })
            ->filter(fn (array $product): bool => $product['trendyol_product_id'] !== '')
            ->unique('trendyol_product_id')
            ->take(40)
            ->values();

        $productIds = collect((array) ($data['product_ids'] ?? []))
            ->map(fn (mixed $productId): string => preg_replace('/\D+/', '', (string) $productId) ?: '')
            ->merge($topProducts->pluck('trendyol_product_id'))
            ->filter()
            ->unique()
            ->take(50)
            ->values()
            ->all();

        return [
            'keyword' => $this->normalizeKeyword($keyword),
            'source_url' => $this->normalizeTrendyolUrl((string) ($data['source_url'] ?? '')),
            'product_ids' => $productIds,
            'result_count' => max(0, min(4294967295, (int) ($data['result_count'] ?? count($productIds)))),
            'checked_result_count' => max(0, min(500, (int) ($data['checked_result_count'] ?? count($productIds)))),
            'scan_limit' => max(1, min(500, (int) ($data['scan_limit'] ?? 50))),
            'top_products' => $topProducts->all(),
            'source' => $source,
        ];
    }

    protected function normalizeTrendyolUrl(string $url): string
    {
        $url = trim($url);
        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));

        if (! filter_var($url, FILTER_VALIDATE_URL)
            || ! ($host === 'trendyol.com' || Str::endsWith($host, '.trendyol.com'))) {
            return '';
        }

        return Str::limit($url, 1000, '');
    }

    protected function brandFromTrendyolUrl(string $url): string
    {
        $segments = array_values(array_filter(explode('/', trim((string) parse_url($url, PHP_URL_PATH), '/'))));
        $slug = rawurldecode((string) ($segments[0] ?? ''));

        if ($slug === '' || in_array(Str::lower($slug), ['sr', 'magaza', 'butik'], true)) {
            return '';
        }

        return Str::limit(Str::headline(str_replace('-', ' ', $slug)), 120, '');
    }

    protected function normalizeProvidedBrand(string $brand, string $title): string
    {
        $brand = $this->cleanText($brand, 120);
        $brandWords = preg_split('/\s+/u', $brand, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $titleWords = preg_split('/\s+/u', $title, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($brand === ''
            || mb_strtolower($brand, 'UTF-8') === mb_strtolower($title, 'UTF-8')
            || count($brandWords) > 4
            || count($brandWords) > max(2, (int) floor(count($titleWords) * 0.45))) {
            return '';
        }

        return $brand;
    }

    protected function cleanText(string $value, int $limit): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?: '';
        $value = preg_replace('/\s+/u', ' ', $value) ?: '';

        return trim(Str::limit($value, $limit, ''));
    }
}
