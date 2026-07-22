<?php

namespace App\Services\Cargo;

use App\Models\CargoCarrierAccount;
use App\Models\Shipment;
use App\Services\Cargo\Contracts\CargoCarrierConnector;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class DhlExpressCargoConnector extends AbstractCargoConnector implements CargoCarrierConnector
{
    public function testConnection(CargoCarrierAccount $account): array
    {
        $this->requireCredentials($account, ['username', 'password', 'account_number', 'shipper_postal_code', 'shipper_city']);
        $response = $this->request($account)->get('/products', [
            'accountNumber' => $this->credential($account, 'account_number'),
            'originCountryCode' => $this->credential($account, 'shipper_country_code', 'TR'),
            'originPostalCode' => $this->credential($account, 'shipper_postal_code'),
            'originCityName' => $this->credential($account, 'shipper_city'),
            'destinationCountryCode' => 'TR',
            'destinationPostalCode' => '34000',
            'destinationCityName' => 'İstanbul',
            'plannedShippingDate' => now()->addWeekday()->toDateString(),
            'isCustomsDeclarable' => 'false',
            'unitOfMeasurement' => 'metric',
        ])->throw()->json();

        return ['success' => true, 'ready' => true, 'message' => 'DHL Express bağlantısı başarılı.', 'response' => $response];
    }

    public function createShipment(CargoCarrierAccount $account, Shipment $shipment): array
    {
        $this->requireCredentials($account, ['account_number', 'shipper_postal_code', 'shipper_city', 'shipper_country_code']);
        $receiverPostal = data_get($shipment->raw_payload, 'order.shipmentAddress.postalCode')
            ?: data_get($shipment->raw_payload, 'order.shipping.postcode')
            ?: data_get($shipment->meta_json, 'destination_postal_code');
        $receiverCountry = data_get($shipment->raw_payload, 'order.shipmentAddress.countryCode')
            ?: data_get($shipment->meta_json, 'destination_country_code', 'TR');

        if (blank($receiverPostal)) {
            throw new \RuntimeException('DHL Express gönderisi için alıcı posta kodu gerekli. Sipariş adresine posta kodu ekleyin.');
        }

        $payload = [
            'plannedShippingDateAndTime' => now()->addWeekday()->setTime(10, 0)->format('Y-m-d\TH:i:s \G\M\TP'),
            'pickup' => ['isRequested' => false],
            'productCode' => (string) $this->credential($account, 'product_code', 'N'),
            'accounts' => [['typeCode' => 'shipper', 'number' => $this->credential($account, 'account_number')]],
            'customerDetails' => [
                'shipperDetails' => $this->partyPayload(
                    $account->account_name,
                    $account->contact_phone,
                    $account->origin_address,
                    $this->credential($account, 'shipper_postal_code'),
                    $this->credential($account, 'shipper_city'),
                    $this->credential($account, 'shipper_country_code', 'TR'),
                    $account->contact_name,
                ),
                'receiverDetails' => $this->partyPayload(
                    $shipment->customer_name,
                    $shipment->customer_phone,
                    $shipment->destination_address,
                    $receiverPostal,
                    $shipment->destination_city,
                    $receiverCountry,
                    $shipment->customer_name,
                ),
            ],
            'content' => [
                'packages' => [[
                    'weight' => max(0.1, (float) $shipment->total_weight),
                    'dimensions' => [
                        'length' => (float) $this->credential($account, 'default_length', 20),
                        'width' => (float) $this->credential($account, 'default_width', 20),
                        'height' => (float) $this->credential($account, 'default_height', 10),
                    ],
                    'customerReferences' => [['value' => $this->reference($shipment), 'typeCode' => 'CU']],
                ]],
                'isCustomsDeclarable' => false,
                'description' => (string) $this->credential($account, 'content_description', 'Documents'),
                'unitOfMeasurement' => 'metric',
            ],
            'outputImageProperties' => [
                'printerDPI' => 300,
                'encodingFormat' => 'pdf',
                'imageOptions' => [['typeCode' => 'label', 'templateName' => 'ECOM26_84_001']],
            ],
        ];
        $response = $this->request($account)->post('/shipments', $payload)->throw()->json();
        $tracking = (string) ($response['shipmentTrackingNumber'] ?? data_get($response, 'packages.0.trackingNumber', ''));

        return [
            'success' => true,
            'external_shipment_id' => $tracking ?: $this->reference($shipment),
            'tracking_number' => $tracking,
            'barcode' => $tracking,
            'status' => 'label_created',
            'status_label' => 'DHL Express etiketi oluşturuldu',
            'raw_payload' => ['request' => $payload, 'response' => $response],
        ];
    }

    public function cancelShipment(CargoCarrierAccount $account, Shipment $shipment, array $context = []): array
    {
        throw new \RuntimeException('DHL Express MyDHL API oluşturulmuş gönderiyi silmez. Pickup rezervasyonu varsa DHL üzerinden iptal edilmelidir.');
    }

    public function trackShipment(CargoCarrierAccount $account, Shipment $shipment): array
    {
        $tracking = $shipment->tracking_number ?: $shipment->barcode;
        if (blank($tracking)) {
            throw new \RuntimeException('DHL Express takip numarası bulunamadı.');
        }
        $response = $this->request($account)
            ->get('/shipments/'.rawurlencode($tracking).'/tracking')
            ->throw()
            ->json();
        $shipmentData = data_get($response, 'shipments.0', $response);
        $label = (string) (data_get($shipmentData, 'status.description') ?: data_get($shipmentData, 'status.statusCode', 'Gönderi sorgulandı'));
        $events = collect($shipmentData['events'] ?? [])->map(fn (array $event) => [
            'event_code' => data_get($event, 'typeCode'),
            'event_status' => $this->normalizeStatus((string) data_get($event, 'description')),
            'event_description' => data_get($event, 'description', 'DHL hareketi'),
            'event_location' => data_get($event, 'serviceArea.0.description'),
            'event_at' => trim((string) data_get($event, 'date').' '.(string) data_get($event, 'time')),
            'raw_payload' => $event,
        ])->all();

        return [
            'success' => true,
            'tracking_number' => $tracking,
            'status' => $this->normalizeStatus($label),
            'status_label' => $label,
            'delivered_at' => data_get($shipmentData, 'estimatedTimeOfDelivery'),
            'events' => $events,
            'raw_payload' => $response,
        ];
    }

    protected function request(CargoCarrierAccount $account): PendingRequest
    {
        $this->requireCredentials($account, ['username', 'password']);
        $environment = (string) $this->credential($account, 'environment', 'test');
        $baseUrl = $account->api_base_url ?: $this->credential($account, 'base_url') ?: config("cargo.integrations.dhl_express.{$environment}_base_url");

        return Http::baseUrl(rtrim((string) $baseUrl, '/'))
            ->acceptJson()
            ->asJson()
            ->withBasicAuth((string) $this->credential($account, 'username'), (string) $this->credential($account, 'password'))
            ->timeout(45)
            ->retry(2, 250, throw: false);
    }

    protected function partyPayload(?string $company, ?string $phone, ?string $address, ?string $postal, ?string $city, ?string $country, ?string $contact): array
    {
        return [
            'postalAddress' => [
                'postalCode' => $postal,
                'cityName' => $city,
                'countryCode' => $country,
                'addressLine1' => mb_substr((string) $address, 0, 45),
            ],
            'contactInformation' => [
                'phone' => $phone,
                'companyName' => $company,
                'fullName' => $contact ?: $company,
            ],
        ];
    }
}
