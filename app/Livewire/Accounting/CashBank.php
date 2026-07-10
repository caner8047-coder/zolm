<?php

namespace App\Livewire\Accounting;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\MoneyTransfer;
use App\Services\Accounting\CashBankService;
use Livewire\Component;

class CashBank extends Component
{
    // Search & Filter
    public string $search = '';
    public string $filterDateFrom = '';
    public string $filterDateTo = '';
    public string $filterStatus = '';

    // Lists
    public ?int $selectedAccountId = null;
    public string $statementDateFrom = '';
    public string $statementDateTo = '';

    // Table view standard
    public array $visibleColumns = ['date', 'type', 'description', 'debit', 'credit', 'balance'];
    public string $sortBy = 'date';
    public string $sortDir = 'asc';

    // Create Kasa Form
    public bool $showCashForm = false;
    public string $cashName = '';
    public ?int $cashLegalEntityId = null;

    // Create Banka Form
    public bool $showBankForm = false;
    public string $bankName = '';
    public string $branchName = '';
    public string $accountNumber = '';
    public string $iban = '';
    public string $currencyCode = 'TRY';
    public ?int $bankLegalEntityId = null;

    // Transfer Form (Virman)
    public bool $showTransferForm = false;
    public ?int $fromAccountId = null;
    public ?int $toAccountId = null;
    public float $transferAmount = 0.0;
    public string $transferDate = '';
    public string $transferDescription = '';
    public ?int $transferLegalEntityId = null;

    // Status Messaging
    public string $message = '';
    public string $messageType = 'success';

    public function mount(): void
    {
        $this->transferDate = now()->toDateString();
    }

    public function toggleColumn(string $col): void
    {
        if (in_array($col, $this->visibleColumns)) {
            $this->visibleColumns = array_values(array_diff($this->visibleColumns, [$col]));
        } else {
            $this->visibleColumns[] = $col;
        }
    }

    public function sortTable(string $col): void
    {
        if ($this->sortBy === $col) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $col;
            $this->sortDir = 'asc';
        }
    }

    public function createCashAccount(): void
    {
        $userId = auth()->id();

        $this->validate([
            'cashName' => 'required|string|max:100',
        ], [
            'cashName.required' => 'Kasa adı zorunludur.',
        ]);

        try {
            $service = app(CashBankService::class);
            $service->createCashAccount($userId, $this->cashName, 'TRY', $this->cashLegalEntityId ?: null);

            $this->message = 'Kasa hesabı başarıyla oluşturuldu.';
            $this->messageType = 'success';
            $this->cashName = '';
            $this->cashLegalEntityId = null;
            $this->showCashForm = false;
        } catch (\Exception $e) {
            $this->message = 'Kasa oluşturulurken hata: ' . $e->getMessage();
            $this->messageType = 'error';
        }
    }

    public function createBankAccount(): void
    {
        $userId = auth()->id();

        $this->validate([
            'bankName' => 'required|string|max:100',
            'currencyCode' => 'required|string|max:3',
        ], [
            'bankName.required' => 'Banka adı zorunludur.',
        ]);

        try {
            $service = app(CashBankService::class);
            $service->createBankAccount($userId, [
                'bank_name' => $this->bankName,
                'branch_name' => $this->branchName ?: null,
                'account_number' => $this->accountNumber ?: null,
                'iban' => $this->iban ?: null,
                'currency_code' => $this->currencyCode,
                'legal_entity_id' => $this->bankLegalEntityId ?: null,
            ]);

            $this->message = 'Banka hesabı başarıyla oluşturuldu.';
            $this->messageType = 'success';

            // Reset
            $this->bankName = '';
            $this->branchName = '';
            $this->accountNumber = '';
            $this->iban = '';
            $this->currencyCode = 'TRY';
            $this->bankLegalEntityId = null;
            $this->showBankForm = false;
        } catch (\Exception $e) {
            $this->message = 'Banka oluşturulurken hata: ' . $e->getMessage();
            $this->messageType = 'error';
        }
    }

    public function executeTransfer(): void
    {
        $userId = auth()->id();

        $this->validate([
            'fromAccountId' => 'required|integer',
            'toAccountId' => 'required|integer',
            'transferAmount' => 'required|numeric|min:0.01',
            'transferDate' => 'required|date',
        ], [
            'fromAccountId.required' => 'Kaynak hesap seçimi zorunludur.',
            'toAccountId.required' => 'Hedef hesap seçimi zorunludur.',
            'transferAmount.min' => 'Transfer tutarı 0.01 veya daha büyük olmalıdır.',
        ]);

        if ((int) $this->fromAccountId === (int) $this->toAccountId) {
            $this->message = 'Kaynak ve hedef hesap aynı olamaz.';
            $this->messageType = 'error';
            return;
        }

        // Enforce tenant checks
        $fromAcc = Account::where('user_id', $userId)->find($this->fromAccountId);
        $toAcc = Account::where('user_id', $userId)->find($this->toAccountId);
        if (!$fromAcc || !$toAcc) {
            $this->message = 'İşlem yapmak istediğiniz hesaplar bu kullanıcıya ait değil.';
            $this->messageType = 'error';
            return;
        }

        try {
            $service = app(CashBankService::class);
            $service->transferFunds([
                'user_id' => $userId,
                'from_account_id' => (int) $this->fromAccountId,
                'to_account_id' => (int) $this->toAccountId,
                'amount' => (float) $this->transferAmount,
                'transfer_date' => $this->transferDate,
                'description' => $this->transferDescription ?: 'Virman Transferi',
                'legal_entity_id' => $this->transferLegalEntityId ?: null,
            ]);

            $this->message = 'Para transferi başarıyla gerçekleştirildi.';
            $this->messageType = 'success';

            // Reset
            $this->fromAccountId = null;
            $this->toAccountId = null;
            $this->transferAmount = 0.0;
            $this->transferDescription = '';
            $this->transferLegalEntityId = null;
            $this->showTransferForm = false;
        } catch (\Exception $e) {
            $this->message = 'Para transferi sırasında hata: ' . $e->getMessage();
            $this->messageType = 'error';
        }
    }

    public function voidTransfer(int $transferId): void
    {
        try {
            $transfer = MoneyTransfer::where('user_id', auth()->id())->findOrFail($transferId);
            app(CashBankService::class)->voidTransfer($transfer, 'Kullanıcı tarafından iptal edildi.', auth()->id());

            $this->message = 'Virman transferi başarıyla iptal edildi.';
            $this->messageType = 'success';
        } catch (\Exception $e) {
            $this->message = 'İptal sırasında hata: ' . $e->getMessage();
            $this->messageType = 'error';
        }
    }


    public function selectAccount(?int $accountId): void
    {
        if ($accountId) {
            $account = Account::where('user_id', auth()->id())->find($accountId);
            if ($account) {
                $this->selectedAccountId = $accountId;
                return;
            }
        }
        $this->selectedAccountId = null;
    }

    public function getCashAccountsProperty()
    {
        return CashAccount::where('user_id', auth()->id())
            ->when($this->search, function ($q) {
                $q->where(function ($inner) {
                    $inner->where('name', 'like', '%' . $this->search . '%')
                          ->orWhereHas('account', fn($a) => $a->where('code', 'like', '%' . $this->search . '%'));
                });
            })
            ->with('account')
            ->get();
    }

    public function getBankAccountsProperty()
    {
        return BankAccount::where('user_id', auth()->id())
            ->when($this->search, function ($q) {
                $q->where(function ($inner) {
                    $inner->where('bank_name', 'like', '%' . $this->search . '%')
                          ->orWhere('branch_name', 'like', '%' . $this->search . '%')
                          ->orWhere('account_number', 'like', '%' . $this->search . '%')
                          ->orWhere('iban', 'like', '%' . $this->search . '%')
                          ->orWhereHas('account', fn($a) => $a->where('code', 'like', '%' . $this->search . '%'));
                });
            })
            ->with('account')
            ->get();
    }

    public function getTransferableAccountsProperty()
    {
        return Account::where('user_id', auth()->id())
            ->active()
            ->where(function ($q) {
                $q->where('is_cash_account', true)
                  ->orWhere('is_bank_account', true);
            })
            ->orderBy('code')
            ->get();
    }

    public function getActiveAccountProperty()
    {
        if ($this->selectedAccountId) {
            return Account::where('user_id', auth()->id())
                ->find($this->selectedAccountId);
        }
        return null;
    }

    public function getAccountStatementProperty(): array
    {
        if (!$this->activeAccount) {
            return [];
        }

        try {
            $service = app(CashBankService::class);
            $statement = $service->getAccountStatement(
                $this->activeAccount,
                $this->statementDateFrom ?: null,
                $this->statementDateTo ?: null
            );

            // Sort array based on selected field and direction
            usort($statement, function ($a, $b) {
                $valA = $a[$this->sortBy] ?? '';
                $valB = $b[$this->sortBy] ?? '';

                if ($valA === $valB) {
                    return 0;
                }

                if ($this->sortDir === 'asc') {
                    return $valA < $valB ? -1 : 1;
                } else {
                    return $valA > $valB ? -1 : 1;
                }
            });

            return $statement;
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getRecentTransfersProperty()
    {
        return MoneyTransfer::where('user_id', auth()->id())
            ->with(['fromAccount', 'toAccount'])
            ->when($this->filterDateFrom, fn($q) => $q->where('transfer_date', '>=', $this->filterDateFrom))
            ->when($this->filterDateTo, fn($q) => $q->where('transfer_date', '<=', $this->filterDateTo))
            ->when($this->filterStatus, fn($q) => $q->where('status', $this->filterStatus))
            ->orderByDesc('transfer_date')
            ->orderByDesc('id')
            ->limit(30)
            ->get();
    }

    public function getLegalEntitiesProperty()
    {
        return \App\Models\LegalEntity::where('user_id', auth()->id())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function getKpiMetricsProperty(): array
    {
        $userId = auth()->id();
        $cashAccounts = CashAccount::where('user_id', $userId)->with('account')->get();
        $bankAccounts = BankAccount::where('user_id', $userId)->with('account')->get();

        $totalCash = 0.0;
        foreach ($cashAccounts as $ca) {
            $totalCash += $ca->balance();
        }

        $totalBank = 0.0;
        foreach ($bankAccounts as $ba) {
            $totalBank += $ba->balance();
        }

        $todayTransfersSum = (float) MoneyTransfer::where('user_id', $userId)
            ->where('status', 'posted')
            ->where('transfer_date', now()->toDateString())
            ->sum('amount');

        $voidedTransfersCount = MoneyTransfer::where('user_id', $userId)
            ->where('status', 'voided')
            ->count();

        return [
            'total_cash' => $totalCash,
            'total_bank' => $totalBank,
            'total_liquidity' => $totalCash + $totalBank,
            'today_transfers_sum' => $todayTransfersSum,
            'voided_transfers_count' => $voidedTransfersCount,
        ];
    }

    public function render()
    {
        return view('livewire.accounting.cash-bank')
            ->layout('layouts.app');
    }
}
