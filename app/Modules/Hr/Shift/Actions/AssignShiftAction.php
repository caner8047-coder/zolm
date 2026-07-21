<?php

namespace App\Modules\Hr\Shift\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Leave\Enums\LeaveRequestStatus;
use App\Modules\Hr\Leave\Models\HrLeaveRequest;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Shift\Models\HrShiftAssignment;
use App\Modules\Hr\Shift\Models\HrShiftTemplate;
use Illuminate\Support\Facades\DB;

class AssignShiftAction
{
    public function __construct(private HrAuditService $audit) {}

    public function execute(HrEmployee $employee, HrShiftTemplate $template, string $date, ?string $note = null): HrShiftAssignment
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.shifts.assign'), 403);
        $tenantId = app(TenantContext::class)->getId();
        $template->refresh();
        abort_unless($employee->legal_entity_id === $tenantId && $template->legal_entity_id === $tenantId && $template->is_active, 422, 'Çalışan veya vardiya şablonu geçersiz.');
        abort_unless($employee->status->value === 'active', 422, 'Pasif çalışana vardiya atanamaz.');

        return DB::transaction(function () use ($employee, $template, $date, $note, $tenantId) {
            $onLeave = HrLeaveRequest::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('employee_id', $employee->id)->where('status', LeaveRequestStatus::Approved->value)->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)->exists();
            abort_if($onLeave, 422, 'Onaylı izni bulunan çalışana vardiya atanamaz.');
            $assignment = HrShiftAssignment::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('employee_id', $employee->id)->whereDate('shift_date', $date)->lockForUpdate()->first();
            $values = ['shift_template_id' => $template->id, 'status' => 'planned', 'note' => $note, 'updated_by' => auth()->id()];
            $assignment = $assignment ? tap($assignment)->update($values) : HrShiftAssignment::create($values + ['legal_entity_id' => $tenantId, 'employee_id' => $employee->id, 'shift_date' => $date, 'created_by' => auth()->id()]);
            $this->audit->log('shift_assigned', $assignment, null, ['employee_id' => $employee->id, 'template_id' => $template->id]);
            return $assignment->fresh(['employee', 'template']);
        });
    }
}
