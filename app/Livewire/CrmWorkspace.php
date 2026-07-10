<?php

namespace App\Livewire;

use App\Models\CrmCase;
use App\Models\CrmContact;
use App\Models\CrmNote;
use App\Models\CrmTask;
use App\Models\CrmTimelineEvent;
use App\Models\MarketplaceStore;
use App\Services\Crm\CrmSourceLinkService;
use App\Services\Crm\CrmProjectionService;
use App\Services\MpSettingsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithPagination;

class CrmWorkspace extends Component
{
    use WithPagination;

    public array $visibleColumns = ['musteri', 'son_olay', 'siparis', 'vaka', 'risk', 'aksiyon'];

    public static array $sortableColumns = [
        'musteri' => 'display_name',
        'son_olay' => 'last_event_at',
        'siparis' => 'order_count',
        'ciro' => 'gross_revenue_total',
        'vaka' => 'open_case_count',
        'risk' => 'risk_score',
        'deger' => 'value_score',
    ];

    public static array $allColumnDefs = [
        'musteri' => 'Müşteri',
        'son_olay' => 'Son Olay',
        'siparis' => 'Sipariş',
        'ciro' => 'Ciro',
        'vaka' => 'Vaka',
        'risk' => 'Risk',
        'deger' => 'Değer',
        'aksiyon' => 'Aksiyon',
    ];

    public string $search = '';
    public string $sourceFilter = '';
    public string $statusFilter = '';
    public string $priorityFilter = '';
    public string $storeFilter = '';
    public string $sortField = 'last_event_at';
    public string $sortDirection = 'desc';
    public ?int $selectedContactId = null;
    public string $sourceContext = '';
    public string $sourceId = '';
    public string $noteBody = '';
    public string $taskTitle = '';
    public string $taskDueAt = '';
    public string $workspaceMessage = '';
    public string $workspaceMessageTone = 'success';

    protected $queryString = [
        'search' => ['except' => ''],
        'sourceFilter' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'priorityFilter' => ['except' => ''],
        'storeFilter' => ['except' => ''],
        'selectedContactId' => ['except' => null, 'as' => 'contact'],
        'sourceContext' => ['except' => '', 'as' => 'source'],
        'sourceId' => ['except' => '', 'as' => 'sourceId'],
    ];

    public function mount(): void
    {
        if (!$this->crmTablesReady()) {
            return;
        }

        $savedVisibleColumns = $this->normalizeVisibleColumns(
            app(MpSettingsService::class)->getArray('crm.workspace.visible_columns', $this->visibleColumns)
        );

        $this->visibleColumns = $savedVisibleColumns;
        $this->selectedContactId = $this->resolveInitialContactId();
    }

    public function updated($property): void
    {
        if (in_array($property, ['search', 'sourceFilter', 'statusFilter', 'priorityFilter', 'storeFilter'], true)) {
            $this->resetPage();
        }
    }

    public function refreshWorkspace(CrmProjectionService $projectionService): void
    {
        if (!$this->crmTablesReady()) {
            $this->notify('CRM tabloları hazır değil. Önce migration çalıştırın.', 'warning');
            return;
        }

        $summary = $projectionService->projectUser(auth()->user(), [
            'recent_days' => 7,
        ]);
        $this->notify("Son 7 gün CRM’e işlendi: {$summary['contacts']} kişi sinyali, {$summary['events']} olay, {$summary['cases']} yeni vaka.");

        if (!$this->selectedContactId) {
            $this->selectedContactId = CrmContact::query()
                ->where('user_id', auth()->id())
                ->latest('last_event_at')
                ->value('id');
        }
    }

    public function sortTable(string $columnKey): void
    {
        $field = static::$sortableColumns[$columnKey] ?? null;

        if (!$field) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = $field === 'display_name' ? 'asc' : 'desc';
        }

        $this->resetPage();
    }

    public function toggleColumn(string $column): void
    {
        if (!array_key_exists($column, static::$allColumnDefs)) {
            return;
        }

        if (in_array($column, $this->visibleColumns, true)) {
            if (count($this->visibleColumns) === 1) {
                return;
            }

            $this->visibleColumns = array_values(array_diff($this->visibleColumns, [$column]));
        } else {
            $this->visibleColumns[] = $column;
            $this->visibleColumns = $this->normalizeVisibleColumns($this->visibleColumns);
        }

        app(MpSettingsService::class)->set('crm.workspace.visible_columns', $this->visibleColumns);
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->sourceFilter = '';
        $this->statusFilter = '';
        $this->priorityFilter = '';
        $this->storeFilter = '';
        $this->resetPage();
    }

    public function selectContact(int $contactId): void
    {
        $this->selectedContactId = CrmContact::query()
            ->where('user_id', auth()->id())
            ->whereKey($contactId)
            ->value('id');
        $this->sourceContext = '';
        $this->sourceId = '';
        $this->noteBody = '';
        $this->taskTitle = '';
        $this->taskDueAt = '';
    }

    public function addNote(): void
    {
        $contact = $this->selectedContact();

        $this->validate([
            'noteBody' => ['required', 'string', 'min:2', 'max:5000'],
        ], [], [
            'noteBody' => 'not',
        ]);

        $note = CrmNote::create([
            'user_id' => auth()->id(),
            'contact_id' => $contact->id,
            'author_user_id' => auth()->id(),
            'body' => trim($this->noteBody),
            'visibility' => 'internal',
        ]);

        CrmTimelineEvent::create([
            'user_id' => auth()->id(),
            'contact_id' => $contact->id,
            'actor_user_id' => auth()->id(),
            'event_type' => 'note',
            'source_type' => 'crm',
            'subject_type' => $note::class,
            'subject_id' => $note->id,
            'title' => 'CRM notu',
            'body' => trim($this->noteBody),
            'occurred_at' => now(),
        ]);

        $contact->forceFill([
            'last_event_at' => now(),
            'last_event_type' => 'note',
            'last_event_title' => 'CRM notu',
        ])->save();

        $this->noteBody = '';
        $this->notify('Not müşteri timeline kaydına eklendi.');
    }

    public function addTask(): void
    {
        $contact = $this->selectedContact();

        $this->validate([
            'taskTitle' => ['required', 'string', 'min:2', 'max:220'],
            'taskDueAt' => ['nullable', 'date'],
        ], [], [
            'taskTitle' => 'görev',
            'taskDueAt' => 'son tarih',
        ]);

        CrmTask::create([
            'user_id' => auth()->id(),
            'contact_id' => $contact->id,
            'owner_user_id' => auth()->id(),
            'task_type' => 'follow_up',
            'priority' => 'normal',
            'status' => 'open',
            'title' => trim($this->taskTitle),
            'due_at' => $this->taskDueAt ?: null,
        ]);

        $this->taskTitle = '';
        $this->taskDueAt = '';
        $this->notify('Görev CRM takip listesine eklendi.');
    }

    public function completeTask(int $taskId): void
    {
        CrmTask::query()
            ->where('user_id', auth()->id())
            ->whereKey($taskId)
            ->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

        $this->notify('Görev tamamlandı.');
    }

    public function resolveCase(int $caseId): void
    {
        $case = CrmCase::query()
            ->with('contact')
            ->where('user_id', auth()->id())
            ->whereKey($caseId)
            ->firstOrFail();

        $case->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        if ($case->contact) {
            $case->contact->forceFill([
                'open_case_count' => $case->contact->openCases()->count(),
            ])->save();
        }

        $this->notify('Vaka kapatıldı.');
    }

    public function sourceOptions(): array
    {
        return [
            'marketplace_orders' => 'Sipariş',
            'marketplace_questions' => 'Soru',
            'returns' => 'İade',
            'marketplace_claims' => 'Pazaryeri İade',
            'cargo_reports' => 'Kargo',
            'supply_reports' => 'Tedarik',
            'marketplace_finance' => 'Finans',
            'crm_customer_ledger' => 'Müşteri Cari',
            'crm' => 'CRM',
        ];
    }

    public function statusOptions(): array
    {
        return [
            'open_cases' => 'Açık vakalı',
            'high_risk' => 'Yüksek risk',
            'high_value' => 'Yüksek değer',
            'no_case' => 'Vakasız',
        ];
    }

    public function priorityOptions(): array
    {
        return [
            'critical' => 'Kritik',
            'high' => 'Yüksek',
            'normal' => 'Normal',
            'low' => 'Düşük',
        ];
    }

    /**
     * @return array{label: string, title: string, url: string, hint: string, source: string}|null
     */
    public function sourceActionForEvent(CrmTimelineEvent $event): ?array
    {
        return app(CrmSourceLinkService::class)->actionForTimelineEvent($event);
    }

    /**
     * @return array{label: string, title: string, url: string, hint: string, source: string}|null
     */
    public function sourceActionForCase(CrmCase $case): ?array
    {
        return app(CrmSourceLinkService::class)->actionForCase($case);
    }

    public function render()
    {
        if (!$this->crmTablesReady()) {
            return view('livewire.crm-workspace', [
                'crmReady' => false,
                'contacts' => collect(),
                'stats' => $this->emptyStats(),
                'stores' => collect(),
                'selectedContact' => null,
                'activeFilters' => [],
                'columnDefs' => static::$allColumnDefs,
                'sortableColumns' => static::$sortableColumns,
            ])->layout('layouts.app', ['title' => 'CRM']);
        }

        $contacts = $this->buildContactsQuery()
            ->reorder($this->sortField, $this->sortDirection)
            ->orderByDesc('last_event_at')
            ->paginate(20);

        if (!$this->selectedContactId && $contacts->count() > 0) {
            $this->selectedContactId = $contacts->first()->id;
        }

        return view('livewire.crm-workspace', [
            'crmReady' => true,
            'contacts' => $contacts,
            'stats' => $this->stats(),
            'stores' => $this->stores(),
            'selectedContact' => $this->selectedContactOrNull(),
            'activeFilters' => $this->activeFilters(),
            'columnDefs' => static::$allColumnDefs,
            'sortableColumns' => static::$sortableColumns,
        ])->layout('layouts.app', ['title' => 'CRM']);
    }

    protected function buildContactsQuery(): Builder
    {
        return CrmContact::query()
            ->where('user_id', auth()->id())
            ->with(['identities.store', 'party'])
            ->withCount(['openCases', 'openTasks'])
            ->when($this->search !== '', function (Builder $query) {
                $term = '%' . trim($this->search) . '%';
                $query->where(function (Builder $searchQuery) use ($term) {
                    $searchQuery
                        ->where('display_name', 'like', $term)
                        ->orWhere('primary_email', 'like', $term)
                        ->orWhere('primary_phone', 'like', $term)
                        ->orWhere('billing_tax_number', 'like', $term)
                        ->orWhereHas('timelineEvents', fn (Builder $timelineQuery) => $timelineQuery
                            ->where(fn (Builder $eventQuery) => $eventQuery
                                ->where('title', 'like', $term)
                                ->orWhere('body', 'like', $term)));
                });
            })
            ->when($this->sourceFilter !== '', fn (Builder $query) => $query
                ->whereHas('timelineEvents', fn (Builder $timelineQuery) => $this->applySourceFilter($timelineQuery)))
            ->when($this->storeFilter !== '', fn (Builder $query) => $query
                ->whereHas('timelineEvents', fn (Builder $timelineQuery) => $timelineQuery->where('store_id', (int) $this->storeFilter)))
            ->when($this->priorityFilter !== '', fn (Builder $query) => $query
                ->whereHas('openCases', fn (Builder $caseQuery) => $caseQuery->where('priority', $this->priorityFilter)))
            ->when($this->statusFilter === 'open_cases', fn (Builder $query) => $query->whereHas('openCases'))
            ->when($this->statusFilter === 'high_risk', fn (Builder $query) => $query->where('risk_score', '>=', 70))
            ->when($this->statusFilter === 'high_value', fn (Builder $query) => $query->where('value_score', '>=', 70))
            ->when($this->statusFilter === 'no_case', fn (Builder $query) => $query->whereDoesntHave('openCases'));
    }

    protected function stats(): array
    {
        $contactQuery = CrmContact::query()->where('user_id', auth()->id());
        $caseQuery = CrmCase::query()->where('user_id', auth()->id())->whereIn('status', ['open', 'pending', 'in_progress']);

        return [
            'contacts' => (clone $contactQuery)->count(),
            'open_cases' => (clone $caseQuery)->count(),
            'due_today' => (clone $caseQuery)->whereNotNull('sla_due_at')->whereDate('sla_due_at', '<=', now()->toDateString())->count(),
            'high_risk' => (clone $contactQuery)->where('risk_score', '>=', 70)->count(),
            'total_revenue' => (float) (clone $contactQuery)->sum('gross_revenue_total'),
            'questions' => (clone $caseQuery)->where('category', 'message')->count(),
            'returns' => (clone $caseQuery)->where('category', 'return')->count(),
            'cargo' => (clone $caseQuery)->where('category', 'cargo')->count(),
            'supply' => (clone $caseQuery)->where('category', 'supply')->count(),
        ];
    }

    protected function emptyStats(): array
    {
        return [
            'contacts' => 0,
            'open_cases' => 0,
            'due_today' => 0,
            'high_risk' => 0,
            'total_revenue' => 0,
            'questions' => 0,
            'returns' => 0,
            'cargo' => 0,
            'supply' => 0,
        ];
    }

    protected function stores()
    {
        return MarketplaceStore::query()
            ->where('user_id', auth()->id())
            ->orderBy('store_name')
            ->get(['id', 'store_name', 'marketplace']);
    }

    protected function selectedContact(): CrmContact
    {
        return CrmContact::query()
            ->where('user_id', auth()->id())
            ->whereKey($this->selectedContactId)
            ->firstOrFail();
    }

    protected function selectedContactOrNull(): ?CrmContact
    {
        if (!$this->selectedContactId) {
            return null;
        }

        $relations = [
            'identities.store',
            'cases' => fn ($query) => $query->latest('updated_at')->limit(12),
            'openTasks.owner',
            'notes' => fn ($query) => $query->latest()->limit(8),
            'notes.author',
            'timelineEvents' => fn ($query) => $query->latest('occurred_at')->latest('id')->limit(24),
            'party.roles',
        ];

        if (Schema::hasTable('crm_customer_ledger_entries')) {
            $relations['ledgerEntries'] = fn ($query) => $query
                ->latest('purchased_at')
                ->latest('id')
                ->limit(8);
        }

        return CrmContact::query()
            ->where('user_id', auth()->id())
            ->whereKey($this->selectedContactId)
            ->with($relations)
            ->first();
    }

    protected function activeFilters(): array
    {
        $filters = [];

        if ($this->search !== '') {
            $filters[] = 'Arama: ' . $this->search;
        }

        if ($this->sourceFilter !== '') {
            $filters[] = 'Kaynak: ' . ($this->sourceOptions()[$this->sourceFilter] ?? $this->sourceFilter);
        }

        if ($this->statusFilter !== '') {
            $filters[] = 'Durum: ' . ($this->statusOptions()[$this->statusFilter] ?? $this->statusFilter);
        }

        if ($this->priorityFilter !== '') {
            $filters[] = 'Öncelik: ' . ($this->priorityOptions()[$this->priorityFilter] ?? $this->priorityFilter);
        }

        if ($this->storeFilter !== '') {
            $storeName = $this->stores()->firstWhere('id', (int) $this->storeFilter)?->store_name;
            $filters[] = 'Mağaza: ' . ($storeName ?: $this->storeFilter);
        }

        return $filters;
    }

    protected function normalizeVisibleColumns(array $columns): array
    {
        $valid = array_keys(static::$allColumnDefs);
        $normalized = array_values(array_intersect($valid, $columns));

        return $normalized !== [] ? $normalized : $this->visibleColumns;
    }

    protected function crmTablesReady(): bool
    {
        return Schema::hasTable('crm_contacts')
            && Schema::hasTable('crm_cases')
            && Schema::hasTable('crm_timeline_events');
    }

    protected function resolveInitialContactId(): ?int
    {
        if ($this->selectedContactId && $this->contactBelongsToCurrentUser($this->selectedContactId)) {
            return $this->selectedContactId;
        }

        if ($this->sourceContext !== '' && $this->sourceId !== '') {
            $contactId = app(CrmSourceLinkService::class)->resolveContactId(
                auth()->user(),
                $this->sourceContext,
                (int) $this->sourceId,
            );

            if ($contactId) {
                return $contactId;
            }

            $this->notify('Bu kaynak için CRM kaydı henüz oluşmamış. CRM’i güncelle ile son verileri işleyebilirsiniz.', 'warning');
        }

        return CrmContact::query()
            ->where('user_id', auth()->id())
            ->latest('last_event_at')
            ->value('id');
    }

    protected function contactBelongsToCurrentUser(int $contactId): bool
    {
        return CrmContact::query()
            ->where('user_id', auth()->id())
            ->whereKey($contactId)
            ->exists();
    }

    protected function applySourceFilter(Builder $timelineQuery): Builder
    {
        if ($this->sourceFilter === 'returns') {
            return $timelineQuery->whereIn('source_type', ['returns', 'marketplace_claims']);
        }

        return $timelineQuery->where('source_type', $this->sourceFilter);
    }

    protected function notify(string $message, string $tone = 'success'): void
    {
        $this->workspaceMessage = $message;
        $this->workspaceMessageTone = $tone;
    }
}
