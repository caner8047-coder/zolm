<?php
namespace App\Modules\Hr\Payroll\Livewire;
use App\Modules\Hr\Core\Services\TenantContext; use App\Modules\Hr\Payroll\Actions\ApprovePayrollPeriodAction; use App\Modules\Hr\Payroll\Actions\PreparePayrollPeriodAction; use App\Modules\Hr\Payroll\Models\HrPayrollPeriod; use App\Modules\Hr\Timesheet\Enums\TimesheetPeriodStatus; use App\Modules\Hr\Timesheet\Models\HrTimesheetPeriod; use Livewire\Component;
class PayrollWorkspace extends Component
{
 public ?int $timesheetPeriodId=null; public ?int $selectedPeriodId=null;
 public function prepare(PreparePayrollPeriodAction $action):void{$this->validate(['timesheetPeriodId'=>'required|integer']);$period=HrTimesheetPeriod::withoutGlobalScope('tenant')->where('legal_entity_id',app(TenantContext::class)->getId())->findOrFail($this->timesheetPeriodId);$prepared=$action->execute($period);$this->selectedPeriodId=$prepared->id;session()->flash('success','Bordro hazırlık paketi oluşturuldu.');}
 public function approve(ApprovePayrollPeriodAction $action):void{$action->execute($this->payrollPeriod($this->selectedPeriodId));session()->flash('success','Bordro hazırlık paketi onaylandı ve donduruldu.');}
 public function select(int $id):void{$this->selectedPeriodId=$this->payrollPeriod($id)->id;}
 public function render(){ $tenant=app(TenantContext::class)->getId();$periods=HrPayrollPeriod::withoutGlobalScope('tenant')->where('legal_entity_id',$tenant)->withCount('records')->orderByDesc('id')->get();$selected=$this->selectedPeriodId?$this->payrollPeriod($this->selectedPeriodId)->load(['records.employee','timesheetPeriod']):$periods->first()?->load(['records.employee','timesheetPeriod']);if($selected)$this->selectedPeriodId=$selected->id;return view('livewire.hr.payroll.payroll-workspace',['periods'=>$periods,'selected'=>$selected,'closedTimesheets'=>HrTimesheetPeriod::withoutGlobalScope('tenant')->where('legal_entity_id',$tenant)->where('status',TimesheetPeriodStatus::Closed->value)->orderByDesc('starts_on')->get()])->layout('layouts.app'); }
 private function payrollPeriod(int $id):HrPayrollPeriod{return HrPayrollPeriod::withoutGlobalScope('tenant')->where('legal_entity_id',app(TenantContext::class)->getId())->findOrFail($id);}
}
