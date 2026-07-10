<?php

namespace App\Livewire\Accounting;

use App\Models\Account;
use App\Models\LegalEntity;
use App\Models\MpProduct;
use App\Models\Party;
use App\Models\PosSale;
use App\Models\PosShift;
use App\Models\PosTerminal;
use App\Models\Warehouse;
use App\Services\Accounting\PosService;
use App\Services\Accounting\StockService;
use Livewire\Component;
use Livewire\WithPagination;

class Pos extends Component
{
    use WithPagination;

    // Aktif terminal ve vardiya
    public ?int $selectedTerminalId = null;
    public ?int $activeShiftId = null;

    // Terminal Oluşturma Formu
    public bool $showTerminalForm = false;
    public string $terminalName = '';
    public ?int $terminalWarehouseId = null;
    public ?int $terminalAccountId = null;
    public ?int $terminalLegalEntityId = null;

    // Vardiya Açılış & Kapanış
    public float $shiftOpeningBalance = 0.00;
    public bool $showShiftOpenForm = false;
    public ?int $shiftAccountId = null;
    public ?int $shiftLegalEntityId = null;

    public float $shiftClosingBalance = 0.00;
    public bool $showShiftCloseForm = false;

    // Sepet ve Arama
    public array $cart = [];
    public string $cartSearch = '';
    public string $paymentMethod = 'cash'; // cash, card, bank_transfer
    public ?int $selectedAccountId = null;
    public ?int $selectedPartyId = null;
    public ?int $selectedWarehouseId = null;
    public ?int $selectedLegalEntityId = null;

    // İptal Nedeni Modal
    public bool $showCancelModal = false;
    public ?int $cancellingSaleId = null;
    public string $cancelReason = '';

    // Mesajlaşma
    public string $message = '';
    public string $messageType = 'success'; // success, error

    // Tablo ve Sıralama Standartları
    public string $sortColumn = 'id';
    public string $sortDirection = 'desc';
    public array $visibleColumns = ['id', 'reference_number', 'party', 'payment_method', 'amount', 'status', 'action'];

    protected static array $sortableColumns = ['id', 'reference_number', 'payment_method', 'amount', 'status'];

    protected $queryString = [
        'selectedTerminalId' => ['except' => null],
    ];

    public function mount(): void
    {
        $userId = auth()->id();

        // Eğer URL'den terminal geldiyse onu seç
        if ($this->selectedTerminalId) {
            $this->selectTerminal($this->selectedTerminalId);
        } else {
            // İlk terminali varsayılan seçelim
            $firstTerminal = PosTerminal::where('user_id', $userId)->where('is_active', true)->first();
            if ($firstTerminal) {
                $this->selectTerminal($firstTerminal->id);
            }
        }

        // Varsayılan depoyu seçelim
        $defaultWh = Warehouse::where('user_id', $userId)->where('is_active', true)->where('is_default', true)->first();
        if ($defaultWh) {
            $this->selectedWarehouseId = $defaultWh->id;
        }

        // Kasa/Banka hesabı varsayılan
        $this->updatePaymentMethod($this->paymentMethod);
    }

    public function selectTerminal(int $terminalId): void
    {
        $userId = auth()->id();
        $terminal = PosTerminal::where('user_id', $userId)->findOrFail($terminalId);
        $this->selectedTerminalId = $terminal->id;

        // Bu terminale ait açık vardiya var mı?
        $shift = PosShift::where('user_id', $userId)
            ->where('pos_terminal_id', $terminal->id)
            ->where('status', 'open')
            ->first();

        $this->activeShiftId = $shift ? $shift->id : null;
        $this->cart = [];
        $this->message = '';

        if ($terminal->warehouse_id) {
            $this->selectedWarehouseId = $terminal->warehouse_id;
        }
        if ($terminal->legal_entity_id) {
            $this->selectedLegalEntityId = $terminal->legal_entity_id;
        }

        $this->updatePaymentMethod($this->paymentMethod);
    }

    public function createTerminal(): void
    {
        $userId = auth()->id();

        $this->validate([
            'terminalName' => 'required|string|max:100',
        ], [
            'terminalName.required' => 'Terminal adı zorunludur.',
        ]);

        try {
            if ($this->terminalWarehouseId) {
                Warehouse::where('user_id', $userId)->where('is_active', true)->findOrFail($this->terminalWarehouseId);
            }
            if ($this->terminalAccountId) {
                Account::where('user_id', $userId)->where('is_active', true)->findOrFail($this->terminalAccountId);
            }
            if ($this->terminalLegalEntityId) {
                LegalEntity::where('user_id', $userId)->active()->findOrFail($this->terminalLegalEntityId);
            }

            $terminal = PosTerminal::create([
                'user_id'          => $userId,
                'name'             => $this->terminalName,
                'is_active'        => true,
                'warehouse_id'     => $this->terminalWarehouseId ?: null,
                'account_id'       => $this->terminalAccountId ?: null,
                'legal_entity_id'  => $this->terminalLegalEntityId ?: null,
            ]);

            $this->message = 'Satış terminali başarıyla oluşturuldu.';
            $this->messageType = 'success';
            $this->terminalName = '';
            $this->terminalWarehouseId = null;
            $this->terminalAccountId = null;
            $this->terminalLegalEntityId = null;
            $this->showTerminalForm = false;

            $this->selectTerminal($terminal->id);
        } catch (\Exception $e) {
            $this->message = 'Terminal oluşturulurken hata: ' . $e->getMessage();
            $this->messageType = 'error';
        }
    }

    public function openShift(): void
    {
        $userId = auth()->id();
        if (!$this->selectedTerminalId) {
            return;
        }

        $terminal = PosTerminal::where('user_id', $userId)->findOrFail($this->selectedTerminalId);

        try {
            $service = app(PosService::class);
            $shift = $service->openShift($terminal, $this->shiftOpeningBalance, [
                'account_id'      => $this->shiftAccountId ?: null,
                'legal_entity_id' => $this->shiftLegalEntityId ?: null,
            ]);

            $this->activeShiftId = $shift->id;
            $this->showShiftOpenForm = false;
            $this->message = 'Vardiya başarıyla açıldı.';
            $this->messageType = 'success';
            $this->updatePaymentMethod($this->paymentMethod);
        } catch (\Exception $e) {
            $this->message = 'Vardiya açılırken hata: ' . $e->getMessage();
            $this->messageType = 'error';
        }
    }

    public function closeShift(): void
    {
        $userId = auth()->id();
        if (!$this->activeShiftId) {
            return;
        }

        $shift = PosShift::where('user_id', $userId)->findOrFail($this->activeShiftId);

        try {
            $service = app(PosService::class);
            $service->closeShift($shift, $this->shiftClosingBalance, $userId);

            $this->activeShiftId = null;
            $this->showShiftCloseForm = false;
            $this->message = 'Vardiya başarıyla kapatıldı.';
            $this->messageType = 'success';
        } catch (\Exception $e) {
            $this->message = 'Vardiya kapatılırken hata: ' . $e->getMessage();
            $this->messageType = 'error';
        }
    }

    public function updatePaymentMethod(string $method): void
    {
        $this->paymentMethod = $method;
        $userId = auth()->id();

        // Ödeme yöntemine göre kasa/banka hesabı seç
        $type = $method === 'cash' ? 'cash' : 'bank';
        $acc = Account::where('user_id', $userId)
            ->where('is_active', true)
            ->where('type', $type)
            ->first();

        $this->selectedAccountId = $acc ? $acc->id : null;
    }

    public function addToCart(string $stockCode): void
    {
        $userId = auth()->id();
        $product = MpProduct::where('user_id', $userId)->where('stock_code', $stockCode)->first();
        if (!$product) {
            return;
        }

        foreach ($this->cart as $index => $item) {
            if ($item['stock_code'] === $stockCode) {
                $this->cart[$index]['quantity']++;
                return;
            }
        }

        $this->cart[] = [
            'stock_code'    => $product->stock_code,
            'name'          => $product->product_name,
            'quantity'      => 1,
            'unit_price'    => (float) ($product->sale_price ?? $product->sales_price_try ?? 10.00),
            'vat_rate'      => 20.00,
            'discount_rate' => 0.00,
        ];
    }

    public function removeFromCart(int $index): void
    {
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
    }

    public function updateQuantity(int $index, int $qty): void
    {
        if ($qty < 1) {
            $qty = 1;
        }
        $this->cart[$index]['quantity'] = $qty;
    }

    public function updateUnitPrice(int $index, float $price): void
    {
        if ($price < 0) {
            $price = 0.0;
        }
        $this->cart[$index]['unit_price'] = $price;
    }

    public function updateDiscountRate(int $index, float $rate): void
    {
        if ($rate < 0) {
            $rate = 0.0;
        }
        if ($rate > 100) {
            $rate = 100.0;
        }
        $this->cart[$index]['discount_rate'] = $rate;
    }

    public function getSubtotalProperty(): float
    {
        return array_reduce($this->cart, function ($carry, $item) {
            return $carry + ($item['quantity'] * $item['unit_price']);
        }, 0.0);
    }

    public function getDiscountTotalProperty(): float
    {
        return array_reduce($this->cart, function ($carry, $item) {
            $base = $item['quantity'] * $item['unit_price'];
            return $carry + ($base * $item['discount_rate'] / 100);
        }, 0.0);
    }

    public function getVatTotalProperty(): float
    {
        return array_reduce($this->cart, function ($carry, $item) {
            $base = $item['quantity'] * $item['unit_price'];
            $disc = $base * ($item['discount_rate'] / 100);
            return $carry + (($base - $disc) * $item['vat_rate'] / 100);
        }, 0.0);
    }

    public function getTotalProperty(): float
    {
        return max(0.00, round($this->subtotal - $this->discountTotal + $this->vatTotal, 2));
    }

    public function checkout(): void
    {
        $userId = auth()->id();
        if (!$this->activeShiftId) {
            $this->message = 'Satış yapmak için önce vardiya açmalısınız.';
            $this->messageType = 'error';
            return;
        }

        if (count($this->cart) === 0) {
            $this->message = 'Sepetiniz boş.';
            $this->messageType = 'error';
            return;
        }

        $shift = PosShift::where('user_id', $userId)->findOrFail($this->activeShiftId);

        // Stok uygunluk kontrolü
        $stockService = app(StockService::class);
        $resolvedWhId = $stockService->resolveWarehouseId($userId, $this->selectedWarehouseId);
        foreach ($this->cart as $item) {
            $currentStock = $stockService->getStockLevel($userId, $item['stock_code'], $resolvedWhId);
            if ($currentStock < $item['quantity']) {
                $this->message = sprintf(
                    'Yetersiz stok! "%s" ürünü için depodaki mevcut stok: %d, istenen: %d.',
                    $item['name'],
                    $currentStock,
                    $item['quantity']
                );
                $this->messageType = 'error';
                return;
            }
        }

        try {
            $service = app(PosService::class);
            $service->recordPosSale($shift, [
                'payment_method'  => $this->paymentMethod,
                'party_id'        => $this->selectedPartyId ?: null,
                'warehouse_id'    => $this->selectedWarehouseId ?: null,
                'account_id'      => $this->selectedAccountId ?: null,
                'legal_entity_id' => $this->selectedLegalEntityId ?: null,
            ], $this->cart);

            $this->message = 'Satış başarıyla tamamlandı. Stok çıkışı ve tahsilat yapıldı.';
            $this->messageType = 'success';
            $this->cart = [];
        } catch (\Exception $e) {
            $this->message = 'Satış kaydedilirken hata: ' . $e->getMessage();
            $this->messageType = 'error';
        }
    }

    public function confirmCancel(int $saleId): void
    {
        $this->cancellingSaleId = $saleId;
        $this->cancelReason     = '';
        $this->showCancelModal  = true;
    }

    public function cancelSale(): void
    {
        if (!$this->cancellingSaleId) {
            return;
        }

        $userId = auth()->id();
        $sale = PosSale::where('user_id', $userId)->findOrFail($this->cancellingSaleId);

        try {
            $service = app(PosService::class);
            $service->voidPosSale($sale, $this->cancelReason, $userId);

            $this->message = 'POS satışı başarıyla iptal edildi ve ters kayıtlar işlendi.';
            $this->messageType = 'success';
            $this->showCancelModal = false;
            $this->cancellingSaleId = null;
        } catch (\Exception $e) {
            $this->message = 'Satış iptal edilirken hata: ' . $e->getMessage();
            $this->messageType = 'error';
        }
    }

    // -------------------------------------------------------
    // Computed Properties
    // -------------------------------------------------------

    public function getTerminalsProperty()
    {
        return PosTerminal::where('user_id', auth()->id())->get();
    }

    public function getActiveTerminalProperty()
    {
        if ($this->selectedTerminalId) {
            return PosTerminal::where('user_id', auth()->id())->find($this->selectedTerminalId);
        }
        return null;
    }

    public function getActiveShiftProperty()
    {
        if ($this->activeShiftId) {
            return PosShift::where('user_id', auth()->id())->find($this->activeShiftId);
        }
        return null;
    }

    public function getProductsProperty()
    {
        $userId = auth()->id();
        $query = MpProduct::where('user_id', $userId);

        if ($this->cartSearch !== '') {
            $query->where(function ($q) {
                $q->where('product_name', 'like', '%' . $this->cartSearch . '%')
                  ->orWhere('stock_code', 'like', '%' . $this->cartSearch . '%')
                  ->orWhere('barcode', 'like', '%' . $this->cartSearch . '%');
            });
        }

        return $query->orderBy('product_name')->get();
    }

    public function getPartiesProperty()
    {
        return Party::where('user_id', auth()->id())
            ->whereHas('roles', fn ($q) => $q->where('role', 'customer'))
            ->orderBy('display_name')
            ->get();
    }

    public function getWarehousesProperty()
    {
        return Warehouse::where('user_id', auth()->id())
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    public function getAccountsProperty()
    {
        $type = $this->paymentMethod === 'cash' ? 'cash' : 'bank';
        return Account::where('user_id', auth()->id())
            ->where('is_active', true)
            ->where('type', $type)
            ->orderBy('name')
            ->get();
    }

    public function getLegalEntitiesProperty()
    {
        return LegalEntity::where('user_id', auth()->id())
            ->active()
            ->orderBy('name')
            ->get();
    }

    public function getKpisProperty(): array
    {
        if (!$this->activeShiftId) {
            return [
                'totalSales'  => 0.00,
                'cashSales'   => 0.00,
                'bankSales'   => 0.00,
                'itemCount'   => 0,
                'expected'    => 0.00,
                'diff'        => 0.00,
            ];
        }

        $shift = PosShift::findOrFail($this->activeShiftId);

        $postedSales = PosSale::where('pos_shift_id', $shift->id)
            ->where('status', 'posted')
            ->with('salesOrder.items')
            ->get();

        $totalSales = (float) $postedSales->sum('amount');
        $cashSales  = (float) $postedSales->where('payment_method', 'cash')->sum('amount');
        $bankSales  = (float) $postedSales->whereIn('payment_method', ['card', 'bank_transfer'])->sum('amount');

        $itemCount = 0;
        foreach ($postedSales as $sale) {
            if ($sale->salesOrder) {
                $itemCount += $sale->salesOrder->items->sum('quantity');
            }
        }

        $expected = (float) $shift->opening_balance + $cashSales;

        return [
            'totalSales' => $totalSales,
            'cashSales'  => $cashSales,
            'bankSales'  => $bankSales,
            'itemCount'  => $itemCount,
            'expected'   => $expected,
            'diff'       => 0.00, // Kapanışta hesaplanır
        ];
    }

    public function getColumnDefsProperty(): array
    {
        return [
            'id'               => 'No',
            'reference_number' => 'Fiş / Belge No',
            'party'            => 'Müşteri',
            'payment_method'   => 'Ödeme Yöntemi',
            'amount'           => 'Tutar',
            'status'           => 'Durum',
            'action'           => 'İşlem',
        ];
    }

    public function toggleColumn(string $column): void
    {
        if (in_array($column, $this->visibleColumns, true)) {
            $this->visibleColumns = array_diff($this->visibleColumns, [$column]);
        } else {
            $this->visibleColumns[] = $column;
        }
    }

    public function sortTable(string $column): void
    {
        if (!in_array($column, self::$sortableColumns, true)) {
            return;
        }

        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn    = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function getRecentSalesProperty()
    {
        if (!$this->activeShiftId) {
            return collect();
        }

        $query = PosSale::where('pos_shift_id', $this->activeShiftId)
            ->with(['salesOrder.party', 'party']);

        if (in_array($this->sortColumn, ['id', 'reference_number', 'payment_method', 'amount', 'status'], true)) {
            $query->orderBy($this->sortColumn, $this->sortDirection);
        } else {
            $query->orderBy('id', 'desc');
        }

        return $query->get();
    }

    public function render()
    {
        return view('livewire.accounting.pos')
            ->layout('layouts.app');
    }
}
