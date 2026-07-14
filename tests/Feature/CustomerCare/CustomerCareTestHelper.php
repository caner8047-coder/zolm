<?php

namespace Tests\Feature\CustomerCare;

use App\Models\SupportAiEvalRun;
use App\Models\SupportAiRun;
use App\Models\SupportChannel;
use App\Models\SupportConversation;
use App\Models\SupportOnboardingState;

trait CustomerCareTestHelper
{
    protected function setupSystemActor(): void
    {
        if (!\App\Models\User::where('email', 'system@zolm.com')->exists()) {
            \App\Models\User::factory()->create([
                'email' => 'system@zolm.com',
                'role' => 'admin',
                'is_active' => true,
            ]);
        }

        \Illuminate\Support\Facades\Config::set('customer-care.system_actor_email', 'system@zolm.com');
    }

    protected function seedPassEval(int $storeId): void
    {
        $this->seedGoldenEvalEvidence($storeId);
        $this->seedPassLanguageGate($storeId);

        $this->seedShadowEvidence($storeId);
        $this->seedOnboardingEvidence($storeId);
    }

    protected function seedGoldenEvalEvidence(
        int $storeId,
        int $score = 90,
        bool $passed = true,
        mixed $finishedAt = null,
        int $sourceAccuracy = 100,
        int $criticalErrors = 0,
        ?int $sampleCount = null,
        string $runType = 'golden_dataset'
    ): SupportAiEvalRun {
        $sampleCount ??= max(1, (int) config('customer-care.golden_eval_min_samples', 20));
        $finishedAt = $finishedAt ? \Carbon\Carbon::instance($finishedAt) : now();
        $run = SupportAiEvalRun::create([
            'store_id' => $storeId,
            'run_type' => $runType,
            'provider' => 'Fake',
            'model' => 'fake-model',
            'average_score' => $score,
            'passed_gate' => $passed,
            'status' => 'completed',
            'dataset_version' => 'tr-test-v1',
            'language' => 'tr',
            'dataset_profile' => 'local_typo_slang_abbreviation',
            'started_at' => $finishedAt->copy()->subMinute(),
            'finished_at' => $finishedAt,
            'summary_json' => [
                'total_cases' => $sampleCount,
                'passed_cases' => $passed ? $sampleCount : 0,
                'failed_cases' => $passed ? 0 : $sampleCount,
                'error_cases' => 0,
                'source_accuracy' => $sourceAccuracy,
                'critical_error_count' => $criticalErrors,
            ],
        ]);

        for ($index = 0; $index < $sampleCount; $index++) {
            $run->caseResults()->create([
                'category' => 'test_' . $index,
                'question_hash' => md5("test_{$index}"),
                'expected_keywords' => ['test'],
                'response_preview' => 'Test yanıtı',
                'score' => $score,
                'status' => $passed ? 'passed' : 'failed',
            ]);
        }

        return $run;
    }

    protected function seedShadowEvidence(int $storeId): void
    {
        $channel = SupportChannel::where('store_id', $storeId)->first();
        if ($channel && !SupportAiRun::where('store_id', $storeId)->whereNotNull('shadow_match_score')->exists()) {
            $conversation = SupportConversation::create([
                'support_channel_id' => $channel->id,
                'external_conversation_id' => 'shadow_ready_' . \Illuminate\Support\Str::uuid(),
                'store_id' => $storeId,
                'source_type' => 'shadow_evaluation',
                'status' => 'resolved',
                'priority' => 'normal',
                'ai_mode' => 'manual',
            ]);
            $sampleCount = max(1, (int) config('customer-care.shadow_min_samples', 20));
            for ($index = 0; $index < $sampleCount; $index++) {
                SupportAiRun::create([
                    'store_id' => $storeId,
                    'conversation_id' => $conversation->id,
                    'prompt_template_key' => 'shadow_readiness',
                    'confidence_score' => 90,
                    'shadow_match_score' => 90,
                    'status' => 'draft',
                ]);
            }
        }
    }

    protected function seedPassLanguageGate(int $storeId): void
    {
        \App\Models\SupportLanguageQualityGate::updateOrCreate([
            'store_id' => $storeId,
            'language' => 'tr',
            'dataset_version' => 'tr-local-v1',
        ], [
            'sample_size' => 25,
            'average_score' => 90,
            'source_accuracy' => 100,
            'critical_error_count' => 0,
            'passed' => true,
            'evaluated_at' => now(),
        ]);
    }

    protected function seedOnboardingEvidence(int $storeId): void
    {
        SupportOnboardingState::updateOrCreate([
            'store_id' => $storeId,
        ], [
            'current_step' => 6,
            'steps_completed' => [1, 2, 3, 4, 5, 6],
            'status' => 'completed',
            'recommended_mode' => 'copilot',
            'connection_started_at' => now()->subMinutes(5),
            'first_verified_draft_at' => now(),
            'verification_duration_seconds' => 300,
            'last_verified_at' => now(),
            'catalog_verified_at' => now(),
            'sample_question' => 'Kargom ne zaman gelir?',
            'sample_result_json' => ['success' => true, 'status' => 'draft', 'confidence' => 90],
            'diagnostics_json' => [['verified' => true, 'health_status' => 'ok']],
        ]);
    }
}
