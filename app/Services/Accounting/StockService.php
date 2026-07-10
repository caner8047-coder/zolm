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
    public function createWarehouse(int $userId, string $name, string $code, bool $isDefault = false, ?int $legalEntityId = null): Warehouse
    {
        $normalizedCode = strtolower(trim($code));

        if ($legalEntityId !== null) {
            \App\Models\LegalEntity::where('user_id', $userId)->findOrFail($legalEntityId);
        }

        $exists = Warehouse::where('user_id', $userId)->where('code', $normalizedCode)->exists();
        if ($exists) {
            throw new InvalidArgumentException('Bu depo kodu zaten kullanımda.');
        }

        return DB::transaction(function () use ($userId, $name, $normalizedCode, $isDefault, $legalEntityId) {
            if ($isDefault) {
                // Mevcut varsayılan depoların varsayılan özelliğini kaldıralım
                Warehouse::where('user_id', $userId)->update(['is_default' => false]);
            }

            return Warehouse::create([
                'user_id'         => $userId,
                'name'            => $name,
                'code'            => $normalizedCode,
                'is_default'      => $isDefault,
                'is_active'       => true,
                'legal_entity_id' => $legalEntityId,
            ]);
        });
    }

    /**
     * Depo ID'sini çözümler. Belirtilmemişse varsayılan veya ilk aktif depoyu döner.
     */
    public function resolveWarehouseId(int $userId, ?int $warehouseId = null): int
    {
        if ($warehouseId) {
            $warehouse = Warehouse::where('user_id', $userId)->find($warehouseId);
            if (!$warehouse) {
                throw new InvalidArgumentException('Seçilen depo bu kullanıcıya ait değil veya mevcut değil.');
            }
            if (!$warehouse->is_active) {
                throw new InvalidArgumentException('Seçilen depo pasif durumda.');
            }
            return $warehouse->id;
        }

        $defaultWarehouse = Warehouse::where('user_id', $userId)->where('is_default', true)->where('is_active', true)->first();
        if (!$defaultWarehouse) {
            $defaultWarehouse = Warehouse::where('user_id', $userId)->where('is_active', true)->first();
            if (!$defaultWarehouse) {
                $defaultWarehouse = $this->createWarehouse($userId, 'Merkez Depo', 'depo-merkez', true, null);
            }
        }

        return $defaultWarehouse->id;
    }

    /**
     * Stok Hareketi Kaydet ve Bakiyeyi Güncelle.
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

        if ($direction === 'in' && !str_starts_with($data['movement_type'], 'in_')) {
            throw new InvalidArgumentException('Giriş yönlü hareket tipi "in_" ile başlamalıdır.');
        }
        if ($direction === 'out' && !str_starts_with($data['movement_type'], 'out_')) {
            throw new InvalidArgumentException('Çıkış yönlü hareket tipi "out_" ile başlamalıdır.');
        }

        $stockCode = trim($data['stock_code']);
        if ($stockCode === '') {
            throw new InvalidArgumentException('Stok kodu boş bırakılamaz.');
        }

        // Depo tespiti: belirtilmediyse varsayılan depoyu bul
        $warehouseId = $this->resolveWarehouseId($userId, $data['warehouse_id'] ?? null);

        // Depo doğrulaması
        $warehouse = Warehouse::where('user_id', $userId)->findOrFail($warehouseId);
        if (!$warehouse->is_active) {
            throw new InvalidArgumentException('Seçilen depo pasif durumda.');
        }

        // Legal Entity kontrolü ve çözümlenmesi
        $legalEntityId = $data['legal_entity_id'] ?? null;
        if ($legalEntityId !== null) {
            \App\Models\LegalEntity::where('user_id', $userId)->findOrFail($legalEntityId);
            if ($warehouse->legal_entity_id !== null && (int)$warehouse->legal_entity_id !== (int)$legalEntityId) {
                throw new InvalidArgumentException('Seçilen yasal birlik, deponun yasal birliği ile çakışıyor.');
            }
        } else {
            if ($warehouse->legal_entity_id !== null) {
                $legalEntityId = $warehouse->legal_entity_id;
            }
        }

        // Idempotency check variable
        $sourceKey = $data['source_key'] ?? null;

        return DB::transaction(function () use ($data, $userId, $qty, $direction, $stockCode, $warehouseId, $legalEntityId, $sourceKey) {
            // 1. Idempotency kontrolü (transaction içinde ve locked, status filtresi olmadan)
            if ($sourceKey !== null && $sourceKey !== '') {
                $existing = StockMovement::where('user_id', $userId)
                    ->where('source_key', $sourceKey)
                    ->lockForUpdate()
                    ->first();
                if ($existing) {
                    return $existing;
                }
            }

            // 2. Stok bakiyesi kilitlemesi (lockForUpdate)
            $balance = StockBalance::where('user_id', $userId)
                ->where('warehouse_id', $warehouseId)
                ->where('stock_code', $stockCode)
                ->lockForUpdate()
                ->first();

            // 3. Çıkış hareketi için bakiye kontrolü
            if ($direction === 'out') {
                $currentStock = $balance ? (int) $balance->quantity : 0;
                if ($currentStock < $qty) {
                    throw new InvalidArgumentException("Yetersiz stok bakiyesi! Seçilen depodaki mevcut stok: {$currentStock}, istenen çıkış: {$qty}.");
                }
            }

            // Products tablosunda master ürünü ara (varsa id bağlayalım)
            $product = Product::where('stok_kodu', $stockCode)->first();
            $productId = $product ? $product->id : null;

            // 4. Stok Hareketi Kaydı oluştur
            $movement = StockMovement::create([
                'user_id'          => $userId,
                'warehouse_id'     => $warehouseId,
                'product_id'       => $productId,
                'stock_code'       => $stockCode,
                'movement_type'    => $data['movement_type'],
                'direction'        => $direction,
                'quantity'         => $qty,
                'unit_cost'        => $data['unit_cost'] ?? null,
                'source_type'      => $data['source_type'] ?? null,
                'source_id'        => $data['source_id'] ?? null,
                'source_key'       => $sourceKey,
                'reference_number' => $data['reference_number'] ?? null,
                'description'      => $data['description'] ?? null,
                'movement_date'    => $data['movement_date'] ?? now()->toDateString(),
                'legal_entity_id'  => $legalEntityId,
                'status'           => 'posted',
                'posted_at'        => now(),
            ]);

            // 5. Stok Bakiyesi Güncellemesi (stock_balances)
            if (!$balance) {
                $balance = new StockBalance([
                    'user_id'      => $userId,
                    'warehouse_id' => $warehouseId,
                    'stock_code'   => $stockCode,
                    'quantity'     => 0,
                ]);
            }

            $balance->product_id = $productId; // Varsa ilişkiyi güncelle

            $signedChange = $direction === 'in' ? $qty : -$qty;
            $balance->quantity = (int) $balance->quantity + $signedChange;
            $balance->save();

            // 6. mp_products tablosundaki stock_quantity alanını yeniden hesapla ve senkronize et
            $this->syncProductStockQuantity($userId, $stockCode);

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

    /**
     * Stok Hareketini İptal Et (Void).
     */
    public function voidMovement(StockMovement $movement, ?string $reason = null, ?int $userId = null): StockMovement
    {
        $actorUserId = $userId ?? auth()->id();
        if ($actorUserId === null) {
            throw new InvalidArgumentException('İşlem yapan kullanıcı bilgisi bulunamadı.');
        }

        if ((int)$movement->user_id !== (int)$actorUserId) {
            throw new InvalidArgumentException('Bu hareket üzerinde işlem yapma yetkiniz yok.');
        }

        if ($movement->status === 'voided') {
            throw new InvalidArgumentException('Bu hareket zaten iptal edilmiş.');
        }

        return DB::transaction(function () use ($movement, $reason) {
            $balance = StockBalance::where('user_id', $movement->user_id)
                ->where('warehouse_id', $movement->warehouse_id)
                ->where('stock_code', $movement->stock_code)
                ->first();

            if ($movement->direction === 'in') {
                // Giriş hareketi iptali stoğu azaltır
                if (!$balance || $balance->quantity < $movement->quantity) {
                    throw new InvalidArgumentException('Stok hareketi iptal edilemez, depo stoku negatife düşecektir.');
                }
                $balance->quantity -= $movement->quantity;
                $balance->save();
            } else {
                // Çıkış hareketi iptali stoğu artırır
                if (!$balance) {
                    $product = Product::where('stok_kodu', $movement->stock_code)->first();
                    $balance = new StockBalance([
                        'user_id'      => $movement->user_id,
                        'warehouse_id' => $movement->warehouse_id,
                        'stock_code'   => $movement->stock_code,
                        'product_id'   => $product ? $product->id : null,
                        'quantity'     => 0,
                    ]);
                }
                $balance->quantity += $movement->quantity;
                $balance->save();
            }

            $movement->update([
                'status'      => 'voided',
                'voided_at'   => now(),
                'void_reason' => $reason,
            ]);

            $this->syncProductStockQuantity($movement->user_id, $movement->stock_code);

            return $movement->fresh();
        });
    }

    /**
     * Stok Özetini ve KPI metriklerini getirir.
     */
    public function getStockSummary(int $userId, ?int $warehouseId = null): array
    {
        $balances = StockBalance::where('user_id', $userId)
            ->when($warehouseId, fn($q) => $q->where('warehouse_id', $warehouseId))
            ->get();

        $totalSku = $balances->pluck('stock_code')->unique()->count();
        $totalQuantity = $balances->sum('quantity');

        $criticalCount = 0;
        $outOfStockCount = 0;
        $inventoryValue = 0.0;

        $mpProducts = MpProduct::where('user_id', $userId)->get()->keyBy('stock_code');

        $skuBalances = [];
        foreach ($balances as $b) {
            $skuBalances[$b->stock_code] = ($skuBalances[$b->stock_code] ?? 0) + $b->quantity;
        }

        foreach ($mpProducts as $code => $prod) {
            $qty = $skuBalances[$code] ?? 0;
            if ($qty <= 0) {
                $outOfStockCount++;
            }
            $threshold = (int) ($prod->critical_stock_threshold ?? 5);
            if ($qty > 0 && $qty <= $threshold) {
                $criticalCount++;
            }
            $inventoryValue += $qty * (float) ($prod->cogs ?? 0.0);
        }

        $warehouseCount = Warehouse::where('user_id', $userId)->where('is_active', true)->count();

        return [
            'total_sku'          => $totalSku,
            'total_quantity'     => $totalQuantity,
            'critical_count'     => $criticalCount,
            'out_of_stock_count' => $outOfStockCount,
            'warehouse_count'    => $warehouseCount,
            'inventory_value'    => $inventoryValue,
        ];
    }

    /**
     * Bir ürünün hareket geçmişini getirir.
     */
    public function getProductMovementHistory(int $userId, string $stockCode, ?int $warehouseId = null)
    {
        return StockMovement::where('user_id', $userId)
            ->where('stock_code', $stockCode)
            ->where('status', 'posted')
            ->when($warehouseId, fn($q) => $q->where('warehouse_id', $warehouseId))
            ->orderByDesc('movement_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * mp_products tablosundaki aggregate stok adedini senkronize eder.
     */
    protected function syncProductStockQuantity(int $userId, string $stockCode): void
    {
        $totalStock = (int) StockBalance::where('user_id', $userId)
            ->where('stock_code', $stockCode)
            ->sum('quantity');

        $mpProduct = MpProduct::where('user_id', $userId)->where('stock_code', $stockCode)->first();
        if ($mpProduct) {
            $mpProduct->stock_quantity = max(0, $totalStock);
            $mpProduct->save();
        }
    }

}
