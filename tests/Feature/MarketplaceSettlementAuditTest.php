<?php

namespace Tests\Feature;

use App\Livewire\MarketplaceSettlementAudit;
use App\Models\CargoInvoiceLine;
use App\Models\ChannelOrder;
use App\Models\ChannelOrderItem;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\OrderFinancialEvent;
use App\Models\OrderProfitSnapshot;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Marketplace\MarketplaceSettlementAuditQueryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class MarketplaceSettlementAuditTest extends TestCase
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
        config()->set('marketplace.features.settlement_audit_enabled', true);
        config()->set('cargo.tolerances.desi', 2.0);
        DB::purge('mysql');
        DB::reconnect('mysql');
        DB::setDefaultConnection('mysql');
    }

    public function test_audit_detects_commission_cargo_desi_and_missing_shipment_risks(): void
    {
        [$user, $differenceOrder, $waitingOrder] = $this->createAuditGraph();

        $audit = app(MarketplaceSettlementAuditQueryService::class)->audit($user->id, [
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->addDay()->toDateString(),
        ]);

        $queue = collect($audit['queue'])->keyBy('order_number');
        $difference = $queue[$differenceOrder->order_number];
        $waiting = $queue[$waitingOrder->order_number];

        $this->assertSame(2, $audit['summary']['review_order_count']);
        $this->assertSame(1, $audit['summary']['critical_order_count']);
        $this->assertSame(100.0, $audit['summary']['potential_recovery']);
        $this->assertSame(1, $audit['summary']['commission_difference_count']);
        $this->assertSame(1, $audit['summary']['cargo_difference_count']);
        $this->assertSame(1, $audit['summary']['desi_difference_count']);
        $this->assertSame(1, $audit['summary']['waiting_settlement_count']);
        $this->assertSame(1, $audit['summary']['missing_shipment_count']);

        $this->assertSame(40.0, $difference['commission_delta']);
        $this->assertSame(40.0, $difference['cargo_delta']);
        $this->assertSame(3.0, $difference['desi_delta']);
        $this->assertSame(100.0, $difference['potential_recovery']);
        $this->assertSame('critical', $difference['severity']);
        $this->assertContains('commission_difference', collect($difference['risks'])->pluck('key'));
        $this->assertContains('cargo_amount_difference', collect($difference['risks'])->pluck('key'));
        $this->assertContains('desi_difference', collect($difference['risks'])->pluck('key'));
        $this->assertContains('penalty_other_invoice', collect($difference['risks'])->pluck('key'));
        $this->assertContains('waiting_settlement', collect($waiting['risks'])->pluck('key'));
        $this->assertContains('missing_shipment', collect($waiting['risks'])->pluck('key'));
        $this->assertFalse($queue->contains(fn (array $row) => mb_strtolower($row['order_status']) === 'cancelled'));
    }

    public function test_risk_filter_keeps_orders_matching_selected_control(): void
    {
        [$user, $differenceOrder] = $this->createAuditGraph();

        $audit = app(MarketplaceSettlementAuditQueryService::class)->audit($user->id, [
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->addDay()->toDateString(),
            'risk_type' => 'desi_difference',
        ]);

        $this->assertSame(1, $audit['summary']['review_order_count']);
        $this->assertSame($differenceOrder->order_number, $audit['queue'][0]['order_number']);
    }

    public function test_livewire_screen_renders_filters_and_exports_appeal_workbook(): void
    {
        [$user, $differenceOrder, $waitingOrder] = $this->createAuditGraph();
        $this->actingAs($user);

        $component = Livewire::test(MarketplaceSettlementAudit::class)
            ->assertSee('Hakediş, Desi ve Kesinti Kontrolü')
            ->assertSee('Risk yoğunluğu')
            ->assertSee('Kontrol ve itiraz kuyruğu')
            ->assertSee($differenceOrder->order_number)
            ->assertSee($waitingOrder->order_number)
            ->set('riskTypeFilter', 'desi_difference')
            ->assertSee($differenceOrder->order_number)
            ->assertDontSee($waitingOrder->order_number)
            ->call('toggleColumn', 'desi')
            ->assertHasNoErrors();

        $response = $component->instance()->exportAppealPackage();
        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertStringContainsString('hakedis-desi-kesinti-itiraz-paketi', (string) $response->headers->get('content-disposition'));

        $spreadsheet = IOFactory::load($response->getFile()->getPathname());

        try {
            $this->assertNotNull($spreadsheet->getSheetByName('Kontrol Ozeti'));
            $this->assertNotNull($spreadsheet->getSheetByName('Risk Dagilimi'));
            $this->assertNotNull($spreadsheet->getSheetByName('Itiraz Detayi'));
            $this->assertNotNull($spreadsheet->getSheetByName('Toleranslar'));

            $detailText = collect($spreadsheet->getSheetByName('Itiraz Detayi')->toArray())
                ->flatten()
                ->filter(fn ($value) => $value !== null && $value !== '')
                ->implode('|');

            $this->assertStringContainsString($differenceOrder->order_number, $detailText);
            $this->assertStringContainsString('Desi farkı', $detailText);
        } finally {
            $spreadsheet->disconnectWorksheets();
            @unlink($response->getFile()->getPathname());
        }
    }

    public function test_route_is_protected_by_feature_flag(): void
    {
        [$user] = $this->createAuditGraph();
        $this->actingAs($user);

        config()->set('marketplace.features.settlement_audit_enabled', false);
        $this->get('/marketplace-settlement-audit')->assertNotFound();

        config()->set('marketplace.features.settlement_audit_enabled', true);
        $this->get('/marketplace-settlement-audit')
            ->assertOk()
            ->assertSee('Hakediş, Desi ve Kesinti Kontrolü');
    }

    /**
     * @return array{0: User, 1: ChannelOrder, 2: ChannelOrder}
     */
    protected function createAuditGraph(): array
    {
        $suffix = (string) random_int(100000, 999999);
        $user = User::factory()->create([
            'email' => 'settlement-' . Str::uuid() . '@example.test',
            'role' => 'admin',
            'is_active' => true,
        ]);
        $entity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Hakediş Kontrol Ltd.',
            'tax_number' => '8' . $suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);
        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $entity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'Hakediş Test Mağazası',
            'store_code' => 'SET-' . $suffix,
            'seller_id' => 'SET-' . $suffix,
            'status' => 'active',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $differenceOrder = $this->createOrder($store, $entity, 'SET-DIFF-' . $suffix, 1000, 10);

        OrderProfitSnapshot::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $differenceOrder->id,
            'profit_state' => 'confirmed',
            'gross_revenue' => 1000,
            'net_receivable' => 790,
            'commission_total' => 140,
            'cargo_total' => 50,
            'service_fee_total' => 20,
            'withholding_total' => 0,
            'estimated_profit' => 400,
            'confirmed_profit' => 250,
            'margin_percent' => 1.25,
            'calculated_at' => now(),
            'version' => 1,
        ]);

        foreach ([
            ['type' => 'seller_revenue', 'direction' => 'credit', 'amount' => 1000],
            ['type' => 'commission', 'direction' => 'debit', 'amount' => 140],
            ['type' => 'cargo', 'direction' => 'debit', 'amount' => 50],
            ['type' => 'penalty', 'direction' => 'debit', 'amount' => 20],
        ] as $index => $event) {
            OrderFinancialEvent::query()->create([
                'store_id' => $store->id,
                'legal_entity_id' => $entity->id,
                'channel_order_id' => $differenceOrder->id,
                'event_source' => 'settlement-test',
                'event_type' => $event['type'],
                'external_event_id' => $differenceOrder->order_number . '-' . $index,
                'event_date' => now(),
                'settlement_date' => now(),
                'amount' => $event['amount'],
                'currency' => 'TRY',
                'direction' => $event['direction'],
                'status' => 'settled',
            ]);
        }

        $shipment = Shipment::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $entity->id,
            'store_id' => $store->id,
            'channel_order_id' => $differenceOrder->id,
            'shipment_no' => 'SHIP-' . $suffix,
            'source_type' => 'test',
            'direction' => 'outgoing',
            'flow_type' => 'order',
            'carrier_code' => 'surat',
            'carrier_name' => 'Sürat Kargo',
            'order_number' => $differenceOrder->order_number,
            'tracking_number' => 'TRK-' . $suffix,
            'status' => 'delivered',
            'parcel_count' => 1,
            'total_desi' => 5,
            'expected_cost' => 50,
            'actual_cost' => 90,
            'invoice_cost' => 90,
            'cost_delta' => 40,
            'currency' => 'TRY',
            'delivered_at' => now(),
        ]);

        CargoInvoiceLine::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $entity->id,
            'shipment_id' => $shipment->id,
            'carrier_code' => 'surat',
            'invoice_number' => 'INV-' . $suffix,
            'invoice_date' => now()->toDateString(),
            'tracking_number' => $shipment->tracking_number,
            'order_reference' => $differenceOrder->order_number,
            'parcel_count' => 1,
            'desi' => 8,
            'amount' => 75,
            'vat_amount' => 15,
            'total_amount' => 90,
            'currency' => 'TRY',
            'status' => 'pending',
            'is_reconciled' => false,
        ]);

        $waitingOrder = $this->createOrder($store, $entity, 'SET-WAIT-' . $suffix, 400, 12);
        OrderProfitSnapshot::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $waitingOrder->id,
            'profit_state' => 'estimated',
            'gross_revenue' => 400,
            'net_receivable' => 352,
            'commission_total' => 48,
            'estimated_profit' => 150,
            'confirmed_profit' => 0,
            'margin_percent' => 1.6,
            'calculated_at' => now(),
            'version' => 1,
        ]);

        $cancelledOrder = $this->createOrder($store, $entity, 'SET-CANCEL-' . $suffix, 250, 10);
        $cancelledOrder->update([
            'order_status' => 'Cancelled',
            'cancelled_at' => now(),
        ]);

        return [$user, $differenceOrder, $waitingOrder];
    }

    protected function createOrder(
        MarketplaceStore $store,
        LegalEntity $entity,
        string $orderNumber,
        float $grossAmount,
        float $commissionRate
    ): ChannelOrder {
        $order = ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $entity->id,
            'external_order_id' => $orderNumber,
            'order_number' => $orderNumber,
            'order_status' => 'delivered',
            'ordered_at' => now(),
            'delivered_at' => now(),
        ]);

        ChannelOrderItem::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $order->id,
            'external_line_id' => $orderNumber . '-1',
            'stock_code' => $orderNumber . '-SKU',
            'product_name' => 'Hakediş Test Ürünü',
            'quantity' => 1,
            'unit_price' => $grossAmount,
            'gross_amount' => $grossAmount,
            'billable_amount' => $grossAmount,
            'commission_rate' => $commissionRate,
            'line_status' => 'delivered',
            'is_matched' => false,
        ]);

        return $order;
    }
}
