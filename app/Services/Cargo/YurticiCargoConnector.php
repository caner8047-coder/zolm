<?php

namespace App\Services\Cargo;

use App\Models\CargoCarrierAccount;
use App\Models\Shipment;
use App\Services\Cargo\Contracts\CargoCarrierConnector;

class YurticiCargoConnector extends AbstractSoapCargoConnector implements CargoCarrierConnector
{
    public function testConnection(CargoCarrierAccount $account): array
    {
        $this->requireCredentials($account, ['username', 'password']);
        $response = $this->call($account, 'queryShipment', [
            'keys' => (string) ($this->credential($account, 'test_reference') ?: '__ZOLM_TEST__'),
            'keyType' => 0,
            'addHistoricalData' => false,
            'onlyTracking' => true,
        ]);

        if ($this->hasAuthenticationError($response)) {
            throw new \RuntimeException('Yurtiçi Kargo kullanıcı adı veya şifresi doğrulanamadı.');
        }

        return ['success' => true, 'ready' => true, 'message' => 'Yurtiçi Kargo bağlantısı başarılı.', 'response' => $response];
    }

    public function createShipment(CargoCarrierAccount $account, Shipment $shipment): array
    {
        $response = $this->call($account, 'createShipment', [
            'ShippingOrderVO' => [
                'cargoKey' => $this->reference($shipment),
                'invoiceKey' => $this->reference($shipment),
                'receiverCustName' => $shipment->customer_name,
                'receiverAddress' => $shipment->destination_address,
                'cityName' => $shipment->destination_city,
                'townName' => $shipment->destination_district,
                'receiverPhone1' => $shipment->customer_phone,
                'desi' => max(1, (int) ceil((float) $shipment->total_desi)),
                'kg' => max(1, (int) ceil((float) $shipment->total_weight)),
                'cargoCount' => max(1, (int) $shipment->parcel_count),
                'description' => 'ZOLM '.$this->reference($shipment),
            ],
        ]);
        $errorCode = (string) $this->recursiveValue($response, ['errCode'], '0');
        if (! in_array($errorCode, ['', '0'], true)) {
            throw new \RuntimeException('Yurtiçi gönderi oluşturma hatası: '.(string) $this->recursiveValue($response, ['errMessage'], $errorCode));
        }

        return [
            'success' => true,
            'external_shipment_id' => $this->recursiveValue($response, ['cargoKey'], $this->reference($shipment)),
            'tracking_number' => $this->recursiveValue($response, ['cargoKey'], $this->reference($shipment)),
            'status' => 'ready',
            'status_label' => "Yurtiçi Kargo'ya aktarıldı",
            'raw_payload' => $response,
        ];
    }

    public function cancelShipment(CargoCarrierAccount $account, Shipment $shipment, array $context = []): array
    {
        $response = $this->call($account, 'cancelShipment', ['cargoKeys' => $this->reference($shipment)]);
        $errorCode = (string) $this->recursiveValue($response, ['errCode'], '0');
        if (! in_array($errorCode, ['', '0'], true)) {
            throw new \RuntimeException('Yurtiçi gönderi iptal hatası: '.(string) $this->recursiveValue($response, ['errMessage'], $errorCode));
        }

        return ['success' => true, 'status' => 'cancelled', 'status_label' => 'Yurtiçi gönderisi iptal edildi', 'raw_payload' => $response];
    }

    public function trackShipment(CargoCarrierAccount $account, Shipment $shipment): array
    {
        $response = $this->call($account, 'queryShipment', [
            'keys' => $shipment->tracking_number ?: $this->reference($shipment),
            'keyType' => $shipment->tracking_number ? 1 : 0,
            'addHistoricalData' => true,
            'onlyTracking' => false,
        ]);
        $label = (string) $this->recursiveValue($response, ['cargoEventExplanation', 'documentEventExplanation'], 'Gönderi sorgulandı');

        return [
            'success' => true,
            'tracking_number' => $this->recursiveValue($response, ['docId', 'cargoKey'], $shipment->tracking_number),
            'status' => $this->normalizeStatus($label),
            'status_label' => $label,
            'delivered_at' => $this->recursiveValue($response, ['deliveryDate']),
            'actual_desi' => $this->recursiveValue($response, ['totalDesi']),
            'actual_cost' => $this->recursiveValue($response, ['totalAmount']),
            'raw_payload' => $response,
        ];
    }

    protected function call(CargoCarrierAccount $account, string $method, array $payload): array
    {
        $this->requireCredentials($account, ['username', 'password']);
        $environment = (string) $this->credential($account, 'environment', 'live');
        $wsdl = (string) ($this->credential($account, 'wsdl_url')
            ?: config("cargo.integrations.yurtici.{$environment}_wsdl"));

        $languageKey = $method === 'queryShipment' ? 'wsLanguage' : 'userLanguage';

        return $this->soapCall($account, $wsdl, $method, array_merge([
            'wsUserName' => $this->credential($account, 'username'),
            'wsPassword' => $this->credential($account, 'password'),
            $languageKey => 'TR',
        ], $payload));
    }
}
