<?php

namespace App\Livewire;

use App\Jobs\SyncOperationalToFinancialJob;
use App\Models\ChannelOrder;
use App\Models\ChannelOrderItem;
use App\Models\ChannelOrderPackage;
use App\Models\IntegrationSyncRun;
use App\Models\IntegrationOrderActionRun;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\MpOperationalOrder;
use App\Models\MpOperationalOrderItem;
use App\Models\OrderFinancialEvent;
use App\Models\OrderProfitSnapshot;
use App\Services\MpSettingsService;
use App\Services\Marketplace\MarketplaceConnectorManager;
use App\Services\Marketplace\MarketplaceDiagnosticsGuidanceService;
use App\Services\Marketplace\LegacyFinancialProjectionInsightsService;
use App\Services\Marketplace\LegacyFinancialProjectionService;
use App\Services\Marketplace\MarketplaceManualSyncDispatchService;
use App\Services\Marketplace\MarketplaceHealthRetryService;
use App\Services\Marketplace\MarketplaceOrderActionService;
use App\Services\Marketplace\MarketplaceProviderRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class MarketplaceOrders extends Component
{
    use WithFileUploads;
    use WithPagination;

    public array $visibleColumns = ['siparis', 'musteri', 'lojistik', 'ciro', 'muhasebe', 'kar', 'durum'];

    public static array $sortableColumns = [
        'siparis' => 'order_number',
        'magaza' => 'store_name_alias',
        'musteri' => 'customer_name',
        'tarih' => 'ordered_at',
        'ciro' => 'gross_revenue_metric',
        'muhasebe' => 'net_receivable_metric',
        'kar' => 'profit_value_metric',
        'durum' => 'order_status',
    ];

    public static array $allColumnDefs = [
        'siparis' => 'Sipariş',
        'magaza' => 'Mağaza',
        'musteri' => 'Müşteri',
        'lojistik' => 'Lojistik',
        'ciro' => 'Ciro',
        'muhasebe' => 'Muhasebe',
        'kar' => 'Kâr Oranı',
        'durum' => 'Durum',
    ];

    public string $search = '';
    public string $searchCustomer = '';
    public string $searchProduct = '';
    public string $searchBarcode = '';
    public string $statusFilter = '';
    public string $marketplaceFilter = '';
    public string $storeFilter = '';
    public string $labelFilter = '';
    public string $legalEntityFilter = '';
    public string $profitStateFilter = '';
    public string $financialStateFilter = '';
    public string $matchStateFilter = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $cityFilter = '';
    public string $brandFilter = '';
    public string $corporateFilter = '';
    public string $sortField = 'ordered_at';
    public string $sortDirection = 'desc';

    public $file;
    public string $legacyProjectionStoreId = '';
    public bool $isImporting = false;
    public string $importMessage = '';
    public string $actionMessage = '';
    public string $actionMessageTone = 'info';
    public array $legacyProjectionResult = [];
    public array $packageActionForms = [];
    public array $selectedOrderIds = [];
    public array $selectedPackageIds = [];
    public bool $selectPage = false;
    public string $bulkActionType = '';
    public string $bulkPackageActionType = '';
    public bool $showEditOrderModal = false;
    public ?int $editingOrderId = null;
    public bool $showOrderLabelManager = false;
    public array $orderForm = [
        'order_number' => '',
        'order_status' => '',
        'customer_name' => '',
        'customer_email' => '',
        'customer_phone' => '',
        'commercial_type' => '',
        'billing_name' => '',
        'billing_tax_number' => '',
        'shipment_city' => '',
        'shipment_district' => '',
        'ordered_at' => '',
    ];
    public array $orderItemsForm = [];
    public array $orderLabelForm = [];
    protected array $connectorCapabilitiesCache = [];

    protected $queryString = [
        'search' => ['except' => ''],
        'searchCustomer' => ['except' => ''],
        'searchProduct' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'marketplaceFilter' => ['except' => ''],
        'storeFilter' => ['except' => ''],
        'labelFilter' => ['except' => ''],
        'legalEntityFilter' => ['except' => ''],
        'profitStateFilter' => ['except' => ''],
        'financialStateFilter' => ['except' => ''],
        'matchStateFilter' => ['except' => ''],
        'dateFrom' => ['except' => ''],
        'dateTo' => ['except' => ''],
        'searchBarcode' => ['except' => ''],
    ];

    public function mount(): void
    {
        if ($this->searchProduct === '' && $this->searchBarcode !== '') {
            $this->searchProduct = $this->searchBarcode;
        }

        $this->syncLegacyProjectionStoreFromFilter();

        $savedVisibleColumns = $this->normalizeVisibleColumns(
            app(MpSettingsService::class)->getArray('marketplace_orders.v2.visible_columns', $this->visibleColumns)
        );

        if ($savedVisibleColumns === ['siparis', 'magaza', 'musteri', 'lojistik', 'ciro', 'kar', 'durum']) {
            $savedVisibleColumns = $this->visibleColumns;
            app(MpSettingsService::class)->set('marketplace_orders.v2.visible_columns', $savedVisibleColumns);
        }

        $this->visibleColumns = $savedVisibleColumns;
        $this->orderLabelForm = $this->orderLabelFormDefaults();
    }

    public function updated($property): void
    {
        if (in_array($property, [
            'search',
            'searchCustomer',
            'searchProduct',
            'searchBarcode',
            'statusFilter',
            'marketplaceFilter',
            'storeFilter',
            'labelFilter',
            'legalEntityFilter',
            'profitStateFilter',
            'financialStateFilter',
            'matchStateFilter',
            'dateFrom',
            'dateTo',
            'cityFilter',
            'brandFilter',
            'corporateFilter',
        ], true)) {
            if ($property === 'searchProduct') {
                $this->searchBarcode = $this->searchProduct;
            }

            if ($property === 'storeFilter') {
                $this->syncLegacyProjectionStoreFromFilter();
            }

            $this->resetPage();
        }
    }

    public function sortTable(string $columnKey): void
    {
        $field = static::$sortableColumns[$columnKey] ?? null;
        if (!$field) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = $field === 'ordered_at' ? 'desc' : 'asc';
        }

        $this->resetPage();
    }

    public function updatedSelectPage(bool $value): void
    {
        $this->selectedOrderIds = $value ? $this->currentPageOrderIds() : [];
    }

    public function updatedSelectedOrderIds(): void
    {
        $selected = collect($this->selectedOrderIds)
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();

        $this->selectedOrderIds = $selected;

        $currentPageIds = $this->currentPageOrderIds();
        $this->selectPage = $currentPageIds !== []
            && count(array_intersect($selected, $currentPageIds)) === count($currentPageIds);
    }

    public function updatedSelectedPackageIds(): void
    {
        $this->selectedPackageIds = collect($this->selectedPackageIds)
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function clearSelection(): void
    {
        $this->selectedOrderIds = [];
        $this->selectedPackageIds = [];
        $this->selectPage = false;
        $this->bulkActionType = '';
        $this->bulkPackageActionType = '';
    }

    public function openEditOrder(int $orderId): void
    {
        $order = ChannelOrder::query()
            ->with([
                'items:id,store_id,channel_order_id,channel_order_package_id,external_line_id,stock_code,barcode,product_name,quantity,unit_price,gross_amount,discount_amount,billable_amount,line_status',
                'packages:id,channel_order_id,package_number',
            ])
            ->whereKey($orderId)
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', auth()->id()))
            ->firstOrFail();

        $this->editingOrderId = $order->id;
        $this->orderForm = [
            'order_number' => (string) $order->order_number,
            'order_status' => (string) $order->order_status,
            'customer_name' => (string) ($order->customer_name ?? ''),
            'customer_email' => (string) ($order->customer_email ?? ''),
            'customer_phone' => (string) ($order->customer_phone ?? ''),
            'commercial_type' => (string) ($order->commercial_type ?? ''),
            'billing_name' => (string) ($order->billing_name ?? ''),
            'billing_tax_number' => (string) ($order->billing_tax_number ?? ''),
            'shipment_city' => (string) ($order->shipment_city ?? ''),
            'shipment_district' => (string) ($order->shipment_district ?? ''),
            'ordered_at' => $order->ordered_at?->format('Y-m-d\TH:i') ?? '',
        ];
        $packageNumbers = $order->packages
            ->pluck('package_number', 'id')
            ->map(fn ($value) => filled($value) ? (string) $value : null);
        $this->orderItemsForm = $order->items
            ->sortBy('id')
            ->map(fn (ChannelOrderItem $item) => [
                'id' => $item->id,
                'package_label' => $item->channel_order_package_id ? ($packageNumbers[$item->channel_order_package_id] ?? ('#' . $item->channel_order_package_id)) : null,
                'product_name' => (string) ($item->product_name ?? ''),
                'barcode' => (string) ($item->barcode ?? ''),
                'stock_code' => (string) ($item->stock_code ?? ''),
                'quantity' => (int) ($item->quantity ?? 1),
                'unit_price' => $item->unit_price !== null ? number_format((float) $item->unit_price, 2, '.', '') : '',
                'gross_amount' => $item->gross_amount !== null ? number_format((float) $item->gross_amount, 2, '.', '') : '',
                'discount_amount' => $item->discount_amount !== null ? number_format((float) $item->discount_amount, 2, '.', '') : '',
                'billable_amount' => $item->billable_amount !== null ? number_format((float) $item->billable_amount, 2, '.', '') : '',
                'line_status' => (string) ($item->line_status ?? ''),
            ])
            ->values()
            ->all();
        $this->showEditOrderModal = true;
        $this->resetErrorBag();
    }

    public function closeEditOrderModal(): void
    {
        $this->showEditOrderModal = false;
        $this->editingOrderId = null;
        $this->orderForm = $this->emptyOrderForm();
        $this->orderItemsForm = [];
        $this->resetErrorBag();
    }

    public function saveOrderEdits(): void
    {
        if ($this->editingOrderId === null) {
            return;
        }

        $validated = $this->validate([
            'orderForm.order_number' => ['required', 'string', 'max:100'],
            'orderForm.order_status' => ['required', 'string', 'max:50'],
            'orderForm.customer_name' => ['nullable', 'string', 'max:150'],
            'orderForm.customer_email' => ['nullable', 'email', 'max:150'],
            'orderForm.customer_phone' => ['nullable', 'string', 'max:32'],
            'orderForm.commercial_type' => ['nullable', 'string', 'max:50'],
            'orderForm.billing_name' => ['nullable', 'string', 'max:150'],
            'orderForm.billing_tax_number' => ['nullable', 'string', 'max:32'],
            'orderForm.shipment_city' => ['nullable', 'string', 'max:120'],
            'orderForm.shipment_district' => ['nullable', 'string', 'max:120'],
            'orderForm.ordered_at' => ['nullable', 'date'],
            'orderItemsForm' => ['array'],
            'orderItemsForm.*.id' => ['required', 'integer'],
            'orderItemsForm.*.product_name' => ['nullable', 'string', 'max:255'],
            'orderItemsForm.*.barcode' => ['nullable', 'string', 'max:100'],
            'orderItemsForm.*.stock_code' => ['nullable', 'string', 'max:100'],
            'orderItemsForm.*.quantity' => ['required', 'integer', 'min:1'],
            'orderItemsForm.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'orderItemsForm.*.gross_amount' => ['nullable', 'numeric', 'min:0'],
            'orderItemsForm.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'orderItemsForm.*.billable_amount' => ['nullable', 'numeric', 'min:0'],
            'orderItemsForm.*.line_status' => ['nullable', 'string', 'max:50'],
        ]);

        $order = ChannelOrder::query()
            ->with('items:id,channel_order_id')
            ->whereKey($this->editingOrderId)
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', auth()->id()))
            ->firstOrFail();

        $form = $validated['orderForm'];
        $items = collect($validated['orderItemsForm'] ?? []);
        $orderItems = $order->items->keyBy('id');

        DB::transaction(function () use ($order, $form, $items, $orderItems): void {
            $order->fill([
                'order_number' => trim((string) $form['order_number']),
                'order_status' => trim((string) $form['order_status']),
                'customer_name' => $this->nullableTrim($form['customer_name'] ?? null),
                'customer_email' => $this->nullableTrim($form['customer_email'] ?? null),
                'customer_phone' => $this->nullableTrim($form['customer_phone'] ?? null),
                'commercial_type' => $this->nullableTrim($form['commercial_type'] ?? null),
                'billing_name' => $this->nullableTrim($form['billing_name'] ?? null),
                'billing_tax_number' => $this->nullableTrim($form['billing_tax_number'] ?? null),
                'shipment_city' => $this->nullableTrim($form['shipment_city'] ?? null),
                'shipment_district' => $this->nullableTrim($form['shipment_district'] ?? null),
                'ordered_at' => filled($form['ordered_at'] ?? null) ? Carbon::parse((string) $form['ordered_at']) : null,
            ]);
            $order->save();

            foreach ($items as $itemForm) {
                /** @var ChannelOrderItem|null $item */
                $item = $orderItems->get((int) $itemForm['id']);

                if (!$item) {
                    continue;
                }

                $item->fill([
                    'product_name' => $this->nullableTrim($itemForm['product_name'] ?? null),
                    'barcode' => $this->nullableTrim($itemForm['barcode'] ?? null),
                    'stock_code' => $this->nullableTrim($itemForm['stock_code'] ?? null),
                    'quantity' => (int) ($itemForm['quantity'] ?? 1),
                    'unit_price' => $this->nullableDecimal($itemForm['unit_price'] ?? null),
                    'gross_amount' => $this->nullableDecimal($itemForm['gross_amount'] ?? null),
                    'discount_amount' => $this->nullableDecimal($itemForm['discount_amount'] ?? null) ?? 0,
                    'billable_amount' => $this->nullableDecimal($itemForm['billable_amount'] ?? null),
                    'line_status' => $this->nullableTrim($itemForm['line_status'] ?? null) ?? 'new',
                ]);
                $item->save();
            }
        });

        $this->actionMessage = 'Sipariş ve ürün satırları güncellendi.';
        $this->actionMessageTone = 'success';
        $this->closeEditOrderModal();
    }

    public function duplicateOrder(int $orderId): void
    {
        $order = ChannelOrder::query()
            ->with([
                'packages:id,store_id,channel_order_id,external_package_id,package_number,package_status,cargo_company,cargo_tracking_number,cargo_barcode,cargo_desi,shipment_provider,shipped_at,delivered_at,last_synced_at,raw_payload',
                'items:id,store_id,channel_order_id,channel_order_package_id,channel_listing_id,mp_product_id,external_line_id,stock_code,barcode,product_name,quantity,unit_price,gross_amount,discount_amount,marketplace_discount_amount,billable_amount,commission_rate,vat_rate,line_status,is_matched,match_source,last_synced_at,raw_payload',
            ])
            ->whereKey($orderId)
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', auth()->id()))
            ->firstOrFail();

        $copyToken = 'COPY-' . now()->format('His') . '-' . Str::upper(Str::random(3));

        DB::transaction(function () use ($order, $copyToken) {
            $orderCopy = $order->replicate();
            $orderCopy->external_order_id = $this->appendCopySuffix((string) $order->external_order_id, '-' . $copyToken, 120);
            $orderCopy->order_number = $this->appendCopySuffix((string) $order->order_number, ' (Kopya)', 100);
            $orderCopy->last_synced_at = null;
            $orderCopy->raw_payload = array_merge((array) ($order->raw_payload ?? []), [
                '_zolm_copy_meta' => [
                    'source_order_id' => $order->id,
                    'copied_at' => now()->toIso8601String(),
                ],
            ]);
            $orderCopy->save();

            $packageMap = [];

            foreach ($order->packages as $package) {
                $packageCopy = $package->replicate();
                $packageCopy->channel_order_id = $orderCopy->id;
                $packageCopy->external_package_id = $this->appendCopySuffix((string) $package->external_package_id, '-' . $copyToken, 120);
                $packageCopy->package_number = filled($package->package_number)
                    ? $this->appendCopySuffix((string) $package->package_number, '-KOPYA', 120)
                    : null;
                $packageCopy->last_synced_at = null;
                $packageCopy->raw_payload = array_merge((array) ($package->raw_payload ?? []), [
                    '_zolm_copy_meta' => [
                        'source_package_id' => $package->id,
                        'source_order_id' => $order->id,
                    ],
                ]);
                $packageCopy->save();

                $packageMap[$package->id] = $packageCopy->id;
            }

            foreach ($order->items as $item) {
                $itemCopy = $item->replicate();
                $itemCopy->channel_order_id = $orderCopy->id;
                $itemCopy->channel_order_package_id = $item->channel_order_package_id !== null
                    ? ($packageMap[$item->channel_order_package_id] ?? null)
                    : null;
                $itemCopy->external_line_id = $this->appendCopySuffix((string) $item->external_line_id, '-' . $copyToken . '-' . $item->id, 120);
                $itemCopy->last_synced_at = null;
                $itemCopy->raw_payload = array_merge((array) ($item->raw_payload ?? []), [
                    '_zolm_copy_meta' => [
                        'source_item_id' => $item->id,
                        'source_order_id' => $order->id,
                    ],
                ]);
                $itemCopy->save();
            }
        });

        $this->actionMessage = 'Sipariş kaydı kopyalandı. Operasyon kalemleri yeni kayda taşındı.';
        $this->actionMessageTone = 'success';
    }

    public function deleteOrder(int $orderId): void
    {
        $order = ChannelOrder::query()
            ->with('packages:id,channel_order_id')
            ->whereKey($orderId)
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', auth()->id()))
            ->firstOrFail();

        $packageIds = $order->packages->pluck('id')->map(fn ($id) => (string) $id)->all();
        $deletedOrderNumber = (string) $order->order_number;
        $order->delete();

        $this->selectedOrderIds = array_values(array_diff($this->selectedOrderIds, [(string) $orderId]));
        $this->selectedPackageIds = array_values(array_diff($this->selectedPackageIds, $packageIds));
        $currentPageIds = $this->currentPageOrderIds();
        $this->selectPage = $currentPageIds !== []
            && count(array_intersect($this->selectedOrderIds, $currentPageIds)) === count($currentPageIds);

        if ($this->editingOrderId === $orderId) {
            $this->closeEditOrderModal();
        }

        $this->actionMessage = $deletedOrderNumber . ' siparişi silindi.';
        $this->actionMessageTone = 'success';
    }

    public function openOrderLabelManager(): void
    {
        $this->orderLabelForm = $this->orderLabelFormDefaults();
        $this->showOrderLabelManager = true;
        $this->resetErrorBag();
    }

    public function closeOrderLabelManager(): void
    {
        $this->showOrderLabelManager = false;
        $this->orderLabelForm = $this->orderLabelFormDefaults();
        $this->resetErrorBag();
    }

    public function saveOrderLabelSettings(): void
    {
        $this->validate([
            'orderLabelForm' => ['required', 'array', 'size:' . count($this->orderLabelDefaults())],
            'orderLabelForm.*.name' => ['required', 'string', 'max:24'],
            'orderLabelForm.*.color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $normalized = collect($this->orderLabelDefaults())
            ->mapWithKeys(function (array $defaults, string $key): array {
                return [
                    $key => [
                        'name' => trim((string) data_get($this->orderLabelForm, $key . '.name', $defaults['name'])),
                        'color' => $this->normalizeHexColor(
                            (string) data_get($this->orderLabelForm, $key . '.color', $defaults['color']),
                            $defaults['color']
                        ),
                    ],
                ];
            })
            ->all();

        app(MpSettingsService::class)->set('marketplace_orders.v2.color_labels', $normalized);

        $this->orderLabelForm = $this->orderLabelFormDefaults();
        $this->showOrderLabelManager = false;
        $this->actionMessage = 'Sipariş renk etiketleri güncellendi.';
        $this->actionMessageTone = 'success';
    }

    public function assignOrderColorLabel(int $orderId, string $labelKey): void
    {
        $label = $this->orderLabelDefinitions()[$labelKey] ?? null;

        if (!$label) {
            $this->actionMessage = 'Atanacak renk etiketi bulunamadı.';
            $this->actionMessageTone = 'warning';

            return;
        }

        $order = ChannelOrder::query()
            ->whereKey($orderId)
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', auth()->id()))
            ->firstOrFail();

        $order->update([
            'color_label_key' => $labelKey,
        ]);

        $this->actionMessage = $order->order_number . ' için "' . $label['name'] . '" etiketi atandı.';
        $this->actionMessageTone = 'success';
    }

    public function clearOrderColorLabel(int $orderId): void
    {
        $order = ChannelOrder::query()
            ->whereKey($orderId)
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', auth()->id()))
            ->firstOrFail();

        $order->update([
            'color_label_key' => null,
        ]);

        $this->actionMessage = $order->order_number . ' için renk etiketi kaldırıldı.';
        $this->actionMessageTone = 'success';
    }

    public function toggleColumn(string $column): void
    {
        if (!array_key_exists($column, static::$allColumnDefs)) {
            return;
        }

        if (in_array($column, $this->visibleColumns, true)) {
            if (count($this->visibleColumns) === 1) {
                return;
            }

            $this->visibleColumns = array_values(array_diff($this->visibleColumns, [$column]));
        } else {
            $this->visibleColumns[] = $column;
            $this->visibleColumns = $this->normalizeVisibleColumns($this->visibleColumns);
        }

        app(MpSettingsService::class)->set('marketplace_orders.v2.visible_columns', $this->visibleColumns);
    }

    public function importOrders(): void
    {
        if (empty($this->file)) {
            $this->addError('file', 'Dosya sisteme ulaşmadı. Eğer Excel dosyanız büyükse PHP limitlerine takılmış olabilir. PHP upload limitlerini yükseltip tekrar deneyin.');
            return;
        }

        $this->validate([
            'file' => 'required|mimes:xlsx,xls|max:51200',
            'legacyProjectionStoreId' => 'nullable|integer|exists:marketplace_stores,id',
        ]);

        $this->isImporting = true;

        $path = $this->file->store('marketplace-imports');
        $absolutePath = Storage::path($path);

        try {
            set_time_limit(300);

            $targetStore = filled($this->legacyProjectionStoreId)
                ? MarketplaceStore::query()
                    ->where('user_id', auth()->id())
                    ->findOrFail((int) $this->legacyProjectionStoreId)
                : null;

            $importService = app(\App\Services\DetailedOrderImportService::class);
            $stats = $importService->importDetailedOrders($absolutePath, $targetStore);

            $this->importMessage = $targetStore
                ? 'Eski veri Excel içe aktarımı tamamlandı. ' . ($stats['projected_orders'] ?? 0) . ' sipariş yeni V2 yansıtma hattına da aktarıldı.'
                : 'Eski Excel yedek içe aktarımı başarıyla tamamlandı. Gerekirse eski veri sipariş akışında kullanabilirsiniz.';
        } catch (\Throwable $e) {
            \Log::error('MarketplaceOrders import error', ['error' => $e->getMessage()]);
            $this->importMessage = 'İçe aktarım sırasında hata oluştu: ' . $e->getMessage();
        } finally {
            if (\Illuminate\Support\Facades\File::exists($absolutePath)) {
                \Illuminate\Support\Facades\File::delete($absolutePath);
            }

            $this->isImporting = false;
            $this->file = null;
        }
    }

    public function runSyncEngine(): void
    {
        try {
            set_time_limit(300);

            $syncJob = new SyncOperationalToFinancialJob();
            $syncJob->handle();

            session()->flash('sync_message', 'Eski veri finans senkronu başarıyla tamamlandı.');
        } catch (\Throwable $e) {
            \Log::error('MarketplaceOrders legacy sync error', ['error' => $e->getMessage()]);
            session()->flash('sync_message', 'Eski veri senkronu sırasında hata oluştu: ' . $e->getMessage());
        }
    }

    public function projectLegacyFinancials(): void
    {
        if (!filled($this->legacyProjectionStoreId)) {
            $this->actionMessage = 'Eski veri finans yansıtması için önce bir mağaza seçin.';
            $this->actionMessageTone = 'info';

            return;
        }

        $store = MarketplaceStore::query()
            ->where('user_id', auth()->id())
            ->findOrFail((int) $this->legacyProjectionStoreId);

        $preview = app(LegacyFinancialProjectionService::class)->previewStore($store, true);

        if (($preview['projected_rows'] ?? 0) === 0) {
            $this->legacyProjectionResult = [
                'executed' => false,
                'generated_at' => now()->toDateTimeString(),
                'projected_rows' => 0,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'impacted_orders' => 0,
            ];
            $this->actionMessage = 'Seçili mağaza için taşınacak eski veri finans satırı bulunamadı.';
            $this->actionMessageTone = 'info';

            return;
        }

        $result = app(LegacyFinancialProjectionService::class)->projectStore($store, true);
        $this->legacyProjectionResult = [
            'executed' => true,
            'generated_at' => now()->toDateTimeString(),
            'projected_rows' => (int) ($result['projected_rows'] ?? 0),
            'created' => (int) ($result['created'] ?? 0),
            'updated' => (int) ($result['updated'] ?? 0),
            'skipped' => (int) ($result['skipped'] ?? 0),
            'impacted_orders' => count($result['impacted_order_ids'] ?? []),
        ];

        $this->actionMessage = 'Eski veri finans yansıtması tamamlandı. '
            . ($result['projected_rows'] ?? 0) . ' satır işlendi, '
            . ($result['created'] ?? 0) . ' yeni olay, '
            . ($result['updated'] ?? 0) . ' güncelleme.';
        $this->actionMessageTone = 'success';
    }

    public function previewLegacyFinancials(): void
    {
        if (!filled($this->legacyProjectionStoreId)) {
            $this->actionMessage = 'Eski veri finans yansıtması için önce bir mağaza seçin.';
            $this->actionMessageTone = 'info';

            return;
        }

        $store = MarketplaceStore::query()
            ->where('user_id', auth()->id())
            ->findOrFail((int) $this->legacyProjectionStoreId);

        $preview = app(LegacyFinancialProjectionService::class)->previewStore($store, true);
        $this->legacyProjectionResult = [
            'executed' => false,
            'generated_at' => now()->toDateTimeString(),
            'projected_rows' => (int) ($preview['projected_rows'] ?? 0),
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'impacted_orders' => 0,
        ];

        if (($preview['projected_rows'] ?? 0) === 0) {
            $this->actionMessage = 'Seçili mağaza için yansıtılacak eski veri finans satırı bulunamadı.';
            $this->actionMessageTone = 'info';

            return;
        }

        $this->actionMessage = 'Eski veri finans ön izleme hazırlandı. Aday satırları kontrol edip sonra gerçek yansıtmayı çalıştırabilirsiniz.';
        $this->actionMessageTone = 'success';
    }

    public function resetFilters(): void
    {
        $this->reset([
            'search',
            'searchCustomer',
            'searchProduct',
            'searchBarcode',
            'statusFilter',
            'marketplaceFilter',
            'storeFilter',
            'labelFilter',
            'legalEntityFilter',
            'profitStateFilter',
            'financialStateFilter',
            'matchStateFilter',
            'dateFrom',
            'dateTo',
            'cityFilter',
            'brandFilter',
            'corporateFilter',
        ]);

        $this->sortField = 'ordered_at';
        $this->sortDirection = 'desc';
        $this->clearSelection();
        $this->resetPage();
    }

    public function runOrderAction(int $orderId, string $actionType): void
    {
        if (!config('marketplace.features.order_actions_enabled', true)) {
            $this->actionMessage = 'Siparis aksiyonlari su anda feature flag ile kapali.';
            $this->actionMessageTone = 'warning';

            return;
        }

        $order = ChannelOrder::query()
            ->with([
                'store.connection',
                'store.syncProfile',
                'packages:id,channel_order_id,external_package_id',
            ])
            ->whereKey($orderId)
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', auth()->id()))
            ->firstOrFail();

        $result = app(MarketplaceOrderActionService::class)->dispatch(
            $order,
            $actionType,
            triggeredBy: auth()->id()
        );

        $feedback = app(MarketplaceOrderActionService::class)->feedback(
            $result,
            $actionType,
            $order->store?->store_name,
        );

        $this->actionMessage = $feedback['message'];
        $this->actionMessageTone = $feedback['tone'];
    }

    public function runPackageAction(int $packageId, string $actionType): void
    {
        if (!config('marketplace.features.package_actions_enabled', true)) {
            $this->actionMessage = 'Paket aksiyonlari su anda feature flag ile kapali.';
            $this->actionMessageTone = 'warning';

            return;
        }

        $package = ChannelOrderPackage::query()
            ->with([
                'order:id,store_id,legal_entity_id,order_number',
                'items:id,channel_order_package_id,external_line_id,quantity',
                'store.connection',
            ])
            ->whereKey($packageId)
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', auth()->id()))
            ->firstOrFail();

        $context = $this->buildPackageActionContext($packageId, $actionType);

        $result = app(MarketplaceOrderActionService::class)->dispatch(
            $package->order,
            $actionType,
            $context,
            auth()->id(),
            $package
        );

        $feedback = app(MarketplaceOrderActionService::class)->feedback(
            $result,
            $actionType,
            $package->store?->store_name,
        );

        $this->actionMessage = $feedback['message'];
        $this->actionMessageTone = $feedback['tone'];
    }

    public function runBulkOrderAction(): void
    {
        if (!config('marketplace.features.bulk_order_actions_enabled', true)) {
            $this->actionMessage = 'Toplu siparis aksiyonlari su anda feature flag ile kapali.';
            $this->actionMessageTone = 'warning';

            return;
        }

        $allowedActions = ['refresh_order', 'refresh_cargo', 'refresh_finance', 'recalculate_profit'];

        if (!in_array($this->bulkActionType, $allowedActions, true)) {
            $this->actionMessage = 'Toplu işlem için geçerli bir aksiyon seçin.';
            $this->actionMessageTone = 'info';

            return;
        }

        $selectedIds = collect($this->selectedOrderIds)
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($selectedIds->isEmpty()) {
            $this->actionMessage = 'Önce en az bir sipariş seçin.';
            $this->actionMessageTone = 'info';

            return;
        }

        $orders = ChannelOrder::query()
            ->with([
                'store.connection',
                'store.syncProfile',
                'packages:id,channel_order_id,external_package_id',
            ])
            ->whereIn('id', $selectedIds->all())
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', auth()->id()))
            ->get();

        $createdCount = 0;
        $coalescedCount = 0;
        $busyCount = 0;
        $recentCount = 0;

        foreach ($orders as $order) {
            $result = app(MarketplaceOrderActionService::class)->dispatch(
                $order,
                $this->bulkActionType,
                triggeredBy: auth()->id()
            );

            $createdCount += $result['created'] ? 1 : 0;
            $coalescedCount += $result['coalesced'] ? 1 : 0;
            $busyCount += $result['busy'] ? 1 : 0;
            $recentCount += $result['recent'] ? 1 : 0;
        }

        $messageParts = [];

        if ($createdCount > 0) {
            $messageParts[] = $createdCount . ' sipariş için "' . $this->orderActionLabel($this->bulkActionType) . '" kuyruğa alındı';
        }

        if ($coalescedCount > 0) {
            $messageParts[] = $coalescedCount . ' siparişte bekleyen aksiyon güncellendi';
        }

        if ($busyCount > 0) {
            $messageParts[] = $busyCount . ' siparişte çalışan aksiyon yeniden açılmadı';
        }

        if ($recentCount > 0) {
            $messageParts[] = $recentCount . ' siparişte çok yeni tamamlanan aksiyon debounce edildi';
        }

        $this->actionMessage = implode('. ', $messageParts) . '.';
        $this->actionMessageTone = ($createdCount > 0 || $coalescedCount > 0) ? 'success' : 'info';
        $this->clearSelection();
    }

    public function runBulkPackageAction(): void
    {
        if (!config('marketplace.features.bulk_package_actions_enabled', true)) {
            $this->actionMessage = 'Toplu paket aksiyonlari su anda feature flag ile kapali.';
            $this->actionMessageTone = 'warning';

            return;
        }

        $allowedActions = array_keys($this->bulkPackageActionOptions());

        if (!in_array($this->bulkPackageActionType, $allowedActions, true)) {
            $this->actionMessage = 'Toplu paket işlemi için geçerli bir aksiyon seçin.';
            $this->actionMessageTone = 'info';

            return;
        }

        $selectedIds = collect($this->selectedPackageIds)
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($selectedIds->isEmpty()) {
            $this->actionMessage = 'Önce en az bir paket seçin.';
            $this->actionMessageTone = 'info';

            return;
        }

        $packages = ChannelOrderPackage::query()
            ->with([
                'order:id,store_id,legal_entity_id,order_number',
                'items:id,channel_order_package_id,external_line_id,quantity',
                'store.connection',
            ])
            ->whereIn('id', $selectedIds->all())
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', auth()->id()))
            ->get();

        $createdCount = 0;
        $coalescedCount = 0;
        $busyCount = 0;
        $recentCount = 0;
        $skippedCount = 0;

        foreach ($packages as $package) {
            if (!$this->packageSupportsAction($package, $this->bulkPackageActionType)) {
                $skippedCount++;

                continue;
            }

            $result = app(MarketplaceOrderActionService::class)->dispatch(
                $package->order,
                $this->bulkPackageActionType,
                $this->buildPackageActionContext($package->id, $this->bulkPackageActionType),
                auth()->id(),
                $package
            );

            $createdCount += $result['created'] ? 1 : 0;
            $coalescedCount += $result['coalesced'] ? 1 : 0;
            $busyCount += $result['busy'] ? 1 : 0;
            $recentCount += $result['recent'] ? 1 : 0;
        }

        if (($createdCount + $coalescedCount + $busyCount + $recentCount) === 0) {
            $this->actionMessage = 'Seçili paketlerde bu aksiyon için uygun kanal yeteneği bulunamadı.';
            $this->actionMessageTone = 'info';

            return;
        }

        $messageParts = [];

        if ($createdCount > 0) {
            $messageParts[] = $createdCount . ' paket için "' . $this->orderActionLabel($this->bulkPackageActionType) . '" kuyruğa alındı';
        }

        if ($coalescedCount > 0) {
            $messageParts[] = $coalescedCount . ' pakette bekleyen aksiyon güncellendi';
        }

        if ($busyCount > 0) {
            $messageParts[] = $busyCount . ' pakette çalışan aksiyon yeniden açılmadı';
        }

        if ($recentCount > 0) {
            $messageParts[] = $recentCount . ' pakette çok yeni tamamlanan aksiyon debounce edildi';
        }

        if ($skippedCount > 0) {
            $messageParts[] = $skippedCount . ' paket destek/yetki nedeniyle atlandı';
        }

        $this->actionMessage = implode('. ', $messageParts) . '.';
        $this->actionMessageTone = ($createdCount > 0 || $coalescedCount > 0) ? 'success' : 'info';
        $this->clearSelection();
    }

    public function retryActionRun(int $actionRunId): void
    {
        if (!config('marketplace.features.order_action_retry_enabled', true)) {
            $this->actionMessage = 'Aksiyon tekrar deneme ozelligi su anda feature flag ile kapali.';
            $this->actionMessageTone = 'warning';

            return;
        }

        $actionRun = IntegrationOrderActionRun::query()
            ->with(['package', 'order.packages:id,channel_order_id,external_package_id'])
            ->whereKey($actionRunId)
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', auth()->id()))
            ->firstOrFail();

        $retryRun = app(MarketplaceHealthRetryService::class)->retryOrderAction($actionRun, auth()->id());

        $this->actionMessage = $this->orderActionLabel($actionRun->action_type) . ' tekrar kuyruğa alındı. İşlem no: #' . $retryRun->id;
        $this->actionMessageTone = 'success';
    }

    public function exportCsv()
    {
        if ($this->hasChannelData()) {
            return $this->exportChannelCsv();
        }

        return $this->exportLegacyCsv();
    }

    protected function exportChannelCsv()
    {
        $orders = $this->buildChannelOrdersQuery()
            ->with([
                'store:id,legal_entity_id,marketplace,store_name,store_code,status,is_active',
                'legalEntity:id,name,tax_number',
                'packages:id,channel_order_id,package_number,package_status,cargo_company,cargo_tracking_number,cargo_barcode,cargo_desi,shipped_at,delivered_at',
                'items:id,channel_order_id,channel_order_package_id,mp_product_id,stock_code,barcode,product_name,quantity,unit_price,gross_amount,discount_amount,marketplace_discount_amount,billable_amount,commission_rate,vat_rate,line_status,is_matched,match_source',
                'profitSnapshots' => fn ($snapshotQuery) => $snapshotQuery
                    ->whereNull('channel_order_item_id')
                    ->orderByDesc('calculated_at'),
            ])
            ->reorder($this->sortField, $this->sortDirection)
            ->orderByDesc('channel_orders.ordered_at')
            ->get();
        $filename = 'pazaryeri_siparisleri_v2_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($orders) {
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'Pazaryeri',
                'Mağaza',
                'Firma',
                'Sipariş No',
                'Harici Sipariş ID',
                'Sipariş Tarihi',
                'Durum',
                'Müşteri',
                'Telefon',
                'E-Posta',
                'Şehir',
                'İlçe',
                'Paket No',
                'Takip No',
                'Ürün',
                'Barkod',
                'Stok Kodu',
                'Adet',
                'Brüt Tutar',
                'İndirim',
                'Pazaryeri İndirimi',
                'Faturalanacak',
                'Komisyon %',
                'Kâr Durumu',
                'Tahmini Kâr',
                'Kesin Kâr',
                'Marj %',
                'Finans Event',
            ], ';');

            foreach ($orders as $order) {
                $snapshot = $order->profitSnapshots->first();
                $packageNumbers = $order->packages->pluck('package_number')->filter()->implode(', ');
                $trackingNumbers = $order->packages->pluck('cargo_tracking_number')->filter()->implode(', ');

                foreach ($order->items as $item) {
                    fputcsv($file, [
                        $this->safeExcelString($order->marketplace_alias ?? $order->store?->marketplace),
                        $this->safeExcelString($order->store_name_alias ?? $order->store?->store_name),
                        $this->safeExcelString($order->legal_entity_name_alias ?? $order->legalEntity?->name),
                        $this->safeExcelString($order->order_number),
                        $this->safeExcelString($order->external_order_id),
                        $order->ordered_at?->format('d/m/Y H:i'),
                        $this->safeExcelString($order->order_status),
                        $this->safeExcelString($order->customer_name),
                        $this->safeExcelString($order->customer_phone),
                        $this->safeExcelString($order->customer_email),
                        $this->safeExcelString($order->shipment_city),
                        $this->safeExcelString($order->shipment_district),
                        $this->safeExcelString($packageNumbers),
                        $this->safeExcelString($trackingNumbers),
                        $this->safeExcelString($item->product_name),
                        $this->safeExcelString($item->barcode),
                        $this->safeExcelString($item->stock_code),
                        (int) $item->quantity,
                        (float) $item->gross_amount,
                        (float) $item->discount_amount,
                        (float) $item->marketplace_discount_amount,
                        (float) $item->billable_amount,
                        (float) $item->commission_rate,
                        $this->safeExcelString($snapshot?->profit_state),
                        (float) ($snapshot?->estimated_profit ?? 0),
                        (float) ($snapshot?->confirmed_profit ?? 0),
                        (float) ($snapshot?->margin_percent ?? 0),
                        (int) ($order->financial_event_count ?? 0),
                    ], ';');
                }
            }

            fclose($file);
        }, 200, $headers);
    }

    protected function exportLegacyCsv()
    {
        $orders = $this->buildLegacyQuery()->get();
        $filename = 'pazaryeri_siparislerim_legacy_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($orders) {
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'Sipariş No', 'Paket No', 'Barkod', 'Stok Kodu', 'Marka', 'Ürün Adı', 'Miktar',
                'Birim Fiyat', 'Satış Tutarı', 'İndirim', 'Trendyol İndirimi', 'Faturalanacak Tutar',
                'Komisyon Oranı %', 'Müşteri', 'Telefon', 'E-Posta', 'Şehir', 'İlçe', 'Kargo',
                'Takip No', 'Sipariş Tarihi', 'Durum', 'Muhasebe: Net Hakediş', 'Muhasebe: Net Kâr',
            ], ';');

            foreach ($orders as $order) {
                foreach ($order->items as $item) {
                    $financialData = $order->financialOrders->where('barcode', $item->barcode)->first();

                    fputcsv($file, [
                        $this->safeExcelString($order->order_number),
                        $this->safeExcelString($order->package_number),
                        $this->safeExcelString($item->barcode),
                        $this->safeExcelString($item->stock_code),
                        $this->safeExcelString($item->brand),
                        $this->safeExcelString($item->product_name),
                        (int) $item->quantity,
                        (float) $item->unit_price,
                        (float) $item->sale_price,
                        (float) $item->discount_amount,
                        (float) $item->trendyol_discount,
                        (float) $item->billable_amount,
                        (float) $item->commission_rate,
                        $this->safeExcelString($order->customer_name),
                        $this->safeExcelString($order->customer_phone),
                        $this->safeExcelString($order->email),
                        $this->safeExcelString($order->customer_city),
                        $this->safeExcelString($order->customer_district),
                        $this->safeExcelString($order->cargo_company),
                        $this->safeExcelString($order->tracking_number),
                        $order->order_date?->format('d/m/Y H:i'),
                        $this->safeExcelString($order->status),
                        (float) ($financialData->net_hakedis ?? 0),
                        (float) ($financialData->real_net_profit ?? 0),
                    ], ';');
                }
            }

            fclose($file);
        }, 200, $headers);
    }

    protected function safeExcelString($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return preg_replace('/[\x00-\x1F\x7F]/u', '', (string) $value);
    }

    protected function buildChannelOrdersQuery(): Builder
    {
        return $this->buildChannelBaseQuery()
            ->with([
                'store:id,legal_entity_id,marketplace,store_name,store_code,status,is_active',
                'legalEntity:id,name,tax_number',
                'packages:id,channel_order_id,package_number,package_status,cargo_company,cargo_tracking_number,cargo_barcode,cargo_desi,shipped_at,delivered_at',
                'items:id,channel_order_id,channel_order_package_id,mp_product_id,stock_code,barcode,product_name,quantity,unit_price,gross_amount,discount_amount,marketplace_discount_amount,billable_amount,commission_rate,vat_rate,line_status,is_matched,match_source',
                'items.product:id,stock_code,barcode,product_name,brand,cogs,packaging_cost,cargo_cost,vat_rate',
                'actionRuns:id,channel_order_id,channel_order_package_id,triggered_by,action_type,status,attempt_count,external_action_id,request_context_json,response_json,error_message,started_at,finished_at,created_at',
                'actionRuns.triggeredBy:id,name',
                'actionRuns.package:id,package_number,external_package_id',
                'profitSnapshots' => fn ($snapshotQuery) => $snapshotQuery
                    ->whereNull('channel_order_item_id')
                    ->orderByDesc('calculated_at'),
                'financialEvents' => fn ($eventQuery) => $eventQuery
                    ->latest('event_date'),
            ]);
    }

    protected function buildChannelBaseQuery(): Builder
    {
        $itemAggregate = ChannelOrderItem::query()
            ->selectRaw('
                channel_order_id,
                COUNT(*) as item_lines_count,
                COALESCE(SUM(quantity), 0) as total_quantity,
                COALESCE(SUM(CASE WHEN is_matched = 1 THEN 1 ELSE 0 END), 0) as matched_lines_count,
                COALESCE(SUM(gross_amount), 0) as gross_items_total,
                COALESCE(SUM(discount_amount + marketplace_discount_amount), 0) as total_discount_amount
            ')
            ->groupBy('channel_order_id');

        $financialAggregate = OrderFinancialEvent::query()
            ->selectRaw("
                channel_order_id,
                COUNT(*) as financial_event_count,
                MAX(COALESCE(settlement_date, event_date)) as last_financial_event_at,
                COALESCE(SUM(CASE WHEN event_type IN ('seller_revenue', 'sale', 'capture', 'refund', 'void') THEN CASE WHEN direction = 'credit' THEN ABS(amount) ELSE -ABS(amount) END ELSE 0 END), 0) as seller_revenue_metric,
                COALESCE(SUM(CASE WHEN event_type = 'commission' THEN CASE WHEN direction = 'credit' THEN ABS(amount) ELSE -ABS(amount) END ELSE 0 END), 0) as commission_net_metric,
                COALESCE(SUM(CASE WHEN event_type = 'cargo' THEN CASE WHEN direction = 'credit' THEN ABS(amount) ELSE -ABS(amount) END ELSE 0 END), 0) as cargo_net_metric,
                COALESCE(SUM(CASE WHEN event_type IN ('service_fee', 'deduction_invoice', 'fee') THEN CASE WHEN direction = 'credit' THEN ABS(amount) ELSE -ABS(amount) END ELSE 0 END), 0) as service_fee_net_metric,
                COALESCE(SUM(CASE WHEN event_type = 'withholding' THEN CASE WHEN direction = 'credit' THEN ABS(amount) ELSE -ABS(amount) END ELSE 0 END), 0) as withholding_net_metric
            ")
            ->groupBy('channel_order_id');

        $snapshotAggregate = OrderProfitSnapshot::query()
            ->select([
                'channel_order_id',
                'profit_state',
                'gross_revenue',
                'estimated_profit',
                'confirmed_profit',
                'margin_percent',
                'net_receivable',
                'commission_total',
                'cargo_total',
                'service_fee_total',
                'withholding_total',
                'packaging_cost',
                'own_cargo_cost',
                'cogs_cost',
                'return_effect',
                'calculated_at',
            ])
            ->whereNull('channel_order_item_id');

        $query = ChannelOrder::query()
            ->select([
                'channel_orders.*',
                'marketplace_stores.marketplace as marketplace_alias',
                'marketplace_stores.store_name as store_name_alias',
                'legal_entities.name as legal_entity_name_alias',
                DB::raw('COALESCE(item_agg.item_lines_count, 0) as item_lines_count'),
                DB::raw('COALESCE(item_agg.total_quantity, 0) as total_quantity'),
                DB::raw('COALESCE(item_agg.matched_lines_count, 0) as matched_lines_count'),
                DB::raw('COALESCE(item_agg.total_discount_amount, 0) as total_discount_amount'),
                DB::raw('COALESCE(fin_agg.financial_event_count, 0) as financial_event_count'),
                DB::raw('COALESCE(fin_agg.seller_revenue_metric, 0) as seller_revenue_metric'),
                DB::raw('CASE WHEN COALESCE(fin_agg.commission_net_metric, 0) < 0 THEN ABS(COALESCE(fin_agg.commission_net_metric, 0)) ELSE 0 END as commission_total_metric'),
                DB::raw('CASE WHEN COALESCE(fin_agg.cargo_net_metric, 0) < 0 THEN ABS(COALESCE(fin_agg.cargo_net_metric, 0)) ELSE 0 END as cargo_total_metric'),
                DB::raw('CASE WHEN COALESCE(fin_agg.service_fee_net_metric, 0) < 0 THEN ABS(COALESCE(fin_agg.service_fee_net_metric, 0)) ELSE 0 END as service_fee_total_metric'),
                DB::raw('CASE WHEN COALESCE(fin_agg.withholding_net_metric, 0) < 0 THEN ABS(COALESCE(fin_agg.withholding_net_metric, 0)) ELSE 0 END as withholding_total_metric'),
                DB::raw('COALESCE(order_snapshot.gross_revenue, item_agg.gross_items_total, 0) as gross_revenue_metric'),
                DB::raw('COALESCE(order_snapshot.net_receivable, 0) as net_receivable_metric'),
                DB::raw('COALESCE(order_snapshot.estimated_profit, 0) as estimated_profit_metric'),
                DB::raw('COALESCE(order_snapshot.confirmed_profit, 0) as confirmed_profit_metric'),
                DB::raw('COALESCE(order_snapshot.margin_percent, 0) as margin_percent_metric'),
                DB::raw("
                    COALESCE(
                        order_snapshot.profit_state,
                        CASE WHEN COALESCE(fin_agg.financial_event_count, 0) > 0 THEN 'confirmed' ELSE 'estimated' END
                    ) as profit_state_metric
                "),
                DB::raw("
                    CASE
                        WHEN COALESCE(order_snapshot.profit_state, CASE WHEN COALESCE(fin_agg.financial_event_count, 0) > 0 THEN 'confirmed' ELSE 'estimated' END) = 'confirmed'
                        THEN COALESCE(order_snapshot.confirmed_profit, 0)
                        ELSE COALESCE(order_snapshot.estimated_profit, 0)
                    END as profit_value_metric
                "),
            ])
            ->join('marketplace_stores', 'marketplace_stores.id', '=', 'channel_orders.store_id')
            ->join('legal_entities', 'legal_entities.id', '=', 'channel_orders.legal_entity_id')
            ->leftJoinSub($itemAggregate, 'item_agg', function ($join) {
                $join->on('item_agg.channel_order_id', '=', 'channel_orders.id');
            })
            ->leftJoinSub($financialAggregate, 'fin_agg', function ($join) {
                $join->on('fin_agg.channel_order_id', '=', 'channel_orders.id');
            })
            ->leftJoinSub($snapshotAggregate, 'order_snapshot', function ($join) {
                $join->on('order_snapshot.channel_order_id', '=', 'channel_orders.id');
            })
            ->where('marketplace_stores.user_id', auth()->id());

        if ($this->search !== '') {
            $searchTerm = trim($this->search);
            $query->where(function (Builder $builder) use ($searchTerm) {
                $builder->where('channel_orders.order_number', 'like', '%' . $searchTerm . '%')
                    ->orWhere('channel_orders.external_order_id', 'like', '%' . $searchTerm . '%')
                    ->orWhereHas('packages', function (Builder $packageQuery) use ($searchTerm) {
                        $packageQuery->where('package_number', 'like', '%' . $searchTerm . '%')
                            ->orWhere('cargo_tracking_number', 'like', '%' . $searchTerm . '%')
                            ->orWhere('cargo_barcode', 'like', '%' . $searchTerm . '%');
                    });
            });
        }

        if ($this->searchCustomer !== '') {
            $searchCustomer = trim($this->searchCustomer);
            $query->where(function (Builder $builder) use ($searchCustomer) {
                $builder->where('channel_orders.customer_name', 'like', '%' . $searchCustomer . '%')
                    ->orWhere('channel_orders.customer_phone', 'like', '%' . $searchCustomer . '%')
                    ->orWhere('channel_orders.customer_email', 'like', '%' . $searchCustomer . '%');
            });
        }

        $productSearch = trim($this->searchProduct !== '' ? $this->searchProduct : $this->searchBarcode);
        if ($productSearch !== '') {
            $query->whereHas('items', function (Builder $itemQuery) use ($productSearch) {
                $itemQuery->where('barcode', 'like', '%' . $productSearch . '%')
                    ->orWhere('stock_code', 'like', '%' . $productSearch . '%')
                    ->orWhere('product_name', 'like', '%' . $productSearch . '%');
            });
        }

        if ($this->statusFilter !== '') {
            $query->where('channel_orders.order_status', $this->statusFilter);
        }

        if ($this->marketplaceFilter !== '') {
            $query->where('marketplace_stores.marketplace', $this->marketplaceFilter);
        }

        if ($this->storeFilter !== '') {
            $query->where('channel_orders.store_id', $this->storeFilter);
        }

        if ($this->labelFilter !== '') {
            $query->where('channel_orders.color_label_key', $this->labelFilter);
        }

        if ($this->legalEntityFilter !== '') {
            $query->where('channel_orders.legal_entity_id', $this->legalEntityFilter);
        }

        if ($this->dateFrom !== '') {
            $query->whereDate('channel_orders.ordered_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo !== '') {
            $query->whereDate('channel_orders.ordered_at', '<=', $this->dateTo);
        }

        if ($this->profitStateFilter === 'confirmed') {
            $query->where(function (Builder $builder) {
                $builder->where('order_snapshot.profit_state', 'confirmed')
                    ->orWhere(function (Builder $inner) {
                        $inner->whereNull('order_snapshot.channel_order_id')
                            ->whereRaw('COALESCE(fin_agg.financial_event_count, 0) > 0');
                    });
            });
        } elseif ($this->profitStateFilter === 'estimated') {
            $query->where(function (Builder $builder) {
                $builder->where('order_snapshot.profit_state', 'estimated')
                    ->orWhere(function (Builder $inner) {
                        $inner->whereNull('order_snapshot.channel_order_id')
                            ->whereRaw('COALESCE(fin_agg.financial_event_count, 0) = 0');
                    });
            });
        } elseif ($this->profitStateFilter === 'missing') {
            $query->whereNull('order_snapshot.channel_order_id');
        }

        if ($this->financialStateFilter === 'ready') {
            $query->whereRaw('COALESCE(fin_agg.financial_event_count, 0) > 0');
        } elseif ($this->financialStateFilter === 'waiting') {
            $query->whereRaw('COALESCE(fin_agg.financial_event_count, 0) = 0');
        }

        if ($this->matchStateFilter === 'full_match') {
            $query->whereRaw('COALESCE(item_agg.item_lines_count, 0) > 0')
                ->whereRaw('COALESCE(item_agg.item_lines_count, 0) = COALESCE(item_agg.matched_lines_count, 0)');
        } elseif ($this->matchStateFilter === 'needs_match') {
            $query->whereRaw('COALESCE(item_agg.item_lines_count, 0) > COALESCE(item_agg.matched_lines_count, 0)');
        }

        return $query;
    }

    protected function buildLegacyQuery(): Builder
    {
        $query = MpOperationalOrder::query()
            ->with(['items.product', 'financialOrders'])
            ->orderByDesc('order_date');

        if ($this->search !== '') {
            $query->where(function (Builder $builder) {
                $builder->where('order_number', 'like', '%' . $this->search . '%')
                    ->orWhere('package_number', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->searchCustomer !== '') {
            $query->where(function (Builder $builder) {
                $builder->where('customer_name', 'like', '%' . $this->searchCustomer . '%')
                    ->orWhere('customer_phone', 'like', '%' . $this->searchCustomer . '%')
                    ->orWhere('email', 'like', '%' . $this->searchCustomer . '%');
            });
        }

        if ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->cityFilter !== '') {
            $query->where('customer_city', $this->cityFilter);
        }

        if ($this->corporateFilter !== '') {
            $query->where('is_corporate_invoice', $this->corporateFilter);
        }

        if ($this->dateFrom !== '') {
            $query->whereDate('order_date', '>=', $this->dateFrom);
        }

        if ($this->dateTo !== '') {
            $query->whereDate('order_date', '<=', $this->dateTo);
        }

        $productSearch = trim($this->searchProduct !== '' ? $this->searchProduct : $this->searchBarcode);
        if ($productSearch !== '') {
            $query->whereHas('items', function (Builder $itemQuery) use ($productSearch) {
                $itemQuery->where('barcode', 'like', '%' . $productSearch . '%')
                    ->orWhere('product_name', 'like', '%' . $productSearch . '%')
                    ->orWhere('stock_code', 'like', '%' . $productSearch . '%');
            });
        }

        if ($this->brandFilter !== '') {
            $query->whereHas('items', function (Builder $itemQuery) {
                $itemQuery->where('brand', $this->brandFilter);
            });
        }

        return $query;
    }

    protected function hasChannelData(): bool
    {
        return ChannelOrder::query()
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', auth()->id()))
            ->exists();
    }

    protected function getChannelStats(): array
    {
        $baseQuery = $this->buildChannelBaseQuery();
        $rawStats = DB::query()
            ->fromSub((clone $baseQuery)->reorder(), 'orders_base')
            ->selectRaw('
                COUNT(DISTINCT id) as total_orders,
                COALESCE(SUM(gross_revenue_metric), 0) as total_revenue,
                COALESCE(
                    SUM(
                        CASE
                            WHEN profit_state_metric = "confirmed"
                            THEN COALESCE(confirmed_profit_metric, 0)
                            ELSE COALESCE(estimated_profit_metric, 0)
                        END
                    ),
                0) as total_profit,
                COALESCE(AVG(gross_revenue_metric), 0) as avg_order_value,
                SUM(CASE WHEN COALESCE(financial_event_count, 0) > 0 THEN 1 ELSE 0 END) as finance_ready_orders,
                SUM(CASE WHEN COALESCE(financial_event_count, 0) = 0 THEN 1 ELSE 0 END) as finance_waiting_orders,
                SUM(CASE WHEN COALESCE(item_lines_count, 0) > COALESCE(matched_lines_count, 0) THEN 1 ELSE 0 END) as match_issue_orders,
                SUM(
                    CASE
                        WHEN profit_state_metric = "confirmed"
                        THEN 1 ELSE 0
                    END
                ) as confirmed_orders,
                SUM(
                    CASE
                        WHEN profit_state_metric = "estimated"
                        THEN 1 ELSE 0
                    END
                ) as estimated_orders
            ')
            ->first();

        return [
            'total_orders' => (int) ($rawStats->total_orders ?? 0),
            'total_revenue' => (float) ($rawStats->total_revenue ?? 0),
            'total_profit' => (float) ($rawStats->total_profit ?? 0),
            'avg_order_value' => (float) ($rawStats->avg_order_value ?? 0),
            'finance_ready_orders' => (int) ($rawStats->finance_ready_orders ?? 0),
            'finance_waiting_orders' => (int) ($rawStats->finance_waiting_orders ?? 0),
            'match_issue_orders' => (int) ($rawStats->match_issue_orders ?? 0),
            'confirmed_orders' => (int) ($rawStats->confirmed_orders ?? 0),
            'estimated_orders' => (int) ($rawStats->estimated_orders ?? 0),
        ];
    }

    protected function getSidebarSummary(): array
    {
        $storesQuery = MarketplaceStore::query()->where('user_id', auth()->id());
        $totalStores = (clone $storesQuery)->count();
        $activeStores = (clone $storesQuery)->where('is_active', true)->count();
        $ordersCount = (clone $storesQuery)->withCount('channelOrders')->get()->sum('channel_orders_count');

        $recentRuns = IntegrationSyncRun::query()
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', auth()->id()))
            ->where('created_at', '>=', now()->subDay())
            ->selectRaw('
                SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_runs,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed_runs,
                SUM(CASE WHEN status = "processing" THEN 1 ELSE 0 END) as processing_runs
            ')
            ->first();

        $matchIssueCount = ChannelOrderItem::query()
            ->whereHas('order.store', fn (Builder $query) => $query->where('user_id', auth()->id()))
            ->where(function (Builder $query) {
                $query->where('is_matched', false)
                    ->orWhereNull('mp_product_id');
            })
            ->count();

        $latestSyncAt = IntegrationSyncRun::query()
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', auth()->id()))
            ->where('status', 'completed')
            ->max('finished_at');

        return [
            'total_stores' => $totalStores,
            'active_stores' => $activeStores,
            'orders_count' => $ordersCount,
            'completed_runs' => (int) ($recentRuns->completed_runs ?? 0),
            'failed_runs' => (int) ($recentRuns->failed_runs ?? 0),
            'processing_runs' => (int) ($recentRuns->processing_runs ?? 0),
            'match_issue_count' => $matchIssueCount,
            'latest_sync_at' => $latestSyncAt ? Carbon::parse($latestSyncAt) : null,
        ];
    }

    protected function getLegacySummary(): array
    {
        return [
            'total_orders' => MpOperationalOrder::query()->count(),
            'last_imported_at' => MpOperationalOrder::query()->max('updated_at'),
            'distinct_brands' => MpOperationalOrderItem::query()
                ->whereNotNull('brand')
                ->distinct()
                ->count('brand'),
        ];
    }

    protected function getDiagnosticsGuidanceSummary(): array
    {
        $guidance = app(MarketplaceDiagnosticsGuidanceService::class)->guidanceForUser(auth()->id() ?? 1, [
            'store_id' => $this->storeFilter !== '' ? (int) $this->storeFilter : null,
            'hours' => 168,
            'limit' => 200,
        ]);

        $items = collect($guidance['items'])
            ->filter(fn (array $item) => in_array($item['category'] ?? '', [
                'product_matching',
                'order_identity',
                'finance_mapping',
                'legacy_financial_projection',
            ], true))
            ->when($this->marketplaceFilter !== '', fn ($collection) => $collection->where('marketplace', $this->marketplaceFilter))
            ->take(3)
            ->values();

        return [
            'totals' => [
                'items' => $items->count(),
                'critical' => $items->where('severity', 'critical')->count(),
                'warning' => $items->where('severity', 'warning')->count(),
                'info' => $items->where('severity', 'info')->count(),
            ],
            'items' => $items->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function getLegacyProjectionGuidanceCard(): ?array
    {
        $service = app(LegacyFinancialProjectionInsightsService::class);

        $rows = MarketplaceStore::query()
            ->with('legalEntity:id,name')
            ->where('user_id', auth()->id())
            ->when($this->storeFilter !== '', fn (Builder $query) => $query->whereKey((int) $this->storeFilter))
            ->where('is_active', true)
            ->orderBy('store_name')
            ->get(['id', 'legal_entity_id', 'marketplace', 'store_name'])
            ->map(function (MarketplaceStore $store) use ($service): array {
                $summary = $service->summaryForUser(auth()->id() ?? 1, $store->id, $store->legal_entity_id);

                return [
                    'store_id' => (int) $store->id,
                    'store_name' => $store->store_name,
                    'marketplace' => $store->marketplace,
                    'legal_entity_name' => $store->legalEntity?->name,
                    'pending_rows' => (int) ($summary['pending_rows'] ?? 0),
                    'projected_rows' => (int) ($summary['projected_rows'] ?? 0),
                    'legacy_event_orders' => (int) ($summary['legacy_event_orders'] ?? 0),
                    'confirmed_orders' => (int) ($summary['confirmed_orders'] ?? 0),
                    'last_projected_at' => $summary['last_projected_at'] ?? null,
                ];
            })
            ->filter(fn (array $row) => $row['pending_rows'] > 0 || $row['projected_rows'] > 0 || $row['legacy_event_orders'] > 0 || $row['confirmed_orders'] > 0)
            ->sortByDesc(fn (array $row) => ($row['pending_rows'] * 1000000) + ($row['confirmed_orders'] * 1000) + $row['projected_rows'])
            ->values();

        $top = $rows->first();

        if (!$top) {
            return null;
        }

        return array_merge($top, [
            'scope_count' => $rows->count(),
            'state' => $top['pending_rows'] > 0 ? 'warning' : ($top['confirmed_orders'] > 0 ? 'success' : 'default'),
            'title' => $top['pending_rows'] > 0
                ? 'Eski veri finans kuyruğu mağaza bazında görünüyor'
                : 'Eski veri yansıtması sonrası kesin etkisi görünüyor',
            'description' => $top['pending_rows'] > 0
                ? 'Yansıtma mağazasını hazırla ve bekleyen eski veri finans satırlarını bu mağaza üzerinden V2 kayıt defterine taşı.'
                : 'Bu mağazada eski veri yansıtması tamamlanmış; kesin duruma geçen siparişleri kontrol edebilirsiniz.',
        ]);
    }

    protected function getActiveFilters(): array
    {
        $orderLabels = $this->orderLabelDefinitions();

        return array_values(array_filter([
            $this->search !== '' ? 'Arama: ' . $this->search : null,
            $this->searchCustomer !== '' ? 'Müşteri: ' . $this->searchCustomer : null,
            $this->searchProduct !== '' ? 'Ürün: ' . $this->searchProduct : null,
            $this->marketplaceFilter !== '' ? 'Pazaryeri: ' . $this->humanMarketplace($this->marketplaceFilter) : null,
            $this->storeFilter !== '' ? 'Mağaza filtresi aktif' : null,
            $this->statusFilter !== '' ? 'Durum: ' . $this->humanStatus($this->statusFilter) : null,
            $this->labelFilter !== '' ? 'Etiket: ' . ($orderLabels[$this->labelFilter]['name'] ?? Str::headline($this->labelFilter)) : null,
            $this->profitStateFilter !== '' ? 'Kâr: ' . $this->profitStateLabel($this->profitStateFilter) : null,
            $this->financialStateFilter !== '' ? 'Finans: ' . ($this->financialStateFilter === 'ready' ? 'Hazır' : 'Bekliyor') : null,
            $this->matchStateFilter !== '' ? 'Eşleşme: ' . ($this->matchStateFilter === 'full_match' ? 'Tam' : 'Sorunlu') : null,
            $this->dateFrom !== '' || $this->dateTo !== ''
                ? 'Tarih: ' . ($this->dateFrom ?: '...') . ' - ' . ($this->dateTo ?: '...')
                : null,
        ]));
    }

    protected function getStatusOptions()
    {
        return ChannelOrder::query()
            ->join('marketplace_stores', 'marketplace_stores.id', '=', 'channel_orders.store_id')
            ->where('marketplace_stores.user_id', auth()->id())
            ->whereNotNull('channel_orders.order_status')
            ->distinct()
            ->orderBy('channel_orders.order_status')
            ->pluck('channel_orders.order_status');
    }

    protected function getMarketplaceOptions()
    {
        return MarketplaceStore::query()
            ->where('user_id', auth()->id())
            ->select('marketplace')
            ->distinct()
            ->orderBy('marketplace')
            ->pluck('marketplace');
    }

    protected function getStoreOptions()
    {
        return MarketplaceStore::query()
            ->where('user_id', auth()->id())
            ->orderBy('store_name')
            ->get(['id', 'store_name', 'marketplace', 'legal_entity_id']);
    }

    protected function getLegalEntityOptions()
    {
        return LegalEntity::query()
            ->where('user_id', auth()->id())
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'tax_number']);
    }

    protected function resolveLegacyProjectionStore(): ?MarketplaceStore
    {
        if (!filled($this->legacyProjectionStoreId)) {
            return null;
        }

        return MarketplaceStore::query()
            ->where('user_id', auth()->id())
            ->find((int) $this->legacyProjectionStoreId);
    }

    /**
     * @return array{projected_rows:int,created:int,updated:int,skipped:int,impacted_order_ids:array<int>}
     */
    protected function getLegacyFinancialProjectionPreview(): array
    {
        $store = $this->resolveLegacyProjectionStore();

        if (!$store) {
            return [
                'projected_rows' => 0,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'impacted_order_ids' => [],
            ];
        }

        return app(LegacyFinancialProjectionService::class)->previewStore($store, true);
    }

    protected function syncLegacyProjectionStoreFromFilter(): void
    {
        if ($this->legacyProjectionStoreId === '' && $this->storeFilter !== '') {
            $this->legacyProjectionStoreId = $this->storeFilter;
            $this->previewLegacyFinancials();
        }
    }

    public function getLegacyProjectionDryRunCommandProperty(): ?string
    {
        if (!filled($this->legacyProjectionStoreId)) {
            return null;
        }

        return 'php artisan marketplace:project-legacy-financials '
            . (int) $this->legacyProjectionStoreId
            . ' --only-unprojected --dry-run';
    }

    public function getLegacyProjectionRunCommandProperty(): ?string
    {
        if (!filled($this->legacyProjectionStoreId)) {
            return null;
        }

        return 'php artisan marketplace:project-legacy-financials '
            . (int) $this->legacyProjectionStoreId
            . ' --only-unprojected';
    }

    protected function normalizeVisibleColumns(array $columns): array
    {
        $valid = array_keys(static::$allColumnDefs);
        $normalized = array_values(array_filter($columns, fn ($column) => in_array($column, $valid, true)));

        return $normalized !== [] ? $normalized : $this->visibleColumns;
    }

    protected function orderLabelDefaults(): array
    {
        return [
            'label_1' => ['name' => 'Öncelikli', 'color' => '#EF4444'],
            'label_2' => ['name' => 'Finans', 'color' => '#F97316'],
            'label_3' => ['name' => 'Kontrol', 'color' => '#EAB308'],
            'label_4' => ['name' => 'Beklet', 'color' => '#3B82F6'],
            'label_5' => ['name' => 'VIP', 'color' => '#10B981'],
            'label_6' => ['name' => 'Özel', 'color' => '#8B5CF6'],
        ];
    }

    protected function orderLabelFormDefaults(): array
    {
        return collect($this->orderLabelDefinitions())
            ->mapWithKeys(fn (array $label, string $key) => [
                $key => [
                    'name' => $label['name'],
                    'color' => $label['color'],
                ],
            ])
            ->all();
    }

    /**
     * @return array<string, array{key:string,name:string,color:string,bg_color:string,border_color:string}>
     */
    public function orderLabelDefinitions(): array
    {
        $saved = app(MpSettingsService::class)->getArray('marketplace_orders.v2.color_labels', []);
        $definitions = [];

        foreach ($this->orderLabelDefaults() as $key => $defaults) {
            $name = trim((string) data_get($saved, $key . '.name', $defaults['name']));
            $color = $this->normalizeHexColor((string) data_get($saved, $key . '.color', $defaults['color']), $defaults['color']);

            $definitions[$key] = [
                'key' => $key,
                'name' => $name !== '' ? $name : $defaults['name'],
                'color' => $color,
                'bg_color' => $this->hexToRgba($color, 0.12),
                'border_color' => $this->hexToRgba($color, 0.24),
            ];
        }

        return $definitions;
    }

    public function orderLabelMeta(?string $labelKey): ?array
    {
        if (!filled($labelKey)) {
            return null;
        }

        return $this->orderLabelDefinitions()[(string) $labelKey] ?? null;
    }

    protected function normalizeHexColor(?string $value, string $fallback = '#64748B'): string
    {
        $candidate = strtoupper(trim((string) $value));

        if (preg_match('/^#[0-9A-F]{6}$/', $candidate) === 1) {
            return $candidate;
        }

        return strtoupper($fallback);
    }

    protected function hexToRgba(string $hex, float $alpha): string
    {
        $normalized = ltrim($this->normalizeHexColor($hex), '#');

        return sprintf(
            'rgba(%d, %d, %d, %.2f)',
            hexdec(substr($normalized, 0, 2)),
            hexdec(substr($normalized, 2, 2)),
            hexdec(substr($normalized, 4, 2)),
            $alpha
        );
    }

    public function humanMarketplace(?string $marketplace): string
    {
        return (string) (MarketplaceProviderRegistry::get((string) $marketplace)['label'] ?? Str::headline((string) $marketplace));
    }

    public function humanStatus(?string $status): string
    {
        $normalized = Str::lower(trim((string) $status));

        return match (true) {
            $normalized === '' => 'Durum yok',
            str_contains($normalized, 'created'),
            str_contains($normalized, 'new') => 'Yeni',
            str_contains($normalized, 'approved'),
            str_contains($normalized, 'onay') => 'Onaylandı',
            str_contains($normalized, 'picking'),
            str_contains($normalized, 'packing') => 'Hazırlanıyor',
            str_contains($normalized, 'shipped'),
            str_contains($normalized, 'kargo') => 'Kargoda',
            str_contains($normalized, 'delivered'),
            str_contains($normalized, 'teslim') => 'Teslim edildi',
            str_contains($normalized, 'cancel'),
            str_contains($normalized, 'iptal') => 'İptal',
            str_contains($normalized, 'return'),
            str_contains($normalized, 'iade') => 'İade',
            str_contains($normalized, 'reject'),
            str_contains($normalized, 'redd') => 'Reddedildi',
            default => Str::headline((string) $status),
        };
    }

    public function statusTone(?string $status): string
    {
        $normalized = Str::lower(trim((string) $status));

        return match (true) {
            $normalized === '' => 'default',
            str_contains($normalized, 'delivered'),
            str_contains($normalized, 'teslim') => 'success',
            str_contains($normalized, 'cancel'),
            str_contains($normalized, 'iptal'),
            str_contains($normalized, 'return'),
            str_contains($normalized, 'iade'),
            str_contains($normalized, 'reject'),
            str_contains($normalized, 'redd') => 'danger',
            str_contains($normalized, 'picking'),
            str_contains($normalized, 'packing'),
            str_contains($normalized, 'shipped'),
            str_contains($normalized, 'kargo') => 'warning',
            default => 'info',
        };
    }

    public function profitStateLabel(?string $state): string
    {
        return match ($state) {
            'confirmed' => 'Kesin',
            'estimated' => 'Tahmini',
            'missing' => 'Hesaplanmadı',
            default => 'Tahmini',
        };
    }

    public function profitStateTone(?string $state): string
    {
        return match ($state) {
            'confirmed' => 'success',
            'estimated' => 'warning',
            'missing' => 'danger',
            default => 'info',
        };
    }

    public function guidanceSeverityTone(?string $severity): string
    {
        return match ($severity) {
            'critical' => 'danger',
            'warning' => 'warning',
            'info' => 'info',
            default => 'default',
        };
    }

    public function guidanceSeverityLabel(?string $severity): string
    {
        return match ($severity) {
            'critical' => 'Kritik',
            'warning' => 'Uyarı',
            'info' => 'Bilgi',
            default => Str::headline((string) $severity),
        };
    }

    public function guidanceCategoryLabel(?string $category): string
    {
        return match ($category) {
            'product_matching' => 'Ürün eşleşme',
            'order_identity' => 'Sipariş kimliği',
            'finance_mapping' => 'Finans etkisi',
            'legacy_financial_projection' => 'Eski veri finans köprüsü',
            default => Str::headline((string) $category),
        };
    }

    public function guidanceRouteLabel(?string $route): string
    {
        return match ($route) {
            'mp.matching' => 'Eşleştirme Merkezi',
            'mp.integrations' => 'Entegrasyonlar',
            'mp.finance' => 'Finans',
            'mp.orders' => 'Siparişler',
            default => 'Siparişler',
        };
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function guidanceRoute(array $item): string
    {
        $route = (string) ($item['route'] ?? 'mp.orders');
        $storeId = $item['store_id'] ?? null;

        return match ($route) {
            'mp.matching' => route('mp.matching', array_filter([
                'storeFilter' => $storeId,
                'statusFilter' => 'pending',
            ], fn ($value) => $value !== null && $value !== '')),
            'mp.integrations' => route('mp.integrations', array_filter([
                'store' => $storeId,
            ], fn ($value) => $value !== null && $value !== '')),
            'mp.finance' => route('mp.finance', array_filter([
                'storeFilter' => $storeId,
            ], fn ($value) => $value !== null && $value !== '')),
            default => route('mp.orders', array_filter([
                'storeFilter' => $storeId,
            ], fn ($value) => $value !== null && $value !== '')),
        };
    }

    public function guidanceFocusLabel(): string
    {
        $topItem = $this->getDiagnosticsGuidanceSummary()['items'][0] ?? null;

        if (!$topItem) {
            return 'Odak yok';
        }

        return match ($topItem['category'] ?? null) {
            'legacy_financial_projection' => 'Siparişlere git',
            'product_matching' => 'Listeyi odakla',
            'order_identity' => 'Kimlik riskine odaklan',
            'finance_mapping' => 'Finans bekleyenleri odakla',
            default => 'Listeyi odakla',
        };
    }

    public function guidanceSyncLabel(): string
    {
        $topItem = $this->getDiagnosticsGuidanceSummary()['items'][0] ?? null;

        return match ($topItem['category'] ?? null) {
            'legacy_financial_projection' => 'Yansıtma ekranına git',
            default => 'Sipariş senkronunu başlat',
        };
    }

    public function focusTopGuidance(): void
    {
        $topItem = $this->getDiagnosticsGuidanceSummary()['items'][0] ?? null;

        if (!$topItem) {
            $this->actionMessage = 'Odaklanacak bir diagnostik öneri bulunamadı.';
            $this->actionMessageTone = 'warning';

            return;
        }

        if (($topItem['category'] ?? null) === 'legacy_financial_projection') {
            $this->redirect($this->guidanceRoute($topItem), navigate: true);

            return;
        }

        $this->marketplaceFilter = filled($topItem['marketplace'] ?? null) ? (string) $topItem['marketplace'] : '';
        $this->storeFilter = filled($topItem['store_id'] ?? null) ? (string) $topItem['store_id'] : '';

        if (($topItem['category'] ?? null) === 'product_matching') {
            $this->matchStateFilter = 'needs_match';
            $this->financialStateFilter = '';
        } elseif (($topItem['category'] ?? null) === 'finance_mapping') {
            $this->financialStateFilter = 'waiting';
            $this->matchStateFilter = '';
        } else {
            $this->financialStateFilter = '';
            $this->matchStateFilter = '';
        }

        $this->resetPage();

        $this->actionMessage = 'Sipariş listesi en kritik tanı kaydına göre odaklandı.';
        $this->actionMessageTone = 'success';
    }

    public function syncTopGuidance(): void
    {
        $topItem = $this->getDiagnosticsGuidanceSummary()['items'][0] ?? null;

        if (($topItem['category'] ?? null) === 'legacy_financial_projection') {
            $this->redirect($this->guidanceRoute($topItem), navigate: true);

            return;
        }

        $storeId = (int) ($topItem['store_id'] ?? 0);

        if ($storeId <= 0) {
            $this->actionMessage = 'Senkron başlatmak için mağaza bilgisi içeren bir tanı kaydı bulunamadı.';
            $this->actionMessageTone = 'warning';

            return;
        }

        $store = MarketplaceStore::query()
            ->with('connection')
            ->whereKey($storeId)
            ->where('user_id', auth()->id())
            ->first();

        if (!$store || !$store->connection || $store->connection->status === 'draft') {
            $this->actionMessage = 'Önce seçili mağazanın bağlantı bilgilerini tamamlayın.';
            $this->actionMessageTone = 'warning';

            return;
        }

        $result = app(MarketplaceManualSyncDispatchService::class)->dispatch($store, 'orders', [
            'options' => [],
            'source' => 'guidance_shortcut',
            'category' => $topItem['category'] ?? null,
            'origin_screen' => 'orders',
        ]);

        $feedback = app(MarketplaceManualSyncDispatchService::class)->feedback(
            $result,
            'sipariş',
            $store->store_name,
        );

        $this->actionMessage = $feedback['message'];
        $this->actionMessageTone = $feedback['tone'];
    }

    public function focusLegacyProjectionCard(): void
    {
        $card = $this->getLegacyProjectionGuidanceCard();

        if (!$card) {
            $this->actionMessage = 'Odaklanacak eski veri yansıtma kuyruğu kaydı bulunamadı.';
            $this->actionMessageTone = 'warning';

            return;
        }

        $this->marketplaceFilter = filled($card['marketplace'] ?? null) ? (string) $card['marketplace'] : '';
        $this->storeFilter = (string) $card['store_id'];
        $this->legacyProjectionStoreId = (string) $card['store_id'];
        $this->resetPage();

        $this->previewLegacyFinancials();

        $this->actionMessage = 'Eski veri kuyruğu için mağaza ve yansıtma alanı odaklandı.';
        $this->actionMessageTone = 'success';
    }

    public function updatedLegacyProjectionStoreId(): void
    {
        if (filled($this->legacyProjectionStoreId)) {
            $this->previewLegacyFinancials();
        } else {
            $this->legacyProjectionResult = [];
        }
    }

    public function orderActionLabel(?string $actionType): string
    {
        return MarketplaceOrderActionService::ACTION_LABELS[$actionType] ?? Str::headline((string) $actionType);
    }

    public function orderActionStatusLabel(?string $status): string
    {
        return match ($status) {
            'queued' => 'Sırada',
            'processing' => 'İşleniyor',
            'completed' => 'Tamamlandı',
            'failed' => 'Hata',
            'retrying' => 'Tekrar denenecek',
            default => Str::headline((string) $status),
        };
    }

    public function orderActionStatusTone(?string $status): string
    {
        return match ($status) {
            'completed' => 'success',
            'failed' => 'danger',
            'retrying' => 'warning',
            'processing' => 'info',
            default => 'default',
        };
    }

    public function actionCanRetry(?string $status): bool
    {
        return in_array((string) $status, ['failed', 'retrying'], true);
    }

    protected function emptyOrderForm(): array
    {
        return [
            'order_number' => '',
            'order_status' => '',
            'customer_name' => '',
            'customer_email' => '',
            'customer_phone' => '',
            'commercial_type' => '',
            'billing_name' => '',
            'billing_tax_number' => '',
            'shipment_city' => '',
            'shipment_district' => '',
            'ordered_at' => '',
        ];
    }

    protected function nullableTrim(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    protected function nullableDecimal(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }

    protected function appendCopySuffix(string $base, string $suffix, int $maxLength): string
    {
        if (mb_strlen($suffix) >= $maxLength) {
            return mb_substr($suffix, 0, $maxLength);
        }

        return mb_substr($base, 0, $maxLength - mb_strlen($suffix)) . $suffix;
    }

    /**
     * @return array<string, string>
     */
    public function bulkActionOptions(): array
    {
        return [
            'refresh_order' => 'Siparişleri yenile',
            'refresh_cargo' => 'Kargoyu yenile',
            'refresh_finance' => 'Finansı yenile',
            'recalculate_profit' => 'Kârı yeniden hesapla',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function bulkPackageActionOptions(): array
    {
        return [
            'package_picking' => 'Toplama bildir',
            'package_invoiced' => 'Fatura kesildi bildir',
            'package_common_label_create' => 'Ortak barkod talep et',
            'package_common_label_get' => 'Ortak barkod getir',
            'package_invoice_link' => 'Fatura linki gönder',
        ];
    }

    public function selectedDocumentCount(): int
    {
        return count($this->selectedOrderIds) + count($this->selectedPackageIds);
    }

    public function hasDocumentSelection(): bool
    {
        return $this->selectedDocumentCount() > 0;
    }

    public function documentTypeLabel(string $documentType): string
    {
        return match ($documentType) {
            'label' => 'Kargo etiketi PDF',
            'dispatch' => 'İrsaliye PDF',
            default => 'Belge PDF',
        };
    }

    public function documentDownloadUrl(string $documentType, ?int $orderId = null): ?string
    {
        $orderIds = $orderId !== null
            ? [$orderId]
            : collect($this->selectedOrderIds)
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all();

        $packageIds = $orderId !== null
            ? []
            : collect($this->selectedPackageIds)
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all();

        if ($orderIds === [] && $packageIds === []) {
            return null;
        }

        return route('mp.orders.documents.download', array_filter([
            'documentType' => $documentType,
            'order_ids' => $orderIds !== [] ? implode(',', $orderIds) : null,
            'package_ids' => $packageIds !== [] ? implode(',', $packageIds) : null,
        ], fn ($value) => $value !== null && $value !== ''));
    }

    public function actionResponseSummary(IntegrationOrderActionRun $actionRun): ?string
    {
        if (data_get($actionRun, 'response_json.sync_run_id')) {
            return 'Senkron #' . data_get($actionRun, 'response_json.sync_run_id');
        }

        if (filled($actionRun->external_action_id)) {
            return 'İstek #' . $actionRun->external_action_id;
        }

        if (filled(data_get($actionRun, 'response_json.label_count'))) {
            return 'Etiket adedi: ' . data_get($actionRun, 'response_json.label_count');
        }

        if (data_get($actionRun, 'response_json.label_available') === true) {
            return 'Etiket cevabı alındı';
        }

        if (filled(data_get($actionRun, 'response_json.response_status'))) {
            return 'HTTP ' . data_get($actionRun, 'response_json.response_status');
        }

        if (filled(data_get($actionRun, 'request_context_json.invoice_link'))) {
            return 'Fatura linki payload içinde gönderildi';
        }

        return null;
    }

    public function orderSupportsCapability(ChannelOrder $order, string $capability): bool
    {
        $marketplace = (string) ($order->store?->marketplace ?? '');

        if ($marketplace === '') {
            return false;
        }

        return $this->marketplaceSupportsCapability($marketplace, $capability);
    }

    public function packageSupportsAction(ChannelOrderPackage $package, string $actionType): bool
    {
        $capability = $this->packageActionCapability($actionType);

        if ($capability === null) {
            return false;
        }

        $marketplace = (string) ($package->store?->marketplace ?? $package->order?->store?->marketplace ?? '');

        if ($marketplace === '') {
            return false;
        }

        return $this->marketplaceSupportsCapability($marketplace, $capability);
    }

    protected function buildPackageActionContext(int $packageId, string $actionType): array
    {
        $form = $this->packageActionForms[$packageId] ?? [];

        return match ($actionType) {
            'package_invoiced' => [
                'invoice_number' => trim((string) ($form['invoice_number'] ?? '')),
                'invoice_date' => trim((string) ($form['invoice_date'] ?? now()->toDateString())),
            ],
            'package_common_label_create' => array_filter([
                'format' => trim((string) ($form['label_format'] ?? 'ZPL')),
                'box_quantity' => filled($form['box_quantity'] ?? null) ? (int) $form['box_quantity'] : null,
                'volumetric_weight' => filled($form['volumetric_weight'] ?? null) ? (float) $form['volumetric_weight'] : null,
                'desi' => filled($form['desi'] ?? null) ? (float) $form['desi'] : null,
            ], fn ($value) => $value !== null && $value !== ''),
            'package_common_label_get' => [
                'format' => trim((string) ($form['label_format'] ?? 'ZPL')),
            ],
            'package_invoice_link' => [
                'invoice_link' => trim((string) ($form['invoice_link'] ?? '')),
                'invoice_number' => trim((string) ($form['invoice_number'] ?? '')),
                'invoice_date' => trim((string) ($form['invoice_date'] ?? now()->toDateString())),
            ],
            default => [],
        };
    }

    protected function packageActionCapability(string $actionType): ?string
    {
        return match ($actionType) {
            'package_picking' => 'package_picking',
            'package_invoiced' => 'package_invoiced',
            'package_common_label_create' => 'package_common_label_create',
            'package_common_label_get' => 'package_common_label_get',
            'package_invoice_link' => 'package_invoice_link',
            default => null,
        };
    }

    protected function marketplaceSupportsCapability(string $marketplace, string $capability): bool
    {
        $capabilities = $this->marketplaceCapabilities($marketplace);

        if (($capabilities[$capability] ?? false) === true) {
            return true;
        }

        $fallback = match ($capability) {
            'package_picking', 'package_invoiced' => 'package_status',
            'package_common_label_create', 'package_common_label_get' => 'common_label',
            'package_invoice_link' => 'invoice_link',
            default => null,
        };

        return $fallback !== null && (($capabilities[$fallback] ?? false) === true);
    }

    /**
     * @return array<string, bool>
     */
    protected function marketplaceCapabilities(string $marketplace): array
    {
        if (!array_key_exists($marketplace, $this->connectorCapabilitiesCache)) {
            $this->connectorCapabilitiesCache[$marketplace] = app(MarketplaceConnectorManager::class)
                ->resolve($marketplace)
                ->capabilities();
        }

        return $this->connectorCapabilitiesCache[$marketplace];
    }

    /**
     * @return array<int, string>
     */
    protected function currentPageOrderIds(): array
    {
        return $this->buildChannelOrdersQuery()
            ->reorder($this->sortField, $this->sortDirection)
            ->orderByDesc('channel_orders.ordered_at')
            ->forPage($this->getPage(), 20)
            ->pluck('channel_orders.id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    public function render()
    {
        $ordersPaginator = $this->buildChannelOrdersQuery()
            ->reorder($this->sortField, $this->sortDirection)
            ->orderByDesc('channel_orders.ordered_at')
            ->paginate(20);

        $pageOrders = $ordersPaginator->getCollection();
        $legacyOperationalOrders = collect();

        if ($pageOrders->isNotEmpty()) {
            $legacyOperationalOrders = MpOperationalOrder::query()
                ->with(['items.product', 'financialOrders'])
                ->whereIn('store_id', $pageOrders->pluck('store_id')->filter()->unique()->all())
                ->whereIn('order_number', $pageOrders->pluck('order_number')->filter()->unique()->all())
                ->get()
                ->keyBy(fn (MpOperationalOrder $order) => $order->store_id . '|' . $order->order_number);
        }

        $orders = $ordersPaginator->through(function (ChannelOrder $order) use ($legacyOperationalOrders) {
            $snapshot = $order->profitSnapshots->first();
            $order->setAttribute('order_snapshot', $snapshot);
            $order->setAttribute('package_summary', $order->packages->first());
            $order->setAttribute(
                'legacy_operational_order',
                $legacyOperationalOrders->get($order->store_id . '|' . $order->order_number)
            );

            return $order;
        });

        return view('livewire.marketplace-orders', [
            'orders' => $orders,
            'stats' => $this->getChannelStats(),
            'statusOptions' => $this->getStatusOptions(),
            'marketplaceOptions' => $this->getMarketplaceOptions(),
            'storeOptions' => $this->getStoreOptions(),
            'legacyProjectionStoreOptions' => $this->getStoreOptions(),
            'legacyProjectionTargetStore' => $this->resolveLegacyProjectionStore(),
            'legacyFinancialProjectionPreview' => $this->getLegacyFinancialProjectionPreview(),
            'legacyProjectionResult' => $this->legacyProjectionResult,
            'legacyProjectionDryRunCommand' => $this->getLegacyProjectionDryRunCommandProperty(),
            'legacyProjectionRunCommand' => $this->getLegacyProjectionRunCommandProperty(),
            'legalEntityOptions' => $this->getLegalEntityOptions(),
            'sidebarSummary' => $this->getSidebarSummary(),
            'legacySummary' => $this->getLegacySummary(),
            'diagnosticsGuidance' => $this->getDiagnosticsGuidanceSummary(),
            'legacyProjectionGuidanceCard' => $this->getLegacyProjectionGuidanceCard(),
            'activeFilters' => $this->getActiveFilters(),
            'orderLabelDefinitions' => $this->orderLabelDefinitions(),
            'columnDefs' => static::$allColumnDefs,
            'sortableColumns' => static::$sortableColumns,
            'hasConfiguredStores' => MarketplaceStore::query()->where('user_id', auth()->id())->exists(),
            'hasChannelData' => $this->hasChannelData(),
        ])->layout('layouts.app', ['title' => 'Pazaryeri Siparişleri']);
    }
}
