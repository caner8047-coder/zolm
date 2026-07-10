<?php

namespace App\Services\Accounting;

use App\Models\Collection;
use App\Models\Party;
use App\Models\PosSale;
use App\Models\PosShift;
use App\Models\PosTerminal;
use App\Models\SalesOrder;
use App\Services\Accounting\OutstandingInvoiceService;
use App\Services\Accounting\TradeService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Hızlı Satış (POS) ve Kasa Vardiya Yönetim Servisi.
 *
 * Sorumluluklar:
 * 1. Vardiya Açılış (openShift) ve Kapanış (closeShift) işlemleri.
 * 2. Hızlı POS Satışı Yapma (recordPosSale):
 *    - Satış Siparişi (SalesOrder) taslağı oluştur.
 *    - Siparişi onayla (Stok düşer, fatura alacağı açılır, GL Fişi kesilir).
 *    - Ödeme tipine göre (nakit/kart) anında Tahsilat (Collection) al ve faturayı kapat.
 *    - POS Satışı (PosSale) kaydı aç.
 */
class PosService
{
    protected TradeService $tradeService;
    protected OutstandingInvoiceService $invoiceService;

    public function __construct(TradeService $tradeService, OutstandingInvoiceService $invoiceService)
    {
        $this->tradeService = $tradeService;
        $this->invoiceService = $invoiceService;
    }

    /**
     * POS Vardiyası Aç.
     */
    public function openShift(PosTerminal $terminal, float $openingBalance): PosShift
    {
        $userId = $terminal->user_id;

        // Halihazırda açık bir vardiya var mı?
        $existing = PosShift::where('user_id', $userId)
            ->where('pos_terminal_id', $terminal->id)
            ->where('status', 'open')
            ->first();

        if ($existing) {
            throw new InvalidArgumentException('Bu terminalde halihazırda açık bir vardiya bulunuyor.');
        }

        return PosShift::create([
            'user_id'         => $userId,
            'pos_terminal_id' => $terminal->id,
            'opened_at'       => now(),
            'opening_balance' => $openingBalance,
            'status'          => 'open',
        ]);
    }

    /**
     * POS Vardiyası Kapat.
     */
    public function closeShift(PosShift $shift, float $closingBalance): PosShift
    {
        if ($shift->status !== 'open') {
            throw new InvalidArgumentException('Sadece açık vardiyalar kapatılabilir.');
        }

        $shift->update([
            'closed_at'       => now(),
            'closing_balance' => $closingBalance,
            'status'          => 'closed',
        ]);

        return $shift->fresh();
    }

    /**
     * Hızlı POS Satışı Yap.
     * Bu işlem anında faturayı kapatır (tahsil eder).
     *
     * @param array{
     *     payment_method: string, // cash, credit_card
     *     party_id?: int|null,     // Belirtilmezse perakende cari seçilir
     *     legal_entity_id?: int|null,
     * } $header
     * @param array $items Cart items
     */
    public function recordPosSale(PosShift $shift, array $header, array $items): PosSale
    {
        if ($shift->status !== 'open') {
            throw new InvalidArgumentException('Satış yapmak için açık bir vardiya olmalıdır.');
        }

        return DB::transaction(function () use ($shift, $header, $items) {
            $userId = $shift->user_id;

            // 1. Perakende/Walk-in Müşteri Carisini bul veya oluştur
            $partyId = $header['party_id'] ?? null;
            if (!$partyId) {
                $party = Party::where('user_id', $userId)->where('display_name', 'Perakende Müşteri')->first();
                if (!$party) {
                    $party = Party::create([
                        'user_id'      => $userId,
                        'display_name' => 'Perakende Müşteri',
                        'party_type'   => 'person',
                        'status'       => 'active',
                    ]);
                }
                $partyId = $party->id;
            }

            // Döküman numarası üret
            $docNum = 'POS-' . $shift->pos_terminal_id . '-' . time() . '-' . rand(100, 999);

            // 2. Satış Siparişi oluştur (Draft)
            $salesOrder = $this->tradeService->createSalesOrder([
                'user_id'          => $userId,
                'party_id'         => $partyId,
                'legal_entity_id'  => $header['legal_entity_id'] ?? null,
                'document_number'  => $docNum,
                'order_date'       => now()->toDateString(),
                'currency_code'    => 'TRY',
                'exchange_rate'    => 1.0,
                'description'      => 'Hızlı POS Satışı',
            ], $items);

            // 3. Siparişi Onayla (Stok düşer, Cari Alacak (Receivable) oluşur)
            $this->tradeService->approveSalesOrder($salesOrder);

            // 4. Anında Tahsilat (Collection) yap (Çünkü POS check-out anında tahsil edilir)
            $collection = $this->invoiceService->recordCollection([
                'user_id'         => $userId,
                'party_id'        => $partyId,
                'legal_entity_id' => $header['legal_entity_id'] ?? null,
                'amount'          => (float) $salesOrder->total_amount,
                'collection_date' => now()->toDateString(),
                'payment_method'  => $header['payment_method'],
                'description'     => 'POS Fiş Tahsilatı: ' . $docNum,
            ]);

            // 5. Tahsilatı Alacak faturasına dağıt (Fatura paid olarak kapanır)
            $this->invoiceService->allocateCollection($collection, [
                [
                    'receivable_id' => $salesOrder->receivable_id,
                    'amount'        => (float) $salesOrder->total_amount,
                ]
            ]);

            // 6. POS Satış Kaydı oluştur
            return PosSale::create([
                'user_id'        => $userId,
                'pos_shift_id'   => $shift->id,
                'sales_order_id' => $salesOrder->id,
                'payment_method' => $header['payment_method'],
                'amount'         => (float) $salesOrder->total_amount,
            ]);
        });
    }
}
