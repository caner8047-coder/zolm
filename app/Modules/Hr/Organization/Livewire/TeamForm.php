<?php

namespace App\Modules\Hr\Organization\Livewire;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Organization\Models\HrDepartment;
use App\Modules\Hr\Organization\Models\HrTeam;
use App\Modules\Hr\Organization\Models\HrUnit;
use Livewire\Component;

class TeamForm extends Component
{
    public ?int $teamId = null;
    public string $name = '';
    public ?int $unit_id = null;
    public ?int $lead_employee_id = null;
    public bool $is_active = true;
    public int $sort_order = 0;

    public function mount(?HrTeam $team = null): void
    {
        if ($team) {
            abort_unless($team->unit?->department?->legal_entity_id === app(TenantContext::class)->getId(), 404);
            $this->teamId = $team->id;
            $teamModel = HrTeam::withoutGlobalScope('tenant')
                ->whereHas('unit.department', fn($query) => $query->where('legal_entity_id', app(TenantContext::class)->getId()))
                ->findOrFail($team->id);
            $this->name = $teamModel->name;
            $this->unit_id = $teamModel->unit_id;
            $this->lead_employee_id = $teamModel->lead_employee_id;
            $this->is_active = $teamModel->is_active;
            $this->sort_order = $teamModel->sort_order;
        }
    }

    public function render()
    {
        $tenantId = app(TenantContext::class)->getId();

        return view('livewire.hr.organization.team-form', [
            'units' => HrUnit::withoutGlobalScope('tenant')
                ->whereHas('department', fn($query) => $query->where('legal_entity_id', $tenantId))
                ->active()
                ->with('department')
                ->ordered()
                ->get(),
            'isEdit' => $this->teamId !== null,
        ])->layout('layouts.app');
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'unit_id' => 'required|exists:hr_units,id',
        ]);

        // Birim aktif mi kontrol et
        $tenantId = app(TenantContext::class)->getId();
        $unit = HrUnit::withoutGlobalScope('tenant')
            ->whereHas('department', fn($query) => $query->where('legal_entity_id', $tenantId))
            ->find($this->unit_id);
        if (!$unit || !$unit->is_active) {
            session()->flash('error', 'Pasif birime ekip eklenemez.');
            return;
        }

        // Departman aktif mi kontrol et
        if ($unit && $unit->department && !$unit->department->is_active) {
            session()->flash('error', 'Pasif departmana ait birime ekip eklenemez.');
            return;
        }

        // Aynı birimde aynı isim kontrolü
        $query = HrTeam::withoutGlobalScope('tenant')
            ->where('unit_id', $this->unit_id)
            ->where('name', $this->name);

        if ($this->teamId) {
            $query->where('id', '!=', $this->teamId);
        }

        if ($query->exists()) {
            session()->flash('error', 'Bu birimde aynı isimde ekip zaten var.');
            return;
        }

        $data = [
            'name' => $this->name,
            'unit_id' => $this->unit_id,
            'lead_employee_id' => $this->lead_employee_id,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'updated_by' => auth()->id(),
        ];

        if ($this->teamId) {
            $team = HrTeam::withoutGlobalScope('tenant')
                ->whereHas('unit.department', fn($query) => $query->where('legal_entity_id', $tenantId))
                ->findOrFail($this->teamId);
            $team->update($data);
            app(HrAuditService::class)->log('team_updated', $team);
            session()->flash('success', 'Ekip güncellendi.');
        } else {
            $data['created_by'] = auth()->id();
            $team = HrTeam::create($data);
            app(HrAuditService::class)->log('team_created', $team);
            session()->flash('success', 'Ekip oluşturuldu.');
        }

        $this->redirect(route('hr.settings.teams'));
    }
}
