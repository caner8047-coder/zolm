<?php

namespace Tests\Feature;

use App\Livewire\MarketplacePricingSimulator;
use App\Livewire\PublicTrendyolProfitCalculator;
use App\Models\ChannelListing;
use App\Models\LegalEntity;
use App\Models\MarketplacePricingScenario;
use App\Models\MarketplaceStore;
use App\Models\MpProduct;
use App\Models\User;
use App\Services\Marketplace\MarketplacePricingSimulationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class MarketplacePricingSimulatorTest extends TestCase
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
        config()->set('marketplace.features.pricing_simulator_enabled', true);
        config()->set('marketplace.features.public_trendyol_profit_tool_enabled', true);
        DB::purge('mysql');
        DB::reconnect('mysql');
        DB::setDefaultConnection('mysql');
    }

    public function test_pricing_service_calculates_profit_and_target_price(): void
    {
        $service = app(MarketplacePricingSimulationService::class);
        $input = [
            'sale_price' => 1000,
            'cogs' => 400,
            'packaging_cost' => 20,
            'cargo_cost' => 50,
            'return_cargo_cost' => 40,
            'extra_cost_fixed' => 10,
            'commission_rate' => 10,
            'service_fee_rate' => 2,
            'advertising_rate' => 3,
            'return_rate' => 5,
            'vat_enabled' => false,
            'withholding_enabled' => false,
            'target_mode' => 'amount',
            'target_profit_amount' => 200,
        ];

        $result = $service->simulate($input);

        $this->assertSame(748.0, $result['net_receivable']);
        $this->assertSame(318.0, $result['net_profit']);
        $this->assertSame(31.8, $result['profit_margin_percent']);
        $this->assertSame('healthy', $result['status']);
        $this->assertNotNull($result['break_even_price']);
        $this->assertNotNull($result['target_price']);

        $atTarget = $service->simulate(array_merge($input, [
            'sale_price' => $result['target_price'],
        ]));

        $this->assertGreaterThanOrEqual(199.99, $atTarget['net_profit']);
    }

    public function test_micro_export_zeroes_sales_vat_but_keeps_rule_explicit(): void
    {
        $service = app(MarketplacePricingSimulationService::class);
        $base = [
            'sale_price' => 1200,
            'cogs' => 600,
            'packaging_cost' => 0,
            'cargo_cost' => 0,
            'commission_rate' => 0,
            'vat_enabled' => true,
            'vat_rate' => 20,
            'cost_vat_rate' => 20,
            'expense_vat_rate' => 20,
            'withholding_enabled' => false,
        ];

        $domestic = $service->simulate($base);
        $microExport = $service->simulate(array_merge($base, ['micro_export' => true]));

        $this->assertSame(200.0, $domestic['breakdown']['sales_vat']);
        $this->assertSame(0.0, $microExport['breakdown']['sales_vat']);
        $this->assertSame(-100.0, $microExport['breakdown']['net_vat']);
        $this->assertSame(700.0, $microExport['net_profit']);
        $this->assertNotEmpty($microExport['warnings']);
    }

    public function test_negative_profit_returns_loss_warning(): void
    {
        $result = app(MarketplacePricingSimulationService::class)->simulate([
            'sale_price' => 300,
            'cogs' => 500,
            'commission_rate' => 10,
            'vat_enabled' => false,
        ]);

        $this->assertSame('loss', $result['status']);
        $this->assertLessThan(0, $result['net_profit']);
        $this->assertContains('Bu senaryo ürün başına zarar üretiyor.', $result['warnings']);
    }

    public function test_authenticated_simulator_loads_product_listing_and_saves_scenario(): void
    {
        [$user, $product, $listing] = $this->createProductGraph();
        $this->actingAs($user);

        Livewire::test(MarketplacePricingSimulator::class)
            ->set('selectedProductId', $product->id)
            ->assertSet('selectedListingId', $listing->id)
            ->assertSet('marketplace', 'trendyol')
            ->assertSet('salePrice', 1500.0)
            ->assertSet('commissionRate', 12.0)
            ->assertSee('Simülasyon sonucu')
            ->assertSee('Kayıtlı senaryolar')
            ->set('scenarioName', 'Trendyol hedef marj')
            ->call('saveScenario')
            ->assertSee('Fiyatlandırma senaryosu kaydedildi.');

        $scenario = MarketplacePricingScenario::query()
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->assertSame($product->id, $scenario->mp_product_id);
        $this->assertSame($listing->id, $scenario->channel_listing_id);
        $this->assertSame('Trendyol hedef marj', $scenario->name);
        $this->assertSame(1500.0, (float) data_get($scenario->input_json, 'sale_price'));
        $this->assertArrayHasKey('target_price', $scenario->result_json);
    }

    public function test_pricing_simulator_route_is_feature_flag_protected(): void
    {
        [$user] = $this->createProductGraph();
        $this->actingAs($user);

        config()->set('marketplace.features.pricing_simulator_enabled', false);
        $this->get('/marketplace-pricing-simulator')->assertNotFound();

        config()->set('marketplace.features.pricing_simulator_enabled', true);
        $this->get('/marketplace-pricing-simulator')
            ->assertOk()
            ->assertSee('Kâr Simülatörü');
    }

    public function test_public_trendyol_calculator_is_available_without_authentication(): void
    {
        $this->assertGuest();

        $this->get('/tools/trendyol-kar-hesaplama')
            ->assertOk()
            ->assertSee('Trendyol Kâr Hesaplama')
            ->assertSee('Hesaplama bilgileri')
            ->assertSee('Hesaplama sonucu');
    }

    public function test_public_calculator_updates_results_without_persisting_scenarios(): void
    {
        $beforeCount = MarketplacePricingScenario::query()->count();

        $component = Livewire::test(PublicTrendyolProfitCalculator::class)
            ->set('salePrice', 1000)
            ->set('cogs', 400)
            ->set('packagingCost', 20)
            ->set('cargoCost', 50)
            ->set('commissionRate', 10)
            ->set('vatEnabled', false)
            ->set('withholdingEnabled', false)
            ->set('targetMode', 'amount')
            ->set('targetProfitAmount', 200)
            ->assertSee('Hedef satış fiyatı')
            ->assertSee('Zarar etmeme fiyatı');

        $this->assertSame(430.0, $component->instance()->simulation['net_profit']);
        $this->assertSame(43.0, $component->instance()->simulation['profit_margin_percent']);
        $this->assertSame($beforeCount, MarketplacePricingScenario::query()->count());
    }

    public function test_public_calculator_route_respects_public_tool_feature_flag(): void
    {
        config()->set('marketplace.features.pricing_simulator_enabled', true);
        config()->set('marketplace.features.public_trendyol_profit_tool_enabled', false);
        $this->get('/tools/trendyol-kar-hesaplama')->assertNotFound();

        config()->set('marketplace.features.public_trendyol_profit_tool_enabled', true);
        $this->get('/tools/trendyol-kar-hesaplama')->assertOk();
    }

    /**
     * @return array{0: User, 1: MpProduct, 2: ChannelListing}
     */
    protected function createProductGraph(): array
    {
        $suffix = (string) random_int(100000, 999999);
        $user = User::factory()->create([
            'email' => 'pricing-' . Str::uuid() . '@example.test',
            'role' => 'admin',
            'is_active' => true,
        ]);
        $entity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Pricing Test Ltd.',
            'tax_number' => '7' . $suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);
        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $entity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'Pricing Store',
            'store_code' => 'PRICE-' . $suffix,
            'seller_id' => 'PRICE-' . $suffix,
            'status' => 'active',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);
        $product = MpProduct::query()->create([
            'user_id' => $user->id,
            'barcode' => 'PRICE-' . $suffix,
            'stock_code' => 'PRICE-' . $suffix,
            'product_name' => 'Fiyatlandırma Test Ürünü',
            'sale_price' => 1400,
            'market_price' => 1600,
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
            'listing_id' => 'LIST-' . $suffix,
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
}
