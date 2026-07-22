<?php

namespace Tests\Feature;

use App\Models\ChannelOrder;
use App\Models\ChannelOrderItem;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\MpProduct;
use App\Models\OrderFinancialEvent;
use App\Models\OrderProfitSnapshot;
use App\Models\User;
use App\Services\Marketplace\MarketplaceProfitSnapshotService;
use App\Services\Marketplace\MarketplaceVatEffectService;
use App\Services\MpSettingsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        config()->set('database.connections.mysql.database', $this->mysqlTestDatabaseName());
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

    public function test_confirmed_net_receivable_subtracts_commission_events(): void
    {
        [$store, $order] = $this->createOrderGraph('trendyol');

        foreach ([
            ['event_type' => 'seller_revenue', 'amount' => 2300, 'direction' => 'credit'],
            ['event_type' => 'commission', 'amount' => 230, 'direction' => 'debit'],
            ['event_type' => 'service_fee', 'amount' => 69, 'direction' => 'debit'],
            ['event_type' => 'withholding', 'amount' => 20, 'direction' => 'debit'],
        ] as $index => $event) {
            OrderFinancialEvent::query()->create([
                'store_id' => $store->id,
                'legal_entity_id' => $store->legal_entity_id,
                'channel_order_id' => $order->id,
                'event_source' => 'settlement',
                'event_type' => $event['event_type'],
                'external_event_id' => 'settlement-'.$index,
                'event_date' => now(),
                'settlement_date' => now(),
                'amount' => $event['amount'],
                'currency' => 'TRY',
                'direction' => $event['direction'],
                'status' => 'posted',
            ]);
        }

        app(MarketplaceProfitSnapshotService::class)->recalculateForOrders($store, [$order->id]);

        $snapshot = OrderProfitSnapshot::query()
            ->where('store_id', $store->id)
            ->where('channel_order_id', $order->id)
            ->whereNull('channel_order_item_id')
            ->firstOrFail();

        $this->assertSame('confirmed', $snapshot->profit_state);
        $this->assertSame('1981.00', (string) $snapshot->net_receivable);
        $this->assertSame('230.00', (string) $snapshot->commission_total);
        $this->assertSame('69.00', (string) $snapshot->service_fee_total);
        $this->assertSame('20.00', (string) $snapshot->withholding_total);
        $this->assertSame('0.00', (string) $snapshot->vat_effect);
        $this->assertSame('1981.00', (string) $snapshot->confirmed_profit);
    }

    public function test_extended_cost_categories_reduce_confirmed_profit_without_new_snapshot_columns(): void
    {
        [$store, $order] = $this->createOrderGraph('trendyol');

        foreach ([
            ['event_type' => 'seller_revenue', 'amount' => 2300, 'direction' => 'credit'],
            ['event_type' => 'commission', 'amount' => 230, 'direction' => 'debit'],
            ['event_type' => 'return_cargo', 'amount' => 50, 'direction' => 'debit'],
            ['event_type' => 'advertising', 'amount' => 40, 'direction' => 'debit'],
            ['event_type' => 'penalty', 'amount' => 10, 'direction' => 'debit'],
        ] as $index => $event) {
            OrderFinancialEvent::query()->create([
                'store_id' => $store->id,
                'legal_entity_id' => $store->legal_entity_id,
                'channel_order_id' => $order->id,
                'event_source' => 'settlement',
                'event_type' => $event['event_type'],
                'external_event_id' => 'extended-cost-'.$index,
                'event_date' => now(),
                'settlement_date' => now(),
                'amount' => $event['amount'],
                'currency' => 'TRY',
                'direction' => $event['direction'],
                'status' => 'posted',
            ]);
        }

        app(MarketplaceProfitSnapshotService::class)->recalculateForOrders($store, [$order->id]);

        $snapshot = OrderProfitSnapshot::query()
            ->where('store_id', $store->id)
            ->where('channel_order_id', $order->id)
            ->whereNull('channel_order_item_id')
            ->firstOrFail();

        $this->assertSame('confirmed', $snapshot->profit_state);
        $this->assertSame('230.00', (string) $snapshot->commission_total);
        $this->assertSame('50.00', (string) $snapshot->cargo_total);
        $this->assertSame('50.00', (string) $snapshot->service_fee_total);
        $this->assertSame('1970.00', (string) $snapshot->net_receivable);
        $this->assertSame('1970.00', (string) $snapshot->confirmed_profit);
    }

    public function test_kdv_enabled_applies_item_vat_rate_to_confirmed_profit(): void
    {
        [$store, $order] = $this->createOrderGraph('trendyol');
        $this->enableKdv($store);

        ChannelOrderItem::query()
            ->where('channel_order_id', $order->id)
            ->update(['vat_rate' => 20]);

        foreach ([
            ['event_type' => 'seller_revenue', 'amount' => 2300, 'direction' => 'credit'],
            ['event_type' => 'commission', 'amount' => 230, 'direction' => 'debit'],
            ['event_type' => 'cargo', 'amount' => 50, 'direction' => 'debit'],
        ] as $index => $event) {
            OrderFinancialEvent::query()->create([
                'store_id' => $store->id,
                'legal_entity_id' => $store->legal_entity_id,
                'channel_order_id' => $order->id,
                'event_source' => 'settlement',
                'event_type' => $event['event_type'],
                'external_event_id' => 'kdv-settlement-'.$index,
                'event_date' => now(),
                'settlement_date' => now(),
                'amount' => $event['amount'],
                'currency' => 'TRY',
                'direction' => $event['direction'],
                'status' => 'posted',
            ]);
        }

        app(MarketplaceProfitSnapshotService::class)->recalculateForOrders($store, [$order->id]);

        $snapshot = OrderProfitSnapshot::query()
            ->where('store_id', $store->id)
            ->where('channel_order_id', $order->id)
            ->whereNull('channel_order_item_id')
            ->firstOrFail();

        $this->assertSame('2020.00', (string) $snapshot->net_receivable);
        $this->assertSame('327.33', (string) $snapshot->vat_effect);
        $this->assertSame('1903.67', (string) $snapshot->estimated_profit);
        $this->assertSame('1692.67', (string) $snapshot->confirmed_profit);
    }

    public function test_kdv_enabled_falls_back_to_product_vat_rate_when_item_rate_is_missing(): void
    {
        [$store, $order] = $this->createOrderGraph('trendyol');
        $this->enableKdv($store);

        $product = MpProduct::query()->create([
            'user_id' => $store->user_id,
            'barcode' => 'KDV-PRODUCT-'.random_int(100000, 999999),
            'stock_code' => 'KDV-PRODUCT',
            'product_name' => 'KDV Fallback Ürünü',
            'cogs' => 0,
            'packaging_cost' => 0,
            'vat_rate' => 10,
            'cost_vat_rate' => 20,
            'commission_rate' => 3,
            'status' => 'active',
        ]);

        ChannelOrderItem::query()
            ->where('channel_order_id', $order->id)
            ->update([
                'mp_product_id' => $product->id,
                'is_matched' => true,
                'vat_rate' => null,
            ]);

        app(MarketplaceProfitSnapshotService::class)->recalculateForOrders($store, [$order->id]);

        $snapshot = OrderProfitSnapshot::query()
            ->where('store_id', $store->id)
            ->where('channel_order_id', $order->id)
            ->whereNull('channel_order_item_id')
            ->firstOrFail();

        $this->assertSame('estimated', $snapshot->profit_state);
        $this->assertSame('195.29', (string) $snapshot->vat_effect);
        $this->assertSame('2035.71', (string) $snapshot->estimated_profit);
        $this->assertSame('2035.71', (string) $snapshot->confirmed_profit);
    }

    public function test_vat_service_normalizes_zero_one_ten_and_twenty_percent_rates(): void
    {
        [$store, $order] = $this->createOrderGraph('trendyol');
        $this->enableKdv($store);

        $expected = [
            0 => 0.0,
            1 => 22.77,
            10 => 209.09,
            20 => 383.33,
        ];

        foreach ($expected as $rate => $salesVat) {
            ChannelOrderItem::query()
                ->where('channel_order_id', $order->id)
                ->update(['vat_rate' => $rate]);

            $items = ChannelOrderItem::query()
                ->with('product')
                ->where('channel_order_id', $order->id)
                ->get();

            $result = app(MarketplaceVatEffectService::class)->calculate(
                store: $store,
                order: $order,
                items: $items,
                grossRevenue: 2300,
                commissionTotal: 0,
                cargoTotal: 0,
            );

            $this->assertSame($salesVat, $result['sales_vat']);
            $this->assertSame(round($rate / 100, 4), $result['sales_vat_rate']);
        }
    }

    public function test_estimated_withholding_enabled_uses_theoretical_stopaj_when_event_is_missing(): void
    {
        [$store, $order] = $this->createOrderGraph('trendyol');
        $this->enableEstimatedWithholding($store);

        ChannelOrderItem::query()
            ->where('channel_order_id', $order->id)
            ->update(['vat_rate' => 10]);

        app(MarketplaceProfitSnapshotService::class)->recalculateForOrders($store, [$order->id]);

        $snapshot = OrderProfitSnapshot::query()
            ->where('store_id', $store->id)
            ->where('channel_order_id', $order->id)
            ->whereNull('channel_order_item_id')
            ->firstOrFail();

        $this->assertSame('estimated', $snapshot->profit_state);
        $this->assertSame('20.91', (string) $snapshot->withholding_total);
        $this->assertSame('2210.09', (string) $snapshot->net_receivable);
        $this->assertSame('2210.09', (string) $snapshot->estimated_profit);
        $this->assertSame('2210.09', (string) $snapshot->confirmed_profit);
    }

    public function test_actual_withholding_event_wins_over_theoretical_stopaj(): void
    {
        [$store, $order] = $this->createOrderGraph('trendyol');
        $this->enableEstimatedWithholding($store);

        ChannelOrderItem::query()
            ->where('channel_order_id', $order->id)
            ->update(['vat_rate' => 10]);

        foreach ([
            ['event_type' => 'seller_revenue', 'amount' => 2300, 'direction' => 'credit'],
            ['event_type' => 'commission', 'amount' => 230, 'direction' => 'debit'],
            ['event_type' => 'withholding', 'amount' => 15, 'direction' => 'debit'],
        ] as $index => $event) {
            OrderFinancialEvent::query()->create([
                'store_id' => $store->id,
                'legal_entity_id' => $store->legal_entity_id,
                'channel_order_id' => $order->id,
                'event_source' => 'settlement',
                'event_type' => $event['event_type'],
                'external_event_id' => 'stopaj-settlement-'.$index,
                'event_date' => now(),
                'settlement_date' => now(),
                'amount' => $event['amount'],
                'currency' => 'TRY',
                'direction' => $event['direction'],
                'status' => 'posted',
            ]);
        }

        app(MarketplaceProfitSnapshotService::class)->recalculateForOrders($store, [$order->id]);

        $snapshot = OrderProfitSnapshot::query()
            ->where('store_id', $store->id)
            ->where('channel_order_id', $order->id)
            ->whereNull('channel_order_item_id')
            ->firstOrFail();

        $this->assertSame('confirmed', $snapshot->profit_state);
        $this->assertSame('15.00', (string) $snapshot->withholding_total);
        $this->assertSame('2055.00', (string) $snapshot->net_receivable);
        $this->assertSame('2055.00', (string) $snapshot->confirmed_profit);
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

    public function test_pending_confirming_event_does_not_mark_order_as_confirmed(): void
    {
        [$store, $order] = $this->createOrderGraph('shopify');

        OrderFinancialEvent::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'channel_order_id' => $order->id,
            'event_source' => 'shopify_transaction',
            'event_type' => 'sale',
            'external_event_id' => 'txn-sale-pending-1',
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
        $this->assertSame('2231.00', (string) $snapshot->net_receivable);
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
        $suffix = (string) random_int(100000, 999999);
        $user = User::factory()->create([
            'email' => 'snapshot-'.Str::uuid().'@example.test',
        ]);

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

    protected function enableKdv(MarketplaceStore $store): void
    {
        (new MpSettingsService((int) $store->user_id))->setMany([
            'tax.kdv_hesaplama_aktif' => true,
            'tax.default_product_vat_rate' => 0.10,
            'tax.expense_vat_rate' => 0.20,
        ]);
    }

    protected function enableEstimatedWithholding(MarketplaceStore $store): void
    {
        (new MpSettingsService((int) $store->user_id))->setMany([
            'tax.estimated_withholding_enabled' => true,
            'tax.stopaj_rate' => 0.01,
            'tax.default_product_vat_rate' => 0.10,
        ]);
    }
}
