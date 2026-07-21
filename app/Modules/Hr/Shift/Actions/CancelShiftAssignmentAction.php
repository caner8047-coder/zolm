<?php

namespace App\Modules\Hr\Shift\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Shift\Enums\ShiftAssignmentStatus;
use App\Modules\Hr\Shift\Models\HrShiftAssignment;
use Illuminate\Support\Facades\DB;

class CancelShiftAssignmentAction
{
    public function __construct(private HrAuditService $audit) {}

    public function execute(HrShiftAssignment $assignment, string $reason): HrShiftAssignment
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.shifts.assign'), 403);
        abort_if(blank($reason), 422, 'İptal gerekçesi zorunludur.');
        $tenantId = app(TenantContext::class)->getId();
        abort_unless($assignment->legal_entity_id === $tenantId, 422, 'Vardiya başka bir tüzel kişiliğe ait.');

        return DB::transaction(function () use ($assignment, $reason, $tenantId) {
            $assignment = HrShiftAssignment::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->lockForUpdate()->findOrFail($assignment->id);
            abort_if($assignment->status === ShiftAssignmentStatus::Cancelled, 422, 'Vardiya zaten iptal edilmiş.');
            $before = $assignment->toArray();
            $assignment->update(['status' => ShiftAssignmentStatus::Cancelled, 'cancelled_at' => now(), 'cancelled_by' => auth()->id(), 'cancellation_reason' => $reason, 'updated_by' => auth()->id()]);
            $this->audit->log('shift_assignment_cancelled', $assignment, $before, $assignment->fresh()->toArray());
            return $assignment->fresh(['employee', 'template']);
        });
    }
}
