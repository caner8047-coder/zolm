<?php

namespace App\Services\Returns;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WhatsAppCloudApiService
{
    public function __construct(
        protected ReturnBridgeSettingsService $settingsService,
    ) {
    }

    /**
     * @return array{contents: string, mime_type: string|null, extension: string|null, metadata: array<string, mixed>}
     */
    public function downloadMedia(string $mediaId): array
    {
        $token = trim((string) $this->settingsService->get('access_token', ''));

        if ($token === '') {
            throw new \RuntimeException('WhatsApp access token tanimli degil.');
        }

        $version = trim((string) $this->settingsService->get('graph_version', 'v23.0'));
        $baseUrl = rtrim((string) $this->settingsService->get('graph_base_url', 'https://graph.facebook.com'), '/');

        $metadataResponse = Http::withToken($token)
            ->acceptJson()
            ->timeout(45)
            ->get("{$baseUrl}/{$version}/{$mediaId}");

        if (!$metadataResponse->successful()) {
            throw new \RuntimeException('WhatsApp medya metadata istegi basarisiz: ' . $metadataResponse->body());
        }

        $metadata = $metadataResponse->json();
        $downloadUrl = trim((string) data_get($metadata, 'url', ''));

        if ($downloadUrl === '') {
            throw new \RuntimeException('WhatsApp medya URL bilgisi donmedi.');
        }

        $binaryResponse = Http::withToken($token)
            ->timeout(90)
            ->get($downloadUrl);

        if (!$binaryResponse->successful()) {
            throw new \RuntimeException('WhatsApp medya dosyasi indirilemedi: ' . $binaryResponse->body());
        }

        $mimeType = (string) ($binaryResponse->header('Content-Type') ?: data_get($metadata, 'mime_type', ''));
        $extension = $this->guessExtension($mimeType, $downloadUrl);

        return [
            'contents' => (string) $binaryResponse->body(),
            'mime_type' => $mimeType !== '' ? $mimeType : null,
            'extension' => $extension,
            'metadata' => is_array($metadata) ? $metadata : [],
        ];
    }

    protected function guessExtension(?string $mimeType, string $url): ?string
    {
        $mime = trim((string) $mimeType);

        return match ($mime) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => Str::of(parse_url($url, PHP_URL_PATH) ?: '')->afterLast('.')->lower()->value() ?: null,
        };
    }
}
