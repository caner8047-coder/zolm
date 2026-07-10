<?php

namespace App\Livewire\Accounting;

use App\Models\LegalEntity;
use App\Models\MpProduct;
use App\Models\Party;
use App\Models\PurchaseOrder;
use App\Services\Accounting\TradeService;
use Livewire\Component;
use Livewire\WithPagination;

class Purchases extends Component
{
    use WithPagination;

    // Filters
    public string $search = '';
    public string $filterStatus = '';

    // Create Form Modal Toggle
    public bool $showCreateForm = false;

    // Header Inputs
    public ?int $partyId = null;
    public ?int $legalEntityId = null;
    public string $documentNumber = '';
    public string $orderDate = '';
    public string $description = '';

    // Item rows
    public array $items = [];

    // Messaging
    public string $message = '';
    public string $messageType = 'success';

    protected $queryString = [
        'search' => ['except' => ''],
        'filterStatus' => ['except' => ''],
    ];

    public function mount(): void
    {
        $this->orderDate = now()->toDateString();
        $this->resetItems();
    }

    public function resetItems(): void
    {
        $this->items = [
            ['stock_code' => '', 'quantity' => 1, 'unit_price' => 0.0, 'vat_rate' => 20.00],
        ];
    }

    public function addItem(): void
    {
        $this->items[] = ['stock_code' => '', 'quantity' => 1, 'unit_price' => 0.0, 'vat_rate' => 20.00];
    }

    public function removeItem(int $index): void
    {
        if (count($this->items) <= 1) {
            $this->message = 'Satın alma siparişinde en az 1 satır bulunmalıdır.';
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

    public function getVatTotalProperty(): float
    {
        return array_reduce($this->items, function ($carry, $item) {
            $base = (int) ($item['quantity'] ?? 0) * (float) ($item['unit_price'] ?? 0);
            $vatRate = (float) ($item['vat_rate'] ?? 20.00);
            return $carry + ($base * $vatRate / 100);
        }, 0.0);
    }

    public function getTotalProperty(): float
    {
        return round($this->subtotal + $this->vatTotal, 2);
    }

    public function createPurchaseOrder(): void
    {
        $userId = auth()->id();

        $this->validate([
            'partyId' => 'required|integer',
            'documentNumber' => 'required|string|max:50',
            'orderDate' => 'required|date',
            'items.*.stock_code' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ], [
            'partyId.required' => 'Tedarikçi (Cari) seçimi zorunludur.',
            'documentNumber.required' => 'Belge numarası zorunludur.',
            'items.*.stock_code.required' => 'Ürün seçimi zorunludur.',
            'items.*.quantity.min' => 'Miktar en az 1 olmalıdır.',
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
            $tradeService->createPurchaseOrder([
                'user_id' => $userId,
                'party_id' => (int) $this->partyId,
                'legal_entity_id' => $this->legalEntityId ? (int) $this->legalEntityId : null,
                'document_number' => $this->documentNumber,
                'order_date' => $this->orderDate,
                'description' => $this->description ?: null,
            ], $this->items);

            $this->message = 'Satın alma siparişi taslağı başarıyla oluşturuldu.';
            $this->messageType = 'success';

            // Reset
            $this->partyId = null;
            $this->legalEntityId = null;
            $this->documentNumber = '';
            $this->description = '';
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
        $order = PurchaseOrder::where('user_id', $userId)->findOrFail($orderId);

        if ($order->status !== 'draft') {
            $this->message = 'Sadece taslak durumundaki siparişler onaylanabilir.';
            $this->messageType = 'error';
            return;
        }

        try {
            $tradeService = app(TradeService::class);
            $tradeService->approvePurchaseOrder($order);

            $this->message = 'Satın alma siparişi onaylandı, cari borcu işlendi ve stok girişi yapıldı.';
            $this->messageType = 'success';
        } catch (\Exception $e) {
            $this->message = 'Sipariş onaylanırken hata: ' . $e->getMessage();
            $this->messageType = 'error';
        }
    }

    public function getPartiesProperty()
    {
        return Party::where('user_id', auth()->id())->orderBy('display_name')->get();
    }

    public function getLegalEntitiesProperty()
    {
        return LegalEntity::where('user_id', auth()->id())->orderBy('name')->get();
    }

    public function getProductsProperty()
    {
        return MpProduct::where('user_id', auth()->id())->orderBy('product_name')->get();
    }

    public function getOrdersProperty()
    {
        $query = PurchaseOrder::where('user_id', auth()->id())
            ->with(['party', 'legalEntity', 'items']);

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('document_number', 'like', "%{$this->search}%")
                  ->orWhere('description', 'like', "%{$this->search}%");
            });
        }

        if ($this->filterStatus !== '') {
            $query->where('status', $this->filterStatus);
        }

        return $query->orderByDesc('id')
            ->paginate(15);
    }

    public function render()
    {
        return view('livewire.accounting.purchases')
            ->layout('layouts.app');
    }
}
