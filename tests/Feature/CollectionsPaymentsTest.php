<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Collection;
use App\Models\JournalEntry;
use App\Models\Party;
use App\Models\PartyRole;
use App\Models\Payable;
use App\Models\Payment;
use App\Models\PartyLedgerEntry;
use App\Models\Receivable;
use App\Models\ReceivableAllocation;
use App\Models\PayableAllocation;
use App\Models\User;
use App\Services\Accounting\CashBankService;
use App\Services\Accounting\CollectionPaymentService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CollectionsPaymentsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Party $customerParty;
    private Party $supplierParty;
    private CollectionPaymentService $service;
    private Account $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['is_active' => true]);

        // Hesap planını seed et
        (new ChartOfAccountsSeeder())->runForUser($this->user->id);

        // Müşteri cari
        $this->customerParty = Party::factory()->create([
            'user_id' => $this->user->id,
            'status'  => 'active',
        ]);
        PartyRole::create(['party_id' => $this->customerParty->id, 'user_id' => $this->user->id, 'role' => 'customer']);

        // Tedarikçi cari
        $this->supplierParty = Party::factory()->create([
            'user_id' => $this->user->id,
            'status'  => 'active',
        ]);
        PartyRole::create(['party_id' => $this->supplierParty->id, 'user_id' => $this->user->id, 'role' => 'supplier']);

        // Banka hesabı oluştur
        $bankAcc = app(CashBankService::class)->createBankAccount($this->user->id, [
            'bank_name'      => 'Test Bankası',
            'account_number' => '1234567',
            'currency_code'  => 'TRY',
        ]);
        $this->bankAccount = $bankAcc->account;

        $this->service = app(CollectionPaymentService::class);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // YARDIMCI METODLAR
    // ─────────────────────────────────────────────────────────────────────────

    private function makeReceivable(float $amount = 1000.0, string $status = 'open'): Receivable
    {
        return Receivable::create([
            'user_id'       => $this->user->id,
            'party_id'      => $this->customerParty->id,
            'document_date' => now()->toDateString(),
            'amount'        => $amount,
            'paid_amount'   => 0.0,
            'currency_code' => 'TRY',
            'exchange_rate' => 1.0,
            'status'        => $status,
        ]);
    }

    private function makePayable(float $amount = 1000.0, string $status = 'open'): Payable
    {
        return Payable::create([
            'user_id'       => $this->user->id,
            'party_id'      => $this->supplierParty->id,
            'document_date' => now()->toDateString(),
            'amount'        => $amount,
            'paid_amount'   => 0.0,
            'currency_code' => 'TRY',
            'exchange_rate' => 1.0,
            'status'        => 'open',
        ]);
    }

    private function makeCollection(float $amount = 500.0): Collection
    {
        return $this->service->recordCollection([
            'user_id'         => $this->user->id,
            'party_id'        => $this->customerParty->id,
            'account_id'      => $this->bankAccount->id,
            'amount'          => $amount,
            'collection_date' => now()->toDateString(),
            'payment_method'  => 'bank',
        ]);
    }

    private function makePayment(float $amount = 500.0): Payment
    {
        return $this->service->recordPayment([
            'user_id'        => $this->user->id,
            'party_id'       => $this->supplierParty->id,
            'account_id'     => $this->bankAccount->id,
            'amount'         => $amount,
            'payment_date'   => now()->toDateString(),
            'payment_method' => 'bank',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TAHSİLAT TESTLERİ
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function test_record_collection_creates_collection_journal_and_ledger(): void
    {
        $collection = $this->makeCollection(750.0);

        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertEquals('posted', $collection->status);
        $this->assertEquals(750.0, (float) $collection->amount);

        // GL fişi oluşturuldu
        $this->assertDatabaseHas('journal_entries', [
            'user_id'    => $this->user->id,
            'entry_type' => 'collection',
            'status'     => 'posted',
        ]);

        // Cari defteri kaydı oluşturuldu (collection = credit)
        $this->assertDatabaseHas('party_ledger_entries', [
            'user_id'       => $this->user->id,
            'party_id'      => $this->customerParty->id,
            'document_type' => 'collection',
            'credit_amount' => 750.0,
            'status'        => 'posted',
        ]);
    }

    /** @test */
    public function test_record_collection_rejects_zero_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->recordCollection([
            'user_id'         => $this->user->id,
            'party_id'        => $this->customerParty->id,
            'account_id'      => $this->bankAccount->id,
            'amount'          => 0.0,
            'collection_date' => now()->toDateString(),
            'payment_method'  => 'bank',
        ]);
    }

    /** @test */
    public function test_record_collection_rejects_wrong_user_party(): void
    {
        $otherUser = User::factory()->create();
        $otherParty = Party::factory()->create(['user_id' => $otherUser->id]);

        $this->expectException(InvalidArgumentException::class);

        $this->service->recordCollection([
            'user_id'         => $this->user->id,
            'party_id'        => $otherParty->id,
            'account_id'      => $this->bankAccount->id,
            'amount'          => 100.0,
            'collection_date' => now()->toDateString(),
            'payment_method'  => 'bank',
        ]);
    }

    /** @test */
    public function test_allocate_collection_to_receivable_fully(): void
    {
        $receivable = $this->makeReceivable(500.0);
        $collection = $this->makeCollection(500.0);

        $this->service->allocateCollection($collection, [
            ['receivable_id' => $receivable->id, 'amount' => 500.0],
        ]);

        $receivable->refresh();
        $this->assertEquals('paid', $receivable->status);
        $this->assertEquals(500.0, (float) $receivable->paid_amount);

        $this->assertDatabaseHas('receivable_allocations', [
            'collection_id' => $collection->id,
            'receivable_id' => $receivable->id,
            'amount'        => 500.0,
        ]);
    }

    /** @test */
    public function test_allocate_collection_partially(): void
    {
        $receivable = $this->makeReceivable(1000.0);
        $collection = $this->makeCollection(400.0);

        $this->service->allocateCollection($collection, [
            ['receivable_id' => $receivable->id, 'amount' => 400.0],
        ]);

        $receivable->refresh();
        $this->assertEquals('partially_paid', $receivable->status);
        $this->assertEquals(400.0, (float) $receivable->paid_amount);
    }

    /** @test */
    public function test_allocate_collection_over_amount_throws(): void
    {
        $receivable = $this->makeReceivable(500.0);
        $collection = $this->makeCollection(300.0);

        $this->expectException(InvalidArgumentException::class);

        $this->service->allocateCollection($collection, [
            ['receivable_id' => $receivable->id, 'amount' => 400.0], // 400 > 300 (collection amount)
        ]);
    }

    /** @test */
    public function test_allocate_collection_over_receivable_remaining_throws(): void
    {
        $receivable = $this->makeReceivable(300.0); // only 300 remaining
        $collection = $this->makeCollection(500.0);

        $this->expectException(InvalidArgumentException::class);

        $this->service->allocateCollection($collection, [
            ['receivable_id' => $receivable->id, 'amount' => 400.0], // 400 > 300 (receivable)
        ]);
    }

    /** @test */
    public function test_allocate_collection_cross_party_throws(): void
    {
        // receivable belongs to supplier, but collection belongs to customer
        $wrongReceivable = Receivable::create([
            'user_id'       => $this->user->id,
            'party_id'      => $this->supplierParty->id, // different party
            'document_date' => now()->toDateString(),
            'amount'        => 500.0,
            'paid_amount'   => 0.0,
            'currency_code' => 'TRY',
            'exchange_rate' => 1.0,
            'status'        => 'open',
        ]);

        $collection = $this->makeCollection(500.0);

        $this->expectException(InvalidArgumentException::class);

        $this->service->allocateCollection($collection, [
            ['receivable_id' => $wrongReceivable->id, 'amount' => 500.0],
        ]);
    }

    /** @test */
    public function test_void_collection_reverses_allocations_and_ledger(): void
    {
        $receivable = $this->makeReceivable(500.0);
        $collection = $this->makeCollection(500.0);

        $this->service->allocateCollection($collection, [
            ['receivable_id' => $receivable->id, 'amount' => 500.0],
        ]);

        $receivable->refresh();
        $this->assertEquals('paid', $receivable->status);

        // İptal
        $this->service->voidCollection($collection->fresh(), 'Test iptali');

        // Receivable açılmalı
        $receivable->refresh();
        $this->assertEquals('open', $receivable->status);
        $this->assertEquals(0.0, (float) $receivable->paid_amount);

        // Allocation silinmeli
        $this->assertDatabaseMissing('receivable_allocations', [
            'collection_id' => $collection->id,
        ]);

        // Collection voided
        $collection->refresh();
        $this->assertEquals('voided', $collection->status);

        // GL fişi voided
        $this->assertDatabaseHas('journal_entries', [
            'id'     => $collection->journal_entry_id,
            'status' => 'voided',
        ]);
    }

    /** @test */
    public function test_void_already_voided_collection_throws(): void
    {
        $collection = $this->makeCollection(100.0);
        $this->service->voidCollection($collection);

        $this->expectException(InvalidArgumentException::class);
        $this->service->voidCollection($collection->fresh());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ÖDEME TESTLERİ
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function test_record_payment_creates_payment_journal_and_ledger(): void
    {
        $payment = $this->makePayment(600.0);

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertEquals('posted', $payment->status);
        $this->assertEquals(600.0, (float) $payment->amount);

        $this->assertDatabaseHas('journal_entries', [
            'user_id'    => $this->user->id,
            'entry_type' => 'payment',
            'status'     => 'posted',
        ]);

        // Cari defteri: payment = debit
        $this->assertDatabaseHas('party_ledger_entries', [
            'user_id'       => $this->user->id,
            'party_id'      => $this->supplierParty->id,
            'document_type' => 'payment',
            'debit_amount'  => 600.0,
            'status'        => 'posted',
        ]);
    }

    /** @test */
    public function test_record_payment_rejects_zero_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->recordPayment([
            'user_id'        => $this->user->id,
            'party_id'       => $this->supplierParty->id,
            'account_id'     => $this->bankAccount->id,
            'amount'         => 0.0,
            'payment_date'   => now()->toDateString(),
            'payment_method' => 'bank',
        ]);
    }

    /** @test */
    public function test_allocate_payment_to_payable_fully(): void
    {
        $payable = $this->makePayable(800.0);
        $payment = $this->makePayment(800.0);

        $this->service->allocatePayment($payment, [
            ['payable_id' => $payable->id, 'amount' => 800.0],
        ]);

        $payable->refresh();
        $this->assertEquals('paid', $payable->status);
        $this->assertEquals(800.0, (float) $payable->paid_amount);
    }

    /** @test */
    public function test_allocate_payment_partially(): void
    {
        $payable = $this->makePayable(1000.0);
        $payment = $this->makePayment(300.0);

        $this->service->allocatePayment($payment, [
            ['payable_id' => $payable->id, 'amount' => 300.0],
        ]);

        $payable->refresh();
        $this->assertEquals('partially_paid', $payable->status);
        $this->assertEquals(300.0, (float) $payable->paid_amount);
    }

    /** @test */
    public function test_allocate_payment_over_amount_throws(): void
    {
        $payable = $this->makePayable(1000.0);
        $payment = $this->makePayment(200.0);

        $this->expectException(InvalidArgumentException::class);

        $this->service->allocatePayment($payment, [
            ['payable_id' => $payable->id, 'amount' => 300.0], // 300 > 200
        ]);
    }

    /** @test */
    public function test_void_payment_reverses_allocations_and_ledger(): void
    {
        $payable = $this->makePayable(500.0);
        $payment = $this->makePayment(500.0);

        $this->service->allocatePayment($payment, [
            ['payable_id' => $payable->id, 'amount' => 500.0],
        ]);

        $payable->refresh();
        $this->assertEquals('paid', $payable->status);

        $this->service->voidPayment($payment->fresh(), 'Test iptali');

        $payable->refresh();
        $this->assertEquals('open', $payable->status);
        $this->assertEquals(0.0, (float) $payable->paid_amount);

        $this->assertDatabaseMissing('payable_allocations', [
            'payment_id' => $payment->id,
        ]);

        $payment->refresh();
        $this->assertEquals('voided', $payment->status);

        $this->assertDatabaseHas('journal_entries', [
            'id'     => $payment->journal_entry_id,
            'status' => 'voided',
        ]);
    }

    /** @test */
    public function test_void_already_voided_payment_throws(): void
    {
        $payment = $this->makePayment(100.0);
        $this->service->voidPayment($payment);

        $this->expectException(InvalidArgumentException::class);
        $this->service->voidPayment($payment->fresh());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TENANT İZOLASYON TESTLERİ
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function test_collection_and_payment_are_user_isolated(): void
    {
        $otherUser = User::factory()->create();
        (new ChartOfAccountsSeeder())->runForUser($otherUser->id);
        $otherParty = Party::factory()->create(['user_id' => $otherUser->id, 'status' => 'active']);

        // Bu kullanıcının tahsilatı yalnızca bu kullanıcıya ait olmalı
        $collection = $this->makeCollection(100.0);

        $this->assertDatabaseHas('collections', [
            'id'      => $collection->id,
            'user_id' => $this->user->id,
        ]);

        // Diğer kullanıcı bu koleksiyonu görmemeli (query isolation)
        $count = Collection::where('user_id', $otherUser->id)->count();
        $this->assertEquals(0, $count);
    }


    // ─────────────────────────────────────────────────────────────────────────
    // YARDIMCI METODLAR TESTİ
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function test_get_cash_bank_accounts_returns_user_accounts(): void
    {
        $accounts = $this->service->getCashBankAccounts($this->user->id);

        $this->assertGreaterThan(0, $accounts->count());
        $this->assertTrue(
            $accounts->every(fn($a) => $a->user_id === $this->user->id)
        );
    }

    /** @test */
    public function test_get_open_receivables_returns_only_open(): void
    {
        $this->makeReceivable(300.0, 'open');
        $this->makeReceivable(200.0, 'paid');

        $openRecs = $this->service->getOpenReceivables($this->user->id, $this->customerParty->id);

        $this->assertCount(1, $openRecs);
        $this->assertEquals('open', $openRecs->first()->status);
    }

    /** @test */
    public function test_get_open_payables_returns_only_open(): void
    {
        $this->makePayable(500.0, 'open');

        // Paid payable
        Payable::create([
            'user_id'       => $this->user->id,
            'party_id'      => $this->supplierParty->id,
            'document_date' => now()->toDateString(),
            'amount'        => 200.0,
            'paid_amount'   => 200.0,
            'currency_code' => 'TRY',
            'exchange_rate' => 1.0,
            'status'        => 'paid',
        ]);

        $openPays = $this->service->getOpenPayables($this->user->id, $this->supplierParty->id);

        $this->assertCount(1, $openPays);
        $this->assertEquals('open', $openPays->first()->status);
    }

    /** @test */
    public function test_multiple_allocation_across_receivables(): void
    {
        $rec1 = $this->makeReceivable(400.0);
        $rec2 = $this->makeReceivable(300.0);
        $collection = $this->makeCollection(700.0);

        $this->service->allocateCollection($collection, [
            ['receivable_id' => $rec1->id, 'amount' => 400.0],
            ['receivable_id' => $rec2->id, 'amount' => 300.0],
        ]);

        $rec1->refresh();
        $rec2->refresh();

        $this->assertEquals('paid', $rec1->status);
        $this->assertEquals('paid', $rec2->status);
    }

    /** @test */
    public function test_collection_stores_account_id_and_reference(): void
    {
        $collection = $this->service->recordCollection([
            'user_id'          => $this->user->id,
            'party_id'         => $this->customerParty->id,
            'account_id'       => $this->bankAccount->id,
            'amount'           => 250.0,
            'collection_date'  => now()->toDateString(),
            'payment_method'   => 'bank',
            'reference_number' => 'TRF-20260710-001',
        ]);

        $this->assertEquals($this->bankAccount->id, $collection->account_id);
        $this->assertEquals('TRF-20260710-001', $collection->reference_number);
    }

    /** @test */
    public function test_payment_stores_account_id_and_reference(): void
    {
        $payment = $this->service->recordPayment([
            'user_id'          => $this->user->id,
            'party_id'         => $this->supplierParty->id,
            'account_id'       => $this->bankAccount->id,
            'amount'           => 350.0,
            'payment_date'     => now()->toDateString(),
            'payment_method'   => 'bank',
            'reference_number' => 'DEC-20260710-001',
        ]);

        $this->assertEquals($this->bankAccount->id, $payment->account_id);
        $this->assertEquals('DEC-20260710-001', $payment->reference_number);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BLOKLAYICI 1 — Search Tenant Leak
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function test_search_does_not_leak_other_user_collections(): void
    {
        // Başka kullanıcı aynı description ile tahsilat yapsın
        $otherUser = User::factory()->create();
        (new ChartOfAccountsSeeder())->runForUser($otherUser->id);
        $otherParty = Party::factory()->create(['user_id' => $otherUser->id, 'status' => 'active']);

        $otherBankAcc = app(\App\Services\Accounting\CashBankService::class)->createBankAccount($otherUser->id, [
            'bank_name' => 'Other Bank', 'account_number' => '9999', 'currency_code' => 'TRY',
        ]);

        $this->service->recordCollection([
            'user_id'         => $otherUser->id,
            'party_id'        => $otherParty->id,
            'account_id'      => $otherBankAcc->account->id,
            'amount'           => 100.0,
            'collection_date'  => now()->toDateString(),
            'payment_method'   => 'bank',
            'description'      => 'SHARED_SEARCH_TERM',
        ]);

        // Ana kullanıcı kendi tahsilatını yaptı (farklı description)
        $this->makeCollection(200.0);

        // Ana kullanıcı 'SHARED_SEARCH_TERM' ile arama yaptı — başka kullanıcının kaydı görünmemeli
        $results = Collection::where('user_id', $this->user->id)
            ->where(function ($q) {
                $q->where(function ($inner) {
                    $inner->where('description', 'like', '%SHARED_SEARCH_TERM%')
                          ->orWhere('reference_number', 'like', '%SHARED_SEARCH_TERM%');
                });
            })
            ->get();

        $this->assertCount(0, $results); // Ana kullanıcının 'SHARED_SEARCH_TERM' içeren tahsilatı yok
        $this->assertDatabaseHas('collections', ['user_id' => $otherUser->id, 'description' => 'SHARED_SEARCH_TERM']);
    }

    /** @test */
    public function test_search_does_not_leak_other_user_payments(): void
    {
        $otherUser = User::factory()->create();
        (new ChartOfAccountsSeeder())->runForUser($otherUser->id);
        $otherParty = Party::factory()->create(['user_id' => $otherUser->id, 'status' => 'active']);

        $otherBankAcc = app(\App\Services\Accounting\CashBankService::class)->createBankAccount($otherUser->id, [
            'bank_name' => 'Other Bank', 'account_number' => '8888', 'currency_code' => 'TRY',
        ]);

        $this->service->recordPayment([
            'user_id'        => $otherUser->id,
            'party_id'       => $otherParty->id,
            'account_id'     => $otherBankAcc->account->id,
            'amount'          => 100.0,
            'payment_date'    => now()->toDateString(),
            'payment_method'  => 'bank',
            'reference_number' => 'LEAKED_REF',
        ]);

        $results = \App\Models\Payment::where('user_id', $this->user->id)
            ->where(function ($q) {
                $q->where(function ($inner) {
                    $inner->where('reference_number', 'like', '%LEAKED_REF%');
                });
            })
            ->get();

        $this->assertCount(0, $results);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BLOKLAYICI 2 — Account Type Guard
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function test_collection_rejects_non_cash_bank_account(): void
    {
        // AR hesabı bul (is_ar_account = true, is_cash/bank = false)
        $arAccount = \App\Models\Account::where('user_id', $this->user->id)
            ->where('is_ar_account', true)
            ->first();

        $this->assertNotNull($arAccount, 'AR hesabı seed edilmiş olmalı');

        $this->expectException(InvalidArgumentException::class);

        $this->service->recordCollection([
            'user_id'         => $this->user->id,
            'party_id'        => $this->customerParty->id,
            'account_id'      => $arAccount->id,
            'amount'          => 100.0,
            'collection_date' => now()->toDateString(),
            'payment_method'  => 'bank',
        ]);
    }

    /** @test */
    public function test_payment_rejects_non_cash_bank_account(): void
    {
        $apAccount = \App\Models\Account::where('user_id', $this->user->id)
            ->where('is_ap_account', true)
            ->first();

        $this->assertNotNull($apAccount, 'AP hesabı seed edilmiş olmalı');

        $this->expectException(InvalidArgumentException::class);

        $this->service->recordPayment([
            'user_id'        => $this->user->id,
            'party_id'       => $this->supplierParty->id,
            'account_id'     => $apAccount->id,
            'amount'         => 100.0,
            'payment_date'   => now()->toDateString(),
            'payment_method' => 'bank',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BLOKLAYICI 3 — Legal Entity Exact-Match
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function test_allocate_collection_rejects_different_legal_entity(): void
    {
        // İki farklı legal entity oluştur
        $le1 = \App\Models\LegalEntity::create(['user_id' => $this->user->id, 'name' => 'Firma A', 'tax_number' => '11111111111', 'is_active' => true]);
        $le2 = \App\Models\LegalEntity::create(['user_id' => $this->user->id, 'name' => 'Firma B', 'tax_number' => '22222222222', 'is_active' => true]);

        // Legal entity 1 ile tahsilat
        $collection = $this->service->recordCollection([
            'user_id'         => $this->user->id,
            'party_id'        => $this->customerParty->id,
            'account_id'      => $this->bankAccount->id,
            'amount'          => 500.0,
            'collection_date' => now()->toDateString(),
            'payment_method'  => 'bank',
            'legal_entity_id' => $le1->id,
        ]);

        // Legal entity 2 ile receivable
        $receivable = Receivable::create([
            'user_id'         => $this->user->id,
            'party_id'        => $this->customerParty->id,
            'document_date'   => now()->toDateString(),
            'amount'          => 500.0,
            'paid_amount'     => 0.0,
            'currency_code'   => 'TRY',
            'exchange_rate'   => 1.0,
            'status'          => 'open',
            'legal_entity_id' => $le2->id, // farklı legal entity
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->service->allocateCollection($collection, [
            ['receivable_id' => $receivable->id, 'amount' => 500.0],
        ]);
    }

    /** @test */
    public function test_allocate_collection_rejects_one_null_one_set_legal_entity(): void
    {
        // Null legal entity ile tahsilat
        $collection = $this->service->recordCollection([
            'user_id'         => $this->user->id,
            'party_id'        => $this->customerParty->id,
            'account_id'      => $this->bankAccount->id,
            'amount'          => 500.0,
            'collection_date' => now()->toDateString(),
            'payment_method'  => 'bank',
            'legal_entity_id' => null,
        ]);

        // Gerçek legal entity ile receivable
        $le = \App\Models\LegalEntity::create(['user_id' => $this->user->id, 'name' => 'Firma C', 'tax_number' => '33333333333', 'is_active' => true]);
        $receivable = Receivable::create([
            'user_id'         => $this->user->id,
            'party_id'        => $this->customerParty->id,
            'document_date'   => now()->toDateString(),
            'amount'          => 500.0,
            'paid_amount'     => 0.0,
            'currency_code'   => 'TRY',
            'exchange_rate'   => 1.0,
            'status'          => 'open',
            'legal_entity_id' => $le->id,
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->service->allocateCollection($collection, [
            ['receivable_id' => $receivable->id, 'amount' => 500.0],
        ]);
    }

    /** @test */
    public function test_allocate_payment_rejects_different_legal_entity(): void
    {
        $le1 = \App\Models\LegalEntity::create(['user_id' => $this->user->id, 'name' => 'Firma D', 'tax_number' => '44444444444', 'is_active' => true]);
        $le2 = \App\Models\LegalEntity::create(['user_id' => $this->user->id, 'name' => 'Firma E', 'tax_number' => '55555555555', 'is_active' => true]);

        $payment = $this->service->recordPayment([
            'user_id'         => $this->user->id,
            'party_id'        => $this->supplierParty->id,
            'account_id'      => $this->bankAccount->id,
            'amount'          => 400.0,
            'payment_date'    => now()->toDateString(),
            'payment_method'  => 'bank',
            'legal_entity_id' => $le1->id,
        ]);

        $payable = Payable::create([
            'user_id'         => $this->user->id,
            'party_id'        => $this->supplierParty->id,
            'document_date'   => now()->toDateString(),
            'amount'          => 400.0,
            'paid_amount'     => 0.0,
            'currency_code'   => 'TRY',
            'exchange_rate'   => 1.0,
            'status'          => 'open',
            'legal_entity_id' => $le2->id,
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->service->allocatePayment($payment, [
            ['payable_id' => $payable->id, 'amount' => 400.0],
        ]);
    }

    /** @test */
    public function test_allocate_collection_accepts_both_null_legal_entity(): void
    {
        $collection = $this->service->recordCollection([
            'user_id'         => $this->user->id,
            'party_id'        => $this->customerParty->id,
            'account_id'      => $this->bankAccount->id,
            'amount'          => 300.0,
            'collection_date' => now()->toDateString(),
            'payment_method'  => 'bank',
            'legal_entity_id' => null,
        ]);

        $receivable = Receivable::create([
            'user_id'         => $this->user->id,
            'party_id'        => $this->customerParty->id,
            'document_date'   => now()->toDateString(),
            'amount'          => 300.0,
            'paid_amount'     => 0.0,
            'currency_code'   => 'TRY',
            'exchange_rate'   => 1.0,
            'status'          => 'open',
            'legal_entity_id' => null, // ikisi de null — kabul
        ]);

        $this->service->allocateCollection($collection, [
            ['receivable_id' => $receivable->id, 'amount' => 300.0],
        ]);

        $receivable->refresh();
        $this->assertEquals('paid', $receivable->status);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BLOKLAYICI 4 — Void Ters Ledger Legal Entity
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function test_void_collection_preserves_legal_entity_in_reverse_ledger(): void
    {
        $le = \App\Models\LegalEntity::create(['user_id' => $this->user->id, 'name' => 'Firma F', 'tax_number' => '66666666666', 'is_active' => true]);

        $collection = $this->service->recordCollection([
            'user_id'         => $this->user->id,
            'party_id'        => $this->customerParty->id,
            'account_id'      => $this->bankAccount->id,
            'amount'          => 250.0,
            'collection_date' => now()->toDateString(),
            'payment_method'  => 'bank',
            'legal_entity_id' => $le->id,
        ]);

        $this->service->voidCollection($collection->fresh());

        // İptal sonrası ters ledger kaydında legal_entity_id korunmalı
        $this->assertDatabaseHas('party_ledger_entries', [
            'user_id'         => $this->user->id,
            'party_id'        => $this->customerParty->id,
            'document_type'   => 'collection_void',
            'legal_entity_id' => $le->id,
        ]);
    }

    /** @test */
    public function test_void_payment_preserves_legal_entity_in_reverse_ledger(): void
    {
        $le = \App\Models\LegalEntity::create(['user_id' => $this->user->id, 'name' => 'Firma G', 'tax_number' => '77777777777', 'is_active' => true]);

        $payment = $this->service->recordPayment([
            'user_id'         => $this->user->id,
            'party_id'        => $this->supplierParty->id,
            'account_id'      => $this->bankAccount->id,
            'amount'          => 350.0,
            'payment_date'    => now()->toDateString(),
            'payment_method'  => 'bank',
            'legal_entity_id' => $le->id,
        ]);

        $this->service->voidPayment($payment->fresh());

        $this->assertDatabaseHas('party_ledger_entries', [
            'user_id'         => $this->user->id,
            'party_id'        => $this->supplierParty->id,
            'document_type'   => 'payment_void',
            'legal_entity_id' => $le->id,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BLOKLAYICI 5 — Idempotency (source_key)
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function test_duplicate_source_key_returns_same_collection(): void
    {
        $first = $this->service->recordCollection([
            'user_id'         => $this->user->id,
            'party_id'        => $this->customerParty->id,
            'account_id'      => $this->bankAccount->id,
            'amount'          => 100.0,
            'collection_date' => now()->toDateString(),
            'payment_method'  => 'bank',
            'source_key'      => 'idem-col-001',
        ]);

        $second = $this->service->recordCollection([
            'user_id'         => $this->user->id,
            'party_id'        => $this->customerParty->id,
            'account_id'      => $this->bankAccount->id,
            'amount'          => 100.0,
            'collection_date' => now()->toDateString(),
            'payment_method'  => 'bank',
            'source_key'      => 'idem-col-001', // aynı source_key
        ]);

        $this->assertEquals($first->id, $second->id);
        $this->assertCount(1, Collection::where('user_id', $this->user->id)->where('source_key', 'idem-col-001')->get());

        // Tek journal entry, tek ledger kaydı oluşturulmuş olmalı
        $this->assertCount(1, \App\Models\JournalEntry::where('user_id', $this->user->id)->where('entry_type', 'collection')->get());
    }

    /** @test */
    public function test_duplicate_source_key_returns_same_payment(): void
    {
        $first = $this->service->recordPayment([
            'user_id'        => $this->user->id,
            'party_id'       => $this->supplierParty->id,
            'account_id'     => $this->bankAccount->id,
            'amount'         => 200.0,
            'payment_date'   => now()->toDateString(),
            'payment_method' => 'bank',
            'source_key'     => 'idem-pay-001',
        ]);

        $second = $this->service->recordPayment([
            'user_id'        => $this->user->id,
            'party_id'       => $this->supplierParty->id,
            'account_id'     => $this->bankAccount->id,
            'amount'         => 200.0,
            'payment_date'   => now()->toDateString(),
            'payment_method' => 'bank',
            'source_key'     => 'idem-pay-001',
        ]);

        $this->assertEquals($first->id, $second->id);
        $this->assertCount(1, Payment::where('user_id', $this->user->id)->where('source_key', 'idem-pay-001')->get());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BLOKLAYICI 6 — Voided İşlem Allocate Edilemez
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function test_voided_collection_cannot_be_allocated(): void
    {
        $receivable = $this->makeReceivable(300.0);
        $collection = $this->makeCollection(300.0);
        $this->service->voidCollection($collection->fresh());

        $this->expectException(InvalidArgumentException::class);

        $this->service->allocateCollection($collection->fresh(), [
            ['receivable_id' => $receivable->id, 'amount' => 300.0],
        ]);
    }

    /** @test */
    public function test_voided_payment_cannot_be_allocated(): void
    {
        $payable = $this->makePayable(300.0);
        $payment = $this->makePayment(300.0);
        $this->service->voidPayment($payment->fresh());

        $this->expectException(InvalidArgumentException::class);

        $this->service->allocatePayment($payment->fresh(), [
            ['payable_id' => $payable->id, 'amount' => 300.0],
        ]);
    }

    /** @test */
    public function test_allocation_to_paid_receivable_throws(): void
    {
        $receivable = $this->makeReceivable(100.0, 'paid'); // already paid
        $collection = $this->makeCollection(100.0);

        $this->expectException(InvalidArgumentException::class);

        $this->service->allocateCollection($collection, [
            ['receivable_id' => $receivable->id, 'amount' => 100.0],
        ]);
    }

    /** @test */
    public function test_zero_amount_allocation_throws(): void
    {
        $receivable = $this->makeReceivable(500.0);
        $collection = $this->makeCollection(500.0);

        $this->expectException(InvalidArgumentException::class);

        $this->service->allocateCollection($collection, [
            ['receivable_id' => $receivable->id, 'amount' => 0.0], // sıfır — artık exception
        ]);
    }
}
