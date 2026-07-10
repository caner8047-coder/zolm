<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Collection;
use App\Models\MpProduct;
use App\Models\Party;
use App\Models\PartyRole;
use App\Models\PosSale;
use App\Models\PosShift;
use App\Models\PosTerminal;
use App\Models\SalesOrder;
use App\Models\StockBalance;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Accounting\PosService;
use App\Services\Accounting\StockService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class PosServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private PosTerminal $terminal;
    private PosService $service;
    private Warehouse $warehouse;
    private Account $cashAccount;
    private Account $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['is_active' => true]);

        $seeder = new ChartOfAccountsSeeder();
        $seeder->runForUser($this->user->id);

        $this->warehouse = app(StockService::class)->createWarehouse($this->user->id, 'Merkez Depo', 'depo-merkez', true);

        // Kasa ve banka hesapları
        $this->cashAccount = Account::create([
            'user_id' => $this->user->id,
            'code'    => '100.POS.01',
            'name'    => 'Merkez Kasa',
            'type'    => 'cash',
            'is_active' => true,
            'normal_balance' => 'debit',
            'is_cash_account' => true,
        ]);

        $this->bankAccount = Account::create([
            'user_id' => $this->user->id,
            'code'    => '102.POS.01',
            'name'    => 'Merkez Banka',
            'type'    => 'bank',
            'is_active' => true,
            'normal_balance' => 'debit',
            'is_bank_account' => true,
        ]);

        $this->terminal = PosTerminal::create([
            'user_id'      => $this->user->id,
            'name'         => 'Kasa 1',
            'is_active'    => true,
            'warehouse_id' => $this->warehouse->id,
            'account_id'   => $this->cashAccount->id,
        ]);

        $this->service = app(PosService::class);
        $this->actingAs($this->user);
    }

    /** @test */
    public function test_open_shift_rejects_other_user_terminal(): void
    {
        $otherUser = User::factory()->create(['is_active' => true]);
        $otherTerminal = PosTerminal::create([
            'user_id'   => $otherUser->id,
            'name'      => 'Kasa 2',
            'is_active' => true,
        ]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->service->openShift($otherTerminal, 100.00);
    }

    /** @test */
    public function test_open_shift_rejects_duplicate_open_shift(): void
    {
        $this->service->openShift($this->terminal, 100.00);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/açık bir vardiya/i');
        $this->service->openShift($this->terminal, 200.00);
    }

    /** @test */
    public function test_open_shift_rejects_negative_opening_balance(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/negatif olamaz/i');
        $this->service->openShift($this->terminal, -50.00);
    }

    /** @test */
    public function test_close_shift_rejects_other_user_shift(): void
    {
        $otherUser = User::factory()->create(['is_active' => true]);
        $otherTerminal = PosTerminal::create([
            'user_id' => $otherUser->id,
            'name' => 'Other Terminal',
            'is_active' => true,
        ]);

        $shift = PosShift::create([
            'user_id'         => $otherUser->id,
            'pos_terminal_id' => $otherTerminal->id,
            'opened_at'       => now(),
            'opening_balance' => 100.00,
            'status'          => 'open',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/başka bir kullanıcıya ait/i');
        $this->service->closeShift($shift, 200.00, $this->user->id);
    }

    /** @test */
    public function test_close_shift_rejects_already_closed_shift(): void
    {
        $shift = $this->service->openShift($this->terminal, 100.00);
        $this->service->closeShift($shift, 150.00, $this->user->id);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/sadece açık/i');
        $this->service->closeShift($shift, 200.00, $this->user->id);
    }

    /** @test */
    public function test_close_shift_calculates_expected_balance_and_difference(): void
    {
        // 1. Setup stock
        $product = MpProduct::create([
            'user_id' => $this->user->id,
            'stock_code' => 'P-CLOSE',
            'product_name' => 'Fabric Blue',
            'barcode' => 'BAR-CLOSE',
        ]);

        StockBalance::create([
            'user_id' => $this->user->id,
            'warehouse_id' => $this->warehouse->id,
            'stock_code' => 'P-CLOSE',
            'quantity' => 100,
        ]);

        $shift = $this->service->openShift($this->terminal, 100.00);

        // 2. record two sales
        $this->service->recordPosSale($shift, [
            'payment_method' => 'cash',
            'account_id' => $this->cashAccount->id,
        ], [
            ['stock_code' => 'P-CLOSE', 'quantity' => 2, 'unit_price' => 50.00, 'vat_rate' => 0.00]
        ]); // Total = 100

        $sale2 = $this->service->recordPosSale($shift, [
            'payment_method' => 'cash',
            'account_id' => $this->cashAccount->id,
        ], [
            ['stock_code' => 'P-CLOSE', 'quantity' => 1, 'unit_price' => 40.00, 'vat_rate' => 0.00]
        ]); // Total = 40

        // 3. Void second sale (voided sales shouldn't be counted in expected balance)
        $this->service->voidPosSale($sale2, 'Müşteri vazgeçti', $this->user->id);

        // Expected = Opening(100) + Sale1(100) = 200. (Sale2 was voided, shouldn't affect expected)
        // Closing balance counted by user = 210.00
        // Difference = 210 - 200 = +10.00
        $closed = $this->service->closeShift($shift, 210.00, $this->user->id);

        $this->assertEquals(200.00, (float) $closed->expected_closing_balance);
        $this->assertEquals(10.00, (float) $closed->difference_amount);
    }

    /** @test */
    public function test_record_pos_sale_rejects_closed_shift(): void
    {
        $product = MpProduct::create([
            'user_id' => $this->user->id,
            'stock_code' => 'PRD-X',
            'product_name' => 'Item X',
        ]);

        $shift = $this->service->openShift($this->terminal, 100.00);
        $this->service->closeShift($shift, 100.00, $this->user->id);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/açık bir vardiya/i');

        $this->service->recordPosSale($shift, [
            'payment_method' => 'cash',
        ], [
            ['stock_code' => 'PRD-X', 'quantity' => 1, 'unit_price' => 10.00]
        ]);
    }

    /** @test */
    public function test_record_pos_sale_rejects_other_user_party_product_warehouse_account(): void
    {
        $otherUser = User::factory()->create(['is_active' => true]);
        $otherParty = Party::factory()->create(['user_id' => $otherUser->id]);
        $otherParty->roles()->create(['user_id' => $otherUser->id, 'role' => 'customer']);

        $product = MpProduct::create([
            'user_id' => $this->user->id,
            'stock_code' => 'POS-BARCODE-01',
            'product_name' => 'Valid User 1 Product',
        ]);

        $shift = $this->service->openShift($this->terminal, 100.00);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        // Attempting to checkout using other user's customer
        $this->service->recordPosSale($shift, [
            'payment_method' => 'cash',
            'party_id' => $otherParty->id,
        ], [
            ['stock_code' => 'POS-BARCODE-01', 'quantity' => 1, 'unit_price' => 50.00]
        ]);
    }

    /** @test */
    public function test_payment_method_validations(): void
    {
        $shift = $this->service->openShift($this->terminal, 100.00);

        $product = MpProduct::create([
            'user_id' => $this->user->id,
            'stock_code' => 'P-PMETH',
            'product_name' => 'Item PM',
        ]);
        StockBalance::create([
            'user_id' => $this->user->id,
            'warehouse_id' => $this->warehouse->id,
            'stock_code' => 'P-PMETH',
            'quantity' => 10,
        ]);

        // Cash payment rejects bank account
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/kasa hesabı kullanılabilir/i');
        $this->service->recordPosSale($shift, [
            'payment_method' => 'cash',
            'account_id' => $this->bankAccount->id, // Bank account for cash payment!
        ], [
            ['stock_code' => 'P-PMETH', 'quantity' => 1, 'unit_price' => 10.00]
        ]);
    }

    /** @test */
    public function test_payment_method_bank_rejects_cash_account(): void
    {
        $shift = $this->service->openShift($this->terminal, 100.00);

        $product = MpProduct::create([
            'user_id' => $this->user->id,
            'stock_code' => 'P-PMETH',
            'product_name' => 'Item PM',
        ]);
        StockBalance::create([
            'user_id' => $this->user->id,
            'warehouse_id' => $this->warehouse->id,
            'stock_code' => 'P-PMETH',
            'quantity' => 10,
        ]);

        // Card payment rejects cash account
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/banka hesabı seçilmelidir/i');
        $this->service->recordPosSale($shift, [
            'payment_method' => 'card',
            'account_id' => $this->cashAccount->id, // Cash account for card payment!
        ], [
            ['stock_code' => 'P-PMETH', 'quantity' => 1, 'unit_price' => 10.00]
        ]);
    }

    /** @test */
    public function test_party_id_not_set_creates_retail_customer_idempotently(): void
    {
        $shift = $this->service->openShift($this->terminal, 100.00);

        $product = MpProduct::create([
            'user_id' => $this->user->id,
            'stock_code' => 'P-RETAIL',
            'product_name' => 'Item Retail',
        ]);
        StockBalance::create([
            'user_id' => $this->user->id,
            'warehouse_id' => $this->warehouse->id,
            'stock_code' => 'P-RETAIL',
            'quantity' => 10,
        ]);

        // Count customer role initially
        $initialCount = PartyRole::where('user_id', $this->user->id)->where('role', 'customer')->count();

        // 1. First sale without party_id
        $this->service->recordPosSale($shift, [
            'payment_method' => 'cash',
            'account_id' => $this->cashAccount->id,
        ], [
            ['stock_code' => 'P-RETAIL', 'quantity' => 1, 'unit_price' => 10.00]
        ]);

        $this->assertDatabaseHas('parties', [
            'user_id' => $this->user->id,
            'display_name' => 'Perakende Müşteri',
        ]);

        // 2. Second sale should reuse same party
        $this->service->recordPosSale($shift, [
            'payment_method' => 'cash',
            'account_id' => $this->cashAccount->id,
        ], [
            ['stock_code' => 'P-RETAIL', 'quantity' => 1, 'unit_price' => 10.00]
        ]);

        // Retail customer party count should be 1
        $this->assertEquals(1, Party::where('user_id', $this->user->id)->where('display_name', 'Perakende Müşteri')->count());
    }

    /** @test */
    public function test_successful_pos_sale_full_integration(): void
    {
        $shift = $this->service->openShift($this->terminal, 100.00);

        $product = MpProduct::create([
            'user_id' => $this->user->id,
            'stock_code' => 'P-SUCCESS',
            'product_name' => 'Successful Item',
        ]);
        StockBalance::create([
            'user_id' => $this->user->id,
            'warehouse_id' => $this->warehouse->id,
            'stock_code' => 'P-SUCCESS',
            'quantity' => 15,
        ]);

        $posSale = $this->service->recordPosSale($shift, [
            'payment_method' => 'cash',
            'account_id' => $this->cashAccount->id,
        ], [
            ['stock_code' => 'P-SUCCESS', 'quantity' => 5, 'unit_price' => 20.00, 'vat_rate' => 20.00] // Total = 100 + 20 VAT = 120
        ]);

        $this->assertNotNull($posSale);
        $this->assertEquals('posted', $posSale->status);
        $this->assertEquals(120.00, (float) $posSale->amount);

        // SalesOrder approved
        $order = $posSale->salesOrder;
        $this->assertEquals('approved', $order->status);

        // Stock decreased by 5 (15 - 5 = 10)
        $this->assertEquals(10, app(StockService::class)->getStockLevel($this->user->id, 'P-SUCCESS', $this->warehouse->id));

        // Collection created and allocated
        $collection = $posSale->collection;
        $this->assertNotNull($collection);
        $this->assertEquals(120.00, (float) $collection->amount);
        $this->assertEquals('posted', $collection->status);

        // Receivable fully paid
        $receivable = $order->receivable;
        $this->assertEquals('paid', $receivable->status);
        $this->assertEquals(120.00, (float) $receivable->paid_amount);
    }

    /** @test */
    public function test_insufficient_stock_causes_complete_rollback(): void
    {
        $shift = $this->service->openShift($this->terminal, 100.00);

        $product = MpProduct::create([
            'user_id' => $this->user->id,
            'stock_code' => 'P-ROLLBACK',
            'product_name' => 'Rollback Item',
        ]);
        StockBalance::create([
            'user_id' => $this->user->id,
            'warehouse_id' => $this->warehouse->id,
            'stock_code' => 'P-ROLLBACK',
            'quantity' => 2, // only 2 in stock
        ]);

        try {
            // Attempting to buy 5 items (exceeding stock)
            $this->service->recordPosSale($shift, [
                'payment_method' => 'cash',
                'account_id' => $this->cashAccount->id,
            ], [
                ['stock_code' => 'P-ROLLBACK', 'quantity' => 5, 'unit_price' => 20.00]
            ]);
            $this->fail('Rollback should have thrown exception due to insufficient stock.');
        } catch (\Exception $e) {
            // Verify database has no order, collection, or pos sale
            $this->assertEquals(0, PosSale::count());
            $this->assertEquals(0, SalesOrder::count());
            $this->assertEquals(0, Collection::count());
        }
    }

    /** @test */
    public function test_source_key_idempotency_checks(): void
    {
        $shift = $this->service->openShift($this->terminal, 100.00);

        $product = MpProduct::create([
            'user_id' => $this->user->id,
            'stock_code' => 'P-IDEM',
            'product_name' => 'Idempotent Item',
        ]);
        StockBalance::create([
            'user_id' => $this->user->id,
            'warehouse_id' => $this->warehouse->id,
            'stock_code' => 'P-IDEM',
            'quantity' => 10,
        ]);

        // 1. Success first record
        $sale1 = $this->service->recordPosSale($shift, [
            'payment_method' => 'cash',
            'account_id' => $this->cashAccount->id,
            'source_key' => 'idem_key_01',
        ], [
            ['stock_code' => 'P-IDEM', 'quantity' => 1, 'unit_price' => 10.00]
        ]);

        // 2. Same key + same payload should return existing
        $sale2 = $this->service->recordPosSale($shift, [
            'payment_method' => 'cash',
            'account_id' => $this->cashAccount->id,
            'source_key' => 'idem_key_01',
        ], [
            ['stock_code' => 'P-IDEM', 'quantity' => 1, 'unit_price' => 10.00]
        ]);

        $this->assertEquals($sale1->id, $sale2->id);

        // 3. Same key + different payload should throw exception
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/farklı detaylara sahip/i');
        $this->service->recordPosSale($shift, [
            'payment_method' => 'cash',
            'account_id' => $this->cashAccount->id,
            'source_key' => 'idem_key_01',
        ], [
            ['stock_code' => 'P-IDEM', 'quantity' => 2, 'unit_price' => 10.00] // different quantity!
        ]);
    }

    /** @test */
    public function test_void_pos_sale_reverses_all_records(): void
    {
        $shift = $this->service->openShift($this->terminal, 100.00);

        $product = MpProduct::create([
            'user_id' => $this->user->id,
            'stock_code' => 'P-VOID',
            'product_name' => 'Void Item',
        ]);
        StockBalance::create([
            'user_id' => $this->user->id,
            'warehouse_id' => $this->warehouse->id,
            'stock_code' => 'P-VOID',
            'quantity' => 10,
        ]);

        $sale = $this->service->recordPosSale($shift, [
            'payment_method' => 'cash',
            'account_id' => $this->cashAccount->id,
        ], [
            ['stock_code' => 'P-VOID', 'quantity' => 3, 'unit_price' => 10.00, 'vat_rate' => 0.00] // Total = 30
        ]);

        // Initial stock check = 10 - 3 = 7
        $this->assertEquals(7, app(StockService::class)->getStockLevel($this->user->id, 'P-VOID', $this->warehouse->id));

        // Perform Void
        $this->service->voidPosSale($sale, 'Hatalı miktar', $this->user->id);

        $sale->refresh();
        $this->assertEquals('voided', $sale->status);
        $this->assertNotNull($sale->voided_at);
        $this->assertEquals('Hatalı miktar', $sale->void_reason);

        // Stock restored back to 10
        $this->assertEquals(10, app(StockService::class)->getStockLevel($this->user->id, 'P-VOID', $this->warehouse->id));

        // Collection is voided
        $collection = $sale->collection;
        $this->assertEquals('voided', $collection->status);

        // SalesOrder is cancelled
        $this->assertEquals('cancelled', $sale->salesOrder->status);
    }

    /** @test */
    public function test_void_pos_sale_rejects_closed_shift_sales(): void
    {
        $shift = $this->service->openShift($this->terminal, 100.00);

        $product = MpProduct::create([
            'user_id' => $this->user->id,
            'stock_code' => 'P-VOID-CLOSED',
            'product_name' => 'Void Closed Item',
        ]);
        StockBalance::create([
            'user_id' => $this->user->id,
            'warehouse_id' => $this->warehouse->id,
            'stock_code' => 'P-VOID-CLOSED',
            'quantity' => 10,
        ]);

        $sale = $this->service->recordPosSale($shift, [
            'payment_method' => 'cash',
            'account_id' => $this->cashAccount->id,
        ], [
            ['stock_code' => 'P-VOID-CLOSED', 'quantity' => 2, 'unit_price' => 10.00]
        ]);

        // Close shift
        $this->service->closeShift($shift, 120.00, $this->user->id);

        // Voiding sale from closed shift should be blocked
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/kapalı bir vardiyadaki satış/i');
        $this->service->voidPosSale($sale, 'Void after close', $this->user->id);
    }
}
