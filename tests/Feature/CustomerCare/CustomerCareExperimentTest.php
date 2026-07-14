<?php

namespace Tests\Feature\CustomerCare;

use Tests\TestCase;
use App\Models\User;
use App\Models\MarketplaceStore;
use App\Models\SupportExperiment;
use App\Models\SupportExperimentVariant;
use App\Models\SupportArtifactVersion;
use App\Models\SupportAiEvalRun;
use App\Models\LegalEntity;
use App\Services\Support\CustomerCareExperimentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;

class CustomerCareExperimentTest extends TestCase
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
        Config::set('customer-care.experiments_enabled', true);
    }

    #[Test]
    public function experiments_route_blocks_when_flag_off(): void
    {
        Config::set('customer-care.experiments_enabled', false);
        $response = $this->actingAs($this->adminUser)->get('/customer-care/experiments');
        $response->assertStatus(404);
    }

    #[Test]
    public function draft_artifact_version_blocks_experiment(): void
    {
        $artifactVersion = SupportArtifactVersion::create([
            'store_id'       => $this->store->id,
            'artifact_type'  => 'prompt',
            'is_current'     => false, // aktif değil — izin vermemeli
            'version_number' => 1,
            'content_json'   => ['body' => 'test prompt'],
        ]);

        $experiment = SupportExperiment::create([
            'store_id' => $this->store->id,
            'name'     => 'Test Experiment',
            'type'     => 'prompt_variant',
            'status'   => 'ready',
        ]);

        SupportExperimentVariant::create([
            'experiment_id'       => $experiment->id,
            'label'               => 'variant_a',
            'artifact_type'       => 'prompt',
            'artifact_version_id' => $artifactVersion->id,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/aktif değil/');

        $service = app(CustomerCareExperimentService::class);
        $service->runExperiment($this->store->id, $experiment->id, $this->adminUser, false);
    }

    #[Test]
    public function cross_store_experiment_is_blocked(): void
    {
        // otherStore'da bir deney oluştur
        $experiment = SupportExperiment::create([
            'store_id' => $this->otherStore->id,
            'name'     => 'Other Experiment',
            'type'     => 'prompt_variant',
            'status'   => 'ready',
        ]);

        // adminUser kendi store'u üzerinden otherStore deneyine erişmeye çalışıyor
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $service = app(CustomerCareExperimentService::class);
        $service->runExperiment($this->store->id, $experiment->id, $this->adminUser, true);
    }

    #[Test]
    public function release_comparison_does_not_invent_a_winner_without_measurement_evidence(): void
    {
        $current   = SupportArtifactVersion::create([
            'store_id'       => $this->store->id,
            'artifact_type'  => 'prompt',
            'is_current'     => true,
            'version_number' => 1,
            'content_json'   => ['body' => 'current'],
        ]);

        $candidate = SupportArtifactVersion::create([
            'store_id'       => $this->store->id,
            'artifact_type'  => 'prompt',
            'is_current'     => true,
            'version_number' => 2,
            'content_json'   => ['body' => 'candidate'],
        ]);

        $service    = app(CustomerCareExperimentService::class);
        $comparison = $service->compareRelease($this->store->id, $current->id, $candidate->id, $this->adminUser);

        $this->assertFalse($comparison['auto_publish']);
        $this->assertNull($comparison['winner_candidate']);
        $this->assertNull($comparison['evidence']['current_experiment_run_id']);
        $this->assertNull($comparison['evidence']['candidate_experiment_run_id']);
    }

    #[Test]
    public function dry_run_experiment_does_not_write_results(): void
    {
        $experiment = SupportExperiment::create([
            'store_id' => $this->store->id,
            'name'     => 'Dry Run Test',
            'type'     => 'prompt_variant',
            'status'   => 'ready',
        ]);

        SupportExperimentVariant::create([
            'experiment_id' => $experiment->id,
            'label'         => 'control',
        ]);

        $service = app(CustomerCareExperimentService::class);
        $results = $service->runExperiment($this->store->id, $experiment->id, $this->adminUser, true);

        $this->assertNotEmpty($results);
        $this->assertTrue($results[0]['dry_run']);

        // dry-run olduğu için veritabanına run/result kaydedilmemeli
        $this->assertDatabaseMissing('support_experiment_runs', [
            'experiment_id' => $experiment->id,
        ]);
    }

    #[Test]
    public function non_ready_experiment_is_blocked(): void
    {
        $experiment = SupportExperiment::create([
            'store_id' => $this->store->id,
            'name'     => 'Draft Exp',
            'type'     => 'prompt_variant',
            'status'   => 'draft', // draft — çalıştırılamaz
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/çalıştırılabilir durumda değil/');

        $service = app(CustomerCareExperimentService::class);
        $service->runExperiment($this->store->id, $experiment->id, $this->adminUser, true);
    }

    #[Test]
    public function execute_experiment_fails_closed_without_bound_measurement_evidence(): void
    {
        $experiment = SupportExperiment::create([
            'store_id' => $this->store->id,
            'name' => 'Kanıtsız Deney',
            'type' => 'prompt_variant',
            'status' => 'ready',
        ]);
        SupportExperimentVariant::create([
            'experiment_id' => $experiment->id,
            'label' => 'control',
        ]);

        try {
            app(CustomerCareExperimentService::class)
                ->runExperiment($this->store->id, $experiment->id, $this->adminUser, false);
            $this->fail('Kanıtsız deney çalıştırması engellenmeliydi.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('değerlendirme kanıtı', $exception->getMessage());
        }

        $this->assertDatabaseCount('support_experiment_runs', 0);
        $this->assertDatabaseCount('support_experiment_results', 0);
    }

    #[Test]
    public function execute_experiment_persists_only_metrics_bound_to_real_eval_cases(): void
    {
        $artifact = SupportArtifactVersion::create([
            'store_id' => $this->store->id,
            'artifact_type' => 'prompt',
            'is_current' => true,
            'version_number' => 3,
            'content_json' => ['body' => 'ölçülen prompt'],
        ]);
        $experiment = SupportExperiment::create([
            'store_id' => $this->store->id,
            'name' => 'Kanıtlı Deney',
            'type' => 'prompt_variant',
            'status' => 'ready',
        ]);
        $evalRun = SupportAiEvalRun::create([
            'store_id' => $this->store->id,
            'run_type' => 'experiment',
            'provider' => 'GeminiProvider',
            'model' => 'test-model',
            'dataset_version' => 'exp-v1',
            'average_score' => 88,
            'passed_gate' => true,
            'status' => 'completed',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
            'summary_json' => [
                'experiment_binding' => ['artifact_version_id' => $artifact->id],
            ],
        ]);
        $case = $evalRun->caseResults()->create([
            'category' => 'kargo',
            'question_hash' => md5('Kargom nerede?'),
            'expected_keywords' => ['kargo'],
            'response_preview' => 'Siparişiniz için kargo kaydını kontrol ediyorum.',
            'score' => 88,
            'status' => 'passed',
        ]);
        $evalRun->update(['summary_json' => [
            'experiment_binding' => ['artifact_version_id' => $artifact->id],
            'experiment_case_metrics' => [
                (string) $case->id => [
                    'policy_violation' => false,
                    'hallucination_detected' => false,
                    'brand_voice_score' => 'pass',
                    'latency_ms' => 120,
                    'total_tokens' => 40,
                ],
            ],
        ]]);
        $variant = SupportExperimentVariant::create([
            'experiment_id' => $experiment->id,
            'label' => 'variant_a',
            'artifact_type' => 'prompt',
            'artifact_version_id' => $artifact->id,
            'config_override' => ['eval_run_id' => $evalRun->id],
        ]);

        $results = app(CustomerCareExperimentService::class)
            ->runExperiment($this->store->id, $experiment->id, $this->adminUser, false);

        $this->assertSame($evalRun->id, $results[0]['evidence_eval_run_id']);
        $this->assertDatabaseHas('support_experiment_runs', [
            'experiment_id' => $experiment->id,
            'variant_id' => $variant->id,
            'status' => 'completed',
            'case_count' => 1,
        ]);
        $this->assertDatabaseHas('support_experiment_results', [
            'store_id' => $this->store->id,
            'eval_case_id' => $case->id,
            'policy_violation' => false,
            'hallucination_detected' => false,
            'brand_voice_score' => 'pass',
        ]);
        $this->assertSame('completed', $experiment->fresh()->status);
    }

    #[Test]
    public function test_run_experiment_rejects_variant_artifact_from_another_store(): void
    {
        // Yabancı mağazaya ait artifact version
        $foreignArtifact = SupportArtifactVersion::create([
            'store_id'       => $this->otherStore->id,
            'artifact_type'  => 'prompt',
            'is_current'     => true,
            'version_number' => 1,
            'content_json'   => ['body' => 'foreign prompt'],
        ]);

        $experiment = SupportExperiment::create([
            'store_id' => $this->store->id,
            'name'     => 'Rejects Foreign Variant Artifact',
            'type'     => 'prompt_variant',
            'status'   => 'ready',
        ]);

        SupportExperimentVariant::create([
            'experiment_id'       => $experiment->id,
            'label'               => 'variant_a',
            'artifact_type'       => 'prompt',
            'artifact_version_id' => $foreignArtifact->id,
        ]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $service = app(CustomerCareExperimentService::class);
        $service->runExperiment($this->store->id, $experiment->id, $this->adminUser, false);
    }

    #[Test]
    public function test_compare_release_rejects_current_artifact_from_another_store(): void
    {
        // Kendi mağazamızın adayı
        $candidate = SupportArtifactVersion::create([
            'store_id'       => $this->store->id,
            'artifact_type'  => 'prompt',
            'is_current'     => true,
            'version_number' => 2,
            'content_json'   => ['body' => 'candidate prompt'],
        ]);

        // Yabancı mağazanın current'ı
        $foreignCurrent = SupportArtifactVersion::create([
            'store_id'       => $this->otherStore->id,
            'artifact_type'  => 'prompt',
            'is_current'     => true,
            'version_number' => 1,
            'content_json'   => ['body' => 'foreign current'],
        ]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $service = app(CustomerCareExperimentService::class);
        $service->compareRelease($this->store->id, $foreignCurrent->id, $candidate->id, $this->adminUser);
    }

    #[Test]
    public function test_compare_release_rejects_candidate_artifact_from_another_store(): void
    {
        // Kendi mağazamızın current'ı
        $current = SupportArtifactVersion::create([
            'store_id'       => $this->store->id,
            'artifact_type'  => 'prompt',
            'is_current'     => true,
            'version_number' => 1,
            'content_json'   => ['body' => 'current prompt'],
        ]);

        // Yabancı mağazanın adayı
        $foreignCandidate = SupportArtifactVersion::create([
            'store_id'       => $this->otherStore->id,
            'artifact_type'  => 'prompt',
            'is_current'     => true,
            'version_number' => 2,
            'content_json'   => ['body' => 'foreign candidate'],
        ]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $service = app(CustomerCareExperimentService::class);
        $service->compareRelease($this->store->id, $current->id, $foreignCandidate->id, $this->adminUser);
    }
}
