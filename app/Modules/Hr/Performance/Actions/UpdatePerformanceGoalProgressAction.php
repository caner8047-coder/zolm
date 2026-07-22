<?php

namespace App\Modules\Hr\Performance\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Performance\Models\HrPerformanceGoal;
use App\Modules\Hr\Performance\Models\HrPerformanceGoalCheckIn;
use Illuminate\Support\Facades\DB;

class UpdatePerformanceGoalProgressAction
{
    public function __construct(private HrAuditService $audit) {}

    public function execute(HrPerformanceGoal $goal, float|string $value, string $note = 'İlerleme güncellendi.', ?string $evidence = null): HrPerformanceGoal
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.performance.manage_goals'), 403);
        abort_unless($goal->legal_entity_id === app(TenantContext::class)->getId(), 404);
        abort_unless($goal->status === 'active' && in_array($goal->cycle?->status->value, ['active', 'evaluation'], true), 422, 'Bu hedefin ilerlemesi güncellenemez.');
        abort_if(blank($note), 422, 'Check-in notu zorunludur.');

        return DB::transaction(function () use ($goal, $value, $note, $evidence) {
            $before = ['current_value' => $goal->current_value, 'current_text' => $goal->current_text];
            if ($goal->measurement_type === 'text') {
                abort_if(blank($value), 422, 'Sözel ilerleme açıklaması zorunludur.');
                $goal->update(['current_text' => trim((string) $value)]);
            } else {
                abort_unless(is_numeric($value), 422, 'Güncel değer sayısal olmalıdır.');
                $goal->update(['current_value' => round((float) $value, 2)]);
            }
            HrPerformanceGoalCheckIn::create([
                'legal_entity_id' => $goal->legal_entity_id, 'goal_id' => $goal->id,
                'previous_value' => $before['current_value'], 'new_value' => $goal->current_value,
                'previous_text' => $before['current_text'], 'new_text' => $goal->current_text,
                'note' => trim($note), 'evidence' => $evidence ? trim($evidence) : null, 'created_by' => auth()->id(),
            ]);
            $this->audit->log('performance_goal_progress_updated', $goal, $before, ['current_value' => $goal->current_value, 'current_text' => $goal->current_text]);

            return $goal->fresh();
        });
    }
}
