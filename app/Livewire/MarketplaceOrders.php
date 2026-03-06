<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Models\MpOperationalOrder;
use App\Jobs\ProcessDetailedOrderImport;
use App\Jobs\SyncOperationalToFinancialJob;

class MarketplaceOrders extends Component
{
    use WithPagination, WithFileUploads;

    // ─── Arama & Filtre ─────────────────────────────────────────
    public $search = '';
    public $searchCustomer = '';
    public $searchBarcode = '';
    public $statusFilter = '';
    public $cityFilter = '';
    public $brandFilter = '';
    public $dateFrom = '';
    public $dateTo = '';
    public $corporateFilter = '';

    // ─── Import ─────────────────────────────────────────────────
    public $file;
    public $isImporting = false;
    public $importMessage = '';

    protected $queryString = [
        'search'         => ['except' => ''],
        'searchCustomer' => ['except' => ''],
        'searchBarcode'  => ['except' => ''],
        'statusFilter'   => ['except' => ''],
        'cityFilter'     => ['except' => ''],
        'brandFilter'    => ['except' => ''],
        'dateFrom'       => ['except' => ''],
        'dateTo'         => ['except' => ''],
        'corporateFilter'=> ['except' => ''],
    ];

    public function updatingSearch() { $this->resetPage(); }
    public function updatingSearchCustomer() { $this->resetPage(); }
    public function updatingSearchBarcode() { $this->resetPage(); }
    public function updatingStatusFilter() { $this->resetPage(); }
    public function updatingCityFilter() { $this->resetPage(); }
    public function updatingBrandFilter() { $this->resetPage(); }
    public function updatingDateFrom() { $this->resetPage(); }
    public function updatingDateTo() { $this->resetPage(); }
    public function updatingCorporateFilter() { $this->resetPage(); }

    public function importOrders()
    {
        if (empty($this->file)) {
            $this->addError('file', 'Dosya sisteme ulaşmadı. Eğer Excel dosyanız büyükse PHP limitlerine takılmış olabilir. (Çözüm: Laragon Sağ Tık -> PHP -> Quick Settings kısmından upload_max_filesize ve post_max_size değerlerini 100M yapıp Apache\'yi yeniden başlatın.)');
            return;
        }

        $this->validate([
            'file' => 'required|mimes:xlsx,xls|max:51200', // 50MB max
        ]);

        $this->isImporting = true;
        
        $path = $this->file->store('marketplace-imports');
        $absolutePath = Storage::path($path);

        try {
            set_time_limit(300);

            $importService = app(\App\Services\DetailedOrderImportService::class);
            $importService->importDetailedOrders($absolutePath);

            $this->importMessage = '✅ İçe aktarım başarıyla tamamlandı! Siparişleriniz aşağıda listelenmiştir.';
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('ImportOrders Sync Error', ['error' => $e->getMessage()]);
            $this->importMessage = '❌ İçe aktarım sırasında hata oluştu: ' . $e->getMessage();
        } finally {
            if (\Illuminate\Support\Facades\File::exists($absolutePath)) {
                \Illuminate\Support\Facades\File::delete($absolutePath);
            }
            $this->isImporting = false;
            $this->file = null;
        }
    }

    public function runSyncEngine()
    {
        try {
            set_time_limit(300);
            
            $syncJob = new SyncOperationalToFinancialJob();
            $syncJob->handle();
            
            session()->flash('sync_message', '✅ Finansal senkronizasyon başarıyla tamamlandı! Kâr Motoru ve Muhasebe verileri güncellendi.');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('SyncEngine Error', ['error' => $e->getMessage()]);
            session()->flash('sync_message', '❌ Senkronizasyon sırasında hata oluştu: ' . $e->getMessage());
        }
    }

    public function resetFilters()
    {
        $this->reset([
            'search', 'searchCustomer', 'searchBarcode',
            'statusFilter', 'cityFilter', 'brandFilter',
            'dateFrom', 'dateTo', 'corporateFilter',
        ]);
        $this->resetPage();
    }

    public function exportCsv()
    {
        $query = $this->buildQuery();
        $orders = $query->get();

        $filename = "pazaryeri_siparislerim_" . date('Ymd_His') . ".csv";

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        return response()->stream(function () use ($orders) {
            $file = fopen('php://output', 'w');
            
            // Excel UTF-8 BOM
            fputs($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'Sipariş No', 'Paket No', 'Barkod', 'Stok Kodu', 'Marka', 'Ürün Adı', 'Miktar',
                'Birim Fiyat', 'Satış Tutarı', 'İndirim', 'Trendyol İndirimi', 'Faturalanacak Tutar',
                'Komisyon Oranı %', 'Desi (Kargo)', 'Desi (Hesaplanan)',
                'Müşteri', 'Telefon', 'E-Posta', 'Şehir', 'İlçe', 'Ülke',
                'Yaş', 'Cinsiyet', 'Sipariş Adedi',
                'Kargo Firması', 'Kargo Kodu', 'Takip No',
                'Sipariş Tarihi', 'Teslim Tarihi', 'Termin Tarihi', 'Kargoya Teslim',
                'Fatura No', 'Fatura Tarihi', 'Kurumsal Fatura',
                'Durum', 'Alt. Teslimat',
                'Muhasebe: Net Hakediş', 'Muhasebe: Komisyon TL', 'Muhasebe: Net Kâr',
            ], ';');

            foreach ($orders as $order) {
                foreach ($order->items as $item) {
                    // Muhasebeden finansal veriler
                    $financialData = $order->financialOrders->where('barcode', $item->barcode)->first();

                    $row = [
                        $order->order_number,
                        $this->safeExcelString($order->package_number),
                        $this->safeExcelString($item->barcode),
                        $this->safeExcelString($item->stock_code),
                        $this->safeExcelString($item->brand),
                        $this->safeExcelString($item->product_name),
                        $item->quantity,
                        $item->unit_price,
                        $item->sale_price,
                        $item->discount_amount,
                        $item->trendyol_discount,
                        $item->billable_amount,
                        $item->commission_rate,
                        $item->cargo_desi,
                        $item->calculated_desi,
                        $this->safeExcelString($order->customer_name),
                        $this->safeExcelString($order->customer_phone),
                        $this->safeExcelString($order->email),
                        $this->safeExcelString($order->customer_city),
                        $this->safeExcelString($order->customer_district),
                        $this->safeExcelString($order->country),
                        $this->safeExcelString($order->customer_age),
                        $this->safeExcelString($order->customer_gender),
                        $this->safeExcelString($order->customer_order_count),
                        $this->safeExcelString($order->cargo_company),
                        $this->safeExcelString($order->cargo_code),
                        $this->safeExcelString($order->tracking_number),
                        $order->order_date ? $order->order_date->format('d/m/Y H:i') : '',
                        $order->delivery_date ? $order->delivery_date->format('d/m/Y H:i') : '',
                        $order->deadline_date ? $order->deadline_date->format('d/m/Y') : '',
                        $order->cargo_delivery_date ? $order->cargo_delivery_date->format('d/m/Y H:i') : '',
                        $this->safeExcelString($order->invoice_number),
                        $order->invoice_date ? $order->invoice_date->format('d/m/Y') : '',
                        $this->safeExcelString($order->is_corporate_invoice),
                        $this->safeExcelString($order->status),
                        $this->safeExcelString($order->alt_delivery_status),
                        $financialData ? $financialData->net_hakedis : '',
                        $financialData ? $financialData->commission_amount : '',
                        $financialData ? $financialData->real_net_profit : '',
                    ];
                    fputcsv($file, $row, ';');
                }
            }

            fclose($file);
        }, 200, $headers);
    }

    protected function safeExcelString($val): string
    {
        if (empty($val)) return '';
        return preg_replace('/[\x00-\x1F\x7F]/u', '', (string)$val);
    }

    protected function buildQuery()
    {
        $query = MpOperationalOrder::with(['items.product', 'financialOrders'])->orderByDesc('order_date');

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('order_number', 'like', '%' . $this->search . '%')
                  ->orWhere('package_number', 'like', '%' . $this->search . '%');
            });
        }

        if (!empty($this->searchCustomer)) {
            $query->where(function ($q) {
                $q->where('customer_name', 'like', '%' . $this->searchCustomer . '%')
                  ->orWhere('customer_phone', 'like', '%' . $this->searchCustomer . '%')
                  ->orWhere('email', 'like', '%' . $this->searchCustomer . '%');
            });
        }

        if (!empty($this->statusFilter)) {
            $query->where('status', $this->statusFilter);
        }

        if (!empty($this->cityFilter)) {
            $query->where('customer_city', $this->cityFilter);
        }

        if (!empty($this->corporateFilter)) {
            $query->where('is_corporate_invoice', $this->corporateFilter);
        }

        if (!empty($this->dateFrom)) {
            $query->whereDate('order_date', '>=', $this->dateFrom);
        }

        if (!empty($this->dateTo)) {
            $query->whereDate('order_date', '<=', $this->dateTo);
        }

        if (!empty($this->searchBarcode)) {
            $query->whereHas('items', function ($q) {
                $q->where('barcode', 'like', '%' . $this->searchBarcode . '%')
                  ->orWhere('product_name', 'like', '%' . $this->searchBarcode . '%')
                  ->orWhere('stock_code', 'like', '%' . $this->searchBarcode . '%');
            });
        }

        if (!empty($this->brandFilter)) {
            $query->whereHas('items', function ($q) {
                $q->where('brand', $this->brandFilter);
            });
        }

        return $query;
    }

    /**
     * Özet istatistikleri hesapla (sayfa genelinde, filtrelere bağlı)
     */
    protected function getStats()
    {
        $baseQuery = $this->buildQuery();
        $cloned = clone $baseQuery;

        // Toplam sipariş sayısı
        $totalOrders = $cloned->reorder()->count();
        
        $cloned2 = clone $baseQuery;
        $financialAgg = $cloned2->reorder()->selectRaw('
            SUM(total_gross_amount) as total_revenue,
            SUM(total_discount) as total_discount,
            AVG(total_gross_amount) as avg_order_value
        ')->first();

        // Durum dağılımı
        $cloned3 = clone $baseQuery;
        $statusCounts = $cloned3->reorder()->select('status', DB::raw('count(*) as cnt'))
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        return [
            'total_orders'    => $totalOrders,
            'total_revenue'   => (float) ($financialAgg->total_revenue ?? 0),
            'total_discount'  => (float) ($financialAgg->total_discount ?? 0),
            'avg_order_value' => (float) ($financialAgg->avg_order_value ?? 0),
            'delivered'       => $statusCounts['Teslim Edildi'] ?? 0,
            'in_transit'      => ($statusCounts['Kargoda'] ?? 0) + ($statusCounts['Tedarik Sürecinde'] ?? 0),
            'cancelled'       => $statusCounts['İptal Edildi'] ?? 0,
            'returned'        => $statusCounts['İade Edildi'] ?? 0,
        ];
    }

    public function render()
    {
        $stats = $this->getStats();

        return view('livewire.marketplace-orders', [
            'orders'         => $this->buildQuery()->paginate(30),
            'uniqueStatuses' => MpOperationalOrder::select('status')->distinct()->whereNotNull('status')->pluck('status'),
            'uniqueCities'   => MpOperationalOrder::select('customer_city')->distinct()->whereNotNull('customer_city')->orderBy('customer_city')->pluck('customer_city'),
            'uniqueBrands'   => \App\Models\MpOperationalOrderItem::select('brand')->distinct()->whereNotNull('brand')->where('brand', '!=', '')->orderBy('brand')->pluck('brand'),
            'stats'          => $stats,
        ])->layout('layouts.app', ['title' => 'Pazaryeri Siparişlerim (İç Dağıtım)']);
    }
}
