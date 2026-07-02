<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Str;

class TrendyolCategoryLookupService
{
    /**
     * Finds the category ID for a given search query using the local JSON map.
     */
    public function findIdForQuery(string $query): ?int
    {
        $query = $this->normalize($query);
        if ($query === '') {
            return null;
        }

        $mapFile = storage_path('app/trendyol_categories_map.json');
        if (! file_exists($mapFile)) {
            return null;
        }

        $map = json_decode(file_get_contents($mapFile), true);
        if (! is_array($map)) {
            return null;
        }

        return $map[$query] ?? null;
    }

    protected function normalize(string $value): string
    {
        // 1. Convert specific Turkish characters correctly before ascii conversion
        $search  = ['ı', 'ğ', 'ü', 'ş', 'ö', 'ç', 'I', 'Ğ', 'Ü', 'Ş', 'Ö', 'Ç'];
        $replace = ['i', 'g', 'u', 's', 'o', 'c', 'i', 'g', 'u', 's', 'o', 'c'];
        $value = str_replace($search, $replace, mb_strtolower($value, 'UTF-8'));
        
        $value = Str::ascii($value);
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? '';
        return trim($value);
    }
}
