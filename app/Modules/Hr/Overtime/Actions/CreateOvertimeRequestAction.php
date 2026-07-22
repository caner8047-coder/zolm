<?php

namespace App\Modules\Hr\Overtime\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Overtime\Enums\OvertimeRequestStatus;
use App\Modules\Hr\Overtime\Models\HrOvertimeRequest;
use App\Modules\Hr\Overtime\Models\HrOvertimeType;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Payroll\Services\PayrollSourceStalenessService;
use Carbon\Carbon;

class CreateOvertimeRequestAction
{
    public function __construct(private HrAuditService $audit, private PayrollSourceStalenessService $staleness) {}

    public function execute(HrEmployee $employee, HrOvertimeType $type, string $workDate, string $startsAt, string $endsAt, string $reason, ?string $projectReference = null, ?string $productionOrderReference = null): HrOvertimeRequest
    {
        $tenantId = app(TenantContext::class)->getId();
        $employee->refresh();
        $type->refresh();
        $isOwn = $employee->user_id === auth()->id();
        abort_unless($isOwn || auth()->user()?->hasHrPermission('hr.timesheet.confirm'), 403);
        abort_unless($employee->legal_entity_id === $tenantId && $type->legal_entity_id === $tenantId && $type->is_active, 422, 'Çalışan veya fazla mesai türü geçersiz.');
        abort_if(blank($reason), 422, 'Fazla mesai gerekçesi zorunludur.');
        $start = Carbon::parse($workDate.' '.$startsAt);
        $end = Carbon::parse($workDate.' '.$endsAt);
        if ($end->lte($start)) $end->addDay();
        $minutes = $start->diffInMinutes($end);
        abort_if($minutes < 15 || $minutes > 16 * 60, 422, 'Fazla mesai süresi 15 dakika ile 16 saat arasında olmalıdır.');
        $overlap = HrOvertimeRequest::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('employee_id', $employee->id)->whereDate('work_date', $workDate)->whereIn('status', [OvertimeRequestStatus::PendingManager->value, OvertimeRequestStatus::PendingHr->value, OvertimeRequestStatus::Approved->value])->where(function ($q) use ($startsAt, $endsAt) { $q->where('starts_at', '<', $endsAt)->where('ends_at', '>', $startsAt); })->exists();
        abort_if($overlap, 422, 'Bu saat aralığında başka bir fazla mesai talebi var.');
        if ($type->annual_limit_minutes) {
            $used = HrOvertimeRequest::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('employee_id', $employee->id)->where('overtime_type_id', $type->id)->whereYear('work_date', Carbon::parse($workDate)->year)->where('status', OvertimeRequestStatus::Approved->value)->sum('approved_minutes');
            abort_if($used + $minutes > $type->annual_limit_minutes, 422, 'Yıllık fazla mesai limiti aşılır.');
        }
        $status = $type->requires_approval ? OvertimeRequestStatus::PendingManager : OvertimeRequestStatus::Approved;
        $request = HrOvertimeRequest::create(['legal_entity_id' => $tenantId, 'employee_id' => $employee->id, 'overtime_type_id' => $type->id, 'work_date' => $workDate, 'starts_at' => $startsAt, 'ends_at' => $endsAt, 'requested_minutes' => $minutes, 'approved_minutes' => $status === OvertimeRequestStatus::Approved ? $minutes : null, 'status' => $status, 'reason' => trim($reason), 'project_reference' => $projectReference ?: null, 'production_order_reference' => $productionOrderReference ?: null, 'requested_by' => auth()->id(), 'decided_by' => $status === OvertimeRequestStatus::Approved ? auth()->id() : null, 'decided_at' => $status === OvertimeRequestStatus::Approved ? now() : null]);
        if ($status === OvertimeRequestStatus::Approved) {
            $this->staleness->markForWorkDate($tenantId, $workDate, 'overtime_approved', 'Bordro dönemine doğrudan onaylı fazla mesai eklendi.', $employee->id);
        }
        $this->audit->log('overtime_requested', $request, null, ['minutes' => $minutes, 'status' => $status->value]);
        return $request;
    }
}
