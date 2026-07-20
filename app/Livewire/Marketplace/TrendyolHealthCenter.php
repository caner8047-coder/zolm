<?php

namespace App\Livewire\Marketplace;

use App\Models\MarketplaceStore;
use App\Models\MarketplaceSyncRun;
use Livewire\Component;

class TrendyolHealthCenter extends Component
{
    public int $selectedStoreId;

    public function mount()
    {
        $store = MarketplaceStore::where('marketplace', 'trendyol')->where('user_id', auth()->id())->first();
        if ($store) {
            $this->selectedStoreId = $store->id;
        } else {
            $this->selectedStoreId = 0;
        }
    }

    public function updatedSelectedStoreId()
    {
        if ($this->selectedStoreId) {
            $exists = MarketplaceStore::where('id', $this->selectedStoreId)
                ->where('user_id', auth()->id())
                ->exists();
            
            if (! $exists) {
                $this->selectedStoreId = 0;
            }
        }
    }

    public function render()
    {
        $stores = MarketplaceStore::where('marketplace', 'trendyol')->where('user_id', auth()->id())->get();
        
        $runs = collect();
        $latestBatch = null;

        if ($this->selectedStoreId) {
            $runs = MarketplaceSyncRun::where('store_id', $this->selectedStoreId)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
        }

        return view('livewire.marketplace.trendyol-health-center', [
            'stores' => $stores,
            'runs' => $runs,
        ])->layout('layouts.app');
    }
}
