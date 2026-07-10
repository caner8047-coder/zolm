<?php

namespace App\Livewire\Accounting;

use App\Models\LegalEntity;
use App\Models\MpProduct;
use App\Models\Party;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Warehouse;
use App\Services\Accounting\TradeService;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;

class Purchases extends Component
{
    use WithPagination;

    // -------------------------------------------------------
    // Filtreler
    // -------------------------------------------------------
    public string $search              = '';
    public string $filterStatus        = '';
    public ?int   $filterPartyId       = null;
    public ?int   $filterLegalEntityId = null;
    public ?int   $filterWarehouseId   = null;
    public string $filterDateFrom      = '';
    public string $filterDateTo        = '';

    // -------------------------------------------------------
    // Form Modal
    // -------------------------------------------------------
    public bool $showCreateForm = false;

    // -------------------------------------------------------
    // Başlık Girdileri
    // -------------------------------------------------------
    public ?int   $partyId        = null;
    public ?int   $legalEntityId  = null;
    public ?int   $warehouseId    = null;
    public string $documentNumber = '';
    public string $orderDate      = '';
    public string $description    = '';
    public float  $discountAmount = 0.00;

    // -------------------------------------------------------
    // Kalem Satırları
    // -------------------------------------------------------
    public array $items = [];

    // -------------------------------------------------------
    // İptal Nedeni Modal
    // -------------------------------------------------------
    public bool   $showCancelModal   = false;
    public ?int   $cancellingOrderId = null;
    public string $cancelReason      = '';

    // -------------------------------------------------------
    // Bildirim
    // -------------------------------------------------------
    public string $message     = '';
    public string $messageType = 'success';

    // -------------------------------------------------------
    // Sıralama & Görünürlük
    // -------------------------------------------------------
    public string $sortColumn    = 'id';
    public string $sortDirection = 'desc';
    public array  $visibleColumns = ['id', 'document_number', 'order_date', 'party', 'description', 'total_amount', 'status', 'action'];

    public static array $sortableColumns = [
        'id'              => 'id',
        'document_number' => 'document_number',
        'order_date'      => 'order_date',
        'total_amount'    => 'total_amount',
        'status'          => 'status',
    ];

    protected $queryString = [
        'search'              => ['except' => ''],
        'filterStatus'        => ['except' => ''],
        'filterPartyId'       => ['except' => null],
        'filterLegalEntityId' => ['except' => null],
        'filterWarehouseId'   => ['except' => null],
        'filterDateFrom'      => ['except' => ''],
        'filterDateTo'        => ['except' => ''],
    ];

    public function mount(): void
    {
        $this->orderDate = now()->toDateString();
        $this->resetItems();

        // Varsayılan depoyu otomatik seç
        $defaultWarehouse = Warehouse::where('user_id', auth()->id())
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();
        if ($defaultWarehouse) {
            $this->warehouseId = $defaultWarehouse->id;
        }
    }

    // -------------------------------------------------------
    // Kalem Yönetimi
    // -------------------------------------------------------

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
            $this->message     = 'Satın alma siparişinde en az 1 satır bulunmalıdır.';
            $this->messageType = 'error';
            return;
        }
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    // -------------------------------------------------------
    // Özet Hesaplamalar (computed)
    // -------------------------------------------------------

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
            $base        = (int) ($item['quantity'] ?? 0) * (float) ($item['unit_price'] ?? 0);
            $disc        = (float) ($item['discount_rate'] ?? 0.00);
            $vatRate     = (float) ($item['vat_rate'] ?? 20.00);
            $discounted  = $base - ($base * $disc / 100);
            return $carry + ($discounted * $vatRate / 100);
        }, 0.0);
    }

    public function getTotalProperty(): float
    {
        return round($this->subtotal - $this->discountTotal + $this->vatTotal, 2);
    }

    // -------------------------------------------------------
    // Aksiyon: Satın Alma Siparişi Oluştur
    // -------------------------------------------------------

    public function createPurchaseOrder(): void
    {
        $userId = auth()->id();

        $this->validate([
            'partyId'              => 'required|integer',
            'documentNumber'       => 'required|string|max:50',
            'orderDate'            => 'required|date',
            'items.*.stock_code'   => 'required|string',
            'items.*.quantity'     => 'required|integer|min:1',
            'items.*.unit_price'   => 'required|numeric|min:0.01',
            'items.*.discount_rate' => 'nullable|numeric|min:0|max:100',
            'discountAmount'       => 'nullable|numeric|min:0',
        ], [
            'partyId.required'          => 'Tedarikçi (Cari) seçimi zorunludur.',
            'documentNumber.required'   => 'Belge numarası zorunludur.',
            'items.*.stock_code.required' => 'Ürün seçimi zorunludur.',
            'items.*.quantity.min'       => 'Miktar en az 1 olmalıdır.',
            'items.*.unit_price.min'     => 'Birim fiyat sıfırdan büyük olmalıdır.',
            'discountAmount.min'         => 'İndirim tutarı negatif olamaz.',
        ]);

        // Tenant validasyonu
        $party = Party::where('user_id', $userId)->find($this->partyId);
        if (!$party) {
            $this->message     = 'Seçilen cari bu kullanıcıya ait değil.';
            $this->messageType = 'error';
            return;
        }

        if ($this->legalEntityId) {
            $le = LegalEntity::where('user_id', $userId)->active()->find($this->legalEntityId);
            if (!$le) {
                $this->message     = 'Seçilen şirket aktif değil veya bulunamadı.';
                $this->messageType = 'error';
                return;
            }
        }

        if ($this->warehouseId) {
            $wh = Warehouse::where('user_id', $userId)->where('is_active', true)->find($this->warehouseId);
            if (!$wh) {
                $this->message     = 'Seçilen depo bu kullanıcıya ait değil veya aktif değil.';
                $this->messageType = 'error';
                return;
            }
        }

        // Ürün tenant kontrolü
        foreach ($this->items as $item) {
            $mpProduct = MpProduct::where('user_id', $userId)->where('stock_code', $item['stock_code'])->first();
            if (!$mpProduct) {
                $this->message     = 'Seçilen ürünlerden biri bu kullanıcıya ait değil.';
                $this->messageType = 'error';
                return;
            }
        }

        try {
            $tradeService = app(TradeService::class);
            $tradeService->createPurchaseOrder([
                'user_id'         => $userId,
                'party_id'        => (int) $this->partyId,
                'legal_entity_id' => $this->legalEntityId ? (int) $this->legalEntityId : null,
                'warehouse_id'   => $this->warehouseId   ? (int) $this->warehouseId   : null,
                'document_number' => $this->documentNumber,
                'order_date'      => $this->orderDate,
                'description'     => $this->description ?: null,
                'discount_amount' => (float) $this->discountAmount,
            ], $this->items);

            $this->message     = 'Satın alma siparişi taslağı başarıyla oluşturuldu.';
            $this->messageType = 'success';
            $this->resetForm();
        } catch (\Exception $e) {
            $this->message     = 'Sipariş oluşturulurken hata: ' . $e->getMessage();
            $this->messageType = 'error';
        }
    }

    // -------------------------------------------------------
    // Aksiyon: Onayla
    // -------------------------------------------------------

    public function approveOrder(int $orderId): void
    {
        $userId = auth()->id();
        $order  = PurchaseOrder::where('user_id', $userId)->with('items')->findOrFail($orderId);

        if ($order->status !== 'draft') {
            $this->message     = 'Sadece taslak durumundaki siparişler onaylanabilir.';
            $this->messageType = 'error';
            return;
        }

        try {
            app(TradeService::class)->approvePurchaseOrder($order);
            $this->message     = 'Satın alma siparişi onaylandı, cari borcu işlendi ve stok girişi yapıldı.';
            $this->messageType = 'success';
        } catch (\Exception $e) {
            $this->message     = 'Sipariş onaylanırken hata: ' . $e->getMessage();
            $this->messageType = 'error';
        }
    }

    // -------------------------------------------------------
    // Aksiyon: İptal Et (Reason Modallı)
    // -------------------------------------------------------

    public function confirmCancel(int $orderId): void
    {
        $this->cancellingOrderId = $orderId;
        $this->cancelReason      = '';
        $this->showCancelModal   = true;
    }

    public function cancelOrder(?int $orderId = null): void
    {
        if ($orderId) {
            $this->cancellingOrderId = $orderId;
        }

        if (!$this->cancellingOrderId) {
            return;
        }

        $userId = auth()->id();
        $order  = PurchaseOrder::where('user_id', $userId)->with('items')->findOrFail($this->cancellingOrderId);

        if ($order->status !== 'approved') {
            $this->message     = 'Sadece onaylanmış satın alma siparişleri iptal edilebilir.';
            $this->messageType = 'error';
            $this->showCancelModal = false;
            return;
        }

        try {
            app(TradeService::class)->cancelPurchaseOrder($order, $this->cancelReason ?: null);
            $this->message     = 'Satın alma siparişi iptal edildi, cari kaydı geri çekildi ve stoklar iade edildi.';
            $this->messageType = 'success';
        } catch (\Exception $e) {
            $this->message     = 'Sipariş iptal edilirken hata: ' . $e->getMessage();
            $this->messageType = 'error';
        } finally {
            $this->showCancelModal = false;
            $this->cancellingOrderId = null;
            $this->cancelReason = '';
        }
    }

    // -------------------------------------------------------
    // Sıralama & Görünüm Metotları
    // -------------------------------------------------------

    public function sortTable(string $column): void
    {
        if (!array_key_exists($column, static::$sortableColumns)) {
            return;
        }

        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn    = $column;
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
            'id'              => 'No',
            'document_number' => 'Belge No',
            'order_date'      => 'Tarih',
            'party'           => 'Tedarikçi / Cari',
            'description'     => 'Açıklama',
            'total_amount'    => 'Toplam Tutar',
            'status'          => 'Durum',
            'action'          => 'Aksiyon',
        ];
    }

    // -------------------------------------------------------
    // Yardımcılar
    // -------------------------------------------------------

    protected function resetForm(): void
    {
        $this->partyId        = null;
        $this->legalEntityId  = null;
        $this->documentNumber = '';
        $this->description    = '';
        $this->discountAmount = 0.00;
        $this->resetItems();
        $this->showCreateForm = false;

        // Varsayılan depoyu koru
        $defaultWarehouse = Warehouse::where('user_id', auth()->id())
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();
        $this->warehouseId = $defaultWarehouse ? $defaultWarehouse->id : null;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatingFilterPartyId(): void
    {
        $this->resetPage();
    }

    public function updatingFilterLegalEntityId(): void
    {
        $this->resetPage();
    }

    public function updatingFilterWarehouseId(): void
    {
        $this->resetPage();
    }

    // -------------------------------------------------------
    // Computed Properties
    // -------------------------------------------------------

    public function getPartiesProperty()
    {
        return Party::where('user_id', auth()->id())
            ->whereHas('roles', fn ($q) => $q->where('role', 'supplier'))
            ->orderBy('display_name')
            ->get();
    }

    public function getPartiesForFilterProperty()
    {
        return Party::where('user_id', auth()->id())
            ->whereHas('roles', fn ($q) => $q->where('role', 'supplier'))
            ->orderBy('display_name')
            ->get();
    }

    public function getLegalEntitiesProperty()
    {
        return LegalEntity::where('user_id', auth()->id())->active()->orderBy('name')->get();
    }

    public function getWarehousesProperty()
    {
        return Warehouse::where('user_id', auth()->id())
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    public function getProductsProperty()
    {
        return MpProduct::where('user_id', auth()->id())->orderBy('product_name')->get();
    }

    public function getOrdersProperty()
    {
        $query = PurchaseOrder::where('user_id', auth()->id())
            ->with(['party', 'legalEntity', 'warehouse', 'items']);

        // Search filter with nested where to avoid tenant sızması
        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('document_number', 'like', "%{$this->search}%")
                  ->orWhere('description', 'like', "%{$this->search}%")
                  ->orWhereHas('party', function ($qp) {
                      $qp->where('display_name', 'like', "%{$this->search}%");
                  });
            });
        }

        // Status
        if ($this->filterStatus !== '') {
            $query->where('status', $this->filterStatus);
        }

        // Party filter
        if ($this->filterPartyId) {
            $query->where('party_id', $this->filterPartyId);
        }

        // Legal Entity filter
        if ($this->filterLegalEntityId) {
            // Guard
            $le = LegalEntity::where('user_id', auth()->id())->find($this->filterLegalEntityId);
            if ($le) {
                $query->where('legal_entity_id', $this->filterLegalEntityId);
            } else {
                $query->whereRaw('1=0'); // safe empty result if unauthorized ID
            }
        }

        // Warehouse filter
        if ($this->filterWarehouseId) {
            // Guard
            $wh = Warehouse::where('user_id', auth()->id())->find($this->filterWarehouseId);
            if ($wh) {
                $query->where('warehouse_id', $this->filterWarehouseId);
            } else {
                $query->whereRaw('1=0'); // safe empty result if unauthorized ID
            }
        }

        // Dates
        if ($this->filterDateFrom !== '') {
            $query->where('order_date', '>=', $this->filterDateFrom);
        }

        if ($this->filterDateTo !== '') {
            $query->where('order_date', '<=', $this->filterDateTo);
        }

        // Ordering dynamically
        $dbColumn = static::$sortableColumns[$this->sortColumn] ?? 'id';
        return $query->orderBy($dbColumn, $this->sortDirection)->paginate(15);
    }

    public function getKpisProperty(): array
    {
        $userId = auth()->id();
        $base   = PurchaseOrder::where('user_id', $userId);

        // Bu ay onaylanmış siparişlerin kalem miktarlarının toplamı
        $itemCount = PurchaseOrderItem::whereHas('purchaseOrder', function($q) use($userId) {
            $q->where('user_id', $userId)
              ->where('status', 'approved')
              ->whereMonth('order_date', now()->month)
              ->whereYear('order_date', now()->year);
        })->sum('quantity');

        return [
            'draftCount'         => (clone $base)->where('status', 'draft')->count(),
            'approvedTotal'      => (clone $base)->where('status', 'approved')->sum('total_amount'),
            'cancelledTotal'     => (clone $base)->where('status', 'cancelled')->sum('total_amount'),
            'openPayableTotal'   => \App\Models\Payable::where('user_id', $userId)->where('status', 'open')->sum('amount'),
            'itemCount'          => $itemCount,
        ];
    }

    public function render()
    {
        return view('livewire.accounting.purchases')
            ->layout('layouts.app');
    }
}
