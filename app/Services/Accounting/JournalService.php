<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Party;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Yevmiye fişi (journal entry) oluşturma ve iptal servisi.
 *
 * Çift taraflı muhasebe kuralı:
 * - Her fişte toplam borç (debit) = toplam alacak (credit) olmalıdır.
 * - Dengeli olmayan fiş reddedilir (InvalidArgumentException).
 * - Her fiş source_type + source_key ile idempotent olabilir.
 * - user_id izolasyonu zorunludur; hesap başka user'a aitse reddedilir.
 *
 * Bu faz kapsamı: manuel fiş + void.
 * İleride: sales_invoice, collection, purchase_invoice bağlamı buradan beslenir.
 */
class JournalService
{
    /**
     * Manuel yevmiye fişi kaydet.
     *
     * @param array{
     *     user_id: int,
     *     entry_date: string,
     *     entry_type?: string,
     *     description?: string|null,
     *     currency_code?: string,
     *     exchange_rate?: float,
     *     reference_number?: string|null,
     *     due_date?: string|null,
     *     legal_entity_id?: int|null,
     *     party_id?: int|null,
     *     source_type?: string|null,
     *     source_id?: int|null,
     *     source_key?: string|null,
     *     meta_json?: array|null,
     * } $header Fiş başlık bilgileri
     *
     * @param array<int, array{
     *     account_id: int,
     *     debit_amount?: float,
     *     credit_amount?: float,
     *     description?: string|null,
     *     party_id?: int|null,
     * }> $lines Fiş satırları (en az 2 satır)
     */
    public function postManual(array $header, array $lines): JournalEntry
    {
        $userId = (int) $header['user_id'];

        // Tenant validation for party_id and legal_entity_id in header
        if (isset($header['party_id']) && $header['party_id'] !== null) {
            $party = \App\Models\Party::find($header['party_id']);
            if (!$party || (int) $party->user_id !== $userId) {
                throw new InvalidArgumentException('Fiş başlığındaki party bu kullanıcıya ait değil.');
            }
        }
        if (isset($header['legal_entity_id']) && $header['legal_entity_id'] !== null) {
            $legalEntity = \App\Models\LegalEntity::find($header['legal_entity_id']);
            if (!$legalEntity || (int) $legalEntity->user_id !== $userId) {
                throw new InvalidArgumentException('Fiş başlığındaki legal entity bu kullanıcıya ait değil.');
            }
        }

        // source_key idempotency kontrolü
        $sourceKey = $header['source_key'] ?? null;
        if ($sourceKey !== null && $sourceKey !== '') {
            $existing = JournalEntry::where('user_id', $userId)
                ->where('source_key', $sourceKey)
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        // Satır validasyonları
        $this->validateLines($userId, $lines, $header['currency_code'] ?? 'TRY', (float) ($header['exchange_rate'] ?? 1));

        return DB::transaction(function () use ($header, $lines, $userId, $sourceKey) {
            $entry = JournalEntry::create([
                'user_id'          => $userId,
                'legal_entity_id'  => $header['legal_entity_id'] ?? null,
                'party_id'         => $header['party_id'] ?? null,
                'entry_type'       => $header['entry_type'] ?? 'manual',
                'source_type'      => $header['source_type'] ?? 'manual',
                'source_id'        => $header['source_id'] ?? null,
                'source_key'       => $sourceKey,
                'reference_number' => $header['reference_number'] ?? null,
                'entry_date'       => $header['entry_date'],
                'due_date'         => $header['due_date'] ?? null,
                'description'      => $header['description'] ?? null,
                'currency_code'    => $header['currency_code'] ?? 'TRY',
                'exchange_rate'    => $header['exchange_rate'] ?? 1,
                'status'           => 'posted',
                'posted_at'        => now(),
                'meta_json'        => $header['meta_json'] ?? null,
            ]);

            foreach ($lines as $i => $line) {
                $rate       = (float) ($header['exchange_rate'] ?? 1);
                $debit      = (float) ($line['debit_amount'] ?? 0);
                $credit     = (float) ($line['credit_amount'] ?? 0);
                $debitBase  = round($debit * $rate, 2);
                $creditBase = round($credit * $rate, 2);

                JournalLine::create([
                    'user_id'            => $userId,
                    'journal_entry_id'   => $entry->id,
                    'account_id'         => (int) $line['account_id'],
                    'party_id'           => $line['party_id'] ?? null,
                    'debit_amount'       => $debit,
                    'credit_amount'      => $credit,
                    'currency_code'      => $header['currency_code'] ?? 'TRY',
                    'exchange_rate'      => $rate,
                    'debit_base_amount'  => $debitBase,
                    'credit_base_amount' => $creditBase,
                    'sort_order'         => $i,
                    'description'        => $line['description'] ?? null,
                    'meta_json'          => $line['meta_json'] ?? null,
                ]);
            }

            return $entry->load('lines');
        });
    }

    /**
     * Fişi iptal et (void).
     * Voided fiş bakiyeye dahil edilmez.
     */
    public function voidEntry(JournalEntry $entry, ?string $reason = null, ?int $voidedBy = null): JournalEntry
    {
        if ($entry->isVoid()) {
            throw new InvalidArgumentException('Fiş zaten iptal edilmiş.');
        }

        $entry->update([
            'status'     => 'voided',
            'voided_at'  => now(),
            'voided_by'  => $voidedBy,
            'void_reason' => $reason,
        ]);

        return $entry->fresh();
    }

    /**
     * Kullanıcıya ait hesapların bakiyesini hesapla.
     *
     * @return array{ debit: float, credit: float, balance: float }
     */
    public function accountBalance(Account $account, ?int $legalEntityId = null): array
    {
        $query = JournalLine::where('account_id', $account->id)
            ->whereHas('journalEntry', function ($q) use ($account, $legalEntityId) {
                $q->where('status', 'posted')->where('user_id', $account->user_id);
                if ($legalEntityId !== null) {
                    $q->where('legal_entity_id', $legalEntityId);
                }
            });

        $debit  = (float) $query->sum('debit_base_amount');
        $credit = (float) $query->sum('credit_base_amount');

        $balance = $account->isDebitNormal()
            ? $debit - $credit
            : $credit - $debit;

        return compact('debit', 'credit', 'balance');
    }

    /**
     * Satır validasyonları:
     * 1. En az 2 satır olmalı.
     * 2. Her satırda debit ve credit ikisi birden pozitif olamaz.
     * 3. Her satırda en az biri pozitif olmalı.
     * 4. Tüm satırlarda toplam debit = toplam credit (denge).
     * 5. Hesaplar current user'a ait olmalı.
     */
    private function validateLines(int $userId, array $lines, string $currencyCode, float $rate): void
    {
        if (count($lines) < 2) {
            throw new InvalidArgumentException('Yevmiye fişinde en az 2 satır olmalıdır.');
        }

        $totalDebit  = 0.0;
        $totalCredit = 0.0;

        foreach ($lines as $i => $line) {
            $debit  = (float) ($line['debit_amount'] ?? 0);
            $credit = (float) ($line['credit_amount'] ?? 0);

            if ($debit < 0 || $credit < 0) {
                throw new InvalidArgumentException("Satır #{$i}: borç veya alacak negatif olamaz.");
            }

            if ($debit > 0 && $credit > 0) {
                throw new InvalidArgumentException("Satır #{$i}: aynı satırda borç ve alacak ikisi birden pozitif olamaz.");
            }

            if ($debit <= 0 && $credit <= 0) {
                throw new InvalidArgumentException("Satır #{$i}: borç veya alacaktan en az biri pozitif olmalıdır.");
            }

            // Hesap kullanıcıya ait mi?
            $account = Account::find((int) ($line['account_id'] ?? 0));
            if (!$account || (int) $account->user_id !== $userId) {
                throw new InvalidArgumentException("Satır #{$i}: hesap bu kullanıcıya ait değil.");
            }

            // Satırdaki party_id kullanıcıya ait mi?
            if (isset($line['party_id']) && $line['party_id'] !== null) {
                $lineParty = \App\Models\Party::find($line['party_id']);
                if (!$lineParty || (int) $lineParty->user_id !== $userId) {
                    throw new InvalidArgumentException("Satır #{$i}: party bu kullanıcıya ait değil.");
                }
            }

            if (!$account->is_active) {
                throw new InvalidArgumentException("Satır #{$i}: hesap pasif durumda; fişe eklenemez.");
            }

            $totalDebit  += round($debit * $rate, 2);
            $totalCredit += round($credit * $rate, 2);
        }

        // Denge kontrolü (floating point tolerans: 0.005)
        if (abs($totalDebit - $totalCredit) >= 0.005) {
            throw new InvalidArgumentException(
                sprintf(
                    'Fiş dengeli değil. Toplam borç: %.2f, toplam alacak: %.2f, fark: %.2f',
                    $totalDebit,
                    $totalCredit,
                    $totalDebit - $totalCredit
                )
            );
        }
    }
}
