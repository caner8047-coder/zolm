<?php

namespace App\Modules\Hr\Shift\Livewire;

use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Shift\Actions\CreateShiftChangeRequestAction;
use App\Modules\Hr\Shift\Models\HrShiftAssignment;
use App\Modules\Hr\Shift\Models\HrShiftChangeRequest;
use App\Modules\Hr\Shift\Models\HrShiftTemplate;
use Livewire\Component;

class MyShiftChangeRequests extends Component
{
    public ?int $assignmentId = null;
    public ?int $desiredTemplateId = null;
    public string $desiredDate = '';
    public string $reason = '';

    public function mount(): void { $this->desiredDate = now()->addDay()->toDateString(); }

    public function save(CreateShiftChangeRequestAction $action): void
    {
        $this->validate(['assignmentId' => 'required|integer', 'desiredTemplateId' => 'required|integer', 'desiredDate' => 'required|date|after_or_equal:today', 'reason' => 'required|string|max:2000']);
        $tenantId = app(TenantContext::class)->getId();
        $employee = HrEmployee::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('user_id', auth()->id())->firstOrFail();
        $assignment = HrShiftAssignment::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('employee_id', $employee->id)->findOrFail($this->assignmentId);
        $template = HrShiftTemplate::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->findOrFail($this->desiredTemplateId);
        $action->execute($assignment, $template, $this->desiredDate, $this->reason);
        session()->flash('success', 'Vardiya değişiklik talebiniz gönderildi.'); $this->reset(['assignmentId', 'desiredTemplateId', 'reason']);
    }

    public function render()
    {
        $tenantId = app(TenantContext::class)->getId(); $employee = HrEmployee::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('user_id', auth()->id())->firstOrFail();
        return view('livewire.hr.shift.my-shift-change-requests', [
            'assignments' => HrShiftAssignment::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('employee_id', $employee->id)->whereDate('shift_date', '>=', today())->where('status', '!=', 'cancelled')->with('template')->orderBy('shift_date')->get(),
            'templates' => HrShiftTemplate::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('is_active', true)->orderBy('starts_at')->get(),
            'requests' => HrShiftChangeRequest::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('employee_id', $employee->id)->with(['assignment.template', 'desiredTemplate'])->latest()->get(),
        ])->layout('layouts.app');
    }
}
