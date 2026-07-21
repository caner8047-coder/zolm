<?php

namespace App\Modules\Hr\Timesheet\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Timesheet\Enums\TimesheetPeriodStatus;
use App\Modules\Hr\Timesheet\Models\HrTimesheetPeriod;
use Carbon\Carbon;

class CreateTimesheetPeriodAction
{
    public function __construct(private HrAuditService $audit) {}

    public function execute(string $name, string $startsOn, string $endsOn): HrTimesheetPeriod
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.timesheet.confirm'), 403);
        $tenantId = app(TenantContext::class)->getId();
        abort_if(blank($name), 422, 'Dönem adı zorunludur.');
        $start = Carbon::parse($startsOn)->startOfDay();
        $end = Carbon::parse($endsOn)->startOfDay();
        abort_if($end->lt($start) || $start->diffInDays($end) > 31, 422, 'Puantaj dönemi 1-32 gün aralığında olmalıdır.');
        $overlaps = HrTimesheetPeriod::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->whereDate('starts_on', '<=', $end)->whereDate('ends_on', '>=', $start)->exists();
        abort_if($overlaps, 422, 'Bu tarihler mevcut bir puantaj dönemiyle çakışıyor.');

        $period = HrTimesheetPeriod::create(['legal_entity_id' => $tenantId, 'name' => trim($name), 'starts_on' => $start, 'ends_on' => $end, 'status' => TimesheetPeriodStatus::Draft]);
        $this->audit->log('timesheet_period_created', $period, null, ['starts_on' => $start->toDateString(), 'ends_on' => $end->toDateString()]);
        return $period;
    }
}
