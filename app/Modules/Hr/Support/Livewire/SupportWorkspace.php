<?php

namespace App\Modules\Hr\Support\Livewire;

use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Support\Actions\ManageSupportTicketAction;
use App\Modules\Hr\Support\Models\HrSupportTicket;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class SupportWorkspace extends Component
{
    public string $category = 'hr';

    public string $subject = '';

    public string $description = '';

    public string $priority = 'normal';

    public string $search = '';

    public string $statusFilter = '';

    public ?int $selectedTicketId = null;

    public string $message = '';

    public bool $internalMessage = false;

    public string $sortField = 'created_at';

    public string $sortDirection = 'desc';

    public array $visibleColumns = ['number', 'subject', 'requester', 'priority', 'status', 'updated'];

    public const COLUMNS = [
        'number' => 'Talep no',
        'subject' => 'Konu',
        'requester' => 'Talep sahibi',
        'priority' => 'Öncelik',
        'status' => 'Durum',
        'updated' => 'Güncelleme',
    ];

    private const SORTABLE_COLUMNS = [
        'number' => 'ticket_number',
        'subject' => 'subject',
        'priority' => 'priority',
        'status' => 'status',
        'updated' => 'updated_at',
    ];

    public function create(ManageSupportTicketAction $action): void
    {
        $ticket = $action->create([
            'category' => $this->category,
            'subject' => $this->subject,
            'description' => $this->description,
            'priority' => $this->priority,
        ]);
        $this->selectedTicketId = $ticket->id;
        $this->reset(['subject', 'description']);
        $this->priority = 'normal';
        session()->flash('success', 'Destek talebi oluşturuldu.');
    }

    public function select(int $id): void
    {
        $this->selectedTicketId = $this->ticketQuery()->findOrFail($id)->id;
    }

    public function addMessage(ManageSupportTicketAction $action): void
    {
        $action->addMessage($this->selectedTicket(), $this->message, $this->internalMessage);
        $this->reset(['message', 'internalMessage']);
        session()->flash('success', 'Mesaj eklendi.');
    }

    public function assignToSelf(ManageSupportTicketAction $action): void
    {
        $action->assignToSelf($this->selectedTicket());
        session()->flash('success', 'Talep üzerinize atandı.');
    }

    public function changeStatus(ManageSupportTicketAction $action, string $status): void
    {
        $action->changeStatus($this->selectedTicket(), $status);
        session()->flash('success', 'Talep durumu güncellendi.');
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
        abort_if(in_array($column, ['number', 'subject'], true), 422);
        $this->visibleColumns = in_array($column, $this->visibleColumns, true)
            ? array_values(array_diff($this->visibleColumns, [$column]))
            : array_values(array_intersect(array_keys(self::COLUMNS), [...$this->visibleColumns, $column]));
    }

    public function render()
    {
        $tickets = $this->ticketQuery()
            ->with(['requester', 'assignee'])
            ->when($this->search !== '', fn (Builder $query) => $query->where(function (Builder $nested) {
                $nested->where('ticket_number', 'like', '%'.$this->search.'%')
                    ->orWhere('subject', 'like', '%'.$this->search.'%');
            }))
            ->when($this->statusFilter !== '', fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->orderBy($this->sortField, $this->sortDirection)
            ->limit(100)
            ->get();
        $selected = $this->selectedTicketId ? $this->ticketQuery()->with(['requester', 'assignee'])->findOrFail($this->selectedTicketId) : $tickets->first();

        if ($selected) {
            $this->selectedTicketId = $selected->id;
        }

        $messages = $selected
            ? $selected->messages()->with('author')
                ->when(! auth()->user()?->hasHrPermission('hr.support.manage'), fn ($query) => $query->where('is_internal', false))
                ->oldest()->get()
            : collect();

        return view('livewire.hr.support.support-workspace', [
            'tickets' => $tickets,
            'selected' => $selected,
            'messages' => $messages,
            'columnLabels' => self::COLUMNS,
            'sortableColumns' => self::SORTABLE_COLUMNS,
        ])->layout('layouts.app');
    }

    private function ticketQuery(): Builder
    {
        return HrSupportTicket::withoutGlobalScope('tenant')
            ->where('legal_entity_id', app(TenantContext::class)->getId())
            ->when(! auth()->user()?->hasHrPermission('hr.support.manage'), fn (Builder $query) => $query->where('requester_user_id', auth()->id()));
    }

    private function selectedTicket(): HrSupportTicket
    {
        return $this->ticketQuery()->findOrFail($this->selectedTicketId);
    }
}
