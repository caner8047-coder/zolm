<?php

namespace Tests\Feature;

use App\Models\ChannelListing;
use App\Models\ChannelProduct;
use App\Models\IntegrationConnection;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\Connectors\TicimaxConnector;
use App\Services\Marketplace\MarketplaceConnectorManager;
use App\Services\Marketplace\TicimaxSoapGateway;
use Mockery\MockInterface;
use Tests\TestCase;

class TicimaxConnectorTest extends TestCase
{
    public function test_manager_resolves_ticimax_with_truthful_capabilities(): void
    {
        $connector = app(MarketplaceConnectorManager::class)->resolve('ticimax');

        $this->assertInstanceOf(TicimaxConnector::class, $connector);
        $this->assertTrue($connector->capabilities()['orders']);
        $this->assertTrue($connector->capabilities()['products']);
        $this->assertTrue($connector->capabilities()['finance']);
        $this->assertTrue($connector->capabilities()['claims']);
        $this->assertTrue($connector->capabilities()['price_push']);
        $this->assertTrue($connector->capabilities()['stock_push']);
        $this->assertFalse($connector->capabilities()['webhooks']);
        $this->assertFalse($connector->capabilities()['questions']);
    }

    public function test_it_verifies_product_service_with_membership_code(): void
    {
        $this->mock(TicimaxSoapGateway::class, function (MockInterface $mock): void {
            $mock->shouldReceive('call')
                ->once()
                ->withArgs(fn ($store, $service, $operation, $parameters) => $service === 'products'
                    && $operation === 'SelectUrunCount'
                    && data_get($parameters, 'UyeKodu') === 'member-code'
                    && data_get($parameters, 'f.Aktif') === -1
                    && ! array_key_exists('UrunFiltre', $parameters))
                ->andReturn(['SelectUrunCountResult' => 42]);
            $mock->shouldReceive('wsdlUrl')->once()->withArgs(fn ($store, $service) => $service === 'products')->andReturn('https://magaza.example/Servis/UrunServis.svc?wsdl');
            $mock->shouldReceive('wsdlUrl')->once()->withArgs(fn ($store, $service) => $service === 'orders')->andReturn('https://magaza.example/Servis/SiparisServis.svc?wsdl');
        });

        $result = app(TicimaxConnector::class)->testConnection($this->makeStore());

        $this->assertTrue($result['ok']);
        $this->assertSame(42, data_get($result, 'meta.product_count'));
    }

    public function test_it_normalizes_orders_products_payments_and_return_statuses(): void
    {
        $calls = [];
        $this->mock(TicimaxSoapGateway::class, function (MockInterface $mock) use (&$calls): void {
            $mock->shouldReceive('call')->andReturnUsing(function ($store, string $service, string $operation, array $parameters) use (&$calls): array {
                $calls[$operation][] = $parameters;

                return match ($operation) {
                    'SelectSiparis' => ['SelectSiparisResult' => ['WebSiparis' => [$this->orderPayload()]]],
                    'SelectUrun' => ['SelectUrunResult' => ['UrunKarti' => [$this->productPayload()]]],
                    default => [],
                };
            });
        });

        $connector = app(TicimaxConnector::class);
        $store = $this->makeStore();
        $orders = $connector->pullOrders($store, ['page_size' => 100]);
        $products = $connector->pullProducts($store, ['page_size' => 100]);
        $finance = $connector->pullFinancialEvents($store, ['page_size' => 100]);
        $claims = $connector->pullClaims($store, ['page_size' => 100]);

        $this->assertSame('returned', data_get($orders, 'items.0.order.order_status'));
        $this->assertSame('TRK-55', data_get($orders, 'items.0.package.cargo_tracking_number'));
        $this->assertSame('MASA-CEVIZ', data_get($orders, 'items.0.items.0.stock_code'));
        $this->assertSame('201', data_get($products, 'items.0.product.external_product_id'));
        $this->assertSame('200', data_get($products, 'items.0.product.external_parent_id'));
        $this->assertSame(12, data_get($products, 'items.0.listing.stock_quantity'));
        $this->assertSame('ticimax_order_payment', data_get($finance, 'items.0.event_source'));
        $this->assertSame('debit', data_get($finance, 'items.0.direction'));
        $this->assertSame('requested', data_get($claims, 'items.0.status'));
        $this->assertSame('return', data_get($claims, 'items.0.type'));
        $this->assertSame(100, data_get($calls, 'SelectSiparis.0.s.KayitSayisi'));
        $this->assertSame(-1, data_get($calls, 'SelectSiparis.0.f.SiparisDurumu'));
        $this->assertArrayNotHasKey('WebSiparisFiltre', $calls['SelectSiparis'][0]);
        $this->assertSame(100, data_get($calls, 'SelectUrun.0.s.KayitSayisi'));
        $this->assertSame(-1, data_get($calls, 'SelectUrun.0.f.Aktif'));
        $this->assertArrayNotHasKey('UrunFiltre', $calls['SelectUrun'][0]);
    }

    public function test_finance_fallback_uses_live_wsdl_parameter_names(): void
    {
        $order = $this->orderPayload();
        unset($order['Odemeler']);

        $this->mock(TicimaxSoapGateway::class, function (MockInterface $mock) use ($order): void {
            $mock->shouldReceive('call')
                ->once()
                ->withArgs(fn ($store, $service, $operation) => $service === 'orders' && $operation === 'SelectSiparis')
                ->andReturn(['SelectSiparisResult' => ['WebSiparis' => [$order]]]);
            $mock->shouldReceive('call')
                ->once()
                ->withArgs(fn ($store, $service, $operation, $parameters) => $service === 'orders'
                    && $operation === 'SelectSiparisOdeme'
                    && data_get($parameters, 'siparisId') === 55
                    && data_get($parameters, 'odemeId') === 0
                    && ! array_key_exists('SiparisId', $parameters))
                ->andReturn(['SelectSiparisOdemeResult' => ['WebSiparisOdeme' => [[
                    'ID' => 702,
                    'SiparisID' => 55,
                    'Tutar' => 2799.90,
                    'Tarih' => '2026-07-21T10:05:00+03:00',
                    'Onaylandi' => 1,
                ]]]]);
        });

        $result = app(TicimaxConnector::class)->pullFinancialEvents($this->makeStore(), [
            'page_size' => 100,
            'max_pages' => 1,
        ]);

        $this->assertCount(1, $result['items']);
        $this->assertSame('702', data_get($result, 'items.0.external_event_id'));
    }

    public function test_it_uses_documented_variant_price_and_stock_operations(): void
    {
        $calls = [];
        $this->mock(TicimaxSoapGateway::class, function (MockInterface $mock) use (&$calls): void {
            $mock->shouldReceive('call')->twice()->andReturnUsing(function ($store, $service, $operation, $parameters) use (&$calls): array {
                $calls[$operation] = $parameters;

                return [$operation.'Result' => 1];
            });
        });

        $connector = app(TicimaxConnector::class);
        $listing = $this->makeListing();
        $price = $connector->pushPrice($listing, 2799.90);
        $stock = $connector->pushStock($listing, 18);

        $this->assertSame('completed', $price['status']);
        $this->assertSame('completed', $stock['status']);
        $this->assertSame(201, data_get($calls, 'VaryasyonGuncelle.urun.ID'));
        $this->assertSame(2799.90, data_get($calls, 'VaryasyonGuncelle.urun.SatisFiyati'));
        $this->assertTrue(data_get($calls, 'VaryasyonGuncelle.ayar.SatisFiyatiGuncelle'));
        $this->assertSame(201, data_get($calls, 'StokAdediGuncelle.urunler.0.ID'));
        $this->assertSame(18, data_get($calls, 'StokAdediGuncelle.urunler.0.StokAdedi'));
    }

    public function test_gateway_derives_https_wsdl_urls_from_store_url(): void
    {
        $gateway = app(TicimaxSoapGateway::class);
        $store = $this->makeStore();

        $this->assertSame('https://magaza.example/Servis/UrunServis.svc?wsdl', $gateway->wsdlUrl($store, 'products'));
        $this->assertSame('https://magaza.example/Servis/SiparisServis.svc?wsdl', $gateway->wsdlUrl($store, 'orders'));
    }

    protected function makeStore(): MarketplaceStore
    {
        $store = new MarketplaceStore([
            'marketplace' => 'ticimax',
            'store_name' => 'Ticimax Test',
            'seller_id' => 'ticimax-test',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
        ]);
        $connection = new IntegrationConnection([
            'provider' => 'ticimax',
            'auth_type' => 'membership_code',
            'credentials_encrypted' => [
                'api_secret' => 'member-code',
                'store_url' => 'https://magaza.example',
            ],
            'api_base_url' => 'https://magaza.example',
            'status' => 'configured',
        ]);
        $connection->id = 30;
        $store->setRelation('connection', $connection);

        return $store;
    }

    protected function makeListing(): ChannelListing
    {
        $product = new ChannelProduct([
            'external_product_id' => '201',
            'external_parent_id' => '200',
            'stock_code' => 'MASA-CEVIZ',
            'raw_payload' => ['ID' => 200, 'variant' => ['ID' => 201]],
        ]);
        $listing = new ChannelListing([
            'listing_id' => '201',
            'currency' => 'TRY',
        ]);
        $listing->id = 1;
        $listing->setRelation('store', $this->makeStore());
        $listing->setRelation('channelProduct', $product);

        return $listing;
    }

    /**
     * @return array<string, mixed>
     */
    protected function orderPayload(): array
    {
        return [
            'ID' => 55,
            'SiparisKodu' => 'TCX-55',
            'Durum' => 16,
            'SiparisDurumu' => 'Kısmi iade talebi',
            'SiparisTarihi' => '2026-07-21T10:00:00+03:00',
            'SiparisToplamTutari' => 2799.90,
            'ParaBirimi' => 'TRY',
            'Kur' => 1,
            'AdiSoyadi' => 'Ayşe Test',
            'Mail' => 'ayse@example.com',
            'TeslimatAdresi' => [
                'AliciAdi' => 'Ayşe Test',
                'AliciTelefon' => '5551112233',
                'Il' => 'İstanbul',
                'Ilce' => 'Kadıköy',
                'Ulke' => ['Alpha2Code' => 'TR'],
            ],
            'FaturaAdresi' => [
                'FirmaAdi' => 'Ayşe Test',
                'VergiNo' => '11111111111',
                'isKurumsal' => false,
            ],
            'KargoEntegrasyonTanim' => 'Yurtiçi Kargo',
            'KargoEntegrasyonTakipNo' => 'TRK-55',
            'Urunler' => ['WebSiparisUrun' => [[
                'ID' => 551,
                'UrunID' => 201,
                'UrunKartiID' => 200,
                'UrunAdi' => 'Masa Ceviz',
                'StokKodu' => 'MASA-CEVIZ',
                'Barkod' => '8690055',
                'Adet' => 1,
                'Tutar' => 2799.90,
                'KdvOrani' => 20,
                'DurumAd' => 'İade talebi',
                'IslemAd' => 'Kısmi iade',
            ]]],
            'Odemeler' => ['WebSiparisOdeme' => [[
                'ID' => 701,
                'SiparisID' => 55,
                'Tutar' => 2799.90,
                'Tarih' => '2026-07-21T10:05:00+03:00',
                'Onaylandi' => 1,
                'PosReferansID' => 'POS-701',
                'TaksitSayisi' => 3,
            ]]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function productPayload(): array
    {
        return [
            'ID' => 200,
            'Aktif' => true,
            'UrunAdi' => 'Masa',
            'Aciklama' => 'Ahşap masa',
            'AnaKategori' => 'Mobilya',
            'Marka' => 'ZOLM',
            'Resimler' => ['https://cdn.example/masa.jpg'],
            'TahminiTeslimSuresi' => 3,
            'Varyasyonlar' => ['Varyasyon' => [[
                'ID' => 201,
                'Aktif' => true,
                'StokKodu' => 'MASA-CEVIZ',
                'Barkod' => '8690055',
                'SatisFiyati' => 2799.90,
                'PiyasaFiyati' => 2999.90,
                'StokAdedi' => 12,
                'KdvOrani' => 20,
                'ParaBirimiKodu' => 'TRY',
                'Ozellikler' => ['VaryasyonOzellik' => [['Tanim' => 'Renk', 'Deger' => 'Ceviz']]],
            ]]],
        ];
    }
}
