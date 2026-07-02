<?php

namespace App\Services\Marketplace;

use App\Models\TrendyolBoosterStoreWatchItem;
use App\Models\TrendyolBoosterTrendKeyword;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TrendyolBoosterTrendKeywordService
{
    private const SOURCE = 'Rakip mağaza başlıkları';

    /**
     * @return array{keywords: int, products: int, stores: int}
     */
    public function discoverFromCompetitors(int $userId): array
    {
        TrendyolBoosterTrendKeyword::query()
            ->where('user_id', $userId)
            ->where('source', 'ZOLM örnek set')
            ->delete();

        $items = TrendyolBoosterStoreWatchItem::query()
            ->with('storeWatch:id,store_id,store_name')
            ->where('user_id', $userId)
            ->where('is_removed', false)
            ->whereHas('storeWatch', fn (Builder $query) => $query->where('is_active', true))
            ->whereNotNull('title')
            ->where('title', '!=', '')
            ->get();

        if ($items->isEmpty()) {
            return ['keywords' => 0, 'products' => 0, 'stores' => 0];
        }

        /** @var array<string, array<string, mixed>> $signals */
        $signals = [];

        foreach ($items as $item) {
            $productKey = trim((string) $item->trendyol_product_id) ?: 'item-'.$item->id;
            $storeKey = trim((string) $item->storeWatch?->store_id) ?: 'store-'.$item->trendyol_booster_store_watch_id;
            $category = trim((string) $item->category_name) ?: 'Rakip katalog';
            $hasCampaign = count((array) $item->campaign_badges) > 0;

            foreach ($this->candidatePhrases((string) $item->title, (string) $item->brand, $category) as $keyword) {
                $key = hash('sha256', Str::lower($keyword));
                $signals[$key] ??= [
                    'keyword' => $keyword,
                    'products' => [],
                    'stores' => [],
                    'categories' => [],
                    'favorites' => 0,
                    'reviews' => 0,
                    'ratings' => [],
                    'campaign_products' => 0,
                    'samples' => [],
                ];

                $signals[$key]['products'][$productKey] = true;
                $signals[$key]['stores'][$storeKey] = true;
                $signals[$key]['categories'][$category] = ($signals[$key]['categories'][$category] ?? 0) + 1;
                $signals[$key]['favorites'] += max(0, (int) $item->favorite_count);
                $signals[$key]['reviews'] += max(0, (int) $item->review_count);
                $signals[$key]['campaign_products'] += $hasCampaign ? 1 : 0;

                if ((float) $item->rating > 0) {
                    $signals[$key]['ratings'][] = (float) $item->rating;
                }

                if (count($signals[$key]['samples']) < 3) {
                    $signals[$key]['samples'][] = [
                        'title' => Str::limit((string) $item->title, 120, ''),
                        'product_id' => (string) $item->trendyol_product_id,
                        'store' => (string) ($item->storeWatch?->store_name ?: 'Rakip mağaza'),
                    ];
                }
            }
        }

        $signals = array_filter(
            $signals,
            fn (array $signal): bool => $this->containsCommerceTerm(explode(' ', (string) $signal['keyword']))
        );

        $now = now();
        $savedIds = [];
        $totalProducts = max(1, $items->unique(fn ($item) => (string) ($item->trendyol_product_id ?: $item->id))->count());

        foreach ($signals as $key => $signal) {
            $productCount = count($signal['products']);
            $storeCount = count($signal['stores']);
            $averageRating = count($signal['ratings']) > 0
                ? round(array_sum($signal['ratings']) / count($signal['ratings']), 2)
                : null;
            $score = $this->signalScore(
                $productCount,
                $storeCount,
                (int) $signal['favorites'],
                (int) $signal['reviews'],
                $averageRating,
                (int) $signal['campaign_products'],
            );
            $category = (string) collect($signal['categories'])->sortDesc()->keys()->first();
            $model = TrendyolBoosterTrendKeyword::query()->firstOrNew([
                'user_id' => $userId,
                'keyword_hash' => $key,
                'category_name' => $category,
            ]);
            $previousScore = $model->exists ? (int) $model->signal_score : null;

            $model->forceFill([
                'keyword' => $signal['keyword'],
                'search_volume_min' => 0,
                'search_volume_max' => 0,
                'search_volume_label' => null,
                'competition_level' => $this->competitionLevel($productCount, $totalProducts),
                'signal_score' => $score,
                'previous_signal_score' => $previousScore,
                'product_count' => $productCount,
                'store_count' => $storeCount,
                'total_favorite_count' => (int) $signal['favorites'],
                'total_review_count' => (int) $signal['reviews'],
                'average_rating' => $averageRating,
                'campaign_product_count' => (int) $signal['campaign_products'],
                'trend_direction' => $this->trendDirection($previousScore, $score),
                'recommended_bid' => 0,
                'best_bid' => 0,
                'source' => self::SOURCE,
                'source_context' => ['sample_products' => $signal['samples']],
                'first_seen_at' => $model->first_seen_at ?: $now,
                'last_seen_at' => $now,
                'imported_at' => $now,
            ])->save();

            $savedIds[] = $model->id;
        }

        TrendyolBoosterTrendKeyword::query()
            ->where('user_id', $userId)
            ->where('source', self::SOURCE)
            ->when($savedIds !== [], fn (Builder $query) => $query->whereNotIn('id', $savedIds))
            ->delete();

        return [
            'keywords' => count($savedIds),
            'products' => $totalProducts,
            'stores' => $items->pluck('trendyol_booster_store_watch_id')->unique()->count(),
        ];
    }

    /**
     * @return array{total: int, rising_count: int, opportunity_count: int, source_product_count: int, source_store_count: int, last_scanned_at: mixed, rows: Collection<int, TrendyolBoosterTrendKeyword>}
     */
    public function dashboard(int $userId, string $search = '', string $competition = 'all'): array
    {
        $base = TrendyolBoosterTrendKeyword::query()->where('user_id', $userId);

        $rows = (clone $base)
            ->when(trim($search) !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('keyword', 'like', '%'.trim($search).'%')
                        ->orWhere('category_name', 'like', '%'.trim($search).'%');
                });
            })
            ->when($competition !== 'all', fn (Builder $query) => $query->where('competition_level', $competition))
            ->orderByDesc('signal_score')
            ->orderByDesc('product_count')
            ->orderBy('keyword')
            ->limit(100)
            ->get();

        $sourceItems = TrendyolBoosterStoreWatchItem::query()
            ->where('user_id', $userId)
            ->where('is_removed', false)
            ->whereHas('storeWatch', fn (Builder $query) => $query->where('is_active', true));

        return [
            'total' => (clone $base)->count(),
            'rising_count' => (clone $base)->where('trend_direction', 'rising')->count(),
            'opportunity_count' => (clone $base)->where('competition_level', 'low')->where('signal_score', '>=', 20)->count(),
            'source_product_count' => (clone $sourceItems)->distinct('trendyol_product_id')->count('trendyol_product_id'),
            'source_store_count' => (clone $sourceItems)->distinct('trendyol_booster_store_watch_id')->count('trendyol_booster_store_watch_id'),
            'last_scanned_at' => (clone $base)->max('imported_at'),
            'rows' => $rows,
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function candidatePhrases(string $title, string $brand, string $category): array
    {
        $brandTokens = array_flip($this->tokens($brand));
        $tokens = array_values(collect($this->tokens($title))
            ->reject(fn (string $token): bool => isset($brandTokens[$token]))
            ->reduce(function (array $carry, string $token): array {
                if (end($carry) !== $token) {
                    $carry[] = $token;
                }

                return $carry;
            }, []));
        $categoryTokens = $this->tokens($category);
        $phrases = [];

        foreach ($tokens as $index => $token) {
            $phrases[] = $token;

            if (isset($tokens[$index + 1])) {
                $phrases[] = $token.' '.$tokens[$index + 1];
            }

            if (isset($tokens[$index + 2])) {
                $phrases[] = $token.' '.$tokens[$index + 1].' '.$tokens[$index + 2];
            }
        }

        foreach ($categoryTokens as $token) {
            if (in_array($token, $tokens, true)) {
                $phrases[] = $token;
            }
        }

        return collect($phrases)
            ->map(fn (string $keyword): string => $this->normalizeKeyword($keyword))
            ->filter(fn (string $keyword): bool => mb_strlen($keyword) >= 3 && mb_strlen($keyword) <= 80)
            ->unique(fn (string $keyword): string => Str::lower($keyword))
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    protected function tokens(string $value): array
    {
        $value = Str::lower(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $value = str_replace("\u{0307}", '', $value);
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?: '';
        $stopwords = array_flip($this->stopwords());

        return collect(preg_split('/\s+/u', trim($value)) ?: [])
            ->map(fn (string $token): string => match (trim($token)) {
                'masasi', 'mmasasi' => 'masası',
                default => trim($token),
            })
            ->filter(fn (string $token): bool => mb_strlen($token) >= 3)
            ->reject(fn (string $token): bool => isset($stopwords[$token]))
            ->reject(fn (string $token): bool => preg_match('/^\d+(?:cm|mm|gr|kg|li|lü|lu)?$/u', $token) === 1)
            ->reject(fn (string $token): bool => preg_match('/^\d+x\d+/u', $token) === 1)
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    protected function stopwords(): array
    {
        return [
            've', 'ile', 'için', 'icin', 'bir', 'bu', 'çok', 'cok', 'yeni', 'özel', 'ozel', 'model',
            'adet', 'takım', 'takim', 'seti', 'set', 'renk', 'renkli', 'ürün', 'urun', 'plus', 'pro',
            'uygun', 'kaliteli', 'şık', 'sik', 'modern', 'dekoratif', 'kampanyalı', 'indirimli', 'ücretsiz',
            'bedava', 'kargo', 'hediyeli', 'boy', 'ebat', 'ölçü', 'olcu', 'kişilik', 'kisilik', 'tüm', 'tum',
        ];
    }

    /** @param array<int, string> $tokens */
    protected function containsCommerceTerm(array $tokens): bool
    {
        $terms = [
            'sandalye', 'masa', 'masası', 'sehpa', 'puf', 'bench', 'koltuk', 'berjer', 'tabure',
            'dolap', 'kitaplık', 'konsol', 'komodin', 'yatak', 'baza', 'başlık', 'raf', 'bank',
            'ayna', 'avize', 'lamba', 'halı', 'perde', 'çalışma', 'ofis', 'oyuncu', 'bahçe', 'mutfak',
        ];

        return count(array_intersect($tokens, $terms)) > 0;
    }

    protected function signalScore(
        int $productCount,
        int $storeCount,
        int $favorites,
        int $reviews,
        ?float $averageRating,
        int $campaignProducts,
    ): int {
        $score = min(45, $productCount * 9)
            + min(10, $storeCount * 5)
            + min(15, log10(1 + $favorites) * 4)
            + min(18, log10(1 + $reviews) * 5)
            + ($averageRating !== null ? min(7, ($averageRating / 5) * 7) : 0)
            + min(5, ($campaignProducts / max(1, $productCount)) * 5);

        return (int) round(min(100, $score));
    }

    protected function competitionLevel(int $productCount, int $totalProducts): string
    {
        $coverage = $productCount / max(1, $totalProducts);

        return match (true) {
            $productCount >= 5 || $coverage >= 0.25 => 'high',
            $productCount >= 2 => 'medium',
            default => 'low',
        };
    }

    protected function trendDirection(?int $previousScore, int $currentScore): string
    {
        if ($previousScore === null) {
            return 'new';
        }

        return match (true) {
            $currentScore >= $previousScore + 3 => 'rising',
            $currentScore <= $previousScore - 3 => 'falling',
            default => 'stable',
        };
    }

    protected function normalizeKeyword(string $keyword): string
    {
        $keyword = html_entity_decode(strip_tags($keyword), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $keyword = preg_replace('/\s+/u', ' ', $keyword) ?: '';

        return trim(Str::limit($keyword, 180, ''));
    }
}
