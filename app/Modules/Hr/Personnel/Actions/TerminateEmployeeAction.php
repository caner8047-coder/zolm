<?php

namespace App\Modules\Hr\Personnel\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Personnel\Enums\EmployeeStatus;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Personnel\Models\HrEmploymentRecord;
use Illuminate\Support\Facades\DB;

class TerminateEmployeeAction
{
    public function __construct(
        private HrAuditService $auditService
    ) {}

    public function execute(HrEmployee $employee, string $reason, ?string $terminationDate = null): HrEmployee
    {
        return DB::transaction(function () use ($employee, $reason, $terminationDate) {
            $date = $terminationDate ?? now()->toDateString();

            // Employee durumunu güncelle
            $employee->update([
                'status' => EmployeeStatus::Terminated,
                'termination_date' => $date,
                'termination_reason' => $reason,
                'updated_by' => auth()->id(),
            ]);

            // Aktif employment record'u kapat
            HrEmploymentRecord::where('employee_id', $employee->id)
                ->where('status', 'active')
                ->update([
                    'status' => 'completed',
                    'end_date' => $date,
                    'termination_reason' => $reason,
                    'updated_by' => auth()->id(),
                ]);

            $this->auditService->log('employee_terminated', $employee, null, [
                'reason' => $reason,
                'termination_date' => $date,
            ]);

            return $employee->fresh();
        });
    }
}
