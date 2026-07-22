<?php

namespace App\Modules\Hr\Performance\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Performance\Enums\PerformanceCycleStatus;
use App\Modules\Hr\Performance\Enums\PerformanceEvaluationStatus;
use App\Modules\Hr\Performance\Models\HrPerformanceEvaluation;
use App\Modules\Hr\Performance\Services\PerformanceQuestionnaireService;
use App\Modules\Hr\Performance\Services\PerformanceResultService;
use Illuminate\Support\Facades\DB;

class SubmitPerformanceEvaluationAction
{
    public function __construct(
        private HrAuditService $audit,
        private PerformanceQuestionnaireService $questionnaire,
        private PerformanceResultService $results,
    ) {}

    public function execute(HrPerformanceEvaluation $evaluation, array $answers): HrPerformanceEvaluation
    {
        abort_unless($evaluation->legal_entity_id === app(TenantContext::class)->getId(), 404);
        $evaluation->loadMissing(['reviewer', 'template', 'cycle']);
        abort_unless($evaluation->reviewer?->user_id === auth()->id(), 403, 'Yalnız atanmış değerlendirici yanıt gönderebilir.');
        abort_unless($evaluation->cycle->status === PerformanceCycleStatus::Evaluation, 422, 'Döngü değerlendirme aşamasında değil.');
        abort_unless(today()->betweenIncluded($evaluation->cycle->evaluation_starts_on, $evaluation->cycle->evaluation_ends_on), 422, 'Değerlendirme tarih aralığı dışında yanıt gönderilemez.');

        return DB::transaction(function () use ($evaluation, $answers) {
            $row = HrPerformanceEvaluation::withoutGlobalScope('tenant')->whereKey($evaluation->id)->lockForUpdate()->firstOrFail();
            abort_unless($row->status === PerformanceEvaluationStatus::Draft, 422, 'Gönderilmiş değerlendirme değiştirilemez.');
            $row->load('template');
            $result = $this->questionnaire->evaluate($row->template->sections, $answers);
            $row->update([
                'answers' => $result['answers'], 'overall_score' => $result['score'],
                'status' => PerformanceEvaluationStatus::Submitted, 'submitted_at' => now(),
            ]);
            $this->results->recalculate($row->legal_entity_id, $row->cycle_id, $row->employee_id);
            $this->audit->log('performance_evaluation_submitted', $row, null, [
                'overall_score' => $result['score'], 'reviewer_type' => $row->reviewer_type->value,
            ]);

            return $row->fresh();
        });
    }
}
