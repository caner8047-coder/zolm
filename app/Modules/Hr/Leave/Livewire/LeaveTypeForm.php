<?php

namespace App\Modules\Hr\Leave\Livewire;

use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Leave\Models\HrLeaveType;
use Livewire\Component;

class LeaveTypeForm extends Component
{
    public ?int $typeId = null;
    public string $name = '';
    public string $code = '';
    public string $unit = 'day';
    public bool $is_paid = true;
    public bool $requires_document = false;
    public bool $allows_negative_balance = false;
    public bool $is_active = true;

    public function mount(?int $id = null): void
    {
        if (!$id) return;
        $type = HrLeaveType::withoutGlobalScope('tenant')->where('legal_entity_id', app(TenantContext::class)->getId())->findOrFail($id);
        $this->typeId = $type->id;
        foreach (['name', 'code', 'unit', 'is_paid', 'requires_document', 'allows_negative_balance', 'is_active'] as $field) $this->{$field} = $type->{$field} instanceof \BackedEnum ? $type->{$field}->value : $type->{$field};
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.leaves.manage_type'), 403);
        $this->validate(['name' => 'required|string|max:255', 'code' => 'required|string|max:50', 'unit' => 'required|in:day,hour']);
        $tenantId = app(TenantContext::class)->getId();
        $duplicate = HrLeaveType::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('code', $this->code);
        if ($this->typeId) $duplicate->where('id', '!=', $this->typeId);
        if ($duplicate->exists()) { $this->addError('code', 'Bu kod aynı tüzel kişilikte zaten kullanılıyor.'); return; }

        $data = $this->only(['name', 'code', 'unit', 'is_paid', 'requires_document', 'allows_negative_balance', 'is_active']) + ['legal_entity_id' => $tenantId, 'updated_by' => auth()->id()];
        if ($this->typeId) {
            HrLeaveType::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('id', $this->typeId)->update($data);
            session()->flash('success', 'İzin türü güncellendi.');
        } else {
            HrLeaveType::create($data + ['created_by' => auth()->id()]);
            session()->flash('success', 'İzin türü oluşturuldu.');
        }
        $this->redirect(route('hr.settings.leave-types'));
    }

    public function render() { return view('livewire.hr.leave.leave-type-form', ['isEdit' => $this->typeId !== null])->layout('layouts.app'); }
}
