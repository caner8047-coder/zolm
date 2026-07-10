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
    // Lists
    public ?int $selectedAccountId = null;
    public string $statementDateFrom = '';
    public string $statementDateTo = '';

    // Create Kasa Form
    public bool $showCashForm = false;
    public string $cashName = '';

    // Create Banka Form
    public bool $showBankForm = false;
    public string $bankName = '';
    public string $branchName = '';
    public string $accountNumber = '';
    public string $iban = '';
    public string $currencyCode = 'TRY';

    // Transfer Form (Virman)
    public bool $showTransferForm = false;
    public ?int $fromAccountId = null;
    public ?int $toAccountId = null;
    public float $transferAmount = 0.0;
    public string $transferDate = '';
    public string $transferDescription = '';

    // Status Messaging
    public string $message = '';
    public string $messageType = 'success';

    public function mount(): void
    {
        $this->transferDate = now()->toDateString();
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
            $service->createCashAccount($userId, $this->cashName);

            $this->message = 'Kasa hesabı başarıyla oluşturuldu.';
            $this->messageType = 'success';
            $this->cashName = '';
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
            ]);

            $this->message = 'Banka hesabı başarıyla oluşturuldu.';
            $this->messageType = 'success';

            // Reset
            $this->bankName = '';
            $this->branchName = '';
            $this->accountNumber = '';
            $this->iban = '';
            $this->currencyCode = 'TRY';
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
        $fromAcc = Account::find($this->fromAccountId);
        $toAcc = Account::find($this->toAccountId);
        if (!$fromAcc || (int) $fromAcc->user_id !== $userId || !$toAcc || (int) $toAcc->user_id !== $userId) {
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
            ]);

            $this->message = 'Para transferi başarıyla gerçekleştirildi.';
            $this->messageType = 'success';

            // Reset
            $this->fromAccountId = null;
            $this->toAccountId = null;
            $this->transferAmount = 0.0;
            $this->transferDescription = '';
            $this->showTransferForm = false;
        } catch (\Exception $e) {
            $this->message = 'Para transferi sırasında hata: ' . $e->getMessage();
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
            ->with('account')
            ->get();
    }

    public function getBankAccountsProperty()
    {
        return BankAccount::where('user_id', auth()->id())
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
            return $service->getAccountStatement(
                $this->activeAccount,
                $this->statementDateFrom ?: null,
                $this->statementDateTo ?: null
            );
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getRecentTransfersProperty()
    {
        return MoneyTransfer::where('user_id', auth()->id())
            ->with(['fromAccount', 'toAccount'])
            ->orderByDesc('transfer_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get();
    }

    public function render()
    {
        return view('livewire.accounting.cash-bank')
            ->layout('layouts.app');
    }
}
