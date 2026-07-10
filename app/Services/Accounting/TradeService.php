<?php

namespace App\Services\Accounting;

use App\Models\Product;
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

            foreach ($items as $item) {
                $qty = (int) $item['quantity'];
                $price = (float) $item['unit_price'];
                $vat = (float) ($item['vat_rate'] ?? 20.00);

                $lineTotal = round(($qty * $price) * (1 + $vat / 100), 2);
                $totalAmount += $lineTotal;

                $product = Product::where('stok_kodu', $item['stock_code'])->first();

                SalesOrderItem::create([
                    'sales_order_id' => $order->id,
                    'product_id'     => $product ? $product->id : null,
                    'stock_code'     => $item['stock_code'],
                    'quantity'       => $qty,
                    'unit_price'     => $price,
                    'vat_rate'       => $vat,
                    'total_amount'   => $lineTotal,
                ]);
            }

            $order->total_amount = $totalAmount;
            $order->save();

            return $order->load('items');
        });
    }

    /**
     * Satış Belgesini Onayla.
     * Bu işlem cari alacak açar, muhasebeleşir ve stoktan düşer.
     */
    public function approveSalesOrder(SalesOrder $order): void
    {
        if ($order->status !== 'draft') {
            throw new InvalidArgumentException('Sadece taslak durumundaki satış belgeleri onaylanabilir.');
        }

        DB::transaction(function () use ($order) {
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

            // 2. Depodan Stok Çıkışlarını Yap
            foreach ($order->items as $item) {
                $this->stockService->recordMovement([
                    'user_id'       => $order->user_id,
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

            foreach ($items as $item) {
                $qty = (int) $item['quantity'];
                $price = (float) $item['unit_price'];
                $vat = (float) ($item['vat_rate'] ?? 20.00);

                $lineTotal = round(($qty * $price) * (1 + $vat / 100), 2);
                $totalAmount += $lineTotal;

                $product = Product::where('stok_kodu', $item['stock_code'])->first();

                PurchaseOrderItem::create([
                    'purchase_order_id' => $order->id,
                    'product_id'        => $product ? $product->id : null,
                    'stock_code'        => $item['stock_code'],
                    'quantity'          => $qty,
                    'unit_price'        => $price,
                    'vat_rate'          => $vat,
                    'total_amount'      => $lineTotal,
                ]);
            }

            $order->total_amount = $totalAmount;
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
