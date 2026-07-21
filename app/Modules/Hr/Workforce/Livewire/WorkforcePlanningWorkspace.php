<?php

namespace App\Modules\Hr\Workforce\Livewire;

use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Organization\Models\HrDepartment;
use App\Modules\Hr\Organization\Models\HrPosition;
use App\Modules\Hr\Workforce\Actions\ManageWorkforcePlanAction;
use App\Modules\Hr\Workforce\Models\HrWorkforcePlan;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class WorkforcePlanningWorkspace extends Component
{
    public string $name = '';

    public string $startsOn = '';

    public string $endsOn = '';

    public string $budget = '';

    public string $currency = 'TRY';

    public ?int $selectedPlanId = null;

    public ?int $departmentId = null;

    public ?int $positionId = null;

    public string $plannedFte = '';

    public string $plannedCost = '';

    public string $notes = '';

    public string $sortField = 'department_id';

    public string $sortDirection = 'asc';

    public array $visibleColumns = [
        'department', 'position', 'planned_fte', 'actual_fte', 'planned_cost', 'actual_cost', 'gap',
    ];

    public const COLUMNS = [
        'department' => 'Departman',
        'position' => 'Pozisyon',
        'planned_fte' => 'Plan FTE',
        'actual_fte' => 'Dolu FTE',
        'planned_cost' => 'Plan maliyet',
        'actual_cost' => 'Gerçek maliyet',
        'gap' => 'FTE farkı',
    ];

    private const SORTABLE_COLUMNS = [
        'department' => 'department_id',
        'position' => 'position_id',
        'planned_fte' => 'planned_fte',
        'actual_fte' => 'actual_fte_snapshot',
    ];

    public function mount(): void
    {
        $this->startsOn = today()->startOfYear()->toDateString();
        $this->endsOn = today()->endOfYear()->toDateString();
    }

    public function create(ManageWorkforcePlanAction $action): void
    {
        $plan = $action->create([
            'name' => $this->name,
            'starts_on' => $this->startsOn,
            'ends_on' => $this->endsOn,
            'budget' => $this->budget,
            'currency' => $this->currency,
        ]);

        $this->selectedPlanId = $plan->id;
        $this->reset(['name', 'budget']);
        session()->flash('success', 'Kadro planı oluşturuldu.');
    }

    public function addLine(ManageWorkforcePlanAction $action): void
    {
        $this->validate([
            'departmentId' => 'required|integer',
            'positionId' => 'required|integer',
            'plannedFte' => 'required|numeric|min:0.01',
            'plannedCost' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
        ]);

        $action->addLine(
            $this->plan(),
            $this->department($this->departmentId),
            $this->position($this->positionId),
            (float) $this->plannedFte,
            (float) $this->plannedCost,
            $this->notes ?: null,
        );

        $this->reset(['departmentId', 'positionId', 'plannedFte', 'plannedCost', 'notes']);
        session()->flash('success', 'Kadro satırı kaydedildi.');
    }

    public function select(int $id): void
    {
        $this->selectedPlanId = $this->tenantPlans()->findOrFail($id)->id;
    }

    public function submit(ManageWorkforcePlanAction $action): void
    {
        $action->submit($this->plan());
        session()->flash('success', 'Kadro planı onaya gönderildi.');
    }

    public function approve(ManageWorkforcePlanAction $action): void
    {
        $action->approve($this->plan());
        session()->flash('success', 'Kadro planı gerçekleşen FTE ve maliyet anlık görüntüsüyle onaylandı.');
    }

    public function sortTable(string $column): void
    {
        abort_unless(isset(self::SORTABLE_COLUMNS[$column]), 422);

        $field = self::SORTABLE_COLUMNS[$column];
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function toggleColumn(string $column): void
    {
        abort_unless(isset(self::COLUMNS[$column]), 422);
        abort_if(in_array($column, ['department', 'position'], true), 422);

        $this->visibleColumns = in_array($column, $this->visibleColumns, true)
            ? array_values(array_diff($this->visibleColumns, [$column]))
            : array_values(array_intersect(array_keys(self::COLUMNS), [...$this->visibleColumns, $column]));
    }

    public function render()
    {
        $plans = $this->tenantPlans()->withCount('lines')->latest()->get();
        $selected = $this->selectedPlanId
            ? $this->tenantPlans()->findOrFail($this->selectedPlanId)
            : $plans->first();

        if ($selected) {
            $this->selectedPlanId = $selected->id;
        }

        $lines = $selected
            ? $selected->lines()->with(['department', 'position'])->orderBy($this->sortField, $this->sortDirection)->get()
            : collect();
        $tenantId = app(TenantContext::class)->getId();

        return view('livewire.hr.workforce.workforce-planning-workspace', [
            'plans' => $plans,
            'selected' => $selected,
            'lines' => $lines,
            'departments' => HrDepartment::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->active()->ordered()->get(),
            'positions' => HrPosition::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->active()->ordered()->get(),
            'columnLabels' => self::COLUMNS,
            'sortableColumns' => self::SORTABLE_COLUMNS,
        ])->layout('layouts.app');
    }

    private function tenantPlans(): Builder
    {
        return HrWorkforcePlan::withoutGlobalScope('tenant')
            ->where('legal_entity_id', app(TenantContext::class)->getId());
    }

    private function plan(): HrWorkforcePlan
    {
        return $this->tenantPlans()->findOrFail($this->selectedPlanId);
    }

    private function department(int $id): HrDepartment
    {
        return HrDepartment::withoutGlobalScope('tenant')
            ->where('legal_entity_id', app(TenantContext::class)->getId())
            ->findOrFail($id);
    }

    private function position(int $id): HrPosition
    {
        return HrPosition::withoutGlobalScope('tenant')
            ->where('legal_entity_id', app(TenantContext::class)->getId())
            ->findOrFail($id);
    }
}
