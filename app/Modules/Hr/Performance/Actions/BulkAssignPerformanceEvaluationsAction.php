<?php

namespace App\Modules\Hr\Performance\Actions;

use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Performance\Enums\ReviewerType;
use App\Modules\Hr\Performance\Models\HrPerformanceCycle;
use App\Modules\Hr\Performance\Models\HrPerformanceTemplate;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Personnel\Models\HrEmploymentRecord;

class BulkAssignPerformanceEvaluationsAction
{
    public function __construct(private CreateEvaluationAssignmentAction $assign) {}

    public function execute(HrPerformanceCycle $cycle, HrPerformanceTemplate $template, string $scope, ?int $scopeId, array $types): int
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.performance.manage_templates'), 403);
        abort_unless(in_array($scope, ['company', 'department', 'unit', 'team'], true), 422, 'Organizasyon kapsamı geçersiz.');
        $tenant = app(TenantContext::class)->getId();
        $records = HrEmploymentRecord::withoutGlobalScope('tenant')->where('legal_entity_id', $tenant)->current()
            ->when($scope !== 'company', fn ($query) => $query->where($scope.'_id', $scopeId))
            ->with(['employee', 'manager'])->get();
        $created = 0;
        foreach ($records as $record) {
            $employee = $record->employee;
            if (! $employee) continue;
            foreach ($types as $typeValue) {
                $type = ReviewerType::from($typeValue);
                if ($type === ReviewerType::Self) {
                    $created += $this->create($cycle, $template, $employee, $employee, $type, 20, false);
                } elseif ($type === ReviewerType::Manager && $record->manager) {
                    $created += $this->create($cycle, $template, $employee, $record->manager, $type, 40, false);
                } elseif ($type === ReviewerType::DirectReport) {
                    $reports = HrEmploymentRecord::withoutGlobalScope('tenant')->where('legal_entity_id', $tenant)->current()->where('manager_employee_id', $employee->id)->with('employee')->get();
                    foreach ($reports as $report) if ($report->employee) $created += $this->create($cycle, $template, $employee, $report->employee, $type, 20, true);
                } elseif ($type === ReviewerType::Peer && $record->team_id) {
                    $peers = HrEmploymentRecord::withoutGlobalScope('tenant')->where('legal_entity_id', $tenant)->current()->where('team_id', $record->team_id)->where('employee_id', '!=', $employee->id)->with('employee')->limit(5)->get();
                    foreach ($peers as $peer) if ($peer->employee) $created += $this->create($cycle, $template, $employee, $peer->employee, $type, 20, true);
                }
            }
        }

        return $created;
    }

    private function create(HrPerformanceCycle $cycle, HrPerformanceTemplate $template, HrEmployee $employee, HrEmployee $reviewer, ReviewerType $type, float $weight, bool $anonymous): int
    {
        return $this->assign->execute($cycle, $template, $employee, $reviewer, $type, $weight, $anonymous)->wasRecentlyCreated ? 1 : 0;
    }
}
