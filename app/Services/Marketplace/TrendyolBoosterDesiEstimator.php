<?php

namespace App\Services\Marketplace;

use App\Models\MpProduct;
use App\Models\TrendyolBoosterProduct;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TrendyolBoosterDesiEstimator
{
    /**
     * @param  array<string, mixed>  $productData
     * @return array<string, mixed>
     */
    public function estimate(int $userId, array $productData, ?TrendyolBoosterProduct $tracked = null): array
    {
        $tracked?->loadMissing('product');
        $savedDesi = (float) ($tracked?->product?->desi ?? 0);

        if ($savedDesi > 0) {
            return $this->result(
                $savedDesi,
                'product_master',
                'ZOLM ürün kartındaki desi',
                99,
                null,
                'Ürünün kayıtlı lojistik desisi kullanıldı.'
            );
        }

        $dimensions = $this->dimensionsFromAttributes($productData);
        if ($dimensions !== null) {
            $desi = $this->desiFromDimensions($dimensions);

            return $this->result(
                $desi,
                'product_dimensions',
                'Trendyol ürün ölçüleri',
                $dimensions['confidence'],
                $dimensions,
                $dimensions['note']
            );
        }

        $historical = $this->historicalCategoryDesi($userId, $productData);
        if ($historical !== null) {
            return $this->result(
                $historical['desi'],
                'historical_category',
                'Benzer ZOLM ürünleri',
                $historical['confidence'],
                null,
                $historical['sample_count'].' benzer üründeki kayıtlı desinin medyanı kullanıldı.'
            );
        }

        $vision = $this->visionEstimate($productData);
        if ($vision !== null) {
            return $this->result(
                $vision['desi'],
                'ai_image',
                'AI görsel paket tahmini',
                $vision['confidence'],
                $vision['dimensions'],
                $vision['note']
            );
        }

        $profile = $this->categoryProfile($productData);

        return $this->result(
            $profile['desi'],
            'category_profile',
            'Kategori desi profili',
            $profile['confidence'],
            $profile['dimensions'],
            $profile['note']
        );
    }

    /** @return array<string, mixed>|null */
    protected function dimensionsFromAttributes(array $productData): ?array
    {
        $attributes = collect((array) ($productData['attributes'] ?? []))
            ->filter(fn ($attribute): bool => is_array($attribute))
            ->map(fn (array $attribute): array => [
                'name' => Str::lower(Str::ascii(trim((string) ($attribute['name'] ?? '')))),
                'value' => trim((string) ($attribute['value'] ?? '')),
            ])
            ->filter(fn (array $attribute): bool => $attribute['name'] !== '' && $attribute['value'] !== '');

        $individual = ['width' => null, 'length' => null, 'height' => null];
        foreach ($attributes as $attribute) {
            $name = $attribute['name'];
            $number = $this->firstDimension($attribute['value']);

            if ($number === null) {
                continue;
            }

            if (preg_match('/^(en|genislik|cap)$/', $name)) {
                $individual['width'] = $number;
            } elseif (preg_match('/^(boy|uzunluk|derinlik)$/', $name)) {
                $individual['length'] = $number;
            } elseif (str_contains($name, 'yukseklik')) {
                $individual['height'] = $number;
            }
        }

        if (collect($individual)->filter(fn ($value): bool => $value !== null)->count() === 3) {
            return [
                'width_cm' => $individual['width'],
                'length_cm' => $individual['length'],
                'height_cm' => $individual['height'],
                'confidence' => 92,
                'note' => 'En, boy ve yükseklik ürün özelliklerinden alındı; paket payı %8 eklendi.',
                'packaging_factor' => 1.08,
            ];
        }

        $combined = $attributes->first(fn (array $attribute): bool => str_contains($attribute['name'], 'boyut')
            || str_contains($attribute['name'], 'ebat')
            || str_contains($attribute['name'], 'olcu')
            || str_contains($attribute['name'], 'paket'));

        if (! $combined) {
            return null;
        }

        $numbers = $this->dimensionNumbers($combined['value']);
        if (count($numbers) >= 3) {
            return [
                'width_cm' => $numbers[0],
                'length_cm' => $numbers[1],
                'height_cm' => $numbers[2],
                'confidence' => str_contains($combined['name'], 'paket') ? 95 : 88,
                'note' => str_contains($combined['name'], 'paket')
                    ? 'Paket ölçüleri Trendyol ürün özelliklerinden alındı.'
                    : 'Üç ürün ölçüsü alındı; paket payı %8 eklendi.',
                'packaging_factor' => str_contains($combined['name'], 'paket') ? 1.0 : 1.08,
            ];
        }

        $context = Str::lower(Str::ascii(($productData['title'] ?? '').' '.($productData['category_name'] ?? '')));
        if (count($numbers) === 2 && preg_match('/puf|tabure|saksi|abajur|silindir|yuvarlak/', $context)) {
            return [
                'width_cm' => $numbers[0],
                'length_cm' => $numbers[0],
                'height_cm' => $numbers[1],
                'confidence' => 76,
                'note' => 'İki ölçü silindirik ürün yapısına göre çap x çap x yükseklik olarak yorumlandı; paket payı %8 eklendi.',
                'packaging_factor' => 1.08,
            ];
        }

        return null;
    }

    /** @return array{desi: float, confidence: float, sample_count: int}|null */
    protected function historicalCategoryDesi(int $userId, array $productData): ?array
    {
        $contextTokens = $this->tokens(($productData['category_name'] ?? '').' '.($productData['title'] ?? ''));
        if ($contextTokens === []) {
            return null;
        }

        $matches = MpProduct::query()
            ->where('user_id', $userId)
            ->where('desi', '>', 0)
            ->latest('updated_at')
            ->limit(300)
            ->get(['product_name', 'category_name', 'desi'])
            ->map(function (MpProduct $product) use ($contextTokens): array {
                $tokens = $this->tokens($product->category_name.' '.$product->product_name);
                $overlap = count(array_intersect($contextTokens, $tokens));

                return ['desi' => (float) $product->desi, 'overlap' => $overlap];
            })
            ->filter(fn (array $row): bool => $row['overlap'] >= 2)
            ->sortByDesc('overlap')
            ->take(50)
            ->pluck('desi')
            ->sort()
            ->values();

        if ($matches->count() < 3) {
            return null;
        }

        return [
            'desi' => $this->median($matches),
            'confidence' => min(90, 66 + ($matches->count() * 2)),
            'sample_count' => $matches->count(),
        ];
    }

    /** @return array<string, mixed>|null */
    protected function visionEstimate(array $productData): ?array
    {
        $config = $this->geminiConfig();
        $imageUrl = trim((string) ($productData['image_url'] ?? ''));

        if ($config === null || ! Str::startsWith($imageUrl, 'https://')) {
            return null;
        }

        try {
            $image = Http::timeout(12)->retry(1, 250)->get($imageUrl);
            if (! $image->successful() || strlen($image->body()) > 6_000_000) {
                return null;
            }

            $mime = $image->header('Content-Type') ?: 'image/jpeg';
            $context = [
                'title' => $productData['title'] ?? '',
                'category' => $productData['category_name'] ?? '',
                'attributes' => $productData['attributes'] ?? [],
            ];
            $prompt = 'Bir e-ticaret lojistik uzmanı gibi davran. Ürünün kargoya hazır paket ölçülerini santimetre cinsinden muhafazakar tahmin et. Görsel tek başına kesin ölçek vermiyorsa güveni düşük tut. Desi = en x boy x yükseklik / 3000. Yalnızca istenen JSON şemasını döndür. Ürün: '.json_encode($context, JSON_UNESCAPED_UNICODE);
            $url = sprintf(
                'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
                $config['model'],
                $config['api_key']
            );
            $response = Http::timeout(min(45, (int) config('ai.timeout', 60)))
                ->acceptJson()
                ->post($url, [
                    'contents' => [[
                        'role' => 'user',
                        'parts' => [
                            ['text' => $prompt],
                            ['inline_data' => ['mime_type' => $mime, 'data' => base64_encode($image->body())]],
                        ],
                    ]],
                    'generationConfig' => [
                        'temperature' => 0.1,
                        'response_mime_type' => 'application/json',
                        'response_schema' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'width_cm' => ['type' => 'NUMBER'],
                                'length_cm' => ['type' => 'NUMBER'],
                                'height_cm' => ['type' => 'NUMBER'],
                                'estimated_desi' => ['type' => 'NUMBER'],
                                'confidence' => ['type' => 'NUMBER'],
                                'reasoning' => ['type' => 'STRING'],
                            ],
                            'required' => ['width_cm', 'length_cm', 'height_cm', 'estimated_desi', 'confidence', 'reasoning'],
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                return null;
            }

            $payload = json_decode((string) data_get($response->json(), 'candidates.0.content.parts.0.text'), true);
            if (! is_array($payload)) {
                return null;
            }

            $dimensions = [
                'width_cm' => max(1, (float) ($payload['width_cm'] ?? 0)),
                'length_cm' => max(1, (float) ($payload['length_cm'] ?? 0)),
                'height_cm' => max(1, (float) ($payload['height_cm'] ?? 0)),
                'packaging_factor' => 1.0,
            ];
            $calculated = $this->desiFromDimensions($dimensions);
            $reported = max(0, (float) ($payload['estimated_desi'] ?? 0));
            $confidence = (float) ($payload['confidence'] ?? 0);
            $confidence = $confidence <= 1 ? $confidence * 100 : $confidence;

            return [
                'desi' => max($calculated, $reported),
                'confidence' => min(60, max(20, $confidence)),
                'dimensions' => $dimensions,
                'note' => 'Görselden paket tahmini: '.Str::limit(trim((string) ($payload['reasoning'] ?? '')), 300, ''),
            ];
        } catch (\Throwable $exception) {
            Log::debug('Trendyol Booster görsel desi tahmini atlandı.', ['error' => $exception->getMessage()]);

            return null;
        }
    }

    /** @return array{desi: float, confidence: float, dimensions: ?array, note: string} */
    protected function categoryProfile(array $productData): array
    {
        $context = Str::lower(Str::ascii(($productData['category_name'] ?? '').' '.($productData['title'] ?? '')));
        $profiles = [
            [['puf', 'tabure'], 16, [38, 38, 33], 'Puf ve tabure kategori profili'],
            [['ayakkabi', 'sneaker', 'bot'], 3, [35, 22, 12], 'Ayakkabı kutusu kategori profili'],
            [['telefon', 'cep telefonu'], 1, [20, 12, 8], 'Telefon paketi kategori profili'],
            [['laptop', 'notebook'], 5, [45, 35, 10], 'Dizüstü bilgisayar paketi kategori profili'],
            [['canta', 'sirt cantasi'], 5, [40, 30, 12], 'Çanta kategori profili'],
            [['sandalye'], 35, [65, 55, 50], 'Sandalye kategori profili'],
            [['koltuk', 'kanepe'], 100, [100, 80, 75], 'Koltuk kategori profili'],
            [['masa'], 80, [110, 70, 30], 'Masa kategori profili'],
            [['hali', 'kilim'], 15, [100, 20, 20], 'Halı kategori profili'],
            [['giyim', 'elbise', 'gomlek', 'pantolon'], 2, [35, 25, 7], 'Giyim paketi kategori profili'],
        ];

        foreach ($profiles as [$keywords, $desi, $dimensions, $label]) {
            if (collect($keywords)->contains(fn (string $keyword): bool => str_contains($context, $keyword))) {
                return [
                    'desi' => $desi,
                    'confidence' => 42,
                    'dimensions' => [
                        'width_cm' => $dimensions[0],
                        'length_cm' => $dimensions[1],
                        'height_cm' => $dimensions[2],
                        'packaging_factor' => 1.0,
                    ],
                    'note' => $label.' kullanıldı; gerçek paket ölçüsüyle doğrulanmalıdır.',
                ];
            }
        }

        return [
            'desi' => 3,
            'confidence' => 15,
            'dimensions' => null,
            'note' => 'Kategori için ölçü veya geçmiş veri bulunamadı; düşük güvenli küçük paket varsayımı kullanıldı.',
        ];
    }

    /** @return array<string, mixed> */
    protected function result(
        float $desi,
        string $source,
        string $sourceLabel,
        float $confidence,
        ?array $dimensions,
        string $note,
    ): array {
        $desi = max(0.1, round($desi, 2));

        return [
            'estimated_desi' => $desi,
            'billable_desi' => (int) ceil($desi),
            'source' => $source,
            'source_label' => $sourceLabel,
            'confidence' => round(max(0, min(100, $confidence)), 1),
            'dimensions' => $dimensions,
            'note' => $note,
        ];
    }

    /** @param array<string, mixed> $dimensions */
    protected function desiFromDimensions(array $dimensions): float
    {
        $factor = max(1, (float) ($dimensions['packaging_factor'] ?? 1));
        $volume = (float) $dimensions['width_cm']
            * (float) $dimensions['length_cm']
            * (float) $dimensions['height_cm']
            * $factor;

        return round(max(0.1, $volume / 3000), 2);
    }

    /** @return array<int, float> */
    protected function dimensionNumbers(string $value): array
    {
        preg_match_all('/\d+(?:[.,]\d+)?/u', $value, $matches);

        return collect($matches[0] ?? [])
            ->map(fn (string $number): float => $this->dimensionToCentimeters($number, $value))
            ->filter(fn (float $number): bool => $number > 0 && $number <= 500)
            ->take(3)
            ->values()
            ->all();
    }

    protected function firstDimension(string $value): ?float
    {
        return $this->dimensionNumbers($value)[0] ?? null;
    }

    protected function dimensionToCentimeters(string $number, string $source): float
    {
        $value = (float) str_replace(',', '.', $number);
        $source = Str::lower(Str::ascii($source));

        if (str_contains($source, 'mm')) {
            return $value / 10;
        }

        if (preg_match('/\d+(?:[.,]\d+)?\s*m(?:etre)?\b/', $source) && ! str_contains($source, 'cm')) {
            return $value * 100;
        }

        return $value;
    }

    /** @return array<int, string> */
    protected function tokens(string $value): array
    {
        $value = Str::lower(Str::ascii($value));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?: '';

        return collect(explode(' ', $value))
            ->filter(fn (string $token): bool => strlen($token) >= 3)
            ->unique()
            ->values()
            ->all();
    }

    /** @param Collection<int, float> $values */
    protected function median(Collection $values): float
    {
        $values = $values->sort()->values();
        $middle = intdiv($values->count(), 2);

        return round($values->count() % 2 === 1
            ? (float) $values[$middle]
            : (((float) $values[$middle - 1] + (float) $values[$middle]) / 2), 2);
    }

    /** @return array{api_key: string, model: string}|null */
    protected function geminiConfig(): ?array
    {
        $candidates = [
            [config('ai.provider'), config('ai.api_key'), config('ai.model')],
            [config('ai.fallback_provider'), config('ai.fallback_api_key'), config('ai.fallback_model')],
            [config('ai.fallback2_provider'), config('ai.fallback2_api_key'), config('ai.fallback2_model')],
        ];

        foreach ($candidates as [$provider, $apiKey, $model]) {
            if ($provider === 'gemini' && trim((string) $apiKey) !== '') {
                return [
                    'api_key' => (string) $apiKey,
                    'model' => trim((string) $model) ?: 'gemini-2.0-flash',
                ];
            }
        }

        return null;
    }
}
