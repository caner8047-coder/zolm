<?php

namespace App\Services\Accounting;

use App\Models\CrmContact;
use App\Models\LegalEntity;
use App\Models\Party;
use App\Models\PartyLedgerEntry;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Party bazlı cari açık hesap (alacak-borç) hareket defteri servisi.
 *
 * Bu servis ön muhasebe için güvenli bir cari hareket temeli sağlar; tam
 * muhasebe/yevmiye sistemi değildir. Bakiye kuralı: debit - credit.
 * Pozitif bakiye = biz alacaklıyız; negatif bakiye = biz borçluyuz.
 * Void kayıtlar bakiyeye dahil edilmez.
 *
 * Güvenlik:
 * - user_id izolasyonu korunur; party->user_id dışındaki user ile kayıt yazılmaz.
 * - source_key verilirse idempotent davranır (user_id + source_key unique).
 * - amount negatif/sıfır reddedilir.
 * - Aynı entry'de debit ve credit birlikte pozitif olamaz.
 */
class PartyLedgerService
{
    public function isEnabled(): bool
    {
        return (bool) config('marketplace.features.accounting_enabled', false);
    }

    /**
     * @param array{
     *     user_id:int,
     *     party_id:int,
     *     legal_entity_id?:int|null,
     *     crm_contact_id?:int|null,
     *     source_type?:string,
     *     source_key?:string|null,
     *     document_type:string,
     *     document_number?:string|null,
     *     document_date:string,
     *     due_date?:string|null,
     *     description?:string|null,
     *     debit_amount?:float,
     *     credit_amount?:float,
     *     currency_code?:string,
     *     exchange_rate?:float,
     *     meta_json?:array|null,
     * } $data
     */
    public function postEntry(array $data): PartyLedgerEntry
    {
        $userId = (int) $data['user_id'];
        $party = Party::findOrFail((int) $data['party_id']);

        if ((int) $party->user_id !== $userId) {
            throw new \InvalidArgumentException('Party bu kullanıcıya ait değil; user izolasyonu ihlal edildi.');
        }

        // legal_entity_id verildiyse user_id doğrulaması
        if (!empty($data['legal_entity_id'])) {
            $legalEntity = LegalEntity::find((int) $data['legal_entity_id']);
            if (!$legalEntity || (int) $legalEntity->user_id !== $userId) {
                throw new \InvalidArgumentException('LegalEntity bu kullanıcıya ait değil; user izolasyonu ihlal edildi.');
            }
        }

        // crm_contact_id verildiyse user_id doğrulaması
        if (!empty($data['crm_contact_id'])) {
            $crmContact = CrmContact::find((int) $data['crm_contact_id']);
            if (!$crmContact || (int) $crmContact->user_id !== $userId) {
                throw new \InvalidArgumentException('CrmContact bu kullanıcıya ait değil; user izolasyonu ihlal edildi.');
            }
        }

        $debit = (float) ($data['debit_amount'] ?? 0);
        $credit = (float) ($data['credit_amount'] ?? 0);

        if ($debit < 0 || $credit < 0) {
            throw new \InvalidArgumentException('debit/credit_amount negatif olamaz.');
        }

        if ($debit <= 0 && $credit <= 0) {
            throw new \InvalidArgumentException('amount sıfır veya negatif; en azından debit veya credit pozitif olmalı.');
        }

        if ($debit > 0 && $credit > 0) {
            throw new \InvalidArgumentException('Aynı entryde debit ve credit birlikte pozitif olamaz.');
        }

        $exchangeRate = (float) ($data['exchange_rate'] ?? 1);
        if ($exchangeRate <= 0) {
            throw new \InvalidArgumentException('exchange_rate sıfır veya negatif olamaz.');
        }

        $currencyCode = (string) ($data['currency_code'] ?? 'TRY');
        $debitBase = round($debit * $exchangeRate, 2);
        $creditBase = round($credit * $exchangeRate, 2);
        $sourceKey = $data['source_key'] ?? null;

        return DB::transaction(function () use ($data, $party, $userId, $debit, $credit, $currencyCode, $exchangeRate, $debitBase, $creditBase, $sourceKey) {
            // source_key verilirse idempotent: aynı user_id + source_key varsa döndür.
            if ($sourceKey !== null && $sourceKey !== '') {
                $existing = PartyLedgerEntry::query()
                    ->where('user_id', $userId)
                    ->where('source_key', $sourceKey)
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            return PartyLedgerEntry::create([
                'user_id' => $userId,
                'party_id' => $party->id,
                'legal_entity_id' => $data['legal_entity_id'] ?? null,
                'crm_contact_id' => $data['crm_contact_id'] ?? null,
                'source_type' => (string) ($data['source_type'] ?? 'manual'),
                'source_key' => $sourceKey,
                'document_type' => (string) $data['document_type'],
                'document_number' => $data['document_number'] ?? null,
                'document_date' => (string) $data['document_date'],
                'due_date' => $data['due_date'] ?? null,
                'description' => $data['description'] ?? null,
                'debit_amount' => $debit,
                'credit_amount' => $credit,
                'currency_code' => $currencyCode,
                'exchange_rate' => $exchangeRate,
                'debit_base_amount' => $debitBase,
                'credit_base_amount' => $creditBase,
                'status' => 'posted',
                'posted_at' => now(),
                'meta_json' => $data['meta_json'] ?? null,
            ]);
        });
    }

    public function postReceivable(Party $party, float $amount, array $context = []): PartyLedgerEntry
    {
        $this->validateAmount($amount);

        return $this->postEntry(array_merge([
            'user_id' => $party->user_id,
            'party_id' => $party->id,
            'document_type' => 'receivable',
            'document_date' => now()->toDateString(),
            'debit_amount' => $amount,
            'credit_amount' => 0,
        ], $context));
    }

    public function postCollection(Party $party, float $amount, array $context = []): PartyLedgerEntry
    {
        $this->validateAmount($amount);

        return $this->postEntry(array_merge([
            'user_id' => $party->user_id,
            'party_id' => $party->id,
            'document_type' => 'collection',
            'document_date' => now()->toDateString(),
            'debit_amount' => 0,
            'credit_amount' => $amount,
        ], $context));
    }

    public function postPayable(Party $party, float $amount, array $context = []): PartyLedgerEntry
    {
        $this->validateAmount($amount);

        return $this->postEntry(array_merge([
            'user_id' => $party->user_id,
            'party_id' => $party->id,
            'document_type' => 'payable',
            'document_date' => now()->toDateString(),
            'debit_amount' => 0,
            'credit_amount' => $amount,
        ], $context));
    }

    public function postPayment(Party $party, float $amount, array $context = []): PartyLedgerEntry
    {
        $this->validateAmount($amount);

        return $this->postEntry(array_merge([
            'user_id' => $party->user_id,
            'party_id' => $party->id,
            'document_type' => 'payment',
            'document_date' => now()->toDateString(),
            'debit_amount' => $amount,
            'credit_amount' => 0,
        ], $context));
    }

    public function voidEntry(PartyLedgerEntry $entry, ?string $reason = null): PartyLedgerEntry
    {
        if ($entry->isVoid()) {
            throw new InvalidArgumentException('Entry zaten iptal edilmiş.');
        }

        $entry->update([
            'status' => 'voided',
            'voided_at' => now(),
            'void_reason' => $reason,
        ]);

        return $entry->fresh();
    }

    public function balanceForParty(Party $party, ?int $legalEntityId = null): array
    {
        $query = PartyLedgerEntry::where('user_id', $party->user_id)
            ->where('party_id', $party->id)
            ->posted();

        if ($legalEntityId !== null) {
            $query->where('legal_entity_id', $legalEntityId);
        }

        $entries = $query->get();

        $debit = (float) $entries->sum('debit_amount');
        $credit = (float) $entries->sum('credit_amount');

        return [
            'debit' => $debit,
            'credit' => $credit,
            'balance' => $debit - $credit,
        ];
    }

    private function validateAmount(float $amount): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Amount sıfır veya negatif olamaz.');
        }
    }
}
