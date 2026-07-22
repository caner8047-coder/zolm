<?php

namespace App\Services\Cargo;

use App\Models\CargoCarrierAccount;
use App\Models\Shipment;
use App\Services\Cargo\Contracts\CargoCarrierConnector;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class HepsiJetCargoConnector extends AbstractCargoConnector implements CargoCarrierConnector
{
    public function testConnection(CargoCarrierAccount $account): array
    {
        $token = $this->token($account);

        return [
            'success' => true,
            'ready' => true,
            'message' => 'HepsiJet bağlantısı başarılı.',
            'response' => ['token_received' => filled($token)],
        ];
    }

    public function createShipment(CargoCarrierAccount $account, Shipment $shipment): array
    {
        $this->requireCredentials($account, [
            'company_name', 'company_code', 'sender_address_id', 'crossdock_code',
        ]);
        $reference = $this->reference($shipment);
        $name = preg_split('/\s+/', trim((string) $shipment->customer_name), 2);
        $payload = [
            'company' => [
                'name' => $this->credential($account, 'company_name'),
                'abbreviationCode' => $this->credential($account, 'company_code'),
            ],
            'delivery' => [
                'customerDeliveryNo' => $reference,
                'customerOrderId' => $shipment->order_number ?: $reference,
                'totalParcels' => max(1, (int) $shipment->parcel_count),
                'desi' => max(1, (float) $shipment->total_desi),
                'deliverySlotOriginal' => '0',
                'deliveryDateOriginal' => now()->toDateString(),
                'deliveryType' => 'RETAIL',
                'product' => ['productCode' => (string) $this->credential($account, 'product_code', 'HX_STD')],
                'receiver' => [
                    'companyCustomerId' => $shipment->order_number ?: $reference,
                    'firstName' => $name[0] ?? $shipment->customer_name,
                    'lastName' => $name[1] ?? '-',
                    'phone1' => $shipment->customer_phone,
                    'email' => data_get($shipment->raw_payload, 'order.customer.email'),
                ],
                'senderAddress' => $this->addressPayload(
                    (string) $this->credential($account, 'sender_address_id'),
                    $account->origin_city,
                    $account->origin_district,
                    $account->origin_address,
                ),
                'recipientAddress' => $this->addressPayload(
                    $reference,
                    $shipment->destination_city,
                    $shipment->destination_district,
                    $shipment->destination_address,
                ),
                'recipientPerson' => $shipment->customer_name,
                'recipientPersonPhone1' => $shipment->customer_phone,
            ],
            'currentXDock' => ['abbreviationCode' => $this->credential($account, 'crossdock_code')],
        ];

        $response = $this->request($account)
            ->post('/delivery/sendDeliveryOrderEnhanced', $payload)
            ->throw()
            ->json();

        if (($response['status'] ?? 'OK') === 'ERROR') {
            throw new \RuntimeException('HepsiJet gönderi oluşturma hatası: '.($response['message'] ?? 'Beklenmeyen yanıt.'));
        }

        $data = $this->toArray($response['data'] ?? $response);

        return [
            'success' => true,
            'external_shipment_id' => $data['barcode'] ?? $reference,
            'tracking_number' => $data['barcode'] ?? $data['customerDeliveryNo'] ?? $reference,
            'barcode' => $data['barcode'] ?? null,
            'status' => 'ready',
            'status_label' => "HepsiJet'e aktarıldı",
            'raw_payload' => ['request' => $payload, 'response' => $response],
        ];
    }

    public function cancelShipment(CargoCarrierAccount $account, Shipment $shipment, array $context = []): array
    {
        $barcode = $shipment->barcode ?: $shipment->tracking_number ?: $this->reference($shipment);
        $response = $this->request($account)
            ->post('/rest/delivery/deleteDeliveryOrder/'.rawurlencode($barcode), [
                'deleteReason' => (string) ($context['reason'] ?? 'IPTAL'),
            ])
            ->throw()
            ->json();

        return ['success' => true, 'status' => 'cancelled', 'status_label' => 'HepsiJet gönderisi iptal edildi', 'raw_payload' => $response];
    }

    public function trackShipment(CargoCarrierAccount $account, Shipment $shipment): array
    {
        $response = $this->request($account)
            ->post('/rest/deliveryTransaction/getDeliveryTracking', [
                'deliveries' => [['customerDeliveryNo' => $this->reference($shipment)]],
            ])
            ->throw()
            ->json();
        $data = $this->toArray(data_get($response, 'data.0', data_get($response, 'data', $response)));
        $label = (string) ($data['lastTransaction'] ?? $data['deliveryStatus'] ?? $data['operationStatus'] ?? 'Gönderi sorgulandı');
        $events = collect($data['transactions'] ?? [])->map(fn (array $event) => [
            'event_code' => $event['operationStatus'] ?? $event['transactionCode'] ?? null,
            'event_status' => $this->normalizeStatus((string) ($event['operationStatus'] ?? $event['description'] ?? '')),
            'event_description' => $event['description'] ?? $event['operationStatus'] ?? 'HepsiJet hareketi',
            'event_location' => $event['location'] ?? null,
            'event_at' => $event['transactionDate'] ?? $event['date'] ?? now(),
            'raw_payload' => $event,
        ])->all();

        return [
            'success' => true,
            'tracking_number' => $data['barcode'] ?? $shipment->tracking_number,
            'barcode' => $data['barcode'] ?? $shipment->barcode,
            'status' => $this->normalizeStatus($label),
            'status_label' => $label,
            'delivered_at' => $data['deliveredDate'] ?? null,
            'events' => $events,
            'raw_payload' => $response,
        ];
    }

    protected function token(CargoCarrierAccount $account): string
    {
        $this->requireCredentials($account, ['username', 'password']);
        $response = Http::baseUrl($this->baseUrl($account))
            ->acceptJson()
            ->withBasicAuth((string) $this->credential($account, 'username'), (string) $this->credential($account, 'password'))
            ->timeout(30)
            ->get('/auth/getToken')
            ->throw();
        $payload = $response->json();
        $token = is_string($payload)
            ? $payload
            : (string) ($payload['token'] ?? data_get($payload, 'data.token', data_get($payload, 'data', '')));

        if ($token === '') {
            throw new \RuntimeException('HepsiJet token yanıtında erişim anahtarı bulunamadı.');
        }

        return $token;
    }

    protected function request(CargoCarrierAccount $account): PendingRequest
    {
        return Http::baseUrl($this->baseUrl($account))
            ->acceptJson()
            ->asJson()
            ->withBasicAuth((string) $this->credential($account, 'username'), (string) $this->credential($account, 'password'))
            ->withHeader('X-Auth-Token', $this->token($account))
            ->timeout(30)
            ->retry(2, 250, throw: false);
    }

    protected function baseUrl(CargoCarrierAccount $account): string
    {
        $environment = (string) $this->credential($account, 'environment', 'test');

        return rtrim((string) ($account->api_base_url ?: $this->credential($account, 'base_url') ?: config("cargo.integrations.hepsijet.{$environment}_base_url")), '/');
    }

    protected function addressPayload(?string $id, ?string $city, ?string $town, ?string $address): array
    {
        return [
            'companyAddressId' => $id,
            'country' => ['name' => 'Türkiye'],
            'city' => ['name' => $city],
            'town' => ['name' => $town],
            'district' => ['name' => $town],
            'addressLine1' => $address,
        ];
    }
}
