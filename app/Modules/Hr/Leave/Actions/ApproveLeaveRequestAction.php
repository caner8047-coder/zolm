<?php

namespace App\Modules\Hr\Leave\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Leave\Enums\LeaveApprovalStatus;
use App\Modules\Hr\Leave\Enums\LeaveRequestStatus;
use App\Modules\Hr\Leave\Enums\LeaveTransactionType;
use App\Modules\Hr\Leave\Events\LeaveApproved;
use App\Modules\Hr\Leave\Models\HrLeaveApprovalStep;
use App\Modules\Hr\Leave\Models\HrLeaveRequest;
use App\Modules\Hr\Leave\Services\LeaveBalanceService;
use Illuminate\Support\Facades\DB;

class ApproveLeaveRequestAction
{
    public function __construct(private LeaveBalanceService $balances, private HrAuditService $audit) {}

    public function execute(HrLeaveRequest $request, ?string $comment = null): HrLeaveRequest
    {
        $tenantId = app(TenantContext::class)->getId();
        abort_unless($request->legal_entity_id === $tenantId, 422, 'İzin talebi başka bir tüzel kişiliğe ait.');
        abort_unless(auth()->user()?->hasHrPermission('hr.leaves.approve'), 403);

        $request = DB::transaction(function () use ($request, $comment, $tenantId) {
            $request = HrLeaveRequest::withoutGlobalScope('tenant')->lockForUpdate()->findOrFail($request->id);
            abort_unless(in_array($request->status, [LeaveRequestStatus::PendingManager, LeaveRequestStatus::PendingHr], true), 422, 'Bu talep onay beklemiyor.');

            $step = HrLeaveApprovalStep::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('leave_request_id', $request->id)->where('status', LeaveApprovalStatus::Pending)->orderBy('step_order')->first();
            if ($step && $step->approver_user_id) {
                abort_unless($step->approver_user_id === auth()->id(), 403, 'Bu onay adımı size atanmamış.');
            }
            if ($step) {
                $step->update(['status' => LeaveApprovalStatus::Approved, 'comment' => $comment, 'decided_at' => now()]);
            }

            $next = HrLeaveApprovalStep::withoutGlobalScope('tenant')->where('leave_request_id', $request->id)->where('status', LeaveApprovalStatus::Pending)->orderBy('step_order')->first();
            if ($next) {
                $request->update(['status' => $next->approver_type === 'hr' ? LeaveRequestStatus::PendingHr : LeaveRequestStatus::PendingManager]);
                return $request->fresh(['approvalSteps']);
            }

            $leaveType = $request->leaveType;
            $policy = $request->policy;
            $balance = $this->balances->balanceFor($request->employee, $leaveType, $request->start_date->year);
            $allowsNegative = $leaveType->allows_negative_balance || ($policy?->allows_negative_balance ?? false);
            abort_if(!$allowsNegative && (float) $balance->remaining_amount < (float) $request->requested_amount, 422, 'Yetersiz izin bakiyesi.');

            if ($leaveType->is_paid) {
                $this->balances->record($request->employee, $leaveType, LeaveTransactionType::Usage, -1 * (float) $request->requested_amount, HrLeaveRequest::class, $request->id, $request->start_date->year, $request, 'Onaylanan izin kullanımı');
            }
            $request->update(['status' => LeaveRequestStatus::Approved]);
            $this->audit->log('leave_approved', $request);
            return $request->fresh(['approvalSteps']);
        });

        if ($request->status === LeaveRequestStatus::Approved) {
            DB::afterCommit(fn () => event(new LeaveApproved($request->legal_entity_id, $request->id, $request->employee_id, auth()->id())));
        }
        return $request;
    }
}
