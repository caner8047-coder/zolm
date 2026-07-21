<?php

namespace App\Modules\Hr\Shift\Livewire;

use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Shift\Models\HrShiftTemplate;
use Livewire\Component;

class ShiftTemplateList extends Component
{
    public string $search = '';
    public ?string $statusFilter = null;

    public function toggleActive(int $id): void
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.shifts.manage'), 403);
        $template = HrShiftTemplate::withoutGlobalScope('tenant')->where('legal_entity_id', app(TenantContext::class)->getId())->findOrFail($id);
        $template->update(['is_active' => ! $template->is_active, 'updated_by' => auth()->id()]);
        session()->flash('success', $template->is_active ? 'Vardiya şablonu aktifleştirildi.' : 'Vardiya şablonu pasifleştirildi.');
    }

    public function render()
    {
        $query = HrShiftTemplate::withoutGlobalScope('tenant')->where('legal_entity_id', app(TenantContext::class)->getId());
        if ($this->search !== '') $query->where(fn ($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('code', 'like', "%{$this->search}%"));
        if ($this->statusFilter !== null) $query->where('is_active', $this->statusFilter === 'active');
        return view('livewire.hr.shift.shift-template-list', ['templates' => $query->orderBy('starts_at')->get()])->layout('layouts.app');
    }
}
