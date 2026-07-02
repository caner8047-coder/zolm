<?php

namespace Tests\Feature;

use App\Livewire\MarketplaceMatchingCenter;
use App\Models\ChannelListing;
use App\Models\ChannelOrder;
use App\Models\ChannelOrderItem;
use App\Models\ChannelProduct;
use App\Models\IntegrationConnection;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\MpProduct;
use App\Models\ProductMatchIssue;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class MarketplaceMatchingCenterBulkActionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql.host', 'mysql');
        config()->set('database.connections.mysql.port', '3306');
        config()->set('database.connections.mysql.database', 'zolm');
        config()->set('database.connections.mysql.username', 'sail');
        config()->set('database.connections.mysql.password', 'password');
        DB::purge('mysql');
        DB::reconnect('mysql');
        DB::setDefaultConnection('mysql');
    }

    public function test_it_ignores_selected_issues_from_matching_center(): void
    {
        [$user, $pendingIssue] = $this->createGraph('pending');

        $this->actingAs($user);

        Livewire::test(MarketplaceMatchingCenter::class)
            ->set('selectedIssueIds', [(string) $pendingIssue->id])
            ->set('bulkIssueActionType', 'ignore')
            ->call('runBulkIssueAction')
            ->assertSet('selectedIssueIds', [])
            ->assertSet('bulkIssueActionType', '');

        $this->assertDatabaseHas('product_match_issues', [
            'id' => $pendingIssue->id,
            'match_status' => 'ignored',
            'resolved_by' => $user->id,
        ]);
    }

    public function test_it_reopens_selected_issues_from_matching_center(): void
    {
        [$user, $resolvedIssue] = $this->createGraph('resolved');

        $this->actingAs($user);

        Livewire::test(MarketplaceMatchingCenter::class)
            ->set('selectedIssueIds', [(string) $resolvedIssue->id])
            ->set('bulkIssueActionType', 'reopen')
            ->call('runBulkIssueAction')
            ->assertSet('selectedIssueIds', [])
            ->assertSet('bulkIssueActionType', '');

        $this->assertDatabaseHas('product_match_issues', [
            'id' => $resolvedIssue->id,
            'match_status' => 'pending',
            'resolved_by' => null,
        ]);
    }

    public function test_it_matches_recommended_candidate_from_stored_candidate_ids(): void
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $entity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Recommended Ltd.',
            'tax_number' => '9' . $suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $entity->id,
            'marketplace' => 'n11',
            'store_name' => 'ZEM RECOMMENDED',
            'store_code' => 'ZEM-REC-' . $suffix,
            'seller_id' => 'REC' . $suffix,
            'status' => 'active',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        IntegrationConnection::query()->create([
            'store_id' => $store->id,
            'provider' => 'n11',
            'auth_type' => 'api_key_secret',
            'credentials_encrypted' => [
                'api_key' => 'key',
                'api_secret' => 'secret',
            ],
            'api_base_url' => 'https://api.n11.com',
            'status' => 'configured',
        ]);

        $product = MpProduct::query()->create([
            'user_id' => $user->id,
            'product_name' => 'Master Aday Urun ' . $suffix,
            'stock_code' => 'MASTER-' . $suffix,
            'barcode' => '868' . $suffix,
            'brand' => 'Zem',
            'category_name' => 'Mobilya',
            'sale_price' => 2499.90,
            'cogs' => 1200,
            'stock_quantity' => 3,
        ]);

        $channelProduct = ChannelProduct::query()->create([
            'store_id' => $store->id,
            'external_product_id' => 'CP-REC-' . $suffix,
            'stock_code' => $product->stock_code,
            'barcode' => $product->barcode,
            'title' => 'Pazaryeri Farkli Baslik ' . $suffix,
            'brand' => 'Zem',
            'category_name' => 'Mobilya',
        ]);

        $listing = ChannelListing::query()->create([
            'store_id' => $store->id,
            'channel_product_id' => $channelProduct->id,
            'listing_id' => 'LIST-REC-' . $suffix,
            'listing_status' => 'active',
            'sale_price' => 2499.90,
            'stock_quantity' => 2,
            'currency' => 'TRY',
        ]);

        $order = ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $entity->id,
            'external_order_id' => 'ORD-REC-' . $suffix,
            'order_number' => 'ORD-REC-' . $suffix,
            'order_status' => 'Created',
            'ordered_at' => now(),
        ]);

        $item = ChannelOrderItem::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $order->id,
            'channel_listing_id' => $listing->id,
            'external_line_id' => 'LINE-REC-' . $suffix,
            'stock_code' => $channelProduct->stock_code,
            'barcode' => $channelProduct->barcode,
            'product_name' => $channelProduct->title,
            'quantity' => 1,
            'unit_price' => 2499.90,
            'gross_amount' => 2499.90,
            'billable_amount' => 2499.90,
            'is_matched' => false,
        ]);

        $issue = ProductMatchIssue::query()->create([
            'store_id' => $store->id,
            'channel_listing_id' => $listing->id,
            'match_status' => 'pending',
            'match_reason' => 'candidate_found',
            'candidate_ids_json' => [$product->id],
        ]);

        $this->actingAs($user);

        Livewire::test(MarketplaceMatchingCenter::class)
            ->call('manualMatchRecommended', $issue->id);

        $this->assertSame('resolved', $issue->fresh()->match_status);
        $this->assertSame($product->id, (int) $listing->fresh()->mp_product_id);
        $this->assertSame($product->id, (int) $item->fresh()->mp_product_id);
        $this->assertTrue((bool) $item->fresh()->is_matched);
    }

    /**
     * @return array{0: User, 1: ProductMatchIssue}
     */
    protected function createGraph(string $status): array
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $entity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Test Ltd.',
            'tax_number' => '8' . $suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $entity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'ZEM MATCH CENTER',
            'store_code' => 'ZEM-MC-' . $suffix,
            'seller_id' => 'MC' . $suffix,
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
                'seller_id' => 'MC' . $suffix,
                'api_key' => 'key',
                'api_secret' => 'secret',
            ],
            'api_base_url' => 'https://apigw.trendyol.com',
            'status' => 'configured',
        ]);

        $channelProduct = ChannelProduct::query()->create([
            'store_id' => $store->id,
            'external_product_id' => 'CP-' . $suffix,
            'stock_code' => 'STK-' . $suffix,
            'barcode' => '869' . $suffix,
            'title' => 'ZEM Test Urun',
        ]);

        $listing = ChannelListing::query()->create([
            'store_id' => $store->id,
            'channel_product_id' => $channelProduct->id,
            'listing_id' => 'LIST-' . $suffix,
            'listing_status' => 'active',
            'sale_price' => 999.90,
            'stock_quantity' => 2,
            'currency' => 'TRY',
        ]);

        $issue = ProductMatchIssue::query()->create([
            'store_id' => $store->id,
            'channel_listing_id' => $listing->id,
            'match_status' => $status,
            'match_reason' => 'not_found',
            'candidate_ids_json' => [],
            'resolved_by' => $status === 'resolved' ? $user->id : null,
            'resolved_at' => $status === 'resolved' ? now() : null,
        ]);

        return [$user, $issue];
    }
}
