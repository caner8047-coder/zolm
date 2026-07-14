<?php

namespace App\Services\Support;

use App\Models\SupportExperiment;
use App\Models\SupportExperimentVariant;
use App\Models\SupportExperimentRun;
use App\Models\SupportExperimentResult;
use App\Models\SupportArtifactVersion;
use App\Models\SupportAiEvalRun;
use App\Models\User;
use App\Services\Support\TenantContext;
use App\Services\Support\Security\PiiRedactor;
use App\Services\Support\Security\SupportRbacService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CustomerCareExperimentService
{
    /**
     * Deney başlatır — öncesinde güvenlik ve preflight kontrolleri yapar.
     */
    public function runExperiment(int $storeId, int $experimentId, ?User $user = null, bool $dryRun = true): array
    {
        $user = $user ?? Auth::user() ?? TenantContext::getSystemActor();
        TenantContext::enforceStoreAccess($storeId, $user);

        if (!$dryRun) {
            app(SupportRbacService::class)->enforcePermission($user, $storeId, 'approve_quality_review');
        }

        $experiment = SupportExperiment::where('id', $experimentId)
            ->where('store_id', $storeId) // cross-store izolasyon
            ->firstOrFail();

        // Preflight: draft/cancelled deneyi çalıştırma
        if (!in_array($experiment->status, ['ready', 'running'])) {
            throw new \RuntimeException('Deney çalıştırılabilir durumda değil: ' . $experiment->status);
        }

        if ($experiment->variants->isEmpty()) {
            throw new \RuntimeException('Deneyde çalıştırılabilir varyant bulunmuyor.');
        }

        $preparedVariants = [];
        foreach ($experiment->variants as $variant) {
            if ($variant->artifact_version_id) {
                $version = SupportArtifactVersion::where('store_id', $storeId)->findOrFail($variant->artifact_version_id);
                if (!$version->is_current) {
                    throw new \RuntimeException(
                        "Varyant [{$variant->label}] için kullanılan artifact versiyon aktif değil (is_current=false). Deney durduruldu."
                    );
                }
            }

            if ($dryRun) {
                $preparedVariants[] = [
                    'variant'  => $variant->label,
                    'dry_run'  => true,
                    'message'  => 'Dry-run: artifact ve tenant ön kontrolleri geçti; ölçüm sonucu yazılmadı.',
                ];
                continue;
            }

            $preparedVariants[] = $this->resolveVariantEvidence($storeId, $variant);
        }

        if ($dryRun) {
            return $preparedVariants;
        }

        return DB::transaction(function () use ($experiment, $experimentId, $storeId, $preparedVariants): array {
            $results = [];
            $redactor = app(PiiRedactor::class);

            foreach ($preparedVariants as $prepared) {
                $variant = $prepared['variant_model'];
                $evalRun = $prepared['eval_run'];
                $caseMetrics = $prepared['case_metrics'];

                $run = SupportExperimentRun::create([
                    'experiment_id' => $experimentId,
                    'variant_id' => $variant->id,
                    'status' => 'completed',
                    'case_count' => $evalRun->caseResults->count(),
                    'started_at' => $evalRun->started_at,
                    'completed_at' => $evalRun->finished_at ?? now(),
                    'summary' => [
                        'evidence_eval_run_id' => $evalRun->id,
                        'dataset_version' => $evalRun->dataset_version,
                        'average_score' => $evalRun->average_score,
                        'passed_gate' => (bool) $evalRun->passed_gate,
                        'policy_violation_count' => collect($caseMetrics)->where('policy_violation', true)->count(),
                        'hallucination_count' => collect($caseMetrics)->where('hallucination_detected', true)->count(),
                        'brand_voice_pass_count' => collect($caseMetrics)->where('brand_voice_score', 'pass')->count(),
                        'measurement_source' => 'bound_eval_evidence',
                    ],
                ]);

                foreach ($evalRun->caseResults as $caseResult) {
                    $metric = $caseMetrics[(string) $caseResult->id];
                    SupportExperimentResult::create([
                        'run_id' => $run->id,
                        'store_id' => $storeId,
                        'eval_case_id' => $caseResult->id,
                        'policy_violation' => $metric['policy_violation'],
                        'hallucination_detected' => $metric['hallucination_detected'],
                        'brand_voice_score' => $metric['brand_voice_score'],
                        'latency_ms' => $metric['latency_ms'] ?? null,
                        'total_tokens' => $metric['total_tokens'] ?? null,
                        'estimated_cost' => $metric['estimated_cost'] ?? null,
                        'human_verdict' => $metric['human_verdict'] ?? null,
                        'redacted_response_sample' => $redactor->maskPii((string) ($caseResult->response_preview ?? '')),
                    ]);
                }

                $results[] = [
                    'variant' => $variant->label,
                    'run_id' => $run->id,
                    'evidence_eval_run_id' => $evalRun->id,
                    'status' => 'completed',
                ];
            }

            $experiment->update(['status' => 'completed']);

            return $results;
        });
    }

    /**
     * İki release paketini karşılaştırır (offline/dry-run).
     * Winner otomatik publish etmez; sadece öneri üretir.
     */
    public function compareRelease(int $storeId, int $currentId, int $candidateId, ?User $user = null): array
    {
        $user = $user ?? Auth::user() ?? TenantContext::getSystemActor();
        TenantContext::enforceStoreAccess($storeId, $user);

        $current   = SupportArtifactVersion::where('store_id', $storeId)->findOrFail($currentId);
        $candidate = SupportArtifactVersion::where('store_id', $storeId)->findOrFail($candidateId);

        if (!$current || !$candidate) {
            throw new \RuntimeException('Karşılaştırılacak artifact versiyonu bulunamadı.');
        }

        if (!$candidate->is_current) {
            throw new \RuntimeException('Aday versiyon aktif değil (is_current=false). Sadece aktif artifact versiyonları karşılaştırılabilir.');
        }

        $currentRun = $this->latestMeasuredRunForArtifact($storeId, $currentId);
        $candidateRun = $this->latestMeasuredRunForArtifact($storeId, $candidateId);
        $winner = null;
        $recommendation = 'Karşılaştırma için her iki artifact’e bağlı, tamamlanmış deney kanıtı gereklidir.';

        if ($currentRun && $candidateRun) {
            $currentPassed = (bool) data_get($currentRun->summary, 'passed_gate', false);
            $candidatePassed = (bool) data_get($candidateRun->summary, 'passed_gate', false);
            $currentScore = (int) data_get($currentRun->summary, 'average_score', 0);
            $candidateScore = (int) data_get($candidateRun->summary, 'average_score', 0);

            if ($candidatePassed && (!$currentPassed || $candidateScore > $currentScore)) {
                $winner = $candidateId;
                $recommendation = 'Aday artifact bağlı değerlendirme kanıtında daha güçlüdür; yayın yine yönetişim onayı gerektirir.';
            } elseif ($currentPassed) {
                $winner = $currentId;
                $recommendation = 'Mevcut artifact bağlı değerlendirme kanıtına göre korunmalıdır.';
            } else {
                $recommendation = 'Her iki artifact de kalite kapısını geçemedi; kazanan seçilmedi.';
            }
        }

        return [
            'current_id'    => $currentId,
            'candidate_id'  => $candidateId,
            'winner_candidate' => $winner,
            'recommendation' => $recommendation,
            'evidence' => [
                'current_experiment_run_id' => $currentRun?->id,
                'candidate_experiment_run_id' => $candidateRun?->id,
            ],
            'auto_publish'  => false,
        ];
    }

    private function resolveVariantEvidence(int $storeId, SupportExperimentVariant $variant): array
    {
        $config = $variant->config_override ?? [];
        $evalRunId = (int) ($config['eval_run_id'] ?? 0);
        if ($evalRunId <= 0) {
            throw new \RuntimeException("Varyant [{$variant->label}] için bağlı değerlendirme kanıtı (eval_run_id) yok.");
        }

        $evalRun = SupportAiEvalRun::with('caseResults')
            ->where('store_id', $storeId)
            ->where('status', 'completed')
            ->findOrFail($evalRunId);
        $maxAgeDays = max(1, (int) config('customer-care.experiment_evidence_max_age_days', 30));
        if (!$evalRun->finished_at || $evalRun->finished_at->lt(now()->subDays($maxAgeDays))) {
            throw new \RuntimeException("Varyant [{$variant->label}] için değerlendirme kanıtı güncel değil.");
        }
        if ($evalRun->caseResults->isEmpty()) {
            throw new \RuntimeException("Varyant [{$variant->label}] için değerlendirme vaka kanıtı bulunmuyor.");
        }

        $binding = (array) data_get($evalRun->summary_json, 'experiment_binding', []);
        $isBound = $variant->artifact_version_id
            ? (int) ($binding['artifact_version_id'] ?? 0) === (int) $variant->artifact_version_id
            : (int) ($binding['variant_id'] ?? 0) === (int) $variant->id;
        if (!$isBound) {
            throw new \RuntimeException("Varyant [{$variant->label}] değerlendirme kanıtıyla eşleşmiyor.");
        }

        $caseMetrics = (array) data_get($evalRun->summary_json, 'experiment_case_metrics', []);
        foreach ($evalRun->caseResults as $caseResult) {
            $metric = $caseMetrics[(string) $caseResult->id] ?? null;
            if (!is_array($metric)
                || !is_bool($metric['policy_violation'] ?? null)
                || !is_bool($metric['hallucination_detected'] ?? null)
                || !in_array($metric['brand_voice_score'] ?? null, ['pass', 'fail', 'unknown'], true)) {
                throw new \RuntimeException("Varyant [{$variant->label}] için vaka ölçüm kanıtı eksik veya geçersiz.");
            }
        }

        return [
            'variant_model' => $variant,
            'eval_run' => $evalRun,
            'case_metrics' => $caseMetrics,
        ];
    }

    private function latestMeasuredRunForArtifact(int $storeId, int $artifactId): ?SupportExperimentRun
    {
        return SupportExperimentRun::where('status', 'completed')
            ->whereHas('experiment', fn ($query) => $query->where('store_id', $storeId))
            ->whereHas('variant', fn ($query) => $query->where('artifact_version_id', $artifactId))
            ->whereNotNull('summary')
            ->latest('id')
            ->get()
            ->first(fn (SupportExperimentRun $run): bool =>
                data_get($run->summary, 'measurement_source') === 'bound_eval_evidence'
            );
    }

    /**
     * Deney sonuçlarında cross-store izolasyon — başka store'un run sonuçları okunamaz.
     */
    public function getExperimentResults(int $storeId, int $runId, User $user): array
    {
        TenantContext::enforceStoreAccess($storeId, $user);

        $results = SupportExperimentResult::where('run_id', $runId)
            ->where('store_id', $storeId)
            ->get();

        return $results->toArray();
    }
}
