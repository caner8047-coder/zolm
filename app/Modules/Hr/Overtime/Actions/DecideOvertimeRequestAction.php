<?php

namespace App\Modules\Hr\Overtime\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Overtime\Enums\OvertimeRequestStatus;
use App\Modules\Hr\Overtime\Models\HrOvertimeRequest;
use App\Modules\Hr\Payroll\Services\PayrollSourceStalenessService;
use Illuminate\Support\Facades\DB;

class DecideOvertimeRequestAction
{
    public function __construct(private HrAuditService $audit, private PayrollSourceStalenessService $staleness) {}

    public function approve(HrOvertimeRequest $request, ?int $approvedMinutes = null, ?string $note = null): HrOvertimeRequest
    {
        $this->authorize($request);
        return DB::transaction(function () use ($request, $approvedMinutes, $note) {
            $locked = HrOvertimeRequest::withoutGlobalScope('tenant')->whereKey($request->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($locked->status, [OvertimeRequestStatus::PendingManager, OvertimeRequestStatus::PendingHr], true), 422, 'Bu talep artık beklemede değil.');
            $next = $locked->status === OvertimeRequestStatus::PendingManager ? OvertimeRequestStatus::PendingHr : OvertimeRequestStatus::Approved;
            $minutes = $approvedMinutes ?? $locked->requested_minutes;
            abort_if($minutes < 1 || $minutes > $locked->requested_minutes, 422, 'Onaylanan süre talep süresini aşamaz.');
            $locked->update(['status' => $next, 'approved_minutes' => $next === OvertimeRequestStatus::Approved ? $minutes : null, 'decided_by' => auth()->id(), 'decided_at' => now(), 'decision_note' => $note]);
            if ($next === OvertimeRequestStatus::Approved) {
                $this->staleness->markForWorkDate($locked->legal_entity_id, $locked->work_date, 'overtime_approved', 'Bordro dönemindeki fazla mesai onayı değişti.', $locked->employee_id);
            }
            $this->audit->log($next === OvertimeRequestStatus::Approved ? 'overtime_approved' : 'overtime_manager_approved', $locked, null, ['approved_minutes' => $minutes]);
            return $locked->fresh();
        });
    }

    public function reject(HrOvertimeRequest $request, string $note): HrOvertimeRequest
    {
        $this->authorize($request);
        abort_if(blank($note), 422, 'Ret gerekçesi zorunludur.');
        abort_unless(in_array($request->status, [OvertimeRequestStatus::PendingManager, OvertimeRequestStatus::PendingHr], true), 422, 'Bu talep artık beklemede değil.');
        $request->update(['status' => OvertimeRequestStatus::Rejected, 'decided_by' => auth()->id(), 'decided_at' => now(), 'decision_note' => trim($note)]);
        $this->audit->log('overtime_rejected', $request, null, ['decision_note' => $note]);
        return $request->fresh();
    }

    private function authorize(HrOvertimeRequest $request): void
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.timesheet.confirm'), 403);
        abort_unless($request->legal_entity_id === app(TenantContext::class)->getId(), 404);
    }
}
