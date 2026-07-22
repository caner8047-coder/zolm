<?php

namespace App\Services\Cargo;

use App\Models\CargoCarrierAccount;
use App\Models\Shipment;
use App\Services\Cargo\Contracts\CargoCarrierConnector;
use Illuminate\Support\Facades\DB;

class PttCargoConnector extends AbstractSoapCargoConnector implements CargoCarrierConnector
{
    public function testConnection(CargoCarrierAccount $account): array
    {
        $this->validateAccount($account);
        $barcode = $this->barcodeWithCheckDigit((string) $this->credential($account, 'barcode_start'));
        $response = $this->callTracking($account, 'barkodSorgu', [
            'input' => $this->trackingCredentials($account, $barcode),
        ]);

        if ($this->hasAuthenticationError($response)) {
            throw new \RuntimeException('PTT müşteri numarası veya şifresi doğrulanamadı.');
        }

        return [
            'success' => true,
            'ready' => true,
            'message' => 'PTT Kargo bağlantısı ve barkod aralığı doğrulandı.',
            'response' => $response,
        ];
    }

    public function createShipment(CargoCarrierAccount $account, Shipment $shipment): array
    {
        $this->validateAccount($account);
        $barcodeBase = $this->allocateBarcodeBase($account);
        $barcode = $this->barcodeWithCheckDigit($barcodeBase);
        $fileName = 'ZOLM-'.now()->format('YmdHis').'-'.$shipment->id.'-'.substr($barcodeBase, -4);
        $sendSms = (bool) $this->credential($account, 'send_receiver_sms', false);
        $phone = $this->pttPhone($shipment->customer_phone);
        $postalCheque = trim((string) $this->credential($account, 'postal_cheque_number', ''));

        $input = [
            'dongu' => [[
                'aAdres' => $shipment->destination_address,
                'agirlik' => max(1, (int) round(max(0.001, (float) $shipment->total_weight) * 1000)),
                'aliciAdi' => $shipment->customer_name,
                'aliciIlAdi' => $shipment->destination_city,
                'aliciIlceAdi' => $shipment->destination_district,
                'aliciSms' => $sendSms ? $phone : null,
                'aliciTel' => $phone,
                'barkodNo' => $barcode,
                'desi' => max(0.01, (float) $shipment->total_desi),
                'ekhizmet' => $sendSms ? 'SB' : null,
                'musteriReferansNo' => $this->reference($shipment),
                'odemesekli' => $postalCheque !== '' ? 'MHS' : 'N',
                'rezerve1' => $postalCheque !== '' ? $postalCheque : null,
            ]],
            'dosyaAdi' => $fileName,
            'gonderiTip' => 'NORMAL',
            'gonderiTur' => 'KARGO',
            'kullanici' => (string) $this->credential($account, 'service_user', 'PttWs'),
            'musteriId' => (int) $this->credential($account, 'customer_id'),
            'sifre' => (string) $this->credential($account, 'password'),
        ];
        $response = $this->callUpload($account, 'kabulEkle2', ['input' => $input]);
        $errorCode = (int) $this->recursiveValue($response, ['hataKodu'], 0);
        $rowSuccess = $this->recursiveValue($response, ['donguSonuc'], null);
        $message = (string) $this->recursiveValue($response, ['donguAciklama', 'aciklama'], '');

        if ($errorCode !== 1 || $rowSuccess === false) {
            throw new \RuntimeException('PTT gönderi oluşturma hatası: '.($message ?: "Hata kodu {$errorCode}"));
        }

        $acceptedBarcode = (string) $this->recursiveValue($response, ['barkod'], $barcode);

        return [
            'success' => true,
            'external_shipment_id' => $acceptedBarcode,
            'tracking_number' => $acceptedBarcode,
            'barcode' => $acceptedBarcode,
            'status' => 'ready',
            'status_label' => "PTT Kargo'ya aktarıldı",
            'raw_payload' => [
                'request' => ['input' => $input],
                'response' => $response,
                'ptt_file_name' => $fileName,
            ],
        ];
    }

    public function cancelShipment(CargoCarrierAccount $account, Shipment $shipment, array $context = []): array
    {
        $barcode = $shipment->barcode ?: $shipment->tracking_number;
        $fileName = data_get($shipment->raw_payload, 'ptt_file_name')
            ?: data_get($shipment->raw_payload, 'request.input.dosyaAdi');

        if (blank($barcode) || blank($fileName)) {
            throw new \RuntimeException('PTT iptali için barkod ve ilk yüklemede kullanılan dosya adı gerekli.');
        }

        $response = $this->callUpload($account, 'barkodVeriSil', [
            'inpDelete' => [
                'barcode' => $barcode,
                'dosyaAdi' => $fileName,
                'musteriId' => (int) $this->credential($account, 'customer_id'),
                'sifre' => (string) $this->credential($account, 'password'),
            ],
        ]);
        $errorCode = (int) $this->recursiveValue($response, ['hataKodu'], 0);
        $message = (string) $this->recursiveValue($response, ['aciklama'], '');

        if ($errorCode !== 1) {
            throw new \RuntimeException('PTT gönderi iptal hatası: '.($message ?: "Hata kodu {$errorCode}"));
        }

        return [
            'success' => true,
            'status' => 'cancelled',
            'status_label' => 'PTT gönderisi iptal edildi',
            'raw_payload' => $response,
        ];
    }

    public function trackShipment(CargoCarrierAccount $account, Shipment $shipment): array
    {
        $barcode = $shipment->barcode ?: $shipment->tracking_number;
        if (blank($barcode)) {
            throw new \RuntimeException('PTT takip sorgusu için barkod gerekli.');
        }

        $response = $this->callTracking($account, 'barkodSorgu', [
            'input' => $this->trackingCredentials($account, (string) $barcode),
        ]);
        $errorCode = (int) $this->recursiveValue($response, ['hataKodu'], 0);
        $label = (string) $this->recursiveValue($response, [
            'sonIslemAciklama', 'gonderi_durum_aciklama', 'aciklama',
        ], 'Gönderi sorgulandı');

        if ($errorCode > 1) {
            throw new \RuntimeException('PTT takip hatası: '.$label);
        }

        return [
            'success' => true,
            'tracking_number' => $this->recursiveValue($response, ['barkod', 'barkod_no'], $barcode),
            'barcode' => $this->recursiveValue($response, ['barkod', 'barkod_no'], $barcode),
            'status' => $this->normalizeStatus($label),
            'status_label' => $label,
            'delivered_at' => $this->recursiveValue($response, ['aliciTeslimTarih', 'ptt_teslim_tarihi']),
            'actual_cost' => $this->recursiveValue($response, ['ucret']),
            'actual_desi' => $this->recursiveValue($response, ['desi']),
            'last_event_at' => $this->recursiveValue($response, ['sonIslemTarih', 'son_islem_tarihi']),
            'raw_payload' => $response,
        ];
    }

    public function barcodeWithCheckDigit(string $barcodeBase): string
    {
        if (! preg_match('/^\d{12}$/', $barcodeBase)) {
            throw new \RuntimeException('PTT barkod değeri 12 haneli sayısal olmalıdır.');
        }

        $sum = 0;
        foreach (str_split($barcodeBase) as $index => $digit) {
            $sum += (int) $digit * ($index % 2 === 0 ? 1 : 3);
        }

        return $barcodeBase.((10 - ($sum % 10)) % 10);
    }

    protected function allocateBarcodeBase(CargoCarrierAccount $account): string
    {
        if (! $account->exists) {
            return (string) $this->credential($account, 'barcode_start');
        }

        return DB::transaction(function () use ($account) {
            $lockedAccount = CargoCarrierAccount::query()->lockForUpdate()->findOrFail($account->id);
            $credentials = $lockedAccount->credentials_encrypted ?? [];
            $start = (string) data_get($credentials, 'barcode_start', '');
            $end = (string) data_get($credentials, 'barcode_end', '');
            $next = (string) data_get($credentials, 'next_barcode', $start);

            $this->validateBarcodeRange($start, $end);
            if ((int) $next < (int) $start) {
                $next = $start;
            }
            if ((int) $next > (int) $end) {
                throw new \RuntimeException('PTT barkod aralığı tükendi. PTT’den yeni bir barkod aralığı alıp hesabı güncelleyin.');
            }

            data_set($credentials, 'next_barcode', str_pad((string) ((int) $next + 1), 12, '0', STR_PAD_LEFT));
            $lockedAccount->forceFill(['credentials_encrypted' => $credentials])->save();
            $account->setRawAttributes($lockedAccount->getAttributes(), true);

            return str_pad($next, 12, '0', STR_PAD_LEFT);
        }, 3);
    }

    protected function validateAccount(CargoCarrierAccount $account): void
    {
        $this->requireCredentials($account, ['customer_id', 'password', 'barcode_start', 'barcode_end']);
        if (! preg_match('/^\d{9,10}$/', (string) $this->credential($account, 'customer_id'))) {
            throw new \RuntimeException('PTT müşteri numarası 9 veya 10 haneli sayısal olmalıdır.');
        }
        $this->validateBarcodeRange(
            (string) $this->credential($account, 'barcode_start'),
            (string) $this->credential($account, 'barcode_end'),
        );

        $postalCheque = (string) $this->credential($account, 'postal_cheque_number', '');
        if ($postalCheque !== '' && ! preg_match('/^\d{8}$/', $postalCheque)) {
            throw new \RuntimeException('PTT Posta Çeki No 8 haneli olmalıdır.');
        }
    }

    protected function validateBarcodeRange(string $start, string $end): void
    {
        if (! preg_match('/^\d{12}$/', $start) || ! preg_match('/^\d{12}$/', $end)) {
            throw new \RuntimeException('PTT barkod başlangıç ve bitiş değerleri 12 haneli sayısal olmalıdır.');
        }
        if ((int) $start > (int) $end) {
            throw new \RuntimeException('PTT barkod başlangıç değeri bitiş değerinden büyük olamaz.');
        }
    }

    protected function trackingCredentials(CargoCarrierAccount $account, string $barcode): array
    {
        return [
            'musteri_no' => (int) $this->credential($account, 'customer_id'),
            'sifre' => (string) $this->credential($account, 'password'),
            'barkod' => $barcode,
        ];
    }

    protected function callUpload(CargoCarrierAccount $account, string $method, array $payload): array
    {
        $environment = (string) $this->credential($account, 'environment', 'test');
        $wsdl = (string) ($this->credential($account, 'upload_wsdl_url')
            ?: config("cargo.integrations.ptt.{$environment}_upload_wsdl"));

        return $this->soapCall($account, $wsdl, $method, $payload);
    }

    protected function callTracking(CargoCarrierAccount $account, string $method, array $payload): array
    {
        $wsdl = (string) ($this->credential($account, 'tracking_wsdl_url')
            ?: config('cargo.integrations.ptt.tracking_wsdl'));

        return $this->soapCall($account, $wsdl, $method, $payload);
    }

    protected function pttPhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?: '';
        if (str_starts_with($digits, '90') && strlen($digits) > 10) {
            $digits = substr($digits, 2);
        }
        $digits = ltrim($digits, '0');

        return strlen($digits) === 10 ? $digits : null;
    }
}
