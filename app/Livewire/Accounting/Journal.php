<?php

namespace App\Livewire\Accounting;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\LegalEntity;
use App\Models\Party;
use App\Services\Accounting\JournalService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class Journal extends Component
{
    use WithPagination;

    // Filters
    public string $search = '';
    public string $filterType = '';
    public string $filterStatus = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $filterMinAmount = '';
    public string $filterMaxAmount = '';

    public array $expandedEntries = [];

    // Show manual entry form modal
    public bool $showCreateForm = false;

    // Header inputs
    public string $entryDate = '';
    public string $entryType = 'manual';
    public string $referenceNumber = '';
    public string $description = '';
    public ?int $legalEntityId = null;
    public ?int $partyId = null;

    // Lines input array
    public array $lines = [];

    // Void entry state
    public bool $showVoidModal = false;
    public ?int $voidingEntryId = null;
    public string $voidReason = '';

    // Messaging
    public string $message = '';
    public string $messageType = 'success';

    protected $queryString = [
        'search' => ['except' => ''],
        'filterType' => ['except' => ''],
        'filterStatus' => ['except' => ''],
        'dateFrom' => ['except' => ''],
        'dateTo' => ['except' => ''],
        'filterMinAmount' => ['except' => ''],
        'filterMaxAmount' => ['except' => ''],
    ];

    public function mount(): void
    {
        $this->entryDate = now()->toDateString();
        $this->resetLines();
    }

    public function resetLines(): void
    {
        $this->lines = [
            ['account_id' => '', 'debit_amount' => 0.0, 'credit_amount' => 0.0, 'description' => '', 'party_id' => null],
            ['account_id' => '', 'debit_amount' => 0.0, 'credit_amount' => 0.0, 'description' => '', 'party_id' => null],
        ];
    }

    public function addLine(): void
    {
        $this->lines[] = ['account_id' => '', 'debit_amount' => 0.0, 'credit_amount' => 0.0, 'description' => '', 'party_id' => null];
    }

    public function removeLine(int $index): void
    {
        if (count($this->lines) <= 2) {
            $this->message = 'Yevmiye fişinde en az 2 satır bulunmalıdır.';
            $this->messageType = 'error';
            return;
        }

        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function getDebitTotalProperty(): float
    {
        return array_reduce($this->lines, fn ($carry, $line) => $carry + (float) ($line['debit_amount'] ?? 0), 0.0);
    }

    public function getCreditTotalProperty(): float
    {
        return array_reduce($this->lines, fn ($carry, $line) => $carry + (float) ($line['credit_amount'] ?? 0), 0.0);
    }

    public function getDiffProperty(): float
    {
        return round($this->debitTotal - $this->creditTotal, 2);
    }

    public function postJournalEntry(): void
    {
        $userId = auth()->id();

        // Strict forms input validation
        $this->validate([
            'entryDate' => 'required|date',
            'entryType' => 'required|string',
            'lines.*.account_id' => 'required|integer',
            'lines.*.debit_amount' => 'required|numeric|min:0',
            'lines.*.credit_amount' => 'required|numeric|min:0',
        ], [
            'entryDate.required' => 'Fatura/Fiş tarihi zorunludur.',
            'lines.*.account_id.required' => 'Hesap seçimi zorunludur.',
        ]);

        if (abs($this->diff) >= 0.005) {
            $this->message = sprintf('Fiş dengeli değil. Borç ve alacak toplamı eşit olmalıdır. Fark: %s', $this->diff);
            $this->messageType = 'error';
            return;
        }

        if ($this->debitTotal <= 0) {
            $this->message = 'Fiş tutarı sıfırdan büyük olmalıdır.';
            $this->messageType = 'error';
            return;
        }

        // Verify accounts ownership
        foreach ($this->lines as $line) {
            $account = Account::find((int) $line['account_id']);
            if (!$account || (int) $account->user_id !== $userId) {
                $this->message = 'Seçilen hesap(lar) bu kullanıcıya ait değil.';
                $this->messageType = 'error';
                return;
            }
            if (isset($line['party_id']) && $line['party_id'] !== null) {
                $party = Party::find((int) $line['party_id']);
                if (!$party || (int) $party->user_id !== $userId) {
                    $this->message = 'Seçilen party bu kullanıcıya ait değil.';
                    $this->messageType = 'error';
                    return;
                }
            }
        }

        if ($this->partyId !== null) {
            $hParty = Party::find((int) $this->partyId);
            if (!$hParty || (int) $hParty->user_id !== $userId) {
                $this->message = 'Başlıktaki party bu kullanıcıya ait değil.';
                $this->messageType = 'error';
                return;
            }
        }

        if ($this->legalEntityId !== null) {
            $hLE = LegalEntity::find((int) $this->legalEntityId);
            if (!$hLE || (int) $hLE->user_id !== $userId) {
                $this->message = 'Başlıktaki legal entity bu kullanıcıya ait değil.';
                $this->messageType = 'error';
                return;
            }
        }

        try {
            $header = [
                'user_id' => $userId,
                'entry_date' => $this->entryDate,
                'entry_type' => $this->entryType,
                'reference_number' => $this->referenceNumber ?: null,
                'description' => $this->description ?: null,
                'legal_entity_id' => $this->legalEntityId ? (int) $this->legalEntityId : null,
                'party_id' => $this->partyId ? (int) $this->partyId : null,
                'currency_code' => 'TRY',
                'exchange_rate' => 1.0,
            ];

            $mappedLines = [];
            foreach ($this->lines as $line) {
                $mappedLines[] = [
                    'account_id' => (int) $line['account_id'],
                    'debit_amount' => (float) $line['debit_amount'],
                    'credit_amount' => (float) $line['credit_amount'],
                    'description' => $line['description'] ?: null,
                    'party_id' => $line['party_id'] ? (int) $line['party_id'] : null,
                ];
            }

            $journalService = app(JournalService::class);
            $journalService->postManual($header, $mappedLines);

            $this->message = 'Yevmiye fişi başarıyla oluşturuldu.';
            $this->messageType = 'success';

            // Reset state
            $this->resetLines();
            $this->referenceNumber = '';
            $this->description = '';
            $this->legalEntityId = null;
            $this->partyId = null;
            $this->showCreateForm = false;
        } catch (\Exception $e) {
            $this->message = 'Yevmiye fişi oluşturulurken hata: ' . $e->getMessage();
            $this->messageType = 'error';
        }
    }

    public function confirmVoid(int $id): void
    {
        $userId = auth()->id();
        $entry = JournalEntry::where('user_id', $userId)->findOrFail($id);

        if ($entry->isVoid()) {
            $this->message = 'Fiş zaten iptal edilmiş.';
            $this->messageType = 'error';
            return;
        }

        $this->voidingEntryId = $id;
        $this->voidReason = '';
        $this->showVoidModal = true;
    }

    public function voidEntry(): void
    {
        $userId = auth()->id();

        $this->validate([
            'voidReason' => 'required|string|max:255',
        ], [
            'voidReason.required' => 'İptal edilme sebebi zorunludur.',
        ]);

        try {
            $entry = JournalEntry::where('user_id', $userId)->findOrFail($this->voidingEntryId);

            $journalService = app(JournalService::class);
            $journalService->voidEntry($entry, $this->voidReason, $userId);

            $this->message = 'Yevmiye fişi başarıyla iptal edildi.';
            $this->messageType = 'success';
            $this->showVoidModal = false;
            $this->voidingEntryId = null;
        } catch (\Exception $e) {
            $this->message = 'Yevmiye fişi iptal edilirken hata: ' . $e->getMessage();
            $this->messageType = 'error';
        }
    }

    public function getAccountsProperty()
    {
        return Account::where('user_id', auth()->id())
            ->active()
            ->orderBy('code')
            ->get();
    }

    public function getPartiesProperty()
    {
        return Party::where('user_id', auth()->id())
            ->orderBy('display_name')
            ->get();
    }

    public function getLegalEntitiesProperty()
    {
        return LegalEntity::where('user_id', auth()->id())
            ->orderBy('name')
            ->get();
    }

    public function getEntriesProperty()
    {
        $userId = auth()->id();
        $query = JournalEntry::where('user_id', $userId)
            ->with(['lines.account', 'party', 'legalEntity']);

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('reference_number', 'like', "%{$this->search}%")
                  ->orWhere('description', 'like', "%{$this->search}%")
                  ->orWhere('id', 'like', "%{$this->search}%");
            });
        }

        if ($this->filterType !== '') {
            $query->where('entry_type', $this->filterType);
        }

        if ($this->filterStatus !== '') {
            $query->where('status', $this->filterStatus);
        }

        if ($this->dateFrom !== '') {
            $query->whereDate('entry_date', '>=', $this->dateFrom);
        }

        if ($this->dateTo !== '') {
            $query->whereDate('entry_date', '<=', $this->dateTo);
        }

        if ($this->filterMinAmount !== '') {
            $minIds = DB::table('journal_lines')
                ->select('journal_entry_id')
                ->groupBy('journal_entry_id')
                ->havingRaw('SUM(debit_base_amount) >= ' . (float) $this->filterMinAmount)
                ->pluck('journal_entry_id')
                ->toArray();
            $query->whereIn('id', $minIds);
        }

        if ($this->filterMaxAmount !== '') {
            $maxIds = DB::table('journal_lines')
                ->select('journal_entry_id')
                ->groupBy('journal_entry_id')
                ->havingRaw('SUM(debit_base_amount) <= ' . (float) $this->filterMaxAmount)
                ->pluck('journal_entry_id')
                ->toArray();
            $query->whereIn('id', $maxIds);
        }

        return $query->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate(15);
    }

    public function toggleEntry(int $entryId): void
    {
        if (in_array($entryId, $this->expandedEntries)) {
            $this->expandedEntries = array_diff($this->expandedEntries, [$entryId]);
        } else {
            $this->expandedEntries[] = $entryId;
        }
    }

    public function render()
    {
        return view('livewire.accounting.journal')
            ->layout('layouts.app');
    }
}
