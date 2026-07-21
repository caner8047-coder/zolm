<?php

namespace App\Modules\Hr\Integration\Livewire;

use App\Modules\Hr\Core\Models\HrIntegrationOutbox;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Integration\Actions\RetryHrIntegrationOutboxAction;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class HrIntegrationWorkspace extends Component
{
    public string $search = '';

    public string $targetFilter = '';

    public string $statusFilter = '';

    public string $sortField = 'created_at';

    public string $sortDirection = 'desc';

    public array $visibleColumns = ['target', 'event', 'source', 'status', 'attempts', 'updated', 'actions'];

    public const COLUMNS = [
        'target' => 'Hedef',
        'event' => 'Olay',
        'source' => 'Kaynak izi',
        'status' => 'Durum',
        'attempts' => 'Deneme',
        'updated' => 'Güncelleme',
        'actions' => 'İşlem',
    ];

    private const SORTABLE_COLUMNS = [
        'target' => 'target',
        'event' => 'event_type',
        'status' => 'status',
        'attempts' => 'attempt_count',
        'updated' => 'updated_at',
    ];

    public function retry(RetryHrIntegrationOutboxAction $action, int $id): void
    {
        $row = $this->outboxQuery()->findOrFail($id);
        $action->execute($row);
        session()->flash('success', 'Entegrasyon kaydı yeniden kuyruğa alındı.');
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
        abort_if(in_array($column, ['target', 'event', 'actions'], true), 422);
        $this->visibleColumns = in_array($column, $this->visibleColumns, true)
            ? array_values(array_diff($this->visibleColumns, [$column]))
            : array_values(array_intersect(array_keys(self::COLUMNS), [...$this->visibleColumns, $column]));
    }

    public function render()
    {
        $base = $this->outboxQuery();
        $stats = [
            'pending' => (clone $base)->where('status', 'pending')->count(),
            'failed' => (clone $base)->where('status', 'failed')->count(),
            'processed' => (clone $base)->where('status', 'processed')->count(),
        ];
        $rows = $base
            ->when($this->targetFilter !== '', fn (Builder $query) => $query->where('target', $this->targetFilter))
            ->when($this->statusFilter !== '', fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->when($this->search !== '', fn (Builder $query) => $query->where(function (Builder $nested) {
                $nested->where('event_type', 'like', '%'.$this->search.'%')->orWhere('source_key', 'like', '%'.$this->search.'%');
            }))
            ->orderBy($this->sortField, $this->sortDirection)
            ->limit(150)
            ->get();

        return view('livewire.hr.integration.hr-integration-workspace', [
            'rows' => $rows,
            'stats' => $stats,
            'columnLabels' => self::COLUMNS,
            'sortableColumns' => self::SORTABLE_COLUMNS,
        ])->layout('layouts.app');
    }

    private function outboxQuery(): Builder
    {
        return HrIntegrationOutbox::withoutGlobalScope('tenant')
            ->where('legal_entity_id', app(TenantContext::class)->getId());
    }
}
