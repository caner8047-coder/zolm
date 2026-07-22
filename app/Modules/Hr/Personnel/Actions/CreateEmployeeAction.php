<?php

namespace App\Modules\Hr\Personnel\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\HrIntegrationOutboxService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Personnel\Models\HrEmploymentRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateEmployeeAction
{
    public function __construct(
        private HrAuditService $auditService,
        private HrIntegrationOutboxService $outbox,
    ) {}

    public function execute(array $employeeData, array $employmentData): HrEmployee
    {
        return DB::transaction(function () use ($employeeData, $employmentData) {
            $tenantId = app(TenantContext::class)->getId();

            // TC kimlik alanları veritabanında zorunludur. Action doğrudan
            // çağrılsa bile eksik veya geçersiz değer DB hatasına dönüşmemeli.
            $nationalId = trim((string) ($employeeData['national_id'] ?? ''));
            if (!preg_match('/^\d{11}$/', $nationalId)) {
                throw ValidationException::withMessages([
                    'national_id' => 'TC kimlik numarası 11 rakamdan oluşmalıdır.',
                ]);
            }

            $nationalIdHash = hash('sha256', $nationalId . config('app.key'));
            $duplicateExists = HrEmployee::withoutGlobalScope('tenant')
                ->where('legal_entity_id', $tenantId)
                ->where('national_id_hash', $nationalIdHash)
                ->exists();

            if ($duplicateExists) {
                throw ValidationException::withMessages([
                    'national_id' => 'Bu TC kimlik numarasıyla kayıtlı bir çalışan zaten var.',
                ]);
            }

            $employeeData['national_id_hash'] = $nationalIdHash;
            $employeeData['national_id_last_four'] = substr($nationalId, -4);
            $employeeData['national_id_encrypted'] = $nationalId;
            unset($employeeData['national_id']);

            // Employee number üret
            if (empty($employeeData['employee_number'])) {
                $employeeData['employee_number'] = $this->generateEmployeeNumber($tenantId);
            }

            $employeeData['legal_entity_id'] = $tenantId;
            $employeeData['created_by'] = auth()->id();

            $employee = HrEmployee::create($employeeData);

            // Employment record oluştur
            $employmentData['employee_id'] = $employee->id;
            $employmentData['legal_entity_id'] = $tenantId;
            $employmentData['created_by'] = auth()->id();
            $employmentData['status'] = 'active';

            HrEmploymentRecord::create($employmentData);

            // Audit log
            $this->auditService->log('employee_created', $employee);
            $this->outbox->enqueue('crm', 'employee_created', $employee, 'hr-employee-created-'.$employee->id, [
                'employee_id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'department_id' => $employmentData['department_id'] ?? null,
                'position_id' => $employmentData['position_id'] ?? null,
                'status' => 'active',
            ]);

            return $employee;
        });
    }

    public function findExistingByNationalId(string $nationalId): ?HrEmployee
    {
        $nationalIdHash = hash('sha256', trim($nationalId) . config('app.key'));

        return HrEmployee::withoutGlobalScope('tenant')
            ->where('legal_entity_id', app(TenantContext::class)->getId())
            ->where('national_id_hash', $nationalIdHash)
            ->first();
    }

    private function generateEmployeeNumber(int $tenantId): string
    {
        $prefix = config('hr.employee_number.prefix', 'EMP');
        $length = config('hr.employee_number.length', 5);

        $lastNumber = HrEmployee::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->where('employee_number', 'like', "{$prefix}%")
            ->orderByDesc('employee_number')
            ->value('employee_number');

        if ($lastNumber) {
            $num = (int) substr($lastNumber, strlen($prefix));
            $num++;
        } else {
            $num = 1;
        }

        return $prefix . str_pad($num, $length, '0', STR_PAD_LEFT);
    }
}
