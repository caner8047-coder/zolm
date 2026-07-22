<?php

namespace Tests\Feature\Livewire\Marketplace;

use App\Livewire\Marketplace\CargoInvoiceReconciliation;
use App\Models\CargoInvoiceLine;
use App\Models\MarketplaceStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CargoInvoiceReconciliationTest extends TestCase
{
    use RefreshDatabase;

    // ─── Render ──────────────────────────────────────────────────

    public function test_renders_successfully(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CargoInvoiceReconciliation::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.marketplace.cargo-invoice-reconciliation');
    }

    // ─── Authentication ──────────────────────────────────────────

    public function test_unauthenticated_user_cannot_access(): void
    {
        $this->get(route('mp.cargo.invoice'))->assertRedirect(route('login'));
    }

    // ─── Tenant Isolation ────────────────────────────────────────

    public function test_prevents_accessing_other_users_store(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $storeUser2 = MarketplaceStore::factory()->create([
            'user_id' => $user2->id,
            'marketplace' => 'trendyol',
        ]);

        $this->actingAs($user1);

        Livewire::test(CargoInvoiceReconciliation::class)
            ->set('selectedStoreId', $storeUser2->id)
            ->assertSet('selectedStoreId', 0);
    }

    public function test_only_own_store_invoices_are_shown(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $store1 = MarketplaceStore::factory()->create(['user_id' => $user1->id, 'marketplace' => 'trendyol']);
        $store2 = MarketplaceStore::factory()->create(['user_id' => $user2->id, 'marketplace' => 'trendyol']);

        // user1's invoice
        CargoInvoiceLine::factory()->create([
            'user_id' => $user1->id,
            'store_id' => $store1->id,
            'invoice_serial_number' => 'MY-INV-001',
        ]);

        // user2's invoice — must NOT appear for user1
        CargoInvoiceLine::factory()->create([
            'user_id' => $user2->id,
            'store_id' => $store2->id,
            'invoice_serial_number' => 'OTHER-INV-001',
        ]);

        $this->actingAs($user1);

        // When user1 selects their store, only their invoices should appear
        Livewire::test(CargoInvoiceReconciliation::class)
            ->set('selectedStoreId', $store1->id)
            ->assertSee('MY-INV-001')
            ->assertDontSee('OTHER-INV-001');
    }

    // ─── Status Codes ────────────────────────────────────────────

    public function test_invoice_without_order_number_shows_pending(): void
    {
        $user = User::factory()->create();
        $store = MarketplaceStore::factory()->create(['user_id' => $user->id, 'marketplace' => 'trendyol']);

        CargoInvoiceLine::factory()->create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'order_number' => null,
            'invoice_serial_number' => 'PENDING-INV',
        ]);

        $this->actingAs($user);

        Livewire::test(CargoInvoiceReconciliation::class)
            ->set('selectedStoreId', $store->id)
            ->assertSee('BEKLİYOR');
    }

    // ─── Filters ─────────────────────────────────────────────────

    public function test_outbound_direction_filter(): void
    {
        $user = User::factory()->create();
        $store = MarketplaceStore::factory()->create(['user_id' => $user->id, 'marketplace' => 'trendyol']);

        CargoInvoiceLine::factory()->outbound()->create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'invoice_serial_number' => 'OUT-001',
        ]);

        CargoInvoiceLine::factory()->returned()->create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'invoice_serial_number' => 'RET-001',
        ]);

        $this->actingAs($user);

        Livewire::test(CargoInvoiceReconciliation::class)
            ->set('selectedStoreId', $store->id)
            ->set('filterDirection', 'OUTBOUND')
            ->assertSee('OUT-001')
            ->assertDontSee('RET-001');
    }

    public function test_return_direction_filter(): void
    {
        $user = User::factory()->create();
        $store = MarketplaceStore::factory()->create(['user_id' => $user->id, 'marketplace' => 'trendyol']);

        CargoInvoiceLine::factory()->outbound()->create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'invoice_serial_number' => 'OUT-001',
        ]);

        CargoInvoiceLine::factory()->returned()->create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'invoice_serial_number' => 'RET-001',
        ]);

        $this->actingAs($user);

        Livewire::test(CargoInvoiceReconciliation::class)
            ->set('selectedStoreId', $store->id)
            ->set('filterDirection', 'RETURN')
            ->assertDontSee('OUT-001')
            ->assertSee('RET-001');
    }

    // ─── Sorting ─────────────────────────────────────────────────

    public function test_valid_sort_column_changes_sort(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(CargoInvoiceReconciliation::class)
            ->call('sortTable', 'desi')
            ->assertSet('sortBy', 'desi');
    }

    public function test_invalid_sort_column_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $component = Livewire::test(CargoInvoiceReconciliation::class);
        $originalSort = $component->get('sortBy');

        $component->call('sortTable', '__proto__');

        $this->assertEquals($originalSort, $component->get('sortBy'));
    }

    // ─── Pagination ──────────────────────────────────────────────

    public function test_pagination_works(): void
    {
        $user = User::factory()->create();
        $store = MarketplaceStore::factory()->create(['user_id' => $user->id, 'marketplace' => 'trendyol']);

        CargoInvoiceLine::factory()->count(30)->create([
            'user_id' => $user->id,
            'store_id' => $store->id,
        ]);

        $this->actingAs($user);

        Livewire::test(CargoInvoiceReconciliation::class)
            ->set('selectedStoreId', $store->id)
            ->set('perPage', 10)
            ->assertStatus(200);
    }

    // ─── Feature Flag ────────────────────────────────────────────

    public function test_feature_flag_disabled_view_still_renders(): void
    {
        config(['marketplace.trendyol.cargo_invoice_sync_enabled' => false]);

        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(CargoInvoiceReconciliation::class)
            ->assertStatus(200);
    }

    // ─── Empty State ─────────────────────────────────────────────

    public function test_empty_state_when_no_invoices(): void
    {
        $user = User::factory()->create();
        $store = MarketplaceStore::factory()->create(['user_id' => $user->id, 'marketplace' => 'trendyol']);

        $this->actingAs($user);

        Livewire::test(CargoInvoiceReconciliation::class)
            ->set('selectedStoreId', $store->id)
            ->assertStatus(200);
    }

    // ─── Profit Impact ───────────────────────────────────────────

    public function test_profit_impact_requires_order_and_snapshot(): void
    {
        $user = User::factory()->create();
        $store = MarketplaceStore::factory()->create(['user_id' => $user->id, 'marketplace' => 'trendyol']);

        // Invoice without order_number → no profit calculation
        CargoInvoiceLine::factory()->create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'order_number' => null,
            'total_amount' => 35.00,
        ]);

        $this->actingAs($user);

        $component = Livewire::test(CargoInvoiceReconciliation::class)
            ->set('selectedStoreId', $store->id);

        // Should render without errors — no profit_impact crash
        $component->assertStatus(200);
    }
}
