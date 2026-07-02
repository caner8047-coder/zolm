<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TrendyolStorePageReader
{
    public function __construct(
        protected TrendyolProductPageReader $productReader,
    ) {
    }

    /**
     * @return array{ok: bool, message: string, data: array<string, mixed>}
     */
    public function fetch(string $url): array
    {
        $url = $this->normalizeUrl($url);

        if ($this->isProductUrl($url)) {
            return $this->fetchFromProductUrl($url);
        }

        $fallbackStoreName = $this->storeNameFromUrl($url);
        $lastResult = null;

        foreach ($this->storeCandidateUrls($url) as $candidateUrl) {
            $result = $this->fetchStorePage($candidateUrl, $fallbackStoreName);
            $lastResult = $result;

            if (! empty($result['data']['items'] ?? [])) {
                return $result;
            }
        }

        $data = (array) ($lastResult['data'] ?? $this->emptyStoreData($url));
        $data['store_url'] = $url;
        $data['store_id'] = $data['store_id'] ?: $this->extractStoreId($url);
        $data['store_name'] = $fallbackStoreName !== '' ? $fallbackStoreName : ($data['store_name'] ?: 'Rakip Mağaza');

        return [
            'ok' => false,
            'message' => (string) ($lastResult['message'] ?? 'Trendyol mağaza ürünleri okunamadı.'),
            'data' => $data,
        ];
    }

    /**
     * Trendyol mağaza URL'sini ürün listesi döndüren arama URL'sine çevirir.
     */
    protected function convertToSearchUrl(string $url): string
    {
        // Zaten sr?mid= formatındaysa değiştirme
        if (str_contains($url, '/sr?mid=') || str_contains($url, '/sr?')) {
            return $url;
        }

        // -m-XXXXX formatından store ID çıkar
        if (preg_match('/-m-(\d+)/iu', $url, $match)) {
            return 'https://www.trendyol.com/sr?mid=' . $match[1] . '&os=1';
        }

        // mid=XXXXX query param
        $query = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        if (isset($query['mid']) && is_scalar($query['mid']) && preg_match('/^\d+$/', (string) $query['mid'])) {
            return 'https://www.trendyol.com/sr?mid=' . $query['mid'] . '&os=1';
        }

        return $url;
    }

    /**
     * @return array<int, string>
     */
    protected function storeCandidateUrls(string $url): array
    {
        $candidates = [$url];
        $convertedUrl = $this->convertToSearchUrl($url);
        $storeId = $this->extractStoreId($url) ?: $this->extractStoreId($convertedUrl);

        if ($convertedUrl !== $url) {
            $candidates[] = $convertedUrl;
        }

        if ($storeId !== '') {
            $candidates[] = 'https://www.trendyol.com/sr?mid='.$storeId.'&os=1';
            $candidates[] = 'https://www.trendyol.com/sr/?mid='.$storeId.'&os=1';
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    /**
     * @return array{ok: bool, message: string, data: array<string, mixed>}
     */
    protected function fetchStorePage(string $url, string $fallbackStoreName = ''): array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; ZOLM-Trendyol-Booster/1.0)',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'tr-TR,tr;q=0.9,en;q=0.6',
            ])
                ->timeout((int) config('marketplace.trendyol_booster.request_timeout', 15))
                ->retry(
                    (int) config('marketplace.trendyol_booster.request_retries', 1),
                    (int) config('marketplace.trendyol_booster.request_retry_delay_ms', 250)
                )
                ->get($url);
        } catch (\Throwable) {
            return $this->fallback('Trendyol mağaza sayfası şu anda okunamadı.', $url, $fallbackStoreName);
        }

        if (! $response->successful()) {
            return $this->fallback('Trendyol mağaza sayfası erişimi sınırladı. Mağaza kaydı linkten oluşturuldu.', $url, $fallbackStoreName);
        }

        $parsed = $this->parse($response->body(), $url);
        if (($parsed['store_name'] === 'Sr' || empty($parsed['store_name'])) && $fallbackStoreName !== '') {
            $parsed['store_name'] = $fallbackStoreName;
        }

        if (empty($parsed['items'])) {
            return [
                'ok' => false,
                'message' => 'Trendyol mağaza ürün kartları bu adresten okunamadı.',
                'data' => $parsed,
            ];
        }

        return [
            'ok' => true,
            'message' => 'Rakip mağaza tarandı.',
            'data' => $parsed,
        ];
    }

    /**
     * @return array{ok: bool, message: string, data: array<string, mixed>}
     */
    protected function fetchFromProductUrl(string $url): array
    {
        $productResult = $this->productReader->fetch($url);
        $product = (array) ($productResult['data'] ?? []);
        $seller = $this->sellerFromProductData($product, $url);
        $storeUrl = $seller['store_url'] ?: $url;
        $storeResult = $storeUrl !== $url
            ? $this->fetchStorePage($storeUrl)
            : $this->fallback('Ürün linkinden satıcı mağaza linki çözülemedi; ürün temel kaydı oluşturuldu.', $url);
        $data = (array) ($storeResult['data'] ?? []);
        $starterItem = $this->productItemFromProductData($product, $url);
        $productPreview = $this->productPreviewFromProductData($product, $url);
        $items = $this->mergeItems((array) ($data['items'] ?? []), $starterItem ? [$starterItem] : []);
        $storeName = $seller['store_name'] ?: (string) ($data['store_name'] ?? '') ?: $this->storeNameFromUrl($storeUrl);
        $storeId = $seller['store_id'] ?: (string) ($data['store_id'] ?? '') ?: $this->extractStoreId($storeUrl);
        $sellerDetails = $this->mergeSellerDetails((array) ($data['seller'] ?? []), $seller);

        $data = array_merge($this->emptyStoreData($storeUrl), $data, [
            'store_url' => $storeUrl,
            'source_product_url' => $url,
            'resolved_from_product' => true,
            'store_id' => $storeId,
            'store_name' => $storeName ?: 'Rakip Mağaza',
            'items' => $items,
            'product_preview' => $productPreview,
            'total_products' => max((int) ($data['total_products'] ?? 0), count($items)),
            'seller' => $sellerDetails,
            'seller_title' => $sellerDetails['title'] ?: (string) ($data['seller_title'] ?? ''),
            'address' => $sellerDetails['address'] ?: (string) ($data['address'] ?? ''),
            'kep' => $sellerDetails['kep'] ?: (string) ($data['kep'] ?? ''),
            'tax_number' => $sellerDetails['tax_number'] ?: (string) ($data['tax_number'] ?? ''),
            'tax_office' => $sellerDetails['tax_office'] ?: (string) ($data['tax_office'] ?? ''),
            'phone' => $sellerDetails['phone'] ?: (string) ($data['phone'] ?? ''),
        ]);

        return [
            'ok' => true,
            'message' => count($items) > ($starterItem ? 1 : 0)
                ? $storeName.' satıcısı ürün linkinden çözüldü ve mağaza vitrini tarandı.'
                : $storeName.' satıcısı ürün linkinden çözüldü; Trendyol mağaza vitrini sınırlı veri döndürdü.',
            'data' => $data,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function parse(string $html, string $url): array
    {
        $items = $this->extractStructuredItems($html);
        $seller = $this->extractSellerDetails($html);

        if (preg_match_all('/href=["\']([^"\']*-p-(\d+)[^"\']*)["\'][^>]*>(.*?)<\/a>/isu', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $productId = (string) $match[2];

                if (isset($items[$productId])) {
                    continue;
                }

                $title = $this->cleanText(strip_tags($match[3] ?? ''));
                $href = html_entity_decode((string) $match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $items[$productId] = [
                    'trendyol_product_id' => $productId,
                    'source_url' => Str::startsWith($href, ['http://', 'https://']) ? $href : 'https://www.trendyol.com'.$href,
                    'title' => Str::limit($title, 240, ''),
                    'brand' => '',
                    'sale_price' => 0,
                ];
            }
        }

        $items = array_slice(array_values($items), 0, 72);

        return [
            'store_url' => $url,
            'store_id' => $this->extractStoreId($url),
            'store_name' => $this->storeNameFromUrl($url),
            'items' => $items,
            'total_products' => count($items),
            'seller' => $seller,
            'seller_title' => $seller['title'],
            'address' => $seller['address'],
            'kep' => $seller['kep'],
            'tax_number' => $seller['tax_number'],
            'tax_office' => $seller['tax_office'],
            'phone' => $seller['phone'],
        ];
    }

    protected function fallback(string $message, string $url, string $fallbackStoreName = ''): array
    {
        $data = $this->emptyStoreData($url);
        if ($fallbackStoreName !== '') {
            $data['store_name'] = $fallbackStoreName;
        }

        return [
            'ok' => false,
            'message' => $message,
            'data' => $data,
        ];
    }

    /** @return array<string, mixed> */
    protected function emptyStoreData(string $url): array
    {
        return [
            'store_url' => $url,
            'store_id' => $this->extractStoreId($url),
            'store_name' => $this->storeNameFromUrl($url),
            'items' => [],
            'product_preview' => null,
            'total_products' => 0,
            'seller' => $this->emptySellerDetails(),
            'seller_title' => '',
            'address' => '',
            'kep' => '',
            'tax_number' => '',
            'tax_office' => '',
            'phone' => '',
        ];
    }

    protected function isProductUrl(string $url): bool
    {
        return preg_match('/-p-\d+/iu', $url) === 1;
    }

    protected function extractStoreId(string $url): string
    {
        if (preg_match('/-m-(\d+)/iu', $url, $match)) {
            return (string) $match[1];
        }

        $query = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        foreach (['mid', 'merchantId'] as $key) {
            if (isset($query[$key]) && is_scalar($query[$key])) {
                return preg_replace('/\D+/u', '', (string) $query[$key]);
            }
        }

        return '';
    }

    protected function storeNameFromUrl(string $url): string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $segments = array_values(array_filter(explode('/', $path)));
        $segment = (string) end($segments);
        $segment = preg_replace('/-m-\d+.*$/iu', '', $segment) ?: $segment;
        $segment = str_replace('-', ' ', $segment);

        return Str::title(trim($segment)) ?: 'Rakip Mağaza';
    }

    /** @return array<string, string> */
    protected function sellerFromProductData(array $product, string $url): array
    {
        $primarySeller = collect((array) ($product['sellers'] ?? []))
            ->first(fn ($seller): bool => is_array($seller)) ?: [];
        $storeId = $this->filledText(
            data_get($product, 'seller_id')
            ?: data_get($primarySeller, 'seller_id')
            ?: $this->extractStoreId($url),
            ''
        );
        $storeName = $this->filledText(
            data_get($product, 'seller_name')
            ?: data_get($primarySeller, 'seller_name')
            ?: '',
            ''
        );
        $legal = (array) ($product['seller_legal'] ?? []);

        return [
            'store_id' => $storeId,
            'store_name' => $storeName,
            'store_url' => $storeId !== '' ? $this->storeUrlForSeller($storeName, $storeId) : '',
            'title' => $this->filledText($legal['title'] ?? $product['seller_title'] ?? '', ''),
            'address' => $this->filledText($legal['address'] ?? $product['seller_address'] ?? '', ''),
            'kep' => $this->filledText($legal['kep'] ?? $product['seller_kep'] ?? '', ''),
            'tax_number' => $this->filledText($legal['tax_number'] ?? $product['seller_tax_number'] ?? '', ''),
            'tax_office' => $this->filledText($legal['tax_office'] ?? $product['seller_tax_office'] ?? '', ''),
            'phone' => $this->filledText($legal['phone'] ?? $product['seller_phone'] ?? '', ''),
        ];
    }

    /** @return array<string, mixed>|null */
    protected function productItemFromProductData(array $product, string $url): ?array
    {
        $productId = $this->filledText($product['trendyol_product_id'] ?? $this->extractProductId($url), '');
        $title = $this->filledText($product['title'] ?? '', '');

        if ($productId === '' && $title === '') {
            return null;
        }

        return [
            'trendyol_product_id' => $productId,
            'source_url' => $this->filledText($product['source_url'] ?? $url, $url),
            'title' => $title !== '' ? $title : $this->storeNameFromUrl($url),
            'brand' => $this->filledText($product['brand'] ?? '', ''),
            'category_name' => $this->filledText($product['category_name'] ?? '', ''),
            'image_url' => $this->filledText($product['image_url'] ?? '', ''),
            'total_stock' => is_numeric($product['total_stock'] ?? null) ? max(0, (int) $product['total_stock']) : null,
            'stock_status' => $this->filledText($product['stock_status'] ?? '', ''),
            'seller_id' => $this->filledText($product['seller_id'] ?? '', ''),
            'seller_name' => $this->filledText(
                data_get($product, 'seller_name')
                ?: data_get($product, 'sellers.0.seller_name')
                ?: '',
                ''
            ),
            'sale_price' => $this->money($product['sale_price'] ?? 0),
        ];
    }

    /** @return array<string, mixed>|null */
    protected function productPreviewFromProductData(array $product, string $url): ?array
    {
        $item = $this->productItemFromProductData($product, $url);

        if (! $item) {
            return null;
        }

        return $item + [
            'currency' => $this->filledText($product['currency'] ?? 'TRY', 'TRY'),
            'availability' => $this->filledText($product['availability'] ?? '', ''),
            'favorite_count' => is_numeric($product['favorite_count'] ?? null) ? max(0, (int) $product['favorite_count']) : null,
            'review_count' => is_numeric($product['review_count'] ?? null) ? max(0, (int) $product['review_count']) : null,
            'average_rating' => is_numeric($product['average_rating'] ?? null) ? max(0, min(5, (float) $product['average_rating'])) : null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $existing
     * @param  array<int, array<string, mixed>>  $incoming
     * @return array<int, array<string, mixed>>
     */
    protected function mergeItems(array $existing, array $incoming): array
    {
        $items = [];

        foreach (array_merge($incoming, $existing) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $key = $this->filledText($item['trendyol_product_id'] ?? '', '');
            $key = $key !== '' ? 'id:'.$key : 'url:'.$this->filledText($item['source_url'] ?? '', '');

            if ($key === 'url:' || isset($items[$key])) {
                continue;
            }

            $items[$key] = $item;
        }

        return array_slice(array_values($items), 0, 72);
    }

    protected function storeUrlForSeller(string $storeName, string $storeId): string
    {
        $slug = Str::slug(Str::ascii($storeName !== '' ? $storeName : 'satici'));
        $slug = $slug !== '' ? $slug : 'satici';

        return 'https://www.trendyol.com/magaza/'.$slug.'-m-'.$storeId;
    }

    protected function extractProductId(string $value): string
    {
        return preg_match('/-p-(\d+)/iu', $value, $match) ? (string) $match[1] : '';
    }

    protected function normalizeUrl(string $url): string
    {
        $url = trim($url);
        $url = preg_replace('/\s+/u', '', $url) ?: '';

        return Str::limit($url, 1000, '');
    }

    protected function cleanText(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value) ?: '';

        return trim($value);
    }

    /** @return array{title: string, address: string, kep: string, tax_number: string, tax_office: string, phone: string} */
    protected function extractSellerDetails(string $html): array
    {
        $payloads = [];

        if (preg_match_all('/<script[^>]*>(.*?)<\/script>/isu', $html, $scripts)) {
            foreach ($scripts[1] ?? [] as $script) {
                $script = trim(html_entity_decode((string) $script, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $decoded = json_decode($script, true);

                if (is_array($decoded)) {
                    $payloads[] = $decoded;
                }
            }
        }

        $decodedHtml = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $aliases = [
            'title' => ['sellerTitle', 'companyTitle', 'companyName', 'commercialTitle', 'tradeName', 'merchantName'],
            'address' => ['companyAddress', 'registeredAddress', 'sellerAddress', 'fullAddress', 'address'],
            'kep' => ['registeredEmailAddress', 'kepAddress', 'kep', 'registeredEmail'],
            'tax_number' => ['taxNumber', 'taxNo', 'taxIdentityNumber', 'vkn'],
            'tax_office' => ['taxOfficeName', 'taxOffice'],
            'phone' => ['phoneNumber', 'telephone', 'phone'],
        ];
        $seller = [];

        foreach ($aliases as $field => $keys) {
            $value = $this->findStructuredValue($payloads, $keys);
            $seller[$field] = $value !== '' ? $value : $this->findJsonString($decodedHtml, $keys);
        }

        return [
            'title' => $seller['title'],
            'address' => $seller['address'],
            'kep' => $seller['kep'],
            'tax_number' => $seller['tax_number'],
            'tax_office' => $seller['tax_office'],
            'phone' => $seller['phone'],
        ];
    }

    /** @return array{title: string, address: string, kep: string, tax_number: string, tax_office: string, phone: string} */
    protected function emptySellerDetails(): array
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
     * @param  array<string, mixed>  $fallback
     * @return array{title: string, address: string, kep: string, tax_number: string, tax_office: string, phone: string}
     */
    protected function mergeSellerDetails(array $base, array $fallback): array
    {
        $empty = $this->emptySellerDetails();
        $merged = [];

        foreach ($empty as $key => $value) {
            $merged[$key] = $this->filledText($base[$key] ?? '', '')
                ?: $this->filledText($fallback[$key] ?? '', '');
        }

        return $merged;
    }

    /** @return array<string, array<string, mixed>> */
    protected function extractStructuredItems(string $html): array
    {
        $items = [];

        foreach ($this->structuredPayloads($html) as $payload) {
            foreach ($this->productCandidates($payload) as $product) {
                $item = $this->itemFromStructuredProduct($product);

                if (! $item) {
                    continue;
                }

                $items[$item['trendyol_product_id']] = $item;

                if (count($items) >= 72) {
                    return $items;
                }
            }
        }

        return $items;
    }

    /** @return array<int, mixed> */
    protected function structuredPayloads(string $html): array
    {
        $payloads = [];

        if (! preg_match_all('/<script\b[^>]*>(.*?)<\/script>/isu', $html, $scripts)) {
            return [];
        }

        foreach ($scripts[1] ?? [] as $script) {
            $script = trim(html_entity_decode((string) $script, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $json = null;

            if (str_starts_with($script, '{') || str_starts_with($script, '[')) {
                $json = $script;
            } elseif (preg_match('/(?:window\.__SEARCH_APP_INITIAL_STATE__|window\["__SEARCH_APP_INITIAL_STATE__"\])\s*=\s*(\{.*\})\s*;?\s*$/isu', $script, $match)) {
                $json = $match[1];
            }

            if ($json === null) {
                continue;
            }

            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $payloads[] = $decoded;
            }
        }

        return $payloads;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function productCandidates(mixed $payload): array
    {
        $candidates = [];
        $queue = [[$payload, 0]];

        while ($queue !== []) {
            [$node, $depth] = array_shift($queue);

            if ($depth > 7 || ! is_array($node)) {
                continue;
            }

            if ($this->looksLikeProduct($node)) {
                $candidates[] = $node;
                if (count($candidates) >= 120) {
                    break;
                }
            }

            foreach ($node as $value) {
                if (is_array($value)) {
                    $queue[] = [$value, $depth + 1];
                }
            }
        }

        return $candidates;
    }

    /** @param array<string, mixed> $product */
    protected function looksLikeProduct(array $product): bool
    {
        $id = $product['id'] ?? $product['productId'] ?? $product['contentId'] ?? $product['trendyol_product_id'] ?? null;
        $url = (string) ($product['url'] ?? $product['productUrl'] ?? $product['source_url'] ?? '');
        $name = $product['name'] ?? $product['title'] ?? $product['productName'] ?? null;

        return (is_scalar($id) || preg_match('/-p-\d+/iu', $url) === 1)
            && is_scalar($name);
    }

    /** @param array<string, mixed> $product */
    protected function itemFromStructuredProduct(array $product): ?array
    {
        $url = $this->filledText($product['source_url'] ?? $product['url'] ?? $product['productUrl'] ?? '', '');
        $productId = $this->filledText(
            $product['trendyol_product_id'] ?? $product['id'] ?? $product['productId'] ?? $product['contentId'] ?? $this->extractProductId($url),
            ''
        );

        if ($productId === '') {
            return null;
        }

        $brand = is_array($product['brand'] ?? null)
            ? $this->filledText(data_get($product, 'brand.name'), '')
            : $this->filledText($product['brand'] ?? $product['brandName'] ?? '', '');
        $price = $product['sale_price']
            ?? $product['price']
            ?? $product['sellingPrice']
            ?? data_get($product, 'price.sellingPrice')
            ?? data_get($product, 'price.discountedPrice')
            ?? data_get($product, 'price.discountedPrice.value')
            ?? 0;

        return [
            'trendyol_product_id' => $productId,
            'source_url' => Str::startsWith($url, ['http://', 'https://'])
                ? $url
                : ($url !== '' ? 'https://www.trendyol.com/'.ltrim($url, '/') : ''),
            'title' => Str::limit($this->filledText($product['title'] ?? $product['name'] ?? $product['productName'] ?? '', 'Rakip ürün'), 240, ''),
            'brand' => Str::limit($brand, 120, ''),
            'sale_price' => $this->money($price),
        ];
    }

    protected function filledText(mixed $value, string $fallback): string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? Str::limit($text, 1000, '') : $fallback;
    }

    protected function money(mixed $value): float
    {
        if (is_array($value)) {
            $value = data_get($value, 'value')
                ?? data_get($value, 'sellingPrice')
                ?? data_get($value, 'discountedPrice')
                ?? data_get($value, 'text')
                ?? 0;
        }

        $raw = trim((string) ($value ?? ''));

        if ($raw === '') {
            return 0.0;
        }

        $raw = preg_replace('/[^\d,.\-]/u', '', $raw) ?: '';
        $lastComma = strrpos($raw, ',');
        $lastDot = strrpos($raw, '.');

        if ($lastComma !== false && $lastDot !== false) {
            $raw = $lastComma > $lastDot
                ? str_replace('.', '', str_replace(',', '.', $raw))
                : str_replace(',', '', $raw);
        } elseif ($lastComma !== false) {
            $raw = str_replace(',', '.', $raw);
        } elseif (substr_count($raw, '.') > 1) {
            $raw = str_replace('.', '', $raw);
        }

        $price = round(max(0, (float) $raw), 2);

        return $price <= 9_999_999.99 ? $price : 0.0;
    }

    /**
     * @param  array<int, array<string, mixed>>  $payloads
     * @param  array<int, string>  $aliases
     */
    protected function findStructuredValue(array $payloads, array $aliases): string
    {
        $normalizedAliases = array_map('strtolower', $aliases);
        $queue = $payloads;

        while ($queue !== []) {
            $node = array_shift($queue);

            foreach ($node as $key => $value) {
                if (in_array(strtolower((string) $key), $normalizedAliases, true)) {
                    $text = $this->structuredText($value);

                    if ($text !== '') {
                        return $text;
                    }
                }

                if (is_array($value)) {
                    $queue[] = $value;
                }
            }
        }

        return '';
    }

    /** @param array<int, string> $aliases */
    protected function findJsonString(string $html, array $aliases): string
    {
        foreach ($aliases as $alias) {
            $pattern = '/"'.preg_quote($alias, '/').'"\s*:\s*"((?:\\\\.|[^"\\\\])*)"/iu';

            if (! preg_match($pattern, $html, $match)) {
                continue;
            }

            $decoded = json_decode('"'.$match[1].'"');
            $value = $this->cleanText(is_string($decoded) ? $decoded : (string) $match[1]);

            if ($value !== '') {
                return Str::limit($value, 1000, '');
            }
        }

        return '';
    }

    protected function structuredText(mixed $value): string
    {
        if (is_scalar($value)) {
            return Str::limit($this->cleanText((string) $value), 1000, '');
        }

        if (! is_array($value)) {
            return '';
        }

        return collect($value)
            ->flatten()
            ->filter(fn (mixed $part): bool => is_scalar($part) && trim((string) $part) !== '')
            ->map(fn (mixed $part): string => $this->cleanText((string) $part))
            ->unique()
            ->take(8)
            ->implode(', ');
    }
}
