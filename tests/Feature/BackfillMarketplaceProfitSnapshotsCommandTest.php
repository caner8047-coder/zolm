<?php

namespace Tests\Feature;

use App\Models\ChannelOrder;
use App\Models\ChannelOrderItem;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\OrderProfitSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BackfillMarketplaceProfitSnapshotsCommandTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql.host', 'mysql');
        config()->set('database.connections.mysql.port', '3306');
        config()->set('database.connections.mysql.database', $this->mysqlTestDatabaseName());
        config()->set('database.connections.mysql.username', 'sail');
        config()->set('database.connections.mysql.password', 'password');
        DB::purge('mysql');
        DB::reconnect('mysql');
        DB::setDefaultConnection('mysql');
    }

    public function test_profit_snapshot_backfill_dry_run_does_not_write_snapshots(): void
    {
        [$store, $order] = $this->createGraph();

        $this->artisan('marketplace:backfill-profit-snapshots', [
            '--store' => [$store->id],
            '--dry-run' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseMissing('order_profit_snapshots', [
            'channel_order_id' => $order->id,
            'channel_order_item_id' => null,
        ]);
    }

    public function test_profit_snapshot_backfill_missing_option_leaves_existing_snapshots_untouched(): void
    {
        [$store, $missingOrder, $existingOrder] = $this->createGraph(withSecondOrder: true);

        OrderProfitSnapshot::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $existingOrder->id,
            'channel_order_item_id' => null,
            'profit_state' => 'estimated',
            'gross_revenue' => 500,
            'net_receivable' => 450,
            'commission_total' => 50,
            'cargo_total' => 0,
            'service_fee_total' => 0,
            'withholding_total' => 0,
            'packaging_cost' => 0,
            'own_cargo_cost' => 0,
            'cogs_cost' => 0,
            'return_effect' => 0,
            'vat_effect' => 0,
            'estimated_profit' => 777,
            'confirmed_profit' => 777,
            'margin_percent' => 0,
            'calculated_at' => now()->subDay(),
            'version' => 5,
        ]);

        $this->artisan('marketplace:backfill-profit-snapshots', [
            '--store' => [$store->id],
            '--missing' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('order_profit_snapshots', [
            'channel_order_id' => $missingOrder->id,
            'channel_order_item_id' => null,
            'gross_revenue' => 1000,
        ]);

        $this->assertDatabaseHas('order_profit_snapshots', [
            'channel_order_id' => $existingOrder->id,
            'channel_order_item_id' => null,
            'estimated_profit' => 777,
            'version' => 5,
        ]);
    }

    public function test_profit_snapshot_backfill_order_filter_only_recalculates_matching_order(): void
    {
        [$store, $targetOrder, $otherOrder] = $this->createGraph(withSecondOrder: true);

        $this->artisan('marketplace:backfill-profit-snapshots', [
            '--store' => [$store->id],
            '--order' => $targetOrder->order_number,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('order_profit_snapshots', [
            'channel_order_id' => $targetOrder->id,
            'channel_order_item_id' => null,
        ]);

        $this->assertDatabaseMissing('order_profit_snapshots', [
            'channel_order_id' => $otherOrder->id,
            'channel_order_item_id' => null,
        ]);
    }

    public function test_profit_snapshot_backfill_requires_scope_or_all_flag(): void
    {
        $this->artisan('marketplace:backfill-profit-snapshots')
            ->assertExitCode(1);
    }

    /**
     * @return array{0: MarketplaceStore, 1: ChannelOrder, 2?: ChannelOrder}
     */
    protected function createGraph(bool $withSecondOrder = false): array
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $entity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Snapshot Backfill Ltd.',
            'tax_number' => '6'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $entity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'Snapshot Backfill',
            'store_code' => 'SNAP-BACK-'.$suffix,
            'seller_id' => 'SNAP-BACK-'.$suffix,
            'status' => 'active',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $firstOrder = $this->createOrder($store, $entity, 'SNAP-BACK-ONE-'.$suffix, 1000, now()->subDay());

        if (! $withSecondOrder) {
            return [$store, $firstOrder];
        }

        $secondOrder = $this->createOrder($store, $entity, 'SNAP-BACK-TWO-'.$suffix, 500, now()->subDays(2));

        return [$store, $firstOrder, $secondOrder];
    }

    protected function createOrder(
        MarketplaceStore $store,
        LegalEntity $entity,
        string $orderNumber,
        float $grossAmount,
        mixed $orderedAt,
    ): ChannelOrder {
        $order = ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $entity->id,
            'external_order_id' => $orderNumber,
            'order_number' => $orderNumber,
            'order_status' => 'Delivered',
            'customer_name' => 'Snapshot Backfill Test',
            'ordered_at' => $orderedAt,
        ]);

        ChannelOrderItem::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $order->id,
            'external_line_id' => $orderNumber.'-LINE',
            'stock_code' => 'SKU-'.$orderNumber,
            'barcode' => 'BAR-'.$orderNumber,
            'product_name' => 'Snapshot Backfill Ürünü',
            'quantity' => 1,
            'unit_price' => $grossAmount,
            'gross_amount' => $grossAmount,
            'billable_amount' => $grossAmount,
            'commission_rate' => 10,
            'line_status' => 'Delivered',
            'is_matched' => false,
        ]);

        return $order;
    }
}
