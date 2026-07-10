<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\JournalLine;
use App\Models\MoneyTransfer;
use App\Services\Accounting\JournalService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Kasa, Banka ve Virman Yönetim Servisi.
 *
 * Sorumluluklar:
 * 1. Banka ve Kasa hesaplarının oluşturulması ve hesap planına bağlanması.
 * 2. Virman (Banka/Kasa arası para transferi) işlemleri.
 * 3. Genel Muhasebe entegrasyonu: Virman fişi (debit alıcı, credit verici) üretimi.
 * 4. Kasa/Banka hesap dökümü (ekstre/transactions) listesi.
 */
class CashBankService
{
    protected JournalService $journalService;

    public function __construct(JournalService $journalService)
    {
        $this->journalService = $journalService;
    }

    /**
     * Kasa Hesabı ve Muhasebe Hesabı Oluştur.
     */
    public function createCashAccount(int $userId, string $name, string $currencyCode = 'TRY'): CashAccount
    {
        return DB::transaction(function () use ($userId, $name, $currencyCode) {
            // TDHP'deki son 100 kodunu bulup yeni bir alt hesap kodu üretelim (örn: 100.01, 100.02)
            $lastCode = Account::where('user_id', $userId)
                ->where('code', 'like', '100.%')
                ->orderByDesc('code')
                ->first();

            $nextNumber = $lastCode ? ((int) substr($lastCode->code, 4)) + 1 : 1;
            $code = '100.' . str_pad($nextNumber, 2, '0', STR_PAD_LEFT);

            // Muhasebe hesabı oluştur
            $account = Account::create([
                'user_id'         => $userId,
                'code'            => $code,
                'name'            => $name,
                'type'            => 'asset',
                'normal_balance'  => 'debit',
                'currency_code'   => $currencyCode,
                'is_cash_account' => true,
                'is_active'       => true,
            ]);

            return CashAccount::create([
                'user_id'       => $userId,
                'account_id'    => $account->id,
                'name'          => $name,
                'currency_code' => $currencyCode,
                'is_active'     => true,
            ]);
        });
    }

    /**
     * Banka Hesabı ve Muhasebe Hesabı Oluştur.
     */
    public function createBankAccount(int $userId, array $data): BankAccount
    {
        return DB::transaction(function () use ($userId, $data) {
            $lastCode = Account::where('user_id', $userId)
                ->where('code', 'like', '102.%')
                ->orderByDesc('code')
                ->first();

            $nextNumber = $lastCode ? ((int) substr($lastCode->code, 4)) + 1 : 1;
            $code = '102.' . str_pad($nextNumber, 2, '0', STR_PAD_LEFT);

            $account = Account::create([
                'user_id'         => $userId,
                'code'            => $code,
                'name'            => $data['bank_name'] . ' - ' . ($data['account_number'] ?? 'Hesabı'),
                'type'            => 'asset',
                'normal_balance'  => 'debit',
                'currency_code'   => $data['currency_code'] ?? 'TRY',
                'is_bank_account' => true,
                'is_active'       => true,
            ]);

            return BankAccount::create([
                'user_id'        => $userId,
                'account_id'     => $account->id,
                'bank_name'      => $data['bank_name'],
                'branch_name'    => $data['branch_name'] ?? null,
                'account_number' => $data['account_number'] ?? null,
                'iban'           => $data['iban'] ?? null,
                'currency_code'  => $data['currency_code'] ?? 'TRY',
                'is_active'      => true,
            ]);
        });
    }

    /**
     * Virman İşlemi (Para Transferi).
     * Genel Muhasebe: Borç Alıcı Hesap (to) - Alacak Verici Hesap (from)
     */
    public function transferFunds(array $data): MoneyTransfer
    {
        $userId = (int) $data['user_id'];
        $amount = (float) $data['amount'];

        if ($amount <= 0) {
            throw new InvalidArgumentException('Transfer tutarı sıfırdan büyük olmalıdır.');
        }

        $fromAccountId = (int) $data['from_account_id'];
        $toAccountId = (int) $data['to_account_id'];

        if ($fromAccountId === $toAccountId) {
            throw new InvalidArgumentException('Kaynak ve hedef hesap aynı olamaz.');
        }

        return DB::transaction(function () use ($data, $userId, $amount, $fromAccountId, $toAccountId) {
            $fromAccount = Account::where('user_id', $userId)->findOrFail($fromAccountId);
            $toAccount = Account::where('user_id', $userId)->findOrFail($toAccountId);

            if (!$fromAccount->is_active || !$toAccount->is_active) {
                throw new InvalidArgumentException('İşlem yapmak istediğiniz hesaplardan biri pasif durumda.');
            }

            // 1. Journal Entry oluştur
            $journal = $this->journalService->postManual([
                'user_id'         => $userId,
                'entry_date'      => $data['transfer_date'],
                'entry_type'      => 'bank_transfer',
                'description'     => $data['description'] ?? 'Virman Transferi',
                'currency_code'   => $data['currency_code'] ?? 'TRY',
                'exchange_rate'   => $data['exchange_rate'] ?? 1.0,
                'legal_entity_id' => $data['legal_entity_id'] ?? null,
            ], [
                [
                    'account_id'   => $toAccount->id, // Alıcı borçlanır
                    'debit_amount' => $amount,
                ],
                [
                    'account_id'    => $fromAccount->id, // Verici alacaklanır
                    'credit_amount' => $amount,
                ]
            ]);

            // 2. Transfer kaydını oluştur
            return MoneyTransfer::create([
                'user_id'          => $userId,
                'from_account_id'  => $fromAccountId,
                'to_account_id'    => $toAccountId,
                'journal_entry_id' => $journal->id,
                'amount'           => $amount,
                'exchange_rate'    => $data['exchange_rate'] ?? 1.0,
                'transfer_date'    => $data['transfer_date'],
                'description'      => $data['description'] ?? null,
            ]);
        });
    }

    /**
     * Kasa/Banka Hesap Dökümü (Ekstre).
     */
    public function getAccountStatement(Account $account, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = JournalLine::where('account_id', $account->id)
            ->whereHas('journalEntry', function ($q) use ($account, $dateFrom, $dateTo) {
                $q->where('status', 'posted')->where('user_id', $account->user_id);
                if ($dateFrom) {
                    $q->where('entry_date', '>=', $dateFrom);
                }
                if ($dateTo) {
                    $q->where('entry_date', '<=', $dateTo);
                }
            })
            ->with('journalEntry')
            ->get();

        $statement = $query->map(fn($line) => [
            'date'        => $line->journalEntry->entry_date->toDateString(),
            'type'        => $line->journalEntry->entry_type,
            'description' => $line->description ?? $line->journalEntry->description,
            'debit'       => (float) $line->debit_base_amount,
            'credit'      => (float) $line->credit_base_amount,
        ])->sortBy('date')->values()->toArray();

        // Kasa/Banka borç normal bakiyedir
        $runningBalance = 0.0;
        foreach ($statement as &$row) {
            $runningBalance += ($row['debit'] - $row['credit']);
            $row['balance'] = $runningBalance;
        }

        return $statement;
    }
}
