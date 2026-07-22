<?php

namespace App\Modules\Hr\Lifecycle\Livewire;

use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Lifecycle\Services\SeveranceCalculatorService;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Livewire\Component;

class SeveranceCalculator extends Component
{
    public ?int $selectedEmployeeId = null;
    public string $startDate = '';
    public string $endDate = '';
    public string $monthlyGrossSalary = '45000';
    public string $monthlyBenefits = '6500';
    public string $severanceCeiling = '46244.38';

    public ?array $result = null;

    public function mount(): void
    {
        $this->startDate = now()->subYears(3)->toDateString();
        $this->endDate = now()->toDateString();
        $this->calculate();
    }

    public function updatedSelectedEmployeeId(): void
    {
        if ($this->selectedEmployeeId) {
            $tenant = app(TenantContext::class)->getId();
            $employee = HrEmployee::withoutGlobalScope('tenant')
                ->where('legal_entity_id', $tenant)
                ->find($this->selectedEmployeeId);

            if ($employee && $employee->activeEmployment) {
                $employment = $employee->activeEmployment;
                $this->startDate = $employment->start_date?->toDateString() ?? $this->startDate;

                $salaryRecord = $employee->currentSalaryRecord;
                if ($salaryRecord) {
                    $this->monthlyGrossSalary = (string) $salaryRecord->grossSalary();
                }
                $this->calculate();
            }
        }
    }

    public function calculate(): void
    {
        $this->validate([
            'startDate' => 'required|date',
            'endDate' => 'required|date|after_or_equal:startDate',
            'monthlyGrossSalary' => 'required|numeric|min:0',
            'monthlyBenefits' => 'nullable|numeric|min:0',
            'severanceCeiling' => 'required|numeric|min:0',
        ]);

        $service = new SeveranceCalculatorService();
        $this->result = $service->calculate(
            $this->startDate,
            $this->endDate,
            (float) $this->monthlyGrossSalary,
            (float) ($this->monthlyBenefits ?: 0),
            (float) $this->severanceCeiling
        );
    }

    public function render()
    {
        $tenant = app(TenantContext::class)->getId();
        try {
            $employees = HrEmployee::withoutGlobalScope('tenant')
                ->where('legal_entity_id', $tenant)
                ->orderBy('first_name')
                ->get();
        } catch (\Throwable $e) {
            $employees = collect();
        }

        return view('livewire.hr.lifecycle.severance-calculator', [
            'employees' => $employees,
        ])->layout('layouts.app');
    }
}
