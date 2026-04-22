<?php

namespace Tests\Feature;

use App\Models\ChannelOrder;
use App\Models\ChannelOrderItem;
use App\Models\ChannelOrderPackage;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\User;
use App\Livewire\MarketplaceOrders;
use App\Services\Marketplace\MarketplaceOrderSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceOrderSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_normalizes_pazarama_approved_payloads_before_persisting(): void
    {
        $store = $this->createPazaramaStore();

        app(MarketplaceOrderSyncService::class)->sync($store, [[
            'order' => [
                'external_order_id' => '986089186',
                'order_number' => '986089186',
                'order_status' => 'Shipped',
                'ordered_at' => '2026-04-20 19:30:00',
                'raw_payload' => [
                    'orderStatus' => 3,
                ],
            ],
            'package' => [
                'external_package_id' => '20550',
                'package_number' => '20550',
                'package_status' => 'Shipped',
                'cargo_company' => 'Surat',
                'cargo_tracking_number' => null,
                'shipped_at' => '2026-04-24 19:00:00',
                'raw_payload' => [
                    'orderStatus' => 3,
                ],
            ],
            'items' => [[
                'external_line_id' => 'LINE-986089186-1',
                'product_name' => 'Liva Bohem Sandikli Puf',
                'quantity' => 1,
                'unit_price' => 1419.90,
                'gross_amount' => 1419.90,
                'line_status' => 'Shipped',
                'raw_payload' => [
                    'orderItemStatus' => 3,
                    'orderItemStatusName' => 'Siparişiniz Alındı',
                    'estimatedShippingDate' => '2026-04-24T19:00:00+03:00',
                    'trackingNumber' => null,
                ],
            ]],
        ]]);

        $order = ChannelOrder::query()->firstOrFail();
        $package = ChannelOrderPackage::query()->firstOrFail();
        $item = ChannelOrderItem::query()->firstOrFail();

        $this->assertSame('approved', $order->order_status);
        $this->assertSame('approved', $package->package_status);
        $this->assertSame('approved', $item->line_status);
        $this->assertNull($package->shipped_at);
        $this->assertSame('2026-04-24T19:00:00+03:00', data_get($package->raw_payload, 'estimatedShippingDate'));
        $this->assertSame(
            '2026-04-24 19:00:00',
            (new MarketplaceOrders())->packageShipmentAt($package, 'pazarama')?->format('Y-m-d H:i:s')
        );
    }

    public function test_it_keeps_pazarama_shipped_payloads_when_tracking_exists(): void
    {
        $store = $this->createPazaramaStore('2');

        app(MarketplaceOrderSyncService::class)->sync($store, [[
            'order' => [
                'external_order_id' => '629342353',
                'order_number' => '629342353',
                'order_status' => 'Shipped',
                'ordered_at' => '2026-04-18 23:23:00',
                'raw_payload' => [
                    'orderStatus' => 5,
                ],
            ],
            'package' => [
                'external_package_id' => '20549',
                'package_number' => '20549',
                'package_status' => 'Shipped',
                'cargo_company' => 'Surat',
                'cargo_tracking_number' => 'TRK-20549',
                'shipped_at' => '2026-04-22 19:00:00',
                'raw_payload' => [
                    'trackingNumber' => 'TRK-20549',
                ],
            ],
            'items' => [[
                'external_line_id' => 'LINE-629342353-1',
                'product_name' => 'Test Product',
                'quantity' => 1,
                'unit_price' => 2224.90,
                'gross_amount' => 2224.90,
                'line_status' => 'Shipped',
                'raw_payload' => [
                    'orderItemStatusName' => 'Kargoya Verildi',
                    'trackingNumber' => 'TRK-20549',
                ],
            ]],
        ]]);

        $order = ChannelOrder::query()->firstOrFail();
        $package = ChannelOrderPackage::query()->firstOrFail();
        $item = ChannelOrderItem::query()->firstOrFail();

        $this->assertSame('shipped', $order->order_status);
        $this->assertSame('shipped', $package->package_status);
        $this->assertSame('shipped', $item->line_status);
        $this->assertNotNull($package->shipped_at);
        $this->assertSame('2026-04-22 19:00:00', $package->shipped_at?->format('Y-m-d H:i:s'));
    }

    protected function createPazaramaStore(string $suffix = '1'): MarketplaceStore
    {
        $user = User::factory()->create();

        $entity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Pazarama Test Entity ' . $suffix,
            'tax_number' => '99' . str_pad($suffix, 8, '0', STR_PAD_LEFT),
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        return MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $entity->id,
            'marketplace' => 'pazarama',
            'store_name' => 'Pazarama Test Store ' . $suffix,
            'store_code' => 'PZR-' . $suffix,
            'seller_id' => 'PZR-SELLER-' . $suffix,
            'status' => 'connected',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);
    }
}
