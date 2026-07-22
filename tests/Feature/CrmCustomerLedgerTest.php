<?php

namespace Tests\Feature;

use App\Livewire\CrmCustomerLedger;
use App\Models\ChannelListing;
use App\Models\ChannelOrder;
use App\Models\ChannelOrderItem;
use App\Models\CrmCustomerLedgerEntry;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\MpProduct;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Crm\CrmCustomerLedgerProjectionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class CrmCustomerLedgerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql.host', 'mysql');
        config()->set('database.connections.mysql.port', '3306');
        config()->set('database.connections.mysql.database', $this->mysqlTestDatabaseName());
        config()->set('database.connections.mysql.username', 'sail');
        config()->set('database.connections.mysql.password', 'password');
        DB::purge('mysql');
        DB::reconnect('mysql');
        DB::setDefaultConnection('mysql');
    }

    public function test_manual_customer_ledger_entry_creates_contact_and_timeline_event(): void
    {
        $user = User::factory()->create([
            'role' => 'operator',
            'is_active' => true,
        ]);

        $this->actingAs($user);

        Livewire::test(CrmCustomerLedger::class)
            ->assertSee('Müşteri Cari Defteri')
            ->set('entryForm.customer_name', 'Ayşe Cari Test')
            ->set('entryForm.customer_phone', '0532 111 22 33')
            ->set('entryForm.platform', 'Trendyol')
            ->set('entryForm.marketplace_order_number', 'TY-CARI-1')
            ->set('entryForm.product_name', 'Berjer Koltuk')
            ->set('entryForm.tariff_name', 'Tarife 2')
            ->set('entryForm.quantity', 2)
            ->set('entryForm.unit_price', 1500)
            ->set('entryForm.commission_rate', 18)
            ->set('entryForm.cargo_amount', 120)
            ->set('entryForm.cost_amount', 1600)
            ->call('saveManualEntry')
            ->assertSet('ledgerMessageTone', 'success');

        $entry = CrmCustomerLedgerEntry::query()
            ->where('user_id', $user->id)
            ->where('marketplace_order_number', 'TY-CARI-1')
            ->firstOrFail();

        $this->assertSame('Ayşe Cari Test', $entry->contact->display_name);
        $this->assertSame('Berjer Koltuk', $entry->product_name);
        $this->assertSame(3000.0, (float) $entry->gross_amount);
        $this->assertSame(540.0, (float) $entry->commission_amount);
        $this->assertSame(740.0, (float) $entry->profit_amount);

        $this->assertDatabaseHas('crm_timeline_events', [
            'user_id' => $user->id,
            'contact_id' => $entry->contact_id,
            'source_type' => 'crm_customer_ledger',
            'subject_type' => CrmCustomerLedgerEntry::class,
            'subject_id' => $entry->id,
        ]);
    }

    public function test_selected_contact_query_param_filters_customer_ledger(): void
    {
        $user = User::factory()->create([
            'role' => 'operator',
            'is_active' => true,
        ]);
        $service = app(CrmCustomerLedgerProjectionService::class);

        $first = $service->createManualEntry($user, [
            'customer_name' => 'Filtre Cari Bir',
            'customer_phone' => '0532 300 10 10',
            'platform' => 'Trendyol',
            'product_name' => 'Filtrelenecek Berjer',
            'quantity' => 1,
            'unit_price' => 900,
            'commission_rate' => 10,
            'cost_amount' => 500,
            'status' => 'completed',
            'purchased_at' => now(),
        ]);
        $service->createManualEntry($user, [
            'customer_name' => 'Filtre Cari İki',
            'customer_phone' => '0532 300 20 20',
            'platform' => 'Hepsiburada',
            'product_name' => 'Gizlenecek Masa',
            'quantity' => 1,
            'unit_price' => 1200,
            'commission_rate' => 12,
            'cost_amount' => 700,
            'status' => 'completed',
            'purchased_at' => now(),
        ]);

        $this->actingAs($user);

        Livewire::withQueryParams(['contact' => $first->contact_id])
            ->test(CrmCustomerLedger::class)
            ->assertSet('selectedContactId', $first->contact_id)
            ->assertSee('Filtrelenecek Berjer')
            ->assertDontSee('Gizlenecek Masa');
    }

    public function test_manual_customer_ledger_entry_can_be_edited_and_voided(): void
    {
        $user = User::factory()->create([
            'role' => 'operator',
            'is_active' => true,
        ]);
        $entry = app(CrmCustomerLedgerProjectionService::class)->createManualEntry($user, [
            'customer_name' => 'Düzenleme Cari Test',
            'customer_phone' => '0532 333 44 55',
            'platform' => 'Manuel',
            'product_name' => 'Eski Ürün',
            'quantity' => 1,
            'unit_price' => 1000,
            'commission_rate' => 20,
            'cost_amount' => 400,
            'status' => 'completed',
            'purchased_at' => now(),
        ]);

        $this->actingAs($user);

        Livewire::test(CrmCustomerLedger::class)
            ->call('editEntry', $entry->id)
            ->assertSet('editingEntryId', $entry->id)
            ->set('entryForm.product_name', 'Güncel Ürün')
            ->set('entryForm.unit_price', 1500)
            ->set('entryForm.commission_rate', 10)
            ->set('entryForm.cost_amount', 600)
            ->call('saveManualEntry')
            ->assertSet('editingEntryId', null)
            ->call('voidManualEntry', $entry->id)
            ->assertSet('ledgerMessageTone', 'success');

        $entry->refresh();

        $this->assertSame('Güncel Ürün', $entry->product_name);
        $this->assertSame(1500.0, (float) $entry->gross_amount);
        $this->assertSame(150.0, (float) $entry->commission_amount);
        $this->assertSame(750.0, (float) $entry->profit_amount);
        $this->assertSame('cancelled', $entry->status);

        $this->assertDatabaseHas('crm_timeline_events', [
            'user_id' => $user->id,
            'contact_id' => $entry->contact_id,
            'source_type' => 'crm_customer_ledger',
            'subject_type' => CrmCustomerLedgerEntry::class,
            'subject_id' => $entry->id,
        ]);
    }

    public function test_order_items_are_projected_to_customer_ledger_with_recipe_and_commission(): void
    {
        $user = User::factory()->create([
            'role' => 'operator',
            'is_active' => true,
        ]);
        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'ZOLM Test Ltd',
            'tax_number' => '1234567890',
        ]);
        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'Test Trendyol',
            'seller_id' => 'CARI-'.random_int(10000, 99999),
            'status' => 'active',
            'is_active' => true,
        ]);
        $product = MpProduct::query()->create([
            'user_id' => $user->id,
            'barcode' => 'BRJ-CARI-'.random_int(10000, 99999),
            'stock_code' => 'BRJ-CARI-1',
            'product_name' => 'Berjer Koltuk',
            'cogs' => 500,
            'packaging_cost' => 50,
            'vat_rate' => 20,
            'sale_price' => 1000,
            'commission_rate' => 18,
            'status' => 'active',
        ]);
        $recipe = Recipe::query()->create([
            'user_id' => $user->id,
            'mp_product_id' => $product->id,
            'stock_code' => 'BRJ-CARI-1',
            'name' => 'Berjer Reçetesi',
            'version' => 'v1',
            'status' => 'active',
        ]);
        $suffix = (string) random_int(10000, 99999);
        $listing = ChannelListing::query()->create([
            'store_id' => $store->id,
            'mp_product_id' => $product->id,
            'listing_id' => 'LIST-CARI-'.$suffix,
            'listing_status' => 'active',
            'sale_price' => 1000,
            'commission_rate' => 18,
            'commission_source' => 'Tarife 2',
        ]);
        $order = ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $legalEntity->id,
            'external_order_id' => 'EXT-CARI-'.$suffix,
            'order_number' => 'TY-CARI-ORDER-'.$suffix,
            'order_status' => 'delivered',
            'customer_name' => 'Mehmet Cari Test',
            'customer_phone' => '0532 222 33 44',
            'ordered_at' => now(),
        ]);
        ChannelOrderItem::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $order->id,
            'channel_listing_id' => $listing->id,
            'mp_product_id' => $product->id,
            'external_line_id' => 'LINE-CARI-'.$suffix,
            'stock_code' => 'BRJ-CARI-1',
            'barcode' => $product->barcode,
            'product_name' => 'Berjer Koltuk',
            'quantity' => 1,
            'unit_price' => 1000,
            'gross_amount' => 1000,
            'commission_rate' => 18,
            'line_status' => 'delivered',
        ]);

        $summary = app(CrmCustomerLedgerProjectionService::class)->syncUser($user, [
            'recent_days' => 30,
        ]);

        $this->assertSame(1, $summary['entries']);
        $this->assertSame(1, $summary['created']);

        $entry = CrmCustomerLedgerEntry::query()
            ->where('user_id', $user->id)
            ->where('marketplace_order_number', 'TY-CARI-ORDER-'.$suffix)
            ->firstOrFail();

        $this->assertSame('Mehmet Cari Test', $entry->contact->display_name);
        $this->assertSame('trendyol', $entry->platform);
        $this->assertSame($recipe->id, $entry->recipe_id);
        $this->assertSame('Berjer Reçetesi', $entry->recipe_name);
        $this->assertSame('Tarife 2', $entry->tariff_name);
        $this->assertSame(180.0, (float) $entry->commission_amount);
    }
}
