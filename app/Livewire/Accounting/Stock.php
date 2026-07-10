<?php

namespace App\Livewire\Accounting;

use App\Models\MpProduct;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\LegalEntity;
use App\Services\Accounting\StockService;
use Livewire\Component;
use Livewire\WithPagination;
use InvalidArgumentException;

class Stock extends Component
{
    use WithPagination;

    // Filters & Search
    public string $search = '';
    public string $filterWarehouse = '';
    public string $filterStatus = 'all'; // all, critical, out_of_stock, positive
    public string $filterDirection = 'all'; // all, in, out
    public string $filterMovementType = '';
    public string $filterDateFrom = '';
    public string $filterDateTo = '';

    // Sorting
    public string $sortColumn = 'stock_code';
    public string $sortDirection = 'asc';

    // Column customisation
    public array $visibleColumns = [
        'stock_code' => true,
        'product_name' => true,
        'warehouse_name' => true,
        'quantity' => true,
        'status' => true,
    ];

    // Create Warehouse Form
    public bool $showWarehouseForm = false;
    public string $warehouseName = '';
    public string $warehouseCode = '';
    public ?int $warehouseLegalEntityId = null;
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
    public string $movRef = '';
    public ?int $movLegalEntityId = null;

    // Messaging
    public string $message = '';
    public string $messageType = 'success';

    protected $queryString = [
        'search'          => ['except' => ''],
        'filterWarehouse' => ['except' => ''],
        'filterStatus'    => ['except' => 'all'],
        'filterDirection' => ['except' => 'all'],
        'filterDateFrom'  => ['except' => ''],
        'filterDateTo'    => ['except' => ''],
    ];

    public function toggleColumn(string $col): void
    {
        if (isset($this->visibleColumns[$col])) {
            $this->visibleColumns[$col] = !$this->visibleColumns[$col];
        }
    }

    public function sortTable(string $column): void
    {
        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn = $column;
            $this->sortDirection = 'asc';
        }
    }

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
                        ->where('code', strtolower(trim($value)))
                        ->exists();
                    if ($exists) {
                        $fail('Bu depo kodu zaten kullanımda.');
                    }
                }
            ],
            'warehouseLegalEntityId' => 'nullable|integer',
        ], [
            'warehouseName.required' => 'Depo adı zorunludur.',
            'warehouseCode.required' => 'Depo kodu zorunludur.',
        ]);

        try {
            $service = app(StockService::class);
            $service->createWarehouse(
                $userId,
                $this->warehouseName,
                $this->warehouseCode,
                $this->warehouseIsDefault,
                $this->warehouseLegalEntityId ?: null
            );

            $this->message = 'Depo başarıyla oluşturuldu.';
            $this->messageType = 'success';

            // Reset
            $this->warehouseName = '';
            $this->warehouseCode = '';
            $this->warehouseLegalEntityId = null;
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
            'movWarehouseId'   => 'required|integer',
            'movStockCode'     => 'required|string',
            'movQuantity'      => 'required|integer|min:1',
            'movUnitCost'      => 'nullable|numeric|min:0',
            'movRef'           => 'nullable|string',
            'movLegalEntityId' => 'nullable|integer',
        ], [
            'movWarehouseId.required' => 'Depo seçimi zorunludur.',
            'movStockCode.required'   => 'Ürün seçimi zorunludur.',
            'movQuantity.min'         => 'Miktar en az 1 olmalıdır.',
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

            $sourceKey = 'manual_stock_' . $userId . '_' . microtime(true) . '_' . bin2hex(random_bytes(4));

            $service->recordMovement([
                'user_id'          => $userId,
                'warehouse_id'     => $this->movWarehouseId,
                'stock_code'       => $this->movStockCode,
                'movement_type'    => $this->movType,
                'direction'        => $this->movDirection,
                'quantity'         => $this->movQuantity,
                'unit_cost'        => $this->movUnitCost ?: null,
                'description'      => $this->movDescription ?: null,
                'reference_number' => $this->movRef ?: null,
                'legal_entity_id'  => $this->movLegalEntityId ?: null,
                'movement_date'    => now()->toDateString(),
                'source_key'       => $sourceKey,
            ]);
            $this->message = 'Stok hareketi başarıyla kaydedildi.';
            $this->messageType = 'success';

            // Reset
            $this->movStockCode = '';
            $this->movQuantity = 1;
            $this->movUnitCost = 0.0;
            $this->movDescription = '';
            $this->movRef = '';
            $this->movLegalEntityId = null;
            $this->showMovementForm = false;
        } catch (\Exception $e) {
            $this->message = 'Stok hareketi kaydedilirken hata: ' . $e->getMessage();
            $this->messageType = 'error';
        }
    }

    public function voidMovement(int $movementId): void
    {
        try {
            $movement = StockMovement::where('user_id', auth()->id())->findOrFail($movementId);
            app(StockService::class)->voidMovement($movement, 'Kullanıcı tarafından iptal edildi.', auth()->id());

            $this->message = 'Stok hareketi başarıyla iptal edildi (Void).';
            $this->messageType = 'success';
        } catch (\Exception $e) {
            $this->message = 'İptal sırasında hata: ' . $e->getMessage();
            $this->messageType = 'error';
        }
    }

    public function getWarehousesProperty()
    {
        return Warehouse::where('user_id', auth()->id())->active()->get();
    }


    public function getLegalEntitiesProperty()
    {
        return LegalEntity::where('user_id', auth()->id())->active()->get();
    }

    public function getProductsProperty()
    {
        return MpProduct::where('user_id', auth()->id())
            ->orderBy('product_name')
            ->get();
    }

    public function getStockSummaryProperty()
    {
        return app(StockService::class)->getStockSummary(
            auth()->id(),
            $this->filterWarehouse ?: null
        );
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

        // Status Filter
        if ($this->filterStatus === 'critical') {
            $query->whereRaw('quantity <= COALESCE(
                (SELECT critical_stock_threshold FROM mp_products WHERE mp_products.user_id = stock_balances.user_id AND mp_products.stock_code = stock_balances.stock_code),
                5
            )');
        } elseif ($this->filterStatus === 'out_of_stock') {

            $query->where('quantity', '<=', 0);
        } elseif ($this->filterStatus === 'positive') {
            $query->where('quantity', '>', 0);
        }

        // Order
        if (in_array($this->sortColumn, ['stock_code', 'quantity'])) {
            $query->orderBy($this->sortColumn, $this->sortDirection);
        }

        return $query->paginate(10, ['*'], 'balancesPage');
    }

    public function getStockMovementsProperty()
    {
        $userId = auth()->id();
        $query = StockMovement::where('user_id', $userId)
            ->with(['warehouse', 'product']);

        if ($this->filterWarehouse !== '') {
            $query->where('warehouse_id', $this->filterWarehouse);
        }

        if ($this->filterDirection !== 'all') {
            $query->where('direction', $this->filterDirection);
        }

        if ($this->filterMovementType !== '') {
            $query->where('movement_type', $this->filterMovementType);
        }

        if ($this->filterDateFrom !== '') {
            $query->whereDate('movement_date', '>=', $this->filterDateFrom);
        }

        if ($this->filterDateTo !== '') {
            $query->whereDate('movement_date', '<=', $this->filterDateTo);
        }

        return $query->orderByDesc('id')
            ->paginate(10, ['*'], 'movementsPage');
    }

    public function render()
    {
        // Feature flag protection
        if (!config('marketplace.features.accounting_enabled')) {
            abort(404);
        }

        return view('livewire.accounting.stock')
            ->layout('layouts.app');
    }
}
