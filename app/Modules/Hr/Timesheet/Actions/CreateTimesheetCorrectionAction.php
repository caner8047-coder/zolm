<?php

namespace App\Modules\Hr\Timesheet\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Timesheet\Enums\TimesheetStatus;
use App\Modules\Hr\Timesheet\Models\HrTimesheet;
use App\Modules\Hr\Timesheet\Models\HrTimesheetCorrection;
use Illuminate\Support\Facades\DB;

class CreateTimesheetCorrectionAction
{
    private const FIELDS = ['worked_minutes', 'break_minutes', 'leave_minutes', 'overtime_minutes', 'missing_minutes'];
    public function __construct(private HrAuditService $audit) {}
    public function execute(HrTimesheet $timesheet, array $newValues, string $reason): HrTimesheetCorrection
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.timesheet.correct'), 403);
        abort_unless($timesheet->legal_entity_id === app(TenantContext::class)->getId(), 404);
        abort_unless(in_array($timesheet->status, [TimesheetStatus::Confirmed, TimesheetStatus::Closed], true), 422, 'Düzeltme yalnız onaylı veya kapanmış puantaj için oluşturulabilir.');
        abort_if(blank($reason), 422, 'Düzeltme gerekçesi zorunludur.');
        $values = [];
        foreach (self::FIELDS as $field) $values[$field] = max(0, (int) ($newValues[$field] ?? $timesheet->effective($field)));

        $correction = DB::transaction(function () use ($timesheet, $values, $reason) {
            $locked = HrTimesheet::withoutGlobalScope('tenant')->whereKey($timesheet->id)->lockForUpdate()->firstOrFail();
            $revision = (int) $locked->corrections()->max('revision_number') + 1;
            $old = collect(self::FIELDS)->mapWithKeys(fn ($field) => [$field => (int) $locked->effective($field)])->all();
            return HrTimesheetCorrection::create(['legal_entity_id' => $locked->legal_entity_id, 'timesheet_id' => $locked->id, 'revision_number' => $revision, 'old_values' => $old, 'new_values' => $values, 'reason' => trim($reason), 'created_by' => auth()->id()]);
        });
        $this->audit->log('timesheet_correction_created', $correction, null, ['timesheet_id' => $timesheet->id, 'revision' => $correction->revision_number]);
        return $correction;
    }
}
