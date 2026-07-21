<?php

namespace App\Modules\Hr\Leave\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Leave\Enums\LeaveApprovalStatus;
use App\Modules\Hr\Leave\Enums\LeaveRequestStatus;
use App\Modules\Hr\Leave\Events\LeaveRejected;
use App\Modules\Hr\Leave\Models\HrLeaveApprovalStep;
use App\Modules\Hr\Leave\Models\HrLeaveRequest;
use Illuminate\Support\Facades\DB;

class RejectLeaveRequestAction
{
    public function __construct(private HrAuditService $audit) {}

    public function execute(HrLeaveRequest $request, string $comment): HrLeaveRequest
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.leaves.approve'), 403);
        abort_unless($request->legal_entity_id === app(TenantContext::class)->getId(), 422, 'İzin talebi başka bir tüzel kişiliğe ait.');
        abort_if(blank($comment), 422, 'Ret gerekçesi zorunludur.');

        $request = DB::transaction(function () use ($request, $comment) {
            $request = HrLeaveRequest::withoutGlobalScope('tenant')->lockForUpdate()->findOrFail($request->id);
            abort_unless(in_array($request->status, [LeaveRequestStatus::PendingManager, LeaveRequestStatus::PendingHr], true), 422, 'Bu talep reddedilemez.');
            $step = $request->approvalSteps()->where('status', LeaveApprovalStatus::Pending->value)->first();
            if ($step?->approver_user_id) {
                abort_unless($step->approver_user_id === auth()->id(), 403, 'Bu onay adımı size atanmamış.');
            }
            $step?->update(['status' => LeaveApprovalStatus::Rejected, 'comment' => $comment, 'decided_at' => now()]);
            $request->approvalSteps()->where('status', LeaveApprovalStatus::Pending->value)->update(['status' => LeaveApprovalStatus::Skipped]);
            $request->update(['status' => LeaveRequestStatus::Rejected]);
            $this->audit->log('leave_rejected', $request, null, ['reason' => $comment]);
            return $request->fresh(['approvalSteps']);
        });

        DB::afterCommit(fn () => event(new LeaveRejected($request->legal_entity_id, $request->id, $request->employee_id, auth()->id())));
        return $request;
    }
}
