<?php

namespace App\Modules\Hr\Performance\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Performance\Models\HrPerformanceCycle;
use App\Modules\Hr\Performance\Models\HrPerformanceGoal;
use App\Modules\Hr\Personnel\Models\HrEmployee;

class CreatePerformanceGoalAction
{
    public function __construct(private HrAuditService $audit) {}

    public function execute(HrPerformanceCycle $cycle, HrEmployee $employee, array $data): HrPerformanceGoal
    {
        $tenant = app(TenantContext::class)->getId();
        abort_unless(auth()->user()?->hasHrPermission('hr.performance.manage_goals'), 403);
        abort_unless($cycle->legal_entity_id === $tenant && $employee->legal_entity_id === $tenant, 404);
        abort_unless(in_array($cycle->status->value, ['draft', 'active'], true), 422, 'Hedefler yalnız taslak veya hedef döneminde tanımlanabilir.');
        $weight = round((float) ($data['weight'] ?? 0), 2);
        $measurementType = (string) ($data['measurement_type'] ?? 'numeric');
        abort_unless(in_array($measurementType, ['numeric', 'text'], true), 422, 'Hedef ölçüm tipi geçersiz.');
        abort_if(blank($data['title'] ?? null) || $weight <= 0 || $weight > 100, 422, 'Hedef adı ve ağırlığı zorunludur.');
        abort_if($measurementType === 'numeric' && (blank($data['metric_unit'] ?? null) || ! is_numeric($data['target_value'] ?? null)), 422, 'Sayısal hedef için birim ve hedef değer zorunludur.');
        abort_if($measurementType === 'text' && blank($data['target_text'] ?? null), 422, 'Sözel hedef için başarı tanımı zorunludur.');
        $total = (float) HrPerformanceGoal::withoutGlobalScope('tenant')->where('cycle_id', $cycle->id)->where('employee_id', $employee->id)->where('status', 'active')->sum('weight');
        abort_if($total + $weight > 100.001, 422, 'Çalışanın hedef ağırlıkları 100 değerini aşamaz.');
        $typeValue = $data['type'] ?? 'kpi';
        $type = in_array($typeValue, ['kpi', 'okr'], true) ? $typeValue : 'kpi';
        $goal = HrPerformanceGoal::create([
            'legal_entity_id' => $tenant, 'cycle_id' => $cycle->id, 'employee_id' => $employee->id,
            'type' => $type, 'measurement_type' => $measurementType, 'title' => trim($data['title']),
            'description' => $data['description'] ?? null, 'metric_unit' => $measurementType === 'numeric' ? trim((string) $data['metric_unit']) : 'sözel',
            'baseline_value' => $measurementType === 'numeric' ? ($data['baseline_value'] ?? 0) : 0,
            'target_value' => $measurementType === 'numeric' ? $data['target_value'] : 0,
            'target_text' => $measurementType === 'text' ? trim((string) $data['target_text']) : null,
            'current_value' => $measurementType === 'numeric' ? ($data['current_value'] ?? 0) : 0,
            'current_text' => $measurementType === 'text' ? ($data['current_text'] ?? null) : null,
            'weight' => $weight, 'status' => 'active', 'created_by' => auth()->id(),
        ]);
        $this->audit->log('performance_goal_created', $goal);

        return $goal;
    }
}
