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
use App\Services\MpSettingsService;
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
        config()->set('database.connections.mysql.database', $this->mysqlTestDatabaseName());
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
            'tax_number' => '9'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $entity->id,
            'marketplace' => 'n11',
            'store_name' => 'ZEM RECOMMENDED',
            'store_code' => 'ZEM-REC-'.$suffix,
            'seller_id' => 'REC'.$suffix,
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
            'product_name' => 'Master Aday Urun '.$suffix,
            'stock_code' => 'MASTER-'.$suffix,
            'barcode' => '868'.$suffix,
            'brand' => 'Zem',
            'category_name' => 'Mobilya',
            'sale_price' => 2499.90,
            'cogs' => 1200,
            'stock_quantity' => 3,
        ]);

        $channelProduct = ChannelProduct::query()->create([
            'store_id' => $store->id,
            'external_product_id' => 'CP-REC-'.$suffix,
            'stock_code' => $product->stock_code,
            'barcode' => $product->barcode,
            'title' => 'Pazaryeri Farkli Baslik '.$suffix,
            'brand' => 'Zem',
            'category_name' => 'Mobilya',
        ]);

        $listing = ChannelListing::query()->create([
            'store_id' => $store->id,
            'channel_product_id' => $channelProduct->id,
            'listing_id' => 'LIST-REC-'.$suffix,
            'listing_status' => 'active',
            'sale_price' => 2499.90,
            'stock_quantity' => 2,
            'currency' => 'TRY',
        ]);

        $order = ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $entity->id,
            'external_order_id' => 'ORD-REC-'.$suffix,
            'order_number' => 'ORD-REC-'.$suffix,
            'order_status' => 'Created',
            'ordered_at' => now(),
        ]);

        $item = ChannelOrderItem::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $order->id,
            'channel_listing_id' => $listing->id,
            'external_line_id' => 'LINE-REC-'.$suffix,
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
            'tax_number' => '8'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $entity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'ZEM MATCH CENTER',
            'store_code' => 'ZEM-MC-'.$suffix,
            'seller_id' => 'MC'.$suffix,
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
                'seller_id' => 'MC'.$suffix,
                'api_key' => 'key',
                'api_secret' => 'secret',
            ],
            'api_base_url' => 'https://apigw.trendyol.com',
            'status' => 'configured',
        ]);

        $channelProduct = ChannelProduct::query()->create([
            'store_id' => $store->id,
            'external_product_id' => 'CP-'.$suffix,
            'stock_code' => 'STK-'.$suffix,
            'barcode' => '869'.$suffix,
            'title' => 'ZEM Test Urun',
        ]);

        $listing = ChannelListing::query()->create([
            'store_id' => $store->id,
            'channel_product_id' => $channelProduct->id,
            'listing_id' => 'LIST-'.$suffix,
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

    public function test_auto_recommend_threshold_defaults_to_100(): void
    {
        $user = User::factory()->create();

        $this->assertSame(100, (new MpSettingsService($user->id))->getAutoRecommendThreshold());
    }

    public function test_candidate_with_score_above_threshold_is_recommended(): void
    {
        $user = User::factory()->create();

        (new MpSettingsService($user->id))->set('matching.auto_recommend_threshold', 100);

        $product = MpProduct::query()->create([
            'user_id' => $user->id,
            'barcode' => '8691234567890',
            'stock_code' => 'MATCH-TH-120',
            'product_name' => 'Test Ürün',
            'cogs' => 100,
            'vat_rate' => 10,
            'market_price' => 200,
            'sale_price' => 190,
            'commission_rate' => 21,
            'stock_quantity' => 10,
            'desi' => 2,
            'pieces' => 1,
            'status' => 'active',
        ]);

        $product->setAttribute('match_score_metric', 120);

        $this->actingAs($user);

        $component = new MarketplaceMatchingCenter;

        $reflection = new \ReflectionMethod(MarketplaceMatchingCenter::class, 'canAutoRecommend');
        $reflection->setAccessible(true);

        $this->assertTrue($reflection->invoke($component, $product));
    }

    public function test_candidate_below_threshold_is_not_recommended(): void
    {
        $user = User::factory()->create();

        (new MpSettingsService($user->id))->set('matching.auto_recommend_threshold', 150);

        $product = MpProduct::query()->create([
            'user_id' => $user->id,
            'barcode' => '8691234567891',
            'stock_code' => 'MATCH-TH-150',
            'product_name' => 'Test Ürün 2',
            'cogs' => 100,
            'vat_rate' => 10,
            'market_price' => 200,
            'sale_price' => 190,
            'commission_rate' => 21,
            'stock_quantity' => 10,
            'desi' => 2,
            'pieces' => 1,
            'status' => 'active',
        ]);

        $product->setAttribute('match_score_metric', 120);

        $this->actingAs($user);

        $component = new MarketplaceMatchingCenter;

        $reflection = new \ReflectionMethod(MarketplaceMatchingCenter::class, 'canAutoRecommend');
        $reflection->setAccessible(true);

        $this->assertFalse($reflection->invoke($component, $product));
    }

    public function test_invalid_threshold_normalizes_to_100(): void
    {
        $user = User::factory()->create();
        $service = new MpSettingsService($user->id);

        $service->set('matching.auto_recommend_threshold', 0);
        $this->assertSame(100, $service->getAutoRecommendThreshold());

        $service->set('matching.auto_recommend_threshold', 999);
        $this->assertSame(100, $service->getAutoRecommendThreshold());
    }

    public function test_threshold_is_isolated_per_user(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        (new MpSettingsService($user1->id))->set('matching.auto_recommend_threshold', 150);

        $this->assertSame(150, (new MpSettingsService($user1->id))->getAutoRecommendThreshold());
        $this->assertSame(100, (new MpSettingsService($user2->id))->getAutoRecommendThreshold());
    }

    public function test_score_tone_success_when_above_threshold(): void
    {
        $user = User::factory()->create();

        (new MpSettingsService($user->id))->set('matching.auto_recommend_threshold', 100);

        $product = $this->createScoredProduct($user->id, 'TONE-S1', 120);

        $this->actingAs($user);

        $component = new MarketplaceMatchingCenter;
        $reflection = new \ReflectionMethod(MarketplaceMatchingCenter::class, 'candidateScoreTone');
        $reflection->setAccessible(true);

        $this->assertSame('success', $reflection->invoke($component, $product));
    }

    public function test_score_tone_info_when_below_threshold_but_above_70(): void
    {
        $user = User::factory()->create();

        (new MpSettingsService($user->id))->set('matching.auto_recommend_threshold', 150);

        $product = $this->createScoredProduct($user->id, 'TONE-I1', 120);

        $this->actingAs($user);

        $component = new MarketplaceMatchingCenter;
        $reflection = new \ReflectionMethod(MarketplaceMatchingCenter::class, 'candidateScoreTone');
        $reflection->setAccessible(true);

        $this->assertSame('info', $reflection->invoke($component, $product));
    }

    public function test_score_tone_default_when_threshold_is_1_and_score_is_0(): void
    {
        $user = User::factory()->create();

        (new MpSettingsService($user->id))->set('matching.auto_recommend_threshold', 1);

        $product = $this->createScoredProduct($user->id, 'TONE-D1', 0);

        $this->actingAs($user);

        $component = new MarketplaceMatchingCenter;
        $reflection = new \ReflectionMethod(MarketplaceMatchingCenter::class, 'candidateScoreTone');
        $reflection->setAccessible(true);

        $this->assertSame('default', $reflection->invoke($component, $product));
    }

    protected function createScoredProduct(int $userId, string $suffix, int $score): MpProduct
    {
        $product = MpProduct::query()->create([
            'user_id' => $userId,
            'barcode' => "869{$suffix}",
            'stock_code' => "TONE-{$suffix}",
            'product_name' => "Tone Test {$suffix}",
            'cogs' => 100,
            'vat_rate' => 10,
            'market_price' => 200,
            'sale_price' => 190,
            'commission_rate' => 21,
            'stock_quantity' => 10,
            'desi' => 2,
            'pieces' => 1,
            'status' => 'active',
        ]);

        $product->setAttribute('match_score_metric', $score);

        return $product;
    }

    public function test_manual_search_respects_candidate_limits(): void
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        (new MpSettingsService($user->id))->set('matching.candidate_search_limit', 5);
        (new MpSettingsService($user->id))->set('matching.candidate_result_limit', 3);

        $entity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Limit Test Ltd.',
            'tax_number' => '7'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $entity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'ZEM LIMIT',
            'store_code' => 'ZEM-LT-'.$suffix,
            'seller_id' => 'LT'.$suffix,
            'status' => 'active',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $channelProduct = ChannelProduct::query()->create([
            'store_id' => $store->id,
            'external_product_id' => 'CP-LT-'.$suffix,
            'stock_code' => 'STK-LT-'.$suffix,
            'barcode' => '869'.$suffix,
            'title' => 'Koltuk Takimi Test',
        ]);

        $listing = ChannelListing::query()->create([
            'store_id' => $store->id,
            'channel_product_id' => $channelProduct->id,
            'listing_id' => 'LIST-LT-'.$suffix,
            'listing_status' => 'active',
            'sale_price' => 1999.90,
            'stock_quantity' => 5,
            'currency' => 'TRY',
        ]);

        $issue = ProductMatchIssue::query()->create([
            'store_id' => $store->id,
            'channel_listing_id' => $listing->id,
            'match_status' => 'pending',
            'match_reason' => 'not_found',
            'candidate_ids_json' => [],
        ]);

        for ($i = 1; $i <= 6; $i++) {
            MpProduct::query()->create([
                'user_id' => $user->id,
                'barcode' => "869LT{$suffix}{$i}",
                'stock_code' => "LT-{$suffix}-{$i}",
                'product_name' => "Koltuk Takimi Model {$i}",
                'cogs' => 500 + $i,
                'vat_rate' => 10,
                'market_price' => 1000 + $i,
                'sale_price' => 900 + $i,
                'commission_rate' => 15,
                'stock_quantity' => 10,
                'desi' => 3,
                'pieces' => 1,
                'status' => 'active',
            ]);
        }

        $this->actingAs($user);

        $component = Livewire::test(MarketplaceMatchingCenter::class)
            ->set('issueSearchTerms', [$issue->id => 'Koltuk'])
            ->instance();

        $reflection = new \ReflectionMethod(MarketplaceMatchingCenter::class, 'resolveCandidatesForIssue');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($component, $issue->fresh()->load('channelListing.channelProduct'), collect());

        $this->assertCount(3, $result);
    }

    public function test_fallback_search_respects_candidate_limits(): void
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        (new MpSettingsService($user->id))->set('matching.candidate_search_limit', 5);
        (new MpSettingsService($user->id))->set('matching.candidate_result_limit', 3);

        $entity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Fallback Ltd.',
            'tax_number' => '6'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $entity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'ZEM FALLBACK',
            'store_code' => 'ZEM-FB-'.$suffix,
            'seller_id' => 'FB'.$suffix,
            'status' => 'active',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $channelProduct = ChannelProduct::query()->create([
            'store_id' => $store->id,
            'external_product_id' => 'CP-FB-'.$suffix,
            'stock_code' => 'STK-FB-'.$suffix,
            'barcode' => '869FB'.$suffix,
            'title' => 'Dolap Beyaz Modern',
        ]);

        $listing = ChannelListing::query()->create([
            'store_id' => $store->id,
            'channel_product_id' => $channelProduct->id,
            'listing_id' => 'LIST-FB-'.$suffix,
            'listing_status' => 'active',
            'sale_price' => 3499.90,
            'stock_quantity' => 3,
            'currency' => 'TRY',
        ]);

        $issue = ProductMatchIssue::query()->create([
            'store_id' => $store->id,
            'channel_listing_id' => $listing->id,
            'match_status' => 'pending',
            'match_reason' => 'not_found',
            'candidate_ids_json' => [],
        ]);

        for ($i = 1; $i <= 6; $i++) {
            MpProduct::query()->create([
                'user_id' => $user->id,
                'barcode' => "869FB{$suffix}{$i}",
                'stock_code' => "FB-{$suffix}-{$i}",
                'product_name' => "Dolap Beyaz Model {$i}",
                'cogs' => 800 + $i,
                'vat_rate' => 10,
                'market_price' => 2000 + $i,
                'sale_price' => 1800 + $i,
                'commission_rate' => 15,
                'stock_quantity' => 5,
                'desi' => 8,
                'pieces' => 1,
                'status' => 'active',
            ]);
        }

        $this->actingAs($user);

        $component = Livewire::test(MarketplaceMatchingCenter::class)->instance();

        $reflection = new \ReflectionMethod(MarketplaceMatchingCenter::class, 'resolveCandidatesForIssue');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($component, $issue->fresh()->load('channelListing.channelProduct'), collect());

        $this->assertCount(3, $result);
    }

    public function test_result_limit_capped_by_search_limit_at_component_level(): void
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        (new MpSettingsService($user->id))->set('matching.candidate_search_limit', 4);
        (new MpSettingsService($user->id))->set('matching.candidate_result_limit', 10);

        $entity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Cap Test Ltd.',
            'tax_number' => '5'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $entity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'ZEM CAP',
            'store_code' => 'ZEM-CP-'.$suffix,
            'seller_id' => 'CP'.$suffix,
            'status' => 'active',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $channelProduct = ChannelProduct::query()->create([
            'store_id' => $store->id,
            'external_product_id' => 'CP-CP-'.$suffix,
            'stock_code' => 'STK-CP-'.$suffix,
            'barcode' => '869CP'.$suffix,
            'title' => 'Sandalye Test',
        ]);

        $listing = ChannelListing::query()->create([
            'store_id' => $store->id,
            'channel_product_id' => $channelProduct->id,
            'listing_id' => 'LIST-CP-'.$suffix,
            'listing_status' => 'active',
            'sale_price' => 999.90,
            'stock_quantity' => 10,
            'currency' => 'TRY',
        ]);

        $issue = ProductMatchIssue::query()->create([
            'store_id' => $store->id,
            'channel_listing_id' => $listing->id,
            'match_status' => 'pending',
            'match_reason' => 'not_found',
            'candidate_ids_json' => [],
        ]);

        for ($i = 1; $i <= 8; $i++) {
            MpProduct::query()->create([
                'user_id' => $user->id,
                'barcode' => "869CP{$suffix}{$i}",
                'stock_code' => "CP-{$suffix}-{$i}",
                'product_name' => "Sandalye Model {$i}",
                'cogs' => 200 + $i,
                'vat_rate' => 10,
                'market_price' => 500 + $i,
                'sale_price' => 450 + $i,
                'commission_rate' => 15,
                'stock_quantity' => 20,
                'desi' => 2,
                'pieces' => 1,
                'status' => 'active',
            ]);
        }

        $this->actingAs($user);

        $component = Livewire::test(MarketplaceMatchingCenter::class)->instance();

        $reflection = new \ReflectionMethod(MarketplaceMatchingCenter::class, 'resolveCandidatesForIssue');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($component, $issue->fresh()->load('channelListing.channelProduct'), collect());

        $this->assertLessThanOrEqual(4, $result->count());
    }
}
