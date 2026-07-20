<?php

namespace App\Modules\Hr\Personnel\Livewire;

use App\Modules\Hr\Personnel\Models\HrEmployee;
use Livewire\Component;

class EmployeeDetail extends Component
{
    public HrEmployee $employee;
    public string $activeTab = 'overview';

    public function mount(int $id): void
    {
        $this->employee = HrEmployee::withoutGlobalScope('tenant')
            ->with([
                'activeEmployment.position',
                'activeEmployment.department',
                'activeEmployment.branch',
                'activeEmployment.manager',
                'employmentRecords.position',
                'employmentRecords.department',
            ])
            ->findOrFail($id);
    }

    public function render()
    {
        return view('livewire.hr.personnel.employee-detail', [
            'employee' => $this->employee,
        ])->layout('layouts.app');
    }
}
