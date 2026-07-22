<?php

namespace App\Modules\Hr\Performance\Services;

use App\Modules\Hr\Performance\Enums\PerformanceEvaluationStatus;
use App\Modules\Hr\Performance\Models\HrPerformanceEvaluation;
use App\Modules\Hr\Performance\Models\HrPerformanceResult;
use App\Modules\Hr\Performance\Models\HrPerformanceCycle;

class PerformanceResultService
{
    public function recalculate(int $legalEntityId, int $cycleId, int $employeeId): HrPerformanceResult
    {
        $evaluations = HrPerformanceEvaluation::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $legalEntityId)
            ->where('cycle_id', $cycleId)
            ->where('employee_id', $employeeId)
            ->get();
        $completed = $evaluations->filter(fn ($evaluation) => in_array($evaluation->status, [
            PerformanceEvaluationStatus::Submitted,
            PerformanceEvaluationStatus::Calibrated,
        ], true));

        $cycle = HrPerformanceCycle::withoutGlobalScope('tenant')->findOrFail($cycleId);
        $weightedScore = 0.0;
        $weightTotal = 0.0;
        $breakdown = [];
        foreach ($completed->groupBy(fn ($evaluation) => $evaluation->reviewer_type->value) as $type => $rows) {
            $anonymous = $rows->contains(fn ($evaluation) => $evaluation->is_anonymous);
            if ($anonymous && $rows->count() < $cycle->anonymity_threshold) {
                $breakdown[$type] = ['count' => $rows->count(), 'score' => null, 'withheld' => true];
                continue;
            }
            $typeScore = $rows->avg(fn ($evaluation) => (float) ($evaluation->calibrated_score ?? $evaluation->overall_score));
            $typeWeight = (float) $rows->max('reviewer_weight');
            $weightedScore += $typeScore * $typeWeight;
            $weightTotal += $typeWeight;
            $breakdown[$type] = ['count' => $rows->count(), 'score' => round($typeScore, 2), 'withheld' => false];
        }

        $payload = [
            'cycle_id' => $cycleId,
            'employee_id' => $employeeId,
            'expected' => $evaluations->count(),
            'completed' => $completed->count(),
            'score' => $weightTotal > 0 ? round($weightedScore / $weightTotal, 2) : null,
            'breakdown' => $breakdown,
        ];

        return HrPerformanceResult::withoutGlobalScope('tenant')->updateOrCreate(
            ['cycle_id' => $cycleId, 'employee_id' => $employeeId],
            [
                'legal_entity_id' => $legalEntityId,
                'overall_score' => $payload['score'],
                'expected_responses' => $payload['expected'],
                'completed_responses' => $payload['completed'],
                'status' => $payload['expected'] > 0 && $payload['completed'] === $payload['expected'] ? 'complete' : 'in_progress',
                'reviewer_breakdown' => $breakdown,
                'calculation_hash' => hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION)),
                'calculated_at' => now(),
            ],
        );
    }
}
