<?php

namespace Tests\Feature;

use App\Livewire\TrendyolBooster;
use App\Mail\TrendyolBoosterDigestMail;
use App\Models\AppNotification;
use App\Models\ChannelListing;
use App\Models\ChannelOrder;
use App\Models\ChannelOrderItem;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\MpProduct;
use App\Models\TrendyolBoosterActivityLog;
use App\Models\TrendyolBoosterCampaignScenario;
use App\Models\TrendyolBoosterCommissionRate;
use App\Models\TrendyolBoosterCompetitor;
use App\Models\TrendyolBoosterCostPreset;
use App\Models\TrendyolBoosterKeyword;
use App\Models\TrendyolBoosterProduct;
use App\Models\TrendyolBoosterShippingRate;
use App\Models\TrendyolBoosterSnapshot;
use App\Models\TrendyolBoosterStockCheck;
use App\Models\TrendyolBoosterStoreWatch;
use App\Models\TrendyolBoosterStoreWatchSnapshot;
use App\Models\TrendyolBoosterSupplierOffer;
use App\Models\TrendyolBoosterSupplierResearch;
use App\Models\TrendyolBoosterTrendKeyword;
use App\Models\User;
use App\Services\Marketplace\TrendyolBestsellerReader;
use App\Services\Marketplace\TrendyolBestsellerReportService;
use App\Services\Marketplace\TrendyolBoosterAnalysisService;
use App\Services\Marketplace\TrendyolBoosterCampaignScenarioService;
use App\Services\Marketplace\TrendyolBoosterCommissionEstimator;
use App\Services\Marketplace\TrendyolBoosterCompetitorService;
use App\Services\Marketplace\TrendyolBoosterCostPresetService;
use App\Services\Marketplace\TrendyolBoosterDesiEstimator;
use App\Services\Marketplace\TrendyolBoosterEmailDigestService;
use App\Services\Marketplace\TrendyolBoosterKeywordLookupService;
use App\Services\Marketplace\TrendyolBoosterKeywordService;
use App\Services\Marketplace\TrendyolBoosterModuleInsightService;
use App\Services\Marketplace\TrendyolBoosterMonitorService;
use App\Services\Marketplace\TrendyolBoosterProductAnalysisService;
use App\Services\Marketplace\TrendyolBoosterResearchService;
use App\Services\Marketplace\TrendyolBoosterScheduledAnalysisService;
use App\Services\Marketplace\TrendyolBoosterSellDecisionService;
use App\Services\Marketplace\TrendyolBoosterShippingRateService;
use App\Services\Marketplace\TrendyolBoosterStockService;
use App\Services\Marketplace\TrendyolBoosterStoreWatchService;
use App\Services\Marketplace\TrendyolBoosterSupplierResearchService;
use App\Services\Marketplace\TrendyolBoosterTrendKeywordService;
use App\Services\Marketplace\TrendyolCategoryDictionary;
use App\Services\Marketplace\TrendyolProductPageReader;
use App\Services\Marketplace\TrendyolSearchResultReader;
use App\Services\Marketplace\TrendyolSellerLevelService;
use App\Services\Marketplace\TrendyolStorePageReader;
use App\Services\NotificationCenterService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class TrendyolBoosterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql.host', 'mysql');
        config()->set('database.connections.mysql.port', '3306');
        config()->set('database.connections.mysql.database', 'zolm');
        config()->set('database.connections.mysql.username', 'sail');
        config()->set('database.connections.mysql.password', 'password');
        config()->set('marketplace.features.notifications_enabled', true);
        config()->set('marketplace.features.trendyol_booster_enabled', true);
        DB::purge('mysql');
        DB::reconnect('mysql');
        DB::setDefaultConnection('mysql');
        DB::connection('mysql')->beginTransaction();
    }

    protected function tearDown(): void
    {
        $connection = DB::connection('mysql');
        while ($connection->transactionLevel() > 0) {
            $connection->rollBack();
        }

        parent::tearDown();
    }

    public function test_booster_service_scores_and_persists_trendyol_product_decision(): void
    {
        [$user, $product, $listing] = $this->createBoosterGraph();

        $service = app(TrendyolBoosterAnalysisService::class);
        $input = [
            'user_id' => $user->id,
            'source_url' => 'https://www.trendyol.com/test/urun-p-123456',
            'mp_product_id' => $product->id,
            'channel_listing_id' => $listing->id,
            'target_margin_percent' => 20,
            'vat_enabled' => false,
            'withholding_enabled' => false,
            'watch_price' => true,
            'watch_stock' => true,
        ];

        $preview = $service->preview($input);

        $this->assertSame('go', $preview['decision']);
        $this->assertGreaterThanOrEqual(85, $preview['score']);
        $this->assertSame('123456', $preview['normalized']['trendyol_product_id']);
        $this->assertGreaterThan(0, $preview['simulation']['net_profit']);

        $tracked = $service->store($user->id, $input);
        $updated = $service->store($user->id, array_merge($input, ['watch_stock' => false]));

        $this->assertSame($tracked->id, $updated->id);
        $this->assertSame(1, TrendyolBoosterProduct::query()->where('user_id', $user->id)->count());
        $this->assertSame('go', $tracked->fresh()->decision_status);
        $this->assertTrue($tracked->fresh()->watch_price);
        $this->assertFalse($updated->fresh()->watch_stock);
    }

    public function test_trendyol_product_reader_extracts_product_data_from_page_metadata(): void
    {
        Http::fake([
            'https://www.trendyol.com/*' => Http::response($this->trendyolProductHtml(), 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]),
        ]);

        $result = app(TrendyolProductPageReader::class)->fetch('https://www.trendyol.com/zolm/booster-test-urunu-p-123456');

        $this->assertTrue($result['ok']);
        $this->assertSame('123456', $result['data']['trendyol_product_id']);
        $this->assertSame('ZOLM Booster Test Ürünü', $result['data']['title']);
        $this->assertSame('ZOLM', $result['data']['brand']);
        $this->assertSame('Ev & Yaşam', $result['data']['category_name']);
        $this->assertSame(1299.9, $result['data']['sale_price']);
        $this->assertSame('TRY', $result['data']['currency']);
        $this->assertSame('in_stock', $result['data']['stock_status']);
        Http::assertSentCount(1);
    }

    public function test_trendyol_product_reader_falls_back_to_url_data_when_page_is_blocked(): void
    {
        Http::fake([
            'https://www.trendyol.com/*' => Http::response('Forbidden', 403),
        ]);

        $result = app(TrendyolProductPageReader::class)
            ->fetch('https://www.trendyol.com/zem/lines-puf-teddy-kumas-kirik-beyaz-p-2868');

        $this->assertTrue($result['ok']);
        $this->assertSame('2868', $result['data']['trendyol_product_id']);
        $this->assertSame('Lines Puf Teddy Kumas Kirik Beyaz', $result['data']['title']);
        $this->assertSame('Zem', $result['data']['brand']);
        $this->assertSame(0.0, $result['data']['sale_price']);
        $this->assertSame('Trendyol sayfası erişimi sınırladı; linkten temel bilgiler alındı. Fiyatı manuel girebilirsiniz.', $result['message']);
    }

    public function test_trendyol_product_reader_extracts_envoy_seller_stock_data(): void
    {
        $url = 'https://www.trendyol.com/zem/lines-puf-teddy-kumas-kirik-beyaz-p-286823139?merchantId=121057';
        $result = app(TrendyolProductPageReader::class)->parse($this->trendyolEnvoyProductHtml(), $url);

        $this->assertSame('286823139', $result['trendyol_product_id']);
        $this->assertSame('Lines Puf, Teddy Kumaş Kırık Beyaz', $result['title']);
        $this->assertSame('Zem', $result['brand']);
        $this->assertSame('Puf & Bench', $result['category_name']);
        $this->assertSame(1289.9, $result['sale_price']);
        $this->assertSame('87874848484848484', $result['barcode']);
        $this->assertSame(780, $result['total_stock']);
        $this->assertSame(945, $result['evaluation_count']);
        $this->assertSame(627, $result['review_count']);
        $this->assertSame(4.61, $result['average_rating']);
        $this->assertSame(59825, $result['favorite_count']);
        $this->assertSame('envoy_shared_props', $result['stock_source']);
        $this->assertSame('Zem Home', $result['sellers'][0]['seller_name']);
        $this->assertSame('121057', $result['sellers'][0]['seller_id']);
        $this->assertSame('121057', $result['seller_id']);
        $this->assertSame(780, $result['sellers'][0]['stock']);
        $this->assertSame(9.1, $result['sellers'][0]['seller_score']);
        $this->assertSame('ZEM EV ÜRÜNLERİ LTD. ŞTİ.', data_get($result, 'seller_legal.title'));
        $this->assertSame('zem@hs01.kep.tr', data_get($result, 'seller_legal.kep'));
        $this->assertSame('1234567890', data_get($result, 'seller_legal.tax_number'));
        $this->assertSame('Kadıköy Vergi Dairesi', data_get($result, 'seller_legal.tax_office'));
        $this->assertSame('İstanbul Test Mah. No:1', data_get($result, 'seller_legal.address'));
    }

    public function test_trendyol_product_reader_prefers_current_discounted_price(): void
    {
        $state = [
            'product' => [
                'id' => 842980519,
                'name' => 'Usta Ağaç Puf Koltuk Tabure',
                'brand' => ['name' => 'ustaagac'],
                'category' => ['name' => 'Puflar'],
                'merchantListing' => [
                    'merchant' => ['id' => 973137, 'name' => 'ustaagac'],
                    'winnerVariant' => [
                        'quantity' => 9885,
                        'price' => [
                            'currency' => 'TRY',
                            'sellingPrice' => ['value' => 588.35],
                            'discountedPrice' => ['value' => 538.35],
                            'discountedPriceAfterNoLimitPromotions' => ['value' => 538.35],
                            'originalPrice' => ['value' => 649],
                        ],
                    ],
                ],
            ],
        ];
        $html = '<script>window["__envoy__SHARED_PROPS"]='.
            json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).';</script>';

        $result = app(TrendyolProductPageReader::class)->parse(
            $html,
            'https://www.trendyol.com/ustaagac/puf-koltuk-tabure-p-842980519',
        );

        $this->assertSame(538.35, $result['sale_price']);
        $this->assertSame(538.35, data_get($result, 'sellers.0.sale_price'));
    }

    public function test_trendyol_product_reader_collects_all_merchant_listings_from_product_state(): void
    {
        $state = [
            'product' => [
                'id' => 286823139,
                'name' => 'Lines Puf, Teddy Kumaş Kırık Beyaz',
                'brand' => ['name' => 'Zem'],
                'merchantListing' => [
                    'merchant' => ['id' => 121057, 'name' => 'Zem Home', 'sellerScore' => ['value' => 9.1]],
                    'winnerVariant' => ['quantity' => 20, 'price' => ['sellingPrice' => ['value' => 1289.90]]],
                ],
                'otherMerchantListings' => [[
                    'merchant' => ['id' => 991122, 'name' => 'Ev Dünyası', 'sellerScore' => ['value' => 8.7]],
                    'winnerVariant' => ['quantity' => 7, 'price' => ['discountedPrice' => ['value' => 1249.90]]],
                ]],
            ],
        ];
        $html = '<script>window["__envoy__SHARED_PROPS"]='.
            json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).';</script>';

        $result = app(TrendyolProductPageReader::class)->parse(
            $html,
            'https://www.trendyol.com/zem/lines-puf-teddy-kumas-kirik-beyaz-p-286823139',
        );

        $this->assertCount(2, $result['sellers']);
        $this->assertSame(['Zem Home', 'Ev Dünyası'], array_column($result['sellers'], 'seller_name'));
        $this->assertSame(1249.9, data_get($result, 'sellers.1.sale_price'));
        $this->assertSame(7, data_get($result, 'sellers.1.stock'));
    }

    public function test_supplier_research_persists_marketplace_offers_and_builds_time_signals(): void
    {
        [$user] = $this->createBoosterGraph();
        $sourceUrl = 'https://www.trendyol.com/zem/lines-puf-teddy-kumas-kirik-beyaz-p-286823139';
        $page = [
            'source_url' => $sourceUrl,
            'trendyol_product_id' => '286823139',
            'title' => 'Zem Lines Puf Teddy Kumaş Kırık Beyaz',
            'brand' => 'Zem',
            'category_name' => 'Puf & Bench',
            'sale_price' => 1289.90,
            'favorite_count' => 59600,
            'review_count' => 86,
            'sellers' => [
                ['seller_name' => 'Zem Home', 'seller_id' => '121057', 'stock' => 20, 'sale_price' => 1289.90],
                ['seller_name' => 'Ev Dünyası', 'seller_id' => '991122', 'stock' => 7, 'sale_price' => 1249.90],
                ['seller_name' => 'Trendyol satıcısı', 'seller_id' => '', 'stock' => null, 'sale_price' => 0],
                ['seller_name' => '', 'seller_id' => '', 'stock' => null, 'sale_price' => 0],
            ],
        ];
        $external = [
            [
                'platform' => 'hepsiburada',
                'platform_label' => 'Hepsiburada',
                'seller_name' => 'Zem Home',
                'title' => 'Zem Lines Puf Teddy Kumaş Kırık Beyaz',
                'source_url' => 'https://www.hepsiburada.com/zem-lines-puf-p-HBCV0001',
                'sale_price' => 1289.90,
                'stock' => 12,
                'source_type' => 'google_shopping',
            ],
            [
                'platform' => 'koctas',
                'platform_label' => 'Koçtaş Pazaryeri',
                'seller_name' => 'Koçtaş',
                'title' => 'Zem Lines Puf, Teddy Kumaş Kırık Beyaz',
                'source_url' => 'https://www.koctas.com.tr/zem-lines-puf/p/5003085339',
                'sale_price' => 1299.90,
                'source_type' => 'google_shopping',
            ],
            [
                'platform' => 'other',
                'platform_label' => 'Başka Mağaza',
                'title' => 'Bambaşka Ahşap Tabure',
                'source_url' => 'https://example-store.test/baska-tabure',
                'sale_price' => 399.90,
                'source_type' => 'google_shopping',
                'match_score' => 100,
            ],
        ];

        $first = app(TrendyolBoosterSupplierResearchService::class)->capture($user->id, $sourceUrl, $page, $external, [
            'search_query' => '"Zem Lines Puf Teddy Kumaş Kırık Beyaz" fiyat satıcı',
            'search_url' => 'https://www.google.com/search?q=zem+lines+puf',
        ]);

        $this->assertTrue($first['ok']);
        $this->assertSame(1, TrendyolBoosterSupplierResearch::query()->where('user_id', $user->id)->count());
        $this->assertSame(4, TrendyolBoosterSupplierOffer::query()->where('user_id', $user->id)->count());
        $this->assertSame(3, $first['research']->platform_count);
        $this->assertSame(4, $first['research']->verified_offer_count);
        $this->assertNull(TrendyolBoosterSupplierOffer::query()->where('title', 'Bambaşka Ahşap Tabure')->first());
        $this->assertSame(0, TrendyolBoosterSupplierOffer::query()->where('seller_name', 'Trendyol satıcısı')->count());

        foreach ([
            ['platform' => 'other', 'platform_label' => 'instagram.com', 'seller_name' => '', 'title' => 'Derimod aşkı linkleri hikayemde', 'match_score' => 0, 'source_type' => 'google_browser'],
            ['platform' => 'trendyol', 'platform_label' => 'Trendyol', 'seller_name' => 'Trendyol satıcısı', 'title' => $page['title'], 'match_score' => 100, 'source_type' => 'trendyol_product'],
        ] as $index => $legacyOffer) {
            TrendyolBoosterSupplierOffer::query()->create($legacyOffer + [
                'trendyol_booster_supplier_research_id' => $first['research']->id,
                'user_id' => $user->id,
                'scan_uuid' => $first['research']->last_scan_uuid,
                'offer_key' => hash('sha256', 'legacy-'.$index),
                'source_url' => $index === 0 ? 'https://www.instagram.com/reel/example' : $sourceUrl.'?legacy='.$index,
                'source_url_hash' => hash('sha256', 'legacy-url-'.$index),
                'sale_price' => 0,
                'match_status' => $legacyOffer['match_score'] === 100 ? 'verified' : 'review',
                'rank' => 90 + $index,
                'observed_at' => now(),
            ]);
        }

        $filteredDashboard = app(TrendyolBoosterSupplierResearchService::class)->dashboard($user->id);
        $this->assertCount(2, $filteredDashboard['trendyol_offers']);
        $this->assertCount(2, $filteredDashboard['external_offers']);
        $this->assertFalse($filteredDashboard['offers']->contains(fn ($offer): bool => $offer->platform_label === 'instagram.com'));
        $this->assertSame(3, $filteredDashboard['latest']->platform_count);

        $page['sellers'][0]['stock'] = 16;
        $external[0]['stock'] = 9;
        $external[0]['sale_price'] = 1199.90;
        $second = app(TrendyolBoosterSupplierResearchService::class)->capture($user->id, $sourceUrl, $page, $external);
        $latestHepsiburada = TrendyolBoosterSupplierOffer::query()
            ->where('user_id', $user->id)
            ->where('platform', 'hepsiburada')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(2, $second['research']->scan_count);
        $this->assertSame(3, $latestHepsiburada->estimated_sales);
        $this->assertSame('1289.90', $latestHepsiburada->previous_sale_price);
        $this->assertSame('-90.00', $latestHepsiburada->price_delta);
    }

    public function test_supplier_research_uses_model_code_for_smart_marketplace_matching(): void
    {
        [$user] = $this->createBoosterGraph();
        $sourceUrl = 'https://www.trendyol.com/kumtel/ktf-345-siyah-18-inc-ayakli-vantilator-60-w-5-pervaneli-3-kademeli-132-cm-p-1139403146';
        $page = [
            'source_url' => $sourceUrl,
            'trendyol_product_id' => '1139403146',
            'title' => 'KTF-345 Siyah 18 İnç Ayaklı Vantilatör 60 W, 5 Pervaneli, 3 Kademeli, 132 cm',
            'brand' => 'KUMTEL',
            'category_name' => 'Vantilatör',
            'sale_price' => 1414.00,
            'favorite_count' => 0,
            'review_count' => 721,
            'sellers' => [
                ['seller_name' => 'BORAKS', 'seller_id' => '113012', 'stock' => 19999, 'sale_price' => 1414.00, 'seller_score' => 8.9],
                ['seller_name' => 'EJESHA', 'seller_id' => '', 'stock' => null, 'sale_price' => 2849.05, 'seller_score' => 9.5],
            ],
        ];
        $external = [
            [
                'platform' => 'hepsiburada',
                'platform_label' => 'Hepsiburada',
                'seller_name' => 'Hepsiburada',
                'title' => 'Kumtel KTF-345 Ayaklı Vantilatör',
                'source_url' => 'https://www.hepsiburada.com/kumtel-ktf-345-ayakli-vantilator-p-HBCV0002',
                'sale_price' => 1499.00,
                'source_type' => 'google_shopping',
            ],
            [
                'platform' => 'amazon_tr',
                'platform_label' => 'Amazon Türkiye',
                'seller_name' => 'Amazon.com.tr',
                'title' => 'Kumtel KTF-295 Siyah Ayaklı Vantilatör',
                'source_url' => 'https://www.amazon.com.tr/kumtel-ktf-295-vantilator/dp/example',
                'sale_price' => 999.00,
                'source_type' => 'google_shopping',
                'match_score' => 100,
            ],
            [
                'platform' => 'pazarama',
                'platform_label' => 'Pazarama',
                'seller_name' => 'Pazarama',
                'title' => 'Kumtel Siyah Ayaklı Vantilatör',
                'source_url' => 'https://www.pazarama.com/kumtel-siyah-ayakli-vantilator-p-example',
                'sale_price' => 899.00,
                'source_type' => 'google_shopping',
                'match_score' => 100,
            ],
        ];

        $result = app(TrendyolBoosterSupplierResearchService::class)->capture($user->id, $sourceUrl, $page, $external);

        $this->assertTrue($result['ok']);
        $this->assertSame(3, $result['research']->seller_count);
        $this->assertSame(2, $result['research']->platform_count);
        $this->assertSame(3, $result['research']->verified_offer_count);
        $this->assertSame(1, TrendyolBoosterSupplierOffer::query()->where('user_id', $user->id)->where('seller_name', 'EJESHA')->count());
        $this->assertNotNull(TrendyolBoosterSupplierOffer::query()->where('user_id', $user->id)->where('platform', 'hepsiburada')->where('match_score', '>=', 85)->first());
        $this->assertNull(TrendyolBoosterSupplierOffer::query()->where('user_id', $user->id)->where('title', 'Kumtel KTF-295 Siyah Ayaklı Vantilatör')->first());
        $this->assertNull(TrendyolBoosterSupplierOffer::query()->where('user_id', $user->id)->where('title', 'Kumtel Siyah Ayaklı Vantilatör')->first());
    }

    public function test_bestseller_reader_normalizes_current_trendyol_ranking_cards(): void
    {
        $result = app(TrendyolBestsellerReader::class)->parseBridgeData([
            [
                'id' => '677950852',
                'trendyol_product_id' => '677950852',
                'source_url' => 'https://www.trendyol.com/stradivarius/keten-karisimli-bol-kesim-pantolon-p-677950852',
                'rank' => 1,
                'title' => 'Keten Karışımlı Bol Kesim Pantolon',
                'brand' => 'Stradivarius',
                'image_url' => 'https://cdn.dsmcdn.com/product.jpg',
                'price' => 1290,
                'rating' => 4.3,
                'rating_count' => 17191,
                'sold_text' => '3 günde 2B+ ürün satıldı!',
                'estimated_sales_3d' => 2000,
                'estimated_revenue_3d' => 2_580_000,
                'favorite_count' => 765600,
                'basket_count' => 28400,
                'view_count_24h' => 25900,
            ],
        ], 'kadın giyim', 'https://www.trendyol.com/cok-satanlar?categoryId=82&type=bestSeller&webGenderId=1');

        $this->assertSame(1, $result['result_count']);
        $this->assertSame('677950852', data_get($result, 'top_products.0.trendyol_product_id'));
        $this->assertSame(1, data_get($result, 'top_products.0.rank'));
        $this->assertSame(2000, data_get($result, 'top_products.0.estimated_sales_3d'));
        $this->assertSame(2_580_000.0, data_get($result, 'top_products.0.estimated_revenue_3d'));
        $this->assertSame(765600, data_get($result, 'top_products.0.favorite_count'));
    }

    public function test_trendyol_category_dictionary_resolves_excel_product_groups(): void
    {
        $match = app(TrendyolCategoryDictionary::class)->resolve('puf');

        $this->assertNotNull($match);
        $this->assertSame('Mobilya', $match['category']);
        $this->assertSame('Salon Mobilyası', $match['sub_category']);
        $this->assertStringContainsString('Puf', $match['product_group']);
        $this->assertSame('Puf', $match['matched_term']);
    }

    public function test_bestseller_reader_routes_puf_to_precise_trendyol_category(): void
    {
        $requestedUrl = '';
        Http::fake(function ($request) use (&$requestedUrl) {
            $requestedUrl = $request->url();

            return Http::response('<script>window.__SEARCH_APP_INITIAL_STATE__ = {"products":[{"id":"171062238","url":"/pufyhome/dublin-etna-nil-pelus-krem-cok-amacli-dekoratif-pufkoltuk-p-171062238","title":"Dublin Etna Nil Peluş Krem Çok Amaçlı Dekoratif Pufkoltuk","brand":"PufyHome","price":605,"rating":4.6,"rating_count":42,"sold_text":"3 günde 100+ ürün satıldı!","estimated_sales_3d":100,"estimated_revenue_3d":60500}]};</script>', 200);
        });

        $result = app(TrendyolBestsellerReader::class)->fetch('puf');

        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('categoryId=104493', $requestedUrl);
        $this->assertStringNotContainsString('webGenderId=', $requestedUrl);
        $this->assertSame('171062238', data_get($result, 'data.top_products.0.trendyol_product_id'));
        $this->assertSame(100, data_get($result, 'data.top_products.0.estimated_sales_3d'));
    }

    public function test_bestseller_reader_routes_furniture_keywords_to_precise_trendyol_categories(): void
    {
        $requestedUrls = [];
        Http::fake(function ($request) use (&$requestedUrls) {
            $requestedUrls[] = $request->url();

            return Http::response('<script>window.__SEARCH_APP_INITIAL_STATE__ = {"products":[{"id":"700100","url":"/z/findik-berjer-p-700100","title":"Fındık Berjer","brand":"ZOLM","price":4999,"rating":4.7,"rating_count":12}]};</script>', 200);
        });

        $berjer = app(TrendyolBestsellerReader::class)->fetch('berjer');
        $kanepe = app(TrendyolBestsellerReader::class)->fetch('kanepe');

        $this->assertTrue($berjer['ok']);
        $this->assertTrue($kanepe['ok']);
        $this->assertStringContainsString('categoryId=104495', $requestedUrls[0]);
        $this->assertStringContainsString('categoryId=104491', $requestedUrls[1]);
        $this->assertSame('Berjerler', data_get($berjer, 'data.matched_label'));
        $this->assertSame('Kanepeler', data_get($kanepe, 'data.matched_label'));
    }

    public function test_livewire_bestseller_bridge_renders_sales_and_revenue_signals(): void
    {
        [$user] = $this->createBoosterGraph();
        $this->actingAs($user);

        Livewire::withQueryParams(['booster' => 'bestseller'])
            ->test(TrendyolBooster::class)
            ->set('bestsellerSearch', 'kadın giyim')
            ->call('bestsellerBridgeCompleted', [[
                'id' => '677950852',
                'source_url' => 'https://www.trendyol.com/stradivarius/keten-karisimli-bol-kesim-pantolon-p-677950852',
                'rank' => 1,
                'title' => 'Keten Karışımlı Bol Kesim Pantolon',
                'brand' => 'Stradivarius',
                'price' => 1290,
                'rating' => 4.3,
                'rating_count' => 17191,
                'sold_text' => '3 günde 2B+ ürün satıldı!',
                'estimated_sales_3d' => 2000,
                'estimated_revenue_3d' => 2_580_000,
                'favorite_count' => 765600,
            ]], 'kadın giyim', 'https://www.trendyol.com/cok-satanlar?categoryId=82&type=bestSeller&webGenderId=1', 'Giyim için 1 çok satan ürün getirildi.', true)
            ->assertSet('bestsellerResults.0.estimated_sales_3d', 2000)
            ->assertSee('3 günde 2B+ ürün satıldı!')
            ->assertSee('2.580.000 ₺')
            ->assertSee('Giyim için 1 çok satan ürün getirildi.');
    }

    public function test_bestseller_reader_keeps_enriched_seller_stock_and_campaign_data(): void
    {
        $result = app(TrendyolBestsellerReader::class)->parseBridgeData([[
            'id' => '842980519',
            'source_url' => 'https://www.trendyol.com/ustaagac/puf-koltuk-tabure-p-842980519',
            'rank' => 3,
            'title' => 'Usta Ağaç Puf Koltuk Tabure',
            'brand' => 'ustaagac',
            'sale_price' => 538.35,
            'stock_quantity' => 9885,
            'stock_status' => 'in_stock',
            'seller_name' => 'Usta Ağaç',
            'seller_id' => '973137',
            'seller_score' => 9.4,
            'sellers' => [[
                'seller_name' => 'Usta Ağaç',
                'seller_id' => '973137',
                'seller_score' => 9.4,
                'stock' => 9885,
                'sale_price' => 538.35,
            ]],
            'campaign_count' => 2,
            'campaigns' => ['Sepette %10 İndirim', '100 TL Kupon'],
            'enrichment_status' => 'enriched',
        ]], 'puf');

        $item = $result['top_products'][0];
        $this->assertSame(538.35, $item['price']);
        $this->assertSame('Usta Ağaç', $item['seller_name']);
        $this->assertSame(9885, $item['stock_quantity']);
        $this->assertSame('in_stock', $item['stock_status']);
        $this->assertSame(['Sepette %10 İndirim', '100 TL Kupon'], $item['campaigns']);
        $this->assertSame('enriched', $item['enrichment_status']);
    }

    public function test_bestseller_report_series_calculates_rank_trends_and_possible_causes(): void
    {
        [$user] = $this->createBoosterGraph();
        $service = app(TrendyolBestsellerReportService::class);
        $context = [
            'query' => 'puf',
            'matched_label' => 'Puflar',
            'source_url' => 'https://www.trendyol.com/cok-satanlar?type=bestSeller&categoryId=104493',
            'source' => 'browser_companion',
        ];

        $first = $service->storeRun($user->id, $context, [
            [
                'id' => '700001',
                'source_url' => 'https://www.trendyol.com/zolm/alpha-p-700001',
                'rank' => 4,
                'title' => 'Alpha Puf',
                'brand' => 'ZOLM',
                'price' => 1000,
                'seller_name' => 'ZOLM Home',
                'stock_quantity' => 40,
                'stock_status' => 'in_stock',
                'campaign_count' => 0,
                'campaigns' => [],
            ],
            [
                'id' => '700002',
                'source_url' => 'https://www.trendyol.com/zolm/beta-p-700002',
                'rank' => 1,
                'title' => 'Beta Puf',
                'brand' => 'ZOLM',
                'price' => 900,
                'seller_name' => 'ZOLM Home',
                'stock_quantity' => 30,
                'stock_status' => 'in_stock',
                'campaign_count' => 1,
                'campaigns' => ['Sepette İndirim'],
            ],
        ]);

        $second = $service->storeRun($user->id, $context, [
            [
                'id' => '700001',
                'source_url' => 'https://www.trendyol.com/zolm/alpha-p-700001',
                'rank' => 1,
                'title' => 'Alpha Puf',
                'brand' => 'ZOLM',
                'price' => 900,
                'seller_name' => 'ZOLM Home',
                'stock_quantity' => 42,
                'stock_status' => 'in_stock',
                'campaign_count' => 1,
                'campaigns' => ['100 TL Kupon'],
            ],
            [
                'id' => '700002',
                'source_url' => 'https://www.trendyol.com/zolm/beta-p-700002',
                'rank' => 3,
                'title' => 'Beta Puf',
                'brand' => 'ZOLM',
                'price' => 900,
                'seller_name' => 'ZOLM Home',
                'stock_quantity' => 0,
                'stock_status' => 'out_of_stock',
                'campaign_count' => 1,
                'campaigns' => ['Sepette İndirim'],
            ],
        ]);

        $this->assertSame($first['report']->id, $second['report']->id);
        $this->assertSame(2, $second['report']->run_count);
        $this->assertDatabaseHas('trendyol_bestseller_report_items', [
            'trendyol_bestseller_report_run_id' => $second['run']->id,
            'trendyol_product_id' => '700001',
            'rank_position' => 1,
            'previous_rank' => 4,
            'rank_delta' => 3,
        ]);

        $dashboard = $service->dashboard($user->id, $second['report']->id);
        $this->assertSame(2, data_get($dashboard, 'analysis.summary.run_count'));
        $this->assertSame(1, data_get($dashboard, 'analysis.summary.rising_count'));
        $this->assertSame(1, data_get($dashboard, 'analysis.summary.falling_count'));
        $alpha = collect(data_get($dashboard, 'analysis.latest_items'))->firstWhere('trendyol_product_id', '700001');
        $beta = collect(data_get($dashboard, 'analysis.latest_items'))->firstWhere('trendyol_product_id', '700002');
        $this->assertSame('Kampanya desteği', data_get($alpha, 'cause.label'));
        $this->assertSame('Stok baskısı', data_get($beta, 'cause.label'));
    }

    public function test_livewire_bestseller_tracking_activates_product_with_enriched_snapshot(): void
    {
        [$user] = $this->createBoosterGraph();
        $this->actingAs($user);

        Livewire::withQueryParams(['booster' => 'bestseller'])
            ->test(TrendyolBooster::class)
            ->set('bestsellerSearch', 'puf')
            ->call('bestsellerBridgeCompleted', [[
                'id' => '812345678',
                'source_url' => 'https://www.trendyol.com/zolm/puf-p-812345678',
                'rank' => 2,
                'title' => 'ZOLM Puf',
                'brand' => 'ZOLM',
                'price' => 750,
                'seller_name' => 'ZOLM Home',
                'seller_id' => '9911',
                'seller_score' => 9.5,
                'sellers' => [[
                    'seller_name' => 'ZOLM Home',
                    'seller_id' => '9911',
                    'seller_score' => 9.5,
                    'stock' => 25,
                    'sale_price' => 750,
                ]],
                'stock_quantity' => 25,
                'stock_status' => 'in_stock',
                'campaign_count' => 1,
                'campaigns' => ['Sepette 75 TL İndirim'],
                'rating' => 4.8,
                'rating_count' => 120,
            ]], 'puf', 'https://www.trendyol.com/cok-satanlar?type=bestSeller&categoryId=104493', 'Puflar için 1 ürün getirildi.', true, 'Puflar')
            ->call('trackBestseller', 0)
            ->assertSet('bestsellerTrackedProductIds.0', '812345678')
            ->assertSee('Takipte');

        $tracked = TrendyolBoosterProduct::query()
            ->where('user_id', $user->id)
            ->where('trendyol_product_id', '812345678')
            ->firstOrFail();
        $this->assertSame('active', $tracked->tracking_status);
        $this->assertTrue($tracked->analysis_auto_refresh_enabled);
        $this->assertSame('bestseller', data_get($tracked->tracking_sources, '0'));
        $this->assertSame('Sepette 75 TL İndirim', data_get($tracked->latestSnapshot?->raw_payload, 'page.promotions.0'));
    }

    public function test_monitor_service_writes_price_stock_snapshot_and_updates_tracked_product(): void
    {
        [$user, $product, $listing] = $this->createBoosterGraph();
        $tracked = app(TrendyolBoosterAnalysisService::class)->store($user->id, [
            'user_id' => $user->id,
            'source_url' => 'https://www.trendyol.com/zolm/booster-test-urunu-p-123456',
            'mp_product_id' => $product->id,
            'channel_listing_id' => $listing->id,
            'sale_price' => 1500,
            'target_margin_percent' => 20,
            'watch_price' => true,
            'watch_stock' => true,
        ]);

        Http::fake([
            'https://www.trendyol.com/*' => Http::response($this->trendyolProductHtml(), 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]),
        ]);

        $result = app(TrendyolBoosterMonitorService::class)->check($tracked);

        $this->assertTrue($result['ok']);
        $this->assertInstanceOf(TrendyolBoosterSnapshot::class, $result['snapshot']);
        $this->assertSame(1299.9, (float) $tracked->fresh()->sale_price);
        $this->assertSame(-200.1, (float) $result['snapshot']->price_delta);
        $this->assertSame('in_stock', $result['snapshot']->stock_status);
        $this->assertDatabaseHas('trendyol_booster_snapshots', [
            'trendyol_booster_product_id' => $tracked->id,
            'user_id' => $user->id,
            'stock_status' => 'in_stock',
        ]);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $user->id,
            'type' => 'booster_price_drop',
            'subject_type' => TrendyolBoosterSnapshot::class,
        ]);
    }

    public function test_competitor_service_adds_rival_and_marks_price_pressure(): void
    {
        [$user, $product, $listing] = $this->createBoosterGraph();
        $tracked = app(TrendyolBoosterAnalysisService::class)->store($user->id, [
            'user_id' => $user->id,
            'source_url' => 'https://www.trendyol.com/zolm/own-product-p-999999',
            'mp_product_id' => $product->id,
            'channel_listing_id' => $listing->id,
            'sale_price' => 1500,
            'target_margin_percent' => 20,
            'watch_price' => true,
        ]);

        Http::fake([
            'https://www.trendyol.com/*' => Http::response($this->trendyolProductHtml(), 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]),
        ]);

        $result = app(TrendyolBoosterCompetitorService::class)
            ->addFromUrl($tracked, 'https://www.trendyol.com/rakip/booster-rakip-p-123456');

        $this->assertTrue($result['ok']);
        $this->assertInstanceOf(TrendyolBoosterCompetitor::class, $result['competitor']);
        $this->assertSame('price_pressure', $result['competitor']->opportunity_type);
        $this->assertSame(200.1, (float) $result['competitor']->price_delta_vs_own);
        $this->assertDatabaseHas('trendyol_booster_competitors', [
            'trendyol_booster_product_id' => $tracked->id,
            'user_id' => $user->id,
            'opportunity_type' => 'price_pressure',
        ]);
    }

    public function test_search_result_reader_extracts_ranked_product_ids(): void
    {
        $data = app(TrendyolSearchResultReader::class)
            ->parse($this->trendyolSearchHtml(), 'booster test', 'https://www.trendyol.com/sr?q=booster%20test');

        $this->assertSame(['111111', '123456', '222222'], $data['product_ids']);
        $this->assertSame(3, $data['result_count']);
        $this->assertSame(3, $data['checked_result_count']);
    }

    public function test_search_result_reader_limits_rank_measurement_to_first_fifty_products(): void
    {
        $html = collect(range(1, 60))
            ->map(fn (int $id): string => '<a href="/urun/test-p-'.$id.'">Ürün '.$id.'</a>')
            ->implode('');

        $data = app(TrendyolSearchResultReader::class)
            ->parse($html, 'booster test', 'https://www.trendyol.com/sr?q=booster%20test');

        $this->assertCount(50, $data['product_ids']);
        $this->assertSame('1', $data['product_ids'][0]);
        $this->assertSame('50', $data['product_ids'][49]);
        $this->assertSame(50, $data['checked_result_count']);
        $this->assertCount(40, $data['top_products']);
    }

    public function test_store_reader_extracts_public_supplier_identity_fields_from_structured_data(): void
    {
        $html = <<<'HTML'
            <script type="application/json">
            {"merchant":{"companyName":"ZOLM Ev Ürünleri Ltd. Şti.","companyAddress":"İstanbul, Türkiye","registeredEmailAddress":"zolm@hs01.kep.tr","taxNumber":"1234567890","taxOfficeName":"Kadıköy","phoneNumber":"0212 000 00 00"}}
            </script>
            <a href="/zolm/test-urun-p-123456">Test Ürün</a>
            HTML;

        $data = app(TrendyolStorePageReader::class)
            ->parse($html, 'https://www.trendyol.com/magaza/zolm-m-321');

        $this->assertSame('ZOLM Ev Ürünleri Ltd. Şti.', $data['seller_title']);
        $this->assertSame('İstanbul, Türkiye', $data['address']);
        $this->assertSame('zolm@hs01.kep.tr', $data['kep']);
        $this->assertSame('1234567890', $data['tax_number']);
        $this->assertSame('Kadıköy', $data['tax_office']);
        $this->assertSame('0212 000 00 00', $data['phone']);
        $this->assertCount(1, $data['items']);
    }

    public function test_store_watch_resolves_product_link_to_seller_store_and_catalog_items(): void
    {
        [$user] = $this->createBoosterGraph();
        $requestedUrls = [];
        Http::fake(function ($request) use (&$requestedUrls) {
            $url = $request->url();
            $requestedUrls[] = $url;

            if (str_contains($url, '/magaza/zem-home-m-121057')) {
                return Http::response(<<<'HTML'
                    <script>
                    window.__SEARCH_APP_INITIAL_STATE__ = {"products":[
                        {"id":"900001","url":"/zem-home/rakip-sehpa-p-900001","name":"Zem Home Rakip Sehpa","brand":{"name":"Zem Home"},"price":{"sellingPrice":499.90}}
                    ]};
                    </script>
                    HTML, 200);
            }

            return Http::response($this->trendyolEnvoyProductHtml(), 200);
        });

        $result = app(TrendyolBoosterStoreWatchService::class)->scan(
            $user->id,
            'https://www.trendyol.com/zem/lines-puf-teddy-kumas-kirik-beyaz-p-286823139?merchantId=121057',
        );

        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('/magaza/zem-home-m-121057', implode("\n", $requestedUrls));
        $this->assertSame('121057', $result['watch']->store_id);
        $this->assertSame('Zem Home', $result['watch']->store_name);
        $this->assertSame('ZEM EV ÜRÜNLERİ LTD. ŞTİ.', data_get($result['watch']->raw_payload, 'seller_title'));
        $this->assertSame('zem@hs01.kep.tr', data_get($result['watch']->raw_payload, 'kep'));
        $this->assertSame('https://cdn.dsmcdn.com/zem/lines-puf.jpg', data_get($result['watch']->raw_payload, 'product_preview.image_url'));
        $this->assertSame(2, (int) $result['watch']->best_seller_count);
        $this->assertTrue((bool) data_get($result['watch']->raw_payload, 'resolved_from_product'));
        $this->assertSame('Lines Puf, Teddy Kumaş Kırık Beyaz', $result['watch']->items->first()->title);
    }

    public function test_target_planner_converts_revenue_and_margin_into_order_and_purchase_targets(): void
    {
        $dashboard = app(TrendyolBoosterModuleInsightService::class)->finance([
            'sale_price' => 1000,
            'cogs' => 400,
            'packaging_cost' => 0,
            'cargo_cost' => 50,
            'commission_rate' => 15,
            'service_fee_rate' => 0,
            'advertising_rate' => 0,
            'return_rate' => 0,
            'vat_enabled' => true,
            'withholding_enabled' => true,
            'withholding_rate' => 1,
            'income_tax_rate' => 25,
            'vat_rate' => 20,
            'cost_vat_rate' => 20,
            'expense_vat_rate' => 20,
            'target_margin_percent' => 20,
        ], 120000, 30);

        $this->assertSame(120, data_get($dashboard, 'target.planned_units'));
        $this->assertSame(4.0, data_get($dashboard, 'target.daily_units'));
        $this->assertSame(24000.0, data_get($dashboard, 'target.target_profit'));
        $this->assertGreaterThan(0, data_get($dashboard, 'target.max_purchase_gross'));
        $this->assertGreaterThan(0, data_get($dashboard, 'target.total_withholding'));
    }

    public function test_livewire_converts_decimal_tax_settings_to_percentage_inputs(): void
    {
        [$user] = $this->createBoosterGraph();
        $this->actingAs($user);

        Livewire::withQueryParams(['booster' => 'profit_loss'])
            ->test(TrendyolBooster::class)
            ->assertSet('vatRate', 10.0)
            ->assertSet('expenseVatRate', 20.0)
            ->assertSet('withholdingRate', 1.0);
    }

    public function test_keyword_tracking_tool_accepts_up_to_six_keywords_at_once(): void
    {
        [$user, $product, $listing] = $this->createBoosterGraph();
        $this->actingAs($user);
        $tracked = app(TrendyolBoosterAnalysisService::class)->store($user->id, [
            'user_id' => $user->id,
            'source_url' => 'https://www.trendyol.com/zolm/booster-test-urunu-p-123456',
            'trendyol_product_id' => '123456',
            'mp_product_id' => $product->id,
            'channel_listing_id' => $listing->id,
            'title' => 'Booster Test Ürünü',
            'sale_price' => 1000,
        ]);
        Http::fake([
            'https://www.trendyol.com/sr*' => Http::response(
                '<a href="/zolm/booster-test-urunu-p-123456">Booster Test Ürünü</a>',
                200,
            ),
        ]);

        Livewire::withQueryParams(['booster' => 'keyword_tracking'])
            ->test(TrendyolBooster::class)
            ->set('keywordTrackingProductId', $tracked->id)
            ->set('keywordTrackingKeyword', "puf\nkoltuk, teddy; dekoratif")
            ->set('keywordTrackingTarget', 10)
            ->call('trackKeywordFromTool')
            ->assertSet('messageType', 'success')
            ->assertSet('keywordTrackingKeyword', '');

        $this->assertSame(4, TrendyolBoosterKeyword::query()
            ->where('user_id', $user->id)
            ->where('trendyol_booster_product_id', $tracked->id)
            ->count());
    }

    public function test_keyword_tracking_url_falls_back_to_server_reader_without_companion(): void
    {
        [$user] = $this->createBoosterGraph();
        $this->actingAs($user);

        Http::fake([
            'https://www.trendyol.com/sr*' => Http::response($this->trendyolSearchHtml(), 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]),
            'https://www.trendyol.com/*' => Http::response($this->trendyolProductHtml(), 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]),
        ]);

        Livewire::withQueryParams(['booster' => 'keyword_tracking'])
            ->test(TrendyolBooster::class)
            ->call(
                'keywordTrackingServerFallback',
                'https://www.trendyol.com/zolm/booster-test-urunu-p-123456',
                ['booster test', 'beyaz puf'],
                10,
            )
            ->assertSet('messageType', 'success')
            ->assertSet('keywordTrackingKeyword', '');

        $tracked = TrendyolBoosterProduct::query()
            ->where('user_id', $user->id)
            ->where('trendyol_product_id', '123456')
            ->first();

        $this->assertNotNull($tracked);
        $this->assertSame(2, TrendyolBoosterKeyword::query()
            ->where('user_id', $user->id)
            ->where('trendyol_booster_product_id', $tracked->id)
            ->count());
        $this->assertDatabaseHas('trendyol_booster_keywords', [
            'trendyol_booster_product_id' => $tracked->id,
            'keyword' => 'booster test',
            'visibility_status' => 'visible',
        ]);
    }

    public function test_sell_decision_service_uses_velocity_and_does_not_double_deduct_withholding(): void
    {
        [$user, $product, $listing] = $this->createBoosterGraph();
        $tracked = app(TrendyolBoosterAnalysisService::class)->store($user->id, [
            'user_id' => $user->id,
            'source_url' => 'https://www.trendyol.com/zolm/booster-test-urunu-p-123456',
            'trendyol_product_id' => '123456',
            'mp_product_id' => $product->id,
            'channel_listing_id' => $listing->id,
            'sale_price' => 1500,
            'cogs' => 700,
            'packaging_cost' => 35,
            'cargo_cost' => 70,
            'commission_rate' => 12,
            'vat_enabled' => true,
            'withholding_enabled' => true,
            'withholding_rate' => 1,
            'target_margin_percent' => 20,
        ]);

        TrendyolBoosterSnapshot::query()->create([
            'trendyol_booster_product_id' => $tracked->id,
            'user_id' => $user->id,
            'sale_price' => 1500,
            'stock_status' => 'in_stock',
            'availability' => 'in_stock',
            'stock_quantity' => 100,
            'evaluation_count' => 100,
            'review_count' => 20,
            'average_rating' => 4.6,
            'favorite_count' => 1000,
            'question_count' => 12,
            'seller_score' => 9.1,
            'analysis_source' => 'manual_refresh',
            'opportunity_score' => 80,
            'decision_status' => 'go',
            'net_profit' => 250,
            'profit_margin_percent' => 16,
            'checked_at' => now()->subDay(),
        ]);
        TrendyolBoosterSnapshot::query()->create([
            'trendyol_booster_product_id' => $tracked->id,
            'user_id' => $user->id,
            'sale_price' => 1500,
            'stock_status' => 'in_stock',
            'availability' => 'in_stock',
            'stock_quantity' => 88,
            'evaluation_count' => 112,
            'review_count' => 23,
            'average_rating' => 4.6,
            'favorite_count' => 1120,
            'question_count' => 15,
            'seller_score' => 9.1,
            'analysis_source' => 'manual_refresh',
            'opportunity_score' => 80,
            'decision_status' => 'go',
            'net_profit' => 250,
            'profit_margin_percent' => 16,
            'checked_at' => now(),
        ]);

        $service = app(TrendyolBoosterSellDecisionService::class);
        $input = [
            'sale_price' => 1500,
            'cogs' => 700,
            'packaging_cost' => 35,
            'cargo_cost' => 70,
            'commission_rate' => 12,
            'vat_enabled' => true,
            'withholding_enabled' => true,
            'withholding_rate' => 1,
            'income_tax_rate' => 25,
            'vat_rate' => 20,
            'cost_vat_rate' => 20,
            'expense_vat_rate' => 20,
        ];

        $decision = $service->decide($tracked->fresh(), $input, [
            'keyword' => 'booster test',
            'product_ids' => ['123456', '222222'],
            'top_products' => [],
        ]);
        $withoutWithholding = $service->decide($tracked->fresh(), array_merge($input, [
            'withholding_enabled' => false,
        ]));

        $this->assertSame(12.0, data_get($decision, 'velocity.stock_drop_per_day'));
        $this->assertSame(3.0, data_get($decision, 'velocity.review_per_day'));
        $this->assertSame(12.0, data_get($decision, 'velocity.estimated_daily_sales'));
        $this->assertGreaterThan(0, data_get($decision, 'financial.withholding'));
        $this->assertSame(
            data_get($withoutWithholding, 'financial.net_profit'),
            data_get($decision, 'financial.net_profit'),
        );
        $this->assertContains(data_get($decision, 'decision'), ['sell', 'test']);
    }

    public function test_livewire_runs_sell_or_do_not_sell_decision_from_live_product_data(): void
    {
        [$user] = $this->createBoosterGraph();
        $this->actingAs($user);

        Http::fake([
            'https://www.trendyol.com/sr*' => Http::response(
                '<a href="/zem/lines-puf-p-286823139">1</a><a href="/rakip/urun-p-999999">2</a>',
                200,
            ),
            'https://www.trendyol.com/*' => Http::response($this->trendyolEnvoyProductHtml(), 200),
        ]);

        $component = Livewire::withQueryParams(['booster' => 'sell_decision'])
            ->test(TrendyolBooster::class)
            ->set('productUrl', 'https://www.trendyol.com/zem/lines-puf-teddy-kumas-kirik-beyaz-p-286823139')
            ->set('cogs', 620)
            ->set('packagingCost', 35)
            ->set('cargoCost', 70)
            ->set('commissionRate', 18)
            ->set('vatEnabled', true)
            ->set('withholdingEnabled', true)
            ->set('incomeTaxRate', 25)
            ->call('runSellDecision')
            ->assertSet('activeModule', 'sell_decision')
            ->assertSet('messageType', 'success');

        $result = $component->get('sellDecisionResult');

        $this->assertSame('286823139', data_get($result, 'product.trendyol_product_id'));
        $this->assertGreaterThan(0, data_get($result, 'financial.net_profit'));
        $this->assertSame(1, data_get($result, 'market.product_rank'));
        $this->assertDatabaseHas('trendyol_booster_products', [
            'user_id' => $user->id,
            'trendyol_product_id' => '286823139',
            'tracking_status' => 'active',
        ]);
    }

    public function test_livewire_completes_sell_decision_with_companion_price_and_keeps_live_snapshot(): void
    {
        [$user, $product, $listing] = $this->createBoosterGraph();
        $this->actingAs($user);
        $tracked = app(TrendyolBoosterAnalysisService::class)->store($user->id, [
            'user_id' => $user->id,
            'source_url' => 'https://www.trendyol.com/pufyhome/dublin-etna-nil-pelus-krem-cok-amacli-dekoratif-pufkoltuk-p-171062238',
            'trendyol_product_id' => '171062238',
            'mp_product_id' => $product->id,
            'channel_listing_id' => $listing->id,
            'title' => 'Dublin Etna Nil Peluş Krem Çok Amaçlı Dekoratif Pufkoltuk',
            'brand' => 'PufyHome',
            'category_name' => 'Puf & Bench',
            'sale_price' => 605,
            'watch_price' => true,
        ]);
        TrendyolBoosterSnapshot::query()->create([
            'trendyol_booster_product_id' => $tracked->id,
            'user_id' => $user->id,
            'sale_price' => 605,
            'stock_status' => 'in_stock',
            'availability' => 'InStock',
            'stock_quantity' => 48,
            'evaluation_count' => 381,
            'review_count' => 182,
            'average_rating' => 4.3,
            'favorite_count' => 2450,
            'category_rank' => 1,
            'analysis_source' => 'browser_companion',
            'opportunity_score' => 75,
            'decision_status' => 'go',
            'net_profit' => 0,
            'profit_margin_percent' => 0,
            'checked_at' => now(),
        ]);

        $component = Livewire::withQueryParams(['booster' => 'sell_decision'])
            ->test(TrendyolBooster::class)
            ->set('productUrl', $tracked->source_url)
            ->set('cogs', 250)
            ->set('cargoCost', 55)
            ->set('commissionRate', 18)
            ->set('sellDecisionUseMarketSearch', false)
            ->call('sellDecisionBridgeCompleted', $tracked->id, 'Canlı ürün analizi tamamlandı.', true)
            ->assertSet('salePrice', 605.0)
            ->assertSet('messageType', 'success');

        $result = $component->get('sellDecisionResult');

        $this->assertSame('171062238', data_get($result, 'product.trendyol_product_id'));
        $this->assertSame(182, data_get($result, 'velocity.total_reviews'));
        $this->assertSame(1, TrendyolBoosterSnapshot::query()->where('trendyol_booster_product_id', $tracked->id)->count());
        $this->assertDatabaseHas('trendyol_booster_products', [
            'id' => $tracked->id,
            'sale_price' => 605,
            'cogs' => 250,
            'tracking_status' => 'active',
        ]);
    }

    public function test_keyword_service_tracks_visibility_rank_for_product(): void
    {
        [$user, $product, $listing] = $this->createBoosterGraph();
        $tracked = app(TrendyolBoosterAnalysisService::class)->store($user->id, [
            'user_id' => $user->id,
            'source_url' => 'https://www.trendyol.com/zolm/booster-test-urunu-p-123456',
            'mp_product_id' => $product->id,
            'channel_listing_id' => $listing->id,
            'sale_price' => 1500,
            'target_margin_percent' => 20,
            'watch_keyword' => true,
        ]);

        Http::fake([
            'https://www.trendyol.com/sr*' => Http::response($this->trendyolSearchHtml(), 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]),
        ]);

        $result = app(TrendyolBoosterKeywordService::class)->addKeyword($tracked, 'booster test', 3);

        $this->assertTrue($result['ok']);
        $this->assertInstanceOf(TrendyolBoosterKeyword::class, $result['keyword']);
        $this->assertSame('visible', $result['keyword']->visibility_status);
        $this->assertSame(2, $result['keyword']->observed_rank);
        $this->assertSame(3, $result['keyword']->checked_result_count);
        $this->assertDatabaseHas('trendyol_booster_keywords', [
            'trendyol_booster_product_id' => $tracked->id,
            'user_id' => $user->id,
            'keyword' => 'booster test',
            'visibility_status' => 'visible',
        ]);
    }

    public function test_keyword_tracking_dashboard_explains_rank_band_and_checked_scope(): void
    {
        [$user, $product, $listing] = $this->createBoosterGraph();
        $tracked = app(TrendyolBoosterAnalysisService::class)->store($user->id, [
            'user_id' => $user->id,
            'source_url' => 'https://www.trendyol.com/zolm/booster-test-urunu-p-123456',
            'trendyol_product_id' => '123456',
            'mp_product_id' => $product->id,
            'channel_listing_id' => $listing->id,
            'title' => 'Booster Test Ürünü',
            'sale_price' => 1500,
        ]);
        $keyword = TrendyolBoosterKeyword::query()->create([
            'trendyol_booster_product_id' => $tracked->id,
            'user_id' => $user->id,
            'keyword' => 'beyaz puf',
            'keyword_hash' => hash('sha256', 'beyaz puf'),
            'target_rank' => 10,
            'is_active' => true,
        ]);

        app(TrendyolBoosterKeywordService::class)->recordObservation($keyword, null, 382, 120);

        $dashboard = app(TrendyolBoosterModuleInsightService::class)
            ->marketDashboard('keyword_tracking', $user->id, '', $tracked->id);
        $row = $dashboard['rows']->first();

        $this->assertSame('missing', $row['status']);
        $this->assertSame(120, $row['checked_count']);
        $this->assertSame(382, $row['result_count']);
        $this->assertSame('İlk 120 içinde yok', $row['rank_band_label']);
        $this->assertSame(1, data_get($dashboard, 'summary.missing'));
        $this->assertSame(0, data_get($dashboard, 'summary.found'));

        $this->actingAs($user);
        Livewire::withQueryParams(['booster' => 'keyword_tracking'])
            ->test(TrendyolBooster::class)
            ->set('keywordTrackingCurrentProductId', $tracked->id)
            ->assertSee('Ürün hangi kelimede kaçıncı sırada?')
            ->assertSee('İlk 120 içinde yok')
            ->assertSee('382 toplam sonuç')
            ->assertSee('Bulunamadı');
    }

    public function test_cost_preset_service_stores_normalized_values(): void
    {
        [$user] = $this->createBoosterGraph();
        $preset = app(TrendyolBoosterCostPresetService::class)->store($user->id, [
            'name' => 'Elektronik',
            'category_name' => 'Elektronik',
            'commission_rate' => '12,5',
            'cargo_cost' => '45,90',
            'return_cargo_cost' => 45.9,
            'packaging_cost' => 9.75,
            'service_fee_rate' => 2,
            'advertising_rate' => 4,
            'return_rate' => 6,
        ]);
        $values = app(TrendyolBoosterCostPresetService::class)->values($preset);

        $this->assertSame('Elektronik', $preset->name);
        $this->assertSame(12.5, $values['commissionRate']);
        $this->assertSame(45.9, $values['cargoCost']);
        $this->assertDatabaseHas('trendyol_booster_cost_presets', [
            'user_id' => $user->id,
            'name' => 'Elektronik',
        ]);
    }

    public function test_campaign_scenario_service_stores_decision_result(): void
    {
        [$user, $product, $listing] = $this->createBoosterGraph();
        $tracked = app(TrendyolBoosterAnalysisService::class)->store($user->id, [
            'user_id' => $user->id,
            'source_url' => 'https://www.trendyol.com/zolm/booster-test-urunu-p-123456',
            'mp_product_id' => $product->id,
            'channel_listing_id' => $listing->id,
            'sale_price' => 1500,
            'target_margin_percent' => 20,
        ]);

        $scenario = app(TrendyolBoosterCampaignScenarioService::class)->simulateAndStore($tracked, [
            'name' => 'Kupon Test',
            'discount_rate' => 5,
            'commission_discount_rate' => 12,
            'expected_units' => 10,
        ]);

        $this->assertSame('approve', $scenario->decision_status);
        $this->assertSame(1425.0, (float) $scenario->campaign_price);
        $this->assertGreaterThan(0, (float) $scenario->campaign_net_profit);
        $this->assertDatabaseHas('trendyol_booster_campaign_scenarios', [
            'trendyol_booster_product_id' => $tracked->id,
            'user_id' => $user->id,
            'name' => 'Kupon Test',
        ]);
    }

    public function test_companion_preview_endpoint_returns_booster_decision_without_tracking(): void
    {
        [$user, $product, $listing] = $this->createBoosterGraph();
        $this->actingAs($user);

        $response = $this->postJson(route('mp.trendyol-booster.companion.preview'), $this->companionPayload($product, $listing));

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('mode', 'preview')
            ->assertJsonPath('decision.status', 'go')
            ->assertJsonPath('normalized.trendyol_product_id', '123456');

        $this->assertSame(0, TrendyolBoosterProduct::query()
            ->where('user_id', $user->id)
            ->where('trendyol_product_id', '123456')
            ->count());
    }

    public function test_companion_preview_caps_decision_when_costs_are_missing(): void
    {
        [$user] = $this->createBoosterGraph();
        $this->actingAs($user);

        $response = $this->postJson(route('mp.trendyol-booster.companion.preview'), [
            'source_url' => 'https://www.trendyol.com/zem/long-line-puf-kirik-beyaz-gold-p-76241080',
            'page' => [
                'trendyol_product_id' => '76241080',
                'title' => 'Zem Long Line Puf, Kırık Beyaz Gold',
                'brand' => 'Zem',
                'sale_price' => 1259.91,
            ],
            'watch_price' => true,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('decision.status', 'risk')
            ->assertJsonPath('decision.score', 35)
            ->assertJsonFragment([
                'Ürün maliyeti eksik olduğu için kâr ve marj güvenilir kabul edilmedi.',
            ]);
    }

    public function test_companion_session_endpoint_returns_csrf_and_routes(): void
    {
        [$user] = $this->createBoosterGraph();
        $this->actingAs($user);

        $this->getJson(route('mp.trendyol-booster.companion.session'))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('authenticated', true)
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonStructure([
                'csrf_token',
                'endpoints' => ['preview', 'track', 'dashboard'],
            ]);
    }

    public function test_companion_track_endpoint_persists_booster_product(): void
    {
        [$user, $product, $listing] = $this->createBoosterGraph();
        $this->actingAs($user);

        $response = $this->postJson(route('mp.trendyol-booster.companion.track'), $this->companionPayload($product, $listing));

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('mode', 'track')
            ->assertJsonPath('decision.status', 'go')
            ->assertJsonPath('message', 'Booster takibine eklendi.');

        $this->assertDatabaseHas('trendyol_booster_products', [
            'user_id' => $user->id,
            'trendyol_product_id' => '123456',
            'title' => 'ZOLM Booster Test Ürünü',
        ]);
    }

    public function test_livewire_booster_fetches_trendyol_link_and_fills_form_values(): void
    {
        [$user] = $this->createBoosterGraph();
        $this->actingAs($user);

        Http::fake([
            'https://www.trendyol.com/*' => Http::response($this->trendyolProductHtml(), 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]),
        ]);

        Livewire::test(TrendyolBooster::class)
            ->set('productUrl', 'https://www.trendyol.com/zolm/booster-test-urunu-p-123456')
            ->call('fetchProductFromUrl')
            ->assertSet('title', 'ZOLM Booster Test Ürünü')
            ->assertSet('brand', 'ZOLM')
            ->assertSet('categoryName', 'Ev & Yaşam')
            ->assertSet('salePrice', 1299.9)
            ->assertSee('Ürün bilgileri getirildi.');
    }

    public function test_livewire_booster_uses_url_fallback_when_trendyol_blocks_page(): void
    {
        [$user] = $this->createBoosterGraph();
        $this->actingAs($user);

        Http::fake([
            'https://www.trendyol.com/*' => Http::response('Forbidden', 403),
        ]);

        Livewire::test(TrendyolBooster::class)
            ->set('productUrl', 'https://www.trendyol.com/zem/lines-puf-teddy-kumas-kirik-beyaz-p-2868')
            ->call('fetchProductFromUrl')
            ->assertSet('title', 'Lines Puf Teddy Kumas Kirik Beyaz')
            ->assertSet('brand', 'Zem')
            ->assertSet('salePrice', 0)
            ->assertSee('Trendyol sayfası erişimi sınırladı; linkten temel bilgiler alındı.');
    }

    public function test_finance_tool_applies_companion_price_without_overwriting_manual_costs(): void
    {
        [$user, $product, $listing] = $this->createBoosterGraph();
        $this->actingAs($user);
        $tracked = app(TrendyolBoosterAnalysisService::class)->store($user->id, [
            'user_id' => $user->id,
            'source_url' => 'https://www.trendyol.com/ustaagac/puf-koltuk-tabure-p-842980519',
            'trendyol_product_id' => '842980519',
            'mp_product_id' => $product->id,
            'channel_listing_id' => $listing->id,
            'title' => 'Usta Ağaç Puf Koltuk Tabure',
            'brand' => 'UstaAğaç',
            'sale_price' => 538,
            'commission_rate' => 12,
        ]);

        Livewire::withQueryParams(['booster' => 'profit_loss'])
            ->test(TrendyolBooster::class)
            ->set('cogs', 250)
            ->set('cargoCost', 60)
            ->set('commissionRate', 18)
            ->call('financeProductBridgeCompleted', $tracked->id, 538, 'Canlı ürün fiyatı okundu.', true)
            ->assertSet('salePrice', 538.0)
            ->assertSet('cogs', 250)
            ->assertSet('cargoCost', 60)
            ->assertSet('commissionRate', 18)
            ->assertSet('messageType', 'success')
            ->assertSee('Canlı Trendyol fiyatı Chrome Companion ile alındı')
            ->assertSee('Chrome Companion hazır');
    }

    public function test_seller_level_uses_official_general_and_category_thresholds(): void
    {
        $service = app(TrendyolSellerLevelService::class);

        $this->assertSame(4, $service->classify(40_000_000, 38_500)['level']);
        $this->assertSame(3, $service->classify(6_000_000, 6_000)['level']);
        $this->assertSame(2, $service->classify(300_000, 385)['level']);
        $this->assertSame(1, $service->classify(299_999, 384)['level']);

        $hafifMobilya = [
            'name' => 'Hafif Mobilya',
            5 => ['revenue' => 150_000_000, 'orders' => 50_000],
            4 => ['revenue' => 20_000_000, 'orders' => 15_000],
        ];
        $banyo = [
            'name' => 'Banyo',
            5 => null,
            4 => ['revenue' => null, 'orders' => 38_000],
        ];

        $this->assertSame(4, $service->classify(1_000, 1, $hafifMobilya, 20_000_000, 15_000)['level']);
        $this->assertSame(4, $service->classify(1_000, 1, $banyo, 0, 38_000)['level']);
    }

    public function test_public_seller_score_is_not_used_as_a_seller_level_proxy(): void
    {
        [$user, $product, $listing] = $this->createBoosterGraph();
        $tracked = app(TrendyolBoosterAnalysisService::class)->store($user->id, [
            'user_id' => $user->id,
            'source_url' => 'https://www.trendyol.com/zolm/booster-test-urunu-p-998877',
            'trendyol_product_id' => '998877',
            'mp_product_id' => $product->id,
            'channel_listing_id' => $listing->id,
            'sale_price' => 1_000,
        ]);

        $result = app(TrendyolSellerLevelService::class)->resolve($user->id, [
            'seller_id' => 'PUBLIC-SELLER-NOT-CONNECTED',
            'seller_score' => 9.9,
            'title' => 'Puf Koltuk Tabure',
            'category_name' => 'Puflar',
        ], $tracked);

        $this->assertNull($result['level']);
        $this->assertSame('unavailable', $result['source']);
    }

    public function test_connected_store_level_uses_net_order_metrics(): void
    {
        [$user, $product, $listing] = $this->createBoosterGraph();
        $store = $listing->store;
        $listing->forceFill(['published_at' => now()->subDays(90)])->save();
        $order = ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'external_order_id' => 'LEVEL-ORDER-'.Str::uuid(),
            'order_number' => 'LEVEL-1',
            'order_status' => 'delivered',
            'ordered_at' => now()->subDays(10),
        ]);
        ChannelOrderItem::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $order->id,
            'mp_product_id' => $product->id,
            'external_line_id' => 'LEVEL-LINE-'.Str::uuid(),
            'product_name' => 'Booster Test Ürünü',
            'quantity' => 1,
            'unit_price' => 1_000,
            'gross_amount' => 1_000,
            'discount_amount' => 100,
            'billable_amount' => 900,
            'line_status' => 'delivered',
        ]);
        $tracked = app(TrendyolBoosterAnalysisService::class)->store($user->id, [
            'user_id' => $user->id,
            'source_url' => 'https://www.trendyol.com/zolm/booster-test-urunu-p-987654',
            'trendyol_product_id' => '987654',
            'mp_product_id' => $product->id,
            'channel_listing_id' => $listing->id,
            'sale_price' => 1_000,
        ]);

        $result = app(TrendyolSellerLevelService::class)->resolve($user->id, [
            'seller_id' => $store->seller_id,
            'title' => $product->product_name,
            'category_name' => $product->category_name,
        ], $tracked);

        $this->assertSame(1, $result['level']);
        $this->assertSame('connected_store_180d', $result['source']);
        $this->assertSame(900.0, $result['metrics']['revenue_180d']);
        $this->assertSame(1, $result['metrics']['orders_180d']);
        $this->assertSame(1, $result['metrics']['orders_30d']);
    }

    public function test_commission_estimator_uses_explicit_level_but_not_seller_score(): void
    {
        [$user] = $this->createBoosterGraph();
        TrendyolBoosterCommissionRate::query()->create([
            'user_id' => $user->id,
            'category_name' => 'Ev ve Mobilya',
            'sub_category_name' => 'Hafif Mobilya',
            'product_group' => 'Puf, Tabure',
            'maturity_days' => 21,
            'commission_rate' => 20,
            'level_5_rate' => 12,
            'level_4_rate' => 14,
            'level_3_rate' => 16,
            'level_2_rate' => 18,
            'level_1_rate' => 20,
            'marketplace' => 'trendyol',
            'source' => 'Test',
            'imported_at' => now(),
        ]);
        $estimator = app(TrendyolBoosterCommissionEstimator::class);
        $context = [
            'seller_id' => 'PUBLIC-SELLER',
            'seller_score' => 9.9,
            'title' => 'Dekoratif Puf Tabure',
            'category_name' => 'Puflar',
        ];

        $unknown = $estimator->estimate($user->id, $context);
        $levelFour = $estimator->estimate($user->id, $context + ['seller_level' => 4]);

        $this->assertNull($unknown['seller_level']);
        $this->assertSame(20.0, $unknown['rate']);
        $this->assertSame(4, $levelFour['seller_level']);
        $this->assertSame(14.0, $levelFour['rate']);
    }

    public function test_shipping_recommendation_applies_exact_barem_and_desi_tariff(): void
    {
        [$user] = $this->createBoosterGraph();
        $service = app(TrendyolBoosterShippingRateService::class);

        $advantage = $service->recommend(199, 5, 'TEX', true, $user->id);
        $standard = $service->recommend(200, 5, 'Aras', false, $user->id);

        $this->assertSame(34.16, $advantage['cost_net']);
        $this->assertSame(40.99, $advantage['cost_gross']);
        $this->assertSame('trendyol_barem', $advantage['source']);
        $this->assertSame(79.99, $standard['cost_net']);

        TrendyolBoosterShippingRate::query()->create([
            'user_id' => $user->id,
            'cargo_company' => 'Aras',
            'desi' => 18,
            'price' => 120,
            'marketplace' => 'trendyol',
            'source' => 'Test tarifesi',
            'imported_at' => now(),
        ]);
        $desiTariff = $service->recommend(350, 18, 'Aras', true, $user->id);

        $this->assertSame(120.0, $desiTariff['cost_net']);
        $this->assertSame(144.0, $desiTariff['cost_gross']);
        $this->assertSame('user_shipping_tariff', $desiTariff['source']);
    }

    public function test_desi_estimator_uses_product_dimensions_with_packaging_allowance(): void
    {
        [$user] = $this->createBoosterGraph();

        $result = app(TrendyolBoosterDesiEstimator::class)->estimate($user->id, [
            'title' => 'Dekoratif Puf Tabure',
            'category_name' => 'Puflar',
            'attributes' => [
                ['name' => 'Boyut', 'value' => '38 x 38 x 33 cm'],
            ],
        ]);

        $this->assertSame('product_dimensions', $result['source']);
        $this->assertSame(17.15, $result['estimated_desi']);
        $this->assertSame(18, $result['billable_desi']);
    }

    public function test_livewire_booster_can_check_tracked_product_snapshot(): void
    {
        [$user, $product, $listing] = $this->createBoosterGraph();
        $this->actingAs($user);
        $tracked = app(TrendyolBoosterAnalysisService::class)->store($user->id, [
            'user_id' => $user->id,
            'source_url' => 'https://www.trendyol.com/zolm/booster-test-urunu-p-123456',
            'mp_product_id' => $product->id,
            'channel_listing_id' => $listing->id,
            'sale_price' => 1500,
            'target_margin_percent' => 20,
            'watch_price' => true,
            'watch_stock' => true,
        ]);

        Http::fake([
            'https://www.trendyol.com/*' => Http::response($this->trendyolProductHtml(), 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]),
        ]);

        Livewire::test(TrendyolBooster::class)
            ->call('checkTrackedProduct', $tracked->id)
            ->assertSee('Kontrol tamamlandı');

        $this->assertSame(1, TrendyolBoosterSnapshot::query()
            ->where('trendyol_booster_product_id', $tracked->id)
            ->count());
    }

    public function test_livewire_booster_can_add_competitor_to_tracked_product(): void
    {
        [$user, $product, $listing] = $this->createBoosterGraph();
        $this->actingAs($user);
        $tracked = app(TrendyolBoosterAnalysisService::class)->store($user->id, [
            'user_id' => $user->id,
            'source_url' => 'https://www.trendyol.com/zolm/own-product-p-999999',
            'mp_product_id' => $product->id,
            'channel_listing_id' => $listing->id,
            'sale_price' => 1500,
            'target_margin_percent' => 20,
            'watch_price' => true,
        ]);

        Http::fake([
            'https://www.trendyol.com/*' => Http::response($this->trendyolProductHtml(), 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]),
        ]);

        Livewire::test(TrendyolBooster::class)
            ->set('competitorUrls.'.$tracked->id, 'https://www.trendyol.com/rakip/booster-rakip-p-123456')
            ->call('addCompetitor', $tracked->id)
            ->assertSee('Rakip radarına eklendi.');

        $this->assertSame(1, TrendyolBoosterCompetitor::query()
            ->where('trendyol_booster_product_id', $tracked->id)
            ->count());
    }

    public function test_livewire_booster_can_add_keyword_to_tracked_product(): void
    {
        [$user, $product, $listing] = $this->createBoosterGraph();
        $this->actingAs($user);
        $tracked = app(TrendyolBoosterAnalysisService::class)->store($user->id, [
            'user_id' => $user->id,
            'source_url' => 'https://www.trendyol.com/zolm/booster-test-urunu-p-123456',
            'mp_product_id' => $product->id,
            'channel_listing_id' => $listing->id,
            'sale_price' => 1500,
            'target_margin_percent' => 20,
            'watch_keyword' => true,
        ]);

        Http::fake([
            'https://www.trendyol.com/sr*' => Http::response($this->trendyolSearchHtml(), 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]),
        ]);

        Livewire::test(TrendyolBooster::class)
            ->set('keywordInputs.'.$tracked->id, 'booster test')
            ->set('keywordTargets.'.$tracked->id, 3)
            ->call('addKeyword', $tracked->id)
            ->assertSee('Anahtar kelime görünürlüğü güncellendi.');

        $this->assertSame(1, TrendyolBoosterKeyword::query()
            ->where('trendyol_booster_product_id', $tracked->id)
            ->count());
    }

    public function test_livewire_booster_saves_and_applies_cost_preset(): void
    {
        [$user] = $this->createBoosterGraph();
        $this->actingAs($user);

        Livewire::test(TrendyolBooster::class)
            ->set('costPresetName', 'Elektronik')
            ->set('commissionRate', 18.5)
            ->set('cargoCost', 66)
            ->set('returnCargoCost', 44)
            ->set('packagingCost', 12)
            ->set('serviceFeeRate', 1.5)
            ->set('advertisingRate', 3)
            ->call('saveCostPreset')
            ->assertSee('Maliyet preset kaydedildi.');

        $preset = TrendyolBoosterCostPreset::query()
            ->where('user_id', $user->id)
            ->where('name', 'Elektronik')
            ->firstOrFail();

        Livewire::test(TrendyolBooster::class)
            ->set('selectedCostPresetId', $preset->id)
            ->set('commissionRate', 0)
            ->set('cargoCost', 0)
            ->call('applyCostPreset')
            ->assertSet('commissionRate', 18.5)
            ->assertSet('cargoCost', 66.0)
            ->assertSet('returnCargoCost', 44.0)
            ->assertSee('Maliyet preset uygulandı.');
    }

    public function test_livewire_booster_can_simulate_campaign_scenario(): void
    {
        [$user, $product, $listing] = $this->createBoosterGraph();
        $this->actingAs($user);
        $tracked = app(TrendyolBoosterAnalysisService::class)->store($user->id, [
            'user_id' => $user->id,
            'source_url' => 'https://www.trendyol.com/zolm/booster-test-urunu-p-123456',
            'mp_product_id' => $product->id,
            'channel_listing_id' => $listing->id,
            'sale_price' => 1500,
            'target_margin_percent' => 20,
        ]);

        Livewire::test(TrendyolBooster::class)
            ->set('campaignNames.'.$tracked->id, 'Kupon Test')
            ->set('campaignDiscountRates.'.$tracked->id, 5)
            ->set('campaignCommissionDiscountRates.'.$tracked->id, 12)
            ->set('campaignExpectedUnits.'.$tracked->id, 10)
            ->call('simulateCampaign', $tracked->id)
            ->assertSee('Kampanya senaryosu hesaplandı');

        $this->assertSame(1, TrendyolBoosterCampaignScenario::query()
            ->where('trendyol_booster_product_id', $tracked->id)
            ->count());
    }

    public function test_livewire_booster_loads_product_values_and_tracks_link(): void
    {
        [$user, $product] = $this->createBoosterGraph();
        $this->actingAs($user);

        Livewire::test(TrendyolBooster::class)
            ->set('selectedProductId', $product->id)
            ->assertSet('title', 'Booster Test Ürünü')
            ->assertSet('salePrice', 1500.0)
            ->set('productUrl', 'https://www.trendyol.com/test/urun-p-987654')
            ->call('analyzeAndTrack')
            ->assertSee('Booster takibine eklendi.')
            ->assertSee('Analiz edilmiş ürünler');

        $this->assertDatabaseHas('trendyol_booster_products', [
            'user_id' => $user->id,
            'trendyol_product_id' => '987654',
            'title' => 'Booster Test Ürünü',
        ]);
    }

    public function test_favorite_action_feeds_the_ledger_favorites_filter(): void
    {
        [$user] = $this->createBoosterGraph();
        $this->actingAs($user);

        $favorite = app(TrendyolBoosterAnalysisService::class)->store($user->id, [
            'source_url' => 'https://www.trendyol.com/zolm/favori-urun-p-701001',
            'title' => 'Favori Booster Ürünü',
            'sale_price' => 900,
        ]);
        app(TrendyolBoosterAnalysisService::class)->store($user->id, [
            'source_url' => 'https://www.trendyol.com/zolm/diger-urun-p-701002',
            'title' => 'Diğer Booster Ürünü',
            'sale_price' => 800,
        ]);
        $favorite->forceFill(['is_favorite' => true])->save();

        Livewire::test(TrendyolBooster::class)
            ->assertSee('Favori Booster Ürünü')
            ->assertSee('Diğer Booster Ürünü')
            ->call('toggleFavoritesOnly')
            ->assertSet('favoritesOnly', true)
            ->assertSee('Favori Booster Ürünü')
            ->assertDontSee('Diğer Booster Ürünü')
            ->assertSee('Yalnızca favoriler');

        $dashboard = app(TrendyolBoosterAnalysisService::class)->dashboard($user->id, true);
        $this->assertSame(1, $dashboard['favorite_count']);
        $this->assertCount(1, $dashboard['products']);
    }

    public function test_stock_service_compares_stock_checks_and_logs_history(): void
    {
        [$user, $product, $listing] = $this->createBoosterGraph();
        app(TrendyolBoosterAnalysisService::class)->store($user->id, [
            'user_id' => $user->id,
            'source_url' => 'https://www.trendyol.com/zolm/booster-test-urunu-p-123456',
            'mp_product_id' => $product->id,
            'channel_listing_id' => $listing->id,
            'sale_price' => 1500,
            'target_margin_percent' => 20,
            'watch_price' => true,
            'watch_stock' => true,
        ]);

        $service = app(TrendyolBoosterStockService::class);
        $service->check($user->id, [
            'source_url' => 'https://www.trendyol.com/zolm/booster-test-urunu-p-123456',
            'page' => ['title' => 'ZOLM Booster Test Ürünü', 'trendyol_product_id' => '123456'],
            'total_stock' => 100,
            'sellers' => [['seller_name' => 'Booster Store', 'stock' => 100, 'sale_price' => 1500]],
        ]);
        $result = $service->check($user->id, [
            'source_url' => 'https://www.trendyol.com/zolm/booster-test-urunu-p-123456',
            'page' => ['title' => 'ZOLM Booster Test Ürünü', 'trendyol_product_id' => '123456'],
            'total_stock' => 82,
            'sellers' => [['seller_name' => 'Booster Store', 'stock' => 82, 'sale_price' => 1500]],
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame(18, (int) $result['check']->estimated_sales);
        $this->assertDatabaseHas('trendyol_booster_stock_checks', [
            'user_id' => $user->id,
            'total_stock' => 82,
            'previous_total_stock' => 100,
            'estimated_sales' => 18,
        ]);
        $this->assertDatabaseHas('trendyol_booster_activity_logs', [
            'user_id' => $user->id,
            'activity_type' => 'stock_check',
            'result_label' => 'stok',
        ]);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $user->id,
            'type' => 'booster_stock_sales',
            'subject_type' => TrendyolBoosterStockCheck::class,
        ]);
    }

    public function test_stock_service_persists_envoy_stock_and_seller_from_product_page(): void
    {
        [$user] = $this->createBoosterGraph();
        Http::fake([
            'https://www.trendyol.com/*' => Http::response($this->trendyolEnvoyProductHtml(), 200),
        ]);

        $result = app(TrendyolBoosterStockService::class)->check($user->id, [
            'source_url' => 'https://www.trendyol.com/zem/lines-puf-teddy-kumas-kirik-beyaz-p-286823139?merchantId=121057',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame(780, (int) $result['check']->total_stock);
        $this->assertSame(1, (int) $result['check']->seller_count);
        $this->assertSame('87874848484848484', $result['check']->barcode);
        $this->assertDatabaseHas('trendyol_booster_stock_sellers', [
            'trendyol_booster_stock_check_id' => $result['check']->id,
            'seller_name' => 'Zem Home',
            'seller_id' => '121057',
            'stock' => 780,
        ]);
    }

    public function test_stock_service_does_not_create_false_zero_when_trendyol_blocks_page(): void
    {
        [$user] = $this->createBoosterGraph();
        Http::fake([
            'https://www.trendyol.com/*' => Http::response('', 403),
        ]);

        $result = app(TrendyolBoosterStockService::class)->check($user->id, [
            'source_url' => 'https://www.trendyol.com/zem/lines-puf-teddy-kumas-kirik-beyaz-p-286823139?merchantId=121057',
        ]);

        $this->assertFalse($result['ok']);
        $this->assertNull($result['check']);
        $this->assertStringContainsString('sıfır stok kaydı oluşturulmadı', $result['message']);
        $this->assertSame(0, TrendyolBoosterStockCheck::query()->where('user_id', $user->id)->count());
    }

    public function test_stock_dashboard_ignores_legacy_unknown_zero_records(): void
    {
        [$user] = $this->createBoosterGraph();
        TrendyolBoosterStockCheck::query()->create([
            'user_id' => $user->id,
            'source_url' => 'https://www.trendyol.com/zem/legacy-p-123',
            'source_url_hash' => hash('sha256', 'https://www.trendyol.com/zem/legacy-p-123'),
            'title' => 'Eski hatalı stok kaydı',
            'total_stock' => 0,
            'stock_delta' => 0,
            'estimated_sales' => 0,
            'seller_count' => 0,
            'stock_status' => 'unknown',
            'checked_at' => now(),
        ]);

        $dashboard = app(TrendyolBoosterStockService::class)->dashboard($user->id);

        $this->assertSame(0, $dashboard['total_checks']);
        $this->assertSame(0, $dashboard['last_total_stock']);
        $this->assertTrue($dashboard['latest_checks']->isEmpty());
    }

    public function test_store_watch_service_notifies_when_competitor_catalog_changes(): void
    {
        [$user] = $this->createBoosterGraph();
        $service = app(TrendyolBoosterStoreWatchService::class);
        $storeUrl = 'https://www.trendyol.com/magaza/zolm-store-m-987';

        $service->scan($user->id, $storeUrl, [
            'store_id' => '987',
            'store_name' => 'ZOLM Store',
            'items' => [
                ['trendyol_product_id' => '123456', 'title' => 'ZOLM Booster Test Ürünü', 'source_url' => 'https://www.trendyol.com/zolm/booster-test-urunu-p-123456', 'sale_price' => 1500],
            ],
        ]);
        $result = $service->scan($user->id, $storeUrl, [
            'store_id' => '987',
            'store_name' => 'ZOLM Store',
            'items' => [
                ['trendyol_product_id' => '123456', 'title' => 'ZOLM Booster Test Ürünü', 'source_url' => 'https://www.trendyol.com/zolm/booster-test-urunu-p-123456', 'sale_price' => 1450],
                ['trendyol_product_id' => '222222', 'title' => 'Rakip Yeni Ürün', 'source_url' => 'https://www.trendyol.com/zolm/yeni-rakip-p-222222', 'sale_price' => 1200],
            ],
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame(1, (int) $result['watch']->new_product_count);
        $this->assertSame(1, (int) $result['watch']->price_change_count);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $user->id,
            'type' => 'booster_store_change',
            'subject_type' => TrendyolBoosterStoreWatch::class,
        ]);
    }

    public function test_store_reader_uses_storefront_before_current_seller_catalog_url(): void
    {
        $requestedUrls = [];
        Http::fake(function ($request) use (&$requestedUrls) {
            $requestedUrls[] = $request->url();

            return Http::response(<<<'HTML'
                <html><body>
                    <h1>Zem Home</h1>
                    <a href="/zem/lines-puf-teddy-kumas-kirik-beyaz-p-286823139">
                        <img alt="Lines Puf Teddy Kumaş Kırık Beyaz">
                        <span>1.289,90 TL</span>
                    </a>
                </body></html>
                HTML, 200);
        });

        $result = app(TrendyolStorePageReader::class)
            ->fetch('https://www.trendyol.com/magaza/zem-home-m-121057?sst=0&channelId=1');

        $this->assertTrue($result['ok']);
        $this->assertSame(['https://www.trendyol.com/magaza/zem-home-m-121057?sst=0&channelId=1'], $requestedUrls);
        $this->assertSame('121057', data_get($result, 'data.store_id'));
        $this->assertSame('Zem Home', data_get($result, 'data.store_name'));
        $this->assertSame('286823139', data_get($result, 'data.items.0.trendyol_product_id'));
    }

    public function test_store_reader_falls_back_to_current_seller_catalog_url(): void
    {
        $requestedUrls = [];
        Http::fake(function ($request) use (&$requestedUrls) {
            $requestedUrls[] = $request->url();

            if (str_contains($request->url(), '/sr?mid=993019')) {
                return Http::response(<<<'HTML'
                    <html><body>
                        <h1>Ardelya Chair</h1>
                        <a href="/efza/puf-puf-bench-p-1145343517">Efza Puf Puf & Bench</a>
                    </body></html>
                    HTML, 200);
            }

            return Http::response('<html><body><h1>Ardelya Chair</h1></body></html>', 200);
        });

        $result = app(TrendyolStorePageReader::class)
            ->fetch('https://www.trendyol.com/magaza/ardelya-chair-m-993019?sst=0&channelId=1');

        $this->assertTrue($result['ok']);
        $this->assertSame('https://www.trendyol.com/magaza/ardelya-chair-m-993019?sst=0&channelId=1', $requestedUrls[0]);
        $this->assertContains('https://www.trendyol.com/sr?mid=993019&os=1', $requestedUrls);
        $this->assertSame('993019', data_get($result, 'data.store_id'));
        $this->assertSame('Ardelya Chair', data_get($result, 'data.store_name'));
        $this->assertSame('1145343517', data_get($result, 'data.items.0.trendyol_product_id'));
    }

    public function test_store_watch_merges_legacy_url_variants_by_store_id(): void
    {
        [$user] = $this->createBoosterGraph();
        $emptyWatch = TrendyolBoosterStoreWatch::query()->create([
            'user_id' => $user->id,
            'store_url' => 'https://www.trendyol.com/sr?mid=993019&siralama=en-cok-degerlendirilenler',
            'store_url_hash' => hash('sha256', 'https://www.trendyol.com/sr?mid=993019&siralama=en-cok-degerlendirilenler'),
            'store_name' => 'Ardelya Chair',
        ]);
        $filledWatch = TrendyolBoosterStoreWatch::query()->create([
            'user_id' => $user->id,
            'store_url' => 'https://www.trendyol.com/magaza/satici-m-993019',
            'store_url_hash' => hash('sha256', 'https://www.trendyol.com/magaza/satici-m-993019'),
            'store_id' => '993019',
            'store_name' => 'Satici',
        ]);
        $filledWatch->items()->create([
            'user_id' => $user->id,
            'trendyol_product_id' => '1145343517',
            'source_url' => 'https://www.trendyol.com/efza/puf-puf-bench-p-1145343517',
            'title' => 'Efza Puf Puf & Bench',
            'sale_price' => 2805.70,
        ]);

        $result = app(TrendyolBoosterStoreWatchService::class)->scan(
            $user->id,
            'https://www.trendyol.com/magaza/ardelya-chair-m-993019?sst=0&channelId=1',
            [
                'store_id' => '993019',
                'store_name' => 'Ardelya Chair',
                'items' => [
                    ['trendyol_product_id' => '1145343517', 'title' => 'Efza Puf Puf & Bench', 'source_url' => 'https://www.trendyol.com/efza/puf-puf-bench-p-1145343517', 'sale_price' => 2805.70],
                    ['trendyol_product_id' => '1134129829', 'title' => 'Efza Armeni sandalye', 'source_url' => 'https://www.trendyol.com/efza/armeni-sandalye-p-1134129829', 'sale_price' => 5650],
                ],
            ],
        );

        $this->assertTrue($result['ok']);
        $this->assertSame($filledWatch->id, $result['watch']->id);
        $this->assertSame('Ardelya Chair', $result['watch']->store_name);
        $this->assertSame('https://www.trendyol.com/magaza/ardelya-chair-m-993019', $result['watch']->store_url);
        $this->assertSame(2, $result['watch']->items()->count());
        $this->assertDatabaseMissing('trendyol_booster_store_watches', ['id' => $emptyWatch->id]);
        $this->assertSame(1, TrendyolBoosterStoreWatch::query()->where('user_id', $user->id)->where('store_id', '993019')->count());
    }

    public function test_store_watch_does_not_publish_empty_scans_to_dashboard(): void
    {
        [$user] = $this->createBoosterGraph();
        Http::fake(fn () => Http::response('<html><body><h1>Zem Home</h1><p>Çerez tercihleri</p></body></html>', 200));

        $service = app(TrendyolBoosterStoreWatchService::class);
        $result = $service->scan($user->id, 'https://www.trendyol.com/magaza/zem-home-m-121057');

        $this->assertFalse($result['ok']);
        $this->assertFalse((bool) $result['watch']->is_active);
        $this->assertSame(0, $result['watch']->items()->count());
        $this->assertSame(0, $service->dashboard($user->id)['total']);
        $this->assertDatabaseHas('trendyol_booster_store_watches', [
            'user_id' => $user->id,
            'store_id' => '121057',
            'is_active' => false,
        ]);
    }

    public function test_store_detail_renders_category_distribution_links_and_inline_product_analysis(): void
    {
        [$user] = $this->createBoosterGraph();
        $this->actingAs($user);
        $watch = TrendyolBoosterStoreWatch::query()->create([
            'user_id' => $user->id,
            'store_url' => 'https://www.trendyol.com/magaza/zem-home-m-121057',
            'store_url_hash' => hash('sha256', 'https://www.trendyol.com/magaza/zem-home-m-121057'),
            'store_id' => '121057',
            'store_name' => 'Zem Home',
            'total_products' => 1,
            'best_seller_count' => 1,
            'is_active' => true,
            'last_checked_at' => now(),
        ]);
        $item = $watch->items()->create([
            'user_id' => $user->id,
            'trendyol_product_id' => '286823139',
            'source_url' => 'https://www.trendyol.com/zem/lines-puf-teddy-kumas-kirik-beyaz-p-286823139',
            'image_url' => 'https://cdn.dsmcdn.com/zem/lines-puf.jpg',
            'title' => 'Zem Lines Puf, Teddy Kumaş Kırık Beyaz',
            'brand' => '',
            'sale_price' => 1289.90,
            'rating' => 4.6,
            'review_count' => 940,
            'favorite_count' => 1200,
            'stock_quantity' => 14,
            'stock_status' => 'in_stock',
            'rank' => 1,
            'is_new' => true,
            'campaign_badges' => ['Kargo Bedava'],
        ]);

        Livewire::test(TrendyolBooster::class)
            ->set('activeModule', 'competitor')
            ->call('viewStoreDetail', $watch->id)
            ->assertSee('Puf & Bench')
            ->assertSeeHtml('href="https://www.trendyol.com/zem/lines-puf-teddy-kumas-kirik-beyaz-p-286823139"')
            ->call('analyzeStoreWatchItem', $item->id)
            ->assertSet('storeDetailAnalysisItemId', $item->id)
            ->assertSee('Hızlı Ürün Analizi')
            ->assertSee('Stok')
            ->assertSee('14')
            ->assertSee('1.200')
            ->assertSee('Rakip ürün analizi detay panelinde hazırlandı.');
    }

    public function test_store_watch_records_scan_snapshots_and_compares_later_scans(): void
    {
        [$user] = $this->createBoosterGraph();
        $this->actingAs($user);
        $service = app(TrendyolBoosterStoreWatchService::class);
        $url = 'https://www.trendyol.com/magaza/tacev-m-768162';

        $first = $service->scan($user->id, $url, [
            'store_id' => '768162',
            'store_name' => 'Taçev',
            'items' => [
                ['trendyol_product_id' => '501', 'title' => 'Taç Bella Döküm Sahan Seti', 'brand' => 'Taç', 'sale_price' => 999, 'review_count' => 5, 'favorite_count' => 10, 'total_stock' => 18],
                ['trendyol_product_id' => '502', 'title' => 'Taç Eva Döküm Krep Tava', 'brand' => 'Taç', 'sale_price' => 579, 'review_count' => 3, 'favorite_count' => 6],
            ],
        ]);
        $this->assertTrue($first['ok']);

        $second = $service->scan($user->id, $url, [
            'store_id' => '768162',
            'store_name' => 'Taçev',
            'items' => [
                ['trendyol_product_id' => '501', 'title' => 'Taç Bella Döküm Sahan Seti', 'brand' => 'Taç', 'sale_price' => 949, 'review_count' => 8, 'favorite_count' => 15, 'total_stock' => 12],
                ['trendyol_product_id' => '503', 'title' => 'Taç Triton Döküm Tava Seti', 'brand' => 'Taç', 'sale_price' => 1350, 'review_count' => 4, 'favorite_count' => 20],
            ],
        ]);
        $this->assertTrue($second['ok']);

        $watch = TrendyolBoosterStoreWatch::query()
            ->where('user_id', $user->id)
            ->where('store_id', '768162')
            ->firstOrFail();
        $latestSnapshot = TrendyolBoosterStoreWatchSnapshot::query()
            ->where('trendyol_booster_store_watch_id', $watch->id)
            ->latest('checked_at')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(2, $watch->snapshots()->where('status', 'ok')->count());
        $this->assertSame(2, (int) $latestSnapshot->scan_number);
        $this->assertSame(1, (int) $latestSnapshot->price_change_count);
        $this->assertSame(35, (int) $latestSnapshot->total_favorites);
        $this->assertSame(0, data_get($latestSnapshot->change_summary, 'active_product_delta'));
        $this->assertDatabaseHas('trendyol_booster_store_item_histories', [
            'trendyol_booster_store_watch_snapshot_id' => $latestSnapshot->id,
            'favorite_count' => 15,
            'stock_quantity' => 12,
        ]);
        DB::table('trendyol_booster_store_item_histories')
            ->where('favorite_count', 10)
            ->where('stock_quantity', 18)
            ->update(['created_at' => now()->subDay()]);
        $detail = $service->storeDetail($user->id, $watch->id);
        $mainItem = $detail['items']->firstWhere('trendyol_product_id', '501');

        $this->assertSame(6, data_get($mainItem?->store_sales_signal, 'stock_drop'));
        $this->assertGreaterThan(0, (float) data_get($mainItem?->store_sales_signal, 'estimated_daily_sales'));
        $this->assertSame(5, data_get($mainItem?->store_sales_signal, 'favorite_delta'));

        Livewire::test(TrendyolBooster::class)
            ->set('activeModule', 'competitor')
            ->call('viewStoreDetail', $watch->id)
            ->assertSee('Tarama geçmişi')
            ->assertSee('#2')
            ->assertSee('Favori değişimi')
            ->assertSee('Tahmini');
    }

    public function test_store_watch_rejects_suspiciously_small_scans_without_removing_catalog(): void
    {
        [$user] = $this->createBoosterGraph();
        $service = app(TrendyolBoosterStoreWatchService::class);
        $url = 'https://www.trendyol.com/magaza/zem-home-m-121057';

        $items = collect(range(1, 40))
            ->map(fn (int $index): array => [
                'trendyol_product_id' => (string) (900000 + $index),
                'source_url' => 'https://www.trendyol.com/zem/bohem-puf-'.$index.'-p-'.(900000 + $index),
                'title' => 'Zem Bohem Puf '.$index,
                'brand' => 'Zem Home',
                'category_name' => 'Puf & Bench',
                'sale_price' => 1000 + $index,
                'review_count' => $index,
                'favorite_count' => $index * 10,
            ])
            ->all();

        $first = $service->scan($user->id, $url, [
            'store_id' => '121057',
            'store_name' => 'Zem Home',
            'items' => $items,
        ]);

        $this->assertTrue($first['ok']);
        $this->assertSame(40, $first['watch']->items()->where('is_removed', false)->count());

        $second = $service->scan($user->id, $url, [
            'store_id' => '121057',
            'store_name' => 'Zem Home',
            'items' => [[
                'trendyol_product_id' => '999999999',
                'source_url' => 'https://www.trendyol.com/apple/iphone-16-pro-max-256gb-siyah-titanyum-p-999999999',
                'title' => 'iPhone 16 Pro Max 256GB Siyah Titanyum',
                'brand' => 'Apple',
                'category_name' => 'IOS Cep Telefonları',
                'sale_price' => 100999,
                'review_count' => 94,
                'favorite_count' => 12551,
            ]],
        ]);

        $this->assertFalse($second['ok']);
        $watch = TrendyolBoosterStoreWatch::query()
            ->where('user_id', $user->id)
            ->where('store_id', '121057')
            ->firstOrFail();

        $this->assertSame(40, $watch->items()->count());
        $this->assertSame(40, $watch->items()->where('is_removed', false)->count());
        $this->assertDatabaseMissing('trendyol_booster_store_watch_items', [
            'trendyol_booster_store_watch_id' => $watch->id,
            'trendyol_product_id' => '999999999',
        ]);
        $this->assertSame(1, $watch->snapshots()->where('status', 'ok')->count());
        $this->assertSame(1, $watch->snapshots()->where('status', 'failed')->count());

        $this->actingAs($user);
        Livewire::test(TrendyolBooster::class)
            ->set('activeModule', 'competitor')
            ->call('viewStoreDetail', $watch->id)
            ->assertSee('Son deneme mağaza kataloğunu güvenli okuyamadı')
            ->assertSee('Korunan Ürün Listesi');
    }

    public function test_store_watch_sanitizes_campaign_badges_from_browser_payload(): void
    {
        [$user] = $this->createBoosterGraph();

        $result = app(TrendyolBoosterStoreWatchService::class)->scan(
            $user->id,
            'https://www.trendyol.com/magaza/zem-home-m-121057',
            [
                'store_id' => '121057',
                'store_name' => 'Zem Home',
                'items' => [[
                    'trendyol_product_id' => '286823139',
                    'source_url' => 'https://www.trendyol.com/zem/lines-puf-p-286823139',
                    'title' => 'Zem Lines Puf',
                    'sale_price' => 1190.13,
                    'campaign_badges' => [
                        "1000 TL'ye 100 TL İndirimSepette1.090,13 TL1.190,13 TL",
                        'Sepette1.090,13 TL1.190,13 TL',
                        'KUPONLUÜRÜN',
                        "KuponlarımTrendyol'da Satış YapHakkımızda Yardım & Destek Ürün, kategori veya marka ara Giriş Yap",
                        '350 TL ve Üzeri Kargo Bedava (Satıcı Karşılar)',
                        'En Çok Satılan #6 Ürün Zem Lines Puf',
                        "6000 TL'ye %5 İndirimSepette Uygulanacak",
                        "Trendyol Plus'a Özel Fiyat",
                    ],
                ]],
            ],
        );

        $this->assertTrue($result['ok']);
        $this->assertSame([
            "1000 TL'ye 100 TL İndirim",
            'Kuponlu Ürün',
            '350 TL ve Üzeri Kargo Bedava',
            'En Çok Satılan #6 Ürün',
            "6000 TL'ye %5 İndirim",
            "Trendyol Plus'a Özel Fiyat",
        ], $result['watch']->items->first()->campaign_badges);
    }

    public function test_store_watch_clamps_implausible_browser_prices_before_persisting(): void
    {
        [$user] = $this->createBoosterGraph();

        $result = app(TrendyolBoosterStoreWatchService::class)->scan($user->id, 'https://www.trendyol.com/magaza/onetick-outdoor-m-111745', [
            'store_id' => '111745',
            'store_name' => 'Onetick Outdoor',
            'items' => [[
                'trendyol_product_id' => '991493292',
                'source_url' => 'https://www.trendyol.com/onetick/4-mevsim-sisme-kamp-cadiri-yuksek-ve-cam-tavanli-6-8-kisilik-300x250x220cm-p-991493292?merchantId=111745',
                'title' => 'Onetick 4 Mevsim Şişme Kamp Çadırı 300x250x220cm 4.4 Kargo Bedava 15.193 TL',
                'brand' => 'Onetick',
                'sale_price' => 4_683_002_502_204.42,
            ]],
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame(0.0, (float) $result['watch']->items->first()->sale_price);
    }

    public function test_keyword_service_notifies_when_visibility_rank_changes(): void
    {
        [$user, $product, $listing] = $this->createBoosterGraph();
        $tracked = app(TrendyolBoosterAnalysisService::class)->store($user->id, [
            'user_id' => $user->id,
            'source_url' => 'https://www.trendyol.com/zolm/booster-test-urunu-p-123456',
            'mp_product_id' => $product->id,
            'channel_listing_id' => $listing->id,
            'sale_price' => 1500,
            'target_margin_percent' => 20,
            'watch_keyword' => true,
        ]);

        Http::fake([
            'https://www.trendyol.com/sr*' => Http::sequence()
                ->push($this->trendyolSearchHtml(), 200, ['Content-Type' => 'text/html; charset=UTF-8'])
                ->push($this->trendyolSearchHtmlWithProductFirst(), 200, ['Content-Type' => 'text/html; charset=UTF-8']),
        ]);

        $service = app(TrendyolBoosterKeywordService::class);
        $service->addKeyword($tracked, 'booster test', 3);
        $result = $service->addKeyword($tracked, 'booster test', 3);

        $this->assertTrue($result['ok']);
        $this->assertSame(1, (int) $result['keyword']->observed_rank);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $user->id,
            'type' => 'booster_keyword_change',
            'subject_type' => TrendyolBoosterKeyword::class,
        ]);
    }

    public function test_booster_email_digest_sends_pending_notifications_once(): void
    {
        Mail::fake();
        [$user] = $this->createBoosterGraph();
        $notification = $this->createBoosterNotification($user, 'booster_price_drop', 'Fiyat düştü');
        $mutedNotification = $this->createBoosterNotification($user, 'booster_stock_sales', 'Stok eridi');
        $this->createBoosterNotification($user, 'new_order', 'Sipariş bildirimi');
        app(NotificationCenterService::class)->setMutedTypes($user->id, ['booster_stock_sales']);

        $result = app(TrendyolBoosterEmailDigestService::class)->sendPending($user->id);

        $this->assertSame(1, $result['processed']);
        $this->assertSame(1, $result['sent']);
        $this->assertSame(1, $result['notifications']);
        $this->assertNotNull($notification->fresh()->email_digest_sent_at);
        $this->assertNull($mutedNotification->fresh()->email_digest_sent_at);

        Mail::assertSent(TrendyolBoosterDigestMail::class, function (TrendyolBoosterDigestMail $mail): bool {
            return ($mail->payload['counts']['total'] ?? 0) === 1
                && ($mail->payload['counts']['price'] ?? 0) === 1
                && str_contains((string) $mail->payload['subject'], 'ZOLM Trendyol Booster Özeti');
        });

        $again = app(TrendyolBoosterEmailDigestService::class)->sendPending($user->id);

        $this->assertSame(0, $again['processed']);
        $this->assertSame(0, $again['sent']);
        $this->assertSame(0, $again['notifications']);
    }

    public function test_livewire_booster_can_toggle_notification_groups(): void
    {
        [$user] = $this->createBoosterGraph();
        $this->actingAs($user);

        Livewire::test(TrendyolBooster::class)
            ->set('activeModule', 'notifications')
            ->assertSee('Fiyat uyarıları')
            ->call('toggleBoosterNotificationGroup', 'price')
            ->assertSee('Fiyat uyarıları kapatıldı.')
            ->call('toggleBoosterNotificationGroup', 'price')
            ->assertSee('Fiyat uyarıları açıldı.');

        $mutedTypes = app(NotificationCenterService::class)->mutedTypesForUser($user->id);

        $this->assertNotContains('booster_price_drop', $mutedTypes);
        $this->assertNotContains('booster_price_rise', $mutedTypes);
    }

    public function test_booster_email_digest_command_can_force_send(): void
    {
        Mail::fake();
        [$user] = $this->createBoosterGraph();
        $this->createBoosterNotification($user, 'booster_stock_sales', 'Stok eridi');

        config()->set('marketplace.trendyol_booster.email_digest_enabled', false);

        $this->artisan('marketplace:send-trendyol-booster-digests', [
            '--user' => $user->id,
            '--force' => true,
        ])->assertExitCode(0);

        Mail::assertSent(TrendyolBoosterDigestMail::class, 1);
    }

    public function test_booster_monitor_dry_run_respects_stale_window_without_http(): void
    {
        [$user, $product, $listing] = $this->createBoosterGraph();
        $oldTracked = app(TrendyolBoosterAnalysisService::class)->store($user->id, [
            'user_id' => $user->id,
            'source_url' => 'https://www.trendyol.com/zolm/old-booster-product-p-111111',
            'mp_product_id' => $product->id,
            'channel_listing_id' => $listing->id,
            'sale_price' => 1500,
            'target_margin_percent' => 20,
            'watch_price' => true,
        ]);
        $freshTracked = app(TrendyolBoosterAnalysisService::class)->store($user->id, [
            'user_id' => $user->id,
            'source_url' => 'https://www.trendyol.com/zolm/fresh-booster-product-p-222222',
            'mp_product_id' => $product->id,
            'channel_listing_id' => $listing->id,
            'sale_price' => 1500,
            'target_margin_percent' => 20,
            'watch_price' => true,
        ]);
        $oldTracked->forceFill(['last_checked_at' => now()->subHours(3)])->save();
        $freshTracked->forceFill(['last_checked_at' => now()])->save();

        Http::fake();
        $result = app(TrendyolBoosterMonitorService::class)->checkDue(10, $user->id, 60, true);

        $this->assertSame(1, $result['processed']);
        $this->assertSame(1, $result['skipped']);
        $this->assertTrue($result['dry_run']);
        $this->assertSame(0, TrendyolBoosterSnapshot::query()->where('user_id', $user->id)->count());
        Http::assertNothingSent();
    }

    public function test_booster_sync_command_prioritizes_area_stale_window_over_common_option(): void
    {
        [$user, $product, $listing] = $this->createBoosterGraph();
        $tracked = app(TrendyolBoosterAnalysisService::class)->store($user->id, [
            'user_id' => $user->id,
            'source_url' => 'https://www.trendyol.com/zolm/old-booster-product-p-111111',
            'mp_product_id' => $product->id,
            'channel_listing_id' => $listing->id,
            'sale_price' => 1500,
            'target_margin_percent' => 20,
            'watch_price' => true,
        ]);
        $tracked->forceFill(['last_checked_at' => now()->subHours(3)])->save();

        Http::fake();

        $exitCode = Artisan::call('marketplace:sync-trendyol-booster', [
            '--user' => $user->id,
            '--dry-run' => true,
            '--product-limit' => 1,
            '--competitor-limit' => 1,
            '--keyword-limit' => 1,
            '--store-limit' => 1,
            '--stale-minutes' => 10,
            '--product-stale-minutes' => 5,
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Ürün limit / stale', $output);
        $this->assertStringContainsString('1 / 5 dk', $output);
        $this->assertStringContainsString('Rakip limit / stale', $output);
        $this->assertStringContainsString('1 / 10 dk', $output);

        Http::assertNothingSent();
    }

    public function test_booster_monitor_logs_sync_errors_without_stopping_queue(): void
    {
        [$user, $product, $listing] = $this->createBoosterGraph();
        $tracked = app(TrendyolBoosterAnalysisService::class)->store($user->id, [
            'user_id' => $user->id,
            'source_url' => 'https://example.com/not-trendyol-p-333333',
            'mp_product_id' => $product->id,
            'channel_listing_id' => $listing->id,
            'sale_price' => 1500,
            'target_margin_percent' => 20,
            'watch_price' => true,
        ]);
        $tracked->forceFill(['last_checked_at' => null])->save();

        $result = app(TrendyolBoosterMonitorService::class)->checkDue(10, $user->id);

        $this->assertSame(1, $result['processed']);
        $this->assertSame(1, $result['failed']);
        $this->assertDatabaseHas('trendyol_booster_activity_logs', [
            'user_id' => $user->id,
            'trendyol_booster_product_id' => $tracked->id,
            'activity_type' => 'sync_error',
            'title' => 'Otomatik ürün kontrolü başarısız',
        ]);
    }

    public function test_keyword_lookup_service_persists_search_history(): void
    {
        [$user] = $this->createBoosterGraph();
        Http::fake([
            'https://www.trendyol.com/sr*' => Http::response($this->trendyolSearchHtml(), 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]),
        ]);

        $result = app(TrendyolBoosterKeywordLookupService::class)->search($user->id, 'booster test');

        $this->assertTrue($result['ok']);
        $this->assertSame(3, (int) $result['lookup']->result_count);
        $this->assertNotEmpty($result['lookup']->top_products);
        $this->assertDatabaseHas('trendyol_booster_keyword_lookups', [
            'user_id' => $user->id,
            'keyword' => 'booster test',
        ]);
    }

    public function test_companion_stock_and_store_endpoints_persist_browser_payloads(): void
    {
        [$user] = $this->createBoosterGraph();
        $this->actingAs($user);

        $this->postJson(route('mp.trendyol-booster.companion.stock-check'), [
            'source_url' => 'https://www.trendyol.com/zolm/booster-test-urunu-p-123456',
            'page' => ['trendyol_product_id' => '123456', 'title' => 'ZOLM Booster Test Ürünü', 'sale_price' => 1500],
            'total_stock' => 44,
            'sellers' => [['seller_name' => 'ZOLM Store', 'stock' => 44, 'sale_price' => 1500]],
        ])
            ->assertOk()
            ->assertJsonPath('mode', 'stock_check')
            ->assertJsonPath('stock.total_stock', 44);

        $this->postJson(route('mp.trendyol-booster.companion.store-scan'), [
            'store_url' => 'https://www.trendyol.com/magaza/zolm-store-m-987',
            'store' => [
                'store_id' => '987',
                'store_name' => 'ZOLM Store',
                'items' => [
                    ['trendyol_product_id' => '123456', 'title' => 'ZOLM Booster Test Ürünü', 'source_url' => 'https://www.trendyol.com/zolm/booster-test-urunu-p-123456', 'sale_price' => 1500, 'total_stock' => 44, 'favorite_count' => 1200, 'enrichment_status' => 'enriched'],
                    ['trendyol_product_id' => '222222', 'title' => 'Rakip Ürün', 'source_url' => 'https://www.trendyol.com/zolm/rakip-p-222222', 'sale_price' => 1400],
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('mode', 'store_scan')
            ->assertJsonPath('store.total_products', 2);

        $this->assertDatabaseHas('trendyol_booster_store_watch_items', [
            'user_id' => $user->id,
            'trendyol_product_id' => '123456',
            'stock_quantity' => 44,
            'favorite_count' => 1200,
        ]);

        $this->postJson(route('mp.trendyol-booster.companion.market-research'), [
            'source_url' => 'https://www.trendyol.com/zolm/booster-test-urunu-p-123456',
            'page' => [
                'trendyol_product_id' => '123456',
                'title' => 'ZOLM Booster Test Ürünü',
                'brand' => 'ZOLM',
                'sale_price' => 1500,
                'sellers' => [['seller_name' => 'ZOLM Store', 'seller_id' => '987', 'stock' => 44, 'sale_price' => 1500]],
            ],
            'offers' => [[
                'platform' => 'hepsiburada',
                'platform_label' => 'Hepsiburada',
                'seller_name' => 'ZOLM Store',
                'title' => 'ZOLM Booster Test Ürünü',
                'source_url' => 'https://www.hepsiburada.com/zolm-booster-test-p-HBCV123',
                'sale_price' => 1490,
                'availability' => 'in_stock',
                'source_type' => 'google_shopping',
            ]],
            'search_query' => '"ZOLM Booster Test Ürünü" fiyat satıcı',
            'search_url' => 'https://www.google.com/search?q=zolm+booster+test',
            'searched_platforms' => ['trendyol', 'hepsiburada'],
        ])
            ->assertOk()
            ->assertJsonPath('mode', 'market_research')
            ->assertJsonPath('research.platform_count', 2)
            ->assertJsonPath('research.seller_count', 2);

        $this->assertSame(1, TrendyolBoosterStockCheck::query()->where('user_id', $user->id)->count());
        $this->assertSame(1, TrendyolBoosterStoreWatch::query()->where('user_id', $user->id)->count());
        $this->assertSame(1, TrendyolBoosterSupplierResearch::query()->where('user_id', $user->id)->count());
        $this->assertSame(2, TrendyolBoosterSupplierOffer::query()->where('user_id', $user->id)->count());
    }

    public function test_companion_product_analysis_persists_all_metrics_and_recent_reviews(): void
    {
        [$user] = $this->createBoosterGraph();
        $this->actingAs($user);

        $response = $this->postJson(
            route('mp.trendyol-booster.companion.product-analysis'),
            $this->productAnalysisPayload(),
        );

        $response
            ->assertOk()
            ->assertJsonPath('mode', 'product_analysis')
            ->assertJsonPath('analysis.trendyol_product_id', '286823139')
            ->assertJsonPath('analysis.current.evaluation_count', 940)
            ->assertJsonPath('analysis.current.review_count', 622)
            ->assertJsonPath('analysis.current.average_rating', 4.59)
            ->assertJsonPath('analysis.current.favorite_count', 59680)
            ->assertJsonCount(2, 'analysis.recent_reviews');

        $trackedId = (int) $response->json('analysis.tracked_product_id');
        $snapshot = TrendyolBoosterSnapshot::query()
            ->where('trendyol_booster_product_id', $trackedId)
            ->latest('id')
            ->firstOrFail();

        $this->assertDatabaseHas('trendyol_booster_products', [
            'id' => $trackedId,
            'user_id' => $user->id,
            'trendyol_product_id' => '286823139',
            'image_url' => 'https://cdn.dsmcdn.com/zem/lines-puf.jpg',
        ]);
        $this->assertSame(940, $snapshot->evaluation_count);
        $this->assertSame(622, $snapshot->review_count);
        $this->assertSame(59680, $snapshot->favorite_count);
        $this->assertCount(2, $snapshot->recent_reviews);
        $this->assertSame('Çok beğendimmm', $snapshot->recent_reviews[0]['comment']);
    }

    public function test_companion_product_analysis_rejects_zero_price_payload(): void
    {
        [$user] = $this->createBoosterGraph();
        $this->actingAs($user);
        $payload = $this->productAnalysisPayload();
        $payload['page']['sale_price'] = 0;

        $this->postJson(route('mp.trendyol-booster.companion.product-analysis'), $payload)
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('mode', 'product_analysis');

        $this->assertDatabaseMissing('trendyol_booster_products', [
            'user_id' => $user->id,
            'trendyol_product_id' => '286823139',
        ]);
    }

    public function test_livewire_product_analysis_refuses_url_fallback_without_live_price(): void
    {
        [$user] = $this->createBoosterGraph();
        $this->actingAs($user);

        Http::fake([
            'https://www.trendyol.com/*' => Http::response('Forbidden', 403),
        ]);

        Livewire::test(TrendyolBooster::class)
            ->set('productUrl', 'https://www.trendyol.com/pierre-cardin/800051f303-kol-saati-p-66901615?merchantId=968')
            ->call('analyzeResearchProduct')
            ->assertSet('messageType', 'error')
            ->assertSee('Ürün analiz edilemedi');

        $this->assertDatabaseMissing('trendyol_booster_products', [
            'user_id' => $user->id,
            'trendyol_product_id' => '66901615',
        ]);
    }

    public function test_product_analysis_compares_previous_scan_and_renders_mobile_friendly_result(): void
    {
        [$user] = $this->createBoosterGraph();
        $this->actingAs($user);

        $first = $this->postJson(
            route('mp.trendyol-booster.companion.product-analysis'),
            $this->productAnalysisPayload(),
        )->assertOk();
        $trackedId = (int) $first->json('analysis.tracked_product_id');

        $updatedPayload = $this->productAnalysisPayload();
        $updatedPayload['page']['sale_price'] = 1249.90;
        $updatedPayload['metrics']['evaluation_count'] = 945;
        $updatedPayload['metrics']['review_count'] = 627;
        $updatedPayload['metrics']['favorite_count'] = 59725;

        $this->postJson(
            route('mp.trendyol-booster.companion.product-analysis'),
            $updatedPayload,
        )
            ->assertOk()
            ->assertJsonPath('analysis.previous.evaluation_count', 940)
            ->assertJsonPath('analysis.current.evaluation_count', 945)
            ->assertJsonPath('analysis.current.favorite_count', 59725);

        Livewire::test(TrendyolBooster::class)
            ->set('selectedAnalysisProductId', $trackedId)
            ->assertSeeHtml('data-testid="booster-product-analysis"')
            ->assertSee('Ürün değerlendirme sayısı')
            ->assertSee('Son 24 saatte görüntüleme')
            ->assertSee('Trendyol sosyal kanıt servisi bu ürün için görüntüleme sayısı yayınlamıyor.')
            ->assertSee('Ölçüm geçmişi bekleniyor')
            ->assertSee('Stok düşüşü / ölçüm süresi × 24')
            ->assertSee('Tahmini günlük satış × güncel fiyat')
            ->assertSee('Yeniden eskiye son 10 yorum')
            ->assertSee('Çok beğendimmm');

        Livewire::test(TrendyolBooster::class)
            ->call('loadTrackedProduct', $trackedId)
            ->assertSet('selectedAnalysisProductId', $trackedId)
            ->assertSee('İncele')
            ->assertSee('Ölçüm geçmişi bekleniyor');
    }

    public function test_manual_analysis_refresh_reads_live_metrics_and_persists_comparison(): void
    {
        [$user] = $this->createBoosterGraph();
        $this->actingAs($user);

        $first = $this->postJson(
            route('mp.trendyol-booster.companion.product-analysis'),
            $this->productAnalysisPayload(),
        )->assertOk();
        $tracked = TrendyolBoosterProduct::query()->findOrFail((int) $first->json('analysis.tracked_product_id'));

        Http::fake([
            'https://apigw.trendyol.com/*social-proof*' => Http::response([
                '286823139' => [
                    'socialProofs' => [
                        ['id' => 'basket-count', 'count' => '825'],
                        ['id' => 'favorite-count', 'count' => '59,8B'],
                        ['id' => 'page-view-count', 'count' => '1,4B'],
                    ],
                    'sentiments' => [],
                ],
            ]),
            'https://apigw.trendyol.com/*review-read*' => Http::response([
                'result' => [
                    'summary' => [
                        'totalRatingCount' => 945,
                        'totalCommentCount' => 627,
                        'averageRating' => 4.61,
                    ],
                    'reviews' => [[
                        'id' => 9001,
                        'userFullName' => 'Yeni Kullanıcı',
                        'rate' => 5,
                        'comment' => 'Anlık yenileme yorumu',
                        'lastModifiedAt' => '2026-06-28T01:00:00+03:00',
                        'seller' => ['name' => 'Zem Home'],
                    ]],
                ],
            ]),
            'https://www.trendyol.com/*' => Http::response($this->trendyolEnvoyProductHtml()),
        ]);

        $result = app(TrendyolBoosterScheduledAnalysisService::class)->refresh($tracked);

        $this->assertTrue($result['ok']);
        $this->assertSame(945, $result['snapshot']->evaluation_count);
        $this->assertSame(59825, $result['snapshot']->favorite_count);
        $this->assertSame(825, $result['snapshot']->basket_count);
        $this->assertSame(1400, $result['snapshot']->view_count_24h);
        $this->assertSame('manual_refresh', $result['snapshot']->analysis_source);
        $this->assertSame(940, $result['analysis']['previous']['evaluation_count']);
        $this->assertSame('success', $tracked->fresh()->last_analysis_refresh_status);
    }

    public function test_livewire_can_schedule_full_analysis_refresh(): void
    {
        [$user] = $this->createBoosterGraph();
        $this->actingAs($user);
        $tracked = app(TrendyolBoosterAnalysisService::class)->store($user->id, [
            'source_url' => 'https://www.trendyol.com/zolm/oto-analiz-p-702001',
            'title' => 'Otomatik Analiz Ürünü',
            'sale_price' => 1000,
        ]);

        Livewire::test(TrendyolBooster::class)
            ->call('toggleAnalysisAutoRefresh', $tracked->id)
            ->assertSee('Otomatik analiz açıldı')
            ->call('updateAnalysisRefreshInterval', $tracked->id, 360)
            ->assertSee('Otomatik analiz aralığı güncellendi');

        $tracked->refresh();
        $this->assertTrue($tracked->analysis_auto_refresh_enabled);
        $this->assertSame(360, $tracked->analysis_refresh_interval_minutes);
        $this->assertNotNull($tracked->next_analysis_refresh_at);
    }

    public function test_companion_stock_endpoint_normalizes_oversized_dom_seller_text(): void
    {
        [$user] = $this->createBoosterGraph();
        $this->actingAs($user);
        $longSellerName = str_repeat('Trendyol satıcı kartı metni ', 12);
        $longSellerId = str_repeat('121057', 20);
        $longShippingNote = str_repeat('Hızlı teslimat açıklaması ', 12);

        $this->postJson(route('mp.trendyol-booster.companion.stock-check'), [
            'source_url' => 'https://www.trendyol.com/zem/uzun-satici-metni-p-286823139',
            'page' => ['trendyol_product_id' => '286823139', 'title' => 'Lines Puf'],
            'total_stock' => 780,
            'sellers' => [[
                'seller_name' => $longSellerName,
                'seller_id' => $longSellerId,
                'stock' => 780,
                'sale_price' => 1289.90,
                'shipping_note' => $longShippingNote,
            ]],
        ])
            ->assertOk()
            ->assertJsonPath('stock.total_stock', 780)
            ->assertJsonPath('stock.seller_count', 1);

        $seller = DB::table('trendyol_booster_stock_sellers')
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($seller);
        $this->assertSame(180, mb_strlen($seller->seller_name));
        $this->assertSame(80, mb_strlen($seller->seller_id));
        $this->assertSame(180, mb_strlen($seller->shipping_note));
    }

    public function test_livewire_booster_discovers_competitor_keywords_and_seeds_commissions(): void
    {
        [$user] = $this->createBoosterGraph();
        $this->actingAs($user);
        app(TrendyolBoosterStoreWatchService::class)->scan($user->id, 'https://www.trendyol.com/magaza/ardelya-chair-m-993019', [
            'store_id' => '993019',
            'store_name' => 'Ardelya Chair',
            'items' => [
                ['trendyol_product_id' => '101', 'title' => 'Efza Armeni Sandalye', 'brand' => 'Efza', 'category_name' => 'Sandalye', 'review_count' => 120, 'favorite_count' => 800, 'rating' => 4.6, 'sale_price' => 5650],
                ['trendyol_product_id' => '102', 'title' => 'Efza Luma Yemek Masası Sandalye', 'brand' => 'Efza', 'category_name' => 'Sandalye', 'review_count' => 80, 'favorite_count' => 450, 'rating' => 4.4, 'sale_price' => 6100],
            ],
        ]);

        Livewire::test(TrendyolBooster::class)
            ->set('activeModule', 'trends')
            ->call('discoverTrendKeywords')
            ->assertSee('rakip üründen')
            ->assertSee('sandalye')
            ->assertSee('Trend puanı')
            ->assertDontSee('Örnek seti yükle')
            ->set('activeModule', 'commissions')
            ->call('seedCommissionRates')
            ->assertSee('komisyon oran');

        $this->assertGreaterThan(0, TrendyolBoosterTrendKeyword::query()->where('user_id', $user->id)->count());
        $this->assertDatabaseHas('trendyol_booster_trend_keywords', [
            'user_id' => $user->id,
            'keyword' => 'sandalye',
            'product_count' => 2,
            'source' => 'Rakip mağaza başlıkları',
        ]);
        $this->assertGreaterThan(0, TrendyolBoosterCommissionRate::query()->where('user_id', $user->id)->count());
        $this->assertGreaterThanOrEqual(2, TrendyolBoosterActivityLog::query()->where('user_id', $user->id)->count());
    }

    public function test_trend_keyword_discovery_updates_direction_from_competitor_signals(): void
    {
        [$user] = $this->createBoosterGraph();
        $storeService = app(TrendyolBoosterStoreWatchService::class);
        $trendService = app(TrendyolBoosterTrendKeywordService::class);
        $url = 'https://www.trendyol.com/magaza/ardelya-chair-m-993019';

        $storeService->scan($user->id, $url, [
            'store_id' => '993019',
            'store_name' => 'Ardelya Chair',
            'items' => [
                ['trendyol_product_id' => '201', 'title' => 'Efza Armeni Sandalye', 'brand' => 'Efza', 'review_count' => 10, 'favorite_count' => 20, 'sale_price' => 5000],
            ],
        ]);
        $trendService->discoverFromCompetitors($user->id);

        $storeService->scan($user->id, $url, [
            'store_id' => '993019',
            'store_name' => 'Ardelya Chair',
            'items' => [
                ['trendyol_product_id' => '201', 'title' => 'Efza Armeni Sandalye', 'brand' => 'Efza', 'review_count' => 10, 'favorite_count' => 20, 'sale_price' => 5000],
                ['trendyol_product_id' => '202', 'title' => 'Efza Luma Sandalye', 'brand' => 'Efza', 'review_count' => 140, 'favorite_count' => 900, 'sale_price' => 6200],
            ],
        ]);
        $summary = $trendService->discoverFromCompetitors($user->id);
        $keyword = TrendyolBoosterTrendKeyword::query()
            ->where('user_id', $user->id)
            ->where('keyword', 'sandalye')
            ->firstOrFail();

        $this->assertSame(2, $summary['products']);
        $this->assertSame(2, $keyword->product_count);
        $this->assertSame('rising', $keyword->trend_direction);
        $this->assertGreaterThan($keyword->previous_signal_score, $keyword->signal_score);
        $this->assertSame(0, $keyword->search_volume_max);
        $this->assertSame(0.0, (float) $keyword->recommended_bid);
    }

    public function test_discovered_trend_keyword_can_move_to_product_rank_tracking(): void
    {
        [$user, $product, $listing] = $this->createBoosterGraph();
        $this->actingAs($user);
        $tracked = app(TrendyolBoosterAnalysisService::class)->store($user->id, [
            'source_url' => 'https://www.trendyol.com/zolm/booster-test-urunu-p-123456',
            'trendyol_product_id' => '123456',
            'mp_product_id' => $product->id,
            'channel_listing_id' => $listing->id,
            'title' => 'Booster Test Ürünü',
            'sale_price' => 1500,
        ]);
        $trendKeyword = TrendyolBoosterTrendKeyword::query()->create([
            'user_id' => $user->id,
            'category_name' => 'Mobilya',
            'keyword' => 'yemek masası',
            'keyword_hash' => hash('sha256', 'yemek masası'),
            'competition_level' => 'medium',
            'signal_score' => 62,
            'product_count' => 4,
            'store_count' => 1,
            'source' => 'Rakip mağaza başlıkları',
        ]);
        Http::fake([
            'https://www.trendyol.com/sr*' => Http::response(
                '<a href="/zolm/booster-test-urunu-p-123456">Booster Test Ürünü</a>',
                200,
            ),
        ]);

        Livewire::withQueryParams(['booster' => 'trends'])
            ->test(TrendyolBooster::class)
            ->set('trendTargetProductId', $tracked->id)
            ->call('trackTrendKeyword', $trendKeyword->id)
            ->assertSet('activeModule', 'keyword_tracking')
            ->assertSet('keywordTrackingCurrentProductId', $tracked->id)
            ->assertSet('messageType', 'success')
            ->assertSee('yemek masası');

        $this->assertDatabaseHas('trendyol_booster_keywords', [
            'user_id' => $user->id,
            'trendyol_booster_product_id' => $tracked->id,
            'keyword' => 'yemek masası',
            'observed_rank' => 1,
        ]);
    }

    public function test_livewire_booster_renders_mobile_ledgers_for_data_tables(): void
    {
        [$user] = $this->createBoosterGraph();
        $this->actingAs($user);

        Livewire::test(TrendyolBooster::class)
            ->set('activeModule', 'trends')
            ->assertSeeHtml('data-testid="booster-trend-mobile-ledger"')
            ->assertSeeHtml('data-testid="booster-trend-table"')
            ->set('activeModule', 'history')
            ->assertSeeHtml('data-testid="booster-history-mobile-ledger"')
            ->assertSeeHtml('data-testid="booster-history-table"')
            ->set('activeModule', 'commissions')
            ->assertSeeHtml('data-testid="booster-commission-mobile-ledger"')
            ->assertSeeHtml('data-testid="booster-commission-table"');
    }

    public function test_booster_intelligence_calculates_stock_velocity_without_false_rounded_favorite_delta(): void
    {
        [$user] = $this->createBoosterGraph();
        $service = app(TrendyolBoosterProductAnalysisService::class);
        $firstPayload = $this->productAnalysisPayload();
        $firstPayload['page']['total_stock'] = 100;
        $firstPayload['metrics']['favorite_count'] = 59669;
        $firstPayload['metrics']['favorite_precision'] = 'exact';

        $first = $service->store($user->id, $firstPayload, 'manual_refresh');
        $this->travel(1)->hour();

        $secondPayload = $this->productAnalysisPayload();
        $secondPayload['page']['total_stock'] = 94;
        $secondPayload['metrics']['favorite_count'] = 59600;
        $secondPayload['metrics']['favorite_precision'] = 'rounded';
        $second = $service->store($user->id, $secondPayload, 'manual_refresh');
        $snapshot = $second['snapshot']->fresh();

        $this->assertSame(-6.0, (float) data_get($snapshot->metrics_json, 'deltas.stock'));
        $this->assertSame(6, data_get($snapshot->metrics_json, 'stock_velocity_24h.observed_drop_units'));
        $this->assertSame(2, data_get($snapshot->metrics_json, 'stock_velocity_24h.sample_count'));
        $this->assertNull(data_get($snapshot->metrics_json, 'deltas.favorite'));
        $this->assertFalse((bool) data_get($snapshot->metrics_json, 'favorite_comparable'));
        $this->assertSame(144.0, (float) $snapshot->estimated_daily_sales);
        $this->assertSame('observed', data_get($snapshot->metrics_json, 'sales_estimate.status'));
        $this->assertLessThan(60, (int) data_get($snapshot->metrics_json, 'sales_estimate.confidence'));
        $this->assertGreaterThan(50, (int) $snapshot->confidence_score);
        $this->assertSame('active', $first['product']->tracking_status);
    }

    public function test_booster_intelligence_uses_a_rolling_24_hour_stock_window_for_sales_and_stock_end(): void
    {
        [$user] = $this->createBoosterGraph();
        $service = app(TrendyolBoosterProductAnalysisService::class);

        foreach ([100, 95, 92, 90] as $index => $stock) {
            $payload = $this->productAnalysisPayload();
            $payload['page']['trendyol_product_id'] = '880024001';
            $payload['source_url'] = 'https://www.trendyol.com/zolm/24-saatlik-hiz-p-880024001';
            $payload['page']['total_stock'] = $stock;
            $result = $service->store($user->id, $payload, 'scheduled_refresh');

            if ($index < 3) {
                $this->travel(8)->hours();
            }
        }

        $snapshot = $result['snapshot']->fresh();
        $this->assertSame(10, data_get($snapshot->metrics_json, 'stock_velocity_24h.observed_drop_units'));
        $this->assertSame(24.0, (float) data_get($snapshot->metrics_json, 'stock_velocity_24h.observed_hours'));
        $this->assertTrue((bool) data_get($snapshot->metrics_json, 'stock_velocity_24h.window_complete'));
        $this->assertSame(10.0, (float) $snapshot->estimated_daily_sales);
        $this->assertSame(9.0, (float) $snapshot->estimated_days_of_stock);
        $this->assertSame('stock_velocity_24h', data_get($snapshot->metrics_json, 'sales_method'));
        $this->assertSame('observed', data_get($snapshot->metrics_json, 'sales_estimate.status'));
        $this->assertLessThan(10.0, (float) data_get($snapshot->metrics_json, 'sales_estimate.low'));
        $this->assertGreaterThan(10.0, (float) data_get($snapshot->metrics_json, 'sales_estimate.high'));
        $this->assertGreaterThanOrEqual(75, (int) data_get($snapshot->metrics_json, 'sales_estimate.confidence'));
    }

    public function test_booster_intelligence_uses_rolling_engagement_as_a_low_confidence_proxy_when_stock_is_unavailable(): void
    {
        [$user] = $this->createBoosterGraph();
        $service = app(TrendyolBoosterProductAnalysisService::class);
        $firstPayload = $this->productAnalysisPayload();
        $firstPayload['source_url'] = 'https://www.trendyol.com/zolm/ilgi-proxy-p-880024004';
        $firstPayload['page']['trendyol_product_id'] = '880024004';
        $firstPayload['metrics']['evaluation_count'] = 100;
        $firstPayload['metrics']['review_count'] = 50;
        $firstPayload['metrics']['favorite_count'] = 1000;
        $firstPayload['metrics']['favorite_precision'] = 'exact';
        $service->store($user->id, $firstPayload, 'scheduled_refresh');

        $this->travel(12)->hours();

        $secondPayload = $firstPayload;
        $secondPayload['metrics']['evaluation_count'] = 102;
        $secondPayload['metrics']['review_count'] = 51;
        $result = $service->store($user->id, $secondPayload, 'scheduled_refresh');
        $snapshot = $result['snapshot']->fresh();

        $this->assertNull($snapshot->stock_quantity);
        $this->assertSame(2, data_get($snapshot->metrics_json, 'engagement_velocity_24h.sample_count'));
        $this->assertSame(2.0, (float) data_get($snapshot->metrics_json, 'engagement_velocity_24h.evaluation_delta'));
        $this->assertSame(1.0, (float) data_get($snapshot->metrics_json, 'engagement_velocity_24h.review_delta'));
        $this->assertSame('engagement_velocity_24h', data_get($snapshot->metrics_json, 'sales_method'));
        $this->assertSame('proxy', data_get($snapshot->metrics_json, 'sales_estimate.status'));
        $this->assertSame(20.0, (float) $snapshot->estimated_daily_sales);
        $this->assertSame(7.0, (float) data_get($snapshot->metrics_json, 'sales_estimate.low'));
        $this->assertSame(33.0, (float) data_get($snapshot->metrics_json, 'sales_estimate.high'));
        $this->assertLessThanOrEqual(60, (int) data_get($snapshot->metrics_json, 'sales_estimate.confidence'));
    }

    public function test_booster_radar_explains_zero_sales_and_missing_stock_without_saying_data_is_still_accumulating(): void
    {
        [$user] = $this->createBoosterGraph();
        $this->actingAs($user);

        $stableUrl = 'https://www.trendyol.com/zolm/sabit-stok-p-880024002';
        $stable = TrendyolBoosterProduct::query()->create([
            'user_id' => $user->id,
            'source_url' => $stableUrl,
            'source_url_hash' => hash('sha256', $stableUrl),
            'trendyol_product_id' => '880024002',
            'title' => 'Sabit stoklu ürün',
            'sale_price' => 1000,
            'tracking_status' => 'active',
            'watch_stock' => true,
            'estimated_daily_sales' => 0,
            'last_checked_at' => now(),
        ]);
        TrendyolBoosterSnapshot::query()->create([
            'trendyol_booster_product_id' => $stable->id,
            'user_id' => $user->id,
            'sale_price' => 1000,
            'stock_status' => 'in_stock',
            'stock_quantity' => 50,
            'estimated_daily_sales' => 0,
            'metrics_json' => [
                'sales_method' => 'stock_velocity_24h',
                'sales_estimate' => [
                    'daily' => 0,
                    'low' => 0,
                    'high' => 1.71,
                    'confidence' => 52,
                    'method' => 'stock_velocity_24h',
                    'status' => 'no_movement',
                ],
                'stock_velocity_24h' => [
                    'sample_count' => 15,
                    'observed_hours' => 14,
                    'observed_drop_units' => 0,
                    'coverage_percent' => 58,
                    'window_complete' => false,
                ],
            ],
            'checked_at' => now(),
        ]);

        $missingUrl = 'https://www.trendyol.com/zolm/stok-yayinlanmiyor-p-880024003';
        $missing = TrendyolBoosterProduct::query()->create([
            'user_id' => $user->id,
            'source_url' => $missingUrl,
            'source_url_hash' => hash('sha256', $missingUrl),
            'trendyol_product_id' => '880024003',
            'title' => 'Stok yayınlanmayan ürün',
            'sale_price' => 1200,
            'tracking_status' => 'active',
            'watch_stock' => true,
            'last_checked_at' => now(),
        ]);
        TrendyolBoosterSnapshot::query()->create([
            'trendyol_booster_product_id' => $missing->id,
            'user_id' => $user->id,
            'sale_price' => 1200,
            'stock_status' => 'unknown',
            'stock_quantity' => null,
            'metrics_json' => [
                'sales_method' => 'source_unavailable',
                'sales_estimate' => [
                    'daily' => null,
                    'low' => null,
                    'high' => null,
                    'confidence' => 15,
                    'method' => 'source_unavailable',
                    'status' => 'unavailable',
                ],
                'stock_velocity_24h' => [
                    'sample_count' => 0,
                    'observed_hours' => null,
                    'observed_drop_units' => null,
                    'window_complete' => false,
                ],
            ],
            'checked_at' => now(),
        ]);

        Livewire::withQueryParams(['booster' => 'tracking'])
            ->test(TrendyolBooster::class)
            ->assertSee('0 gözlenen satış')
            ->assertSee('Algılama üst sınırı ≤1,71/gün')
            ->assertSee('Tahmin güveni %52')
            ->assertSee('Tahmin üretilemedi')
            ->assertSee('Kaynak yok')
            ->assertSee('Trendyol sayısal stok yayınlamıyor')
            ->assertSee('Stok sinyali')
            ->assertSee('Veri bekliyor');
    }

    public function test_research_comparison_tracks_products_as_one_radar_group(): void
    {
        [$user] = $this->createBoosterGraph();
        $service = app(TrendyolBoosterResearchService::class);
        $payloads = [
            [
                'source_url' => 'https://www.trendyol.com/zolm/alpha-p-801001',
                'page' => ['trendyol_product_id' => '801001', 'title' => 'Alpha', 'brand' => 'ZOLM', 'category_name' => 'Puf', 'sale_price' => 900, 'total_stock' => 40],
                'metrics' => ['average_rating' => 4.8, 'evaluation_count' => 100, 'favorite_count' => 2000, 'favorite_precision' => 'exact', 'seller_score' => 9.2],
            ],
            [
                'source_url' => 'https://www.trendyol.com/zolm/beta-p-801002',
                'page' => ['trendyol_product_id' => '801002', 'title' => 'Beta', 'brand' => 'ZOLM', 'category_name' => 'Puf', 'sale_price' => 1100, 'total_stock' => 20],
                'metrics' => ['average_rating' => 4.5, 'evaluation_count' => 80, 'favorite_count' => 1200, 'favorite_precision' => 'exact', 'seller_score' => 8.7],
            ],
        ];

        $comparison = $service->comparePayloads($payloads);
        $this->assertCount(2, $comparison['products']);
        $this->assertSame('801001', data_get($comparison, 'products.0.page.trendyol_product_id'));
        $this->assertSame(900.0, (float) data_get($comparison, 'summary.minimum_price'));

        foreach ($comparison['products'] as $payload) {
            $service->track($user->id, $payload, 'comparison', $comparison['group_key']);
        }

        $tracked = TrendyolBoosterProduct::query()->where('user_id', $user->id)->whereIn('trendyol_product_id', ['801001', '801002'])->get();
        $this->assertCount(2, $tracked);
        $this->assertTrue($tracked->every(fn (TrendyolBoosterProduct $product): bool => $product->tracking_status === 'active'));
        $this->assertTrue($tracked->every(fn (TrendyolBoosterProduct $product): bool => $product->analysis_auto_refresh_enabled));
        $this->assertTrue($tracked->every(fn (TrendyolBoosterProduct $product): bool => $product->analysis_refresh_interval_minutes === 60));
        $this->assertTrue($tracked->every(fn (TrendyolBoosterProduct $product): bool => $product->watch_stock));
        $this->assertTrue($tracked->every(fn (TrendyolBoosterProduct $product): bool => $product->tracking_group_key === $comparison['group_key']));
    }

    public function test_livewire_exposes_product_stalk_modules(): void
    {
        [$user] = $this->createBoosterGraph();
        $this->actingAs($user);

        Livewire::test(TrendyolBooster::class)
            ->assertSeeHtml('data-testid="booster-module-search"')
            ->assertSeeHtml('data-testid="booster-module-tabs"')
            ->assertSee('Ürün Analizi')
            ->assertSee('Ürün Karşılaştırma')
            ->assertSee('Ürün Alım Kararı')
            ->assertSee('Pazar Karşılaştırması')
            ->assertSee('Booster Radar')
            ->assertSee('Stok Sorgulama')
            ->assertSee('Rakip Takibi')
            ->assertSee('Sat veya Satma (AI)')
            ->assertSee('Kâr-Zarar Hesaplama')
            ->assertSee('Brüt Kâr-Zarar Hesaplama')
            ->assertSee('Net Kâr Hesaplama')
            ->assertSee('Hedef Planlayıcı')
            ->assertSee('Komisyon Oranları')
            ->assertSee('Çok Satanlar')
            ->assertSee('Tedarikçi Bul')
            ->assertSee('Anahtar Kelime Takibi')
            ->assertSee('Anahtar Kelime Aratma')
            ->assertSee('Trend Kelimeler')
            ->assertSee('Favorilerim')
            ->assertSee('Fiyat Takibi')
            ->assertSee('Analiz Geçmişi')
            ->assertSee('Bildirimler')
            ->assertDontSee('Yakında')
            ->call('setActiveModule', 'tracking')
            ->assertSet('activeModule', 'tracking')
            ->assertSet('favoritesOnly', false)
            ->assertDispatched('booster-module-changed', module: 'tracking', item: 'tracking', group: 'product')
            ->assertSee('Takipteki ürünler')
            ->assertSee('Tahmini metrikler')
            ->call('openFavorites')
            ->assertSet('activeModule', 'tracking')
            ->assertSet('favoritesOnly', true)
            ->assertDispatched('booster-module-changed', module: 'tracking', item: 'favorites', group: 'tracking')
            ->assertSee('Favoriye aldığınız takip ürünlerini')
            ->call('setActiveModule', 'stock')
            ->assertSet('activeModule', 'stock')
            ->assertSet('favoritesOnly', false)
            ->assertDispatched('booster-module-changed', module: 'stock', item: 'stock', group: 'market')
            ->assertSee('Satıcı bazlı stok erime takibi')
            ->call('setActiveModule', 'bestseller')
            ->assertSet('activeModule', 'bestseller')
            ->assertDispatched('booster-module-changed', module: 'bestseller', item: 'bestseller', group: 'market')
            ->assertSee('Trendyol Çok Satanlar');
    }

    public function test_supplier_finder_renders_market_radar_workspace_and_responsive_ledger(): void
    {
        [$user] = $this->createBoosterGraph();
        $this->actingAs($user);
        $sourceUrl = 'https://www.trendyol.com/zem/lines-puf-teddy-kumas-kirik-beyaz-p-286823139';
        app(TrendyolBoosterSupplierResearchService::class)->capture($user->id, $sourceUrl, [
            'source_url' => $sourceUrl,
            'trendyol_product_id' => '286823139',
            'title' => 'Zem Lines Puf Teddy Kumaş Kırık Beyaz',
            'brand' => 'Zem',
            'sale_price' => 1289.90,
            'sellers' => [['seller_name' => 'Zem Home', 'seller_id' => '121057', 'stock' => 20, 'sale_price' => 1289.90]],
        ], [[
            'platform' => 'hepsiburada',
            'platform_label' => 'Hepsiburada',
            'seller_name' => 'Zem Home',
            'title' => 'Zem Lines Puf Teddy Kumaş Kırık Beyaz',
            'source_url' => 'https://www.hepsiburada.com/zem-lines-puf-p-HBCV0001',
            'sale_price' => 1289.90,
            'source_type' => 'google_shopping',
        ]]);

        Livewire::withQueryParams(['booster' => 'supplier_finder'])
            ->test(TrendyolBooster::class)
            ->assertSet('activeModule', 'supplier_finder')
            ->assertSeeHtml('data-testid="booster-supplier-finder"')
            ->assertSee('Booster Tedarikçi Radar')
            ->assertSee('Pazarı Araştır')
            ->assertSee('Trendyol’daki satıcılar')
            ->assertSee('Google Alışveriş ve diğer kanallar')
            ->assertSee('Zem Home')
            ->assertSee('Hepsiburada');
    }

    public function test_booster_sidebar_groups_expose_reference_tool_set_without_placeholders(): void
    {
        [$user] = $this->createBoosterGraph();
        $this->actingAs($user);

        $response = $this->get(route('mp.trendyol-booster', ['booster' => 'stock']));
        $response
            ->assertOk()
            ->assertSeeHtml('data-testid="trendyol-booster-sidebar-menu"')
            ->assertSeeHtml('data-testid="booster-group-product"')
            ->assertSeeHtml('data-testid="booster-group-calculation"')
            ->assertSeeHtml('data-testid="booster-group-market"')
            ->assertSeeHtml('data-testid="booster-group-tracking"')
            ->assertSee('Ürün Stalk')
            ->assertSee('Hesaplama')
            ->assertSee('Pazar Araçları')
            ->assertSee('Takip Araçları')
            ->assertSee('Stok Sorgulama')
            ->assertSee('Rakip Takibi')
            ->assertSee('Komisyon Oranları')
            ->assertSee('Bildirimler')
            ->assertSee('Sat veya Satma (AI)')
            ->assertSee('Tedarikçi Bul')
            ->assertSee('Anahtar Kelime Takibi')
            ->assertSee('Anahtar Kelime Aratma');

        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query("//nav/*[@data-testid='trendyol-booster-sidebar-menu']")->length);
        $this->assertSame(0, $xpath->query("//*[@data-testid='marketplace-sidebar-menu']//*[@data-testid='trendyol-booster-sidebar-menu']")->length);
        $boosterMenu = $xpath->query("//*[@data-testid='trendyol-booster-sidebar-menu']")->item(0);
        $this->assertStringNotContainsString('Yakında', $boosterMenu?->textContent ?? '');

        Livewire::withQueryParams(['booster' => 'stock'])
            ->test(TrendyolBooster::class)
            ->assertSet('activeModule', 'stock')
            ->assertSee('Satıcı bazlı stok erime takibi');

        Livewire::withQueryParams(['booster' => 'tracking', 'favorites' => 1])
            ->test(TrendyolBooster::class)
            ->assertSet('activeModule', 'tracking')
            ->assertSet('favoritesOnly', true)
            ->assertSee('Favorilerim');
    }

    public function test_companion_status_returns_radar_summary_for_tracked_product(): void
    {
        [$user] = $this->createBoosterGraph();
        $this->actingAs($user);
        $result = app(TrendyolBoosterProductAnalysisService::class)->store($user->id, $this->productAnalysisPayload(), 'browser_companion');
        $tracked = $result['product'];
        $tracked->forceFill([
            'tracking_status' => 'active',
            'tracking_started_at' => now(),
            'analysis_auto_refresh_enabled' => true,
            'interest_score' => 72,
            'risk_score' => 18,
        ])->save();

        $this->getJson(route('mp.trendyol-booster.companion.status', ['product_id' => '286823139']))
            ->assertOk()
            ->assertJsonPath('tracked', true)
            ->assertJsonPath('product.id', $tracked->id)
            ->assertJsonPath('product.interest_score', 72)
            ->assertJsonPath('product.risk_score', 18)
            ->assertJsonPath('product.favorite_count', 59680);
    }

    public function test_booster_route_is_feature_flag_protected(): void
    {
        [$user] = $this->createBoosterGraph();
        $this->actingAs($user);

        config()->set('marketplace.features.trendyol_booster_enabled', false);
        $this->get('/marketplace-trendyol-booster')->assertNotFound();

        config()->set('marketplace.features.trendyol_booster_enabled', true);
        $this->get('/marketplace-trendyol-booster')
            ->assertOk()
            ->assertSee('Trendyol Booster')
            ->assertSee('Ürün karar radarı');
    }

    /**
     * @return array{0: User, 1: MpProduct, 2: ChannelListing}
     */
    protected function createBoosterGraph(): array
    {
        $suffix = str_replace('-', '', (string) Str::uuid());
        $user = User::factory()->create([
            'email' => 'booster-'.Str::uuid().'@example.test',
            'role' => 'admin',
            'is_active' => true,
        ]);
        $entity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Booster Test Ltd.',
            'tax_number' => '8'.substr((string) preg_replace('/\D/', '', $suffix), 0, 10),
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);
        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $entity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'Booster Store',
            'store_code' => 'BOOST-'.$suffix,
            'seller_id' => 'BOOST-'.$suffix,
            'status' => 'active',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);
        $product = MpProduct::query()->create([
            'user_id' => $user->id,
            'barcode' => 'BOOST-'.$suffix,
            'stock_code' => 'BOOST-'.$suffix,
            'product_name' => 'Booster Test Ürünü',
            'brand' => 'ZOLM',
            'category_name' => 'Elektronik',
            'sale_price' => 1400,
            'cogs' => 600,
            'packaging_cost' => 40,
            'cargo_cost' => 70,
            'commission_rate' => 10,
            'vat_rate' => 20,
            'cost_vat_rate' => 20,
            'return_rate' => 4,
            'status' => 'active',
        ]);
        $listing = ChannelListing::query()->create([
            'store_id' => $store->id,
            'mp_product_id' => $product->id,
            'listing_id' => 'BOOST-LIST-'.$suffix,
            'listing_status' => 'active',
            'sale_price' => 1500,
            'list_price' => 1600,
            'commission_rate' => 12,
            'commission_source' => 'api',
            'currency' => 'TRY',
            'stock_quantity' => 10,
            'last_synced_at' => now(),
        ]);

        return [$user, $product, $listing];
    }

    protected function createBoosterNotification(User $user, string $type, string $title): AppNotification
    {
        return AppNotification::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'severity' => str_contains($type, 'stock') ? 'warning' : 'info',
            'event_key' => $type.':'.Str::uuid(),
            'title' => $title,
            'body' => 'ZOLM Booster test bildirimi',
            'action_url' => route('mp.trendyol-booster', ['booster' => 'history']),
            'triggered_at' => now(),
        ]);
    }

    protected function trendyolProductHtml(): string
    {
        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => 'ZOLM Booster Test Ürünü',
            'brand' => [
                '@type' => 'Brand',
                'name' => 'ZOLM',
            ],
            'category' => 'Ev & Yaşam',
            'image' => 'https://cdn.dsmcdn.com/zolm/test.jpg',
            'offers' => [
                '@type' => 'Offer',
                'price' => '1299.90',
                'priceCurrency' => 'TRY',
                'availability' => 'https://schema.org/InStock',
            ],
        ];

        return '<!doctype html>
            <html lang="tr">
                <head>
                    <title>Fallback Ürün | Trendyol</title>
                    <meta property="og:title" content="Meta Ürün | Trendyol">
                    <meta property="product:price:amount" content="1199.50">
                    <script type="application/ld+json">'.json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).'</script>
                </head>
                <body>Trendyol test ürünü</body>
            </html>';
    }

    protected function trendyolEnvoyProductHtml(): string
    {
        $state = [
            'product' => [
                'id' => 286823139,
                'name' => 'Lines Puf, Teddy Kumaş Kırık Beyaz',
                'inStock' => true,
                'brand' => ['id' => 827203, 'name' => 'Zem'],
                'category' => ['id' => 2111, 'name' => 'Puf & Bench'],
                'images' => ['https://cdn.dsmcdn.com/zem/lines-puf.jpg'],
                'ratingScore' => ['totalCount' => 945, 'commentCount' => 627, 'averageRating' => 4.61],
                'favoriteCount' => 59825,
                'merchantListing' => [
                    'merchant' => [
                        'id' => 121057,
                        'name' => 'Zem Home',
                        'sellerScore' => ['value' => 9.1],
                    ],
                    'winnerVariant' => [
                        'barcode' => '87874848484848484',
                        'inStock' => true,
                        'quantity' => 780,
                        'rushDeliveryDuration' => 24,
                        'price' => [
                            'currency' => 'TRY',
                            'sellingPrice' => ['value' => 1289.9, 'text' => '1.289,90 TL'],
                        ],
                    ],
                ],
                'variants' => [
                    ['barcode' => '87874848484848484', 'inStock' => true],
                ],
            ],
        ];

        return '<!doctype html><html lang="tr"><head><script>window["__envoy__SHARED_PROPS"]='
            .json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            .';</script></head><body>
                <section class="seller-marketplace-card">
                    <p>Satıcı: Zem Home</p>
                    <p>Satıcı Ünvanı: ZEM EV ÜRÜNLERİ LTD. ŞTİ.</p>
                    <p>Ticaret sicili: Kadıköy Vergi Dairesi</p>
                    <p>Vergi Kimlik Numarası: 1234567890</p>
                    <p>Kep Adresi: zem@hs01.kep.tr</p>
                    <p>İletişim: Satıcının Trendyol tarafından teyit edilmiş e-posta ve iletişim adresi kayıt altındadır.</p>
                    <p>Adres: İstanbul Test Mah. No:1</p>
                </section>
            </body></html>';
    }

    protected function trendyolSearchHtml(): string
    {
        return '<!doctype html>
            <html lang="tr">
                <body>
                    <a href="/marka/baska-urun-p-111111">1</a>
                    <a href="/zolm/booster-test-urunu-p-123456">2</a>
                    <a href="/marka/ucuncu-urun-p-222222">3</a>
                    <a href="/zolm/booster-test-urunu-p-123456">tekrar</a>
                </body>
            </html>';
    }

    protected function trendyolSearchHtmlWithProductFirst(): string
    {
        return '<!doctype html>
            <html lang="tr">
                <body>
                    <a href="/zolm/booster-test-urunu-p-123456">1</a>
                    <a href="/marka/baska-urun-p-111111">2</a>
                    <a href="/marka/ucuncu-urun-p-222222">3</a>
                </body>
            </html>';
    }

    /**
     * @return array<string, mixed>
     */
    protected function companionPayload(MpProduct $product, ChannelListing $listing): array
    {
        return [
            'source_url' => 'https://www.trendyol.com/zolm/booster-test-urunu-p-123456',
            'page' => [
                'trendyol_product_id' => '123456',
                'title' => 'ZOLM Booster Test Ürünü',
                'brand' => 'ZOLM',
                'category_name' => 'Ev & Yaşam',
                'sale_price' => 1500,
            ],
            'mp_product_id' => $product->id,
            'channel_listing_id' => $listing->id,
            'target_margin_percent' => 20,
            'watch_price' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function productAnalysisPayload(): array
    {
        return [
            'source_url' => 'https://www.trendyol.com/zem/lines-puf-teddy-kumas-kirik-beyaz-p-286823139?boutiqueId=61&merchantId=121057',
            'page' => [
                'trendyol_product_id' => '286823139',
                'title' => 'Lines Puf, Teddy Kumaş Kırık Beyaz',
                'brand' => 'Zem',
                'category_name' => 'Puf & Bench',
                'sale_price' => 1289.90,
                'currency' => 'TRY',
                'image_url' => 'https://cdn.dsmcdn.com/zem/lines-puf.jpg',
                'availability' => 'InStock',
                'stock_status' => 'in_stock',
            ],
            'metrics' => [
                'evaluation_count' => 940,
                'review_count' => 622,
                'average_rating' => 4.59,
                'favorite_count' => 59680,
                'basket_count' => null,
                'view_count_24h' => null,
            ],
            'recent_reviews' => [
                [
                    'review_id' => '501',
                    'user_name' => 'Ş** H**',
                    'rate' => 5,
                    'comment' => 'Çok beğendimmm',
                    'seller_name' => 'Zem Home',
                    'reviewed_at' => '2026-01-09T12:37:00+03:00',
                ],
                [
                    'review_id' => '502',
                    'user_name' => '**** ****',
                    'rate' => 5,
                    'comment' => 'Çok iyi ben beğendim, görüntüsü güzel.',
                    'seller_name' => 'Zem Home',
                    'reviewed_at' => '2025-11-13T18:37:00+03:00',
                ],
            ],
        ];
    }
}
