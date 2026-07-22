<?php

namespace Tests\Unit;

use App\Models\CargoCarrierAccount;
use App\Models\Shipment;
use App\Services\Cargo\ArasCargoConnector;
use App\Services\Cargo\DhlExpressCargoConnector;
use App\Services\Cargo\HepsiJetCargoConnector;
use App\Services\Cargo\PttCargoConnector;
use App\Services\Cargo\YurticiCargoConnector;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CargoCarrierConnectorTest extends TestCase
{
    public function test_yurtici_connector_maps_create_and_tracking_responses(): void
    {
        $connector = new FakeYurticiCargoConnector;
        $account = $this->account('yurtici');
        $shipment = $this->shipment();

        $created = $connector->createShipment($account, $shipment);
        $tracked = $connector->trackShipment($account, $shipment);

        $this->assertSame('YK123', $created['tracking_number']);
        $this->assertSame('delivered', $tracked['status']);
    }

    public function test_aras_connector_maps_create_and_tracking_responses(): void
    {
        $connector = new FakeArasCargoConnector;
        $account = $this->account('aras');
        $shipment = $this->shipment();

        $created = $connector->createShipment($account, $shipment);
        $tracked = $connector->trackShipment($account, $shipment);

        $this->assertSame('ARAS123', $created['tracking_number']);
        $this->assertSame('in_transit', $tracked['status']);
    }

    public function test_hepsijet_connection_uses_token_endpoint(): void
    {
        config()->set('cargo.integrations.hepsijet.test_base_url', 'https://hepsijet.test');
        Http::fake(['https://hepsijet.test/auth/getToken' => Http::response(['token' => 'token-123'])]);

        $result = (new HepsiJetCargoConnector)->testConnection($this->account('hepsijet'));

        $this->assertTrue($result['success']);
        Http::assertSent(fn ($request) => $request->url() === 'https://hepsijet.test/auth/getToken');
    }

    public function test_ptt_connector_calculates_check_digit_and_maps_shipment_lifecycle(): void
    {
        $connector = new FakePttCargoConnector;
        $account = $this->account('ptt', [
            'customer_id' => '123456789',
            'barcode_start' => '275036569845',
            'barcode_end' => '275036569899',
            'postal_cheque_number' => '12345678',
            'send_receiver_sms' => true,
        ]);
        $shipment = $this->shipment();

        $created = $connector->createShipment($account, $shipment);
        $shipment->forceFill([
            'tracking_number' => $created['tracking_number'],
            'barcode' => $created['barcode'],
            'raw_payload' => $created['raw_payload'],
        ]);
        $tracked = $connector->trackShipment($account, $shipment);
        $cancelled = $connector->cancelShipment($account, $shipment);

        $this->assertSame('2750365698456', $connector->barcodeWithCheckDigit('275036569845'));
        $this->assertSame('2750365698456', $created['tracking_number']);
        $this->assertSame('delivered', $tracked['status']);
        $this->assertSame('cancelled', $cancelled['status']);
        $this->assertSame('MHS', data_get($created, 'raw_payload.request.input.dongu.0.odemesekli'));
        $this->assertSame('SB', data_get($created, 'raw_payload.request.input.dongu.0.ekhizmet'));
    }

    public function test_dhl_connection_queries_available_products(): void
    {
        config()->set('cargo.integrations.dhl_express.test_base_url', 'https://dhl.test');
        Http::fake(['https://dhl.test/products*' => Http::response(['products' => [['productCode' => 'N']]])]);
        $account = $this->account('dhl_express', [
            'account_number' => '123456789',
            'shipper_postal_code' => '20000',
            'shipper_city' => 'Denizli',
            'shipper_country_code' => 'TR',
        ]);

        $result = (new DhlExpressCargoConnector)->testConnection($account);

        $this->assertTrue($result['success']);
        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://dhl.test/products'));
    }

    protected function account(string $carrier, array $credentials = []): CargoCarrierAccount
    {
        return new CargoCarrierAccount([
            'carrier_code' => $carrier,
            'account_name' => 'ZOLM Test',
            'origin_city' => 'Denizli',
            'origin_district' => 'Merkezefendi',
            'origin_address' => 'Test adresi',
            'contact_name' => 'ZOLM Test',
            'contact_phone' => '05550000000',
            'credentials_encrypted' => array_merge([
                'environment' => 'test',
                'username' => 'test-user',
                'password' => 'test-password',
            ], $credentials),
        ]);
    }

    protected function shipment(): Shipment
    {
        return new Shipment([
            'shipment_no' => 'SHP-TEST-1',
            'reference_number' => 'REF-TEST-1',
            'order_number' => 'ORDER-1',
            'customer_name' => 'Test Müşteri',
            'customer_phone' => '05551111111',
            'destination_city' => 'İstanbul',
            'destination_district' => 'Kadıköy',
            'destination_address' => 'Test teslimat adresi',
            'parcel_count' => 1,
            'total_desi' => 2,
            'total_weight' => 1,
        ]);
    }
}

class FakeYurticiCargoConnector extends YurticiCargoConnector
{
    protected function call(CargoCarrierAccount $account, string $method, array $payload): array
    {
        return $method === 'createShipment'
            ? ['ShippingOrderResultVO' => ['shippingOrderDetailVO' => ['errCode' => '0', 'cargoKey' => 'YK123']]]
            : ['ShippingDeliveryVO' => ['shippingDeliveryDetailVO' => ['docId' => 'YK123', 'cargoEventExplanation' => 'Teslim edildi']]];
    }
}

class FakeArasCargoConnector extends ArasCargoConnector
{
    protected function call(CargoCarrierAccount $account, string $method, array $payload): array
    {
        return $method === 'SetOrder'
            ? ['SetOrderResult' => ['OrderResultInfo' => ['ResultCode' => '0', 'CargoKey' => 'ARAS123']]]
            : ['GetCargoTransactionResult' => ['Status' => 'Transfer merkezinde', 'CargoKey' => 'ARAS123']];
    }
}

class FakePttCargoConnector extends PttCargoConnector
{
    protected function callUpload(CargoCarrierAccount $account, string $method, array $payload): array
    {
        if ($method === 'barkodVeriSil') {
            return ['return' => ['hataKodu' => 1, 'aciklama' => 'Silindi']];
        }

        return [
            'return' => [
                'hataKodu' => 1,
                'aciklama' => 'Başarılı',
                'dongu' => [[
                    'barkod' => data_get($payload, 'input.dongu.0.barkodNo'),
                    'donguSonuc' => true,
                    'donguHataKodu' => 1,
                ]],
            ],
        ];
    }

    protected function callTracking(CargoCarrierAccount $account, string $method, array $payload): array
    {
        return [
            'return' => [
                'hataKodu' => 1,
                'barkod' => data_get($payload, 'input.barkod'),
                'sonIslemAciklama' => 'Teslim edildi',
                'aliciTeslimTarih' => '21/07/2026 16:30:00',
            ],
        ];
    }
}
