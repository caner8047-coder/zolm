<?php

namespace Tests\Feature;

use App\Models\ChannelOrder;
use App\Models\ChannelOrderItem;
use App\Models\JournalEntry;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\MpProduct;
use App\Models\OrderFinancialEvent;
use App\Models\SalesOrder;
use App\Models\StockBalance;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MarketplaceBridgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_is_blocked_when_accounting_enabled_is_false(): void
    {
        config()->set('marketplace.features.accounting_enabled', false);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $this->actingAs($user)
            ->get(route('accounting.marketplace-bridge'))
            ->assertStatus(404);
    }

    public function test_page_renders_when_accounting_enabled_is_true(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $this->actingAs($user)
            ->get(route('accounting.marketplace-bridge'))
            ->assertStatus(200)
            ->assertSeeLivewire('accounting.marketplace-bridge');
    }

    public function test_bridging_channel_order_draft_and_approval(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        (new \Database\Seeders\ChartOfAccountsSeeder())->runForUser($user->id);

        $legalEntity = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Firma Test',
            'tax_number' => '1234567890',
            'company_type' => 'limited',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'store_name' => 'Trendyol Store',
            'marketplace' => 'trendyol',
            'is_active' => true,
        ]);

        $warehouse = Warehouse::create([
            'user_id' => $user->id,
            'name' => 'Main',
            'code' => 'depo-main',
            'is_default' => true,
            'is_active' => true,
        ]);

        $product = MpProduct::create([
            'user_id' => $user->id,
            'stock_code' => 'PRD-MKT',
            'product_name' => 'Mkt Item',
            'barcode' => 'BAR-MKT',
        ]);

        // Place 10 in stock
        StockBalance::create([
            'user_id' => $user->id,
            'warehouse_id' => $warehouse->id,
            'stock_code' => 'PRD-MKT',
            'quantity' => 10,
        ]);

        $order = ChannelOrder::create([
            'store_id' => $store->id,
            'legal_entity_id' => $legalEntity->id,
            'external_order_id' => '112233',
            'order_number' => 'TY-001',
            'order_status' => 'approved',
            'currency' => 'TRY',
            'customer_name' => 'John Doe',
            'ordered_at' => now(),
        ]);

        ChannelOrderItem::create([
            'channel_order_id' => $order->id,
            'store_id' => $store->id,
            'external_line_id' => 'line-1',
            'stock_code' => 'PRD-MKT',
            'quantity' => 2,
            'unit_price' => 50.00,
            'vat_rate' => 20.00,
        ]);

        Livewire::actingAs($user)
            ->test('accounting.marketplace-bridge')
            ->call('bridgeSingleOrder', $order->id)
            ->assertSet('messageType', 'success');

        $this->assertDatabaseHas('sales_orders', [
            'user_id' => $user->id,
            'document_number' => 'TY-001',
            'status' => 'approved',
            'total_amount' => 120.00, // 2 * 50 * 1.20 = 120
        ]);

        // Stock reduced
        $this->assertEquals(8, StockBalance::where('user_id', $user->id)->first()->quantity);
    }

    public function test_bridging_financial_events(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        (new \Database\Seeders\ChartOfAccountsSeeder())->runForUser($user->id);

        $legalEntity = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Firma Test',
            'tax_number' => '1234567890',
            'company_type' => 'limited',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'store_name' => 'Trendyol Store',
            'marketplace' => 'trendyol',
            'is_active' => true,
        ]);

        $event = OrderFinancialEvent::create([
            'store_id' => $store->id,
            'legal_entity_id' => $legalEntity->id,
            'event_type' => 'commission',
            'amount' => 25.00,
            'event_date' => now(),
            'currency' => 'TRY',
            'event_source' => 'trendyol',
        ]);

        Livewire::actingAs($user)
            ->test('accounting.marketplace-bridge')
            ->call('bridgeSingleEvent', $event->id)
            ->assertSet('messageType', 'success');

        $this->assertDatabaseHas('journal_entries', [
            'user_id' => $user->id,
            'source_type' => 'financial_event',
            'source_id' => $event->id,
        ]);
    }

    public function test_tenant_isolation_on_marketplace_bridge(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user1 = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $user2 = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $legalEntity2 = LegalEntity::create([
            'user_id' => $user2->id,
            'name' => 'Firma Test 2',
            'tax_number' => '0987654321',
            'company_type' => 'limited',
            'is_active' => true,
        ]);

        $store2 = MarketplaceStore::create([
            'user_id' => $user2->id,
            'legal_entity_id' => $legalEntity2->id,
            'store_name' => 'Store 2',
            'marketplace' => 'trendyol',
            'is_active' => true,
        ]);

        $order2 = ChannelOrder::create([
            'store_id' => $store2->id,
            'legal_entity_id' => $legalEntity2->id,
            'external_order_id' => '999',
            'order_number' => 'TY-999',
            'order_status' => 'approved',
            'currency' => 'TRY',
            'customer_name' => 'User 2 Client',
        ]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        // User 1 attempting to bridge User 2's order should fail
        Livewire::actingAs($user1)
            ->test('accounting.marketplace-bridge')
            ->call('bridgeSingleOrder', $order2->id);
    }

    public function test_ui_filters_sorting_and_column_toggling(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        Livewire::actingAs($user)
            ->test('accounting.marketplace-bridge')
            // Column toggling
            ->assertSet('visibleColumns', ['id', 'store_name', 'order_number', 'customer_name', 'ordered_at', 'status', 'actions'])
            ->call('toggleColumn', 'customer_name')
            ->assertSet('visibleColumns', ['id', 'store_name', 'order_number', 'ordered_at', 'status', 'actions'])
            // Sorting whitelist check
            ->set('sortColumn', 'id')
            ->call('sortTable', 'order_number')
            ->assertSet('sortColumn', 'order_number')
            // Non-whitelist sort should be ignored
            ->call('sortTable', 'invalid_column_xyz')
            ->assertSet('sortColumn', 'order_number')
            // Reset filters
            ->set('search', 'TY-001')
            ->call('clearFilters')
            ->assertSet('search', '');
    }

    public function test_batch_action_provides_proper_feedback(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        (new \Database\Seeders\ChartOfAccountsSeeder())->runForUser($user->id);

        $legalEntity = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Firma Test',
            'tax_number' => '1234567890',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'store_name' => 'Trendyol Store',
            'marketplace' => 'trendyol',
            'is_active' => true,
        ]);

        $warehouse = Warehouse::create([
            'user_id' => $user->id,
            'name' => 'Main',
            'code' => 'depo-main',
            'is_default' => true,
            'is_active' => true,
        ]);

        $order = ChannelOrder::create([
            'store_id' => $store->id,
            'legal_entity_id' => $legalEntity->id,
            'external_order_id' => '112233',
            'order_number' => 'TY-001',
            'order_status' => 'approved',
            'currency' => 'TRY',
            'customer_name' => 'John Doe',
            'ordered_at' => now(),
        ]);

        // Missing items, should fail
        Livewire::actingAs($user)
            ->test('accounting.marketplace-bridge')
            ->call('bridgeFilteredOrders')
            ->assertSet('messageType', 'error')
            ->assertSee('0 başarılı, 1 başarısız, 0 atlandı');
    }

    public function test_ui_retry_action_reprocesses_run(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        (new \Database\Seeders\ChartOfAccountsSeeder())->runForUser($user->id);

        $run = \App\Models\MarketplaceFinanceBridgeRun::create([
            'user_id' => $user->id,
            'bridge_type' => 'order',
            'status' => 'failed',
        ]);

        Livewire::actingAs($user)
            ->test('accounting.marketplace-bridge')
            ->call('retryRun', $run->id)
            ->assertSet('messageType', 'error'); // fails since channel_order_id is missing, but verifies call executes
    }
}
