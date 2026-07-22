<?php

namespace App\Services\Marketplace;

use App\Models\IntegrationConnection;
use App\Models\MarketplaceStore;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TSoftRestGateway
{
    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    public function call(MarketplaceStore $store, string $path, array $parameters = []): array
    {
        $response = $this->send($store, $path, $parameters, $this->token($store));
        $payload = $response->json();

        if ($this->isExpiredTokenResponse($payload)) {
            Cache::forget($this->cacheKey($store));
            $response = $this->send($store, $path, $parameters, $this->token($store));
            $payload = $response->json();
        }

        if (! is_array($payload)) {
            throw new \RuntimeException('T-Soft REST servisi geçerli JSON yanıtı döndürmedi.');
        }

        $this->assertSuccessful($payload);

        return $payload;
    }

    public function baseUrl(MarketplaceStore $store): string
    {
        $store->loadMissing('connection');

        if (! $store->connection) {
            throw new \RuntimeException('T-Soft bağlantı kaydı bulunamadı.');
        }

        return $this->baseUrlFromConnection($store->connection);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    protected function send(MarketplaceStore $store, string $path, array $parameters, string $token): Response
    {
        return Http::asForm()
            ->acceptJson()
            ->timeout((int) config('marketplace.tsoft.timeout_seconds', 30))
            ->post($this->baseUrl($store).'/rest1/'.ltrim($path, '/'), array_merge([
                'token' => $token,
            ], array_filter($parameters, static fn ($value) => $value !== null && $value !== '')))
            ->throw();
    }

    protected function token(MarketplaceStore $store): string
    {
        $cacheKey = $this->cacheKey($store);
        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $store->loadMissing('connection');
        $credentials = $store->connection?->credentials_encrypted ?? [];
        $username = trim((string) ($credentials['api_key'] ?? ''));
        $password = trim((string) ($credentials['api_secret'] ?? ''));

        if ($username === '' || $password === '') {
            throw new \RuntimeException('T-Soft Web Servis kullanıcı adı ve parolası zorunludur.');
        }

        $response = Http::asForm()
            ->acceptJson()
            ->timeout((int) config('marketplace.tsoft.timeout_seconds', 30))
            ->post($this->baseUrl($store).'/rest1/auth/login/'.rawurlencode($username), [
                'pass' => $password,
            ])
            ->throw();
        $payload = $response->json();

        if (! is_array($payload)) {
            throw new \RuntimeException('T-Soft kimlik doğrulama servisi geçerli JSON yanıtı döndürmedi.');
        }

        $this->assertSuccessful($payload);
        $token = trim((string) data_get($payload, 'data.0.token'));

        if ($token === '') {
            throw new \RuntimeException('T-Soft kimlik doğrulama cevabında token bulunamadı.');
        }

        Cache::put($cacheKey, $token, $this->tokenTtl(data_get($payload, 'data.0.expirationTime')));

        return $token;
    }

    protected function baseUrlFromConnection(IntegrationConnection $connection): string
    {
        $credentials = $connection->credentials_encrypted ?? [];
        $url = rtrim(trim((string) ($connection->api_base_url ?: ($credentials['store_url'] ?? '') ?: config('marketplace.tsoft.base_url'))), '/');
        $parts = parse_url($url);
        $host = Str::lower(trim((string) ($parts['host'] ?? '')));

        if ($url === '' || ($parts['scheme'] ?? null) !== 'https' || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            throw new \RuntimeException('T-Soft mağaza URL kullanıcı bilgisi içermeyen geçerli bir HTTPS adresi olmalıdır.');
        }

        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true) || Str::endsWith($host, ['.local', '.internal'])) {
            throw new \RuntimeException('T-Soft mağaza URL yerel veya özel bir ağ adresi olamaz.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP)
            && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new \RuntimeException('T-Soft mağaza URL özel veya rezerve IP adresi içeremez.');
        }

        return $url;
    }

    protected function cacheKey(MarketplaceStore $store): string
    {
        $store->loadMissing('connection');
        $credentials = $store->connection?->credentials_encrypted ?? [];

        return 'marketplace:tsoft:token:'.sha1(implode('|', [
            (string) ($store->connection?->id ?? 0),
            (string) ($store->connection?->api_base_url ?? ''),
            (string) ($credentials['store_url'] ?? ''),
            (string) ($credentials['api_key'] ?? ''),
        ]));
    }

    protected function tokenTtl(mixed $expirationTime): int
    {
        try {
            $expiresAt = CarbonImmutable::createFromFormat('d-m-Y H:i:s', (string) $expirationTime, 'Europe/Istanbul');
            $seconds = now()->diffInSeconds($expiresAt, false) - 60;

            return (int) max(60, min(86400, $seconds));
        } catch (\Throwable) {
            return 3600;
        }
    }

    protected function isExpiredTokenResponse(mixed $payload): bool
    {
        if (! is_array($payload) || ($payload['success'] ?? false) === true) {
            return false;
        }

        $message = Str::lower($this->messageText($payload));

        return Str::contains($message, ['token', 'oturum', 'giriş yapınız', 'giris yapiniz']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function assertSuccessful(array $payload): void
    {
        if (($payload['success'] ?? false) === true) {
            return;
        }

        throw new \RuntimeException('T-Soft API hatası: '.($this->messageText($payload) ?: 'Bilinmeyen servis hatası.'));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function messageText(array $payload): string
    {
        return collect(data_get($payload, 'message', []))
            ->filter('is_array')
            ->map(function (array $message): string {
                $code = trim((string) ($message['code'] ?? ''));
                $text = collect((array) ($message['text'] ?? []))->filter()->implode(' ');

                return trim($code.' '.$text);
            })
            ->filter()
            ->implode(' | ');
    }
}
