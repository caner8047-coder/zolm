<?php

namespace App\Livewire\Accounting;

use App\Models\MpProduct;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\Accounting\StockService;
use Livewire\Component;
use Livewire\WithPagination;

class Stock extends Component
{
    use WithPagination;

    // Filters
    public string $search = '';
    public string $filterWarehouse = '';

    // Create Warehouse Form
    public bool $showWarehouseForm = false;
    public string $warehouseName = '';
    public string $warehouseCode = '';
    public bool $warehouseIsDefault = false;

    // Record Stock Movement Form
    public bool $showMovementForm = false;
    public string $movDirection = 'in';
    public string $movType = 'in_purchase';
    public ?int $movWarehouseId = null;
    public string $movStockCode = '';
    public int $movQuantity = 1;
    public float $movUnitCost = 0.0;
    public string $movDescription = '';

    // Messaging
    public string $message = '';
    public string $messageType = 'success';

    protected $queryString = [
        'search' => ['except' => ''],
        'filterWarehouse' => ['except' => ''],
    ];

    public function createWarehouse(): void
    {
        $userId = auth()->id();

        $this->validate([
            'warehouseName' => 'required|string|max:100',
            'warehouseCode' => [
                'required',
                'string',
                'max:50',
                function ($attribute, $value, $fail) use ($userId) {
                    $exists = Warehouse::where('user_id', $userId)
                        ->where('code', $value)
                        ->exists();
                    if ($exists) {
                        $fail('Bu depo kodu zaten kullanımda.');
                    }
                }
            ],
        ], [
            'warehouseName.required' => 'Depo adı zorunludur.',
            'warehouseCode.required' => 'Depo kodu zorunludur.',
        ]);

        try {
            $service = app(StockService::class);
            $service->createWarehouse($userId, $this->warehouseName, $this->warehouseCode, $this->warehouseIsDefault);

            $this->message = 'Depo başarıyla oluşturuldu.';
            $this->messageType = 'success';

            // Reset
            $this->warehouseName = '';
            $this->warehouseCode = '';
            $this->warehouseIsDefault = false;
            $this->showWarehouseForm = false;
        } catch (\Exception $e) {
            $this->message = 'Depo oluşturulurken hata: ' . $e->getMessage();
            $this->messageType = 'error';
        }
    }

    public function updatedMovDirection(string $value): void
    {
        $this->movType = $value === 'in' ? 'in_purchase' : 'out_sale';
    }

    public function recordStockMovement(): void
    {
        $userId = auth()->id();

        $this->validate([
            'movWarehouseId' => 'required|integer',
            'movStockCode' => 'required|string',
            'movQuantity' => 'required|integer|min:1',
            'movUnitCost' => 'nullable|numeric|min:0',
        ], [
            'movWarehouseId.required' => 'Depo seçimi zorunludur.',
            'movStockCode.required' => 'Ürün seçimi zorunludur.',
            'movQuantity.min' => 'Miktar en az 1 olmalıdır.',
        ]);

        // Tenant checks
        $warehouse = Warehouse::where('user_id', $userId)->find($this->movWarehouseId);
        if (!$warehouse) {
            $this->message = 'Seçilen depo bu kullanıcıya ait değil.';
            $this->messageType = 'error';
            return;
        }

        $mpProduct = MpProduct::where('user_id', $userId)->where('stock_code', $this->movStockCode)->first();
        if (!$mpProduct) {
            $this->message = 'Seçilen ürün bu kullanıcıya ait değil.';
            $this->messageType = 'error';
            return;
        }

        try {
            $service = app(StockService::class);

            // Outflow balance guard check
            if ($this->movDirection === 'out') {
                $currentStock = $service->getStockLevel($userId, $this->movStockCode, $this->movWarehouseId);
                if ($currentStock < $this->movQuantity) {
                    $this->message = sprintf(
                        'Yetersiz stok bakiyesi! Seçilen depodaki mevcut stok: %d, istenen çıkış: %d.',
                        $currentStock,
                        $this->movQuantity
                    );
                    $this->messageType = 'error';
                    return;
                }
            }

            $service->recordMovement([
                'user_id' => $userId,
                'warehouse_id' => $this->movWarehouseId,
                'stock_code' => $this->movStockCode,
                'movement_type' => $this->movType,
                'direction' => $this->movDirection,
                'quantity' => $this->movQuantity,
                'unit_cost' => $this->movUnitCost ?: null,
                'description' => $this->movDescription ?: null,
                'movement_date' => now()->toDateString(),
            ]);

            $this->message = 'Stok hareketi başarıyla kaydedildi.';
            $this->messageType = 'success';

            // Reset
            $this->movStockCode = '';
            $this->movQuantity = 1;
            $this->movUnitCost = 0.0;
            $this->movDescription = '';
            $this->showMovementForm = false;
        } catch (\Exception $e) {
            $this->message = 'Stok hareketi kaydedilirken hata: ' . $e->getMessage();
            $this->messageType = 'error';
        }
    }

    public function getWarehousesProperty()
    {
        return Warehouse::where('user_id', auth()->id())->get();
    }

    public function getProductsProperty()
    {
        return MpProduct::where('user_id', auth()->id())
            ->orderBy('product_name')
            ->get();
    }

    public function getStockBalancesProperty()
    {
        $userId = auth()->id();
        $query = StockBalance::where('user_id', $userId)
            ->with(['warehouse', 'product']);

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('stock_code', 'like', "%{$this->search}%")
                  ->orWhereHas('product', function ($p) {
                      $p->where('product_name', 'like', "%{$this->search}%");
                  });
            });
        }

        if ($this->filterWarehouse !== '') {
            $query->where('warehouse_id', $this->filterWarehouse);
        }

        return $query->paginate(15);
    }

    public function getStockMovementsProperty()
    {
        return StockMovement::where('user_id', auth()->id())
            ->with(['warehouse', 'product'])
            ->orderByDesc('id')
            ->limit(15)
            ->get();
    }

    public function render()
    {
        return view('livewire.accounting.stock')
            ->layout('layouts.app');
    }
}
