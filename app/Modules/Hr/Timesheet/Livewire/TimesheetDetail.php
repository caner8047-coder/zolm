<?php

namespace App\Modules\Hr\Timesheet\Livewire;

use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Timesheet\Actions\CalculateTimesheetPeriodAction;
use App\Modules\Hr\Timesheet\Actions\CloseTimesheetPeriodAction;
use App\Modules\Hr\Timesheet\Actions\ConfirmTimesheetAction;
use App\Modules\Hr\Timesheet\Actions\CreateTimesheetCorrectionAction;
use App\Modules\Hr\Timesheet\Enums\TimesheetStatus;
use App\Modules\Hr\Timesheet\Models\HrTimesheet;
use App\Modules\Hr\Timesheet\Models\HrTimesheetPeriod;
use Livewire\Component;
use Livewire\WithPagination;

class TimesheetDetail extends Component
{
    use WithPagination;
    public int $periodId;
    public string $search = '';
    public string $statusFilter = '';
    public string $sortField = 'work_date';
    public string $sortDirection = 'asc';
    public array $visibleColumns = ['employee', 'date', 'scheduled', 'worked', 'leave', 'overtime', 'missing', 'status', 'actions'];
    public ?int $correctingId = null;
    public array $correction = [];
    public string $correctionReason = '';
    public static array $sortableColumns = ['work_date', 'scheduled_minutes', 'worked_minutes', 'leave_minutes', 'overtime_minutes', 'missing_minutes', 'status'];

    public function mount(int $period): void { $this->periodId = $period; $this->period(); }
    public function updated($property): void { if (in_array($property, ['search', 'statusFilter'], true)) $this->resetPage(); }
    public function sortTable(string $field): void { if (!in_array($field, self::$sortableColumns, true)) return; if ($this->sortField === $field) $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc'; else { $this->sortField = $field; $this->sortDirection = 'asc'; } }
    public function toggleColumn(string $column): void { $allowed = ['employee','date','scheduled','worked','leave','overtime','missing','status','actions']; if (!in_array($column,$allowed,true)) return; if (in_array($column,$this->visibleColumns,true)) { if (count($this->visibleColumns)>1) $this->visibleColumns=array_values(array_diff($this->visibleColumns,[$column])); } else $this->visibleColumns[]=$column; }
    public function calculate(CalculateTimesheetPeriodAction $action): void { $count=$action->execute($this->period()); session()->flash('success',"{$count} puantaj satırı hesaplandı."); }
    public function confirm(int $id, ConfirmTimesheetAction $action): void { $action->execute($this->row($id)); session()->flash('success','Puantaj satırı onaylandı.'); }
    public function confirmAll(ConfirmTimesheetAction $action): void { $rows=$this->period()->timesheets()->where('status',TimesheetStatus::Draft->value)->get(); foreach($rows as $row) $action->execute($row); session()->flash('success',$rows->count().' puantaj satırı onaylandı.'); }
    public function close(CloseTimesheetPeriodAction $action): void { $action->execute($this->period()); session()->flash('success','Puantaj dönemi kapatıldı.'); }
    public function startCorrection(int $id): void { $row=$this->row($id)->load('latestCorrection'); $this->correctingId=$row->id; foreach(['worked_minutes','break_minutes','leave_minutes','overtime_minutes','missing_minutes'] as $field) $this->correction[$field]=(int)$row->effective($field); $this->correctionReason=''; }
    public function saveCorrection(CreateTimesheetCorrectionAction $action): void { $this->validate(['correction.worked_minutes'=>'required|integer|min:0','correction.break_minutes'=>'required|integer|min:0','correction.leave_minutes'=>'required|integer|min:0','correction.overtime_minutes'=>'required|integer|min:0','correction.missing_minutes'=>'required|integer|min:0','correctionReason'=>'required|string|max:1000']); $action->execute($this->row($this->correctingId),$this->correction,$this->correctionReason); $this->reset(['correctingId','correction','correctionReason']); session()->flash('success','Puantaj düzeltme revizyonu oluşturuldu.'); }
    public function render()
    {
        $period=$this->period(); $query=HrTimesheet::withoutGlobalScope('tenant')->where('legal_entity_id',$period->legal_entity_id)->where('timesheet_period_id',$period->id)->with(['employee','latestCorrection']); if($this->search!=='')$query->whereHas('employee',fn($q)=>$q->search($this->search)); if($this->statusFilter!=='')$query->where('status',$this->statusFilter);
        $all=HrTimesheet::withoutGlobalScope('tenant')->where('legal_entity_id',$period->legal_entity_id)->where('timesheet_period_id',$period->id)->with('latestCorrection')->get();
        return view('livewire.hr.timesheet.timesheet-detail',['period'=>$period,'rows'=>$query->orderBy($this->sortField,$this->sortDirection)->paginate(25),'totals'=>collect(['scheduled_minutes','worked_minutes','leave_minutes','overtime_minutes','missing_minutes'])->mapWithKeys(fn($field)=>[$field=>$all->sum(fn($row)=>(int)$row->effective($field))])])->layout('layouts.app');
    }
    private function period(): HrTimesheetPeriod { return HrTimesheetPeriod::withoutGlobalScope('tenant')->where('legal_entity_id',app(TenantContext::class)->getId())->findOrFail($this->periodId); }
    private function row(int $id): HrTimesheet { return HrTimesheet::withoutGlobalScope('tenant')->where('legal_entity_id',app(TenantContext::class)->getId())->where('timesheet_period_id',$this->periodId)->findOrFail($id); }
}
