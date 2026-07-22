<?php

namespace App\Services\Cargo;

use App\Models\CargoCarrierAccount;
use App\Models\Shipment;
use App\Services\Cargo\Contracts\CargoCarrierConnector;

class ArasCargoConnector extends AbstractSoapCargoConnector implements CargoCarrierConnector
{
    public function testConnection(CargoCarrierAccount $account): array
    {
        $response = $this->call($account, 'GetOrderWithIntegrationCode', [
            'integrationCode' => (string) ($this->credential($account, 'test_reference') ?: '__ZOLM_TEST__'),
        ]);

        if ($this->hasAuthenticationError($response)) {
            throw new \RuntimeException('Aras Kargo kullanıcı adı veya şifresi doğrulanamadı.');
        }

        return ['success' => true, 'ready' => true, 'message' => 'Aras Kargo bağlantısı başarılı.', 'response' => $response];
    }

    public function createShipment(CargoCarrierAccount $account, Shipment $shipment): array
    {
        $response = $this->call($account, 'SetOrder', [
            'orderInfo' => ['Order' => [[
                'UserName' => $this->credential($account, 'username'),
                'Password' => $this->credential($account, 'password'),
                'TradingWaybillNumber' => $this->reference($shipment),
                'InvoiceNumber' => $this->reference($shipment),
                'ReceiverName' => $shipment->customer_name,
                'ReceiverAddress' => $shipment->destination_address,
                'ReceiverPhone1' => $shipment->customer_phone,
                'ReceiverCityName' => $shipment->destination_city,
                'ReceiverTownName' => $shipment->destination_district,
                'VolumetricWeight' => max(1, (int) ceil((float) $shipment->total_desi)),
                'Weight' => max(1, (int) ceil((float) $shipment->total_weight)),
                'PieceCount' => max(1, (int) $shipment->parcel_count),
                'IntegrationCode' => $this->reference($shipment),
                'PayorTypeCode' => '1',
                'IsCod' => '0',
            ]]],
        ]);
        $resultCode = (string) $this->recursiveValue($response, ['ResultCode'], '0');
        if (! in_array($resultCode, ['', '0'], true)) {
            throw new \RuntimeException('Aras gönderi oluşturma hatası: '.(string) $this->recursiveValue($response, ['ResultMessage'], $resultCode));
        }

        return [
            'success' => true,
            'external_shipment_id' => $this->recursiveValue($response, ['InvoiceKey'], $this->reference($shipment)),
            'tracking_number' => $this->recursiveValue($response, ['CargoKey', 'InvoiceKey']),
            'status' => 'ready',
            'status_label' => "Aras Kargo'ya aktarıldı",
            'raw_payload' => $response,
        ];
    }

    public function cancelShipment(CargoCarrierAccount $account, Shipment $shipment, array $context = []): array
    {
        $response = $this->call($account, 'CancelDispatch', ['integrationCode' => $this->reference($shipment)]);
        $resultCode = (string) $this->recursiveValue($response, ['ResultCode'], '0');
        if (! in_array($resultCode, ['', '0'], true)) {
            throw new \RuntimeException('Aras gönderi iptal hatası: '.(string) $this->recursiveValue($response, ['ResultMessage'], $resultCode));
        }

        return ['success' => true, 'status' => 'cancelled', 'status_label' => 'Aras gönderisi iptal edildi', 'raw_payload' => $response];
    }

    public function trackShipment(CargoCarrierAccount $account, Shipment $shipment): array
    {
        $response = $this->call($account, 'GetCargoTransaction', [
            'code' => $shipment->tracking_number ?: $this->reference($shipment),
            'integrationCode' => $this->reference($shipment),
        ]);
        $label = (string) $this->recursiveValue($response, ['Durum', 'Status', 'EventName', 'ResultMessage'], 'Gönderi sorgulandı');

        return [
            'success' => true,
            'tracking_number' => $this->recursiveValue($response, ['CargoKey', 'KargoTakipNo'], $shipment->tracking_number),
            'status' => $this->normalizeStatus($label),
            'status_label' => $label,
            'delivered_at' => $this->recursiveValue($response, ['DeliveryDate', 'TeslimTarihi']),
            'raw_payload' => $response,
        ];
    }

    protected function call(CargoCarrierAccount $account, string $method, array $payload): array
    {
        $this->requireCredentials($account, ['username', 'password']);
        $environment = (string) $this->credential($account, 'environment', 'live');
        $wsdl = (string) ($this->credential($account, 'wsdl_url')
            ?: config("cargo.integrations.aras.{$environment}_wsdl"));

        $credentials = match ($method) {
            'GetCargoTransaction' => [
                'username' => $this->credential($account, 'username'),
                'password' => $this->credential($account, 'password'),
            ],
            default => [
                'userName' => $this->credential($account, 'username'),
                'password' => $this->credential($account, 'password'),
            ],
        };

        return $this->soapCall($account, $wsdl, $method, array_merge($payload, $credentials));
    }
}
