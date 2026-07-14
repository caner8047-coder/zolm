<?php

namespace App\Services\Support;

use App\Models\IntegrationConnection;
use Illuminate\Support\Facades\Http;

class HttpGoogleBusinessConnector implements GoogleBusinessConnectorInterface
{
    private ?IntegrationConnection $connection = null;

    public function useConnection(IntegrationConnection $connection): self
    {
        $this->connection = $connection;
        return $this;
    }

    /** Google credential ve kaynak erişimini gerçek API çağrısıyla doğrular. */
    public function healthCheck(IntegrationConnection $connection): array
    {
        try {
            $credentials = $connection->credentials_encrypted ?? [];
            $token = trim((string) ($credentials['access_token'] ?? ''));
            $accountId = trim((string) ($credentials['account_id'] ?? ''));
            $locationId = trim((string) ($credentials['location_id'] ?? ''));
            if ($token === '' || $accountId === '' || $locationId === '') {
                throw new \RuntimeException('Google erişim anahtarı, hesap veya lokasyon kimliği eksik.');
            }
            foreach ([$accountId, $locationId] as $identifier) {
                if (!preg_match('/^[A-Za-z0-9_-]+$/', $identifier)) {
                    throw new \RuntimeException('Google Business kaynak kimliği geçersiz.');
                }
            }
            $baseUrl = rtrim((string) ($connection->api_base_url ?: 'https://mybusiness.googleapis.com/v4'), '/');
            if (!str_starts_with($baseUrl, 'https://mybusiness.googleapis.com/')) {
                throw new \RuntimeException('Google Business API adresi resmi Google alan adı olmalıdır.');
            }
            $response = Http::acceptJson()->withToken($token)
                ->timeout(8)->connectTimeout(3)->retry(1, 200, throw: false)
                ->get("{$baseUrl}/accounts/{$accountId}/locations/{$locationId}");
            if (!$response->successful()) {
                throw new \RuntimeException('Google credential doğrulaması başarısız (HTTP ' . $response->status() . ').');
            }
            $connection->update(['status' => 'active', 'last_verified_at' => now(), 'last_error' => null]);
            return ['status' => 'ok', 'message' => 'Google credential ve lokasyon erişimi doğrulandı.'];
        } catch (\Throwable $e) {
            $connection->update(['status' => 'error', 'last_error' => mb_substr($e->getMessage(), 0, 500)]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function reply(string $reviewId, string $message): string
    {
        $connection = $this->connection;
        $credentials = $connection?->credentials_encrypted ?? [];
        $token = trim((string) ($credentials['access_token'] ?? ''));
        $accountId = trim((string) ($credentials['account_id'] ?? ''));
        $locationId = trim((string) ($credentials['location_id'] ?? ''));
        if (!$connection || $token === '' || $accountId === '' || $locationId === '' || trim($message) === '') {
            throw new \RuntimeException('Google Business bağlantı bilgileri veya mesaj eksik.');
        }
        foreach ([$accountId, $locationId, $reviewId] as $identifier) {
            if (!preg_match('/^[A-Za-z0-9_-]+$/', $identifier)) {
                throw new \RuntimeException('Google Business kaynak kimliği geçersiz.');
            }
        }

        $baseUrl = rtrim((string) ($connection->api_base_url ?: 'https://mybusiness.googleapis.com/v4'), '/');
        if (!str_starts_with($baseUrl, 'https://mybusiness.googleapis.com/')) {
            throw new \RuntimeException('Google Business API adresi resmi Google alan adı olmalıdır.');
        }
        $url = "{$baseUrl}/accounts/{$accountId}/locations/{$locationId}/reviews/{$reviewId}/reply";
        $response = Http::acceptJson()->asJson()->withToken($token)
            ->timeout(8)->connectTimeout(3)->retry(2, 200, throw: false)
            ->put($url, ['comment' => $message]);
        if (!$response->successful()) {
            throw new \RuntimeException('Google Business API HTTP ' . $response->status() . ': ' . mb_substr($response->body(), 0, 250));
        }
        $id = $response->json('updateTime') ?? $response->json('name');
        if (!is_string($id) || $id === '') {
            throw new \RuntimeException('Google Business API doğrulanabilir yanıt kimliği döndürmedi.');
        }
        return $id;
    }
}
