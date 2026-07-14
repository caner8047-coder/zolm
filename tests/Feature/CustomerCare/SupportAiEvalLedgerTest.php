<?php

namespace Tests\Feature\CustomerCare;

use Tests\TestCase;
use App\Models\MarketplaceStore;
use App\Models\User;
use App\Models\SupportAiEvalRun;
use App\Models\SupportChannel;
use App\Services\Support\AI\CustomerCareEvalService;
use App\Services\Support\CustomerCarePilotReadinessService;
use App\Services\Support\AI\FakeCustomerCareAiAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;

class SupportAiEvalLedgerTest extends TestCase
{
    use RefreshDatabase, CustomerCareTestHelper;

    protected MarketplaceStore $store;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $legalEntity = \App\Models\LegalEntity::create([
            'user_id' => $this->user->id,
            'name' => 'Test Store Legal',
            'tax_number' => '1234567890',
            'is_active' => true,
        ]);
        $this->store = MarketplaceStore::create([
            'user_id' => $this->user->id,
            'legal_entity_id' => $legalEntity->id,
            'store_name' => 'Test Store',
            'marketplace' => 'trendyol',
            'is_active' => true,
        ]);

        $channel = SupportChannel::create([
            'store_id' => $this->store->id,
            'channel_type' => 'trendyol',
            'key' => 'trendyol',
            'name' => 'Trendyol Soru-Cevap',
            'status' => 'active',
            'is_enabled' => true,
        ]);

        $channel->capabilities()->create([
            'capability' => 'send_messages',
            'status' => 'available',
            'source' => 'adapter',
        ]);

        User::factory()->create([
            'email' => 'system@zolm.com',
            'role' => 'admin',
            'is_active' => true,
        ]);
        Config::set('customer-care.system_actor_email', 'system@zolm.com');
        Config::set('services.gemini.api_key', 'test_gemini_key_ok');

        Config::set('customer-care.enabled', true);
        Config::set('customer-care.pilot_store_allowlist', [$this->store->id]);
        $this->seedShadowEvidence($this->store->id);
        $this->seedOnboardingEvidence($this->store->id);
    }

    public function test_it_saves_eval_run_and_cases_to_database()
    {
        $evalService = app(CustomerCareEvalService::class);
        $provider = new FakeCustomerCareAiAdapter();

        $result = $evalService->runGoldenDatasetEval($this->store->id, $provider, $this->user->id);

        $this->assertDatabaseHas('support_ai_eval_runs', [
            'store_id' => $this->store->id,
            'run_type' => 'golden_dataset',
            'average_score' => $result['average_score'],
            'passed_gate' => $result['passed_eval_gate'],
            'triggered_by_user_id' => $this->user->id,
            'status' => 'completed',
        ]);

        $run = SupportAiEvalRun::where('store_id', $this->store->id)->first();
        $this->assertNotNull($run);
        $this->assertGreaterThan(0, $run->caseResults()->count());

        $case = $run->caseResults()->first();
        $this->assertNotNull($case->category);
        $this->assertNotNull($case->question_hash);
        $this->assertNotNull($case->expected_keywords);
    }

    public function test_pii_is_masked_in_case_results()
    {
        // Custom provider returning text with personal details
        $provider = new class implements \App\Services\Support\AI\CustomerCareAiProviderInterface {
            public function generateAnswer($conversation, $history, $promptTemplate = null): \App\Services\Support\AI\CustomerCareAiResponseDto {
                return new \App\Services\Support\AI\CustomerCareAiResponseDto(
                    'İletişim e-postam: test@example.com ve numaram: 0532 123 45 67, TC no: 12345678901',
                    90,
                    ['test'],
                    false,
                    'tr'
                );
            }
        };

        $evalService = app(CustomerCareEvalService::class);
        $evalService->runGoldenDatasetEval($this->store->id, $provider, $this->user->id);

        $run = SupportAiEvalRun::where('store_id', $this->store->id)->first();
        foreach ($run->caseResults as $case) {
            $this->assertStringNotContainsString('test@example.com', $case->response_preview);
            $this->assertStringNotContainsString('0532 123 45 67', $case->response_preview);
            $this->assertStringNotContainsString('12345678901', $case->response_preview);
        }
    }

    public function test_readiness_status_based_on_evaluation_results()
    {
        $readinessService = app(CustomerCarePilotReadinessService::class);

        // 1. No evaluation done
        $status = $readinessService->checkReadiness($this->store->id);
        $this->assertFalse($status['ready']);
        $this->assertEquals('failed', $status['checks']['golden_eval']['status']);

        // 2. Score < 80 (Failed evaluation)
        $run = $this->seedGoldenEvalEvidence($this->store->id, 70, false);

        $status = $readinessService->checkReadiness($this->store->id);
        $this->assertFalse($status['ready']);
        $this->assertEquals('failed', $status['checks']['golden_eval']['status']);

        // 3. Score >= 80 (Passed evaluation)
        $run->update([
            'average_score' => 85,
            'passed_gate' => true,
            'summary_json' => array_merge($run->summary_json, [
                'passed_cases' => $run->caseResults()->count(),
                'failed_cases' => 0,
            ]),
        ]);
        $run->caseResults()->update([
            'score' => 85,
            'status' => 'passed',
        ]);
        \App\Models\SupportLanguageQualityGate::create([
            'store_id' => $this->store->id,
            'language' => 'tr',
            'dataset_version' => 'tr-local-v1',
            'sample_size' => 24,
            'average_score' => 85,
            'source_accuracy' => 100,
            'critical_error_count' => 0,
            'passed' => true,
            'evaluated_at' => now(),
        ]);

        $status = $readinessService->checkReadiness($this->store->id);
        $this->assertTrue($status['ready']);
        $this->assertEquals('passed', $status['checks']['golden_eval']['status']);
    }

    public function test_readiness_fails_if_evaluation_is_stale()
    {
        $readinessService = app(CustomerCarePilotReadinessService::class);
        Config::set('customer-care.golden_eval_max_age_days', 7);

        // Passed evaluation, but 8 days ago (stale)
        $this->seedGoldenEvalEvidence($this->store->id, 90, true, now()->subDays(8));

        $status = $readinessService->checkReadiness($this->store->id);
        $this->assertFalse($status['ready']);
        $this->assertEquals('failed', $status['checks']['golden_eval']['status']);
        $this->assertStringContainsString('Eski Sonuç', $status['checks']['golden_eval']['detail']);
    }

    public function test_golden_gate_rejects_tampered_case_evidence_and_latest_failure(): void
    {
        $validRun = $this->seedGoldenEvalEvidence($this->store->id, 90);
        $validRun->caseResults()->first()->delete();

        $tampered = app(\App\Services\Support\AI\CustomerCareGoldenEvalGateService::class)
            ->evaluate($this->store->id);

        $this->assertFalse($tampered['passed']);
        $this->assertSame('sample_evidence_failed', $tampered['code']);

        $failedRun = $this->seedGoldenEvalEvidence($this->store->id, 70, false, now()->addSecond());
        $latest = app(\App\Services\Support\AI\CustomerCareGoldenEvalGateService::class)
            ->evaluate($this->store->id);

        $this->assertSame($failedRun->id, $latest['run']->id);
        $this->assertFalse($latest['passed']);
        $this->assertSame('score_failed', $latest['code']);
    }

    public function test_tenant_isolation_in_golden_evaluation()
    {
        $otherUser = User::factory()->create();
        $otherLegalEntity = \App\Models\LegalEntity::create([
            'user_id' => $otherUser->id,
            'name' => 'Other Store Legal',
            'tax_number' => '0987654321',
            'is_active' => true,
        ]);
        $otherStore = MarketplaceStore::create([
            'user_id' => $otherUser->id,
            'legal_entity_id' => $otherLegalEntity->id,
            'store_name' => 'Other Store',
            'marketplace' => 'trendyol',
            'is_active' => true,
        ]);

        // Run evaluation for store 1
        SupportAiEvalRun::create([
            'store_id' => $this->store->id,
            'run_type' => 'golden_dataset',
            'provider' => 'Fake',
            'model' => 'fake-model',
            'average_score' => 90,
            'passed_gate' => true,
            'status' => 'completed',
            'finished_at' => now(),
        ]);

        $evalService = app(CustomerCareEvalService::class);
        $this->assertNotNull($evalService->getLatestGoldenEval($this->store->id));
        $this->assertNull($evalService->getLatestGoldenEval($otherStore->id));
    }
}
