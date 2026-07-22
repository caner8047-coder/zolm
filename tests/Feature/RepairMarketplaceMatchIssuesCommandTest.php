<?php

namespace Tests\Feature;

use App\Models\ChannelOrder;
use App\Models\ChannelOrderItem;
use App\Models\IntegrationSyncProfile;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\MpProduct;
use App\Models\ProductMatchIssue;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RepairMarketplaceMatchIssuesCommandTest extends TestCase
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

    public function test_it_repairs_unmatched_order_items_into_actionable_listing_issues(): void
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $entity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Repair Ltd.',
            'tax_number' => '5'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $entity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'ZEM REPAIR',
            'store_code' => 'ZEM-REPAIR-'.$suffix,
            'seller_id' => 'R'.$suffix,
            'status' => 'active',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store->syncProfile()->create(IntegrationSyncProfile::defaults());
        $store->refresh()->load('syncProfile');

        $candidate = MpProduct::query()->create([
            'user_id' => $user->id,
            'product_name' => 'Benetta Bohem Kanepe Kırık Beyaz '.$suffix,
            'stock_code' => 'MASTER-BNT-'.$suffix,
            'barcode' => '8685'.$suffix,
            'model_code' => 'ZEMBNT',
            'brand' => 'Zem',
            'category_name' => 'Kanepe',
            'sale_price' => 1299.90,
            'cogs' => 700,
            'stock_quantity' => 2,
        ]);

        $order = ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $entity->id,
            'external_order_id' => 'ORD-REPAIR-'.$suffix,
            'order_number' => 'ORD-REPAIR-'.$suffix,
            'order_status' => 'Created',
            'ordered_at' => now(),
        ]);

        $item = ChannelOrderItem::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $order->id,
            'external_line_id' => 'LINE-REPAIR-'.$suffix,
            'stock_code' => 'REPAIR-SKU-'.$suffix,
            'barcode' => '8695'.$suffix,
            'product_name' => 'Benetta Koltuk Takımı Kırık Beyaz ZEMBNTKT010 '.$suffix,
            'quantity' => 1,
            'unit_price' => 1299.90,
            'gross_amount' => 1299.90,
            'billable_amount' => 1299.90,
            'is_matched' => false,
        ]);

        $this->artisan('marketplace:repair-match-issues', [
            '--store' => [$store->id],
            '--no-recalculate' => true,
        ])->assertExitCode(0);

        $item->refresh();

        $this->assertNotNull($item->channel_listing_id);
        $this->assertNull($item->mp_product_id);
        $this->assertFalse((bool) $item->is_matched);

        $this->assertDatabaseHas('product_match_issues', [
            'store_id' => $store->id,
            'channel_listing_id' => $item->channel_listing_id,
            'match_status' => 'pending',
            'match_reason' => 'candidate_found',
        ]);

        $issue = ProductMatchIssue::query()
            ->where('store_id', $store->id)
            ->where('channel_listing_id', $item->channel_listing_id)
            ->where('match_status', 'pending')
            ->firstOrFail();

        $this->assertContains($candidate->id, collect((array) $issue->candidate_ids_json)->map(fn ($id) => (int) $id)->all());

        $this->assertSame(1, ProductMatchIssue::query()
            ->where('store_id', $store->id)
            ->where('channel_listing_id', $item->channel_listing_id)
            ->where('match_status', 'pending')
            ->count());
    }
}
