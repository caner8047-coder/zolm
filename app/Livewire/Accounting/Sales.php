<?php

namespace App\Livewire\Accounting;

use App\Models\LegalEntity;
use App\Models\MpProduct;
use App\Models\Party;
use App\Models\SalesOrder;
use App\Services\Accounting\StockService;
use App\Services\Accounting\TradeService;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;

class Sales extends Component
{
    use WithPagination;

    // Filters
    public string $search = '';
    public string $filterStatus = '';
    public ?int $filterPartyId = null;
    public ?int $filterLegalEntityId = null;
    public string $filterDateFrom = '';
    public string $filterDateTo = '';
    public bool $filterStockRisk = false;

    // Create Form Modal Toggle
    public bool $showCreateForm = false;

    // Header Inputs
    public ?int $partyId = null;
    public ?int $legalEntityId = null;
    public ?int $warehouseId = null;
    public string $documentNumber = '';
    public string $orderDate = '';
    public string $description = '';
    public float $discountAmount = 0.00;

    // Item rows
    public array $items = [];

    // Cancellation Reason Modal
    public bool $showCancelModal = false;
    public ?int $cancellingOrderId = null;
    public string $cancelReason = '';

    // Messaging
    public string $message = '';
    public string $messageType = 'success';

    // Sorting & Visibility
    public string $sortColumn = 'id';
    public string $sortDirection = 'desc';
    public array $visibleColumns = ['id', 'document_number', 'order_date', 'party', 'description', 'total_amount', 'status', 'action'];

    public static array $sortableColumns = [
        'id' => 'id',
        'document_number' => 'document_number',
        'order_date' => 'order_date',
        'total_amount' => 'total_amount',
        'status' => 'status',
    ];

    protected $queryString = [
        'search' => ['except' => ''],
        'filterStatus' => ['except' => ''],
        'filterPartyId' => ['except' => null],
        'filterLegalEntityId' => ['except' => null],
        'filterDateFrom' => ['except' => ''],
        'filterDateTo' => ['except' => ''],
        'filterStockRisk' => ['except' => false],
    ];

    public function mount(): void
    {
        $this->orderDate = now()->toDateString();
        $this->resetItems();

        // Default warehouse'u otomatik seç
        $default = \App\Models\Warehouse::where('user_id', auth()->id())
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
        if (!$default) {
            $default = \App\Models\Warehouse::where('user_id', auth()->id())
                ->where('is_active', true)
                ->orderBy('id')
                ->first();
        }
        $this->warehouseId = $default?->id;
    }

    public function resetItems(): void
    {
        $this->items = [
            ['stock_code' => '', 'quantity' => 1, 'unit_price' => 0.0, 'vat_rate' => 20.00, 'discount_rate' => 0.00],
        ];
    }

    public function addItem(): void
    {
        $this->items[] = ['stock_code' => '', 'quantity' => 1, 'unit_price' => 0.0, 'vat_rate' => 20.00, 'discount_rate' => 0.00];
    }

    public function removeItem(int $index): void
    {
        if (count($this->items) <= 1) {
            $this->message = 'Satış siparişinde en az 1 satır bulunmalıdır.';
            $this->messageType = 'error';
            return;
        }
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function getSubtotalProperty(): float
    {
        return array_reduce($this->items, function ($carry, $item) {
            return $carry + ((int) ($item['quantity'] ?? 0) * (float) ($item['unit_price'] ?? 0));
        }, 0.0);
    }

    public function getDiscountTotalProperty(): float
    {
        $lineDisc = array_reduce($this->items, function ($carry, $item) {
            $base = (int) ($item['quantity'] ?? 0) * (float) ($item['unit_price'] ?? 0);
            $disc = (float) ($item['discount_rate'] ?? 0.00);
            return $carry + ($base * $disc / 100);
        }, 0.0);

        return round($lineDisc + $this->discountAmount, 2);
    }

    public function getVatTotalProperty(): float
    {
        return array_reduce($this->items, function ($carry, $item) {
            $base = (int) ($item['quantity'] ?? 0) * (float) ($item['unit_price'] ?? 0);
            $disc = (float) ($item['discount_rate'] ?? 0.00);
            $vatRate = (float) ($item['vat_rate'] ?? 20.00);
            $discountedBase = $base - ($base * $disc / 100);
            return $carry + ($discountedBase * $vatRate / 100);
        }, 0.0);
    }

    public function getTotalProperty(): float
    {
        return round($this->subtotal - $this->discountTotal + $this->vatTotal, 2);
    }

    public function createSalesOrder(): void
    {
        $userId = auth()->id();

        $this->validate([
            'partyId' => 'required|integer',
            'documentNumber' => 'required|string|max:50',
            'orderDate' => 'required|date',
            'items.*.stock_code' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0.00',
            'items.*.discount_rate' => 'nullable|numeric|min:0|max:100',
            'discountAmount' => 'nullable|numeric|min:0',
        ], [
            'partyId.required' => 'Müşteri (Cari) seçimi zorunludur.',
            'documentNumber.required' => 'Belge numarası zorunludur.',
            'items.*.stock_code.required' => 'Ürün seçimi zorunludur.',
            'items.*.quantity.min' => 'Miktar en az 1 olmalıdır.',
            'items.*.unit_price.min' => 'Birim fiyat sıfır veya sıfırdan büyük olmalıdır.',
        ]);

        // Tenant validations
        $party = Party::where('user_id', $userId)->find($this->partyId);
        if (!$party) {
            $this->message = 'Seçilen cari bu kullanıcıya ait değil.';
            $this->messageType = 'error';
            return;
        }

        if ($this->legalEntityId) {
            $le = LegalEntity::where('user_id', $userId)->find($this->legalEntityId);
            if (!$le) {
                $this->message = 'Seçilen şirket bu kullanıcıya ait değil.';
                $this->messageType = 'error';
                return;
            }
        }

        if ($this->warehouseId) {
            $wh = \App\Models\Warehouse::where('user_id', $userId)->find($this->warehouseId);
            if (!$wh) {
                $this->message = 'Seçilen depo bu kullanıcıya ait değil.';
                $this->messageType = 'error';
                return;
            }
        }

        // Validate products
        foreach ($this->items as $item) {
            $mpProduct = MpProduct::where('user_id', $userId)->where('stock_code', $item['stock_code'])->first();
            if (!$mpProduct) {
                $this->message = 'Seçilen ürünlerden biri bu kullanıcıya ait değil.';
                $this->messageType = 'error';
                return;
            }
        }

        try {
            $tradeService = app(TradeService::class);
            $tradeService->createSalesOrder([
                'user_id' => $userId,
                'party_id' => (int) $this->partyId,
                'legal_entity_id' => $this->legalEntityId ? (int) $this->legalEntityId : null,
                'warehouse_id' => $this->warehouseId ? (int) $this->warehouseId : null,
                'document_number' => $this->documentNumber,
                'order_date' => $this->orderDate,
                'description' => $this->description ?: null,
                'discount_amount' => $this->discountAmount,
            ], $this->items);

            $this->message = 'Satış siparişi taslağı başarıyla oluşturuldu.';
            $this->messageType = 'success';

            // Reset
            $this->partyId = null;
            $this->legalEntityId = null;
            $this->warehouseId = $this->warehouses->where('is_default', true)->first()?->id
                ?? $this->warehouses->first()?->id;
            $this->documentNumber = '';
            $this->description = '';
            $this->discountAmount = 0.00;
            $this->resetItems();
            $this->showCreateForm = false;
        } catch (\Exception $e) {
            $this->message = 'Sipariş oluşturulurken hata: ' . $e->getMessage();
            $this->messageType = 'error';
        }
    }

    public function approveOrder(int $orderId): void
    {
        $userId = auth()->id();
        $order = SalesOrder::where('user_id', $userId)->with('items')->findOrFail($orderId);

        if ($order->status !== 'draft') {
            $this->message = 'Sadece taslak durumundaki siparişler onaylanabilir.';
            $this->messageType = 'error';
            return;
        }

        try {
            $tradeService = app(TradeService::class);
            $tradeService->approveSalesOrder($order);

            $this->message = 'Satış siparişi onaylandı, cari alacağı işlendi ve stok çıkışı yapıldı.';
            $this->messageType = 'success';
        } catch (\Exception $e) {
            $this->message = 'Sipariş onaylanırken hata: ' . $e->getMessage();
            $this->messageType = 'error';
        }
    }

    public function confirmCancel(int $orderId): void
    {
        $userId = auth()->id();
        $order = SalesOrder::where('user_id', $userId)->findOrFail($orderId);

        if ($order->status !== 'approved') {
            $this->message = 'Sadece onaylanmış satış siparişleri iptal edilebilir.';
            $this->messageType = 'error';
            return;
        }

        $this->cancellingOrderId = $orderId;
        $this->cancelReason = '';
        $this->showCancelModal = true;
    }

    public function cancelOrder(): void
    {
        if (!$this->cancellingOrderId) {
            return;
        }

        $userId = auth()->id();
        $order = SalesOrder::where('user_id', $userId)->with('items')->findOrFail($this->cancellingOrderId);

        try {
            $tradeService = app(TradeService::class);
            $tradeService->cancelSalesOrder($order, $this->cancelReason ?: null);

            $this->message = 'Satış siparişi iptal edildi, cari kaydı geri çekildi ve stoklar iade edildi.';
            $this->messageType = 'success';
            $this->showCancelModal = false;
            $this->cancellingOrderId = null;
            $this->cancelReason = '';
        } catch (\Exception $e) {
            $this->message = 'Sipariş iptal edilirken hata: ' . $e->getMessage();
            $this->messageType = 'error';
            $this->showCancelModal = false;
            $this->cancellingOrderId = null;
            $this->cancelReason = '';
        }
    }

    public function getPartiesProperty()
    {
        return Party::where('user_id', auth()->id())
            ->whereHas('roles', function ($q) {
                $q->where('role', 'customer');
            })
            ->orderBy('display_name')
            ->get();
    }

    public function getLegalEntitiesProperty()
    {
        return LegalEntity::where('user_id', auth()->id())->active()->orderBy('name')->get();
    }

    public function getProductsProperty()
    {
        return MpProduct::where('user_id', auth()->id())->orderBy('product_name')->get();
    }

    public function getWarehousesProperty()
    {
        return \App\Models\Warehouse::where('user_id', auth()->id())
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    public function sortTable(string $column): void
    {
        if (!array_key_exists($column, static::$sortableColumns)) {
            return;
        }

        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function toggleColumn(string $column): void
    {
        if (in_array($column, $this->visibleColumns, true)) {
            if (count($this->visibleColumns) === 1) {
                return;
            }
            $this->visibleColumns = array_values(array_diff($this->visibleColumns, [$column]));
        } else {
            $this->visibleColumns[] = $column;
        }
    }

    public function getColumnDefsProperty(): array
    {
        return [
            'id' => 'No',
            'document_number' => 'Belge No',
            'order_date' => 'Tarih',
            'party' => 'Müşteri / Cari',
            'description' => 'Açıklama',
            'total_amount' => 'Toplam Tutar',
            'status' => 'Durum',
            'action' => 'Aksiyon',
        ];
    }

    // KPIs & Metrics
    public function getKpisProperty(): array
    {
        $userId = auth()->id();

        // 1. Taslak sayısı
        $draftCount = SalesOrder::where('user_id', $userId)
            ->where('status', 'draft')
            ->count();

        // 2. Onaylı satış toplamı
        $approvedTotal = SalesOrder::where('user_id', $userId)
            ->where('status', 'approved')
            ->sum('total_amount');

        // 3. İptal satış toplamı
        $cancelledTotal = SalesOrder::where('user_id', $userId)
            ->where('status', 'cancelled')
            ->sum('total_amount');

        // 4. Açık alacak toplamı
        $openReceivableTotal = \App\Models\Receivable::where('user_id', $userId)
            ->whereIn('status', ['open', 'partially_paid'])
            ->sum(DB::raw('amount - paid_amount'));

        // 5. Stok riski taşıyan taslak sayısı
        $draftOrders = SalesOrder::where('user_id', $userId)
            ->where('status', 'draft')
            ->with(['items'])
            ->get();
        $stockService = app(StockService::class);
        $stockRiskDraftCount = 0;
        foreach ($draftOrders as $order) {
            $warehouseId = $order->warehouse_id ?: $stockService->resolveWarehouseId($userId, null);
            foreach ($order->items as $item) {
                $currentStock = $stockService->getStockLevel($userId, $item->stock_code, $warehouseId);
                if ($currentStock < $item->quantity) {
                    $stockRiskDraftCount++;
                    break;
                }
            }
        }

        return [
            'draftCount' => $draftCount,
            'approvedTotal' => $approvedTotal,
            'cancelledTotal' => $cancelledTotal,
            'openReceivableTotal' => $openReceivableTotal,
            'stockRiskDraftCount' => $stockRiskDraftCount,
        ];
    }

    public function getOrdersProperty()
    {
        $query = SalesOrder::where('user_id', auth()->id())
            ->with(['party', 'legalEntity', 'items']);

        // Search filter (scoped correctly to avoid leaks)
        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('document_number', 'like', "%{$this->search}%")
                  ->orWhere('description', 'like', "%{$this->search}%")
                  ->orWhereHas('party', function ($qp) {
                      $qp->where('display_name', 'like', "%{$this->search}%");
                  });
            });
        }

        // Status filter
        if ($this->filterStatus !== '') {
            $query->where('status', $this->filterStatus);
        }

        // Party / Customer filter
        if ($this->filterPartyId) {
            $query->where('party_id', $this->filterPartyId);
        }

        // Legal Entity filter
        if ($this->filterLegalEntityId) {
            $query->where('legal_entity_id', $this->filterLegalEntityId);
        }

        // Date range filter
        if ($this->filterDateFrom !== '') {
            $query->where('order_date', '>=', $this->filterDateFrom);
        }
        if ($this->filterDateTo !== '') {
            $query->where('order_date', '<=', $this->filterDateTo);
        }

        // Stock Risk filter (Only show draft orders with insufficient stock)
        if ($this->filterStockRisk) {
            $draftIdsWithRisk = [];
            $draftOrders = SalesOrder::where('user_id', auth()->id())
                ->where('status', 'draft')
                ->with(['items'])
                ->get();
            $stockService = app(StockService::class);
            foreach ($draftOrders as $o) {
                $whId = $o->warehouse_id ?: $stockService->resolveWarehouseId($o->user_id, null);
                foreach ($o->items as $item) {
                    if ($stockService->getStockLevel($o->user_id, $item->stock_code, $whId) < $item->quantity) {
                        $draftIdsWithRisk[] = $o->id;
                        break;
                    }
                }
            }
            $query->whereIn('id', $draftIdsWithRisk);
        }

        $sortCol = $this->sortColumn;
        if (!array_key_exists($sortCol, static::$sortableColumns)) {
            $sortCol = 'id';
        }

        return $query->orderBy($sortCol, $this->sortDirection)
            ->paginate(15);
    }

    public function render()
    {
        return view('livewire.accounting.sales')
            ->layout('layouts.app');
    }
}
