<?php

namespace Tests\Feature;

use App\Livewire\MarketplaceQuestions;
use App\Models\ChannelListing;
use App\Models\ChannelProduct;
use App\Models\IntegrationConnection;
use App\Models\IntegrationSyncRun;
use App\Models\LegalEntity;
use App\Models\MarketplaceQuestion;
use App\Models\MarketplaceQuestionRule;
use App\Models\MarketplaceQuestionTemplate;
use App\Models\MarketplaceStore;
use App\Models\User;
use App\Services\Marketplace\Connectors\CiceksepetiConnector;
use App\Services\Marketplace\Connectors\HepsiburadaConnector;
use App\Services\Marketplace\Connectors\KoctasConnector;
use App\Services\Marketplace\Connectors\N11Connector;
use App\Services\Marketplace\Connectors\PazaramaConnector;
use App\Services\Marketplace\Connectors\TrendyolConnector;
use App\Services\Marketplace\Connectors\WooCommerceConnector;
use App\Services\Marketplace\MarketplaceQuestionRuleEngine;
use App\Services\Marketplace\MarketplaceQuestionSyncService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class MarketplaceQuestionsTest extends TestCase
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

    public function test_question_sync_upserts_marketplace_questions(): void
    {
        [$user, $store] = $this->createStoreGraph('trendyol');
        [$product, $listing] = $this->createListingGraph($store);

        $result = app(MarketplaceQuestionSyncService::class)->sync($store, [[
            'id' => 'Q-1001',
            'question' => 'Bu ürünün ölçüsü nedir?',
            'productName' => 'Tom Puf',
            'sku' => $product->stock_code,
            'barcode' => $product->barcode,
            'listingId' => $listing->listing_id,
            'customerName' => 'Test Müşteri',
            'createdDate' => now()->subHour()->toIso8601String(),
        ]]);

        $this->assertSame(1, $result['created']);
        $this->assertDatabaseHas('marketplace_questions', [
            'store_id' => $store->id,
            'external_question_id' => 'Q-1001',
            'channel_product_id' => $product->id,
            'channel_listing_id' => $listing->id,
            'question_text' => 'Bu ürünün ölçüsü nedir?',
        ]);

        app(MarketplaceQuestionSyncService::class)->sync($store, [[
            'id' => 'Q-1001',
            'question' => 'Bu ürünün rengi nedir?',
            'sku' => $product->stock_code,
        ]]);

        $this->assertDatabaseHas('marketplace_questions', [
            'store_id' => $store->id,
            'external_question_id' => 'Q-1001',
            'question_text' => 'Bu ürünün rengi nedir?',
        ]);
        $this->assertSame(1, MarketplaceQuestion::query()
            ->where('store_id', $store->id)
            ->where('external_question_id', 'Q-1001')
            ->count());
    }

    public function test_livewire_screen_manages_templates_and_drafts_answer(): void
    {
        [$user, $store] = $this->createStoreGraph('n11');
        $question = MarketplaceQuestion::query()->create([
            'store_id' => $store->id,
            'external_question_id' => 'N11-Q-1',
            'status' => 'open',
            'product_name' => 'Tom Puf',
            'product_sku' => 'TOM-1',
            'question_text' => 'Kargo ne zaman çıkar?',
            'asked_at' => now(),
        ]);

        $this->actingAs($user);

        Livewire::test(MarketplaceQuestions::class)
            ->assertSee('Müşteri Soruları')
            ->call('createTemplate')
            ->set('templateTitle', 'Kargo Bilgisi')
            ->set('templateBody', 'Merhaba, {urun} için kargo bilgisi kontrol edilip paylaşılacaktır.')
            ->call('saveTemplate')
            ->assertSet('toastTone', 'success')
            ->call('selectQuestion', $question->id)
            ->call('useTemplate', MarketplaceQuestionTemplate::query()
                ->where('user_id', $user->id)
                ->where('title', 'Kargo Bilgisi')
                ->value('id'))
            ->assertSet('answerText', 'Merhaba, Tom Puf için kargo bilgisi kontrol edilip paylaşılacaktır.')
            ->call('saveDraft')
            ->assertSet('toastTone', 'success');

        $this->assertDatabaseHas('marketplace_questions', [
            'id' => $question->id,
            'status' => 'draft',
            'answer_text' => 'Merhaba, Tom Puf için kargo bilgisi kontrol edilip paylaşılacaktır.',
        ]);
    }

    public function test_livewire_screen_opens_question_from_notification_deep_link(): void
    {
        [$user, $store] = $this->createStoreGraph('trendyol');
        $question = MarketplaceQuestion::query()->create([
            'store_id' => $store->id,
            'external_question_id' => 'TY-Q-501',
            'status' => 'answered',
            'product_name' => 'Liva Puf',
            'product_sku' => 'LIVA-1',
            'question_text' => 'Kumaş türü nedir?',
            'answer_text' => 'Welsoft kumaştır.',
            'asked_at' => now()->subDay(),
        ]);

        $this->actingAs($user);

        Livewire::withQueryParams(['question' => (string) $question->id])
            ->test(MarketplaceQuestions::class)
            ->assertSet('selectedQuestionId', $question->id)
            ->assertSet('statusFilter', '')
            ->assertSet('storeFilter', (string) $store->id)
            ->assertSee('Kumaş türü nedir?')
            ->assertSee('Welsoft kumaştır.');
    }

    public function test_sent_answer_clears_selection_when_pending_filter_has_no_more_questions(): void
    {
        [$user, $store] = $this->createStoreGraph('trendyol');
        IntegrationConnection::query()->create([
            'store_id' => $store->id,
            'provider' => 'trendyol',
            'auth_type' => 'basic',
            'credentials_encrypted' => [
                'api_key' => 'key',
                'api_secret' => 'secret',
            ],
            'api_base_url' => 'https://apigw.trendyol.com/',
            'status' => 'configured',
        ]);
        $question = MarketplaceQuestion::query()->create([
            'store_id' => $store->id,
            'external_question_id' => 'TY-Q-777',
            'status' => 'open',
            'product_name' => 'Lines Puf',
            'question_text' => 'Yüksekliği kaç cm?',
            'asked_at' => now(),
        ]);

        Http::fake([
            '*questions/TY-Q-777/answers' => Http::response(['id' => 888], 200),
        ]);

        $this->actingAs($user);

        Livewire::test(MarketplaceQuestions::class)
            ->call('selectQuestion', $question->id)
            ->set('answerText', 'Merhaba, ürün yüksekliği 38 cm olarak paylaşılmaktadır.')
            ->call('sendAnswer')
            ->assertSet('toastTone', 'success')
            ->assertSet('selectedQuestionId', null)
            ->assertSet('question', '')
            ->assertSet('answerText', '');

        $this->assertDatabaseHas('marketplace_questions', [
            'id' => $question->id,
            'status' => 'answered',
            'answer_text' => 'Merhaba, ürün yüksekliği 38 cm olarak paylaşılmaktadır.',
        ]);
    }

    public function test_sync_notification_treats_debounced_supported_store_as_handled(): void
    {
        [$user, $trendyolStore] = $this->createStoreGraph('trendyol');
        $suffix = (string) random_int(100000, 999999);
        MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $trendyolStore->legal_entity_id,
            'marketplace' => 'amazon',
            'store_name' => 'AMAZON TEST',
            'store_code' => 'AMZ-Q-' . $suffix,
            'seller_id' => 'AMZ-SELLER-' . $suffix,
            'status' => 'active',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);
        IntegrationSyncRun::query()->create([
            'store_id' => $trendyolStore->id,
            'sync_type' => 'questions',
            'trigger_type' => 'manual',
            'status' => 'queued',
            'notes_json' => ['options' => []],
        ]);

        $this->actingAs($user);

        $component = Livewire::test(MarketplaceQuestions::class)
            ->call('syncQuestions')
            ->assertSet('toastTone', 'info');

        $message = (string) $component->get('toastMessage');

        $this->assertStringNotContainsString('uygun connector bulunamadı', $message);
        $this->assertStringContainsString('zaten sırada', $message);
        $this->assertStringContainsString('Henüz desteklenmeyenler', $message);
    }

    public function test_sync_questions_respects_active_store_filters(): void
    {
        [$user, $ciceksepetiStore] = $this->createStoreGraph('ciceksepeti');
        MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $ciceksepetiStore->legal_entity_id,
            'marketplace' => 'trendyol',
            'store_name' => 'ZEM SORULAR TRENDYOL',
            'store_code' => 'Q-TREND-' . random_int(100000, 999999),
            'seller_id' => 'SELLER-TREND-' . random_int(100000, 999999),
            'status' => 'active',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);
        IntegrationConnection::query()->create([
            'store_id' => $ciceksepetiStore->id,
            'provider' => 'ciceksepeti',
            'auth_type' => 'api_key',
            'credentials_encrypted' => [
                'api_key' => 'cs-key',
                'extra_user' => 'ZOLM',
            ],
            'api_base_url' => 'https://ciceksepeti.test/api/v1/',
            'status' => 'configured',
        ]);
        Http::fake([
            'https://ciceksepeti.test/api/v1/sellerquestions?*' => Http::response([
                'items' => [],
                'hasNextPage' => false,
            ], 200),
        ]);

        $this->actingAs($user);
        config()->set('marketplace.manual_sync.inline_on_local', false);

        $component = Livewire::test(MarketplaceQuestions::class)
            ->set('marketplaceFilter', 'ciceksepeti')
            ->set('storeFilter', (string) $ciceksepetiStore->id)
            ->call('syncQuestions');

        $this->assertStringContainsString('1 mağaza', (string) $component->get('toastMessage'));
        $this->assertSame(1, IntegrationSyncRun::query()->where('store_id', $ciceksepetiStore->id)->count());
        $this->assertDatabaseHas('integration_sync_runs', [
            'store_id' => $ciceksepetiStore->id,
            'sync_type' => 'questions',
            'trigger_type' => 'manual',
        ]);
    }

    public function test_rule_engine_prepares_draft_when_question_matches_keywords(): void
    {
        [$user, $store] = $this->createStoreGraph('pazarama');
        $question = MarketplaceQuestion::query()->create([
            'store_id' => $store->id,
            'external_question_id' => 'PZ-Q-1',
            'status' => 'open',
            'product_name' => 'Tom Puf',
            'question_text' => 'Ürünün ölçü bilgisini paylaşır mısınız?',
            'asked_at' => now(),
        ]);
        $template = MarketplaceQuestionTemplate::query()->create([
            'user_id' => $user->id,
            'title' => 'Ölçü Yanıtı',
            'body' => 'Merhaba, ürün ölçüsü ürün açıklamasında yer almaktadır. İsterseniz tekrar kontrol edip net bilgi paylaşabiliriz.',
            'is_active' => true,
        ]);
        $rule = MarketplaceQuestionRule::query()->create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'template_id' => $template->id,
            'name' => 'Ölçü sorusu',
            'match_type' => 'contains',
            'keywords_json' => ['ölçü', 'ebat'],
            'action_mode' => 'draft',
            'requires_approval' => true,
            'priority' => 10,
            'is_active' => true,
        ]);

        $matched = app(MarketplaceQuestionRuleEngine::class)->apply($question, $user);

        $this->assertTrue($matched->is($rule));
        $question->refresh();
        $this->assertSame('draft', $question->status);
        $this->assertSame($rule->id, $question->matched_rule_id);
        $this->assertDatabaseHas('marketplace_question_answer_logs', [
            'marketplace_question_id' => $question->id,
            'rule_id' => $rule->id,
            'status' => 'draft',
            'source' => 'rule',
        ]);
    }

    public function test_trendyol_connector_normalizes_and_answers_customer_questions(): void
    {
        [$user, $store] = $this->createStoreGraph('trendyol');
        IntegrationConnection::query()->create([
            'store_id' => $store->id,
            'provider' => 'trendyol',
            'auth_type' => 'basic',
            'credentials_encrypted' => [
                'api_key' => 'key',
                'api_secret' => 'secret',
            ],
            'api_base_url' => 'https://apigw.trendyol.com/',
            'status' => 'configured',
        ]);

        Http::fake([
            '*questions/filter*' => Http::response([
                'content' => [[
                    'id' => 123,
                    'status' => 'WAITING_FOR_ANSWER',
                    'text' => 'Kumaş türü nedir?',
                    'userName' => 'Ayşe Yılmaz',
                    'productName' => 'Tom Puf',
                    'barcode' => '8690000000001',
                    'merchantSku' => 'TOM-SKU-1',
                    'creationDate' => now()->valueOf(),
                ]],
                'totalPages' => 1,
            ]),
            '*questions/123/answers' => Http::response(['id' => 456], 200),
        ]);

        $connector = app(TrendyolConnector::class);
        $response = $connector->pullCustomerQuestions($store->fresh('connection'), [
            'start_date' => now()->subDays(20)->toIso8601String(),
            'end_date' => now()->toIso8601String(),
            'status' => 'open',
        ]);

        $this->assertSame('123', $response['items'][0]['external_question_id']);
        $this->assertSame('Kumaş türü nedir?', $response['items'][0]['question_text']);
        $this->assertSame('Ayşe Yılmaz', $response['items'][0]['customer_name']);
        $this->assertSame(2, $response['meta']['pages_processed']);
        Http::assertSentCount(2);
        Http::assertSent(function ($request) {
            if (!str_contains($request->url(), '/questions/filter?')) {
                return false;
            }

            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return ($query['status'] ?? null) === 'WAITING_FOR_ANSWER';
        });

        $question = MarketplaceQuestion::query()->create([
            'store_id' => $store->id,
            'external_question_id' => '123',
            'status' => 'open',
            'product_name' => 'Tom Puf',
            'question_text' => 'Kumaş türü nedir?',
            'asked_at' => now(),
        ]);

        $answer = $connector->answerCustomerQuestion($question->fresh('store.connection'), 'Merhaba, ürün welsoft kumaştan üretilmektedir.');

        $this->assertSame(456, $answer['external_answer_id']);
    }

    public function test_hepsiburada_connector_normalizes_and_answers_customer_questions(): void
    {
        [$user, $store] = $this->createStoreGraph('hepsiburada');
        IntegrationConnection::query()->create([
            'store_id' => $store->id,
            'provider' => 'hepsiburada',
            'auth_type' => 'basic',
            'credentials_encrypted' => [
                'api_key' => 'service-key',
            ],
            'status' => 'configured',
        ]);

        Http::fake([
            '*api-asktoseller-merchant-sit.hepsiburada.com*/issues?*' => Http::response([
                'items' => [
                    [
                        'issueNumber' => 'HB-1001',
                        'question' => 'Kurulum gerekir mi?',
                        'productName' => 'Tom Puf',
                        'merchantSku' => 'TOM-SKU-1',
                        'createdAt' => now()->toIso8601String(),
                    ],
                    [
                        'issueNumber' => 'HB-1002',
                        'question' => 'Siparişim ne zaman teslim edilir?',
                        'orderNumber' => 'HB-ORDER-1',
                        'createdAt' => now()->toIso8601String(),
                    ],
                ],
                'totalCount' => 2,
            ]),
            '*api-asktoseller-merchant-sit.hepsiburada.com*/issues/HB-1001/answer' => Http::response(['id' => 'HB-A-1'], 200),
        ]);

        $connector = app(HepsiburadaConnector::class);
        $response = $connector->pullCustomerQuestions($store->fresh('connection'));

        $this->assertSame('HB-1001', $response['items'][0]['external_question_id']);
        $this->assertSame('Kurulum gerekir mi?', $response['items'][0]['question_text']);
        $this->assertSame('order', $response['items'][1]['question_type']);
        $this->assertSame('HB-ORDER-1', $response['items'][1]['order_number']);

        $question = MarketplaceQuestion::query()->create([
            'store_id' => $store->id,
            'external_question_id' => 'HB-1001',
            'status' => 'open',
            'product_name' => 'Tom Puf',
            'question_text' => 'Kurulum gerekir mi?',
            'asked_at' => now(),
        ]);

        $answer = $connector->answerCustomerQuestion($question, 'Merhaba, ürün kurulum gerektirmez.');

        $this->assertSame('HB-A-1', $answer['external_answer_id']);
    }

    public function test_n11_connector_uses_product_question_soap_service(): void
    {
        [$user, $store] = $this->createStoreGraph('n11');
        IntegrationConnection::query()->create([
            'store_id' => $store->id,
            'provider' => 'n11',
            'auth_type' => 'api_key',
            'credentials_encrypted' => [
                'api_key' => 'app-key',
                'api_secret' => 'app-secret',
            ],
            'status' => 'configured',
        ]);

        Http::fake([
            '*productService*' => Http::sequence()
                ->push('<?xml version="1.0" encoding="UTF-8"?><soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"><soapenv:Body><GetProductQuestionListResponse><productQuestions><productQuestion><productQuestionId>N11-Q-1</productQuestionId><question>Kumaş silinebilir mi?</question><status>OPEN</status><product><title>Tom Puf</title><stockCode>TOM-SKU-1</stockCode></product><questionDate>24/04/2026</questionDate></productQuestion></productQuestions><pagingData><pageCount>1</pageCount></pagingData></GetProductQuestionListResponse></soapenv:Body></soapenv:Envelope>', 200)
                ->push('<?xml version="1.0" encoding="UTF-8"?><soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"><soapenv:Body><SaveProductAnswerResponse><result><status>success</status></result><productQuestionId>N11-Q-1</productQuestionId></SaveProductAnswerResponse></soapenv:Body></soapenv:Envelope>', 200),
        ]);

        $connector = app(N11Connector::class);
        $response = $connector->pullCustomerQuestions($store->fresh('connection'), ['status' => 'open']);

        $this->assertSame('N11-Q-1', $response['items'][0]['external_question_id']);
        $this->assertSame('Kumaş silinebilir mi?', $response['items'][0]['question_text']);

        Http::assertSent(fn ($request) => str_contains($request->body(), '<status>OPEN</status>')
            && ! str_contains($request->body(), '<status>open</status>'));

        $question = MarketplaceQuestion::query()->create([
            'store_id' => $store->id,
            'external_question_id' => 'N11-Q-1',
            'status' => 'open',
            'product_name' => 'Tom Puf',
            'question_text' => 'Kumaş silinebilir mi?',
            'asked_at' => now(),
        ]);

        $answer = $connector->answerCustomerQuestion($question, 'Merhaba, nemli bezle silinebilir.');

        $this->assertSame('N11-Q-1', $answer['external_answer_id']);
    }

    public function test_configurable_question_connectors_pull_and_answer(): void
    {
        [$user, $pazaramaStore] = $this->createStoreGraph('pazarama');
        [$user, $ciceksepetiStore] = $this->createStoreGraph('ciceksepeti');
        [$user, $koctasStore] = $this->createStoreGraph('koctas');

        IntegrationConnection::query()->create([
            'store_id' => $pazaramaStore->id,
            'provider' => 'pazarama',
            'auth_type' => 'oauth',
            'credentials_encrypted' => ['api_key' => 'client', 'api_secret' => 'secret'],
            'api_base_url' => 'https://pazarama.test/',
            'status' => 'configured',
        ]);
        IntegrationConnection::query()->create([
            'store_id' => $ciceksepetiStore->id,
            'provider' => 'ciceksepeti',
            'auth_type' => 'api_key',
            'credentials_encrypted' => ['api_key' => 'cs-key'],
            'api_base_url' => 'https://ciceksepeti.test/api/v1/',
            'status' => 'configured',
        ]);
        IntegrationConnection::query()->create([
            'store_id' => $koctasStore->id,
            'provider' => 'koctas',
            'auth_type' => 'api_key',
            'credentials_encrypted' => ['api_key' => 'mirakl-key'],
            'api_base_url' => 'https://koctas.test/',
            'status' => 'configured',
        ]);

        Http::fake([
            '*connect/token' => Http::response(['data' => ['accessToken' => 'token']], 200),
            'https://pazarama.test/question/getQuestionsForApi' => Http::response([
                'data' => [[
                    'questionId' => 'PZ-Q-1',
                    'questionText' => 'Renk beyaz mı?',
                    'productName' => 'Tom Puf',
                    'stockCode' => 'TOM-SKU-1',
                ]],
                'totalCount' => 1,
            ], 200),
            'https://pazarama.test/question/answerQuestionForApi' => Http::response(['success' => true, 'data' => ['id' => 'PZ-A-1']], 200),
            'https://ciceksepeti.test/api/v1/sellerquestions?*' => Http::response([
                'items' => [[
                    'id' => 'CS-Q-1',
                    'question' => 'Bugün kargo olur mu?',
                    'product' => [
                        'code' => 'TOM-SKU-1',
                        'name' => 'Tom Puf',
                    ],
                    'questionDate' => '2026-04-27T01:43:00+03:00',
                    'answered' => false,
                ]],
                'hasNextPage' => false,
            ], 200),
            'https://ciceksepeti.test/api/v1/sellerquestions/CS-Q-1' => Http::response(['isSuccess' => true, 'id' => 'CS-A-1'], 200),
            'https://koctas.test/api/inbox/threads*' => Http::response([
                'threads' => [
                    [
                        'id' => 'KOC-Q-1',
                        'topic' => 'Garanti süresi nedir?',
                        'state' => 'OPEN',
                        'entity' => ['type' => 'OFFER', 'product' => ['title' => 'Tom Puf']],
                    ],
                    [
                        'id' => 'KOC-Q-2',
                        'topic' => 'Teslimat gecikti',
                        'state' => 'OPEN',
                        'entity' => ['type' => 'ORDER', 'id' => 'KOC-ORDER-1'],
                    ],
                ],
                'total_count' => 2,
            ], 200),
            'https://koctas.test/api/inbox/threads/KOC-Q-1/messages*' => Http::response(['id' => 'KOC-A-1'], 200),
        ]);

        $pazarama = app(PazaramaConnector::class)->pullCustomerQuestions($pazaramaStore->fresh('connection'));
        $ciceksepeti = app(CiceksepetiConnector::class)->pullCustomerQuestions($ciceksepetiStore->fresh('connection'));
        $koctas = app(KoctasConnector::class)->pullCustomerQuestions($koctasStore->fresh('connection'));

        $this->assertSame('PZ-Q-1', $pazarama['items'][0]['external_question_id']);
        $this->assertSame('CS-Q-1', $ciceksepeti['items'][0]['external_question_id']);
        $this->assertSame('KOC-Q-1', $koctas['items'][0]['external_question_id']);
        $this->assertSame('order', $koctas['items'][1]['question_type']);
        $this->assertSame('KOC-ORDER-1', $koctas['items'][1]['order_number']);

        foreach ([[$pazaramaStore, 'PZ-Q-1', PazaramaConnector::class], [$ciceksepetiStore, 'CS-Q-1', CiceksepetiConnector::class], [$koctasStore, 'KOC-Q-1', KoctasConnector::class]] as [$store, $externalId, $connector]) {
            $question = MarketplaceQuestion::query()->create([
                'store_id' => $store->id,
                'external_question_id' => $externalId,
                'status' => 'open',
                'product_name' => 'Tom Puf',
                'question_text' => 'Soru?',
                'asked_at' => now(),
            ]);

            $answer = app($connector)->answerCustomerQuestion($question, 'Merhaba, kontrol edip yardımcı olalım.');

            $this->assertNotEmpty($answer['external_answer_id']);
        }
    }

    public function test_woocommerce_connector_imports_reviews_and_replies_with_wordpress_comment(): void
    {
        [$user, $store] = $this->createStoreGraph('woocommerce');
        IntegrationConnection::query()->create([
            'store_id' => $store->id,
            'provider' => 'woocommerce',
            'auth_type' => 'basic',
            'credentials_encrypted' => [
                'api_key' => 'ck_test',
                'api_secret' => 'cs_test',
                'store_url' => 'https://woo.test',
                'extra_user' => 'admin',
                'extra_password' => 'app-password',
            ],
            'status' => 'configured',
        ]);

        Http::fake([
            'https://woo.test/wp-json/wc/v3/products/reviews?*' => Http::response([[
                'id' => 501,
                'product_id' => 9001,
                'reviewer' => 'Müşteri',
                'reviewer_email' => 'musteri@example.com',
                'review' => 'Ayakları sağlam mı?',
                'status' => 'approved',
                'date_created_gmt' => now()->toIso8601String(),
            ]], 200, ['X-WP-TotalPages' => '1']),
            'https://woo.test/wp-json/wp/v2/comments' => Http::response(['id' => 777], 201),
        ]);

        $connector = app(WooCommerceConnector::class);
        $response = $connector->pullCustomerQuestions($store->fresh('connection'));

        $this->assertSame('501', $response['items'][0]['external_question_id']);
        $this->assertSame('Ayakları sağlam mı?', $response['items'][0]['question_text']);

        $question = MarketplaceQuestion::query()->create([
            'store_id' => $store->id,
            'external_question_id' => '501',
            'status' => 'open',
            'product_name' => 'Tom Puf',
            'question_text' => 'Ayakları sağlam mı?',
            'raw_payload' => ['product_id' => 9001],
            'asked_at' => now(),
        ]);

        $answer = $connector->answerCustomerQuestion($question, 'Merhaba, ayak bağlantıları sağlamdır.');

        $this->assertSame('777', $answer['external_answer_id']);
    }

    /**
     * @return array{0: User, 1: MarketplaceStore}
     */
    protected function createStoreGraph(string $marketplace): array
    {
        $suffix = (string) random_int(100000, 999999);
        $user = User::factory()->create([
            'email' => "questions-{$suffix}@example.test",
            'role' => 'admin',
            'is_active' => true,
        ]);
        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Questions Ltd.',
            'tax_number' => '7' . $suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);
        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => $marketplace,
            'store_name' => 'ZEM SORULAR ' . strtoupper($marketplace),
            'store_code' => 'Q-' . $suffix,
            'seller_id' => 'SELLER-' . $suffix,
            'status' => 'active',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        return [$user, $store];
    }

    /**
     * @return array{0: ChannelProduct, 1: ChannelListing}
     */
    protected function createListingGraph(MarketplaceStore $store): array
    {
        $product = ChannelProduct::query()->create([
            'store_id' => $store->id,
            'external_product_id' => 'EXT-TOM-1',
            'stock_code' => 'TOM-SKU-1',
            'barcode' => '8690000000001',
            'title' => 'Tom Puf',
            'brand' => 'Zem',
            'category_name' => 'Puf',
            'last_synced_at' => now(),
        ]);
        $listing = ChannelListing::query()->create([
            'store_id' => $store->id,
            'channel_product_id' => $product->id,
            'listing_id' => 'LIST-TOM-1',
            'listing_status' => 'active',
            'sale_price' => 839.90,
            'currency' => 'TRY',
            'stock_quantity' => 19,
            'last_synced_at' => now(),
        ]);

        return [$product, $listing];
    }
}
