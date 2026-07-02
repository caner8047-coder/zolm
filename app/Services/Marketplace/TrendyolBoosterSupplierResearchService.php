<?php

namespace App\Services\Marketplace;

use App\Models\TrendyolBoosterSupplierOffer;
use App\Models\TrendyolBoosterSupplierResearch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TrendyolBoosterSupplierResearchService
{
    protected const VERIFIED_MATCH_SCORE = 85;

    public function __construct(
        protected TrendyolProductPageReader $productReader,
        protected GoogleMarketplaceSearchService $googleSearch,
        protected TrendyolBoosterActivityLogger $activityLogger,
    ) {}

    /** @return array{ok: bool, message: string, research: TrendyolBoosterSupplierResearch} */
    public function researchFromUrl(int $userId, string $sourceUrl): array
    {
        $productResult = $this->productReader->fetch($sourceUrl);
        $page = (array) ($productResult['data'] ?? []);

        if (trim((string) ($page['title'] ?? '')) === '') {
            return [
                'ok' => false,
                'message' => (string) ($productResult['message'] ?? 'Ürün bilgisi okunamadı.'),
                'research' => new TrendyolBoosterSupplierResearch(),
            ];
        }

        $google = $this->googleSearch->search((string) $page['title'], (string) ($page['brand'] ?? ''));

        return $this->capture($userId, $sourceUrl, $page, (array) ($google['offers'] ?? []), [
            'search_query' => $google['query'] ?? '',
            'search_url' => $google['search_url'] ?? '',
            'search_source' => ($google['configured'] ?? false) ? 'google_api' : 'server_fallback',
            'search_message' => $google['message'] ?? '',
        ]);
    }

    /**
     * @param  array<string, mixed>  $page
     * @param  array<int, array<string, mixed>>  $externalOffers
     * @param  array<string, mixed>  $meta
     * @return array{ok: bool, message: string, research: TrendyolBoosterSupplierResearch}
     */
    public function capture(int $userId, string $sourceUrl, array $page, array $externalOffers, array $meta = []): array
    {
        $sourceUrl = $this->normalizeUrl($sourceUrl);
        $title = Str::limit(trim((string) ($page['title'] ?? '')), 500, '');

        if ($sourceUrl === '' || $title === '') {
            return [
                'ok' => false,
                'message' => 'Araştırma için Trendyol ürün linki ve ürün adı gereklidir.',
                'research' => new TrendyolBoosterSupplierResearch(),
            ];
        }

        $page['source_url'] = $page['source_url'] ?? $sourceUrl;

        $scanUuid = (string) Str::uuid();
        $sourceHash = hash('sha256', $sourceUrl);
        $research = TrendyolBoosterSupplierResearch::query()->firstOrNew([
            'user_id' => $userId,
            'source_url_hash' => $sourceHash,
        ]);
        $offers = $this->normalizeOffers($research, $page, $externalOffers, $title, (string) ($page['brand'] ?? ''));
        $summary = $this->summary($offers, $page);

        DB::transaction(function () use ($research, $userId, $sourceUrl, $sourceHash, $page, $title, $scanUuid, $offers, $summary, $meta): void {
            $research->forceFill([
                'source_url' => $sourceUrl,
                'source_url_hash' => $sourceHash,
                'trendyol_product_id' => $this->text($page['trendyol_product_id'] ?? null, 80),
                'title' => $title,
                'brand' => $this->text($page['brand'] ?? null, 120),
                'category_name' => $this->text($page['category_name'] ?? null, 180),
                'image_url' => $this->text($page['image_url'] ?? null, 1000),
                'source_price' => $this->money($page['sale_price'] ?? 0),
                'currency' => $this->text($page['currency'] ?? 'TRY', 8) ?: 'TRY',
                'scan_count' => ((int) $research->scan_count) + 1,
                'platform_count' => $summary['platform_count'],
                'seller_count' => $summary['seller_count'],
                'offer_count' => $offers->count(),
                'verified_offer_count' => $summary['verified_offer_count'],
                'min_price' => $summary['min_price'],
                'median_price' => $summary['median_price'],
                'max_price' => $summary['max_price'],
                'price_spread_percent' => $summary['price_spread_percent'],
                'market_fit_score' => $summary['market_fit_score'],
                'confidence_score' => $summary['confidence_score'],
                'risk_level' => $summary['risk_level'],
                'verdict' => $summary['verdict'],
                'search_query' => $this->text($meta['search_query'] ?? '', 1000),
                'search_url' => $this->text($meta['search_url'] ?? '', 1000),
                'last_scan_uuid' => $scanUuid,
                'raw_payload' => [
                    'page' => $page,
                    'search_source' => $meta['search_source'] ?? 'browser_bridge',
                    'search_message' => $meta['search_message'] ?? '',
                    'searched_platforms' => $meta['searched_platforms'] ?? array_keys($this->googleSearch->platforms()),
                ],
                'is_active' => true,
                'last_checked_at' => now(),
            ])->save();

            foreach ($offers as $index => $offer) {
                TrendyolBoosterSupplierOffer::query()->create($offer + [
                    'trendyol_booster_supplier_research_id' => $research->id,
                    'user_id' => $userId,
                    'scan_uuid' => $scanUuid,
                    'rank' => $offer['rank'] ?: $index + 1,
                    'observed_at' => now(),
                ]);
            }
        });

        $this->activityLogger->log(
            $userId,
            'supplier_research',
            'Tedarikçi Radar',
            $title,
            $summary['platform_count'].' kanal, '.$summary['seller_count'].' satıcı ve '.$summary['verified_offer_count'].' güçlü ürün eşleşmesi bulundu.',
            'teklif',
            $offers->count(),
            ['supplier_research_id' => $research->id, 'scan_uuid' => $scanUuid],
        );

        return [
            'ok' => true,
            'message' => $summary['platform_count'].' kanalda '.$summary['seller_count'].' satıcı bulundu; '.$summary['verified_offer_count'].' teklif akıllı ürün eşleşmesinden geçti.',
            'research' => $research->fresh(['offers' => fn ($query) => $query->where('scan_uuid', $scanUuid)->orderBy('rank')]) ?: $research,
        ];
    }

    /** @return array<string, mixed> */
    public function dashboard(int $userId): array
    {
        $base = TrendyolBoosterSupplierResearch::query()->where('user_id', $userId);
        $latest = (clone $base)->latest('last_checked_at')->first();

        if ($latest) {
            $latest->setRelation('offers', $latest->offers()
                ->where('scan_uuid', $latest->last_scan_uuid)
                ->orderByRaw("CASE WHEN platform = 'trendyol' THEN 0 ELSE 1 END")
                ->orderByDesc('match_score')
                ->orderBy('sale_price')
                ->get());
        }

        $offers = collect($latest?->offers ?? [])
            ->filter(function (TrendyolBoosterSupplierOffer $offer): bool {
                if ($offer->platform !== 'trendyol') {
                    return $this->isVerifiedMatch((int) $offer->match_score)
                        && $this->isShoppingSource((string) $offer->source_type);
                }

                return $this->hasSellerIdentity((string) $offer->seller_name, (string) $offer->seller_id);
            })
            ->values();

        if ($latest) {
            $summary = $this->summary(
                $offers->map(fn (TrendyolBoosterSupplierOffer $offer): array => $offer->toArray()),
                (array) data_get($latest->raw_payload, 'page', []),
            );
            $latest->forceFill([
                'platform_count' => $summary['platform_count'],
                'seller_count' => $summary['seller_count'],
                'offer_count' => $offers->count(),
                'verified_offer_count' => $summary['verified_offer_count'],
                'min_price' => $summary['min_price'],
                'median_price' => $summary['median_price'],
                'max_price' => $summary['max_price'],
                'price_spread_percent' => $summary['price_spread_percent'],
                'market_fit_score' => $summary['market_fit_score'],
                'confidence_score' => $summary['confidence_score'],
                'risk_level' => $summary['risk_level'],
                'verdict' => $summary['verdict'],
            ]);
        }

        $presentPlatforms = $offers->pluck('platform')->unique();
        $coverage = collect($this->googleSearch->platforms())
            ->map(fn (array $platform, string $key): array => [
                'key' => $key,
                'label' => $platform['label'],
                'kind' => $platform['kind'],
                'found' => $presentPlatforms->contains($key),
                'offer_count' => $offers->where('platform', $key)->count(),
            ])->values();

        return [
            'total' => (clone $base)->count(),
            'latest' => $latest,
            'offers' => $offers,
            'trendyol_offers' => $offers->where('platform', 'trendyol')->values(),
            'external_offers' => $offers->where('platform', '!=', 'trendyol')->values(),
            'coverage' => $coverage,
            'covered_platforms' => $coverage->where('found', true)->count(),
            'target_platforms' => $coverage->count(),
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    protected function normalizeOffers(TrendyolBoosterSupplierResearch $research, array $page, array $externalOffers, string $title, string $brand): Collection
    {
        $trendyolOffers = collect((array) ($page['sellers'] ?? []))
            ->filter(function (mixed $seller) use ($page): bool {
                $seller = is_array($seller) ? $seller : [];

                return $this->hasSellerIdentity(
                    (string) ($seller['seller_name'] ?? $page['seller_name'] ?? ''),
                    (string) ($seller['seller_id'] ?? $page['seller_id'] ?? ''),
                );
            })
            ->map(function (mixed $seller, int $index) use ($page, $title): array {
                $seller = is_array($seller) ? $seller : [];

                return [
                    'platform' => 'trendyol',
                    'platform_label' => 'Trendyol',
                    'seller_name' => $seller['seller_name'] ?? $page['seller_name'] ?? '',
                    'seller_id' => $seller['seller_id'] ?? $page['seller_id'] ?? '',
                    'external_product_id' => $page['trendyol_product_id'] ?? '',
                    'title' => $title,
                    'source_url' => $page['source_url'] ?? '',
                    'sale_price' => $seller['sale_price'] ?? $page['sale_price'] ?? 0,
                    'stock' => $seller['stock'] ?? null,
                    'availability' => $page['stock_status'] ?? 'unknown',
                    'match_score' => 100,
                    'match_status' => 'verified',
                    'source_type' => 'trendyol_product',
                    'rank' => $index + 1,
                    'shipping_note' => $seller['shipping_note'] ?? '',
                    'seller_score' => $seller['seller_score'] ?? null,
                ];
            });

        if ($trendyolOffers->isEmpty() && $this->hasSellerIdentity(
            (string) ($page['seller_name'] ?? ''),
            (string) ($page['seller_id'] ?? ''),
        )) {
            $trendyolOffers->push([
                'platform' => 'trendyol',
                'platform_label' => 'Trendyol',
                'seller_name' => $page['seller_name'] ?? '',
                'seller_id' => $page['seller_id'] ?? '',
                'external_product_id' => $page['trendyol_product_id'] ?? '',
                'title' => $title,
                'source_url' => $page['source_url'] ?? '',
                'sale_price' => $page['sale_price'] ?? 0,
                'stock' => $page['total_stock'] ?? null,
                'availability' => $page['stock_status'] ?? 'unknown',
                'match_score' => 100,
                'match_status' => 'verified',
                'source_type' => 'trendyol_product',
                'rank' => 1,
            ]);
        }

        return $trendyolOffers
            ->concat(collect($externalOffers)->filter(fn (mixed $offer): bool => is_array($offer)))
            ->map(function (array $offer, int $index) use ($research, $title, $brand): array {
                $url = $this->normalizeUrl((string) ($offer['source_url'] ?? $offer['url'] ?? ''));
                $platform = $this->googleSearch->detectPlatform($url);
                $platformKey = $this->text($offer['platform'] ?? $platform['key'], 50) ?: $platform['key'];
                $platformLabel = $this->text($offer['platform_label'] ?? $platform['label'], 100) ?: $platform['label'];
                $offerTitle = $this->text($offer['title'] ?? '', 500) ?: $title;
                $sellerName = $this->text($offer['seller_name'] ?? '', 180);
                $sellerId = $this->text($offer['seller_id'] ?? '', 80);
                $externalId = $this->text($offer['external_product_id'] ?? '', 120);
                $salePrice = $this->money($offer['sale_price'] ?? $offer['price'] ?? 0);
                $offerKey = hash('sha256', implode('|', [
                    $platformKey,
                    Str::lower($sellerId ?: $sellerName),
                    $externalId ?: $url,
                    $platformKey === 'trendyol' ? '' : Str::lower($offerTitle),
                ]));
                $previous = $research->exists
                    ? $research->offers()->where('offer_key', $offerKey)->latest('observed_at')->first()
                    : null;
                $stock = is_numeric($offer['stock'] ?? null) ? max(0, (int) $offer['stock']) : null;
                $matchScore = $platformKey === 'trendyol' ? 100 : $this->matchScore($title, $brand, $offerTitle);
                $matchStatus = $this->isVerifiedMatch($matchScore) ? 'verified' : 'review';

                return [
                    'offer_key' => $offerKey,
                    'platform' => $platformKey,
                    'platform_label' => $platformLabel,
                    'seller_name' => $sellerName,
                    'seller_id' => $sellerId,
                    'external_product_id' => $externalId,
                    'title' => $offerTitle,
                    'source_url' => $url,
                    'source_url_hash' => hash('sha256', $url),
                    'sale_price' => $salePrice,
                    'previous_sale_price' => $previous?->sale_price,
                    'price_delta' => $previous?->sale_price !== null ? round($salePrice - (float) $previous->sale_price, 2) : 0,
                    'stock' => $stock,
                    'previous_stock' => $previous?->stock,
                    'estimated_sales' => $previous?->stock !== null && $stock !== null ? max(0, (int) $previous->stock - $stock) : 0,
                    'availability' => $this->availability($offer['availability'] ?? null, $stock),
                    'match_score' => $matchScore,
                    'match_status' => $matchStatus,
                    'source_type' => $this->text($offer['source_type'] ?? ($platformKey === 'trendyol' ? 'trendyol_product' : 'google'), 40),
                    'rank' => max(1, (int) ($offer['rank'] ?? $index + 1)),
                    'raw_payload' => $offer,
                ];
            })
            ->filter(fn (array $offer): bool => $offer['source_url'] !== '' && $offer['title'] !== '')
            ->filter(fn (array $offer): bool => $offer['platform'] === 'trendyol' || (
                $this->isVerifiedMatch((int) $offer['match_score']) && $this->isShoppingSource((string) $offer['source_type'])
            ))
            ->unique('offer_key')
            ->take(50)
            ->values();
    }

    /** @return array<string, mixed> */
    protected function summary(Collection $offers, array $page): array
    {
        $verified = $offers->where('match_status', 'verified');
        $priced = $offers->filter(fn (array $offer): bool => $this->isVerifiedMatch((int) $offer['match_score']) && (float) $offer['sale_price'] > 0)
            ->pluck('sale_price')->map(fn ($price): float => (float) $price)->sort()->values();
        $min = $priced->isNotEmpty() ? (float) $priced->first() : null;
        $max = $priced->isNotEmpty() ? (float) $priced->last() : null;
        $median = $this->median($priced);
        $spread = $min && $max ? round((($max - $min) / $min) * 100, 2) : null;
        $platformCount = $verified->pluck('platform')->unique()->count();
        $sellerCount = $verified->map(fn (array $offer): string => $offer['platform'].'|'.($offer['seller_id'] ?: $offer['seller_name'] ?: $offer['source_url']))->unique()->count();
        $confidence = min(100, 20 + ($verified->count() * 8) + ($platformCount * 7));
        $fit = 45;
        $fit += min(15, (int) floor(max(0, (int) ($page['favorite_count'] ?? 0)) / 5000));
        $fit += min(10, (int) floor(max(0, (int) ($page['review_count'] ?? 0)) / 100));
        $fit += min(12, $platformCount * 3);
        $fit += $spread !== null && $spread >= 12 ? 8 : 0;
        $fit -= $sellerCount >= 10 ? 12 : ($sellerCount >= 6 ? 6 : 0);
        $fit = max(0, min(100, $fit));
        $risk = $confidence < 45 ? 'unknown' : ($sellerCount >= 10 || ($spread !== null && $spread < 5 && $platformCount >= 4) ? 'high' : ($sellerCount >= 6 ? 'medium' : 'low'));
        $verdict = $confidence < 45
            ? 'Karar için veri birikiyor; dış pazar eşleşmelerini doğrulayın ve ikinci taramayı alın.'
            : ($fit >= 70
                ? 'Talep ve kanal yayılımı güçlü. Marjı doğrulayıp kontrollü test stoğuyla ilerlemeye aday.'
                : ($risk === 'high'
                    ? 'Ürün görünür fakat satıcı yoğunluğu yüksek; fiyat savaşı ve marj baskısı riski var.'
                    : 'Pazar sinyali dengeli. Tedarik maliyeti ve ikinci taramadaki stok erimesi kararı netleştirir.'));

        return [
            'platform_count' => $platformCount,
            'seller_count' => $sellerCount,
            'verified_offer_count' => $verified->count(),
            'min_price' => $min,
            'median_price' => $median,
            'max_price' => $max,
            'price_spread_percent' => $spread,
            'market_fit_score' => $fit,
            'confidence_score' => $confidence,
            'risk_level' => $risk,
            'verdict' => $verdict,
        ];
    }

    protected function matchScore(string $sourceTitle, string $brand, string $candidateTitle): int
    {
        $sourceCanonical = $this->canonicalProductName($sourceTitle, $brand);
        $candidateCanonical = $this->canonicalProductName($candidateTitle, $brand);

        if ($sourceCanonical === '' || $candidateCanonical === '') {
            return 0;
        }

        if ($sourceCanonical === $candidateCanonical) {
            return 100;
        }

        $source = $this->productSignature($sourceTitle, $brand);
        $candidate = $this->productSignature($candidateTitle, $brand);

        if ($source['tokens'] === [] || $candidate['tokens'] === []) {
            return 0;
        }

        $sourceModels = $source['model_codes'];
        $candidateModels = $candidate['model_codes'];
        $matchedModels = array_values(array_intersect($sourceModels, $candidateModels));
        $hasModel = $sourceModels !== [];

        // Model kodu varsa ürün kimliğinin kilidi odur. KTF-345 gibi kodlar
        // farklıysa aynı marka/kategori bile olsa güvenli eşleşme saymıyoruz.
        if ($hasModel && $matchedModels === []) {
            return 0;
        }

        if ($hasModel && $candidateModels !== []) {
            $conflictingModels = array_values(array_diff($candidateModels, $sourceModels));
            if ($conflictingModels !== [] && count($matchedModels) < count($sourceModels)) {
                return 0;
            }
        }

        $brandScore = 0;
        if ($source['brand'] !== '') {
            $brandScore = $candidate['has_brand'] ? 15 : ($matchedModels !== [] ? 8 : 0);
            if ($brandScore === 0 && ! $hasModel) {
                return 0;
            }
        }

        $modelScore = $hasModel ? (int) round(30 + (10 * count($matchedModels) / max(1, count($sourceModels)))) : 0;

        $unitRatio = $this->overlapRatio($source['unit_features'], $candidate['unit_features']);
        if (! $hasModel && $source['unit_features'] !== [] && $unitRatio < 0.45) {
            return 0;
        }
        $unitScore = (int) round(20 * $unitRatio);

        $tokenRatio = $this->overlapRatio($source['tokens'], $candidate['tokens']);
        if (! $hasModel && $tokenRatio < 0.72) {
            return 0;
        }
        $tokenScore = (int) round(25 * $tokenRatio);

        $score = $brandScore + $modelScore + $unitScore + $tokenScore;

        if ($hasModel && $matchedModels !== [] && $tokenRatio >= 0.35) {
            $score = max($score, 88);
        }

        if (! $hasModel && $brandScore > 0 && $tokenRatio >= 0.9 && ($source['unit_features'] === [] || $unitRatio >= 0.6)) {
            $score = max($score, 92);
        }

        return max(0, min(99, $score));
    }

    protected function canonicalProductName(string $title, string $brand): string
    {
        $title = $this->normalizeIdentity($title);
        $brand = $this->normalizeIdentity($brand);

        if ($brand !== '' && ! preg_match('/(?:^| )'.preg_quote($brand, '/').'(?: |$)/u', $title)) {
            $title = trim($brand.' '.$title);
        }

        return $title;
    }

    protected function normalizeIdentity(string $value): string
    {
        $value = Str::lower(Str::ascii($value));
        $value = str_replace('&', ' ve ', $value);
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?: '';

        return trim(preg_replace('/\s+/u', ' ', $value) ?: '');
    }

    /** @return array{brand: string, has_brand: bool, tokens: array<int, string>, model_codes: array<int, string>, unit_features: array<int, string>} */
    protected function productSignature(string $title, string $brand): array
    {
        $normalizedTitle = $this->canonicalProductName($title, $brand);
        $normalizedBrand = $this->normalizeIdentity($brand);
        $tokens = collect(explode(' ', $normalizedTitle))
            ->filter(fn (string $token): bool => $token !== '' && ! $this->isWeakProductToken($token) && $token !== $normalizedBrand)
            ->unique()
            ->values()
            ->all();

        return [
            'brand' => $normalizedBrand,
            'has_brand' => $normalizedBrand === '' || preg_match('/(?:^| )'.preg_quote($normalizedBrand, '/').'(?: |$)/u', $this->normalizeIdentity($title)) === 1,
            'tokens' => $tokens,
            'model_codes' => $this->modelCodes($title),
            'unit_features' => $this->unitFeatures($title),
        ];
    }

    /** @return array<int, string> */
    protected function modelCodes(string $title): array
    {
        $normalized = Str::upper(Str::ascii($title));
        preg_match_all('/\b[A-Z]{1,8}\s?\-?\s?\d{2,}[A-Z0-9]*(?:\-[A-Z0-9]{1,8})*\b/u', $normalized, $matches);

        return collect($matches[0] ?? [])
            ->map(fn (string $code): string => preg_replace('/[^A-Z0-9]+/u', '', $code) ?: '')
            ->filter(fn (string $code): bool => $code !== '' && ! preg_match('/^(CM|MM|KG|GR|LT|ML|W|V)\d+$/u', $code))
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    protected function unitFeatures(string $title): array
    {
        $normalized = $this->normalizeIdentity($title);
        $patterns = [
            '/\b(\d{1,3})\s*(?:inc|inch)\b/u' => 'inc',
            '/\b(\d{1,4})\s*(?:w|watt)\b/u' => 'w',
            '/\b(\d{1,4})\s*(?:cm|santim|santimetre)\b/u' => 'cm',
            '/\b(\d{1,3})\s*(?:kg|kilo)\b/u' => 'kg',
            '/\b(\d{1,2})\s*(?:pervane|pervaneli|kanat)\b/u' => 'pervane',
            '/\b(\d{1,2})\s*(?:kademe|kademeli|hiz|hizli)\b/u' => 'kademe',
        ];

        $features = [];
        foreach ($patterns as $pattern => $label) {
            if (preg_match_all($pattern, $normalized, $matches)) {
                foreach ($matches[1] as $value) {
                    $features[] = $label.':'.(int) $value;
                }
            }
        }

        return array_values(array_unique($features));
    }

    protected function overlapRatio(array $source, array $candidate): float
    {
        if ($source === []) {
            return 1.0;
        }

        return count(array_intersect($source, $candidate)) / max(1, count($source));
    }

    protected function isWeakProductToken(string $token): bool
    {
        return preg_match('/^\d+$/u', $token) === 1
            || in_array($token, [
                'urun', 'urunu', 'urunler', 'model', 'modelleri', 'fiyat', 'fiyati', 'fiyatlari',
                'en', 'ucuz', 'kampanya', 'kampanyali', 'indirim', 'indirimli', 'satici', 'magaza',
                'tl', 'try', 'adet', 'set', 'icin', 'ile', 've', 'veya', 'cm', 'mm', 'kg', 'gr',
                'w', 'watt', 'inc', 'inch',
            ], true);
    }

    protected function isVerifiedMatch(int $matchScore): bool
    {
        return $matchScore >= self::VERIFIED_MATCH_SCORE;
    }

    protected function hasSellerIdentity(string $sellerName, string $sellerId): bool
    {
        if (trim($sellerId) !== '') {
            return true;
        }

        $sellerName = $this->normalizeIdentity($sellerName);

        return $sellerName !== '' && ! in_array($sellerName, ['trendyol saticisi', 'ana satici', 'satici'], true);
    }

    protected function isShoppingSource(string $sourceType): bool
    {
        return in_array($sourceType, ['google_shopping', 'google_site_search', 'google_api'], true);
    }

    protected function median(Collection $values): ?float
    {
        $count = $values->count();
        if ($count === 0) {
            return null;
        }

        $middle = intdiv($count, 2);

        return $count % 2 === 1
            ? round((float) $values[$middle], 2)
            : round(((float) $values[$middle - 1] + (float) $values[$middle]) / 2, 2);
    }

    protected function availability(mixed $value, ?int $stock): string
    {
        if ($stock !== null) {
            return $stock > 0 ? 'in_stock' : 'out_of_stock';
        }

        $value = Str::lower((string) $value);
        if (str_contains($value, 'in_stock') || str_contains($value, 'instock') || str_contains($value, 'stokta')) {
            return 'in_stock';
        }
        if (str_contains($value, 'out_of_stock') || str_contains($value, 'outofstock') || str_contains($value, 'tükendi')) {
            return 'out_of_stock';
        }

        return 'unknown';
    }

    protected function normalizeUrl(string $url): string
    {
        $url = trim(preg_replace('/\s+/u', '', $url) ?: '');

        return Str::limit($url, 1000, '');
    }

    protected function text(mixed $value, int $limit): string
    {
        return Str::limit(trim((string) ($value ?? '')), $limit, '');
    }

    protected function money(mixed $value): float
    {
        if (is_string($value)) {
            $value = preg_replace('/[^\d,.\-]/u', '', $value) ?: '0';
            $lastComma = strrpos($value, ',');
            $lastDot = strrpos($value, '.');
            $value = $lastComma !== false && ($lastDot === false || $lastComma > $lastDot)
                ? str_replace(',', '.', str_replace('.', '', $value))
                : str_replace(',', '', $value);
        }

        return round(max(0, min(9_999_999.99, (float) $value)), 2);
    }
}
