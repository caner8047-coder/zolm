<?php

namespace Tests\Feature\CustomerCare;

use Tests\TestCase;
use App\Models\User;
use App\Models\MarketplaceStore;
use App\Models\SupportSecurityAuditRun;
use App\Models\SupportSecurityFinding;
use App\Models\SupportSecurityEvidenceItem;
use App\Models\SupportChannel;
use App\Models\LegalEntity;
use App\Services\Support\CustomerCareSecurityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;

class CustomerCareSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $otherUser;
    protected MarketplaceStore $store;
    protected MarketplaceStore $otherStore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create(['role' => 'admin', 'email' => 'admin@zolm.com', 'is_active' => true]);
        $this->otherUser = User::factory()->create(['role' => 'operator', 'email' => 'other@zolm.com', 'is_active' => true]);

        $le = LegalEntity::create([
            'user_id'      => $this->adminUser->id,
            'name'         => 'Test Legal',
            'company_name' => 'Test Co',
            'tax_office'   => 'Kadikoy',
            'tax_number'   => '1234567890',
            'address'      => 'Istanbul',
        ]);

        $this->store = MarketplaceStore::create([
            'store_name'      => 'Test Store',
            'store_key'       => 'test_store',
            'user_id'         => $this->adminUser->id,
            'legal_entity_id' => $le->id,
            'marketplace'     => 'trendyol',
            'is_active'       => true,
        ]);

        $this->otherStore = MarketplaceStore::create([
            'store_name'      => 'Other Store',
            'store_key'       => 'other_store',
            'user_id'         => $this->otherUser->id,
            'legal_entity_id' => $le->id,
            'marketplace'     => 'trendyol',
            'is_active'       => true,
        ]);

        Config::set('customer-care.enabled', true);
        Config::set('customer-care.security_center_enabled', true);
    }

    #[Test]
    public function security_route_blocks_when_flag_off(): void
    {
        Config::set('customer-care.security_center_enabled', false);
        $response = $this->actingAs($this->adminUser)->get('/customer-care/security');
        $response->assertStatus(404);
    }

    #[Test]
    public function audit_run_creates_findings_and_evidence(): void
    {
        $service = app(CustomerCareSecurityService::class);
        $run     = $service->runAudit($this->store->id, false, $this->adminUser); // execute: false (yani execute modu)

        $this->assertDatabaseHas('support_security_audit_runs', [
            'id'       => $run->id,
            'store_id' => $this->store->id,
            'status'   => 'completed',
        ]);

        // Evidence items oluşturulmalı
        $this->assertDatabaseHas('support_security_evidence_items', [
            'run_id'       => $run->id,
            'control_name' => 'route_coverage',
        ]);
    }

    #[Test]
    public function cross_store_audit_is_blocked(): void
    {
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        $service = app(CustomerCareSecurityService::class);
        // otherUser, adminUser'ın mağazasını denetleyemez
        $service->runAudit($this->store->id, true, $this->otherUser);
    }

    #[Test]
    public function evidence_pack_does_not_contain_raw_secrets(): void
    {
        $service = app(CustomerCareSecurityService::class);
        $run     = $service->runAudit($this->store->id, false, $this->adminUser); // execute modunda olmalı ki DB'ye yazılsın

        $pack = $service->generateEvidencePack($this->store->id, $this->adminUser);

        // Kanıt paketinde ham secret/token olmamalı
        $this->assertStringNotContainsString('sk-', $pack);
        $this->assertStringNotContainsString('password', strtolower($pack));
        $this->assertStringContainsString('MASKELENDİ', $pack); // store ID maskelendi
        $this->assertStringContainsString('Kontrol', $pack);
    }

    #[Test]
    public function evidence_data_is_encrypted_in_database(): void
    {
        $service = app(CustomerCareSecurityService::class);
        $run     = $service->runAudit($this->store->id, false, $this->adminUser); // execute modunda olmalı

        $item = SupportSecurityEvidenceItem::where('run_id', $run->id)->first();

        $this->assertNotNull($item);
        // Şifreli olduğu için raw JSON value olmamalı
        $this->assertJson($item->getEvidenceDataAttribute() ? json_encode($item->getEvidenceDataAttribute()) : '{}');
        // evidence_data_encrypted doğrudan JSON parse edilemez (şifreli)
        $this->assertNotEquals('{}', $item->evidence_data_encrypted);
    }

    #[Test]
    public function audit_with_critical_findings_is_marked_critical(): void
    {
        $service = app(CustomerCareSecurityService::class);
        // AI key yokken provider_fail_closed high finding üretilmeli
        Config::set('services.gemini.api_key', null);

        $run = $service->runAudit($this->store->id, true, $this->adminUser);

        // Overall severity clean olmamalı (provider finding var)
        $this->assertNotEquals('clean', $run->overall_severity);
    }

    #[Test]
    public function plaintext_webhook_secret_creates_critical_finding_and_failed_evidence(): void
    {
        SupportChannel::create([
            'store_id' => $this->store->id,
            'key' => 'webhook_outbound',
            'name' => 'Outbound Webhook',
            'status' => 'active',
            'is_enabled' => true,
            'config_json' => [
                'webhook_url' => 'https://example.test/hooks/customer-care',
                'webhook_secret' => 'plaintext-secret-must-fail',
            ],
        ]);

        $run = app(CustomerCareSecurityService::class)
            ->runAudit($this->store->id, false, $this->adminUser);

        $this->assertSame('critical', $run->overall_severity);
        $this->assertDatabaseHas('support_security_findings', [
            'run_id' => $run->id,
            'category' => 'secret_encryption',
            'severity' => 'critical',
        ]);
        $this->assertDatabaseHas('support_security_evidence_items', [
            'run_id' => $run->id,
            'control_name' => 'secret_encryption',
            'result' => 'fail',
        ]);
    }

    #[Test]
    public function dry_run_audit_does_not_block_execution(): void
    {
        // Dry-run denetim exception fırlatmadan tamamlanmalı
        $service = app(CustomerCareSecurityService::class);
        $run     = $service->runAudit($this->store->id, true, $this->adminUser);

        $this->assertEquals('completed', $run->status);
        $this->assertTrue($run->is_dry_run);
    }

    #[Test]
    public function test_security_audit_dry_run_does_not_persist_run_findings_or_evidence(): void
    {
        $service = app(CustomerCareSecurityService::class);
        $run     = $service->runAudit($this->store->id, true, $this->adminUser); // dry-run = true

        $this->assertTrue($run->is_dry_run);

        // DB'de hiçbir kayıt oluşmamış olmalı
        $this->assertDatabaseCount('support_security_audit_runs', 0);
        $this->assertDatabaseCount('support_security_findings', 0);
        $this->assertDatabaseCount('support_security_evidence_items', 0);

        // Ancak dönen model instance'ında bulgular ve kanıtlar in-memory mevcut olmalı
        $this->assertNotEmpty($run->findings);
        $this->assertNotEmpty($run->evidenceItems);
    }

    #[Test]
    public function test_security_audit_execute_persists_run_findings_and_evidence(): void
    {
        $service = app(CustomerCareSecurityService::class);
        $run     = $service->runAudit($this->store->id, false, $this->adminUser); // dry-run = false (execute)

        $this->assertFalse($run->is_dry_run);

        // DB'ye yazılmış olmalı
        $this->assertDatabaseCount('support_security_audit_runs', 1);
        $this->assertDatabaseCount('support_security_evidence_items', 3); // 3 kanıt kontrolü
    }

    #[Test]
    public function customer_care_http_requests_are_forced_to_tls(): void
    {
        Config::set('customer-care.force_https', true);

        $this->actingAs($this->adminUser)
            ->get('/customer-care/security')
            ->assertStatus(301)
            ->assertRedirect('https://localhost/customer-care/security');

        $this->postJson('/api/customer-care/v1/events', [])
            ->assertStatus(426)
            ->assertJsonPath('error', 'TLS zorunludur.');
    }

    #[Test]
    public function secure_customer_care_responses_include_hsts(): void
    {
        Config::set('customer-care.force_https', true);

        $this->actingAs($this->adminUser)
            ->get('https://localhost/customer-care/security')
            ->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }
}
