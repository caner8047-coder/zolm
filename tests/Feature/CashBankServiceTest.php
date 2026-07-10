<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\MoneyTransfer;
use App\Models\User;
use App\Services\Accounting\CashBankService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashBankServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private CashBankService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['is_active' => true]);

        $seeder = new ChartOfAccountsSeeder();
        $seeder->runForUser($this->user->id);

        $this->service = app(CashBankService::class);
    }

    public function test_create_cash_account_sets_correct_code_sequence(): void
    {
        $cash1 = $this->service->createCashAccount($this->user->id, 'Merkez Kasa');
        $cash2 = $this->service->createCashAccount($this->user->id, 'Şube Kasa');

        $this->assertDatabaseHas('cash_accounts', ['id' => $cash1->id, 'name' => 'Merkez Kasa']);
        $this->assertDatabaseHas('cash_accounts', ['id' => $cash2->id, 'name' => 'Şube Kasa']);

        $this->assertEquals('100.01', $cash1->account->code);
        $this->assertEquals('100.02', $cash2->account->code);
    }

    public function test_create_bank_account_sets_correct_code_sequence(): void
    {
        $bank1 = $this->service->createBankAccount($this->user->id, [
            'bank_name' => 'Garanti BBVA',
            'account_number' => '4444555',
            'iban' => 'TR112233445566778899001122',
        ]);

        $bank2 = $this->service->createBankAccount($this->user->id, [
            'bank_name' => 'Akbank',
            'account_number' => '9998887',
        ]);

        $this->assertDatabaseHas('bank_accounts', ['id' => $bank1->id, 'bank_name' => 'Garanti BBVA']);
        $this->assertEquals('102.01', $bank1->account->code);
        $this->assertEquals('102.02', $bank2->account->code);
    }

    public function test_transfer_funds_creates_transfer_and_balanced_journal_entry(): void
    {
        $cash = $this->service->createCashAccount($this->user->id, 'Kasa');
        $bank = $this->service->createBankAccount($this->user->id, ['bank_name' => 'Yapı Kredi']);

        // Transfer 500 from Cash to Bank (Kasa'dan Bankaya para yatırma)
        $transfer = $this->service->transferFunds([
            'user_id'         => $this->user->id,
            'from_account_id' => $cash->account->id,
            'to_account_id'   => $bank->account->id,
            'amount'          => 500.00,
            'transfer_date'   => now()->toDateString(),
            'description'     => 'Kasadan bankaya virman yatırma',
        ]);

        $this->assertDatabaseHas('money_transfers', [
            'id'              => $transfer->id,
            'from_account_id' => $cash->account->id,
            'to_account_id'   => $bank->account->id,
            'amount'          => 500.00,
        ]);

        // General Ledger: debit bank.account (+500), credit cash.account (-500)
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $transfer->journal_entry_id,
            'account_id'       => $bank->account->id,
            'debit_amount'     => 500.00,
            'credit_amount'    => 0.00,
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $transfer->journal_entry_id,
            'account_id'       => $cash->account->id,
            'debit_amount'     => 0.00,
            'credit_amount'    => 500.00,
        ]);
    }

    public function test_transfer_funds_same_account_is_rejected(): void
    {
        $cash = $this->service->createCashAccount($this->user->id, 'Kasa');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/aynı olamaz/i');

        $this->service->transferFunds([
            'user_id'         => $this->user->id,
            'from_account_id' => $cash->account->id,
            'to_account_id'   => $cash->account->id,
            'amount'          => 200.00,
            'transfer_date'   => now()->toDateString(),
        ]);
    }

    public function test_transfer_funds_zero_or_negative_amount_is_rejected(): void
    {
        $cash = $this->service->createCashAccount($this->user->id, 'Kasa');
        $bank = $this->service->createBankAccount($this->user->id, ['bank_name' => 'İş Bankası']);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->transferFunds([
            'user_id'         => $this->user->id,
            'from_account_id' => $cash->account->id,
            'to_account_id'   => $bank->account->id,
            'amount'          => -10.00,
            'transfer_date'   => now()->toDateString(),
        ]);
    }

    public function test_cash_bank_statement_calculates_correct_running_balance(): void
    {
        $cash = $this->service->createCashAccount($this->user->id, 'Merkez Kasa');
        $bank = $this->service->createBankAccount($this->user->id, ['bank_name' => 'İş Bankası']);

        // Let's deposit some money into Cash first (via manual Journal entry)
        $journalService = app(\App\Services\Accounting\JournalService::class);
        $capitalAccount = Account::where('user_id', $this->user->id)->where('code', '600')->first(); // use 600 as standard dummy for testing

        $journalService->postManual(
            ['user_id' => $this->user->id, 'entry_date' => now()->subDays(5)->toDateString()],
            [
                ['account_id' => $cash->account->id, 'debit_amount' => 1000.00],
                ['account_id' => $capitalAccount->id, 'credit_amount' => 1000.00],
            ]
        );

        // Virman 300 to Bank
        $this->service->transferFunds([
            'user_id'         => $this->user->id,
            'from_account_id' => $cash->account->id,
            'to_account_id'   => $bank->account->id,
            'amount'          => 300.00,
            'transfer_date'   => now()->subDays(2)->toDateString(),
        ]);

        $statement = $this->service->getAccountStatement($cash->account);

        $this->assertCount(2, $statement);

        // 1st item: deposit 1000
        $this->assertEquals(1000.00, $statement[0]['debit']);
        $this->assertEquals(0.00, $statement[0]['credit']);
        $this->assertEquals(1000.00, $statement[0]['balance']);

        // 2nd item: transfer out 300
        $this->assertEquals(0.00, $statement[1]['debit']);
        $this->assertEquals(300.00, $statement[1]['credit']);
        $this->assertEquals(700.00, $statement[1]['balance']);
    }

    public function test_legal_entity_id_another_user_is_rejected(): void
    {
        $otherUser = User::factory()->create();
        $otherLe = \App\Models\LegalEntity::create([
            'user_id' => $otherUser->id,
            'name' => 'Other Corp',
            'tax_number' => '1234567890',
            'is_active' => true,
        ]);

        // 1. cash account creation with other user's legal entity should fail
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->service->createCashAccount($this->user->id, 'Merkez Kasa', 'TRY', $otherLe->id);
    }

    public function test_legal_entity_id_another_user_bank_rejected(): void
    {
        $otherUser = User::factory()->create();
        $otherLe = \App\Models\LegalEntity::create([
            'user_id' => $otherUser->id,
            'name' => 'Other Corp',
            'tax_number' => '1234567890',
            'is_active' => true,
        ]);

        // 2. bank account creation with other user's legal entity should fail
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->service->createBankAccount($this->user->id, [
            'bank_name' => 'Garanti',
            'legal_entity_id' => $otherLe->id,
        ]);
    }

    public function test_transfer_funds_source_key_idempotency(): void
    {
        $cash = $this->service->createCashAccount($this->user->id, 'Kasa');
        $bank = $this->service->createBankAccount($this->user->id, ['bank_name' => 'Banka']);

        $data = [
            'user_id'         => $this->user->id,
            'from_account_id' => $cash->account->id,
            'to_account_id'   => $bank->account->id,
            'amount'          => 150.00,
            'transfer_date'   => now()->toDateString(),
            'source_key'      => 'transfer-key-999',
            'reference_number'=> 'REF999',
        ];

        $t1 = $this->service->transferFunds($data);
        $t2 = $this->service->transferFunds($data);

        $this->assertEquals($t1->id, $t2->id);

        $transfersCount = MoneyTransfer::where('user_id', $this->user->id)
            ->where('source_key', 'transfer-key-999')
            ->count();
        $this->assertEquals(1, $transfersCount);
    }

    public function test_transfer_funds_exchange_rate_negative_or_zero_rejected(): void
    {
        $cash = $this->service->createCashAccount($this->user->id, 'Kasa');
        $bank = $this->service->createBankAccount($this->user->id, ['bank_name' => 'Banka']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/döviz kuru/i');

        $this->service->transferFunds([
            'user_id'         => $this->user->id,
            'from_account_id' => $cash->account->id,
            'to_account_id'   => $bank->account->id,
            'amount'          => 150.00,
            'transfer_date'   => now()->toDateString(),
            'exchange_rate'   => -0.5,
        ]);
    }

    public function test_transfer_funds_other_user_account_rejected(): void
    {
        $otherUser = User::factory()->create();
        (new ChartOfAccountsSeeder())->runForUser($otherUser->id);
        $otherCash = app(CashBankService::class)->createCashAccount($otherUser->id, 'Other Kasa');

        $cash = $this->service->createCashAccount($this->user->id, 'My Kasa');

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->service->transferFunds([
            'user_id'         => $this->user->id,
            'from_account_id' => $cash->account->id,
            'to_account_id'   => $otherCash->account->id, // other user account
            'amount'          => 100.00,
            'transfer_date'   => now()->toDateString(),
        ]);
    }

    public function test_transfer_funds_passive_account_rejected(): void
    {
        $cash = $this->service->createCashAccount($this->user->id, 'Kasa');
        $bank = $this->service->createBankAccount($this->user->id, ['bank_name' => 'Banka']);

        $cash->account->update(['is_active' => false]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/pasif/i');

        $this->service->transferFunds([
            'user_id'         => $this->user->id,
            'from_account_id' => $cash->account->id,
            'to_account_id'   => $bank->account->id,
            'amount'          => 50.00,
            'transfer_date'   => now()->toDateString(),
        ]);
    }

    public function test_transfer_funds_non_cash_bank_account_rejected(): void
    {
        $cash = $this->service->createCashAccount($this->user->id, 'Kasa');
        $arAccount = Account::where('user_id', $this->user->id)->where('is_ar_account', true)->first();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/kasa veya banka hesabı olmalıdır/i');

        $this->service->transferFunds([
            'user_id'         => $this->user->id,
            'from_account_id' => $cash->account->id,
            'to_account_id'   => $arAccount->id, // AR account (non-cash/bank)
            'amount'          => 50.00,
            'transfer_date'   => now()->toDateString(),
        ]);
    }

    public function test_void_transfer_voids_journal_and_statement(): void
    {
        $cash = $this->service->createCashAccount($this->user->id, 'Kasa');
        $bank = $this->service->createBankAccount($this->user->id, ['bank_name' => 'Banka']);

        // Let's deposit some money into Cash first (via manual Journal entry)
        $journalService = app(\App\Services\Accounting\JournalService::class);
        $capitalAccount = Account::where('user_id', $this->user->id)->where('code', '600')->first();

        $journalService->postManual(
            ['user_id' => $this->user->id, 'entry_date' => now()->subDays(5)->toDateString()],
            [
                ['account_id' => $cash->account->id, 'debit_amount' => 1000.00],
                ['account_id' => $capitalAccount->id, 'credit_amount' => 1000.00],
            ]
        );

        $transfer = $this->service->transferFunds([
            'user_id'         => $this->user->id,
            'from_account_id' => $cash->account->id,
            'to_account_id'   => $bank->account->id,
            'amount'          => 300.00,
            'transfer_date'   => now()->toDateString(),
        ]);

        $this->assertEquals(700.0, $cash->account->balance());
        $this->assertEquals(300.0, $bank->account->balance());

        // Void the transfer with user id context
        $this->service->voidTransfer($transfer, 'Hatalı virman', $this->user->id);

        $this->assertEquals('voided', $transfer->fresh()->status);
        $this->assertEquals('voided', $transfer->journalEntry->fresh()->status);

        // Balance should go back to 1000 and 0
        $this->assertEquals(1000.0, $cash->account->fresh()->balance());
        $this->assertEquals(0.0, $bank->account->fresh()->balance());

        // Statement should only show 1 item (the deposit)
        $statement = $this->service->getAccountStatement($cash->account);
        $this->assertCount(1, $statement);
    }

    public function test_void_transfer_twice_rejected(): void
    {
        $cash = $this->service->createCashAccount($this->user->id, 'Kasa');
        $bank = $this->service->createBankAccount($this->user->id, ['bank_name' => 'Banka']);

        $transfer = $this->service->transferFunds([
            'user_id'         => $this->user->id,
            'from_account_id' => $cash->account->id,
            'to_account_id'   => $bank->account->id,
            'amount'          => 10.00,
            'transfer_date'   => now()->toDateString(),
        ]);

        $this->service->voidTransfer($transfer, null, $this->user->id);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/zaten iptal edilmiş/i');

        $this->service->voidTransfer($transfer, null, $this->user->id);
    }

    public function test_account_statement_date_filter(): void
    {
        $cash = $this->service->createCashAccount($this->user->id, 'Kasa');
        $capitalAccount = Account::where('user_id', $this->user->id)->where('code', '600')->first();
        $journalService = app(\App\Services\Accounting\JournalService::class);

        // Day 1: Deposit 100
        $journalService->postManual(
            ['user_id' => $this->user->id, 'entry_date' => '2026-07-01'],
            [
                ['account_id' => $cash->account->id, 'debit_amount' => 100.00],
                ['account_id' => $capitalAccount->id, 'credit_amount' => 100.00],
            ]
        );

        // Day 2: Deposit 200
        $journalService->postManual(
            ['user_id' => $this->user->id, 'entry_date' => '2026-07-02'],
            [
                ['account_id' => $cash->account->id, 'debit_amount' => 200.00],
                ['account_id' => $capitalAccount->id, 'credit_amount' => 200.00],
            ]
        );

        // Day 3: Deposit 300
        $journalService->postManual(
            ['user_id' => $this->user->id, 'entry_date' => '2026-07-03'],
            [
                ['account_id' => $cash->account->id, 'debit_amount' => 300.00],
                ['account_id' => $capitalAccount->id, 'credit_amount' => 300.00],
            ]
        );

        $statementAll = $this->service->getAccountStatement($cash->account);
        $this->assertCount(3, $statementAll);

        $statementFiltered = $this->service->getAccountStatement($cash->account, '2026-07-02', '2026-07-02');
        $this->assertCount(1, $statementFiltered);
        $this->assertEquals(200.00, $statementFiltered[0]['debit']);
    }

    public function test_void_transfer_other_user_rejected(): void
    {
        $cash = $this->service->createCashAccount($this->user->id, 'Kasa');
        $bank = $this->service->createBankAccount($this->user->id, ['bank_name' => 'Banka']);

        $transfer = $this->service->transferFunds([
            'user_id'         => $this->user->id,
            'from_account_id' => $cash->account->id,
            'to_account_id'   => $bank->account->id,
            'amount'          => 10.00,
            'transfer_date'   => now()->toDateString(),
        ]);

        $otherUser = User::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/yetkiniz yok/i');

        $this->service->voidTransfer($transfer, 'Hatalı', $otherUser->id);
    }

    public function test_void_transfer_no_user_context_rejected(): void
    {
        $cash = $this->service->createCashAccount($this->user->id, 'Kasa');
        $bank = $this->service->createBankAccount($this->user->id, ['bank_name' => 'Banka']);

        $transfer = $this->service->transferFunds([
            'user_id'         => $this->user->id,
            'from_account_id' => $cash->account->id,
            'to_account_id'   => $bank->account->id,
            'amount'          => 10.00,
            'transfer_date'   => now()->toDateString(),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/kullanıcı bilgisi bulunamadı/i');

        // Force no authenticated user and no parameter
        auth()->logout();
        $this->service->voidTransfer($transfer, 'Hatalı', null);
    }

    public function test_transfer_funds_different_legal_entities_rejected(): void
    {
        $le1 = \App\Models\LegalEntity::create([
            'user_id' => $this->user->id, 'name' => 'A Corp', 'tax_number' => '1111111111', 'is_active' => true
        ]);
        $le2 = \App\Models\LegalEntity::create([
            'user_id' => $this->user->id, 'name' => 'B Corp', 'tax_number' => '2222222222', 'is_active' => true
        ]);

        $cash = $this->service->createCashAccount($this->user->id, 'Kasa A', 'TRY', $le1->id);
        $bank = $this->service->createBankAccount($this->user->id, [
            'bank_name' => 'Banka B',
            'legal_entity_id' => $le2->id,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/farklı yasal birlik/i');

        $this->service->transferFunds([
            'user_id'         => $this->user->id,
            'from_account_id' => $cash->account->id,
            'to_account_id'   => $bank->account->id,
            'amount'          => 50.00,
            'transfer_date'   => now()->toDateString(),
        ]);
    }

    public function test_transfer_funds_same_legal_entity_implicit(): void
    {
        $le = \App\Models\LegalEntity::create([
            'user_id' => $this->user->id, 'name' => 'A Corp', 'tax_number' => '1111111111', 'is_active' => true
        ]);

        $cash = $this->service->createCashAccount($this->user->id, 'Kasa A', 'TRY', $le->id);
        $bank = $this->service->createBankAccount($this->user->id, [
            'bank_name' => 'Banka B',
            'legal_entity_id' => $le->id,
        ]);

        // Virman WITHOUT passing legal_entity_id explicitly
        $transfer = $this->service->transferFunds([
            'user_id'         => $this->user->id,
            'from_account_id' => $cash->account->id,
            'to_account_id'   => $bank->account->id,
            'amount'          => 50.00,
            'transfer_date'   => now()->toDateString(),
        ]);

        $this->assertEquals($le->id, $transfer->legal_entity_id);
        $this->assertEquals($le->id, $transfer->journalEntry->legal_entity_id);
    }

    public function test_transfer_funds_explicit_wrong_legal_entity_rejected(): void
    {
        $le = \App\Models\LegalEntity::create([
            'user_id' => $this->user->id, 'name' => 'A Corp', 'tax_number' => '1111111111', 'is_active' => true
        ]);
        $otherLe = \App\Models\LegalEntity::create([
            'user_id' => $this->user->id, 'name' => 'Other Corp', 'tax_number' => '3333333333', 'is_active' => true
        ]);

        $cash = $this->service->createCashAccount($this->user->id, 'Kasa A', 'TRY', $le->id);
        $bank = $this->service->createBankAccount($this->user->id, [
            'bank_name' => 'Banka B',
            'legal_entity_id' => $le->id,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/yasal birliği ile çakışıyor/i');

        $this->service->transferFunds([
            'user_id'         => $this->user->id,
            'from_account_id' => $cash->account->id,
            'to_account_id'   => $bank->account->id,
            'amount'          => 50.00,
            'transfer_date'   => now()->toDateString(),
            'legal_entity_id' => $otherLe->id, // explicit wrong legal entity
        ]);
    }
}
