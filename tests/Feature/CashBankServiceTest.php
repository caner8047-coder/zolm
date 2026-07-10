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
}
