<?php

namespace App\Modules\Hr\Attendance\Actions;

use App\Modules\Hr\Attendance\Models\HrAttendanceAnomaly;
use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;

class ResolveAttendanceAnomalyAction
{
    public function __construct(private HrAuditService $audit) {}

    public function execute(HrAttendanceAnomaly $anomaly, string $note): HrAttendanceAnomaly
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.attendance.manage'), 403);
        abort_unless($anomaly->legal_entity_id === app(TenantContext::class)->getId(), 404);
        abort_if(blank($note), 422, 'Çözüm notu zorunludur.');

        $anomaly->update([
            'status' => 'resolved',
            'resolution_note' => trim($note),
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
        ]);
        $this->audit->log('attendance_anomaly_resolved', $anomaly, null, ['resolution_note' => $note]);

        return $anomaly->fresh();
    }
}
