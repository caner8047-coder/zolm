<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceStore;

class TicimaxSoapGateway
{
    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>|array<int, mixed>
     */
    public function call(MarketplaceStore $store, string $service, string $operation, array $parameters): array
    {
        if (! class_exists(\SoapClient::class)) {
            throw new \RuntimeException('Ticimax bağlantısı için PHP SOAP eklentisi etkin olmalıdır.');
        }

        $wsdl = $this->wsdlUrl($store, $service);
        $client = new \SoapClient($wsdl, [
            'cache_wsdl' => app()->environment('testing') ? WSDL_CACHE_NONE : WSDL_CACHE_MEMORY,
            'connection_timeout' => (int) config('marketplace.ticimax.timeout_seconds', 30),
            'exceptions' => true,
            'keep_alive' => true,
            'trace' => false,
        ]);
        $response = $client->__soapCall($operation, [$parameters]);
        $normalized = $this->normalize($response);

        return is_array($normalized) ? $normalized : [];
    }

    public function wsdlUrl(MarketplaceStore $store, string $service): string
    {
        $serviceName = match ($service) {
            'orders' => 'SiparisServis',
            'products' => 'UrunServis',
            default => throw new \InvalidArgumentException('Desteklenmeyen Ticimax SOAP servisi: '.$service),
        };

        return $this->baseUrl($store).'/Servis/'.$serviceName.'.svc?wsdl';
    }

    protected function baseUrl(MarketplaceStore $store): string
    {
        $store->loadMissing('connection');
        $credentials = $store->connection?->credentials_encrypted ?? [];
        $url = rtrim(trim((string) ($store->connection?->api_base_url ?: ($credentials['store_url'] ?? ''))), '/');
        $parts = parse_url($url);
        $host = (string) ($parts['host'] ?? '');

        if ($url === '' || ($parts['scheme'] ?? null) !== 'https' || $host === '') {
            throw new \RuntimeException('Ticimax mağaza URL geçerli bir HTTPS adresi olmalıdır.');
        }

        if (in_array(strtolower($host), ['localhost', '127.0.0.1', '::1'], true)) {
            throw new \RuntimeException('Ticimax mağaza URL yerel bir adres olamaz.');
        }

        return $url;
    }

    protected function normalize(mixed $value): mixed
    {
        if (is_object($value)) {
            $value = get_object_vars($value);
        }

        if (! is_array($value)) {
            return $value;
        }

        return array_map(fn ($item) => $this->normalize($item), $value);
    }
}
