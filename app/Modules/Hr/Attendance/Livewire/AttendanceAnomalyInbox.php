<?php

namespace App\Modules\Hr\Attendance\Livewire;

use App\Modules\Hr\Attendance\Actions\ResolveAttendanceAnomalyAction;
use App\Modules\Hr\Attendance\Models\HrAttendanceAnomaly;
use App\Modules\Hr\Core\Services\TenantContext;
use Livewire\Component;
use Livewire\WithPagination;

class AttendanceAnomalyInbox extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'open';
    public string $severityFilter = '';
    public ?int $resolvingId = null;
    public string $resolutionNote = '';

    public function updated($property): void
    {
        if (in_array($property, ['search', 'statusFilter', 'severityFilter'], true)) $this->resetPage();
    }

    public function startResolve(int $id): void { $this->resolvingId = $id; $this->resolutionNote = ''; }

    public function resolve(ResolveAttendanceAnomalyAction $action): void
    {
        $this->validate(['resolutionNote' => 'required|string|max:1000']);
        $anomaly = HrAttendanceAnomaly::withoutGlobalScope('tenant')->where('legal_entity_id', app(TenantContext::class)->getId())->findOrFail($this->resolvingId);
        $action->execute($anomaly, $this->resolutionNote);
        $this->reset(['resolvingId', 'resolutionNote']);
        session()->flash('success', 'Anomali çözüldü.');
    }

    public function render()
    {
        $query = HrAttendanceAnomaly::withoutGlobalScope('tenant')->where('legal_entity_id', app(TenantContext::class)->getId())->with('employee');
        if ($this->search !== '') $query->whereHas('employee', fn ($q) => $q->search($this->search));
        if ($this->statusFilter !== '') $query->where('status', $this->statusFilter);
        if ($this->severityFilter !== '') $query->where('severity', $this->severityFilter);

        return view('livewire.hr.attendance.attendance-anomaly-inbox', [
            'anomalies' => $query->orderByDesc('work_date')->orderByDesc('id')->paginate(20),
            'openCount' => HrAttendanceAnomaly::withoutGlobalScope('tenant')->where('legal_entity_id', app(TenantContext::class)->getId())->where('status', 'open')->count(),
        ])->layout('layouts.app');
    }
}
