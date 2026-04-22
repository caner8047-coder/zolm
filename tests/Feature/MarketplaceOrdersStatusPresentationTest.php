<?php

namespace Tests\Feature;

use App\Livewire\MarketplaceOrders;
use Tests\TestCase;

class MarketplaceOrdersStatusPresentationTest extends TestCase
{
    public function test_it_downgrades_pazarama_cargo_status_without_tracking_to_approved(): void
    {
        $component = new MarketplaceOrders();
        $rawPayload = ['estimatedShippingDate' => '2026-04-24T19:00:00+03:00'];

        $this->assertSame(
            'Onaylandı',
            $component->humanStatus('Kargoya Verildi', 'pazarama', null, null)
        );

        $this->assertSame(
            'info',
            $component->statusTone('Kargoya Verildi', 'pazarama', null, null)
        );

        $this->assertSame(
            'Kargolama tarihi',
            $component->shipmentDateLabel('pazarama', 'Kargoya Verildi', null, null)
        );

        $this->assertSame(
            'Kargolama tarihi',
            $component->shipmentDateLabel('pazarama', 'approved', null, null, $rawPayload)
        );

        $this->assertSame(
            'Kargolama',
            $component->shipmentDateShortLabel('pazarama', 'approved', null, null, $rawPayload)
        );
    }

    public function test_it_keeps_pazarama_cargo_status_when_tracking_exists(): void
    {
        $component = new MarketplaceOrders();

        $this->assertSame(
            'Kargolandı',
            $component->humanStatus('Kargoya Verildi', 'pazarama', '986089186', null)
        );

        $this->assertSame(
            'warning',
            $component->statusTone('Kargoya Verildi', 'pazarama', '986089186', null)
        );
    }
}
