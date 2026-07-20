<?php

namespace App\Livewire\Marketplace;

use App\Models\MarketplaceStore;
use App\Models\MpBuyboxListing;
use Livewire\Component;
use Livewire\WithPagination;

class BuyboxAnalysis extends Component
{
    use WithPagination;

    public int $selectedStoreId = 0;

    // Filters
    public string $filterBarcode = '';
    public string $filterProductName = '';
    public string $filterStatus = ''; // '' | 'winning' | 'losing'
    public string $filterStale = ''; // '' | 'fresh' | 'stale'
    public ?string $filterDateFrom = null;
    public ?string $filterDateTo = null;
    public float $filterMinPriceDiff = 0.0;
    public float $filterMaxPriceDiff = 999999.0;
    public int $perPage = 25;

    // Standard table columns
    public array $visibleColumns = ['barcode', 'buybox_price', 'my_price', 'price_diff', 'seller_rank', 'status', 'last_updated'];
    public string $sortBy = 'updated_at';
    public string $sortDir = 'desc';

    public static array $sortableColumns = [
        'barcode' => 'barcode',
        'buybox_price' => 'buybox_price',
        'my_price' => 'seller_price',
        'price_diff' => 'buybox_price', // derivative, sort by buybox_price
        'seller_rank' => 'seller_rank',
        'last_updated' => 'updated_at',
    ];

    public static array $allColumnDefs = [
        'barcode' => 'Barkod / SKU',
        'buybox_price' => 'Buybox Fiyatı',
        'my_price' => 'Fiyatım',
        'price_diff' => 'Fiyat Farkı',
        'price_diff_pct' => '% Fark',
        'seller_rank' => 'Sıra',
        'winner_seller_id' => 'Kazanan Satıcı',
        'second_price' => '2. Fiyat',
        'third_price' => '3. Fiyat',
        'has_multiple_sellers' => 'Çoklu Satıcı',
        'status' => 'Durum',
        'last_updated' => 'Son Güncelleme',
    ];

    public function mount(): void
    {
        $store = MarketplaceStore::where('marketplace', 'trendyol')
            ->where('user_id', auth()->id())
            ->first();

        $this->selectedStoreId = $store?->id ?? 0;
    }

    public function updatedSelectedStoreId(): void
    {
        if ($this->selectedStoreId) {
            $store = $this->resolveStore();
            if (! $store) {
                $this->selectedStoreId = 0;
            }
        }
        $this->resetPage();
    }

    // Reset page when any filter changes
    public function updatedFilterBarcode(): void { $this->resetPage(); }
    public function updatedFilterProductName(): void { $this->resetPage(); }
    public function updatedFilterStatus(): void { $this->resetPage(); }
    public function updatedFilterStale(): void { $this->resetPage(); }
    public function updatedFilterDateFrom(): void { $this->resetPage(); }
    public function updatedFilterDateTo(): void { $this->resetPage(); }
    public function updatedFilterMinPriceDiff(): void { $this->resetPage(); }
    public function updatedFilterMaxPriceDiff(): void { $this->resetPage(); }

    /**
     * Store sahipliği doğrulaması — her action'da kullanılır.
     */
    protected function resolveStore(): ?MarketplaceStore
    {
        if (! $this->selectedStoreId) {
            return null;
        }

        return MarketplaceStore::where('id', $this->selectedStoreId)
            ->where('user_id', auth()->id())
            ->first();
    }

    public function clearFilters(): void
    {
        $this->filterBarcode = '';
        $this->filterProductName = '';
        $this->filterStatus = '';
        $this->filterStale = '';
        $this->filterDateFrom = null;
        $this->filterDateTo = null;
        $this->filterMinPriceDiff = 0.0;
        $this->filterMaxPriceDiff = 999999.0;
        $this->resetPage();
    }

    public function toggleColumn(string $column): void
    {
        if (in_array($column, $this->visibleColumns)) {
            $this->visibleColumns = array_values(array_diff($this->visibleColumns, [$column]));
        } else {
            $this->visibleColumns[] = $column;
        }
    }

    public function sortTable(string $column): void
    {
        // Only allow sorting on whitelisted columns
        if (! isset(self::$sortableColumns[$column])) {
            return;
        }

        $dbColumn = self::$sortableColumns[$column];

        if ($this->sortBy === $dbColumn) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $dbColumn;
            $this->sortDir = 'desc';
        }

        $this->resetPage();
    }

    public function render()
    {
        $featureEnabled = config('marketplace.trendyol.buybox_sync_enabled', false);

        $stores = MarketplaceStore::where('marketplace', 'trendyol')
            ->where('user_id', auth()->id())
            ->get();

        $listings = collect();
        $store = $this->resolveStore();

        if ($store) {
            $query = MpBuyboxListing::where('store_id', $store->id);

            // Filters
            if ($this->filterBarcode !== '') {
                $query->where('barcode', 'like', '%' . $this->filterBarcode . '%');
            }

            if ($this->filterStatus === 'winning') {
                $query->where('seller_rank', 1);
            } elseif ($this->filterStatus === 'losing') {
                $query->where(fn ($q) => $q->whereNull('seller_rank')->orWhere('seller_rank', '!=', 1));
            }

            // Stale filter: retrieved_at < 60 min
            $staleThreshold = now()->subMinutes(60);
            if ($this->filterStale === 'stale') {
                $query->where('retrieved_at', '<', $staleThreshold);
            } elseif ($this->filterStale === 'fresh') {
                $query->where('retrieved_at', '>=', $staleThreshold);
            }

            if ($this->filterDateFrom) {
                $query->where('retrieved_at', '>=', $this->filterDateFrom);
            }
            if ($this->filterDateTo) {
                $query->where('retrieved_at', '<=', $this->filterDateTo . ' 23:59:59');
            }

            // Price diff filter (absolute difference between buybox_price and seller_price)
            if ($this->filterMinPriceDiff > 0 || $this->filterMaxPriceDiff < 999999.0) {
                $query->whereRaw(
                    'ABS(COALESCE(buybox_price, 0) - COALESCE(seller_price, 0)) BETWEEN ? AND ?',
                    [max(0, $this->filterMinPriceDiff), $this->filterMaxPriceDiff]
                );
            }

            $listings = $query->orderBy($this->sortBy, $this->sortDir)
                ->paginate($this->perPage);

            // Computed fields — null-safe
            foreach ($listings as $listing) {
                $buybox = $listing->buybox_price !== null ? (float) $listing->buybox_price : null;
                $mine = $listing->seller_price !== null ? (float) $listing->seller_price : null;

                $listing->price_diff = ($buybox !== null && $mine !== null)
                    ? round($mine - $buybox, 2)
                    : null;

                $listing->price_diff_pct = ($buybox !== null && $mine !== null && $buybox != 0)
                    ? round((($mine - $buybox) / $buybox) * 100, 1)
                    : null;

                $listing->i_am_winner = $listing->seller_rank === 1;
                $listing->is_stale = $listing->retrieved_at
                    && $listing->retrieved_at->lt($staleThreshold);
            }
        }

        return view('livewire.marketplace.buybox-analysis', [
            'stores' => $stores,
            'listings' => $listings,
            'featureEnabled' => $featureEnabled,
            'hasActiveFilters' => $this->filterBarcode !== ''
                || $this->filterStatus !== ''
                || $this->filterStale !== '',
        ])->layout('layouts.app');
    }
}
