<?php

namespace App\Services\Marketplace;

use App\Models\IntegrationConnection;
use App\Models\MarketplaceStore;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MagentoRestGateway
{
    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $json
     */
    public function call(MarketplaceStore $store, string $method, string $path, array $query = [], ?array $json = null): mixed
    {
        $request = Http::acceptJson()
            ->asJson()
            ->withToken($this->accessToken($store))
            ->timeout((int) config('marketplace.magento.timeout_seconds', 30));

        $response = match (Str::upper($method)) {
            'GET' => $request->get($this->endpoint($store, $path), $query),
            'POST' => $request->post($this->endpoint($store, $path), $json ?? []),
            'PUT' => $request->put($this->endpoint($store, $path), $json ?? []),
            default => throw new \InvalidArgumentException('Desteklenmeyen Magento HTTP metodu: '.$method),
        };

        $this->throwForFailure($response);

        return $response->json();
    }

    public function apiBaseUrl(MarketplaceStore $store): string
    {
        $store->loadMissing('connection');

        if (! $store->connection) {
            throw new \RuntimeException('Magento bağlantı kaydı bulunamadı.');
        }

        $connection = $store->connection;
        $root = $this->rootUrl($connection);

        if (preg_match('#/rest(?:/[^/]+)?/V1$#i', $root) === 1) {
            return $root;
        }

        return $root.'/rest/'.rawurlencode($this->storeViewCode($connection)).'/V1';
    }

    public function sourceCode(MarketplaceStore $store): string
    {
        $store->loadMissing('connection');
        $credentials = $store->connection?->credentials_encrypted ?? [];
        $sourceCode = trim((string) ($credentials['extra_user'] ?? config('marketplace.magento.default_source_code', 'default')));

        if ($sourceCode === '' || preg_match('/^[A-Za-z0-9_-]+$/', $sourceCode) !== 1) {
            throw new \RuntimeException('Magento stok kaynak kodu yalnız harf, rakam, tire ve alt çizgi içerebilir.');
        }

        return $sourceCode;
    }

    protected function endpoint(MarketplaceStore $store, string $path): string
    {
        return $this->apiBaseUrl($store).'/'.ltrim($path, '/');
    }

    protected function accessToken(MarketplaceStore $store): string
    {
        $store->loadMissing('connection');
        $credentials = $store->connection?->credentials_encrypted ?? [];
        $token = trim((string) (($credentials['api_secret'] ?? '') ?: ($credentials['api_key'] ?? '')));

        if ($token === '') {
            throw new \RuntimeException('Magento Integration Access Token zorunludur.');
        }

        return $token;
    }

    protected function rootUrl(IntegrationConnection $connection): string
    {
        $credentials = $connection->credentials_encrypted ?? [];
        $url = rtrim(trim((string) ($connection->api_base_url ?: ($credentials['store_url'] ?? '') ?: config('marketplace.magento.base_url'))), '/');
        $parts = parse_url($url);
        $host = Str::lower(trim((string) ($parts['host'] ?? '')));

        if ($url === '' || ($parts['scheme'] ?? null) !== 'https' || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            throw new \RuntimeException('Magento mağaza URL kullanıcı bilgisi içermeyen geçerli bir HTTPS adresi olmalıdır.');
        }

        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true) || Str::endsWith($host, ['.local', '.internal'])) {
            throw new \RuntimeException('Magento mağaza URL yerel veya özel bir ağ adresi olamaz.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP)
            && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new \RuntimeException('Magento mağaza URL özel veya rezerve IP adresi içeremez.');
        }

        if ($host === 'api.commerce.adobe.com' || Str::endsWith($host, '.api.commerce.adobe.com')) {
            throw new \RuntimeException('Adobe Commerce as a Cloud Service IMS kimlik doğrulaması gerektirir ve bu Magento PaaS/on-prem bağlantısıyla kullanılamaz.');
        }

        return $url;
    }

    protected function storeViewCode(IntegrationConnection $connection): string
    {
        $credentials = $connection->credentials_encrypted ?? [];
        $code = trim((string) ($credentials['store_front_code'] ?? config('marketplace.magento.default_store_view_code', 'all')));

        if ($code === '' || preg_match('/^[A-Za-z0-9_-]+$/', $code) !== 1) {
            throw new \RuntimeException('Magento store view kodu yalnız harf, rakam, tire ve alt çizgi içerebilir.');
        }

        return $code;
    }

    protected function throwForFailure(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $message = trim((string) data_get($response->json(), 'message'));
        $parameters = data_get($response->json(), 'parameters');

        if (is_array($parameters) && $parameters !== []) {
            $message = trim($message.' '.collect($parameters)->filter()->implode(' '));
        }

        throw new \RuntimeException('Magento REST API hatası ('.$response->status().'): '.($message ?: 'Bilinmeyen servis hatası.'));
    }
}
