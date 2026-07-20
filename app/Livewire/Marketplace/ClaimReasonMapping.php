<?php

namespace App\Livewire\Marketplace;

use App\Models\MarketplaceStore;
use App\Models\MpClaimReason;
use Livewire\Component;
use Livewire\WithPagination;

class ClaimReasonMapping extends Component
{
    use WithPagination;

    public int $selectedStoreId = 0;
    
    // Properties for standard table visibility and sorting
    public array $visibleColumns = ['platform_reason_id', 'name', 'mapped_zolm_reason_code', 'is_active', 'updated_at'];
    public string $sortBy = 'name';
    public string $sortDir = 'asc';

    public function getZolmReasonsProperty()
    {
        return \App\Enums\ZolmClaimReason::options();
    }

    public static array $sortableColumns = [
        'platform_reason_id' => 'platform_reason_id',
        'name' => 'name',
        'mapped_zolm_reason_code' => 'mapped_zolm_reason_code',
        'updated_at' => 'updated_at',
    ];

    public static array $allColumnDefs = [
        'platform_reason_id' => 'Platform ID',
        'name' => 'Trendyol İade Nedeni',
        'mapped_zolm_reason_code' => 'ZOLM İç Nedeni',
        'is_active' => 'Durum',
        'updated_at' => 'Son Güncelleme',
    ];

    public function mount()
    {
        $store = MarketplaceStore::where('marketplace', 'trendyol')->where('user_id', auth()->id())->first();
        if ($store) {
            $this->selectedStoreId = $store->id;
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
            $this->sortDir = 'asc';
        }
        
        $this->resetPage();
    }

    public function updateMapping($reasonId, $zolmCode)
    {
        $reason = MpClaimReason::find($reasonId);
        if ($reason && $reason->store_id == $this->selectedStoreId) {
            $reason->mapped_zolm_reason_code = $zolmCode ?: null;
            $reason->save();
        }
    }

    public function render()
    {
        $stores = MarketplaceStore::where('marketplace', 'trendyol')->where('user_id', auth()->id())->get();
        
        $reasons = collect();
        if ($this->selectedStoreId) {
            $reasons = MpClaimReason::where('store_id', $this->selectedStoreId)
                ->orderBy($this->sortBy, $this->sortDir)
                ->paginate(25);
        }

        return view('livewire.marketplace.claim-reason-mapping', [
            'stores' => $stores,
            'reasons' => $reasons,
        ])->layout('layouts.app');
    }
}
