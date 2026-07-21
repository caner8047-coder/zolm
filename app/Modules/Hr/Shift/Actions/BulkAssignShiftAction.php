<?php

namespace App\Modules\Hr\Shift\Actions;

use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Shift\Models\HrShiftTemplate;
use Throwable;

class BulkAssignShiftAction
{
    public function __construct(private AssignShiftAction $assigner) {}

    public function execute(array $employeeIds, HrShiftTemplate $template, string $date, ?string $note = null): array
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.shifts.assign'), 403);
        $tenantId = app(TenantContext::class)->getId();
        $result = ['assigned' => 0, 'errors' => []];
        foreach (array_values(array_unique(array_map('intval', $employeeIds))) as $employeeId) {
            try {
                $employee = HrEmployee::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->findOrFail($employeeId);
                $this->assigner->execute($employee, $template, $date, $note);
                $result['assigned']++;
            } catch (Throwable $exception) {
                $result['errors'][$employeeId] = $exception->getMessage();
            }
        }
        return $result;
    }
}
