<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\User;
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
}
