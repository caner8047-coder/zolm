<?php

namespace App\Modules\Hr\Leave\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Document\Models\HrEmployeeDocument;
use App\Modules\Hr\Leave\Enums\LeaveRequestStatus;
use App\Modules\Hr\Leave\Events\LeaveRequested;
use App\Modules\Hr\Leave\Models\HrLeaveApprovalStep;
use App\Modules\Hr\Leave\Models\HrLeaveRequest;
use App\Modules\Hr\Leave\Models\HrLeaveType;
use App\Modules\Hr\Leave\Services\LeaveDurationCalculator;
use App\Modules\Hr\Leave\Services\LeavePolicyResolver;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CreateLeaveRequestAction
{
    public function __construct(private LeavePolicyResolver $policyResolver, private LeaveDurationCalculator $durationCalculator, private HrAuditService $audit) {}

    public function execute(HrEmployee $employee, HrLeaveType $leaveType, array $data): HrLeaveRequest
    {
        $tenantId = app(TenantContext::class)->getId();
        abort_unless($employee->legal_entity_id === $tenantId, 422, 'Çalışan başka bir tüzel kişiliğe ait.');
        $leaveType = HrLeaveType::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->where('id', $leaveType->id)
            ->where('is_active', true)
            ->firstOrFail();

        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end = Carbon::parse($data['end_date'])->startOfDay();
        $amount = $this->durationCalculator->calculate($leaveType->unit, $start, $end, $data['start_time'] ?? null, $data['end_time'] ?? null);
        abort_if($amount <= 0, 422, 'İzin süresi en az bir çalışma birimi olmalıdır.');

        $overlaps = HrLeaveRequest::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('employee_id', $employee->id)
            ->whereIn('status', [LeaveRequestStatus::PendingManager->value, LeaveRequestStatus::PendingHr->value, LeaveRequestStatus::Approved->value])
            ->whereDate('start_date', '<=', $end)->whereDate('end_date', '>=', $start)->exists();
        abort_if($overlaps, 422, 'Bu tarih aralığında mevcut bir izin talebi var.');

        if ($leaveType->requires_document) {
            $documentId = (int) ($data['document_id'] ?? 0);
            $documentExists = HrEmployeeDocument::withoutGlobalScope('tenant')->where('id', $documentId)->where('legal_entity_id', $tenantId)->where('employee_id', $employee->id)->exists();
            abort_unless($documentExists, 422, 'Bu izin türü için çalışana ait geçerli belge zorunludur.');
        }

        $policy = $this->policyResolver->resolve($employee, $leaveType, $start);
        abort_unless($policy, 422, 'Çalışan için geçerli izin politikası bulunamadı.');

        $request = DB::transaction(function () use ($employee, $leaveType, $data, $policy, $amount, $tenantId, $start, $end) {
            $manager = $employee->activeEmployment?->manager?->user;
            // Yönetici kaydı yoksa talep İK kuyruğuna düşer; bakiye hareketi
            // insan onayı olmadan asla oluşmaz.
            $status = $manager ? LeaveRequestStatus::PendingManager : LeaveRequestStatus::PendingHr;
            $request = HrLeaveRequest::create([
                'legal_entity_id' => $tenantId, 'employee_id' => $employee->id, 'leave_type_id' => $leaveType->id, 'policy_id' => $policy->id,
                'status' => $status, 'start_date' => $start, 'end_date' => $end, 'start_time' => $data['start_time'] ?? null,
                'end_time' => $data['end_time'] ?? null, 'requested_amount' => $amount, 'unit' => $leaveType->unit,
                'reason' => $data['reason'] ?? null, 'document_id' => $data['document_id'] ?? null, 'delegate_employee_id' => $data['delegate_employee_id'] ?? null,
            ]);

            if ($manager) {
                HrLeaveApprovalStep::create(['legal_entity_id' => $tenantId, 'leave_request_id' => $request->id, 'step_order' => 1, 'approver_type' => 'manager', 'approver_employee_id' => $manager->id, 'approver_user_id' => $manager->user_id]);
            }
            if ($policy->requires_hr_approval || !$manager) {
                HrLeaveApprovalStep::create(['legal_entity_id' => $tenantId, 'leave_request_id' => $request->id, 'step_order' => $manager ? 2 : 1, 'approver_type' => 'hr']);
            }
            $this->audit->log('leave_requested', $request);
            return $request;
        });

        DB::afterCommit(fn () => event(new LeaveRequested($request->legal_entity_id, $request->id, $request->employee_id, auth()->id())));
        return $request->fresh(['approvalSteps']);
    }
}
