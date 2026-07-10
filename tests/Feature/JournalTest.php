<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Party;
use App\Models\User;
use App\Services\Accounting\JournalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class JournalTest extends TestCase
{
    use RefreshDatabase;

    private function makeAccounts(User $user): array
    {
        $debit = Account::create([
            'user_id'        => $user->id,
            'code'           => '100.01',
            'name'           => 'Kasa',
            'type'           => 'asset',
            'normal_balance' => 'debit',
        ]);
        $credit = Account::create([
            'user_id'        => $user->id,
            'code'           => '600.01',
            'name'           => 'Yurt İçi Satışlar',
            'type'           => 'revenue',
            'normal_balance' => 'credit',
        ]);
        return [$debit, $credit];
    }

    public function test_route_is_blocked_when_accounting_enabled_is_false(): void
    {
        config()->set('marketplace.features.accounting_enabled', false);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $this->actingAs($user)
            ->get(route('accounting.journal'))
            ->assertStatus(404);
    }

    public function test_page_renders_when_accounting_enabled_is_true(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $this->actingAs($user)
            ->get(route('accounting.journal'))
            ->assertStatus(200)
            ->assertSeeLivewire('accounting.journal');
    }

    public function test_manual_journal_posting_is_validated_and_posted(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        [$debit, $credit] = $this->makeAccounts($user);

        Livewire::actingAs($user)
            ->test('accounting.journal')
            ->set('entryDate', now()->toDateString())
            ->set('entryType', 'manual')
            ->set('referenceNumber', 'REF-99')
            ->set('description', 'Test entry')
            ->set('lines', [
                ['account_id' => $debit->id, 'debit_amount' => 120.00, 'credit_amount' => 0.00, 'description' => 'Borç satırı', 'party_id' => null],
                ['account_id' => $credit->id, 'debit_amount' => 0.00, 'credit_amount' => 120.00, 'description' => 'Alacak satırı', 'party_id' => null],
            ])
            ->call('postJournalEntry')
            ->assertSet('messageType', 'success');

        $this->assertDatabaseHas('journal_entries', [
            'user_id' => $user->id,
            'reference_number' => 'REF-99',
            'description' => 'Test entry',
            'status' => 'posted',
        ]);

        $this->assertDatabaseCount('journal_lines', 2);
    }

    public function test_manual_journal_posting_with_mismatch_amounts_fails(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        [$debit, $credit] = $this->makeAccounts($user);

        Livewire::actingAs($user)
            ->test('accounting.journal')
            ->set('lines', [
                ['account_id' => $debit->id, 'debit_amount' => 120.00, 'credit_amount' => 0.00, 'description' => 'Borç satırı', 'party_id' => null],
                ['account_id' => $credit->id, 'debit_amount' => 0.00, 'credit_amount' => 150.00, 'description' => 'Alacak satırı', 'party_id' => null],
            ])
            ->call('postJournalEntry')
            ->assertSet('messageType', 'error');

        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_manual_journal_voiding(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        [$debit, $credit] = $this->makeAccounts($user);

        // First post one manual entry
        $entry = JournalEntry::create([
            'user_id' => $user->id,
            'entry_date' => now(),
            'entry_type' => 'manual',
            'status' => 'posted',
        ]);

        Livewire::actingAs($user)
            ->test('accounting.journal')
            ->call('confirmVoid', $entry->id)
            ->assertSet('voidingEntryId', $entry->id)
            ->set('voidReason', 'Hata düzeltme')
            ->call('voidEntry')
            ->assertSet('messageType', 'success');

        $this->assertEquals('voided', $entry->fresh()->status);
        $this->assertEquals('Hata düzeltme', $entry->fresh()->void_reason);
    }

    public function test_tenant_isolation_on_journal(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user1 = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $user2 = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        [$debit1, $credit1] = $this->makeAccounts($user1);
        [$debit2, $credit2] = $this->makeAccounts($user2);

        // Attempting to post a journal with accounts belonging to User 2 using actingAs(User 1)
        Livewire::actingAs($user1)
            ->test('accounting.journal')
            ->set('lines', [
                ['account_id' => $debit2->id, 'debit_amount' => 100.00, 'credit_amount' => 0.00, 'description' => '', 'party_id' => null],
                ['account_id' => $credit2->id, 'debit_amount' => 0.00, 'credit_amount' => 100.00, 'description' => '', 'party_id' => null],
            ])
            ->call('postJournalEntry')
            ->assertSet('messageType', 'error');
    }

    public function test_filtering_by_amount_range(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        [$debit, $credit] = $this->makeAccounts($user);

        // Fiş 1: 150 TRY
        app(JournalService::class)->postManual([
            'user_id' => $user->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Fiş 150',
        ], [
            ['account_id' => $debit->id, 'debit_amount' => 150.00],
            ['account_id' => $credit->id, 'credit_amount' => 150.00],
        ]);

        // Fiş 2: 500 TRY
        app(JournalService::class)->postManual([
            'user_id' => $user->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Fiş 500',
        ], [
            ['account_id' => $debit->id, 'debit_amount' => 500.00],
            ['account_id' => $credit->id, 'credit_amount' => 500.00],
        ]);

        // Filter min=200, max=600 -> should see only Fiş 500
        Livewire::actingAs($user)
            ->test('accounting.journal')
            ->set('filterMinAmount', '200')
            ->set('filterMaxAmount', '600')
            ->assertSee('Fiş 500')
            ->assertDontSee('Fiş 150');
    }

    public function test_collapsible_entry_lines_can_be_toggled(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        [$debit, $credit] = $this->makeAccounts($user);

        $entry = app(JournalService::class)->postManual([
            'user_id' => $user->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Collapsible Test',
        ], [
            ['account_id' => $debit->id, 'debit_amount' => 123.45],
            ['account_id' => $credit->id, 'credit_amount' => 123.45],
        ]);

        Livewire::actingAs($user)
            ->test('accounting.journal')
            ->assertDontSee('Fiş Detayı')
            ->call('toggleEntry', $entry->id)
            ->assertSee('Fiş Detayı')
            ->call('toggleEntry', $entry->id)
            ->assertDontSee('Fiş Detayı');
    }
}
