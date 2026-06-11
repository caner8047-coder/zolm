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
}
