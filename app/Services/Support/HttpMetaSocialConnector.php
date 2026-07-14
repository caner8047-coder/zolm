<?php

namespace App\Services\Support;

use App\Models\IntegrationConnection;
use Illuminate\Support\Facades\Http;

class HttpMetaSocialConnector implements MetaSocialConnectorInterface
{
    private ?IntegrationConnection $connection = null;

    public function useConnection(IntegrationConnection $connection): self
    {
        $this->connection = $connection;
        return $this;
    }

    /**
     * Credential'ın yalnız veritabanında bulunmasını değil Meta tarafından
     * gerçekten kabul edildiğini doğrular.
     */
    public function healthCheck(IntegrationConnection $connection): array
    {
        try {
            $credentials = $connection->credentials_encrypted ?? [];
            $token = trim((string) ($credentials['access_token'] ?? ''));
            if ($token === '') {
                throw new \RuntimeException('Meta erişim anahtarı eksik.');
            }
            $baseUrl = rtrim((string) ($connection->api_base_url ?: 'https://graph.facebook.com/v21.0'), '/');
            if (!str_starts_with($baseUrl, 'https://graph.facebook.com/')) {
                throw new \RuntimeException('Meta API adresi resmi Graph API alan adı olmalıdır.');
            }
            $response = Http::acceptJson()->withToken($token)
                ->timeout(8)->connectTimeout(3)->retry(1, 200, throw: false)
                ->get($baseUrl . '/me', ['fields' => 'id,name']);
            if (!$response->successful() || !is_string($response->json('id')) || $response->json('id') === '') {
                throw new \RuntimeException('Meta credential doğrulaması başarısız (HTTP ' . $response->status() . ').');
            }
            $connection->update(['status' => 'active', 'last_verified_at' => now(), 'last_error' => null]);
            return ['status' => 'ok', 'message' => 'Meta credential gerçek API çağrısıyla doğrulandı.'];
        } catch (\Throwable $e) {
            $connection->update(['status' => 'error', 'last_error' => mb_substr($e->getMessage(), 0, 500)]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function send(string $key, string $threadId, string $message): string
    {
        $connection = $this->connection;
        $credentials = $connection?->credentials_encrypted ?? [];
        $token = trim((string) ($credentials['access_token'] ?? ''));
        if (!$connection || $token === '' || trim($threadId) === '' || trim($message) === '') {
            throw new \RuntimeException('Meta bağlantı bilgileri veya mesaj eksik.');
        }

        $baseUrl = rtrim((string) ($connection->api_base_url ?: 'https://graph.facebook.com/v21.0'), '/');
        if (!str_starts_with($baseUrl, 'https://graph.facebook.com/')) {
            throw new \RuntimeException('Meta API adresi resmi Graph API alan adı olmalıdır.');
        }
        $response = Http::acceptJson()->asJson()->withToken($token)
            ->timeout(8)->connectTimeout(3)->retry(2, 200, throw: false)
            ->post($baseUrl . '/me/messages', [
                'recipient' => ['id' => $threadId],
                'message' => ['text' => $message],
                'messaging_type' => 'RESPONSE',
            ]);
        if (!$response->successful()) {
            throw new \RuntimeException('Meta API HTTP ' . $response->status() . ': ' . mb_substr($response->body(), 0, 250));
        }
        $id = $response->json('message_id') ?? $response->json('id');
        if (!is_string($id) || $id === '') {
            throw new \RuntimeException('Meta API doğrulanabilir mesaj kimliği döndürmedi.');
        }
        return $id;
    }
}
