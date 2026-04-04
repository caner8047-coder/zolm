<?php

namespace Tests\Feature;

use App\Livewire\MarketplaceFinance;
use App\Models\ChannelOrder;
use App\Models\ChannelOrderItem;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\OrderFinancialEvent;
use App\Models\OrderProfitSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class MarketplaceFinanceReconciliationTest extends TestCase
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

    public function test_it_filters_orders_by_reconciliation_state(): void
    {
        [$user, $materialOrder, $alignedOrder, $waitingOrder, $snapshotMissingOrder] = $this->createFinanceGraph();

        $this->actingAs($user);

        Livewire::test(MarketplaceFinance::class)
            ->set('deltaStateFilter', 'material')
            ->assertSee($materialOrder->order_number)
            ->assertDontSee($alignedOrder->order_number)
            ->assertDontSee($waitingOrder->order_number)
            ->assertDontSee($snapshotMissingOrder->order_number);

        Livewire::test(MarketplaceFinance::class)
            ->set('deltaStateFilter', 'snapshot_missing')
            ->assertSee($snapshotMissingOrder->order_number)
            ->assertDontSee($materialOrder->order_number)
            ->assertDontSee($alignedOrder->order_number)
            ->assertDontSee($waitingOrder->order_number);
    }

    public function test_summary_export_contains_reconciliation_columns(): void
    {
        [$user, $materialOrder] = $this->createFinanceGraph();

        $this->actingAs($user);

        $component = app(MarketplaceFinance::class);
        $component->mount();

        $response = $component->exportSummaryCsv();

        $this->assertInstanceOf(StreamedResponse::class, $response);

        ob_start();
        $response->sendContent();
        $content = (string) ob_get_clean();

        $this->assertStringContainsString('Tahmini Kâr', $content);
        $this->assertStringContainsString('Kesin Kâr', $content);
        $this->assertStringContainsString('Kâr Farkı', $content);
        $this->assertStringContainsString('Kesinti Farkı', $content);
        $this->assertStringContainsString('Mutabakat Durumu', $content);
        $this->assertStringContainsString($materialOrder->order_number, $content);
        $this->assertStringContainsString('Materyal Fark', $content);
    }

    /**
     * @return array{0: User, 1: ChannelOrder, 2: ChannelOrder, 3: ChannelOrder, 4: ChannelOrder}
     */
    protected function createFinanceGraph(): array
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Finance Ltd.',
            'tax_number' => '9' . $suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'ZEM FINANCE',
            'store_code' => 'ZEM-FIN-' . $suffix,
            'seller_id' => 'FIN-' . $suffix,
            'status' => 'active',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $materialOrder = $this->createOrderWithFinance(
            $store,
            $legalEntity,
            'FIN-MATERIAL-' . $suffix,
            1,
            1000,
            10,
            [
                'profit_state' => 'confirmed',
                'gross_revenue' => 1000,
                'net_receivable' => 830,
                'commission_total' => 100,
                'cargo_total' => 40,
                'service_fee_total' => 20,
                'withholding_total' => 10,
                'estimated_profit' => 400,
                'confirmed_profit' => 250,
                'margin_percent' => 25,
            ],
            [
                ['event_type' => 'seller_revenue', 'direction' => 'credit', 'amount' => 1000],
                ['event_type' => 'commission', 'direction' => 'debit', 'amount' => 100],
                ['event_type' => 'cargo', 'direction' => 'debit', 'amount' => 40],
                ['event_type' => 'service_fee', 'direction' => 'debit', 'amount' => 20],
                ['event_type' => 'withholding', 'direction' => 'debit', 'amount' => 10],
            ]
        );

        $alignedOrder = $this->createOrderWithFinance(
            $store,
            $legalEntity,
            'FIN-ALIGNED-' . $suffix,
            1,
            500,
            10,
            [
                'profit_state' => 'confirmed',
                'gross_revenue' => 500,
                'net_receivable' => 450,
                'commission_total' => 50,
                'cargo_total' => 0,
                'service_fee_total' => 0,
                'withholding_total' => 0,
                'estimated_profit' => 200,
                'confirmed_profit' => 195,
                'margin_percent' => 39,
            ],
            [
                ['event_type' => 'seller_revenue', 'direction' => 'credit', 'amount' => 500],
                ['event_type' => 'commission', 'direction' => 'debit', 'amount' => 50],
            ]
        );

        $waitingOrder = $this->createOrderWithFinance(
            $store,
            $legalEntity,
            'FIN-WAITING-' . $suffix,
            1,
            300,
            10,
            [
                'profit_state' => 'estimated',
                'gross_revenue' => 300,
                'net_receivable' => 0,
                'commission_total' => 30,
                'cargo_total' => 0,
                'service_fee_total' => 0,
                'withholding_total' => 0,
                'estimated_profit' => 120,
                'confirmed_profit' => 0,
                'margin_percent' => 40,
            ],
            []
        );

        $snapshotMissingOrder = $this->createOrderWithFinance(
            $store,
            $legalEntity,
            'FIN-SNAPSHOT-' . $suffix,
            1,
            700,
            12,
            null,
            [
                ['event_type' => 'seller_revenue', 'direction' => 'credit', 'amount' => 700],
                ['event_type' => 'commission', 'direction' => 'debit', 'amount' => 84],
            ]
        );

        return [$user, $materialOrder, $alignedOrder, $waitingOrder, $snapshotMissingOrder];
    }

    protected function createOrderWithFinance(
        MarketplaceStore $store,
        LegalEntity $legalEntity,
        string $orderNumber,
        int $quantity,
        float $grossAmount,
        float $commissionRate,
        ?array $snapshot,
        array $events
    ): ChannelOrder {
        $order = ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $legalEntity->id,
            'external_order_id' => $orderNumber,
            'order_number' => $orderNumber,
            'order_status' => 'Delivered',
            'customer_name' => 'Finans Test',
            'ordered_at' => now(),
        ]);

        ChannelOrderItem::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $order->id,
            'external_line_id' => $orderNumber . '-LINE',
            'stock_code' => 'SKU-' . $orderNumber,
            'barcode' => 'BAR-' . $orderNumber,
            'product_name' => 'Finans Test Ürünü',
            'quantity' => $quantity,
            'unit_price' => $grossAmount / max($quantity, 1),
            'gross_amount' => $grossAmount,
            'billable_amount' => $grossAmount,
            'commission_rate' => $commissionRate,
            'line_status' => 'Delivered',
            'is_matched' => true,
            'match_source' => 'manual',
        ]);

        if ($snapshot !== null) {
            OrderProfitSnapshot::query()->create(array_merge([
                'store_id' => $store->id,
                'channel_order_id' => $order->id,
                'channel_order_item_id' => null,
                'packaging_cost' => 0,
                'own_cargo_cost' => 0,
                'cogs_cost' => 0,
                'return_effect' => 0,
                'vat_effect' => 0,
                'calculated_at' => now(),
                'version' => 1,
            ], $snapshot));
        }

        foreach ($events as $index => $event) {
            OrderFinancialEvent::query()->create([
                'store_id' => $store->id,
                'legal_entity_id' => $legalEntity->id,
                'channel_order_id' => $order->id,
                'event_source' => 'sync',
                'event_type' => $event['event_type'],
                'external_event_id' => $orderNumber . '-EVT-' . $index,
                'reference_number' => $orderNumber . '-REF-' . $index,
                'event_date' => now(),
                'settlement_date' => now(),
                'amount' => $event['amount'],
                'currency' => 'TRY',
                'direction' => $event['direction'],
                'status' => 'settled',
            ]);
        }

        return $order;
    }
}
