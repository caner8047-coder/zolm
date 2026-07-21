<?php

namespace App\Modules\Hr\Leave\Livewire;

use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Leave\Models\HrLeaveBalance;
use App\Modules\Hr\Leave\Models\HrLeaveRequest;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Livewire\Component;

class MyLeaveList extends Component
{
    public function render()
    {
        $tenantId = app(TenantContext::class)->getId();
        $employee = HrEmployee::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('user_id', auth()->id())->firstOrFail();
        return view('livewire.hr.leave.my-leave-list', [
            'employee' => $employee,
            'balances' => HrLeaveBalance::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('employee_id', $employee->id)->with('leaveType')->orderByDesc('period_year')->get(),
            'requests' => HrLeaveRequest::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('employee_id', $employee->id)->with('leaveType')->latest()->paginate(15),
        ])->layout('layouts.app');
    }
}
