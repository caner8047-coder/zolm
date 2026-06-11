<?php

namespace Tests\Unit;

use App\Services\Cargo\CargoDeliveryLookupService;
use App\Services\Cargo\CargoShipmentService;
use App\Services\Cargo\SuratCargoConnector;
use Tests\TestCase;

class CargoDeliveryLookupServiceTest extends TestCase
{
    protected CargoDeliveryLookupService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new CargoDeliveryLookupService(
            app(CargoShipmentService::class),
            app(SuratCargoConnector::class),
        );
    }

    public function test_it_marks_out_for_delivery_as_distribution_available(): void
    {
        $result = $this->service->analyzeDistribution([
            'available' => true,
            'status' => 'out_for_delivery',
            'status_label' => 'Kurye Dağıtımda',
            'status_code' => 5,
            'events' => [
                ['event_description' => 'Kurye Dağıtıma Çıktı'],
            ],
        ]);

        $this->assertSame('yes', $result['state']);
        $this->assertSame('Dağıtım var', $result['label']);
        $this->assertSame('high', $result['confidence']);
    }

    public function test_it_marks_out_of_distribution_area_as_not_available(): void
    {
        $result = $this->service->analyzeDistribution([
            'available' => true,
            'status' => 'in_transit',
            'status_label' => 'Kargo Devir',
            'status_code' => 7,
            'devir_status' => 'Evet',
            'devir_reason' => 'Dağıtım Alanı Dışı',
        ]);

        $this->assertSame('no', $result['state']);
        $this->assertSame('Dağıtım yok', $result['label']);
        $this->assertSame('high', $result['confidence']);
    }

    public function test_it_marks_mobile_distribution_as_limited(): void
    {
        $result = $this->service->analyzeDistribution([
            'available' => true,
            'status' => 'in_transit',
            'status_label' => 'Kargo Devir',
            'status_code' => 7,
            'devir_status' => 'Evet',
            'devir_reason' => 'Mobil - Belirli Günler Gidilen Alan',
        ]);

        $this->assertSame('warning', $result['state']);
        $this->assertSame('Dağıtım sınırlı', $result['label']);
        $this->assertSame('medium', $result['confidence']);
    }

    public function test_it_keeps_missing_surat_response_unknown(): void
    {
        $result = $this->service->analyzeDistribution([
            'available' => false,
            'message' => 'Aktif Sürat sorgulama hesabı bulunamadı.',
        ]);

        $this->assertSame('unknown', $result['state']);
        $this->assertSame('Sürat bilgisi yok', $result['label']);
        $this->assertSame('low', $result['confidence']);
    }
}
