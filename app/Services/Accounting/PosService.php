<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\Collection;
use App\Models\LegalEntity;
use App\Models\MpProduct;
use App\Models\Party;
use App\Models\PartyRole;
use App\Models\PosSale;
use App\Models\PosShift;
use App\Models\PosTerminal;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use App\Services\Accounting\CollectionPaymentService;
use App\Services\Accounting\StockService;
use App\Services\Accounting\TradeService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Hızlı Satış (POS) ve Kasa Vardiya Yönetim Servisi.
 */
class PosService
{
    protected TradeService $tradeService;
    protected CollectionPaymentService $collectionPaymentService;
    protected StockService $stockService;

    public function __construct(
        TradeService $tradeService,
        CollectionPaymentService $collectionPaymentService,
        StockService $stockService
    ) {
        $this->tradeService = $tradeService;
        $this->collectionPaymentService = $collectionPaymentService;
        $this->stockService = $stockService;
    }

    /**
     * POS Vardiyası Aç.
     */
    public function openShift(PosTerminal $terminal, float $openingBalance, array $context = []): PosShift
    {
        $userId = auth()->id() ?: $terminal->user_id;

        if ($terminal->user_id !== $userId) {
            throw (new \Illuminate\Database\Eloquent\ModelNotFoundException)->setModel(PosTerminal::class, [$terminal->id]);
        }

        // Terminal aktiflik kontrolü
        if (!$terminal->is_active) {
            throw new InvalidArgumentException('Pasif bir terminalde vardiya açılamaz.');
        }

        // Halihazırda açık bir vardiya var mı?
        $existing = PosShift::where('user_id', $userId)
            ->where('pos_terminal_id', $terminal->id)
            ->where('status', 'open')
            ->first();

        if ($existing) {
            throw new InvalidArgumentException('Bu terminalde halihazırda açık bir vardiya bulunuyor.');
        }

        if ($openingBalance < 0) {
            throw new InvalidArgumentException('Açılış bakiyesi negatif olamaz.');
        }

        // Çözümleme mantığı: context veya terminalden al
        $accountId     = $context['account_id'] ?? $terminal->account_id ?? null;
        $warehouseId   = $context['warehouse_id'] ?? $terminal->warehouse_id ?? null;
        $legalEntityId = $context['legal_entity_id'] ?? $terminal->legal_entity_id ?? null;

        if ($accountId) {
            $account = Account::where('user_id', $userId)->where('is_active', true)->findOrFail($accountId);
            if (!in_array($account->type, ['cash', 'bank'], true)) {
                throw new InvalidArgumentException('Vardiya hesabı sadece kasa veya banka hesabı olabilir.');
            }
        }

        if ($warehouseId) {
            Warehouse::where('user_id', $userId)->where('is_active', true)->findOrFail($warehouseId);
        }

        if ($legalEntityId) {
            LegalEntity::where('user_id', $userId)->active()->findOrFail($legalEntityId);
        }

        return PosShift::create([
            'user_id'         => $userId,
            'pos_terminal_id' => $terminal->id,
            'opened_at'       => now(),
            'opening_balance' => $openingBalance,
            'status'          => 'open',
            'account_id'      => $accountId,
            'warehouse_id'    => $warehouseId,
            'legal_entity_id' => $legalEntityId,
            'meta_json'       => count($context) > 0 ? $context : null,
        ]);
    }

    /**
     * POS Vardiyası Kapat.
     */
    public function closeShift(PosShift $shift, float $closingBalance, ?int $userId = null): PosShift
    {
        $actorUserId = $userId ?? auth()->id();
        if (!$actorUserId) {
            throw new InvalidArgumentException('Kullanıcı context/aktör bilgisi bulunamadı.');
        }

        if ($shift->user_id !== $actorUserId) {
            throw new InvalidArgumentException('Bu vardiya başka bir kullanıcıya ait.');
        }

        if ($shift->status !== 'open') {
            throw new InvalidArgumentException('Sadece açık vardiyalar kapatılabilir.');
        }

        if ($closingBalance < 0) {
            throw new InvalidArgumentException('Kapanış bakiyesi negatif olamaz.');
        }

        // Beklenen kapanış bakiyesi = Açılış bakiyesi + posted olan NAKİT POS satışların tutarı
        $salesSum = PosSale::where('pos_shift_id', $shift->id)
            ->where('status', 'posted')
            ->where('payment_method', 'cash')
            ->sum('amount');

        $expectedClosingBalance = (float) $shift->opening_balance + (float) $salesSum;
        $differenceAmount       = $closingBalance - $expectedClosingBalance;

        $shift->update([
            'closed_at'                => now(),
            'closing_balance'          => $closingBalance,
            'expected_closing_balance' => $expectedClosingBalance,
            'difference_amount'        => $differenceAmount,
            'status'                   => 'closed',
        ]);

        return $shift->fresh();
    }

    /**
     * Hızlı POS Satışı Yap.
     */
    public function recordPosSale(PosShift $shift, array $header, array $items): PosSale
    {
        if ($shift->status !== 'open') {
            throw new InvalidArgumentException('Satış yapmak için açık bir vardiya olmalıdır.');
        }

        $userId = $shift->user_id;

        // Terminal aktiflik ve sahiplik kontrolü
        $terminal = PosTerminal::where('user_id', $userId)->findOrFail($shift->pos_terminal_id);
        if (!$terminal->is_active) {
            throw new InvalidArgumentException('Terminal aktif değil.');
        }

        if (count($items) === 0) {
            throw new InvalidArgumentException('Sepet boş olamaz.');
        }

        // Sepet validasyonları
        foreach ($items as $item) {
            $qty          = (int) $item['quantity'];
            $price        = (float) $item['unit_price'];
            $vat          = (float) ($item['vat_rate'] ?? 20.00);
            $discountRate = (float) ($item['discount_rate'] ?? 0.00);

            if ($qty <= 0) {
                throw new InvalidArgumentException('Miktar sıfırdan büyük olmalıdır.');
            }
            if ($price < 0) {
                throw new InvalidArgumentException('Birim fiyatı negatif olamaz.');
            }
            if ($vat < 0) {
                throw new InvalidArgumentException('Geçersiz KDV oranı.');
            }
            if ($discountRate < 0 || $discountRate > 100) {
                throw new InvalidArgumentException('İskonto oranı 0 ile 100 arasında olmalıdır.');
            }

            // Ürün sahiplik kontrolü
            $product = MpProduct::where('user_id', $userId)->where('stock_code', $item['stock_code'])->first();
            if (!$product) {
                throw new InvalidArgumentException('Belirtilen ürün bulunamadı veya bu kullanıcıya ait değil: ' . $item['stock_code']);
            }
        }

        // Cari bul / oluştur
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

            // Fırsat: Sadece Perakende Müşteri için rol yoksa otomatik oluşturabiliriz
            if (!$party->roles()->where('role', 'customer')->exists()) {
                $party->roles()->create([
                    'user_id' => $userId,
                    'role'    => 'customer',
                ]);
            }
        } else {
            $party = Party::where('user_id', $userId)->findOrFail($partyId);
            if (!$party->roles()->where('role', 'customer')->exists()) {
                throw new InvalidArgumentException('Seçilen cari müşteri (customer) rolüne sahip değil.');
            }
        }

        // Depo çözümü
        $warehouseId = $header['warehouse_id'] ?? $terminal->warehouse_id ?? null;
        $resolvedWarehouseId = $this->stockService->resolveWarehouseId($userId, $warehouseId);
        $wh = Warehouse::where('user_id', $userId)->where('is_active', true)->findOrFail($resolvedWarehouseId);

        // Kasa/Banka hesabı çözümü
        $paymentMethod = $header['payment_method'] ?? 'cash';
        if (!in_array($paymentMethod, ['cash', 'card', 'bank_transfer'], true)) {
            throw new InvalidArgumentException('Geçersiz ödeme yöntemi.');
        }

        $accountId = $header['account_id'] ?? $terminal->account_id ?? $shift->account_id ?? null;
        if (!$accountId) {
            // Varsayılan hesap bulalım
            $defaultAcc = Account::where('user_id', $userId)
                ->where('is_active', true)
                ->where('type', $paymentMethod === 'cash' ? 'cash' : 'bank')
                ->first();
            if ($defaultAcc) {
                $accountId = $defaultAcc->id;
            }
        }

        if (!$accountId) {
            throw new InvalidArgumentException('Ödeme için uygun bir kasa/banka hesabı seçilmedi veya tanımlanmadı.');
        }

        $account = Account::where('user_id', $userId)->where('is_active', true)->findOrFail($accountId);
        if ($paymentMethod === 'cash' && $account->type !== 'cash') {
            throw new InvalidArgumentException('Nakit ödemeler için sadece kasa hesabı kullanılabilir.');
        }
        if (in_array($paymentMethod, ['card', 'bank_transfer'], true) && $account->type !== 'bank') {
            throw new InvalidArgumentException('Kart veya Havale ödemeleri için banka hesabı seçilmelidir.');
        }

        $legalEntityId = $header['legal_entity_id'] ?? $terminal->legal_entity_id ?? $shift->legal_entity_id ?? null;
        if ($legalEntityId) {
            LegalEntity::where('user_id', $userId)->active()->findOrFail($legalEntityId);
        }

        $sourceKey = $header['source_key'] ?? null;

        // Idempotency kontrolü
        if ($sourceKey) {
            $existingSale = PosSale::where('user_id', $userId)->where('source_key', $sourceKey)->first();
            if ($existingSale) {
                // Payload kontrolü
                $so = SalesOrder::with('items')->find($existingSale->sales_order_id);
                $matching = true;

                if ($existingSale->pos_shift_id !== $shift->id
                    || $existingSale->party_id !== $partyId
                    || $existingSale->warehouse_id !== $resolvedWarehouseId
                    || $existingSale->account_id !== $accountId
                    || $existingSale->payment_method !== $paymentMethod
                    || $existingSale->legal_entity_id !== $legalEntityId
                    || count($so->items) !== count($items)
                ) {
                    $matching = false;
                }

                if ($matching) {
                    // Kalemleri karşılaştır
                    for ($i = 0; $i < count($so->items); $i++) {
                        $eItem = $so->items[$i];
                        $iItem = $items[$i];
                        if ($eItem->stock_code !== $iItem['stock_code']
                            || (int) $eItem->quantity !== (int) $iItem['quantity']
                            || abs((float) $eItem->unit_price - (float) $iItem['unit_price']) > 0.005
                            || abs((float) ($eItem->vat_rate ?? 20.00) - (float) ($iItem['vat_rate'] ?? 20.00)) > 0.005
                            || abs((float) ($eItem->discount_rate ?? 0.00) - (float) ($iItem['discount_rate'] ?? 0.00)) > 0.005
                        ) {
                            $matching = false;
                            break;
                        }
                    }
                }

                if (!$matching) {
                    throw new InvalidArgumentException('Çakışan source_key ile farklı detaylara sahip bir POS satışı zaten mevcut: ' . $sourceKey);
                }

                return $existingSale;
            }
        }

        return DB::transaction(function () use ($shift, $userId, $partyId, $resolvedWarehouseId, $accountId, $legalEntityId, $paymentMethod, $sourceKey, $items, $header) {
            $docNum = 'POS-' . $shift->pos_terminal_id . '-' . time() . '-' . rand(100, 999);

            // Deterministic source keys
            $soSourceKey   = 'pos_sale_order_' . ($sourceKey ?: $docNum);
            $collSourceKey = 'pos_sale_collection_' . ($sourceKey ?: $docNum);

            // 1. Satış Siparişi oluştur (Draft)
            $salesOrder = $this->tradeService->createSalesOrder([
                'user_id'          => $userId,
                'party_id'         => $partyId,
                'legal_entity_id'  => $legalEntityId,
                'warehouse_id'     => $resolvedWarehouseId,
                'document_number'  => $docNum,
                'order_date'       => now()->toDateString(),
                'currency_code'    => 'TRY',
                'exchange_rate'    => 1.0,
                'description'      => 'Hızlı POS Satışı',
                'source_key'       => $soSourceKey,
            ], $items);

            // 2. Siparişi Onayla (Stok düşer, Cari Alacak / Receivable oluşur)
            $this->tradeService->approveSalesOrder($salesOrder);

            // 3. Tahsilat oluştur
            $collection = $this->collectionPaymentService->recordCollection([
                'user_id'         => $userId,
                'party_id'        => $partyId,
                'legal_entity_id' => $legalEntityId,
                'account_id'      => $accountId,
                'amount'          => (float) $salesOrder->total_amount,
                'collection_date' => now()->toDateString(),
                'payment_method'  => $paymentMethod === 'cash' ? 'cash' : 'bank',
                'reference'       => 'POS Fiş Tahsilatı: ' . $docNum,
                'source_key'      => $collSourceKey,
            ]);

            // 4. Tahsilatı Receivable'a allocate et
            $this->collectionPaymentService->allocateCollection($collection, [
                [
                    'receivable_id' => $salesOrder->receivable_id,
                    'amount'        => (float) $salesOrder->total_amount,
                ]
            ]);

            // 5. PosSale kaydını oluştur
            return PosSale::create([
                'user_id'          => $userId,
                'pos_shift_id'     => $shift->id,
                'sales_order_id'   => $salesOrder->id,
                'collection_id'    => $collection->id,
                'legal_entity_id'  => $legalEntityId,
                'warehouse_id'     => $resolvedWarehouseId,
                'party_id'         => $partyId,
                'account_id'       => $accountId,
                'source_key'       => $sourceKey,
                'reference_number' => $docNum,
                'payment_method'   => $paymentMethod,
                'amount'           => (float) $salesOrder->total_amount,
                'status'           => 'posted',
                'posted_at'        => now(),
            ]);
        });
    }

    /**
     * POS Satışını İptal Et (Ters Kayıt).
     */
    public function voidPosSale(PosSale $sale, ?string $reason = null, ?int $userId = null): PosSale
    {
        $actorUserId = $userId ?? auth()->id();
        if (!$actorUserId) {
            throw new InvalidArgumentException('Kullanıcı context/aktör bilgisi bulunamadı.');
        }

        if ($sale->user_id !== $actorUserId) {
            throw new InvalidArgumentException('Bu satış kaydı başka bir kullanıcıya ait.');
        }

        if ($sale->status !== 'posted') {
            throw new InvalidArgumentException('Sadece aktif (posted) satışlar iptal edilebilir.');
        }

        // Vardiya açık olmalı
        $shift = PosShift::findOrFail($sale->pos_shift_id);
        if ($shift->status !== 'open') {
            throw new InvalidArgumentException('Kapalı bir vardiyadaki satış iptal edilemez.');
        }

        return DB::transaction(function () use ($sale, $reason) {
            // 1. Önce tahsilat ve dağıtımları void et
            if ($sale->collection_id) {
                $collection = Collection::findOrFail($sale->collection_id);
                $this->collectionPaymentService->voidCollection($collection, $reason);
            }

            // 2. Satış Siparişini cancel et (Bu işlem yevmiye ters kaydını yapar ve stokları orijinal depoya iade eder)
            if ($sale->sales_order_id) {
                $salesOrder = SalesOrder::findOrFail($sale->sales_order_id);
                $this->tradeService->cancelSalesOrder($salesOrder, $reason);
            }

            // 3. POS Satış durumunu güncelle
            $sale->update([
                'status'      => 'voided',
                'voided_at'   => now(),
                'void_reason' => $reason ?: 'POS arayüzünden iptal edildi.',
            ]);

            return $sale->fresh();
        });
    }
}
