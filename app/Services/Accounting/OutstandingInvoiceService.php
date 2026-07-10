<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\Collection;
use App\Models\Payable;
use App\Models\PayableAllocation;
use App\Models\Payment;
use App\Models\Receivable;
use App\Models\ReceivableAllocation;
use App\Services\Accounting\JournalService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Cari Açık İşlem, Fatura Alacak/Borç Takip ve Tahsilat/Ödeme Kapatma Servisi.
 *
 * Sorumluluklar:
 * 1. Receivable (Fatura/Alacak) ve Payable (Fatura/Borç) oluşturma.
 * 2. Collection (Tahsilat) ve Payment (Ödeme) kaydetme.
 * 3. Alınan ödemeleri faturalara kısmi veya tam dağıtarak (allocate) borç kapatma.
 * 4. Tüm hareketler için otomatik Genel Muhasebe (General Ledger / JournalEntry) kaydı üretme.
 */
class OutstandingInvoiceService
{
    protected JournalService $journalService;

    public function __construct(JournalService $journalService)
    {
        $this->journalService = $journalService;
    }

    /**
     * Fatura Alacak Kaydı (Receivable) Oluştur.
     * Genel Muhasebe: Borç 120 (Alıcılar) - Alacak 600 (Satışlar)
     */
    public function createReceivable(array $data): Receivable
    {
        $userId = (int) $data['user_id'];
        $amount = (float) $data['amount'];
        if ($amount <= 0) {
            throw new InvalidArgumentException('Tutar 0 veya daha küçük olamaz.');
        }

        $this->validateTenantOwnership($userId, (int) $data['party_id'], isset($data['legal_entity_id']) ? (int) $data['legal_entity_id'] : null);

        return DB::transaction(function () use ($data, $userId, $amount) {
            // Eşleşecek hesapları bul
            $arAccount = Account::where('user_id', $userId)->where('is_ar_account', true)->first();
            $salesAccount = Account::where('user_id', $userId)->where('code', '600')->first();

            if (!$arAccount || !$salesAccount) {
                throw new InvalidArgumentException('Gerekli sistem hesapları (120/600) bulunamadı. Lütfen hesap planını seede edin.');
            }

            // 1. Journal Entry (Çift taraflı fiş) oluştur
            $journal = $this->journalService->postManual([
                'user_id'          => $userId,
                'entry_date'       => $data['document_date'],
                'entry_type'       => 'sales_invoice',
                'description'      => $data['description'] ?? 'Fatura Alacak Kaydı',
                'currency_code'    => $data['currency_code'] ?? 'TRY',
                'exchange_rate'    => $data['exchange_rate'] ?? 1.0,
                'reference_number' => $data['document_number'] ?? null,
                'legal_entity_id'  => $data['legal_entity_id'] ?? null,
                'party_id'         => $data['party_id'],
            ], [
                [
                    'account_id'   => $arAccount->id,
                    'debit_amount' => $amount,
                    'party_id'     => $data['party_id'],
                ],
                [
                    'account_id'    => $salesAccount->id,
                    'credit_amount' => $amount,
                ]
            ]);

            // 2. Alacak faturası kaydını aç
            return Receivable::create([
                'user_id'          => $userId,
                'party_id'         => $data['party_id'],
                'legal_entity_id'  => $data['legal_entity_id'] ?? null,
                'journal_entry_id' => $journal->id,
                'document_number'  => $data['document_number'] ?? null,
                'document_date'    => $data['document_date'],
                'due_date'         => $data['due_date'] ?? null,
                'amount'           => $amount,
                'currency_code'    => $data['currency_code'] ?? 'TRY',
                'exchange_rate'    => $data['exchange_rate'] ?? 1.0,
                'status'           => 'open',
                'description'      => $data['description'] ?? null,
            ]);
        });
    }

    /**
     * Fatura Borç Kaydı (Payable) Oluştur.
     * Genel Muhasebe: Borç 770 (Giderler) - Alacak 320 (Satıcılar)
     */
    public function createPayable(array $data): Payable
    {
        $userId = (int) $data['user_id'];
        $amount = (float) $data['amount'];
        if ($amount <= 0) {
            throw new InvalidArgumentException('Tutar 0 veya daha küçük olamaz.');
        }

        $this->validateTenantOwnership($userId, (int) $data['party_id'], isset($data['legal_entity_id']) ? (int) $data['legal_entity_id'] : null);

        return DB::transaction(function () use ($data, $userId, $amount) {
            $apAccount = Account::where('user_id', $userId)->where('is_ap_account', true)->first();
            $expenseAccount = Account::where('user_id', $userId)->where('code', '770')->first();

            if (!$apAccount || !$expenseAccount) {
                throw new InvalidArgumentException('Gerekli sistem hesapları (320/770) bulunamadı. Lütfen hesap planını seede edin.');
            }

            // 1. Journal Entry oluştur
            $journal = $this->journalService->postManual([
                'user_id'          => $userId,
                'entry_date'       => $data['document_date'],
                'entry_type'       => 'purchase_invoice',
                'description'      => $data['description'] ?? 'Alış Faturası Borç Kaydı',
                'currency_code'    => $data['currency_code'] ?? 'TRY',
                'exchange_rate'    => $data['exchange_rate'] ?? 1.0,
                'reference_number' => $data['document_number'] ?? null,
                'legal_entity_id'  => $data['legal_entity_id'] ?? null,
                'party_id'         => $data['party_id'],
            ], [
                [
                    'account_id'   => $expenseAccount->id,
                    'debit_amount' => $amount,
                ],
                [
                    'account_id'    => $apAccount->id,
                    'credit_amount' => $amount,
                    'party_id'      => $data['party_id'],
                ]
            ]);

            // 2. Borç faturası kaydını aç
            return Payable::create([
                'user_id'          => $userId,
                'party_id'         => $data['party_id'],
                'legal_entity_id'  => $data['legal_entity_id'] ?? null,
                'journal_entry_id' => $journal->id,
                'document_number'  => $data['document_number'] ?? null,
                'document_date'    => $data['document_date'],
                'due_date'         => $data['due_date'] ?? null,
                'amount'           => $amount,
                'currency_code'    => $data['currency_code'] ?? 'TRY',
                'exchange_rate'    => $data['exchange_rate'] ?? 1.0,
                'status'           => 'open',
                'description'      => $data['description'] ?? null,
            ]);
        });
    }

    /**
     * Müşteri Tahsilatı Kaydet.
     * Genel Muhasebe: Borç 102 (Bankalar) - Alacak 120 (Alıcılar)
     */
    public function recordCollection(array $data): Collection
    {
        $userId = (int) $data['user_id'];
        $amount = (float) $data['amount'];
        if ($amount <= 0) {
            throw new InvalidArgumentException('Tutar 0 veya daha küçük olamaz.');
        }

        $this->validateTenantOwnership($userId, (int) $data['party_id'], isset($data['legal_entity_id']) ? (int) $data['legal_entity_id'] : null);

        return DB::transaction(function () use ($data, $userId, $amount) {
            $bankAccount = Account::where('user_id', $userId)->where('is_bank_account', true)->first();
            $arAccount = Account::where('user_id', $userId)->where('is_ar_account', true)->first();

            if (!$bankAccount || !$arAccount) {
                throw new InvalidArgumentException('Gerekli sistem hesapları (102/120) bulunamadı.');
            }

            // 1. Journal Entry oluştur
            $journal = $this->journalService->postManual([
                'user_id'          => $userId,
                'entry_date'       => $data['collection_date'],
                'entry_type'       => 'collection',
                'description'      => $data['description'] ?? 'Tahsilat Kaydı',
                'currency_code'    => $data['currency_code'] ?? 'TRY',
                'exchange_rate'    => $data['exchange_rate'] ?? 1.0,
                'legal_entity_id'  => $data['legal_entity_id'] ?? null,
                'party_id'         => $data['party_id'],
            ], [
                [
                    'account_id'   => $bankAccount->id,
                    'debit_amount' => $amount,
                ],
                [
                    'account_id'    => $arAccount->id,
                    'credit_amount' => $amount,
                    'party_id'      => $data['party_id'],
                ]
            ]);

            // 2. Tahsilat kaydını aç
            return Collection::create([
                'user_id'          => $userId,
                'party_id'         => $data['party_id'],
                'legal_entity_id'  => $data['legal_entity_id'] ?? null,
                'journal_entry_id' => $journal->id,
                'collection_date'  => $data['collection_date'],
                'amount'           => $amount,
                'currency_code'    => $data['currency_code'] ?? 'TRY',
                'exchange_rate'    => $data['exchange_rate'] ?? 1.0,
                'payment_method'   => $data['payment_method'] ?? 'bank',
                'status'           => 'posted',
                'description'      => $data['description'] ?? null,
            ]);
        });
    }

    /**
     * Tedarikçi Ödemesi Kaydet.
     * Genel Muhasebe: Borç 320 (Satıcılar) - Alacak 102 (Bankalar)
     */
    public function recordPayment(array $data): Payment
    {
        $userId = (int) $data['user_id'];
        $amount = (float) $data['amount'];
        if ($amount <= 0) {
            throw new InvalidArgumentException('Tutar 0 veya daha küçük olamaz.');
        }

        $this->validateTenantOwnership($userId, (int) $data['party_id'], isset($data['legal_entity_id']) ? (int) $data['legal_entity_id'] : null);

        return DB::transaction(function () use ($data, $userId, $amount) {
            $apAccount = Account::where('user_id', $userId)->where('is_ap_account', true)->first();
            $bankAccount = Account::where('user_id', $userId)->where('is_bank_account', true)->first();

            if (!$apAccount || !$bankAccount) {
                throw new InvalidArgumentException('Gerekli sistem hesapları (320/102) bulunamadı.');
            }

            // 1. Journal Entry oluştur
            $journal = $this->journalService->postManual([
                'user_id'          => $userId,
                'entry_date'       => $data['payment_date'],
                'entry_type'       => 'payment',
                'description'      => $data['description'] ?? 'Ödeme Kaydı',
                'currency_code'    => $data['currency_code'] ?? 'TRY',
                'exchange_rate'    => $data['exchange_rate'] ?? 1.0,
                'legal_entity_id'  => $data['legal_entity_id'] ?? null,
                'party_id'         => $data['party_id'],
            ], [
                [
                    'account_id'   => $apAccount->id,
                    'debit_amount' => $amount,
                    'party_id'     => $data['party_id'],
                ],
                [
                    'account_id'    => $bankAccount->id,
                    'credit_amount' => $amount,
                ]
            ]);

            // 2. Ödeme kaydını aç
            return Payment::create([
                'user_id'          => $userId,
                'party_id'         => $data['party_id'],
                'legal_entity_id'  => $data['legal_entity_id'] ?? null,
                'journal_entry_id' => $journal->id,
                'payment_date'     => $data['payment_date'],
                'amount'           => $amount,
                'currency_code'    => $data['currency_code'] ?? 'TRY',
                'exchange_rate'    => $data['exchange_rate'] ?? 1.0,
                'payment_method'   => $data['payment_method'] ?? 'bank',
                'status'           => 'posted',
                'description'      => $data['description'] ?? null,
            ]);
        });
    }

    /**
     * Tahsilatı alacak faturalarına dağıt (Kısmi / Tam kapatma).
     */
    public function allocateCollection(Collection $collection, array $allocations): void
    {
        DB::transaction(function () use ($collection, $allocations) {
            foreach ($allocations as $item) {
                $receivableId = (int) $item['receivable_id'];
                $allocAmount = (float) $item['amount'];

                if ($allocAmount <= 0) {
                    continue;
                }

                $receivable = Receivable::where('user_id', $collection->user_id)->findOrFail($receivableId);

                // Party matching check
                if ((int) $receivable->party_id !== (int) $collection->party_id) {
                    throw new InvalidArgumentException('Tahsilat sadece ilişkili olduğu party faturasına dağıtılabilir.');
                }

                // Legal Entity exact match check (both must be same, or both null)
                $receivableLE = $receivable->legal_entity_id ? (int) $receivable->legal_entity_id : null;
                $collectionLE = $collection->legal_entity_id ? (int) $collection->legal_entity_id : null;
                if ($receivableLE !== $collectionLE) {
                    throw new InvalidArgumentException('Tahsilat sadece ilişkili olduğu legal entity faturasına dağıtılabilir.');
                }

                // Alacak faturasının kalan bakiyesinden fazlasını kapatamayız
                $remaining = $receivable->remainingAmount();
                if ($allocAmount > $remaining + 0.005) {
                    throw new InvalidArgumentException(sprintf(
                        'Dağıtılan tutar (%.2f) faturanın kalan bakiyesinden (%.2f) büyük olamaz.',
                        $allocAmount,
                        $remaining
                    ));
                }

                // Allocation oluştur
                ReceivableAllocation::create([
                    'user_id'       => $collection->user_id,
                    'receivable_id' => $receivable->id,
                    'collection_id' => $collection->id,
                    'amount'        => $allocAmount,
                ]);

                // Faturanın paid_amount alanını güncelle
                $receivable->paid_amount = (float) $receivable->paid_amount + $allocAmount;

                // Durum belirlemesi
                if ($receivable->remainingAmount() < 0.005) {
                    $receivable->status = 'paid';
                } else {
                    $receivable->status = 'partially_paid';
                }
                $receivable->save();
            }
        });
    }

    /**
     * Ödemeyi borç faturalarına dağıt.
     */
    public function allocatePayment(Payment $payment, array $allocations): void
    {
        DB::transaction(function () use ($payment, $allocations) {
            foreach ($allocations as $item) {
                $payableId = (int) $item['payable_id'];
                $allocAmount = (float) $item['amount'];

                if ($allocAmount <= 0) {
                    continue;
                }

                $payable = Payable::where('user_id', $payment->user_id)->findOrFail($payableId);

                // Party matching check
                if ((int) $payable->party_id !== (int) $payment->party_id) {
                    throw new InvalidArgumentException('Ödeme sadece ilişkili olduğu party faturasına dağıtılabilir.');
                }

                // Legal Entity exact match check (both must be same, or both null)
                $payableLE = $payable->legal_entity_id ? (int) $payable->legal_entity_id : null;
                $paymentLE = $payment->legal_entity_id ? (int) $payment->legal_entity_id : null;
                if ($payableLE !== $paymentLE) {
                    throw new InvalidArgumentException('Ödeme sadece ilişkili olduğu legal entity faturasına dağıtılabilir.');
                }

                $remaining = $payable->remainingAmount();
                if ($allocAmount > $remaining + 0.005) {
                    throw new InvalidArgumentException(sprintf(
                        'Dağıtılan tutar (%.2f) faturanın kalan bakiyesinden (%.2f) büyük olamaz.',
                        $allocAmount,
                        $remaining
                    ));
                }

                // Allocation oluştur
                PayableAllocation::create([
                    'user_id'    => $payment->user_id,
                    'payable_id' => $payable->id,
                    'payment_id' => $payment->id,
                    'amount'     => $allocAmount,
                ]);

                $payable->paid_amount = (float) $payable->paid_amount + $allocAmount;

                if ($payable->remainingAmount() < 0.005) {
                    $payable->status = 'paid';
                } else {
                    $payable->status = 'partially_paid';
                }
                $payable->save();
            }
        });
    }

    /**
     * Cari Ekstre / Hareket dökümünü getiren sorgu.
     * Alacaklar (receivables) ve borçlar (payables) ile ödeme/tahsilatları tek bir
     * kronolojik listede toplar.
     */
    public function getPartyStatement(int $userId, int $partyId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $receivables = Receivable::where('user_id', $userId)
            ->where('party_id', $partyId)
            ->when($dateFrom, fn($q) => $q->where('document_date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->where('document_date', '<=', $dateTo))
            ->get()
            ->map(fn($r) => [
                'date'        => $r->document_date->toDateString(),
                'type'        => 'receivable',
                'type_label'  => 'Satış Faturası (Alacak)',
                'ref'         => $r->document_number ?? 'REC-' . $r->id,
                'debit'       => (float) $r->amount,
                'credit'      => 0.0,
                'status'      => $r->status,
                'description' => $r->description ?? 'Alacak kaydı',
            ]);

        $payables = Payable::where('user_id', $userId)
            ->where('party_id', $partyId)
            ->when($dateFrom, fn($q) => $q->where('document_date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->where('document_date', '<=', $dateTo))
            ->get()
            ->map(fn($p) => [
                'date'        => $p->document_date->toDateString(),
                'type'        => 'payable',
                'type_label'  => 'Alış Faturası (Borç)',
                'ref'         => $p->document_number ?? 'PAY-' . $p->id,
                'debit'       => 0.0,
                'credit'      => (float) $p->amount,
                'status'      => $p->status,
                'description' => $p->description ?? 'Borç kaydı',
            ]);

        $collections = Collection::where('user_id', $userId)
            ->where('party_id', $partyId)
            ->where('status', 'posted')
            ->when($dateFrom, fn($q) => $q->where('collection_date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->where('collection_date', '<=', $dateTo))
            ->get()
            ->map(fn($c) => [
                'date'        => $c->collection_date->toDateString(),
                'type'        => 'collection',
                'type_label'  => 'Tahsilat',
                'ref'         => 'COL-' . $c->id,
                'debit'       => 0.0,
                'credit'      => (float) $c->amount,
                'status'      => $c->status,
                'description' => $c->description ?? 'Tahsilat kaydı',
            ]);

        $payments = Payment::where('user_id', $userId)
            ->where('party_id', $partyId)
            ->where('status', 'posted')
            ->when($dateFrom, fn($q) => $q->where('payment_date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->where('payment_date', '<=', $dateTo))
            ->get()
            ->map(fn($p) => [
                'date'        => $p->payment_date->toDateString(),
                'type'        => 'payment',
                'type_label'  => 'Ödeme',
                'ref'         => 'PAYN-' . $p->id,
                'debit'       => (float) $p->amount,
                'credit'      => 0.0,
                'status'      => $p->status,
                'description' => $p->description ?? 'Ödeme kaydı',
            ]);

        // Hepsini birleştir ve kronolojik sırala
        $statement = $receivables->concat($payables)->concat($collections)->concat($payments)
            ->sortBy('date')
            ->values()
            ->toArray();

        // Kümülatif bakiye hesapla (debit - credit)
        $runningBalance = 0.0;
        foreach ($statement as &$row) {
            $runningBalance += ($row['debit'] - $row['credit']);
            $row['balance'] = $runningBalance;
        }

        return $statement;
    }

    /**
     * Sahiplik doğrulaması yapar. Eşleşmeyen durumlarda InvalidArgumentException fırlatır.
     */
    protected function validateTenantOwnership(int $userId, int $partyId, ?int $legalEntityId = null): void
    {
        $party = \App\Models\Party::find($partyId);
        if (!$party || (int) $party->user_id !== $userId) {
            throw new InvalidArgumentException('Belirtilen party bu kullanıcıya ait değil.');
        }

        if ($legalEntityId !== null) {
            $legalEntity = \App\Models\LegalEntity::find($legalEntityId);
            if (!$legalEntity || (int) $legalEntity->user_id !== $userId) {
                throw new InvalidArgumentException('Belirtilen legal entity bu kullanıcıya ait değil.');
            }
        }
    }
}
