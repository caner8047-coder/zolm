<?php

namespace App\Modules\Hr\Shift\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Shift\Enums\ShiftAssignmentStatus;
use App\Modules\Hr\Shift\Models\HrShiftAssignment;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class PublishShiftWeekAction
{
    public function __construct(private HrAuditService $audit) {}

    public function execute(CarbonInterface $start, CarbonInterface $end): int
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.shifts.plan'), 403);
        $tenantId = app(TenantContext::class)->getId();

        return DB::transaction(function () use ($start, $end, $tenantId) {
            $assignments = HrShiftAssignment::withoutGlobalScope('tenant')
                ->where('legal_entity_id', $tenantId)
                ->whereBetween('shift_date', [$start->toDateString(), $end->toDateString()])
                ->where('status', ShiftAssignmentStatus::Planned->value)
                ->lockForUpdate()
                ->get();

            foreach ($assignments as $assignment) {
                $assignment->update(['status' => ShiftAssignmentStatus::Published, 'published_at' => now(), 'published_by' => auth()->id(), 'updated_by' => auth()->id()]);
            }
            $this->audit->logEvent('shift_week_published', 'Vardiya haftası yayımlandı', ['legal_entity_id' => $tenantId, 'start' => $start->toDateString(), 'end' => $end->toDateString(), 'count' => $assignments->count()]);
            return $assignments->count();
        });
    }
}
