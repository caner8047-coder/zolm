<?php

namespace App\Livewire\Accounting;

use App\Models\LegalEntity;
use App\Models\Party;
use App\Models\PartyLedgerEntry;
use App\Services\Accounting\PartyLedgerService;
use Livewire\Component;
use Livewire\WithPagination;

class PartyLedgerWorkspace extends Component
{
    use WithPagination;

    // Filtre state
    public string $search = '';
    public ?int $partyId = null;
    public ?int $legalEntityId = null;
    public ?string $documentType = null;
    public string $status = 'posted';
    public ?string $dateFrom = null;
    public ?string $dateTo = null;
    public ?string $sourceType = null;

    // Sorting state
    public string $sortField = 'document_date';
    public string $sortDirection = 'desc';

    // Visible columns
    public array $visibleColumns = ['tarih', 'party', 'tip', 'belge_no', 'aciklama', 'borc', 'alacak', 'bakiye_etkisi', 'durum', 'aksiyon'];

    public static array $allColumnDefs = [
        'tarih' => 'Tarih',
        'party' => 'Party',
        'tip' => 'Tip',
        'belge_no' => 'Belge No',
        'aciklama' => 'Açıklama',
        'borc' => 'Borç',
        'alacak' => 'Alacak',
        'bakiye_etkisi' => 'Bakiye Etkisi',
        'durum' => 'Durum',
        'aksiyon' => 'İşlem',
    ];

    // Form state
    public ?int $formPartyId = null;
    public string $formEntryType = 'receivable';
    public float $formAmount = 0;
    public ?string $formDocumentDate = null;
    public ?string $formDueDate = null;
    public ?string $formDocumentNumber = null;
    public ?string $formDescription = null;
    public string $formCurrencyCode = 'TRY';
    public float $formExchangeRate = 1;
    public ?int $formLegalEntityId = null;
    public ?int $formCrmContactId = null;
    public ?string $formSourceKey = null;

    // Void state
    public ?int $voidEntryId = null;
    public ?string $voidReason = null;

    // UI state
    public bool $showForm = false;
    public string $message = '';
    public string $messageType = 'success';

    public int $perPage = 20;

    protected $rules = [
        'formPartyId' => 'required|integer',
        'formEntryType' => 'required|in:receivable,collection,payable,payment',
        'formAmount' => 'required|numeric|min:0.01',
        'formDocumentDate' => 'required|date',
        'formDueDate' => 'nullable|date',
        'formDocumentNumber' => 'nullable|string|max:120',
        'formDescription' => 'nullable|string|max:255',
        'formCurrencyCode' => 'nullable|string|max:3',
        'formExchangeRate' => 'required|numeric|min:0.01',
        'formLegalEntityId' => 'nullable|integer',
        'formCrmContactId' => 'nullable|integer',
        'formSourceKey' => 'nullable|string|max:191',
    ];

    protected $queryString = [
        'partyId' => ['except' => null, 'as' => 'party'],
        'sortField' => ['except' => 'document_date'],
        'sortDirection' => ['except' => 'desc'],
    ];

    protected $listeners = ['refreshWorkspace' => '$refresh'];

    public function mount(?int $party = null, ?int $partyId = null): void
    {
        $this->formDocumentDate = now()->toDateString();
        if ($party) {
            $this->partyId = $party;
        } elseif ($partyId) {
            $this->partyId = $partyId;
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedDocumentType(): void
    {
        $this->resetPage();
    }

    public function submitEntry(PartyLedgerService $service): void
    {
        $this->validate();

        try {
            $party = Party::where('user_id', auth()->id())->findOrFail($this->formPartyId);

            $data = [
                'user_id' => auth()->id(),
                'party_id' => $party->id,
                'document_type' => $this->formEntryType,
                'document_date' => $this->formDocumentDate,
                'due_date' => $this->formDueDate,
                'document_number' => $this->formDocumentNumber,
                'description' => $this->formDescription,
                'currency_code' => $this->formCurrencyCode,
                'exchange_rate' => $this->formExchangeRate,
                'legal_entity_id' => $this->formLegalEntityId,
                'crm_contact_id' => $this->formCrmContactId,
                'source_key' => $this->formSourceKey,
            ];

            match ($this->formEntryType) {
                'receivable' => $service->postReceivable($party, $this->formAmount, $data),
                'collection' => $service->postCollection($party, $this->formAmount, $data),
                'payable' => $service->postPayable($party, $this->formAmount, $data),
                'payment' => $service->postPayment($party, $this->formAmount, $data),
            };

            $this->message = 'Kayıt başarıyla eklendi.';
            $this->messageType = 'success';
            $this->resetForm();
            $this->dispatch('refreshWorkspace');
        } catch (\InvalidArgumentException $e) {
            $this->message = $e->getMessage();
            $this->messageType = 'error';
        }
    }

    public function voidEntry(PartyLedgerService $service): void
    {
        if (!$this->voidEntryId) {
            return;
        }

        try {
            $entry = PartyLedgerEntry::where('user_id', auth()->id())->findOrFail($this->voidEntryId);
            $service->voidEntry($entry, $this->voidReason);

            $this->message = 'Kayıt iptal edildi.';
            $this->messageType = 'success';
            $this->voidEntryId = null;
            $this->voidReason = null;
            $this->dispatch('refreshWorkspace');
        } catch (\InvalidArgumentException $e) {
            $this->message = $e->getMessage();
            $this->messageType = 'error';
        }
    }

    public function confirmVoid(int $entryId): void
    {
        $this->voidEntryId = $entryId;
        $this->voidReason = null;
    }

    public function cancelVoid(): void
    {
        $this->voidEntryId = null;
        $this->voidReason = null;
    }

    public function resetForm(): void
    {
        $this->formPartyId = null;
        $this->formEntryType = 'receivable';
        $this->formAmount = 0;
        $this->formDocumentDate = now()->toDateString();
        $this->formDueDate = null;
        $this->formDocumentNumber = null;
        $this->formDescription = null;
        $this->formCurrencyCode = 'TRY';
        $this->formExchangeRate = 1;
        $this->formLegalEntityId = null;
        $this->formCrmContactId = null;
        $this->formSourceKey = null;
        $this->showForm = false;
    }

    public function getPartiesProperty()
    {
        return Party::where('user_id', auth()->id())
            ->where('status', 'active')
            ->get()
            ->map(fn ($p) => ['id' => $p->id, 'label' => $p->display_name]);
    }

    public function getLegalEntitiesProperty()
    {
        return LegalEntity::where('user_id', auth()->id())
            ->where('is_active', true)
            ->get()
            ->map(fn ($e) => ['id' => $e->id, 'label' => $e->name]);
    }

    public function getEntriesProperty()
    {
        $query = PartyLedgerEntry::where('user_id', auth()->id())
            ->with('party');

        if ($this->partyId) {
            $query->where('party_id', $this->partyId);
        }

        if ($this->legalEntityId) {
            $query->where('legal_entity_id', $this->legalEntityId);
        }

        if ($this->documentType) {
            $query->where('document_type', $this->documentType);
        }

        if ($this->status === 'posted') {
            $query->posted();
        } elseif ($this->status === 'voided') {
            $query->where('status', 'voided');
        }

        if ($this->dateFrom) {
            $query->where('document_date', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->where('document_date', '<=', $this->dateTo);
        }

        if ($this->sourceType) {
            $query->where('source_type', $this->sourceType);
        }

        if ($this->search) {
            $query->whereHas('party', function ($q) {
                $q->where('display_name', 'like', "%{$this->search}%")
                    ->orWhere('primary_email', 'like', "%{$this->search}%")
                    ->orWhere('primary_phone', 'like', "%{$this->search}%")
                    ->orWhere('tax_number', 'like', "%{$this->search}%");
            });
        }

        $validFields = [
            'document_date' => 'document_date',
            'tarih' => 'document_date',
            'document_type' => 'document_type',
            'tip' => 'document_type',
            'document_number' => 'document_number',
            'belge_no' => 'document_number',
            'debit_amount' => 'debit_amount',
            'borc' => 'debit_amount',
            'credit_amount' => 'credit_amount',
            'alacak' => 'credit_amount',
            'party' => 'party',
        ];

        $resolvedSortField = $validFields[$this->sortField] ?? 'document_date';
        $resolvedSortDirection = in_array(strtolower($this->sortDirection), ['asc', 'desc'], true) ? strtolower($this->sortDirection) : 'desc';

        if ($resolvedSortField === 'party') {
            $query->orderBy(
                Party::select('display_name')
                    ->whereColumn('parties.id', 'party_ledger_entries.party_id')
                    ->limit(1),
                $resolvedSortDirection
            );
        } elseif ($resolvedSortField === 'document_date') {
            $query->orderBy('document_date', $resolvedSortDirection);
        } elseif ($resolvedSortField === 'debit_amount') {
            $query->orderBy('debit_amount', $resolvedSortDirection);
        } elseif ($resolvedSortField === 'credit_amount') {
            $query->orderBy('credit_amount', $resolvedSortDirection);
        } elseif ($resolvedSortField === 'document_type') {
            $query->orderBy('document_type', $resolvedSortDirection);
        } elseif ($resolvedSortField === 'document_number') {
            $query->orderBy('document_number', $resolvedSortDirection);
        } else {
            $query->orderBy('document_date', 'desc');
        }

        return $query->orderByDesc('id')
            ->paginate($this->perPage);
    }

    public function getKpiProperty(): array
    {
        $query = PartyLedgerEntry::where('user_id', auth()->id())->posted();

        if ($this->partyId) {
            $query->where('party_id', $this->partyId);
        }

        if ($this->legalEntityId) {
            $query->where('legal_entity_id', $this->legalEntityId);
        }

        if ($this->documentType) {
            $query->where('document_type', $this->documentType);
        }

        if ($this->dateFrom) {
            $query->where('document_date', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->where('document_date', '<=', $this->dateTo);
        }

        if ($this->sourceType) {
            $query->where('source_type', $this->sourceType);
        }

        $entries = $query->get();

        $debit = (float) $entries->sum('debit_amount');
        $credit = (float) $entries->sum('credit_amount');

        // Net bakiyesi sıfır olmayan unique party sayısını bul
        $balancesByParty = [];
        foreach ($entries as $entry) {
            $pid = $entry->party_id;
            if (!isset($balancesByParty[$pid])) {
                $balancesByParty[$pid] = 0.0;
            }
            $balancesByParty[$pid] += ((float)$entry->debit_amount - (float)$entry->credit_amount);
        }
        $activePartyCount = 0;
        foreach ($balancesByParty as $pid => $balance) {
            if (abs($balance) >= 0.005) {
                $activePartyCount++;
            }
        }

        // Voided count'ı da aktif filtrelerle hesapla
        $voidedQuery = PartyLedgerEntry::where('user_id', auth()->id())->where('status', 'voided');
        if ($this->partyId) {
            $voidedQuery->where('party_id', $this->partyId);
        }
        if ($this->legalEntityId) {
            $voidedQuery->where('legal_entity_id', $this->legalEntityId);
        }
        if ($this->documentType) {
            $voidedQuery->where('document_type', $this->documentType);
        }
        if ($this->dateFrom) {
            $voidedQuery->where('document_date', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $voidedQuery->where('document_date', '<=', $this->dateTo);
        }
        if ($this->sourceType) {
            $voidedQuery->where('source_type', $this->sourceType);
        }
        $voidedCount = $voidedQuery->count();

        return [
            'debit' => $debit,
            'credit' => $credit,
            'balance' => $debit - $credit,
            'active_party_count' => $activePartyCount,
            'voided_count' => $voidedCount,
        ];
    }

    public function sortTable(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = in_array($field, ['tarih', 'borc', 'alacak', 'bakiye_etkisi'], true) ? 'desc' : 'asc';
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
    }

    protected function normalizeVisibleColumns(array $columns): array
    {
        $ordered = ['tarih', 'party', 'tip', 'belge_no', 'aciklama', 'borc', 'alacak', 'bakiye_etkisi', 'durum', 'aksiyon'];
        return array_values(array_intersect($ordered, $columns));
    }

    public function getCrmContactsProperty()
    {
        return \App\Models\CrmContact::where('user_id', auth()->id())
            ->where('status', 'active')
            ->orderBy('display_name')
            ->get()
            ->map(fn ($c) => ['id' => $c->id, 'label' => $c->display_name]);
    }

    public function render()
    {
        return view('livewire.accounting.party-ledger-workspace')
            ->layout('layouts.app');
    }
}
