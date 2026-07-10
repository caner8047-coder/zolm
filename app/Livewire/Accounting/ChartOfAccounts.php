<?php

namespace App\Livewire\Accounting;

use App\Models\Account;
use Database\Seeders\ChartOfAccountsSeeder;
use Livewire\Component;
use Livewire\WithPagination;

class ChartOfAccounts extends Component
{
    use WithPagination;

    // Filters
    public string $search = '';
    public string $filterType = '';

    // Sorting
    public string $sortField = 'code';
    public string $sortDirection = 'asc';

    // Form inputs for creating a new account
    public bool $showCreateForm = false;
    public string $newCode = '';
    public string $newName = '';
    public string $newType = 'asset';
    public string $newNormalBalance = 'debit';

    // Status message
    public string $message = '';
    public string $messageType = 'success';

    protected $queryString = [
        'search' => ['except' => ''],
        'filterType' => ['except' => ''],
        'sortField' => ['except' => 'code'],
        'sortDirection' => ['except' => 'asc'],
    ];

    public function sortTable(string $field): void
    {
        $validFields = ['code', 'name', 'type', 'normal_balance'];
        if (!in_array($field, $validFields, true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function seedDefaultAccounts(): void
    {
        $userId = auth()->id();

        try {
            $seeder = new ChartOfAccountsSeeder();
            $seeder->runForUser($userId);

            $this->message = 'Varsayılan hesap planı başarıyla yüklendi.';
            $this->messageType = 'success';
        } catch (\Exception $e) {
            $this->message = 'Hesap planı yüklenirken hata oluştu: ' . $e->getMessage();
            $this->messageType = 'error';
        }
    }

    public function createAccount(): void
    {
        $userId = auth()->id();

        $this->validate([
            'newCode' => [
                'required',
                'string',
                'max:20',
                function ($attribute, $value, $fail) use ($userId) {
                    $exists = Account::where('user_id', $userId)
                        ->where('code', $value)
                        ->exists();
                    if ($exists) {
                        $fail('Bu hesap kodu zaten kullanımda.');
                    }
                }
            ],
            'newName' => 'required|string|max:100',
            'newType' => 'required|in:asset,liability,equity,revenue,expense',
            'newNormalBalance' => 'required|in:debit,credit',
        ], [
            'newCode.required' => 'Hesap kodu zorunludur.',
            'newName.required' => 'Hesap adı zorunludur.',
        ]);

        try {
            Account::create([
                'user_id' => $userId,
                'code' => $this->newCode,
                'name' => $this->newName,
                'type' => $this->newType,
                'normal_balance' => $this->newNormalBalance,
                'is_active' => true,
                'is_system' => false,
            ]);

            $this->message = 'Hesap başarıyla oluşturuldu.';
            $this->messageType = 'success';

            // Reset form
            $this->newCode = '';
            $this->newName = '';
            $this->newType = 'asset';
            $this->newNormalBalance = 'debit';
            $this->showCreateForm = false;
        } catch (\Exception $e) {
            $this->message = 'Hesap oluşturulurken hata: ' . $e->getMessage();
            $this->messageType = 'error';
        }
    }

    public function getAccountsProperty()
    {
        $userId = auth()->id();
        $query = Account::where('user_id', $userId);

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('code', 'like', "%{$this->search}%")
                  ->orWhere('name', 'like', "%{$this->search}%");
            });
        }

        if ($this->filterType !== '') {
            $query->where('type', $this->filterType);
        }

        // Apply whitelisted sorting
        $sort = in_array($this->sortField, ['code', 'name', 'type', 'normal_balance'], true) ? $this->sortField : 'code';
        $direction = in_array(strtolower($this->sortDirection), ['asc', 'desc'], true) ? strtolower($this->sortDirection) : 'asc';

        return $query->orderBy($sort, $direction)
            ->paginate(15);
    }

    public function render()
    {
        return view('livewire.accounting.chart-of-accounts')
            ->layout('layouts.app');
    }
}
