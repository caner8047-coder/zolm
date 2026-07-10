<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\ChannelOrder;
use App\Models\OrderFinancialEvent;
use App\Models\Party;
use App\Models\SalesOrder;
use App\Models\LegalEntity;
use App\Models\Warehouse;
use App\Models\MpProduct;
use App\Models\MarketplaceFinanceBridgeRun;
use App\Services\Crm\PartyIdentityResolver;
use App\Services\Accounting\TradeService;
use App\Services\Accounting\JournalService;
use App\Services\Accounting\PartyLedgerService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Pazaryeri Finans Köprüsü (Marketplace Finance Bridge) Servisi.
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
    public function bridgeOrder(ChannelOrder $order, bool $autoApprove = true, ?int $actorUserId = null): SalesOrder
    {
        $userId = (int) $order->store->user_id;

        // 1. Actor & Tenant & Legal Entity Guard
        $actorUserId = $actorUserId ?? auth()->id();
        if ($actorUserId !== null && (int)$actorUserId !== $userId) {
            throw new InvalidArgumentException('İşlem yapan kullanıcı ile sipariş sahibi uyuşmuyor.');
        }

        if ($order->store->user_id != $userId) {
            throw new InvalidArgumentException('Mağaza kullanıcısı uyuşmuyor.');
        }

        if ($order->legal_entity_id) {
            $legalEntity = LegalEntity::where('user_id', $userId)->active()->find($order->legal_entity_id);
            if (!$legalEntity) {
                throw new InvalidArgumentException('Geçersiz veya aktif olmayan şirket.');
            }
            if ($order->store->legal_entity_id && (int) $order->store->legal_entity_id !== (int) $order->legal_entity_id) {
                throw new InvalidArgumentException('Mağaza ve sipariş şirket eşleşmesi çakışıyor.');
            }
        }

        $sourceKey = 'marketplace_order_bridge_' . $order->id;

        // 2. Deterministic source_key ile duplicate kontrolü & Payload Drift Kontrolü
        $existingRun = MarketplaceFinanceBridgeRun::where('user_id', $userId)
            ->where('source_key', $sourceKey)
            ->where('status', 'succeeded')
            ->first();

        $existingOrder = null;
        if ($existingRun && $existingRun->target_id) {
            $existingOrder = SalesOrder::where('user_id', $userId)->find($existingRun->target_id);
        }

        if (!$existingOrder) {
            $existingOrder = SalesOrder::where('user_id', $userId)
                ->where('source_key', $sourceKey)
                ->first();
        }

        if ($existingOrder) {
            $existingOrder->loadMissing('items');
            $this->validateOrderPayloadDrift($existingOrder, $order);
            return $existingOrder;
        }

        // Run kaydı oluştur veya bul
        $run = MarketplaceFinanceBridgeRun::firstOrNew([
            'user_id'    => $userId,
            'source_key' => $sourceKey,
        ]);
        $run->fill([
            'marketplace_store_id' => $order->store_id,
            'channel_order_id'     => $order->id,
            'bridge_type'          => 'order',
            'status'               => 'processing',
            'attempted_at'         => now(),
            'payload_json'         => $order->toArray(),
        ]);
        $run->save();

        try {
            $salesOrder = DB::transaction(function () use ($order, $autoApprove, $userId, $sourceKey) {
                $party = $this->identityResolver->resolve([
                    'user_id' => $userId,
                    'email'   => $order->customer_email,
                    'phone'   => $order->customer_phone,
                    'name'    => $order->customer_name,
                    'source'  => 'marketplace',
                    'store_id'=> $order->store_id,
                ]);

                if (!$party) {
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

                if (!$party->roles()->where('role', 'customer')->exists()) {
                    $party->roles()->create([
                        'user_id' => $userId,
                        'role'    => 'customer',
                    ]);
                }

                $items = [];
                $orderItems = $order->items;
                if ($orderItems->isEmpty()) {
                    throw new InvalidArgumentException('Sipariş kalemi bulunamadı.');
                }

                foreach ($orderItems as $item) {
                    if ($item->quantity <= 0) {
                        throw new InvalidArgumentException('Miktar sıfırdan büyük olmalıdır.');
                    }
                    if (($item->unit_price ?? $item->gross_amount) < 0) {
                        throw new InvalidArgumentException('Birim fiyatı negatif olamaz.');
                    }

                    $product = MpProduct::where('user_id', $userId)
                        ->where('stock_code', $item->stock_code)
                        ->first();
                    if (!$product) {
                        throw new InvalidArgumentException(sprintf('"%s" stok kodlu ürün sistemde bulunamadı.', $item->stock_code));
                    }

                    $items[] = [
                        'stock_code' => $item->stock_code,
                        'quantity'   => (int) $item->quantity,
                        'unit_price' => (float) ($item->unit_price ?? $item->gross_amount),
                        'vat_rate'   => (float) ($item->vat_rate ?? 20.00),
                    ];
                }

                $defaultWarehouse = Warehouse::where('user_id', $userId)
                    ->where('is_active', true)
                    ->where('is_default', true)
                    ->first();
                if (!$defaultWarehouse) {
                    throw new InvalidArgumentException('Sistemde varsayılan ve aktif bir depo tanımlanmamış.');
                }

                $salesOrder = $this->tradeService->createSalesOrder([
                    'user_id'          => $userId,
                    'party_id'         => $party->id,
                    'legal_entity_id'  => $order->legal_entity_id ?: $order->store->legal_entity_id,
                    'document_number'  => $order->order_number,
                    'order_date'       => $order->ordered_at ? $order->ordered_at->toDateString() : now()->toDateString(),
                    'currency_code'    => $order->currency ?? 'TRY',
                    'exchange_rate'    => (float) ($order->exchange_rate ?? 1.0),
                    'description'      => 'Pazaryeri Siparişi: #' . $order->order_number,
                    'source_key'       => $sourceKey,
                    'warehouse_id'     => $defaultWarehouse->id,
                ], $items);

                if ($autoApprove) {
                    $this->tradeService->approveSalesOrder($salesOrder);
                }

                return $salesOrder;
            });

            $salesOrder->load('items', 'receivable');

            $run->status = 'succeeded';
            $run->target_type = 'sales_order';
            $run->target_id = $salesOrder->id;
            $run->completed_at = now();
            $run->result_json = [
                'sales_order_id'         => $salesOrder->id,
                'status'                 => $salesOrder->status,
                'approved'               => $salesOrder->status === 'approved',
                'stock_movements_count'  => $salesOrder->items->count(),
                'ledger_entry_id'        => $salesOrder->receivable ? $salesOrder->receivable->id : null,
                'journal_entry_id'       => ($salesOrder->receivable && $salesOrder->receivable->journalEntry) ? $salesOrder->receivable->journalEntry->id : null,
            ];
            $run->save();

            return $salesOrder;

        } catch (\Exception $e) {
            $run->status = 'failed';
            $run->error_message = $e->getMessage();
            $run->completed_at = now();
            $run->save();

            throw $e;
        }
    }

    /**
     * Pazaryeri Finansal Olayını (Hakediş/Masraf) Genel Muhasebeye Fiş Olarak İşle.
     */
    public function bridgeFinancialEvent(OrderFinancialEvent $event, ?int $actorUserId = null): ?\App\Models\JournalEntry
    {
        $userId = $event->legalEntity ? (int) $event->legalEntity->user_id : 0;
        if ($userId === 0 && $event->store) {
            $userId = (int) $event->store->user_id;
        }

        if ($userId === 0) {
            throw new InvalidArgumentException('Finansal olay için kullanıcı (user_id) tespit edilemedi.');
        }

        // 1. Actor Guard (P1)
        $actorUserId = $actorUserId ?? auth()->id();
        if ($actorUserId !== null && (int)$actorUserId !== $userId) {
            throw new InvalidArgumentException('İşlem yapan kullanıcı ile finansal olay sahibi uyuşmuyor.');
        }

        // Tenant & Legal Entity Guard
        if ($event->store && (int) $event->store->user_id !== $userId) {
            throw new InvalidArgumentException('Mağaza ve finansal olay kullanıcı eşleşmesi çakışıyor.');
        }
        if ($event->legalEntity && (int) $event->legalEntity->user_id !== $userId) {
            throw new InvalidArgumentException('Şirket ve finansal olay kullanıcı eşleşmesi çakışıyor.');
        }
        if ($event->legalEntity && !$event->legalEntity->is_active) {
            throw new InvalidArgumentException('Şirket aktif değil.');
        }

        $sourceKey = 'marketplace_fin_event_' . $event->id;

        // Duplicate kontrolü & Payload Drift Kontrolü
        $existingRun = MarketplaceFinanceBridgeRun::where('user_id', $userId)
            ->where('source_key', $sourceKey)
            ->where('status', 'succeeded')
            ->first();

        $existingJournal = null;
        if ($existingRun && $existingRun->target_id) {
            $existingJournal = \App\Models\JournalEntry::where('user_id', $userId)->find($existingRun->target_id);
        }

        if (!$existingJournal) {
            $existingJournal = \App\Models\JournalEntry::where('user_id', $userId)
                ->where('source_key', $sourceKey)
                ->first();
        }

        if ($existingJournal) {
            $this->validateEventPayloadDrift($existingJournal, $event);
            return $existingJournal;
        }

        $run = MarketplaceFinanceBridgeRun::firstOrNew([
            'user_id'    => $userId,
            'source_key' => $sourceKey,
        ]);
        $run->fill([
            'marketplace_store_id'     => $event->store_id,
            'order_financial_event_id' => $event->id,
            'bridge_type'              => 'financial_event',
            'status'                   => 'processing',
            'attempted_at'             => now(),
            'payload_json'             => $event->toArray(),
        ]);
        $run->save();

        $amount = (float) $event->amount;
        if ($amount <= 0) {
            $run->status = 'skipped';
            $run->error_message = 'Tutar sıfır veya negatif olduğu için atlandı.';
            $run->completed_at = now();
            $run->save();
            return null;
        }

        $eventType = strtolower($event->event_type);
        $supportedTypes = ['commission', 'shipping_fee', 'cargo', 'payout', 'settlement'];
        if (!in_array($eventType, $supportedTypes, true)) {
            $run->status = 'skipped';
            $run->error_message = 'Desteklenmeyen olay tipi: ' . $event->event_type;
            $run->completed_at = now();
            $run->save();
            return null;
        }

        try {
            $journalEntry = DB::transaction(function () use ($userId, $amount, $eventType, $sourceKey, $event) {
                $arAccount = Account::where('user_id', $userId)
                    ->where('is_ar_account', true)
                    ->where('is_active', true)
                    ->first();
                $bankAccount = Account::where('user_id', $userId)
                    ->where('is_bank_account', true)
                    ->where('is_active', true)
                    ->first();
                $expenseAccount = Account::where('user_id', $userId)
                    ->where('code', '760')
                    ->where('is_active', true)
                    ->first();

                if (!$arAccount || !$bankAccount || !$expenseAccount) {
                    throw new InvalidArgumentException('Finansal olay köprülemesi için gerekli aktif hesaplar (102/120/760) bulunamadı.');
                }

                if ((int)$arAccount->user_id !== $userId || (int)$bankAccount->user_id !== $userId || (int)$expenseAccount->user_id !== $userId) {
                    throw new InvalidArgumentException('Kullanılan hesaplar ilgili kullanıcıya ait değil.');
                }

                $partyId = null;
                if ($event->order) {
                    $salesOrder = SalesOrder::where('user_id', $userId)
                        ->where('document_number', $event->order->order_number)
                        ->first();
                    if ($salesOrder) {
                        $partyId = $salesOrder->party_id;
                    }
                }

                $legalEntityId = $event->legal_entity_id ?: ($event->store ? $event->store->legal_entity_id : null);

                switch ($eventType) {
                    case 'commission':
                    case 'shipping_fee':
                    case 'cargo':
                        return $this->journalService->postManual([
                            'user_id'         => $userId,
                            'legal_entity_id' => $legalEntityId,
                            'entry_date'      => $event->event_date->toDateString(),
                            'entry_type'      => 'adjustment',
                            'description'     => 'Pazaryeri Masrafı: ' . $event->event_type,
                            'reference_number'=> $event->order ? $event->order->order_number : null,
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
                        return $this->journalService->postManual([
                            'user_id'         => $userId,
                            'legal_entity_id' => $legalEntityId,
                            'entry_date'      => $event->event_date->toDateString(),
                            'entry_type'      => 'collection',
                            'description'     => 'Pazaryeri Hakediş Ödemesi (Payout)',
                            'reference_number'=> $event->order ? $event->order->order_number : null,
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
                }

                return null;
            });

            if ($journalEntry) {
                $run->status = 'succeeded';
                $run->target_type = 'journal_entry';
                $run->target_id = $journalEntry->id;
                $run->completed_at = now();
                $run->result_json = [
                    'journal_entry_id' => $journalEntry->id,
                    'status'           => $journalEntry->status,
                ];
                $run->save();
            } else {
                $run->status = 'skipped';
                $run->completed_at = now();
                $run->save();
            }

            return $journalEntry;

        } catch (\Exception $e) {
            $run->status = 'failed';
            $run->error_message = $e->getMessage();
            $run->completed_at = now();
            $run->save();

            throw $e;
        }
    }

    /**
     * Başarısız veya atlanmış köprüleme işlemini yeniden çalıştır.
     */
    public function retryRun(MarketplaceFinanceBridgeRun $run): MarketplaceFinanceBridgeRun
    {
        $userId = auth()->id();
        if ((int)$run->user_id !== $userId) {
            throw new InvalidArgumentException('Bu köprüleme kaydını yeniden çalıştırma yetkiniz yok.');
        }

        if (!$run->isRetryable()) {
            throw new InvalidArgumentException('Sadece başarısız veya atlanmış kayıtlar yeniden çalıştırılabilir.');
        }

        // Run user_id ile bağlı ChannelOrder / OrderFinancialEvent tenant'ını doğrula (P1 Guard)
        if ($run->bridge_type === 'order') {
            $order = ChannelOrder::findOrFail($run->channel_order_id);
            if ((int)$order->store->user_id !== $userId) {
                throw new InvalidArgumentException('İlişkili siparişin sahibi ile yetki eşleşmiyor.');
            }
        } elseif ($run->bridge_type === 'financial_event') {
            $event = OrderFinancialEvent::findOrFail($run->order_financial_event_id);
            $eventUserId = $event->legalEntity ? (int)$event->legalEntity->user_id : 0;
            if ($eventUserId === 0 && $event->store) {
                $eventUserId = (int)$event->store->user_id;
            }
            if ($eventUserId !== $userId) {
                throw new InvalidArgumentException('İlişkili finansal olayın sahibi ile yetki eşleşmiyor.');
            }
        }

        $run->status = 'processing';
        $run->error_message = null;
        $run->attempted_at = now();
        $run->save();

        try {
            if ($run->bridge_type === 'order') {
                $order = ChannelOrder::findOrFail($run->channel_order_id);
                $this->bridgeOrder($order, true, (int)$run->user_id);
            } elseif ($run->bridge_type === 'financial_event') {
                $event = OrderFinancialEvent::findOrFail($run->order_financial_event_id);
                $this->bridgeFinancialEvent($event, (int)$run->user_id);
            }
            $run->refresh();
        } catch (\Exception $e) {
            $run->refresh();
            throw $e;
        }

        return $run;
    }

    // ─── DRIFT VALIDATIONS ────────────────────────────────────────────────

    protected function validateOrderPayloadDrift(SalesOrder $existingOrder, ChannelOrder $order): void
    {
        if ((int)($existingOrder->legal_entity_id ?? 0) !== (int)($order->legal_entity_id ?: $order->store->legal_entity_id)) {
            throw new InvalidArgumentException('Bridge payload mismatch: legal_entity_id changed.');
        }
        if ($existingOrder->document_number !== $order->order_number) {
            throw new InvalidArgumentException('Bridge payload mismatch: order_number changed.');
        }
        if ($existingOrder->currency_code !== ($order->currency ?? 'TRY')) {
            throw new InvalidArgumentException('Bridge payload mismatch: currency changed.');
        }

        $existingItems = $existingOrder->items;
        $orderItems = $order->items;

        if ($existingItems->count() !== $orderItems->count()) {
            throw new InvalidArgumentException('Bridge payload mismatch: items count changed.');
        }

        $existingSorted = $existingItems->sortBy('stock_code')->values();
        $orderSorted = $orderItems->sortBy('stock_code')->values();

        for ($i = 0; $i < $existingSorted->count(); $i++) {
            $eItem = $existingSorted[$i];
            $oItem = $orderSorted[$i];

            if ($eItem->stock_code !== $oItem->stock_code) {
                throw new InvalidArgumentException('Bridge payload mismatch: stock_code mismatch.');
            }
            if ((int)$eItem->quantity !== (int)$oItem->quantity) {
                throw new InvalidArgumentException('Bridge payload mismatch: quantity changed.');
            }
            if (abs((float)$eItem->unit_price - (float)($oItem->unit_price ?? $oItem->gross_amount)) > 0.001) {
                throw new InvalidArgumentException('Bridge payload mismatch: unit_price changed.');
            }
            if (abs((float)$eItem->vat_rate - (float)($oItem->vat_rate ?? 20.00)) > 0.001) {
                throw new InvalidArgumentException('Bridge payload mismatch: vat_rate changed.');
            }
        }
    }

    protected function validateEventPayloadDrift(\App\Models\JournalEntry $existingJournal, OrderFinancialEvent $event): void
    {
        $journalAmount = (float)$existingJournal->lines()->sum('debit_amount');
        if (abs($journalAmount - (float)$event->amount) > 0.001) {
            throw new InvalidArgumentException('Bridge payload mismatch: event amount changed.');
        }

        if ($existingJournal->currency_code !== ($event->currency ?? 'TRY')) {
            throw new InvalidArgumentException('Bridge payload mismatch: currency changed.');
        }

        $expectedLegalEntityId = $event->legal_entity_id ?: ($event->store ? $event->store->legal_entity_id : null);
        if ((int)$existingJournal->legal_entity_id !== (int)$expectedLegalEntityId) {
            throw new InvalidArgumentException('Bridge payload mismatch: legal_entity_id changed.');
        }
    }
}
