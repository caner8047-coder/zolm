<?php

namespace App\Services\Marketplace;

use App\Models\TrendyolBoosterProduct;
use App\Services\AIService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TrendyolBoosterDecisionAssistantService
{
    public function __construct(protected AIService $aiService) {}

    /** @return array<string, mixed> */
    public function answer(int $userId, string $question): array
    {
        $question = trim(Str::limit(strip_tags($question), 400, ''));
        $products = TrendyolBoosterProduct::query()
            ->where('user_id', $userId)
            ->with('latestSnapshot')
            ->latest('updated_at')
            ->limit(25)
            ->get();
        $evidence = $this->buildEvidence($products);
        $fallback = $this->fallbackAnswer($question, $evidence);
        $apiConfigured = trim((string) config('ai.api_key', '')) !== '';

        if (! $apiConfigured || $evidence->isEmpty()) {
            return $fallback + ['provider' => 'evidence_engine'];
        }

        $ledger = $evidence->map(fn (array $row): string => sprintf(
            '[%s] %s | fiyat %.2f TL | maliyet %s | net kâr %s | marj %s | risk %d | kalite %d | günlük satış %s',
            $row['source_id'],
            $row['title'],
            $row['sale_price'],
            $row['cost_ready'] ? number_format($row['cogs'], 2, '.', '') : 'yok',
            $row['cost_ready'] ? number_format($row['net_profit'], 2, '.', '') : 'hesaplanamaz',
            $row['cost_ready'] ? number_format($row['net_margin'], 2, '.', '').'%' : 'hesaplanamaz',
            $row['risk_score'],
            $row['quality_score'],
            $row['estimated_daily_sales'] ?? 'ölçülmedi',
        ))->implode("\n");
        $prompt = <<<PROMPT
Yalnız aşağıdaki ZOLM kanıt defterini kullanarak Türkçe, kısa ve uygulanabilir yanıt ver.
Kurallar: Kaynak dışı sayı üretme. Her maddede [K1] biçiminde kaynak kimliği kullan. Maliyet yoksa kâr/marj iddiası kurma. Tahmini satışın kesin sipariş olmadığını belirt. En fazla 6 madde yaz.

KANIT DEFTERİ:
{$ledger}

KULLANICI SORUSU:
{$question}
PROMPT;
        $answer = trim($this->aiService->ask('analyst', $prompt));

        if ($answer === '' || Str::startsWith($answer, ['❌', 'Bağlantı hatası:']) || ! preg_match('/\[K\d+\]/', $answer)) {
            return $fallback + ['provider' => 'evidence_engine'];
        }

        return [
            'answer' => Str::limit($answer, 5000, ''),
            'sources' => $evidence->take(8)->all(),
            'provider' => (string) config('ai.provider', 'ai'),
            'generated_at' => now()->toIso8601String(),
            'disclaimer' => 'Yanıt karar desteğidir; eksik maliyet ve tahmini satış sinyalleri kesin finansal sonuç değildir.',
        ];
    }

    /** @param Collection<int, TrendyolBoosterProduct> $products */
    public function buildEvidence(Collection $products): Collection
    {
        return $products->values()->map(function (TrendyolBoosterProduct $product, int $index): array {
            $costReady = (float) $product->cogs > 0;

            return [
                'source_id' => 'K'.($index + 1),
                'product_id' => $product->id,
                'title' => (string) ($product->title ?: 'Trendyol ürünü'),
                'sale_price' => (float) $product->sale_price,
                'cogs' => (float) $product->cogs,
                'net_profit' => $costReady ? (float) $product->net_profit : null,
                'net_margin' => $costReady ? (float) $product->net_margin : null,
                'cost_ready' => $costReady,
                'risk_score' => (int) $product->risk_score,
                'quality_score' => (int) $product->data_quality_score,
                'estimated_daily_sales' => $product->estimated_daily_sales !== null ? (float) $product->estimated_daily_sales : null,
                'source_url' => (string) ($product->source_url ?: ''),
            ];
        });
    }

    /** @param Collection<int, array<string, mixed>> $evidence */
    public function fallbackAnswer(string $question, Collection $evidence): array
    {
        if ($evidence->isEmpty()) {
            $answer = 'Yanıt üretmek için henüz ürün kanıtı yok. Önce en az bir Trendyol ürününü analiz edin.';
        } else {
            $ready = $evidence->where('cost_ready', true)->sortByDesc('net_profit')->first();
            $risk = $evidence->sortByDesc('risk_score')->first();
            $parts = [];
            $parts[] = $ready
                ? '['.$ready['source_id'].'] Finans verisi hazır ürünler içinde en yüksek görünen net kâr '.number_format((float) $ready['net_profit'], 2, ',', '.').' TL ile “'.$ready['title'].'”.'
                : 'Kâr karşılaştırması yapılamıyor; ürünlerde doğrulanmış maliyet eksik.';
            if ($risk && (int) $risk['risk_score'] > 0) {
                $parts[] = '['.$risk['source_id'].'] En yüksek risk skoru '.$risk['risk_score'].'/100; fiyat ve veri kaynaklarını yeniden doğrulayın.';
            }
            $parts[] = 'Tahmini satış metrikleri sipariş kaydı değil, gözlenen pazar sinyalidir.';
            $answer = implode("\n\n", $parts);
        }

        return [
            'answer' => $answer,
            'sources' => $evidence->take(8)->all(),
            'generated_at' => now()->toIso8601String(),
            'disclaimer' => 'Yanıt karar desteğidir; eksik maliyet ve tahmini satış sinyalleri kesin finansal sonuç değildir.',
        ];
    }
}
