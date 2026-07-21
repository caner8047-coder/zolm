<?php

namespace App\Modules\Hr\Shift\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Shift\Enums\ShiftAvailabilityStatus;
use App\Modules\Hr\Shift\Models\HrShiftAvailability;

class SetShiftAvailabilityAction
{
    public function __construct(private HrAuditService $audit) {}

    public function execute(HrEmployee $employee, string $date, ShiftAvailabilityStatus $status, ?string $start = null, ?string $end = null, ?string $note = null): HrShiftAvailability
    {
        $tenantId = app(TenantContext::class)->getId();
        $user = auth()->user();
        $isOwn = $employee->user_id === $user?->id;
        abort_unless($user && ($isOwn || $user->hasHrPermission('hr.shifts.manage')), 403);
        abort_unless($employee->legal_entity_id === $tenantId, 422, 'Çalışan başka bir tüzel kişiliğe ait.');
        abort_if($status === ShiftAvailabilityStatus::Preferred && (!$start || !$end), 422, 'Tercihli saat aralığı zorunludur.');

        $availability = HrShiftAvailability::withoutGlobalScope('tenant')->firstOrNew(['legal_entity_id' => $tenantId, 'employee_id' => $employee->id, 'availability_date' => $date]);
        if (! $availability->exists) $availability->created_by = $user->id;
        $availability->fill(['status' => $status, 'preferred_start' => $status === ShiftAvailabilityStatus::Preferred ? $start : null, 'preferred_end' => $status === ShiftAvailabilityStatus::Preferred ? $end : null, 'note' => $note, 'updated_by' => $user->id])->save();
        $this->audit->log('shift_availability_set', $availability);
        return $availability;
    }
}
