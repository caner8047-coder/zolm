<?php

namespace App\Modules\Hr\Leave\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Leave\Enums\LeaveRequestStatus;
use App\Modules\Hr\Leave\Enums\LeaveTransactionType;
use App\Modules\Hr\Leave\Events\LeaveCancelled;
use App\Modules\Hr\Leave\Models\HrLeaveRequest;
use App\Modules\Hr\Leave\Services\LeaveBalanceService;
use Illuminate\Support\Facades\DB;

class CancelLeaveRequestAction
{
    public function __construct(private LeaveBalanceService $balances, private HrAuditService $audit) {}

    public function execute(HrLeaveRequest $request, string $reason): HrLeaveRequest
    {
        $user = auth()->user();
        abort_unless($user && ($user->hasHrPermission('hr.leaves.cancel_any') || $user->hasHrPermission('hr.leaves.cancel_own')), 403);
        abort_unless($request->legal_entity_id === app(TenantContext::class)->getId(), 422, 'İzin talebi başka bir tüzel kişiliğe ait.');
        abort_if(blank($reason), 422, 'İptal gerekçesi zorunludur.');

        $request = DB::transaction(function () use ($request, $reason, $user) {
            $request = HrLeaveRequest::withoutGlobalScope('tenant')->lockForUpdate()->findOrFail($request->id);
            $isOwn = $request->employee->user_id === $user->id;
            abort_unless($user->hasHrPermission('hr.leaves.cancel_any') || ($isOwn && $user->hasHrPermission('hr.leaves.cancel_own')), 403);
            abort_unless(in_array($request->status, [LeaveRequestStatus::PendingManager, LeaveRequestStatus::PendingHr, LeaveRequestStatus::Approved], true), 422, 'Bu talep iptal edilemez.');

            if ($request->status === LeaveRequestStatus::Approved && $request->leaveType->is_paid) {
                $this->balances->record($request->employee, $request->leaveType, LeaveTransactionType::Cancellation, (float) $request->requested_amount, HrLeaveRequest::class . ':cancel', $request->id, $request->start_date->year, $request, 'İptal edilen izin iadesi');
            }
            $request->update(['status' => LeaveRequestStatus::Cancelled, 'cancelled_by' => $user->id, 'cancelled_at' => now(), 'cancellation_reason' => $reason]);
            $this->audit->log('leave_cancelled', $request, null, ['reason' => $reason]);
            return $request->fresh(['approvalSteps']);
        });

        DB::afterCommit(fn () => event(new LeaveCancelled($request->legal_entity_id, $request->id, $request->employee_id, auth()->id())));
        return $request;
    }
}
