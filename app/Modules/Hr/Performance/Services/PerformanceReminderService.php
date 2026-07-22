<?php

namespace App\Modules\Hr\Performance\Services;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Performance\Models\HrPerformanceCycle;
use App\Modules\Hr\Performance\Notifications\PerformanceEvaluationReminderNotification;

class PerformanceReminderService
{
    public function __construct(private HrAuditService $audit) {}

    public function send(HrPerformanceCycle $cycle, bool $force = false): int
    {
        $evaluations = $cycle->evaluations()->where('status', 'draft')->with(['reviewer.user', 'cycle'])->get();
        $sent = 0;
        foreach ($evaluations as $evaluation) {
            if (! $evaluation->reviewer?->user) continue;
            if (! $force && $evaluation->last_reminded_at?->isToday()) continue;
            $evaluation->reviewer->user->notify(new PerformanceEvaluationReminderNotification($evaluation));
            $evaluation->update(['reminder_count' => $evaluation->reminder_count + 1, 'last_reminded_at' => now()]);
            $sent++;
        }

        if ($sent > 0) $this->audit->log('performance_automatic_reminders_sent', $cycle, null, ['sent_count' => $sent]);

        return $sent;
    }
}
