<?php

namespace App\Modules\Hr\Leave\Livewire;

use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Leave\Models\HrLeaveRequest;
use App\Modules\Hr\Leave\Models\HrLeaveType;
use App\Modules\Hr\Leave\Actions\CancelLeaveRequestAction;
use Livewire\Component;
use Livewire\WithPagination;

class LeaveList extends Component
{
    use WithPagination;

    public string $search = '';
    public ?string $statusFilter = null;
    public ?int $leaveTypeFilter = null;
    public ?int $cancellingId = null;
    public string $cancellationReason = '';

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedStatusFilter(): void { $this->resetPage(); }
    public function updatedLeaveTypeFilter(): void { $this->resetPage(); }
    public function resetFilters(): void { $this->reset(['search', 'statusFilter', 'leaveTypeFilter']); $this->resetPage(); }
    public function startCancel(int $id): void { $this->cancellingId = $id; $this->cancellationReason = ''; }
    public function cancel(CancelLeaveRequestAction $action): void
    {
        $this->validate(['cancellationReason' => 'required|string|max:1000']);
        $request = HrLeaveRequest::withoutGlobalScope('tenant')->where('legal_entity_id', app(TenantContext::class)->getId())->findOrFail($this->cancellingId);
        $action->execute($request, $this->cancellationReason);
        $this->reset(['cancellingId', 'cancellationReason']);
        session()->flash('success', 'İzin talebi iptal edildi.');
    }

    public function render()
    {
        $tenantId = app(TenantContext::class)->getId();
        $query = HrLeaveRequest::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->with(['employee', 'leaveType', 'approvalSteps'])->latest();
        if ($this->search !== '') $query->whereHas('employee', fn ($q) => $q->where('first_name', 'like', "%{$this->search}%")->orWhere('last_name', 'like', "%{$this->search}%")->orWhere('employee_number', 'like', "%{$this->search}%"));
        if ($this->statusFilter) $query->where('status', $this->statusFilter);
        if ($this->leaveTypeFilter) $query->where('leave_type_id', $this->leaveTypeFilter);

        return view('livewire.hr.leave.leave-list', ['requests' => $query->paginate(15), 'leaveTypes' => HrLeaveType::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('is_active', true)->orderBy('name')->get()])->layout('layouts.app');
    }
}
