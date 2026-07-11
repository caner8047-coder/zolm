<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AccountingPilotReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected array $routes = [
        'accounting.dashboard' => '/accounting',
        'accounting.parties' => '/accounting/parties',
        'accounting.party-ledger' => '/accounting/party-ledger',
        'accounting.chart-of-accounts' => '/accounting/chart-of-accounts',
        'accounting.journal' => '/accounting/journal',
        'accounting.cash-bank' => '/accounting/cash-bank',
        'accounting.stock' => '/accounting/stock',
        'accounting.products' => '/accounting/products',
        'accounting.sales' => '/accounting/sales',
        'accounting.purchases' => '/accounting/purchases',
        'accounting.collections-payments' => '/accounting/collections-payments',
        'accounting.pos' => '/accounting/pos',
        'accounting.e-documents' => '/accounting/e-documents',
        'accounting.reports' => '/accounting/reports',
        'accounting.assistant' => '/accounting/assistant',
        'accounting.marketplace-bridge' => '/accounting/marketplace-bridge',
        'accounting.audit-logs' => '/accounting/audit-logs',
    ];

    public function test_pilot_routes_return_200_after_seeding_for_admin_users(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        Artisan::call('accounting:seed-demo', ['--user' => $user->id]);

        foreach ($this->routes as $name => $path) {
            $response = $this->actingAs($user)->get($path);
            $response->assertStatus(200);
        }
    }

    public function test_sidebar_menu_visible_only_for_admin_when_accounting_enabled(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);
        
        $admin = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $responseAdmin = $this->actingAs($admin)->get('/onboarding'); // Stabil 200 dönen onboarding sayfası
        $responseAdmin->assertSee('Muhasebe (ERP)');

        $role = \App\Models\Role::create(['name' => 'CRM Sorumlusu', 'slug' => 'crm_sorumlusu']);
        $member = User::factory()->create([
            'is_active' => true,
            'role_id' => $role->id,
            'role' => 'operator',
        ]);
        unset($member->role);
        $member->setRelation('role', $role);

        $responseMember = $this->actingAs($member)->get('/onboarding');
        $responseMember->assertDontSee('Muhasebe (ERP)');
    }

    public function test_non_admin_cannot_access_pilot_routes(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);
        
        $role = \App\Models\Role::create(['name' => 'CRM Sorumlusu', 'slug' => 'crm_sorumlusu']);
        $user = User::factory()->create([
            'is_active' => true,
            'role_id' => $role->id,
            'role' => 'operator',
        ]);
        unset($user->role);
        $user->setRelation('role', $role);

        foreach ($this->routes as $name => $path) {
            $response = $this->actingAs($user)->get($path);
            $this->assertTrue(
                in_array($response->status(), [403, 404, 302]),
                "Route [{$path}] returned status [{$response->status()}] for non-admin"
            );
        }
    }

    public function test_seed_demo_command_is_idempotent(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $res1 = Artisan::call('accounting:seed-demo', ['--user' => $user->id]);
        $this->assertSame(0, $res1);

        $res2 = Artisan::call('accounting:seed-demo', ['--user' => $user->id]);
        $this->assertSame(0, $res2);
    }

    public function test_required_documents_exist(): void
    {
        $this->assertFileExists(base_path('docs/accounting-release-checklist.md'));
        $this->assertFileExists(base_path('docs/accounting-user-acceptance-scenarios.md'));
        $this->assertFileExists(base_path('docs/accounting-pilot-risk-register.md'));
        $this->assertFileExists(base_path('docs/parola-esdegerlik-ve-urunlesme-checklist.md'));
    }

    public function test_parola_checklist_retains_mvp_acceptances(): void
    {
        $content = file_get_contents(base_path('docs/parola-esdegerlik-ve-urunlesme-checklist.md'));
        $this->assertStringContainsString('MVP Kabul', $content);
        $this->assertStringContainsString('Gerçek özel entegratör/GİB entegrasyonu yok', $content);
        $this->assertStringContainsString('Donanım, fiş yazıcı, barkod okuyucu ve ödeme terminali entegrasyonu yok', $content);
        $this->assertStringContainsString('Salt okunur/kural tabanlı; gerçek LLM', $content);
    }

    public function test_known_issue_documented_in_release_checklist(): void
    {
        $content = file_get_contents(base_path('docs/accounting-release-checklist.md'));
        $this->assertStringContainsString('MarketplaceReportDigestTest', $content);
    }

    public function test_seed_demo_command_production_guard(): void
    {
        $originalEnv = $this->app->environment();
        $this->app->detectEnvironment(fn() => 'production');

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        // --force olmadan hata vermeli
        $resWithoutForce = Artisan::call('accounting:seed-demo', ['--user' => $user->id]);
        $this->assertSame(1, $resWithoutForce);

        // --force ile basariyla calismali
        $resWithForce = Artisan::call('accounting:seed-demo', ['--user' => $user->id, '--force' => true]);
        $this->assertSame(0, $resWithForce);

        // Environment'ı geri al
        $this->app->detectEnvironment(fn() => $originalEnv);
    }

    public function test_user_acceptance_scenarios_documents_are_aligned_with_seeded_data(): void
    {
        $content = file_get_contents(base_path('docs/accounting-user-acceptance-scenarios.md'));
        
        $this->assertStringContainsString('Muhasebe (ERP)', $content);
        $this->assertStringContainsString('ZOLM Masa Sandalye Seti', $content);
        $this->assertStringContainsString('ZOLM Kitaplık Raflı', $content);
        $this->assertStringContainsString('ZOLM Demo Ziraat Bankası (Vadesiz)', $content);

        $this->assertStringNotContainsString('Demo Akıllı Telefon', $content);
        $this->assertStringNotContainsString('Demo Kulaklık', $content);
        $this->assertStringNotContainsString('ZOLM Demo Garanti Bankası', $content);
    }
}
