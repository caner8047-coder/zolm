<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\LegalEntity;
use App\Models\Party;
use App\Models\Warehouse;
use App\Models\MpProduct;
use App\Models\Account;
use App\Models\Receivable;
use App\Models\Payable;
use App\Models\JournalEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Tests\TestCase;

class AccountingProductAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected array $routes = [
        'accounting.dashboard' => '/accounting',
        'accounting.parties' => '/accounting/parties',
        'accounting.party-ledger' => '/accounting/party-ledger',
        'accounting.chart-of-accounts' => '/accounting/chart-of-accounts',
        'accounting.journal' => '/accounting/journal',
        'accounting.cash-bank' => '/accounting/cash-bank',
        'accounting.stock' => '/accounting/stock',
        'accounting.products' => '/accounting/products',
        'accounting.sales' => '/accounting/sales',
        'accounting.purchases' => '/accounting/purchases',
        'accounting.collections-payments' => '/accounting/collections-payments',
        'accounting.pos' => '/accounting/pos',
        'accounting.e-documents' => '/accounting/e-documents',
        'accounting.reports' => '/accounting/reports',
        'accounting.assistant' => '/accounting/assistant',
        'accounting.marketplace-bridge' => '/accounting/marketplace-bridge',
        'accounting.audit-logs' => '/accounting/audit-logs',
    ];

    public function test_routes_return_200_after_seeding_for_admin_users(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        // Seeder ile demo verilerini kur
        Artisan::call('accounting:seed-demo', ['--user' => $user->id]);

        foreach ($this->routes as $name => $path) {
            $response = $this->actingAs($user)->get($path);
            $response->assertStatus(200);
        }
    }

    public function test_dashboard_kpis_are_not_zero(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        // Seeder ile demo verilerini kur
        Artisan::call('accounting:seed-demo', ['--user' => $user->id]);

        // Dashboard component'ini test edelim
        $component = Livewire::actingAs($user)
            ->test(\App\Livewire\Accounting\AccountingDashboard::class);
        
        $kpis = $component->get('kpis');
        $this->assertNotNull($kpis);
        $this->assertGreaterThan(0, $kpis['open_receivables']);
        $this->assertGreaterThan(0, $kpis['open_payables']);
    }

    public function test_party_ledger_shows_seeded_data(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        Artisan::call('accounting:seed-demo', ['--user' => $user->id]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Accounting\PartyLedgerWorkspace::class)
            ->assertStatus(200)
            ->assertSee('ZOLM Demo Perakende Müşteri A.Ş.');
    }

    public function test_stock_shows_seeded_data(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        Artisan::call('accounting:seed-demo', ['--user' => $user->id]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Accounting\Stock::class)
            ->assertStatus(200)
            ->assertSee('demo-depo-merkez');
    }

    public function test_sales_shows_seeded_data(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        Artisan::call('accounting:seed-demo', ['--user' => $user->id]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Accounting\Sales::class)
            ->assertStatus(200)
            ->assertSee('DEMO-');
    }

    public function test_purchases_shows_seeded_data(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        Artisan::call('accounting:seed-demo', ['--user' => $user->id]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Accounting\Purchases::class)
            ->assertStatus(200)
            ->assertSee('DEMO-');
    }

    public function test_cash_bank_shows_seeded_data(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        Artisan::call('accounting:seed-demo', ['--user' => $user->id]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Accounting\CashBank::class)
            ->assertStatus(200)
            ->assertSee('ZOLM Demo Merkez Kasa');
    }

    public function test_reports_generates_executive_summary(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        Artisan::call('accounting:seed-demo', ['--user' => $user->id]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Accounting\Reports::class)
            ->assertStatus(200)
            ->assertSee('Yönetici Özeti');
    }

    public function test_assistant_renders(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Accounting\Assistant::class)
            ->assertStatus(200);
    }

    public function test_routes_return_404_when_accounting_disabled(): void
    {
        config()->set('marketplace.features.accounting_enabled', false);
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        foreach ($this->routes as $name => $path) {
            $response = $this->actingAs($user)->get($path);
            $response->assertStatus(404);
        }
    }
}
