<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\MoneyTransfer;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;


class CashBankTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_is_blocked_when_accounting_enabled_is_false(): void
    {
        config()->set('marketplace.features.accounting_enabled', false);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $this->actingAs($user)
            ->get(route('accounting.cash-bank'))
            ->assertStatus(404);
    }

    public function test_page_renders_when_accounting_enabled_is_true(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $this->actingAs($user)
            ->get(route('accounting.cash-bank'))
            ->assertStatus(200)
            ->assertSeeLivewire('accounting.cash-bank');
    }

    public function test_creating_cash_account(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        Livewire::actingAs($user)
            ->test('accounting.cash-bank')
            ->set('cashName', 'Merkez Kasa TL')
            ->call('createCashAccount')
            ->assertSet('messageType', 'success');

        $this->assertDatabaseHas('cash_accounts', [
            'user_id' => $user->id,
            'name' => 'Merkez Kasa TL',
        ]);

        $this->assertDatabaseHas('accounts', [
            'user_id' => $user->id,
            'code' => '100.01',
            'name' => 'Merkez Kasa TL',
            'is_cash_account' => true,
        ]);
    }

    public function test_creating_bank_account(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        Livewire::actingAs($user)
            ->test('accounting.cash-bank')
            ->set('bankName', 'Garanti BBVA')
            ->set('branchName', 'Kadıköy')
            ->set('accountNumber', '98765432')
            ->set('iban', 'TR000000000000000000009876')
            ->set('currencyCode', 'TRY')
            ->call('createBankAccount')
            ->assertSet('messageType', 'success');

        $this->assertDatabaseHas('bank_accounts', [
            'user_id' => $user->id,
            'bank_name' => 'Garanti BBVA',
            'iban' => 'TR000000000000000000009876',
        ]);

        $this->assertDatabaseHas('accounts', [
            'user_id' => $user->id,
            'code' => '102.01',
            'is_bank_account' => true,
        ]);
    }

    public function test_executing_transfer_funds(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        // Setup cash and bank accounts first
        $cash = Account::create([
            'user_id' => $user->id,
            'code' => '100.01',
            'name' => 'Kasa',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_cash_account' => true,
        ]);

        $bank = Account::create([
            'user_id' => $user->id,
            'code' => '102.01',
            'name' => 'Banka',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_bank_account' => true,
        ]);

        Livewire::actingAs($user)
            ->test('accounting.cash-bank')
            ->set('fromAccountId', $cash->id)
            ->set('toAccountId', $bank->id)
            ->set('transferAmount', 450.00)
            ->set('transferDate', now()->toDateString())
            ->set('transferDescription', 'Kasadan bankaya aktarım')
            ->call('executeTransfer')
            ->assertSet('messageType', 'success');

        $this->assertDatabaseHas('money_transfers', [
            'user_id' => $user->id,
            'from_account_id' => $cash->id,
            'to_account_id' => $bank->id,
            'amount' => 450.00,
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'user_id' => $user->id,
            'entry_type' => 'bank_transfer',
            'status' => 'posted',
        ]);
    }

    public function test_transfer_same_account_fails(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $cash = Account::create([
            'user_id' => $user->id,
            'code' => '100.01',
            'name' => 'Kasa',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_cash_account' => true,
        ]);

        Livewire::actingAs($user)
            ->test('accounting.cash-bank')
            ->set('fromAccountId', $cash->id)
            ->set('toAccountId', $cash->id)
            ->set('transferAmount', 450.00)
            ->call('executeTransfer')
            ->assertSet('messageType', 'error');
    }

    public function test_tenant_isolation_on_transfer(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user1 = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $user2 = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $cash1 = Account::create([
            'user_id' => $user1->id,
            'code' => '100.01',
            'name' => 'User1 Kasa',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_cash_account' => true,
        ]);

        $cash2 = Account::create([
            'user_id' => $user2->id,
            'code' => '100.01',
            'name' => 'User2 Kasa',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_cash_account' => true,
        ]);

        // Attempting to transfer from User 2 cash to User 1 cash while logged in as User 1 should be blocked
        Livewire::actingAs($user1)
            ->test('accounting.cash-bank')
            ->set('fromAccountId', $cash2->id)
            ->set('toAccountId', $cash1->id)
            ->set('transferAmount', 100.00)
            ->call('executeTransfer')
            ->assertSet('messageType', 'error');
    }

    public function test_dropdown_excludes_other_users_and_non_cash_bank_accounts(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user1 = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $user2 = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        (new ChartOfAccountsSeeder())->runForUser($user1->id);
        (new ChartOfAccountsSeeder())->runForUser($user2->id);

        // User 2 cash account
        $cash2 = Account::create([
            'user_id' => $user2->id,
            'code' => '100.02',
            'name' => 'User2 Kasa',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_cash_account' => true,
        ]);

        // User 1 cash account
        $cash1 = Account::create([
            'user_id' => $user1->id,
            'code' => '100.01',
            'name' => 'User1 Kasa',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_cash_account' => true,
        ]);

        // Non cash/bank account (e.g. 120 AR account)
        $arAccount = Account::where('user_id', $user1->id)->where('is_ar_account', true)->first();

        $component = Livewire::actingAs($user1)
            ->test('accounting.cash-bank');

        $accounts = $component->instance()->transferableAccounts;

        // Should contain user 1 cash
        $this->assertTrue($accounts->contains('id', $cash1->id));
        // Should NOT contain user 2 cash
        $this->assertFalse($accounts->contains('id', $cash2->id));
        // Should NOT contain non cash/bank account
        $this->assertFalse($accounts->contains('id', $arAccount->id));
    }

    public function test_search_filter_does_not_leak_tenant_records(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user1 = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $user2 = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        // User 2 cash with specific name
        $cash2 = CashAccount::create([
            'user_id' => $user2->id,
            'account_id' => Account::create([
                'user_id' => $user2->id,
                'code' => '100.02',
                'name' => 'LEAK_SEARCH_TERM',
                'type' => 'asset',
                'normal_balance' => 'debit',
                'is_cash_account' => true,
            ])->id,
            'name' => 'LEAK_SEARCH_TERM',
            'currency_code' => 'TRY',
            'is_active' => true,
        ]);

        $component = Livewire::actingAs($user1)
            ->test('accounting.cash-bank')
            ->set('search', 'LEAK_SEARCH_TERM');

        $this->assertCount(0, $component->instance()->cashAccounts);
    }


    public function test_void_transfer_action_via_ui(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        (new ChartOfAccountsSeeder())->runForUser($user->id);

        $service = app(\App\Services\Accounting\CashBankService::class);
        $cash = $service->createCashAccount($user->id, 'Kasa');
        $bank = $service->createBankAccount($user->id, ['bank_name' => 'Banka']);

        $transfer = $service->transferFunds([
            'user_id'         => $user->id,
            'from_account_id' => $cash->account->id,
            'to_account_id'   => $bank->account->id,
            'amount'          => 100.00,
            'transfer_date'   => now()->toDateString(),
        ]);

        Livewire::actingAs($user)
            ->test('accounting.cash-bank')
            ->call('voidTransfer', $transfer->id)
            ->assertSet('messageType', 'success');

        $this->assertEquals('voided', $transfer->fresh()->status);
    }
}
