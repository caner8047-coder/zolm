<?php

namespace App\Livewire\Accounting;

use App\Models\ChannelOrder;
use App\Models\JournalEntry;
use App\Models\MarketplaceStore;
use App\Models\OrderFinancialEvent;
use App\Models\SalesOrder;
use App\Models\MarketplaceFinanceBridgeRun;
use App\Services\Accounting\MarketplaceFinanceBridgeService;
use Livewire\Component;
use Livewire\WithPagination;
use InvalidArgumentException;

class MarketplaceBridge extends Component
{
    use WithPagination;

    // UI Tab state
    public string $activeTab = 'orders'; // orders, financial_events, runs

    // Filters
    public string $search = '';
    public string $storeId = '';
    public string $marketplace = '';
    public string $bridgeStatus = ''; // pending, bridged
    public string $eventType = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $runStatus = ''; // pending, processing, succeeded, failed, skipped

    // Sorting
    public string $sortColumn = 'id';
    public string $sortDirection = 'desc';

    // Column Visibility
    public array $visibleColumns = [];

    // Whitelists
    public static array $sortableColumns = [
        'id', 'order_number', 'customer_name', 'ordered_at',
        'event_type', 'amount', 'event_date', 'attempted_at', 'completed_at', 'status'
    ];

    // Messaging
    public string $message = '';
    public string $messageType = 'success';

    protected $queryString = [
        'activeTab'     => ['except' => 'orders'],
        'search'        => ['except' => ''],
        'storeId'       => ['except' => ''],
        'marketplace'   => ['except' => ''],
        'bridgeStatus'  => ['except' => ''],
        'eventType'     => ['except' => ''],
        'dateFrom'      => ['except' => ''],
        'dateTo'        => ['except' => ''],
        'runStatus'     => ['except' => ''],
        'sortColumn'    => ['except' => 'id'],
        'sortDirection' => ['except' => 'desc'],
    ];

    public function mount(): void
    {
        $this->resetColumnVisibility();
    }

    public function resetColumnVisibility(): void
    {
        if ($this->activeTab === 'orders') {
            $this->visibleColumns = ['id', 'store_name', 'order_number', 'customer_name', 'ordered_at', 'status', 'actions'];
        } elseif ($this->activeTab === 'financial_events') {
            $this->visibleColumns = ['id', 'store_name', 'event_type', 'amount', 'event_date', 'status', 'actions'];
        } else {
            $this->visibleColumns = ['id', 'bridge_type', 'source_key', 'status', 'error_message', 'attempted_at', 'actions'];
        }
    }

    public function updatedActiveTab(): void
    {
        $this->resetPage('ordersPage');
        $this->resetPage('eventsPage');
        $this->resetPage('runsPage');
        $this->resetColumnVisibility();
        $this->message = '';
    }

    public function toggleColumn(string $column): void
    {
        if (in_array($column, $this->visibleColumns, true)) {
            $this->visibleColumns = array_values(array_diff($this->visibleColumns, [$column]));
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
            $this->sortColumn = $column;
            $this->sortDirection = 'desc';
        }
    }

    public function clearFilters(): void
    {
        $this->reset([
            'search', 'storeId', 'marketplace', 'bridgeStatus',
            'eventType', 'dateFrom', 'dateTo', 'runStatus'
        ]);
        $this->resetPage('ordersPage');
        $this->resetPage('eventsPage');
        $this->resetPage('runsPage');
        $this->message = '';
    }

    // ─── ACTIONS ─────────────────────────────────────────────────────────

    public function bridgeSingleOrder(int $orderId): void
    {
        $userId = auth()->id();
        $order = ChannelOrder::whereHas('store', function($q) use ($userId) {
            $q->where('user_id', $userId);
        })->findOrFail($orderId);

        try {
            $service = app(MarketplaceFinanceBridgeService::class);
            $service->bridgeOrder($order, true, $userId);

            $this->message = "Sipariş #{$order->order_number} başarıyla ön muhasebeye köprülendi.";
            $this->messageType = 'success';
        } catch (\Exception $e) {
            $this->message = 'Köprüleme hatası: ' . $e->getMessage();
            $this->messageType = 'error';
        }
    }

    public function bridgeFilteredOrders(): void
    {
        $userId = auth()->id();

        $query = ChannelOrder::whereHas('store', function($q) use ($userId) {
            $q->where('user_id', $userId);
        });

        if ($this->storeId !== '') {
            $query->where('store_id', $this->storeId);
        }

        if ($this->marketplace !== '') {
            $query->whereHas('store', function($q) {
                $q->where('marketplace', $this->marketplace);
            });
        }

        if ($this->search !== '') {
            $query->where(function($q) {
                $q->where('order_number', 'like', "%{$this->search}%")
                  ->orWhere('customer_name', 'like', "%{$this->search}%");
            });
        }

        if ($this->dateFrom !== '') {
            $query->whereDate('ordered_at', '>=', $this->dateFrom);
        }
        if ($this->dateTo !== '') {
            $query->whereDate('ordered_at', '<=', $this->dateTo);
        }

        if ($this->bridgeStatus === 'pending') {
            $query->whereNotExists(function($q) use ($userId) {
                $q->select('*')
                  ->from('marketplace_finance_bridge_runs')
                  ->where('marketplace_finance_bridge_runs.user_id', $userId)
                  ->where('marketplace_finance_bridge_runs.bridge_type', 'order')
                  ->where('marketplace_finance_bridge_runs.status', 'succeeded')
                  ->whereColumn('marketplace_finance_bridge_runs.channel_order_id', 'channel_orders.id');
            });
        } elseif ($this->bridgeStatus === 'bridged') {
            $query->whereExists(function($q) use ($userId) {
                $q->select('*')
                  ->from('marketplace_finance_bridge_runs')
                  ->where('marketplace_finance_bridge_runs.user_id', $userId)
                  ->where('marketplace_finance_bridge_runs.bridge_type', 'order')
                  ->where('marketplace_finance_bridge_runs.status', 'succeeded')
                  ->whereColumn('marketplace_finance_bridge_runs.channel_order_id', 'channel_orders.id');
            });
        }

        $orders = $query->take(50)->get();

        if ($orders->isEmpty()) {
            $this->message = 'Köprülenecek yeni sipariş bulunamadı.';
            $this->messageType = 'error';
            return;
        }

        $succeeded = 0;
        $failed = 0;
        $skipped = 0;
        $service = app(MarketplaceFinanceBridgeService::class);

        foreach ($orders as $order) {
            try {
                if ($this->isBridgedOrder($order->id)) {
                    $skipped++;
                    continue;
                }
                $service->bridgeOrder($order, true, $userId);
                $succeeded++;
            } catch (\Exception $e) {
                $failed++;
            }
        }

        $this->message = "Köprüleme tamamlandı: {$succeeded} başarılı, {$failed} başarısız, {$skipped} atlandı.";
        $this->messageType = $failed > 0 ? 'error' : 'success';
    }

    public function bridgeSingleEvent(int $eventId): void
    {
        $userId = auth()->id();
        $event = OrderFinancialEvent::whereHas('store', function($q) use ($userId) {
            $q->where('user_id', $userId);
        })->findOrFail($eventId);

        try {
            $service = app(MarketplaceFinanceBridgeService::class);
            $service->bridgeFinancialEvent($event, $userId);

            $this->message = 'Finansal olay başarıyla genel muhasebeye işlendi.';
            $this->messageType = 'success';
        } catch (\Exception $e) {
            $this->message = 'Muhasebeleştirme hatası: ' . $e->getMessage();
            $this->messageType = 'error';
        }
    }

    public function bridgeFilteredEvents(): void
    {
        $userId = auth()->id();

        $query = OrderFinancialEvent::whereHas('store', function($q) use ($userId) {
            $q->where('user_id', $userId);
        });

        if ($this->storeId !== '') {
            $query->where('store_id', $this->storeId);
        }

        if ($this->marketplace !== '') {
            $query->whereHas('store', function($q) {
                $q->where('marketplace', $this->marketplace);
            });
        }

        if ($this->eventType !== '') {
            $query->where('event_type', $this->eventType);
        }

        if ($this->search !== '') {
            $query->where(function($q) {
                $q->where('event_type', 'like', "%{$this->search}%")
                  ->orWhereHas('order', function($oq) {
                      $oq->where('order_number', 'like', "%{$this->search}%");
                  });
            });
        }

        if ($this->dateFrom !== '') {
            $query->whereDate('event_date', '>=', $this->dateFrom);
        }
        if ($this->dateTo !== '') {
            $query->whereDate('event_date', '<=', $this->dateTo);
        }

        if ($this->bridgeStatus === 'pending') {
            $query->whereNotExists(function($q) use ($userId) {
                $q->select('*')
                  ->from('marketplace_finance_bridge_runs')
                  ->where('marketplace_finance_bridge_runs.user_id', $userId)
                  ->where('marketplace_finance_bridge_runs.bridge_type', 'financial_event')
                  ->where('marketplace_finance_bridge_runs.status', 'succeeded')
                  ->whereColumn('marketplace_finance_bridge_runs.order_financial_event_id', 'order_financial_events.id');
            });
        } elseif ($this->bridgeStatus === 'bridged') {
            $query->whereExists(function($q) use ($userId) {
                $q->select('*')
                  ->from('marketplace_finance_bridge_runs')
                  ->where('marketplace_finance_bridge_runs.user_id', $userId)
                  ->where('marketplace_finance_bridge_runs.bridge_type', 'financial_event')
                  ->where('marketplace_finance_bridge_runs.status', 'succeeded')
                  ->whereColumn('marketplace_finance_bridge_runs.order_financial_event_id', 'order_financial_events.id');
            });
        }

        $events = $query->take(50)->get();

        if ($events->isEmpty()) {
            $this->message = 'Muhasebeleşecek yeni finansal olay bulunamadı.';
            $this->messageType = 'error';
            return;
        }

        $succeeded = 0;
        $failed = 0;
        $skipped = 0;
        $service = app(MarketplaceFinanceBridgeService::class);

        foreach ($events as $event) {
            try {
                if ($this->isBridgedEvent($event->id)) {
                    $skipped++;
                    continue;
                }
                $res = $service->bridgeFinancialEvent($event, $userId);
                if ($res === null) {
                    $skipped++;
                } else {
                    $succeeded++;
                }
            } catch (\Exception $e) {
                $failed++;
            }
        }

        $this->message = "Muhasebeleştirme tamamlandı: {$succeeded} başarılı, {$failed} başarısız, {$skipped} atlandı.";
        $this->messageType = $failed > 0 ? 'error' : 'success';
    }

    public function retryRun(int $runId): void
    {
        $userId = auth()->id();
        $run = MarketplaceFinanceBridgeRun::where('user_id', $userId)->findOrFail($runId);

        try {
            $service = app(MarketplaceFinanceBridgeService::class);
            $service->retryRun($run);

            $this->message = 'İşlem başarıyla yeniden çalıştırıldı.';
            $this->messageType = 'success';
        } catch (\Exception $e) {
            $this->message = 'Retry hatası: ' . $e->getMessage();
            $this->messageType = 'error';
        }
    }

    // ─── PROPERTIES ──────────────────────────────────────────────────────

    public function getStoresProperty()
    {
        return MarketplaceStore::where('user_id', auth()->id())->get();
    }

    public function getOrdersProperty()
    {
        $userId = auth()->id();
        $query = ChannelOrder::whereHas('store', function($q) use ($userId) {
            $q->where('user_id', $userId);
        })->with(['store', 'items']);

        if ($this->storeId !== '') {
            $query->where('store_id', $this->storeId);
        }

        if ($this->marketplace !== '') {
            $query->whereHas('store', function($q) {
                $q->where('marketplace', $this->marketplace);
            });
        }

        if ($this->search !== '') {
            $query->where(function($q) {
                $q->where('order_number', 'like', "%{$this->search}%")
                  ->orWhere('customer_name', 'like', "%{$this->search}%");
            });
        }

        if ($this->dateFrom !== '') {
            $query->whereDate('ordered_at', '>=', $this->dateFrom);
        }
        if ($this->dateTo !== '') {
            $query->whereDate('ordered_at', '<=', $this->dateTo);
        }

        if ($this->bridgeStatus === 'pending') {
            $query->whereNotExists(function($q) use ($userId) {
                $q->select('*')
                  ->from('marketplace_finance_bridge_runs')
                  ->where('marketplace_finance_bridge_runs.user_id', $userId)
                  ->where('marketplace_finance_bridge_runs.bridge_type', 'order')
                  ->where('marketplace_finance_bridge_runs.status', 'succeeded')
                  ->whereColumn('marketplace_finance_bridge_runs.channel_order_id', 'channel_orders.id');
            });
        } elseif ($this->bridgeStatus === 'bridged') {
            $query->whereExists(function($q) use ($userId) {
                $q->select('*')
                  ->from('marketplace_finance_bridge_runs')
                  ->where('marketplace_finance_bridge_runs.user_id', $userId)
                  ->where('marketplace_finance_bridge_runs.bridge_type', 'order')
                  ->where('marketplace_finance_bridge_runs.status', 'succeeded')
                  ->whereColumn('marketplace_finance_bridge_runs.channel_order_id', 'channel_orders.id');
            });
        }

        $sort = in_array($this->sortColumn, ['id', 'order_number', 'customer_name', 'ordered_at', 'status'], true)
            ? $this->sortColumn
            : 'id';

        return $query->orderBy($sort, $this->sortDirection)
            ->paginate(15, ['*'], 'ordersPage');
    }

    public function getEventsProperty()
    {
        $userId = auth()->id();
        $query = OrderFinancialEvent::whereHas('store', function($q) use ($userId) {
            $q->where('user_id', $userId);
        })->with(['store', 'order']);

        if ($this->storeId !== '') {
            $query->where('store_id', $this->storeId);
        }

        if ($this->marketplace !== '') {
            $query->whereHas('store', function($q) {
                $q->where('marketplace', $this->marketplace);
            });
        }

        if ($this->eventType !== '') {
            $query->where('event_type', $this->eventType);
        }

        if ($this->search !== '') {
            $query->where(function($q) {
                $q->where('event_type', 'like', "%{$this->search}%")
                  ->orWhereHas('order', function($oq) {
                      $oq->where('order_number', 'like', "%{$this->search}%");
                  });
            });
        }

        if ($this->dateFrom !== '') {
            $query->whereDate('event_date', '>=', $this->dateFrom);
        }
        if ($this->dateTo !== '') {
            $query->whereDate('event_date', '<=', $this->dateTo);
        }

        if ($this->bridgeStatus === 'pending') {
            $query->whereNotExists(function($q) use ($userId) {
                $q->select('*')
                  ->from('marketplace_finance_bridge_runs')
                  ->where('marketplace_finance_bridge_runs.user_id', $userId)
                  ->where('marketplace_finance_bridge_runs.bridge_type', 'financial_event')
                  ->where('marketplace_finance_bridge_runs.status', 'succeeded')
                  ->whereColumn('marketplace_finance_bridge_runs.order_financial_event_id', 'order_financial_events.id');
            });
        } elseif ($this->bridgeStatus === 'bridged') {
            $query->whereExists(function($q) use ($userId) {
                $q->select('*')
                  ->from('marketplace_finance_bridge_runs')
                  ->where('marketplace_finance_bridge_runs.user_id', $userId)
                  ->where('marketplace_finance_bridge_runs.bridge_type', 'financial_event')
                  ->where('marketplace_finance_bridge_runs.status', 'succeeded')
                  ->whereColumn('marketplace_finance_bridge_runs.order_financial_event_id', 'order_financial_events.id');
            });
        }

        $sort = in_array($this->sortColumn, ['id', 'event_type', 'amount', 'event_date', 'status'], true)
            ? $this->sortColumn
            : 'id';

        return $query->orderBy($sort, $this->sortDirection)
            ->paginate(15, ['*'], 'eventsPage');
    }

    public function getRunsProperty()
    {
        $userId = auth()->id();
        $query = MarketplaceFinanceBridgeRun::where('user_id', $userId)
            ->with(['store', 'channelOrder', 'financialEvent']);

        if ($this->storeId !== '') {
            $query->where('marketplace_store_id', $this->storeId);
        }

        if ($this->marketplace !== '') {
            $query->whereHas('store', function($q) {
                $q->where('marketplace', $this->marketplace);
            });
        }

        if ($this->runStatus !== '') {
            $query->where('status', $this->runStatus);
        }

        if ($this->search !== '') {
            $query->where(function($q) {
                $q->where('source_key', 'like', "%{$this->search}%")
                  ->orWhere('error_message', 'like', "%{$this->search}%");
            });
        }

        $sort = in_array($this->sortColumn, ['id', 'attempted_at', 'completed_at', 'status'], true)
            ? $this->sortColumn
            : 'id';

        return $query->orderBy($sort, $this->sortDirection)
            ->paginate(15, ['*'], 'runsPage');
    }

    public function getKpiMetricsProperty(): array
    {
        $userId = auth()->id();

        // 1. Bekleyen sipariş (marketplace_finance_bridge_runs'da succeeded bridge_type = order olmayanlar)
        $pendingOrders = ChannelOrder::whereHas('store', function($q) use ($userId) {
            $q->where('user_id', $userId);
        })->whereNotExists(function($q) use ($userId) {
            $q->select('*')
              ->from('marketplace_finance_bridge_runs')
              ->where('marketplace_finance_bridge_runs.user_id', $userId)
              ->where('marketplace_finance_bridge_runs.bridge_type', 'order')
              ->where('marketplace_finance_bridge_runs.status', 'succeeded')
              ->whereColumn('marketplace_finance_bridge_runs.channel_order_id', 'channel_orders.id');
        })->count();

        // 2. Köprülenen sipariş (succeeded run'ı olanlar)
        $bridgedOrders = ChannelOrder::whereHas('store', function($q) use ($userId) {
            $q->where('user_id', $userId);
        })->whereExists(function($q) use ($userId) {
            $q->select('*')
              ->from('marketplace_finance_bridge_runs')
              ->where('marketplace_finance_bridge_runs.user_id', $userId)
              ->where('marketplace_finance_bridge_runs.bridge_type', 'order')
              ->where('marketplace_finance_bridge_runs.status', 'succeeded')
              ->whereColumn('marketplace_finance_bridge_runs.channel_order_id', 'channel_orders.id');
        })->count();

        // 3. Hatalı sipariş
        $failedOrders = MarketplaceFinanceBridgeRun::where('user_id', $userId)
            ->where('bridge_type', 'order')
            ->where('status', 'failed')
            ->count();

        // 4. Bekleyen finans olayı (succeeded run'ı olmayanlar)
        $pendingEvents = OrderFinancialEvent::whereHas('store', function($q) use ($userId) {
            $q->where('user_id', $userId);
        })->whereIn('event_type', ['commission', 'shipping_fee', 'cargo', 'payout', 'settlement'])
          ->whereNotExists(function($q) use ($userId) {
            $q->select('*')
              ->from('marketplace_finance_bridge_runs')
              ->where('marketplace_finance_bridge_runs.user_id', $userId)
              ->where('marketplace_finance_bridge_runs.bridge_type', 'financial_event')
              ->where('marketplace_finance_bridge_runs.status', 'succeeded')
              ->whereColumn('marketplace_finance_bridge_runs.order_financial_event_id', 'order_financial_events.id');
        })->count();

        // 5. Muhasebeleşen finans olayı (succeeded run'ı olanlar)
        $bridgedEvents = OrderFinancialEvent::whereHas('store', function($q) use ($userId) {
            $q->where('user_id', $userId);
        })->whereExists(function($q) use ($userId) {
            $q->select('*')
              ->from('marketplace_finance_bridge_runs')
              ->where('marketplace_finance_bridge_runs.user_id', $userId)
              ->where('marketplace_finance_bridge_runs.bridge_type', 'financial_event')
              ->where('marketplace_finance_bridge_runs.status', 'succeeded')
              ->whereColumn('marketplace_finance_bridge_runs.order_financial_event_id', 'order_financial_events.id');
        })->count();

        // 6. Hatalı finans olayı
        $failedEvents = MarketplaceFinanceBridgeRun::where('user_id', $userId)
            ->where('bridge_type', 'financial_event')
            ->where('status', 'failed')
            ->count();

        // 7. Son başarılı köprüleme
        $lastSuccess = MarketplaceFinanceBridgeRun::where('user_id', $userId)
            ->where('status', 'succeeded')
            ->latest('completed_at')
            ->value('completed_at');

        return [
            'pending_orders'  => $pendingOrders,
            'bridged_orders'  => $bridgedOrders,
            'failed_orders'   => $failedOrders,
            'pending_events'  => $pendingEvents,
            'bridged_events'  => $bridgedEvents,
            'failed_events'   => $failedEvents,
            'last_success_at' => $lastSuccess ? $lastSuccess->format('d.m.Y H:i') : 'Yok',
        ];
    }

    public function isBridgedOrder(int $orderId): bool
    {
        return MarketplaceFinanceBridgeRun::where('user_id', auth()->id())
            ->where('channel_order_id', $orderId)
            ->where('bridge_type', 'order')
            ->where('status', 'succeeded')
            ->exists();
    }

    public function isBridgedEvent(int $eventId): bool
    {
        return MarketplaceFinanceBridgeRun::where('user_id', auth()->id())
            ->where('order_financial_event_id', $eventId)
            ->where('bridge_type', 'financial_event')
            ->where('status', 'succeeded')
            ->exists();
    }

    public function getColumnDefsProperty(): array
    {
        if ($this->activeTab === 'orders') {
            return [
                ['name' => 'id', 'label' => 'ID', 'sortable' => true],
                ['name' => 'store_name', 'label' => 'Mağaza', 'sortable' => false],
                ['name' => 'order_number', 'label' => 'Sipariş No', 'sortable' => true],
                ['name' => 'customer_name', 'label' => 'Müşteri', 'sortable' => true],
                ['name' => 'ordered_at', 'label' => 'Tarih', 'sortable' => true],
                ['name' => 'status', 'label' => 'Durum', 'sortable' => false],
                ['name' => 'actions', 'label' => 'İşlemler', 'sortable' => false],
            ];
        } elseif ($this->activeTab === 'financial_events') {
            return [
                ['name' => 'id', 'label' => 'ID', 'sortable' => true],
                ['name' => 'store_name', 'label' => 'Mağaza', 'sortable' => false],
                ['name' => 'event_type', 'label' => 'İşlem Tipi', 'sortable' => true],
                ['name' => 'amount', 'label' => 'Tutar', 'sortable' => true],
                ['name' => 'event_date', 'label' => 'Tarih', 'sortable' => true],
                ['name' => 'status', 'label' => 'Durum', 'sortable' => false],
                ['name' => 'actions', 'label' => 'İşlemler', 'sortable' => false],
            ];
        } else {
            return [
                ['name' => 'id', 'label' => 'ID', 'sortable' => true],
                ['name' => 'bridge_type', 'label' => 'Tip', 'sortable' => false],
                ['name' => 'source_key', 'label' => 'Kaynak Anahtarı', 'sortable' => false],
                ['name' => 'status', 'label' => 'İşlem Durumu', 'sortable' => true],
                ['name' => 'error_message', 'label' => 'Hata Detayı', 'sortable' => false],
                ['name' => 'attempted_at', 'label' => 'Tarih', 'sortable' => true],
                ['name' => 'actions', 'label' => 'İşlemler', 'sortable' => false],
            ];
        }
    }

    public function render()
    {
        return view('livewire.accounting.marketplace-bridge')
            ->layout('layouts.app');
    }
}
