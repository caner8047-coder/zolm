<?php

namespace App\Modules\Hr\Personnel\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Support\Facades\DB;

class UpdateEmployeeAction
{
    public function __construct(
        private HrAuditService $auditService
    ) {}

    public function execute(HrEmployee $employee, array $data): HrEmployee
    {
        return DB::transaction(function () use ($employee, $data) {
            $oldValues = $employee->toArray();

            // national_id güncellemesi
            if (isset($data['national_id'])) {
                $nationalId = $data['national_id'];
                $data['national_id_hash'] = hash('sha256', $nationalId . config('app.key'));
                $data['national_id_last_four'] = substr($nationalId, -4);
                $data['national_id_encrypted'] = $nationalId;
                unset($data['national_id']);
            }

            $data['updated_by'] = auth()->id();
            $employee->update($data);

            $this->auditService->log('employee_updated', $employee, $oldValues, $employee->toArray());

            return $employee;
        });
    }
}
