<?php

namespace App\Services\Accounting;

use App\Models\MpProduct;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Services\Accounting\OutstandingInvoiceService;
use App\Services\Accounting\StockService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Satış ve Satın Alma Belge Akışı Yönetim Servisi.
 *
 * Sorumluluklar:
 * 1. Satış siparişi (SalesOrder) taslağı oluşturma ve satır ekleme.
 * 2. Satın alma siparişi (PurchaseOrder) oluşturma.
 * 3. Satış Onayı (approveSalesOrder) Akışı:
 *    - Faturayı cari alacak (Receivable) olarak kaydet.
 *    - Depodan stokları düş (StockMovement - direction='out').
 *    - Yevmiye fişini Genel Muhasebe (JournalEntry) tarafına işle.
 * 4. Satın Alma Onayı (approvePurchaseOrder) Akışı:
 *    - Faturayı cari borç (Payable) olarak kaydet.
 *    - Depoya stok girişini yap (StockMovement - direction='in').
 *    - Yevmiye fişini işle.
 */
class TradeService
{
    protected OutstandingInvoiceService $invoiceService;
    protected StockService $stockService;

    public function __construct(OutstandingInvoiceService $invoiceService, StockService $stockService)
    {
        $this->invoiceService = $invoiceService;
        $this->stockService = $stockService;
    }

    /**
     * Satış Belgesi (Draft) Oluştur.
     */
    public function createSalesOrder(array $header, array $items): SalesOrder
    {
        $userId = (int) $header['user_id'];
        $this->validateTenant($userId, (int) $header['party_id'], isset($header['legal_entity_id']) ? (int) $header['legal_entity_id'] : null);

        return DB::transaction(function () use ($header, $items, $userId) {

            $order = SalesOrder::create([
                'user_id'          => $userId,
                'party_id'         => (int) $header['party_id'],
                'legal_entity_id'  => $header['legal_entity_id'] ?? null,
                'document_number'  => $header['document_number'],
                'order_date'       => $header['order_date'],
                'currency_code'    => $header['currency_code'] ?? 'TRY',
                'exchange_rate'    => $header['exchange_rate'] ?? 1.0,
                'description'      => $header['description'] ?? null,
                'status'           => 'draft',
            ]);

            $totalAmount = 0.0;
            $totalDiscount = 0.0;

            foreach ($items as $item) {
                $qty = (int) $item['quantity'];
                $price = (float) $item['unit_price'];
                $vat = (float) ($item['vat_rate'] ?? 20.00);
                $discountRate = (float) ($item['discount_rate'] ?? 0.00);

                if ($qty <= 0 || $price <= 0) {
                    throw new InvalidArgumentException('Miktar ve birim fiyat sıfırdan büyük olmalıdır.');
                }
                if ($vat < 0 || $discountRate < 0 || $discountRate > 100) {
                    throw new InvalidArgumentException('Geçersiz KDV oranı veya iskonto oranı.');
                }

                $baseTotal = $qty * $price;
                $lineDiscount = round($baseTotal * ($discountRate / 100), 2);
                $totalBeforeVat = $baseTotal - $lineDiscount;
                $lineVat = round($totalBeforeVat * ($vat / 100), 2);
                $lineTotal = round($totalBeforeVat + $lineVat, 2);

                $totalAmount += $lineTotal;
                $totalDiscount += $lineDiscount;

                $product = MpProduct::where('user_id', $userId)->where('stock_code', $item['stock_code'])->first();

                SalesOrderItem::create([
                    'sales_order_id' => $order->id,
                    'product_id'     => $product ? $product->id : null,
                    'stock_code'     => $item['stock_code'],
                    'quantity'       => $qty,
                    'unit_price'     => $price,
                    'vat_rate'       => $vat,
                    'discount_rate'  => $discountRate,
                    'discount_amount'=> $lineDiscount,
                    'total_amount'   => $lineTotal,
                ]);
            }

            $order->total_amount = $totalAmount;
            $order->discount_amount = $totalDiscount;
            $order->save();

            return $order->load('items');
        });
    }

    public function approveSalesOrder(SalesOrder $order): void
    {
        if ($order->status !== 'draft') {
            throw new InvalidArgumentException('Sadece taslak durumundaki satış belgeleri onaylanabilir.');
        }

        DB::transaction(function () use ($order) {
            // 0. Depo tespiti
            $warehouseId = $this->stockService->resolveWarehouseId($order->user_id, null);

            // 0b. Stok seviyesi kontrolü
            foreach ($order->items as $item) {
                $currentStock = $this->stockService->getStockLevel($order->user_id, $item->stock_code, $warehouseId);
                if ($currentStock < $item->quantity) {
                    throw new InvalidArgumentException(sprintf(
                        'Onay Başarısız! "%s" ürünü için yetersiz stok. Mevcut stok: %d, Sipariş: %d.',
                        $item->stock_code,
                        $currentStock,
                        $item->quantity
                    ));
                }
            }

            // 1. Cari Alacak Oluştur (Bu işlem double-entry journal entry'yi de tetikler)
            $receivable = $this->invoiceService->createReceivable([
                'user_id'         => $order->user_id,
                'party_id'        => $order->party_id,
                'legal_entity_id' => $order->legal_entity_id,
                'amount'          => (float) $order->total_amount,
                'document_date'   => $order->order_date->toDateString(),
                'document_number' => $order->document_number,
                'currency_code'   => $order->currency_code,
                'exchange_rate'   => (float) $order->exchange_rate,
                'description'     => $order->description ?? 'Satış Belgesi Fatura Alacağı',
            ]);

            // 1b. Cari Açık Hesap (PartyLedger) Hareketi oluştur (debit / borç yansıtma)
            $party = \App\Models\Party::findOrFail($order->party_id);
            app(PartyLedgerService::class)->postReceivable($party, (float) $order->total_amount, [
                'legal_entity_id' => $order->legal_entity_id,
                'document_number' => $order->document_number,
                'document_date'   => $order->order_date->toDateString(),
                'source_type'     => 'sales_order',
                'source_key'      => 'sales_order_post_' . $order->id,
                'description'     => $order->description ?? 'Satış Belgesi Cari Borç Kaydı',
            ]);

            // 2. Depodan Stok Çıkışlarını Yap
            foreach ($order->items as $item) {
                $this->stockService->recordMovement([
                    'user_id'       => $order->user_id,
                    'warehouse_id'  => $warehouseId,
                    'stock_code'    => $item->stock_code,
                    'movement_type' => 'out_sale',
                    'direction'     => 'out',
                    'quantity'      => $item->quantity,
                    'unit_cost'     => (float) $item->unit_price,
                    'source_type'   => 'sales_order',
                    'source_id'     => $order->id,
                    'description'   => $order->document_number . ' nolu satış çıkışı',
                    'movement_date' => $order->order_date->toDateString(),
                ]);
            }

            // 3. Durumu güncelle
            $order->receivable_id = $receivable->id;
            $order->status = 'approved';
            $order->save();
        });
    }

    /**
     * Satış Belgesini İptal Et.
     * Stokları depoya iade eder, Cari/Muhasebe hareketlerini iptal (void) eder.
     */
    public function cancelSalesOrder(SalesOrder $order): void
    {
        if ($order->status !== 'approved') {
            throw new InvalidArgumentException('Sadece onaylanmış satış belgeleri iptal edilebilir.');
        }

        DB::transaction(function () use ($order) {
            $userId = $order->user_id;

            // 1. Depoya Stok Girişi Yap (Ters Hareket)
            foreach ($order->items as $item) {
                $this->stockService->recordMovement([
                    'user_id'       => $userId,
                    'stock_code'    => $item->stock_code,
                    'movement_type' => 'in_return',
                    'direction'     => 'in',
                    'quantity'      => $item->quantity,
                    'unit_cost'     => (float) $item->unit_price,
                    'source_type'   => 'sales_order',
                    'source_id'     => $order->id,
                    'description'   => $order->document_number . ' nolu satış iptali stok iadesi',
                    'movement_date' => now()->toDateString(),
                ]);
            }

            // 2. Cari Alacağı ve Fişi Void Et
            if ($order->receivable_id) {
                $receivable = \App\Models\Receivable::find($order->receivable_id);
                if ($receivable) {
                    $receivable->update(['status' => 'voided']);
                    if ($receivable->journal_entry_id) {
                        $journalEntry = \App\Models\JournalEntry::find($receivable->journal_entry_id);
                        if ($journalEntry && !$journalEntry->isVoid()) {
                            app(JournalService::class)->voidEntry($journalEntry, 'Satış siparişi iptal edildi.', $userId);
                        }
                    }
                }
            }

            // 3. PartyLedgerEntry'yi Void Et
            $partyLedgerEntry = \App\Models\PartyLedgerEntry::where('user_id', $userId)
                ->where('source_type', 'sales_order')
                ->where('source_key', 'sales_order_post_' . $order->id)
                ->first();
            if ($partyLedgerEntry && !$partyLedgerEntry->isVoid()) {
                app(PartyLedgerService::class)->voidEntry($partyLedgerEntry, 'Satış siparişi iptal edildi.');
            }

            // 4. Durumu Güncelle
            $order->status = 'cancelled';
            $order->save();
        });
    }

    /**
     * Satın Alma Belgesi (Draft) Oluştur.
     */
    public function createPurchaseOrder(array $header, array $items): PurchaseOrder
    {
        $userId = (int) $header['user_id'];
        $this->validateTenant($userId, (int) $header['party_id'], isset($header['legal_entity_id']) ? (int) $header['legal_entity_id'] : null);

        return DB::transaction(function () use ($header, $items, $userId) {

            $order = PurchaseOrder::create([
                'user_id'          => $userId,
                'party_id'         => (int) $header['party_id'],
                'legal_entity_id'  => $header['legal_entity_id'] ?? null,
                'document_number'  => $header['document_number'],
                'order_date'       => $header['order_date'],
                'currency_code'    => $header['currency_code'] ?? 'TRY',
                'exchange_rate'    => $header['exchange_rate'] ?? 1.0,
                'description'      => $header['description'] ?? null,
                'status'           => 'draft',
            ]);

            $totalAmount = 0.0;
            $totalDiscount = 0.0;

            foreach ($items as $item) {
                $qty = (int) $item['quantity'];
                $price = (float) $item['unit_price'];
                $vat = (float) ($item['vat_rate'] ?? 20.00);
                $discountRate = (float) ($item['discount_rate'] ?? 0.00);

                if ($qty <= 0 || $price <= 0) {
                    throw new InvalidArgumentException('Miktar ve birim fiyat sıfırdan büyük olmalıdır.');
                }
                if ($vat < 0 || $discountRate < 0 || $discountRate > 100) {
                    throw new InvalidArgumentException('Geçersiz KDV oranı veya iskonto oranı.');
                }

                $baseTotal = $qty * $price;
                $lineDiscount = round($baseTotal * ($discountRate / 100), 2);
                $totalBeforeVat = $baseTotal - $lineDiscount;
                $lineVat = round($totalBeforeVat * ($vat / 100), 2);
                $lineTotal = round($totalBeforeVat + $lineVat, 2);

                $totalAmount += $lineTotal;
                $totalDiscount += $lineDiscount;

                $product = MpProduct::where('user_id', $userId)->where('stock_code', $item['stock_code'])->first();

                PurchaseOrderItem::create([
                    'purchase_order_id' => $order->id,
                    'product_id'        => $product ? $product->id : null,
                    'stock_code'        => $item['stock_code'],
                    'quantity'          => $qty,
                    'unit_price'        => $price,
                    'vat_rate'          => $vat,
                    'discount_rate'     => $discountRate,
                    'discount_amount'   => $lineDiscount,
                    'total_amount'      => $lineTotal,
                ]);
            }

            $order->total_amount = $totalAmount;
            $order->discount_amount = $totalDiscount;
            $order->save();

            return $order->load('items');
        });
    }

    /**
     * Satın Alma Belgesini Onayla.
     * Bu işlem cari borç açar, muhasebeleşir ve depoya stok sokar.
     */
    public function approvePurchaseOrder(PurchaseOrder $order): void
    {
        if ($order->status !== 'draft') {
            throw new InvalidArgumentException('Sadece taslak durumundaki satın alma belgeleri onaylanabilir.');
        }

        DB::transaction(function () use ($order) {
            // 1. Cari Borç Oluştur
            $payable = $this->invoiceService->createPayable([
                'user_id'         => $order->user_id,
                'party_id'        => $order->party_id,
                'legal_entity_id' => $order->legal_entity_id,
                'amount'          => (float) $order->total_amount,
                'document_date'   => $order->order_date->toDateString(),
                'document_number' => $order->document_number,
                'currency_code'   => $order->currency_code,
                'exchange_rate'   => (float) $order->exchange_rate,
                'description'     => $order->description ?? 'Satın Alma Belgesi Fatura Borcu',
            ]);

            // 1b. Cari Açık Hesap (PartyLedger) Hareketi oluştur (credit / alacak yansıtma)
            $party = \App\Models\Party::findOrFail($order->party_id);
            app(PartyLedgerService::class)->postPayable($party, (float) $order->total_amount, [
                'legal_entity_id' => $order->legal_entity_id,
                'document_number' => $order->document_number,
                'document_date'   => $order->order_date->toDateString(),
                'source_type'     => 'purchase_order',
                'source_key'      => 'purchase_order_post_' . $order->id,
                'description'     => $order->description ?? 'Satın Alma Belgesi Cari Alacak Kaydı',
            ]);

            // 2. Depoya Stok Girişi Yap
            foreach ($order->items as $item) {
                $this->stockService->recordMovement([
                    'user_id'       => $order->user_id,
                    'stock_code'    => $item->stock_code,
                    'movement_type' => 'in_purchase',
                    'direction'     => 'in',
                    'quantity'      => $item->quantity,
                    'unit_cost'     => (float) $item->unit_price,
                    'source_type'   => 'purchase_order',
                    'source_id'     => $order->id,
                    'description'   => $order->document_number . ' nolu satın alma girişi',
                    'movement_date' => $order->order_date->toDateString(),
                ]);
            }

            // 3. Durumu güncelle
            $order->payable_id = $payable->id;
            $order->status = 'approved';
            $order->save();
        });
    }

    /**
     * Satın Alma Belgesini İptal Et.
     * Stokları depodan düşer, Cari/Muhasebe hareketlerini iptal eder.
     */
    public function cancelPurchaseOrder(PurchaseOrder $order): void
    {
        if ($order->status !== 'approved') {
            throw new InvalidArgumentException('Sadece onaylanmış satın alma belgeleri iptal edilebilir.');
        }

        DB::transaction(function () use ($order) {
            $userId = $order->user_id;

            // 1. Depodan Stok Düşüşü Yap (Ters Hareket)
            foreach ($order->items as $item) {
                $this->stockService->recordMovement([
                    'user_id'       => $userId,
                    'stock_code'    => $item->stock_code,
                    'movement_type' => 'out_loss',
                    'direction'     => 'out',
                    'quantity'      => $item->quantity,
                    'unit_cost'     => (float) $item->unit_price,
                    'source_type'   => 'purchase_order',
                    'source_id'     => $order->id,
                    'description'   => $order->document_number . ' nolu satın alma iptali stok çıkışı',
                    'movement_date' => now()->toDateString(),
                ]);
            }

            // 2. Cari Borcu ve Fişi Void Et
            if ($order->payable_id) {
                $payable = \App\Models\Payable::find($order->payable_id);
                if ($payable) {
                    $payable->update(['status' => 'voided']);
                    if ($payable->journal_entry_id) {
                        $journalEntry = \App\Models\JournalEntry::find($payable->journal_entry_id);
                        if ($journalEntry && !$journalEntry->isVoid()) {
                            app(JournalService::class)->voidEntry($journalEntry, 'Satın alma siparişi iptal edildi.', $userId);
                        }
                    }
                }
            }

            // 3. PartyLedgerEntry'yi Void Et
            $partyLedgerEntry = \App\Models\PartyLedgerEntry::where('user_id', $userId)
                ->where('source_type', 'purchase_order')
                ->where('source_key', 'purchase_order_post_' . $order->id)
                ->first();
            if ($partyLedgerEntry && !$partyLedgerEntry->isVoid()) {
                app(PartyLedgerService::class)->voidEntry($partyLedgerEntry, 'Satın alma siparişi iptal edildi.');
            }

            // 4. Durumu Güncelle
            $order->status = 'cancelled';
            $order->save();
        });
    }

    /**
     * Sahiplik doğrulaması yapar. Eşleşmeyen durumlarda InvalidArgumentException fırlatır.
     */
    protected function validateTenant(int $userId, int $partyId, ?int $legalEntityId = null): void
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
