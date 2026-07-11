<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountGroup;
use App\Models\JournalEntry;
use App\Models\Party;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_groups_table_exists(): void
    {
        $this->assertTrue(\Schema::hasTable('account_groups'));
    }

    public function test_account_groups_has_expected_columns(): void
    {
        $columns = \Schema::getColumnListing('account_groups');
        foreach (['id', 'user_id', 'parent_id', 'code', 'name', 'type', 'normal_balance', 'sort_order', 'is_active', 'created_at', 'updated_at'] as $col) {
            $this->assertContains($col, $columns, "account_groups.$col kolonu eksik");
        }
    }

    public function test_accounts_table_exists(): void
    {
        $this->assertTrue(\Schema::hasTable('accounts'));
    }

    public function test_accounts_has_expected_columns(): void
    {
        $columns = \Schema::getColumnListing('accounts');
        foreach (['id', 'user_id', 'account_group_id', 'legal_entity_id', 'code', 'name', 'type', 'normal_balance', 'is_cash_account', 'is_bank_account', 'is_ar_account', 'is_ap_account', 'is_system', 'is_active', 'currency_code'] as $col) {
            $this->assertContains($col, $columns, "accounts.$col kolonu eksik");
        }
    }

    public function test_journal_entries_table_exists(): void
    {
        $this->assertTrue(\Schema::hasTable('journal_entries'));
    }

    public function test_journal_entries_has_expected_columns(): void
    {
        $columns = \Schema::getColumnListing('journal_entries');
        foreach (['id', 'user_id', 'legal_entity_id', 'party_id', 'entry_type', 'source_type', 'source_id', 'source_key', 'reference_number', 'entry_date', 'due_date', 'description', 'currency_code', 'exchange_rate', 'status', 'voided_at', 'voided_by', 'void_reason', 'posted_at'] as $col) {
            $this->assertContains($col, $columns, "journal_entries.$col kolonu eksik");
        }
    }

    public function test_journal_lines_table_exists(): void
    {
        $this->assertTrue(\Schema::hasTable('journal_lines'));
    }

    public function test_journal_lines_has_expected_columns(): void
    {
        $columns = \Schema::getColumnListing('journal_lines');
        foreach (['id', 'user_id', 'journal_entry_id', 'account_id', 'debit_amount', 'credit_amount', 'exchange_rate', 'debit_base_amount', 'credit_base_amount', 'party_id', 'sort_order', 'description'] as $col) {
            $this->assertContains($col, $columns, "journal_lines.$col kolonu eksik");
        }
    }

    public function test_account_group_user_code_unique(): void
    {
        $user = User::factory()->create();
        AccountGroup::create(['user_id' => $user->id, 'code' => 'X1', 'name' => 'Test', 'type' => 'asset', 'normal_balance' => 'debit']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        AccountGroup::create(['user_id' => $user->id, 'code' => 'X1', 'name' => 'Test2', 'type' => 'asset', 'normal_balance' => 'debit']);
    }

    public function test_account_user_code_unique(): void
    {
        $user = User::factory()->create();
        Account::create(['user_id' => $user->id, 'code' => '999', 'name' => 'Test', 'type' => 'asset', 'normal_balance' => 'debit']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Account::create(['user_id' => $user->id, 'code' => '999', 'name' => 'Test2', 'type' => 'asset', 'normal_balance' => 'debit']);
    }

    public function test_different_users_can_have_same_account_code(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Account::create(['user_id' => $user1->id, 'code' => '100', 'name' => 'Kasa', 'type' => 'asset', 'normal_balance' => 'debit']);
        Account::create(['user_id' => $user2->id, 'code' => '100', 'name' => 'Kasa', 'type' => 'asset', 'normal_balance' => 'debit']);

        $this->assertDatabaseCount('accounts', 2);
    }

    public function test_journal_entry_source_key_unique_per_user(): void
    {
        $user = User::factory()->create();
        $acct1 = Account::create(['user_id' => $user->id, 'code' => 'D1', 'name' => 'Debit Acc', 'type' => 'asset', 'normal_balance' => 'debit']);
        $acct2 = Account::create(['user_id' => $user->id, 'code' => 'C1', 'name' => 'Credit Acc', 'type' => 'liability', 'normal_balance' => 'credit']);

        \App\Models\JournalEntry::create([
            'user_id'    => $user->id,
            'entry_date' => now()->toDateString(),
            'source_key' => 'test-key-001',
            'status'     => 'posted',
            'posted_at'  => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        \App\Models\JournalEntry::create([
            'user_id'    => $user->id,
            'entry_date' => now()->toDateString(),
            'source_key' => 'test-key-001',
            'status'     => 'posted',
            'posted_at'  => now(),
        ]);
    }

    public function test_artisan_accounting_seed_demo_runs_successfully(): void
    {
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $this->artisan('accounting:seed-demo', ['--user' => $user->id])
            ->assertExitCode(0);

        // Check seeded accounts
        $this->assertTrue(Account::where('user_id', $user->id)->where('code', '120')->exists());
        $this->assertTrue(Account::where('user_id', $user->id)->where('code', '320')->exists());

        // Check seeded parties
        $this->assertTrue(Party::where('user_id', $user->id)->where('primary_email', 'musteri@example.com')->exists());
        $this->assertTrue(Party::where('user_id', $user->id)->where('primary_email', 'tedarikci@example.com')->exists());

        // Check seeded warehouse
        $this->assertTrue(Warehouse::where('user_id', $user->id)->where('code', 'demo-depo-merkez')->exists());

        // Check seeded opening journal entry
        $this->assertTrue(JournalEntry::where('user_id', $user->id)->where('source_key', 'demo_journal_entry_1')->exists());
    }

    public function test_artisan_accounting_seed_demo_is_idempotent(): void
    {
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        // Run once
        $this->artisan('accounting:seed-demo', ['--user' => $user->id])->assertExitCode(0);
        $count = JournalEntry::where('user_id', $user->id)->count();

        // Run twice
        $this->artisan('accounting:seed-demo', ['--user' => $user->id])->assertExitCode(0);
        $this->assertEquals($count, JournalEntry::where('user_id', $user->id)->count());
    }
}
