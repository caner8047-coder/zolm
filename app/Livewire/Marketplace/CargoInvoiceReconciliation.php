<?php

namespace App\Livewire\Marketplace;

use App\Models\CargoInvoiceLine;
use App\Models\ChannelOrder;
use App\Models\MarketplaceStore;
use Livewire\Component;
use Livewire\WithPagination;

class CargoInvoiceReconciliation extends Component
{
    use WithPagination;

    public int $selectedStoreId = 0;

    // Filters
    public string $filterStatus = '';  // '' | 'matched' | 'pending' | 'order_not_found'
    public ?string $filterDateFrom = null;
    public ?string $filterDateTo = null;
    public string $filterDirection = ''; // '' | 'OUTBOUND' | 'RETURN'
    public int $perPage = 25;

    // Standard table columns
    public array $visibleColumns = ['invoice_serial_number', 'order_number', 'carrier_code', 'cargo_direction', 'desi', 'amount', 'estimated_cargo', 'profit_impact', 'reconciliation_status', 'invoice_date'];
    public string $sortBy = 'invoice_date';
    public string $sortDir = 'desc';

    public static array $sortableColumns = [
        'invoice_serial_number' => 'invoice_serial_number',
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
        'cargo_direction' => 'Yön',
        'desi' => 'Desi',
        'amount' => 'Gerçek Kargo Maliyeti',
        'estimated_cargo' => 'Tahmini Kargo',
        'profit_impact' => 'Kâr Etkisi',
        'reconciliation_status' => 'Durum',
        'invoice_date' => 'Fatura Tarihi',
        'updated_at' => 'Son Güncelleme',
    ];

    /** Durum sabitleri */
    const STATUS_MATCHED = 'EŞLEŞTİ';
    const STATUS_PENDING = 'BEKLİYOR';
    const STATUS_ORDER_NOT_FOUND = 'SİPARİŞ BULUNAMADI';
    const STATUS_SNAPSHOT_NOT_FOUND = 'SNAPSHOT BULUNAMADI';
    const STATUS_CURRENCY_MISMATCH = 'PARA BİRİMİ UYUŞMUYOR';

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

    public function updatedFilterStatus(): void { $this->resetPage(); }
    public function updatedFilterDirection(): void { $this->resetPage(); }
    public function updatedFilterDateFrom(): void { $this->resetPage(); }
    public function updatedFilterDateTo(): void { $this->resetPage(); }

    protected function resolveStore(): ?MarketplaceStore
    {
        if (! $this->selectedStoreId) {
            return null;
        }

        return MarketplaceStore::where('id', $this->selectedStoreId)
            ->where('user_id', auth()->id())
            ->first();
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
        if (! isset(self::$sortableColumns[$column])) {
            return;
        }

        if ($this->sortBy === self::$sortableColumns[$column]) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = self::$sortableColumns[$column];
            $this->sortDir = 'desc';
        }

        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->filterStatus = '';
        $this->filterDirection = '';
        $this->filterDateFrom = null;
        $this->filterDateTo = null;
        $this->resetPage();
    }

    public function render()
    {
        $featureEnabled = config('marketplace.trendyol.cargo_invoice_sync_enabled', false);

        $stores = MarketplaceStore::where('marketplace', 'trendyol')
            ->where('user_id', auth()->id())
            ->get();

        $invoices = collect();
        $store = $this->resolveStore();

        if ($store) {
            $query = CargoInvoiceLine::where('store_id', $store->id);

            // Direction filter
            if ($this->filterDirection !== '') {
                $query->where('cargo_type', $this->filterDirection);
            }

            // Date range filter
            if ($this->filterDateFrom) {
                $query->where('invoice_date', '>=', $this->filterDateFrom);
            }
            if ($this->filterDateTo) {
                $query->where('invoice_date', '<=', $this->filterDateTo);
            }

            // Status filter — matched means has order_number
            if ($this->filterStatus === 'matched') {
                $query->whereNotNull('order_number');
            } elseif ($this->filterStatus === 'pending' || $this->filterStatus === 'order_not_found') {
                $query->whereNull('order_number');
            }

            $invoices = $query->orderBy($this->sortBy, $this->sortDir)->paginate($this->perPage);

            // Eager load related orders + profit snapshots (N+1 prevention)
            $orderNumbers = $invoices->pluck('order_number')->filter()->unique()->values();

            $orders = ChannelOrder::with('profitSnapshots')
                ->whereIn('order_number', $orderNumbers)
                ->where('store_id', $store->id)
                ->get()
                ->keyBy('order_number');

            foreach ($invoices as $invoice) {
                $relatedOrder = $orders->get($invoice->order_number);
                $invoice->related_order = $relatedOrder;
                $invoice->estimated_cargo = 0;
                $invoice->profit_impact = 0;

                // Determine reconciliation status
                if (! $invoice->order_number) {
                    $invoice->reconciliation_status = self::STATUS_PENDING;
                } elseif (! $relatedOrder) {
                    $invoice->reconciliation_status = self::STATUS_ORDER_NOT_FOUND;
                } elseif ($relatedOrder->profitSnapshots->isEmpty()) {
                    $invoice->reconciliation_status = self::STATUS_SNAPSHOT_NOT_FOUND;
                } else {
                    $snapshot = $relatedOrder->profitSnapshots->first();

                    // Currency mismatch guard — don't silently mix currencies
                    $snapshotCurrency = $snapshot->currency ?? 'TRY';
                    $invoiceCurrency = $invoice->currency ?? 'TRY';

                    if ($snapshotCurrency !== $invoiceCurrency) {
                        $invoice->reconciliation_status = self::STATUS_CURRENCY_MISMATCH;
                    } else {
                        $invoice->estimated_cargo = (float) ($snapshot->cargo_total ?? 0);
                        // Kâr etkisi = Tahmini - Gerçek (negatif = gerçek fazla)
                        $invoice->profit_impact = round($invoice->estimated_cargo - (float) $invoice->total_amount, 2);
                        $invoice->reconciliation_status = self::STATUS_MATCHED;
                    }
                }

                // Cargo direction human label
                $invoice->direction_label = match ($invoice->cargo_type) {
                    'OUTBOUND' => 'Gidiş',
                    'RETURN' => 'İade',
                    default => '-',
                };
            }
        }

        return view('livewire.marketplace.cargo-invoice-reconciliation', [
            'stores' => $stores,
            'invoices' => $invoices,
            'featureEnabled' => $featureEnabled,
        ])->layout('layouts.app');
    }
}
