<?php

namespace Tests\Feature;

use App\Http\Middleware\AdminMiddleware;
use App\Livewire\MarketplaceSettings;
use App\Models\Material;
use App\Models\MpAccountingSetting;
use App\Models\MpProduct;
use App\Models\Recipe;
use App\Models\RecipeLine;
use App\Models\User;
use App\Services\MpSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MarketplaceSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketplace_settings_page_renders(): void
    {
        $user = User::factory()->create();

        $response = $this->withoutMiddleware(AdminMiddleware::class)
            ->actingAs($user)
            ->get(route('mp.settings'));

        $response->assertOk();
        $response->assertSee('Pazaryeri Ayarları');
        $response->assertSee('Bilgilendirici yardım ipuçlarını göster');
    }

    public function test_marketplace_settings_can_persist_help_tip_visibility(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(MarketplaceSettings::class)
            ->set('helpTipsEnabled', false)
            ->set('defaultProfitMarketplace', 'woocommerce')
            ->set('woocommerceCommissionRate', 3.25)
            ->call('saveSettings');

        $settings = MpAccountingSetting::where('user_id', $user->id)->firstOrFail();

        $this->assertFalse((bool) data_get($settings->settings, 'ui.help_tips_enabled', true));
        $this->assertSame('woocommerce', data_get($settings->settings, 'marketplace_products.profit.default_marketplace'));
        $this->assertSame(3.25, (float) data_get($settings->settings, 'marketplace_products.profit.woocommerce_commission_rate'));
    }

    public function test_enabling_recipe_cost_sync_updates_matching_stock_cards(): void
    {
        $user = User::factory()->create();
        $product = MpProduct::query()->create([
            'user_id' => $user->id,
            'barcode' => '8691234567890',
            'stock_code' => 'REC-STK-001',
            'product_name' => 'Reçete Test Ürün',
            'cogs' => 10,
            'packaging_cost' => 0,
            'vat_rate' => 10,
            'sale_price' => 100,
            'market_price' => 100,
            'commission_rate' => 10,
            'stock_quantity' => 1,
            'cargo_cost' => 0,
            'pieces' => 1,
            'desi' => 1,
            'status' => 'active',
        ]);
        $material = Material::query()->create([
            'user_id' => $user->id,
            'code' => 'HM-001',
            'name' => 'Ana Malzeme',
            'category' => 'other',
            'base_unit' => 'pcs',
            'unit_price' => 25.50,
            'currency' => 'TRY',
        ]);
        $recipe = Recipe::query()->create([
            'user_id' => $user->id,
            'stock_code' => 'REC-STK-001',
            'name' => 'Reçete Test',
            'version' => 'v1',
            'status' => 'active',
        ]);
        RecipeLine::query()->create([
            'recipe_id' => $recipe->id,
            'material_id' => $material->id,
            'operation' => 'diger',
            'calc_type' => 'fixed_qty',
            'constant_qty' => 2,
            'calculated_qty' => 2,
            'calculated_unit' => 'pcs',
        ]);

        $this->actingAs($user);

        Livewire::test(MarketplaceSettings::class)
            ->set('recipeCostSyncEnabled', true)
            ->call('saveSettings')
            ->assertSet('recipeCostSyncEnabled', true);

        $settings = MpAccountingSetting::where('user_id', $user->id)->firstOrFail();

        $this->assertTrue((bool) data_get($settings->settings, 'marketplace_products.recipe_cost_sync_enabled', false));
        $this->assertSame(51.0, (float) $product->fresh()->cogs);
    }

    public function test_marketplace_settings_can_reset_ui_preferences(): void
    {
        $user = User::factory()->create();

        (new MpSettingsService($user->id))->set('ui.help_tips_enabled', false);
        (new MpSettingsService($user->id))->set('marketplace_products.recipe_cost_sync_enabled', true);

        $this->actingAs($user);

        Livewire::test(MarketplaceSettings::class)
            ->assertSet('helpTipsEnabled', false)
            ->call('resetUiSettings')
            ->assertSet('helpTipsEnabled', true);

        $settings = MpAccountingSetting::where('user_id', $user->id)->firstOrFail();

        $this->assertTrue((bool) data_get($settings->settings, 'ui.help_tips_enabled', false));
        $this->assertSame('average', data_get($settings->settings, 'marketplace_products.profit.default_marketplace'));
        $this->assertSame(0.0, (float) data_get($settings->settings, 'marketplace_products.profit.woocommerce_commission_rate'));
        $this->assertFalse((bool) data_get($settings->settings, 'marketplace_products.recipe_cost_sync_enabled', true));
    }

    public function test_marketplace_settings_can_persist_document_output_preferences(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(MarketplaceSettings::class)
            ->set('labelPrintSettings.template', 'compact')
            ->set('labelPrintSettings.paper', 'a6')
            ->set('labelPrintSettings.barcode_height', 64)
            ->set('labelPrintSettings.show_sender', false)
            ->set('dispatchPrintSettings.template', 'warehouse')
            ->set('dispatchPrintSettings.paper', 'a5_landscape')
            ->set('dispatchPrintSettings.show_signature_area', false)
            ->set('companyForm.name', 'ZOLM Test')
            ->set('companyForm.phone', '0212 000 00 00')
            ->set('companyForm.tax_number', '1234567890')
            ->set('companyForm.address', 'İstanbul / Türkiye')
            ->call('saveDocumentSettings');

        $settings = MpAccountingSetting::where('user_id', $user->id)->firstOrFail();

        $this->assertSame('compact', data_get($settings->settings, 'print.label.template'));
        $this->assertSame('a6', data_get($settings->settings, 'print.label.paper'));
        $this->assertSame(64, data_get($settings->settings, 'print.label.barcode_height'));
        $this->assertFalse((bool) data_get($settings->settings, 'print.label.show_sender'));
        $this->assertSame('warehouse', data_get($settings->settings, 'print.dispatch.template'));
        $this->assertSame('a5_landscape', data_get($settings->settings, 'print.dispatch.paper'));
        $this->assertFalse((bool) data_get($settings->settings, 'print.dispatch.show_signature_area'));
        $this->assertSame('ZOLM Test', data_get($settings->settings, 'company.name'));
        $this->assertSame('0212 000 00 00', data_get($settings->settings, 'company.phone'));
    }

    public function test_settings_saves_per_page_values(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(MarketplaceSettings::class)
            ->set('ordersPerPage', 50)
            ->set('productsPerPage', 100)
            ->call('saveSettings');

        $settings = MpAccountingSetting::where('user_id', $user->id)->firstOrFail();

        $this->assertSame(50, data_get($settings->settings, 'ui.orders_per_page'));
        $this->assertSame(100, data_get($settings->settings, 'ui.products_per_page'));
    }

    public function test_settings_rejects_invalid_per_page_values(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(MarketplaceSettings::class)
            ->set('ordersPerPage', 77)
            ->call('saveSettings')
            ->assertHasErrors(['ordersPerPage']);
    }

    public function test_settings_resets_per_page_to_defaults(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(MarketplaceSettings::class)
            ->set('ordersPerPage', 50)
            ->set('productsPerPage', 100)
            ->call('saveSettings');

        Livewire::test(MarketplaceSettings::class)
            ->call('resetUiSettings')
            ->assertSet('ordersPerPage', 20)
            ->assertSet('productsPerPage', 25);

        $settings = MpAccountingSetting::where('user_id', $user->id)->firstOrFail();

        $this->assertSame(20, data_get($settings->settings, 'ui.orders_per_page'));
        $this->assertSame(25, data_get($settings->settings, 'ui.products_per_page'));
    }

    public function test_service_normalizes_invalid_per_page_to_default(): void
    {
        $user = User::factory()->create();
        $service = new MpSettingsService($user->id);

        $this->assertSame(20, $service->normalizePerPage(77, 20));
        $this->assertSame(25, $service->normalizePerPage(0, 25));
        $this->assertSame(50, $service->normalizePerPage(50, 20));
        $this->assertSame(10, $service->normalizePerPage(10, 20));
        $this->assertSame(100, $service->normalizePerPage(100, 20));
    }

    public function test_settings_saves_date_range_values(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(MarketplaceSettings::class)
            ->set('ordersDefaultDateRangeDays', 60)
            ->set('financeDefaultDateRangeDays', 90)
            ->call('saveSettings');

        $settings = MpAccountingSetting::where('user_id', $user->id)->firstOrFail();

        $this->assertSame(60, data_get($settings->settings, 'ui.orders_default_date_range_days'));
        $this->assertSame(90, data_get($settings->settings, 'ui.finance_default_date_range_days'));
    }

    public function test_settings_rejects_invalid_date_range(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(MarketplaceSettings::class)
            ->set('ordersDefaultDateRangeDays', 15)
            ->call('saveSettings')
            ->assertHasErrors(['ordersDefaultDateRangeDays']);
    }

    public function test_settings_resets_date_range_to_defaults(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(MarketplaceSettings::class)
            ->set('ordersDefaultDateRangeDays', 180)
            ->set('financeDefaultDateRangeDays', 365)
            ->call('saveSettings');

        Livewire::test(MarketplaceSettings::class)
            ->call('resetUiSettings')
            ->assertSet('ordersDefaultDateRangeDays', 0)
            ->assertSet('financeDefaultDateRangeDays', 30);

        $settings = MpAccountingSetting::where('user_id', $user->id)->firstOrFail();

        $this->assertSame(0, data_get($settings->settings, 'ui.orders_default_date_range_days'));
        $this->assertSame(30, data_get($settings->settings, 'ui.finance_default_date_range_days'));
    }

    public function test_service_normalizes_invalid_date_range_with_module_default(): void
    {
        $user = User::factory()->create();
        $service = new MpSettingsService($user->id);

        $this->assertSame(0, $service->normalizeDateRangeDays(15, 0));
        $this->assertSame(30, $service->normalizeDateRangeDays(15, 30));
        $this->assertSame(0, $service->normalizeDateRangeDays(-1, 0));
        $this->assertSame(0, $service->normalizeDateRangeDays(0, 0));
        $this->assertSame(7, $service->normalizeDateRangeDays(7, 0));
        $this->assertSame(365, $service->normalizeDateRangeDays(365, 30));
    }

    public function test_getters_return_module_specific_fallback_for_invalid_values(): void
    {
        $user = User::factory()->create();
        $service = new MpSettingsService($user->id);

        $service->set('ui.orders_default_date_range_days', 15);
        $service->set('ui.finance_default_date_range_days', 99);

        $this->assertSame(0, $service->getOrdersDefaultDateRangeDays());
        $this->assertSame(30, $service->getFinanceDefaultDateRangeDays());
    }

    public function test_service_normalizes_currency_codes(): void
    {
        $service = new MpSettingsService(User::factory()->create()->id);

        $this->assertSame('TRY', $service->normalizeCurrency('TRY'));
        $this->assertSame('EUR', $service->normalizeCurrency('eur'));
        $this->assertSame('USD', $service->normalizeCurrency('Usd'));
        $this->assertSame('GBP', $service->normalizeCurrency('GBP'));
        $this->assertSame('TRY', $service->normalizeCurrency('JPY'));
        $this->assertSame('TRY', $service->normalizeCurrency(''));
    }

    public function test_settings_saves_auto_recommend_threshold(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(MarketplaceSettings::class)
            ->set('autoRecommendThreshold', 120)
            ->call('saveSettings');

        $settings = MpAccountingSetting::where('user_id', $user->id)->firstOrFail();

        $this->assertSame(120, data_get($settings->settings, 'matching.auto_recommend_threshold'));
    }

    public function test_settings_rejects_invalid_auto_recommend_threshold(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(MarketplaceSettings::class)
            ->set('autoRecommendThreshold', 0)
            ->call('saveSettings')
            ->assertHasErrors(['autoRecommendThreshold']);

        Livewire::test(MarketplaceSettings::class)
            ->set('autoRecommendThreshold', 501)
            ->call('saveSettings')
            ->assertHasErrors(['autoRecommendThreshold']);
    }

    public function test_settings_resets_auto_recommend_threshold(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(MarketplaceSettings::class)
            ->set('autoRecommendThreshold', 150)
            ->call('saveSettings');

        Livewire::test(MarketplaceSettings::class)
            ->call('resetUiSettings')
            ->assertSet('autoRecommendThreshold', 100);
    }

    public function test_service_normalizes_auto_recommend_threshold(): void
    {
        $service = new MpSettingsService(User::factory()->create()->id);

        $this->assertSame(100, $service->normalizeAutoRecommendThreshold(0));
        $this->assertSame(100, $service->normalizeAutoRecommendThreshold(501));
        $this->assertSame(1, $service->normalizeAutoRecommendThreshold(1));
        $this->assertSame(500, $service->normalizeAutoRecommendThreshold(500));
        $this->assertSame(120, $service->normalizeAutoRecommendThreshold(120));
    }

    public function test_matching_weights_defaults_are_correct(): void
    {
        $service = new MpSettingsService(User::factory()->create()->id);
        $weights = $service->getMatchingWeights();

        $this->assertSame(120, $weights['barcode_exact']);
        $this->assertSame(100, $weights['stock_code_exact']);
        $this->assertSame(90, $weights['model_exact']);
        $this->assertSame(70, $weights['model_family']);
        $this->assertSame(12, $weights['brand_exact']);
        $this->assertSame(8, $weights['category_exact']);
        $this->assertSame(6, $weights['title_token']);
        $this->assertSame(30, $weights['title_max']);
    }

    public function test_matching_weights_partial_override(): void
    {
        $user = User::factory()->create();
        $service = new MpSettingsService($user->id);

        $service->set('matching.weights', ['barcode_exact' => 200, 'brand_exact' => 0]);

        $weights = $service->getMatchingWeights();

        $this->assertSame(200, $weights['barcode_exact']);
        $this->assertSame(0, $weights['brand_exact']);
        $this->assertSame(100, $weights['stock_code_exact']);
    }

    public function test_matching_weights_invalid_value_normalizes_to_default(): void
    {
        $service = new MpSettingsService(User::factory()->create()->id);

        $this->assertSame(120, $service->normalizeMatchingWeight(-1, 120));
        $this->assertSame(120, $service->normalizeMatchingWeight(501, 120));
        $this->assertSame(0, $service->normalizeMatchingWeight(0, 120));
        $this->assertSame(200, $service->normalizeMatchingWeight(200, 120));
    }

    public function test_matching_weights_isolated_per_user(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        (new MpSettingsService($user1->id))->set('matching.weights', ['barcode_exact' => 200]);

        $this->assertSame(200, (new MpSettingsService($user1->id))->getMatchingWeights()['barcode_exact']);
        $this->assertSame(120, (new MpSettingsService($user2->id))->getMatchingWeights()['barcode_exact']);
    }

    public function test_settings_saves_matching_weights(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(MarketplaceSettings::class)
            ->set('matchingWeights.barcode_exact', 200)
            ->set('matchingWeights.brand_exact', 0)
            ->call('saveSettings');

        $settings = MpAccountingSetting::where('user_id', $user->id)->firstOrFail();

        $this->assertSame(200, data_get($settings->settings, 'matching.weights.barcode_exact'));
        $this->assertSame(0, data_get($settings->settings, 'matching.weights.brand_exact'));
    }

    public function test_settings_resets_matching_weights(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(MarketplaceSettings::class)
            ->set('matchingWeights.barcode_exact', 200)
            ->call('saveSettings');

        Livewire::test(MarketplaceSettings::class)
            ->call('resetUiSettings')
            ->assertSet('matchingWeights.barcode_exact', 120);
    }

    public function test_matching_stop_words_defaults_are_correct(): void
    {
        $service = new MpSettingsService(User::factory()->create()->id);
        $stopWords = $service->getMatchingStopWords();

        $this->assertContains('adet', $stopWords);
        $this->assertContains('için', $stopWords);
        $this->assertContains('ürün', $stopWords);
        $this->assertContains('seti', $stopWords);
        $this->assertCount(17, $stopWords);
    }

    public function test_matching_stop_words_partial_override(): void
    {
        $user = User::factory()->create();
        $service = new MpSettingsService($user->id);

        $service->set('matching.stop_words', ['custom', 'stop']);

        $stopWords = $service->getMatchingStopWords();

        $this->assertContains('custom', $stopWords);
        $this->assertContains('stop', $stopWords);
        $this->assertNotContains('adet', $stopWords);
    }

    public function test_matching_stop_words_normalizes_input(): void
    {
        $service = new MpSettingsService(User::factory()->create()->id);

        $result = $service->normalizeStopWords(['  Hello  ', '', 'A', 'ab', 'a' . str_repeat('x', 40), 'valid'], []);

        $this->assertContains('hello', $result);
        $this->assertNotContains('', $result);
        $this->assertNotContains('a', $result);
        $this->assertContains('ab', $result);
        $this->assertNotContains('a' . str_repeat('x', 40), $result);
        $this->assertContains('valid', $result);
    }

    public function test_matching_stop_words_deduplicates(): void
    {
        $service = new MpSettingsService(User::factory()->create()->id);

        $result = $service->normalizeStopWords(['hello', 'Hello', 'HELLO', 'world'], []);

        $this->assertCount(2, $result);
        $this->assertContains('hello', $result);
        $this->assertContains('world', $result);
    }

    public function test_matching_stop_words_empty_returns_defaults(): void
    {
        $service = new MpSettingsService(User::factory()->create()->id);

        $result = $service->normalizeStopWords([], ['fallback']);

        $this->assertSame(['fallback'], $result);
    }

    public function test_matching_stop_words_max_100(): void
    {
        $service = new MpSettingsService(User::factory()->create()->id);

        $input = array_map(fn ($i) => "word{$i}", range(1, 150));

        $result = $service->normalizeStopWords($input, []);

        $this->assertCount(100, $result);
    }

    public function test_settings_saves_stop_words(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(MarketplaceSettings::class)
            ->set('matchingStopWords', 'custom, stop, words')
            ->call('saveSettings');

        $settings = MpAccountingSetting::where('user_id', $user->id)->firstOrFail();

        $saved = data_get($settings->settings, 'matching.stop_words', []);
        $this->assertContains('custom', $saved);
        $this->assertContains('stop', $saved);
        $this->assertContains('words', $saved);
    }

    public function test_settings_resets_stop_words(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(MarketplaceSettings::class)
            ->set('matchingStopWords', 'custom, words')
            ->call('saveSettings');

        Livewire::test(MarketplaceSettings::class)
            ->call('resetUiSettings')
            ->assertSet('matchingStopWords', implode(', ', [
                'adet', 'one', 'size', 'olan', 'icin', 'için', 'ile', 've', 'bir', 'iki',
                'tak', 'takim', 'takimi', 'takımı', 'urun', 'ürün', 'seti',
            ]));
    }

    public function test_stop_words_is_isolated_per_user(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        (new MpSettingsService($user1->id))->set('matching.stop_words', ['custom']);

        $this->assertContains('custom', (new MpSettingsService($user1->id))->getMatchingStopWords());
        $this->assertNotContains('custom', (new MpSettingsService($user2->id))->getMatchingStopWords());
    }

    public function test_candidate_limits_defaults(): void
    {
        $service = new MpSettingsService(User::factory()->create()->id);

        $this->assertSame(12, $service->getMatchingCandidateSearchLimit());
        $this->assertSame(8, $service->getMatchingCandidateResultLimit());
    }

    public function test_candidate_limits_custom_values(): void
    {
        $user = User::factory()->create();
        $service = new MpSettingsService($user->id);

        $service->set('matching.candidate_search_limit', 50);
        $service->set('matching.candidate_result_limit', 20);

        $this->assertSame(50, $service->getMatchingCandidateSearchLimit());
        $this->assertSame(20, $service->getMatchingCandidateResultLimit());
    }

    public function test_result_limit_capped_by_search_limit(): void
    {
        $user = User::factory()->create();
        $service = new MpSettingsService($user->id);

        $service->set('matching.candidate_search_limit', 10);
        $service->set('matching.candidate_result_limit', 20);

        $this->assertSame(10, $service->getMatchingCandidateResultLimit());
    }

    public function test_candidate_limits_invalid_values_normalize(): void
    {
        $user = User::factory()->create();
        $service = new MpSettingsService($user->id);

        $service->set('matching.candidate_search_limit', 0);
        $this->assertSame(12, $service->getMatchingCandidateSearchLimit());

        $service->set('matching.candidate_search_limit', 101);
        $this->assertSame(12, $service->getMatchingCandidateSearchLimit());

        $service->set('matching.candidate_result_limit', 0);
        $this->assertSame(8, $service->getMatchingCandidateResultLimit());

        $service->set('matching.candidate_result_limit', 51);
        $this->assertSame(8, $service->getMatchingCandidateResultLimit());
    }

    public function test_settings_saves_candidate_limits(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(MarketplaceSettings::class)
            ->set('candidateSearchLimit', 30)
            ->set('candidateResultLimit', 15)
            ->call('saveSettings');

        $settings = MpAccountingSetting::where('user_id', $user->id)->firstOrFail();

        $this->assertSame(30, data_get($settings->settings, 'matching.candidate_search_limit'));
        $this->assertSame(15, data_get($settings->settings, 'matching.candidate_result_limit'));
    }

    public function test_settings_resets_candidate_limits(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(MarketplaceSettings::class)
            ->set('candidateSearchLimit', 30)
            ->set('candidateResultLimit', 15)
            ->call('saveSettings');

        Livewire::test(MarketplaceSettings::class)
            ->call('resetUiSettings')
            ->assertSet('candidateSearchLimit', 12)
            ->assertSet('candidateResultLimit', 8);
    }

    public function test_candidate_limits_isolated_per_user(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        (new MpSettingsService($user1->id))->set('matching.candidate_search_limit', 50);

        $this->assertSame(50, (new MpSettingsService($user1->id))->getMatchingCandidateSearchLimit());
        $this->assertSame(12, (new MpSettingsService($user2->id))->getMatchingCandidateSearchLimit());
    }

    public function test_auto_run_on_sync_defaults_to_true(): void
    {
        $service = new MpSettingsService(User::factory()->create()->id);

        $this->assertTrue($service->getAutoRunMatchingOnSync());
    }

    public function test_auto_run_on_sync_can_be_disabled(): void
    {
        $user = User::factory()->create();
        $service = new MpSettingsService($user->id);

        $service->set('matching.auto_run_on_sync', false);

        $this->assertFalse($service->getAutoRunMatchingOnSync());
    }

    public function test_settings_saves_auto_run_on_sync(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(MarketplaceSettings::class)
            ->set('autoRunMatchingOnSync', false)
            ->call('saveSettings');

        $settings = MpAccountingSetting::where('user_id', $user->id)->firstOrFail();

        $this->assertFalse((bool) data_get($settings->settings, 'matching.auto_run_on_sync'));
    }

    public function test_settings_resets_auto_run_on_sync(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(MarketplaceSettings::class)
            ->set('autoRunMatchingOnSync', false)
            ->call('saveSettings');

        Livewire::test(MarketplaceSettings::class)
            ->call('resetUiSettings')
            ->assertSet('autoRunMatchingOnSync', true);
    }

    public function test_auto_run_on_sync_normalizes_string_false(): void
    {
        $user = User::factory()->create();
        $service = new MpSettingsService($user->id);

        $service->set('matching.auto_run_on_sync', 'false');

        $this->assertFalse($service->getAutoRunMatchingOnSync());
    }

    public function test_auto_run_on_sync_normalizes_invalid_to_true(): void
    {
        $user = User::factory()->create();
        $service = new MpSettingsService($user->id);

        $service->set('matching.auto_run_on_sync', 'xyz');

        $this->assertTrue($service->getAutoRunMatchingOnSync());
    }

    public function test_trendyol_timestamp_offset_defaults_to_10800(): void
    {
        $service = new MpSettingsService(User::factory()->create()->id);

        $this->assertSame(10800, $service->getTrendyolTimestampOffsetSeconds());
    }

    public function test_trendyol_timestamp_offset_can_be_set_to_7200(): void
    {
        $user = User::factory()->create();
        $service = new MpSettingsService($user->id);

        $service->set('orders.trendyol_timestamp_offset_seconds', 7200);

        $this->assertSame(7200, $service->getTrendyolTimestampOffsetSeconds());
    }

    public function test_trendyol_timestamp_offset_normalizes_invalid(): void
    {
        $service = new MpSettingsService(User::factory()->create()->id);

        $this->assertSame(10800, $service->normalizeTrendyolTimestampOffset(-43201));
        $this->assertSame(10800, $service->normalizeTrendyolTimestampOffset(50401));
        $this->assertSame(-3600, $service->normalizeTrendyolTimestampOffset(-3600));
        $this->assertSame(0, $service->normalizeTrendyolTimestampOffset(0));
        $this->assertSame(-43200, $service->normalizeTrendyolTimestampOffset(-43200));
        $this->assertSame(50400, $service->normalizeTrendyolTimestampOffset(50400));
        $this->assertSame(7200, $service->normalizeTrendyolTimestampOffset(7200));
    }

    public function test_settings_saves_trendyol_timestamp_offset(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(MarketplaceSettings::class)
            ->set('trendyolTimestampOffsetSeconds', 7200)
            ->call('saveSettings');

        $settings = MpAccountingSetting::where('user_id', $user->id)->firstOrFail();

        $this->assertSame(7200, data_get($settings->settings, 'orders.trendyol_timestamp_offset_seconds'));
    }

    public function test_settings_resets_trendyol_timestamp_offset(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(MarketplaceSettings::class)
            ->set('trendyolTimestampOffsetSeconds', 7200)
            ->call('saveSettings');

        Livewire::test(MarketplaceSettings::class)
            ->call('resetUiSettings')
            ->assertSet('trendyolTimestampOffsetSeconds', 10800);
    }
}
