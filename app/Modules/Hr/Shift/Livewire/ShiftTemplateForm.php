<?php

namespace App\Modules\Hr\Shift\Livewire;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Shift\Models\HrShiftTemplate;
use App\Modules\Hr\Training\Models\HrTrainingCourse;
use Livewire\Component;

class ShiftTemplateForm extends Component
{
    public ?int $templateId = null;
    public string $code = '';
    public string $name = '';
    public string $starts_at = '08:00';
    public string $ends_at = '17:00';
    public int $break_minutes = 60;
    public bool $crosses_midnight = false;
    public string $color = '#334155';
    public bool $is_active = true;
    public ?int $required_training_course_id = null;

    public function mount(?int $id = null): void
    {
        if (!$id) return;
        $template = HrShiftTemplate::withoutGlobalScope('tenant')->where('legal_entity_id', app(TenantContext::class)->getId())->findOrFail($id);
        $this->templateId = $template->id;
        foreach (['code', 'name', 'starts_at', 'ends_at', 'break_minutes', 'crosses_midnight', 'color', 'is_active', 'required_training_course_id'] as $field) $this->{$field} = $template->{$field};
        $this->starts_at = substr($this->starts_at, 0, 5); $this->ends_at = substr($this->ends_at, 0, 5);
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.shifts.manage'), 403);
        $tenantId = app(TenantContext::class)->getId();
        $data = $this->validate(['code' => 'required|string|max:40', 'name' => 'required|string|max:120', 'starts_at' => 'required|date_format:H:i', 'ends_at' => 'required|date_format:H:i', 'break_minutes' => 'required|integer|min:0|max:720', 'crosses_midnight' => 'boolean', 'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'], 'is_active' => 'boolean', 'required_training_course_id' => 'nullable|integer']);
        if ($data['required_training_course_id']) abort_unless(HrTrainingCourse::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('is_active', true)->whereKey($data['required_training_course_id'])->exists(), 422, 'Zorunlu eğitim geçersiz.');
        $duplicate = HrShiftTemplate::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('code', strtoupper($data['code']))->when($this->templateId, fn ($q) => $q->whereKeyNot($this->templateId))->exists();
        abort_if($duplicate, 422, 'Bu vardiya kodu zaten kullanılıyor.');
        $data['code'] = strtoupper($data['code']); $data['updated_by'] = auth()->id();
        if ($this->templateId) {
            $template = HrShiftTemplate::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->findOrFail($this->templateId); $template->update($data); $event = 'shift_template_updated';
        } else {
            $template = HrShiftTemplate::create($data + ['legal_entity_id' => $tenantId, 'created_by' => auth()->id()]); $event = 'shift_template_created';
        }
        app(HrAuditService::class)->log($event, $template); session()->flash('success', 'Vardiya şablonu kaydedildi.'); $this->redirect(route('hr.settings.shift-templates'));
    }

    public function render() { $tenant=app(TenantContext::class)->getId(); return view('livewire.hr.shift.shift-template-form',['trainingCourses'=>HrTrainingCourse::withoutGlobalScope('tenant')->where('legal_entity_id',$tenant)->where('is_active',true)->orderBy('title')->get()])->layout('layouts.app'); }
}
