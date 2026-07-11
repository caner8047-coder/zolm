<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\AccountingPilotFeedback;
use App\Livewire\Accounting\PilotCenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AccountingPilotCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_pilot_center_route_404_when_accounting_disabled(): void
    {
        config()->set('marketplace.features.accounting_enabled', false);
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $response = $this->actingAs($user)->get('/accounting/pilot-center');
        $response->assertStatus(404);
    }

    public function test_admin_can_access_pilot_center(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $response = $this->actingAs($user)->get('/accounting/pilot-center');
        $response->assertStatus(200);
    }

    public function test_non_admin_cannot_access_pilot_center(): void
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

        $response = $this->actingAs($user)->get('/accounting/pilot-center');
        $this->assertTrue(in_array($response->status(), [403, 404, 302]));
    }

    public function test_sidebar_pilot_link_visible_only_for_admin_when_accounting_enabled(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);
        
        $admin = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $responseAdmin = $this->actingAs($admin)->get('/onboarding');
        $responseAdmin->assertSee('Pilot Merkezi');

        $role = \App\Models\Role::create(['name' => 'CRM Sorumlusu', 'slug' => 'crm_sorumlusu']);
        $member = User::factory()->create([
            'is_active' => true,
            'role_id' => $role->id,
            'role' => 'operator',
        ]);
        unset($member->role);
        $member->setRelation('role', $role);

        $responseMember = $this->actingAs($member)->get('/onboarding');
        $responseMember->assertDontSee('Pilot Merkezi');
    }

    public function test_runbook_file_exists(): void
    {
        $this->assertFileExists(base_path('docs/accounting-pilot-runbook.md'));
    }

    public function test_risk_document_retains_mvp_limits(): void
    {
        $content = file_get_contents(base_path('docs/accounting-pilot-risk-register.md'));
        $this->assertStringContainsString('Gerçek e-Fatura / e-Arşiv Entegratörünün Bulunmaması', $content);
        $this->assertStringContainsString('POS Donanım ve Ödeme Cihazı Entegrasyonunun Bulunmaması', $content);
        $this->assertStringContainsString('MarketplaceReportDigestTest Known Issue', $content);
    }

    public function test_feedback_submission_and_resolution_via_livewire(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        // Livewire testi
        $component = Livewire::actingAs($user)
            ->test(PilotCenter::class)
            ->set('module', 'Cariler')
            ->set('feedbackType', 'bug')
            ->set('severity', 'high')
            ->set('title', 'Cari silme calismiyor')
            ->set('description', 'Butona basinca tepki yok')
            ->call('createFeedback')
            ->assertSet('title', '')
            ->assertSet('module', '');

        $this->assertDatabaseHas('accounting_pilot_feedbacks', [
            'user_id' => $user->id,
            'module' => 'Cariler',
            'title' => 'Cari silme calismiyor',
            'status' => 'open',
        ]);

        $feedback = AccountingPilotFeedback::first();

        // Resolve
        Livewire::actingAs($user)
            ->test(PilotCenter::class)
            ->call('resolveFeedback', $feedback->id);

        $this->assertDatabaseHas('accounting_pilot_feedbacks', [
            'id' => $feedback->id,
            'status' => 'resolved',
        ]);
    }

    public function test_livewire_feedback_search_and_tenant_isolation(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);
        $user1 = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $user2 = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        // User1 icin feedback
        AccountingPilotFeedback::create([
            'user_id' => $user1->id,
            'actor_user_id' => $user1->id,
            'module' => 'Cariler',
            'type' => 'bug',
            'severity' => 'high',
            'status' => 'open',
            'title' => 'User1 Hatası',
        ]);

        // User2 icin feedback
        AccountingPilotFeedback::create([
            'user_id' => $user2->id,
            'actor_user_id' => $user2->id,
            'module' => 'Stok',
            'type' => 'bug',
            'severity' => 'low',
            'status' => 'open',
            'title' => 'User2 Hatası',
        ]);

        // User1 olarak listele, User2'nin hatasını görmemeli
        Livewire::actingAs($user1)
            ->test(PilotCenter::class)
            ->set('activeTab', 'feedback')
            ->assertSee('User1 Hatası')
            ->assertDontSee('User2 Hatası');
    }

    public function test_livewire_feedback_sort_validation_prevents_sql_injection(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        Livewire::actingAs($user)
            ->test(PilotCenter::class)
            ->set('sortField', 'created_at')
            ->call('sortTable', 'non_existing_column_select_1_from_users')
            ->assertSet('sortField', 'created_at'); // Should not change because it's not whitelisted
    }
}
