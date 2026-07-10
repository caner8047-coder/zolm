<?php

namespace Tests\Feature;

use App\Models\Party;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\LegalEntity;
use App\Models\Account;
use App\Models\MpProduct;
use App\Models\StockBalance;
use App\Models\PartyLedgerEntry;
use App\Services\Accounting\OutstandingInvoiceService;
use App\Services\Accounting\ReportService;
use App\Services\Accounting\StockService;
use App\Services\Accounting\JournalService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use InvalidArgumentException;

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

        // 2. Overdue: 15 days ago (days_1_30)
        $invoice->createReceivable([
            'user_id'       => $this->user->id,
            'party_id'      => $this->party->id,
            'amount'        => 1000.00,
            'document_date' => now()->subDays(20)->toDateString(),
            'due_date'      => now()->subDays(15)->toDateString(),
        ]);

        // 3. Overdue: 45 days ago (days_31_60)
        $invoice->createReceivable([
            'user_id'       => $this->user->id,
            'party_id'      => $this->party->id,
            'amount'        => 2000.00,
            'document_date' => now()->subDays(50)->toDateString(),
            'due_date'      => now()->subDays(45)->toDateString(),
        ]);

        $report = $this->service->receivablesAging($this->user->id);

        $this->assertEquals(3500.00, $report['total_open']);
        $this->assertEquals(500.00, $report['current']);
        $this->assertEquals(1000.00, $report['days_1_30']);
        $this->assertEquals(2000.00, $report['days_31_60']);
        $this->assertEquals(0.00, $report['days_61_90']);
        $this->assertEquals(3, $report['count']);
    }

    public function test_aged_payables_categorization(): void
    {
        $invoice = app(OutstandingInvoiceService::class);

        // 1. Not Due (vadesi gelmemiş)
        $invoice->createPayable([
            'user_id'       => $this->user->id,
            'party_id'      => $this->party->id,
            'amount'        => 400.00,
            'document_date' => now()->toDateString(),
            'due_date'      => now()->addDays(5)->toDateString(),
        ]);

        // 2. Overdue: 15 days ago (days_1_30)
        $invoice->createPayable([
            'user_id'       => $this->user->id,
            'party_id'      => $this->party->id,
            'amount'        => 800.00,
            'document_date' => now()->subDays(20)->toDateString(),
            'due_date'      => now()->subDays(15)->toDateString(),
        ]);

        $report = $this->service->payablesAging($this->user->id);

        $this->assertEquals(1200.00, $report['total_open']);
        $this->assertEquals(400.00, $report['current']);
        $this->assertEquals(800.00, $report['days_1_30']);
        $this->assertEquals(2, $report['count']);
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
            'due_date'      => now()->addDays(2)->toDateString(),
        ]);

        // Open payable: -3000
        $invoice->createPayable([
            'user_id'       => $this->user->id,
            'party_id'      => $this->party->id,
            'amount'        => 3000.00,
            'document_date' => now()->toDateString(),
            'due_date'      => now()->addDays(5)->toDateString(),
        ]);

        $flow = $this->service->cashFlowForecast($this->user->id, 30);

        $this->assertEquals(10000.00, $flow['opening_cash_balance']);
        $this->assertEquals(2000.00, $flow['total_expected_inflows']);
        $this->assertEquals(3000.00, $flow['total_expected_outflows']);
        $this->assertEquals(9000.00, $flow['projected_closing_balance']); // 10000 + 2000 - 3000 = 9000
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

        // Voided journal entry
        $revenue = Account::where('user_id', $this->user->id)->where('code', '600')->first();
        $cash = Account::where('user_id', $this->user->id)->where('code', '100')->first();
        $journalService = app(JournalService::class);
        $entry = $journalService->postManual([
            'user_id' => $this->user->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Test Void',
        ], [
            ['account_id' => $cash->id, 'debit_amount' => 500.00],
            ['account_id' => $revenue->id, 'credit_amount' => 500.00],
        ]);
        $journalService->voidEntry($entry, 'Yanlış Kayıt');

        $pl = $this->service->incomeExpenseSummary($this->user->id, [
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->addDay()->toDateString()
        ]);

        $this->assertEquals(1500.00, $pl['total_income']);
        $this->assertEquals(400.00, $pl['total_expense']);
        $this->assertEquals(1100.00, $pl['net_result']);
    }

    public function test_warehouse_stock_valuation(): void
    {
        $stock = app(StockService::class);
        $w = $stock->createWarehouse($this->user->id, 'Merkez', 'merkez', true);

        // Seed MpProduct metadata
        MpProduct::create([
            'user_id' => $this->user->id,
            'stock_code' => 'P-ABC',
            'product_name' => 'Test Product ABC',
            'cogs' => 15.00,
            'barcode' => 'BAR-ABC',
        ]);

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

        $valuation = $this->service->stockInventoryValue($this->user->id, ['warehouse_id' => $w->id]);

        $this->assertEquals(15, $valuation['total_quantity']);
        // Valuation: 15 items * 20.00 (most recent cost) = 300.00
        $this->assertEquals(300.00, $valuation['total_inventory_value']);
    }

    public function test_tenant_isolation_on_reporting(): void
    {
        $otherUser = User::factory()->create(['is_active' => true]);
        $otherParty = Party::factory()->create(['user_id' => $otherUser->id]);

        (new ChartOfAccountsSeeder())->runForUser($otherUser->id);

        // Other user's invoice
        app(OutstandingInvoiceService::class)->createReceivable([
            'user_id'       => $otherUser->id,
            'party_id'      => $otherParty->id,
            'amount'        => 5000.00,
            'document_date' => now()->toDateString(),
        ]);

        $report = $this->service->receivablesAging($this->user->id);
        $this->assertEquals(0.00, $report['total_open']); // Other user's data should not leak
    }

    public function test_invalid_filter_id_throws_exception(): void
    {
        $otherUser = User::factory()->create(['is_active' => true]);
        $otherParty = Party::factory()->create(['user_id' => $otherUser->id]);

        $this->expectException(InvalidArgumentException::class);
        $this->service->receivablesAging($this->user->id, ['party_id' => $otherParty->id]);
    }

    // ─── P10 BLOCKER ADDED TESTS ──────────────────────────────────────────

    public function test_date_filters_exclude_records_outside_range(): void
    {
        $invoice = app(OutstandingInvoiceService::class);

        // Created 10 days ago
        $invoice->createReceivable([
            'user_id'       => $this->user->id,
            'party_id'      => $this->party->id,
            'amount'        => 500.00,
            'document_date' => now()->subDays(10)->toDateString(),
            'due_date'      => now()->addDays(5)->toDateString(),
        ]);

        // Created 5 days ago
        $invoice->createReceivable([
            'user_id'       => $this->user->id,
            'party_id'      => $this->party->id,
            'amount'        => 1000.00,
            'document_date' => now()->subDays(5)->toDateString(),
            'due_date'      => now()->addDays(5)->toDateString(),
        ]);

        // 1. Receivables aging filter range (excluding the 10 days ago one)
        $report = $this->service->receivablesAging($this->user->id, [
            'date_from' => now()->subDays(7)->toDateString(),
            'date_to'   => now()->toDateString()
        ]);
        $this->assertEquals(1000.00, $report['total_open']);
        $this->assertEquals(1, $report['count']);

        // 2. Cash flow forecast filter range
        $flow = $this->service->cashFlowForecast($this->user->id, 30, [
            'date_from' => now()->subDays(7)->toDateString(),
            'date_to'   => now()->toDateString()
        ]);
        $this->assertEquals(1000.00, $flow['total_expected_inflows']);

        // 3. Party ledger bakiye filter range
        PartyLedgerEntry::create([
            'user_id' => $this->user->id,
            'party_id' => $this->party->id,
            'debit_base_amount' => 500.00,
            'credit_base_amount' => 0.00,
            'document_date' => now()->subDays(10)->toDateString(),
            'status' => 'posted',
            'document_type' => 'receivable',
        ]);

        PartyLedgerEntry::create([
            'user_id' => $this->user->id,
            'party_id' => $this->party->id,
            'debit_base_amount' => 1000.00,
            'credit_base_amount' => 0.00,
            'document_date' => now()->subDays(5)->toDateString(),
            'status' => 'posted',
            'document_type' => 'receivable',
        ]);

        $parties = $this->service->partyBalanceSummary($this->user->id, [
            'date_from' => now()->subDays(7)->toDateString(),
            'date_to'   => now()->toDateString()
        ]);
        $this->assertEquals(1000.00, $parties['total_receivable_balance']);
    }

    public function test_cash_flow_horizon_limits_and_row_count(): void
    {
        $invoice = app(OutstandingInvoiceService::class);

        // Due in 5 days
        $invoice->createReceivable([
            'user_id'       => $this->user->id,
            'party_id'      => $this->party->id,
            'amount'        => 500.00,
            'document_date' => now()->toDateString(),
            'due_date'      => now()->addDays(5)->toDateString(),
        ]);

        // Due in 45 days (out of the 30-day forecast horizon)
        $invoice->createReceivable([
            'user_id'       => $this->user->id,
            'party_id'      => $this->party->id,
            'amount'        => 2000.00,
            'document_date' => now()->toDateString(),
            'due_date'      => now()->addDays(45)->toDateString(),
        ]);

        $flow = $this->service->cashFlowForecast($this->user->id, 30);

        // Assert 45 day receivable is excluded
        $this->assertEquals(500.00, $flow['total_expected_inflows']);

        // Assert row count is exactly 31 (today + 30 days inclusive)
        $this->assertCount(31, $flow['daily_rows']);
    }

    public function test_stock_inventory_value_legal_entity_and_warehouse_filtering(): void
    {
        $stock = app(StockService::class);

        // Create 2 Legal Entities
        $leA = LegalEntity::create(['user_id' => $this->user->id, 'name' => 'Entity A', 'is_active' => true, 'tax_number' => '1111111111']);
        $leB = LegalEntity::create(['user_id' => $this->user->id, 'name' => 'Entity B', 'is_active' => true, 'tax_number' => '2222222222']);

        // Create Warehouses tied to specific Legal Entities
        $whA = $stock->createWarehouse($this->user->id, 'Depo A', 'depo-a', false, $leA->id);
        $whB = $stock->createWarehouse($this->user->id, 'Depo B', 'depo-b', false, $leB->id);

        // Seed product
        MpProduct::create([
            'user_id'      => $this->user->id,
            'stock_code'   => 'P-XYZ',
            'product_name' => 'XYZ',
            'cogs'         => 10.00,
            'barcode'      => 'BAR-XYZ',
        ]);

        // Movement in Warehouse A with cost 12.00
        $stock->recordMovement([
            'user_id'         => $this->user->id,
            'warehouse_id'    => $whA->id,
            'stock_code'      => 'P-XYZ',
            'movement_type'   => 'in_purchase',
            'direction'       => 'in',
            'quantity'        => 10,
            'unit_cost'       => 12.00,
            'legal_entity_id' => $leA->id,
        ]);

        // Movement in Warehouse B with cost 18.00
        $stock->recordMovement([
            'user_id'         => $this->user->id,
            'warehouse_id'    => $whB->id,
            'stock_code'      => 'P-XYZ',
            'movement_type'   => 'in_purchase',
            'direction'       => 'in',
            'quantity'        => 5,
            'unit_cost'       => 18.00,
            'legal_entity_id' => $leB->id,
        ]);

        // 1. Filter by Legal Entity A only
        $reportA = $this->service->stockInventoryValue($this->user->id, ['legal_entity_id' => $leA->id]);
        $this->assertEquals(10, $reportA['total_quantity']);
        $this->assertEquals(120.00, $reportA['total_inventory_value']); // 10 * 12.00

        // 2. Filter by Legal Entity B only
        $reportB = $this->service->stockInventoryValue($this->user->id, ['legal_entity_id' => $leB->id]);
        $this->assertEquals(5, $reportB['total_quantity']);
        $this->assertEquals(90.00, $reportB['total_inventory_value']); // 5 * 18.00
    }

    public function test_legal_entity_filter_mismatch_warehouse_throws_exception(): void
    {
        $stock = app(StockService::class);
        $leA = LegalEntity::create(['user_id' => $this->user->id, 'name' => 'Entity A', 'is_active' => true, 'tax_number' => '1111111111']);
        $leB = LegalEntity::create(['user_id' => $this->user->id, 'name' => 'Entity B', 'is_active' => true, 'tax_number' => '2222222222']);

        $whA = $stock->createWarehouse($this->user->id, 'Depo A', 'depo-a', false, $leA->id);

        // Filter for Entity B but supplying Warehouse A (which belongs to Entity A) should throw
        $this->expectException(InvalidArgumentException::class);
        $this->service->stockInventoryValue($this->user->id, [
            'legal_entity_id' => $leB->id,
            'warehouse_id'    => $whA->id
        ]);
    }
}
