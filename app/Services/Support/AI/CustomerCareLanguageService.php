<?php

namespace App\Services\Support\AI;

use App\Models\SupportChannel;
use App\Models\SupportLanguageQualityGate;

class CustomerCareLanguageService
{
    public const MIN_DETECTION_CONFIDENCE = 0.75;

    public function detect(string $text): array
    {
        $text = mb_strtolower(trim($text));
        if ($text === '') return ['language' => 'tr', 'confidence' => 1.0, 'source' => 'tenant_default'];

        $scores = [
            'tr' => $this->matches($text, ['merhaba', 'selam', 'kargo', 'sipariş', 'siparis', 'beden', 'iade', 'ürün', 'urun', 'stok', 'fiyat', 'nerde', 'abi', 'ya', 'değil', 'degil', 'mı', 'mi']),
            'en' => $this->matches($text, ['hello', 'hi', 'order', 'shipping', 'size', 'return', 'product', 'stock', 'price', 'where', 'please', 'thanks']),
            'de' => $this->matches($text, ['hallo', 'bestellung', 'versand', 'größe', 'grosse', 'rückgabe', 'produkt', 'preis', 'bitte', 'danke']),
        ];
        arsort($scores);
        $language = array_key_first($scores);
        $top = (int) reset($scores);
        $second = (int) (array_values($scores)[1] ?? 0);
        if ($top === 0) return ['language' => 'und', 'confidence' => 0.35, 'source' => 'heuristic'];
        $confidence = min(0.99, 0.70 + ($top * 0.08) + (($top - $second) * 0.04));
        return ['language' => $language, 'confidence' => round($confidence, 4), 'source' => 'heuristic'];
    }

    public function supportedLanguages(SupportChannel $channel): array
    {
        $rules = $channel->config_json['brand_voice']['language_rules'] ?? ['tr' => []];
        $languages = array_values(array_filter(array_keys((array) $rules), fn ($lang) => preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/', $lang)));
        return $languages ?: ['tr'];
    }

    public function hasPassedAutomationGate(int $storeId, string $language): bool
    {
        return SupportLanguageQualityGate::where('store_id', $storeId)
            ->where('language', $language)->where('passed', true)
            ->where('sample_size', '>=', 20)->where('critical_error_count', 0)
            ->where('average_score', '>=', 80)->where('source_accuracy', '>=', 95)
            ->where('evaluated_at', '>=', now()->subDays(30))
            ->exists();
    }

    private function matches(string $text, array $terms): int
    {
        return collect($terms)->filter(fn ($term) => preg_match('/(^|[^\p{L}])' . preg_quote($term, '/') . '([^\p{L}]|$)/u', $text) === 1)->count();
    }
}
