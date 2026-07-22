<?php

namespace App\Modules\Hr\Payroll\Livewire;

use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Payroll\Actions\ManagePayrollEmployeeProfileAction;
use App\Modules\Hr\Payroll\Models\HrPayrollEmployeeProfile;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Livewire\Component;

class PayrollProfileManager extends Component
{
    public string $search = '';
    public ?int $selectedEmployeeId = null;
    public string $effectiveFrom = '';
    public string $effectiveUntil = '';
    public string $payrollGroupCode = '';
    public string $paymentMethod = 'bank';
    public string $iban = '';
    public string $bankName = '';
    public string $bankAccountHolder = '';
    public string $socialSecurityStatus = 'standard';
    public string $insuranceBranchCode = '';
    public string $incentiveLawCode = '';
    public string $missingDayDefaultCode = '';
    public string $disabilityDegree = '';
    public bool $isRetired = false;
    public bool $isRdEmployee = false;
    public bool $isTechnoparkEmployee = false;
    public string $changeReason = '';

    public function mount(): void
    {
        $this->effectiveFrom = now()->startOfMonth()->toDateString();
    }

    public function selectEmployee(int $employeeId): void
    {
        $employee = $this->employee($employeeId);
        $this->selectedEmployeeId = $employee->id;
        $this->resetProfileForm();

        $profile = HrPayrollEmployeeProfile::withoutGlobalScope('tenant')
            ->where('legal_entity_id', app(TenantContext::class)->getId())
            ->where('employee_id', $employee->id)
            ->whereIn('status', ['approved', 'superseded'])
            ->latest('version')
            ->first();

        if (! $profile) {
            $this->bankAccountHolder = $employee->full_name;

            return;
        }

        $this->effectiveFrom = now()->startOfMonth()->toDateString();
        $this->payrollGroupCode = (string) $profile->payroll_group_code;
        $this->paymentMethod = $profile->payment_method;
        $this->bankName = (string) $profile->bank_name;
        $this->bankAccountHolder = (string) ($profile->bank_account_holder ?: $employee->full_name);
        $this->socialSecurityStatus = $profile->social_security_status;
        $this->insuranceBranchCode = (string) $profile->insurance_branch_code;
        $this->incentiveLawCode = (string) $profile->incentive_law_code;
        $this->missingDayDefaultCode = (string) $profile->missing_day_default_code;
        $this->disabilityDegree = (string) ($profile->disability_degree ?? '');
        $this->isRetired = $profile->is_retired;
        $this->isRdEmployee = $profile->is_rd_employee;
        $this->isTechnoparkEmployee = $profile->is_technopark_employee;
    }

    public function save(ManagePayrollEmployeeProfileAction $action): void
    {
        $employee = $this->employee($this->selectedEmployeeId);
        $action->propose($employee, [
            'effective_from' => $this->effectiveFrom,
            'effective_until' => $this->effectiveUntil ?: null,
            'payroll_group_code' => $this->payrollGroupCode ?: null,
            'payment_method' => $this->paymentMethod,
            'iban' => $this->iban ?: null,
            'bank_name' => $this->bankName ?: null,
            'bank_account_holder' => $this->bankAccountHolder ?: null,
            'social_security_status' => $this->socialSecurityStatus,
            'insurance_branch_code' => $this->insuranceBranchCode ?: null,
            'incentive_law_code' => $this->incentiveLawCode ?: null,
            'missing_day_default_code' => $this->missingDayDefaultCode ?: null,
            'disability_degree' => $this->disabilityDegree !== '' ? (int) $this->disabilityDegree : null,
            'is_retired' => $this->isRetired,
            'is_rd_employee' => $this->isRdEmployee,
            'is_technopark_employee' => $this->isTechnoparkEmployee,
            'change_reason' => $this->changeReason,
        ]);

        $this->iban = '';
        $this->changeReason = '';
        session()->flash('success', 'Bordro profili onaya gönderildi. Hazırlayan kullanıcı profili onaylayamaz.');
    }

    public function approve(int $profileId, ManagePayrollEmployeeProfileAction $action): void
    {
        $profile = HrPayrollEmployeeProfile::withoutGlobalScope('tenant')
            ->where('legal_entity_id', app(TenantContext::class)->getId())
            ->findOrFail($profileId);
        $action->approve($profile);
        session()->flash('success', 'Bordro profili onaylandı ve ilgili açık bordro kaynakları güncelliğini yitirdi olarak işaretlendi.');
    }

    public function render()
    {
        $tenantId = app(TenantContext::class)->getId();
        $employees = HrEmployee::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->active()
            ->search($this->search)
            ->with(['payrollProfiles' => fn ($query) => $query->latest('version')])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(100)
            ->get();
        $selectedEmployee = $this->selectedEmployeeId
            ? $employees->firstWhere('id', $this->selectedEmployeeId) ?? $this->employee($this->selectedEmployeeId)->load('payrollProfiles')
            : null;

        return view('livewire.hr.payroll.payroll-profile-manager', [
            'employees' => $employees,
            'selectedEmployee' => $selectedEmployee,
            'profiledCount' => $employees->filter(fn (HrEmployee $employee) => $employee->payrollProfiles->where('status', 'approved')->isNotEmpty())->count(),
            'pendingCount' => HrPayrollEmployeeProfile::withoutGlobalScope('tenant')
                ->where('legal_entity_id', $tenantId)
                ->where('status', 'pending_approval')
                ->count(),
        ])->layout('layouts.app');
    }

    private function employee(?int $employeeId): HrEmployee
    {
        abort_unless($employeeId, 422, 'Önce bir çalışan seçin.');

        return HrEmployee::withoutGlobalScope('tenant')
            ->where('legal_entity_id', app(TenantContext::class)->getId())
            ->findOrFail($employeeId);
    }

    private function resetProfileForm(): void
    {
        $this->reset([
            'effectiveUntil', 'payrollGroupCode', 'iban', 'bankName', 'bankAccountHolder',
            'insuranceBranchCode', 'incentiveLawCode', 'missingDayDefaultCode',
            'disabilityDegree', 'isRetired', 'isRdEmployee', 'isTechnoparkEmployee',
            'changeReason',
        ]);
        $this->effectiveFrom = now()->startOfMonth()->toDateString();
        $this->paymentMethod = 'bank';
        $this->socialSecurityStatus = 'standard';
    }
}
