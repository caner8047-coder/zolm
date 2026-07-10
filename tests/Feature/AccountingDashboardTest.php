<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Party;
use App\Models\Receivable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AccountingDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_is_blocked_when_accounting_enabled_is_false(): void
    {
        config()->set('marketplace.features.accounting_enabled', false);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $this->actingAs($user)
            ->get(route('accounting.dashboard'))
            ->assertStatus(404);
    }

    public function test_page_renders_when_accounting_enabled_is_true(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $this->actingAs($user)
            ->get(route('accounting.dashboard'))
            ->assertStatus(200)
            ->assertSeeLivewire('accounting.accounting-dashboard');
    }

    public function test_accounting_dashboard_loads_kpis(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $party = Party::factory()->create(['user_id' => $user->id]);

        // Create a receivable to see a KPI value change
        Receivable::create([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'amount' => 1500.00,
            'document_date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'status' => 'draft',
        ]);

        Livewire::actingAs($user)
            ->test('accounting.accounting-dashboard')
            ->assertSee('Ön Muhasebe & ERP Dashboard', false)
            ->assertSet('kpis.open_receivables', 1500.00)
            ->assertSet('kpis.open_payables', 0.0)
            ->assertSet('kpis.cash_bank', 0.0)
            ->assertSet('kpis.stock_value', 0.0);
    }

    public function test_user_isolation_for_dashboard_kpi_query(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user1 = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $user2 = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $party1 = Party::factory()->create(['user_id' => $user1->id]);
        $party2 = Party::factory()->create(['user_id' => $user2->id]);

        // Receivable for User 1
        Receivable::create([
            'user_id' => $user1->id,
            'party_id' => $party1->id,
            'amount' => 1500.00,
            'document_date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        // Receivable for User 2 (should not leak to User 1)
        Receivable::create([
            'user_id' => $user2->id,
            'party_id' => $party2->id,
            'amount' => 9999.00,
            'document_date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        Livewire::actingAs($user1)
            ->test('accounting.accounting-dashboard')
            ->assertSet('kpis.open_receivables', 1500.00);

        Livewire::actingAs($user2)
            ->test('accounting.accounting-dashboard')
            ->assertSet('kpis.open_receivables', 9999.00);
    }
}
