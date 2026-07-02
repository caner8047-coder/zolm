<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GoogleMarketplaceSearchService
{
    /** @return array<string, array{label: string, domains: array<int, string>, kind: string}> */
    public function platforms(): array
    {
        return [
            'trendyol' => ['label' => 'Trendyol', 'domains' => ['trendyol.com'], 'kind' => 'marketplace'],
            'hepsiburada' => ['label' => 'Hepsiburada', 'domains' => ['hepsiburada.com'], 'kind' => 'marketplace'],
            'n11' => ['label' => 'n11', 'domains' => ['n11.com'], 'kind' => 'marketplace'],
            'amazon_tr' => ['label' => 'Amazon Türkiye', 'domains' => ['amazon.com.tr'], 'kind' => 'marketplace'],
            'pazarama' => ['label' => 'Pazarama', 'domains' => ['pazarama.com'], 'kind' => 'marketplace'],
            'pttavm' => ['label' => 'PttAVM / EpttAVM', 'domains' => ['pttavm.com', 'epttavm.com'], 'kind' => 'marketplace'],
            'boyner' => ['label' => 'Boyner Pazaryeri', 'domains' => ['boyner.com.tr'], 'kind' => 'marketplace'],
            'teknosa' => ['label' => 'Teknosa Pazaryeri', 'domains' => ['teknosa.com'], 'kind' => 'marketplace'],
            'mediamarkt' => ['label' => 'MediaMarkt Pazaryeri', 'domains' => ['mediamarkt.com.tr'], 'kind' => 'marketplace'],
            'modanisa' => ['label' => 'Modanisa', 'domains' => ['modanisa.com'], 'kind' => 'marketplace'],
            'koctas' => ['label' => 'Koçtaş Pazaryeri', 'domains' => ['koctas.com.tr'], 'kind' => 'marketplace'],
            'ciceksepeti' => ['label' => 'ÇiçekSepeti', 'domains' => ['ciceksepeti.com'], 'kind' => 'marketplace'],
            'akakce' => ['label' => 'Akakçe', 'domains' => ['akakce.com'], 'kind' => 'comparison'],
            'cimri' => ['label' => 'Cimri', 'domains' => ['cimri.com'], 'kind' => 'comparison'],
        ];
    }

    /**
     * @return array{ok: bool, configured: bool, message: string, query: string, search_url: string, offers: array<int, array<string, mixed>>}
     */
    public function search(string $title, string $brand = ''): array
    {
        $query = $this->query($title, $brand);
        $searchUrl = 'https://www.google.com/search?hl=tr&gl=tr&udm=28&q='.rawurlencode($query);
        $apiKey = trim((string) config('marketplace.trendyol_booster.market_research.google_api_key', ''));
        $engineId = trim((string) config('marketplace.trendyol_booster.market_research.google_engine_id', ''));

        if ($apiKey === '' || $engineId === '') {
            return [
                'ok' => true,
                'configured' => false,
                'message' => 'Google API yapılandırılmadı; dış pazar sonuçları Chrome Booster köprüsüyle alınabilir.',
                'query' => $query,
                'search_url' => $searchUrl,
                'offers' => [],
            ];
        }

        try {
            $response = Http::timeout((int) config('marketplace.trendyol_booster.request_timeout', 12))
                ->retry(1, 250)
                ->get('https://www.googleapis.com/customsearch/v1', [
                    'key' => $apiKey,
                    'cx' => $engineId,
                    'q' => $query,
                    'num' => min(10, max(1, (int) config('marketplace.trendyol_booster.market_research.max_results', 10))),
                    'gl' => 'tr',
                    'hl' => 'tr',
                    'lr' => 'lang_tr',
                    'safe' => 'active',
                ]);
        } catch (\Throwable) {
            return $this->failure($query, $searchUrl, 'Google pazar araması şu anda yanıt vermiyor.');
        }

        if (! $response->successful()) {
            return $this->failure($query, $searchUrl, 'Google pazar araması sınırına ulaştı veya erişim reddedildi.');
        }

        $offers = collect((array) $response->json('items', []))
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item, int $index): array => $this->normalizeItem($item, $index + 1))
            ->filter(fn (array $item): bool => $item['source_url'] !== '' && $item['platform'] !== 'google')
            ->values()
            ->all();

        return [
            'ok' => true,
            'configured' => true,
            'message' => count($offers).' Google pazar sonucu bulundu.',
            'query' => $query,
            'search_url' => $searchUrl,
            'offers' => $offers,
        ];
    }

    /** @return array{key: string, label: string, kind: string} */
    public function detectPlatform(string $url): array
    {
        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./u', '', $host) ?: $host;

        foreach ($this->platforms() as $key => $platform) {
            foreach ($platform['domains'] as $domain) {
                if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                    return ['key' => $key, 'label' => $platform['label'], 'kind' => $platform['kind']];
                }
            }
        }

        return [
            'key' => $host !== '' ? 'other' : 'google',
            'label' => $host !== '' ? Str::limit($host, 100, '') : 'Google',
            'kind' => 'other',
        ];
    }

    public function query(string $title, string $brand = ''): string
    {
        $product = $brand !== '' && ! Str::contains(Str::lower(Str::ascii($title)), Str::lower(Str::ascii($brand)))
            ? trim($brand.' '.$title)
            : trim($title);

        return Str::limit($product, 350, '');
    }

    /** @return array<string, mixed> */
    protected function normalizeItem(array $item, int $rank): array
    {
        $url = trim((string) ($item['link'] ?? ''));
        $platform = $this->detectPlatform($url);
        $meta = (array) data_get($item, 'pagemap.metatags.0', []);
        $offer = (array) data_get($item, 'pagemap.offer.0', []);
        $snippet = trim((string) ($item['snippet'] ?? ''));

        return [
            'platform' => $platform['key'],
            'platform_label' => $platform['label'],
            'platform_kind' => $platform['kind'],
            'seller_name' => $this->sellerFromSnippet($snippet),
            'title' => Str::limit(trim((string) ($item['title'] ?? '')), 500, ''),
            'source_url' => Str::limit($url, 1000, ''),
            'sale_price' => $this->money(
                $offer['price']
                ?? $meta['product:price:amount']
                ?? $meta['og:price:amount']
                ?? $this->priceFromText($snippet)
            ),
            'availability' => $this->availability($offer['availability'] ?? $meta['product:availability'] ?? $snippet),
            'rank' => $rank,
            'source_type' => 'google_api',
            'snippet' => Str::limit($snippet, 1000, ''),
        ];
    }

    protected function sellerFromSnippet(string $snippet): string
    {
        if (preg_match('/(?:Satıcı|Satici)\s*[:\-]\s*([^·|,;]{2,80})/iu', $snippet, $match)) {
            return trim($match[1]);
        }

        return '';
    }

    protected function priceFromText(string $text): float
    {
        if (preg_match('/(?:₺\s*([\d.]+,\d{2})|([\d.]+,\d{2})\s*(?:TL|₺))/u', $text, $match)) {
            return $this->money($match[1] ?: $match[2]);
        }

        return 0;
    }

    protected function availability(mixed $value): string
    {
        $value = Str::lower((string) $value);

        if (str_contains($value, 'instock') || str_contains($value, 'stokta')) {
            return 'in_stock';
        }

        if (str_contains($value, 'outofstock') || str_contains($value, 'tükendi')) {
            return 'out_of_stock';
        }

        return 'unknown';
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

    /** @return array{ok: bool, configured: bool, message: string, query: string, search_url: string, offers: array<int, mixed>} */
    protected function failure(string $query, string $searchUrl, string $message): array
    {
        return compact('query') + [
            'ok' => false,
            'configured' => true,
            'message' => $message,
            'search_url' => $searchUrl,
            'offers' => [],
        ];
    }
}
