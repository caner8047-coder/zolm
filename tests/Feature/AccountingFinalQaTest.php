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

class AccountingFinalQaTest extends TestCase
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

    protected array $components = [
        \App\Livewire\Accounting\AccountingDashboard::class,
        \App\Livewire\Accounting\Parties::class,
        \App\Livewire\Accounting\PartyLedgerWorkspace::class,
        \App\Livewire\Accounting\ChartOfAccounts::class,
        \App\Livewire\Accounting\Journal::class,
        \App\Livewire\Accounting\CashBank::class,
        \App\Livewire\Accounting\Stock::class,
        \App\Livewire\Accounting\Products::class,
        \App\Livewire\Accounting\Sales::class,
        \App\Livewire\Accounting\Purchases::class,
        \App\Livewire\Accounting\CollectionsPayments::class,
        \App\Livewire\Accounting\Pos::class,
        \App\Livewire\Accounting\EDocuments::class,
        \App\Livewire\Accounting\Reports::class,
        \App\Livewire\Accounting\Assistant::class,
        \App\Livewire\Accounting\MarketplaceBridge::class,
        \App\Livewire\Accounting\AuditLogs::class,
    ];

    public function test_routes_return_404_when_accounting_disabled(): void
    {
        config()->set('marketplace.features.accounting_enabled', false);
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        foreach ($this->routes as $name => $path) {
            $response = $this->actingAs($user)->get($path);
            $response->assertStatus(404);
        }
    }

    public function test_routes_redirect_or_403_for_non_admin_users(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);
        
        // SQLite constraint gereği operator rolü verilip roleSlug CRM sorumlusu yapılır
        $role = \App\Models\Role::firstOrCreate(['slug' => 'crm_sorumlusu'], ['name' => 'CRM Sorumlusu']);
        $user = User::factory()->create([
            'is_active' => true,
            'role' => 'operator',
            'role_id' => $role->id,
        ]);
        unset($user->role);

        foreach ($this->routes as $name => $path) {
            $response = $this->actingAs($user)->get($path);
            // Non-admin kullanıcılar için yönlendirme (örneğin ana sayfaya) veya 403/404 beklenir
            $this->assertTrue($response->status() === 302 || $response->status() === 403 || $response->status() === 404);
        }
    }

    public function test_routes_render_successfully_for_admin_users(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        // Seeder ile veri yükleyelim ki render esnasında veriler eksik kalmasın
        Artisan::call('accounting:seed-demo', ['--user' => $user->id]);

        foreach ($this->routes as $name => $path) {
            $response = $this->actingAs($user)->get($path);
            $response->assertStatus(200);
        }
    }

    public function test_sidebar_menu_visibility(): void
    {
        // 1. Flag kapalıyken sidebar ERP menüsü görünmez olmalı
        config()->set('marketplace.features.accounting_enabled', false);
        $admin = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $response = $this->actingAs($admin)->get('/production');
        $response->assertStatus(200);
        $response->assertDontSee('/accounting/journal');

        // 2. Flag açıkken admin için görünür olmalı
        config()->set('marketplace.features.accounting_enabled', true);
        $response = $this->actingAs($admin)->get('/accounting/parties');
        $response->assertStatus(200);
        $response->assertSee('/accounting/journal');

        // 3. Flag açıkken non-admin için görünmez olmalı
        $role = \App\Models\Role::firstOrCreate(['slug' => 'crm_sorumlusu'], ['name' => 'CRM Sorumlusu']);
        $operator = User::factory()->create([
            'is_active' => true,
            'role' => 'operator',
            'role_id' => $role->id,
        ]);
        unset($operator->role);

        $response = $this->actingAs($operator)->get('/onboarding');
        $response->assertStatus(200);
        $response->assertDontSee('/accounting/journal');
    }

    public function test_all_livewire_components_smoke_render_with_seeded_data(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        // Seeder'ı çalıştır
        Artisan::call('accounting:seed-demo', ['--user' => $user->id]);

        // Tüm Livewire componentlerinin hatasız render olduğunu teyit edelim
        foreach ($this->components as $component) {
            Livewire::actingAs($user)
                ->test($component)
                ->assertStatus(200);
        }
    }
}
