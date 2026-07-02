<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TrendyolProductPageReader
{
    /**
     * @return array{ok: bool, message: string, data: array<string, mixed>}
     */
    public function fetch(string $url): array
    {
        $url = $this->normalizeUrl($url);

        if (! $this->isAllowedUrl($url)) {
            return $this->failure('Geçerli bir Trendyol ürün linki girin.', $url);
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; ZOLM-Trendyol-Booster/1.0)',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'tr-TR,tr;q=0.9,en;q=0.6',
            ])
                ->timeout((int) config('marketplace.trendyol_booster.request_timeout', 12))
                ->retry(
                    (int) config('marketplace.trendyol_booster.request_retries', 1),
                    (int) config('marketplace.trendyol_booster.request_retry_delay_ms', 250)
                )
                ->get($url);
        } catch (\Throwable) {
            return $this->failure('Trendyol sayfası şu anda okunamadı. Linki kontrol edip tekrar deneyin.', $url);
        }

        if (! $response->successful()) {
            $fallbackData = $this->extractUrlData($url);

            if ($this->hasProductData($fallbackData) || $fallbackData['trendyol_product_id'] !== null) {
                return [
                    'ok' => true,
                    'message' => 'Trendyol sayfası erişimi sınırladı; linkten temel bilgiler alındı. Fiyatı manuel girebilirsiniz.',
                    'data' => $fallbackData,
                ];
            }

            return $this->failure('Trendyol sayfası okunamadı. HTTP durum kodu: '.$response->status(), $url);
        }

        $data = $this->parse($response->body(), $url);

        if (! $this->hasProductData($data)) {
            return $this->failure('Bu linkten ürün bilgisi çıkarılamadı. Başlık ve fiyatı manuel girebilirsiniz.', $url, $data);
        }

        return [
            'ok' => true,
            'message' => 'Ürün bilgileri getirildi.',
            'data' => $data,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function parse(string $html, string $url): array
    {
        $url = $this->normalizeUrl($url);
        $data = $this->emptyData($url);

        foreach ([
            $this->extractFallbackData($html),
            $this->extractMetaData($html),
            $this->extractJsonLdData($html),
            $this->extractEnvoySharedPropsData($html),
            $this->extractVisibleSellerLegalData($html),
        ] as $candidate) {
            $data = $this->mergeProductData($data, $candidate);
        }

        if ($data['trendyol_product_id'] === null) {
            $data['trendyol_product_id'] = $this->extractProductId($html);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyData(string $url): array
    {
        return [
            'source_url' => $url,
            'trendyol_product_id' => $this->extractProductId($url),
            'title' => '',
            'brand' => '',
            'category_name' => '',
            'sale_price' => 0.0,
            'currency' => 'TRY',
            'image_url' => '',
            'availability' => '',
            'stock_status' => 'unknown',
            'barcode' => '',
            'total_stock' => null,
            'sellers' => [],
            'stock_source' => '',
            'evaluation_count' => null,
            'review_count' => null,
            'average_rating' => null,
            'favorite_count' => null,
            'favorite_precision' => null,
            'question_count' => null,
            'category_rank' => null,
            'seller_score' => null,
            'seller_follower_count' => null,
            'seller_legal' => $this->emptySellerLegalDetails(),
            'seller_title' => '',
            'seller_address' => '',
            'seller_kep' => '',
            'seller_tax_number' => '',
            'seller_tax_office' => '',
            'seller_phone' => '',
            'campaign_count' => null,
            'campaign_signature' => null,
            'promotions' => [],
            'listing_id' => '',
            'item_number' => '',
            'product_group_id' => '',
            'product_code' => '',
            'max_installment' => null,
            'max_sale_limit' => null,
            'rush_delivery_duration' => null,
            'attributes' => [],
            'image_count' => null,
            'data_sources' => [],
        ];
    }

    /**
     * Trendyol, seçili satıcı ve gerçek stok adedini sayfadaki Envoy state içinde yayınlar.
     *
     * @return array<string, mixed>
     */
    protected function extractEnvoySharedPropsData(string $html): array
    {
        $state = $this->extractEnvoySharedProps($html);
        $product = is_array($state['product'] ?? null) ? $state['product'] : [];

        if ($product === []) {
            return [];
        }

        $listing = is_array($product['merchantListing'] ?? null) ? $product['merchantListing'] : [];
        $merchant = is_array($listing['merchant'] ?? null) ? $listing['merchant'] : [];
        $winner = is_array($listing['winnerVariant'] ?? null) ? $listing['winnerVariant'] : [];
        $price = is_array($winner['price'] ?? null) ? $winner['price'] : [];
        $brand = is_array($product['brand'] ?? null) ? $product['brand'] : [];
        $category = is_array($product['category'] ?? null) ? $product['category'] : [];
        $images = is_array($product['images'] ?? null) ? $product['images'] : [];
        $ratingScore = is_array($product['ratingScore'] ?? null) ? $product['ratingScore'] : [];
        $categoryRankings = is_array($product['categoryTopRankings'] ?? null) ? $product['categoryTopRankings'] : [];
        $attributes = collect(is_array($product['attributes'] ?? null) ? $product['attributes'] : [])
            ->map(fn (array $attribute): array => [
                'name' => $this->cleanText(data_get($attribute, 'key.name', '')),
                'value' => $this->cleanText(data_get($attribute, 'value.name', '')),
            ])
            ->filter(fn (array $attribute): bool => $attribute['name'] !== '' && $attribute['value'] !== '')
            ->values()
            ->all();
        $promotions = collect(is_array($listing['promotions'] ?? null) ? $listing['promotions'] : [])
            ->pluck('name')
            ->filter()
            ->map(fn ($name): string => $this->cleanText($name))
            ->values();
        $categoryRank = collect($categoryRankings)
            ->first(fn ($rank): bool => is_array($rank) && is_numeric($rank['order'] ?? null));
        $quantity = is_numeric($winner['quantity'] ?? null) ? max(0, (int) $winner['quantity']) : null;
        $salePrice = $this->money(
            data_get($price, 'discountedPriceAfterNoLimitPromotions.value')
            ?? data_get($price, 'discountedPrice.value')
            ?? data_get($price, 'couponApplicablePrice.value')
            ?? data_get($price, 'tyPlusCouponApplicablePrice.value')
            ?? data_get($price, 'sellingPrice.value')
            ?? data_get($price, 'originalPrice.value')
            ?? 0
        );
        $sellerName = $this->cleanText($merchant['name'] ?? '');
        $sellerScore = data_get($merchant, 'sellerScore.value');
        $sellerLevel = data_get($merchant, 'sellerLevel.level')
            ?? data_get($merchant, 'sellerLevel')
            ?? data_get($merchant, 'sellerTier.level')
            ?? data_get($merchant, 'sellerTier');
        $sellerLevel = is_numeric($sellerLevel) && (int) $sellerLevel >= 1 && (int) $sellerLevel <= 5
            ? (int) $sellerLevel
            : null;
        $stockStatus = ($winner['inStock'] ?? $product['inStock'] ?? null) === true
            ? 'in_stock'
            : (($winner['inStock'] ?? $product['inStock'] ?? null) === false ? 'out_of_stock' : 'unknown');
        $sellers = $this->extractSellersFromProduct($product, $salePrice);

        return [
            'trendyol_product_id' => isset($product['id']) ? (string) $product['id'] : null,
            'title' => $this->cleanTitle($product['name'] ?? ''),
            'brand' => $this->cleanText($brand['name'] ?? ''),
            'category_name' => $this->cleanText($category['name'] ?? ''),
            'sale_price' => $salePrice,
            'currency' => $this->currency($price['currency'] ?? 'TRY'),
            'image_url' => $this->cleanUrl($images[0] ?? ''),
            'availability' => $stockStatus,
            'stock_status' => $stockStatus,
            'barcode' => $this->cleanText($winner['barcode'] ?? ''),
            'total_stock' => $quantity,
            'sellers' => $sellers,
            'stock_source' => 'envoy_shared_props',
            'evaluation_count' => is_numeric($ratingScore['totalCount'] ?? null) ? max(0, (int) $ratingScore['totalCount']) : null,
            'review_count' => is_numeric($ratingScore['commentCount'] ?? null) ? max(0, (int) $ratingScore['commentCount']) : null,
            'average_rating' => is_numeric($ratingScore['averageRating'] ?? null) ? max(0, min(5, (float) $ratingScore['averageRating'])) : null,
            'favorite_count' => is_numeric($product['favoriteCount'] ?? null) ? max(0, (int) $product['favoriteCount']) : null,
            'favorite_precision' => is_numeric($product['favoriteCount'] ?? null) ? 'exact' : null,
            'question_count' => is_numeric($product['questionCount'] ?? $product['sellerQuestionCount'] ?? null)
                ? max(0, (int) ($product['questionCount'] ?? $product['sellerQuestionCount']))
                : null,
            'category_rank' => is_array($categoryRank) ? max(1, (int) $categoryRank['order']) : null,
            'seller_score' => is_numeric($sellerScore) ? (float) $sellerScore : null,
            'seller_id' => isset($merchant['id']) ? (string) $merchant['id'] : null,
            'seller_level' => $sellerLevel,
            'seller_follower_count' => is_numeric($merchant['followerCount'] ?? null) ? max(0, (int) $merchant['followerCount']) : null,
            'campaign_count' => $promotions->count(),
            'campaign_signature' => $promotions->isNotEmpty() ? hash('sha256', $promotions->sort()->implode('|')) : null,
            'promotions' => $promotions->all(),
            'listing_id' => $this->cleanText($winner['listingId'] ?? ''),
            'item_number' => isset($winner['itemNumber']) ? (string) $winner['itemNumber'] : '',
            'product_group_id' => isset($product['productGroupId']) ? (string) $product['productGroupId'] : '',
            'product_code' => $this->cleanText($product['productCode'] ?? ''),
            'max_installment' => is_numeric($product['maxInstallment'] ?? null) ? max(0, (int) $product['maxInstallment']) : null,
            'max_sale_limit' => is_numeric($winner['maxSaleLimit'] ?? null) ? max(0, (int) $winner['maxSaleLimit']) : null,
            'rush_delivery_duration' => is_numeric($winner['rushDeliveryDuration'] ?? null) ? max(0, (int) $winner['rushDeliveryDuration']) : null,
            'attributes' => $attributes,
            'image_count' => count($images),
            'data_sources' => ['envoy_shared_props'],
        ];
    }

    /**
     * Trendyol farklı sürümlerde diğer teklifleri farklı anahtarların altında yayınlıyor.
     * Merchant + winnerVariant çifti taşıyan tüm listing düğümlerini güvenli biçimde toplar.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function extractSellersFromProduct(array $product, float $defaultPrice): array
    {
        $sellers = [];
        $walk = function (mixed $node) use (&$walk, &$sellers, $defaultPrice): void {
            if (! is_array($node)) {
                return;
            }

            $merchant = is_array($node['merchant'] ?? null) ? $node['merchant'] : [];
            $winner = is_array($node['winnerVariant'] ?? null) ? $node['winnerVariant'] : [];

            if ($merchant !== [] && $winner !== [] && (isset($merchant['id']) || filled($merchant['name'] ?? null))) {
                $sellerId = isset($merchant['id']) ? (string) $merchant['id'] : '';
                $sellerName = $this->cleanText($merchant['name'] ?? '') ?: 'Ana satıcı';
                $key = $sellerId !== '' ? 'id:'.$sellerId : 'name:'.Str::lower($sellerName);
                $quantity = is_numeric($winner['quantity'] ?? null) ? max(0, (int) $winner['quantity']) : null;

                if (! isset($sellers[$key])) {
                    $sellers[$key] = [
                        'seller_name' => $sellerName,
                        'seller_id' => $sellerId,
                        'stock' => $quantity,
                        'sale_price' => $this->money(is_array($winner['price'] ?? null) ? (
                            data_get($winner, 'price.discountedPriceAfterNoLimitPromotions.value')
                            ?? data_get($winner, 'price.discountedPrice.value')
                            ?? data_get($winner, 'price.couponApplicablePrice.value')
                            ?? data_get($winner, 'price.sellingPrice.value')
                            ?? data_get($winner, 'price.originalPrice.value')
                            ?? $defaultPrice
                        ) : $defaultPrice),
                        'seller_score' => is_numeric(data_get($merchant, 'sellerScore.value'))
                            ? (float) data_get($merchant, 'sellerScore.value')
                            : null,
                        'shipping_note' => is_numeric($winner['rushDeliveryDuration'] ?? null)
                            ? max(0, (int) $winner['rushDeliveryDuration']).' saatte kargo'
                            : '',
                    ];
                }
            }

            foreach ($node as $child) {
                if (is_array($child) && count($sellers) < 20) {
                    $walk($child);
                }
            }
        };

        $walk($product);

        return array_values($sellers);
    }

    /**
     * @return array<string, mixed>
     */
    protected function extractEnvoySharedProps(string $html): array
    {
        if (! preg_match_all('/<script\b[^>]*>(.*?)<\/script>/isu', $html, $matches)) {
            return [];
        }

        foreach ($matches[1] as $script) {
            $script = trim(html_entity_decode((string) $script, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            if (! preg_match('/window\[\s*["\']__envoy__SHARED_PROPS["\']\s*\]\s*=\s*/u', $script, $assignment, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            $offset = $assignment[0][1] + strlen($assignment[0][0]);
            $json = preg_replace('/;\s*$/u', '', trim(substr($script, $offset))) ?: '';
            $decoded = json_decode($json, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function extractUrlData(string $url): array
    {
        $data = $this->emptyData($url);
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $segments = array_values(array_filter(explode('/', $path)));
        $slug = (string) end($segments);

        if ($slug !== '') {
            $titleSlug = preg_replace('/-p-\d+.*$/iu', '', $slug) ?: $slug;
            $data['title'] = $this->titleFromSlug($titleSlug);
        }

        if (count($segments) >= 2) {
            $data['brand'] = $this->titleFromSlug((string) $segments[0]);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function extractJsonLdData(string $html): array
    {
        if (! preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/isu', $html, $matches)) {
            return [];
        }

        foreach ($matches[1] as $script) {
            $json = trim(html_entity_decode((string) $script, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $json = preg_replace('/^\s*<!--|-->\s*$/u', '', $json) ?: $json;
            $decoded = json_decode($json, true);

            if (! is_array($decoded)) {
                continue;
            }

            foreach ($this->flattenJsonLd($decoded) as $node) {
                if (! $this->looksLikeProductNode($node)) {
                    continue;
                }

                $offers = $this->firstAssoc($node['offers'] ?? []);

                $availability = $this->cleanText($offers['availability'] ?? '');

                return [
                    'title' => $this->cleanText($node['name'] ?? ''),
                    'brand' => $this->extractNamedValue($node['brand'] ?? ''),
                    'category_name' => $this->extractCategory($node['category'] ?? ''),
                    'sale_price' => $this->money($offers['price'] ?? $offers['lowPrice'] ?? $offers['highPrice'] ?? 0),
                    'currency' => $this->currency($offers['priceCurrency'] ?? ''),
                    'image_url' => $this->extractImage($node['image'] ?? ''),
                    'availability' => $availability,
                    'stock_status' => $this->stockStatus($availability),
                ];
            }
        }

        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function flattenJsonLd(array $payload): array
    {
        $nodes = [];

        if ($this->isList($payload)) {
            foreach ($payload as $item) {
                if (is_array($item)) {
                    $nodes = array_merge($nodes, $this->flattenJsonLd($item));
                }
            }

            return $nodes;
        }

        $nodes[] = $payload;

        if (isset($payload['@graph']) && is_array($payload['@graph'])) {
            foreach ($payload['@graph'] as $item) {
                if (is_array($item)) {
                    $nodes = array_merge($nodes, $this->flattenJsonLd($item));
                }
            }
        }

        return $nodes;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    protected function looksLikeProductNode(array $node): bool
    {
        $type = $node['@type'] ?? '';
        $types = is_array($type) ? $type : [$type];

        foreach ($types as $candidate) {
            if (Str::lower((string) $candidate) === 'product') {
                return true;
            }
        }

        return isset($node['name'], $node['offers']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function extractMetaData(string $html): array
    {
        $meta = $this->metaMap($html);
        $title = $meta['og:title'] ?? $meta['twitter:title'] ?? $this->titleTag($html);

        $availability = $this->cleanText($meta['product:availability'] ?? $meta['availability'] ?? '');

        return [
            'title' => $this->cleanTitle($title ?? ''),
            'brand' => $this->cleanText($meta['product:brand'] ?? $meta['brand'] ?? ''),
            'category_name' => $this->cleanText($meta['product:category'] ?? $meta['category'] ?? ''),
            'sale_price' => $this->money($meta['product:price:amount'] ?? $meta['price'] ?? 0),
            'currency' => $this->currency($meta['product:price:currency'] ?? $meta['currency'] ?? ''),
            'image_url' => $this->cleanUrl($meta['og:image'] ?? $meta['twitter:image'] ?? ''),
            'availability' => $availability,
            'stock_status' => $this->stockStatus($availability),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function metaMap(string $html): array
    {
        $map = [];
        $patterns = [
            '/<meta\s+[^>]*(?:property|name)=["\']([^"\']+)["\'][^>]*content=["\']([^"\']*)["\'][^>]*>/isu',
            '/<meta\s+[^>]*content=["\']([^"\']*)["\'][^>]*(?:property|name)=["\']([^"\']+)["\'][^>]*>/isu',
        ];

        foreach ($patterns as $index => $pattern) {
            if (! preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
                continue;
            }

            foreach ($matches as $match) {
                $name = $index === 0 ? $match[1] : $match[2];
                $content = $index === 0 ? $match[2] : $match[1];
                $map[Str::lower(trim((string) $name))] = html_entity_decode(trim((string) $content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        return $map;
    }

    /**
     * @return array<string, mixed>
     */
    protected function extractFallbackData(string $html): array
    {
        $availability = $this->cleanText($this->jsonStringValue($html, ['availability', 'stockStatus']));

        return [
            'title' => $this->cleanTitle($this->jsonStringValue($html, ['productName', 'name', 'title'])),
            'brand' => $this->cleanText($this->jsonStringValue($html, ['brandName', 'brand'])),
            'category_name' => $this->cleanText($this->jsonStringValue($html, ['categoryName', 'category'])),
            'sale_price' => $this->money($this->jsonNumberValue($html, ['sellingPrice', 'salePrice', 'discountedPrice', 'price'])),
            'currency' => $this->currency($this->jsonStringValue($html, ['priceCurrency', 'currency'])),
            'image_url' => $this->cleanUrl($this->jsonStringValue($html, ['imageUrl', 'image'])),
            'availability' => $availability,
            'stock_status' => $this->stockStatus($availability),
        ];
    }

    /**
     * @param  array<int, string>  $keys
     */
    protected function jsonStringValue(string $html, array $keys): string
    {
        foreach ($keys as $key) {
            if (preg_match('/"'.preg_quote($key, '/').'"\s*:\s*"((?:\\\\.|[^"\\\\])*)"/isu', $html, $match)) {
                $decoded = json_decode('"'.$match[1].'"');

                if (is_string($decoded) && trim($decoded) !== '') {
                    return $decoded;
                }
            }

            if ($key === 'brand' && preg_match('/"brand"\s*:\s*\{[^}]*"name"\s*:\s*"((?:\\\\.|[^"\\\\])*)"/isu', $html, $match)) {
                $decoded = json_decode('"'.$match[1].'"');

                if (is_string($decoded) && trim($decoded) !== '') {
                    return $decoded;
                }
            }
        }

        return '';
    }

    /**
     * @param  array<int, string>  $keys
     */
    protected function jsonNumberValue(string $html, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (preg_match('/"'.preg_quote($key, '/').'"\s*:\s*"?([0-9]+(?:[.,][0-9]+)?)"?/isu', $html, $match)) {
                return $match[1];
            }
        }

        return 0;
    }

    protected function titleTag(string $html): string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/isu', $html, $match)) {
            return html_entity_decode((string) $match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return '';
    }

    protected function mergeProductData(array $base, array $candidate): array
    {
        foreach ($candidate as $key => $value) {
            if ($key === 'sale_price') {
                $price = $this->money($value);
                if ($price > 0) {
                    $base[$key] = $price;
                }

                continue;
            }

            if ($key === 'total_stock') {
                if (is_numeric($value)) {
                    $base[$key] = max(0, (int) $value);
                }

                continue;
            }

            if (in_array($key, [
                'evaluation_count',
                'review_count',
                'favorite_count',
                'question_count',
                'category_rank',
                'seller_follower_count',
                'campaign_count',
                'max_installment',
                'max_sale_limit',
                'rush_delivery_duration',
                'image_count',
            ], true)) {
                if (is_numeric($value)) {
                    $base[$key] = max(0, (int) $value);
                }

                continue;
            }

            if (in_array($key, ['average_rating', 'seller_score'], true)) {
                if (is_numeric($value)) {
                    $base[$key] = $key === 'average_rating'
                        ? max(0, min(5, (float) $value))
                        : max(0, min(10, (float) $value));
                }

                continue;
            }

            if (in_array($key, ['sellers', 'promotions', 'attributes', 'data_sources'], true)) {
                if (is_array($value) && $value !== []) {
                    $base[$key] = array_values($value);
                }

                continue;
            }

            if ($key === 'seller_legal') {
                if (is_array($value) && $value !== []) {
                    $base[$key] = $this->mergeSellerLegalDetails((array) ($base[$key] ?? []), $value);
                }

                continue;
            }

            if ($key === 'currency') {
                $currency = $this->currency($value);
                if ($currency !== '') {
                    $base[$key] = $currency;
                }

                continue;
            }

            if ($key === 'stock_status') {
                $stockStatus = $this->stockStatus($value);
                if ($stockStatus !== 'unknown') {
                    $base[$key] = $stockStatus;
                }

                continue;
            }

            if (is_string($value) && trim($value) !== '') {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    protected function hasProductData(array $data): bool
    {
        return trim((string) ($data['title'] ?? '')) !== ''
            || (float) ($data['sale_price'] ?? 0) > 0
            || trim((string) ($data['brand'] ?? '')) !== '';
    }

    protected function failure(string $message, string $url, array $data = []): array
    {
        return [
            'ok' => false,
            'message' => $message,
            'data' => $data ?: $this->emptyData($url),
        ];
    }

    protected function normalizeUrl(string $url): string
    {
        $url = trim($url);
        $url = preg_replace('/\s+/u', '', $url) ?: '';

        return Str::limit($url, 1000, '');
    }

    protected function isAllowedUrl(string $url): bool
    {
        if (! Str::startsWith($url, ['http://', 'https://'])) {
            return false;
        }

        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));

        return $host === 'ty.gl'
            || Str::endsWith($host, '.ty.gl')
            || $host === 'trendyol.com'
            || Str::endsWith($host, '.trendyol.com');
    }

    protected function extractProductId(string $value): ?string
    {
        if (preg_match('/-p-(\d+)/iu', $value, $match)) {
            return $match[1];
        }

        if (preg_match('/"productId"\s*:\s*"?(\d+)"?/iu', $value, $match)) {
            return $match[1];
        }

        return null;
    }

    protected function firstAssoc(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        if ($this->isList($value)) {
            $first = $value[0] ?? [];

            return is_array($first) ? $first : [];
        }

        return $value;
    }

    protected function extractNamedValue(mixed $value): string
    {
        if (is_array($value)) {
            return $this->cleanText($value['name'] ?? ($value[0]['name'] ?? $value[0] ?? ''));
        }

        return $this->cleanText($value);
    }

    protected function extractCategory(mixed $value): string
    {
        if (is_array($value)) {
            $candidate = $value['name'] ?? end($value);

            return $this->extractNamedValue($candidate);
        }

        return $this->cleanText($value);
    }

    protected function extractImage(mixed $value): string
    {
        if (is_array($value)) {
            $candidate = $value['url'] ?? $value['contentUrl'] ?? $value[0] ?? '';

            if (is_array($candidate)) {
                return $this->extractImage($candidate);
            }

            return $this->cleanUrl($candidate);
        }

        return $this->cleanUrl($value);
    }

    protected function cleanTitle(mixed $value): string
    {
        $title = $this->cleanText($value);
        $title = preg_replace('/\s+[-|]\s+Trendyol\s*$/iu', '', $title) ?: $title;

        return trim($title);
    }

    protected function titleFromSlug(string $value): string
    {
        $value = rawurldecode($value);
        $value = preg_replace('/[-_]+/u', ' ', $value) ?: '';
        $value = preg_replace('/\s+/u', ' ', $value) ?: '';
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        return Str::of($value)->title()->limit(500, '')->toString();
    }

    protected function cleanText(mixed $value): string
    {
        if (is_array($value)) {
            $value = implode(' ', array_filter(array_map(fn ($item) => is_scalar($item) ? (string) $item : '', $value)));
        }

        $html = preg_replace('/<[^>]+>/u', ' ', (string) $value) ?: (string) $value;
        $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if ($text !== '' && ! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }

        $text = preg_replace('/\s+/u', ' ', $text) ?: '';

        return trim(Str::limit($text, 500, ''));
    }

    /**
     * Trendyol'un satıcı hukuk kartı bazen sayfanın görünür metni içinde geç
     * geldiği için burada 500 karakterlik genel temizleyici limiti uygulanmaz.
     */
    protected function cleanLongText(mixed $value, int $limit = 12000): string
    {
        if (is_array($value)) {
            $value = implode(' ', array_filter(array_map(fn ($item) => is_scalar($item) ? (string) $item : '', $value)));
        }

        $html = preg_replace('/<[^>]+>/u', ' ', (string) $value) ?: (string) $value;
        $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if ($text !== '' && ! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }

        $text = preg_replace('/\s+/u', ' ', $text) ?: '';

        return trim(Str::limit($text, $limit, ''));
    }

    /**
     * Trendyol ürün sayfasındaki "Trendyol Pazaryeri" / satıcı güvence kartında
     * görünen hukuki alanları yakalar. Bu alanlar çoğu zaman JSON state yerine
     * görünür metin olarak yayınlandığı için label bazlı okunur.
     *
     * @return array<string, mixed>
     */
    protected function extractVisibleSellerLegalData(string $html): array
    {
        $text = $this->cleanLongText($html);

        if ($text === '' || ! $this->looksLikeSellerLegalText($text)) {
            return [];
        }

        $legal = $this->mergeSellerLegalDetails($this->emptySellerLegalDetails(), [
            'title' => $this->extractSellerLabeledValue($text, ['Satıcı Ünvanı', 'Satıcı Unvanı', 'Satici Unvani']),
            'address' => $this->extractSellerLabeledValue($text, ['Adres', 'Açık Adres', 'Acik Adres']),
            'kep' => $this->extractSellerLabeledValue($text, ['Kep Adresi', 'KEP Adresi', 'Kep', 'KEP']),
            'tax_number' => preg_replace('/\D+/u', '', $this->extractSellerLabeledValue($text, ['Vergi Kimlik Numarası', 'Vergi Kimlik Numarasi', 'Vergi No', 'VKN'])) ?: '',
            'tax_office' => $this->extractSellerLabeledValue($text, ['Ticaret sicili', 'Ticaret Sicili', 'Vergi Dairesi', 'Vergi Dairesi Bilgisi']),
            'phone' => $this->extractSellerLabeledValue($text, ['Telefon', 'Telefon Numarası', 'Telefon Numarasi']),
        ]);

        if (array_filter($legal) === []) {
            return [];
        }

        return [
            'seller_legal' => $legal,
            'seller_title' => $legal['title'],
            'seller_address' => $legal['address'],
            'seller_kep' => $legal['kep'],
            'seller_tax_number' => $legal['tax_number'],
            'seller_tax_office' => $legal['tax_office'],
            'seller_phone' => $legal['phone'],
        ];
    }

    protected function looksLikeSellerLegalText(string $text): bool
    {
        $haystack = Str::lower($text);
        $score = 0;

        foreach (['Satıcı Ünvanı', 'Satıcı Unvanı', 'Vergi Kimlik', 'Kep Adresi', 'KEP', 'Ticaret sicili', 'Adres'] as $needle) {
            if (Str::contains($haystack, Str::lower($needle))) {
                $score++;
            }
        }

        return $score >= 2;
    }

    /**
     * @param  array<int, string>  $labels
     */
    protected function extractSellerLabeledValue(string $text, array $labels): string
    {
        $stopLabels = [
            'Satıcı Ünvanı',
            'Satıcı Unvanı',
            'Satici Unvani',
            'Satıcı',
            'Satici',
            'Ticaret sicili',
            'Ticaret Sicili',
            'Vergi Kimlik Numarası',
            'Vergi Kimlik Numarasi',
            'Vergi No',
            'VKN',
            'Kep Adresi',
            'KEP Adresi',
            'İletişim',
            'Iletisim',
            'Telefon',
            'Adres',
            'Ücretsiz İade',
            'Hızlı Teslimat',
            'Trendyol Müşteri Desteği',
            'Bu ürün',
            'Ürünün Tüm Özellikleri',
            'Benzer Ürünler',
        ];
        $stopPattern = implode('|', array_map(fn (string $label): string => preg_quote($label, '/'), $stopLabels));

        foreach ($labels as $label) {
            $pattern = '/(?:^|\s)'.preg_quote($label, '/').'\s*:\s*(.*?)(?=\s*(?:'.$stopPattern.')(?:\s*:)?|$)/isu';

            if (! preg_match($pattern, $text, $match)) {
                continue;
            }

            $value = $this->cleanLongText($match[1] ?? '', 1000);
            $value = preg_replace('/^(?:tarafından|tarafindan)\s+/iu', '', $value) ?: $value;

            if ($value !== '') {
                return Str::limit($value, 1000, '');
            }
        }

        return '';
    }

    /** @return array{title: string, address: string, kep: string, tax_number: string, tax_office: string, phone: string} */
    protected function emptySellerLegalDetails(): array
    {
        return [
            'title' => '',
            'address' => '',
            'kep' => '',
            'tax_number' => '',
            'tax_office' => '',
            'phone' => '',
        ];
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $incoming
     * @return array{title: string, address: string, kep: string, tax_number: string, tax_office: string, phone: string}
     */
    protected function mergeSellerLegalDetails(array $base, array $incoming): array
    {
        $merged = $this->emptySellerLegalDetails();

        foreach ($merged as $key => $value) {
            $merged[$key] = $this->cleanText($base[$key] ?? '')
                ?: $this->cleanText($incoming[$key] ?? '');
        }

        return $merged;
    }

    protected function cleanUrl(mixed $value): string
    {
        $url = trim((string) $value);

        return Str::limit($url, 1000, '');
    }

    protected function currency(mixed $value): string
    {
        $currency = Str::upper(trim((string) $value));

        return $currency !== '' ? Str::limit($currency, 8, '') : 'TRY';
    }

    protected function stockStatus(mixed $value): string
    {
        $availability = Str::lower(trim((string) $value));

        return match (true) {
            str_contains($availability, 'instock') || str_contains($availability, 'in_stock') || str_contains($availability, 'stokta') => 'in_stock',
            str_contains($availability, 'outofstock') || str_contains($availability, 'out_of_stock') || str_contains($availability, 'tukendi') || str_contains($availability, 'tükendi') => 'out_of_stock',
            str_contains($availability, 'preorder') || str_contains($availability, 'pre_order') || str_contains($availability, 'on siparis') || str_contains($availability, 'ön sipariş') => 'preorder',
            default => 'unknown',
        };
    }

    protected function money(mixed $value): float
    {
        $raw = trim((string) ($value ?? ''));

        if ($raw === '') {
            return 0.0;
        }

        $raw = preg_replace('/[^0-9,.\-]/u', '', $raw) ?: '';

        if (str_contains($raw, ',') && str_contains($raw, '.')) {
            $raw = strrpos($raw, ',') > strrpos($raw, '.')
                ? str_replace('.', '', str_replace(',', '.', $raw))
                : str_replace(',', '', $raw);
        } elseif (str_contains($raw, ',')) {
            $raw = str_replace(',', '.', $raw);
        }

        return round(max(0, (float) $raw), 2);
    }

    protected function isList(array $value): bool
    {
        return array_keys($value) === range(0, count($value) - 1);
    }
}
