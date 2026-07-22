<?php

namespace App\Modules\Hr\Organization\Livewire;

use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Organization\Models\HrDepartment;
use App\Modules\Hr\Organization\Models\HrUnit;
use Livewire\Component;
use Livewire\WithPagination;

class UnitList extends Component
{
    use WithPagination;

    public string $search = '';
    public ?int $departmentFilter = null;
    public ?string $statusFilter = null;

    public function render()
    {
        $tenantId = app(TenantContext::class)->getId();

        $query = HrUnit::withoutGlobalScope('tenant')
            ->whereHas('department', fn($q) => $q->where('legal_entity_id', $tenantId))
            ->with('department');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('code', 'like', "%{$this->search}%");
            });
        }

        if ($this->departmentFilter) {
            $query->where('department_id', $this->departmentFilter);
        }

        if ($this->statusFilter !== null) {
            $query->where('is_active', $this->statusFilter === 'active');
        }

        $units = $query->orderBy('sort_order')->orderBy('name')->paginate(15);

        $departments = HrDepartment::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->active()
            ->ordered()
            ->get();

        return view('livewire.hr.organization.unit-list', [
            'units' => $units,
            'departments' => $departments,
        ])->layout('layouts.app');
    }

    public function toggleActive(int $unitId): void
    {
        $unit = HrUnit::withoutGlobalScope('tenant')
            ->whereHas('department', fn($q) => $q->where('legal_entity_id', app(TenantContext::class)->getId()))
            ->findOrFail($unitId);

        $unit->update(['is_active' => !$unit->is_active, 'updated_by' => auth()->id()]);

        session()->flash('success', $unit->is_active ? 'Birim aktifleştirildi.' : 'Birim pasifleştirildi.');
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'departmentFilter', 'statusFilter']);
    }
}
