<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TrendyolSearchResultReader
{
    /**
     * @return array{ok: bool, message: string, data: array<string, mixed>}
     */
    public function fetch(string $keyword): array
    {
        $keyword = $this->normalizeKeyword($keyword);

        if (mb_strlen($keyword) < 2) {
            return $this->failure('Anahtar kelime en az 2 karakter olmalı.', $keyword);
        }

        $url = 'https://www.trendyol.com/sr?q='.rawurlencode($keyword);

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
            return $this->failure('Trendyol arama sonucu şu anda okunamadı.', $keyword, $url);
        }

        if (! $response->successful()) {
            return $this->failure('Trendyol arama sonucu okunamadı. HTTP durum kodu: '.$response->status(), $keyword, $url);
        }

        return [
            'ok' => true,
            'message' => 'Anahtar kelime görünürlüğü güncellendi.',
            'data' => $this->parse($response->body(), $keyword, $url),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function parse(string $html, string $keyword, string $url = ''): array
    {
        preg_match_all('/-p-(\d+)/iu', $html, $matches);
        $productIds = [];

        foreach ($matches[1] ?? [] as $productId) {
            $productId = (string) $productId;

            if (! in_array($productId, $productIds, true)) {
                $productIds[] = $productId;
            }

            if (count($productIds) >= 50) {
                break;
            }
        }

        $topProducts = $this->extractTopProducts($html);
        $resultCount = $this->extractResultCount($html) ?: count($productIds);

        return [
            'keyword' => $this->normalizeKeyword($keyword),
            'source_url' => $url,
            'product_ids' => $productIds,
            'result_count' => $resultCount,
            'checked_result_count' => count($productIds),
            'scan_limit' => 50,
            'top_products' => $topProducts,
        ];
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
                'product_ids' => [],
                'result_count' => 0,
                'checked_result_count' => 0,
                'scan_limit' => 50,
                'top_products' => [],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function extractTopProducts(string $html): array
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
                'title' => Str::limit($title, 180, ''),
            ];

            if (count($items) >= 40) {
                break;
            }
        }

        return array_values($items);
    }

    protected function extractResultCount(string $html): int
    {
        if (! preg_match('/"totalCount"\s*:\s*(\d+)/iu', $html, $match)
            && ! preg_match('/([\d.]+)\s*(?:sonuç|ürün)/iu', $html, $match)) {
            return 0;
        }

        return max(0, (int) str_replace('.', '', (string) ($match[1] ?? '0')));
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
}
