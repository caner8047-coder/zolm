<?php

namespace Tests\Feature;

use App\Models\Party;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Accounting\OutstandingInvoiceService;
use App\Services\Accounting\ReportService;
use App\Services\Accounting\StockService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Party $party;
    private ReportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['is_active' => true]);
        $this->party = Party::factory()->create(['user_id' => $this->user->id]);

        $seeder = new ChartOfAccountsSeeder();
        $seeder->runForUser($this->user->id);

        $this->service = app(ReportService::class);
    }

    public function test_aged_receivables_categorization(): void
    {
        $invoice = app(OutstandingInvoiceService::class);

        // 1. Not Due (vadesi gelmemiş)
        $invoice->createReceivable([
            'user_id'       => $this->user->id,
            'party_id'      => $this->party->id,
            'amount'        => 500.00,
            'document_date' => now()->toDateString(),
            'due_date'      => now()->addDays(5)->toDateString(),
        ]);

        // 2. Overdue: 15 days ago (aged_0_30)
        $invoice->createReceivable([
            'user_id'       => $this->user->id,
            'party_id'      => $this->party->id,
            'amount'        => 1000.00,
            'document_date' => now()->subDays(20)->toDateString(),
            'due_date'      => now()->subDays(15)->toDateString(),
        ]);

        // 3. Overdue: 45 days ago (aged_31_60)
        $invoice->createReceivable([
            'user_id'       => $this->user->id,
            'party_id'      => $this->party->id,
            'amount'        => 2000.00,
            'document_date' => now()->subDays(50)->toDateString(),
            'due_date'      => now()->subDays(45)->toDateString(),
        ]);

        $report = $this->service->getAgedReceivables($this->user->id);

        $this->assertEquals(3500.00, $report['total']);
        $this->assertEquals(500.00, $report['not_due']);
        $this->assertEquals(1000.00, $report['aged_0_30']);
        $this->assertEquals(2000.00, $report['aged_31_60']);
        $this->assertEquals(0.00, $report['aged_61_90']);
    }

    public function test_cash_flow_forecasting(): void
    {
        $invoice = app(OutstandingInvoiceService::class);

        // Deposit initial cash via Collection (credit card/bank)
        $invoice->recordCollection([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'amount'          => 10000.00,
            'collection_date' => now()->toDateString(),
        ]);

        // Open receivable: +2000
        $invoice->createReceivable([
            'user_id'       => $this->user->id,
            'party_id'      => $this->party->id,
            'amount'        => 2000.00,
            'document_date' => now()->toDateString(),
        ]);

        // Open payable: -3000
        $invoice->createPayable([
            'user_id'       => $this->user->id,
            'party_id'      => $this->party->id,
            'amount'        => 3000.00,
            'document_date' => now()->toDateString(),
        ]);

        $flow = $this->service->getCashFlowForecast($this->user->id);

        $this->assertEquals(10000.00, $flow['bank_balance']);
        $this->assertEquals(2000.00, $flow['expected_inflow']);
        $this->assertEquals(3000.00, $flow['expected_outflow']);
        $this->assertEquals(9000.00, $flow['net_forecast']); // 10000 + 2000 - 3000 = 9000
    }

    public function test_profit_loss_statement(): void
    {
        $invoice = app(OutstandingInvoiceService::class);

        // Sales (Revenue): +1500
        $invoice->createReceivable([
            'user_id'       => $this->user->id,
            'party_id'      => $this->party->id,
            'amount'        => 1500.00,
            'document_date' => now()->toDateString(),
        ]);

        // General Management Gideri (Expense): -400
        $invoice->createPayable([
            'user_id'       => $this->user->id,
            'party_id'      => $this->party->id,
            'amount'        => 400.00,
            'document_date' => now()->toDateString(),
        ]);

        $pl = $this->service->getProfitLossSummary(
            $this->user->id,
            now()->subDay()->toDateString(),
            now()->addDay()->toDateString()
        );

        $this->assertEquals(1500.00, $pl['gross_revenue']);
        $this->assertEquals(400.00, $pl['total_expense']);
        $this->assertEquals(1100.00, $pl['net_profit']);
    }

    public function test_warehouse_stock_valuation(): void
    {
        $stock = app(StockService::class);
        $w = $stock->createWarehouse($this->user->id, 'Merkez', 'merkez', true);

        // Stock in: qty 10 @ cost 15.00
        $stock->recordMovement([
            'user_id'       => $this->user->id,
            'warehouse_id'  => $w->id,
            'stock_code'    => 'P-ABC',
            'movement_type' => 'in_purchase',
            'direction'     => 'in',
            'quantity'      => 10,
            'unit_cost'     => 15.00,
        ]);

        // Stock in: qty 5 @ cost 20.00 (most recent cost)
        $stock->recordMovement([
            'user_id'       => $this->user->id,
            'warehouse_id'  => $w->id,
            'stock_code'    => 'P-ABC',
            'movement_type' => 'in_purchase',
            'direction'     => 'in',
            'quantity'      => 5,
            'unit_cost'     => 20.00,
        ]);

        $valuation = $this->service->getWarehouseStockValue($this->user->id, $w->id);

        $this->assertEquals(15, $valuation['total_items']);
        // Valuation: 15 items * 20.00 (most recent cost) = 300.00
        $this->assertEquals(300.00, $valuation['total_value']);
    }
}
