<?php

namespace App\Services\Support\Integration;

use App\Models\IntegrationConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CustomerCareHttpConnector
{
    public function connection(int $storeId, string $provider): IntegrationConnection
    {
        $connection = IntegrationConnection::where('store_id', $storeId)
            ->where('provider', $provider)
            ->where('status', 'active')
            ->first();

        if (!$connection) {
            throw new \RuntimeException(strtoupper($provider) . ' bağlantısı aktif değil.');
        }

        $this->assertSafeBaseUrl((string) $connection->api_base_url);
        return $connection;
    }

    public function healthCheck(IntegrationConnection $connection): array
    {
        try {
            $this->assertSafeBaseUrl((string) $connection->api_base_url);
            $credentials = $connection->credentials_encrypted ?? [];
            $path = (string) ($credentials['health_path'] ?? '/health');
            $response = $this->client($connection)->get($this->url($connection, $path));
            if (!$response->successful()) {
                throw new \RuntimeException('HTTP ' . $response->status());
            }

            $connection->update(['last_verified_at' => now(), 'last_error' => null]);
            return ['success' => true, 'status' => 'ok', 'http_status' => $response->status()];
        } catch (\Throwable $e) {
            $connection->update(['last_error' => mb_substr($e->getMessage(), 0, 500)]);
            return ['success' => false, 'status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function post(IntegrationConnection $connection, string $path, array $payload, string $idempotencyKey): Response
    {
        $this->assertSafeBaseUrl((string) $connection->api_base_url);
        if (!preg_match('/^[A-Za-z0-9._:-]{8,120}$/', $idempotencyKey)) {
            throw new \InvalidArgumentException('Geçerli bir idempotency anahtarı zorunludur.');
        }

        $response = $this->client($connection)
            ->withHeaders([
                'X-Zolm-Schema-Version' => '1.0',
                'X-Zolm-Idempotency-Key' => $idempotencyKey,
            ])
            ->post($this->url($connection, $path), $payload);

        if (!$response->successful()) {
            $message = 'HTTP ' . $response->status() . ': ' . mb_substr($response->body(), 0, 300);
            $connection->update(['last_error' => $message]);
            throw new \RuntimeException($message);
        }

        $connection->update(['last_verified_at' => now(), 'last_error' => null]);
        return $response;
    }

    private function client(IntegrationConnection $connection): PendingRequest
    {
        $credentials = $connection->credentials_encrypted ?? [];
        $token = trim((string) ($credentials['access_token'] ?? $credentials['api_key'] ?? ''));
        if ($token === '') {
            throw new \RuntimeException('Entegrasyon erişim anahtarı eksik.');
        }

        $request = $this->secureRequest((string) $connection->api_base_url)
            ->retry(2, 200, throw: false);

        return $connection->auth_type === 'bearer' || isset($credentials['access_token'])
            ? $request->withToken($token)
            : $request->withHeaders(['X-API-Key' => $token]);
    }

    private function url(IntegrationConnection $connection, string $path): string
    {
        if (!str_starts_with($path, '/') || str_contains($path, '..')) {
            throw new \InvalidArgumentException('Entegrasyon endpoint yolu geçersiz.');
        }
        return rtrim((string) $connection->api_base_url, '/') . $path;
    }

    private function assertSafeBaseUrl(string $url): void
    {
        $this->assertSafeUrl($url, false);
    }

    public function assertSafeEndpointUrl(string $url): void
    {
        $this->assertSafeUrl($url, true);
    }

    public function secureRequest(string $url): PendingRequest
    {
        $this->assertSafeEndpointUrl($url);
        $parts = parse_url($url) ?: [];
        $host = $this->normalizeHost((string) ($parts['host'] ?? ''));
        $publicAddresses = $this->resolvePublicAddresses($host);

        $request = Http::acceptJson()
            ->asJson()
            ->timeout(8)
            ->connectTimeout(3)
            ->withOptions(['allow_redirects' => false]);

        // DNS cevaplarını da özel/rezerve ağ açısından denetle. Çözümlenen ilk
        // güvenli IP'yi cURL'e sabitlemek DNS rebinding/TOCTOU penceresini kapatır.
        if ($publicAddresses !== [] && !filter_var($host, FILTER_VALIDATE_IP) && defined('CURLOPT_RESOLVE')) {
            $port = (int) ($parts['port'] ?? 443);
            $pinnedAddress = str_contains($publicAddresses[0], ':')
                ? '[' . $publicAddresses[0] . ']'
                : $publicAddresses[0];
            $request = $request->withOptions([
                'curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:{$pinnedAddress}"]],
                'allow_redirects' => false,
            ]);
        }

        return $request;
    }

    private function assertSafeUrl(string $url, bool $allowPath): void
    {
        $parts = parse_url($url);
        $host = $this->normalizeHost((string) ($parts['host'] ?? ''));
        if (($parts['scheme'] ?? null) !== 'https'
            || $host === ''
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || (!$allowPath && !in_array((string) ($parts['path'] ?? ''), ['', '/'], true))) {
            throw new \InvalidArgumentException('Entegrasyon adresi kullanıcı bilgisi içermeyen geçerli bir HTTPS URL olmalıdır.');
        }
        if ($host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            throw new \InvalidArgumentException('Yerel veya özel ağ adreslerine bağlantı kurulamaz.');
        }

        if (preg_match('/^[0-9.]+$/', $host) && !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new \InvalidArgumentException('Standart dışı sayısal ağ adreslerine bağlantı kurulamaz.');
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $this->assertPublicAddress($host);
        } elseif (!preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $host)) {
            throw new \InvalidArgumentException('Entegrasyon adresi geçerli bir tam alan adı içermelidir.');
        }
    }

    private function normalizeHost(string $host): string
    {
        return strtolower(rtrim(trim($host, '[]'), '.'));
    }

    /** @return list<string> */
    private function resolvePublicAddresses(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $this->assertPublicAddress($host);
            return [$host];
        }

        $addresses = [];
        if (function_exists('dns_get_record')) {
            foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $record) {
                $address = $record['ip'] ?? $record['ipv6'] ?? null;
                if (is_string($address)) {
                    $addresses[] = $address;
                }
            }
        }
        if ($addresses === [] && function_exists('gethostbynamel')) {
            $addresses = @gethostbynamel($host) ?: [];
        }

        $addresses = array_values(array_unique($addresses));
        foreach ($addresses as $address) {
            $this->assertPublicAddress($address);
        }

        return $addresses;
    }

    private function assertPublicAddress(string $address): void
    {
        if (!filter_var($address, FILTER_VALIDATE_IP)
            || !filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new \InvalidArgumentException('Özel/rezerve IP adreslerine bağlantı kurulamaz.');
        }
    }
}
