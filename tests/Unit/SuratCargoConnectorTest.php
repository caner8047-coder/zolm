<?php

namespace Tests\Unit;

use App\Models\CargoCarrierAccount;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Services\Cargo\SuratCargoConnector;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SuratCargoConnectorTest extends TestCase
{
    public function test_it_posts_real_surat_create_payload(): void
    {
        Http::fake([
            'api01.suratkargo.com.tr/api/GonderiyiKargoyaGonder' => Http::response('Tamam', 200),
        ]);

        $result = app(SuratCargoConnector::class)->createShipment($this->account(), $this->shipment());

        $this->assertTrue($result['success']);
        $this->assertSame('PKG-1', $result['external_shipment_id']);
        $this->assertSame('ready', $result['status']);

        Http::assertSent(function (Request $request) {
            $data = $request->data();

            return $request->url() === 'https://api01.suratkargo.com.tr/api/GonderiyiKargoyaGonder'
                && $data['KullaniciAdi'] === 'TEST-USER'
                && $data['Sifre'] === 'TEST-PASS'
                && data_get($data, 'Gonderi.OzelKargoTakipNo') === 'PKG-1'
                && data_get($data, 'Gonderi.KisiKurum') === 'Musteri Adi'
                && data_get($data, 'Gonderi.Pazaryerimi') === 1
                && data_get($data, 'Gonderi.EntegrasyonFirmasi') === 'Trendyol'
                && data_get($data, 'Gonderi.BirimDesi') === '10';
        });
    }

    public function test_it_tracks_with_web_order_reference_and_sums_surat_costs(): void
    {
        Http::fake([
            'api01.suratkargo.com.tr/api/KargoTakipHareketDetayi*' => Http::response([
                'IsError' => false,
                'Gonderiler' => [[
                    'KargoTakipNo' => 'TRK-123',
                    'KargonunDurumu' => 'Teslim Edildi',
                    'KargonunDurumuSayi' => 6,
                    'TeslimTarihi' => '2026-04-29T10:30:00',
                    'Tutar' => '100,50',
                    'OlcumTutar' => '12.25',
                    'ToplamDesiKg' => '10',
                    'Hareketler' => [
                        ['HareketAciklama' => 'Teslim Edildi', 'IslemTarihi' => '2026-04-29T10:30:00'],
                    ],
                ]],
            ], 200),
        ]);

        $result = app(SuratCargoConnector::class)->trackShipment($this->account([
            'query_password_encrypted' => 'QUERY-PASS',
        ]), $this->shipment());

        $this->assertSame('TRK-123', $result['tracking_number']);
        $this->assertSame('delivered', $result['status']);
        $this->assertEqualsWithDelta(112.75, $result['actual_cost'], 0.001);
        $this->assertEqualsWithDelta(10.0, $result['actual_desi'], 0.001);
        $this->assertCount(1, $result['events']);

        Http::assertSent(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $query['CariKodu'] === 'TEST-CUSTOMER'
                && $query['Sifre'] === 'QUERY-PASS'
                && $query['WebSiparisKodu'] === 'PKG-1';
        });
    }

    public function test_connection_accepts_reference_not_found_as_ready(): void
    {
        Http::fake([
            'api01.suratkargo.com.tr/api/KargoTakipHareketDetayi*' => Http::response([
                'IsError' => true,
                'errorMessage' => 'Kayıt bulunamadı.',
            ], 200),
        ]);

        $result = app(SuratCargoConnector::class)->testConnection($this->account());

        $this->assertTrue($result['success']);
        $this->assertTrue($result['ready']);
    }

    public function test_it_builds_date_range_report_with_sent_amount_and_deduped_rows(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/api/BarkodDetay/GonderilenKargoDetayi')) {
                return Http::response([
                    'IsError' => false,
                    'errorMessage' => null,
                    'GonderilenKargoDetayi' => [
                        [
                            'TakipNo' => 'TRK-REPORT',
                            'AliciUnvan' => 'Rapor Musterisi',
                            'Tutar' => 125.40,
                            'WebSiparisKodu' => 'WEB-1',
                            'OlusturulmaTarihi' => '2026-04-29T09:00:00',
                        ],
                        [
                            'TakipNo' => 'TRK-REPORT',
                            'AliciUnvan' => 'Rapor Musterisi',
                            'Tutar' => 125.40,
                            'WebSiparisKodu' => 'WEB-1',
                            'OlusturulmaTarihi' => '2026-04-29T09:00:00',
                        ],
                    ],
                ], 200);
            }

            if (str_contains($request->url(), '/api/KargoTakipHareketCoklu') && str_contains($request->url(), 'KargonunDurumuSayi=3')) {
                return Http::response([
                    'IsError' => false,
                    'errorMessage' => null,
                    'Gonderiler' => [[
                        'AliciUnvan' => 'Rapor Musterisi',
                        'KargoTakipNo' => 'TRK-REPORT',
                        'Evraktarihi' => '2026-04-29T10:00:00',
                        'ToplamAdet' => 2,
                        'ToplamDesiKg' => 18,
                        'Tutar' => 0,
                        'OlcumTutar' => 7.60,
                        'KargonunDurumu' => 'Gönderi Yolda',
                        'KargonunDurumuSayi' => 3,
                    ]],
                ], 200);
            }

            return Http::response([
                'IsError' => false,
                'errorMessage' => null,
                'Gonderiler' => [],
            ], 200);
        });

        $result = app(SuratCargoConnector::class)->sentShipmentReport($this->account(), '2026-04-29', '2026-04-29');

        $this->assertSame(1, $result['totals']['row_count']);
        $this->assertSame(2, $result['totals']['pieces']);
        $this->assertEqualsWithDelta(18, $result['totals']['desi'], 0.001);
        $this->assertEqualsWithDelta(125.40, $result['totals']['amount'], 0.001);
        $this->assertEqualsWithDelta(7.60, $result['totals']['measurement_amount'], 0.001);
        $this->assertEqualsWithDelta(133.00, $result['totals']['total_amount'], 0.001);
        $this->assertSame('Rapor Musterisi', $result['rows'][0]['customer_name']);
    }

    public function test_it_estimates_report_amount_from_vat_when_surat_returns_zero_amount(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/api/KargoTakipHareketCoklu') && str_contains($request->url(), 'KargonunDurumuSayi=3')) {
                return Http::response([
                    'IsError' => false,
                    'errorMessage' => null,
                    'Gonderiler' => [[
                        'AliciUnvan' => 'Kdv Musterisi',
                        'KargoTakipNo' => 'TRK-KDV',
                        'Evraktarihi' => '2026-04-29T10:00:00',
                        'ToplamAdet' => 1,
                        'ToplamDesiKg' => 18,
                        'Tutar' => 0,
                        'TutarKdvsiz' => 0,
                        'KdvTutar' => 33.78,
                        'OlcumTutar' => 0,
                        'KargonunDurumu' => 'Gönderi Yolda',
                        'KargonunDurumuSayi' => 3,
                    ]],
                ], 200);
            }

            return Http::response([
                'IsError' => false,
                'errorMessage' => null,
                'Gonderiler' => [],
                'GonderilenKargoDetayi' => [],
            ], 200);
        });

        $result = app(SuratCargoConnector::class)->sentShipmentReport($this->account(), '2026-04-29', '2026-04-29');

        $this->assertSame(1, $result['totals']['row_count']);
        $this->assertEqualsWithDelta(202.68, $result['totals']['amount'], 0.001);
        $this->assertEqualsWithDelta(202.68, $result['totals']['total_amount'], 0.001);
        $this->assertEqualsWithDelta(33.78, $result['rows'][0]['vat_amount'], 0.001);
        $this->assertSame('estimated_from_vat', $result['rows'][0]['amount_source']);
    }

    protected function account(array $overrides = []): CargoCarrierAccount
    {
        return new CargoCarrierAccount(array_merge([
            'customer_code' => 'TEST-CUSTOMER',
            'sender_username' => 'TEST-USER',
            'sender_password_encrypted' => 'TEST-PASS',
            'query_password_encrypted' => '',
            'api_base_url' => 'https://api01.suratkargo.com.tr',
            'query_base_url' => 'https://api01.suratkargo.com.tr',
            'settings_json' => [
                'endpoints' => [
                    'create_shipment' => '/api/GonderiyiKargoyaGonder',
                    'track_shipment' => '/api/KargoTakipHareketDetayi',
                    'cancel_shipment' => '/api/GonderiSil',
                    'recall_shipment' => '/api/GonderiGeriCek',
                ],
            ],
        ], $overrides));
    }

    protected function shipment(): Shipment
    {
        $shipment = new Shipment([
            'shipment_no' => 'SHP-1',
            'source_type' => 'marketplace_order',
            'direction' => 'outgoing',
            'flow_type' => 'order',
            'reference_number' => 'PKG-1',
            'order_number' => 'ORD-1',
            'package_number' => 'PKG-1',
            'customer_name' => 'Musteri Adi',
            'customer_phone' => '05551234567',
            'destination_city' => 'Istanbul',
            'destination_district' => 'Kadikoy',
            'destination_address' => 'Test mahallesi test sokak no 1',
            'parcel_count' => 1,
            'total_desi' => 10,
            'total_weight' => 2,
            'meta_json' => ['marketplace' => 'trendyol'],
        ]);

        $shipment->setRelation('items', new EloquentCollection([
            new ShipmentItem([
                'product_name' => 'Test Urun',
                'stock_code' => 'SKU-1',
                'quantity' => 1,
            ]),
        ]));

        $shipment->setRelation('parcels', new EloquentCollection());

        return $shipment;
    }
}
