<?php

namespace App\Services\Accounting;

use App\Models\MpOrder;
use App\Models\MpProduct;

/**
 * Pazaryeri siparişi–ürün stok kodu eşleştirme servisi.
 *
 * Önce kesin stok kodu eşleşmesi dener; bulamazsa
 * ürün adı benzerlik skoru (similar_text + Levenshtein) ile
 * güven oranına göre sıralı öneri listesi döner.
 */
class ProductMatcherService
{
    /** Doğrudan eşleşme güven skoru */
    const EXACT_SCORE     = 1.0;

    /** Yüksek benzerlik eşiği (%80) — otomatik öneriye dahil et */
    const HIGH_THRESHOLD  = 0.80;

    /** Düşük benzerlik eşiği (%40) — gösterme */
    const LOW_THRESHOLD   = 0.40;

    /**
     * Tek bir sipariş satırı için eşleşme önerisi döner.
     *
     * @return array<int, array{type: string, score: float, product_id: int, stock_code: string, product_name: string}>
     */
    public function suggest(string $stockCode, string $productName, int $userId, int $limit = 5): array
    {
        // 1. Tam stok kodu eşleşmesi
        if ($stockCode !== '') {
            $exact = MpProduct::query()
                ->where('user_id', $userId)
                ->where('stock_code', $stockCode)
                ->first(['id', 'stock_code', 'model_code', 'product_name']);

            if ($exact) {
                return [[
                    'type'         => 'exact',
                    'score'        => self::EXACT_SCORE,
                    'product_id'   => $exact->id,
                    'stock_code'   => $exact->stock_code,
                    'product_name' => $exact->product_name,
                    'model_code'   => $exact->model_code,
                ]];
            }

            // 1b. Model kodu eşleşmesi
            $byModel = MpProduct::query()
                ->where('user_id', $userId)
                ->where('model_code', $stockCode)
                ->first(['id', 'stock_code', 'model_code', 'product_name']);

            if ($byModel) {
                return [[
                    'type'         => 'model_code',
                    'score'        => 0.95,
                    'product_id'   => $byModel->id,
                    'stock_code'   => $byModel->stock_code,
                    'product_name' => $byModel->product_name,
                    'model_code'   => $byModel->model_code,
                ]];
            }
        }

        if ($productName === '') {
            return [];
        }

        return $this->fuzzyByName($productName, $userId, $limit);
    }

    /**
     * Eşleşmeyen tüm sipariş satırları için toplu öneri üretir.
     *
     * @return array<int, array{order_stock_code: string, order_product_name: string, order_count: int, suggestions: array}>
     */
    public function suggestBatch(int $userId, int $limit = 200): array
    {
        $unmatched = MpOrder::query()
            ->whereHas('store', fn ($s) => $s->where('user_id', $userId))
            ->whereNotExists(function ($q) {
                $q->select('id')
                  ->from('mp_products')
                  ->whereColumn('mp_products.stock_code', 'mp_orders.stock_code')
                  ->where('mp_products.stock_code', '!=', '');
            })
            ->where(function ($q) {
                $q->where('stock_code', '')->orWhereNull('stock_code')
                  ->orWhere(function ($q2) {
                      $q2->whereNotNull('product_name')->where('product_name', '!=', '');
                  });
            })
            ->select('stock_code', 'product_name')
            ->selectRaw('COUNT(*) as order_count')
            ->groupBy('stock_code', 'product_name')
            ->orderByDesc('order_count')
            ->limit($limit)
            ->get();

        // Tüm ürünleri belleğe çek (küçük dataset, ~1.132 ürün)
        $products = MpProduct::query()
            ->where('user_id', $userId)
            ->get(['id', 'stock_code', 'model_code', 'product_name'])
            ->toArray();

        $results = [];

        foreach ($unmatched as $row) {
            $suggestions = $this->scoreAgainstProducts(
                (string) ($row->stock_code ?? ''),
                (string) ($row->product_name ?? ''),
                $products,
                3
            );

            if (empty($suggestions)) {
                continue;
            }

            $results[] = [
                'order_stock_code'   => (string) ($row->stock_code ?? ''),
                'order_product_name' => (string) ($row->product_name ?? ''),
                'order_count'        => (int) $row->order_count,
                'suggestions'        => $suggestions,
            ];
        }

        return $results;
    }

    /**
     * Eşleşmeyi onaylar ve mp_orders tablosunu günceller.
     */
    public function confirmMatch(
        string $orderStockCode,
        string $orderProductName,
        int $targetProductId,
        int $userId,
        string $matchMethod = 'manual'
    ): int {
        return MpOrder::query()
            ->whereHas('store', fn ($q) => $q->where('user_id', $userId))
            ->where('stock_code', $orderStockCode)
            ->where('product_name', $orderProductName)
            ->update(['matched_product_id' => $targetProductId]);
    }

    // ─── Private Helpers ─────────────────────────────────────────

    private function fuzzyByName(string $needle, int $userId, int $limit): array
    {
        $products = MpProduct::query()
            ->where('user_id', $userId)
            ->get(['id', 'stock_code', 'model_code', 'product_name'])
            ->toArray();

        return $this->scoreAgainstProducts('', $needle, $products, $limit);
    }

    /**
     * @param  array<int, array{id: int, stock_code: string, model_code: string, product_name: string}> $products
     * @return array<int, array{type: string, score: float, product_id: int, stock_code: string, product_name: string}>
     */
    private function scoreAgainstProducts(string $stockCode, string $needle, array $products, int $limit): array
    {
        $needleNorm = $this->normalize($needle);
        $scored     = [];

        foreach ($products as $product) {
            // Stok kodu prefix eşleşmesi (ilk 6 karakter)
            if ($stockCode !== '' && ($product['stock_code'] ?? '') !== '') {
                if (str_starts_with($product['stock_code'], substr($stockCode, 0, 6))) {
                    $scored[] = [
                        'type'         => 'prefix',
                        'score'        => 0.75,
                        'product_id'   => $product['id'],
                        'stock_code'   => $product['stock_code'],
                        'product_name' => $product['product_name'],
                        'model_code'   => $product['model_code'] ?? '',
                    ];
                    continue;
                }
            }

            // Ürün adı benzerlik skoru
            $productNorm = $this->normalize($product['product_name'] ?? '');
            if ($productNorm === '' || $needleNorm === '') {
                continue;
            }

            $score = $this->combinedScore($needleNorm, $productNorm);

            if ($score >= self::LOW_THRESHOLD) {
                $scored[] = [
                    'type'         => 'fuzzy',
                    'score'        => round($score, 3),
                    'product_id'   => $product['id'],
                    'stock_code'   => $product['stock_code'],
                    'product_name' => $product['product_name'],
                    'model_code'   => $product['model_code'] ?? '',
                ];
            }
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $limit);
    }

    /**
     * similar_text (%) ve Levenshtein mesafesi kombinasyonu.
     */
    private function combinedScore(string $a, string $b): float
    {
        if ($a === $b) {
            return 1.0;
        }

        if ($a === '' || $b === '') {
            return 0.0;
        }

        similar_text($a, $b, $similarPct);
        $similarScore = $similarPct / 100.0;

        $lev      = levenshtein($a, $b);
        $maxLen   = max(strlen($a), strlen($b));
        $levScore = $maxLen > 0 ? 1.0 - ($lev / $maxLen) : 0.0;

        return ($similarScore + $levScore) / 2.0;
    }

    /**
     * Normalize: küçük harf, Türkçe karakter, parantez içi temizle.
     */
    private function normalize(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');

        $map  = [
            'ş' => 's', 'ğ' => 'g', 'ü' => 'u',
            'ö' => 'o', 'ç' => 'c', 'ı' => 'i', 'İ' => 'i',
        ];
        $text = strtr($text, $map);

        // Parantez içini çıkar (boyut/SKU bilgisi)
        $text = preg_replace('/\([^)]*\)/', '', $text);

        // Fazla boşluk
        return trim(preg_replace('/\s+/', ' ', $text));
    }
}
