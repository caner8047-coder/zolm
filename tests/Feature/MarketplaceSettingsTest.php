<?php

namespace Tests\Feature;

use App\Http\Middleware\AdminMiddleware;
use App\Livewire\MarketplaceSettings;
use App\Models\MpAccountingSetting;
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
            ->call('saveSettings');

        $settings = MpAccountingSetting::where('user_id', $user->id)->firstOrFail();

        $this->assertFalse((bool) data_get($settings->settings, 'ui.help_tips_enabled', true));
    }

    public function test_marketplace_settings_can_reset_ui_preferences(): void
    {
        $user = User::factory()->create();

        (new MpSettingsService($user->id))->set('ui.help_tips_enabled', false);

        $this->actingAs($user);

        Livewire::test(MarketplaceSettings::class)
            ->assertSet('helpTipsEnabled', false)
            ->call('resetUiSettings')
            ->assertSet('helpTipsEnabled', true);

        $settings = MpAccountingSetting::where('user_id', $user->id)->firstOrFail();

        $this->assertTrue((bool) data_get($settings->settings, 'ui.help_tips_enabled', false));
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
