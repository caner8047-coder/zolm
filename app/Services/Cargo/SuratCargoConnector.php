<?php

namespace App\Services\Cargo;

use App\Models\CargoCarrierAccount;
use App\Models\Shipment;
use App\Services\Cargo\Contracts\CargoCarrierConnector;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SuratCargoConnector implements CargoCarrierConnector
{
    public function testConnection(CargoCarrierAccount $account): array
    {
        $endpointKey = $this->isEndpointReady($account, 'test_connection', true)
            ? 'test_connection'
            : 'track_shipment';

        if (!$this->isEndpointReady($account, $endpointKey, true)) {
            return [
                'success' => false,
                'ready' => false,
                'message' => 'Sürat takip endpointi tanımlı değil. Hesap bilgileri kaydedildi; endpoint bilgisi girildiğinde bağlantı testi aktif olur.',
            ];
        }

        $reference = trim((string) data_get($account->settings_json, 'test_reference', ''));

        if ($reference === '') {
            $reference = '__ZOLM_TEST__';
        }

        $response = $this->request(
            $account,
            $endpointKey,
            $this->trackingPayload($account, $reference),
            true,
            'query'
        );

        $payload = $response['payload'] ?? [];
        $message = $this->payloadMessage($payload) ?: 'Sürat takip servisi yanıt verdi.';

        if ($this->payloadHasError($payload)) {
            if ($this->isReferenceNotFoundMessage($message)) {
                return [
                    'success' => true,
                    'ready' => true,
                    'message' => 'Sürat REST endpointleri ve şifre doğrulaması hazır. Test referansı bulunamadığı için gönderi kaydı dönmedi; gerçek sipariş koduyla takip çalışır.',
                    'response' => $response,
                ];
            }

            return [
                'success' => false,
                'ready' => true,
                'message' => 'Sürat bağlantı testi yanıt verdi: ' . $message,
                'response' => $response,
            ];
        }

        return [
            'success' => true,
            'ready' => true,
            'message' => 'Sürat bağlantısı başarılı.',
            'response' => $response,
        ];
    }

    public function createShipment(CargoCarrierAccount $account, Shipment $shipment): array
    {
        $reference = $this->shipmentReference($shipment);
        $response = $this->request($account, 'create_shipment', $this->shipmentPayload($account, $shipment));
        $payload = $response['payload'] ?? [];
        $message = $this->payloadMessage($payload) ?: (string) ($response['body'] ?? '');

        if (!$this->isCreateAccepted($payload, $message)) {
            throw new \RuntimeException('Sürat gönderi oluşturma hatası: ' . ($message ?: 'Beklenmeyen yanıt alındı.'));
        }

        return [
            'success' => true,
            'external_shipment_id' => $this->firstFilled($payload, [
                'external_shipment_id', 'shipment_id', 'shipmentId', 'id', 'KargoId', 'GonderiId',
            ]) ?: $reference,
            'tracking_number' => $this->firstFilled($payload, [
                'tracking_number', 'trackingNumber', 'trackingNo', 'takipNo', 'TakipNo', 'KargoTakipNo', 'GonderiNo',
            ]),
            'barcode' => $this->firstFilled($payload, [
                'barcode', 'barkod', 'Barkod', 'cargo_barcode', 'KargoBarkod',
            ]),
            'status' => 'ready',
            'status_label' => "Sürat'e aktarıldı",
            'raw_payload' => [
                'request_reference' => $reference,
                'response' => $payload ?: $response,
            ],
        ];
    }

    public function cancelShipment(CargoCarrierAccount $account, Shipment $shipment, array $context = []): array
    {
        $response = null;
        $message = '';

        if ($this->isEndpointReady($account, 'cancel_shipment', true)) {
            $response = $this->request(
                $account,
                'cancel_shipment',
                $this->trackingPayload($account, $this->shipmentReference($shipment)),
                true,
                'query'
            );

            $payload = $response['payload'] ?? [];
            $message = $this->payloadMessage($payload);

            if (!$this->payloadHasError($payload)) {
                return [
                    'success' => true,
                    'status' => 'cancelled',
                    'status_label' => $message ?: 'Gönderi pasif edildi',
                    'raw_payload' => $payload ?: $response,
                ];
            }
        }

        if ($this->isEndpointReady($account, 'recall_shipment')) {
            $response = $this->request($account, 'recall_shipment', $this->recallPayload($account, $shipment, $context));
            $payload = $response['payload'] ?? [];
            $message = $this->payloadMessage($payload);

            if (!$this->payloadHasError($payload)) {
                return [
                    'success' => true,
                    'status' => 'cancelled',
                    'status_label' => $message ?: 'Gönderi geri çekildi',
                    'raw_payload' => $payload ?: $response,
                ];
            }
        }

        throw new \RuntimeException('Sürat gönderi iptal/geri çekme hatası: ' . ($message ?: 'Beklenmeyen yanıt alındı.'));
    }

    public function trackShipment(CargoCarrierAccount $account, Shipment $shipment): array
    {
        $reference = $this->shipmentReference($shipment);
        $response = $this->request(
            $account,
            'track_shipment',
            $this->trackingPayload($account, $reference),
            true,
            'query'
        );

        $payload = $response['payload'] ?? [];
        $message = $this->payloadMessage($payload);

        if ($this->payloadHasError($payload)) {
            throw new \RuntimeException('Sürat takip hatası: ' . ($message ?: 'Beklenmeyen yanıt alındı.'));
        }

        $shipmentPayload = $this->firstShipmentPayload($payload) ?: $payload;
        $statusLabel = (string) ($this->firstFilled($shipmentPayload, [
            'KargonunDurumu', 'Durum', 'durum', 'status_label', 'statusLabel', 'shipmentStatus',
        ]) ?: '');

        $statusNumber = $this->firstFilled($shipmentPayload, [
            'KargonunDurumuSayi', 'DurumKodu', 'StatusCode', 'status_code',
        ]);

        $actualCost = $this->suratTotalCost($shipmentPayload);

        return array_filter([
            'success' => true,
            'tracking_number' => $this->firstFilled($shipmentPayload, [
                'tracking_number', 'trackingNumber', 'TakipNo', 'KargoTakipNo',
            ]) ?: $shipment->tracking_number,
            'barcode' => $this->firstFilled($shipmentPayload, ['Barkod', 'barcode']) ?: $shipment->barcode,
            'status' => $this->normalizeSuratStatus($statusNumber, $statusLabel),
            'status_label' => $statusLabel ?: null,
            'delivered_at' => $this->firstFilled($shipmentPayload, [
                'delivered_at', 'deliveredAt', 'TeslimTarihi', 'teslimTarihi',
            ]),
            'actual_cost' => $actualCost > 0 ? $actualCost : null,
            'actual_desi' => $this->decimalValue($this->firstFilled($shipmentPayload, [
                'ToplamDesiKg', 'ToplamDesi', 'Desi', 'desi',
            ])),
            'events' => $this->normalizeEvents($shipmentPayload),
            'raw_payload' => [
                'request_reference' => $reference,
                'response' => $payload ?: $response,
            ],
        ], fn ($value) => $value !== null);
    }

    /**
     * Shipment kaydı oluşturmadan Trendyol/Sürat referansı ile takip sorgular.
     *
     * @return array<string, mixed>
     */
    public function lookupByReference(CargoCarrierAccount $account, string $reference): array
    {
        $reference = trim($reference);

        if ($reference === '') {
            throw new \RuntimeException('Sürat takip sorgusu için kargo kodu gerekli.');
        }

        $response = $this->request(
            $account,
            'track_shipment',
            $this->trackingPayload($account, $reference),
            true,
            'query'
        );

        $payload = $response['payload'] ?? [];
        $message = $this->payloadMessage($payload);

        if ($this->payloadHasError($payload)) {
            throw new \RuntimeException('Sürat takip hatası: ' . ($message ?: 'Beklenmeyen yanıt alındı.'));
        }

        $shipmentPayload = $this->firstShipmentPayload($payload) ?: $payload;
        $statusLabel = (string) ($this->firstFilled($shipmentPayload, [
            'KargonunDurumu', 'Durum', 'durum', 'status_label', 'statusLabel', 'shipmentStatus',
        ]) ?: '');
        $statusNumber = $this->firstFilled($shipmentPayload, [
            'KargonunDurumuSayi', 'DurumKodu', 'StatusCode', 'status_code',
        ]);
        $statusCode = is_numeric($statusNumber) ? (int) $statusNumber : $statusNumber;

        return array_filter([
            'success' => true,
            'reference' => $reference,
            'tracking_number' => $this->firstFilled($shipmentPayload, [
                'tracking_number', 'trackingNumber', 'TakipNo', 'KargoTakipNo',
            ]),
            'barcode' => $this->firstFilled($shipmentPayload, ['Barkod', 'barcode']),
            'status' => $this->normalizeSuratStatus($statusNumber, $statusLabel),
            'status_label' => $statusLabel ?: null,
            'status_code' => $statusCode,
            'current_location' => $this->firstFilled($shipmentPayload, [
                'KargonunBulunduguYer', 'SonBulunduguYer', 'current_location',
            ]),
            'origin_branch' => $this->firstFilled($shipmentPayload, ['CikisSubesi', 'ÇıkışSubesi']),
            'origin_branch_phone' => $this->firstFilled($shipmentPayload, ['CikisSubeTel', 'ÇıkışSubeTel']),
            'delivery_branch' => $this->firstFilled($shipmentPayload, ['TeslimatSubesi']),
            'delivery_branch_phone' => $this->firstFilled($shipmentPayload, ['TeslimatSubeTel']),
            'last_event_at' => $this->firstFilled($shipmentPayload, [
                'SonHareketTarihi', 'last_event_at', 'lastEventAt',
            ]),
            'delivered_at' => $this->firstFilled($shipmentPayload, [
                'delivered_at', 'deliveredAt', 'TeslimTarihi', 'teslimTarihi',
            ]),
            'delivered_to' => $this->firstFilled($shipmentPayload, ['TeslimAlan']),
            'devir_status' => $this->firstFilled($shipmentPayload, ['DevirDurum']),
            'devir_reason' => $this->firstFilled($shipmentPayload, ['DevirSebebi']),
            'return_status' => $this->firstFilled($shipmentPayload, ['IadeDurum', 'İadeDurum']),
            'return_reason' => $this->firstFilled($shipmentPayload, ['IadeAciklama', 'İadeAciklama']),
            'planned_delivery_at' => $this->firstFilled($shipmentPayload, ['PlanlananTeslimTarihi']),
            'tracking_url' => $this->firstFilled($shipmentPayload, ['TakipUrl', 'tracking_url']),
            'events' => $this->normalizeEvents($shipmentPayload),
            'raw_payload' => [
                'request_reference' => $reference,
                'response' => $payload ?: $response,
            ],
        ], fn ($value) => $value !== null);
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, totals: array<string, mixed>, raw_payload: array<string, mixed>}
     */
    public function sentShipmentReport(CargoCarrierAccount $account, string $startDate, string $endDate): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        if ($end->lt($start)) {
            throw new \RuntimeException('Bitiş tarihi başlangıç tarihinden önce olamaz.');
        }

        $warnings = [];
        $sentPayload = [];

        try {
            $sentResponse = $this->request($account, 'sent_shipment_details', [
                'CariKodu' => $account->customer_code,
                'Sifre' => $this->queryPassword($account),
                'BasTar' => $start->toDateString(),
                'BitTar' => $end->toDateString(),
            ], true, 'query');

            $sentPayload = $this->contentPayload($sentResponse['payload'] ?? []);

            if ($this->payloadHasError($sentPayload)) {
                $warnings[] = 'GonderilenKargoDetayi tutar servisi yanıt verdi fakat hata döndürdü: ' . ($this->payloadMessage($sentPayload) ?: 'Beklenmeyen yanıt.');
                $sentPayload = [];
            }
        } catch (\Throwable $exception) {
            $warnings[] = 'GonderilenKargoDetayi tutar servisi çalışmadı: ' . $exception->getMessage();
        }

        $sentRows = collect(Arr::wrap(data_get($sentPayload, 'GonderilenKargoDetayi', [])))
            ->filter(fn ($row) => is_array($row))
            ->unique(fn (array $row) => (string) ($row['TakipNo'] ?? $row['WebSiparisKodu'] ?? md5(json_encode($row))))
            ->values();

        $trackingRows = collect();
        $trackingPayloads = [];

        foreach ($this->suratStatusCodes() as $statusCode) {
            $response = $this->request($account, 'multi_tracking', [
                'CariKodu' => $account->customer_code,
                'Sifre' => $this->queryPassword($account),
                'BaslangicTarihi' => $start->toDateString(),
                'BitisTarihi' => $end->toDateString(),
                'KargonunDurumuSayi' => $statusCode,
            ], true, 'query');

            $payload = $this->contentPayload($response['payload'] ?? []);
            $trackingPayloads[] = [
                'status_code' => $statusCode,
                'payload' => $payload,
            ];

            if ($this->payloadHasError($payload)) {
                continue;
            }

            $trackingRows = $trackingRows->merge(
                collect(Arr::wrap(data_get($payload, 'Gonderiler', [])))
                    ->filter(fn ($row) => is_array($row))
                    ->map(fn (array $row) => array_merge($row, ['_requested_status_code' => $statusCode]))
            );
        }

        $trackingRows = $trackingRows
            ->unique(fn (array $row) => (string) ($row['KargoTakipNo'] ?? $row['KargoObjId'] ?? md5(json_encode($row))))
            ->values();

        $trackingByNo = $trackingRows
            ->keyBy(fn (array $row) => (string) ($row['KargoTakipNo'] ?? $row['TakipNo'] ?? ''));

        $rows = collect();
        $usedTrackingNumbers = [];

        foreach ($sentRows as $sentRow) {
            $trackingNo = (string) ($sentRow['TakipNo'] ?? '');
            $trackingRow = $trackingByNo->get($trackingNo, []);
            $usedTrackingNumbers[$trackingNo] = true;
            $rows->push($this->normalizeReportRow($sentRow, $trackingRow));
        }

        foreach ($trackingRows as $trackingRow) {
            $trackingNo = (string) ($trackingRow['KargoTakipNo'] ?? $trackingRow['TakipNo'] ?? '');

            if ($trackingNo !== '' && isset($usedTrackingNumbers[$trackingNo])) {
                continue;
            }

            $rows->push($this->normalizeReportRow([], $trackingRow));
        }

        $rows = $rows
            ->sortByDesc(fn (array $row) => $row['document_date'] ?: $row['created_at'] ?: '')
            ->values()
            ->all();

        return [
            'rows' => $rows,
            'totals' => [
                'row_count' => count($rows),
                'pieces' => array_sum(array_map(fn ($row) => (int) ($row['pieces'] ?? 0), $rows)),
                'desi' => round(array_sum(array_map(fn ($row) => (float) ($row['desi'] ?? 0), $rows)), 2),
                'amount' => round(array_sum(array_map(fn ($row) => (float) ($row['amount'] ?? 0), $rows)), 2),
                'measurement_amount' => round(array_sum(array_map(fn ($row) => (float) ($row['measurement_amount'] ?? 0), $rows)), 2),
                'total_amount' => round(array_sum(array_map(fn ($row) => (float) ($row['total_amount'] ?? 0), $rows)), 2),
            ],
            'warnings' => $warnings,
            'raw_payload' => [
                'sent_details' => $sentPayload,
                'multi_tracking' => $trackingPayloads,
            ],
        ];
    }

    protected function isEndpointReady(CargoCarrierAccount $account, string $endpointKey, bool $queryEndpoint = false): bool
    {
        return $this->endpoint($account, $endpointKey) !== null && $this->baseUrl($account, $endpointKey, $queryEndpoint) !== null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function request(
        CargoCarrierAccount $account,
        string $endpointKey,
        array $payload,
        bool $queryEndpoint = false,
        string $transport = 'json'
    ): array {
        $endpoint = $this->endpoint($account, $endpointKey);
        $baseUrl = $this->baseUrl($account, $endpointKey, $queryEndpoint);

        if ($endpoint === null || $baseUrl === null) {
            throw new \RuntimeException('Sürat API endpointi tanımlı değil: ' . $endpointKey);
        }

        $url = Str::startsWith($endpoint, ['http://', 'https://'])
            ? $endpoint
            : rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');

        $method = Str::lower((string) data_get(
            $account->settings_json,
            "methods.{$endpointKey}",
            config("cargo.integrations.surat.methods.{$endpointKey}", 'POST')
        ));

        $client = Http::timeout((int) config('cargo.integrations.surat.timeout', 30))
            ->acceptJson();

        if ($transport === 'json') {
            $client = $client->asJson();
        }

        if ($transport === 'query') {
            $url = $this->appendQuery($url, $payload);
            $payload = [];
        }

        /** @var Response $response */
        $response = match ($method) {
            'get' => $client->get($url, $payload),
            'put' => $client->put($url, $payload),
            'delete' => $client->delete($url, $payload),
            default => $client->post($url, $payload),
        };

        if ($response->failed()) {
            throw new \RuntimeException('Sürat API hatası: HTTP ' . $response->status() . ' - ' . Str::limit($response->body(), 300));
        }

        $decoded = $response->json();
        $body = trim($response->body());

        return [
            'http_status' => $response->status(),
            'body' => $body,
            'payload' => is_array($decoded) ? $decoded : ['raw' => $body],
        ];
    }

    protected function endpoint(CargoCarrierAccount $account, string $endpointKey): ?string
    {
        $endpoint = data_get($account->settings_json, "endpoints.{$endpointKey}")
            ?: config("cargo.integrations.surat.endpoints.{$endpointKey}");

        $endpoint = is_string($endpoint) ? trim($endpoint) : '';

        return $endpoint !== '' ? $endpoint : null;
    }

    protected function baseUrl(CargoCarrierAccount $account, string $endpointKey, bool $queryEndpoint = false): ?string
    {
        if ($endpoint = $this->endpoint($account, $endpointKey)) {
            if (Str::startsWith($endpoint, ['http://', 'https://'])) {
                return '';
            }
        }

        $baseUrl = $queryEndpoint
            ? ($account->query_base_url ?: config('cargo.integrations.surat.query_base_url'))
            : ($account->api_base_url ?: config('cargo.integrations.surat.base_url'));

        $baseUrl = is_string($baseUrl) ? trim($baseUrl) : '';

        return $baseUrl !== '' ? $baseUrl : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function shipmentPayload(CargoCarrierAccount $account, Shipment $shipment): array
    {
        $shipment->loadMissing(['items', 'parcels', 'store']);

        $recipient = $this->recipientForShipment($account, $shipment);
        $parcelCount = max(1, (int) $shipment->parcel_count);
        $unitDesi = max(1, round(((float) $shipment->total_desi ?: 1) / $parcelCount, 2));
        $unitWeight = max(1, round(((float) $shipment->total_weight ?: 1) / $parcelCount, 2));
        $reference = $this->shipmentReference($shipment);
        $marketplaceName = $this->marketplaceName($shipment);

        $payload = [
            'KullaniciAdi' => $this->senderUsername($account),
            'Sifre' => $this->senderPassword($account),
            'Gonderi' => [
                'KisiKurum' => $this->limitText($recipient['name'], 100),
                'SahisBirim' => $this->limitText($this->itemSummary($shipment), 100),
                'AliciAdresi' => $this->limitText($recipient['address'], 250),
                'Il' => $this->limitText($recipient['city'], 50),
                'Ilce' => $this->limitText($recipient['district'], 50),
                'TelefonEv' => '',
                'TelefonIs' => '',
                'TelefonCep' => $this->normalizePhone($recipient['phone']),
                'Email' => '',
                'AliciKodu' => '',
                'KargoTuru' => 3,
                'OdemeTipi' => 1,
                'IrsaliyeSeriNo' => '',
                'IrsaliyeSiraNo' => '',
                'ReferansNo' => $this->limitText($shipment->order_number ?: $reference, 30),
                'OzelKargoTakipNo' => $this->limitText($reference, 50),
                'Adet' => $parcelCount,
                'BirimDesi' => (string) $unitDesi,
                'BirimKg' => (string) $unitWeight,
                'KargoIcerigi' => $this->cargoContent($unitDesi, $unitWeight, $parcelCount),
                'KapidanOdemeTahsilatTipi' => 0,
                'KapidanOdemeTutari' => 0.0,
                'EkHizmetler' => null,
                'TasimaSekli' => 1,
                'TeslimSekli' => 1,
                'SevkAdresi' => '',
                'GonderiSekli' => 0,
                'TeslimSubeKodu' => (string) ($account->branch_code ?: ''),
                'Pazaryerimi' => $marketplaceName ? 1 : 0,
                'EntegrasyonFirmasi' => $marketplaceName ?: '',
                'Iademi' => $this->isReturnFlow($shipment) ? 1 : 0,
            ],
        ];

        $this->validateShipmentPayload($payload['Gonderi']);

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    protected function trackingPayload(CargoCarrierAccount $account, string $reference): array
    {
        return [
            'CariKodu' => $account->customer_code,
            'Sifre' => $this->queryPassword($account),
            'WebSiparisKodu' => $reference,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function recallPayload(CargoCarrierAccount $account, Shipment $shipment, array $context = []): array
    {
        $trackingCode = trim((string) ($shipment->tracking_number ?: $shipment->barcode));

        return [
            'KullaniciAdi' => $this->senderUsername($account),
            'Sifre' => $this->senderPassword($account),
            'TakipKodu' => $trackingCode !== '' ? $trackingCode : $this->shipmentReference($shipment),
            'isSatis' => $trackingCode === '',
            'IptalNeden' => (string) ($context['reason'] ?? 'ZOLM panelinden gönderi geri çekildi.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function recipientForShipment(CargoCarrierAccount $account, Shipment $shipment): array
    {
        if ($this->isReturnFlow($shipment)) {
            return [
                'name' => (string) ($account->account_name ?: $shipment->sender_name ?: 'ZOLM'),
                'phone' => (string) ($account->contact_phone ?: $shipment->sender_phone ?: $shipment->customer_phone),
                'city' => (string) ($shipment->destination_city ?: $account->origin_city),
                'district' => (string) ($shipment->destination_district ?: $account->origin_district),
                'address' => (string) ($shipment->destination_address ?: $account->origin_address),
            ];
        }

        return [
            'name' => (string) $shipment->customer_name,
            'phone' => (string) $shipment->customer_phone,
            'city' => (string) $shipment->destination_city,
            'district' => (string) $shipment->destination_district,
            'address' => (string) $shipment->destination_address,
        ];
    }

    protected function validateShipmentPayload(array $gonderi): void
    {
        $required = [
            'KisiKurum' => 'alıcı adı',
            'AliciAdresi' => 'alıcı adresi',
            'Il' => 'alıcı ili',
            'Ilce' => 'alıcı ilçesi',
            'TelefonCep' => 'alıcı cep telefonu',
            'OzelKargoTakipNo' => 'özel takip referansı',
        ];

        foreach ($required as $key => $label) {
            if (!filled($gonderi[$key] ?? null)) {
                throw new \RuntimeException('Sürat gönderi oluşturmak için ' . $label . ' gerekli.');
            }
        }
    }

    protected function shipmentReference(Shipment $shipment): string
    {
        $reference = trim((string) (
            $shipment->reference_number
            ?: $shipment->package_number
            ?: $shipment->order_number
            ?: $shipment->external_shipment_id
            ?: $shipment->tracking_number
            ?: $shipment->barcode
            ?: $shipment->shipment_no
        ));

        if ($reference === '') {
            throw new \RuntimeException('Sürat işlemi için sipariş/paket referansı gerekli.');
        }

        return $reference;
    }

    protected function senderUsername(CargoCarrierAccount $account): string
    {
        return (string) ($account->sender_username ?: $account->customer_code);
    }

    protected function senderPassword(CargoCarrierAccount $account): string
    {
        return (string) ($account->sender_password_encrypted ?? '');
    }

    protected function queryPassword(CargoCarrierAccount $account): string
    {
        return (string) (($account->query_password_encrypted ?? '') ?: ($account->sender_password_encrypted ?? ''));
    }

    protected function isReturnFlow(Shipment $shipment): bool
    {
        return $shipment->direction === 'incoming' || in_array($shipment->flow_type, ['return', 'exchange'], true);
    }

    protected function itemSummary(Shipment $shipment): string
    {
        return $shipment->items
            ->take(3)
            ->map(fn ($item) => trim((string) ($item->product_name ?: $item->stock_code ?: $item->barcode)))
            ->filter()
            ->implode('; ');
    }

    protected function cargoContent(float $unitDesi, float $unitWeight, int $parcelCount): string
    {
        return $unitDesi . ':' . $unitWeight . ':3:' . max(1, $parcelCount) . ';';
    }

    protected function marketplaceName(Shipment $shipment): ?string
    {
        $marketplace = Str::lower(Str::ascii((string) data_get($shipment->meta_json, 'marketplace', $shipment->store?->marketplace)));

        return match (true) {
            Str::contains($marketplace, 'trendyol') => 'Trendyol',
            Str::contains($marketplace, 'hepsiburada') || Str::contains($marketplace, 'hepsi') => 'Hepsiburada',
            Str::contains($marketplace, 'n11') => 'N11',
            Str::contains($marketplace, 'pazarama') => 'Pazarama',
            Str::contains($marketplace, 'lcw') => 'LCW',
            default => null,
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeEvents(array $payload): array
    {
        $rows = Arr::wrap(
            data_get($payload, 'events')
            ?: data_get($payload, 'hareketler')
            ?: data_get($payload, 'Hareketler')
            ?: data_get($payload, 'tracking.events')
            ?: []
        );

        return collect($rows)
            ->filter(fn ($row) => is_array($row))
            ->map(function (array $row) {
                $description = (string) ($this->firstFilled($row, [
                    'description', 'event_description', 'Aciklama', 'Açıklama', 'HareketAciklama',
                    'HareketAçıklama', 'HareketTipi', 'Islem', 'İşlem', 'durum', 'Durum',
                ]) ?: '');

                return [
                    'event_code' => $this->firstFilled($row, ['code', 'event_code', 'Kod', 'HareketKodu']),
                    'event_status' => $this->normalizeStatus($description ?: (string) data_get($row, 'status', '')),
                    'event_description' => $description,
                    'location_city' => $this->firstFilled($row, ['city', 'il', 'Il', 'İl', 'location_city']),
                    'location_district' => $this->firstFilled($row, ['district', 'ilce', 'Ilce', 'İlce', 'location_district']),
                    'branch_name' => $this->firstFilled($row, [
                        'branch', 'Sube', 'Şube', 'BirimAdi', 'BirimAdı', 'HareketYeri', 'branch_name',
                    ]),
                    'event_at' => $this->firstFilled($row, ['event_at', 'date', 'Tarih', 'IslemTarihi', 'İşlemTarihi', 'HareketTarihi']),
                    'raw_payload' => $row,
                ];
            })
            ->values()
            ->all();
    }

    protected function normalizeSuratStatus(mixed $statusNumber, string $statusLabel): string
    {
        $number = is_numeric($statusNumber) ? (int) $statusNumber : null;

        if ($number !== null) {
            return match (true) {
                $number === 1 => 'ready',
                in_array($number, [2, 3, 4, 7, 8, 9, 10, 11], true) => 'in_transit',
                in_array($number, [5, 15], true) => 'out_for_delivery',
                $number === 6 => 'delivered',
                in_array($number, [12, 13, 14, 16], true) => 'returned',
                default => $this->normalizeStatus($statusLabel),
            };
        }

        return $this->normalizeStatus($statusLabel);
    }

    protected function normalizeStatus(string $status): string
    {
        $normalized = Str::lower(Str::ascii(trim($status)));

        return match (true) {
            $normalized === '' => 'ready',
            Str::contains($normalized, ['teslim edildi', 'delivered', 'completed']) => 'delivered',
            Str::contains($normalized, ['dagitim', 'delivery']) => 'out_for_delivery',
            Str::contains($normalized, ['evrak', 'hazir', 'tamam', 'olusturuldu']) => 'ready',
            Str::contains($normalized, ['yolda', 'transfer', 'tasima', 'transit', 'shipped', 'aktarma']) => 'in_transit',
            Str::contains($normalized, ['iptal', 'cancel']) => 'cancelled',
            Str::contains($normalized, ['iade', 'return']) => 'returned',
            Str::contains($normalized, ['hata', 'sorun', 'exception', 'failed']) => 'exception',
            default => 'shipped',
        };
    }

    protected function firstShipmentPayload(array $payload): ?array
    {
        $rows = Arr::wrap(
            data_get($payload, 'Gonderiler')
            ?: data_get($payload, 'Gönderiler')
            ?: data_get($payload, 'gonderiler')
            ?: data_get($payload, 'shipments')
            ?: []
        );

        $first = collect($rows)->first(fn ($row) => is_array($row));

        return is_array($first) ? $first : null;
    }

    protected function suratTotalCost(array $payload): float
    {
        $amount = $this->decimalValue($this->firstFilled($payload, ['Tutar', 'tutar', 'total_amount']));
        $measurementAmount = $this->decimalValue($this->firstFilled($payload, ['OlcumTutar', 'ÖlçümTutar', 'olcumTutar']));

        return round($amount + $measurementAmount, 2);
    }

    /**
     * @return array<int, int>
     */
    protected function suratStatusCodes(): array
    {
        return range(1, 16);
    }

    protected function contentPayload(array $payload): array
    {
        $content = data_get($payload, 'Content');

        if (is_array($content)) {
            return $content;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizeReportRow(array $sentRow, array $trackingRow): array
    {
        $trackingNumber = (string) (
            $this->firstFilled($sentRow, ['TakipNo', 'KargoTakipNo'])
            ?: $this->firstFilled($trackingRow, ['KargoTakipNo', 'TakipNo'])
            ?: ''
        );

        $sentAmount = $this->decimalValue($this->firstFilled($sentRow, ['Tutar']));
        $trackingAmount = $this->decimalValue($this->firstFilled($trackingRow, ['Tutar']));
        $trackingVatAmount = $this->decimalValue($this->firstFilled($trackingRow, ['KdvTutar']));
        $trackingAmountWithoutVat = $this->decimalValue($this->firstFilled($trackingRow, ['TutarKdvsiz']));
        $trackingGrossFromNet = $trackingAmountWithoutVat > 0
            ? round($trackingAmountWithoutVat + $trackingVatAmount, 2)
            : 0.0;
        $estimatedGrossFromVat = $this->estimateGrossAmountFromVat($trackingVatAmount);
        $amount = $trackingAmount > 0
            ? $trackingAmount
            : ($sentAmount > 0 ? $sentAmount : ($trackingGrossFromNet > 0 ? $trackingGrossFromNet : $estimatedGrossFromVat));
        $measurementAmount = $this->decimalValue($this->firstFilled($trackingRow, ['OlcumTutar']));
        $amountSource = match (true) {
            $trackingAmount > 0 => 'tracking_tutar',
            $sentAmount > 0 => 'sent_tutar',
            $trackingGrossFromNet > 0 => 'tracking_tutar_kdvsiz',
            $estimatedGrossFromVat > 0 => 'estimated_from_vat',
            default => 'empty',
        };

        return [
            'tracking_number' => $trackingNumber,
            'web_order_code' => $this->firstFilled($sentRow, ['WebSiparisKodu'])
                ?: $this->firstFilled($trackingRow, ['Satiskodu']),
            'sales_code' => $this->firstFilled($trackingRow, ['Satiskodu']),
            'customer_name' => $this->firstFilled($trackingRow, ['AliciUnvan'])
                ?: $this->firstFilled($sentRow, ['AliciUnvan']),
            'sender_name' => $this->firstFilled($trackingRow, ['GonderenUnvan']),
            'destination_city' => $this->firstFilled($sentRow, ['VarisIl']),
            'destination_district' => $this->firstFilled($sentRow, ['VarisIlce']),
            'pieces' => (int) $this->decimalValue($this->firstFilled($trackingRow, ['ToplamAdet'])
                ?? $this->firstFilled($trackingRow, ['Adet'])
                ?? 0),
            'piece_text' => $this->firstFilled($trackingRow, ['ParcaSiraSayi']),
            'desi' => $this->decimalValue($this->firstFilled($trackingRow, ['ToplamDesiKg', 'ToplamDesi', 'Desi'])),
            'measurement_desi' => $this->decimalValue($this->firstFilled($trackingRow, ['OlcumDesi'])),
            'measurement_kg' => $this->decimalValue($this->firstFilled($trackingRow, ['OlcumKg'])),
            'amount' => $amount,
            'amount_source' => $amountSource,
            'measurement_amount' => $measurementAmount,
            'total_amount' => round($amount + $measurementAmount, 2),
            'vat_amount' => $trackingVatAmount,
            'amount_without_vat' => $trackingAmountWithoutVat,
            'status' => $this->firstFilled($trackingRow, ['KargonunDurumu'])
                ?: $this->firstFilled($sentRow, ['TeslimatDurum', 'Durum']),
            'status_code' => $this->firstFilled($trackingRow, ['KargonunDurumuSayi', '_requested_status_code']),
            'current_location' => $this->firstFilled($trackingRow, ['KargonunBulunduguYer'])
                ?: $this->firstFilled($sentRow, ['SonBulunduguYer']),
            'document_date' => $this->firstFilled($trackingRow, ['Evraktarihi']),
            'created_at' => $this->firstFilled($sentRow, ['OlusturulmaTarihi']),
            'last_event_at' => $this->firstFilled($trackingRow, ['SonHareketTarihi']),
            'delivered_at' => $this->firstFilled($trackingRow, ['TeslimTarihi'])
                ?: $this->firstFilled($sentRow, ['TeslimTarihi']),
            'delivered_to' => $this->firstFilled($trackingRow, ['TeslimAlan'])
                ?: $this->firstFilled($sentRow, ['TeslimAlan']),
            'raw_payload' => [
                'sent' => $sentRow,
                'tracking' => $trackingRow,
            ],
        ];
    }

    protected function estimateGrossAmountFromVat(float $vatAmount): float
    {
        if ($vatAmount <= 0) {
            return 0.0;
        }

        $vatRate = (float) config('cargo.integrations.surat.vat_rate', 0.20);
        if ($vatRate <= 0) {
            return 0.0;
        }

        return round($vatAmount * (1 + $vatRate) / $vatRate, 2);
    }

    protected function decimalValue(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        $normalized = preg_replace('/[^\d,.\-]/', '', (string) $value) ?: '0';

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace(',', '.', $normalized);
        }

        return round((float) $normalized, 2);
    }

    protected function payloadHasError(array $payload): bool
    {
        $isError = data_get($payload, 'IsError', data_get($payload, 'isError'));

        if (is_bool($isError)) {
            return $isError;
        }

        if (is_string($isError) && $isError !== '') {
            return filter_var($isError, FILTER_VALIDATE_BOOLEAN);
        }

        $statusCode = (int) data_get($payload, 'StatusCode', data_get($payload, 'statusCode', 200));

        return $statusCode >= 400;
    }

    protected function payloadMessage(array $payload): string
    {
        return trim((string) ($this->firstFilled($payload, [
            'errorMessage', 'ErrorMessage', 'Message', 'message', 'raw',
        ]) ?: ''));
    }

    protected function isCreateAccepted(array $payload, string $message): bool
    {
        $text = Str::lower(Str::ascii(trim($message)));
        $explicitSuccess = data_get($payload, 'IsError', data_get($payload, 'isError')) === false
            && (int) data_get($payload, 'StatusCode', 200) === 200;

        return !$this->payloadHasError($payload)
            && (
                Str::contains($text, ['tamam', 'basarili', 'barkod gonderilmistir'])
                || $explicitSuccess
            );
    }

    protected function isReferenceNotFoundMessage(string $message): bool
    {
        $normalized = Str::lower(Str::ascii($message));

        return Str::contains($normalized, ['bulunamadi', 'bulunamamistir', 'kayit yok', 'kayit bulunmadi']);
    }

    protected function appendQuery(string $url, array $payload): string
    {
        $query = Arr::query($payload);

        if ($query === '') {
            return $url;
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . $query;
    }

    protected function normalizePhone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?: '';

        if (strlen($digits) === 12 && str_starts_with($digits, '90')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return $digits;
    }

    protected function limitText(?string $value, int $limit): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        return mb_substr($value, 0, $limit);
    }

    protected function firstFilled(array $payload, array $keys): mixed
    {
        foreach ($keys as $key) {
            $value = data_get($payload, $key);

            if (filled($value)) {
                return $value;
            }
        }

        return null;
    }
}
