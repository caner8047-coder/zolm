<?php

namespace Tests\Feature\WhatsApp;

use App\Models\WaAccount;

class ShippingCarrierConfigTest extends WhatsAppTestCase
{
    public function test_tracking_link_uses_carrier_config_not_hardcoded(): void
    {
        $service = new \App\Services\WhatsApp\ShippingNotificationService();

        // Reflection ile private metoda eriş
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('buildTrackingLink');
        $method->setAccessible(true);

        $shipment = new \App\Models\Shipment();
        $shipment->carrier_code = 'surat';
        $shipment->tracking_number = 'ABC123XYZ';

        $link = $method->invoke($service, $shipment);

        $this->assertStringContainsString('suratkargo.com.tr', $link);
        $this->assertStringContainsString('ABC123XYZ', $link);
        $this->assertStringNotContainsString('hardcoded', $link);
    }

    public function test_unknown_carrier_returns_hash(): void
    {
        $service = new \App\Services\WhatsApp\ShippingNotificationService();

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('buildTrackingLink');
        $method->setAccessible(true);

        $shipment = new \App\Models\Shipment();
        $shipment->carrier_code = 'unknown_carrier';
        $shipment->tracking_number = 'XYZ789';

        $link = $method->invoke($service, $shipment);

        $this->assertEquals('#', $link);
    }
}
