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
    public array $visibleColumns = ['invoice_serial_number', 'parcel_unique_id', 'order_number', 'carrier_code', 'desi', 'amount', 'profit_impact', 'status'];
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
        'amount' => 'Fatura Tutarı',
        'profit_impact' => 'Kâr Etkisi',
        'status' => 'Durum',
        'invoice_date' => 'Fatura Tarihi',
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
            $this->sortDir = 'desc';
        }
        
        $this->resetPage();
    }

    public function render()
    {
        $stores = MarketplaceStore::where('marketplace', 'trendyol')->where('user_id', auth()->id())->get();
        
        $invoices = collect();
        if ($this->selectedStoreId) {
            $invoices = CargoInvoiceLine::where('store_id', $this->selectedStoreId)
                ->orderBy($this->sortBy, $this->sortDir)
                ->paginate(25);
                
            // Eager load related orders and their profit snapshots
            $orderNumbers = $invoices->pluck('order_number')->filter()->unique();
            $orders = \App\Models\ChannelOrder::with('profitSnapshots')
                ->whereIn('order_number', $orderNumbers)
                ->where('store_id', $this->selectedStoreId)
                ->get()
                ->keyBy('order_number');
                
            foreach ($invoices as $invoice) {
                $invoice->related_order = $orders->get($invoice->order_number);
                $invoice->estimated_cargo = 0;
                $invoice->profit_impact = 0;
                
                if ($invoice->related_order && $invoice->related_order->profitSnapshots->isNotEmpty()) {
                    $snapshot = $invoice->related_order->profitSnapshots->first();
                    $invoice->estimated_cargo = $snapshot->cargo_total ?? 0;
                    // Fark = Tahmini - Gerçekleşen
                    // Eğer gerçek fatura 50, tahmini 40 ise -> -10 kâr etkisi
                    $invoice->profit_impact = $invoice->estimated_cargo - $invoice->total_amount;
                }
            }
        }

        return view('livewire.marketplace.cargo-invoice-reconciliation', [
            'stores' => $stores,
            'invoices' => $invoices,
        ])->layout('layouts.app');
    }
}
