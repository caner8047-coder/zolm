<?php

namespace App\Services\Cargo;

use App\Models\CargoCarrierAccount;

abstract class AbstractSoapCargoConnector extends AbstractCargoConnector
{
    protected function soapCall(CargoCarrierAccount $account, string $wsdl, string $method, array $payload): array
    {
        if (! class_exists(\SoapClient::class)) {
            throw new \RuntimeException('PHP SOAP eklentisi etkin değil.');
        }

        $client = new \SoapClient($wsdl, [
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_NONE,
            'connection_timeout' => 30,
            'trace' => app()->environment('local', 'testing'),
        ]);

        return $this->toArray($client->__soapCall($method, [$payload]));
    }

    protected function hasAuthenticationError(array $payload): bool
    {
        $message = mb_strtolower((string) $this->recursiveValue($payload, [
            'errMessage', 'resultMessage', 'message', 'faultstring',
        ], ''));

        return str_contains($message, 'kullanıcı')
            || str_contains($message, 'sifre')
            || str_contains($message, 'şifre')
            || str_contains($message, 'yetki')
            || str_contains($message, 'unauthorized');
    }
}
