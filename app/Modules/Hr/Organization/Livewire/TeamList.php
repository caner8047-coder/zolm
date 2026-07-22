<?php

namespace App\Modules\Hr\Organization\Livewire;

use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Organization\Models\HrDepartment;
use App\Modules\Hr\Organization\Models\HrTeam;
use App\Modules\Hr\Organization\Models\HrUnit;
use Livewire\Component;
use Livewire\WithPagination;

class TeamList extends Component
{
    use WithPagination;

    public string $search = '';
    public ?int $departmentFilter = null;
    public ?int $unitFilter = null;
    public ?string $statusFilter = null;

    public function render()
    {
        $tenantId = app(TenantContext::class)->getId();

        $query = HrTeam::withoutGlobalScope('tenant')
            ->whereHas('unit.department', fn($q) => $q->where('legal_entity_id', $tenantId))
            ->with('unit.department');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%");
            });
        }

        if ($this->departmentFilter) {
            $query->whereHas('unit', fn($q) => $q->where('department_id', $this->departmentFilter));
        }

        if ($this->unitFilter) {
            $query->where('unit_id', $this->unitFilter);
        }

        if ($this->statusFilter !== null) {
            $query->where('is_active', $this->statusFilter === 'active');
        }

        $teams = $query->orderBy('sort_order')->orderBy('name')->paginate(15);

        $departments = HrDepartment::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->active()
            ->ordered()
            ->get();

        $units = HrUnit::withoutGlobalScope('tenant')
            ->whereHas('department', fn($q) => $q->where('legal_entity_id', $tenantId))
            ->active()
            ->ordered()
            ->get();

        return view('livewire.hr.organization.team-list', [
            'teams' => $teams,
            'departments' => $departments,
            'units' => $units,
        ])->layout('layouts.app');
    }

    public function toggleActive(int $teamId): void
    {
        $team = HrTeam::withoutGlobalScope('tenant')
            ->whereHas('unit.department', fn($q) => $q->where('legal_entity_id', app(TenantContext::class)->getId()))
            ->findOrFail($teamId);

        $team->update(['is_active' => !$team->is_active, 'updated_by' => auth()->id()]);

        session()->flash('success', $team->is_active ? 'Ekip aktifleştirildi.' : 'Ekip pasifleştirildi.');
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'departmentFilter', 'unitFilter', 'statusFilter']);
    }
}
