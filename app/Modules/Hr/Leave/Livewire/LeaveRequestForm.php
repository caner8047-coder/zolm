<?php

namespace App\Modules\Hr\Leave\Livewire;

use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Leave\Actions\CreateLeaveRequestAction;
use App\Modules\Hr\Leave\Models\HrLeaveType;
use App\Modules\Hr\Document\Models\HrEmployeeDocument;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Livewire\Component;

class LeaveRequestForm extends Component
{
    public ?int $employee_id = null;
    public ?int $leave_type_id = null;
    public string $start_date = '';
    public string $end_date = '';
    public ?string $start_time = null;
    public ?string $end_time = null;
    public ?string $reason = null;
    public ?int $document_id = null;
    public ?int $delegate_employee_id = null;
    public bool $selfService = false;

    public function mount(?int $employee = null, bool $selfService = false): void
    {
        $this->selfService = $selfService;
        if ($selfService) {
            $this->employee_id = HrEmployee::withoutGlobalScope('tenant')->where('legal_entity_id', app(TenantContext::class)->getId())->where('user_id', auth()->id())->value('id');
            abort_unless($this->employee_id, 403, 'Hesabınıza bağlı aktif çalışan kaydı bulunamadı.');
        } else {
            $this->employee_id = $employee;
        }
        $this->start_date = now()->toDateString();
        $this->end_date = now()->toDateString();
    }

    public function save(CreateLeaveRequestAction $action): void
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.leaves.create'), 403);
        $this->validate(['employee_id' => 'required|integer', 'leave_type_id' => 'required|integer', 'start_date' => 'required|date', 'end_date' => 'required|date|after_or_equal:start_date', 'start_time' => 'nullable|date_format:H:i', 'end_time' => 'nullable|date_format:H:i', 'reason' => 'nullable|string|max:2000', 'delegate_employee_id' => 'nullable|integer']);
        $tenantId = app(TenantContext::class)->getId();
        if ($this->selfService) {
            $this->employee_id = HrEmployee::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('user_id', auth()->id())->value('id');
            abort_unless($this->employee_id, 403);
        }
        $employee = HrEmployee::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->findOrFail($this->employee_id);
        $type = HrLeaveType::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->findOrFail($this->leave_type_id);
        if ($this->delegate_employee_id) abort_unless(HrEmployee::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->whereKey($this->delegate_employee_id)->exists(), 422, 'Vekil çalışan başka bir tüzel kişiliğe ait.');
        $request = $action->execute($employee, $type, $this->only(['start_date', 'end_date', 'start_time', 'end_time', 'reason', 'document_id', 'delegate_employee_id']));
        session()->flash('success', "İzin talebi oluşturuldu: {$request->status->label()}");
        $this->redirect(route($this->selfService ? 'hr.my-leaves' : 'hr.leaves'));
    }

    public function render()
    {
        $tenantId = app(TenantContext::class)->getId();
        $documents = $this->employee_id ? HrEmployeeDocument::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('employee_id', $this->employee_id)->where('status', 'active')->with('documentType')->orderByDesc('created_at')->get() : collect();
        return view('livewire.hr.leave.leave-request-form', ['employees' => HrEmployee::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->active()->when($this->selfService, fn ($query) => $query->where('user_id', auth()->id()))->orderBy('first_name')->get(), 'leaveTypes' => HrLeaveType::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('is_active', true)->orderBy('name')->get(), 'documents' => $documents])->layout('layouts.app');
    }
}
