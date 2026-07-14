<?php

namespace Tests\Feature\CustomerCare;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CustomerCareFeatureTest extends TestCase
{
    use RefreshDatabase;
    use CustomerCareTestHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupSystemActor();
    }

    /**
     * Config varsayılanlarının tamamının güvenli/kapalı olduğunu doğrula.
     */
    public function test_config_defaults_are_secure_and_disabled(): void
    {
        $this->assertFalse(config('customer-care.enabled'));
        $this->assertFalse(config('customer-care.inbox_enabled'));
        $this->assertFalse(config('customer-care.ai_copilot_enabled'));
        $this->assertFalse(config('customer-care.auto_reply_enabled'));
        $this->assertFalse(config('customer-care.knowledge_enabled'));
        $this->assertFalse(config('customer-care.analytics_enabled'));
        $this->assertFalse(config('customer-care.demo_mode'));
        $this->assertEquals('manual', config('customer-care.default_automation_mode'));
        $this->assertEquals('default', config('customer-care.queue'));
    }

    /**
     * Master flag kapalıyken route'un 404 döndürdüğünü doğrula.
     */
    public function test_route_returns_404_when_master_flag_is_disabled(): void
    {
        config()->set('customer-care.enabled', false);
        config()->set('customer-care.inbox_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $response = $this->actingAs($user)->get(route('customer-care.home'));
        $response->assertStatus(404);
    }

    /**
     * Master açık, inbox kapalıyken route'un 404 döndürdüğünü doğrula.
     */
    public function test_route_returns_404_when_inbox_flag_is_disabled(): void
    {
        config()->set('customer-care.enabled', true);
        config()->set('customer-care.inbox_enabled', false);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $response = $this->actingAs($user)->get(route('customer-care.home'));
        $response->assertStatus(404);
    }

    /**
     * İki flag açıkken unauthenticated kullanıcının giriş ekranına yönlendirildiğini doğrula.
     */
    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        config()->set('customer-care.enabled', true);
        config()->set('customer-care.inbox_enabled', true);

        $response = $this->get(route('customer-care.home'));
        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
    }

    /**
     * Public widget dosyasının uygulama rotasını fiziksel klasörle gölgelemediğini doğrula.
     */
    public function test_public_widget_asset_does_not_shadow_customer_care_route(): void
    {
        $this->assertDirectoryDoesNotExist(public_path('customer-care'));
        $this->assertFileExists(public_path('customer-care-widget.js'));
    }

    /**
     * İki flag açıkken authenticated kullanıcının minimal sayfaya erişebildiğini doğrula.
     */
    public function test_authenticated_user_can_access_minimal_page(): void
    {
        config()->set('customer-care.enabled', true);
        config()->set('customer-care.inbox_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $response = $this->actingAs($user)->get(route('customer-care.home'));
        $response->assertStatus(200);
        $response->assertSee('AI Müşteri İletişim Merkezi');
        $response->assertSee('Manual Mod');
        $response->assertSee('Otomatik Yanıt Kapalı');
        $response->assertSeeLivewire('customer-care.home');
    }

    /**
     * Render sırasında config mutasyonu yapılmadığını doğrula.
     */
    public function test_config_is_not_mutated_during_render(): void
    {
        config()->set('customer-care.enabled', true);
        config()->set('customer-care.inbox_enabled', true);
        config()->set('customer-care.auto_reply_enabled', false);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $this->actingAs($user)->get(route('customer-care.home'));

        // Sayfa yüklendikten/render edildikten sonra otomatik yanıt hala kapalı kalmalı
        $this->assertFalse(config('customer-care.auto_reply_enabled'));
    }

    /**
     * auto_reply_enabled flag'inin varsayılan olarak false olduğunu doğrula.
     */
    public function test_auto_reply_enabled_default_is_false(): void
    {
        $this->assertFalse(config('customer-care.auto_reply_enabled'));
    }

    /**
     * Bilinmeyen bir özellik bayrağı talep edildiğinde feature middleware'inin
     * güvenli bir şekilde 404 döndürdüğünü (fail-closed) doğrula.
     */
    public function test_middleware_returns_404_for_unknown_feature(): void
    {
        config()->set('customer-care.enabled', true);
        // customer-care.unknown_feature config'de yok, bu yüzden false kabul edilmeli

        Route::get('/_test_customer_care_unknown', function () {
            return 'ok';
        })->middleware('customer-care.feature:unknown_feature');

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $response = $this->actingAs($user)->get('/_test_customer_care_unknown');
        $response->assertStatus(404);
    }
}
