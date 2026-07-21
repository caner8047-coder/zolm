<?php

namespace App\Modules\Hr\Shift\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Shift\Enums\ShiftAssignmentStatus;
use App\Modules\Hr\Shift\Enums\ShiftChangeRequestStatus;
use App\Modules\Hr\Shift\Models\HrShiftAssignment;
use App\Modules\Hr\Shift\Models\HrShiftChangeRequest;
use App\Modules\Hr\Shift\Models\HrShiftTemplate;
use Illuminate\Support\Facades\DB;

class CreateShiftChangeRequestAction
{
    public function __construct(private HrAuditService $audit) {}

    public function execute(HrShiftAssignment $assignment, HrShiftTemplate $desiredTemplate, string $desiredDate, string $reason): HrShiftChangeRequest
    {
        $tenantId = app(TenantContext::class)->getId(); $user = auth()->user();
        $assignment->loadMissing('employee');
        $desiredTemplate->refresh();
        $isOwn = $assignment->employee?->user_id === $user?->id;
        abort_unless($user && ($isOwn || $user->hasHrPermission('hr.shifts.manage')), 403);
        abort_unless($assignment->legal_entity_id === $tenantId && $desiredTemplate->legal_entity_id === $tenantId && $desiredTemplate->is_active, 422, 'Vardiya veya şablon geçersiz.');
        abort_if($assignment->status === ShiftAssignmentStatus::Cancelled, 422, 'İptal edilmiş vardiya değiştirilemez.');
        abort_if(blank($reason), 422, 'Değişiklik gerekçesi zorunludur.');
        return DB::transaction(function () use ($assignment, $desiredTemplate, $desiredDate, $reason, $user, $tenantId) {
            $assignment = HrShiftAssignment::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->lockForUpdate()->findOrFail($assignment->id);
            abort_if(HrShiftChangeRequest::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('shift_assignment_id', $assignment->id)->where('status', ShiftChangeRequestStatus::Pending->value)->exists(), 422, 'Bu vardiya için bekleyen talep var.');
            $request = HrShiftChangeRequest::create(['legal_entity_id' => $tenantId, 'employee_id' => $assignment->employee_id, 'shift_assignment_id' => $assignment->id, 'desired_shift_template_id' => $desiredTemplate->id, 'desired_shift_date' => $desiredDate, 'status' => ShiftChangeRequestStatus::Pending, 'reason' => $reason, 'created_by' => $user->id]);
            $this->audit->log('shift_change_requested', $request);
            return $request;
        });
    }
}
