<?php

namespace App\Livewire\Accounting;

use App\Models\ChannelOrder;
use App\Models\JournalEntry;
use App\Models\MarketplaceStore;
use App\Models\OrderFinancialEvent;
use App\Models\SalesOrder;
use App\Services\Accounting\MarketplaceFinanceBridgeService;
use Livewire\Component;
use Livewire\WithPagination;

class MarketplaceBridge extends Component
{
    use WithPagination;

    // Filters
    public string $search = '';
    public string $filterStore = '';
    public string $activeTab = 'orders'; // orders, events

    // Messaging
    public string $message = '';
    public string $messageType = 'success';

    protected $queryString = [
        'search' => ['except' => ''],
        'filterStore' => ['except' => ''],
        'activeTab' => ['except' => 'orders'],
    ];

    public function bridgeSingleOrder(int $orderId): void
    {
        $userId = auth()->id();
        $order = ChannelOrder::whereHas('store', function($q) use ($userId) {
            $q->where('user_id', $userId);
        })->findOrFail($orderId);

        // Check if already bridged
        $existing = SalesOrder::where('user_id', $userId)
            ->where('document_number', $order->order_number)
            ->first();

        if ($existing) {
            $this->message = 'Bu sipariş zaten daha önce köprülenmiş.';
            $this->messageType = 'error';
            return;
        }

        try {
            $service = app(MarketplaceFinanceBridgeService::class);
            $service->bridgeOrder($order);

            $this->message = "Sipariş #{$order->order_number} başarıyla ön muhasebeye köprülendi, cari kaydı ve stok çıkışı yapıldı.";
            $this->messageType = 'success';
        } catch (\Exception $e) {
            $this->message = 'Köprüleme hatası: ' . $e->getMessage();
            $this->messageType = 'error';
        }
    }

    public function bridgeAllOrders(): void
    {
        $userId = auth()->id();
        $orders = ChannelOrder::whereHas('store', function($q) use ($userId) {
            $q->where('user_id', $userId);
        })->whereNotExists(function($q) use ($userId) {
            $q->select('*')
              ->from('sales_orders')
              ->where('sales_orders.user_id', $userId)
              ->whereColumn('sales_orders.document_number', 'channel_orders.order_number');
        })->get();

        if ($orders->isEmpty()) {
            $this->message = 'Köprülenecek yeni sipariş bulunamadı.';
            $this->messageType = 'error';
            return;
        }

        $count = 0;
        $service = app(MarketplaceFinanceBridgeService::class);

        foreach ($orders as $order) {
            try {
                $service->bridgeOrder($order);
                $count++;
            } catch (\Exception $e) {
                // Continue with remaining
            }
        }

        $this->message = "Toplam {$count} sipariş başarıyla ön muhasebeye köprülendi.";
        $this->messageType = 'success';
    }

    public function bridgeSingleEvent(int $eventId): void
    {
        $userId = auth()->id();
        $event = OrderFinancialEvent::whereHas('store', function($q) use ($userId) {
            $q->where('user_id', $userId);
        })->findOrFail($eventId);

        // Check if already bridged
        $existing = JournalEntry::where('user_id', $userId)
            ->where('source_type', 'financial_event')
            ->where('source_id', $event->id)
            ->first();

        if ($existing) {
            $this->message = 'Bu finansal olay zaten daha önce muhasebeleşmiş.';
            $this->messageType = 'error';
            return;
        }

        try {
            $service = app(MarketplaceFinanceBridgeService::class);
            $service->bridgeFinancialEvent($event);

            $this->message = 'Finansal olay başarıyla genel muhasebeye işlendi.';
            $this->messageType = 'success';
        } catch (\Exception $e) {
            $this->message = 'Muhasebeleştirme hatası: ' . $e->getMessage();
            $this->messageType = 'error';
        }
    }

    public function bridgeAllEvents(): void
    {
        $userId = auth()->id();
        $events = OrderFinancialEvent::whereHas('store', function($q) use ($userId) {
            $q->where('user_id', $userId);
        })->whereNotExists(function($q) use ($userId) {
            $q->select('*')
              ->from('journal_entries')
              ->where('journal_entries.user_id', $userId)
              ->where('journal_entries.source_type', 'financial_event')
              ->whereColumn('journal_entries.source_id', 'order_financial_events.id');
        })->get();

        if ($events->isEmpty()) {
            $this->message = 'Muhasebeleşecek yeni finansal olay bulunamadı.';
            $this->messageType = 'error';
            return;
        }

        $count = 0;
        $service = app(MarketplaceFinanceBridgeService::class);

        foreach ($events as $event) {
            try {
                $service->bridgeFinancialEvent($event);
                $count++;
            } catch (\Exception $e) {
                // Continue
            }
        }

        $this->message = "Toplam {$count} finansal olay başarıyla genel muhasebeye işlendi.";
        $this->messageType = 'success';
    }

    public function getStoresProperty()
    {
        return MarketplaceStore::where('user_id', auth()->id())->get();
    }

    public function getOrdersProperty()
    {
        $query = ChannelOrder::whereHas('store', function($q) {
            $q->where('user_id', auth()->id());
        })->with(['store', 'items']);

        if ($this->filterStore !== '') {
            $query->where('store_id', $this->filterStore);
        }

        if ($this->search !== '') {
            $query->where(function($q) {
                $q->where('order_number', 'like', "%{$this->search}%")
                  ->orWhere('customer_name', 'like', "%{$this->search}%");
            });
        }

        return $query->orderByDesc('id')
            ->paginate(15, ['*'], 'ordersPage');
    }

    public function getEventsProperty()
    {
        $query = OrderFinancialEvent::whereHas('store', function($q) {
            $q->where('user_id', auth()->id());
        })->with(['store', 'order']);

        if ($this->filterStore !== '') {
            $query->where('store_id', $this->filterStore);
        }

        if ($this->search !== '') {
            $query->where(function($q) {
                $q->where('event_type', 'like', "%{$this->search}%")
                  ->orWhereHas('order', function($oq) {
                      $oq->where('order_number', 'like', "%{$this->search}%");
                  });
            });
        }

        return $query->orderByDesc('id')
            ->paginate(15, ['*'], 'eventsPage');
    }

    public function isBridgedOrder(string $orderNumber): bool
    {
        return SalesOrder::where('user_id', auth()->id())
            ->where('document_number', $orderNumber)
            ->exists();
    }

    public function isBridgedEvent(int $eventId): bool
    {
        return JournalEntry::where('user_id', auth()->id())
            ->where('source_type', 'financial_event')
            ->where('source_id', $eventId)
            ->exists();
    }

    public function render()
    {
        return view('livewire.accounting.marketplace-bridge')
            ->layout('layouts.app');
    }
}
