<?php

namespace App\Modules\Hr\Performance\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Performance\Models\HrPerformanceGoal;

class UpdatePerformanceGoalProgressAction
{
    public function __construct(private HrAuditService $audit) {}

    public function execute(HrPerformanceGoal $goal, float $currentValue): HrPerformanceGoal
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.performance.manage_goals'), 403);
        abort_unless($goal->legal_entity_id === app(TenantContext::class)->getId(), 404);
        abort_unless($goal->status === 'active', 422, 'Yalnızca aktif hedeflerin ilerlemesi güncellenebilir.');

        $before = ['current_value' => $goal->current_value];
        $goal->update(['current_value' => round($currentValue, 2)]);
        $this->audit->log('performance_goal_progress_updated', $goal, $before, ['current_value' => $goal->current_value]);

        return $goal->fresh();
    }
}
