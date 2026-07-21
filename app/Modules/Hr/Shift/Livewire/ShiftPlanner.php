<?php

namespace App\Modules\Hr\Shift\Livewire;

use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Shift\Actions\AssignShiftAction;
use App\Modules\Hr\Shift\Models\HrShiftAssignment;
use App\Modules\Hr\Shift\Models\HrShiftTemplate;
use Carbon\Carbon;
use Livewire\Component;

class ShiftPlanner extends Component
{
    public string $weekStart = '';
    public ?int $employeeId = null;
    public ?int $templateId = null;
    public string $shiftDate = '';
    public string $note = '';

    public function mount(): void { $this->weekStart = now()->startOfWeek()->toDateString(); $this->shiftDate = now()->toDateString(); }
    public function previousWeek(): void { $this->weekStart = Carbon::parse($this->weekStart)->subWeek()->toDateString(); }
    public function nextWeek(): void { $this->weekStart = Carbon::parse($this->weekStart)->addWeek()->toDateString(); }
    public function thisWeek(): void { $this->weekStart = now()->startOfWeek()->toDateString(); }

    public function assign(AssignShiftAction $action): void
    {
        $this->validate(['employeeId' => 'required|integer', 'templateId' => 'required|integer', 'shiftDate' => 'required|date', 'note' => 'nullable|string|max:1000']);
        $tenantId = app(TenantContext::class)->getId();
        $employee = HrEmployee::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->findOrFail($this->employeeId);
        $template = HrShiftTemplate::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->findOrFail($this->templateId);
        $action->execute($employee, $template, $this->shiftDate, $this->note ?: null);
        session()->flash('success', 'Vardiya ataması kaydedildi.'); $this->reset(['employeeId', 'templateId', 'note']);
    }

    public function render()
    {
        $tenantId = app(TenantContext::class)->getId(); $start = Carbon::parse($this->weekStart)->startOfWeek(); $end = $start->copy()->endOfWeek();
        return view('livewire.hr.shift.shift-planner', [
            'days' => collect(range(0, 6))->map(fn ($day) => $start->copy()->addDays($day)),
            'employees' => HrEmployee::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->active()->with('activeEmployment')->orderBy('first_name')->get(),
            'templates' => HrShiftTemplate::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('is_active', true)->orderBy('starts_at')->get(),
            'assignments' => HrShiftAssignment::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->whereBetween('shift_date', [$start->toDateString(), $end->toDateString()])->with(['employee', 'template'])->get()->groupBy(fn ($item) => $item->employee_id . '-' . $item->shift_date->toDateString()),
        ])->layout('layouts.app');
    }
}
