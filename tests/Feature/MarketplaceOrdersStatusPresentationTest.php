<?php

namespace Tests\Feature;

use App\Livewire\MarketplaceOrders;
use App\Models\ChannelOrder;
use App\Models\ChannelOrderItem;
use App\Models\MarketplaceStore;
use App\Models\MpProduct;
use App\Models\OrderProfitSnapshot;
use Tests\TestCase;

class MarketplaceOrdersStatusPresentationTest extends TestCase
{
    public function test_it_translates_received_and_shipping_marketplace_statuses(): void
    {
        $component = new MarketplaceOrders();

        $this->assertSame('Sipariş alındı', $component->humanStatus('RECEIVED'));
        $this->assertSame('info', $component->statusTone('RECEIVED'));

        $this->assertSame('Kargolanıyor', $component->humanStatus('SHIPPING'));
        $this->assertSame('warning', $component->statusTone('SHIPPING'));

        $this->assertSame('Yolda', $component->humanStatus('In transit'));
        $this->assertSame('info', $component->statusTone('In transit'));

        $this->assertSame('Dağıtımda', $component->humanStatus('OUT_FOR_DELIVERY'));
    }

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
            'info',
            $component->statusTone('Kargoya Verildi', 'pazarama', '986089186', null)
        );
    }

    public function test_it_builds_surat_marketplace_tracking_url(): void
    {
        $component = new MarketplaceOrders();

        $this->assertSame(
            'https://suratkargo.com.tr/Default/_KargoTakip?kargotakipno=15871474623784',
            $component->trackingUrl('Sürat Kargo Marketplace', '15871474623784')
        );
    }

    public function test_display_profit_metrics_use_product_commission_when_order_item_rate_is_missing(): void
    {
        $component = new MarketplaceOrders();

        $product = new MpProduct([
            'commission_rate' => 21,
            'cogs' => 0,
            'packaging_cost' => 0,
            'cargo_cost' => 0,
        ]);

        $item = new ChannelOrderItem([
            'quantity' => 1,
            'unit_price' => 1000,
            'gross_amount' => 1000,
            'billable_amount' => 1000,
            'commission_rate' => null,
        ]);
        $item->setRelation('product', $product);

        $order = new ChannelOrder([
            'id' => 1,
            'store_id' => 1,
        ]);
        $order->setAttribute('gross_revenue_metric', 1000);
        $order->setRelation('items', collect([$item]));

        $snapshot = new OrderProfitSnapshot([
            'store_id' => 1,
            'channel_order_id' => 1,
            'profit_state' => 'estimated',
        ]);

        $method = new \ReflectionMethod($component, 'applyDisplayProfitMetrics');
        $method->setAccessible(true);

        $resolvedSnapshot = $method->invoke($component, $order, $snapshot);

        $this->assertSame(21.0, $component->effectiveCommissionRateForOrderItem($item));
        $this->assertSame(210.0, (float) $resolvedSnapshot->commission_total);
        $this->assertSame(790.0, (float) $resolvedSnapshot->estimated_profit);
        $this->assertSame(790.0, (float) $order->profit_value_metric);
    }

    public function test_display_profit_metrics_use_koctas_agreed_commission_rate(): void
    {
        config()->set('marketplace.koctas.commission_rate', 15);

        $component = new MarketplaceOrders();

        $product = new MpProduct([
            'commission_rate' => 20,
            'cogs' => 0,
            'packaging_cost' => 0,
            'cargo_cost' => 0,
        ]);

        $item = new ChannelOrderItem([
            'quantity' => 1,
            'unit_price' => 1000,
            'gross_amount' => 1000,
            'billable_amount' => 1000,
            'commission_rate' => 21,
        ]);
        $item->setRelation('product', $product);
        $item->setRelation('store', new MarketplaceStore(['marketplace' => 'koctas']));

        $order = new ChannelOrder([
            'id' => 1,
            'store_id' => 1,
        ]);
        $order->setAttribute('gross_revenue_metric', 1000);
        $order->setRelation('items', collect([$item]));

        $snapshot = new OrderProfitSnapshot([
            'store_id' => 1,
            'channel_order_id' => 1,
            'profit_state' => 'estimated',
        ]);

        $method = new \ReflectionMethod($component, 'applyDisplayProfitMetrics');
        $method->setAccessible(true);

        $resolvedSnapshot = $method->invoke($component, $order, $snapshot);

        $this->assertSame(15.0, $component->effectiveCommissionRateForOrderItem($item));
        $this->assertSame(150.0, (float) $resolvedSnapshot->commission_total);
        $this->assertSame(850.0, (float) $resolvedSnapshot->estimated_profit);
    }

    public function test_display_profit_metrics_preserve_canonical_snapshot_deductions(): void
    {
        $component = new MarketplaceOrders();

        $product = new MpProduct([
            'commission_rate' => 23,
            'cogs' => 3000,
            'packaging_cost' => 0,
            'cargo_cost' => 560,
        ]);

        $item = new ChannelOrderItem([
            'quantity' => 1,
            'unit_price' => 1999,
            'gross_amount' => 1999,
            'billable_amount' => 1999,
            'commission_rate' => 23,
        ]);
        $item->setRelation('product', $product);

        $order = new ChannelOrder([
            'id' => 1,
            'store_id' => 1,
        ]);
        $order->setAttribute('gross_revenue_metric', 1999);
        $order->setAttribute('net_receivable_metric', 1511.73);
        $order->setAttribute('financial_event_count', 2);
        $order->setAttribute('profit_state_metric', 'confirmed');
        $order->setRelation('items', collect([$item]));

        $snapshot = new OrderProfitSnapshot([
            'store_id' => 1,
            'channel_order_id' => 1,
            'profit_state' => 'confirmed',
            'gross_revenue' => 1999,
            'net_receivable' => 1511.73,
            'commission_total' => 459.77,
            'service_fee_total' => 9.33,
            'withholding_total' => 18.17,
            'cogs_cost' => 3000,
            'packaging_cost' => 0,
            'own_cargo_cost' => 560,
            'estimated_profit' => -2048.27,
            'confirmed_profit' => -2048.27,
        ]);
        $snapshot->exists = true;

        $method = new \ReflectionMethod($component, 'applyDisplayProfitMetrics');
        $method->setAccessible(true);

        $resolvedSnapshot = $method->invoke($component, $order, $snapshot);

        $this->assertSame(9.33, (float) $resolvedSnapshot->service_fee_total);
        $this->assertSame(18.17, (float) $resolvedSnapshot->withholding_total);
        $this->assertSame(-2048.27, (float) $resolvedSnapshot->confirmed_profit);
        $this->assertSame(-2048.27, (float) $order->profit_value_metric);
    }
}
