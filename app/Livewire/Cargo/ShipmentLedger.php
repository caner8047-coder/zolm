<?php

namespace App\Livewire\Cargo;

use App\Models\CargoInvoiceLine;
use App\Models\ChannelClaim;
use App\Models\ChannelOrderPackage;
use App\Models\Shipment;
use App\Models\SupplyOrder;
use App\Services\Cargo\CargoShipmentService;
use App\Services\ExcelService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class ShipmentLedger extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public string $flowFilter = '';
    public string $directionFilter = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $message = '';
    public string $messageTone = 'info';
    public $invoiceFile;
    public string $invoiceNumber = '';
    public string $invoiceDate = '';

    public array $visibleColumns = [
        'shipment',
        'customer',
        'carrier',
        'cost',
        'status',
        'actions',
    ];

    public static array $columnDefs = [
        'shipment' => 'Gönderi',
        'customer' => 'Alıcı',
        'carrier' => 'Kargo',
        'cost' => 'Maliyet',
        'status' => 'Durum',
        'actions' => 'İşlem',
    ];

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'flowFilter' => ['except' => ''],
        'directionFilter' => ['except' => ''],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedFlowFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDirectionFilter(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function tableReady(): bool
    {
        return Schema::hasTable('shipments');
    }

    #[Computed]
    public function stats(): array
    {
        if (!$this->tableReady) {
            return [
                'total' => 0,
                'active' => 0,
                'delivered' => 0,
                'exceptions' => 0,
                'invoice_delta' => 0,
            ];
        }

        $query = $this->baseQuery();

        return [
            'total' => (clone $query)->count(),
            'active' => (clone $query)->whereIn('status', Shipment::ACTIVE_STATUSES)->count(),
            'delivered' => (clone $query)->where('status', 'delivered')->count(),
            'exceptions' => (clone $query)->whereIn('status', ['exception', 'failed'])->count(),
            'invoice_delta' => (float) (clone $query)->sum('cost_delta'),
        ];
    }

    #[Computed]
    public function shipments()
    {
        if (!$this->tableReady) {
            return collect();
        }

        return $this->baseQuery()
            ->with([
                'store:id,store_name,marketplace',
                'order:id,order_number,customer_name',
                'package:id,package_number,cargo_tracking_number,cargo_company',
                'claim:id,external_claim_id,status,type',
                'supplyOrder:id,siparis_no,musteri_adi',
                'carrierAccount:id,account_name,customer_code,status',
            ])
            ->latest('updated_at')
            ->paginate(30);
    }

    public function createDraftsFromMarketplacePackages(): void
    {
        if (!$this->tableReady) {
            $this->showMessage('Gönderi tabloları henüz hazır değil. Migration çalıştıktan sonra işlem aktif olur.', 'warning');
            return;
        }

        $packages = ChannelOrderPackage::query()
            ->with(['order.items.product', 'order.store', 'items.product', 'store'])
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', auth()->id()))
            ->whereDoesntHave('shipments', fn (Builder $query) => $query->where('carrier_code', 'surat')->where('flow_type', 'order'))
            ->latest('updated_at')
            ->limit(100)
            ->get();

        $service = app(CargoShipmentService::class);
        $created = 0;

        foreach ($packages as $package) {
            $service->createOrUpdateFromPackage($package);
            $created++;
        }

        $this->showMessage($created > 0
            ? "{$created} paket için Sürat gönderi taslağı hazırlandı."
            : 'Taslak oluşturulacak yeni pazaryeri paketi bulunamadı.', $created > 0 ? 'success' : 'info');
    }

    public function createDraftsFromMarketplaceClaims(): void
    {
        if (!$this->tableReady || !Schema::hasTable('channel_claims')) {
            $this->showMessage('İade/değişim gönderi kaynağı hazır değil.', 'warning');
            return;
        }

        $claims = ChannelClaim::query()
            ->with(['store', 'items'])
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', auth()->id()))
            ->whereIn('type', ['return', 'exchange'])
            ->whereDoesntHave('shipments', fn (Builder $query) => $query->where('carrier_code', 'surat'))
            ->latest('updated_at')
            ->limit(100)
            ->get();

        $service = app(CargoShipmentService::class);
        $created = 0;

        foreach ($claims as $claim) {
            $service->createOrUpdateFromClaim($claim);
            $created++;
        }

        $this->showMessage($created > 0
            ? "{$created} iade/değişim kaydı için Sürat gönderi taslağı hazırlandı."
            : 'Taslak oluşturulacak yeni iade/değişim kaydı bulunamadı.', $created > 0 ? 'success' : 'info');
    }

    public function createDraftsFromSupplyOrders(): void
    {
        if (!$this->tableReady || !Schema::hasTable('supply_orders')) {
            $this->showMessage('Tedarik gönderi kaynağı hazır değil.', 'warning');
            return;
        }

        $orders = SupplyOrder::query()
            ->whereDoesntHave('shipments', fn (Builder $query) => $query->where('carrier_code', 'surat'))
            ->latest('updated_at')
            ->limit(100)
            ->get();

        $service = app(CargoShipmentService::class);
        $created = 0;

        foreach ($orders as $order) {
            $service->createOrUpdateFromSupplyOrder($order);
            $created++;
        }

        $this->showMessage($created > 0
            ? "{$created} tedarik kaydı için Sürat gönderi taslağı hazırlandı."
            : 'Taslak oluşturulacak yeni tedarik kaydı bulunamadı.', $created > 0 ? 'success' : 'info');
    }

    public function pushShipment(int $shipmentId): void
    {
        $shipment = $this->findUserShipment($shipmentId);

        try {
            app(CargoShipmentService::class)->pushToCarrier($shipment);
            $this->showMessage('Sürat gönderi/barkod isteği tamamlandı.', 'success');
        } catch (\Throwable $exception) {
            $shipment->forceFill(['last_error' => $exception->getMessage()])->save();
            $this->showMessage($exception->getMessage(), 'warning');
        }
    }

    public function refreshTracking(int $shipmentId): void
    {
        $shipment = $this->findUserShipment($shipmentId);

        try {
            app(CargoShipmentService::class)->refreshTracking($shipment);
            $this->showMessage('Sürat takip bilgisi güncellendi.', 'success');
        } catch (\Throwable $exception) {
            $shipment->forceFill([
                'last_error' => $exception->getMessage(),
                'last_tracked_at' => now(),
            ])->save();
            $this->showMessage($exception->getMessage(), 'warning');
        }
    }

    public function cancelShipment(int $shipmentId): void
    {
        $shipment = $this->findUserShipment($shipmentId);

        try {
            app(CargoShipmentService::class)->cancelShipment($shipment, context: [
                'source' => 'shipment_ledger',
                'cancelled_by' => auth()->id(),
            ]);
            $this->showMessage('Sürat gönderisi iptal edildi.', 'success');
        } catch (\Throwable $exception) {
            $shipment->forceFill(['last_error' => $exception->getMessage()])->save();
            $this->showMessage($exception->getMessage(), 'warning');
        }
    }

    public function importSuratInvoice(): void
    {
        if (!$this->tableReady || !Schema::hasTable('cargo_invoice_lines')) {
            $this->showMessage('Kargo fatura mutabakat tabloları hazır değil. Migration çalıştırılmalı.', 'warning');
            return;
        }

        $this->validate([
            'invoiceFile' => 'required|file|mimes:xlsx,xls,csv,txt|max:20480',
            'invoiceNumber' => 'nullable|string|max:120',
            'invoiceDate' => 'nullable|date',
        ], [
            'invoiceFile.required' => 'Sürat fatura/rapor dosyası seçin.',
            'invoiceFile.mimes' => 'Dosya xlsx, xls veya csv formatında olmalı.',
        ]);

        $rows = app(ExcelService::class)->importOrderXls($this->invoiceFile);
        $service = app(CargoShipmentService::class);
        $account = $service->defaultAccount(auth()->id());

        $created = 0;
        $updated = 0;
        $matched = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $payload = $this->mapInvoiceRow((array) $row);

            if (
                blank($payload['tracking_number'])
                && blank($payload['barcode'])
                && blank($payload['order_reference'])
            ) {
                $skipped++;
                continue;
            }

            $invoiceNumber = trim($this->invoiceNumber) ?: $payload['invoice_number'];
            $invoiceDate = $this->invoiceDate ?: $payload['invoice_date'];

            $line = CargoInvoiceLine::query()->firstOrNew([
                'user_id' => auth()->id(),
                'carrier_code' => 'surat',
                'invoice_number' => $invoiceNumber ?: null,
                'tracking_number' => $payload['tracking_number'] ?: null,
                'barcode' => $payload['barcode'] ?: null,
                'order_reference' => $payload['order_reference'] ?: null,
            ]);

            $exists = $line->exists;

            $line->fill([
                'legal_entity_id' => $account?->legal_entity_id,
                'cargo_carrier_account_id' => $account?->id,
                'invoice_date' => $invoiceDate ?: null,
                'waybill_number' => $payload['waybill_number'] ?: null,
                'sender_name' => $payload['sender_name'] ?: null,
                'recipient_name' => $payload['recipient_name'] ?: null,
                'origin_city' => $payload['origin_city'] ?: null,
                'destination_city' => $payload['destination_city'] ?: null,
                'destination_district' => $payload['destination_district'] ?: null,
                'parcel_count' => max(1, (int) $payload['parcel_count']),
                'desi' => $payload['desi'],
                'amount' => $payload['amount'],
                'vat_amount' => $payload['vat_amount'],
                'total_amount' => $payload['total_amount'],
                'currency' => 'TRY',
                'status' => 'imported',
                'raw_payload' => $row,
            ]);
            $line->save();

            $exists ? $updated++ : $created++;

            if ($service->reconcileInvoiceLine($line)) {
                $matched++;
            }
        }

        $this->reset('invoiceFile');
        $this->showMessage(
            "Sürat fatura dosyası işlendi. Yeni: {$created}, Güncellenen: {$updated}, Eşleşen: {$matched}, Atlanan: {$skipped}.",
            $matched > 0 ? 'success' : 'info'
        );
    }

    public function toggleColumn(string $column): void
    {
        if (!array_key_exists($column, static::$columnDefs)) {
            return;
        }

        if (in_array($column, $this->visibleColumns, true)) {
            if (count($this->visibleColumns) <= 1) {
                return;
            }

            $this->visibleColumns = array_values(array_diff($this->visibleColumns, [$column]));
            return;
        }

        $this->visibleColumns[] = $column;
        $this->visibleColumns = array_values(array_intersect(array_keys(static::$columnDefs), $this->visibleColumns));
    }

    protected function baseQuery(): Builder
    {
        $query = Shipment::query()
            ->where('user_id', auth()->id());

        if ($this->search !== '') {
            $term = '%' . $this->search . '%';
            $query->where(function (Builder $subQuery) use ($term) {
                $subQuery->where('shipment_no', 'like', $term)
                    ->orWhere('tracking_number', 'like', $term)
                    ->orWhere('barcode', 'like', $term)
                    ->orWhere('order_number', 'like', $term)
                    ->orWhere('customer_name', 'like', $term)
                    ->orWhere('reference_number', 'like', $term);
            });
        }

        if ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->flowFilter !== '') {
            $query->where('flow_type', $this->flowFilter);
        }

        if ($this->directionFilter !== '') {
            $query->where('direction', $this->directionFilter);
        }

        if ($this->dateFrom !== '') {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo !== '') {
            $query->whereDate('created_at', '<=', $this->dateTo);
        }

        return $query;
    }

    protected function findUserShipment(int $shipmentId): Shipment
    {
        return Shipment::query()
            ->where('user_id', auth()->id())
            ->findOrFail($shipmentId);
    }

    protected function showMessage(string $message, string $tone = 'info'): void
    {
        $this->message = $message;
        $this->messageTone = $tone;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function mapInvoiceRow(array $row): array
    {
        $normalized = [];

        foreach ($row as $key => $value) {
            $normalized[$this->normalizeHeader((string) $key)] = $value;
        }

        $amount = $this->moneyValue($this->rowValue($normalized, [
            'tutar', 'kargo_tutari', 'navlun', 'hizmet_bedeli', 'ara_toplam',
        ]));
        $vatAmount = $this->moneyValue($this->rowValue($normalized, [
            'kdv', 'kdv_tutari', 'vergi', 'vat',
        ]));
        $totalAmount = $this->moneyValue($this->rowValue($normalized, [
            'toplam', 'genel_toplam', 'fatura_tutari', 'tahsil_edilecek_tutar', 'toplam_tutar',
        ]));

        if ($totalAmount <= 0 && ($amount > 0 || $vatAmount > 0)) {
            $totalAmount = round($amount + $vatAmount, 2);
        }

        return [
            'invoice_number' => (string) $this->rowValue($normalized, ['fatura_no', 'fatura_numarasi', 'invoice_no']),
            'invoice_date' => $this->dateValue($this->rowValue($normalized, ['fatura_tarihi', 'tarih', 'cikis_tarihi', 'islem_tarihi'])),
            'waybill_number' => (string) $this->rowValue($normalized, ['irsaliye_no', 'gonderi_no', 'kargo_no']),
            'tracking_number' => (string) $this->rowValue($normalized, ['takip_no', 'takip_kodu', 'kargo_takip_no', 'kargotakipno']),
            'barcode' => (string) $this->rowValue($normalized, ['barkod', 'barkod_no', 'kargo_barkod']),
            'order_reference' => (string) $this->rowValue($normalized, ['siparis_no', 'siparis_numarasi', 'referans_no', 'musteri_referansi', 'musteri_referans_no']),
            'sender_name' => (string) $this->rowValue($normalized, ['gonderen', 'gonderici', 'cikis_subesi']),
            'recipient_name' => (string) $this->rowValue($normalized, ['alici', 'musteri_adi', 'teslim_alacak', 'teslim_alan']),
            'origin_city' => (string) $this->rowValue($normalized, ['cikis_ili', 'gonderen_il', 'origin_city']),
            'destination_city' => (string) $this->rowValue($normalized, ['varis_ili', 'alici_il', 'teslim_ili', 'destination_city']),
            'destination_district' => (string) $this->rowValue($normalized, ['varis_ilcesi', 'alici_ilce', 'teslim_ilcesi', 'destination_district']),
            'parcel_count' => (int) $this->numberValue($this->rowValue($normalized, ['parca', 'parca_adedi', 'koli', 'koli_adedi', 'adet'])),
            'desi' => $this->numberValue($this->rowValue($normalized, ['desi', 'hacimsel_agirlik', 'agirlik_desi'])),
            'amount' => $amount,
            'vat_amount' => $vatAmount,
            'total_amount' => $totalAmount,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $aliases
     */
    protected function rowValue(array $row, array $aliases): mixed
    {
        foreach ($aliases as $alias) {
            if (array_key_exists($alias, $row) && filled($row[$alias])) {
                return $row[$alias];
            }
        }

        return null;
    }

    protected function normalizeHeader(string $header): string
    {
        $header = mb_strtolower(trim($header), 'UTF-8');
        $header = strtr($header, [
            'ı' => 'i',
            'ğ' => 'g',
            'ü' => 'u',
            'ş' => 's',
            'ö' => 'o',
            'ç' => 'c',
        ]);

        return trim((string) preg_replace('/[^a-z0-9]+/u', '_', $header), '_');
    }

    protected function numberValue(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        $clean = preg_replace('/[^0-9,.\-]/u', '', (string) $value) ?: '0';
        $lastComma = strrpos($clean, ',');
        $lastDot = strrpos($clean, '.');

        if ($lastComma !== false && $lastDot !== false) {
            $clean = $lastComma > $lastDot
                ? str_replace(',', '.', str_replace('.', '', $clean))
                : str_replace(',', '', $clean);
        } else {
            $clean = str_replace(',', '.', $clean);
        }

        return round((float) $clean, 2);
    }

    protected function moneyValue(mixed $value): float
    {
        return max(0, $this->numberValue($value));
    }

    protected function dateValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString();
            }

            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    public function render()
    {
        return view('livewire.cargo.shipment-ledger');
    }
}
