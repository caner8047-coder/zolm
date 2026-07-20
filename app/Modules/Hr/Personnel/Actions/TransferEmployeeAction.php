<?php

namespace App\Modules\Hr\Personnel\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Personnel\Models\HrEmploymentRecord;
use Illuminate\Support\Facades\DB;

class TransferEmployeeAction
{
    public function __construct(
        private HrAuditService $auditService
    ) {}

    public function execute(HrEmployee $employee, array $newEmploymentData, string $reason = 'transfer'): HrEmploymentRecord
    {
        return DB::transaction(function () use ($employee, $newEmploymentData, $reason) {
            // Mevcut aktif kaydı kapat
            HrEmploymentRecord::where('employee_id', $employee->id)
                ->where('status', 'active')
                ->update([
                    'status' => 'completed',
                    'end_date' => now()->subDay()->toDateString(),
                    'updated_by' => auth()->id(),
                ]);

            // Yeni kayıt oluştur
            $newEmploymentData['employee_id'] = $employee->id;
            $newEmploymentData['legal_entity_id'] = $employee->legal_entity_id;
            $newEmploymentData['status'] = 'active';
            $newEmploymentData['created_by'] = auth()->id();

            $record = HrEmploymentRecord::create($newEmploymentData);

            $this->auditService->log('employee_transferred', $employee, null, [
                'reason' => $reason,
                'new_employment_id' => $record->id,
            ]);

            return $record;
        });
    }
}
