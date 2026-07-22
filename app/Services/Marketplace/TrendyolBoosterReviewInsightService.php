<?php

namespace App\Services\Marketplace;

use App\Models\TrendyolBoosterReview;
use App\Services\AIService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TrendyolBoosterReviewInsightService
{
    /** @var array<string, array<string, mixed>> */
    private const THEMES = [
        'quality' => [
            'label' => 'Ürün kalitesi',
            'positive' => ['kaliteli', 'sağlam', 'dayanıklı', 'özenli', 'kusursuz'],
            'negative' => ['kalitesiz', 'defolu', 'hatalı', 'kırıldı', 'bozuldu', 'çizik', 'yamuk', 'hasarlı'],
            'action' => ['type' => 'quality', 'title' => 'Ürün ve tedarikçi kalitesini kontrol et'],
        ],
        'size_fit' => [
            'label' => 'Ölçü ve beden',
            'positive' => ['tam oldu', 'bedeni tam', 'ölçüsü iyi', 'tam uydu'],
            'negative' => ['çok küçük', 'çok büyük', 'dar geldi', 'bol geldi', 'beden küçük', 'beden büyük', 'ölçü yanlış'],
            'action' => ['type' => 'listing', 'title' => 'Ölçü ve beden bilgisini listingde netleştir'],
        ],
        'material' => [
            'label' => 'Malzeme ve doku',
            'positive' => ['kumaşı güzel', 'malzemesi kaliteli', 'dokusu güzel', 'yumuşak'],
            'negative' => ['kumaşı kötü', 'malzemesi kötü', 'çok ince', 'çok sert', 'plastik gibi'],
            'action' => ['type' => 'listing', 'title' => 'Malzeme bilgisini ve yakın plan görselini güçlendir'],
        ],
        'visual_match' => [
            'label' => 'Renk ve görsel uyumu',
            'positive' => ['görseldeki gibi', 'fotoğraftaki gibi', 'rengi güzel', 'renk aynı'],
            'negative' => ['görseldeki gibi değil', 'fotoğraftakinden farklı', 'renk farklı', 'rengi soluk'],
            'action' => ['type' => 'ai_studio', 'title' => 'Gerçek renk ve kullanım bağlamını anlatan görsel hazırla'],
        ],
        'shipping_packaging' => [
            'label' => 'Kargo ve paketleme',
            'positive' => ['hızlı geldi', 'güzel paketlenmiş', 'paketleme iyi', 'sağlam paket'],
            'negative' => ['geç geldi', 'kargo kötü', 'paket yırtık', 'paketleme kötü', 'ezilmiş geldi', 'kırık geldi'],
            'action' => ['type' => 'operations', 'title' => 'Paketleme ve kargo operasyonunu incele'],
        ],
        'value' => [
            'label' => 'Fiyat ve değer algısı',
            'positive' => ['fiyat performans', 'parasına değer', 'fiyatına göre iyi', 'uygun fiyat'],
            'negative' => ['çok pahalı', 'parasını hak etmiyor', 'fiyatına değmez', 'gereksiz pahalı'],
            'action' => ['type' => 'pricing', 'title' => 'Fiyat ve değer önerisini yeniden değerlendir'],
        ],
        'usability' => [
            'label' => 'Kullanım deneyimi',
            'positive' => ['kullanışlı', 'çok rahat', 'pratik', 'kolay kullanılıyor'],
            'negative' => ['kullanışsız', 'rahatsız', 'kullanımı zor', 'işe yaramıyor'],
            'action' => ['type' => 'listing', 'title' => 'Kullanım biçimini açıklama ve görsellerde göster'],
        ],
        'odor' => [
            'label' => 'Koku',
            'positive' => [],
            'negative' => ['kötü koku', 'çok kokuyor', 'kimyasal kokusu', 'ağır koku'],
            'action' => ['type' => 'quality', 'title' => 'Malzeme ve depolama kaynaklı koku riskini incele'],
        ],
    ];

    public function __construct(protected AIService $aiService) {}

    /** @return array<string, mixed> */
    public function analyze(int $userId, ?int $reviewSourceId = null, ?string $trendyolProductId = null): array
    {
        $reviews = TrendyolBoosterReview::query()
            ->where('user_id', $userId)
            ->when($reviewSourceId !== null, fn ($query) => $query->where('review_source_id', $reviewSourceId))
            ->when(filled($trendyolProductId), fn ($query) => $query->where('trendyol_product_id', $trendyolProductId))
            ->whereIn('status', ['approved', 'pending'])
            ->where('is_spam', false)
            ->whereNotNull('comment')
            ->where('comment', '!=', '')
            ->latest('reviewed_at')
            ->limit(300)
            ->get();

        return $this->analyzeCollection($reviews, $reviewSourceId, $trendyolProductId);
    }

    /**
     * @param Collection<int, TrendyolBoosterReview> $reviews
     * @return array<string, mixed>
     */
    public function analyzeCollection(Collection $reviews, ?int $reviewSourceId = null, ?string $trendyolProductId = null): array
    {
        $reviews = $reviews
            ->filter(fn (TrendyolBoosterReview $review): bool => ! $review->is_spam && trim((string) $review->comment) !== '')
            ->values();
        $evidence = $this->evidenceLedger($reviews);
        $total = $reviews->count();
        $negative = $reviews->where('rating', '<=', 2)->count();
        $positive = $reviews->where('rating', '>=', 4)->count();
        $average = $total > 0 ? round((float) $reviews->avg('rating'), 2) : 0.0;
        $themes = $this->themeSummary($reviews, $evidence);
        $trend = $this->trend($reviews);
        $negativeRate = $total > 0 ? round(($negative / $total) * 100, 1) : 0.0;
        $largestComplaintShare = (float) collect($themes['complaints'])->max('share_percent');
        $riskScore = (int) round(min(100, ($negativeRate * 0.65) + (max(0, 3.5 - $average) * 13) + ($largestComplaintShare * 0.15) + ($trend['direction'] === 'worsening' ? 8 : 0)));
        $confidenceScore = $this->confidenceScore($total, $reviews->pluck('trendyol_product_id')->unique()->count());

        $result = [
            'scope' => [
                'review_source_id' => $reviewSourceId,
                'trendyol_product_id' => $trendyolProductId,
                'label' => filled($trendyolProductId)
                    ? (string) ($reviews->first()?->product_title ?: 'Seçili ürün')
                    : 'Seçili mağazanın yorum portföyü',
            ],
            'sample_count' => $total,
            'product_count' => $reviews->pluck('trendyol_product_id')->filter()->unique()->count(),
            'average_rating' => $average,
            'positive_count' => $positive,
            'negative_count' => $negative,
            'negative_rate' => $negativeRate,
            'risk_score' => $riskScore,
            'risk_label' => $riskScore >= 65 ? 'Yüksek risk' : ($riskScore >= 35 ? 'İzlenmeli' : 'Kontrollü'),
            'confidence_score' => $confidenceScore,
            'confidence_label' => $confidenceScore >= 75 ? 'Güçlü örneklem' : ($confidenceScore >= 50 ? 'Orta örneklem' : 'Sınırlı örneklem'),
            'trend' => $trend,
            'praises' => $themes['praises'],
            'complaints' => $themes['complaints'],
            'actions' => $this->actions($themes['complaints']),
            'ai_findings' => [],
            'summary' => $this->fallbackSummary($total, $average, $negativeRate, $themes['complaints']),
            'provider' => 'evidence_engine',
            'generated_at' => now()->toIso8601String(),
            'evidence_note' => 'Analiz yalnız seçili kaynakta bulunan spam dışı, silinmemiş yorumlara dayanır. Tema eşleşmeleri ve AI bulguları karar desteğidir; kesin iade veya kalite sonucu değildir.',
        ];

        if ($total >= 5 && trim((string) config('ai.api_key', '')) !== '') {
            $ai = $this->aiAnalysis($result, $evidence);
            if ($ai !== null) {
                $result['summary'] = $ai['summary'];
                $result['ai_findings'] = $ai['findings'];
                $result['actions'] = $this->mergeAiActions($result['actions'], $ai['actions']);
                $result['provider'] = (string) config('ai.provider', 'ai');
            }
        }

        return $result;
    }

    /** @param Collection<int, TrendyolBoosterReview> $reviews */
    private function evidenceLedger(Collection $reviews): Collection
    {
        return $reviews->take(80)->values()->map(fn (TrendyolBoosterReview $review, int $index): array => [
            'id' => 'Y'.($index + 1),
            'review_id' => $review->id,
            'rating' => (int) $review->rating,
            'comment' => Str::limit(trim(strip_tags((string) $review->comment)), 500, ''),
            'reviewed_at' => $review->reviewed_at?->toDateString(),
        ]);
    }

    /**
     * @param Collection<int, TrendyolBoosterReview> $reviews
     * @param Collection<int, array<string, mixed>> $evidence
     * @return array{praises: array<int, array<string, mixed>>, complaints: array<int, array<string, mixed>>}
     */
    private function themeSummary(Collection $reviews, Collection $evidence): array
    {
        $result = ['praises' => [], 'complaints' => []];
        $evidenceByReview = $evidence->keyBy('review_id');

        foreach (self::THEMES as $key => $definition) {
            foreach (['positive' => 'praises', 'negative' => 'complaints'] as $polarity => $bucket) {
                $matches = $reviews->filter(function (TrendyolBoosterReview $review) use ($definition, $polarity): bool {
                    $matchesPolarity = $this->containsAny((string) $review->comment, $definition[$polarity]);

                    return $matchesPolarity && ($polarity !== 'positive' || ! $this->containsAny((string) $review->comment, $definition['negative']));
                });
                if ($matches->isEmpty()) {
                    continue;
                }

                $evidenceRows = $matches->take(3)->map(function (TrendyolBoosterReview $review) use ($evidenceByReview): array {
                    $row = $evidenceByReview->get($review->id);

                    return [
                        'id' => $row['id'] ?? null,
                        'rating' => (int) $review->rating,
                        'snippet' => Str::limit(trim((string) $review->comment), 150),
                    ];
                })->filter(fn (array $row): bool => filled($row['id']))->values()->all();

                $result[$bucket][] = [
                    'key' => $key,
                    'label' => $definition['label'],
                    'count' => $matches->count(),
                    'share_percent' => $reviews->isNotEmpty() ? round(($matches->count() / $reviews->count()) * 100, 1) : 0,
                    'evidence' => $evidenceRows,
                    'action' => $definition['action'],
                ];
            }
        }

        foreach (['praises', 'complaints'] as $bucket) {
            $result[$bucket] = collect($result[$bucket])->sortByDesc('count')->take(6)->values()->all();
        }

        return $result;
    }

    private function containsAny(string $comment, array $needles): bool
    {
        if ($needles === []) {
            return false;
        }

        $normalized = Str::lower(preg_replace('/\s+/u', ' ', trim($comment)) ?: '');

        return Str::contains($normalized, $needles);
    }

    /** @param Collection<int, TrendyolBoosterReview> $reviews */
    private function trend(Collection $reviews): array
    {
        $recent = $reviews->filter(fn (TrendyolBoosterReview $review): bool => $review->reviewed_at?->gte(now()->subDays(30)) ?? false);
        $previous = $reviews->filter(fn (TrendyolBoosterReview $review): bool => ($review->reviewed_at?->lt(now()->subDays(30)) ?? false) && ($review->reviewed_at?->gte(now()->subDays(60)) ?? false));

        if ($recent->count() < 3 || $previous->count() < 3) {
            return ['direction' => 'insufficient', 'label' => 'Trend için veri bekleniyor', 'delta_percent' => null, 'recent_count' => $recent->count(), 'previous_count' => $previous->count()];
        }

        $recentRate = ($recent->where('rating', '<=', 2)->count() / $recent->count()) * 100;
        $previousRate = ($previous->where('rating', '<=', 2)->count() / $previous->count()) * 100;
        $delta = round($recentRate - $previousRate, 1);
        $direction = $delta >= 5 ? 'worsening' : ($delta <= -5 ? 'improving' : 'stable');

        return [
            'direction' => $direction,
            'label' => match ($direction) {
                'worsening' => 'Olumsuz yorum oranı yükseliyor',
                'improving' => 'Olumsuz yorum oranı düşüyor',
                default => 'Yorum eğilimi dengeli',
            },
            'delta_percent' => $delta,
            'recent_count' => $recent->count(),
            'previous_count' => $previous->count(),
        ];
    }

    private function confidenceScore(int $sampleCount, int $productCount): int
    {
        $sampleScore = match (true) {
            $sampleCount >= 100 => 90,
            $sampleCount >= 50 => 82,
            $sampleCount >= 20 => 70,
            $sampleCount >= 10 => 58,
            $sampleCount >= 5 => 42,
            default => 25,
        };

        return min(95, $sampleScore + min(5, max(0, $productCount - 1)));
    }

    /** @param array<int, array<string, mixed>> $complaints */
    private function actions(array $complaints): array
    {
        return collect($complaints)->map(fn (array $theme): array => [
            'type' => $theme['action']['type'],
            'title' => $theme['action']['title'],
            'reason' => $theme['label'].' teması '.$theme['count'].' yorumda görüldü.',
            'priority' => $theme['share_percent'] >= 20 ? 'high' : ($theme['share_percent'] >= 10 ? 'medium' : 'low'),
            'evidence_ids' => collect($theme['evidence'])->pluck('id')->filter()->values()->all(),
        ])->unique(fn (array $action): string => $action['type'].'|'.$action['title'])->take(6)->values()->all();
    }

    /** @param array<int, array<string, mixed>> $complaints */
    private function fallbackSummary(int $total, float $average, float $negativeRate, array $complaints): string
    {
        if ($total === 0) {
            return 'Analiz için uygun yorum bulunamadı. Önce seçili mağazayı tarayın veya yorum filtrelerini kontrol edin.';
        }

        $leading = $complaints[0]['label'] ?? null;
        $summary = "{$total} yorumda ortalama puan ".number_format($average, 1, ',', '.')."; düşük puanlı yorum oranı %".number_format($negativeRate, 1, ',', '.').'.';

        return $leading ? $summary.' En sık yakalanan geliştirme alanı: '.$leading.'.' : $summary.' Tekrarlayan belirgin bir şikâyet teması yakalanmadı.';
    }

    /**
     * @param array<string, mixed> $baseline
     * @param Collection<int, array<string, mixed>> $evidence
     * @return array<string, mixed>|null
     */
    private function aiAnalysis(array $baseline, Collection $evidence): ?array
    {
        $ledger = $evidence->map(fn (array $row): string => sprintf('[%s] %d/5 | %s', $row['id'], $row['rating'], $row['comment']))->implode("\n");
        $allowedIds = $evidence->pluck('id')->all();
        $prompt = <<<PROMPT
Aşağıdaki Trendyol yorum kanıtlarını Türkçe analiz et. Yalnız verilen yorumları kullan; sayı veya neden uydurma. Müşteri adı/kişisel veri üretme. Her bulgu ve aksiyon en az bir [Y#] kanıtına dayanmalı.

Yalnız geçerli JSON döndür:
{"summary":"en fazla 3 cümle","findings":[{"type":"praise|complaint","label":"kısa başlık","reason":"kanıta bağlı açıklama","evidence_ids":["Y1"]}],"actions":[{"type":"listing|ai_studio|quality|operations|pricing","title":"uygulanabilir aksiyon","reason":"neden","priority":"high|medium|low","evidence_ids":["Y1"]}]}

KANITLAR:
{$ledger}
PROMPT;
        $raw = trim($this->aiService->ask('analyst', $prompt));
        if ($raw === '' || Str::startsWith($raw, ['❌', 'Bağlantı hatası:'])) {
            return null;
        }

        $raw = preg_replace('/^```(?:json)?\s*|\s*```$/u', '', $raw) ?: $raw;
        $decoded = json_decode($raw, true);
        if (! is_array($decoded) || blank($decoded['summary'] ?? null)) {
            return null;
        }

        $validateRows = function (mixed $rows) use ($allowedIds): array {
            return collect(is_array($rows) ? $rows : [])->filter(function (mixed $row) use ($allowedIds): bool {
                if (! is_array($row) || blank($row['title'] ?? $row['label'] ?? null)) {
                    return false;
                }
                $ids = array_values(array_intersect($allowedIds, array_map('strval', (array) ($row['evidence_ids'] ?? []))));

                return $ids !== [];
            })->map(function (array $row) use ($allowedIds): array {
                $row['evidence_ids'] = array_values(array_intersect($allowedIds, array_map('strval', (array) ($row['evidence_ids'] ?? []))));
                $row['reason'] = Str::limit(strip_tags((string) ($row['reason'] ?? '')), 300, '');
                if (isset($row['title'])) {
                    $row['title'] = Str::limit(strip_tags((string) $row['title']), 140, '');
                }
                if (isset($row['label'])) {
                    $row['label'] = Str::limit(strip_tags((string) $row['label']), 100, '');
                }

                return $row;
            })->take(6)->values()->all();
        };

        return [
            'summary' => Str::limit(strip_tags((string) $decoded['summary']), 700, ''),
            'findings' => $validateRows($decoded['findings'] ?? []),
            'actions' => $validateRows($decoded['actions'] ?? []),
        ];
    }

    /** @param array<int, array<string, mixed>> $base @param array<int, array<string, mixed>> $ai */
    private function mergeAiActions(array $base, array $ai): array
    {
        return collect($ai)
            ->concat($base)
            ->filter(fn (array $action): bool => in_array((string) ($action['type'] ?? ''), ['listing', 'ai_studio', 'quality', 'operations', 'pricing'], true))
            ->map(function (array $action): array {
                $action['priority'] = in_array((string) ($action['priority'] ?? ''), ['high', 'medium', 'low'], true) ? $action['priority'] : 'medium';

                return $action;
            })
            ->unique(fn (array $action): string => ($action['type'] ?? '').'|'.Str::lower((string) ($action['title'] ?? '')))
            ->take(6)
            ->values()
            ->all();
    }
}
