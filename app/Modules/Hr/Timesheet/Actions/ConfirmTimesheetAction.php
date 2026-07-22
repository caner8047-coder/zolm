<?php

namespace App\Modules\Hr\Timesheet\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Timesheet\Enums\TimesheetStatus;
use App\Modules\Hr\Timesheet\Models\HrTimesheet;

class ConfirmTimesheetAction
{
    public function __construct(private HrAuditService $audit) {}
    public function execute(HrTimesheet $timesheet): HrTimesheet
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.timesheet.confirm'), 403);
        abort_unless($timesheet->legal_entity_id === app(TenantContext::class)->getId(), 404);
        abort_unless($timesheet->status === TimesheetStatus::Draft, 422, 'Yalnız taslak puantaj onaylanabilir.');
        abort_if($timesheet->calculation_version < 2, 422, 'Bu puantaj satırı eski hesap sürümünde. Önce dönemi yeniden hesaplayın.');
        abort_if($timesheet->anomaly_count > 0, 422, 'Açık anomalisi bulunan puantaj satırı onaylanamaz.');
        $timesheet->update(['status' => TimesheetStatus::Confirmed, 'confirmed_by' => auth()->id(), 'confirmed_at' => now()]);
        $this->audit->log('timesheet_confirmed', $timesheet);
        return $timesheet->fresh();
    }
}
