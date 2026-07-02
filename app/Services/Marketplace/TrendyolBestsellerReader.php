<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TrendyolBestsellerReader
{
    /**
     * @return array{ok: bool, message: string, data: array<string, mixed>}
     */
    public function fetch(string $keyword, ?int $minPrice = null, ?int $maxPrice = null): array
    {
        $keyword = $this->normalizeKeyword($keyword);

        if (mb_strlen($keyword) < 2) {
            return $this->failure('Anahtar kelime/kategori en az 2 karakter olmalı.', $keyword);
        }

        $url = $this->bestsellerUrl($keyword);
        $directCategory = $this->directCategory($keyword);
        $dictionaryMatch = app(TrendyolCategoryDictionary::class)->resolve($keyword);

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                'Accept-Language' => 'tr-TR,tr;q=0.9,en-US;q=0.8,en;q=0.7',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Sec-Ch-Ua' => '"Not_A Brand";v="8", "Chromium";v="120", "Google Chrome";v="120"',
                'Sec-Ch-Ua-Mobile' => '?0',
                'Sec-Ch-Ua-Platform' => '"Windows"',
                'Sec-Fetch-Dest' => 'document',
                'Sec-Fetch-Mode' => 'navigate',
                'Sec-Fetch-Site' => 'none',
                'Sec-Fetch-User' => '?1',
                'Upgrade-Insecure-Requests' => '1',
            ])
                ->timeout((int) config('marketplace.trendyol_booster.request_timeout', 12))
                ->retry(
                    (int) config('marketplace.trendyol_booster.request_retries', 1),
                    (int) config('marketplace.trendyol_booster.request_retry_delay_ms', 250)
                )
                ->get($url);
        } catch (\Throwable) {
            return $this->failure('Trendyol arama sonucu şu anda okunamadı.', $keyword, $url);
        }

        if (! $response->successful()) {
            if ($response->status() === 403 || $response->status() === 429) {
                return $this->failure('Trendyol sunucu erişimini sınırladı. Güncel Çok Satanlar verisi için Chrome Companion ile tekrar deneyin.', $keyword, $url);
            }

            return $this->failure('Trendyol arama sonucu okunamadı. HTTP durum kodu: '.$response->status(), $keyword, $url);
        }

        $data = $this->parse($response->body(), $keyword, $url);
        $data['matched_label'] = (string) ($directCategory['label'] ?? $dictionaryMatch['matched_term'] ?? $keyword);
        $data['top_products'] = collect($data['top_products'])
            ->filter(fn (array $item): bool => ($minPrice === null || ($item['price'] !== null && $item['price'] >= $minPrice))
                && ($maxPrice === null || ($item['price'] !== null && $item['price'] <= $maxPrice)))
            ->values()
            ->all();
        $data['result_count'] = count($data['top_products']);

        if ($data['result_count'] === 0) {
            return $this->failure('Trendyol Çok Satanlar sayfası tarayıcıda çalışıyor; güncel kartları okumak için Chrome Companion ile tekrar deneyin.', $keyword, $url);
        }

        return [
            'ok' => true,
            'message' => 'Çok satanlar listesi getirildi.',
            'data' => $data,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function parse(string $html, string $keyword, string $url = ''): array
    {
        $topProducts = $this->extractFromState($html);

        if (empty($topProducts)) {
            $topProducts = $this->extractFromHtmlFallback($html);
        }

        return [
            'keyword' => $this->normalizeKeyword($keyword),
            'source_url' => $url,
            'result_count' => count($topProducts),
            'top_products' => $topProducts,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @return array<string, mixed>
     */
    public function parseBridgeData(array $products, string $keyword, string $url = ''): array
    {
        $topProducts = $this->extractFromProductsArray($products);

        return [
            'keyword' => $this->normalizeKeyword($keyword),
            'source_url' => $url,
            'result_count' => count($topProducts),
            'top_products' => $topProducts,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function extractFromState(string $html): array
    {
        if (! preg_match('/window\.__SEARCH_APP_INITIAL_STATE__\s*=\s*(\{.+?\});/is', $html, $matches)) {
            return [];
        }

        $json = json_decode($matches[1], true);
        if (! is_array($json)) {
            return [];
        }

        $products = data_get($json, 'products') ?? [];
        if (! is_array($products)) {
            return [];
        }

        return $this->extractFromProductsArray($products);
    }

    /**
     * @param  array<int, mixed>  $products
     * @return array<int, array<string, mixed>>
     */
    protected function extractFromProductsArray(array $products): array
    {
        $items = [];
        foreach ($products as $p) {
            if (! is_array($p)) {
                continue;
            }

            $productId = $p['trendyol_product_id'] ?? $p['id'] ?? null;
            if (! is_scalar($productId) || trim((string) $productId) === '') {
                continue;
            }

            $price = is_numeric($p['price'] ?? $p['sale_price'] ?? null)
                ? ($p['price'] ?? $p['sale_price'])
                : (data_get($p, 'price.sellingPrice') ?? data_get($p, 'price.discountedPrice'));
            $rating = is_numeric($p['rating'] ?? null) ? $p['rating'] : data_get($p, 'ratingScore.averageRating');
            $ratingCount = is_numeric($p['rating_count'] ?? null) ? $p['rating_count'] : data_get($p, 'ratingScore.totalRatingCount');
            $sourceUrl = trim((string) ($p['source_url'] ?? $p['url'] ?? ''));
            $brand = is_array($p['brand'] ?? null) ? ($p['brand']['name'] ?? '') : ($p['brand'] ?? '');
            $imageUrl = trim((string) ($p['image_url'] ?? ''));
            if ($imageUrl === '' && isset($p['images'][0])) {
                $imageUrl = Str::startsWith((string) $p['images'][0], ['http://', 'https://'])
                    ? (string) $p['images'][0]
                    : 'https://cdn.dsmcdn.com/'.ltrim((string) $p['images'][0], '/');
            }
            $sales3d = is_numeric($p['estimated_sales_3d'] ?? null) ? (int) $p['estimated_sales_3d'] : null;
            $revenue3d = is_numeric($p['estimated_revenue_3d'] ?? null)
                ? (float) $p['estimated_revenue_3d']
                : ($sales3d !== null && is_numeric($price) ? round($sales3d * (float) $price, 2) : null);
            $sellers = collect((array) ($p['sellers'] ?? []))
                ->filter(fn ($seller): bool => is_array($seller))
                ->values()
                ->all();
            $primarySeller = $sellers[0] ?? [];
            $campaigns = collect((array) ($p['campaigns'] ?? $p['promotions'] ?? []))
                ->map(fn ($campaign): string => Str::limit(trim((string) (is_array($campaign) ? ($campaign['name'] ?? $campaign['title'] ?? '') : $campaign)), 240, ''))
                ->filter()
                ->unique()
                ->values()
                ->all();
            $stockQuantity = is_numeric($p['stock_quantity'] ?? $p['total_stock'] ?? null)
                ? max(0, (int) ($p['stock_quantity'] ?? $p['total_stock']))
                : null;
            $stockStatus = in_array($p['stock_status'] ?? null, ['in_stock', 'out_of_stock', 'unknown'], true)
                ? $p['stock_status']
                : ($stockQuantity === null ? 'unknown' : ($stockQuantity > 0 ? 'in_stock' : 'out_of_stock'));

            $items[] = [
                'trendyol_product_id' => (string) $productId,
                'source_url' => Str::startsWith($sourceUrl, ['http://', 'https://']) ? $sourceUrl : 'https://www.trendyol.com'.($sourceUrl !== '' ? '/'.ltrim($sourceUrl, '/') : ''),
                'rank' => is_numeric($p['rank'] ?? null) ? (int) $p['rank'] : count($items) + 1,
                'title' => Str::limit((string) ($p['title'] ?? $p['name'] ?? ''), 180, ''),
                'brand' => Str::limit((string) $brand, 120, ''),
                'image_url' => $imageUrl,
                'price' => is_numeric($price) ? (float) $price : null,
                'rating' => is_numeric($rating) ? (float) $rating : null,
                'rating_count' => is_numeric($ratingCount) ? (int) $ratingCount : 0,
                'sold_text' => Str::limit((string) ($p['sold_text'] ?? ''), 120, ''),
                'estimated_sales_3d' => $sales3d,
                'estimated_revenue_3d' => $revenue3d,
                'favorite_count' => is_numeric($p['favorite_count'] ?? null) ? (int) $p['favorite_count'] : null,
                'basket_count' => is_numeric($p['basket_count'] ?? null) ? (int) $p['basket_count'] : null,
                'view_count_24h' => is_numeric($p['view_count_24h'] ?? null) ? (int) $p['view_count_24h'] : null,
                'seller_name' => Str::limit(trim((string) ($p['seller_name'] ?? $primarySeller['seller_name'] ?? '')), 180, ''),
                'seller_id' => Str::limit(trim((string) ($p['seller_id'] ?? $primarySeller['seller_id'] ?? '')), 80, ''),
                'seller_score' => is_numeric($p['seller_score'] ?? $primarySeller['seller_score'] ?? null)
                    ? (float) ($p['seller_score'] ?? $primarySeller['seller_score'])
                    : null,
                'sellers' => $sellers,
                'stock_quantity' => $stockQuantity,
                'total_stock' => $stockQuantity,
                'stock_status' => $stockStatus,
                'campaign_count' => is_numeric($p['campaign_count'] ?? null) ? max(0, (int) $p['campaign_count']) : count($campaigns),
                'campaigns' => $campaigns,
                'promotions' => $campaigns,
                'enrichment_status' => in_array($p['enrichment_status'] ?? null, ['enriched', 'partial'], true)
                    ? $p['enrichment_status']
                    : 'partial',
                'captured_at' => isset($p['captured_at']) ? Str::limit((string) $p['captured_at'], 40, '') : null,
            ];

            if (count($items) >= 20) {
                break;
            }
        }

        return $items;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function extractFromHtmlFallback(string $html): array
    {
        $items = [];

        if (! preg_match_all('/<a[^>]+href=["\']([^"\']*-p-(\d+)[^"\']*)["\'][^>]*>(.*?)<\/a>/isu', $html, $matches, PREG_SET_ORDER)) {
            return [];
        }

        foreach ($matches as $match) {
            $productId = (string) $match[2];

            if (isset($items[$productId])) {
                continue;
            }

            $href = html_entity_decode((string) $match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $title = $this->cleanText(strip_tags((string) ($match[3] ?? '')));
            $items[$productId] = [
                'trendyol_product_id' => $productId,
                'source_url' => Str::startsWith($href, ['http://', 'https://']) ? $href : 'https://www.trendyol.com'.$href,
                'rank' => count($items) + 1,
                'title' => Str::limit($title, 180, ''),
                'brand' => '',
                'image_url' => '',
                'price' => null,
                'rating' => null,
                'rating_count' => 0,
                'sold_text' => '',
                'estimated_sales_3d' => null,
                'estimated_revenue_3d' => null,
                'favorite_count' => null,
                'basket_count' => null,
                'view_count_24h' => null,
            ];

            if (count($items) >= 20) {
                break;
            }
        }

        return array_values($items);
    }

    /**
     * @return array{ok: bool, message: string, data: array<string, mixed>}
     */
    protected function failure(string $message, string $keyword, string $url = ''): array
    {
        return [
            'ok' => false,
            'message' => $message,
            'data' => [
                'keyword' => $this->normalizeKeyword($keyword),
                'source_url' => $url,
                'result_count' => 0,
                'top_products' => [],
            ],
        ];
    }

    protected function cleanText(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value) ?: '';

        return trim($value);
    }

    protected function normalizeKeyword(string $keyword): string
    {
        $keyword = html_entity_decode(strip_tags($keyword), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $keyword = preg_replace('/\s+/u', ' ', $keyword) ?: '';

        return trim(Str::limit($keyword, 180, ''));
    }

    protected function bestsellerUrl(string $keyword): string
    {
        $url = 'https://www.trendyol.com/cok-satanlar?type=bestSeller';
        $directCategory = $this->directCategory($keyword);

        if ($directCategory !== null) {
            return $url.'&categoryId='.$directCategory['categoryId'];
        }

        $genderId = $this->genderId($keyword);
        if ($genderId !== null) {
            $url .= '&webGenderId='.$genderId;
        }

        return $url;
    }

    protected function directCategory(string $keyword): ?array
    {
        $keyword = Str::lower(Str::ascii($keyword));

        return match (true) {
            preg_match('/\b(puf|puflar|puf koltuk|puf bench|bench puf|puf minder|puf tabure)\b/', $keyword) === 1 => [
                'label' => 'Puflar',
                'categoryId' => 104493,
            ],
            preg_match('/\b(berjer|berjerler|tekli koltuk|dinlenme koltugu)\b/', $keyword) === 1 => [
                'label' => 'Berjerler',
                'categoryId' => 104495,
            ],
            preg_match('/\b(kanepe|kanepeler|cekyat|cek yat|sofa|chester)\b/', $keyword) === 1 => [
                'label' => 'Kanepeler',
                'categoryId' => 104491,
            ],
            default => null,
        };
    }

    protected function genderId(string $keyword): ?int
    {
        $keyword = Str::lower(Str::ascii($keyword));

        return match (true) {
            preg_match('/\b(erkek|bay)\b/', $keyword) === 1 => 2,
            preg_match('/\b(cocuk|bebek)\b/', $keyword) === 1 => 3,
            preg_match('/\b(kadin|bayan)\b/', $keyword) === 1 => 1,
            default => null,
        };
    }
}
