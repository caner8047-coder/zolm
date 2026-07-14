<?php

namespace Tests\Feature\CustomerCare;

use Tests\TestCase;
use App\Models\User;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\SupportChannel;
use App\Models\IntegrationConnection;
use App\Models\WaAccount;
use App\Services\Support\CustomerCareChannelProvisioningService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Config;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;

class CustomerCareChannelProvisioningTest extends TestCase
{
    use RefreshDatabase, CustomerCareTestHelper;

    private User $adminUser;
    private User $otherUser;
    private MarketplaceStore $trendyolStore;
    private LegalEntity $le;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->otherUser = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->le = LegalEntity::create([
            'user_id'      => $this->adminUser->id,
            'name'         => 'Test Org',
            'company_name' => 'Co',
            'tax_office'   => 'Kadikoy',
            'tax_number'   => '1234567890',
            'address'      => 'Istanbul',
        ]);

        $this->trendyolStore = MarketplaceStore::create([
            'store_name'      => 'Trendyol Mağazam',
            'store_key'       => 'store_ty_1',
            'user_id'         => $this->adminUser->id,
            'legal_entity_id' => $this->le->id,
            'marketplace'     => 'trendyol',
            'is_active'       => true,
        ]);

        Config::set('customer-care.enabled', true);
        Config::set('customer-care.settings_enabled', true);

        $this->setupSystemActor();
    }

    // -----------------------------------------------------------------------
    // 1. Kanal yokken settings empty state gösterir (UI test)
    // -----------------------------------------------------------------------

    #[Test]
    public function settings_empty_state_shown_when_no_channels(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('customer-care.settings'));

        $response->assertOk();
        // Provisioning UI açıklaması görünmeli
        $response->assertSee('provision-channels-btn', false);
    }

    #[Test]
    public function settings_empty_state_shows_provisioning_button_for_trendyol_store(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('customer-care.settings'));

        $response->assertOk();
        $response->assertSee('Trendyol', false);
    }

    // -----------------------------------------------------------------------
    // 2. Provisioning Trendyol kanalı oluşturur
    // -----------------------------------------------------------------------

    #[Test]
    public function provisioning_creates_trendyol_channel_for_trendyol_store(): void
    {
        $service = app(CustomerCareChannelProvisioningService::class);
        $result  = $service->provisionForStore($this->trendyolStore->id, $this->adminUser);

        $this->assertCount(1, $result['created']);
        $this->assertCount(0, $result['existing']);

        $channel = $result['created'][0];
        $this->assertEquals('trendyol', $channel->key);
        $this->assertEquals($this->trendyolStore->id, $channel->store_id);
        $this->assertEquals('active', $channel->status);
    }

    // -----------------------------------------------------------------------
    // 3. Duplicate oluşturmaz
    // -----------------------------------------------------------------------

    #[Test]
    public function provisioning_does_not_duplicate_existing_channel(): void
    {
        $service = app(CustomerCareChannelProvisioningService::class);

        // İlk kez çalıştır
        $first = $service->provisionForStore($this->trendyolStore->id, $this->adminUser);
        $this->assertCount(1, $first['created']);

        // İkinci kez çalıştır — duplicate olmamalı
        $second = $service->provisionForStore($this->trendyolStore->id, $this->adminUser);
        $this->assertCount(0, $second['created']);
        $this->assertCount(1, $second['existing']);

        // Veritabanında tek kayıt
        $this->assertDatabaseCount('support_channels', 1);
    }

    // -----------------------------------------------------------------------
    // 4. Cross-store provisioning engellenir (Settings Livewire üzerinden)
    // -----------------------------------------------------------------------

    #[Test]
    public function cross_store_provisioning_via_livewire_is_blocked(): void
    {
        // Non-admin kullanıcı olarak test edelim — admin tüm mağazaları görür
        $user1 = User::factory()->create(['role' => 'operator', 'is_active' => true]);
        $user2 = User::factory()->create(['role' => 'operator', 'is_active' => true]);

        $le1 = LegalEntity::create([
            'user_id'      => $user1->id,
            'name'         => 'Org1',
            'company_name' => 'Org1 Co',
            'tax_office'   => 'A',
            'tax_number'   => '1111111111',
            'address'      => 'IST',
        ]);
        $le2 = LegalEntity::create([
            'user_id'      => $user2->id,
            'name'         => 'Org2',
            'company_name' => 'Org2 Co',
            'tax_office'   => 'B',
            'tax_number'   => '2222222222',
            'address'      => 'ANK',
        ]);

        $store1 = MarketplaceStore::create([
            'store_name' => 'Mağaza1', 'store_key' => 's1',
            'user_id' => $user1->id, 'legal_entity_id' => $le1->id,
            'marketplace' => 'trendyol', 'is_active' => true,
        ]);
        $store2 = MarketplaceStore::create([
            'store_name' => 'Mağaza2', 'store_key' => 's2',
            'user_id' => $user2->id, 'legal_entity_id' => $le2->id,
            'marketplace' => 'trendyol', 'is_active' => true,
        ]);

        // user1'in erişebildiği store listesi store2'yi içermemeli
        $user1StoreIds = MarketplaceStore::where('user_id', $user1->id)->pluck('id')->toArray();
        $this->assertContains($store1->id, $user1StoreIds);
        $this->assertNotContains($store2->id, $user1StoreIds);

        // user1 store2 üzerinde provizyon yapamamalı.
        // Manipüle edilmiş selectedStoreId daha aksiyona ulaşmadan bloklanmalı.
        $response = $this->actingAs($user1)
            ->get(route('customer-care.settings'))
            ->assertOk();

        // user1 sadece kendi mağazasını görür
        $response->assertSee('Mağaza1', false);
        $response->assertDontSee('Mağaza2', false);

        Livewire::actingAs($user1)
            ->test(\App\Livewire\CustomerCare\Settings::class)
            ->set('selectedStoreId', $store2->id)
            ->assertStatus(403);

        $this->assertDatabaseMissing('support_channels', [
            'store_id' => $store2->id,
            'key' => 'trendyol',
        ]);
    }

    #[Test]
    public function provisioning_service_rejects_unauthorized_actor(): void
    {
        $user = User::factory()->create(['role' => 'operator', 'is_active' => true]);

        $this->expectException(AuthorizationException::class);

        app(CustomerCareChannelProvisioningService::class)
            ->provisionForStore($this->trendyolStore->id, $user);
    }


    // -----------------------------------------------------------------------
    // 5. Oluşturulan kanal default manual ve auto reply kapalıdır
    // -----------------------------------------------------------------------

    #[Test]
    public function provisioned_channel_defaults_to_manual_mode_and_disabled(): void
    {
        $service = app(CustomerCareChannelProvisioningService::class);
        $result  = $service->provisionForStore($this->trendyolStore->id, $this->adminUser);

        $channel = $result['created'][0];

        // is_enabled = false (kullanıcı açar)
        $this->assertFalse($channel->is_enabled);

        // ai_mode = manual
        $aiMode = $channel->config_json['automation_settings']['ai_mode'] ?? 'unknown';
        $this->assertEquals('manual', $aiMode);

        // auto_reply = false
        $autoReply = $channel->config_json['automation_settings']['auto_reply'] ?? true;
        $this->assertFalse($autoReply);
    }

    // -----------------------------------------------------------------------
    // 6. WhatsApp kanalı waAccount aktifken oluşturulur
    // -----------------------------------------------------------------------

    #[Test]
    public function provisioning_creates_whatsapp_channel_when_wa_account_active(): void
    {
        // WaAccount oluştur ve store'a bağla
        $waAccount = WaAccount::create([
            'store_id'               => $this->trendyolStore->id,
            'phone_number_id'        => '111222333',
            'waba_id'                => 'waba_test_id',
            'display_phone_number'   => '+905001234567',
            'access_token_encrypted' => 'dummy_token_for_test',
            'is_active'              => true,
        ]);

        $service = app(CustomerCareChannelProvisioningService::class);
        $result  = $service->provisionForStore($this->trendyolStore->id, $this->adminUser);

        $keys = collect($result['created'])->pluck('key')->toArray();

        // Hem trendyol hem whatsapp kanalı oluşturulmalı
        $this->assertContains('trendyol', $keys);
        $this->assertContains('whatsapp', $keys);
    }

    // -----------------------------------------------------------------------
    // 7. availableToProvision bilinmeyen marketplace için boş döner
    // -----------------------------------------------------------------------

    #[Test]
    public function available_to_provision_is_empty_for_unknown_marketplace(): void
    {
        $unknownStore = MarketplaceStore::create([
            'store_name'      => 'Unknown Store',
            'store_key'       => 'store_unknown',
            'user_id'         => $this->adminUser->id,
            'legal_entity_id' => $this->le->id,
            'marketplace'     => 'bilinmeyen_platform',
            'is_active'       => true,
        ]);

        $service   = app(CustomerCareChannelProvisioningService::class);
        $available = $service->availableToProvision($unknownStore->id, $this->adminUser);

        $this->assertTrue($available->isEmpty());
    }

    #[Test]
    public function available_to_provision_includes_google_business_connection(): void
    {
        $store = MarketplaceStore::create([
            'store_name'      => 'Google Reviews Store',
            'store_key'       => 'store_google_reviews',
            'user_id'         => $this->adminUser->id,
            'legal_entity_id' => $this->le->id,
            'marketplace'     => 'bilinmeyen_platform',
            'is_active'       => true,
        ]);

        IntegrationConnection::create([
            'store_id' => $store->id,
            'provider' => 'google_business',
            'auth_type' => 'oauth',
            'status' => 'active',
        ]);

        $available = app(CustomerCareChannelProvisioningService::class)
            ->availableToProvision($store->id, $this->adminUser);

        $this->assertTrue($available->contains(fn ($channel) => $channel['key'] === 'google_business'));
    }

    // -----------------------------------------------------------------------
    // 8. Provisioning sonrası channel settings ekranında seçilebilir
    // -----------------------------------------------------------------------

    #[Test]
    public function provisioned_channel_is_selectable_in_settings_screen(): void
    {
        // Önce kanalı oluştur
        $service = app(CustomerCareChannelProvisioningService::class);
        $service->provisionForStore($this->trendyolStore->id, $this->adminUser);

        $response = $this->actingAs($this->adminUser)
            ->get(route('customer-care.settings'));

        $response->assertOk();
        // Kanal oluşturulduğu için provision butonu artık gösterilmemeli
        $response->assertDontSee('provision-channels-btn', false);
        // Kanal ayarları formu görünmeli (Marka Sesi bölümü)
        $response->assertSee('Marka Sesi', false);
    }

    #[Test]
    public function provision_channels_command_requires_store_or_all_option(): void
    {
        $this->artisan('customer-care:provision-channels')
            ->expectsOutput('Lütfen --store=ID veya --all seçeneklerinden birini belirtin.')
            ->assertExitCode(1);
    }

    #[Test]
    public function provision_channels_command_dry_run_does_not_create_channels(): void
    {
        $this->artisan('customer-care:provision-channels', [
            '--store' => $this->trendyolStore->id,
        ])
            ->expectsOutputToContain('Mod: DRY-RUN')
            ->expectsOutputToContain('Dry-run tamamlandı')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('support_channels', [
            'store_id' => $this->trendyolStore->id,
            'key' => 'trendyol',
        ]);
    }

    #[Test]
    public function provision_channels_command_execute_creates_safe_default_channel(): void
    {
        $this->artisan('customer-care:provision-channels', [
            '--store' => $this->trendyolStore->id,
            '--execute' => true,
        ])
            ->expectsOutputToContain('Mod: EXECUTE')
            ->expectsOutputToContain('Execute tamamlandı. Yeni: 1')
            ->assertExitCode(0);

        $this->assertDatabaseHas('support_channels', [
            'store_id' => $this->trendyolStore->id,
            'key' => 'trendyol',
            'is_enabled' => false,
        ]);

        $channel = SupportChannel::where('store_id', $this->trendyolStore->id)
            ->where('key', 'trendyol')
            ->firstOrFail();

        $this->assertSame('manual', $channel->config_json['automation_settings']['ai_mode'] ?? null);
        $this->assertFalse($channel->config_json['automation_settings']['auto_reply'] ?? true);
    }

    #[Test]
    public function provision_channels_command_all_processes_only_active_stores(): void
    {
        MarketplaceStore::create([
            'store_name'      => 'Pasif Mağaza',
            'store_key'       => 'store_passive',
            'user_id'         => $this->adminUser->id,
            'legal_entity_id' => $this->le->id,
            'marketplace'     => 'hepsiburada',
            'is_active'       => false,
        ]);

        $this->artisan('customer-care:provision-channels', [
            '--all' => true,
            '--execute' => true,
        ])
            ->expectsOutputToContain('Mod: EXECUTE')
            ->assertExitCode(0);

        $this->assertDatabaseHas('support_channels', [
            'store_id' => $this->trendyolStore->id,
            'key' => 'trendyol',
        ]);

        $this->assertDatabaseMissing('support_channels', [
            'key' => 'hepsiburada',
        ]);
    }
}
