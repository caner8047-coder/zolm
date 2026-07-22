<?php

namespace Tests\Feature;

use App\Livewire\Cargo\CarrierIntegrations;
use App\Livewire\Cargo\ShipmentLedger;
use App\Livewire\CargoReports;
use App\Models\CargoCarrierAccount;
use App\Models\CargoInvoiceLine;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Cargo\CargoShipmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class CargoMultiCarrierFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_generic_carrier_credentials_are_encrypted_at_rest(): void
    {
        $user = User::factory()->create();
        $account = CargoCarrierAccount::query()->create([
            'user_id' => $user->id,
            'carrier_code' => 'dhl_express',
            'carrier_name' => 'DHL Express',
            'customer_code' => 'DHL-100',
            'credentials_encrypted' => [
                'api_key' => 'dhl-client-key',
                'api_secret' => 'dhl-client-secret',
            ],
        ]);

        $rawCredentials = (string) DB::table('cargo_carrier_accounts')
            ->where('id', $account->id)
            ->value('credentials_encrypted');

        $this->assertStringNotContainsString('dhl-client-secret', $rawCredentials);
        $this->assertSame('dhl-client-key', $account->fresh()->credentials_encrypted['api_key']);
    }

    public function test_invoice_reconciliation_never_matches_another_carrier_with_same_tracking_number(): void
    {
        $user = User::factory()->create();
        $suratShipment = Shipment::query()->create([
            'user_id' => $user->id,
            'shipment_no' => 'SHP-SURAT-1',
            'carrier_code' => 'surat',
            'carrier_name' => 'Sürat Kargo',
            'tracking_number' => 'COMMON-TRACKING-1',
            'expected_cost' => 100,
        ]);
        $arasShipment = Shipment::query()->create([
            'user_id' => $user->id,
            'shipment_no' => 'SHP-ARAS-1',
            'carrier_code' => 'aras',
            'carrier_name' => 'Aras Kargo',
            'tracking_number' => 'COMMON-TRACKING-1',
            'expected_cost' => 90,
        ]);
        $line = CargoInvoiceLine::query()->create([
            'user_id' => $user->id,
            'carrier_code' => 'aras',
            'tracking_number' => 'COMMON-TRACKING-1',
            'total_amount' => 95,
            'currency' => 'TRY',
        ]);

        $matched = app(CargoShipmentService::class)->reconcileInvoiceLine($line);

        $this->assertSame($arasShipment->id, $matched?->id);
        $this->assertSame($arasShipment->id, $line->fresh()->shipment_id);
        $this->assertEqualsWithDelta(0, (float) $suratShipment->fresh()->invoice_cost, 0.001);
        $this->assertEqualsWithDelta(95, (float) $arasShipment->fresh()->invoice_cost, 0.001);
    }

    public function test_shipment_ledger_exposes_carrier_selection_and_filtering(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ShipmentLedger::class)
            ->assertSet('draftCarrierCode', 'surat')
            ->assertSee('Yurtiçi Kargo')
            ->assertSee('Aras Kargo')
            ->assertSee('HepsiJet')
            ->assertSee('DHL Express')
            ->assertSee('Trendyol Express')
            ->set('carrierFilter', 'aras')
            ->assertSet('carrierFilter', 'aras');
    }

    public function test_carrier_control_surface_offers_account_setup_instead_of_developer_readiness_states(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(CarrierIntegrations::class)
            ->assertSee('Sürat Kargo')
            ->assertSee('Kuruluma hazır')
            ->assertSee('Hesap ekle')
            ->assertSee('Trendyol mağazasını bağla')
            ->assertDontSee('MNG Kargo')
            ->assertDontSee('Sözleşme gerekli')
            ->assertDontSee('Erişim bekleniyor');
    }

    public function test_surat_uses_the_common_account_popup_and_preserves_legacy_credentials(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)
            ->test(CarrierIntegrations::class)
            ->call('openSetup', 'surat')
            ->assertSet('selectedCarrierCode', 'surat')
            ->assertSet('form.environment', 'live')
            ->assertSee('Gönderim kullanıcı adı')
            ->assertSee('Sorgulama / web servis şifresi')
            ->set('form.account_name', 'Merkez Sürat')
            ->set('form.customer_code', 'SURAT-100')
            ->set('form.credentials.sender_password', 'sender-secret')
            ->set('form.credentials.query_password', 'query-secret')
            ->call('saveAccount')
            ->assertHasNoErrors();

        $account = CargoCarrierAccount::query()
            ->where('user_id', $user->id)
            ->where('carrier_code', 'surat')
            ->firstOrFail();

        $this->assertSame('SURAT-100', $account->sender_username);
        $this->assertSame('sender-secret', $account->sender_password_encrypted);
        $this->assertSame('query-secret', $account->query_password_encrypted);
        $this->assertSame('live', data_get($account->settings_json, 'environment'));

        $component
            ->call('openSetup', 'surat', $account->id)
            ->assertSet('form.credentials.sender_password', '')
            ->assertSet('form.credentials.query_password', '')
            ->set('form.account_name', 'Merkez Sürat Güncel')
            ->call('saveAccount')
            ->assertHasNoErrors();

        $this->assertSame('sender-secret', $account->fresh()->sender_password_encrypted);
        $this->assertSame('query-secret', $account->fresh()->query_password_encrypted);
    }

    public function test_legacy_surat_tab_requests_are_routed_to_the_carrier_control_surface(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/cargo-reports?activeTab=surat')
            ->assertOk()
            ->assertSee('Kargo taşıyıcıları')
            ->assertDontSee('Sürat Entegrasyon');

        Livewire::actingAs($user)
            ->test(CargoReports::class)
            ->assertDontSee('Sürat Entegrasyon')
            ->call('setTab', 'surat')
            ->assertSet('activeTab', 'carriers');
    }

    public function test_user_can_save_a_yurtici_account_from_the_carrier_control_surface(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(CarrierIntegrations::class)
            ->call('openSetup', 'yurtici')
            ->assertSet('form.credentials.username', '')
            ->assertSet('form.credentials.password', '')
            ->assertSeeHtml('autocomplete="new-password"')
            ->assertSeeHtml('data-form-type="other"')
            ->set('form.account_name', 'Merkez Yurtiçi')
            ->set('form.customer_code', 'YK-100')
            ->set('form.credentials.username', 'service-user')
            ->set('form.credentials.password', 'service-secret')
            ->call('saveAccount')
            ->assertHasNoErrors()
            ->assertSee('Hesap bilgileri şifrelenerek kaydedildi');

        $account = CargoCarrierAccount::query()->where('user_id', $user->id)->where('carrier_code', 'yurtici')->firstOrFail();

        $this->assertSame('service-user', $account->credentials_encrypted['username']);
        $this->assertSame('service-secret', $account->credentials_encrypted['password']);
        $this->assertSame('saved', $account->status);
    }

    public function test_each_carrier_setup_modal_uses_its_own_credential_schema(): void
    {
        $user = User::factory()->create();
        $component = Livewire::actingAs($user)->test(CarrierIntegrations::class);

        $component
            ->call('openSetup', 'yurtici')
            ->assertSee('Web servis kullanıcı adı')
            ->assertDontSee('İlk barkod aralığı')
            ->call('closeSetup')
            ->call('openSetup', 'ptt')
            ->assertSee('İlk barkod aralığı')
            ->assertDontSee('Web servis kullanıcı adı')
            ->call('closeSetup')
            ->call('openSetup', 'hepsijet')
            ->assertSee('Cross-dock kodu')
            ->assertDontSee('MyDHL API kullanıcı adı')
            ->call('closeSetup')
            ->call('openSetup', 'dhl_express')
            ->assertSee('MyDHL API kullanıcı adı')
            ->assertDontSee('Cross-dock kodu');
    }

    public function test_user_can_configure_ptt_barcode_range_and_sms_preference(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(CarrierIntegrations::class)
            ->call('openSetup', 'ptt')
            ->assertSee('İlk barkod aralığı')
            ->assertSee('Posta Çeki No')
            ->assertSee('Alıcıya SMS gönder')
            ->set('form.account_name', 'PTT Merkez Hesabı')
            ->set('form.credentials.customer_id', '123456789')
            ->set('form.credentials.password', 'ptt-secret')
            ->set('form.credentials.barcode_start', '275036569845')
            ->set('form.credentials.barcode_end', '275036569899')
            ->set('form.credentials.postal_cheque_number', '12345678')
            ->set('form.credentials.send_receiver_sms', true)
            ->call('saveAccount')
            ->assertHasNoErrors();

        $account = CargoCarrierAccount::query()->where('user_id', $user->id)->where('carrier_code', 'ptt')->firstOrFail();

        $this->assertSame('275036569845', $account->credentials_encrypted['barcode_start']);
        $this->assertSame('275036569899', $account->credentials_encrypted['barcode_end']);
        $this->assertTrue($account->credentials_encrypted['send_receiver_sms']);
    }
}
