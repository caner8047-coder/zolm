<?php

namespace App\Modules\Hr\Safety\Livewire;

use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Safety\Actions\ManageSafetyAction;
use App\Modules\Hr\Safety\Models\HrHealthRecord;
use App\Modules\Hr\Safety\Models\HrSafetyAction;
use App\Modules\Hr\Safety\Models\HrSafetyIncident;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class SafetyWorkspace extends Component
{
    public string $incidentType = 'near_miss';

    public string $severity = 'medium';

    public string $occurredAt = '';

    public string $location = '';

    public string $description = '';

    public string $immediateAction = '';

    public bool $lostTime = false;

    public ?int $affectedEmployeeId = null;

    public ?int $selectedIncidentId = null;

    public string $actionTitle = '';

    public string $actionDueOn = '';

    public array $actionEvidence = [];

    public string $statusFilter = '';

    public string $sortField = 'occurred_at';

    public string $sortDirection = 'desc';

    public array $visibleColumns = ['number', 'type', 'severity', 'occurred', 'location', 'status', 'actions'];

    public ?int $healthEmployeeId = null;

    public string $healthType = 'periodic_exam';

    public string $healthRecordedOn = '';

    public string $healthExpiresOn = '';

    public string $healthProvider = '';

    public string $healthResult = '';

    public string $healthDetails = '';

    public const COLUMNS = [
        'number' => 'Olay no',
        'type' => 'Tür',
        'severity' => 'Şiddet',
        'occurred' => 'Olay zamanı',
        'location' => 'Konum',
        'status' => 'Durum',
        'actions' => 'Aksiyon',
    ];

    private const SORTABLE_COLUMNS = [
        'number' => 'incident_number',
        'type' => 'incident_type',
        'severity' => 'severity',
        'occurred' => 'occurred_at',
        'location' => 'location',
        'status' => 'status',
    ];

    public function mount(): void
    {
        $this->occurredAt = now()->format('Y-m-d\TH:i');
        $this->healthRecordedOn = today()->toDateString();
    }

    public function report(ManageSafetyAction $action): void
    {
        $incident = $action->reportIncident([
            'affected_employee_id' => $this->affectedEmployeeId,
            'incident_type' => $this->incidentType,
            'severity' => $this->severity,
            'occurred_at' => $this->occurredAt,
            'location' => $this->location,
            'description' => $this->description,
            'immediate_action' => $this->immediateAction,
            'lost_time' => $this->lostTime,
        ]);
        $this->selectedIncidentId = $incident->id;
        $this->reset(['affectedEmployeeId', 'location', 'description', 'immediateAction', 'lostTime']);
        $this->occurredAt = now()->format('Y-m-d\TH:i');
        session()->flash('success', 'İSG olayı kaynak iziyle kaydedildi.');
    }

    public function select(int $id): void
    {
        $this->selectedIncidentId = $this->incidentQuery()->findOrFail($id)->id;
    }

    public function assignToSelf(ManageSafetyAction $action): void
    {
        $action->assignToSelf($this->selectedIncident());
        session()->flash('success', 'Olay incelemeye alındı.');
    }

    public function addCorrectiveAction(ManageSafetyAction $action): void
    {
        $action->addCorrectiveAction($this->selectedIncident(), ['title' => $this->actionTitle, 'due_on' => $this->actionDueOn ?: null]);
        $this->reset(['actionTitle', 'actionDueOn']);
        session()->flash('success', 'Düzeltici aksiyon eklendi.');
    }

    public function completeAction(ManageSafetyAction $manager, int $id): void
    {
        $row = HrSafetyAction::withoutGlobalScope('tenant')
            ->where('legal_entity_id', app(TenantContext::class)->getId())
            ->findOrFail($id);
        $manager->completeCorrectiveAction($row, $this->actionEvidence[$id] ?? '');
        unset($this->actionEvidence[$id]);
        session()->flash('success', 'Aksiyon kanıtıyla tamamlandı.');
    }

    public function closeIncident(ManageSafetyAction $action): void
    {
        $action->closeIncident($this->selectedIncident());
        session()->flash('success', 'İSG olayı kapatıldı.');
    }

    public function createHealthRecord(ManageSafetyAction $action): void
    {
        $employee = $this->tenantEmployees()->findOrFail($this->healthEmployeeId);
        $action->createHealthRecord($employee, [
            'record_type' => $this->healthType,
            'recorded_on' => $this->healthRecordedOn,
            'expires_on' => $this->healthExpiresOn ?: null,
            'provider' => $this->healthProvider,
            'result' => $this->healthResult,
            'details' => $this->healthDetails,
        ]);
        $this->reset(['healthEmployeeId', 'healthExpiresOn', 'healthProvider', 'healthResult', 'healthDetails']);
        $this->healthRecordedOn = today()->toDateString();
        session()->flash('success', 'Sağlık kaydı şifreli olarak eklendi.');
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
        abort_if(in_array($column, ['number', 'actions'], true), 422);
        $this->visibleColumns = in_array($column, $this->visibleColumns, true)
            ? array_values(array_diff($this->visibleColumns, [$column]))
            : array_values(array_intersect(array_keys(self::COLUMNS), [...$this->visibleColumns, $column]));
    }

    public function render()
    {
        $incidents = $this->incidentQuery()
            ->with(['reporter', 'affectedEmployee', 'assignee'])
            ->withCount(['actions', 'actions as pending_actions_count' => fn ($query) => $query->where('status', 'pending')])
            ->when($this->statusFilter !== '', fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->orderBy($this->sortField, $this->sortDirection)
            ->limit(100)
            ->get();
        $selected = $this->selectedIncidentId
            ? $this->incidentQuery()->with(['actions.owner', 'affectedEmployee'])->findOrFail($this->selectedIncidentId)
            : $incidents->first();
        if ($selected) {
            $this->selectedIncidentId = $selected->id;
        }

        $healthRecords = auth()->user()?->hasHrPermission('hr.isg.view_health')
            ? HrHealthRecord::withoutGlobalScope('tenant')->where('legal_entity_id', app(TenantContext::class)->getId())->with('employee')->latest('recorded_on')->limit(30)->get()
            : collect();

        return view('livewire.hr.safety.safety-workspace', [
            'incidents' => $incidents,
            'selected' => $selected,
            'employees' => $this->tenantEmployees()->active()->orderBy('first_name')->get(),
            'healthRecords' => $healthRecords,
            'columnLabels' => self::COLUMNS,
            'sortableColumns' => self::SORTABLE_COLUMNS,
        ])->layout('layouts.app');
    }

    private function incidentQuery(): Builder
    {
        $tenantId = app(TenantContext::class)->getId();

        return HrSafetyIncident::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->when(! auth()->user()?->hasHrPermission('hr.isg.manage'), function (Builder $query) use ($tenantId) {
                $employeeId = HrEmployee::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('user_id', auth()->id())->value('id');
                $query->where('reporter_employee_id', $employeeId ?: 0);
            });
    }

    private function selectedIncident(): HrSafetyIncident
    {
        return $this->incidentQuery()->findOrFail($this->selectedIncidentId);
    }

    private function tenantEmployees(): Builder
    {
        return HrEmployee::withoutGlobalScope('tenant')->where('legal_entity_id', app(TenantContext::class)->getId());
    }
}
