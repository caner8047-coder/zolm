<?php

namespace App\Modules\Hr\Personnel\Livewire;

use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Livewire\Component;
use Livewire\WithPagination;

class EmployeeList extends Component
{
    use WithPagination;

    public string $search = '';
    public ?string $statusFilter = null;
    public ?int $departmentFilter = null;
    public ?int $branchFilter = null;
    public string $sortBy = 'last_name';
    public string $sortDirection = 'asc';
    public int $perPage = 15;

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => null],
        'departmentFilter' => ['except' => null],
        'sortBy' => ['except' => 'last_name'],
        'sortDirection' => ['except' => 'asc'],
    ];

    public function render()
    {
        $tenantId = app(TenantContext::class)->getId();

        $query = HrEmployee::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->with('activeEmployment.position', 'activeEmployment.department');

        // Arama
        if ($this->search) {
            $query->search($this->search);
        }

        // Filtreler
        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->departmentFilter) {
            $query->whereHas('activeEmployment', function ($q) {
                $q->where('department_id', $this->departmentFilter);
            });
        }

        if ($this->branchFilter) {
            $query->whereHas('activeEmployment', function ($q) {
                $q->where('branch_id', $this->branchFilter);
            });
        }

        // Sıralama
        $query->orderBy($this->sortBy, $this->sortDirection);

        $employees = $query->paginate($this->perPage);

        return view('livewire.hr.personnel.employee-list', [
            'employees' => $employees,
        ])->layout('layouts.app');
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'departmentFilter', 'branchFilter', 'sortBy', 'sortDirection']);
    }
}
