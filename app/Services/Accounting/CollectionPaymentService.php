<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\Collection;
use App\Models\Party;
use App\Models\Payable;
use App\Models\PayableAllocation;
use App\Models\Payment;
use App\Models\Receivable;
use App\Models\ReceivableAllocation;
use App\Services\Accounting\JournalService;
use App\Services\Accounting\PartyLedgerService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Tahsilat / Ödeme Merkezi Servisi (Faz P3).
 *
 * Sorumluluklar:
 * 1. Müşteri tahsilatı kaydetme ve kasa/banka hesabına yansıtma.
 * 2. Tedarikçi ödemesi kaydetme ve kasa/banka hesabından düşme.
 * 3. Tahsilat/ödemeyi açık faturalara kısmi veya tam dağıtma (allocate).
 * 4. Tahsilat/ödemeyi iptal etme (void) ve tüm etkileri geri alma.
 * 5. Her adımda tam GL (JournalService) + Cari Defteri (PartyLedgerService) kaydı.
 *
 * Güvenlik:
 * - Tüm işlemler DB::transaction içinde.
 * - user_id izolasyonu her sorguda.
 * - source_key ile idempotency: aynı source_key ikinci çağrıda duplicate oluşturmaz.
 * - Hesap tipi guard: sadece cash/bank account kabul edilir.
 * - Legal entity exact-match: farklı legal entity faturasına dağıtım engellenir.
 * - Voided kayıt allocate edilemez.
 * - Negatif/sıfır tutar reddedilir.
 */
class CollectionPaymentService
{
    public function __construct(
        protected JournalService $journalService,
        protected PartyLedgerService $ledgerService,
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // TAHSILAT (Collection)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Müşteri tahsilatı kaydet.
     *
     * GL: Borç Kasa/Banka (102/100) ↔ Alacak Alıcılar (120)
     * Cari Defteri: credit (alacak azalır / bakiye düşer)
     *
     * @param array{
     *   user_id:int,
     *   party_id:int,
     *   account_id:int,           Kasa veya banka GL hesabı
     *   amount:float,
     *   collection_date:string,
     *   payment_method:string,    cash|bank|check|other
     *   description?:string|null,
     *   legal_entity_id?:int|null,
     *   reference_number?:string|null,
     *   source_key?:string|null,  Idempotency anahtarı
     * } $data
     */
    public function recordCollection(array $data): Collection
    {
        $userId    = (int) $data['user_id'];
        $amount    = (float) $data['amount'];
        $sourceKey = $data['source_key'] ?? null;

        if ($amount <= 0) {
            throw new InvalidArgumentException('Tahsilat tutarı sıfırdan büyük olmalıdır.');
        }

        // Bloklayıcı 5: Idempotency — aynı source_key varsa mevcut kaydı dön
        if ($sourceKey !== null) {
            $existing = Collection::where('user_id', $userId)
                ->where('source_key', $sourceKey)
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($data, $userId, $amount, $sourceKey) {
            $party = $this->resolveTenantParty($userId, (int) $data['party_id']);

            // Bloklayıcı 2: Hesap tipi guard — sadece aktif kasa/banka hesabı
            $cashBankAccount = $this->resolveCashBankAccount($userId, (int) $data['account_id']);

            // 120 - Alıcılar hesabı
            $arAccount = Account::where('user_id', $userId)
                ->where('is_ar_account', true)
                ->first();

            if (!$arAccount) {
                throw new InvalidArgumentException('Alıcılar hesabı (120) bulunamadı. Hesap planını kontrol edin.');
            }

            $legalEntityId = $data['legal_entity_id'] ?? null;

            // 1. GL Fişi: Borç Kasa/Banka – Alacak Alıcılar
            $journal = $this->journalService->postManual([
                'user_id'          => $userId,
                'entry_date'       => $data['collection_date'],
                'entry_type'       => 'collection',
                'description'      => $data['description'] ?? 'Tahsilat Kaydı',
                'currency_code'    => $data['currency_code'] ?? 'TRY',
                'exchange_rate'    => $data['exchange_rate'] ?? 1.0,
                'legal_entity_id'  => $legalEntityId,
                'party_id'         => $party->id,
                'reference_number' => $data['reference_number'] ?? null,
            ], [
                [
                    'account_id'   => $cashBankAccount->id,
                    'debit_amount' => $amount,
                ],
                [
                    'account_id'    => $arAccount->id,
                    'credit_amount' => $amount,
                    'party_id'      => $party->id,
                ],
            ]);

            // 2. Collection kaydı
            $collection = Collection::create([
                'user_id'          => $userId,
                'party_id'         => $party->id,
                'legal_entity_id'  => $legalEntityId,
                'journal_entry_id' => $journal->id,
                'account_id'       => $cashBankAccount->id,
                'collection_date'  => $data['collection_date'],
                'amount'           => $amount,
                'currency_code'    => $data['currency_code'] ?? 'TRY',
                'exchange_rate'    => $data['exchange_rate'] ?? 1.0,
                'payment_method'   => $data['payment_method'] ?? 'bank',
                'reference_number' => $data['reference_number'] ?? null,
                'status'           => 'posted',
                'description'      => $data['description'] ?? null,
                'source_key'       => $sourceKey,
            ]);

            // 3. Cari Defteri: credit (alacak azalır)
            $this->ledgerService->postCollection($party, $amount, [
                'source_type'     => 'collection',
                'source_key'      => 'collection_' . $collection->id,
                'document_type'   => 'collection',
                'document_date'   => $data['collection_date'],
                'description'     => $data['description'] ?? 'Tahsilat',
                'legal_entity_id' => $legalEntityId,
            ]);

            return $collection;
        });
    }

    /**
     * Tahsilatı alacak faturalarına dağıt (kısmi/tam kapatma).
     *
     * @param Collection $collection
     * @param array<array{receivable_id:int, amount:float}> $allocations
     */
    public function allocateCollection(Collection $collection, array $allocations): void
    {
        // Bloklayıcı 6: sadece posted tahsilat dağıtılabilir
        if ($collection->status !== 'posted') {
            throw new InvalidArgumentException('Sadece "kayıtlı" (posted) tahsilat dağıtılabilir.');
        }

        DB::transaction(function () use ($collection, $allocations) {
            $totalAllocating = array_sum(array_column($allocations, 'amount'));
            $alreadyAllocated = (float) $collection->allocations()->sum('amount');
            $remaining = (float) $collection->amount - $alreadyAllocated;

            if ($totalAllocating > $remaining + 0.005) {
                throw new InvalidArgumentException(sprintf(
                    'Dağıtılacak tutar (%.2f) tahsilatın kalan tutarından (%.2f) büyük olamaz.',
                    $totalAllocating,
                    $remaining
                ));
            }

            foreach ($allocations as $item) {
                $allocAmount = (float) $item['amount'];

                // Bloklayıcı 6: sıfır/negatif tutar artık exception (sessiz skip değil)
                if ($allocAmount <= 0) {
                    throw new InvalidArgumentException('Dağıtım tutarı sıfırdan büyük olmalıdır.');
                }

                $receivable = Receivable::where('user_id', $collection->user_id)
                    ->findOrFail((int) $item['receivable_id']);

                if ((int) $receivable->party_id !== (int) $collection->party_id) {
                    throw new InvalidArgumentException('Tahsilat yalnızca aynı carinin faturasına dağıtılabilir.');
                }

                // Bloklayıcı 6: receivable açık/kısmi ödenmiş olmalı
                if (!in_array($receivable->status, ['open', 'partially_paid'])) {
                    throw new InvalidArgumentException('Sadece açık veya kısmi ödenmiş faturaya dağıtım yapılabilir.');
                }

                // Bloklayıcı 3: Legal entity exact-match
                $this->assertLegalEntityMatch(
                    $collection->legal_entity_id,
                    $receivable->legal_entity_id,
                    'Tahsilat ve fatura farklı yasal birliklere (legal entity) ait; dağıtım yapılamaz.'
                );

                $invoiceRemaining = $receivable->remainingAmount();
                if ($allocAmount > $invoiceRemaining + 0.005) {
                    throw new InvalidArgumentException(sprintf(
                        'Dağıtılan tutar (%.2f) faturanın kalan bakiyesinden (%.2f) büyük olamaz.',
                        $allocAmount,
                        $invoiceRemaining
                    ));
                }

                ReceivableAllocation::create([
                    'user_id'       => $collection->user_id,
                    'receivable_id' => $receivable->id,
                    'collection_id' => $collection->id,
                    'amount'        => $allocAmount,
                ]);

                $receivable->paid_amount = (float) $receivable->paid_amount + $allocAmount;
                $receivable->status = $receivable->remainingAmount() < 0.005 ? 'paid' : 'partially_paid';
                $receivable->save();
            }
        });
    }

    /**
     * Tahsilatı iptal et (void).
     * Tüm allocations geri alınır, GL fişi void'lanır, status güncellenir.
     */
    public function voidCollection(Collection $collection, ?string $reason = null): void
    {
        if ($collection->status === 'voided') {
            throw new InvalidArgumentException('Bu tahsilat zaten iptal edilmiş.');
        }

        DB::transaction(function () use ($collection, $reason) {
            // 1. Allocation'ları geri al (receivable paid_amount düş)
            foreach ($collection->allocations as $alloc) {
                $receivable = Receivable::find($alloc->receivable_id);
                if ($receivable) {
                    $receivable->paid_amount = max(0.0, (float) $receivable->paid_amount - (float) $alloc->amount);
                    $receivable->status = $receivable->paid_amount < 0.005 ? 'open' : 'partially_paid';
                    $receivable->save();
                }
                $alloc->delete();
            }

            // 2. GL fişini void'la
            if ($collection->journal_entry_id) {
                $this->journalService->voidEntry($collection->journalEntry, $reason ?? 'Tahsilat iptal');
            }

            // 3. Cari defterinde ters kayıt
            // Bloklayıcı 4: legal_entity_id ters kayda da taşınmalı
            $party = $collection->party;
            if ($party) {
                $this->ledgerService->postReceivable($party, (float) $collection->amount, [
                    'source_type'     => 'collection_void',
                    'source_key'      => 'collection_void_' . $collection->id,
                    'document_type'   => 'collection_void',
                    'document_date'   => now()->toDateString(),
                    'description'     => 'Tahsilat İptal: ' . ($reason ?? ''),
                    'legal_entity_id' => $collection->legal_entity_id,  // Bloklayıcı 4 fix
                ]);
            }

            // 4. Durum güncelle
            $collection->update(['status' => 'voided']);
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ÖDEME (Payment)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Tedarikçi ödemesi kaydet.
     *
     * GL: Borç Satıcılar (320) ↔ Alacak Kasa/Banka (102/100)
     * Cari Defteri: debit (borç azalır / bakiye artar tedarikçi tarafından bakılınca)
     *
     * @param array{
     *   user_id:int,
     *   party_id:int,
     *   account_id:int,           Kasa veya banka GL hesabı
     *   amount:float,
     *   payment_date:string,
     *   payment_method:string,
     *   description?:string|null,
     *   legal_entity_id?:int|null,
     *   reference_number?:string|null,
     *   source_key?:string|null,  Idempotency anahtarı
     * } $data
     */
    public function recordPayment(array $data): Payment
    {
        $userId    = (int) $data['user_id'];
        $amount    = (float) $data['amount'];
        $sourceKey = $data['source_key'] ?? null;

        if ($amount <= 0) {
            throw new InvalidArgumentException('Ödeme tutarı sıfırdan büyük olmalıdır.');
        }

        // Bloklayıcı 5: Idempotency — aynı source_key varsa mevcut kaydı dön
        if ($sourceKey !== null) {
            $existing = Payment::where('user_id', $userId)
                ->where('source_key', $sourceKey)
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($data, $userId, $amount, $sourceKey) {
            $party = $this->resolveTenantParty($userId, (int) $data['party_id']);

            // Bloklayıcı 2: Hesap tipi guard — sadece aktif kasa/banka hesabı
            $cashBankAccount = $this->resolveCashBankAccount($userId, (int) $data['account_id']);

            $apAccount = Account::where('user_id', $userId)
                ->where('is_ap_account', true)
                ->first();

            if (!$apAccount) {
                throw new InvalidArgumentException('Satıcılar hesabı (320) bulunamadı. Hesap planını kontrol edin.');
            }

            $legalEntityId = $data['legal_entity_id'] ?? null;

            // 1. GL Fişi: Borç Satıcılar – Alacak Kasa/Banka
            $journal = $this->journalService->postManual([
                'user_id'          => $userId,
                'entry_date'       => $data['payment_date'],
                'entry_type'       => 'payment',
                'description'      => $data['description'] ?? 'Ödeme Kaydı',
                'currency_code'    => $data['currency_code'] ?? 'TRY',
                'exchange_rate'    => $data['exchange_rate'] ?? 1.0,
                'legal_entity_id'  => $legalEntityId,
                'party_id'         => $party->id,
                'reference_number' => $data['reference_number'] ?? null,
            ], [
                [
                    'account_id'   => $apAccount->id,
                    'debit_amount' => $amount,
                    'party_id'     => $party->id,
                ],
                [
                    'account_id'    => $cashBankAccount->id,
                    'credit_amount' => $amount,
                ],
            ]);

            // 2. Payment kaydı
            $payment = Payment::create([
                'user_id'          => $userId,
                'party_id'         => $party->id,
                'legal_entity_id'  => $legalEntityId,
                'journal_entry_id' => $journal->id,
                'account_id'       => $cashBankAccount->id,
                'payment_date'     => $data['payment_date'],
                'amount'           => $amount,
                'currency_code'    => $data['currency_code'] ?? 'TRY',
                'exchange_rate'    => $data['exchange_rate'] ?? 1.0,
                'payment_method'   => $data['payment_method'] ?? 'bank',
                'reference_number' => $data['reference_number'] ?? null,
                'status'           => 'posted',
                'description'      => $data['description'] ?? null,
                'source_key'       => $sourceKey,
            ]);

            // 3. Cari Defteri: debit (borç azalır)
            $this->ledgerService->postPayment($party, $amount, [
                'source_type'     => 'payment',
                'source_key'      => 'payment_' . $payment->id,
                'document_type'   => 'payment',
                'document_date'   => $data['payment_date'],
                'description'     => $data['description'] ?? 'Ödeme',
                'legal_entity_id' => $legalEntityId,
            ]);

            return $payment;
        });
    }

    /**
     * Ödemeyi borç faturalarına dağıt (kısmi/tam kapatma).
     *
     * @param Payment $payment
     * @param array<array{payable_id:int, amount:float}> $allocations
     */
    public function allocatePayment(Payment $payment, array $allocations): void
    {
        // Bloklayıcı 6: sadece posted ödeme dağıtılabilir
        if ($payment->status !== 'posted') {
            throw new InvalidArgumentException('Sadece "kayıtlı" (posted) ödeme dağıtılabilir.');
        }

        DB::transaction(function () use ($payment, $allocations) {
            $totalAllocating = array_sum(array_column($allocations, 'amount'));
            $alreadyAllocated = (float) $payment->allocations()->sum('amount');
            $remaining = (float) $payment->amount - $alreadyAllocated;

            if ($totalAllocating > $remaining + 0.005) {
                throw new InvalidArgumentException(sprintf(
                    'Dağıtılacak tutar (%.2f) ödemenin kalan tutarından (%.2f) büyük olamaz.',
                    $totalAllocating,
                    $remaining
                ));
            }

            foreach ($allocations as $item) {
                $allocAmount = (float) $item['amount'];

                // Bloklayıcı 6: sıfır/negatif tutar artık exception
                if ($allocAmount <= 0) {
                    throw new InvalidArgumentException('Dağıtım tutarı sıfırdan büyük olmalıdır.');
                }

                $payable = Payable::where('user_id', $payment->user_id)
                    ->findOrFail((int) $item['payable_id']);

                if ((int) $payable->party_id !== (int) $payment->party_id) {
                    throw new InvalidArgumentException('Ödeme yalnızca aynı carinin faturasına dağıtılabilir.');
                }

                // Bloklayıcı 6: payable açık/kısmi ödenmiş olmalı
                if (!in_array($payable->status, ['open', 'partially_paid'])) {
                    throw new InvalidArgumentException('Sadece açık veya kısmi ödenmiş faturaya dağıtım yapılabilir.');
                }

                // Bloklayıcı 3: Legal entity exact-match
                $this->assertLegalEntityMatch(
                    $payment->legal_entity_id,
                    $payable->legal_entity_id,
                    'Ödeme ve fatura farklı yasal birliklere (legal entity) ait; dağıtım yapılamaz.'
                );

                $invoiceRemaining = $payable->remainingAmount();
                if ($allocAmount > $invoiceRemaining + 0.005) {
                    throw new InvalidArgumentException(sprintf(
                        'Dağıtılan tutar (%.2f) faturanın kalan bakiyesinden (%.2f) büyük olamaz.',
                        $allocAmount,
                        $invoiceRemaining
                    ));
                }

                PayableAllocation::create([
                    'user_id'    => $payment->user_id,
                    'payable_id' => $payable->id,
                    'payment_id' => $payment->id,
                    'amount'     => $allocAmount,
                ]);

                $payable->paid_amount = (float) $payable->paid_amount + $allocAmount;
                $payable->status = $payable->remainingAmount() < 0.005 ? 'paid' : 'partially_paid';
                $payable->save();
            }
        });
    }

    /**
     * Ödemeyi iptal et (void).
     */
    public function voidPayment(Payment $payment, ?string $reason = null): void
    {
        if ($payment->status === 'voided') {
            throw new InvalidArgumentException('Bu ödeme zaten iptal edilmiş.');
        }

        DB::transaction(function () use ($payment, $reason) {
            // 1. Allocation'ları geri al (payable paid_amount düş)
            foreach ($payment->allocations as $alloc) {
                $payable = Payable::find($alloc->payable_id);
                if ($payable) {
                    $payable->paid_amount = max(0.0, (float) $payable->paid_amount - (float) $alloc->amount);
                    $payable->status = $payable->paid_amount < 0.005 ? 'open' : 'partially_paid';
                    $payable->save();
                }
                $alloc->delete();
            }

            // 2. GL fişini void'la
            if ($payment->journal_entry_id) {
                $this->journalService->voidEntry($payment->journalEntry, $reason ?? 'Ödeme iptal');
            }

            // 3. Cari defterinde ters kayıt (borç tekrar açılır)
            // Bloklayıcı 4: legal_entity_id ters kayda da taşınmalı
            $party = $payment->party;
            if ($party) {
                $this->ledgerService->postPayable($party, (float) $payment->amount, [
                    'source_type'     => 'payment_void',
                    'source_key'      => 'payment_void_' . $payment->id,
                    'document_type'   => 'payment_void',
                    'document_date'   => now()->toDateString(),
                    'description'     => 'Ödeme İptal: ' . ($reason ?? ''),
                    'legal_entity_id' => $payment->legal_entity_id,  // Bloklayıcı 4 fix
                ]);
            }

            // 4. Durum güncelle
            $payment->update(['status' => 'voided']);
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // YARDIMCI METODLAR
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Kullanıcıya ait kasa/banka hesaplarını listele.
     *
     * @return \Illuminate\Database\Eloquent\Collection<Account>
     */
    public function getCashBankAccounts(int $userId)
    {
        return Account::where('user_id', $userId)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('is_cash_account', true)->orWhere('is_bank_account', true);
            })
            ->orderBy('code')
            ->get();
    }

    /**
     * Belirli bir carinin açık alacak faturalarını listele.
     *
     * @return \Illuminate\Database\Eloquent\Collection<Receivable>
     */
    public function getOpenReceivables(int $userId, int $partyId)
    {
        return Receivable::where('user_id', $userId)
            ->where('party_id', $partyId)
            ->whereIn('status', ['open', 'partially_paid'])
            ->orderBy('document_date')
            ->get();
    }

    /**
     * Belirli bir carinin açık borç faturalarını listele.
     *
     * @return \Illuminate\Database\Eloquent\Collection<Payable>
     */
    public function getOpenPayables(int $userId, int $partyId)
    {
        return Payable::where('user_id', $userId)
            ->where('party_id', $partyId)
            ->whereIn('status', ['open', 'partially_paid'])
            ->orderBy('document_date')
            ->get();
    }

    /**
     * Tenant izolasyonu: party user_id doğrulaması.
     */
    protected function resolveTenantParty(int $userId, int $partyId): Party
    {
        $party = Party::findOrFail($partyId);
        if ((int) $party->user_id !== $userId) {
            throw new InvalidArgumentException('Bu cari bu kullanıcıya ait değil; user izolasyonu ihlali.');
        }
        return $party;
    }

    /**
     * Bloklayıcı 2: Hesap tipi guard.
     * Sadece aktif kasa veya banka GL hesabı kullanılabilir.
     */
    protected function resolveCashBankAccount(int $userId, int $accountId): Account
    {
        $account = Account::where('user_id', $userId)->findOrFail($accountId);

        if (!$account->is_active) {
            throw new InvalidArgumentException('Seçili hesap pasif durumda; işlem yapılamaz.');
        }

        if (!$account->is_cash_account && !$account->is_bank_account) {
            throw new InvalidArgumentException(
                sprintf(
                    '"%s (%s)" bir kasa veya banka hesabı değil. Tahsilat/ödeme yalnızca kasa/banka hesapları üzerinden yapılabilir.',
                    $account->name,
                    $account->code
                )
            );
        }

        return $account;
    }

    /**
     * Bloklayıcı 3: Legal entity exact-match kontrolü.
     * Her ikisi null ise kabul; biri null diğeri doluysa red; farklı ID ise red.
     */
    protected function assertLegalEntityMatch(
        ?int $txLegalEntityId,
        ?int $docLegalEntityId,
        string $errorMessage
    ): void {
        if ($txLegalEntityId === null && $docLegalEntityId === null) {
            return; // İkisi de null — kabul
        }

        if ($txLegalEntityId !== $docLegalEntityId) {
            throw new InvalidArgumentException($errorMessage);
        }
    }
}
