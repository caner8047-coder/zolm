<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Party;
use App\Models\User;
use App\Services\Accounting\JournalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalServiceTest extends TestCase
{
    use RefreshDatabase;

    // ─── Yardımcı ───────────────────────────────────────────────────────────

    private function makeAccounts(User $user): array
    {
        $debit = Account::create([
            'user_id' => $user->id, 'code' => '120', 'name' => 'Alıcılar',
            'type' => 'asset', 'normal_balance' => 'debit', 'is_active' => true,
        ]);
        $credit = Account::create([
            'user_id' => $user->id, 'code' => '600', 'name' => 'Satışlar',
            'type' => 'revenue', 'normal_balance' => 'credit', 'is_active' => true,
        ]);
        return [$debit, $credit];
    }

    private function service(): JournalService
    {
        return app(JournalService::class);
    }

    // ─── Mutlu Yol ──────────────────────────────────────────────────────────

    public function test_balanced_entry_is_saved(): void
    {
        $user = User::factory()->create();
        [$debit, $credit] = $this->makeAccounts($user);

        $entry = $this->service()->postManual(
            ['user_id' => $user->id, 'entry_date' => now()->toDateString()],
            [
                ['account_id' => $debit->id,  'debit_amount' => 1000, 'credit_amount' => 0],
                ['account_id' => $credit->id, 'debit_amount' => 0,    'credit_amount' => 1000],
            ]
        );

        $this->assertEquals('posted', $entry->status);
        $this->assertDatabaseHas('journal_entries', ['id' => $entry->id, 'status' => 'posted']);
        $this->assertDatabaseCount('journal_lines', 2);
    }

    public function test_entry_has_correct_debit_and_credit_lines(): void
    {
        $user = User::factory()->create();
        [$debit, $credit] = $this->makeAccounts($user);

        $entry = $this->service()->postManual(
            ['user_id' => $user->id, 'entry_date' => now()->toDateString()],
            [
                ['account_id' => $debit->id,  'debit_amount' => 500, 'credit_amount' => 0],
                ['account_id' => $credit->id, 'debit_amount' => 0,   'credit_amount' => 500],
            ]
        );

        $lines = $entry->lines;
        $this->assertEquals(500, (float) $lines[0]->debit_amount);
        $this->assertEquals(0,   (float) $lines[0]->credit_amount);
        $this->assertEquals(0,   (float) $lines[1]->debit_amount);
        $this->assertEquals(500, (float) $lines[1]->credit_amount);
    }

    public function test_entry_is_balanced(): void
    {
        $user = User::factory()->create();
        [$debit, $credit] = $this->makeAccounts($user);

        $entry = $this->service()->postManual(
            ['user_id' => $user->id, 'entry_date' => now()->toDateString()],
            [
                ['account_id' => $debit->id,  'debit_amount' => 750, 'credit_amount' => 0],
                ['account_id' => $credit->id, 'debit_amount' => 0,   'credit_amount' => 750],
            ]
        );

        $this->assertTrue($entry->isBalanced());
    }

    // ─── Denge Hataları ─────────────────────────────────────────────────────

    public function test_unbalanced_entry_is_rejected(): void
    {
        $user = User::factory()->create();
        [$debit, $credit] = $this->makeAccounts($user);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/dengeli değil/i');

        $this->service()->postManual(
            ['user_id' => $user->id, 'entry_date' => now()->toDateString()],
            [
                ['account_id' => $debit->id,  'debit_amount' => 1000, 'credit_amount' => 0],
                ['account_id' => $credit->id, 'debit_amount' => 0,    'credit_amount' => 900], // 100 fark
            ]
        );
    }

    public function test_single_line_entry_is_rejected(): void
    {
        $user = User::factory()->create();
        [$debit,] = $this->makeAccounts($user);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/en az 2 satır/i');

        $this->service()->postManual(
            ['user_id' => $user->id, 'entry_date' => now()->toDateString()],
            [
                ['account_id' => $debit->id, 'debit_amount' => 500, 'credit_amount' => 0],
            ]
        );
    }

    public function test_line_with_both_debit_and_credit_positive_is_rejected(): void
    {
        $user = User::factory()->create();
        [$debit, $credit] = $this->makeAccounts($user);

        $this->expectException(\InvalidArgumentException::class);

        $this->service()->postManual(
            ['user_id' => $user->id, 'entry_date' => now()->toDateString()],
            [
                ['account_id' => $debit->id,  'debit_amount' => 500, 'credit_amount' => 500],
                ['account_id' => $credit->id, 'debit_amount' => 0,   'credit_amount' => 1000],
            ]
        );
    }

    public function test_line_with_both_zero_is_rejected(): void
    {
        $user = User::factory()->create();
        [$debit, $credit] = $this->makeAccounts($user);

        $this->expectException(\InvalidArgumentException::class);

        $this->service()->postManual(
            ['user_id' => $user->id, 'entry_date' => now()->toDateString()],
            [
                ['account_id' => $debit->id,  'debit_amount' => 0, 'credit_amount' => 0],
                ['account_id' => $credit->id, 'debit_amount' => 0, 'credit_amount' => 500],
            ]
        );
    }

    public function test_negative_amount_is_rejected(): void
    {
        $user = User::factory()->create();
        [$debit, $credit] = $this->makeAccounts($user);

        $this->expectException(\InvalidArgumentException::class);

        $this->service()->postManual(
            ['user_id' => $user->id, 'entry_date' => now()->toDateString()],
            [
                ['account_id' => $debit->id,  'debit_amount' => -100, 'credit_amount' => 0],
                ['account_id' => $credit->id, 'debit_amount' => 0,    'credit_amount' => -100],
            ]
        );
    }

    // ─── Güvenlik ───────────────────────────────────────────────────────────

    public function test_account_from_another_user_is_rejected(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        [$debit,] = $this->makeAccounts($user1);
        $otherCredit = Account::create([
            'user_id' => $user2->id, 'code' => '600', 'name' => 'Satışlar',
            'type' => 'revenue', 'normal_balance' => 'credit', 'is_active' => true,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/bu kullanıcıya ait değil/i');

        $this->service()->postManual(
            ['user_id' => $user1->id, 'entry_date' => now()->toDateString()],
            [
                ['account_id' => $debit->id,      'debit_amount' => 500, 'credit_amount' => 0],
                ['account_id' => $otherCredit->id, 'debit_amount' => 0,  'credit_amount' => 500],
            ]
        );
    }

    public function test_passive_account_is_rejected(): void
    {
        $user = User::factory()->create();
        $debit = Account::create([
            'user_id' => $user->id, 'code' => '120', 'name' => 'Alıcılar',
            'type' => 'asset', 'normal_balance' => 'debit', 'is_active' => true,
        ]);
        $passiveCredit = Account::create([
            'user_id' => $user->id, 'code' => '600', 'name' => 'Satışlar',
            'type' => 'revenue', 'normal_balance' => 'credit', 'is_active' => false, // pasif
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/pasif/i');

        $this->service()->postManual(
            ['user_id' => $user->id, 'entry_date' => now()->toDateString()],
            [
                ['account_id' => $debit->id,        'debit_amount' => 500, 'credit_amount' => 0],
                ['account_id' => $passiveCredit->id, 'debit_amount' => 0,  'credit_amount' => 500],
            ]
        );
    }

    // ─── Idempotency ────────────────────────────────────────────────────────

    public function test_source_key_idempotency_returns_existing_entry(): void
    {
        $user = User::factory()->create();
        [$debit, $credit] = $this->makeAccounts($user);

        $header = [
            'user_id'    => $user->id,
            'entry_date' => now()->toDateString(),
            'source_key' => 'idem-test-001',
        ];
        $lines = [
            ['account_id' => $debit->id,  'debit_amount' => 200, 'credit_amount' => 0],
            ['account_id' => $credit->id, 'debit_amount' => 0,   'credit_amount' => 200],
        ];

        $entry1 = $this->service()->postManual($header, $lines);
        $entry2 = $this->service()->postManual($header, $lines);

        $this->assertEquals($entry1->id, $entry2->id);
        $this->assertDatabaseCount('journal_entries', 1);
    }

    // ─── Void ───────────────────────────────────────────────────────────────

    public function test_void_entry_marks_status_voided(): void
    {
        $user = User::factory()->create();
        [$debit, $credit] = $this->makeAccounts($user);

        $entry = $this->service()->postManual(
            ['user_id' => $user->id, 'entry_date' => now()->toDateString()],
            [
                ['account_id' => $debit->id,  'debit_amount' => 300, 'credit_amount' => 0],
                ['account_id' => $credit->id, 'debit_amount' => 0,   'credit_amount' => 300],
            ]
        );

        $voided = $this->service()->voidEntry($entry, 'Hatalı giriş');

        $this->assertEquals('voided', $voided->status);
        $this->assertNotNull($voided->voided_at);
        $this->assertEquals('Hatalı giriş', $voided->void_reason);
    }

    public function test_void_entry_cannot_be_voided_again(): void
    {
        $user = User::factory()->create();
        [$debit, $credit] = $this->makeAccounts($user);

        $entry = $this->service()->postManual(
            ['user_id' => $user->id, 'entry_date' => now()->toDateString()],
            [
                ['account_id' => $debit->id,  'debit_amount' => 100, 'credit_amount' => 0],
                ['account_id' => $credit->id, 'debit_amount' => 0,   'credit_amount' => 100],
            ]
        );

        $this->service()->voidEntry($entry, 'İlk iptal');
        $entry->refresh();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/zaten iptal/i');

        $this->service()->voidEntry($entry, 'İkinci iptal');
    }

    public function test_voided_entry_excluded_from_balance(): void
    {
        $user = User::factory()->create();
        [$debit, $credit] = $this->makeAccounts($user);

        $entry = $this->service()->postManual(
            ['user_id' => $user->id, 'entry_date' => now()->toDateString()],
            [
                ['account_id' => $debit->id,  'debit_amount' => 1000, 'credit_amount' => 0],
                ['account_id' => $credit->id, 'debit_amount' => 0,    'credit_amount' => 1000],
            ]
        );

        // Void'dan önce bakiye var
        $beforeVoid = $this->service()->accountBalance($debit);
        $this->assertEquals(1000, $beforeVoid['balance']);

        $this->service()->voidEntry($entry);

        // Void'dan sonra bakiye sıfır
        $afterVoid = $this->service()->accountBalance($debit);
        $this->assertEquals(0, $afterVoid['balance']);
    }

    // ─── Bakiye Hesaplama ───────────────────────────────────────────────────

    public function test_account_balance_debit_normal(): void
    {
        $user = User::factory()->create();
        [$debit, $credit] = $this->makeAccounts($user);

        $this->service()->postManual(
            ['user_id' => $user->id, 'entry_date' => now()->toDateString()],
            [
                ['account_id' => $debit->id,  'debit_amount' => 800, 'credit_amount' => 0],
                ['account_id' => $credit->id, 'debit_amount' => 0,   'credit_amount' => 800],
            ]
        );

        $balance = $this->service()->accountBalance($debit);
        $this->assertEquals(800, $balance['balance']);
        $this->assertEquals(800, $balance['debit']);
        $this->assertEquals(0,   $balance['credit']);
    }

    public function test_account_balance_credit_normal(): void
    {
        $user = User::factory()->create();
        [$debit, $credit] = $this->makeAccounts($user);

        $this->service()->postManual(
            ['user_id' => $user->id, 'entry_date' => now()->toDateString()],
            [
                ['account_id' => $debit->id,  'debit_amount' => 600, 'credit_amount' => 0],
                ['account_id' => $credit->id, 'debit_amount' => 0,   'credit_amount' => 600],
            ]
        );

        // credit normal_balance=credit, yani alacak - borç = 600
        $balance = $this->service()->accountBalance($credit);
        $this->assertEquals(600, $balance['balance']);
    }

    public function test_multi_entry_balance_accumulates(): void
    {
        $user = User::factory()->create();
        [$debit, $credit] = $this->makeAccounts($user);

        for ($i = 0; $i < 3; $i++) {
            $this->service()->postManual(
                ['user_id' => $user->id, 'entry_date' => now()->toDateString()],
                [
                    ['account_id' => $debit->id,  'debit_amount' => 100, 'credit_amount' => 0],
                    ['account_id' => $credit->id, 'debit_amount' => 0,   'credit_amount' => 100],
                ]
            );
        }

        $balance = $this->service()->accountBalance($debit);
        $this->assertEquals(300, $balance['balance']);
    }

    // ─── Döviz ──────────────────────────────────────────────────────────────

    public function test_exchange_rate_applies_to_base_amount(): void
    {
        $user = User::factory()->create();
        [$debit, $credit] = $this->makeAccounts($user);

        $entry = $this->service()->postManual(
            ['user_id' => $user->id, 'entry_date' => now()->toDateString(), 'currency_code' => 'USD', 'exchange_rate' => 32.50],
            [
                ['account_id' => $debit->id,  'debit_amount' => 100, 'credit_amount' => 0],
                ['account_id' => $credit->id, 'debit_amount' => 0,   'credit_amount' => 100],
            ]
        );

        $this->assertEquals(3250.00, (float) $entry->lines[0]->debit_base_amount);
        $this->assertEquals(3250.00, (float) $entry->lines[1]->credit_base_amount);
    }

    // ─── ChartOfAccountsSeeder ──────────────────────────────────────────────

    public function test_chart_of_accounts_seeder_creates_system_accounts(): void
    {
        $user = User::factory()->create();
        $seeder = new \Database\Seeders\ChartOfAccountsSeeder();
        $seeder->runForUser($user->id);

        $this->assertDatabaseHas('accounts', ['user_id' => $user->id, 'code' => '100', 'is_cash_account' => true]);
        $this->assertDatabaseHas('accounts', ['user_id' => $user->id, 'code' => '102', 'is_bank_account' => true]);
        $this->assertDatabaseHas('accounts', ['user_id' => $user->id, 'code' => '120', 'is_ar_account' => true]);
        $this->assertDatabaseHas('accounts', ['user_id' => $user->id, 'code' => '320', 'is_ap_account' => true]);
        $this->assertDatabaseHas('accounts', ['user_id' => $user->id, 'code' => '600']);
    }

    public function test_chart_of_accounts_seeder_is_idempotent(): void
    {
        $user = User::factory()->create();
        $seeder = new \Database\Seeders\ChartOfAccountsSeeder();
        $seeder->runForUser($user->id);
        $count1 = Account::where('user_id', $user->id)->count();

        $seeder->runForUser($user->id); // ikinci çalıştırma
        $count2 = Account::where('user_id', $user->id)->count();

        $this->assertEquals($count1, $count2);
    }

    public function test_seeder_user_isolation(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $seeder = new \Database\Seeders\ChartOfAccountsSeeder();

        $seeder->runForUser($user1->id);
        $seeder->runForUser($user2->id);

        $user1Accounts = Account::where('user_id', $user1->id)->count();
        $user2Accounts = Account::where('user_id', $user2->id)->count();

        $this->assertEquals($user1Accounts, $user2Accounts);
        $this->assertGreaterThan(0, $user1Accounts);
    }

    public function test_journal_service_rejects_other_user_party_in_header(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        [$debit, $credit] = $this->makeAccounts($user);
        $otherParty = Party::factory()->create(['user_id' => $otherUser->id]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/başlığındaki party bu kullanıcıya ait değil/i');

        $this->service()->postManual([
            'user_id'    => $user->id,
            'entry_date' => now()->toDateString(),
            'party_id'   => $otherParty->id,
        ], [
            ['account_id' => $debit->id,  'debit_amount' => 100, 'credit_amount' => 0],
            ['account_id' => $credit->id, 'debit_amount' => 0,   'credit_amount' => 100],
        ]);
    }

    public function test_journal_service_rejects_other_user_legal_entity_in_header(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        [$debit, $credit] = $this->makeAccounts($user);
        $otherEntity = \App\Models\LegalEntity::create(['user_id' => $otherUser->id, 'name' => 'Fake LTD', 'tax_number' => '111111']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/başlığındaki legal entity bu kullanıcıya ait değil/i');

        $this->service()->postManual([
            'user_id'         => $user->id,
            'entry_date'      => now()->toDateString(),
            'legal_entity_id' => $otherEntity->id,
        ], [
            ['account_id' => $debit->id,  'debit_amount' => 100, 'credit_amount' => 0],
            ['account_id' => $credit->id, 'debit_amount' => 0,   'credit_amount' => 100],
        ]);
    }

    public function test_journal_service_rejects_other_user_party_in_lines(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        [$debit, $credit] = $this->makeAccounts($user);
        $otherParty = Party::factory()->create(['user_id' => $otherUser->id]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Satır #.*party bu kullanıcıya ait değil/i');

        $this->service()->postManual([
            'user_id'    => $user->id,
            'entry_date' => now()->toDateString(),
        ], [
            ['account_id' => $debit->id,  'debit_amount' => 100, 'credit_amount' => 0, 'party_id' => $otherParty->id],
            ['account_id' => $credit->id, 'debit_amount' => 0,   'credit_amount' => 100],
        ]);
    }
}
