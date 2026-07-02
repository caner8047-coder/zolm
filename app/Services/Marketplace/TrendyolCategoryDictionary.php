<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class TrendyolCategoryDictionary
{
    /** @var array<int, array<string, mixed>>|null */
    protected ?array $entries = null;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function entries(): array
    {
        if ($this->entries !== null) {
            return $this->entries;
        }

        $path = base_path('resources/data/trendyol_category_dictionary.json');
        if (! is_file($path)) {
            return $this->entries = [];
        }

        $json = json_decode((string) file_get_contents($path), true);
        $entries = Arr::get($json, 'entries', []);

        return $this->entries = is_array($entries) ? array_values($entries) : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolve(string $query): ?array
    {
        $normalized = $this->normalize($query);
        if (mb_strlen($normalized) < 2) {
            return null;
        }

        $best = null;
        foreach ($this->entries() as $entry) {
            $terms = (array) ($entry['normalized_terms'] ?? []);
            foreach ($terms as $index => $term) {
                if (! is_string($term) || $term === '') {
                    continue;
                }

                $score = $this->score($normalized, $term);
                if ($score <= 0) {
                    continue;
                }

                if ($best === null || $score > $best['score']) {
                    $best = [
                        'score' => $score,
                        'matched_term' => (string) (($entry['terms'] ?? [])[$index] ?? $term),
                        'category' => (string) ($entry['category'] ?? ''),
                        'sub_category' => (string) ($entry['sub_category'] ?? ''),
                        'product_group' => (string) ($entry['product_group'] ?? ''),
                        'entry' => $entry,
                    ];
                }
            }
        }

        return $best !== null && $best['score'] >= 40 ? $best : null;
    }

    protected function score(string $query, string $term): float
    {
        if ($query === $term) {
            return 1000 + mb_strlen($term);
        }

        if (mb_strlen($query) >= 3 && Str::contains($term, $query)) {
            return 820 + mb_strlen($query);
        }

        if (mb_strlen($term) >= 3 && Str::contains($query, $term)) {
            return 760 + mb_strlen($term);
        }

        $queryTokens = array_values(array_filter(explode(' ', $query)));
        $termTokens = array_values(array_filter(explode(' ', $term)));
        if ($queryTokens === [] || $termTokens === []) {
            return 0;
        }

        $overlap = count(array_intersect($queryTokens, $termTokens));
        if ($overlap === 0) {
            return 0;
        }

        $coverage = $overlap / max(1, count($queryTokens));

        return 35 + ($coverage * 140) + min(25, mb_strlen($term) / 2);
    }

    protected function normalize(string $value): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($value))) ?: '');
    }
}
