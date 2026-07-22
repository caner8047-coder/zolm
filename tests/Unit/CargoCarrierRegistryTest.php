<?php

namespace Tests\Unit;

use App\Models\CargoCarrierAccount;
use App\Models\Shipment;
use App\Services\Cargo\CargoCarrierManager;
use App\Services\Cargo\CargoCarrierRegistry;
use App\Services\Cargo\Contracts\CargoCarrierConnector;
use App\Services\Cargo\SuratCargoConnector;
use App\Services\Cargo\YurticiCargoConnector;
use Tests\TestCase;

class CargoCarrierRegistryTest extends TestCase
{
    public function test_it_registers_requested_carriers_with_canonical_codes(): void
    {
        $registry = app(CargoCarrierRegistry::class);

        $this->assertSame('surat', $registry->canonicalCode('Sürat Kargo'));
        $this->assertSame('yurtici', $registry->canonicalCode('Yurtiçi Kargo'));
        $this->assertSame('dhl_express', $registry->canonicalCode('DHL Kargo'));
        $this->assertSame('trendyol_express', $registry->canonicalCode('TrendyolExpress'));
        $this->assertNull($registry->find('MNG Kargo'));
        $this->assertSame(
            ['surat', 'yurtici', 'aras', 'hepsijet', 'dhl_express', 'trendyol_express'],
            array_values(array_intersect(
                array_keys($registry->all()),
                ['surat', 'yurtici', 'aras', 'hepsijet', 'dhl_express', 'trendyol_express'],
            )),
        );
    }

    public function test_manager_resolves_configured_connector_drivers(): void
    {
        $manager = app(CargoCarrierManager::class);

        $this->assertInstanceOf(SuratCargoConnector::class, $manager->forCode('surat'));
        $this->assertInstanceOf(YurticiCargoConnector::class, $manager->forCode('yurtici'));
        $this->assertEqualsCanonicalizing(
            ['surat', 'yurtici', 'aras', 'ptt', 'hepsijet', 'dhl_express'],
            $manager->connectorCodes(),
        );
    }

    public function test_a_new_driver_can_be_enabled_without_changing_shipment_orchestration(): void
    {
        config()->set('cargo.companies.yurtici.connector', FakeCargoCarrierConnector::class);
        config()->set('cargo.companies.yurtici.capabilities', ['create', 'cancel', 'track']);
        config()->set('cargo.companies.yurtici.integration_status', 'active');

        $manager = app(CargoCarrierManager::class);
        $registry = app(CargoCarrierRegistry::class);

        $this->assertInstanceOf(FakeCargoCarrierConnector::class, $manager->forCode('yurtici'));
        $this->assertTrue($registry->supports('yurtici', 'create'));
        $this->assertContains('yurtici', $manager->connectorCodes());
    }

    public function test_account_endpoint_lookup_is_scoped_to_its_carrier(): void
    {
        config()->set('cargo.integrations.aras.endpoints.track_shipment', '/aras-track');
        config()->set('cargo.integrations.surat.endpoints.cancel_shipment', '/surat-cancel');

        $account = new CargoCarrierAccount([
            'carrier_code' => 'aras',
            'settings_json' => [],
        ]);

        $this->assertTrue($account->hasApiEndpoint('track_shipment'));
        $this->assertFalse($account->hasApiEndpoint('cancel_shipment'));
    }
}

class FakeCargoCarrierConnector implements CargoCarrierConnector
{
    public function testConnection(CargoCarrierAccount $account): array
    {
        return ['success' => true];
    }

    public function createShipment(CargoCarrierAccount $account, Shipment $shipment): array
    {
        return ['success' => true, 'status' => 'label_created'];
    }

    public function cancelShipment(CargoCarrierAccount $account, Shipment $shipment, array $context = []): array
    {
        return ['success' => true, 'status' => 'cancelled'];
    }

    public function trackShipment(CargoCarrierAccount $account, Shipment $shipment): array
    {
        return ['success' => true, 'status' => 'in_transit'];
    }
}
