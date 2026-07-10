<?php

namespace App\Livewire\Accounting;

use App\Models\MpProduct;
use App\Models\PosSale;
use App\Models\PosShift;
use App\Models\PosTerminal;
use App\Services\Accounting\PosService;
use App\Services\Accounting\StockService;
use Livewire\Component;

class Pos extends Component
{
    // Active terminal and shift
    public ?int $selectedTerminalId = null;
    public ?int $activeShiftId = null;

    // Terminal creation
    public bool $showTerminalForm = false;
    public string $terminalName = '';

    // Shift Opening & Closing
    public float $shiftOpeningBalance = 0.00;
    public bool $showShiftOpenForm = false;
    public float $shiftClosingBalance = 0.00;
    public bool $showShiftCloseForm = false;

    // Cart Items
    public array $cart = [];
    public string $cartSearch = '';
    public string $paymentMethod = 'cash'; // cash, credit_card

    // Messaging
    public string $message = '';
    public string $messageType = 'success';

    public function selectTerminal(int $terminalId): void
    {
        $userId = auth()->id();
        $terminal = PosTerminal::where('user_id', $userId)->findOrFail($terminalId);
        $this->selectedTerminalId = $terminal->id;

        // Check if there is an active shift for this terminal
        $shift = PosShift::where('user_id', $userId)
            ->where('pos_terminal_id', $terminal->id)
            ->where('status', 'open')
            ->first();

        $this->activeShiftId = $shift ? $shift->id : null;
        $this->cart = [];
        $this->message = '';
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
            $terminal = PosTerminal::create([
                'user_id' => $userId,
                'name' => $this->terminalName,
                'is_active' => true,
            ]);

            $this->message = 'Satış terminali başarıyla oluşturuldu.';
            $this->messageType = 'success';
            $this->terminalName = '';
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
            $shift = $service->openShift($terminal, $this->shiftOpeningBalance);

            $this->activeShiftId = $shift->id;
            $this->showShiftOpenForm = false;
            $this->message = 'Vardiya başarıyla açıldı.';
            $this->messageType = 'success';
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
            $service->closeShift($shift, $this->shiftClosingBalance);

            $this->activeShiftId = null;
            $this->showShiftCloseForm = false;
            $this->message = 'Vardiya başarıyla kapatıldı.';
            $this->messageType = 'success';
        } catch (\Exception $e) {
            $this->message = 'Vardiya kapatılırken hata: ' . $e->getMessage();
            $this->messageType = 'error';
        }
    }

    public function addToCart(string $stockCode): void
    {
        $userId = auth()->id();
        $product = MpProduct::where('user_id', $userId)->where('stock_code', $stockCode)->first();
        if (!$product) {
            return;
        }

        // Check if already in cart
        foreach ($this->cart as $index => $item) {
            if ($item['stock_code'] === $stockCode) {
                $this->cart[$index]['quantity']++;
                return;
            }
        }

        // Add new
        $this->cart[] = [
            'stock_code' => $product->stock_code,
            'name' => $product->product_name,
            'quantity' => 1,
            'unit_price' => (float) ($product->sale_price ?? $product->sales_price_try ?? 10.00),
            'vat_rate' => 20.00,
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

    public function getSubtotalProperty(): float
    {
        return array_reduce($this->cart, function ($carry, $item) {
            return $carry + ($item['quantity'] * $item['unit_price']);
        }, 0.0);
    }

    public function getVatTotalProperty(): float
    {
        return array_reduce($this->cart, function ($carry, $item) {
            $base = $item['quantity'] * $item['unit_price'];
            return $carry + ($base * $item['vat_rate'] / 100);
        }, 0.0);
    }

    public function getTotalProperty(): float
    {
        return round($this->subtotal + $this->vatTotal, 2);
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

        // Stock availability checks
        $stockService = app(StockService::class);
        foreach ($this->cart as $item) {
            $currentStock = $stockService->getStockLevel($userId, $item['stock_code'], null);
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
                'payment_method' => $this->paymentMethod,
            ], $this->cart);

            $this->message = 'Satış başarıyla tamamlandı. Stok çıkışı ve tahsilat yapıldı.';
            $this->messageType = 'success';
            $this->cart = [];
        } catch (\Exception $e) {
            $this->message = 'Satış kaydedilirken hata: ' . $e->getMessage();
            $this->messageType = 'error';
        }
    }

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
        return MpProduct::where('user_id', auth()->id())->orderBy('product_name')->get();
    }

    public function getRecentSalesProperty()
    {
        if ($this->activeShiftId) {
            return PosSale::where('pos_shift_id', $this->activeShiftId)
                ->with('salesOrder.party')
                ->orderByDesc('id')
                ->get();
        }
        return [];
    }

    public function render()
    {
        return view('livewire.accounting.pos')
            ->layout('layouts.app');
    }
}
