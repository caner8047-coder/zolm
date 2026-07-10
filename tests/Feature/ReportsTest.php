<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use App\Models\Party;
use App\Models\Warehouse;
use App\Models\LegalEntity;
use App\Services\Accounting\OutstandingInvoiceService;
use App\Services\Accounting\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Party $party;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $this->party = Party::factory()->create(['user_id' => $this->user->id]);
        $this->party->roles()->create(['user_id' => $this->user->id, 'role' => 'customer']);

        (new \Database\Seeders\ChartOfAccountsSeeder())->runForUser($this->user->id);
    }

    public function test_route_is_blocked_when_accounting_enabled_is_false(): void
    {
        config()->set('marketplace.features.accounting_enabled', false);

        $this->actingAs($this->user)
            ->get(route('accounting.reports'))
            ->assertStatus(404);
    }

    public function test_page_renders_when_accounting_enabled_is_true(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $this->actingAs($this->user)
            ->get(route('accounting.reports'))
            ->assertStatus(200)
            ->assertSeeLivewire('accounting.reports');
    }

    public function test_empty_data_shows_zero_kpi(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        Livewire::actingAs($this->user)
            ->test('accounting.reports')
            ->assertSee('Yönetim Raporları')
            ->assertSee('₺0,00');
    }

    public function test_switching_report_types_renders_different_tables(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        Livewire::actingAs($this->user)
            ->test('accounting.reports')
            ->set('reportType', 'receivables_aging')
            ->assertSee('Vadesi Gelmemiş')
            ->set('reportType', 'cash_flow')
            ->assertSee('Beklenen Giriş')
            ->set('reportType', 'stock_inventory')
            ->assertSee('Birim Maliyet')
            ->set('reportType', 'party_balances')
            ->assertSee('Müşteri / Tedarikçi');
    }

    public function test_column_toggling_and_sorting(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        // Alacak kaydı oluşturalım ki sıralanacak veri olsun
        app(OutstandingInvoiceService::class)->createReceivable([
            'user_id'       => $this->user->id,
            'party_id'      => $this->party->id,
            'amount'        => 500.00,
            'document_date' => now()->toDateString(),
        ]);

        Livewire::actingAs($this->user)
            ->test('accounting.reports')
            ->set('reportType', 'income_expense')
            // Kolon gizle
            ->call('toggleColumn', 'account_name')
            ->assertSet('visibleColumns', ['account_code', 'type', 'amount'])
            // Kolon geri getir
            ->call('toggleColumn', 'account_name')
            ->assertSet('visibleColumns', ['account_code', 'type', 'amount', 'account_name'])
            // Sırala
            ->call('sortTable', 'account_code')
            ->assertSet('sortColumn', 'account_code')
            // Whitelist dışı kolon sıralanmaz
            ->call('sortTable', 'invalid_column_name')
            ->assertSet('sortColumn', 'account_code');
    }

    public function test_other_user_filter_id_handling(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $otherUser = User::factory()->create(['is_active' => true]);
        $otherParty = Party::factory()->create(['user_id' => $otherUser->id]);

        Livewire::actingAs($this->user)
            ->test('accounting.reports')
            ->set('partyId', $otherParty->id)
            ->assertSee('Güvenlik Uyarısı: Belirtilen cari bulunamadı veya bu kullanıcıya ait değil.');
    }

    public function test_rendering_does_not_mutate_accounting_enabled_feature_flag(): void
    {
        config()->set('marketplace.features.accounting_enabled', false);

        Livewire::actingAs($this->user)
            ->test('accounting.reports');

        $this->assertFalse(config('marketplace.features.accounting_enabled'));

        config()->set('marketplace.features.accounting_enabled', true);

        Livewire::actingAs($this->user)
            ->test('accounting.reports');

        $this->assertTrue(config('marketplace.features.accounting_enabled'));
    }

    public function test_ui_filters_successfully_update_and_filter_results(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $invoice = app(OutstandingInvoiceService::class);

        // Created 10 days ago (amount 700)
        $invoice->createReceivable([
            'user_id'       => $this->user->id,
            'party_id'      => $this->party->id,
            'amount'        => 700.00,
            'document_date' => now()->subDays(10)->toDateString(),
        ]);

        // Created today (amount 1500)
        $invoice->createReceivable([
            'user_id'       => $this->user->id,
            'party_id'      => $this->party->id,
            'amount'        => 1500.00,
            'document_date' => now()->toDateString(),
        ]);

        // When dateFrom is set to 3 days ago, the 10 days ago receivable should be filtered out
        Livewire::actingAs($this->user)
            ->test('accounting.reports')
            ->set('reportType', 'receivables_aging')
            ->set('dateFrom', now()->subDays(3)->toDateString())
            ->assertSee('₺1.500,00')
            ->assertDontSee('₺2.200,00'); // total open would be 2200 if not filtered
    }
}
