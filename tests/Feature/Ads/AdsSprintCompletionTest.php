<?php

namespace Tests\Feature\Ads;

use App\Enums\AdChannelCode;
use App\Livewire\Ads\AdImportCenter;
use App\Livewire\Ads\InfluencerAdsPage;
use App\Livewire\Ads\ProductAdsPage;
use App\Models\AdAccount;
use App\Models\AdCampaign;
use App\Models\AdCampaignProduct;
use App\Models\AdCampaignSnapshot;
use App\Models\AdImportBatch;
use App\Models\AdImportRow;
use App\Models\AdKeywordSnapshot;
use App\Models\AdRecommendation;
use App\Models\InfluencerCreatorSnapshot;
use App\Models\InfluencerProfile;
use App\Models\User;
use App\Services\Ads\AdImportService;
use App\Services\Ads\AdNumberParser;
use App\Services\Ads\ProfitabilityService;
use App\Services\Ads\RuleEngine;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class AdsSprintCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_advertising_intelligence_workspaces_render(): void
    {
        $user = $this->user();

        foreach (['ads.dashboard', 'ads.import', 'ads.product-ads', 'ads.store-ads', 'ads.influencer-ads', 'ads.profitability', 'ads.action-center', 'ads.settings'] as $route) {
            $this->actingAs($user)->get(route($route))->assertOk();
        }

        $this->actingAs($user)
            ->get(route('ads.dashboard'))
            ->assertSee('6 sprint aktif')
            ->assertDontSee('Geliştirme Aşamasında')
            ->assertDontSee("Sprint 2'de aktif olacak");
    }

    public function test_product_campaigns_use_the_latest_snapshot_and_sort_by_roas(): void
    {
        $user = $this->user();
        $account = $this->account($user);
        $low = $this->campaign($user, $account, AdChannelCode::ProductAds, 'Düşük ROAS');
        $high = $this->campaign($user, $account, AdChannelCode::ProductAds, 'Yüksek ROAS');

        $this->snapshot($low, $this->batch($user, $account, 'product_general'), 1.2, '2026-07-01 10:00:00');
        $this->snapshot($low, $this->batch($user, $account, 'product_general'), 2.0, '2026-07-02 10:00:00');
        $this->snapshot($high, $this->batch($user, $account, 'product_general'), 6.0, '2026-07-02 10:00:00');

        $this->assertSame('2.0000', $low->fresh()->latestSnapshot->roas);

        Livewire::actingAs($user)
            ->test(ProductAdsPage::class)
            ->assertSeeInOrder(['Yüksek ROAS', 'Düşük ROAS'])
            ->call('sortTable', 'not_a_column')
            ->assertSet('sortBy', 'roas');
    }

    public function test_detail_store_and_influencer_reports_are_imported_end_to_end(): void
    {
        $user = $this->user();
        $account = $this->account($user);

        $productCampaign = $this->campaign($user, $account, AdChannelCode::ProductAds, 'Ürün Kampanyası');
        $productBatch = $this->batch($user, $account, 'product_campaign', 'preview_ready', $productCampaign);
        $this->row($productBatch, [
            'product_name' => 'Çamaşır Sepeti',
            'content_id' => 'CNT-1',
            'spend' => '125,50',
            'impressions' => '1.200',
            'clicks' => '80',
            'sales_total' => '4',
            'revenue_total' => '600',
        ]);
        $productBatch->update(['valid_row_count' => 1, 'row_count' => 1]);
        app(AdImportService::class)->executeImport($productBatch->id, $user->id);

        $product = AdCampaignProduct::firstOrFail();
        $this->assertSame($productCampaign->id, $product->campaign_id);
        $this->assertSame('CNT-1', $product->marketplace_content_id);

        $storeCampaign = $this->campaign($user, $account, AdChannelCode::StoreAds, 'Mağaza Kampanyası');
        $storeBatch = $this->batch($user, $account, 'store_keyword', 'preview_ready', $storeCampaign);
        $this->row($storeBatch, [
            'keyword' => '  Çamaşır   Sepeti ',
            'spend' => '75,25',
            'impressions' => '2.500',
            'clicks' => '90',
            'sales_total' => '3',
            'revenue_total' => '300',
        ]);
        $storeBatch->update(['valid_row_count' => 1, 'row_count' => 1]);
        app(AdImportService::class)->executeImport($storeBatch->id, $user->id);

        $keyword = AdKeywordSnapshot::firstOrFail();
        $this->assertSame('çamaşır sepeti', $keyword->normalized_keyword);
        $this->assertSame('3.9867', $keyword->roas);

        $influencerCampaign = $this->campaign($user, $account, AdChannelCode::InfluencerAds, 'Creator Kampanyası');
        $influencerBatch = $this->batch($user, $account, 'influencer', 'preview_ready', $influencerCampaign);
        $this->row($influencerBatch, [
            'handle' => '@zolmcreator',
            'display_name' => 'ZOLM Creator',
            'platform' => 'Instagram',
            'link_visits' => '900',
            'sales_total' => '12',
            'revenue_total' => '1.250,00',
            'new_customers' => '8',
        ]);
        $influencerBatch->update(['valid_row_count' => 1, 'row_count' => 1]);
        app(AdImportService::class)->executeImport($influencerBatch->id, $user->id);

        $profile = InfluencerProfile::firstOrFail();
        $this->assertSame('zolmcreator', $profile->handle);
        $this->assertSame('1250.00', InfluencerCreatorSnapshot::firstOrFail()->revenue_total);

        Livewire::actingAs($user)
            ->test(InfluencerAdsPage::class)
            ->assertSee('1.250');
    }

    public function test_current_phpspreadsheet_version_parses_a_real_store_report(): void
    {
        $user = $this->user();
        $account = $this->account($user);
        $campaign = $this->campaign($user, $account, AdChannelCode::StoreAds, 'Kelime Kampanyası');
        $batch = $this->batch($user, $account, 'store_keyword', 'uploaded', $campaign);
        $temporaryFile = tempnam(sys_get_temp_dir(), 'zolm-ads-').'.xlsx';

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([
            ['Trendyol Mağaza Reklamları'],
            ['Anahtar Kelime', 'Harcanan Bütçe', 'Gösterim Sayısı', 'Tıklanma Sayısı', 'Toplam Satış Adedi', 'Toplam Reklam Cirosu'],
            ['çamaşır sepeti', '75,25', '2.500', '90', '3', '300'],
        ]);
        (new Xlsx($spreadsheet))->save($temporaryFile);

        try {
            app(AdImportService::class)->parseImportBatch($batch->id, $temporaryFile, $user->id);
        } finally {
            @unlink($temporaryFile);
        }

        $batch->refresh();
        $this->assertSame('preview_ready', $batch->status);
        $this->assertSame(1, $batch->valid_row_count);
        $this->assertSame('çamaşır sepeti', $batch->adImportRows()->firstOrFail()->normalized_payload['keyword']);
    }

    public function test_import_campaign_candidates_are_scoped_to_user_account_and_channel(): void
    {
        $user = $this->user();
        $other = $this->user('other@example.test');
        $account = $this->account($user);
        $otherAccount = $this->account($other);
        $ownCampaign = $this->campaign($user, $account, AdChannelCode::StoreAds, 'Benim Kampanyam');
        $this->campaign($other, $otherAccount, AdChannelCode::StoreAds, 'Başkasının Kampanyası');

        Livewire::actingAs($user)
            ->test(AdImportCenter::class)
            ->set('selectedAccountId', $account->id)
            ->set('importType', 'store_keyword')
            ->assertSet('campaignCandidates.0.id', $ownCampaign->id)
            ->assertDontSee('Başkasının Kampanyası');
    }

    public function test_store_and_influencer_campaign_context_can_be_created_from_import_center(): void
    {
        $user = $this->user();
        $account = $this->account($user);

        Livewire::actingAs($user)
            ->test(AdImportCenter::class)
            ->set('selectedAccountId', $account->id)
            ->set('importType', 'store_keyword')
            ->set('newCampaignName', 'Yeni Mağaza Kampanyası')
            ->call('createCampaignForImport')
            ->assertHasNoErrors()
            ->assertSet('campaignCandidates.0.name', 'Yeni Mağaza Kampanyası');

        $this->assertDatabaseHas('ad_campaigns', [
            'user_id' => $user->id,
            'ad_account_id' => $account->id,
            'channel_code' => AdChannelCode::StoreAds->value,
            'name' => 'Yeni Mağaza Kampanyası',
        ]);
    }

    public function test_import_confirmation_cannot_execute_another_users_batch(): void
    {
        $owner = $this->user('batch-owner@example.test');
        $attacker = $this->user('batch-attacker@example.test');
        $account = $this->account($owner);
        $campaign = $this->campaign($owner, $account, AdChannelCode::StoreAds, 'Özel Kampanya');
        $batch = $this->batch($owner, $account, 'store_keyword', 'preview_ready', $campaign);
        $this->row($batch, [
            'keyword' => 'gizli kelime',
            'spend' => '75',
            'impressions' => '1000',
        ]);
        $batch->update(['valid_row_count' => 1, 'row_count' => 1]);

        Livewire::actingAs($attacker)
            ->test(AdImportCenter::class)
            ->set('currentBatchId', $batch->id)
            ->call('confirmImport')
            ->assertSet('statusMessage', 'Hata: No query results for model [App\\Models\\AdImportBatch] '.$batch->id);

        $this->assertSame('preview_ready', $batch->fresh()->status);
        $this->assertDatabaseMissing('ad_keyword_snapshots', ['import_batch_id' => $batch->id]);
    }

    public function test_rule_engine_does_not_duplicate_an_unchanged_recommendation(): void
    {
        $user = $this->user();
        $account = $this->account($user);
        $campaign = $this->campaign($user, $account, AdChannelCode::ProductAds, 'İsraf Kampanyası');
        $batch = $this->batch($user, $account, 'product_general');
        $this->snapshot($campaign, $batch, 0, '2026-07-02 10:00:00', 150, 60, 0);

        app(RuleEngine::class)->runAllRules($user->id);
        app(RuleEngine::class)->runAllRules($user->id);

        $this->assertSame(1, AdRecommendation::where('user_id', $user->id)->count());
        $this->assertNotNull(AdRecommendation::first()->metadata['evidence_hash'] ?? null);
    }

    public function test_profitability_calculation_rejects_another_users_campaign(): void
    {
        $owner = $this->user('owner@example.test');
        $attacker = $this->user('attacker@example.test');
        $account = $this->account($owner);
        $campaign = $this->campaign($owner, $account, AdChannelCode::ProductAds, 'Özel Kampanya');
        $this->snapshot($campaign, $this->batch($owner, $account, 'product_general'), 2.0, '2026-07-02 10:00:00');

        $this->expectException(ModelNotFoundException::class);

        app(ProfitabilityService::class)->calculate($attacker->id, $campaign->id, [
            'product_cost' => 10,
            'marketplace_commission' => 10,
            'shipping_cost' => 10,
        ]);
    }

    public function test_turkish_number_parser_handles_single_and_multiple_thousands(): void
    {
        $parser = app(AdNumberParser::class);

        $this->assertSame(1234.0, $parser->parse('1.234'));
        $this->assertSame(1234567.0, $parser->parse('1.234.567'));
        $this->assertSame(1234.56, $parser->parse('1.234,56 ₺'));
    }

    private function user(string $email = 'ads@example.test'): User
    {
        return User::factory()->create([
            'email' => $email,
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    private function account(User $user): AdAccount
    {
        return AdAccount::create([
            'user_id' => $user->id,
            'marketplace' => 'trendyol',
            'account_name' => 'Trendyol Mağazası',
            'currency_code' => 'TRY',
            'timezone' => 'Europe/Istanbul',
            'is_active' => true,
        ]);
    }

    private function campaign(User $user, AdAccount $account, AdChannelCode $channel, string $name): AdCampaign
    {
        return AdCampaign::create([
            'user_id' => $user->id,
            'ad_account_id' => $account->id,
            'channel_code' => $channel->value,
            'campaign_identity_hash' => hash('sha256', $user->id.'|'.$channel->value.'|'.$name),
            'campaign_key' => $channel->value.'|'.$name,
            'name' => $name,
            'status' => 'active',
        ]);
    }

    private function batch(
        User $user,
        AdAccount $account,
        string $type,
        string $status = 'imported',
        ?AdCampaign $campaign = null,
    ): AdImportBatch {
        return AdImportBatch::create([
            'user_id' => $user->id,
            'ad_account_id' => $account->id,
            'channel_code' => $campaign?->channel_code ?? AdChannelCode::ProductAds->value,
            'import_type' => $type,
            'status' => $status,
            'report_period_start' => '2026-07-01',
            'report_period_end' => '2026-07-07',
            'uploaded_by_user_id' => $user->id,
            'source_filename' => $type.'-'.uniqid().'.xlsx',
            'storage_path' => 'testing/'.$type.'.xlsx',
            'file_hash' => hash('sha256', uniqid($type, true)),
            'campaign_id_context' => $campaign?->id,
        ]);
    }

    private function row(AdImportBatch $batch, array $payload): AdImportRow
    {
        return AdImportRow::create([
            'batch_id' => $batch->id,
            'row_number' => 1,
            'raw_payload' => $payload,
            'normalized_payload' => $payload,
            'validation_errors' => [],
            'status' => 'valid',
        ]);
    }

    private function snapshot(
        AdCampaign $campaign,
        AdImportBatch $batch,
        float $roas,
        string $capturedAt,
        float $spend = 100,
        int $clicks = 20,
        int $sales = 2,
    ): AdCampaignSnapshot {
        return AdCampaignSnapshot::create([
            'campaign_id' => $campaign->id,
            'import_batch_id' => $batch->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-07',
            'captured_at' => $capturedAt,
            'spend' => $spend,
            'impressions' => 1000,
            'clicks' => $clicks,
            'sales_total' => $sales,
            'revenue_total' => $spend * $roas,
            'roas' => $roas,
        ]);
    }
}
