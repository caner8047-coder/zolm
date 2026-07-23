<?php

namespace Tests\Feature;

use App\Models\ChannelListing;
use App\Models\ChannelOrder;
use App\Models\ChannelOrderItem;
use App\Models\ChannelProduct;
use App\Models\IntegrationConnection;
use App\Models\IntegrationSyncProfile;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\MpProduct;
use App\Models\ProductMatchIssue;
use App\Models\User;
use App\Services\Marketplace\MarketplaceManualMatchService;
use App\Services\Marketplace\MarketplaceProductMatcher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketplaceManualMatchServiceTest extends TestCase
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

    public function test_it_manually_matches_listing_and_order_items_and_recalculates_profit(): void
    {
        [$user, $store, $product, $listing, $order, $item, $issue] = $this->createGraph();

        $result = app(MarketplaceManualMatchService::class)->manualMatch($issue, $product, $user->id);

        $this->assertSame(1, $result['updated_items']);
        $this->assertSame(1, $result['impacted_orders']);

        $this->assertDatabaseHas('channel_listings', [
            'id' => $listing->id,
            'mp_product_id' => $product->id,
        ]);

        $this->assertDatabaseHas('channel_order_items', [
            'id' => $item->id,
            'channel_listing_id' => $listing->id,
            'mp_product_id' => $product->id,
            'is_matched' => true,
            'match_source' => 'manual',
        ]);

        $this->assertDatabaseHas('product_match_issues', [
            'id' => $issue->id,
            'match_status' => 'resolved',
            'resolved_by' => $user->id,
        ]);

        $this->assertDatabaseHas('order_profit_snapshots', [
            'store_id' => $store->id,
            'channel_order_id' => $order->id,
            'profit_state' => 'estimated',
        ]);
    }

    public function test_it_creates_a_master_product_from_an_unmatched_channel_listing_and_resolves_the_issue(): void
    {
        [$user, $store, $existingProduct, $listing, $order, $item, $issue] = $this->createGraph();
        $existingProduct->delete();
        $listing->channelProduct->update([
            'images' => [
                ['url' => 'https://cdn.example.test/zem-cover.jpg'],
                ['imageUrl' => 'https://cdn.example.test/zem-detail.jpg'],
            ],
        ]);

        $result = app(MarketplaceManualMatchService::class)->createMasterProductFromListing($issue, $user->id);

        $this->assertTrue($result['created']);
        $this->assertSame('ZEM Test Koltuk', $result['product']->product_name);
        $this->assertSame('STK-'.substr($listing->listing_id, 5), $result['product']->stock_code);
        $this->assertSame('https://cdn.example.test/zem-cover.jpg', $result['product']->image_url);
        $this->assertSame([
            'https://cdn.example.test/zem-cover.jpg',
            'https://cdn.example.test/zem-detail.jpg',
        ], $result['product']->image_urls);
        $this->assertSame(1, $result['updated_items']);

        $this->assertDatabaseHas('channel_listings', [
            'id' => $listing->id,
            'mp_product_id' => $result['product']->id,
        ]);

        $this->assertDatabaseHas('product_match_issues', [
            'id' => $issue->id,
            'match_status' => 'resolved',
            'resolved_by' => $user->id,
        ]);
    }

    public function test_order_item_without_listing_gets_actionable_issue_and_can_be_matched(): void
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $entity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Listing Fallback Ltd.',
            'tax_number' => '6'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $entity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'ZEM LISTING FALLBACK',
            'store_code' => 'ZEM-FALLBACK-'.$suffix,
            'seller_id' => 'F'.$suffix,
            'status' => 'active',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        IntegrationConnection::query()->create([
            'store_id' => $store->id,
            'provider' => 'trendyol',
            'auth_type' => 'api_key_secret',
            'credentials_encrypted' => [
                'seller_id' => 'F'.$suffix,
                'api_key' => 'key',
                'api_secret' => 'secret',
            ],
            'api_base_url' => 'https://apigw.trendyol.com',
            'status' => 'configured',
        ]);

        $store->syncProfile()->create(array_merge(
            IntegrationSyncProfile::defaults(),
            [
                'auto_match_enabled' => true,
                'barcode_fallback_enabled' => true,
            ]
        ));
        $store->refresh()->load('syncProfile');

        $product = MpProduct::query()->create([
            'user_id' => $user->id,
            'product_name' => 'Master Manuel Koltuk '.$suffix,
            'stock_code' => 'MASTER-'.$suffix,
            'barcode' => '868'.$suffix,
            'brand' => 'ZEM',
            'category_name' => 'Mobilya',
            'sale_price' => 3499.90,
            'cogs' => 1500,
            'packaging_cost' => 90,
            'cargo_cost' => 150,
            'stock_quantity' => 4,
        ]);

        $order = ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $entity->id,
            'external_order_id' => 'ORD-FALLBACK-'.$suffix,
            'order_number' => 'ORD-FALLBACK-'.$suffix,
            'order_status' => 'Created',
            'customer_name' => 'Ayse Demir',
            'ordered_at' => now(),
        ]);

        $item = ChannelOrderItem::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $order->id,
            'external_line_id' => 'LINE-FALLBACK-'.$suffix,
            'stock_code' => 'CHANNEL-'.$suffix,
            'barcode' => '869'.$suffix,
            'product_name' => 'Kanalda Farklı Başlık '.$suffix,
            'quantity' => 1,
            'unit_price' => 3499.90,
            'gross_amount' => 3499.90,
            'billable_amount' => 3499.90,
            'commission_rate' => 12,
            'is_matched' => false,
        ]);

        $storeLevelIssue = ProductMatchIssue::query()->create([
            'store_id' => $store->id,
            'channel_listing_id' => null,
            'match_status' => 'pending',
            'match_reason' => 'not_found',
            'candidate_ids_json' => [],
        ]);

        app(MarketplaceProductMatcher::class)->applyToOrderItem($item, $item->stock_code, $item->barcode);

        $item->refresh();
        $this->assertNotNull($item->channel_listing_id);
        $this->assertNull($item->mp_product_id);
        $this->assertFalse((bool) $item->is_matched);

        $listing = ChannelListing::query()->with('channelProduct')->findOrFail($item->channel_listing_id);
        $this->assertStringStartsWith('order-item:stock:', $listing->listing_id);
        $this->assertSame('CHANNEL-'.$suffix, $listing->channelProduct?->stock_code);

        $issue = ProductMatchIssue::query()
            ->where('store_id', $store->id)
            ->where('channel_listing_id', $listing->id)
            ->where('match_status', 'pending')
            ->first();

        $this->assertNotNull($issue);
        $this->assertSame('not_found', $issue->match_reason);
        $this->assertSame('resolved', $storeLevelIssue->fresh()->match_status);

        $result = app(MarketplaceManualMatchService::class)->manualMatch($issue, $product, $user->id);

        $this->assertSame(1, $result['updated_items']);
        $this->assertSame(1, $result['impacted_orders']);
        $this->assertSame($product->id, (int) $listing->fresh()->mp_product_id);
        $this->assertSame($product->id, (int) $item->fresh()->mp_product_id);
        $this->assertTrue((bool) $item->fresh()->is_matched);

        $this->assertDatabaseHas('order_profit_snapshots', [
            'store_id' => $store->id,
            'channel_order_id' => $order->id,
            'profit_state' => 'estimated',
        ]);
    }

    /**
     * @return array{0: User, 1: MarketplaceStore, 2: MpProduct, 3: ChannelListing, 4: ChannelOrder, 5: ChannelOrderItem, 6: ProductMatchIssue}
     */
    protected function createGraph(): array
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $entity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Test Ltd.',
            'tax_number' => '7'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $entity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'ZEM MATCH',
            'store_code' => 'ZEM-MATCH-'.$suffix,
            'seller_id' => 'S'.$suffix,
            'status' => 'active',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        IntegrationConnection::query()->create([
            'store_id' => $store->id,
            'provider' => 'trendyol',
            'auth_type' => 'api_key_secret',
            'credentials_encrypted' => [
                'seller_id' => 'S'.$suffix,
                'api_key' => 'key',
                'api_secret' => 'secret',
            ],
            'api_base_url' => 'https://apigw.trendyol.com',
            'status' => 'configured',
        ]);

        $product = MpProduct::query()->create([
            'user_id' => $user->id,
            'product_name' => 'ZEM Test Koltuk',
            'stock_code' => 'STK-'.$suffix,
            'barcode' => '869'.$suffix,
            'brand' => 'ZEM',
            'category_name' => 'Mobilya',
            'sale_price' => 1299.90,
            'cogs' => 700,
            'packaging_cost' => 50,
            'cargo_cost' => 100,
            'stock_quantity' => 5,
        ]);

        $channelProduct = ChannelProduct::query()->create([
            'store_id' => $store->id,
            'external_product_id' => 'CP-'.$suffix,
            'stock_code' => 'STK-'.$suffix,
            'barcode' => '869'.$suffix,
            'title' => 'ZEM Test Koltuk',
            'brand' => 'ZEM',
            'category_name' => 'Mobilya',
        ]);

        $listing = ChannelListing::query()->create([
            'store_id' => $store->id,
            'channel_product_id' => $channelProduct->id,
            'listing_id' => 'LIST-'.$suffix,
            'listing_status' => 'active',
            'sale_price' => 1299.90,
            'stock_quantity' => 5,
            'currency' => 'TRY',
        ]);

        $order = ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $entity->id,
            'external_order_id' => 'ORD-'.$suffix,
            'order_number' => 'ORD-'.$suffix,
            'order_status' => 'Created',
            'customer_name' => 'Ayse Demir',
            'ordered_at' => now(),
        ]);

        $item = ChannelOrderItem::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $order->id,
            'channel_listing_id' => $listing->id,
            'external_line_id' => 'LINE-'.$suffix,
            'stock_code' => 'STK-'.$suffix,
            'barcode' => '869'.$suffix,
            'product_name' => 'ZEM Test Koltuk',
            'quantity' => 1,
            'unit_price' => 1299.90,
            'gross_amount' => 1299.90,
            'billable_amount' => 1299.90,
            'commission_rate' => 12,
            'is_matched' => false,
        ]);

        $issue = ProductMatchIssue::query()->create([
            'store_id' => $store->id,
            'channel_listing_id' => $listing->id,
            'match_status' => 'pending',
            'match_reason' => 'not_found',
            'candidate_ids_json' => [$product->id],
        ]);

        return [$user, $store, $product, $listing, $order, $item, $issue];
    }
}
