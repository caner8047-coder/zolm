<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\ChannelOrder;
use App\Models\OrderFinancialEvent;
use App\Models\Party;
use App\Models\SalesOrder;
use App\Services\Crm\PartyIdentityResolver;
use App\Services\Accounting\TradeService;
use App\Services\Accounting\JournalService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Pazaryeri Finans Köprüsü (Marketplace Finance Bridge) Servisi.
 *
 * Sorumluluklar:
 * 1. ChannelOrder (Pazaryeri Siparişi) kaydını Party ve Cari eşleşmesine bağlama.
 * 2. Siparişten otomatik draft/approved Satış Belgesi (SalesOrder) türetme.
 * 3. Hakediş ve masraf (komisyon, kargo, ceza, kampanya) finans olaylarını (OrderFinancialEvent) Genel Muhasebeye (GL) işleme.
 */
class MarketplaceFinanceBridgeService
{
    protected PartyIdentityResolver $identityResolver;
    protected TradeService $tradeService;
    protected JournalService $journalService;

    public function __construct(
        PartyIdentityResolver $identityResolver,
        TradeService $tradeService,
        JournalService $journalService
    ) {
        $this->identityResolver = $identityResolver;
        $this->tradeService = $tradeService;
        $this->journalService = $journalService;
    }

    /**
     * Pazaryeri Siparişini Cari/Stok/Muhasebe Sürecine Köprüle.
     */
    public function bridgeOrder(ChannelOrder $order, bool $autoApprove = true): SalesOrder
    {
        return DB::transaction(function () use ($order, $autoApprove) {
            $userId = (int) $order->store->user_id;

            // 1. Müşteriyi (Party) çözümle/eşleştir
            $party = $this->identityResolver->resolve([
                'user_id' => $userId,
                'email'   => $order->customer_email,
                'phone'   => $order->customer_phone,
                'name'    => $order->customer_name,
                'source'  => 'marketplace',
                'store_id'=> $order->store_id,
            ]);

            if (!$party) {
                // Fallback: Default/Unknown Party
                $party = Party::where('user_id', $userId)->where('status', 'active')->first();
                if (!$party) {
                    $party = Party::create([
                        'user_id'      => $userId,
                        'display_name' => 'Pazaryeri Müşterisi (Genel)',
                        'party_type'   => 'unknown',
                        'status'       => 'active',
                    ]);
                }
            }

            // Ensure customer role exists on the party
            if (!$party->roles()->where('role', 'customer')->exists()) {
                $party->roles()->create([
                    'user_id' => $userId,
                    'role'    => 'customer',
                ]);
            }

            // Siparişteki items bilgilerinden satırları hazırla
            $items = [];
            foreach ($order->items as $item) {
                $items[] = [
                    'stock_code' => $item->stock_code,
                    'quantity'   => (int) $item->quantity,
                    'unit_price' => (float) ($item->unit_price ?? $item->gross_amount),
                    'vat_rate'   => (float) ($item->vat_rate ?? 20.00),
                ];
            }

            // 2. Satış Siparişi oluştur
            $salesOrder = $this->tradeService->createSalesOrder([
                'user_id'          => $userId,
                'party_id'         => $party->id,
                'legal_entity_id'  => $order->legal_entity_id,
                'document_number'  => $order->order_number,
                'order_date'       => $order->ordered_at ? $order->ordered_at->toDateString() : now()->toDateString(),
                'currency_code'    => $order->currency ?? 'TRY',
                'exchange_rate'    => (float) ($order->exchange_rate ?? 1.0),
                'description'      => 'Pazaryeri Siparişi: #' . $order->order_number,
            ], $items);

            // 3. Otomatik Onayla (Stok düşer, Cari Alacak açılır, GL Fişi kesilir)
            if ($autoApprove && count($items) > 0) {
                $this->tradeService->approveSalesOrder($salesOrder);
            }

            return $salesOrder;
        });
    }

    /**
     * Pazaryeri Finansal Olayını (Hakediş/Masraf) Genel Muhasebeye Fiş Olarak İşle.
     *
     * Örnek Eşleşme Mantığı:
     * - "commission" (Komisyon): Borç 760 (Pazarlama/Satış Giderleri) - Alacak 120 (Alıcılar)
     * - "shipping_fee" (Kargo Masrafı): Borç 760 (Pazarlama/Satış Giderleri) - Alacak 120 (Alıcılar)
     * - "payout" (Banka Hakediş Ödemesi): Borç 102 (Bankalar) - Alacak 120 (Alıcılar)
     */
    public function bridgeFinancialEvent(OrderFinancialEvent $event): ?\App\Models\JournalEntry
    {
        $userId = $event->legalEntity ? (int) $event->legalEntity->user_id : 0;
        if ($userId === 0 && $event->store) {
            $userId = (int) $event->store->user_id;
        }

        if ($userId === 0) {
            throw new InvalidArgumentException('Finansal olay için kullanıcı (user_id) tespit edilemedi.');
        }

        $amount = (float) $event->amount;
        if ($amount <= 0) {
            return null;
        }

        $eventType = strtolower($event->event_type);
        $sourceKey = 'fin-event-' . $event->id;

        return DB::transaction(function () use ($userId, $amount, $eventType, $sourceKey, $event) {
            $arAccount = Account::where('user_id', $userId)->where('is_ar_account', true)->first();
            $bankAccount = Account::where('user_id', $userId)->where('is_bank_account', true)->first();
            $expenseAccount = Account::where('user_id', $userId)->where('code', '760')->first(); // 760 Pazarlama

            if (!$arAccount || !$bankAccount || !$expenseAccount) {
                throw new InvalidArgumentException('Finansal olay köprülemesi için gerekli hesaplar (102/120/760) bulunamadı.');
            }

            $partyId = null;
            if ($event->order) {
                // Siparişe bağlı party'yi bulmaya çalışalım
                $salesOrder = SalesOrder::where('user_id', $userId)->where('document_number', $event->order->order_number)->first();
                if ($salesOrder) {
                    $partyId = $salesOrder->party_id;
                }
            }

            switch ($eventType) {
                case 'commission':
                case 'shipping_fee':
                case 'cargo':
                    // Masraflar: Alıcı cari alacağını azaltır (credit), gideri artırır (debit)
                    return $this->journalService->postManual([
                        'user_id'         => $userId,
                        'entry_date'      => $event->event_date->toDateString(),
                        'entry_type'      => 'adjustment',
                        'description'     => 'Pazaryeri Masrafı: ' . $event->event_type,
                        'currency_code'   => $event->currency ?? 'TRY',
                        'exchange_rate'   => 1.0,
                        'source_type'     => 'financial_event',
                        'source_id'       => $event->id,
                        'source_key'      => $sourceKey,
                    ], [
                        [
                            'account_id'   => $expenseAccount->id,
                            'debit_amount' => $amount,
                        ],
                        [
                            'account_id'    => $arAccount->id,
                            'credit_amount' => $amount,
                            'party_id'      => $partyId,
                        ]
                    ]);

                case 'payout':
                case 'settlement':
                    // Banka Hakediş Payout: Bankaya girer (debit), Alıcı carisini kapatır (credit)
                    return $this->journalService->postManual([
                        'user_id'         => $userId,
                        'entry_date'      => $event->event_date->toDateString(),
                        'entry_type'      => 'collection',
                        'description'     => 'Pazaryeri Hakediş Ödemesi (Payout)',
                        'currency_code'   => $event->currency ?? 'TRY',
                        'exchange_rate'   => 1.0,
                        'source_type'     => 'financial_event',
                        'source_id'       => $event->id,
                        'source_key'      => $sourceKey,
                    ], [
                        [
                            'account_id'   => $bankAccount->id,
                            'debit_amount' => $amount,
                        ],
                        [
                            'account_id'    => $arAccount->id,
                            'credit_amount' => $amount,
                            'party_id'      => $partyId,
                        ]
                    ]);

                default:
                    return null;
            }
        });
    }
}
