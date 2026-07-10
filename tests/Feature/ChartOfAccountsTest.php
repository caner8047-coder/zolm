<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ChartOfAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_is_blocked_when_accounting_enabled_is_false(): void
    {
        config()->set('marketplace.features.accounting_enabled', false);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $this->actingAs($user)
            ->get(route('accounting.chart-of-accounts'))
            ->assertStatus(404);
    }

    public function test_page_renders_when_accounting_enabled_is_true(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $this->actingAs($user)
            ->get(route('accounting.chart-of-accounts'))
            ->assertStatus(200)
            ->assertSeeLivewire('accounting.chart-of-accounts');
    }

    public function test_seeding_default_accounts(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        Livewire::actingAs($user)
            ->test('accounting.chart-of-accounts')
            ->call('seedDefaultAccounts')
            ->assertSet('messageType', 'success');

        $this->assertGreaterThan(0, Account::where('user_id', $user->id)->count());
    }

    public function test_creating_custom_account(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        Livewire::actingAs($user)
            ->test('accounting.chart-of-accounts')
            ->set('newCode', '100.09')
            ->set('newName', 'Şube Kasası')
            ->set('newType', 'asset')
            ->set('newNormalBalance', 'debit')
            ->call('createAccount')
            ->assertSet('messageType', 'success')
            ->assertSet('newCode', '')
            ->assertSet('newName', '');

        $this->assertDatabaseHas('accounts', [
            'user_id' => $user->id,
            'code' => '100.09',
            'name' => 'Şube Kasası',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_system' => false,
        ]);
    }

    public function test_user_isolation_for_accounts(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user1 = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $user2 = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        Account::create([
            'user_id' => $user1->id,
            'code' => '100.01',
            'name' => 'User1 Cash',
            'type' => 'asset',
            'normal_balance' => 'debit',
        ]);

        Account::create([
            'user_id' => $user2->id,
            'code' => '100.02',
            'name' => 'User2 Cash',
            'type' => 'asset',
            'normal_balance' => 'debit',
        ]);

        // User 1 sees user1's cash, not user2's
        Livewire::actingAs($user1)
            ->test('accounting.chart-of-accounts')
            ->assertSee('User1 Cash')
            ->assertDontSee('User2 Cash');

        // User 2 sees user2's cash, not user1's
        Livewire::actingAs($user2)
            ->test('accounting.chart-of-accounts')
            ->assertSee('User2 Cash')
            ->assertDontSee('User1 Cash');
    }
}
