<?php

namespace App\Modules\Hr\Overtime\Livewire;

use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Overtime\Actions\CreateOvertimeRequestAction;
use App\Modules\Hr\Overtime\Actions\CancelOvertimeRequestAction;
use App\Modules\Hr\Overtime\Actions\DecideOvertimeRequestAction;
use App\Modules\Hr\Overtime\Models\HrOvertimeRequest;
use App\Modules\Hr\Overtime\Models\HrOvertimeType;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Livewire\Component;
use Livewire\WithPagination;

class OvertimeWorkspace extends Component
{
    use WithPagination;
    public bool $selfService = false;
    public ?int $employeeId = null; public ?int $typeId = null; public string $workDate=''; public string $startsAt='18:00'; public string $endsAt='20:00'; public string $reason=''; public string $projectReference=''; public string $productionOrderReference=''; public string $statusFilter=''; public string $search=''; public ?int $decidingId=null; public string $decisionNote=''; public ?int $approvedMinutes=null;
    public function mount(bool $selfService=false): void { $this->selfService=$selfService; $this->workDate=now()->addDay()->toDateString(); if($selfService)$this->employeeId=$this->ownEmployee()->id; }
    public function create(CreateOvertimeRequestAction $action): void { $this->validate(['employeeId'=>'required|integer','typeId'=>'required|integer','workDate'=>'required|date','startsAt'=>'required|date_format:H:i','endsAt'=>'required|date_format:H:i','reason'=>'required|string|max:1000']); $tenant=app(TenantContext::class)->getId(); $employee=HrEmployee::withoutGlobalScope('tenant')->where('legal_entity_id',$tenant)->findOrFail($this->employeeId); $type=HrOvertimeType::withoutGlobalScope('tenant')->where('legal_entity_id',$tenant)->findOrFail($this->typeId); $action->execute($employee,$type,$this->workDate,$this->startsAt,$this->endsAt,$this->reason,$this->projectReference,$this->productionOrderReference); $this->reset(['typeId','reason','projectReference','productionOrderReference']); if($this->selfService)$this->employeeId=$this->ownEmployee()->id; session()->flash('success','Fazla mesai talebi oluşturuldu.'); }
    public function startDecision(int $id): void { $request=$this->request($id); $this->decidingId=$id; $this->approvedMinutes=$request->requested_minutes; $this->decisionNote=''; }
    public function approve(DecideOvertimeRequestAction $action): void { $this->validate(['approvedMinutes'=>'required|integer|min:1','decisionNote'=>'nullable|string|max:1000']); $action->approve($this->request($this->decidingId),$this->approvedMinutes,$this->decisionNote?:null); $this->reset(['decidingId','approvedMinutes','decisionNote']); session()->flash('success','Fazla mesai onay adımı tamamlandı.'); }
    public function reject(DecideOvertimeRequestAction $action): void { $this->validate(['decisionNote'=>'required|string|max:1000']); $action->reject($this->request($this->decidingId),$this->decisionNote); $this->reset(['decidingId','approvedMinutes','decisionNote']); session()->flash('success','Fazla mesai talebi reddedildi.'); }
    public function cancel(int $id, CancelOvertimeRequestAction $action): void { $action->execute($this->request($id)->load('employee')); session()->flash('success','Fazla mesai talebi iptal edildi.'); }
    public function render() { $tenant=app(TenantContext::class)->getId(); $query=HrOvertimeRequest::withoutGlobalScope('tenant')->where('legal_entity_id',$tenant)->with(['employee','overtimeType'])->latest('work_date'); if($this->selfService)$query->where('employee_id',$this->ownEmployee()->id); elseif($this->search!=='')$query->whereHas('employee',fn($q)=>$q->search($this->search)); if($this->statusFilter!=='')$query->where('status',$this->statusFilter); return view('livewire.hr.overtime.overtime-workspace',['requests'=>$query->paginate(20),'employees'=>HrEmployee::withoutGlobalScope('tenant')->where('legal_entity_id',$tenant)->active()->orderBy('first_name')->get(),'types'=>HrOvertimeType::withoutGlobalScope('tenant')->where('legal_entity_id',$tenant)->where('is_active',true)->orderBy('name')->get()])->layout('layouts.app'); }
    private function ownEmployee(): HrEmployee { return HrEmployee::withoutGlobalScope('tenant')->where('legal_entity_id',app(TenantContext::class)->getId())->where('user_id',auth()->id())->firstOrFail(); }
    private function request(int $id): HrOvertimeRequest { return HrOvertimeRequest::withoutGlobalScope('tenant')->where('legal_entity_id',app(TenantContext::class)->getId())->findOrFail($id); }
}
