<?php

namespace App\Modules\Hr\Timesheet\Livewire;

use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Timesheet\Actions\CalculateTimesheetPeriodAction;
use App\Modules\Hr\Timesheet\Actions\CreateTimesheetPeriodAction;
use App\Modules\Hr\Timesheet\Models\HrTimesheetPeriod;
use Livewire\Component;

class TimesheetPeriodList extends Component
{
    public string $name = '';
    public string $startsOn = '';
    public string $endsOn = '';

    public function mount(): void
    {
        $this->startsOn = now()->startOfMonth()->toDateString(); $this->endsOn = now()->endOfMonth()->toDateString(); $this->name = now()->translatedFormat('F Y');
    }
    public function create(CreateTimesheetPeriodAction $action): void
    {
        $this->validate(['name' => 'required|string|max:120', 'startsOn' => 'required|date', 'endsOn' => 'required|date|after_or_equal:startsOn']);
        $period = $action->execute($this->name, $this->startsOn, $this->endsOn);
        session()->flash('success', 'Puantaj dönemi oluşturuldu.');
        $this->redirectRoute('hr.timesheets.show', ['period' => $period->id]);
    }
    public function calculate(int $id, CalculateTimesheetPeriodAction $action): void
    {
        $period = HrTimesheetPeriod::withoutGlobalScope('tenant')->where('legal_entity_id', app(TenantContext::class)->getId())->findOrFail($id);
        $count = $action->execute($period);
        session()->flash('success', "{$count} günlük puantaj satırı hesaplandı.");
    }
    public function render()
    {
        return view('livewire.hr.timesheet.timesheet-period-list', ['periods' => HrTimesheetPeriod::withoutGlobalScope('tenant')->where('legal_entity_id', app(TenantContext::class)->getId())->withCount('timesheets')->orderByDesc('starts_on')->get()])->layout('layouts.app');
    }
}
