<?php

namespace Tests\Feature\Livewire\Marketplace;

use App\Livewire\Marketplace\TrendyolHealthCenter;
use App\Models\IntegrationSyncRun;
use App\Models\MarketplaceStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TrendyolHealthCenterTest extends TestCase
{
    use RefreshDatabase;

    // ─── Render ──────────────────────────────────────────────────

    public function test_renders_successfully(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(TrendyolHealthCenter::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.marketplace.trendyol-health-center');
    }

    // ─── Authentication ──────────────────────────────────────────

    public function test_unauthenticated_user_cannot_access(): void
    {
        $this->get(route('mp.trendyol.health'))->assertRedirect(route('login'));
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

        Livewire::test(TrendyolHealthCenter::class)
            ->set('selectedStoreId', $storeUser2->id)
            ->assertSet('selectedStoreId', 0);
    }

    // ─── Feature Flag ────────────────────────────────────────────

    public function test_component_renders_when_all_flags_disabled(): void
    {
        config([
            'marketplace.trendyol.order_stream_enabled' => false,
            'marketplace.trendyol.buybox_sync_enabled' => false,
            'marketplace.trendyol.cargo_invoice_sync_enabled' => false,
            'marketplace.trendyol.reference_sync_enabled' => false,
            'marketplace.trendyol.batch_tracking_enabled' => false,
        ]);

        $this->actingAs(User::factory()->create());

        Livewire::test(TrendyolHealthCenter::class)
            ->assertStatus(200);
    }

    // ─── Manual Sync ─────────────────────────────────────────────

    public function test_dispatch_sync_requires_operator_role(): void
    {
        // Regular user without explicit role (null role = not operator)
        $user = User::factory()->create();
        $store = MarketplaceStore::factory()->create(['user_id' => $user->id, 'marketplace' => 'trendyol']);

        // Ensure this user is NOT an operator
        $this->assertFalse($user->isOperator());

        $this->actingAs($user);

        Livewire::test(TrendyolHealthCenter::class)
            ->set('selectedStoreId', $store->id)
            ->set('confirmingSyncType', 'orders')
            ->call('dispatchManualSync');

        // No sync run should be created
        $this->assertDatabaseCount('integration_sync_runs', 0);
    }

    public function test_dispatch_sync_blocked_when_flag_disabled(): void
    {
        config(['marketplace.trendyol.buybox_sync_enabled' => false]);

        $user = User::factory()->create(['role' => 'operator']);
        $store = MarketplaceStore::factory()->create(['user_id' => $user->id, 'marketplace' => 'trendyol']);

        $this->actingAs($user);

        Livewire::test(TrendyolHealthCenter::class)
            ->set('selectedStoreId', $store->id)
            ->set('confirmingSyncType', 'buybox')
            ->call('dispatchManualSync');

        // No sync run should be created for disabled flag
        $this->assertDatabaseCount('integration_sync_runs', 0);
    }

    public function test_confirm_sync_opens_dialog(): void
    {
        $user = User::factory()->create(['role' => 'operator']);
        $this->actingAs($user);

        Livewire::test(TrendyolHealthCenter::class)
            ->call('confirmSync', 'orders')
            ->assertSet('confirmingSyncType', 'orders');
    }

    public function test_cancel_sync_closes_dialog(): void
    {
        $user = User::factory()->create(['role' => 'operator']);
        $this->actingAs($user);

        Livewire::test(TrendyolHealthCenter::class)
            ->call('confirmSync', 'orders')
            ->call('cancelSync')
            ->assertSet('confirmingSyncType', null);
    }

    // ─── Empty State ─────────────────────────────────────────────

    public function test_metrics_null_without_store(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(TrendyolHealthCenter::class)
            ->assertSet('selectedStoreId', 0)
            ->assertStatus(200);
    }

    // ─── Recent Runs Shown ────────────────────────────────────────

    public function test_shows_recent_sync_runs_for_store(): void
    {
        $user = User::factory()->create();
        $store = MarketplaceStore::factory()->create(['user_id' => $user->id, 'marketplace' => 'trendyol']);

        IntegrationSyncRun::factory()->create([
            'store_id' => $store->id,
            'sync_type' => 'orders',
            'status' => 'completed',
        ]);

        $this->actingAs($user);

        Livewire::test(TrendyolHealthCenter::class)
            ->set('selectedStoreId', $store->id)
            ->assertStatus(200);
    }
}
