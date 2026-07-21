<?php

namespace App\Modules\Hr\Leave\Livewire;

use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Leave\Models\HrLeavePolicy;
use Livewire\Component;
use Livewire\WithPagination;

class LeavePolicyList extends Component
{
    use WithPagination;

    public ?int $leaveTypeFilter = null;
    public ?string $scopeFilter = null;
    public ?string $statusFilter = 'active';

    public function updatedLeaveTypeFilter(): void { $this->resetPage(); }
    public function updatedScopeFilter(): void { $this->resetPage(); }
    public function updatedStatusFilter(): void { $this->resetPage(); }

    public function toggleActive(int $id): void
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.leaves.manage_policy'), 403);
        $policy = HrLeavePolicy::withoutGlobalScope('tenant')->where('legal_entity_id', app(TenantContext::class)->getId())->findOrFail($id);
        $policy->update(['is_active' => !$policy->is_active, 'updated_by' => auth()->id()]);
        session()->flash('success', $policy->is_active ? 'Politika aktifleştirildi.' : 'Politika pasifleştirildi.');
    }

    public function render()
    {
        $tenantId = app(TenantContext::class)->getId();
        $query = HrLeavePolicy::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->with('leaveType', 'branch', 'department', 'position');
        if ($this->leaveTypeFilter) $query->where('leave_type_id', $this->leaveTypeFilter);
        if ($this->scopeFilter) $query->where('scope', $this->scopeFilter);
        if ($this->statusFilter !== null && $this->statusFilter !== '') $query->where('is_active', $this->statusFilter === 'active');

        return view('livewire.hr.leave.leave-policy-list', [
            'policies' => $query->orderBy('leave_type_id')->orderBy('scope')->paginate(15),
            'leaveTypes' => \App\Modules\Hr\Leave\Models\HrLeaveType::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('is_active', true)->orderBy('name')->get(),
        ])->layout('layouts.app');
    }
}
