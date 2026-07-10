<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_is_blocked_when_accounting_enabled_is_false(): void
    {
        config()->set('marketplace.features.accounting_enabled', false);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $this->actingAs($user)
            ->get(route('accounting.reports'))
            ->assertStatus(404);
    }

    public function test_page_renders_when_accounting_enabled_is_true(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $this->actingAs($user)
            ->get(route('accounting.reports'))
            ->assertStatus(200)
            ->assertSeeLivewire('accounting.reports');
    }

    public function test_trial_balance_calculation(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        // Seed default charts of accounts
        (new \Database\Seeders\ChartOfAccountsSeeder())->runForUser($user->id);

        $cash = Account::where('user_id', $user->id)->where('code', '100')->first();
        $bank = Account::where('user_id', $user->id)->where('code', '102')->first();

        // Create a journal entry: Debit Cash 500, Credit Bank 500
        $journalService = app(\App\Services\Accounting\JournalService::class);
        $journalService->postManual([
            'user_id' => $user->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Transfer',
        ], [
            ['account_id' => $cash->id, 'debit_amount' => 500.00],
            ['account_id' => $bank->id, 'credit_amount' => 500.00],
        ]);

        Livewire::actingAs($user)
            ->test('accounting.reports')
            ->set('reportType', 'trial_balance')
            ->call('runReport')
            ->assertSet('message', '')
            ->assertSee('Kasa')
            ->assertSee('Bankalar');
    }

    public function test_balance_sheet_calculation(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        // Seed default charts of accounts
        (new \Database\Seeders\ChartOfAccountsSeeder())->runForUser($user->id);

        $cash = Account::where('user_id', $user->id)->where('code', '100')->first();
        $bank = Account::where('user_id', $user->id)->where('code', '102')->first();

        $journalService = app(\App\Services\Accounting\JournalService::class);
        $journalService->postManual([
            'user_id' => $user->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Transfer',
        ], [
            ['account_id' => $cash->id, 'debit_amount' => 300.00],
            ['account_id' => $bank->id, 'credit_amount' => 300.00],
        ]);

        Livewire::actingAs($user)
            ->test('accounting.reports')
            ->set('reportType', 'balance_sheet')
            ->call('runReport')
            ->assertSee('AKTİFLER')
            ->assertSee('PASİFLER');
    }

    public function test_income_statement_calculation(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        // Seed default charts of accounts
        (new \Database\Seeders\ChartOfAccountsSeeder())->runForUser($user->id);

        $revenue = Account::where('user_id', $user->id)->where('code', '600')->first();
        $expense = Account::where('user_id', $user->id)->where('code', '760')->first();

        $cash = Account::where('user_id', $user->id)->where('code', '100')->first();

        $journalService = app(\App\Services\Accounting\JournalService::class);
        $journalService->postManual([
            'user_id' => $user->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Revenue',
        ], [
            ['account_id' => $cash->id, 'debit_amount' => 1000.00],
            ['account_id' => $revenue->id, 'credit_amount' => 1000.00],
        ]);

        $journalService->postManual([
            'user_id' => $user->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Expense',
        ], [
            ['account_id' => $expense->id, 'debit_amount' => 400.00],
            ['account_id' => $cash->id, 'credit_amount' => 400.00],
        ]);

        Livewire::actingAs($user)
            ->test('accounting.reports')
            ->set('reportType', 'income_statement')
            ->call('runReport')
            ->assertSee('Brüt Satış Gelirleri')
            ->assertSee('Faaliyet Giderleri');
    }

    public function test_excel_export_generation(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        // Seed default charts of accounts
        (new \Database\Seeders\ChartOfAccountsSeeder())->runForUser($user->id);

        $response = Livewire::actingAs($user)
            ->test('accounting.reports')
            ->set('reportType', 'trial_balance')
            ->call('runReport')
            ->call('exportExcel');

        $this->assertNotNull($response);
    }
}
