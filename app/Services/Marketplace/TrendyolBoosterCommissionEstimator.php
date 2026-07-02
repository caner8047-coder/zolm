<?php

namespace App\Services\Marketplace;

use App\Models\ChannelOrderItem;
use App\Models\TrendyolBoosterCommissionRate;
use App\Models\TrendyolBoosterProduct;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TrendyolBoosterCommissionEstimator
{
    public function __construct(protected TrendyolSellerLevelService $sellerLevelService) {}

    /**
     * @param  array<string, mixed>  $productData
     * @return array<string, mixed>
     */
    public function estimate(int $userId, array $productData, ?TrendyolBoosterProduct $tracked = null): array
    {
        $tracked?->loadMissing(['listing', 'product']);
        $sellerScore = $this->nullableFloat($productData['seller_score'] ?? null);
        $level = $this->sellerLevelService->resolve($userId, $productData, $tracked);

        $exact = $this->exactProductRate($userId, $tracked);
        if ($exact !== null) {
            return $this->result(
                $exact['rate'],
                $exact['source'],
                $exact['label'],
                $exact['confidence'],
                $sellerScore,
                $level,
                null,
                [],
                'Bu oran ZOLM içindeki ürün veya gerçekleşmiş sipariş verisinden alındı.'
            );
        }

        $historical = $this->historicalCategoryRate($userId, $productData, $tracked);
        if ($historical !== null) {
            return $this->result(
                $historical['rate'],
                'historical_category',
                'Gerçekleşmiş kategori siparişleri',
                $historical['confidence'],
                $sellerScore,
                $level,
                null,
                [],
                $historical['sample_count'].' gerçek siparişteki komisyon oranının medyanı kullanıldı.'
            );
        }

        $rows = $this->rateRows($userId);
        $matched = $this->bestTariffMatch($rows, $productData);

        if ($matched !== null) {
            /** @var TrendyolBoosterCommissionRate $row */
            $row = $matched['row'];
            $rate = $this->rateForLevel($row, $level['level']);
            $confidence = min(
                in_array($level['source'], ['page', 'connected_store_180d'], true) ? 92 : 76,
                48 + ($matched['score'] * 0.45) + ($level['level'] !== null ? 8 : 0)
            );

            return $this->result(
                $rate,
                $level['level'] !== null ? 'category_tariff_level' : 'category_tariff',
                'Kategori komisyon tarifesi',
                round($confidence, 1),
                $sellerScore,
                $level,
                $row,
                $this->levelAlternatives($row),
                $level['level'] !== null
                    ? 'Kategori tarifesi, doğrulanan satıcı seviyesine göre seçildi. '.$level['note']
                    : 'Kategori tarifesinin standart oranı kullanıldı. '.$level['note']
            );
        }

        $median = $this->median($rows->pluck('commission_rate')->map(fn ($rate): float => (float) $rate));

        return $this->result(
            $median,
            'tariff_median',
            'Genel tarife medyanı',
            $median !== null ? 25 : 0,
            $sellerScore,
            $level,
            null,
            [],
            'Kategori eşleşmediği için yalnızca düşük güvenli genel tarife medyanı önerildi.'
        );
    }

    /** @return array{rate: float, source: string, label: string, confidence: float}|null */
    protected function exactProductRate(int $userId, ?TrendyolBoosterProduct $tracked): ?array
    {
        if (! $tracked) {
            return null;
        }

        $listingRate = (float) ($tracked->listing?->commission_rate ?? 0);
        if ($listingRate > 0) {
            return [
                'rate' => $listingRate,
                'source' => 'channel_listing',
                'label' => 'Eşleşmiş Trendyol ilanı',
                'confidence' => 99,
            ];
        }

        $productRate = (float) ($tracked->product?->commission_rate ?? 0);
        if ($productRate > 0) {
            return [
                'rate' => $productRate,
                'source' => 'product_master',
                'label' => 'ZOLM ürün kartı',
                'confidence' => 96,
            ];
        }

        if (! Schema::hasTable('channel_order_items') || $tracked->mp_product_id === null) {
            return null;
        }

        $rates = ChannelOrderItem::query()
            ->where('mp_product_id', $tracked->mp_product_id)
            ->where('commission_rate', '>', 0)
            ->whereHas('store', fn ($query) => $query
                ->where('user_id', $userId)
                ->where('marketplace', 'trendyol'))
            ->latest('id')
            ->limit(100)
            ->pluck('commission_rate')
            ->map(fn ($rate): float => (float) $rate);
        $median = $this->median($rates);

        return $median !== null ? [
            'rate' => $median,
            'source' => 'historical_product',
            'label' => 'Gerçekleşmiş ürün siparişleri',
            'confidence' => 98,
        ] : null;
    }

    /** @return array{rate: float, confidence: float, sample_count: int}|null */
    protected function historicalCategoryRate(
        int $userId,
        array $productData,
        ?TrendyolBoosterProduct $tracked
    ): ?array {
        if (! Schema::hasTable('channel_order_items') || ! Schema::hasTable('mp_products')) {
            return null;
        }

        $contextTokens = $this->tokens($this->contextText($productData, $tracked));
        if ($contextTokens === []) {
            return null;
        }

        $items = ChannelOrderItem::query()
            ->with('product:id,product_name,category_name')
            ->where('commission_rate', '>', 0)
            ->whereNotNull('mp_product_id')
            ->whereHas('store', fn ($query) => $query
                ->where('user_id', $userId)
                ->where('marketplace', 'trendyol'))
            ->latest('id')
            ->limit(300)
            ->get(['id', 'mp_product_id', 'commission_rate', 'product_name']);

        $rates = $items
            ->filter(function (ChannelOrderItem $item) use ($contextTokens): bool {
                $text = trim(($item->product?->category_name ?? '').' '.($item->product?->product_name ?? $item->product_name));
                $tokens = $this->tokens($text);

                return count(array_intersect($contextTokens, $tokens)) >= 2;
            })
            ->pluck('commission_rate')
            ->map(fn ($rate): float => (float) $rate);
        $median = $this->median($rates);

        return $median !== null && $rates->count() >= 3 ? [
            'rate' => $median,
            'confidence' => min(94, 72 + ($rates->count() * 2)),
            'sample_count' => $rates->count(),
        ] : null;
    }

    /** @return Collection<int, TrendyolBoosterCommissionRate> */
    protected function rateRows(int $userId): Collection
    {
        if (! Schema::hasTable('trendyol_booster_commission_rates')) {
            return collect();
        }

        return TrendyolBoosterCommissionRate::query()
            ->where(fn ($query) => $query->where('user_id', $userId)->orWhereNull('user_id'))
            ->orderByRaw('user_id IS NULL')
            ->latest('imported_at')
            ->limit(1000)
            ->get();
    }

    /**
     * @param  Collection<int, TrendyolBoosterCommissionRate>  $rows
     * @return array{row: TrendyolBoosterCommissionRate, score: float}|null
     */
    protected function bestTariffMatch(Collection $rows, array $productData): ?array
    {
        $context = Str::lower($this->contextText($productData));
        $contextTokens = $this->tokens($context);

        $ranked = $rows->map(function (TrendyolBoosterCommissionRate $row) use ($context, $contextTokens): array {
            $categoryTokens = $this->tokens((string) $row->category_name);
            $subCategoryTokens = $this->tokens((string) $row->sub_category_name);
            $groupTokens = $this->tokens((string) $row->product_group);
            $occurrenceScore = collect($groupTokens)
                ->sum(fn (string $token): int => min(3, substr_count($context, $token)) * 8);
            $score = min(64, $occurrenceScore)
                + ($this->overlap($contextTokens, $groupTokens) * 20)
                + ($this->overlap($contextTokens, $subCategoryTokens) * 10)
                + ($this->overlap($contextTokens, $categoryTokens) * 6)
                + ($row->user_id !== null ? 4 : 0);

            return ['row' => $row, 'score' => round($score, 2)];
        })->sortByDesc('score')->first();

        return $ranked && $ranked['score'] >= 18 ? $ranked : null;
    }

    protected function rateForLevel(TrendyolBoosterCommissionRate $row, ?int $level): float
    {
        $column = $level !== null ? 'level_'.$level.'_rate' : null;
        $levelRate = $column ? (float) ($row->{$column} ?? 0) : 0;

        return $levelRate > 0 ? $levelRate : (float) $row->commission_rate;
    }

    /** @return array<int, array{level: int, rate: float}> */
    protected function levelAlternatives(TrendyolBoosterCommissionRate $row): array
    {
        $alternatives = [];

        foreach ([5, 4, 3, 2, 1] as $level) {
            $rate = (float) ($row->{'level_'.$level.'_rate'} ?? 0);
            if ($rate > 0) {
                $alternatives[] = ['level' => $level, 'rate' => $rate];
            }
        }

        return $alternatives;
    }

    /** @return array<string, mixed> */
    protected function result(
        ?float $rate,
        string $source,
        string $sourceLabel,
        float $confidence,
        ?float $sellerScore,
        array $level,
        ?TrendyolBoosterCommissionRate $row,
        array $alternatives,
        string $note,
    ): array {
        return [
            'rate' => $rate !== null ? round($rate, 2) : null,
            'source' => $source,
            'source_label' => $sourceLabel,
            'confidence' => round(max(0, min(100, $confidence)), 1),
            'seller_score' => $sellerScore,
            'seller_level' => $level['level'],
            'seller_level_source' => $level['source'],
            'seller_level_confidence' => $level['confidence'],
            'seller_status' => $level['status'] ?? 'unknown',
            'seller_metrics' => $level['metrics'] ?? null,
            'seller_level_category_rule' => $level['matched_category_rule'] ?? null,
            'seller_level_note' => $level['note'] ?? null,
            'matched_category' => $row?->category_name,
            'matched_sub_category' => $row?->sub_category_name,
            'matched_product_group' => $row?->product_group,
            'alternatives' => $alternatives,
            'note' => $note,
        ];
    }

    protected function contextText(array $productData, ?TrendyolBoosterProduct $tracked = null): string
    {
        $attributes = collect((array) ($productData['attributes'] ?? []))
            ->filter(fn ($attribute): bool => is_array($attribute))
            ->map(fn (array $attribute): string => trim(($attribute['name'] ?? '').' '.($attribute['value'] ?? '')))
            ->implode(' ');

        return trim(implode(' ', array_filter([
            $productData['category_name'] ?? null,
            $productData['title'] ?? null,
            $tracked?->category_name,
            $tracked?->title,
            $attributes,
        ])));
    }

    /** @return array<int, string> */
    protected function tokens(string $value): array
    {
        $value = Str::lower(Str::ascii($value));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?: '';
        $stopWords = ['ve', 'ile', 'icin', 'urun', 'urunler', 'diger', 'cok', 'set', 'takim'];

        return collect(explode(' ', $value))
            ->map(fn (string $token): string => trim($token))
            ->filter(fn (string $token): bool => strlen($token) >= 3 && ! in_array($token, $stopWords, true))
            ->unique()
            ->values()
            ->all();
    }

    /** @param array<int, string> $left @param array<int, string> $right */
    protected function overlap(array $left, array $right): float
    {
        if ($left === [] || $right === []) {
            return 0;
        }

        return count(array_intersect($left, $right)) / max(1, min(count($right), 4));
    }

    /** @param Collection<int, float> $values */
    protected function median(Collection $values): ?float
    {
        $values = $values->filter(fn (float $value): bool => $value > 0)->sort()->values();
        $count = $values->count();

        if ($count === 0) {
            return null;
        }

        $middle = intdiv($count, 2);

        return round($count % 2 === 1
            ? (float) $values[$middle]
            : (((float) $values[$middle - 1] + (float) $values[$middle]) / 2), 2);
    }

    protected function nullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
