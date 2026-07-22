<?php

namespace Tests\Feature;

use App\Models\ChannelOrder;
use App\Models\ChannelOrderItem;
use App\Models\IntegrationSyncProfile;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\MpOrder;
use App\Models\MpPeriod;
use App\Models\MpSettlement;
use App\Models\OrderFinancialEvent;
use App\Models\OrderProfitSnapshot;
use App\Models\User;
use App\Services\Marketplace\LegacyFinancialProjectionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LegacyFinancialProjectionServiceTest extends TestCase
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

    public function test_it_projects_legacy_financial_rows_into_order_financial_events_and_confirmed_snapshot(): void
    {
        [$store, $legacyOrder, $channelOrder, $channelItem] = $this->createGraph();

        $result = app(LegacyFinancialProjectionService::class)->projectStore($store, true);

        $this->assertSame(1, $result['projected_rows']);
        $this->assertSame(5, $result['created']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame([$channelOrder->id], $result['impacted_order_ids']);

        $events = OrderFinancialEvent::query()
            ->where('store_id', $store->id)
            ->where('channel_order_id', $channelOrder->id)
            ->orderBy('event_type')
            ->get();

        $this->assertCount(5, $events);
        $this->assertSame(
            ['cargo', 'commission', 'seller_revenue', 'service_fee', 'withholding'],
            $events->pluck('event_type')->all()
        );
        $this->assertSame(
            $channelItem->id,
            $events->firstWhere('event_type', 'seller_revenue')?->channel_order_item_id
        );
        $this->assertSame(
            '2260.00',
            (string) $events->firstWhere('event_type', 'seller_revenue')?->amount
        );

        $snapshot = OrderProfitSnapshot::query()
            ->where('store_id', $store->id)
            ->where('channel_order_id', $channelOrder->id)
            ->whereNull('channel_order_item_id')
            ->firstOrFail();

        $this->assertSame('confirmed', $snapshot->profit_state);
        $this->assertSame('1800.00', (string) $snapshot->net_receivable);
        $this->assertSame('180.00', (string) $snapshot->commission_total);
        $this->assertSame('45.00', (string) $snapshot->cargo_total);
        $this->assertSame('15.00', (string) $snapshot->service_fee_total);
        $this->assertSame('220.00', (string) $snapshot->withholding_total);
        $this->assertSame('1800.00', (string) $snapshot->confirmed_profit);

        $legacyOrder->refresh();
        $this->assertSame($store->id, $legacyOrder->store_id);
        $this->assertSame($store->legal_entity_id, $legacyOrder->legal_entity_id);
        $this->assertSame($store->marketplace, $legacyOrder->source_marketplace);
        $this->assertNotNull($legacyOrder->projected_at);
    }

    /**
     * @return array{0: MarketplaceStore, 1: MpOrder, 2: ChannelOrder, 3: ChannelOrderItem}
     */
    protected function createGraph(): array
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $entity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Legacy Finans Ltd.',
            'tax_number' => '6'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $entity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'LEGACY FINANS',
            'store_code' => 'LEG-FIN-'.$suffix,
            'seller_id' => 'LEG-FIN-'.$suffix,
            'status' => 'configured',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        IntegrationSyncProfile::query()->create(array_merge(
            ['store_id' => $store->id],
            IntegrationSyncProfile::defaultsForMarketplace('trendyol'),
        ));

        $period = MpPeriod::query()->create([
            'user_id' => $user->id,
            'seller_id' => $store->seller_id,
            'year' => (int) now()->year,
            'month' => (int) now()->month,
            'marketplace' => $store->marketplace,
            'status' => 'completed',
        ]);

        $channelOrder = ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $entity->id,
            'external_order_id' => 'LEGACY-FIN-'.$suffix,
            'order_number' => 'LEGACY-FIN-'.$suffix,
            'order_status' => 'Teslim Edildi',
            'customer_name' => 'Finans Musteri',
            'ordered_at' => now()->subDay(),
        ]);

        $channelItem = ChannelOrderItem::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $channelOrder->id,
            'external_line_id' => 'LINE-'.$suffix,
            'stock_code' => 'FIN-STOCK-'.$suffix,
            'barcode' => 'FIN-BARCODE-'.$suffix,
            'product_name' => 'Finans Ürün',
            'quantity' => 1,
            'unit_price' => 2200,
            'gross_amount' => 2200,
            'billable_amount' => 2200,
            'commission_rate' => 8.18,
            'is_matched' => false,
        ]);

        $order = MpOrder::query()->create([
            'period_id' => $period->id,
            'order_number' => $channelOrder->order_number,
            'barcode' => $channelItem->barcode,
            'stock_code' => $channelItem->stock_code,
            'product_name' => $channelItem->product_name,
            'quantity' => 1,
            'order_date' => now()->subDay(),
            'delivery_date' => now()->subHours(12),
            'payment_date' => now()->subHours(6)->toDateString(),
            'status' => 'Teslim Edildi',
            'gross_amount' => 2200,
            'commission_amount' => 180,
            'cargo_amount' => 45,
            'service_fee' => 15,
            'withholding_tax' => 220,
            'net_hakedis' => 1800,
        ]);

        MpSettlement::query()->create([
            'user_id' => $user->id,
            'period_id' => $period->id,
            'order_id' => $order->id,
            'transaction_type' => 'Satis',
            'order_number' => $order->order_number,
            'document_number' => 'DOC-'.$suffix,
            'transaction_date' => now()->subDay()->toDateString(),
            'settlement_date' => now()->subHours(6)->toDateString(),
            'due_date' => now()->subHours(3)->toDateString(),
            'seller_hakedis' => 1800,
            'ty_hakedis' => 2025,
            'total_amount' => 2200,
        ]);

        return [$store, $order, $channelOrder, $channelItem];
    }
}
