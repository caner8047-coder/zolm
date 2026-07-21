<?php

namespace App\Modules\Hr\Leave\Livewire;

use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Leave\Models\HrLeaveType;
use Livewire\Component;
use Livewire\WithPagination;

class LeaveTypeList extends Component
{
    use WithPagination;

    public string $search = '';
    public ?string $statusFilter = null;
    public ?string $unitFilter = null;

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedStatusFilter(): void { $this->resetPage(); }
    public function updatedUnitFilter(): void { $this->resetPage(); }

    public function toggleActive(int $id): void
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.leaves.manage_type'), 403);
        $type = HrLeaveType::withoutGlobalScope('tenant')->where('legal_entity_id', app(TenantContext::class)->getId())->findOrFail($id);
        $type->update(['is_active' => !$type->is_active, 'updated_by' => auth()->id()]);
        session()->flash('success', $type->is_active ? 'İzin türü aktifleştirildi.' : 'İzin türü pasifleştirildi.');
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'unitFilter']);
        $this->resetPage();
    }

    public function render()
    {
        $query = HrLeaveType::withoutGlobalScope('tenant')->where('legal_entity_id', app(TenantContext::class)->getId());
        if ($this->search !== '') $query->where(fn ($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('code', 'like', "%{$this->search}%"));
        if ($this->statusFilter !== null && $this->statusFilter !== '') $query->where('is_active', $this->statusFilter === 'active');
        if ($this->unitFilter) $query->where('unit', $this->unitFilter);

        return view('livewire.hr.leave.leave-type-list', ['types' => $query->orderBy('name')->paginate(15)])->layout('layouts.app');
    }
}
