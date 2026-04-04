<?php

namespace Tests\Feature;

use App\Livewire\MarketplaceMatchingCenter;
use App\Models\ChannelListing;
use App\Models\ChannelProduct;
use App\Models\IntegrationConnection;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
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
