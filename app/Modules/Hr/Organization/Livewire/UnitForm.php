<?php

namespace App\Modules\Hr\Organization\Livewire;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Organization\Models\HrDepartment;
use App\Modules\Hr\Organization\Models\HrUnit;
use Livewire\Component;

class UnitForm extends Component
{
    public ?int $unitId = null;
    public string $name = '';
    public string $code = '';
    public ?int $department_id = null;
    public ?int $manager_employee_id = null;
    public bool $is_active = true;
    public int $sort_order = 0;

    public function mount(?HrUnit $unit = null): void
    {
        if ($unit) {
            abort_unless($unit->department?->legal_entity_id === app(TenantContext::class)->getId(), 404);
            $this->unitId = $unit->id;
            $unitModel = HrUnit::withoutGlobalScope('tenant')
                ->whereHas('department', fn($query) => $query->where('legal_entity_id', app(TenantContext::class)->getId()))
                ->findOrFail($unit->id);
            $this->name = $unitModel->name;
            $this->code = $unitModel->code;
            $this->department_id = $unitModel->department_id;
            $this->manager_employee_id = $unitModel->manager_employee_id;
            $this->is_active = $unitModel->is_active;
            $this->sort_order = $unitModel->sort_order;
        }
    }

    public function render()
    {
        $tenantId = app(TenantContext::class)->getId();

        return view('livewire.hr.organization.unit-form', [
            'departments' => HrDepartment::withoutGlobalScope('tenant')
                ->where('legal_entity_id', $tenantId)
                ->active()
                ->ordered()
                ->get(),
            'isEdit' => $this->unitId !== null,
        ])->layout('layouts.app');
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
            'department_id' => 'required|exists:hr_departments,id',
        ]);

        // Departman aktif mi kontrol et
        $tenantId = app(TenantContext::class)->getId();
        $dept = HrDepartment::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->find($this->department_id);
        if (!$dept || !$dept->is_active) {
            session()->flash('error', 'Pasif departmana birim eklenemez.');
            return;
        }

        // Code benzersizliği
        $query = HrUnit::withoutGlobalScope('tenant')
            ->whereHas('department', fn($departmentQuery) => $departmentQuery->where('legal_entity_id', $tenantId))
            ->where('code', $this->code);

        if ($this->unitId) {
            $query->where('id', '!=', $this->unitId);
        }

        if ($query->exists()) {
            session()->flash('error', 'Bu kod zaten kullanılıyor.');
            return;
        }

        $data = [
            'name' => $this->name,
            'code' => $this->code,
            'department_id' => $this->department_id,
            'manager_employee_id' => $this->manager_employee_id,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'updated_by' => auth()->id(),
        ];

        if ($this->unitId) {
            $unit = HrUnit::withoutGlobalScope('tenant')
                ->whereHas('department', fn($query) => $query->where('legal_entity_id', $tenantId))
                ->findOrFail($this->unitId);
            $unit->update($data);
            app(HrAuditService::class)->log('unit_updated', $unit);
            session()->flash('success', 'Birim güncellendi.');
        } else {
            $data['created_by'] = auth()->id();
            $unit = HrUnit::create($data);
            app(HrAuditService::class)->log('unit_created', $unit);
            session()->flash('success', 'Birim oluşturuldu.');
        }

        $this->redirect(route('hr.settings.units'));
    }
}
