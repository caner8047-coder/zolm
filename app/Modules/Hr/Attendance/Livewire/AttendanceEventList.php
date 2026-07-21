<?php

namespace App\Modules\Hr\Attendance\Livewire;

use App\Modules\Hr\Attendance\Actions\RecordAttendanceEventAction;
use App\Modules\Hr\Attendance\Enums\AttendanceEventType;
use App\Modules\Hr\Attendance\Models\HrAttendanceEvent;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Livewire\Component;
use Livewire\WithPagination;

class AttendanceEventList extends Component
{
    use WithPagination;

    public string $search = '';
    public string $typeFilter = '';
    public string $sourceFilter = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $sortField = 'occurred_at';
    public string $sortDirection = 'desc';
    public array $visibleColumns = ['employee', 'event_type', 'occurred_at', 'source', 'device', 'manual'];
    public ?int $employeeId = null;
    public string $manualEventType = 'check_in';
    public string $manualOccurredAt = '';
    public string $manualReason = '';

    public static array $sortableColumns = ['event_type', 'occurred_at', 'source', 'is_manual'];

    public function mount(): void
    {
        $this->dateFrom = now()->subDays(7)->toDateString();
        $this->dateTo = now()->toDateString();
        $this->manualOccurredAt = now()->format('Y-m-d\TH:i');
    }

    public function updated($property): void
    {
        if (in_array($property, ['search', 'typeFilter', 'sourceFilter', 'dateFrom', 'dateTo'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'typeFilter', 'sourceFilter']);
        $this->dateFrom = now()->subDays(7)->toDateString();
        $this->dateTo = now()->toDateString();
        $this->resetPage();
    }

    public function sortTable(string $column): void
    {
        if (!in_array($column, self::$sortableColumns, true)) return;
        if ($this->sortField === $column) $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        else { $this->sortField = $column; $this->sortDirection = 'asc'; }
    }

    public function toggleColumn(string $column): void
    {
        $allowed = ['employee', 'event_type', 'occurred_at', 'source', 'device', 'manual'];
        if (!in_array($column, $allowed, true)) return;
        if (in_array($column, $this->visibleColumns, true)) {
            if (count($this->visibleColumns) > 1) $this->visibleColumns = array_values(array_diff($this->visibleColumns, [$column]));
            return;
        }
        $this->visibleColumns[] = $column;
    }

    public function recordManual(RecordAttendanceEventAction $action): void
    {
        $this->validate([
            'employeeId' => 'required|integer',
            'manualEventType' => 'required|in:check_in,check_out,break_start,break_end',
            'manualOccurredAt' => 'required|date',
            'manualReason' => 'required|string|max:1000',
        ]);
        $tenantId = app(TenantContext::class)->getId();
        $employee = HrEmployee::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->findOrFail($this->employeeId);
        $action->execute($employee, AttendanceEventType::from($this->manualEventType), $this->manualOccurredAt, 'manual', 'manual:'.auth()->id().':'.str()->uuid(), manualReason: $this->manualReason);
        $this->reset(['employeeId', 'manualReason']);
        $this->manualOccurredAt = now()->format('Y-m-d\TH:i');
        session()->flash('success', 'Manuel PDKS olayı kaydedildi ve anomaliler yeniden değerlendirildi.');
    }

    public function render()
    {
        $tenantId = app(TenantContext::class)->getId();
        $query = HrAttendanceEvent::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->with(['employee', 'device']);
        if ($this->search !== '') $query->whereHas('employee', fn ($q) => $q->search($this->search));
        if ($this->typeFilter !== '') $query->where('event_type', $this->typeFilter);
        if ($this->sourceFilter !== '') $query->where('source', $this->sourceFilter);
        if ($this->dateFrom !== '') $query->where('occurred_at', '>=', $this->dateFrom.' 00:00:00');
        if ($this->dateTo !== '') $query->where('occurred_at', '<=', $this->dateTo.' 23:59:59');

        return view('livewire.hr.attendance.attendance-event-list', [
            'events' => $query->orderBy($this->sortField, $this->sortDirection)->paginate(20),
            'employees' => HrEmployee::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->active()->orderBy('first_name')->get(),
            'eventTypes' => AttendanceEventType::cases(),
        ])->layout('layouts.app');
    }
}
