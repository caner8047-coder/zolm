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
 * Kasa, Banka ve Virman Yönetim Servisi (Faz P4 Hardened).
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
    public function createCashAccount(int $userId, string $name, string $currencyCode = 'TRY', ?int $legalEntityId = null): CashAccount
    {
        if ($legalEntityId !== null) {
            // Validate that the legal entity belongs to the user
            \App\Models\LegalEntity::where('user_id', $userId)->findOrFail($legalEntityId);
        }

        return DB::transaction(function () use ($userId, $name, $currencyCode, $legalEntityId) {
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
                'legal_entity_id' => $legalEntityId,
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
        $legalEntityId = $data['legal_entity_id'] ?? null;
        if ($legalEntityId !== null) {
            // Validate that the legal entity belongs to the user
            \App\Models\LegalEntity::where('user_id', $userId)->findOrFail($legalEntityId);
        }

        return DB::transaction(function () use ($userId, $data, $legalEntityId) {
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
                'legal_entity_id' => $legalEntityId,
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
        $rate = (float) ($data['exchange_rate'] ?? 1.0);

        if ($amount <= 0) {
            throw new InvalidArgumentException('Transfer tutarı sıfırdan büyük olmalıdır.');
        }

        if ($rate <= 0) {
            throw new InvalidArgumentException('Döviz kuru sıfırdan büyük olmalıdır.');
        }

        $fromAccountId = (int) $data['from_account_id'];
        $toAccountId = (int) $data['to_account_id'];

        if ($fromAccountId === $toAccountId) {
            throw new InvalidArgumentException('Kaynak ve hedef hesap aynı olamaz.');
        }

        // Idempotency check using source_key
        $sourceKey = $data['source_key'] ?? null;
        if ($sourceKey !== null && $sourceKey !== '') {
            $existing = MoneyTransfer::where('user_id', $userId)
                ->where('source_key', $sourceKey)
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        $fromAccount = Account::where('user_id', $userId)->findOrFail($fromAccountId);
        $toAccount = Account::where('user_id', $userId)->findOrFail($toAccountId);

        if (!$fromAccount->is_active || !$toAccount->is_active) {
            throw new InvalidArgumentException('İşlem yapmak istediğiniz hesaplardan biri pasif durumda.');
        }

        // Sadece kasa veya banka hesapları kabul edilir
        if (!($fromAccount->is_cash_account || $fromAccount->is_bank_account)) {
            throw new InvalidArgumentException('Kaynak hesap kasa veya banka hesabı olmalıdır.');
        }

        if (!($toAccount->is_cash_account || $toAccount->is_bank_account)) {
            throw new InvalidArgumentException('Hedef hesap kasa veya banka hesabı olmalıdır.');
        }

        // Legal entity verification & resolution
        if ($fromAccount->legal_entity_id !== null && $toAccount->legal_entity_id !== null && (int)$fromAccount->legal_entity_id !== (int)$toAccount->legal_entity_id) {
            throw new InvalidArgumentException('Farklı yasal birliklere ait hesaplar arasında virman yapılamaz.');
        }


        $legalEntityId = $data['legal_entity_id'] ?? null;
        $resolvedLegalEntityId = null;

        if ($legalEntityId !== null) {
            \App\Models\LegalEntity::where('user_id', $userId)->findOrFail($legalEntityId);

            if ($fromAccount->legal_entity_id !== null && (int)$fromAccount->legal_entity_id !== (int)$legalEntityId) {
                throw new InvalidArgumentException('Kaynak hesabın yasal birliği transfer yasal birliği ile çakışıyor.');
            }
            if ($toAccount->legal_entity_id !== null && (int)$toAccount->legal_entity_id !== (int)$legalEntityId) {
                throw new InvalidArgumentException('Hedef hesabın yasal birliği transfer yasal birliği ile çakışıyor.');
            }
            $resolvedLegalEntityId = $legalEntityId;
        } else {
            // Eğer legal_entity_id verilmemişse ve iki hesap aynı legal_entity_id'ye sahipse (null değilseler), o zaman o id'yi kullan
            if ($fromAccount->legal_entity_id !== null && $toAccount->legal_entity_id !== null) {
                if ((int)$fromAccount->legal_entity_id === (int)$toAccount->legal_entity_id) {
                    $resolvedLegalEntityId = (int)$fromAccount->legal_entity_id;
                }
            }
        }

        return DB::transaction(function () use ($data, $userId, $amount, $rate, $fromAccount, $toAccount, $sourceKey, $resolvedLegalEntityId) {
            // 1. Journal Entry oluştur
            $journal = $this->journalService->postManual([
                'user_id'          => $userId,
                'entry_date'       => $data['transfer_date'],
                'entry_type'       => 'bank_transfer',
                'source_type'      => 'money_transfer',
                'source_key'       => $sourceKey,
                'reference_number' => $data['reference_number'] ?? null,
                'description'      => $data['description'] ?? 'Virman Transferi',
                'currency_code'    => $data['currency_code'] ?? 'TRY',
                'exchange_rate'    => $rate,
                'legal_entity_id'  => $resolvedLegalEntityId,
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
                'from_account_id'  => $fromAccount->id,
                'to_account_id'    => $toAccount->id,
                'journal_entry_id' => $journal->id,
                'amount'           => $amount,
                'exchange_rate'    => $rate,
                'transfer_date'    => $data['transfer_date'],
                'description'      => $data['description'] ?? null,
                'legal_entity_id'  => $resolvedLegalEntityId,
                'source_key'       => $sourceKey,
                'reference_number' => $data['reference_number'] ?? null,
                'status'           => 'posted',
                'posted_at'        => now(),
            ]);
        });
    }

    /**
     * Virman İptali (Void).
     */
    public function voidTransfer(MoneyTransfer $transfer, ?string $reason = null, ?int $userId = null): MoneyTransfer
    {
        $actorUserId = $userId ?? auth()->id();
        if ($actorUserId === null) {
            throw new InvalidArgumentException('İşlem yapan kullanıcı bilgisi bulunamadı.');
        }

        if ((int)$transfer->user_id !== (int)$actorUserId) {
            throw new InvalidArgumentException('Bu transfer üzerinde işlem yapma yetkiniz yok.');
        }

        if ($transfer->status === 'voided') {
            throw new InvalidArgumentException('Bu transfer zaten iptal edilmiş.');
        }

        DB::transaction(function () use ($transfer, $reason) {
            // Bağlı journal entry void edilir
            if ($transfer->journal_entry_id && $transfer->journalEntry) {
                $this->journalService->voidEntry($transfer->journalEntry, $reason ?? 'Virman iptali');
            }

            // Transfer durumunu güncelle
            $transfer->update([
                'status'      => 'voided',
                'voided_at'   => now(),
                'void_reason' => $reason,
            ]);
        });

        return $transfer->fresh();
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
                    $q->whereDate('entry_date', '>=', $dateFrom);
                }
                if ($dateTo) {
                    $q->whereDate('entry_date', '<=', $dateTo);
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
