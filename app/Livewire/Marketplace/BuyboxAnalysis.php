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
    
    // Properties for standard table visibility and sorting
    public array $visibleColumns = ['barcode', 'buybox_price', 'my_price', 'winner_seller_id', 'status', 'last_updated'];
    public string $sortBy = 'updated_at';
    public string $sortDir = 'desc';

    public static array $sortableColumns = [
        'barcode' => 'barcode',
        'buybox_price' => 'buybox_price',
        'my_price' => 'my_price',
        'last_updated' => 'updated_at',
    ];

    public static array $allColumnDefs = [
        'barcode' => 'Barkod / SKU',
        'buybox_price' => 'Buybox Fiyatı',
        'my_price' => 'Fiyatım',
        'winner_seller_id' => 'Kazanan Satıcı',
        'status' => 'Durum',
        'last_updated' => 'Son Güncelleme',
    ];

    public function mount()
    {
        $store = MarketplaceStore::where('type', 'trendyol_v2')->where('user_id', auth()->id())->first();
        if ($store) {
            $this->selectedStoreId = $store->id;
        }
    }

    public function toggleColumn(string $column)
    {
        if (in_array($column, $this->visibleColumns)) {
            $this->visibleColumns = array_diff($this->visibleColumns, [$column]);
        } else {
            $this->visibleColumns[] = $column;
        }
    }

    public function sortTable(string $column)
    {
        if (!isset(self::$sortableColumns[$column])) return;

        if ($this->sortBy === self::$sortableColumns[$column]) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = self::$sortableColumns[$column];
            $this->sortDir = 'desc';
        }
        
        $this->resetPage();
    }

    public function render()
    {
        $stores = MarketplaceStore::where('type', 'trendyol_v2')->where('user_id', auth()->id())->get();
        
        $listings = collect();
        if ($this->selectedStoreId) {
            $listings = MpBuyboxListing::where('store_id', $this->selectedStoreId)
                ->orderBy($this->sortBy, $this->sortDir)
                ->paginate(25);
        }

        return view('livewire.marketplace.buybox-analysis', [
            'stores' => $stores,
            'listings' => $listings,
        ])->layout('layouts.app');
    }
}
