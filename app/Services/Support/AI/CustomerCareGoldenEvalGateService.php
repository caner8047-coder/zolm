<?php

namespace App\Services\Support\AI;

use App\Models\SupportAiEvalRun;

class CustomerCareGoldenEvalGateService
{
    /**
     * En son tamamlanan değerlendirmeyi tek ve fail-closed bir kanıt standardıyla doğrular.
     *
     * @return array{passed: bool, code: string, detail: string, run: ?SupportAiEvalRun, metrics: array<string, int|float|null>}
     */
    public function evaluate(int $storeId, string $language = 'tr'): array
    {
        $run = SupportAiEvalRun::where('store_id', $storeId)
            ->whereIn('run_type', ['golden', 'golden_dataset'])
            ->where('status', 'completed')
            ->where('language', $language)
            ->orderByDesc('finished_at')
            ->orderByDesc('id')
            ->first();

        if (!$run) {
            return $this->result(false, 'missing', 'Tamamlanmış golden değerlendirme bulunamadı.', null);
        }

        $summary = is_array($run->summary_json) ? $run->summary_json : [];
        $caseResults = $run->caseResults()->get(['score', 'status']);
        $actualCaseCount = $caseResults->count();
        $reportedCaseCount = (int) ($summary['total_cases'] ?? 0);
        $actualPassedCount = $caseResults->where('status', 'passed')->count();
        $actualFailedCount = $caseResults->where('status', 'failed')->count();
        $actualErrorCount = $caseResults->where('status', 'error')->count();
        $reportedPassedCount = isset($summary['passed_cases']) ? (int) $summary['passed_cases'] : null;
        $reportedFailedCount = isset($summary['failed_cases']) ? (int) $summary['failed_cases'] : null;
        $reportedErrorCount = isset($summary['error_cases']) ? (int) $summary['error_cases'] : null;
        $actualAverageScore = $actualCaseCount > 0 ? (float) $caseResults->avg('score') : null;
        $sourceAccuracy = isset($summary['source_accuracy']) ? (float) $summary['source_accuracy'] : null;
        $criticalErrors = isset($summary['critical_error_count']) ? (int) $summary['critical_error_count'] : null;
        $maxAgeDays = max(1, (int) config('customer-care.golden_eval_max_age_days', 7));
        $minimumSamples = max(1, (int) config('customer-care.golden_eval_min_samples', 20));
        $minimumScore = max(0, min(100, (int) config('customer-care.golden_eval_min_score', 80)));
        $minimumSourceAccuracy = max(0, min(100, (int) config('customer-care.golden_eval_min_source_accuracy', 95)));
        $metrics = [
            'average_score' => (int) $run->average_score,
            'actual_case_count' => $actualCaseCount,
            'reported_case_count' => $reportedCaseCount,
            'actual_average_score' => $actualAverageScore,
            'source_accuracy' => $sourceAccuracy,
            'critical_error_count' => $criticalErrors,
            'max_age_days' => $maxAgeDays,
        ];

        if (!$run->finished_at) {
            return $this->result(false, 'missing_finished_at', 'Golden değerlendirme bitiş zamanı bulunamadı.', $run, $metrics);
        }

        if ($run->finished_at->lt(now()->subDays($maxAgeDays))) {
            return $this->result(
                false,
                'stale',
                "Golden değerlendirme {$maxAgeDays} günlük geçerlilik süresini aştı.",
                $run,
                $metrics
            );
        }

        if (!$run->passed_gate || (int) $run->average_score < $minimumScore) {
            return $this->result(
                false,
                'score_failed',
                "Golden değerlendirme kalite eşiğini geçmedi (skor: %{$run->average_score}, hedef: ≥%{$minimumScore}).",
                $run,
                $metrics
            );
        }

        if ($actualCaseCount < $minimumSamples || $reportedCaseCount < $minimumSamples || $actualCaseCount !== $reportedCaseCount) {
            return $this->result(
                false,
                'sample_evidence_failed',
                "Golden değerlendirme örnek kanıtı geçersiz (kayıt: {$actualCaseCount}, özet: {$reportedCaseCount}, hedef: ≥{$minimumSamples}).",
                $run,
                $metrics
            );
        }

        if (
            $reportedPassedCount === null
            || $reportedFailedCount === null
            || $reportedErrorCount === null
            || $actualPassedCount !== $reportedPassedCount
            || $actualFailedCount !== $reportedFailedCount
            || $actualErrorCount !== $reportedErrorCount
            || $actualAverageScore === null
            || abs($actualAverageScore - (int) $run->average_score) >= 1
        ) {
            return $this->result(
                false,
                'case_integrity_failed',
                'Golden değerlendirme vaka sonuçları ile özet/skor kanıtı birbiriyle tutarlı değil.',
                $run,
                $metrics
            );
        }

        if ($sourceAccuracy === null || $sourceAccuracy < $minimumSourceAccuracy) {
            $shownAccuracy = $sourceAccuracy === null ? 'yok' : '%' . round($sourceAccuracy, 2);

            return $this->result(
                false,
                'source_accuracy_failed',
                "Golden değerlendirme kaynak doğruluğu yetersiz ({$shownAccuracy}, hedef: ≥%{$minimumSourceAccuracy}).",
                $run,
                $metrics
            );
        }

        if ($criticalErrors === null || $criticalErrors !== 0) {
            $shownErrors = $criticalErrors === null ? 'yok' : (string) $criticalErrors;

            return $this->result(
                false,
                'critical_errors_failed',
                "Golden değerlendirmede kritik hata kanıtı geçersiz (kritik hata: {$shownErrors}, hedef: 0).",
                $run,
                $metrics
            );
        }

        return $this->result(
            true,
            'passed',
            "Golden değerlendirme doğrulandı (skor: %{$run->average_score}, örnek: {$actualCaseCount}, kaynak doğruluğu: %" . round($sourceAccuracy, 2) . ').',
            $run,
            $metrics
        );
    }

    /**
     * @param  array<string, int|float|null>  $metrics
     * @return array{passed: bool, code: string, detail: string, run: ?SupportAiEvalRun, metrics: array<string, int|float|null>}
     */
    private function result(
        bool $passed,
        string $code,
        string $detail,
        ?SupportAiEvalRun $run,
        array $metrics = []
    ): array {
        return compact('passed', 'code', 'detail', 'run', 'metrics');
    }
}
