<?php

namespace App\Services\Accounting;

use App\Models\MpProduct;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Depo ve Stok Hareketleri Defteri Yönetim Servisi.
 *
 * Sorumluluklar:
 * 1. Depo (warehouse) oluşturma ve varsayılan depo seçimi.
 * 2. Giriş (Alış, İade, Düzeltme) ve Çıkış (Satış, Fire, Düzeltme) stok hareketlerinin işlenmesi.
 * 3. Hızlı sorgulama için stok bakiyelerinin (stock_balances) anlık güncellenmesi.
 * 4. Kritik stok seviyesi kontrolü (mp_products limitleri veya varsayılan limit).
 */
class StockService
{
    /**
     * Depo Oluştur.
     */
    public function createWarehouse(int $userId, string $name, string $code, bool $isDefault = false): Warehouse
    {
        return DB::transaction(function () use ($userId, $name, $code, $isDefault) {
            if ($isDefault) {
                // Mevcut varsayılan depoların varsayılan özelliğini kaldıralım
                Warehouse::where('user_id', $userId)->update(['is_default' => false]);
            }

            return Warehouse::create([
                'user_id'    => $userId,
                'name'       => $name,
                'code'       => $code,
                'is_default' => $isDefault,
                'is_active'  => true,
            ]);
        });
    }

    /**
     * Stok Hareketi Kaydet ve Bakiyeyi Güncelle.
     *
     * @param array{
     *     user_id: int,
     *     warehouse_id?: int|null,
     *     stock_code: string,
     *     movement_type: string, // in_purchase, in_return, in_adjustment, out_sale, out_loss, out_adjustment
     *     direction: string, // in, out
     *     quantity: int,
     *     unit_cost?: float|null,
     *     source_type?: string|null,
     *     source_id?: int|null,
     *     description?: string|null,
     *     movement_date?: string|null,
     * } $data
     */
    public function recordMovement(array $data): StockMovement
    {
        $userId = (int) $data['user_id'];
        $qty = (int) $data['quantity'];

        if ($qty <= 0) {
            throw new InvalidArgumentException('Stok hareket miktarı sıfırdan büyük bir tam sayı olmalıdır.');
        }

        $direction = strtolower($data['direction']);
        if (!in_array($direction, ['in', 'out'])) {
            throw new InvalidArgumentException('Stok yönü sadece "in" veya "out" olabilir.');
        }

        $stockCode = trim($data['stock_code']);
        if ($stockCode === '') {
            throw new InvalidArgumentException('Stok kodu boş bırakılamaz.');
        }

        return DB::transaction(function () use ($data, $userId, $qty, $direction, $stockCode) {
            // Depo tespiti: belirtilmediyse varsayılan depoyu bul
            $warehouseId = $data['warehouse_id'] ?? null;
            if (!$warehouseId) {
                $defaultWarehouse = Warehouse::where('user_id', $userId)->where('is_default', true)->first();
                if (!$defaultWarehouse) {
                    // Varsayılan depo yoksa, ilk aktif depoyu seç veya oluştur
                    $defaultWarehouse = Warehouse::where('user_id', $userId)->where('is_active', true)->first();
                    if (!$defaultWarehouse) {
                        $defaultWarehouse = $this->createWarehouse($userId, 'Merkez Depo', 'depo-merkez', true);
                    }
                }
                $warehouseId = $defaultWarehouse->id;
            }

            // Depo doğrulaması
            $warehouse = Warehouse::where('user_id', $userId)->findOrFail($warehouseId);
            if (!$warehouse->is_active) {
                throw new InvalidArgumentException('Seçilen depo pasif durumda.');
            }

            // Products tablosunda master ürünü ara (varsa id bağlayalım)
            $product = Product::where('stok_kodu', $stockCode)->first();
            $productId = $product ? $product->id : null;

            // 1. Stok Hareketi Kaydı oluştur
            $movement = StockMovement::create([
                'user_id'       => $userId,
                'warehouse_id'  => $warehouseId,
                'product_id'    => $productId,
                'stock_code'    => $stockCode,
                'movement_type' => $data['movement_type'],
                'direction'     => $direction,
                'quantity'      => $qty,
                'unit_cost'     => $data['unit_cost'] ?? null,
                'source_type'   => $data['source_type'] ?? null,
                'source_id'     => $data['source_id'] ?? null,
                'description'   => $data['description'] ?? null,
                'movement_date' => $data['movement_date'] ?? now()->toDateString(),
            ]);

            // 2. Stok Bakiyesi Güncellemesi (stock_balances)
            $balance = StockBalance::firstOrNew([
                'user_id'      => $userId,
                'warehouse_id' => $warehouseId,
                'stock_code'   => $stockCode,
            ]);

            $balance->product_id = $productId; // Varsa ilişkiyi güncelle

            $signedChange = $direction === 'in' ? $qty : -$qty;
            $balance->quantity = (int) $balance->quantity + $signedChange;
            $balance->save();

            // Opsiyonel: mp_products tablosundaki stock_quantity alanını da güncel tut (senkronizasyon için)
            $mpProduct = MpProduct::where('user_id', $userId)->where('stock_code', $stockCode)->first();
            if ($mpProduct) {
                $mpProduct->stock_quantity = max(0, (int) $mpProduct->stock_quantity + $signedChange);
                $mpProduct->save();
            }

            return $movement;
        });
    }

    /**
     * Güncel Stok Seviyesini Getir.
     */
    public function getStockLevel(int $userId, string $stockCode, ?int $warehouseId = null): int
    {
        $query = StockBalance::where('user_id', $userId)
            ->where('stock_code', $stockCode);

        if ($warehouseId !== null) {
            $query->where('warehouse_id', $warehouseId);
        }

        return (int) $query->sum('quantity');
    }

    /**
     * Kritik Stok Seviyesi Kontrolü.
     */
    public function isCriticalStock(int $userId, string $stockCode, ?int $warehouseId = null): bool
    {
        $currentLevel = $this->getStockLevel($userId, $stockCode, $warehouseId);

        // mp_products'ta tanımlı eşik değerini bul
        $mpProduct = MpProduct::where('user_id', $userId)->where('stock_code', $stockCode)->first();
        $threshold = $mpProduct ? (int) $mpProduct->critical_stock_threshold : 5; // Varsayılan eşik: 5 adet

        return $currentLevel <= $threshold;
    }
}
