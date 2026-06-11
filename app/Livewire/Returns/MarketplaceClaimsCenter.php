<?php

namespace App\Livewire\Returns;

use App\Models\ChannelClaim;
use App\Models\MarketplaceStore;
use App\Services\ExcelService;
use App\Services\Marketplace\MarketplaceClaimActionService;
use App\Services\Marketplace\MarketplaceConnectorManager;
use App\Services\Marketplace\MarketplaceManualSyncDispatchService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class MarketplaceClaimsCenter extends Component
{
    use WithPagination;

    public bool $embedded = false;
    public string $searchQuery = '';
    public string $statusFilter = 'all';
    public string $marketplaceFilter = 'all';
    public string $storeFilter = 'all';
    public string $dateFilter = 'last7days';
    public ?int $selectedClaimId = null;
    public string $rejectReason = '';
    public string $message = '';
    public string $messageType = 'info';
    public string $sortField = 'created_date';
    public string $sortDirection = 'desc';

    /**
     * @var array<string, bool>
     */
    public array $visibleColumns = [
        'date' => true,
        'marketplace' => true,
        'claim' => true,
        'order' => true,
        'customer' => true,
        'tracking' => true,
        'status' => true,
        'reason' => false,
    ];

    /**
     * @var array<string, string>
     */
    public static array $sortableColumns = [
        'created_date' => 'Tarih',
        'status' => 'Durum',
        'order_number' => 'Sipariş',
        'customer_name' => 'Müşteri',
        'last_synced_at' => 'Son Sync',
    ];

    public function mount(bool $embedded = false): void
    {
        $this->embedded = $embedded;
        abort_unless(auth()->user()?->canAccessReturnsReview(), 403);

        $requestedClaimId = request()->integer('claim');

        if ($requestedClaimId > 0) {
            $this->selectedClaimId = $requestedClaimId;
        }
    }

    public function updatedSearchQuery(): void
    {
        $this->clearMessage();
        $this->resetPage($this->claimsPageName());
    }

    public function updatedStatusFilter(): void
    {
        $this->clearMessage();
        $this->resetPage($this->claimsPageName());
    }

    public function updatedMarketplaceFilter(): void
    {
        $this->clearMessage();
        $this->storeFilter = 'all';
        $this->resetPage($this->claimsPageName());
    }

    public function updatedStoreFilter(): void
    {
        $this->clearMessage();
        $this->resetPage($this->claimsPageName());
    }

    public function updatedDateFilter(): void
    {
        $this->clearMessage();
        $this->resetPage($this->claimsPageName());
    }

    public function selectClaim(int $claimId): void
    {
        $this->selectedClaimId = $claimId;
        $this->rejectReason = '';
    }

    public function toggleColumn(string $column): void
    {
        if (isset($this->visibleColumns[$column])) {
            $this->visibleColumns[$column] = !$this->visibleColumns[$column];
        }
    }

    public function sortTable(string $field): void
    {
        if (!array_key_exists($field, self::$sortableColumns)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'desc';
        }

        $this->resetPage($this->claimsPageName());
    }

    public function syncClaims(): void
    {
        $this->clearMessage();

        $stores = $this->syncableStores();

        if ($stores->isEmpty()) {
            $this->showMessage('İade çekmeye uygun aktif mağaza bulunamadı.', 'error');
            return;
        }

        $queued = 0;
        $debounced = 0;
        $completed = 0;
        $skipped = 0;
        $received = 0;
        $createdItems = 0;
        $updatedItems = 0;
        $errors = [];
        $dispatcher = app(MarketplaceManualSyncDispatchService::class);

        foreach ($stores as $store) {
            try {
                $result = $dispatcher->dispatch($store, 'claims', [
                    'options' => [],
                    'source' => 'returns_claims_center',
                    'origin_screen' => 'returns_marketplace_claims',
                ]);

                if (($result['inline_error'] ?? null) !== null) {
                    $errors[] = $this->formatSyncError($store->store_name, $result['inline_error']);
                    continue;
                }

                $run = $result['run'];

                if (($result['executed_inline'] ?? false) === true) {
                    if ($run->status === 'failed') {
                        $errors[] = $this->formatSyncError($store->store_name, data_get($run->notes_json, 'last_error') ?: 'İade sync başarısız oldu.');
                        continue;
                    }

                    if ($run->status === 'skipped') {
                        $skipped++;
                        continue;
                    }

                    $completed++;
                    $received += (int) $run->items_received;
                    $createdItems += (int) $run->items_created;
                    $updatedItems += (int) $run->items_updated;
                    continue;
                }

                $queued += $result['created'] ? 1 : 0;
                $debounced += $result['debounced'] ? 1 : 0;
            } catch (\Throwable $exception) {
                $errors[] = $this->formatSyncError($store->store_name, $exception->getMessage());
            }
        }

        if ($errors !== []) {
            $this->showMessage('Bazı mağazalarda iade sync tamamlanamadı: ' . implode(' | ', array_slice($errors, 0, 3)), 'error');
            return;
        }

        $parts = [];

        if ($completed > 0) {
            $summary = $received . ' kayıt alındı';

            if ($createdItems > 0) {
                $summary .= ', ' . $createdItems . ' yeni';
            }

            if ($updatedItems > 0) {
                $summary .= ', ' . $updatedItems . ' güncellendi';
            }

            $parts[] = $completed . ' mağazada sync tamamlandı (' . $summary . ')';
        }

        if ($queued > 0) {
            $parts[] = $queued . ' mağaza kuyruğa alındı';
        }

        if ($debounced > 0) {
            $parts[] = $debounced . ' mağazada mevcut sync kullanıldı';
        }

        if ($skipped > 0) {
            $parts[] = $skipped . ' mağazada sync atlandı';
        }

        $this->showMessage(($parts !== [] ? implode(', ', $parts) : 'Yeni sync açılmadı') . '.', 'success');
    }

    public function approveSelectedClaim(MarketplaceClaimActionService $actionService): void
    {
        $claim = $this->selectedClaim;

        if (!$claim) {
            return;
        }

        try {
            $result = $actionService->approveClaim($claim);
            $this->showMessage($result['message'] ?? 'İade onaylandı.', 'success');
        } catch (\Throwable $exception) {
            $this->showMessage('Onay hatası: ' . $exception->getMessage(), 'error');
        }
    }

    public function rejectSelectedClaim(MarketplaceClaimActionService $actionService): void
    {
        $this->validate([
            'rejectReason' => ['required', 'string', 'min:3', 'max:1000'],
        ], [], [
            'rejectReason' => 'red nedeni',
        ]);

        $claim = $this->selectedClaim;

        if (!$claim) {
            return;
        }

        try {
            $result = $actionService->rejectClaim($claim, $this->rejectReason);
            $this->rejectReason = '';
            $this->showMessage($result['message'] ?? 'İade reddedildi.', 'success');
        } catch (\Throwable $exception) {
            $this->showMessage('Red hatası: ' . $exception->getMessage(), 'error');
        }
    }

    public function markNeedsReview(): void
    {
        $claim = $this->selectedClaim;

        if (!$claim) {
            return;
        }

        $claim->update(['status' => 'unresolved']);
        $this->showMessage('İade yerel olarak incelemeye alındı.', 'success');
    }

    public function exportExcel(ExcelService $excelService)
    {
        $claims = $this->buildQuery()->with('items')->get();

        if ($claims->isEmpty()) {
            $this->showMessage('Dışa aktarılacak iade bulunamadı.', 'error');
            return null;
        }

        $rows = $claims->map(fn (ChannelClaim $claim) => [
            'Tarih' => $claim->created_date?->format('d.m.Y H:i') ?? '',
            'Pazaryeri' => $claim->store?->store_name ?? '',
            'İade No' => $claim->external_claim_id,
            'Sipariş No' => $claim->order_number,
            'Müşteri' => $claim->customer_name,
            'Takip No' => $claim->cargo_tracking_number,
            'Kargo' => $claim->cargo_provider,
            'Durum' => $claim->statusLabel(),
            'Neden' => $claim->reason,
            'Kalemler' => $claim->items->map(fn ($item) => trim((string) $item->product_name) . ' x' . (int) $item->quantity)->implode(' | '),
        ])->all();

        $path = storage_path('app/public/pazaryeri_iadeleri_' . now()->format('Ymd_His') . '.xlsx');

        $excelService->exportToXlsx([
            ['name' => 'Pazaryeri İadeleri', 'data' => $rows],
        ], $path);

        return response()->download($path);
    }

    #[Computed]
    public function claims()
    {
        $claims = $this->buildQuery()->paginate(20, ['*'], $this->claimsPageName());

        if (!$this->selectedClaimId && $claims->count() > 0) {
            $this->selectedClaimId = (int) $claims->first()->id;
        }

        return $claims;
    }

    #[Computed]
    public function selectedClaim(): ?ChannelClaim
    {
        if (!$this->selectedClaimId) {
            return null;
        }

        return ChannelClaim::query()
            ->with(['store.connection', 'items'])
            ->find($this->selectedClaimId);
    }

    #[Computed]
    public function kpis(): array
    {
        $base = ChannelClaim::query();

        return [
            'waiting' => (clone $base)->whereIn('status', ['pending', 'shipped', 'in_transit', 'delivered'])->count(),
            'decision' => (clone $base)->where('status', 'delivered')->count(),
            'approved' => (clone $base)->where('status', 'approved')->count(),
            'rejected' => (clone $base)->whereIn('status', ['rejected', 'unresolved'])->count(),
        ];
    }

    #[Computed]
    public function stores()
    {
        return MarketplaceStore::query()
            ->orderBy('store_name')
            ->get(['id', 'marketplace', 'store_name']);
    }

    /**
     * @return array{approve: bool, reject: bool}
     */
    public function actionCapabilities(?ChannelClaim $claim): array
    {
        if (!$claim?->store) {
            return ['approve' => false, 'reject' => false];
        }

        try {
            $capabilities = app(MarketplaceConnectorManager::class)
                ->resolve($claim->store->marketplace)
                ->capabilities();

            return [
                'approve' => (bool) ($capabilities['claim_approve'] ?? false),
                'reject' => (bool) ($capabilities['claim_reject'] ?? false),
            ];
        } catch (\Throwable) {
            return ['approve' => false, 'reject' => false];
        }
    }

    protected function buildQuery(): Builder
    {
        return ChannelClaim::query()
            ->with(['store', 'items'])
            ->when($this->dateFilter === 'today', fn ($builder) => $builder->whereDate('created_date', today()))
            ->when($this->dateFilter === 'yesterday', fn ($builder) => $builder->whereDate('created_date', today()->subDay()))
            ->when($this->dateFilter === 'last7days', fn ($builder) => $builder->where('created_date', '>=', today()->subDays(7)))
            ->when($this->dateFilter === 'last30days', fn ($builder) => $builder->where('created_date', '>=', today()->subDays(30)))
            ->when($this->statusFilter !== 'all', fn ($builder) => $builder->where('status', $this->statusFilter))
            ->when($this->marketplaceFilter !== 'all', function ($builder) {
                $builder->whereHas('store', fn ($storeQuery) => $storeQuery->where('marketplace', $this->marketplaceFilter));
            })
            ->when($this->storeFilter !== 'all', fn ($builder) => $builder->where('store_id', (int) $this->storeFilter))
            ->when($this->searchQuery !== '', function ($builder) {
                $search = '%' . $this->searchQuery . '%';

                $builder->where(function ($query) use ($search) {
                    $query->where('external_claim_id', 'like', $search)
                        ->orWhere('order_number', 'like', $search)
                        ->orWhere('customer_name', 'like', $search)
                        ->orWhere('cargo_tracking_number', 'like', $search)
                        ->orWhere('reason', 'like', $search)
                        ->orWhereHas('items', function ($itemQuery) use ($search) {
                            $itemQuery->where('product_name', 'like', $search)
                                ->orWhere('barcode', 'like', $search)
                                ->orWhere('stock_code', 'like', $search);
                        });
                });
            })
            ->orderBy($this->safeSortField(), $this->safeSortDirection())
            ->orderByDesc('id');
    }

    protected function safeSortField(): string
    {
        return array_key_exists($this->sortField, self::$sortableColumns)
            ? $this->sortField
            : 'created_date';
    }

    protected function safeSortDirection(): string
    {
        return $this->sortDirection === 'asc' ? 'asc' : 'desc';
    }

    protected function syncableStores()
    {
        $manager = app(MarketplaceConnectorManager::class);

        return MarketplaceStore::query()
            ->with(['connection', 'syncProfile'])
            ->where('is_active', true)
            ->when($this->storeFilter !== 'all', fn ($builder) => $builder->whereKey((int) $this->storeFilter))
            ->when($this->marketplaceFilter !== 'all', fn ($builder) => $builder->where('marketplace', $this->marketplaceFilter))
            ->whereHas('connection', fn ($query) => $query->whereIn('status', ['configured', 'connected']))
            ->get()
            ->filter(function (MarketplaceStore $store) use ($manager): bool {
                try {
                    return (bool) ($manager->resolve($store->marketplace)->capabilities()['claims'] ?? false);
                } catch (\Throwable) {
                    return false;
                }
            })
            ->values();
    }

    protected function claimsPageName(): string
    {
        return 'marketplaceClaimsPage';
    }

    protected function showMessage(string $message, string $type): void
    {
        $this->message = $message;
        $this->messageType = $type;
    }

    protected function clearMessage(): void
    {
        $this->message = '';
        $this->messageType = 'info';
    }

    protected function formatSyncError(string $storeName, mixed $message): string
    {
        $text = trim(preg_replace('/\s+/', ' ', (string) $message));

        return $storeName . ': ' . Str::limit($text !== '' ? $text : 'Bilinmeyen hata', 260);
    }

    public function render(): View
    {
        $view = view('livewire.returns.marketplace-claims-center', [
            'claims' => $this->claims,
            'selectedClaim' => $this->selectedClaim,
            'kpis' => $this->kpis,
            'stores' => $this->stores,
            'sortableColumns' => self::$sortableColumns,
            'statusLabels' => ChannelClaim::STATUS_LABELS,
            'actionCapabilities' => $this->actionCapabilities($this->selectedClaim),
        ]);

        if ($this->embedded) {
            return $view;
        }

        return $view->layout('layouts.app', [
            'title' => 'Pazaryeri İadeleri',
        ]);
    }
}
