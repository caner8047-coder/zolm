<?php

namespace App\Modules\Hr\Overtime\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Overtime\Enums\OvertimeRequestStatus;
use App\Modules\Hr\Overtime\Models\HrOvertimeRequest;

class CancelOvertimeRequestAction
{
    public function __construct(private HrAuditService $audit) {}
    public function execute(HrOvertimeRequest $request): HrOvertimeRequest
    {
        abort_unless($request->legal_entity_id === app(TenantContext::class)->getId(), 404);
        $isOwn = $request->employee?->user_id === auth()->id();
        abort_unless($isOwn || auth()->user()?->hasHrPermission('hr.timesheet.confirm'), 403);
        abort_unless(in_array($request->status, [OvertimeRequestStatus::PendingManager, OvertimeRequestStatus::PendingHr], true), 422, 'Yalnız bekleyen talep iptal edilebilir.');
        $request->update(['status' => OvertimeRequestStatus::Cancelled, 'decided_by' => auth()->id(), 'decided_at' => now(), 'decision_note' => 'Talep sahibi veya İK tarafından iptal edildi.']);
        $this->audit->log('overtime_cancelled', $request);
        return $request->fresh();
    }
}
