<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceStore;
use App\Models\TrendyolBoosterReview;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Trendyol yorumlarını WooCommerce ürünleriyle eşleştirme motoru.
 *
 * 3 katmanlı strateji:
 * 1. Tam eşleşme (normalleştirilmiş ürün adı)
 * 2. Fuzzy eşleşme (kelime tokenları + Jaccard similarity)
 * 3. Manuel eşleme (kullanıcı arayüzünden)
 */
class TrendyolBoosterReviewMatchEngine
{
    /** Otomatik eşleme eşik değeri */
    public const AUTO_MATCH_THRESHOLD = 0.70;

    /** Önerildi olarak gösterilecek minimum skor */
    public const SUGGESTION_THRESHOLD = 0.50;

    /** WC ürün cache süresi (dakika) */
    private const WC_CACHE_MINUTES = 30;

    /** Normalleştirmede atılacak yaygın Türkçe/mobilya kelimeleri */
    private const STOP_WORDS = [
        'adet', 've', 'ile', 'için', 'veya', 'bir', 'de', 'da',
        'takım', 'takımı', 'set', 'seti',
    ];

    /**
     * WC ürün listesini çeker (Booster API key veya WC REST API).
     *
     * @return array{products: array, source: string, error: ?string}
     */
    public function fetchWcProducts(int $userId, bool $forceRefresh = false): array
    {
        $cacheKey = "trendyol_booster_wc_products:{$userId}";

        if (! $forceRefresh && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $connection = $this->getWcConnection($userId);
        if (! $connection) {
            return ['products' => [], 'source' => 'none', 'error' => 'WooCommerce bağlantısı bulunamadı.'];
        }

        // Strateji 1: ZOLM Booster plugin endpoint'i (Booster API key ile)
        $result = $this->fetchViaBoosterApi($connection);

        // Strateji 2: WC Store API (herkese açık blok endpoint'i — API key veya auth gerektirmez)
        if (empty($result['products'])) {
            $result = $this->fetchViaWcStoreApi($connection);
        }

        // Strateji 3: WC REST API (consumer key/secret ile fallback)
        if (empty($result['products']) && $connection['consumer_key'] !== '') {
            $result = $this->fetchViaWcRestApi($connection);
        }

        if (! empty($result['products'])) {
            Cache::put($cacheKey, $result, now()->addMinutes(self::WC_CACHE_MINUTES));
        }

        return $result;
    }

    /**
     * Tüm pending yorumları otomatik eşler.
     *
     * @return array{matched: int, suggested: int, unmatched: int, errors: array}
     */
    public function autoMatchAll(int $userId, array $wcProducts = []): array
    {
        if (empty($wcProducts)) {
            $fetchResult = $this->fetchWcProducts($userId);
            $wcProducts = $fetchResult['products'] ?? [];
        }

        if (empty($wcProducts)) {
            return [
                'matched' => 0,
                'suggested' => 0,
                'unmatched' => 0,
                'errors' => ['WooCommerce ürün listesi alınamadı veya boş.'],
            ];
        }

        // Normalleştirilmiş WC ürün haritası
        $wcMap = $this->buildWcProductMap($wcProducts);

        // Trendyol'daki benzersiz ürün gruplarını al
        $productGroups = TrendyolBoosterReview::where('user_id', $userId)
            ->where('match_status', 'pending')
            ->select('trendyol_product_id', 'product_title')
            ->selectRaw('count(*) as review_count')
            ->groupBy('trendyol_product_id', 'product_title')
            ->get();

        $matched = 0;
        $suggested = 0;
        $unmatched = 0;
        $errors = [];

        foreach ($productGroups as $group) {
            $bestMatch = $this->findBestMatch($group->product_title, $wcMap);

            if ($bestMatch && $bestMatch['score'] >= self::AUTO_MATCH_THRESHOLD) {
                // Otomatik eşle
                $count = $this->applyMatch(
                    $userId,
                    $group->trendyol_product_id,
                    (int) $bestMatch['wc_product_id'],
                    $bestMatch['wc_product_name'],
                    $bestMatch['score'],
                    'auto'
                );
                $matched += $count;
            } elseif ($bestMatch && $bestMatch['score'] >= self::SUGGESTION_THRESHOLD) {
                // Önerildi olarak işaretle
                $count = TrendyolBoosterReview::where('user_id', $userId)
                    ->where('trendyol_product_id', $group->trendyol_product_id)
                    ->where('match_status', 'pending')
                    ->update([
                        'match_status' => 'suggested',
                        'match_score' => $bestMatch['score'],
                        'wc_product_id' => $bestMatch['wc_product_id'],
                        'wc_product_sku' => $bestMatch['wc_sku'] ?? null,
                    ]);
                $suggested += $count;
            } else {
                // Eşleşme bulunamadı
                TrendyolBoosterReview::where('user_id', $userId)
                    ->where('trendyol_product_id', $group->trendyol_product_id)
                    ->where('match_status', 'pending')
                    ->update(['match_status' => 'unmatched', 'match_score' => $bestMatch['score'] ?? 0]);
                $unmatched += $group->review_count;
            }
        }

        return compact('matched', 'suggested', 'unmatched', 'errors');
    }

    /**
     * Aynı Trendyol ürünündeki tüm yorumları belirtilen WC ürününe eşler.
     */
    public function manualMatch(int $userId, string $trendyolProductId, int $wcProductId, ?string $wcProductName = null, ?string $wcSku = null): int
    {
        return $this->applyMatch($userId, $trendyolProductId, $wcProductId, $wcProductName, 1.0, 'manual', $wcSku);
    }

    /**
     * Bir eşlemeyi iptal eder (unmatched'e çevirir).
     */
    public function unmatch(int $userId, string $trendyolProductId): int
    {
        return TrendyolBoosterReview::where('user_id', $userId)
            ->where('trendyol_product_id', $trendyolProductId)
            ->update([
                'match_status' => 'unmatched',
                'match_score' => 0,
                'wc_product_id' => null,
                'wc_product_sku' => null,
            ]);
    }

    /**
     * Bir önerilen eşlemeyi onaylar.
     */
    public function confirmSuggestion(int $userId, string $trendyolProductId): int
    {
        return TrendyolBoosterReview::where('user_id', $userId)
            ->where('trendyol_product_id', $trendyolProductId)
            ->where('match_status', 'suggested')
            ->update(['match_status' => 'matched']);
    }

    /**
     * Ürün grupları bazında eşleme durumunu döner.
     *
     * @return array<int, array{trendyol_product_id: string, product_title: string, ...}>
     */
    public function getProductMatchGroups(int $userId, ?int $reviewSourceId = null): array
    {
        $query = TrendyolBoosterReview::where('user_id', $userId)
            ->when($reviewSourceId, fn ($q) => $q->where('review_source_id', $reviewSourceId))
            ->select(
                'trendyol_product_id',
                'product_title',
                'product_image_url',
                'match_status',
                'wc_product_id',
                'wc_product_sku',
                'match_score',
            )
            ->selectRaw('count(*) as review_count')
            ->selectRaw("sum(case when status = 'approved' then 1 else 0 end) as approved_count")
            ->selectRaw("sum(case when wc_push_status = 'pushed' then 1 else 0 end) as pushed_count")
            ->groupBy('trendyol_product_id', 'product_title', 'product_image_url', 'match_status', 'wc_product_id', 'wc_product_sku', 'match_score')
            ->orderByDesc('review_count')
            ->get();

        return $query->map(fn ($row) => [
            'trendyol_product_id' => $row->trendyol_product_id,
            'product_title' => $row->product_title,
            'product_image_url' => $row->product_image_url,
            'match_status' => $row->match_status,
            'wc_product_id' => $row->wc_product_id,
            'wc_product_sku' => $row->wc_product_sku,
            'match_score' => round((float) $row->match_score, 2),
            'review_count' => (int) $row->review_count,
            'approved_count' => (int) $row->approved_count,
            'pushed_count' => (int) $row->pushed_count,
        ])->all();
    }

    /**
     * Ürün adını normalize eder.
     * Türkçe karakterleri korur, küçük harfe çevirir, noktalama temizler.
     */
    public function normalizeTitle(string $title): string
    {
        // Küçük harf (Türkçe uyumlu)
        $title = mb_strtolower(trim($title), 'UTF-8');

        // Türkçe İ→i, I→ı dönüşümü (mb_strtolower zaten yapmalı ama garanti)
        $title = str_replace(['İ', 'I'], ['i', 'ı'], $title);

        // Noktalama ve özel karakterleri temizle
        $title = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $title);

        // Çoklu boşlukları teke indir
        $title = preg_replace('/\s+/u', ' ', $title);

        return trim($title);
    }

    /**
     * Ürün adını kelime tokenlarına ayırır.
     *
     * @return array<int, string>
     */
    public function tokenize(string $normalizedTitle): array
    {
        $words = preg_split('/\s+/u', $normalizedTitle);

        return array_values(array_filter($words, function (string $word): bool {
            return mb_strlen($word) > 1 && ! in_array($word, self::STOP_WORDS, true);
        }));
    }

    /**
     * İki token dizisi arasındaki Jaccard benzerlik skorunu hesaplar.
     */
    public function jaccardSimilarity(array $tokensA, array $tokensB): float
    {
        if (empty($tokensA) || empty($tokensB)) {
            return 0.0;
        }

        $setA = array_unique($tokensA);
        $setB = array_unique($tokensB);

        $intersection = count(array_intersect($setA, $setB));
        $union = count(array_unique(array_merge($setA, $setB)));

        if ($union === 0) {
            return 0.0;
        }

        return $intersection / $union;
    }

    /**
     * WC ürün haritası oluşturur (normalleştirilmiş ad → ürün bilgisi).
     */
    protected function buildWcProductMap(array $wcProducts): array
    {
        $map = [];
        foreach ($wcProducts as $product) {
            $normalizedName = $this->normalizeTitle($product['name'] ?? '');
            $tokens = $this->tokenize($normalizedName);

            $map[] = [
                'wc_product_id' => (int) ($product['id'] ?? 0),
                'wc_product_name' => $product['name'] ?? '',
                'wc_sku' => $product['sku'] ?? null,
                'normalized_name' => $normalizedName,
                'tokens' => $tokens,
            ];
        }

        return $map;
    }

    /**
     * En iyi WC eşleşmesini bulur.
     *
     * @return ?array{wc_product_id: int, wc_product_name: string, wc_sku: ?string, score: float}
     */
    protected function findBestMatch(string $trendyolTitle, array $wcMap): ?array
    {
        $normalizedTrendyol = $this->normalizeTitle($trendyolTitle);
        $trendyolTokens = $this->tokenize($normalizedTrendyol);

        if (empty($trendyolTokens)) {
            return null;
        }

        $bestMatch = null;
        $bestScore = 0.0;

        foreach ($wcMap as $wcProduct) {
            // Strateji 1: Tam eşleşme
            if ($normalizedTrendyol === $wcProduct['normalized_name']) {
                return [
                    'wc_product_id' => $wcProduct['wc_product_id'],
                    'wc_product_name' => $wcProduct['wc_product_name'],
                    'wc_sku' => $wcProduct['wc_sku'],
                    'score' => 1.0,
                ];
            }

            // WC ürün tokenlerinden mağaza ön ekini (zem, vb.) çıkar
            $wcTokensClean = array_values(array_diff($wcProduct['tokens'], ['zem', 'home', 'mağaza', 'magaza', 'store', 'shop']));
            if (empty($wcTokensClean)) {
                $wcTokensClean = $wcProduct['tokens'];
            }

            // Strateji 2: Jaccard similarity & Overlap (Kapsama) oranı
            $jaccard = $this->jaccardSimilarity($trendyolTokens, $wcTokensClean);
            
            // Overlap coefficient: Daha kısa olan başlığın kelimelerinin yüzde kaçı diğerinde var?
            $intersect = array_intersect($trendyolTokens, $wcTokensClean);
            $minCount = min(count($trendyolTokens), count($wcTokensClean));
            $overlap = $minCount > 0 ? (count($intersect) / $minCount) : 0.0;

            // Hibrit skor (Kapsama oranı + Jaccard)
            $score = ($overlap * 0.6) + ($jaccard * 0.4);

            // Bonus: İlk kelimeler (ürün model adı/serisi genellikle başta olur) eşleşiyorsa
            if (! empty($trendyolTokens) && ! empty($wcTokensClean)) {
                if ($trendyolTokens[0] === $wcTokensClean[0]) {
                    $score += 0.2;
                }
                // İlk iki kelime eşleşmesi ekstra bonus
                if (count($trendyolTokens) >= 2 && count($wcTokensClean) >= 2
                    && $trendyolTokens[0] === $wcTokensClean[0]
                    && $trendyolTokens[1] === $wcTokensClean[1]) {
                    $score += 0.15;
                }
            }

            $score = min(1.0, $score);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = [
                    'wc_product_id' => $wcProduct['wc_product_id'],
                    'wc_product_name' => $wcProduct['wc_product_name'],
                    'wc_sku' => $wcProduct['wc_sku'],
                    'score' => round($score, 3),
                ];
            }
        }

        return $bestMatch;
    }

    /**
     * Eşlemeyi ürün grubuna uygular.
     */
    protected function applyMatch(
        int $userId,
        string $trendyolProductId,
        int $wcProductId,
        ?string $wcProductName,
        float $score,
        string $method,
        ?string $wcSku = null
    ): int {
        return TrendyolBoosterReview::where('user_id', $userId)
            ->where('trendyol_product_id', $trendyolProductId)
            ->whereIn('match_status', ['pending', 'unmatched', 'suggested'])
            ->update([
                'wc_product_id' => $wcProductId,
                'wc_product_sku' => $wcSku,
                'match_status' => 'matched',
                'match_score' => $score,
            ]);
    }

    /**
     * ZOLM Booster WP plugin endpoint'inden ürün listesi çeker.
     */
    protected function fetchViaBoosterApi(array $connection): array
    {
        if ($connection['booster_api_key'] === '') {
            return ['products' => [], 'source' => 'booster_api', 'error' => 'Booster API key tanımlı değil.'];
        }

        $baseUrl = rtrim($connection['base_url'], '/');

        try {
            $response = Http::withHeaders([
                'X-ZOLM-API-Key' => $connection['booster_api_key'],
                'Accept' => 'application/json',
            ])->timeout(30)->get($baseUrl.'/wp-json/zolm-booster/v1/products');

            if ($response->successful()) {
                $data = $response->json();
                $products = $data['products'] ?? $data ?? [];

                if (! empty($products) && isset($products[0]['id'])) {
                    return ['products' => $products, 'source' => 'booster_api', 'error' => null];
                }
            }

            return [
                'products' => [],
                'source' => 'booster_api',
                'error' => 'Booster plugin ürün endpoint\'i yanıt vermedi (HTTP '.$response->status().'). WP plugin\'de /products endpoint\'i olmayabilir.',
            ];
        } catch (\Exception $e) {
            return [
                'products' => [],
                'source' => 'booster_api',
                'error' => 'Bağlantı hatası: '.$e->getMessage(),
            ];
        }
    }

    /**
     * WC REST API'den ürün listesi çeker.
     */
    protected function fetchViaWcRestApi(array $connection): array
    {
        $baseUrl = rtrim($connection['base_url'], '/');

        try {
            $allProducts = [];
            $page = 1;
            $perPage = 100;

            do {
                $response = Http::withBasicAuth($connection['consumer_key'], $connection['consumer_secret'])
                    ->timeout(30)
                    ->get($baseUrl.'/wp-json/wc/v3/products', [
                        'per_page' => $perPage,
                        'page' => $page,
                        'status' => 'publish',
                        '_fields' => 'id,name,sku,slug,permalink,images',
                    ]);

                if (! $response->successful()) {
                    return [
                        'products' => $allProducts,
                        'source' => 'wc_rest_api',
                        'error' => 'WC REST API hatası: HTTP '.$response->status(),
                    ];
                }

                $products = $response->json();
                if (empty($products)) {
                    break;
                }

                $allProducts = array_merge($allProducts, $products);
                $page++;
            } while (count($products) === $perPage && $page <= 10);

            return ['products' => $allProducts, 'source' => 'wc_rest_api', 'error' => null];
        } catch (\Exception $e) {
            return ['products' => [], 'source' => 'wc_rest_api', 'error' => $e->getMessage()];
        }
    }

    /**
     * WC Store API'den (herkese açık blok API'si) ürün listesi çeker.
     * API key gerektirmez, varsayılan olarak her modern WC mağazasında açıktır.
     */
    protected function fetchViaWcStoreApi(array $connection): array
    {
        $baseUrl = rtrim($connection['base_url'], '/');

        try {
            $allProducts = [];
            $page = 1;
            $perPage = 100;

            do {
                $response = Http::timeout(30)
                    ->get($baseUrl.'/wp-json/wc/store/v1/products', [
                        'per_page' => $perPage,
                        'page' => $page,
                    ]);

                if (! $response->successful()) {
                    return [
                        'products' => $allProducts,
                        'source' => 'wc_store_api',
                        'error' => 'WC Store API yanıt vermedi (HTTP '.$response->status().')',
                    ];
                }

                $products = $response->json();
                if (empty($products)) {
                    break;
                }

                $allProducts = array_merge($allProducts, $products);
                $page++;
            } while (count($products) === $perPage && $page <= 20);

            return ['products' => $allProducts, 'source' => 'wc_store_api', 'error' => null];
        } catch (\Exception $e) {
            return ['products' => [], 'source' => 'wc_store_api', 'error' => $e->getMessage()];
        }
    }

    /**
     * WC bağlantı bilgilerini getirir.
     */
    protected function getWcConnection(int $userId): ?array
    {
        $store = MarketplaceStore::where('user_id', $userId)
            ->where('marketplace', 'woocommerce')
            ->where('is_active', true)
            ->first();

        if (! $store || ! $store->connection) {
            return null;
        }

        $credentials = $store->connection->credentials_encrypted ?? [];
        $baseUrl = trim((string) ($store->connection->api_base_url ?? $credentials['store_url'] ?? ''));

        if ($baseUrl === '') {
            return null;
        }

        return [
            'store_id' => $store->id,
            'base_url' => $baseUrl,
            'consumer_key' => trim((string) ($credentials['consumer_key'] ?? $credentials['api_key'] ?? '')),
            'consumer_secret' => trim((string) ($credentials['consumer_secret'] ?? $credentials['api_secret'] ?? '')),
            'booster_api_key' => trim((string) ($credentials['zolm_booster_api_key'] ?? $credentials['booster_api_key'] ?? '')),
        ];
    }
}
