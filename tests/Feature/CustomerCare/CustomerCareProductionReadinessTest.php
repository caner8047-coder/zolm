<?php

namespace Tests\Feature\CustomerCare;

use Tests\TestCase;
use App\Models\User;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\SupportChannel;
use App\Models\SupportConnectorCertificationRun;
use App\Models\SupportSecurityFinding;
use App\Models\SupportLaunchPlan;
use App\Models\SupportProductionReadinessRun;
use App\Models\SupportProductionFreezeSnapshot;
use App\Models\SupportOrganizationMembership;
use App\Models\SupportRoleAssignment;
use App\Services\Support\CustomerCareProductionReadinessService;
use Illuminate\Support\Facades\Config;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;

class CustomerCareProductionReadinessTest extends TestCase
{
    use RefreshDatabase, CustomerCareTestHelper;

    private User $adminUser;
    private User $operatorUser;
    private MarketplaceStore $store;
    private SupportChannel $channel;
    private LegalEntity $legalEntity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->operatorUser = User::factory()->create(['role' => 'operator', 'is_active' => true]);

        $this->legalEntity = LegalEntity::create([
            'user_id'      => $this->adminUser->id,
            'name'         => 'Test Org',
            'company_name' => 'Co',
            'tax_office'   => 'Kadikoy',
            'tax_number'   => '1234567890',
            'address'      => 'Istanbul',
        ]);

        $this->store = MarketplaceStore::create([
            'store_name'      => 'Store A',
            'store_key'       => 'store_a',
            'user_id'         => $this->adminUser->id,
            'legal_entity_id' => $this->legalEntity->id,
            'marketplace'     => 'trendyol',
            'is_active'       => true,
        ]);

        $this->channel = SupportChannel::create([
            'store_id'   => $this->store->id,
            'key'        => 'trendyol',
            'name'       => 'Trendyol',
            'status'     => 'active',
            'is_enabled' => true,
        ]);

        Config::set('customer-care.enabled', true);
    }

    private function createReadyRun(): SupportProductionReadinessRun
    {
        return SupportProductionReadinessRun::create([
            'store_id' => $this->store->id,
            'run_by' => $this->adminUser->id,
            'readiness_score' => 100,
            'status' => 'ready',
        ]);
    }

    #[Test]
    public function readiness_route_blocks_when_flag_off(): void
    {
        Config::set('customer-care.production_center_enabled', false);

        $response = $this->actingAs($this->adminUser)
            ->get(route('customer-care.production'));

        $response->assertStatus(404);
    }

    #[Test]
    public function readiness_route_renders_when_flag_on(): void
    {
        Config::set('customer-care.production_center_enabled', true);

        $response = $this->actingAs($this->adminUser)
            ->get(route('customer-care.production'));

        $response->assertStatus(200);
    }

    #[Test]
    public function readiness_screen_shows_persisted_failure_evidence(): void
    {
        Config::set('customer-care.production_center_enabled', true);

        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\CustomerCare\Production::class)
            ->call('checkReadiness')
            ->assertSee('Tamamlanmış golden değerlendirme bulunamadı.');
    }

    #[Test]
    public function readiness_check_detects_missing_certification_and_golden_eval(): void
    {
        // 1. Her şey eksikken (cert yok, eval yok, launch plan yok) check et
        $service = app(CustomerCareProductionReadinessService::class);
        $run = $service->checkReadiness($this->store->id, $this->adminUser);

        // Durum not_ready ve skor 100'den düşük olmalı
        $this->assertEquals('not_ready', $run->status);
        $this->assertTrue($run->readiness_score < 90);
        $this->assertContains('certification_missing_failed_or_stale_trendyol', $run->failed_checks_json);
        $this->assertSame('failed', $run->check_results_json['golden_eval']['status']);
    }

    #[Test]
    public function readiness_requires_at_least_one_active_channel(): void
    {
        $this->channel->update(['is_enabled' => false]);
        $this->seedGoldenEvalEvidence($this->store->id, 95);
        SupportLaunchPlan::create([
            'store_id' => $this->store->id,
            'status' => 'approved',
            'target_channels' => [],
            'approved_at' => now(),
        ]);

        $run = app(CustomerCareProductionReadinessService::class)
            ->checkReadiness($this->store->id, $this->adminUser);

        $this->assertSame('not_ready', $run->status);
        $this->assertLessThan(100, $run->readiness_score);
        $this->assertContains('active_channel_missing', $run->failed_checks_json);
    }

    #[Test]
    public function readiness_fails_when_unresolved_critical_security_finding_exists(): void
    {
        // 1. Connector certification'ı başarılı yapalım
        SupportConnectorCertificationRun::create([
            'store_id'    => $this->store->id,
            'channel_key' => 'trendyol',
            'status'      => 'pass',
        ]);

        // 2. Golden eval'ı taze yapalım
        $this->seedGoldenEvalEvidence($this->store->id, 95, true, null, 100, 0, null, 'golden');

        // 3. Launch plan ekle
        SupportLaunchPlan::create([
            'store_id'        => $this->store->id,
            'status'          => 'approved',
            'target_channels' => ['trendyol'],
            'approved_at'     => now(),
        ]);

        // 4. Kritik açık ekle
        $auditRun = \App\Models\SupportSecurityAuditRun::create([
            'store_id' => $this->store->id,
            'status'   => 'completed',
        ]);

        SupportSecurityFinding::create([
            'store_id'    => $this->store->id,
            'run_id'      => $auditRun->id,
            'category'    => 'pii',
            'severity'    => 'critical',
            'title'       => 'Critical Leak',
            'description' => 'Plain token stored',
            'status'      => 'open',
        ]);

        $service = app(CustomerCareProductionReadinessService::class);
        $run = $service->checkReadiness($this->store->id, $this->adminUser);

        // Kritik açık olduğu için skordan düşmeli ve durum NOT_READY olmalı
        $this->assertEquals('not_ready', $run->status);
    }

    #[Test]
    public function readiness_checks_stale_golden_evaluation(): void
    {
        Config::set('customer-care.golden_eval_max_age_days', 7);

        // 1. Çok eski (stale) golden eval ekle (8 gün önce)
        $this->seedGoldenEvalEvidence($this->store->id, 95, true, now()->subDays(8), 100, 0, null, 'golden');

        $service = app(CustomerCareProductionReadinessService::class);
        $run = $service->checkReadiness($this->store->id, $this->adminUser);

        $this->assertEquals('not_ready', $run->status);
    }

    #[Test]
    public function readiness_rejects_stale_connector_certification(): void
    {
        Config::set('customer-care.connector_certification_max_age_days', 7);

        SupportConnectorCertificationRun::create([
            'store_id' => $this->store->id,
            'channel_key' => 'trendyol',
            'status' => 'pass',
            'certified_at' => now()->subDays(8),
            'created_at' => now()->subDays(8),
        ]);
        $this->seedGoldenEvalEvidence($this->store->id, 95);
        SupportLaunchPlan::create([
            'store_id' => $this->store->id,
            'status' => 'approved',
            'target_channels' => ['trendyol'],
            'approved_at' => now(),
        ]);

        $run = app(CustomerCareProductionReadinessService::class)
            ->checkReadiness($this->store->id, $this->adminUser);

        $this->assertSame('not_ready', $run->status);
        $this->assertLessThan(100, $run->readiness_score);
        $this->assertContains('certification_missing_failed_or_stale_trendyol', $run->failed_checks_json);
    }

    #[Test]
    public function configuration_freeze_snapshot_is_encrypted_and_pii_safe(): void
    {
        $service = app(CustomerCareProductionReadinessService::class);
        $run = $this->createReadyRun();

        $snap = $service->freezeConfiguration($this->store->id, $run->id, $this->adminUser);

        // Veritabanı ham görüntüsünün şifrelenmiş olduğunu doğrula
        $rawRow = \Illuminate\Support\Facades\DB::table('support_production_freeze_snapshots')->first();
        $this->assertStringNotContainsString('"plan"', $rawRow->snapshot_data_encrypted);

        // Model cast'i ile düzgün okunduğunu doğrula
        $this->assertStringContainsString('"plan"', $snap->snapshot_data_encrypted);

        // Aynı readiness çalışması tekrar dondurulursa yeni kayıt üretilmez.
        $sameSnapshot = $service->freezeConfiguration($this->store->id, $run->id, $this->adminUser);
        $this->assertSame($snap->id, $sameSnapshot->id);
    }

    #[Test]
    public function freeze_rejects_not_ready_run(): void
    {
        $run = SupportProductionReadinessRun::create([
            'store_id' => $this->store->id,
            'run_by' => $this->adminUser->id,
            'readiness_score' => 75,
            'status' => 'not_ready',
        ]);

        $this->expectException(\RuntimeException::class);

        app(CustomerCareProductionReadinessService::class)
            ->freezeConfiguration($this->store->id, $run->id, $this->adminUser);
    }

    #[Test]
    public function freeze_rejects_stale_or_superseded_readiness_evidence(): void
    {
        Config::set('customer-care.production_readiness_max_age_minutes', 60);
        $service = app(CustomerCareProductionReadinessService::class);

        $staleRun = $this->createReadyRun();
        $staleRun->forceFill(['created_at' => now()->subMinutes(61)])->saveQuietly();

        try {
            $service->freezeConfiguration($this->store->id, $staleRun->id, $this->adminUser);
            $this->fail('Süresi geçmiş hazırlık kanıtı dondurulamamalıydı.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('geçerlilik süresini aştı', $e->getMessage());
        }

        $currentRun = $this->createReadyRun();
        $newerRun = SupportProductionReadinessRun::create([
            'store_id' => $this->store->id,
            'run_by' => $this->adminUser->id,
            'readiness_score' => 10,
            'status' => 'not_ready',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('en güncel üretim hazırlık denetimi');
        $service->freezeConfiguration($this->store->id, $currentRun->id, $this->adminUser);
    }

    #[Test]
    public function governance_blocks_self_approval_for_freeze_snapshots(): void
    {
        $service = app(CustomerCareProductionReadinessService::class);

        $run = $this->createReadyRun();
        $snap = $service->freezeConfiguration($this->store->id, $run->id, $this->adminUser);

        // 1. adminUser kendisi onaylamaya çalışırsa bloke olmalı
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $service->approveFreeze($snap->id, $this->adminUser->id);
    }

    #[Test]
    public function governance_allows_other_user_approval(): void
    {
        $service = app(CustomerCareProductionReadinessService::class);

        Config::set('customer-care.governance_enabled', true);
        SupportOrganizationMembership::create([
            'legal_entity_id' => $this->legalEntity->id,
            'user_id' => $this->operatorUser->id,
            'role' => 'supervisor',
        ]);
        SupportRoleAssignment::create([
            'store_id' => $this->store->id,
            'user_id' => $this->operatorUser->id,
            'role' => 'supervisor',
        ]);

        $run = $this->createReadyRun();
        $snap = $service->freezeConfiguration($this->store->id, $run->id, $this->adminUser);

        // Yetkili ve farklı bir organizasyon üyesi onaylayabilir.
        $approvedSnap = $service->approveFreeze($snap->id, $this->operatorUser->id);

        $this->assertNotNull($approvedSnap->approved_at);
        $this->assertEquals($this->operatorUser->id, $approvedSnap->approved_by);

        $secondApprover = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $immutableSnap = $service->approveFreeze($snap->id, $secondApprover->id);
        $this->assertEquals($this->operatorUser->id, $immutableSnap->approved_by);
    }

    #[Test]
    public function governance_rejects_freeze_approval_from_outside_the_store_organization(): void
    {
        $outsider = User::factory()->create(['role' => 'operator', 'is_active' => true]);
        $service = app(CustomerCareProductionReadinessService::class);
        $run = $this->createReadyRun();
        $snap = $service->freezeConfiguration($this->store->id, $run->id, $this->adminUser);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        $service->approveFreeze($snap->id, $outsider->id);
    }

    #[Test]
    public function rollback_drill_returns_correct_metadata(): void
    {
        $service = app(CustomerCareProductionReadinessService::class);
        $result = $service->runRollbackDrill($this->store->id, $this->adminUser);

        $this->assertEquals($this->store->id, $result['store_id']);
        $this->assertEquals('force_manual_and_cancel_ai_outbox', $result['rollback_path']);
        $this->assertArrayHasKey('pending_dispatches', $result);
        $this->assertArrayHasKey('automation_circuit_breaker_active', $result);
    }
}
