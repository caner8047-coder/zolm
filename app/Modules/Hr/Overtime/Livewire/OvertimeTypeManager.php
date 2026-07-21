<?php

namespace App\Modules\Hr\Overtime\Livewire;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Overtime\Models\HrOvertimeType;
use Livewire\Component;

class OvertimeTypeManager extends Component
{
    public ?int $editingId=null; public string $code=''; public string $name=''; public string $multiplier='1.50'; public ?int $annualLimitMinutes=null; public bool $requiresApproval=true; public bool $isActive=true;
    public function save(HrAuditService $audit): void { abort_unless(auth()->user()?->hasHrPermission('hr.timesheet.close'),403); $this->validate(['code'=>'required|string|max:50','name'=>'required|string|max:120','multiplier'=>'required|numeric|min:1|max:10','annualLimitMinutes'=>'nullable|integer|min:1','requiresApproval'=>'boolean','isActive'=>'boolean']); $tenant=app(TenantContext::class)->getId(); $code=strtoupper(trim($this->code)); $duplicate=HrOvertimeType::withoutGlobalScope('tenant')->where('legal_entity_id',$tenant)->where('code',$code)->when($this->editingId,fn($q)=>$q->where('id','!=',$this->editingId))->exists(); abort_if($duplicate,422,'Bu fazla mesai kodu zaten kullanılıyor.'); $values=['code'=>$code,'name'=>trim($this->name),'multiplier'=>$this->multiplier,'annual_limit_minutes'=>$this->annualLimitMinutes,'requires_approval'=>$this->requiresApproval,'is_active'=>$this->isActive,'updated_by'=>auth()->id()]; if($this->editingId){$type=HrOvertimeType::withoutGlobalScope('tenant')->where('legal_entity_id',$tenant)->findOrFail($this->editingId);$type->update($values);$audit->log('overtime_type_updated',$type);}else{$type=HrOvertimeType::create($values+['legal_entity_id'=>$tenant,'created_by'=>auth()->id()]);$audit->log('overtime_type_created',$type);} $this->resetForm();session()->flash('success','Fazla mesai türü kaydedildi.'); }
    public function edit(int $id): void { $type=HrOvertimeType::withoutGlobalScope('tenant')->where('legal_entity_id',app(TenantContext::class)->getId())->findOrFail($id);$this->editingId=$type->id;$this->code=$type->code;$this->name=$type->name;$this->multiplier=$type->multiplier;$this->annualLimitMinutes=$type->annual_limit_minutes;$this->requiresApproval=$type->requires_approval;$this->isActive=$type->is_active; }
    public function resetForm(): void {$this->reset(['editingId','code','name','annualLimitMinutes']);$this->multiplier='1.50';$this->requiresApproval=true;$this->isActive=true;}
    public function render(){return view('livewire.hr.overtime.overtime-type-manager',['types'=>HrOvertimeType::withoutGlobalScope('tenant')->where('legal_entity_id',app(TenantContext::class)->getId())->orderBy('name')->get()])->layout('layouts.app');}
}
