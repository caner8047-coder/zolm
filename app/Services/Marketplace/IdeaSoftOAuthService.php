<?php

namespace App\Services\Marketplace;

use App\Models\IntegrationConnection;
use App\Models\MarketplaceStore;
use Illuminate\Support\Facades\Http;

class IdeaSoftOAuthService
{
    public function authorizationUrl(MarketplaceStore $store, string $redirectUri, string $state): string
    {
        $store->loadMissing('connection');
        $connection = $this->connection($store);
        $credentials = $connection->credentials_encrypted ?? [];
        $clientId = trim((string) ($credentials['api_key'] ?? ''));

        if ($clientId === '') {
            throw new \RuntimeException('IdeaSoft Client ID kaydedilmeden yetkilendirme başlatılamaz.');
        }

        return $this->baseUrl($connection).'/panel/auth?'.http_build_query([
            'client_id' => $clientId,
            'response_type' => 'code',
            'state' => $state,
            'redirect_uri' => $redirectUri,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @return array<string, mixed>
     */
    public function exchangeAuthorizationCode(MarketplaceStore $store, string $code, string $redirectUri): array
    {
        $store->loadMissing('connection');
        $connection = $this->connection($store);
        $credentials = $connection->credentials_encrypted ?? [];
        $clientId = trim((string) ($credentials['api_key'] ?? ''));
        $clientSecret = trim((string) ($credentials['api_secret'] ?? ''));

        if ($clientId === '' || $clientSecret === '') {
            throw new \RuntimeException('IdeaSoft Client ID ve Client Secret zorunludur.');
        }

        $response = Http::asForm()
            ->acceptJson()
            ->timeout((int) config('marketplace.ideasoft.timeout_seconds', 30))
            ->post($this->baseUrl($connection).'/oauth/v2/token', [
                'grant_type' => 'authorization_code',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'code' => $code,
                'redirect_uri' => $redirectUri,
            ])
            ->throw();
        $payload = $response->json();
        $accessToken = trim((string) data_get($payload, 'access_token'));
        $refreshToken = trim((string) data_get($payload, 'refresh_token'));

        if ($accessToken === '' || $refreshToken === '') {
            throw new \RuntimeException('IdeaSoft OAuth cevabında access_token veya refresh_token bulunamadı.');
        }

        $expiresIn = max(120, (int) data_get($payload, 'expires_in', 86400));
        $connection->forceFill([
            'credentials_encrypted' => array_merge($credentials, [
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_expires_at' => now()->addSeconds($expiresIn)->toIso8601String(),
                'oauth_scope' => data_get($payload, 'scope'),
            ]),
            'auth_type' => 'authorization_code',
            'status' => 'configured',
            'last_verified_at' => now(),
            'last_error' => null,
        ])->save();
        $store->forceFill(['status' => 'configured'])->save();

        return is_array($payload) ? $payload : [];
    }

    public function redirectUri(): string
    {
        return route('mp.integrations.ideasoft.callback');
    }

    protected function connection(MarketplaceStore $store): IntegrationConnection
    {
        $connection = $store->connection;

        if (! $connection || $connection->provider !== 'ideasoft') {
            throw new \RuntimeException('IdeaSoft bağlantı kaydı bulunamadı. Önce API bilgilerini kaydedin.');
        }

        return $connection;
    }

    protected function baseUrl(IntegrationConnection $connection): string
    {
        $credentials = $connection->credentials_encrypted ?? [];
        $url = rtrim(trim((string) ($connection->api_base_url ?: ($credentials['store_url'] ?? ''))), '/');
        $parts = parse_url($url);

        if ($url === '' || ($parts['scheme'] ?? null) !== 'https' || blank($parts['host'] ?? null)) {
            throw new \RuntimeException('IdeaSoft mağaza URL geçerli bir HTTPS adresi olmalıdır.');
        }

        return $url;
    }
}
