<?php

namespace App\Modules\Hr\Shift\Livewire;

use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Shift\Actions\AssignShiftAction;
use App\Modules\Hr\Shift\Actions\CancelShiftAssignmentAction;
use App\Modules\Hr\Shift\Actions\PublishShiftWeekAction;
use App\Modules\Hr\Shift\Actions\BulkAssignShiftAction;
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
    public ?int $cancellingId = null;
    public string $cancellationReason = '';
    public array $selectedEmployeeIds = [];

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

    public function bulkAssign(BulkAssignShiftAction $action): void
    {
        $this->validate(['selectedEmployeeIds' => 'required|array|min:1', 'selectedEmployeeIds.*' => 'integer', 'templateId' => 'required|integer', 'shiftDate' => 'required|date', 'note' => 'nullable|string|max:1000']);
        $template = HrShiftTemplate::withoutGlobalScope('tenant')->where('legal_entity_id', app(TenantContext::class)->getId())->findOrFail($this->templateId);
        $result = $action->execute($this->selectedEmployeeIds, $template, $this->shiftDate, $this->note ?: null);
        session()->flash('success', "{$result['assigned']} çalışan için vardiya kaydedildi; " . count($result['errors']) . ' atama engellendi.');
        $this->reset(['selectedEmployeeIds', 'templateId', 'note']);
    }

    public function publishWeek(PublishShiftWeekAction $action): void
    {
        $start = Carbon::parse($this->weekStart)->startOfWeek();
        $count = $action->execute($start, $start->copy()->endOfWeek());
        session()->flash('success', $count > 0 ? "{$count} vardiya yayımlandı." : 'Yayımlanacak taslak vardiya bulunmuyor.');
    }

    public function startCancel(int $id): void
    {
        $this->cancellingId = $id;
        $this->cancellationReason = '';
    }

    public function cancel(CancelShiftAssignmentAction $action): void
    {
        $this->validate(['cancellationReason' => 'required|string|max:1000']);
        $assignment = HrShiftAssignment::withoutGlobalScope('tenant')->where('legal_entity_id', app(TenantContext::class)->getId())->findOrFail($this->cancellingId);
        $action->execute($assignment, $this->cancellationReason);
        $this->reset(['cancellingId', 'cancellationReason']);
        session()->flash('success', 'Vardiya ataması iptal edildi.');
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
