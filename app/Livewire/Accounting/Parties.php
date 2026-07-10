<?php

namespace App\Livewire\Accounting;

use App\Models\Party;
use App\Models\PartyIdentity;
use App\Models\PartyLedgerEntry;
use App\Models\PartyRole;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class Parties extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filterRole = '';
    public string $filterStatus = 'active';
    public string $sortField = 'display_name';
    public string $sortDirection = 'asc';

    public bool $showForm = false;
    public bool $isEditing = false;
    public ?int $editingPartyId = null;

    public string $displayName = '';
    public string $partyType = 'unknown';
    public string $primaryEmail = '';
    public string $primaryPhone = '';
    public string $taxNumber = '';
    public string $taxOffice = '';
    public string $city = '';
    public string $district = '';
    public string $status = 'active';
    public bool $isBlacklisted = false;
    public array $roles = ['customer'];

    public string $message = '';
    public string $messageType = 'success';

    protected $queryString = [
        'search' => ['except' => ''],
        'filterRole' => ['except' => ''],
        'filterStatus' => ['except' => 'active'],
        'sortField' => ['except' => 'display_name'],
        'sortDirection' => ['except' => 'asc'],
    ];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterRole(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function sortTable(string $field): void
    {
        $allowed = ['display_name', 'party_type', 'status', 'city', 'created_at'];
        if (! in_array($field, $allowed, true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
            return;
        }

        $this->sortField = $field;
        $this->sortDirection = 'asc';
    }

    public function openCreateForm(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function editParty(int $partyId): void
    {
        $party = Party::where('user_id', auth()->id())
            ->with('roles')
            ->findOrFail($partyId);

        $this->editingPartyId = $party->id;
        $this->isEditing = true;
        $this->showForm = true;
        $this->displayName = (string) $party->display_name;
        $this->partyType = (string) $party->party_type;
        $this->primaryEmail = (string) ($party->primary_email ?? '');
        $this->primaryPhone = (string) ($party->primary_phone ?? '');
        $this->taxNumber = (string) ($party->tax_number ?? '');
        $this->taxOffice = (string) ($party->tax_office ?? '');
        $this->city = (string) ($party->city ?? '');
        $this->district = (string) ($party->district ?? '');
        $this->status = (string) $party->status;
        $this->isBlacklisted = (bool) $party->is_blacklisted;
        $this->roles = $party->roles
            ->where('status', 'active')
            ->pluck('role')
            ->intersect(['customer', 'supplier'])
            ->values()
            ->all();
    }

    public function saveParty(): void
    {
        $userId = (int) auth()->id();

        $this->validate([
            'displayName' => 'required|string|max:180',
            'partyType' => 'required|in:person,organization,unknown',
            'primaryEmail' => 'nullable|email|max:180',
            'primaryPhone' => 'nullable|string|max:40',
            'taxNumber' => 'nullable|string|max:40',
            'taxOffice' => 'nullable|string|max:120',
            'city' => 'nullable|string|max:120',
            'district' => 'nullable|string|max:120',
            'status' => 'required|in:active,passive',
            'roles' => 'array',
            'roles.*' => 'in:customer,supplier',
        ], [
            'displayName.required' => 'Cari adı zorunludur.',
            'partyType.in' => 'Cari tipi geçerli değil.',
            'primaryEmail.email' => 'E-posta formatı geçerli değil.',
        ]);

        $payload = [
            'user_id' => $userId,
            'display_name' => trim($this->displayName),
            'normalized_name' => $this->normalizeName($this->displayName),
            'party_type' => $this->partyType,
            'primary_email' => $this->blankToNull($this->primaryEmail),
            'primary_phone' => $this->blankToNull($this->primaryPhone),
            'normalized_phone' => $this->normalizePhone($this->primaryPhone),
            'tax_number' => $this->blankToNull($this->taxNumber),
            'tax_office' => $this->blankToNull($this->taxOffice),
            'city' => $this->blankToNull($this->city),
            'district' => $this->blankToNull($this->district),
            'status' => $this->status,
            'is_blacklisted' => $this->isBlacklisted,
        ];

        if ($this->isEditing && $this->editingPartyId) {
            $party = Party::where('user_id', $userId)->findOrFail($this->editingPartyId);
            $party->update($payload);
        } else {
            $party = Party::create($payload);
        }

        $this->syncRoles($party);
        $this->syncManualIdentities($party);

        $this->message = $this->isEditing ? 'Cari kartı güncellendi.' : 'Cari kartı oluşturuldu.';
        $this->messageType = 'success';
        $this->resetForm();
    }

    public function markPassive(int $partyId): void
    {
        $party = Party::where('user_id', auth()->id())->findOrFail($partyId);
        $party->update(['status' => 'passive']);
        $this->message = 'Cari pasife alındı. Geçmiş hareketler korunur.';
        $this->messageType = 'success';
    }

    public function markActive(int $partyId): void
    {
        $party = Party::where('user_id', auth()->id())->findOrFail($partyId);
        $party->update(['status' => 'active']);
        $this->message = 'Cari tekrar aktif edildi.';
        $this->messageType = 'success';
    }

    public function resetForm(): void
    {
        $this->showForm = false;
        $this->isEditing = false;
        $this->editingPartyId = null;
        $this->displayName = '';
        $this->partyType = 'unknown';
        $this->primaryEmail = '';
        $this->primaryPhone = '';
        $this->taxNumber = '';
        $this->taxOffice = '';
        $this->city = '';
        $this->district = '';
        $this->status = 'active';
        $this->isBlacklisted = false;
        $this->roles = ['customer'];
        $this->resetValidation();
    }

    public function getKpisProperty(): array
    {
        $userId = (int) auth()->id();
        $postedEntries = PartyLedgerEntry::where('user_id', $userId)->where('status', 'posted');
        $balance = (clone $postedEntries)->sum('debit_amount') - (clone $postedEntries)->sum('credit_amount');

        return [
            'active' => Party::where('user_id', $userId)->where('status', 'active')->count(),
            'customers' => Party::where('user_id', $userId)->whereHas('roles', fn ($q) => $q->where('role', 'customer')->where('status', 'active'))->count(),
            'suppliers' => Party::where('user_id', $userId)->whereHas('roles', fn ($q) => $q->where('role', 'supplier')->where('status', 'active'))->count(),
            'blacklisted' => Party::where('user_id', $userId)->where('is_blacklisted', true)->count(),
            'net_balance' => $balance,
        ];
    }

    public function getPartiesProperty()
    {
        $userId = (int) auth()->id();
        $sort = in_array($this->sortField, ['display_name', 'party_type', 'status', 'city', 'created_at'], true) ? $this->sortField : 'display_name';
        $direction = in_array(strtolower($this->sortDirection), ['asc', 'desc'], true) ? strtolower($this->sortDirection) : 'asc';

        return Party::where('user_id', $userId)
            ->with(['roles' => fn ($q) => $q->where('status', 'active'), 'identities'])
            ->withSum(['ledgerEntries as posted_debit_sum' => fn ($q) => $q->where('status', 'posted')], 'debit_amount')
            ->withSum(['ledgerEntries as posted_credit_sum' => fn ($q) => $q->where('status', 'posted')], 'credit_amount')
            ->when($this->filterStatus !== '', fn ($query) => $query->where('status', $this->filterStatus))
            ->when($this->filterRole !== '', fn ($query) => $query->whereHas('roles', fn ($q) => $q->where('role', $this->filterRole)->where('status', 'active')))
            ->when($this->search !== '', function ($query): void {
                $term = '%' . $this->search . '%';
                $query->where(function ($q) use ($term): void {
                    $q->where('display_name', 'like', $term)
                        ->orWhere('primary_email', 'like', $term)
                        ->orWhere('primary_phone', 'like', $term)
                        ->orWhere('tax_number', 'like', $term)
                        ->orWhere('city', 'like', $term);
                });
            })
            ->orderBy($sort, $direction)
            ->paginate(15);
    }

    protected function syncRoles(Party $party): void
    {
        $selected = collect($this->roles)->intersect(['customer', 'supplier'])->values();

        PartyRole::where('user_id', $party->user_id)
            ->where('party_id', $party->id)
            ->whereIn('role', ['customer', 'supplier'])
            ->whereNotIn('role', $selected->all())
            ->update(['status' => 'passive']);

        foreach ($selected as $role) {
            PartyRole::updateOrCreate([
                'user_id' => $party->user_id,
                'party_id' => $party->id,
                'role' => $role,
            ], [
                'status' => 'active',
                'assigned_at' => now(),
            ]);
        }
    }

    protected function syncManualIdentities(Party $party): void
    {
        PartyIdentity::where('user_id', $party->user_id)
            ->where('party_id', $party->id)
            ->where('source_type', 'manual')
            ->whereIn('identity_kind', ['email', 'phone', 'tax_number'])
            ->delete();

        $identities = [
            'email' => $this->blankToNull($this->primaryEmail),
            'phone' => $this->normalizePhone($this->primaryPhone),
            'tax_number' => $this->blankToNull($this->taxNumber),
        ];

        foreach ($identities as $kind => $value) {
            if ($value === null) {
                continue;
            }

            PartyIdentity::create([
                'user_id' => $party->user_id,
                'party_id' => $party->id,
                'source_type' => 'manual',
                'identity_kind' => $kind,
                'identity_value' => $value,
                'confidence' => 100,
            ]);
        }
    }

    protected function normalizeName(string $value): ?string
    {
        $normalized = trim(Str::lower($value));

        return $normalized === '' ? null : $normalized;
    }

    protected function normalizePhone(string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', $value);

        return $digits === '' ? null : $digits;
    }

    protected function blankToNull(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    public function render()
    {
        return view('livewire.accounting.parties')
            ->layout('layouts.app');
    }
}
