<?php

namespace App\Modules\Hr\Timesheet\Actions;

use App\Modules\Hr\Attendance\Models\HrAttendanceAnomaly;
use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Timesheet\Enums\TimesheetPeriodStatus;
use App\Modules\Hr\Timesheet\Enums\TimesheetStatus;
use App\Modules\Hr\Timesheet\Models\HrTimesheetPeriod;
use Illuminate\Support\Facades\DB;

class CloseTimesheetPeriodAction
{
    public function __construct(private HrAuditService $audit) {}
    public function execute(HrTimesheetPeriod $period): HrTimesheetPeriod
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.timesheet.close'), 403);
        $tenantId = app(TenantContext::class)->getId();
        abort_unless($period->legal_entity_id === $tenantId, 404);
        abort_unless($period->status === TimesheetPeriodStatus::Calculated, 422, 'Yalnız hesaplanmış dönem kapatılabilir.');
        abort_if(!$period->timesheets()->exists(), 422, 'Boş puantaj dönemi kapatılamaz.');
        abort_if($period->timesheets()->where('status', TimesheetStatus::Draft->value)->exists(), 422, 'Tüm puantaj satırları onaylanmadan dönem kapatılamaz.');
        abort_if(HrAttendanceAnomaly::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('status', 'open')->whereBetween('work_date', [$period->starts_on, $period->ends_on])->exists(), 422, 'Açık PDKS anomalileri çözülmeden dönem kapatılamaz.');

        DB::transaction(function () use ($period) {
            $period->timesheets()->where('status', TimesheetStatus::Confirmed->value)->update(['status' => TimesheetStatus::Closed->value]);
            $period->update(['status' => TimesheetPeriodStatus::Closed, 'closed_at' => now(), 'closed_by' => auth()->id()]);
        });
        $this->audit->log('timesheet_period_closed', $period);
        return $period->fresh();
    }
}
