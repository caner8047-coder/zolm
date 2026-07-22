<?php

namespace App\Services\Marketplace;

use App\Models\MpProduct;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ProductCreativeStudioService
{
    /** @return array<string, mixed> */
    public function generateImage(MpProduct $product, string $instruction = '', string $aspectRatio = '1:1'): array
    {
        if (! config('marketplace.features.product_ai_studio_enabled', false)) {
            throw new RuntimeException('AI Studio özelliği henüz bu ortamda aktif değil.');
        }

        $apiKey = trim((string) config('ai.media_api_key', ''));
        if ($apiKey === '') {
            throw new RuntimeException('AI Studio medya API anahtarı yapılandırılmamış.');
        }

        if (! in_array($aspectRatio, ['1:1', '3:4', '4:3', '9:16', '16:9'], true)) {
            throw new RuntimeException('Desteklenmeyen görsel oranı seçildi.');
        }

        $model = trim((string) config('ai.image_model', 'gemini-3.1-flash-image'));
        $prompt = $this->buildPrompt($product, $instruction, $aspectRatio);
        $endpoint = sprintf(
            'https://generativelanguage.googleapis.com/v1/models/%s:generateContent',
            rawurlencode($model)
        );
        $response = $this->client($apiKey)->post($endpoint, [
            'contents' => [[
                'role' => 'user',
                'parts' => [['text' => $prompt]],
            ]],
            'generationConfig' => [
                'responseModalities' => ['TEXT', 'IMAGE'],
                'imageConfig' => ['aspectRatio' => $aspectRatio],
            ],
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Görsel üretimi başarısız oldu: '.$this->safeApiError($response->json(), $response->status()));
        }

        $imagePart = collect((array) data_get($response->json(), 'candidates.0.content.parts', []))
            ->first(fn ($part): bool => is_array($part) && is_array($part['inlineData'] ?? $part['inline_data'] ?? null));
        $inlineData = is_array($imagePart) ? ($imagePart['inlineData'] ?? $imagePart['inline_data'] ?? []) : [];
        $mimeType = strtolower((string) ($inlineData['mimeType'] ?? $inlineData['mime_type'] ?? ''));
        $encoded = (string) ($inlineData['data'] ?? '');

        if (! in_array($mimeType, ['image/png', 'image/jpeg', 'image/webp'], true) || $encoded === '') {
            throw new RuntimeException('AI sağlayıcısı geçerli bir görsel döndürmedi.');
        }

        $binary = base64_decode($encoded, true);
        if ($binary === false || $binary === '' || strlen($binary) > 15 * 1024 * 1024) {
            throw new RuntimeException('Üretilen görsel doğrulanamadı veya dosya sınırını aştı.');
        }

        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            default => 'png',
        };
        $path = 'mp-products/generated/'.(int) $product->user_id.'/'.Str::uuid().'.'.$extension;

        if (! Storage::disk('public')->put($path, $binary)) {
            throw new RuntimeException('Üretilen görsel kaydedilemedi.');
        }

        return [
            'url' => Storage::disk('public')->url($path),
            'path' => $path,
            'mime_type' => $mimeType,
            'model' => $model,
            'aspect_ratio' => $aspectRatio,
            'prompt' => $prompt,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function generateVideo(MpProduct $product, string $instruction = '', string $aspectRatio = '9:16', ?string $referencePath = null): array
    {
        if (! config('marketplace.features.product_ai_video_enabled', false)) {
            throw new RuntimeException('AI Studio video özelliği henüz bu ortamda aktif değil.');
        }

        $apiKey = trim((string) config('ai.media_api_key', ''));
        if ($apiKey === '') {
            throw new RuntimeException('AI Studio medya API anahtarı yapılandırılmamış.');
        }

        if (! in_array($aspectRatio, ['9:16', '16:9'], true)) {
            throw new RuntimeException('Video için 9:16 veya 16:9 oranı seçilmelidir.');
        }

        $model = trim((string) config('ai.video_model', 'gemini-omni-flash-preview'));
        $prompt = $this->buildVideoPrompt($product, $instruction, $aspectRatio);
        $input = [];
        $reference = $this->localReferenceImage($product, $referencePath);
        if ($reference !== null) {
            $input[] = $reference;
        }
        $input[] = ['type' => 'text', 'text' => $prompt];

        $response = $this->client($apiKey)
            ->timeout((int) config('ai.video_timeout', 240))
            ->post('https://generativelanguage.googleapis.com/v1beta/interactions?key='.rawurlencode($apiKey), [
                'model' => $model,
                'input' => $input,
                'response_format' => [
                    'type' => 'video',
                    'aspect_ratio' => $aspectRatio,
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Video üretimi başarısız oldu: '.$this->safeApiError($response->json(), $response->status()));
        }

        $videoPart = collect((array) data_get($response->json(), 'steps', []))
            ->flatMap(fn ($step): array => is_array($step) ? (array) ($step['content'] ?? []) : [])
            ->first(fn ($part): bool => is_array($part) && ($part['type'] ?? null) === 'video');
        $mimeType = strtolower((string) ($videoPart['mime_type'] ?? $videoPart['mimeType'] ?? ''));
        $encoded = (string) ($videoPart['data'] ?? '');

        if ($mimeType !== 'video/mp4' || $encoded === '') {
            throw new RuntimeException('AI sağlayıcısı geçerli bir MP4 video döndürmedi.');
        }

        $binary = base64_decode($encoded, true);
        if ($binary === false || $binary === '' || strlen($binary) > 80 * 1024 * 1024) {
            throw new RuntimeException('Üretilen video doğrulanamadı veya dosya sınırını aştı.');
        }

        $path = 'mp-products/generated/'.(int) $product->user_id.'/'.Str::uuid().'.mp4';
        if (! Storage::disk('public')->put($path, $binary)) {
            throw new RuntimeException('Üretilen video kaydedilemedi.');
        }

        return [
            'url' => Storage::disk('public')->url($path),
            'path' => $path,
            'mime_type' => $mimeType,
            'model' => $model,
            'aspect_ratio' => $aspectRatio,
            'prompt' => $prompt,
            'used_reference_image' => $reference !== null,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    protected function client(string $apiKey): PendingRequest
    {
        return Http::withHeaders([
            'x-goog-api-key' => $apiKey,
            'Content-Type' => 'application/json',
        ])->timeout((int) config('ai.media_timeout', 120));
    }

    protected function buildPrompt(MpProduct $product, string $instruction, string $aspectRatio): string
    {
        $facts = collect([
            'Ürün adı' => $product->product_name,
            'Marka' => $product->brand,
            'Kategori' => $product->category_name,
            'Model' => $product->model_code,
            'Renk' => $product->color,
            'Beden / ölçü' => $product->size,
            'Varyant' => $product->variant,
        ])->filter(fn ($value): bool => filled($value))
            ->map(fn ($value, string $label): string => "{$label}: {$value}")
            ->implode("\n");
        $instruction = Str::limit(trim(strip_tags($instruction)), 600, '');
        $instruction = $instruction !== ''
            ? $instruction
            : 'Temiz açık arka planda, ürünü merkezde gösteren profesyonel e-ticaret ana görseli oluştur.';

        return <<<PROMPT
Profesyonel bir e-ticaret ürün görseli oluştur. Yalnız aşağıdaki ürün gerçeklerini kullan; görünmeyen logo, metin, sertifika, özellik veya aksesuar ekleme. Rengi ve ürün kimliğini değiştirme. Görsel oranı {$aspectRatio}. Görsel üzerinde yazı, fiyat, filigran veya pazaryeri logosu kullanma.

ÜRÜN GERÇEKLERİ:
{$facts}

KREATİF YÖNERGE:
{$instruction}
PROMPT;
    }

    protected function buildVideoPrompt(MpProduct $product, string $instruction, string $aspectRatio): string
    {
        $facts = collect([
            'Ürün adı' => $product->product_name,
            'Marka' => $product->brand,
            'Kategori' => $product->category_name,
            'Model' => $product->model_code,
            'Renk' => $product->color,
            'Beden / ölçü' => $product->size,
            'Varyant' => $product->variant,
        ])->filter(fn ($value): bool => filled($value))
            ->map(fn ($value, string $label): string => "{$label}: {$value}")
            ->implode("\n");
        $instruction = Str::limit(trim(strip_tags($instruction)), 600, '');
        $instruction = $instruction !== ''
            ? $instruction
            : 'Ürünü yavaş ve pürüzsüz kamera hareketiyle gösteren, tek sahneli kısa e-ticaret videosu oluştur.';

        return <<<PROMPT
Profesyonel, tek sahneli kısa bir e-ticaret ürün videosu oluştur. Ürün kimliğini, rengini ve oranlarını koru. Referans görsel varsa ürünü ona sadık tut. Görsel üzerinde metin, fiyat, filigran, pazaryeri logosu veya kanıtta olmayan aksesuar kullanma. Format {$aspectRatio}; kamera hareketi sade, ürün her an net ve merkezde olsun.

ÜRÜN GERÇEKLERİ:
{$facts}

KREATİF YÖNERGE:
{$instruction}
PROMPT;
    }

    /** @return array<string, string>|null */
    private function localReferenceImage(MpProduct $product, ?string $referencePath): ?array
    {
        $referencePath = trim((string) $referencePath);
        $allowedPrefix = 'mp-products/generated/'.(int) $product->user_id.'/';

        if ($referencePath === '' || ! Str::startsWith($referencePath, $allowedPrefix) || ! Storage::disk('public')->exists($referencePath)) {
            return null;
        }

        $binary = Storage::disk('public')->get($referencePath);
        if ($binary === '' || strlen($binary) > 15 * 1024 * 1024) {
            return null;
        }

        $extension = Str::lower(pathinfo($referencePath, PATHINFO_EXTENSION));
        $mimeType = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'image/png',
        };

        return ['type' => 'image', 'data' => base64_encode($binary), 'mime_type' => $mimeType];
    }

    private function safeApiError(mixed $payload, int $status): string
    {
        $message = is_array($payload) ? data_get($payload, 'error.message') : null;

        return Str::limit(strip_tags((string) ($message ?: "HTTP {$status}")), 300, '');
    }
}
