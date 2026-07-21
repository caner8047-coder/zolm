<?php

namespace App\Modules\Hr\Leave\Livewire;

use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Leave\Actions\AdjustLeaveBalanceAction;
use App\Modules\Hr\Leave\Models\HrLeaveBalance;
use App\Modules\Hr\Leave\Models\HrLeaveType;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Livewire\Component;
use Livewire\WithPagination;

class LeaveBalanceManager extends Component
{
    use WithPagination;
    public string $search = '';
    public ?int $adjustEmployeeId = null;
    public ?int $adjustLeaveTypeId = null;
    public string $adjustAmount = '';
    public string $adjustNote = '';
    public function updatedSearch(): void { $this->resetPage(); }

    public function adjust(AdjustLeaveBalanceAction $action): void
    {
        $this->validate(['adjustEmployeeId' => 'required|integer', 'adjustLeaveTypeId' => 'required|integer', 'adjustAmount' => 'required|numeric|not_in:0', 'adjustNote' => 'required|string|max:1000']);
        $tenantId = app(TenantContext::class)->getId();
        $employee = HrEmployee::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->findOrFail($this->adjustEmployeeId);
        $type = HrLeaveType::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->findOrFail($this->adjustLeaveTypeId);
        $action->execute($employee, $type, (float) $this->adjustAmount, $this->adjustNote);
        $this->reset(['adjustEmployeeId', 'adjustLeaveTypeId', 'adjustAmount', 'adjustNote']);
        session()->flash('success', 'Bakiye düzeltme hareketi kaydedildi.');
    }

    public function render()
    {
        $tenantId = app(TenantContext::class)->getId();
        $balances = HrLeaveBalance::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->with('employee', 'leaveType')->where('period_year', now()->year);
        if ($this->search !== '') $balances->whereHas('employee', fn ($q) => $q->where('first_name', 'like', "%{$this->search}%")->orWhere('last_name', 'like', "%{$this->search}%")->orWhere('employee_number', 'like', "%{$this->search}%"));
        return view('livewire.hr.leave.leave-balance-manager', ['balances' => $balances->paginate(15), 'employees' => HrEmployee::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->active()->orderBy('first_name')->get(), 'leaveTypes' => HrLeaveType::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('is_active', true)->orderBy('name')->get()])->layout('layouts.app');
    }
}
