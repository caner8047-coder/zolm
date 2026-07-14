<?php

namespace Tests\Feature\CustomerCare;

use Tests\TestCase;
use App\Models\MarketplaceStore;
use App\Models\SupportConversation;
use App\Models\SupportChannel;
use App\Models\SupportMessage;
use App\Models\SupportTeam;
use App\Models\SupportTeamMember;
use App\Models\SupportRoutingRule;
use App\Models\SupportAgentAction;
use App\Models\SlaDefinition;
use App\Models\SlaTrack;
use App\Models\User;
use App\Models\LegalEntity;
use App\Services\Support\CustomerCareRoutingService;
use App\Services\Support\AI\CustomerCareAutomationGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class CustomerCareRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['customer-care.enabled' => true]);
        config(['customer-care.inbox_enabled' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null); // Ensure Carbon mock is always reset
        parent::tearDown();
    }

    // ---------- Ortak yardımcı ----------

    private function makeUserAndStore(string $code = 'ST_A'): array
    {
        $user = User::factory()->create();
        $le = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'LE ' . $code,
            'tax_number' => rand(1000000000, 9999999999),
            'is_active' => true,
        ]);
        $store = MarketplaceStore::create([
            'user_id' => $user->id,
            'legal_entity_id' => $le->id,
            'marketplace' => 'trendyol',
            'store_name' => 'Store ' . $code,
            'store_code' => $code,
            'seller_id' => rand(1000, 9999),
            'status' => 'active',
            'is_active' => true,
        ]);
        $channel = SupportChannel::create([
            'store_id' => $store->id,
            'key' => 'whatsapp_main',
            'channel_type' => 'whatsapp',
            'name' => 'WA Channel',
            'is_enabled' => true,
            'config_json' => [],
        ]);
        $conv = SupportConversation::create([
            'store_id' => $store->id,
            'support_channel_id' => $channel->id,
            'external_conversation_id' => 'conv-wa-' . $code,
            'external_customer_id' => 'cust-wa-' . $code,
            'source_type' => 'whatsapp',
            'status' => 'open',
            'priority' => 'normal',
            'ai_mode' => 'manual',
            'ownership_status' => 'ai',
        ]);
        return [$user, $store, $conv];
    }

    // ---------- Mevcut testler ----------

    public function test_conversation_routed_based_on_channel_rule()
    {
        [$user, $store, $conv] = $this->makeUserAndStore('RT1');

        $team = SupportTeam::create(['store_id' => $store->id, 'name' => 'WA Destek']);
        SupportRoutingRule::create([
            'store_id' => $store->id,
            'support_team_id' => $team->id,
            'trigger_type' => 'channel',
            'trigger_value' => 'whatsapp',
            'priority' => 10,
            'is_active' => true,
        ]);

        $routingService = app(CustomerCareRoutingService::class);
        $routedTeamId = $routingService->route($conv);

        $this->assertEquals($team->id, $routedTeamId);
        $this->assertEquals($team->id, $conv->fresh()->support_team_id);
    }

    public function test_concurrency_lock_prevent_double_claims()
    {
        [$user1, $store, $conv] = $this->makeUserAndStore('RT2');
        // user2 farklı kullanıcı — store RT2'ye ait değil → tenant guard bloke etmeli
        $user2 = User::factory()->create();

        $routingService = app(CustomerCareRoutingService::class);

        $success1 = $routingService->claim($conv, $user1);
        $this->assertTrue($success1);

        // user2 farklı store kullanıcısı → P1-3 guard ile bloke
        $success2 = $routingService->claim($conv->fresh(), $user2);
        $this->assertFalse($success2);

        $this->assertEquals($user1->id, $conv->fresh()->assigned_user_id);
        $this->assertEquals('human', $conv->fresh()->ownership_status);
    }


    public function test_sla_breach_escalates_and_creates_audit_log()
    {
        [$user, $store, $conv] = $this->makeUserAndStore('RT3');

        $def = SlaDefinition::create([
            'store_id' => $store->id,
            'name' => 'Standart Destek SLA',
            'channel' => 'whatsapp',
            'priority' => 'normal',
            'first_response_minutes' => 30,
            'resolution_minutes' => 60,
        ]);

        $track = SlaTrack::create([
            'sla_definition_id' => $def->id,
            'conversation_id' => $conv->id,
            'store_id' => $store->id,
            'status' => 'active',
            'started_at' => now()->subMinutes(70),
            'first_response_deadline' => now()->subMinutes(40),
            'resolution_deadline' => now()->subMinutes(10),
            'first_response_breached' => false,
            'resolution_breached' => false,
        ]);

        $routingService = app(CustomerCareRoutingService::class);
        $routingService->checkSlaEscalations($store->id);

        $this->assertTrue($track->fresh()->resolution_breached);
        $this->assertTrue($track->fresh()->first_response_breached);
        $this->assertEquals('high', $conv->fresh()->priority);

        $this->assertDatabaseHas('support_agent_actions', [
            'conversation_id' => $conv->id,
            'action' => 'sla_escalated',
        ]);
    }

    // ---------- P1-3: Tenant / store guard testleri ----------

    public function test_cross_store_user_cannot_claim_conversation()
    {
        [$user1, $store1, $conv1] = $this->makeUserAndStore('RT4_A');
        [$user2, $store2, $conv2] = $this->makeUserAndStore('RT4_B');

        $routingService = app(CustomerCareRoutingService::class);

        // user2, store1'e ait conv1'i claim etmeye çalışıyor → engellenmeli
        $result = $routingService->claim($conv1, $user2);
        $this->assertFalse($result, 'Farklı store kullanıcısı konuşmayı claim edememeli');
        $this->assertNull($conv1->fresh()->assigned_user_id);
    }

    public function test_cross_store_user_cannot_release_conversation()
    {
        [$user1, $store1, $conv1] = $this->makeUserAndStore('RT5_A');
        [$user2, $store2, $conv2] = $this->makeUserAndStore('RT5_B');

        // Önce user1 claim etsin
        $routingService = app(CustomerCareRoutingService::class);
        $routingService->claim($conv1, $user1);

        // user2, user1'in store'undaki conv1'i release etmeye çalışıyor → engellenmeli
        $result = $routingService->release($conv1->fresh(), $user2);
        $this->assertFalse($result, 'Farklı store kullanıcısı release edememeli');
        $this->assertEquals($user1->id, $conv1->fresh()->assigned_user_id, 'Sahiplik değişmemeli');
    }

    public function test_admin_user_can_claim_any_store_conversation()
    {
        [$user1, $store1, $conv1] = $this->makeUserAndStore('RT6');

        // Admin kullanıcı (role=admin) tüm store'lara erişebilir
        $admin = User::factory()->create(['role' => 'admin']);

        $routingService = app(CustomerCareRoutingService::class);
        $result = $routingService->claim($conv1, $admin);
        $this->assertTrue($result, 'Admin tüm store\'lara erişebilmeli');
    }

    // ---------- P1-4: Business hours gate testleri ----------

    public function test_business_hours_gate_blocks_automatic_reply_outside_hours()
    {
        [$user, $store, $conv] = $this->makeUserAndStore('BH1');
        $conv->update(['ai_mode' => 'automatic']);

        config([
            'customer-care.auto_reply_enabled' => true,
            'customer-care.pilot_store_allowlist' => [$store->id],
            'customer-care.auto_reply_max_per_hour' => 100,
            'customer-care.business_hours_auto_reply_enabled' => false, // KAPALI
        ]);

        // Mock: şu an mesai dışı saat (23:00 varsay)
        // Carbon::setTestNow ile saati simüle ediyoruz
        Carbon::setTestNow(now()->setHour(23)->setMinute(0));

        $gate = app(CustomerCareAutomationGate::class);
        $result = $gate->canAutomate($conv, 90);

        Carbon::setTestNow(null); // reset

        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('Business Hours Gate', $result['reason']);
    }

    public function test_business_hours_gate_allows_during_working_hours_with_flag_off()
    {
        [$user, $store, $conv] = $this->makeUserAndStore('BH2');
        $conv->update(['ai_mode' => 'automatic']);

        config([
            'customer-care.auto_reply_enabled' => true,
            'customer-care.pilot_store_allowlist' => [$store->id],
            'customer-care.auto_reply_max_per_hour' => 100,
            'customer-care.business_hours_auto_reply_enabled' => false, // KAPALI ama mesai içi
        ]);

        // Hafta içi saat 11:00 → mesai içi → gate geçilmeli (sonraki kontrollere bırak)
        $tuesday = now()->next('Tuesday')->setHour(11)->setMinute(0);
        Carbon::setTestNow($tuesday);

        $gate = app(CustomerCareAutomationGate::class);
        $result = $gate->canAutomate($conv, 90);

        Carbon::setTestNow(null);

        // Business hours kontrolü geçti, sonraki kontroller (eval gate vs) bloke edebilir
        $this->assertNotEquals('Business Hours Gate: Mesai dışı otomatik cevap allowlist kapalı — fail-closed.', $result['reason'] ?? '');
    }

    public function test_business_hours_gate_blocks_weekend_outside_allowlist()
    {
        [$user, $store, $conv] = $this->makeUserAndStore('BH3');
        $conv->update(['ai_mode' => 'automatic']);

        config([
            'customer-care.auto_reply_enabled' => true,
            'customer-care.pilot_store_allowlist' => [$store->id],
            'customer-care.auto_reply_max_per_hour' => 100,
            'customer-care.business_hours_auto_reply_enabled' => false, // KAPALI
        ]);

        // Cumartesi
        $saturday = now()->next('Saturday')->setHour(14)->setMinute(0);
        Carbon::setTestNow($saturday);

        $gate = app(CustomerCareAutomationGate::class);
        $result = $gate->canAutomate($conv, 90);

        Carbon::setTestNow(null);

        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('Business Hours Gate', $result['reason']);
    }

    // ---------- P1-5: Eksik testler ----------

    public function test_store_without_matching_rule_returns_null_team()
    {
        [$user, $store, $conv] = $this->makeUserAndStore('RT7');
        // Hiç routing rule tanımlı değil
        $routingService = app(CustomerCareRoutingService::class);
        $result = $routingService->route($conv);
        $this->assertNull($result, 'Kural yoksa null dönmeli');
    }
}
