<?php

namespace App\Modules\Hr\Performance\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Performance\Enums\PerformanceCycleStatus;
use App\Modules\Hr\Performance\Models\HrPerformanceCycle;
use Carbon\Carbon;

class CreatePerformanceCycleAction
{
    public function __construct(private HrAuditService $audit) {}

    public function execute(array $data): HrPerformanceCycle
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.performance.manage_templates'), 403);
        $start = Carbon::parse($data['starts_on'] ?? null);
        $end = Carbon::parse($data['ends_on'] ?? null);
        $evaluationStart = Carbon::parse($data['evaluation_starts_on'] ?? null);
        $evaluationEnd = Carbon::parse($data['evaluation_ends_on'] ?? null);
        abort_if(blank($data['name'] ?? null) || $end->lt($start) || $evaluationStart->lt($start) || $evaluationEnd->lt($evaluationStart) || $evaluationEnd->gt($end), 422, 'Döngü tarihleri geçersiz.');
        $threshold = (int) ($data['anonymity_threshold'] ?? 3);
        abort_if($threshold < 2 || $threshold > 20, 422, 'Anonimlik eşiği 2-20 arasında olmalıdır.');
        $cycle = HrPerformanceCycle::create([
            'legal_entity_id' => app(TenantContext::class)->getId(),
            'name' => trim($data['name']),
            'starts_on' => $start,
            'ends_on' => $end,
            'evaluation_starts_on' => $evaluationStart,
            'evaluation_ends_on' => $evaluationEnd,
            'status' => PerformanceCycleStatus::Draft,
            'anonymity_threshold' => $threshold,
            'auto_reminders' => (bool) ($data['auto_reminders'] ?? true),
            'reminder_days_before' => array_values($data['reminder_days_before'] ?? [7, 3, 1]),
            'created_by' => auth()->id(),
        ]);
        $this->audit->log('performance_cycle_created', $cycle);

        return $cycle;
    }

    public function transition(HrPerformanceCycle $cycle, PerformanceCycleStatus $status): HrPerformanceCycle
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.performance.manage_templates'), 403);
        abort_unless($cycle->legal_entity_id === app(TenantContext::class)->getId(), 404);
        $allowed = [
            PerformanceCycleStatus::Draft->value => PerformanceCycleStatus::Active,
            PerformanceCycleStatus::Active->value => PerformanceCycleStatus::Evaluation,
            PerformanceCycleStatus::Evaluation->value => PerformanceCycleStatus::Calibration,
            PerformanceCycleStatus::Calibration->value => PerformanceCycleStatus::Closed,
        ];
        abort_unless(($allowed[$cycle->status->value] ?? null) === $status, 422, 'Geçersiz döngü geçişi.');
        if ($status === PerformanceCycleStatus::Evaluation) {
            abort_unless(today()->betweenIncluded($cycle->evaluation_starts_on, $cycle->evaluation_ends_on), 422, 'Değerlendirme aşaması yalnız tanımlı tarih aralığında başlatılabilir.');
            abort_unless($cycle->evaluations()->exists(), 422, 'Değerlendirme görevi olmayan döngü başlatılamaz.');
        }
        if ($status === PerformanceCycleStatus::Calibration) {
            abort_if($cycle->evaluations()->where('status', 'draft')->exists(), 422, 'Tamamlanmamış değerlendirmeler varken kalibrasyona geçilemez.');
        }
        $from = $cycle->status;
        $cycle->update(['status' => $status]);
        $this->audit->log('performance_cycle_transitioned', $cycle, ['status' => $from->value], ['status' => $status->value]);

        return $cycle->fresh();
    }
}
