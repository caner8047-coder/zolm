<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Str;

class TrendyolBoosterMobileDiscoveryService
{
    public function __construct(protected TrendyolSearchResultReader $searchReader) {}

    /** @return array{ok: bool, message: string, query: string, source_url: string, product_id: string} */
    public function resolve(string $query): array
    {
        $query = $this->normalize($query);

        if (mb_strlen($query) < 2) {
            return $this->failure($query, 'Barkod veya ürün kodu en az 2 karakter olmalı.');
        }

        $result = $this->searchReader->fetch($query);

        if (! ($result['ok'] ?? false)) {
            return $this->failure($query, (string) ($result['message'] ?? 'Trendyol araması tamamlanamadı.'));
        }

        return $this->fromSearchPayload($query, (array) ($result['data'] ?? []));
    }

    /** @return array{ok: bool, message: string, query: string, source_url: string, product_id: string} */
    public function fromSearchPayload(string $query, array $payload): array
    {
        $query = $this->normalize($query);
        $product = collect($payload['top_products'] ?? [])->first(function (mixed $row): bool {
            $url = (string) data_get($row, 'source_url', '');

            return filter_var($url, FILTER_VALIDATE_URL)
                && preg_match('#^https://(?:[^/]+\.)?trendyol\.com/#i', $url) === 1
                && preg_match('/-p-(\d+)/i', $url) === 1;
        });

        if (! is_array($product)) {
            return $this->failure($query, 'Bu kodla doğrulanabilir bir Trendyol ürün bağlantısı bulunamadı.');
        }

        $url = (string) $product['source_url'];
        preg_match('/-p-(\d+)/i', $url, $matches);

        return [
            'ok' => true,
            'message' => 'Ürün bulundu; canlı analiz başlatılıyor.',
            'query' => $query,
            'source_url' => $url,
            'product_id' => (string) ($product['trendyol_product_id'] ?? $matches[1] ?? ''),
        ];
    }

    protected function normalize(string $query): string
    {
        $query = html_entity_decode(strip_tags($query), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $query = preg_replace('/[^\p{L}\p{N}_.\- ]+/u', '', $query) ?: '';

        return trim(Str::limit(preg_replace('/\s+/u', ' ', $query) ?: '', 80, ''));
    }

    /** @return array{ok: false, message: string, query: string, source_url: string, product_id: string} */
    protected function failure(string $query, string $message): array
    {
        return ['ok' => false, 'message' => $message, 'query' => $query, 'source_url' => '', 'product_id' => ''];
    }
}
