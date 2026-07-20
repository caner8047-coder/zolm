<?php

namespace App\Livewire\Marketplace;

use App\Models\CargoInvoiceLine;
use App\Models\MarketplaceStore;
use Livewire\Component;
use Livewire\WithPagination;

class CargoInvoiceReconciliation extends Component
{
    use WithPagination;

    public int $selectedStoreId = 0;
    
    // Properties for standard table visibility and sorting
    public array $visibleColumns = ['invoice_serial_number', 'parcel_unique_id', 'order_number', 'carrier_code', 'desi', 'amount', 'status'];
    public string $sortBy = 'invoice_date';
    public string $sortDir = 'desc';

    public static array $sortableColumns = [
        'invoice_serial_number' => 'invoice_serial_number',
        'parcel_unique_id' => 'parcel_unique_id',
        'order_number' => 'order_number',
        'desi' => 'desi',
        'amount' => 'amount',
        'invoice_date' => 'invoice_date',
    ];

    public static array $allColumnDefs = [
        'invoice_serial_number' => 'Fatura Seri No',
        'parcel_unique_id' => 'Paket ID',
        'order_number' => 'Sipariş No',
        'carrier_code' => 'Kargo Firması',
        'desi' => 'Desi',
        'amount' => 'Tutar',
        'status' => 'Durum',
        'invoice_date' => 'Fatura Tarihi',
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
        
        $invoices = collect();
        if ($this->selectedStoreId) {
            $invoices = CargoInvoiceLine::where('store_id', $this->selectedStoreId)
                ->orderBy($this->sortBy, $this->sortDir)
                ->paginate(25);
        }

        return view('livewire.marketplace.cargo-invoice-reconciliation', [
            'stores' => $stores,
            'invoices' => $invoices,
        ])->layout('layouts.app');
    }
}
