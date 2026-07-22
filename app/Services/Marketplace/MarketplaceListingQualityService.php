<?php

namespace App\Services\Marketplace;

use App\Models\MpProduct;
use App\Models\TrendyolBoosterReview;
use App\Services\AIService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MarketplaceListingQualityService
{
    public function __construct(
        protected AIService $aiService,
        protected TrendyolBoosterReviewInsightService $reviewInsightService,
    ) {}

    /**
     * @param Collection<int, TrendyolBoosterReview> $reviews
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public function analyze(MpProduct $product, Collection $reviews, array $overrides = []): array
    {
        $values = $this->productValues($product, $overrides);
        $listings = $product->relationLoaded('channelListings')
            ? $product->channelListings
            : collect();
        $images = collect([$values['image_url']])
            ->concat((array) $values['image_urls'])
            ->map(fn ($url): string => trim((string) $url))
            ->filter()
            ->unique()
            ->values();

        $scores = [
            'title' => $this->titleScore($values),
            'description' => $this->descriptionScore($values),
            'images' => $this->imageScore($images),
            'attributes' => $this->attributeScore($values),
            'consistency' => $this->consistencyScore($values, $listings),
        ];

        $reviewInsights = $this->reviewInsightService->analyzeCollection($reviews);
        $scores['reviews'] = $reviewInsights['sample_count'] > 0
            ? max(0, 100 - (int) $reviewInsights['risk_score'])
            : 60;

        $overall = (int) round(
            ($scores['title'] * .25)
            + ($scores['description'] * .25)
            + ($scores['images'] * .20)
            + ($scores['attributes'] * .15)
            + ($scores['consistency'] * .10)
            + ($scores['reviews'] * .05)
        );

        $evidence = $this->evidenceLedger($values, $listings, $reviewInsights);
        $issues = $this->issues($values, $images, $listings, $scores, $reviewInsights);
        $result = [
            'overall_score' => $overall,
            'score_label' => $overall >= 80 ? 'Güçlü' : ($overall >= 60 ? 'Geliştirilebilir' : 'Kritik geliştirme alanı'),
            'scores' => $scores,
            'issues' => $issues,
            'summary' => $this->fallbackSummary($overall, $issues),
            'draft' => [
                'title' => $this->draftTitle($values),
                'description' => $this->draftDescription($values),
            ],
            'review_insights' => $reviewInsights,
            'listing_count' => $listings->count(),
            'active_listing_count' => $listings->filter(fn ($listing): bool => in_array(Str::lower((string) $listing->listing_status), ['active', 'approved', 'on_sale', 'onsale', 'published'], true))->count(),
            'evidence' => $evidence,
            'provider' => 'evidence_engine',
            'generated_at' => now()->toIso8601String(),
            'evidence_note' => 'Puan; ürün kartı, bağlı kanal ilanları ve bu ürünle eşleşmiş yorumlardan hesaplanır. Öneriler taslaktır; ürün kaydedilmeden ve desteklenen kanal gönderimi yapılmadan pazaryerindeki içerik değişmez.',
        ];

        if (trim((string) config('ai.api_key', '')) !== '') {
            $ai = $this->aiDraft($result, $evidence);
            if ($ai !== null) {
                $result['summary'] = $ai['summary'];
                $result['draft'] = $ai['draft'];
                $result['issues'] = collect($ai['findings'])
                    ->concat($result['issues'])
                    ->unique(fn (array $issue): string => Str::lower((string) ($issue['title'] ?? '')))
                    ->take(8)
                    ->values()
                    ->all();
                $result['provider'] = (string) config('ai.provider', 'ai');
            }
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private function productValues(MpProduct $product, array $overrides): array
    {
        return collect([
            'barcode' => $product->barcode,
            'stock_code' => $product->stock_code,
            'product_name' => $product->product_name,
            'model_code' => $product->model_code,
            'brand' => $product->brand,
            'category_name' => $product->category_name,
            'color' => $product->color,
            'size' => $product->size,
            'variant' => $product->variant,
            'description' => $product->description,
            'image_url' => $product->image_url,
            'image_urls' => $product->image_urls ?? [],
        ])->merge($overrides)->all();
    }

    private function titleScore(array $values): int
    {
        $title = trim((string) $values['product_name']);
        if ($title === '') {
            return 0;
        }

        $length = mb_strlen($title);
        $score = 35;
        $score += $length >= 20 && $length <= 140 ? 25 : ($length >= 10 ? 12 : 4);
        $score += str_word_count(Str::ascii($title)) >= 3 ? 10 : 3;

        foreach (['brand', 'color', 'size', 'model_code'] as $field) {
            $value = trim((string) ($values[$field] ?? ''));
            if ($value !== '' && Str::contains(Str::lower($title), Str::lower($value))) {
                $score += $field === 'brand' ? 12 : 6;
            }
        }

        return min(100, $score);
    }

    private function descriptionScore(array $values): int
    {
        $description = trim(strip_tags((string) $values['description']));
        if ($description === '') {
            return 0;
        }

        $length = mb_strlen($description);
        $score = $length >= 240 ? 65 : ($length >= 100 ? 48 : ($length >= 40 ? 30 : 15));
        $score += preg_match('/[\n•\-]/u', $description) ? 15 : 5;

        $mentioned = collect(['brand', 'category_name', 'color', 'size', 'variant'])
            ->filter(fn (string $field): bool => filled($values[$field] ?? null))
            ->filter(fn (string $field): bool => Str::contains(Str::lower($description), Str::lower((string) $values[$field])))
            ->count();

        return min(100, $score + min(20, $mentioned * 5));
    }

    /** @param Collection<int, string> $images */
    private function imageScore(Collection $images): int
    {
        if ($images->isEmpty()) {
            return 0;
        }

        $score = 35 + min(55, $images->count() * 11);
        if ($images->every(fn (string $url): bool => Str::startsWith($url, 'https://'))) {
            $score += 10;
        }

        return min(100, $score);
    }

    private function attributeScore(array $values): int
    {
        $fields = ['barcode', 'stock_code', 'brand', 'category_name', 'model_code', 'color', 'size', 'variant'];
        $filled = collect($fields)->filter(fn (string $field): bool => filled($values[$field] ?? null))->count();

        return (int) round(($filled / count($fields)) * 100);
    }

    /** @param Collection<int, mixed> $listings */
    private function consistencyScore(array $values, Collection $listings): int
    {
        if ($listings->isEmpty()) {
            return 50;
        }

        $master = Str::lower(trim((string) $values['product_name']));
        $similarities = $listings->map(function ($listing) use ($master): float {
            $listingTitle = Str::lower(trim((string) $listing->channelProduct?->title));
            if ($master === '' || $listingTitle === '') {
                return 40;
            }
            similar_text($master, $listingTitle, $percent);

            return $percent;
        });

        return (int) round(min(100, max(0, (float) $similarities->avg())));
    }

    /** @param Collection<int, mixed> $listings */
    private function evidenceLedger(array $values, Collection $listings, array $reviewInsights): array
    {
        $evidence = [[
            'id' => 'U1',
            'type' => 'product',
            'label' => 'Ürün ana kartı',
            'value' => collect($values)->except(['image_urls'])->map(fn ($value) => is_scalar($value) ? $value : null)->filter()->all(),
        ]];

        foreach ($listings->take(12)->values() as $index => $listing) {
            $evidence[] = [
                'id' => 'L'.($index + 1),
                'type' => 'listing',
                'label' => (string) ($listing->store?->store_name ?: 'Kanal ilanı'),
                'value' => [
                    'marketplace' => $listing->store?->marketplace,
                    'title' => $listing->channelProduct?->title,
                    'status' => $listing->listing_status,
                ],
            ];
        }

        foreach (collect($reviewInsights['complaints'] ?? [])->flatMap(fn (array $theme): array => $theme['evidence'] ?? [])->take(12) as $row) {
            if (filled($row['id'] ?? null)) {
                $evidence[] = [
                    'id' => (string) $row['id'],
                    'type' => 'review',
                    'label' => 'Müşteri yorumu',
                    'value' => ['rating' => $row['rating'] ?? null, 'snippet' => $row['snippet'] ?? null],
                ];
            }
        }

        return collect($evidence)->unique('id')->values()->all();
    }

    /** @param Collection<int, string> $images @param Collection<int, mixed> $listings */
    private function issues(array $values, Collection $images, Collection $listings, array $scores, array $reviewInsights): array
    {
        $issues = [];
        $definitions = [
            'title' => ['Başlığı güçlendir', 'Ürün başlığı marka ve ayırt edici varyant bilgisini birlikte taşımalı.', 'listing'],
            'description' => ['Açıklamayı zenginleştir', 'Açıklama ürün kartındaki doğrulanmış özellikleri okunabilir biçimde anlatmalı.', 'listing'],
            'images' => ['Görsel setini tamamla', 'Ana görsel ve farklı kullanım açıları dönüşüm kararını destekler.', 'ai_studio'],
            'attributes' => ['Eksik ürün niteliklerini tamamla', 'Eksik marka, kategori veya varyant alanları kanal tutarlılığını düşürür.', 'listing'],
            'consistency' => ['Kanal başlıklarını karşılaştır', 'Ürün ana kartı ile bağlı kanal başlıkları arasında belirgin ayrışma var.', 'listing'],
        ];

        foreach ($definitions as $key => [$title, $reason, $actionType]) {
            if ($scores[$key] < 70) {
                $issues[] = [
                    'severity' => $scores[$key] < 40 ? 'critical' : 'warning',
                    'category' => $key,
                    'title' => $title,
                    'reason' => $reason,
                    'action_type' => $actionType,
                    'evidence_ids' => $key === 'consistency' && $listings->isNotEmpty() ? ['U1', 'L1'] : ['U1'],
                ];
            }
        }

        foreach (collect($reviewInsights['actions'] ?? [])->whereIn('type', ['listing', 'ai_studio'])->take(3) as $action) {
            $issues[] = [
                'severity' => ($action['priority'] ?? 'medium') === 'high' ? 'critical' : 'warning',
                'category' => 'reviews',
                'title' => (string) $action['title'],
                'reason' => (string) $action['reason'],
                'action_type' => (string) $action['type'],
                'evidence_ids' => (array) ($action['evidence_ids'] ?? []),
            ];
        }

        return collect($issues)->take(8)->values()->all();
    }

    private function fallbackSummary(int $score, array $issues): string
    {
        if ($issues === []) {
            return "Listing kalite puanı {$score}/100. Ürün kartı ve bağlı kanal verileri güçlü bir bütünlük gösteriyor.";
        }

        return "Listing kalite puanı {$score}/100. Öncelikli geliştirme alanı: ".($issues[0]['title'] ?? 'ürün içeriği').'.';
    }

    private function draftTitle(array $values): string
    {
        $parts = collect([
            $values['brand'] ?? null,
            $values['product_name'] ?? null,
            $values['model_code'] ?? null,
            $values['color'] ?? null,
            $values['size'] ?? null,
            $values['variant'] ?? null,
        ])->map(fn ($value): string => trim((string) $value))->filter();

        $title = $parts->reduce(function (string $title, string $part): string {
            $normalizedPart = Str::lower($part);
            $normalizedTitle = Str::lower($title);
            $alreadyIncluded = mb_strlen($normalizedPart) <= 3
                ? in_array($normalizedPart, preg_split('/[^\pL\pN]+/u', $normalizedTitle, -1, PREG_SPLIT_NO_EMPTY) ?: [], true)
                : Str::contains($normalizedTitle, $normalizedPart);

            return $alreadyIncluded ? $title : trim($title.' '.$part);
        }, '');

        return Str::limit(preg_replace('/\s+/u', ' ', $title) ?: '', 180, '');
    }

    private function draftDescription(array $values): string
    {
        $title = $this->draftTitle($values) ?: 'Bu ürün';
        $features = collect([
            'Marka' => $values['brand'] ?? null,
            'Kategori' => $values['category_name'] ?? null,
            'Model' => $values['model_code'] ?? null,
            'Renk' => $values['color'] ?? null,
            'Beden / ölçü' => $values['size'] ?? null,
            'Varyant' => $values['variant'] ?? null,
        ])->filter(fn ($value): bool => filled($value));

        $intro = trim(strip_tags((string) ($values['description'] ?? '')));
        if ($intro === '') {
            $intro = $title.'; ürün kartında doğrulanmış aşağıdaki özelliklerle sunulur.';
        }

        $lines = $features->map(fn ($value, string $label): string => "• {$label}: {$value}")->values();

        return Str::limit(trim($intro."\n\n".$lines->implode("\n")), 1500, '');
    }

    /** @return array<string, mixed>|null */
    private function aiDraft(array $baseline, array $evidence): ?array
    {
        $allowedIds = collect($evidence)->pluck('id')->map('strval')->all();
        $ledger = collect($evidence)->map(fn (array $row): string => '['.$row['id'].'] '.json_encode($row['value'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))->implode("\n");
        $fallbackTitle = $baseline['draft']['title'];
        $fallbackDescription = $baseline['draft']['description'];
        $prompt = <<<PROMPT
Aşağıdaki ürün ve kanal kanıtlarıyla Türkçe e-ticaret listing taslağı üret. Kanıtta olmayan malzeme, sertifika, ölçü, garanti, fayda veya teknik özellik uydurma. Yorumları yalnız müşteri beklentisini daha net anlatmak için kullan. Her bulgu geçerli en az bir kanıt kimliği içermeli.

Yalnız geçerli JSON döndür:
{"summary":"en fazla 2 cümle","title":"en fazla 180 karakter","description":"en fazla 1500 karakter","findings":[{"severity":"critical|warning|info","category":"title|description|images|attributes|consistency|reviews","title":"kısa başlık","reason":"kanıta bağlı açıklama","action_type":"listing|ai_studio","evidence_ids":["U1"]}]}

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

        $findings = collect(is_array($decoded['findings'] ?? null) ? $decoded['findings'] : [])
            ->filter(function ($row) use ($allowedIds): bool {
                return is_array($row)
                    && filled($row['title'] ?? null)
                    && array_intersect($allowedIds, array_map('strval', (array) ($row['evidence_ids'] ?? []))) !== [];
            })->map(function (array $row) use ($allowedIds): array {
                return [
                    'severity' => in_array($row['severity'] ?? '', ['critical', 'warning', 'info'], true) ? $row['severity'] : 'warning',
                    'category' => in_array($row['category'] ?? '', ['title', 'description', 'images', 'attributes', 'consistency', 'reviews'], true) ? $row['category'] : 'listing',
                    'title' => Str::limit(strip_tags((string) $row['title']), 140, ''),
                    'reason' => Str::limit(strip_tags((string) ($row['reason'] ?? '')), 300, ''),
                    'action_type' => in_array($row['action_type'] ?? '', ['listing', 'ai_studio'], true) ? $row['action_type'] : 'listing',
                    'evidence_ids' => array_values(array_intersect($allowedIds, array_map('strval', (array) ($row['evidence_ids'] ?? [])))),
                ];
            })->take(5)->values()->all();

        return [
            'summary' => Str::limit(strip_tags((string) $decoded['summary']), 500, ''),
            'draft' => [
                'title' => filled($decoded['title'] ?? null) ? Str::limit(strip_tags((string) $decoded['title']), 180, '') : $fallbackTitle,
                'description' => filled($decoded['description'] ?? null) ? Str::limit(strip_tags((string) $decoded['description']), 1500, '') : $fallbackDescription,
            ],
            'findings' => $findings,
        ];
    }
}
