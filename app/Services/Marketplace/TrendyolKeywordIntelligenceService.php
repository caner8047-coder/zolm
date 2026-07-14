<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Str;

class TrendyolKeywordIntelligenceService
{
    public const VERSION = 3;

    protected const MAX_PRODUCTS_PER_BRAND = 3;

    /** @var array<int, string> */
    protected const STOP_WORDS = [
        'acaba', 'adet', 'ama', 'ancak', 'bir', 'biri', 'biz', 'bu', 'cok', 'daha', 'de', 'da',
        'diye', 'en', 'gibi', 'hem', 'her', 'icin', 'ile', 'ise', 'mi', 'mu', 'mı', 'mü', 'ne',
        'olan', 'olarak', 'ozel', 've', 'veya', 'ya', 'yeni', 'urun', 'urunu', 'urunler', 'model',
        'modelleri', 'renk', 'renkli', 'kalite', 'kaliteli', 'orjinal', 'orijinal', 'kampanya',
        'cm', 'mm', 'metre', 'mt', 'li', 'lu', 'lü', 'lı', 'icin', 'alanlar',
    ];

    /** @var array<string, array<int, string>> */
    protected const INTENT_TERMS = [
        'product' => [
            'berjer', 'bench', 'koltuk', 'masa', 'puf', 'sandalye', 'tabure', 'kanepe', 'sehpa',
            'dolap', 'yatak', 'baza', 'sandalye', 'bank', 'oturma', 'mobilya', 'takim', 'set',
            'dresuar', 'konsol', 'zigon', 'komodin', 'kitaplik', 'raf',
        ],
        'material' => [
            'ahsap', 'metal', 'kumas', 'pelus', 'teddy', 'kadife', 'deri', 'sunger', 'gurgen',
            'nubuk', 'keten', 'polyester', 'pamuk', 'mdf', 'sunta', 'bukle', 'krom', 'celik',
            'viskon', 'hasir', 'masif', 'kece', 'suni deri', 'babyface', 'cam', 'çam', 'traverten',
            'mermer', 'temperli cam', 'seffaf cam', 'fume cam', 'dogal ahsap', 'naturel ahsap',
        ],
        'feature' => [
            'ayakli', 'sandikli', 'yikanabilir', 'katlanir', 'cok amacli', 'kapitoneli', 'silinebilir',
            'cikarilabilir', 'fermuarli', 'depolamali', 'cepli', 'doner', 'sabit', 'ergonomik',
            'yuksek ayakli', 'ahsap ayakli', 'metal ayakli', 'tekli', 'ikili', 'uclu', 'takim',
            'set', 'rafli', 'iki rafli', 'gazetelikli', 'kirılmaz', 'tablalı', 'govdeli',
        ],
        'form' => [
            'yuvarlak', 'oval', 'silindir', 'dikdortgen', 'kare', 'asimetrik', 'elips', 'fasulye',
            'kose', 'koseli', 'cicek formlu', 'dolunay',
        ],
        'style' => [
            'modern', 'bohem', 'retro', 'iskandinav', 'minimal', 'minimalist', 'klasik', 'luks',
            'dekoratif', 'etnik', 'rustik', 'country', 'vintage', 'loft', 'soft', 'zarif',
        ],
        'use_case' => [
            'makyaj', 'salon', 'yatak odasi', 'cocuk odasi', 'balkon', 'ayak uzatma', 'dinlenme',
            'okuma', 'bekleme', 'ofis', 'kafe', 'mutfak', 'antre', 'giyinme', 'dekorasyon',
            'makyaj masasi', 'cay seti', 'oturma odasi', 'calisma odasi',
        ],
        'color' => [
            'beyaz', 'siyah', 'bej', 'krem', 'gri', 'antrasit', 'kahverengi', 'yesil', 'mavi',
            'lacivert', 'pembe', 'mor', 'sari', 'kirmizi', 'turuncu', 'vizon', 'ekru', 'bordo',
            'hardal', 'taba', 'gold', 'gumus', 'ceviz', 'mese',
        ],
    ];

    /** @var array<string, string> */
    protected const INTENT_LABELS = [
        'product' => 'Ana ürün',
        'material' => 'Malzeme',
        'feature' => 'Özellik',
        'form' => 'Form',
        'style' => 'Stil',
        'use_case' => 'Kullanım',
        'color' => 'Renk',
        'generic' => 'Destekleyici',
    ];

    /** @var array<string, string> */
    protected const TOKEN_ALIASES = [
        'sehpasi' => 'sehpa',
        'sehpalari' => 'sehpa',
        'sehpalar' => 'sehpa',
        'masasi' => 'masa',
        'masalari' => 'masa',
        'koltugu' => 'koltuk',
        'koltuklari' => 'koltuk',
        'yesili' => 'yesil',
        'siyahi' => 'siyah',
        'beyazi' => 'beyaz',
        'kremi' => 'krem',
        'kosesi' => 'kose',
        'rafli' => 'rafli',
        'kirilmaz' => 'kirilmaz',
        'tablali' => 'tablali',
    ];

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @return array<string, mixed>
     */
    public function analyze(string $query, array $products, int $resultCount = 0): array
    {
        $queryDisplay = $this->normalizeDisplay($query);
        $queryTokens = $this->tokenize($queryDisplay);
        $queryKeys = array_values(array_unique(array_column($queryTokens, 'key')));
        $prepared = $this->prepareProducts($products, $queryKeys);
        $sampleSize = count($prepared['products']);
        $quality = $prepared['quality'];

        if ($sampleSize === 0) {
            return $this->emptyAnalysis($queryDisplay, $resultCount, $quality);
        }

        $queryCoverage = (int) $quality['relevance_percent'];
        $resultPressure = min(100, (log10(max(1, $resultCount) + 1) / 6) * 100);
        $competitionScore = $this->clamp((int) round(($resultPressure * 0.58) + ($queryCoverage * 0.42)));

        $candidates = $this->buildCandidates(
            $prepared['products'],
            $queryKeys,
            $competitionScore,
            $sampleSize,
        );
        $keywords = array_values(array_slice($candidates, 0, 40));
        $longTail = array_values(array_slice(array_filter(
            $keywords,
            fn (array $candidate): bool => $candidate['ngram'] >= 2
                && $candidate['contains_all_query']
                && ! $candidate['is_query'],
        ), 0, 10));
        $clusters = $this->buildClusters($keywords);
        $brands = $this->buildBrandSignals($prepared['brands'], $sampleSize);
        $titlePlan = $this->buildTitlePlan($queryDisplay, $keywords, $clusters);

        $opportunityPool = $longTail !== [] ? $longTail : array_slice($keywords, 0, 5);
        $opportunityScore = $opportunityPool === []
            ? 0
            : (int) round(array_sum(array_column($opportunityPool, 'opportunity_score')) / count($opportunityPool));
        $rankCompleteness = count(array_filter(
            $prepared['products'],
            fn (array $product): bool => $product['rank'] > 0,
        )) / max(1, $sampleSize);
        $dataConfidence = $this->clamp((int) round(
            (min(1, ((int) $quality['source_sample_size']) / 32) * 24)
            + (($quality['relevance_percent'] / 100) * 24)
            + (($quality['uniqueness_percent'] / 100) * 18)
            + (($quality['brand_diversity_percent'] / 100) * 14)
            + (($quality['exact_phrase_percent'] / 100) * 12)
            + ($rankCompleteness * 8),
        ));

        return [
            'version' => self::VERSION,
            'query' => $queryDisplay,
            'sample_size' => $sampleSize,
            'source_sample_size' => (int) $quality['source_sample_size'],
            'result_count' => max(0, $resultCount),
            'quality' => $quality,
            'scores' => [
                'opportunity' => $opportunityScore,
                'competition' => $competitionScore,
                'query_coverage' => $queryCoverage,
                'confidence' => $dataConfidence,
            ],
            'score_labels' => [
                'opportunity' => $this->scoreLabel($opportunityScore, ['Sınırlı', 'Dengeli', 'Güçlü']),
                'competition' => $this->scoreLabel($competitionScore, ['Düşük', 'Orta', 'Yüksek']),
                'query_coverage' => $this->scoreLabel($queryCoverage, ['Zayıf', 'Orta', 'Güçlü']),
                'confidence' => $this->scoreLabel($dataConfidence, ['Düşük', 'Yeterli', 'Yüksek']),
            ],
            'keywords' => $keywords,
            'long_tail' => $longTail,
            'clusters' => $clusters,
            'brands' => $brands,
            'title_plan' => $titlePlan,
            'recommendations' => $this->buildRecommendations(
                $queryDisplay,
                $queryCoverage,
                $competitionScore,
                $longTail,
                $clusters,
                $brands,
                $quality,
            ),
            'risks' => $this->buildRisks(
                $sampleSize,
                $queryCoverage,
                $competitionScore,
                $brands,
                $quality,
            ),
            'methodology' => [
                'Üst sıradaki ürünlere logaritmik sıra ağırlığı verildi.',
                'Marka URL’den doğrulandı; marka, model ve kategori dışı sonuçlar organik havuzdan çıkarıldı.',
                'Yakın kopyalar tekilleştirildi ve aynı markanın analizi domine etmesi sınırlandı.',
                'Adaylar yalnızca en az iki bağımsız marka sinyali taşıdığında pazar fırsatı sayıldı.',
                'Güven skoru; örneklem, kategori uyumu, tekillik, marka çeşitliliği ve tam ifade oranını birleştirir.',
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @param  array<int, string>  $queryKeys
     * @return array{products: array<int, array<string, mixed>>, brands: array<string, array<string, mixed>>, quality: array<string, int>}
     */
    protected function prepareProducts(array $products, array $queryKeys): array
    {
        $sourceProducts = [];
        $queryPhrase = implode(' ', $queryKeys);

        foreach (array_values($products) as $index => $product) {
            if (! is_array($product)) {
                continue;
            }

            $title = $this->cleanText((string) ($product['title'] ?? $product['name'] ?? ''), 500);
            if ($title === '') {
                continue;
            }

            $rank = max(1, (int) ($product['rank'] ?? $index + 1));
            $allTokens = $this->tokenize($title);
            $allKeys = array_values(array_unique(array_column($allTokens, 'key')));
            $brandDisplay = $this->resolveBrandDisplay($product, $title, $queryKeys);
            $brandKey = $this->phraseKey($brandDisplay);
            $brandTokens = $brandKey === '' ? [] : explode(' ', $brandKey);
            $modelKeys = $this->detectLeadingModelKeys($allTokens, $brandTokens, $queryKeys);
            $queryMatch = $this->containsAll($allKeys, $queryKeys);
            $orderedKeys = array_column($allTokens, 'key');
            $exactPhraseMatch = $queryPhrase !== ''
                && str_contains(' '.implode(' ', $orderedKeys).' ', ' '.$queryPhrase.' ');
            $contentTokens = [];

            foreach ($allTokens as $token) {
                $tokenKey = $token['key'];
                $isBrandToken = $this->isBrandToken($tokenKey, $brandTokens);

                if (in_array($tokenKey, self::STOP_WORDS, true)
                    || in_array($tokenKey, $modelKeys, true)
                    || $isBrandToken
                    || mb_strlen($token['display'], 'UTF-8') < 2
                    || preg_match('/^\d+$/', $tokenKey) === 1
                    || preg_match('/^(?=.*\d)[a-z]+\d+[a-z\d]*$/', $tokenKey) === 1) {
                    continue;
                }

                $contentTokens[] = $token;
            }

            $sourceProducts[] = [
                'title' => $title,
                'rank' => $rank,
                'rank_weight' => 1 / log($rank + 1, 2),
                'tokens' => $contentTokens,
                'all_keys' => $allKeys,
                'query_match' => $queryMatch,
                'exact_phrase_match' => $exactPhraseMatch,
                'brand_key' => $brandKey,
                'brand_label' => $brandDisplay,
                'fingerprint' => implode(' ', array_column($contentTokens, 'key')),
            ];
        }

        usort($sourceProducts, fn (array $left, array $right): int => $left['rank'] <=> $right['rank']);

        $sourceSampleSize = count($sourceProducts);
        $relevantProducts = array_values(array_filter(
            $sourceProducts,
            fn (array $product): bool => $product['query_match'],
        ));
        $relevantBeforeDedup = count($relevantProducts);
        $exactPhraseMatches = count(array_filter(
            $sourceProducts,
            fn (array $product): bool => $product['exact_phrase_match'],
        ));
        $uniqueProducts = [];
        $duplicatesRemoved = 0;

        foreach ($relevantProducts as $product) {
            $duplicate = collect($uniqueProducts)->contains(
                fn (array $existing): bool => $this->isNearDuplicateFingerprint(
                    (string) $existing['fingerprint'],
                    (string) $product['fingerprint'],
                ),
            );

            if ($duplicate) {
                $duplicatesRemoved++;

                continue;
            }

            $uniqueProducts[] = $product;
        }

        $prepared = [];
        $brandCounts = [];
        $brandCappedCount = 0;

        foreach ($uniqueProducts as $productIndex => $product) {
            $capKey = $product['brand_key'] !== '' ? $product['brand_key'] : '__unknown_'.$productIndex;
            $brandCounts[$capKey] = ($brandCounts[$capKey] ?? 0) + 1;

            if ($product['brand_key'] !== '' && $brandCounts[$capKey] > self::MAX_PRODUCTS_PER_BRAND) {
                $brandCappedCount++;

                continue;
            }

            $prepared[] = $product;
        }

        $brands = [];
        foreach ($prepared as $productIndex => $product) {
            if ($product['brand_key'] === '') {
                continue;
            }

            $brands[$product['brand_key']] ??= [
                'key' => $product['brand_key'],
                'label' => $product['brand_label'],
                'documents' => [],
            ];
            $brands[$product['brand_key']]['documents'][$productIndex] = true;
        }

        $analyzedSampleSize = count($prepared);
        $distinctBrands = count($brands);
        $uniquenessBase = max(1, $relevantBeforeDedup);
        $quality = [
            'source_sample_size' => $sourceSampleSize,
            'relevant_sample_size' => $relevantBeforeDedup,
            'analyzed_sample_size' => $analyzedSampleSize,
            'off_topic_count' => max(0, $sourceSampleSize - $relevantBeforeDedup),
            'duplicate_count' => $duplicatesRemoved,
            'brand_capped_count' => $brandCappedCount,
            'distinct_brand_count' => $distinctBrands,
            'relevance_percent' => (int) round(($relevantBeforeDedup / max(1, $sourceSampleSize)) * 100),
            'exact_phrase_percent' => (int) round(($exactPhraseMatches / max(1, $sourceSampleSize)) * 100),
            'uniqueness_percent' => (int) round((($relevantBeforeDedup - $duplicatesRemoved) / $uniquenessBase) * 100),
            'brand_diversity_percent' => $this->clamp((int) round(($distinctBrands / max(1, $analyzedSampleSize * 0.45)) * 100)),
        ];

        return ['products' => $prepared, 'brands' => $brands, 'quality' => $quality];
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @param  array<int, string>  $queryKeys
     * @return array<int, array<string, mixed>>
     */
    protected function buildCandidates(
        array $products,
        array $queryKeys,
        int $competitionScore,
        int $sampleSize,
    ): array {
        $stats = [];
        $queryPhrase = implode(' ', $queryKeys);

        foreach ($products as $productIndex => $product) {
            $tokens = $product['tokens'];
            $tokenCount = count($tokens);

            for ($length = 1; $length <= 3; $length++) {
                for ($start = 0; $start <= $tokenCount - $length; $start++) {
                    $slice = array_slice($tokens, $start, $length);
                    $keys = array_column($slice, 'key');
                    if (count(array_unique($keys)) !== count($keys)) {
                        continue;
                    }

                    $key = implode(' ', $keys);
                    $display = implode(' ', array_column($slice, 'display'));
                    if ($key === '' || ($length === 1 && mb_strlen($display, 'UTF-8') < 3 && ! in_array($key, $queryKeys, true))) {
                        continue;
                    }

                    if (! $this->isCandidatePhraseAllowed($key, $queryKeys)) {
                        continue;
                    }

                    $stats[$key] ??= [
                        'key' => $key,
                        'ngram' => $length,
                        'frequency' => 0,
                        'documents' => [],
                        'query_documents' => [],
                        'brands' => [],
                        'rank_weight_sum' => 0.0,
                        'displays' => [],
                    ];
                    $stats[$key]['frequency']++;
                    $stats[$key]['displays'][$display] = ($stats[$key]['displays'][$display] ?? 0) + 1;

                    if (! isset($stats[$key]['documents'][$productIndex])) {
                        $stats[$key]['documents'][$productIndex] = true;
                        $stats[$key]['rank_weight_sum'] += $product['rank_weight'];
                        $brandEvidenceKey = $product['brand_key'] !== ''
                            ? $product['brand_key']
                            : '__product_'.$productIndex;
                        $stats[$key]['brands'][$brandEvidenceKey] = true;
                    }

                    if ($product['query_match']) {
                        $stats[$key]['query_documents'][$productIndex] = true;
                    }
                }
            }
        }

        $candidates = [];
        foreach ($stats as $stat) {
            $documentFrequency = count($stat['documents']);
            $distinctBrandFrequency = count($stat['brands']);
            $queryDocumentFrequency = count($stat['query_documents']);
            $intent = $this->classifyIntent($stat['key'], $queryKeys);
            $isExactQuery = $queryPhrase !== '' && $stat['key'] === $queryPhrase;
            $candidateTokens = explode(' ', $stat['key']);
            $containsAllQuery = $this->containsAll($candidateTokens, $queryKeys);
            $modifierTokens = array_values(array_filter(
                $candidateTokens,
                fn (string $token): bool => ! in_array($token, $queryKeys, true),
            ));

            if (! $isExactQuery
                && ($documentFrequency < 2 || $distinctBrandFrequency < 2 || $intent === 'generic')) {
                continue;
            }

            arsort($stat['displays']);
            $display = (string) array_key_first($stat['displays']);
            $modifierDisplayTokens = array_values(array_filter(
                $this->tokenize($display),
                fn (array $token): bool => ! in_array($token['key'], $queryKeys, true),
            ));
            $coverage = (int) round(($documentFrequency / max(1, $sampleSize)) * 100);
            $rankScore = $this->clamp((int) round(($stat['rank_weight_sum'] / max(1, $documentFrequency)) * 100));
            $queryOverlap = count(array_intersect($candidateTokens, $queryKeys)) / max(1, count($queryKeys));
            $cooccurrence = $queryDocumentFrequency / max(1, $documentFrequency);
            $queryAffinity = $isExactQuery
                ? 100
                : $this->clamp((int) round(($cooccurrence * 72) + ($queryOverlap * 28)));
            $demandScore = $this->clamp((int) round(
                min(78, $coverage * 1.35) + min(22, log($stat['frequency'] + 1, 2) * 7),
            ));
            $specificityScore = [1 => 44, 2 => 76, 3 => 94][$stat['ngram']] ?? 44;
            $evidenceTarget = max(2, min(5, (int) ceil($sampleSize * 0.20)));
            $evidenceScore = $this->clamp((int) round(
                25
                + (min(1, $documentFrequency / $evidenceTarget) * 45)
                + (min(1, $distinctBrandFrequency / 3) * 30),
            ));
            $intentScore = [
                'product' => 94,
                'material' => 87,
                'feature' => 90,
                'form' => 86,
                'style' => 80,
                'use_case' => 89,
                'color' => 74,
                'generic' => 56,
            ][$intent];
            $semanticScore = $this->clamp((int) round(
                ($demandScore * 0.27)
                + ($rankScore * 0.16)
                + ($queryAffinity * 0.22)
                + ($intentScore * 0.13)
                + ($specificityScore * 0.08)
                + ($evidenceScore * 0.14),
            ));
            $difficultyScore = $this->clamp((int) round(($coverage * 0.58) + ($competitionScore * 0.42)));
            $opportunityScore = $this->clamp((int) round(
                ($semanticScore * 0.52)
                + ((100 - $difficultyScore) * 0.22)
                + ($specificityScore * 0.16)
                + ($evidenceScore * 0.10)
                - ($documentFrequency === 1 && ! $isExactQuery ? 8 : 0),
            ));

            $candidates[] = [
                'key' => $stat['key'],
                'keyword' => $this->titleCase($display),
                'intent' => $intent,
                'intent_label' => self::INTENT_LABELS[$intent],
                'ngram' => $stat['ngram'],
                'frequency' => $stat['frequency'],
                'document_frequency' => $documentFrequency,
                'distinct_brand_frequency' => $distinctBrandFrequency,
                'coverage_percent' => $coverage,
                'rank_score' => $rankScore,
                'query_affinity' => $queryAffinity,
                'demand_score' => $demandScore,
                'evidence_score' => $evidenceScore,
                'semantic_score' => $semanticScore,
                'difficulty_score' => $difficultyScore,
                'difficulty_label' => $this->scoreLabel($difficultyScore, ['Düşük', 'Orta', 'Yüksek']),
                'opportunity_score' => $opportunityScore,
                'priority' => $opportunityScore >= 74 ? 'high' : ($opportunityScore >= 58 ? 'medium' : 'support'),
                'is_query' => $isExactQuery,
                'contains_all_query' => $containsAllQuery,
                'modifier_key' => implode(' ', $modifierTokens),
                'modifier_keyword' => $this->titleCase(implode(' ', array_column($modifierDisplayTokens, 'display'))),
            ];
        }

        usort($candidates, function (array $left, array $right): int {
            return [$right['is_query'], $right['opportunity_score'], $right['semantic_score'], $right['document_frequency']]
                <=> [$left['is_query'], $left['opportunity_score'], $left['semantic_score'], $left['document_frequency']];
        });

        return $candidates;
    }

    /**
     * @param  array<int, array<string, mixed>>  $keywords
     * @return array<string, array<string, mixed>>
     */
    protected function buildClusters(array $keywords): array
    {
        $clusters = [];

        foreach ($keywords as $keyword) {
            $intent = $keyword['intent'];
            if ($intent === 'generic') {
                continue;
            }

            $clusters[$intent] ??= [
                'key' => $intent,
                'label' => self::INTENT_LABELS[$intent],
                'terms' => [],
                'average_score' => 0,
            ];

            if (count($clusters[$intent]['terms']) < 6) {
                $clusters[$intent]['terms'][] = $keyword;
            }
        }

        foreach ($clusters as &$cluster) {
            $cluster['average_score'] = $cluster['terms'] === []
                ? 0
                : (int) round(array_sum(array_column($cluster['terms'], 'opportunity_score')) / count($cluster['terms']));
        }
        unset($cluster);

        uasort($clusters, fn (array $left, array $right): int => $right['average_score'] <=> $left['average_score']);

        return $clusters;
    }

    /**
     * @param  array<string, array<string, mixed>>  $brands
     * @return array<int, array<string, mixed>>
     */
    protected function buildBrandSignals(array $brands, int $sampleSize): array
    {
        $signals = [];

        foreach ($brands as $brand) {
            $documentFrequency = count($brand['documents']);
            $signals[] = [
                'key' => $brand['key'],
                'label' => $brand['label'],
                'document_frequency' => $documentFrequency,
                'share_percent' => (int) round(($documentFrequency / max(1, $sampleSize)) * 100),
            ];
        }

        usort($signals, fn (array $left, array $right): int => $right['document_frequency'] <=> $left['document_frequency']);

        return array_values(array_slice($signals, 0, 8));
    }

    /**
     * @param  array<int, array<string, mixed>>  $keywords
     * @param  array<string, array<string, mixed>>  $clusters
     * @return array<string, mixed>
     */
    protected function buildTitlePlan(string $query, array $keywords, array $clusters): array
    {
        $parts = [$this->titleCase($query)];
        $placeholderLabels = [
            'material' => 'Malzeme',
            'form' => 'Form',
            'feature' => 'Ayırt edici özellik',
            'color' => 'Renk',
            'style' => 'Stil',
            'use_case' => 'Kullanım alanı',
        ];
        $marketOptions = [];

        foreach ($placeholderLabels as $intent => $label) {
            $terms = collect((array) ($clusters[$intent]['terms'] ?? []))
                ->map(fn (array $term): string => trim((string) ($term['modifier_keyword'] ?: $term['keyword'])))
                ->filter()
                ->unique(fn (string $term): string => $this->phraseKey($term))
                ->take(4)
                ->values()
                ->all();

            if ($terms === []) {
                continue;
            }

            $marketOptions[] = ['key' => $intent, 'label' => $label, 'terms' => $terms];
            if (count($parts) < 5) {
                $parts[] = '['.$label.']';
            }
        }

        $recommended = Str::limit(implode(' · ', $parts), 120, '');
        $primary = array_values(array_slice(array_filter(
            $keywords,
            fn (array $keyword): bool => $keyword['is_query'] || $keyword['intent'] === 'product',
        ), 0, 4));

        return [
            'recommended_title' => $recommended,
            'character_count' => mb_strlen($recommended, 'UTF-8'),
            'formula' => '[Ana ürün] + [malzeme] + [ayırt edici özellik] + [stil/kullanım] + [renk veya form]',
            'primary_terms' => array_column($primary, 'keyword'),
            'support_terms' => array_values(array_slice(array_column($keywords, 'keyword'), 0, 10)),
            'market_options' => $marketOptions,
            'rules' => [
                'Ana sorguyu başlığın ilk 45 karakterinde kullan.',
                'Köşeli alanları yalnızca ürünün gerçekten taşıdığı özelliklerle doldur.',
                'Marka adlarını organik anahtar kelime gibi tekrar etme.',
                'Sehpa, dresuar ve konsol gibi farklı ürün kategorilerini aynı başlıkta karıştırma.',
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $longTail
     * @param  array<string, array<string, mixed>>  $clusters
     * @param  array<int, array<string, mixed>>  $brands
     * @return array<int, array<string, string>>
     */
    protected function buildRecommendations(
        string $query,
        int $queryCoverage,
        int $competitionScore,
        array $longTail,
        array $clusters,
        array $brands,
        array $quality,
    ): array {
        $topLongTail = $longTail[0]['keyword'] ?? $this->titleCase($query);
        $strongCluster = collect($clusters)->first();
        $brandCount = count($brands);

        return [
            [
                'title' => 'Çekirdek sorguyu sabitle',
                'description' => $queryCoverage >= 65
                    ? 'Kategoriyle uyumlu sonuçların çoğu ana sorguyu kullanıyor; başlığın ilk bölümünde koruyun.'
                    : 'Ana sorgu başlıklarda dağınık kullanılıyor; tam eşleşmeyi ilk bölümde konumlandırın.',
                'impact' => 'Kapsama %'.$queryCoverage,
            ],
            [
                'title' => 'Uzun kuyruk fırsatını öne al',
                'description' => 'Daha düşük zorluk ve yüksek özgüllük sinyali veren “'.$topLongTail.'” kombinasyonunu başlık ve açıklamada birlikte kullanın.',
                'impact' => 'Rekabet '.$competitionScore.'/100',
            ],
            [
                'title' => 'İlişkili kelimeleri genişlet',
                'description' => $strongCluster
                    ? $strongCluster['label'].' kümesi güçlü; aynı niyetten 2–3 doğal terimi açıklamaya dağıtın.'
                    : 'Malzeme, özellik ve kullanım niyetlerinden dengeli destek terimleri ekleyin.',
                'impact' => count($clusters).' aktif küme',
            ],
            [
                'title' => 'Marka gürültüsünü ayır',
                'description' => $brandCount > 0
                    ? $brandCount.' marka sinyali organik kelime havuzundan ayrıldı; rakip marka tekrarına yüklenmeyin.'
                    : 'Belirgin marka yoğunluğu yok; kategori terimlerine odaklanın.',
                'impact' => $brandCount.' marka filtresi',
            ],
            [
                'title' => 'Veri kalitesini gözet',
                'description' => $quality['off_topic_count'].' kategori dışı ve '.$quality['duplicate_count'].' yakın kopya sonuç puanlamadan çıkarıldı.',
                'impact' => $quality['analyzed_sample_size'].' temiz başlık',
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $brands
     * @return array<int, array<string, string>>
     */
    protected function buildRisks(
        int $sampleSize,
        int $queryCoverage,
        int $competitionScore,
        array $brands,
        array $quality,
    ): array {
        $risks = [];

        if ($sampleSize < 12) {
            $risks[] = ['level' => 'warning', 'label' => 'Örneklem dar', 'detail' => $sampleSize.' ürün başlığıyla analiz yapıldı.'];
        }
        if ($queryCoverage < 45) {
            $risks[] = ['level' => 'warning', 'label' => 'Sorgu uyumu zayıf', 'detail' => 'Sonuç başlıklarının yalnızca %'.$queryCoverage.' kadarı çekirdek sorguyu taşıyor.'];
        }
        if ($quality['off_topic_count'] > 0) {
            $risks[] = [
                'level' => $quality['relevance_percent'] < 70 ? 'warning' : 'info',
                'label' => 'Kategori dışı sonuçlar elendi',
                'detail' => $quality['source_sample_size'].' kaydın '.$quality['off_topic_count'].' tanesi çekirdek sorguyla eşleşmedi.',
            ];
        }
        if ($quality['duplicate_count'] > 0 || $quality['brand_capped_count'] > 0) {
            $risks[] = [
                'level' => 'info',
                'label' => 'Tekrar baskısı dengelendi',
                'detail' => $quality['duplicate_count'].' yakın kopya çıkarıldı; '.$quality['brand_capped_count'].' marka tekrarı sınırlandı.',
            ];
        }
        if ($quality['exact_phrase_percent'] < 45) {
            $risks[] = ['level' => 'warning', 'label' => 'Tam ifade zayıf', 'detail' => 'Ana sorgu yalnızca %'.$quality['exact_phrase_percent'].' oranında aynı sırayla kullanılıyor.'];
        }
        if ($competitionScore >= 76) {
            $risks[] = ['level' => 'critical', 'label' => 'Rekabet yüksek', 'detail' => 'Uzun kuyruk ve niş özellik kombinasyonu olmadan görünürlük zorlaşabilir.'];
        }
        if (($brands[0]['share_percent'] ?? 0) >= 35) {
            $risks[] = ['level' => 'warning', 'label' => 'Marka yoğunluğu', 'detail' => $brands[0]['label'].' başlıkların %'.$brands[0]['share_percent'].' kadarında görülüyor.'];
        }

        if ($risks === []) {
            $risks[] = ['level' => 'info', 'label' => 'Dengeli veri', 'detail' => 'Örneklem ve sorgu uyumu karar üretmek için yeterli görünüyor.'];
        }

        return $risks;
    }

    /**
     * @param  array<int, string>  $queryKeys
     */
    protected function classifyIntent(string $phraseKey, array $queryKeys): string
    {
        $phraseTokens = explode(' ', $phraseKey);
        $modifierTokens = array_values(array_filter(
            $phraseTokens,
            fn (string $token): bool => ! in_array($token, $queryKeys, true),
        ));

        if ($modifierTokens === []) {
            return 'product';
        }

        $modifierPhrase = implode(' ', $modifierTokens);

        foreach (['material', 'form', 'feature', 'style', 'use_case', 'color', 'product'] as $intent) {
            foreach (self::INTENT_TERMS[$intent] as $term) {
                $termKey = $this->phraseKey($term);
                if ($modifierPhrase === $termKey
                    || str_contains(' '.$modifierPhrase.' ', ' '.$termKey.' ')) {
                    return $intent;
                }
            }
        }

        return 'generic';
    }

    /** @param  array<int, string>  $queryKeys */
    protected function isCandidatePhraseAllowed(string $phraseKey, array $queryKeys): bool
    {
        $tokens = explode(' ', $phraseKey);
        $queryProductTerms = array_values(array_intersect($queryKeys, $this->semanticKeys('product')));
        $candidateProductTerms = array_values(array_intersect($tokens, $this->semanticKeys('product')));
        $conflictingProducts = array_diff($candidateProductTerms, $queryProductTerms);
        $queryOverlap = array_values(array_intersect($tokens, $queryKeys));

        if (count($tokens) === 1
            && $queryOverlap !== []
            && array_intersect($tokens, $queryProductTerms) === []) {
            return false;
        }

        if ($queryOverlap !== []
            && ! $this->containsAll($tokens, $queryKeys)
            && array_intersect($tokens, $queryProductTerms) === []) {
            return false;
        }

        if ($queryProductTerms !== [] && $conflictingProducts !== []) {
            return false;
        }

        if (array_intersect($tokens, ['cm', 'mm', 'metre', 'mt', 'li', 'lu']) !== []) {
            return false;
        }

        $modifierTokens = array_values(array_filter(
            $tokens,
            fn (string $token): bool => ! in_array($token, $queryKeys, true),
        ));

        if ($modifierTokens === []) {
            return true;
        }

        $intentHits = [];
        $modifierPhrase = implode(' ', $modifierTokens);
        foreach (['material', 'form', 'feature', 'style', 'use_case', 'color'] as $intent) {
            foreach ($this->semanticKeys($intent) as $termKey) {
                if ($modifierPhrase === $termKey
                    || str_contains(' '.$modifierPhrase.' ', ' '.$termKey.' ')) {
                    $intentHits[$intent] = true;
                }
            }
        }

        if (count($intentHits) <= 1) {
            return true;
        }

        return $this->isRecognizedCompound($modifierPhrase);
    }

    protected function isRecognizedCompound(string $phraseKey): bool
    {
        foreach (self::INTENT_TERMS as $terms) {
            foreach ($terms as $term) {
                if (str_contains($this->phraseKey($term), ' ')
                    && $this->phraseKey($term) === $phraseKey) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return array<int, string> */
    protected function semanticKeys(string $intent): array
    {
        return array_values(array_unique(array_map(
            fn (string $term): string => $this->phraseKey($term),
            self::INTENT_TERMS[$intent] ?? [],
        )));
    }

    /**
     * @param  array<string, mixed>  $product
     * @param  array<int, string>  $queryKeys
     */
    protected function resolveBrandDisplay(array $product, string $title, array $queryKeys): string
    {
        $urlBrand = $this->brandFromTrendyolUrl((string) ($product['source_url'] ?? $product['url'] ?? ''));
        if ($urlBrand !== '') {
            return $urlBrand;
        }

        $explicitBrand = $this->cleanText((string) ($product['brand'] ?? ''), 120);
        if ($this->isPlausibleBrand($explicitBrand, $title, $queryKeys)) {
            return $explicitBrand;
        }

        return (string) ($this->inferBrands($title, $queryKeys)[0] ?? '');
    }

    protected function brandFromTrendyolUrl(string $url): string
    {
        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));
        if (! ($host === 'trendyol.com' || Str::endsWith($host, '.trendyol.com'))) {
            return '';
        }

        $segments = array_values(array_filter(explode('/', trim((string) parse_url($url, PHP_URL_PATH), '/'))));
        $slug = rawurldecode((string) ($segments[0] ?? ''));
        if ($slug === '' || in_array(Str::lower($slug), ['sr', 'magaza', 'butik'], true)) {
            return '';
        }

        return $this->titleCase(str_replace('-', ' ', $slug));
    }

    /** @param  array<int, string>  $queryKeys */
    protected function isPlausibleBrand(string $brand, string $title, array $queryKeys): bool
    {
        $brandKey = $this->phraseKey($brand);
        $titleKey = $this->phraseKey($title);
        $brandTokens = array_values(array_filter(explode(' ', $brandKey)));
        $titleTokens = array_values(array_filter(explode(' ', $titleKey)));

        return $brandKey !== ''
            && $brandKey !== $titleKey
            && count($brandTokens) <= 4
            && mb_strlen($brand, 'UTF-8') <= 80
            && count(array_intersect($brandTokens, $queryKeys)) === 0
            && count($brandTokens) <= max(2, (int) floor(count($titleTokens) * 0.45));
    }

    /**
     * @param  array<int, array{display: string, key: string}>  $tokens
     * @param  array<int, string>  $brandTokens
     * @param  array<int, string>  $queryKeys
     * @return array<int, string>
     */
    protected function detectLeadingModelKeys(array $tokens, array $brandTokens, array $queryKeys): array
    {
        $models = [];
        $brandPassed = $brandTokens === [];

        foreach (array_slice($tokens, 0, 7) as $token) {
            $key = $token['key'];

            if (! $brandPassed && $this->isBrandToken($key, $brandTokens)) {
                continue;
            }

            $brandPassed = true;

            if (preg_match('/^\d+$/', $key) === 1) {
                $models[] = $key;

                continue;
            }

            if (in_array($key, $queryKeys, true)
                || in_array($key, self::STOP_WORDS, true)
                || $this->isKnownSemanticTerm($key)) {
                break;
            }

            $models[] = $key;
            if (count($models) >= 2) {
                break;
            }
        }

        return $models;
    }

    /** @param  array<int, string>  $brandTokens */
    protected function isBrandToken(string $tokenKey, array $brandTokens): bool
    {
        foreach ($brandTokens as $brandToken) {
            if ($tokenKey === $brandToken
                || (mb_strlen($brandToken, 'UTF-8') >= 4 && str_starts_with($tokenKey, $brandToken))) {
                return true;
            }
        }

        return false;
    }

    protected function isNearDuplicateFingerprint(string $left, string $right): bool
    {
        if ($left === '' || $right === '') {
            return false;
        }

        if ($left === $right) {
            return true;
        }

        $leftTokens = array_values(array_unique(explode(' ', $left)));
        $rightTokens = array_values(array_unique(explode(' ', $right)));
        $union = array_unique(array_merge($leftTokens, $rightTokens));
        $similarity = count(array_intersect($leftTokens, $rightTokens)) / max(1, count($union));

        return count($union) >= 5 && $similarity >= 0.92;
    }

    /**
     * @param  array<int, string>  $queryKeys
     * @return array<int, string>
     */
    protected function inferBrands(string $title, array $queryKeys): array
    {
        preg_match_all('/[\p{L}\p{N}]+/u', $title, $matches);
        $rawTokens = array_values($matches[0] ?? []);
        $brandTokens = [];

        foreach (array_slice($rawTokens, 0, 3) as $token) {
            $isUppercase = mb_strtoupper($token, 'UTF-8') === $token
                && mb_strtolower($token, 'UTF-8') !== $token;
            if (! $isUppercase) {
                break;
            }
            $brandTokens[] = $token;
        }

        if ($brandTokens !== []) {
            return [implode(' ', $brandTokens)];
        }

        $first = $rawTokens[0] ?? '';
        $firstKey = $this->phraseKey($first);
        if ($firstKey === ''
            || in_array($firstKey, $queryKeys, true)
            || in_array($firstKey, self::STOP_WORDS, true)
            || $this->isKnownSemanticTerm($firstKey)) {
            return [];
        }

        return [$first];
    }

    protected function isKnownSemanticTerm(string $key): bool
    {
        foreach (self::INTENT_TERMS as $terms) {
            foreach ($terms as $term) {
                if ($this->phraseKey($term) === $key) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array<int, array{display: string, key: string}>
     */
    protected function tokenize(string $value): array
    {
        preg_match_all('/[\p{L}\p{N}]+/u', $this->normalizeDisplay($value), $matches);

        return array_values(array_filter(array_map(function (string $token): array {
            return [
                'display' => $token,
                'key' => $this->phraseKey($token),
            ];
        }, $matches[0] ?? []), fn (array $token): bool => $token['key'] !== ''));
    }

    protected function normalizeDisplay(string $value): string
    {
        $value = $this->cleanText($value, 1000);
        $value = strtr($value, ['İ' => 'i', 'I' => 'ı']);
        $value = mb_strtolower($value, 'UTF-8');
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?: '';

        return trim(preg_replace('/\s+/u', ' ', $value) ?: '');
    }

    protected function phraseKey(string $value): string
    {
        $normalized = $this->normalizeDisplay($value);
        $normalized = str_replace('çam', 'cham', $normalized);
        $ascii = Str::ascii($normalized);
        $ascii = preg_replace('/[^a-z0-9]+/', ' ', Str::lower($ascii)) ?: '';
        $tokens = array_values(array_filter(explode(' ', trim(preg_replace('/\s+/', ' ', $ascii) ?: ''))));

        return implode(' ', array_map(
            fn (string $token): string => self::TOKEN_ALIASES[$token] ?? $token,
            $tokens,
        ));
    }

    /**
     * @param  array<int, string>  $haystack
     * @param  array<int, string>  $needles
     */
    protected function containsAll(array $haystack, array $needles): bool
    {
        return $needles !== [] && count(array_diff($needles, $haystack)) === 0;
    }

    /**
     * @param  array<int, string>  $parts
     */
    protected function phraseOverlaps(array $parts, string $candidate): bool
    {
        $existingTokens = explode(' ', $this->phraseKey(implode(' ', $parts)));
        $candidateTokens = explode(' ', $this->phraseKey($candidate));

        return count(array_diff($candidateTokens, $existingTokens)) === 0;
    }

    protected function titleCase(string $value): string
    {
        $words = preg_split('/\s+/u', $this->normalizeDisplay($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode(' ', array_map(function (string $word): string {
            $first = mb_substr($word, 0, 1, 'UTF-8');
            $upperFirst = match ($first) {
                'i' => 'İ',
                'ı' => 'I',
                default => mb_strtoupper($first, 'UTF-8'),
            };

            return $upperFirst.mb_substr($word, 1, null, 'UTF-8');
        }, $words));
    }

    protected function cleanText(string $value, int $limit): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?: '';
        $value = preg_replace('/\s+/u', ' ', $value) ?: '';

        return trim(Str::limit($value, $limit, ''));
    }

    /** @param  array<int, string>  $labels */
    protected function scoreLabel(int $score, array $labels): string
    {
        if ($score >= 72) {
            return $labels[2];
        }

        if ($score >= 45) {
            return $labels[1];
        }

        return $labels[0];
    }

    protected function clamp(int $value): int
    {
        return max(0, min(100, $value));
    }

    /** @return array<string, mixed> */
    protected function emptyAnalysis(string $query, int $resultCount, array $quality = []): array
    {
        $quality = array_merge([
            'source_sample_size' => 0,
            'relevant_sample_size' => 0,
            'analyzed_sample_size' => 0,
            'off_topic_count' => 0,
            'duplicate_count' => 0,
            'brand_capped_count' => 0,
            'distinct_brand_count' => 0,
            'relevance_percent' => 0,
            'exact_phrase_percent' => 0,
            'uniqueness_percent' => 0,
            'brand_diversity_percent' => 0,
        ], $quality);

        return [
            'version' => self::VERSION,
            'query' => $query,
            'sample_size' => 0,
            'source_sample_size' => (int) $quality['source_sample_size'],
            'result_count' => max(0, $resultCount),
            'quality' => $quality,
            'scores' => ['opportunity' => 0, 'competition' => 0, 'query_coverage' => 0, 'confidence' => 0],
            'score_labels' => ['opportunity' => 'Veri yok', 'competition' => 'Veri yok', 'query_coverage' => 'Veri yok', 'confidence' => 'Veri yok'],
            'keywords' => [],
            'long_tail' => [],
            'clusters' => [],
            'brands' => [],
            'title_plan' => [
                'recommended_title' => '',
                'character_count' => 0,
                'formula' => '[Ana ürün] + [malzeme] + [ayırt edici özellik] + [stil/kullanım] + [renk veya form]',
                'primary_terms' => [],
                'support_terms' => [],
                'market_options' => [],
                'rules' => [],
            ],
            'recommendations' => [],
            'risks' => [['level' => 'warning', 'label' => 'Veri okunamadı', 'detail' => 'Analiz için ürün başlığı bulunamadı.']],
            'methodology' => [],
        ];
    }
}
