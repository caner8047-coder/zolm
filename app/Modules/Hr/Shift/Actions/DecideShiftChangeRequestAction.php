<?php

namespace App\Modules\Hr\Shift\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Leave\Enums\LeaveRequestStatus;
use App\Modules\Hr\Leave\Models\HrLeaveRequest;
use App\Modules\Hr\Shift\Enums\ShiftAssignmentStatus;
use App\Modules\Hr\Shift\Enums\ShiftAvailabilityStatus;
use App\Modules\Hr\Shift\Enums\ShiftChangeRequestStatus;
use App\Modules\Hr\Shift\Models\HrShiftAssignment;
use App\Modules\Hr\Shift\Models\HrShiftAvailability;
use App\Modules\Hr\Shift\Models\HrShiftChangeRequest;
use Illuminate\Support\Facades\DB;

class DecideShiftChangeRequestAction
{
    public function __construct(private HrAuditService $audit) {}

    public function approve(HrShiftChangeRequest $request, ?string $note = null): HrShiftChangeRequest
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.shifts.plan'), 403);
        $tenantId = app(TenantContext::class)->getId();
        return DB::transaction(function () use ($request, $note, $tenantId) {
            $request = HrShiftChangeRequest::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->lockForUpdate()->findOrFail($request->id);
            abort_unless($request->status === ShiftChangeRequestStatus::Pending, 422, 'Talep daha önce sonuçlandırılmış.');
            $request->loadMissing('desiredTemplate');
            abort_unless($request->desiredTemplate && $request->desiredTemplate->legal_entity_id === $tenantId && $request->desiredTemplate->is_active, 422, 'İstenen vardiya şablonu artık aktif değil.');
            $assignment = HrShiftAssignment::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->lockForUpdate()->findOrFail($request->shift_assignment_id);
            $date = $request->desired_shift_date->toDateString();
            $conflict = HrShiftAssignment::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('employee_id', $request->employee_id)->whereDate('shift_date', $date)->whereKeyNot($assignment->id)->where('status', '!=', ShiftAssignmentStatus::Cancelled->value)->exists();
            abort_if($conflict, 422, 'İstenen tarihte başka vardiya bulunuyor.');
            $onLeave = HrLeaveRequest::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('employee_id', $request->employee_id)->where('status', LeaveRequestStatus::Approved->value)->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)->exists();
            abort_if($onLeave, 422, 'İstenen tarihte onaylı izin bulunuyor.');
            $unavailable = HrShiftAvailability::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('employee_id', $request->employee_id)->whereDate('availability_date', $date)->where('status', ShiftAvailabilityStatus::Unavailable->value)->exists();
            abort_if($unavailable, 422, 'Çalışan istenen tarihte müsait değil.');
            $assignment->update(['shift_template_id' => $request->desired_shift_template_id, 'shift_date' => $date, 'status' => ShiftAssignmentStatus::Planned, 'published_at' => null, 'published_by' => null, 'cancelled_at' => null, 'cancelled_by' => null, 'cancellation_reason' => null, 'updated_by' => auth()->id()]);
            $request->update(['status' => ShiftChangeRequestStatus::Approved, 'decision_note' => $note, 'decided_by' => auth()->id(), 'decided_at' => now()]);
            $this->audit->log('shift_change_approved', $request);
            return $request->fresh(['assignment.template', 'desiredTemplate']);
        });
    }

    public function reject(HrShiftChangeRequest $request, string $note): HrShiftChangeRequest
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.shifts.plan'), 403);
        abort_if(blank($note), 422, 'Ret gerekçesi zorunludur.');
        $tenantId = app(TenantContext::class)->getId();
        return DB::transaction(function () use ($request, $note, $tenantId) {
            $request = HrShiftChangeRequest::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->lockForUpdate()->findOrFail($request->id);
            abort_unless($request->status === ShiftChangeRequestStatus::Pending, 422, 'Talep daha önce sonuçlandırılmış.');
            $request->update(['status' => ShiftChangeRequestStatus::Rejected, 'decision_note' => $note, 'decided_by' => auth()->id(), 'decided_at' => now()]);
            $this->audit->log('shift_change_rejected', $request);
            return $request->fresh();
        });
    }
}
