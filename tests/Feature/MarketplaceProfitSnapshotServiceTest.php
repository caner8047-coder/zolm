<?php

namespace Tests\Feature;

use App\Models\ChannelOrder;
use App\Models\ChannelOrderItem;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\OrderFinancialEvent;
use App\Models\OrderProfitSnapshot;
use App\Models\User;
use App\Services\Marketplace\MarketplaceProfitSnapshotService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketplaceProfitSnapshotServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql.host', 'mysql');
        config()->set('database.connections.mysql.port', '3306');
        config()->set('database.connections.mysql.database', 'zolm');
        config()->set('database.connections.mysql.username', 'sail');
        config()->set('database.connections.mysql.password', 'password');
        DB::purge('mysql');
        DB::reconnect('mysql');
        DB::setDefaultConnection('mysql');
    }

    public function test_it_uses_shopify_sale_refund_and_fee_events_in_confirmed_profit(): void
    {
        [$store, $order] = $this->createOrderGraph('shopify');

        OrderFinancialEvent::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'channel_order_id' => $order->id,
            'event_source' => 'shopify_transaction',
            'event_type' => 'sale',
            'external_event_id' => 'txn-sale-1',
            'event_date' => now(),
            'settlement_date' => now(),
            'amount' => 2300,
            'currency' => 'TRY',
            'direction' => 'credit',
            'status' => 'success',
        ]);

        OrderFinancialEvent::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'channel_order_id' => $order->id,
            'event_source' => 'shopify_fee',
            'event_type' => 'fee',
            'external_event_id' => 'txn-fee-1',
            'event_date' => now(),
            'settlement_date' => now(),
            'amount' => 69,
            'currency' => 'TRY',
            'direction' => 'debit',
            'status' => 'success',
        ]);

        OrderFinancialEvent::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'channel_order_id' => $order->id,
            'event_source' => 'shopify_transaction',
            'event_type' => 'refund',
            'external_event_id' => 'txn-refund-1',
            'event_date' => now(),
            'settlement_date' => now(),
            'amount' => 300,
            'currency' => 'TRY',
            'direction' => 'debit',
            'status' => 'success',
        ]);

        app(MarketplaceProfitSnapshotService::class)->recalculateForOrders($store, [$order->id]);

        $snapshot = OrderProfitSnapshot::query()
            ->where('store_id', $store->id)
            ->where('channel_order_id', $order->id)
            ->whereNull('channel_order_item_id')
            ->firstOrFail();

        $this->assertSame('confirmed', $snapshot->profit_state);
        $this->assertSame('2231.00', (string) $snapshot->estimated_profit);
        $this->assertSame('1931.00', (string) $snapshot->net_receivable);
        $this->assertSame('69.00', (string) $snapshot->service_fee_total);
        $this->assertSame('1931.00', (string) $snapshot->confirmed_profit);
    }

    public function test_authorization_alone_does_not_mark_order_as_confirmed(): void
    {
        [$store, $order] = $this->createOrderGraph('shopify');

        OrderFinancialEvent::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'channel_order_id' => $order->id,
            'event_source' => 'shopify_transaction',
            'event_type' => 'authorization',
            'external_event_id' => 'txn-auth-1',
            'event_date' => now(),
            'settlement_date' => now(),
            'amount' => 2300,
            'currency' => 'TRY',
            'direction' => 'credit',
            'status' => 'pending',
        ]);

        app(MarketplaceProfitSnapshotService::class)->recalculateForOrders($store, [$order->id]);

        $snapshot = OrderProfitSnapshot::query()
            ->where('store_id', $store->id)
            ->where('channel_order_id', $order->id)
            ->whereNull('channel_order_item_id')
            ->firstOrFail();

        $this->assertSame('estimated', $snapshot->profit_state);
        $this->assertSame('2231.00', (string) $snapshot->estimated_profit);
        $this->assertSame('2231.00', (string) $snapshot->confirmed_profit);
    }

    public function test_koctas_snapshot_uses_agreed_commission_rate(): void
    {
        config()->set('marketplace.koctas.commission_rate', 15);

        [$store, $order] = $this->createOrderGraph('koctas');

        app(MarketplaceProfitSnapshotService::class)->recalculateForOrders($store, [$order->id]);

        $snapshot = OrderProfitSnapshot::query()
            ->where('store_id', $store->id)
            ->where('channel_order_id', $order->id)
            ->whereNull('channel_order_item_id')
            ->firstOrFail();

        $this->assertSame('345.00', (string) $snapshot->commission_total);
        $this->assertSame('1955.00', (string) $snapshot->estimated_profit);
        $this->assertSame('1955.00', (string) $snapshot->confirmed_profit);
    }

    /**
     * @return array{0: MarketplaceStore, 1: ChannelOrder}
     */
    protected function createOrderGraph(string $marketplace): array
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $entity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Snapshot Ltd.',
            'tax_number' => '8'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $entity->id,
            'marketplace' => $marketplace,
            'store_name' => strtoupper($marketplace).' Snapshot',
            'store_code' => strtoupper($marketplace).'-'.$suffix,
            'seller_id' => 'S'.$suffix,
            'status' => 'active',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $order = ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $entity->id,
            'external_order_id' => 'ORD-'.$suffix,
            'order_number' => '#'.$suffix,
            'order_status' => 'approved',
            'customer_name' => 'Test Kullanici',
            'ordered_at' => now(),
        ]);

        ChannelOrderItem::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $order->id,
            'external_line_id' => 'LINE-'.$suffix,
            'stock_code' => 'STK-'.$suffix,
            'product_name' => 'Snapshot Urunu',
            'quantity' => 1,
            'unit_price' => 2300,
            'gross_amount' => 2300,
            'billable_amount' => 2300,
            'commission_rate' => 3,
            'is_matched' => false,
        ]);

        return [$store, $order];
    }
}
