<?php

namespace App\Livewire;

use App\Jobs\SyncOperationalToFinancialJob;
use App\Models\ChannelListing;
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
use App\Services\ProfitabilityMetric;
use App\Services\ProductCompositionResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

    public int $perPage = 20;

    public static array $sortableColumns = [
        'siparis' => 'source_ordered_at_metric',
        'magaza' => 'store_name_alias',
        'musteri' => 'customer_name',
        'lojistik' => 'cargo_due_at_metric',
        'tarih' => 'source_ordered_at_metric',
        'ciro' => 'gross_revenue_metric',
        'muhasebe' => 'net_receivable_metric',
        'kar' => 'profit_value_metric',
        'durum' => 'display_status_sort_key',
    ];

    public static array $sortPresets = [
        'order_date_desc' => [
            'label' => 'Sipariş tarihi: yeni tarih önce',
            'field' => 'source_ordered_at_metric',
            'direction' => 'desc',
        ],
        'order_date_asc' => [
            'label' => 'Sipariş tarihi: eski tarih önce',
            'field' => 'source_ordered_at_metric',
            'direction' => 'asc',
        ],
        'cargo_due_asc' => [
            'label' => 'Kargoya son teslim: yakın tarih önce',
            'field' => 'cargo_due_at_metric',
            'direction' => 'asc',
        ],
        'cargo_due_desc' => [
            'label' => 'Kargoya son teslim: uzak tarih önce',
            'field' => 'cargo_due_at_metric',
            'direction' => 'desc',
        ],
    ];

    public static array $allColumnDefs = [
        'siparis' => 'Sipariş',
        'magaza' => 'Mağaza',
        'musteri' => 'Müşteri',
        'lojistik' => 'Lojistik',
        'ciro' => 'Ciro',
        'muhasebe' => 'Muhasebe',
        'kar' => 'Kârlılık',
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
    public string $sortField = 'source_ordered_at_metric';
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
        'customer_note' => '',
        'commercial_type' => '',
        'billing_name' => '',
        'billing_tax_number' => '',
        'shipment_city' => '',
        'shipment_district' => '',
        'ordered_at' => '',
    ];
    public array $orderPackagesForm = [];
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
        'sortField' => ['except' => 'source_ordered_at_metric'],
        'sortDirection' => ['except' => 'desc'],
    ];

    public function mount(): void
    {
        $this->sortField = $this->normalizeSortField($this->sortField);
        $this->sortDirection = $this->normalizeSortDirection($this->sortDirection);

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
        $this->perPage = app(MpSettingsService::class)->getOrdersPerPage();

        $defaultDays = app(MpSettingsService::class)->getOrdersDefaultDateRangeDays();
        if ($this->dateFrom === '' && $this->dateTo === '' && $defaultDays > 0) {
            $this->dateFrom = now()->subDays($defaultDays)->toDateString();
            $this->dateTo = now()->toDateString();
        }

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
            'sortField',
            'sortDirection',
        ], true)) {
            if ($property === 'searchProduct') {
                $this->searchBarcode = $this->searchProduct;
            }

            if ($property === 'sortField') {
                $this->sortField = $this->normalizeSortField($this->sortField);
            }

            if ($property === 'sortDirection') {
                $this->sortDirection = $this->normalizeSortDirection($this->sortDirection);
            }

            if ($property === 'storeFilter') {
                $this->syncLegacyProjectionStoreFromFilter();
            }

            $this->resetPage();
        }

        if (
            $this->showEditOrderModal
            && str_starts_with($property, 'orderPackagesForm.')
            && preg_match('/^orderPackagesForm\.\d+\.(package_status|cargo_tracking_number|shipped_at|delivered_at)$/', $property)
        ) {
            $this->syncEditOrderDerivedStatuses();
        }
    }

    public function updatedPerPage(): void
    {
        $this->perPage = app(MpSettingsService::class)->normalizePerPage($this->perPage, 20);
        app(MpSettingsService::class)->set('ui.orders_per_page', $this->perPage);
        $this->resetPage();
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
            $this->sortDirection = $this->defaultSortDirectionFor($field);
        }

        $this->resetPage();
    }

    public function applySortPreset(string $preset): void
    {
        $definition = static::$sortPresets[$preset] ?? null;

        if (!$definition) {
            return;
        }

        $this->sortField = $this->normalizeSortField((string) $definition['field']);
        $this->sortDirection = $this->normalizeSortDirection((string) $definition['direction']);
        $this->resetPage();
    }

    public function currentSortPreset(): string
    {
        $sortField = $this->normalizeSortField($this->sortField);
        $sortDirection = $this->normalizeSortDirection($this->sortDirection);

        foreach (static::$sortPresets as $key => $definition) {
            if (
                ($definition['field'] ?? null) === $sortField
                && ($definition['direction'] ?? null) === $sortDirection
            ) {
                return $key;
            }
        }

        return 'custom';
    }

    public function currentSortLabel(): string
    {
        $preset = $this->currentSortPreset();

        if ($preset !== 'custom') {
            return (string) static::$sortPresets[$preset]['label'];
        }

        return 'Tablo kolonuna göre özel sıralama';
    }

    public function displayOrderDate(ChannelOrder $order): ?Carbon
    {
        return $this->parseMetricDate($order->getAttribute('source_ordered_at_metric'))
            ?: $this->rawPayloadOrderDate($order, ['orderDate', 'createdDate', 'createDate', 'orderCreatedDate'])
            ?: $this->rawPayloadOrderHistoryDate($order, 'Created')
            ?: $this->parseMetricDate($order->ordered_at);
    }

    public function displayCargoDueDate(ChannelOrder $order): ?Carbon
    {
        return $this->parseMetricDate($order->getAttribute('cargo_due_at_metric'))
            ?: $this->rawPayloadDate($order, [
                'estimatedShippingDate',
                'cargoDueDate',
                'cargo_due_at',
                'latestShipDate',
                'shipment.latestShipDate',
                'shipping.latestShipDate',
            ])
            ?: $this->rawPayloadHistoryDate($order, 'Picking')
            ?: $this->rawPayloadDate($order, ['agreedDeliveryDate']);
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

        if (
            $this->bulkPackageActionType !== ''
            && !array_key_exists($this->bulkPackageActionType, $this->bulkPackageActionOptions())
        ) {
            $this->bulkPackageActionType = '';
        }
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
                'packages:id,store_id,channel_order_id,package_number,package_status,cargo_company,cargo_tracking_number,cargo_barcode,cargo_desi,shipment_provider,shipped_at,delivered_at',
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
            'customer_note' => (string) ($order->customer_note ?? ''),
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
        $this->orderPackagesForm = $order->packages
            ->sortBy('id')
            ->map(function (ChannelOrderPackage $package): array {
                $resolvedCargoCompany = $this->displayCargoCompany(
                    $package->cargo_company,
                    $package->shipment_provider,
                );
                $shipmentProvider = (string) ($package->shipment_provider ?? '');

                if (
                    filled($resolvedCargoCompany)
                    && $this->cargoCompanyDefinitionFromValue($shipmentProvider) !== null
                    && $this->normalizeCargoCompany($shipmentProvider) === $resolvedCargoCompany
                ) {
                    $shipmentProvider = '';
                }

                return [
                    'id' => $package->id,
                    'package_number' => (string) ($package->package_number ?? ''),
                    'package_status' => $this->canonicalStatusValue($package->package_status, 'New'),
                    'cargo_company' => (string) ($resolvedCargoCompany ?? ''),
                    'shipment_provider' => $shipmentProvider,
                    'cargo_tracking_number' => (string) ($package->cargo_tracking_number ?? ''),
                    'cargo_barcode' => (string) ($package->cargo_barcode ?? ''),
                    'cargo_desi' => $package->cargo_desi !== null ? number_format((float) $package->cargo_desi, 2, '.', '') : '',
                    'shipped_at' => $package->shipped_at?->format('Y-m-d\TH:i') ?? '',
                    'delivered_at' => $package->delivered_at?->format('Y-m-d\TH:i') ?? '',
                ];
            })
            ->values()
            ->all();
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
        $this->syncEditOrderDerivedStatuses();
        $this->showEditOrderModal = true;
        $this->resetErrorBag();
    }

    public function closeEditOrderModal(): void
    {
        $this->showEditOrderModal = false;
        $this->editingOrderId = null;
        $this->orderForm = $this->emptyOrderForm();
        $this->orderPackagesForm = [];
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
            'orderForm.customer_note' => ['nullable', 'string', 'max:2000'],
            'orderForm.commercial_type' => ['nullable', 'string', 'max:50'],
            'orderForm.billing_name' => ['nullable', 'string', 'max:150'],
            'orderForm.billing_tax_number' => ['nullable', 'string', 'max:32'],
            'orderForm.shipment_city' => ['nullable', 'string', 'max:120'],
            'orderForm.shipment_district' => ['nullable', 'string', 'max:120'],
            'orderForm.ordered_at' => ['nullable', 'date'],
            'orderPackagesForm' => ['array'],
            'orderPackagesForm.*.id' => ['required', 'integer'],
            'orderPackagesForm.*.package_number' => ['nullable', 'string', 'max:120'],
            'orderPackagesForm.*.package_status' => ['nullable', 'string', 'max:50'],
            'orderPackagesForm.*.cargo_company' => ['nullable', 'string', 'max:120'],
            'orderPackagesForm.*.shipment_provider' => ['nullable', 'string', 'max:120'],
            'orderPackagesForm.*.cargo_tracking_number' => ['nullable', 'string', 'max:120'],
            'orderPackagesForm.*.cargo_barcode' => ['nullable', 'string', 'max:120'],
            'orderPackagesForm.*.cargo_desi' => ['nullable', 'numeric', 'min:0'],
            'orderPackagesForm.*.shipped_at' => ['nullable', 'date'],
            'orderPackagesForm.*.delivered_at' => ['nullable', 'date'],
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
            ->with([
                'packages:id,channel_order_id',
                'items:id,channel_order_id',
            ])
            ->whereKey($this->editingOrderId)
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', auth()->id()))
            ->firstOrFail();

        $form = $validated['orderForm'];
        $packages = collect($validated['orderPackagesForm'] ?? []);
        $items = collect($validated['orderItemsForm'] ?? []);
        $orderPackages = $order->packages->keyBy('id');
        $orderItems = $order->items->keyBy('id');

        DB::transaction(function () use ($order, $form, $packages, $items, $orderPackages, $orderItems): void {
                $order->fill([
                    'order_number' => trim((string) $form['order_number']),
                    'order_status' => $this->nullableTrim($form['order_status'] ?? null) ?? 'New',
                    'customer_name' => $this->nullableTrim($form['customer_name'] ?? null),
                    'customer_email' => $this->nullableTrim($form['customer_email'] ?? null),
                    'customer_phone' => $this->nullableTrim($form['customer_phone'] ?? null),
                    'customer_note' => $this->nullableTrim($form['customer_note'] ?? null),
                    'commercial_type' => $this->nullableTrim($form['commercial_type'] ?? null),
                    'billing_name' => $this->nullableTrim($form['billing_name'] ?? null),
                    'billing_tax_number' => $this->nullableTrim($form['billing_tax_number'] ?? null),
                    'shipment_city' => $this->nullableTrim($form['shipment_city'] ?? null),
                    'shipment_district' => $this->nullableTrim($form['shipment_district'] ?? null),
                    'ordered_at' => filled($form['ordered_at'] ?? null) ? Carbon::parse((string) $form['ordered_at']) : null,
                ]);
                $order->save();

                $derivedOrderStatus = null;

                foreach ($packages as $packageForm) {
                    /** @var ChannelOrderPackage|null $package */
                    $package = $orderPackages->get((int) $packageForm['id']);

                    if (!$package) {
                        continue;
                    }

                    $cargoCompany = $this->resolveManualCargoCompany(
                        $packageForm['cargo_company'] ?? null,
                        $packageForm['shipment_provider'] ?? null,
                    );
                    $shipmentProvider = $this->normalizeShipmentProvider(
                        $packageForm['shipment_provider'] ?? null,
                        $cargoCompany,
                    );
                    $trackingNumber = $this->nullableTrim($packageForm['cargo_tracking_number'] ?? null);
                    $packageStatus = $this->resolveManualPackageStatus(
                        $packageForm['package_status'] ?? null,
                        $trackingNumber,
                        $packageForm['shipped_at'] ?? null,
                        $packageForm['delivered_at'] ?? null,
                    );

                    $package->fill([
                        'package_number' => $this->nullableTrim($packageForm['package_number'] ?? null),
                        'package_status' => $packageStatus,
                        'cargo_company' => $cargoCompany,
                        'shipment_provider' => $shipmentProvider,
                        'cargo_tracking_number' => $trackingNumber,
                        'cargo_barcode' => $this->nullableTrim($packageForm['cargo_barcode'] ?? null),
                        'cargo_desi' => $this->nullableDecimal($packageForm['cargo_desi'] ?? null),
                        'shipped_at' => filled($packageForm['shipped_at'] ?? null) ? Carbon::parse((string) $packageForm['shipped_at']) : null,
                        'delivered_at' => filled($packageForm['delivered_at'] ?? null) ? Carbon::parse((string) $packageForm['delivered_at']) : null,
                    ]);
                    $package->save();

                    $packageStatusKey = $this->normalizeStatusKey($packageStatus);

                    if (in_array($packageStatusKey, ['delivered', 'completed'], true)) {
                        $derivedOrderStatus = 'Delivered';
                        continue;
                    }

                    if ($packageStatusKey === 'shipped' && $derivedOrderStatus === null) {
                        $derivedOrderStatus = 'Shipped';
                    }

                    if ($packageStatusKey === 'shipping' && $derivedOrderStatus === null) {
                        $derivedOrderStatus = 'Shipping';
                    }
                }

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

                $currentOrderStatusKey = $this->normalizeStatusKey($order->order_status);

                if (
                    $derivedOrderStatus !== null
                    && !in_array($currentOrderStatusKey, ['delivered', 'completed', 'cancelled', 'returned', 'rejected'], true)
                ) {
                    $order->forceFill([
                        'order_status' => $derivedOrderStatus,
                    ])->save();
                }
        });

        $this->actionMessage = 'Sipariş, paket ve ürün satırları güncellendi.';
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

        $defaultDays = app(MpSettingsService::class)->getOrdersDefaultDateRangeDays();
        if ($defaultDays > 0) {
            $this->dateFrom = now()->subDays($defaultDays)->toDateString();
            $this->dateTo = now()->toDateString();
        }

        $this->sortField = 'source_ordered_at_metric';
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

        if (!$this->packageSupportsAction($package, $actionType)) {
            $marketplace = (string) ($package->store?->marketplace ?? $package->order?->store?->marketplace ?? '');
            $this->actionMessage = $this->humanMarketplace($marketplace)
                . ' için "' . $this->orderActionLabel($actionType)
                . '" paket aksiyonu desteklenmiyor. Paket verisi takip edilir; bu işlem kanal panelinden yapılmalıdır.';
            $this->actionMessageTone = 'info';

            return;
        }

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
        $orders = $this->applyOrderSorting($this->buildChannelOrdersQuery()
            ->with([
                'store:id,legal_entity_id,marketplace,store_name,store_code,seller_id,status,is_active',
                'legalEntity:id,name,tax_number',
                'packages:id,store_id,channel_order_id,external_package_id,package_number,package_status,cargo_company,cargo_tracking_number,cargo_barcode,cargo_desi,shipment_provider,shipped_at,delivered_at,label_printed_at,label_print_count,last_synced_at,raw_payload',
                'items:id,store_id,channel_order_id,channel_order_package_id,channel_listing_id,mp_product_id,stock_code,barcode,product_name,quantity,unit_price,gross_amount,discount_amount,marketplace_discount_amount,billable_amount,commission_rate,vat_rate,line_status,is_matched,match_source',
                'items.product:id,commission_rate',
                'items.listing:id,store_id,commission_rate',
                'items.listing.store:id,marketplace',
                'profitSnapshots' => fn ($snapshotQuery) => $snapshotQuery
                    ->whereNull('channel_order_item_id')
                    ->orderByDesc('calculated_at'),
            ])
        )->get();
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
                'Kârlılık %',
                'Finans Event',
            ], ';');

            foreach ($orders as $order) {
                $snapshot = $order->profitSnapshots->first();
                $packageNumbers = $order->packages->pluck('package_number')->filter()->implode(', ');
                $trackingNumbers = $order->packages->pluck('cargo_tracking_number')->filter()->implode(', ');
                $displayStatus = $this->resolveOrderDisplayStatus($order);

                foreach ($order->items as $item) {
                    $snapshotProfit = $snapshot?->profit_state === 'confirmed'
                        ? (float) ($snapshot?->confirmed_profit ?? 0)
                        : (float) ($snapshot?->estimated_profit ?? 0);
                    $snapshotProductCost = ProfitabilityMetric::productCost(
                        (float) ($snapshot?->cogs_cost ?? 0),
                        (float) ($snapshot?->packaging_cost ?? 0),
                    );

                    fputcsv($file, [
                        $this->safeExcelString($order->marketplace_alias ?? $order->store?->marketplace),
                        $this->safeExcelString($order->store_name_alias ?? $order->store?->store_name),
                        $this->safeExcelString($order->legal_entity_name_alias ?? $order->legalEntity?->name),
                        $this->safeExcelString($order->order_number),
                        $this->safeExcelString($order->external_order_id),
                        $this->displayOrderDate($order)?->format('d/m/Y H:i'),
                        $this->safeExcelString($displayStatus['label']),
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
                        $this->effectiveCommissionRateForOrderItem($item),
                        $this->safeExcelString($snapshot?->profit_state),
                        (float) ($snapshot?->estimated_profit ?? 0),
                        (float) ($snapshot?->confirmed_profit ?? 0),
                        ProfitabilityMetric::profitPercent($snapshotProfit, $snapshotProductCost),
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
                'Takip No', 'Sipariş Tarihi', 'Durum', 'Ödeme: Net Kesin Ödeme', 'Muhasebe: Net Kâr',
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

    protected function applyOrderSorting(Builder $query): Builder
    {
        $field = $this->normalizeSortField($this->sortField);
        $direction = $this->normalizeSortDirection($this->sortDirection);

        $query->reorder();

        if ($field === 'source_ordered_at_metric') {
            $orderDateSql = $this->channelOrderDateMetricSql();
            $query
                ->orderByRaw("CASE WHEN {$orderDateSql} IS NULL THEN 1 ELSE 0 END")
                ->orderByRaw("{$orderDateSql} {$direction}");
        } elseif ($field === 'cargo_due_at_metric') {
            $query
                ->orderByRaw('CASE WHEN package_agg.cargo_due_at_metric IS NULL THEN 1 ELSE 0 END')
                ->orderBy('package_agg.cargo_due_at_metric', $direction);
        } else {
            $query->orderBy($field, $direction);
        }

        return $query
            ->orderByDesc('channel_orders.ordered_at')
            ->orderByDesc('channel_orders.id');
    }

    protected function normalizeSortField(string $field): string
    {
        if ($field === 'ordered_at') {
            return 'source_ordered_at_metric';
        }

        $allowedFields = array_values(array_unique(array_merge(
            array_values(static::$sortableColumns),
            array_map(fn (array $definition) => (string) $definition['field'], static::$sortPresets),
            ['order_number']
        )));

        return in_array($field, $allowedFields, true) ? $field : 'source_ordered_at_metric';
    }

    protected function normalizeSortDirection(string $direction): string
    {
        return $direction === 'asc' ? 'asc' : 'desc';
    }

    protected function parseMetricDate(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (!filled($value)) {
            return null;
        }

        try {
            if (is_numeric($value)) {
                $timestamp = (int) $value;

                if (abs($timestamp) > 9999999999) {
                    $timestamp = (int) floor($timestamp / 1000);
                }

                return Carbon::createFromTimestampUTC($timestamp);
            }

            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function parseOrderMetricDate(ChannelOrder $order, mixed $value): ?Carbon
    {
        if ($this->isTrendyolOrder($order) && is_numeric($value)) {
            $timestamp = (int) $value;

            if (abs($timestamp) > 9999999999) {
                $timestamp = (int) floor($timestamp / 1000);
            }

            return Carbon::createFromTimestampUTC($timestamp)
                ->subSeconds(app(MpSettingsService::class)->getTrendyolTimestampOffsetSeconds())
                ->setTimezone(config('app.timezone', 'Europe/Istanbul'));
        }

        return $this->parseMetricDate($value);
    }

    protected function isTrendyolOrder(ChannelOrder $order): bool
    {
        $marketplace = $order->getAttribute('marketplace_alias')
            ?: $order->store?->marketplace;

        return Str::lower((string) $marketplace) === 'trendyol';
    }

    /**
     * @param  array<int, string>  $paths
     */
    protected function rawPayloadOrderDate(ChannelOrder $order, array $paths): ?Carbon
    {
        $payload = $order->raw_payload;

        if (!is_array($payload)) {
            return null;
        }

        foreach ($paths as $path) {
            $date = $this->parseOrderMetricDate($order, data_get($payload, $path));

            if ($date) {
                return $date;
            }
        }

        return null;
    }

    protected function rawPayloadOrderHistoryDate(ChannelOrder $order, string $status): ?Carbon
    {
        $payload = $order->raw_payload;

        if (!is_array($payload)) {
            return null;
        }

        $status = Str::lower($status);

        foreach (Arr::wrap(data_get($payload, 'packageHistories', [])) as $history) {
            if (!is_array($history)) {
                continue;
            }

            if (Str::lower(trim((string) data_get($history, 'status'))) !== $status) {
                continue;
            }

            return $this->parseOrderMetricDate($order, data_get($history, 'createdDate'));
        }

        return null;
    }

    /**
     * @param  array<int, string>  $paths
     */
    protected function rawPayloadDate(ChannelOrder $order, array $paths): ?Carbon
    {
        $payload = $order->raw_payload;

        if (!is_array($payload)) {
            return null;
        }

        foreach ($paths as $path) {
            $date = $this->parseMetricDate(data_get($payload, $path));

            if ($date) {
                return $date;
            }
        }

        return null;
    }

    protected function rawPayloadHistoryDate(ChannelOrder $order, string $status): ?Carbon
    {
        $payload = $order->raw_payload;

        if (!is_array($payload)) {
            return null;
        }

        $status = Str::lower($status);

        foreach (Arr::wrap(data_get($payload, 'packageHistories', [])) as $history) {
            if (!is_array($history)) {
                continue;
            }

            if (Str::lower(trim((string) data_get($history, 'status'))) !== $status) {
                continue;
            }

            return $this->parseMetricDate(data_get($history, 'createdDate'));
        }

        return null;
    }

    protected function defaultSortDirectionFor(string $field): string
    {
        return match ($field) {
            'order_number',
            'store_name_alias',
            'customer_name',
            'display_status_sort_key',
            'cargo_due_at_metric' => 'asc',
            default => 'desc',
        };
    }

    protected function channelOrderDateMetricSql(): string
    {
        $marketplaceColumn = 'marketplace_stores.marketplace';
        $orderDate = $this->jsonDateValueSql('channel_orders.raw_payload', '$.orderDate', $marketplaceColumn);
        $createdDate = $this->jsonDateValueSql('channel_orders.raw_payload', '$.createdDate', $marketplaceColumn);
        $createDate = $this->jsonDateValueSql('channel_orders.raw_payload', '$.createDate', $marketplaceColumn);
        $orderCreatedDate = $this->jsonDateValueSql('channel_orders.raw_payload', '$.orderCreatedDate', $marketplaceColumn);
        $createdHistoryDate = $this->jsonHistoryDateSql('channel_orders.raw_payload', 'Created', $marketplaceColumn);

        return "COALESCE({$orderDate}, {$createdDate}, {$createDate}, {$orderCreatedDate}, {$createdHistoryDate}, channel_orders.ordered_at)";
    }

    protected function jsonHistoryDateSql(string $column, string $status, ?string $marketplaceColumn = null): string
    {
        $status = Str::lower($status);
        $expressions = [];

        for ($index = 0; $index < 8; $index++) {
            $statusSql = $this->jsonStringValueSql($column, '$.packageHistories[' . $index . '].status');
            $dateSql = $this->jsonDateValueSql($column, '$.packageHistories[' . $index . '].createdDate', $marketplaceColumn);
            $expressions[] = "CASE WHEN LOWER({$statusSql}) = '{$status}' THEN {$dateSql} END";
        }

        return 'COALESCE(' . implode(', ', $expressions) . ')';
    }

    protected function jsonDateValueSql(string $column, string $path, ?string $marketplaceColumn = null): string
    {
        $valueSql = $this->jsonStringValueSql($column, $path);
        $timestampOffsetSql = $marketplaceColumn
            ? " - CASE WHEN LOWER({$marketplaceColumn}) = 'trendyol' THEN " . app(MpSettingsService::class)->getTrendyolTimestampOffsetSeconds() . ' ELSE 0 END'
            : '';

        if (DB::connection()->getDriverName() === 'sqlite') {
            return "
                CASE
                    WHEN (
                        ({$valueSql} GLOB '[0-9]*' AND {$valueSql} NOT GLOB '*[^0-9]*')
                        OR ({$valueSql} GLOB '-[0-9]*' AND substr({$valueSql}, 2) NOT GLOB '*[^0-9]*')
                    )
                    THEN datetime(
                        (
                        CASE
                            WHEN ABS(CAST({$valueSql} AS INTEGER)) > 9999999999
                            THEN CAST({$valueSql} AS INTEGER) / 1000
                            ELSE CAST({$valueSql} AS INTEGER)
                        END{$timestampOffsetSql}
                        ),
                        'unixepoch'
                    )
                    ELSE {$valueSql}
                END
            ";
        }

        return "
            CASE
                WHEN {$valueSql} REGEXP '^-?[0-9]+$'
                THEN FROM_UNIXTIME(
                    (
                    CASE
                        WHEN ABS(CAST({$valueSql} AS SIGNED)) > 9999999999
                        THEN CAST({$valueSql} AS DECIMAL(20, 3)) / 1000
                        ELSE CAST({$valueSql} AS DECIMAL(20, 3))
                    END{$timestampOffsetSql}
                    )
                )
                ELSE {$valueSql}
            END
        ";
    }

    protected function jsonStringValueSql(string $column, string $path): string
    {
        $path = str_replace("'", "''", $path);

        if (DB::connection()->getDriverName() === 'sqlite') {
            return "NULLIF(json_extract({$column}, '{$path}'), '')";
        }

        return "NULLIF(JSON_UNQUOTE(JSON_EXTRACT({$column}, '{$path}')), '')";
    }

    protected function buildChannelOrdersQuery(): Builder
    {
        return $this->buildChannelBaseQuery()
            ->with([
                'store:id,legal_entity_id,marketplace,store_name,store_code,seller_id,status,is_active',
                'legalEntity:id,name,tax_number',
                'packages:id,store_id,channel_order_id,external_package_id,package_number,package_status,cargo_company,cargo_tracking_number,cargo_barcode,cargo_desi,shipment_provider,shipped_at,delivered_at,label_printed_at,label_print_count,last_synced_at,raw_payload',
                'items:id,store_id,channel_order_id,channel_order_package_id,channel_listing_id,mp_product_id,stock_code,barcode,product_name,quantity,unit_price,gross_amount,discount_amount,marketplace_discount_amount,billable_amount,commission_rate,vat_rate,line_status,is_matched,match_source,raw_payload',
                'items.product:id,stock_code,barcode,product_name,brand,cogs,packaging_cost,cargo_cost,commission_rate,vat_rate',
                'items.listing:id,store_id,channel_product_id,mp_product_id,listing_id,listing_status,sale_price,commission_rate,stock_quantity,last_synced_at',
                'items.listing.store:id,marketplace,store_name,seller_id',
                'items.listing.channelProduct:id,store_id,external_product_id,external_parent_id,stock_code,barcode,title,brand,category_name,raw_payload',
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
        $displayStatusSql = $this->channelDisplayStatusSql();
        $orderDateSql = $this->channelOrderDateMetricSql();
        $estimatedShippingDateSql = 'COALESCE('
            . $this->jsonDateValueSql('channel_order_packages.raw_payload', '$.estimatedShippingDate') . ', '
            . $this->jsonDateValueSql('channel_order_packages.raw_payload', '$.items[0].estimatedShippingDate') . ', '
            . $this->jsonDateValueSql('channel_order_packages.raw_payload', '$.itemList[0].estimatedShippingDate')
            . ')';
        $pickingHistoryDateSql = $this->jsonHistoryDateSql('channel_order_packages.raw_payload', 'Picking');
        $agreedDeliveryDateSql = $this->jsonDateValueSql('channel_order_packages.raw_payload', '$.agreedDeliveryDate');
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

        $latestSnapshotVersion = OrderProfitSnapshot::query()
            ->selectRaw('channel_order_id, MAX(version) as latest_version')
            ->whereNull('channel_order_item_id')
            ->groupBy('channel_order_id');

        $snapshotAggregate = OrderProfitSnapshot::query()
            ->joinSub($latestSnapshotVersion, 'latest_snapshot_version', function ($join) {
                $join->on('latest_snapshot_version.channel_order_id', '=', 'order_profit_snapshots.channel_order_id')
                    ->on('latest_snapshot_version.latest_version', '=', 'order_profit_snapshots.version');
            })
            ->select([
                'order_profit_snapshots.channel_order_id',
                'order_profit_snapshots.profit_state',
                'order_profit_snapshots.gross_revenue',
                'order_profit_snapshots.estimated_profit',
                'order_profit_snapshots.confirmed_profit',
                'order_profit_snapshots.margin_percent',
                'order_profit_snapshots.net_receivable',
                'order_profit_snapshots.commission_total',
                'order_profit_snapshots.cargo_total',
                'order_profit_snapshots.service_fee_total',
                'order_profit_snapshots.withholding_total',
                'order_profit_snapshots.packaging_cost',
                'order_profit_snapshots.own_cargo_cost',
                'order_profit_snapshots.cogs_cost',
                'order_profit_snapshots.return_effect',
                'order_profit_snapshots.calculated_at',
            ])
            ->whereNull('order_profit_snapshots.channel_order_item_id');

        $packageAggregate = ChannelOrderPackage::query()
            ->selectRaw("
                channel_order_id,
                MIN(COALESCE({$estimatedShippingDateSql}, {$pickingHistoryDateSql}, {$agreedDeliveryDateSql})) as cargo_due_at_metric
            ")
            ->groupBy('channel_order_id');

        $query = ChannelOrder::query()
            ->select([
                'channel_orders.*',
                'marketplace_stores.marketplace as marketplace_alias',
                'marketplace_stores.store_name as store_name_alias',
                'legal_entities.name as legal_entity_name_alias',
                DB::raw("{$displayStatusSql} as display_status_sort_key"),
                DB::raw("{$orderDateSql} as source_ordered_at_metric"),
                DB::raw('package_agg.cargo_due_at_metric as cargo_due_at_metric'),
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
            ->leftJoinSub($packageAggregate, 'package_agg', function ($join) {
                $join->on('package_agg.channel_order_id', '=', 'channel_orders.id');
            })
            ->where('marketplace_stores.user_id', auth()->id());

        if ($this->search !== '') {
            $searchTerm = trim($this->search);
            $query->where(function (Builder $builder) use ($searchTerm) {
                $builder->where('channel_orders.order_number', 'like', '%' . $searchTerm . '%')
                    ->orWhere('channel_orders.external_order_id', 'like', '%' . $searchTerm . '%')
                    ->orWhere('channel_orders.customer_name', 'like', '%' . $searchTerm . '%')
                    ->orWhere('channel_orders.customer_phone', 'like', '%' . $searchTerm . '%')
                    ->orWhere('channel_orders.customer_email', 'like', '%' . $searchTerm . '%')
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

        $this->applyChannelStatusFilter($query, $displayStatusSql);

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
            $query->whereDate(DB::raw($orderDateSql), '>=', $this->dateFrom);
        }

        if ($this->dateTo !== '') {
            $query->whereDate(DB::raw($orderDateSql), '<=', $this->dateTo);
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
                    ->orWhere('package_number', 'like', '%' . $this->search . '%')
                    ->orWhere('customer_name', 'like', '%' . $this->search . '%')
                    ->orWhere('customer_phone', 'like', '%' . $this->search . '%')
                    ->orWhere('email', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->searchCustomer !== '') {
            $query->where(function (Builder $builder) {
                $builder->where('customer_name', 'like', '%' . $this->searchCustomer . '%')
                    ->orWhere('customer_phone', 'like', '%' . $this->searchCustomer . '%')
                    ->orWhere('email', 'like', '%' . $this->searchCustomer . '%');
            });
        }

        $this->applyTextStatusFilter($query, 'status');

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
                ? 'Legacy finans backlogu mağaza bazında görünüyor'
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
        return $this->filterStatusOptions();
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
        $nextStoreId = $this->nullableTrim($this->storeFilter);

        if (!filled($nextStoreId)) {
            $this->legacyProjectionStoreId = '';
            $this->legacyProjectionResult = [];

            return;
        }

        if ($this->legacyProjectionStoreId !== $nextStoreId) {
            $this->legacyProjectionStoreId = $nextStoreId;
        }

        $this->previewLegacyFinancials();
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

    /**
     * @return array<string, string>
     */
    public function orderStatusOptions(): array
    {
        return $this->editableStatusOptions();
    }

    /**
     * @return array<string, string>
     */
    public function packageStatusOptions(): array
    {
        return $this->editableStatusOptions();
    }

    /**
     * @return array<string, string>
     */
    public function cargoCompanyOptions(): array
    {
        return collect($this->cargoCompanyDefinitions())
            ->mapWithKeys(fn (array $definition) => [$definition['label'] => $definition['label']])
            ->all();
    }

    public function displayCargoCompany(
        ?string $cargoCompany,
        ?string $shipmentProvider = null,
        ?string $fallbackCargoCompany = null,
    ): ?string {
        $resolved = $this->resolveManualCargoCompany($cargoCompany, $shipmentProvider);

        if (filled($resolved)) {
            return $resolved;
        }

        return $this->normalizeCargoCompany($fallbackCargoCompany);
    }

    public function trackingUrl(?string $cargoCompany, ?string $trackingNumber): ?string
    {
        $trackingNumber = $this->nullableTrim($trackingNumber);

        if (!filled($trackingNumber)) {
            return null;
        }

        $definition = $this->cargoCompanyDefinitionFromValue($cargoCompany);
        $trackingUrl = $definition['tracking_url'] ?? null;

        if (!filled($trackingUrl)) {
            return null;
        }

        return str_replace('{tracking}', rawurlencode($trackingNumber), $trackingUrl);
    }

    public function productManagerUrlForOrderItem(ChannelOrderItem $item): ?string
    {
        if ($item->product) {
            return route('mp.products', ['edit' => $item->product->id, 'tab' => 'basic']);
        }

        $stockCode = $this->nullableTrim($item->stock_code);

        if (filled($stockCode)) {
            return route('mp.products', ['search' => $stockCode]);
        }

        $barcode = $this->nullableTrim($item->barcode);

        if (filled($barcode)) {
            return route('mp.products', ['search' => $barcode]);
        }

        return null;
    }

    public function marketplacePublicProductUrl(?ChannelListing $listing): ?string
    {
        if (!$listing) {
            return null;
        }

        $store = $listing->store;
        $channelProduct = $listing->channelProduct;
        $marketplace = strtolower((string) data_get($store, 'marketplace'));
        $rawPayload = $channelProduct?->raw_payload ?? [];

        if ($marketplace === 'trendyol') {
            $contentId = data_get($rawPayload, 'content.contentId')
                ?: data_get($channelProduct, 'external_parent_id');
            $merchantId = data_get($store, 'seller_id');

            if ($contentId && $merchantId) {
                $brandSlug = Str::slug((string) (data_get($channelProduct, 'brand') ?: 'urun'));
                $titleSlug = Str::slug((string) (data_get($channelProduct, 'title') ?: 'urun'));

                return "https://www.trendyol.com/{$brandSlug}/{$titleSlug}-p-{$contentId}?boutiqueId=61&merchantId={$merchantId}&filterOverPriceListings=false&sav=true";
            }
        }

        if ($marketplace === 'pazarama') {
            $productCode = (string) (
                data_get($rawPayload, 'code')
                ?: data_get($channelProduct, 'external_product_id')
                ?: data_get($listing, 'listing_id')
            );

            if ($productCode !== '') {
                $titleSlug = Str::slug((string) (
                    data_get($channelProduct, 'title')
                    ?: data_get($rawPayload, 'displayName')
                    ?: data_get($rawPayload, 'name')
                    ?: 'urun'
                ));

                return "https://www.pazarama.com/{$titleSlug}-p-{$productCode}";
            }
        }

        if ($marketplace === 'n11') {
            $productId = (string) (
                data_get($listing, 'listing_id')
                ?: data_get($channelProduct, 'external_product_id')
            );

            if ($productId !== '') {
                return 'https://urun.n11.com/prapazar-P' . ltrim($productId, 'Pp');
            }
        }

        if ($marketplace === 'hepsiburada') {
            $sku = collect([
                data_get($listing, 'listing_id'),
                data_get($rawPayload, 'hepsiburadaSku'),
                data_get($rawPayload, 'hbSku'),
                data_get($rawPayload, 'sku'),
                data_get($channelProduct, 'external_product_id'),
            ])
                ->map(fn ($value) => strtoupper(trim((string) $value)))
                ->first(fn ($value) => str_starts_with($value, 'HB'));

            if (is_string($sku) && $sku !== '') {
                $titleSlug = Str::slug((string) (
                    data_get($channelProduct, 'title')
                    ?: data_get($rawPayload, 'productName')
                    ?: data_get($rawPayload, 'name')
                    ?: 'urun'
                ));

                return "https://www.hepsiburada.com/{$titleSlug}-p-{$sku}";
            }
        }

        if ($marketplace === 'woocommerce') {
            return $this->wooCommercePublicProductUrl($listing, $channelProduct, $rawPayload);
        }

        $directUrl = collect([
            data_get($rawPayload, 'variant.productUrl'),
            data_get($rawPayload, 'content.variants.0.productUrl'),
            data_get($rawPayload, 'product.url'),
            data_get($rawPayload, 'merchantVariantUrl'),
            data_get($rawPayload, 'link'),
            data_get($rawPayload, 'url'),
            data_get($rawPayload, 'productUrl'),
            data_get($rawPayload, 'product_url'),
            data_get($rawPayload, 'productLink'),
            data_get($rawPayload, 'productPageUrl'),
            data_get($rawPayload, 'webUrl'),
            data_get($rawPayload, 'permalink'),
        ])->first(fn ($url) => is_string($url) && trim($url) !== '');

        if (!is_string($directUrl) || trim($directUrl) === '') {
            if ($marketplace === 'koctas') {
                return $this->koctasPublicProductUrl($listing, $channelProduct, $rawPayload);
            }

            return null;
        }

        if (str_starts_with($directUrl, 'http')) {
            return $directUrl;
        }

        return match ($marketplace) {
            'ciceksepeti' => 'https://www.ciceksepeti.com/' . ltrim($directUrl, '/'),
            'hepsiburada' => 'https://www.hepsiburada.com/' . ltrim($directUrl, '/'),
            'koctas' => 'https://www.koctas.com.tr/' . ltrim($directUrl, '/'),
            'pazarama' => 'https://www.pazarama.com/' . ltrim($directUrl, '/'),
            default => null,
        };
    }

    public function marketplacePublicProductUrlForOrderItem(ChannelOrderItem $item, ?ChannelOrder $order = null): ?string
    {
        $listingUrl = $this->marketplacePublicProductUrl($item->listing);

        if ($listingUrl !== null) {
            return $listingUrl;
        }

        $marketplace = MarketplaceProviderRegistry::normalize((string) (
            $item->listing?->store?->marketplace
            ?: $order?->store?->marketplace
            ?: $item->store?->marketplace
        ));
        $rawPayload = $item->raw_payload ?? [];
        $directUrl = collect([
            data_get($rawPayload, 'variant.productUrl'),
            data_get($rawPayload, 'product.url'),
            data_get($rawPayload, 'merchantVariantUrl'),
            data_get($rawPayload, 'link'),
            data_get($rawPayload, 'url'),
            data_get($rawPayload, 'productUrl'),
            data_get($rawPayload, 'product_url'),
            data_get($rawPayload, 'productLink'),
            data_get($rawPayload, 'productPageUrl'),
            data_get($rawPayload, 'webUrl'),
            data_get($rawPayload, 'permalink'),
        ])->first(fn ($url) => is_string($url) && trim($url) !== '');

        if (is_string($directUrl) && trim($directUrl) !== '') {
            return str_starts_with($directUrl, 'http')
                ? $directUrl
                : match ($marketplace) {
                    'ciceksepeti' => 'https://www.ciceksepeti.com/' . ltrim($directUrl, '/'),
                    'hepsiburada' => 'https://www.hepsiburada.com/' . ltrim($directUrl, '/'),
                    'koctas' => 'https://www.koctas.com.tr/' . ltrim($directUrl, '/'),
                    'pazarama' => 'https://www.pazarama.com/' . ltrim($directUrl, '/'),
                    default => null,
                };
        }

        if ($marketplace === 'woocommerce') {
            $baseUrl = $this->wooCommerceStorefrontBaseUrl($order?->store ?: $item->store);
            $title = trim((string) (
                data_get($rawPayload, 'parent.name')
                ?: data_get($rawPayload, 'name')
                ?: $item->product_name
                ?: $item->product?->product_name
            ));

            if ($baseUrl !== null && $title !== '') {
                $path = trim((string) config('marketplace.woocommerce.public_product_path', 'magaza'), '/');

                return $baseUrl . '/' . ($path !== '' ? $path . '/' : '') . $this->wooCommerceProductSlug($title) . '/';
            }
        }

        if ($marketplace !== 'koctas') {
            return null;
        }

        $title = trim((string) (
            $item->product_name
            ?: $item->product?->product_name
            ?: data_get($rawPayload, 'product_title')
            ?: data_get($rawPayload, 'title')
            ?: data_get($rawPayload, 'description')
        ));
        $barcode = trim((string) ($item->barcode ?? ''));
        $productCode = collect([
            data_get($rawPayload, 'product_code'),
            data_get($rawPayload, 'productCode'),
            data_get($rawPayload, 'product.code'),
            data_get($rawPayload, 'product.id'),
            data_get($rawPayload, 'product_sku'),
            data_get($rawPayload, 'productSku'),
            data_get($rawPayload, 'product_id'),
            data_get($rawPayload, 'productId'),
            data_get($rawPayload, 'code'),
        ])
            ->map(fn ($value) => preg_replace('/\s+/', '', trim((string) $value)) ?: '')
            ->first(fn ($value) => ctype_digit($value)
                && strlen($value) >= 7
                && strlen($value) <= 11
                && $value !== $barcode);

        if ($productCode) {
            $url = 'https://www.koctas.com.tr/' . Str::slug($title ?: 'urun') . '/p/' . $productCode;
            $shopId = trim((string) ($order?->store?->seller_id ?? $item->listing?->store?->seller_id ?? $item->store?->seller_id ?? ''));

            return $shopId !== '' && ctype_digit($shopId)
                ? $url . '?shop=' . $shopId
                : $url;
        }

        $searchTerm = collect([
            $title,
            $item->stock_code,
            $item->barcode,
        ])
            ->map(fn ($value) => trim((string) $value))
            ->first(fn ($value) => $value !== '');

        return $searchTerm
            ? 'https://www.koctas.com.tr/search?text=' . rawurlencode($searchTerm)
            : null;
    }

    protected function wooCommercePublicProductUrl(ChannelListing $listing, mixed $channelProduct, array $rawPayload): ?string
    {
        $directUrl = collect([
            data_get($rawPayload, 'permalink'),
            data_get($rawPayload, 'parent.permalink'),
            data_get($rawPayload, 'product.url'),
            data_get($rawPayload, 'productUrl'),
            data_get($rawPayload, 'product_url'),
            data_get($rawPayload, 'url'),
            data_get($rawPayload, 'link'),
        ])->first(fn ($url) => is_string($url) && trim($url) !== '');

        if (is_string($directUrl) && trim($directUrl) !== '') {
            return str_starts_with($directUrl, 'http')
                ? $directUrl
                : $this->wooCommerceAbsoluteUrl($listing->store, $directUrl);
        }

        $baseUrl = $this->wooCommerceStorefrontBaseUrl($listing->store);

        if ($baseUrl === null) {
            return null;
        }

        $title = trim((string) (
            data_get($rawPayload, 'parent.name')
            ?: data_get($rawPayload, 'name')
            ?: data_get($channelProduct, 'title')
        ));

        if ($title === '') {
            return null;
        }

        $path = trim((string) config('marketplace.woocommerce.public_product_path', 'magaza'), '/');

        return $baseUrl . '/' . ($path !== '' ? $path . '/' : '') . $this->wooCommerceProductSlug($title) . '/';
    }

    protected function wooCommerceProductSlug(string $title): string
    {
        return Str::slug(str_replace(['/', '\\'], ' ', $title));
    }

    protected function wooCommerceAbsoluteUrl(?MarketplaceStore $store, string $path): ?string
    {
        $baseUrl = $this->wooCommerceStorefrontBaseUrl($store);

        return $baseUrl ? $baseUrl . '/' . ltrim($path, '/') : null;
    }

    protected function wooCommerceStorefrontBaseUrl(?MarketplaceStore $store): ?string
    {
        $credentials = $store?->connection?->credentials_encrypted ?? [];
        $sellerId = trim((string) ($store?->seller_id ?? ''));
        $legacyStoreUrl = filter_var($sellerId, FILTER_VALIDATE_URL) ? $sellerId : '';

        $baseUrl = collect([
            data_get($credentials, 'store_url'),
            $store?->connection?->api_base_url,
            data_get($store, 'store_url'),
            $legacyStoreUrl,
            config('marketplace.woocommerce.base_url'),
        ])
            ->map(fn ($value) => trim((string) $value))
            ->first(fn ($value) => $value !== '');

        if (!is_string($baseUrl) || $baseUrl === '') {
            return null;
        }

        if (Str::contains($baseUrl, '/wp-json/')) {
            $baseUrl = Str::before($baseUrl, '/wp-json/');
        }

        return rtrim($baseUrl, '/');
    }

    protected function koctasPublicProductUrl(ChannelListing $listing, mixed $channelProduct, array $rawPayload): ?string
    {
        $title = trim((string) (
            data_get($channelProduct, 'title')
            ?: data_get($rawPayload, 'product_title')
            ?: data_get($rawPayload, 'title')
            ?: data_get($rawPayload, 'description')
        ));

        $productCode = $this->koctasProductCode($listing, $channelProduct, $rawPayload);

        if ($productCode !== null) {
            $url = 'https://www.koctas.com.tr/' . Str::slug($title ?: 'urun') . '/p/' . $productCode;
            $shopId = trim((string) data_get($listing->store, 'seller_id'));

            return $shopId !== '' && ctype_digit($shopId)
                ? $url . '?shop=' . $shopId
                : $url;
        }

        $searchTerm = collect([
            $title,
            data_get($channelProduct, 'stock_code'),
            data_get($listing, 'listing_id'),
        ])
            ->map(fn ($value) => trim((string) $value))
            ->first(fn ($value) => $value !== '');

        return $searchTerm
            ? 'https://www.koctas.com.tr/search?text=' . rawurlencode($searchTerm)
            : null;
    }

    protected function koctasProductCode(ChannelListing $listing, mixed $channelProduct, array $rawPayload): ?string
    {
        $barcode = trim((string) data_get($channelProduct, 'barcode'));

        return collect([
            data_get($rawPayload, 'product_code'),
            data_get($rawPayload, 'productCode'),
            data_get($rawPayload, 'product.code'),
            data_get($rawPayload, 'product.id'),
            data_get($rawPayload, 'product_sku'),
            data_get($rawPayload, 'productSku'),
            data_get($rawPayload, 'product_id'),
            data_get($rawPayload, 'productId'),
            data_get($rawPayload, 'code'),
            data_get($channelProduct, 'external_product_id'),
            data_get($listing, 'listing_id'),
        ])
            ->map(fn ($value) => preg_replace('/\s+/', '', trim((string) $value)) ?: '')
            ->first(fn ($value) => ctype_digit($value)
                && strlen($value) >= 7
                && strlen($value) <= 11
                && $value !== $barcode);
    }

    protected function applyDisplayProfitMetrics(ChannelOrder $order, ?OrderProfitSnapshot $snapshot = null): OrderProfitSnapshot
    {
        $items = collect($order->items ?? [])
            ->filter(fn ($item) => $item instanceof ChannelOrderItem)
            ->values();

        $grossRevenue = round((float) ($order->gross_revenue_metric ?? $snapshot?->gross_revenue ?? 0), 2);
        if ($grossRevenue <= 0) {
            $grossRevenue = round((float) $items->sum(function (ChannelOrderItem $item) {
                return (float) ($item->billable_amount ?: $item->gross_amount ?: ((float) $item->unit_price * (int) $item->quantity));
            }), 2);
        }

        $estimatedCommission = round((float) $items->sum(function (ChannelOrderItem $item) {
            $baseAmount = (float) ($item->billable_amount ?: $item->gross_amount ?: ((float) $item->unit_price * (int) $item->quantity));
            $rate = $this->effectiveCommissionRateForOrderItem($item);

            return $baseAmount * $rate / 100;
        }), 2);

        $compositionTotals = app(ProductCompositionResolver::class)->totalsForOrderItems($items);
        $liveCogsCost = (float) $compositionTotals['cogs_cost'];
        $livePackagingCost = (float) $compositionTotals['packaging_cost'];
        $liveOwnCargoCost = (float) $compositionTotals['own_cargo_cost'];

        $snapshotCogsCost = round((float) ($snapshot?->cogs_cost ?? 0), 2);
        $snapshotPackagingCost = round((float) ($snapshot?->packaging_cost ?? 0), 2);
        $snapshotOwnCargoCost = round((float) ($snapshot?->own_cargo_cost ?? 0), 2);

        $hasLiveProductCosts = $items->contains(fn (ChannelOrderItem $item) => $item->product !== null);
        $liveCostTotal = round($liveCogsCost + $livePackagingCost + $liveOwnCargoCost, 2);
        $snapshotCostTotal = round($snapshotCogsCost + $snapshotPackagingCost + $snapshotOwnCargoCost, 2);
        $useLiveCosts = $hasLiveProductCosts && ($liveCostTotal > 0 || $snapshotCostTotal <= 0);

        $displayCogsCost = $useLiveCosts ? $liveCogsCost : $snapshotCogsCost;
        $displayPackagingCost = $useLiveCosts ? $livePackagingCost : $snapshotPackagingCost;
        $displayOwnCargoCost = $useLiveCosts ? $liveOwnCargoCost : $snapshotOwnCargoCost;

        $marketplaceCargoTotal = round((float) ($snapshot?->cargo_total ?? $order->cargo_total_metric ?? 0), 2);
        $serviceFeeTotal = round((float) ($snapshot?->service_fee_total ?? $order->service_fee_total_metric ?? 0), 2);
        $withholdingTotal = round((float) ($snapshot?->withholding_total ?? $order->withholding_total_metric ?? 0), 2);

        $hasFinancials = (int) ($order->financial_event_count ?? 0) > 0
            || ($snapshot?->profit_state === 'confirmed');
        $profitState = (string) ($order->profit_state_metric ?? ($hasFinancials ? 'confirmed' : 'estimated'));

        $displayCommissionTotal = $hasFinancials
            ? round((float) ($snapshot?->commission_total ?? $order->commission_total_metric ?? $estimatedCommission), 2)
            : $estimatedCommission;
        $estimatedNetReceivable = round(
            $grossRevenue - $displayCommissionTotal - $marketplaceCargoTotal - $serviceFeeTotal - $withholdingTotal,
            2
        );
        $netReceivable = $hasFinancials
            ? round((float) ($order->net_receivable_metric ?? $snapshot?->net_receivable ?? 0), 2)
            : $estimatedNetReceivable;

        $estimatedProfit = round($estimatedNetReceivable - $displayCogsCost - $displayPackagingCost - $displayOwnCargoCost, 2);
        $confirmedProfit = $hasFinancials
            ? round($netReceivable - $displayCogsCost - $displayPackagingCost - $displayOwnCargoCost, 2)
            : $estimatedProfit;

        $profitValue = $profitState === 'confirmed' ? $confirmedProfit : $estimatedProfit;
        $marginPercent = ProfitabilityMetric::multiplierOrZero(
            $profitValue,
            ProfitabilityMetric::productCost($displayCogsCost, $displayPackagingCost),
        );

        $resolvedSnapshot = $snapshot ?: new OrderProfitSnapshot([
            'store_id' => $order->store_id,
            'channel_order_id' => $order->id,
            'channel_order_item_id' => null,
        ]);

        $resolvedSnapshot->forceFill([
            'profit_state' => $profitState,
            'gross_revenue' => $grossRevenue,
            'net_receivable' => $netReceivable,
            'commission_total' => $displayCommissionTotal,
            'cargo_total' => $marketplaceCargoTotal,
            'service_fee_total' => $serviceFeeTotal,
            'withholding_total' => $withholdingTotal,
            'packaging_cost' => $displayPackagingCost,
            'own_cargo_cost' => $displayOwnCargoCost,
            'cogs_cost' => $displayCogsCost,
            'estimated_profit' => $estimatedProfit,
            'confirmed_profit' => $confirmedProfit,
            'margin_percent' => $marginPercent,
        ]);

        $order->setAttribute('gross_revenue_metric', $grossRevenue);
        $order->setAttribute('net_receivable_metric', $netReceivable);
        $order->setAttribute('commission_total_metric', $displayCommissionTotal);
        $order->setAttribute('cargo_total_metric', $marketplaceCargoTotal);
        $order->setAttribute('service_fee_total_metric', $serviceFeeTotal);
        $order->setAttribute('withholding_total_metric', $withholdingTotal);
        $order->setAttribute('estimated_profit_metric', $estimatedProfit);
        $order->setAttribute('confirmed_profit_metric', $confirmedProfit);
        $order->setAttribute('profit_value_metric', $profitValue);
        $order->setAttribute('margin_percent_metric', $marginPercent);
        $order->setAttribute('display_cogs_cost_metric', $displayCogsCost);
        $order->setAttribute('display_packaging_cost_metric', $displayPackagingCost);
        $order->setAttribute('display_own_cargo_cost_metric', $displayOwnCargoCost);
        $order->setAttribute('display_cargo_effect_metric', round($marketplaceCargoTotal + $displayOwnCargoCost, 2));
        $order->setAttribute('display_profit_cost_source', $useLiveCosts ? 'live' : 'snapshot');

        return $resolvedSnapshot;
    }

    public function effectiveCommissionRateForOrderItem(ChannelOrderItem $item): float
    {
        $marketplace = MarketplaceProviderRegistry::normalize((string) (
            $item->store?->marketplace
            ?: $item->listing?->store?->marketplace
            ?: ''
        ));

        if ($marketplace === 'koctas') {
            return $this->koctasCommissionRate();
        }

        foreach ([
            $item->commission_rate,
            $item->listing?->commission_rate,
            $item->product?->commission_rate,
        ] as $rate) {
            if ($rate !== null && $rate !== '') {
                return round((float) $rate, 2);
            }
        }

        return 0.0;
    }

    protected function koctasCommissionRate(): float
    {
        if (! auth()->check()) {
            return round((float) config('marketplace.koctas.commission_rate', 15), 2);
        }

        return app(MpSettingsService::class)->getProductProfitKoctasCommissionRate();
    }

    /**
     * @return array{
     *     total_packages:int,
     *     printed_packages:int,
     *     total_print_count:int,
     *     has_printed:bool,
     *     all_printed:bool,
     *     has_reprint:bool,
     *     badge_label:?string,
     *     meta_label:?string,
     *     confirm_message:?string,
     *     last_printed_at:?Carbon
     * }
     */
    public function orderLabelPrintSummary(ChannelOrder $order): array
    {
        $packages = collect($order->packages ?? [])
            ->filter(fn ($package) => $package instanceof ChannelOrderPackage)
            ->values();

        return $this->labelPrintSummaryFromPackages($packages, 'Bu siparişin');
    }

    /**
     * @return array{
     *     total_packages:int,
     *     printed_packages:int,
     *     total_print_count:int,
     *     has_printed:bool,
     *     all_printed:bool,
     *     has_reprint:bool,
     *     badge_label:?string,
     *     meta_label:?string,
     *     confirm_message:?string,
     *     last_printed_at:?Carbon
     * }
     */
    public function selectedLabelPrintSummary(): array
    {
        $selectedOrderIds = collect($this->selectedOrderIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $selectedPackageIds = collect($this->selectedPackageIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($selectedOrderIds->isEmpty() && $selectedPackageIds->isEmpty()) {
            return $this->emptyLabelPrintSummary();
        }

        $packages = ChannelOrderPackage::query()
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', auth()->id()))
            ->where(function (Builder $query) use ($selectedOrderIds, $selectedPackageIds) {
                if ($selectedOrderIds->isNotEmpty()) {
                    $query->whereIn('channel_order_id', $selectedOrderIds->all());
                }

                if ($selectedPackageIds->isNotEmpty()) {
                    $method = $selectedOrderIds->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                    $query->{$method}('id', $selectedPackageIds->all());
                }
            })
            ->get(['id', 'channel_order_id', 'label_printed_at', 'label_print_count']);

        return $this->labelPrintSummaryFromPackages($packages, 'Bu seçimdeki');
    }

    /**
     * @param  Collection<int, ChannelOrderPackage>  $packages
     * @return array{
     *     total_packages:int,
     *     printed_packages:int,
     *     total_print_count:int,
     *     has_printed:bool,
     *     all_printed:bool,
     *     has_reprint:bool,
     *     badge_label:?string,
     *     meta_label:?string,
     *     confirm_message:?string,
     *     last_printed_at:?Carbon
     * }
     */
    protected function labelPrintSummaryFromPackages(Collection $packages, string $subjectPrefix): array
    {
        $packages = $packages
            ->filter(fn ($package) => $package instanceof ChannelOrderPackage)
            ->values();

        if ($packages->isEmpty()) {
            return $this->emptyLabelPrintSummary();
        }

        $printedPackages = $packages
            ->filter(fn (ChannelOrderPackage $package) => filled($package->label_printed_at) || (int) ($package->label_print_count ?? 0) > 0)
            ->values();

        $totalPackages = $packages->count();
        $printedPackagesCount = $printedPackages->count();
        $totalPrintCount = (int) $printedPackages->sum(fn (ChannelOrderPackage $package) => max(1, (int) ($package->label_print_count ?? 0)));
        $lastPrintedAt = $printedPackages
            ->map(fn (ChannelOrderPackage $package) => [
                'package' => $package,
                'printed_at' => $this->resolvePackageLabelPrintedAt($package),
            ])
            ->sortByDesc(fn (array $row) => $row['printed_at']?->getTimestamp() ?? 0)
            ->first()['printed_at'] ?? null;
        $hasPrinted = $printedPackagesCount > 0;
        $allPrinted = $hasPrinted && $printedPackagesCount === $totalPackages;
        $hasReprint = $printedPackages->contains(fn (ChannelOrderPackage $package) => (int) ($package->label_print_count ?? 0) > 1);

        $badgeLabel = match (true) {
            !$hasPrinted => null,
            $totalPackages <= 1 => 'Etiket yazdırıldı',
            $allPrinted => $printedPackagesCount . ' etiket yazdırıldı',
            default => $printedPackagesCount . '/' . $totalPackages . ' etiket yazdırıldı',
        };

        $metaLabel = match (true) {
            !$hasPrinted => null,
            $hasReprint => 'Toplam ' . $totalPrintCount . ' çıktı alındı',
            $lastPrintedAt instanceof Carbon => 'Son çıktı ' . $lastPrintedAt->format('d.m.Y H:i'),
            default => null,
        };

        $confirmMessage = match (true) {
            !$hasPrinted => null,
            $totalPackages <= 1 => $subjectPrefix . ' kargo etiketi daha önce yazdırıldı. Tekrar indirmek istiyor musunuz?',
            $allPrinted => $subjectPrefix . ' tüm kargo etiketleri daha önce yazdırıldı. Tekrar indirmek istiyor musunuz?',
            default => $subjectPrefix . ' ' . $printedPackagesCount . '/' . $totalPackages . ' kargo etiketi daha önce yazdırıldı. Tekrar indirmek istiyor musunuz?',
        };

        return [
            'total_packages' => $totalPackages,
            'printed_packages' => $printedPackagesCount,
            'total_print_count' => $totalPrintCount,
            'has_printed' => $hasPrinted,
            'all_printed' => $allPrinted,
            'has_reprint' => $hasReprint,
            'badge_label' => $badgeLabel,
            'meta_label' => $metaLabel,
            'confirm_message' => $confirmMessage,
            'last_printed_at' => $lastPrintedAt,
        ];
    }

    /**
     * @return array{
     *     total_packages:int,
     *     printed_packages:int,
     *     total_print_count:int,
     *     has_printed:bool,
     *     all_printed:bool,
     *     has_reprint:bool,
     *     badge_label:?string,
     *     meta_label:?string,
     *     confirm_message:?string,
     *     last_printed_at:?Carbon
     * }
     */
    protected function emptyLabelPrintSummary(): array
    {
        return [
            'total_packages' => 0,
            'printed_packages' => 0,
            'total_print_count' => 0,
            'has_printed' => false,
            'all_printed' => false,
            'has_reprint' => false,
            'badge_label' => null,
            'meta_label' => null,
            'confirm_message' => null,
            'last_printed_at' => null,
        ];
    }

    protected function resolvePackageLabelPrintedAt(ChannelOrderPackage $package): ?Carbon
    {
        $printedAt = $package->label_printed_at;

        if ($printedAt instanceof Carbon) {
            return $printedAt;
        }

        if (!filled($printedAt)) {
            return null;
        }

        try {
            return Carbon::parse((string) $printedAt);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{key:string,label:string,tone:string}
     */
    protected function resolveOrderDisplayStatus(ChannelOrder $order): array
    {
        $packages = $order->packages ?? collect();
        $marketplace = (string) ($order->store?->marketplace ?? $order->marketplace_alias ?? '');
        $firstPackage = $packages->first();
        $orderKey = $this->normalizeStatusKey(
            $order->order_status,
            $marketplace,
            $firstPackage?->cargo_tracking_number,
            $firstPackage?->delivered_at,
        );
        $packageKeys = $packages
            ->map(fn (ChannelOrderPackage $package) => $this->normalizeStatusKey(
                $package->package_status,
                $marketplace,
                $package->cargo_tracking_number,
                $package->delivered_at,
            ))
            ->filter(fn (string $key) => $key !== 'missing')
            ->values();
        $hasActuallyShippedPackage = $packages->contains(fn (ChannelOrderPackage $package) => filled($package->shipped_at)
            && !$this->shouldUsePazaramaPlannedShipmentDate(
                $marketplace,
                $package->package_status,
                $package->cargo_tracking_number,
                $package->delivered_at,
                $package->raw_payload,
            ));

        $resolvedKey = match (true) {
            filled($order->returned_at)
                || $orderKey === 'returned'
                || $packageKeys->contains('returned') => 'returned',
            $orderKey === 'rejected'
                || $packageKeys->contains('rejected') => 'rejected',
            filled($order->cancelled_at)
                || $orderKey === 'cancelled'
                || $packageKeys->contains('cancelled') => 'cancelled',
            filled($order->delivered_at)
                || $packageKeys->contains('delivered')
                || $packageKeys->contains('completed')
                || in_array($orderKey, ['delivered', 'completed'], true) => $orderKey === 'completed' ? 'completed' : 'delivered',
            $packageKeys->contains('out_for_delivery')
                || $orderKey === 'out_for_delivery' => 'out_for_delivery',
            $packageKeys->contains('in_transit')
                || $orderKey === 'in_transit' => 'in_transit',
            $hasActuallyShippedPackage
                || $packageKeys->contains('shipped')
                || $orderKey === 'shipped' => 'shipped',
            $packageKeys->contains('shipping')
                || $orderKey === 'shipping' => 'shipping',
            $packageKeys->contains('processing')
                || $orderKey === 'processing' => 'processing',
            filled($order->approved_at)
                || $packageKeys->contains('approved')
                || $orderKey === 'approved' => 'approved',
            $orderKey === 'received' || $packageKeys->contains('received') => 'received',
            $orderKey === 'pending' || $packageKeys->contains('pending') => 'pending',
            $orderKey === 'new' || $packageKeys->contains('new') => 'new',
            default => $orderKey,
        };

        return [
            'key' => $resolvedKey,
            'label' => $this->statusLabelFromKey($resolvedKey, $order->order_status),
            'tone' => $this->statusToneFromKey($resolvedKey),
        ];
    }

    protected function normalizeStatusKey(
        ?string $status,
        ?string $marketplace = null,
        ?string $trackingNumber = null,
        mixed $deliveredAt = null,
    ): string
    {
        if ($this->shouldTreatPazaramaCargoAsApproved($status, $marketplace, $trackingNumber, $deliveredAt)) {
            return 'approved';
        }

        $normalized = Str::of((string) $status)
            ->trim()
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->value();

        return match (true) {
            $normalized === '' => 'missing',
            $this->statusContains($normalized, ['return', 'refund', 'iade']) => 'returned',
            $this->statusContains($normalized, ['reject', 'redd']) => 'rejected',
            $this->statusContains($normalized, ['cancel', 'iptal', 'refus']) => 'cancelled',
            $this->statusContains($normalized, ['completed', 'tamamlan']) => 'completed',
            $this->statusContains($normalized, ['delivered', 'teslim']) => 'delivered',
            $this->statusContains($normalized, ['out for delivery', 'dagitim', 'dağıtım']) => 'out_for_delivery',
            $this->statusContains($normalized, ['in transit', 'transit', 'yolda', 'tasima', 'taşıma', 'aktarma']) => 'in_transit',
            $this->statusContains($normalized, ['shipping', 'shipment', 'kargolaniyor', 'kargolanıyor']) => 'shipping',
            $this->statusContains($normalized, ['shipped', 'kargo', 'transit', 'dispatch']) => 'shipped',
            $this->statusContains($normalized, ['processing', 'process', 'picking', 'packing', 'hazir', 'prepared', 'invoiced', 'invoice', 'fatura']) => 'processing',
            $this->statusContains($normalized, ['received', 'siparis alindi', 'sipariş alındı']) => 'received',
            $this->statusContains($normalized, ['pending', 'bekliyor', 'awaiting']) => 'pending',
            $this->statusContains($normalized, ['approved', 'onay', 'open', 'confirmed', 'accept']) => 'approved',
            $this->statusContains($normalized, ['created', 'new', 'yeni']) => 'new',
            default => 'unknown',
        };
    }

    /**
     * @param  array<int, string>  $needles
     */
    protected function statusContains(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    protected function statusLabelFromKey(string $key, ?string $fallback = null): string
    {
        return match ($key) {
            'missing' => 'Durum yok',
            'new' => 'Yeni',
            'received' => 'Sipariş alındı',
            'pending' => 'Bekliyor',
            'approved' => 'Onaylandı',
            'processing' => 'Hazırlanıyor',
            'shipping' => 'Kargolanıyor',
            'shipped' => 'Kargolandı',
            'in_transit' => 'Yolda',
            'out_for_delivery' => 'Dağıtımda',
            'delivered' => 'Teslim edildi',
            'completed' => 'Tamamlandı',
            'cancelled' => 'İptal edildi',
            'returned' => 'İade edildi',
            'rejected' => 'Reddedildi',
            default => $this->fallbackStatusLabel($fallback),
        };
    }

    protected function fallbackStatusLabel(?string $status): string
    {
        $normalized = $this->normalizeLookupValue($status);

        if ($normalized === '') {
            return 'Durum yok';
        }

        $knownLabels = [
            'awaiting payment' => 'Ödeme bekliyor',
            'waiting payment' => 'Ödeme bekliyor',
            'waiting for payment' => 'Ödeme bekliyor',
            'payment pending' => 'Ödeme bekliyor',
            'payment received' => 'Ödeme alındı',
            'paid' => 'Ödeme alındı',
            'ready to ship' => 'Kargoya hazır',
            'ready for shipment' => 'Kargoya hazır',
            'out for delivery' => 'Dağıtımda',
            'undelivered' => 'Teslim edilemedi',
            'delivery failed' => 'Teslim edilemedi',
            'missing invoice' => 'Fatura eksik',
            'invoice missing' => 'Fatura eksik',
            'on hold' => 'Beklemeye alındı',
            'failed' => 'Hata',
        ];

        if (array_key_exists($normalized, $knownLabels)) {
            return $knownLabels[$normalized];
        }

        return Str::of($normalized)
            ->title()
            ->value();
    }

    protected function statusToneFromKey(string $key): string
    {
        return match ($key) {
            'delivered', 'completed' => 'success',
            'cancelled', 'returned', 'rejected' => 'danger',
            'processing', 'pending', 'shipping' => 'warning',
            'received', 'approved', 'shipped', 'in_transit', 'out_for_delivery' => 'info',
            default => 'default',
        };
    }

    protected function shouldTreatPazaramaCargoAsApproved(
        ?string $status,
        ?string $marketplace = null,
        ?string $trackingNumber = null,
        mixed $deliveredAt = null,
    ): bool
    {
        if (MarketplaceProviderRegistry::normalize((string) $marketplace) !== 'pazarama') {
            return false;
        }

        $normalized = Str::lower(trim((string) $status));

        if ($normalized === '') {
            return false;
        }

        if (str_contains($normalized, 'deliver') || str_contains($normalized, 'teslim')) {
            return false;
        }

        if (!str_contains($normalized, 'ship') && !str_contains($normalized, 'kargo')) {
            return false;
        }

        if (filled($trackingNumber)) {
            return false;
        }

        return !filled($deliveredAt);
    }

    public function humanStatus(
        ?string $status,
        ?string $marketplace = null,
        ?string $trackingNumber = null,
        mixed $deliveredAt = null,
    ): string
    {
        return $this->statusLabelFromKey(
            $this->normalizeStatusKey($status, $marketplace, $trackingNumber, $deliveredAt),
            $status
        );
    }

    public function statusTone(
        ?string $status,
        ?string $marketplace = null,
        ?string $trackingNumber = null,
        mixed $deliveredAt = null,
    ): string
    {
        return $this->statusToneFromKey(
            $this->normalizeStatusKey($status, $marketplace, $trackingNumber, $deliveredAt)
        );
    }

    public function shipmentDateLabel(
        ?string $marketplace = null,
        ?string $status = null,
        ?string $trackingNumber = null,
        mixed $deliveredAt = null,
        mixed $rawPayload = null,
    ): string {
        if ($this->shouldUsePazaramaPlannedShipmentDate($marketplace, $status, $trackingNumber, $deliveredAt, $rawPayload)) {
            return 'Kargolama tarihi';
        }

        return 'Kargoya verildi';
    }

    public function shipmentDateShortLabel(
        ?string $marketplace = null,
        ?string $status = null,
        ?string $trackingNumber = null,
        mixed $deliveredAt = null,
        mixed $rawPayload = null,
    ): string {
        if ($this->shouldUsePazaramaPlannedShipmentDate($marketplace, $status, $trackingNumber, $deliveredAt, $rawPayload)) {
            return 'Kargolama';
        }

        return 'Kargoya';
    }

    public function packageShipmentAt(
        ?ChannelOrderPackage $package,
        ?string $marketplace = null,
    ): ?Carbon {
        if (!$package) {
            return null;
        }

        if ($this->shouldUsePazaramaPlannedShipmentDate(
            $marketplace,
            $package->package_status,
            $package->cargo_tracking_number,
            $package->delivered_at,
            $package->raw_payload,
        )) {
            $estimatedShippingDate = data_get($package->raw_payload ?? [], 'estimatedShippingDate');

            if (filled($estimatedShippingDate)) {
                try {
                    return Carbon::parse((string) $estimatedShippingDate);
                } catch (\Throwable) {
                    return null;
                }
            }
        }

        return $package->shipped_at instanceof Carbon ? $package->shipped_at : null;
    }

    protected function shouldUsePazaramaPlannedShipmentDate(
        ?string $marketplace = null,
        ?string $status = null,
        ?string $trackingNumber = null,
        mixed $deliveredAt = null,
        mixed $rawPayload = null,
    ): bool {
        if (MarketplaceProviderRegistry::normalize((string) $marketplace) !== 'pazarama') {
            return false;
        }

        if (filled($trackingNumber) || filled($deliveredAt)) {
            return false;
        }

        $normalized = Str::lower(trim((string) $status));

        if (
            str_contains($normalized, 'deliver')
            || str_contains($normalized, 'teslim')
            || str_contains($normalized, 'cancel')
            || str_contains($normalized, 'iptal')
            || str_contains($normalized, 'return')
            || str_contains($normalized, 'iade')
        ) {
            return false;
        }

        if ($this->shouldTreatPazaramaCargoAsApproved($status, $marketplace, $trackingNumber, $deliveredAt)) {
            return true;
        }

        if (
            str_contains($normalized, 'approve')
            || str_contains($normalized, 'onay')
            || str_contains($normalized, 'processing')
            || str_contains($normalized, 'pack')
            || str_contains($normalized, 'new')
            || str_contains($normalized, 'created')
        ) {
            return filled(data_get($rawPayload ?? [], 'estimatedShippingDate'));
        }

        return false;
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

    /**
     * @return array<string, array{label: string, short: string, description: string, requires: string}>
     */
    public function packageOperationDefinitions(): array
    {
        return [
            'package_picking' => [
                'label' => 'Kargola',
                'short' => 'Statü',
                'description' => 'Paketi pazaryerinde kargolama/hazırlık aşamasına taşır.',
                'requires' => 'Harici paket ID ve satır ID',
            ],
            'package_invoiced' => [
                'label' => 'Fatura kesildi',
                'short' => 'Fatura',
                'description' => 'Fatura numarasını pazaryerine bildirir.',
                'requires' => 'Fatura no ve fatura tarihi',
            ],
            'package_common_label_create' => [
                'label' => 'Barkod talep et',
                'short' => 'Barkod',
                'description' => 'Ortak kargo barkodu/etiketi için kanal servisine talep gönderir.',
                'requires' => 'Takip numarası veya kanal paket no',
            ],
            'package_common_label_get' => [
                'label' => 'Barkodu getir',
                'short' => 'Etiket',
                'description' => 'Hazır barkod/etiket bilgisini kanal servisinden çeker.',
                'requires' => 'Takip numarası veya kanal paket no',
            ],
            'package_invoice_link' => [
                'label' => 'Fatura linki gönder',
                'short' => 'Link',
                'description' => 'PDF fatura linkini pazaryerindeki pakete bağlar.',
                'requires' => 'Fatura linki',
            ],
        ];
    }

    public function actionCanRetry(?string $status): bool
    {
        return in_array((string) $status, ['failed', 'retrying'], true);
    }

    protected function emptyOrderForm(): array
    {
        return [
            'order_number' => '',
            'order_status' => 'New',
            'customer_name' => '',
            'customer_email' => '',
            'customer_phone' => '',
            'customer_note' => '',
            'commercial_type' => '',
            'billing_name' => '',
            'billing_tax_number' => '',
            'shipment_city' => '',
            'shipment_district' => '',
            'ordered_at' => '',
        ];
    }

    protected function syncEditOrderDerivedStatuses(): void
    {
        foreach ($this->orderPackagesForm as $index => $packageForm) {
            $resolvedPackageStatus = $this->canonicalStatusValue($this->resolveManualPackageStatus(
                $packageForm['package_status'] ?? null,
                $this->nullableTrim($packageForm['cargo_tracking_number'] ?? null),
                $packageForm['shipped_at'] ?? null,
                $packageForm['delivered_at'] ?? null,
            ), 'New');

            if (($this->orderPackagesForm[$index]['package_status'] ?? null) !== $resolvedPackageStatus) {
                $this->orderPackagesForm[$index]['package_status'] = $resolvedPackageStatus;
            }
        }

        $this->orderForm['order_status'] = $this->resolveEditOrderStatus(
            $this->orderForm['order_status'] ?? null,
            $this->orderPackagesForm,
        );
    }

    protected function nullableTrim(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @return array<string, array{label:string, aliases:array<int, string>, tracking_url?:string}>
     */
    protected function cargoCompanyDefinitions(): array
    {
        return [
            'aras_kargo' => [
                'label' => 'Aras Kargo',
                'aliases' => ['aras', 'aras kargo'],
            ],
            'dhl_express' => [
                'label' => 'DHL Express',
                'aliases' => ['dhl', 'dhl express'],
            ],
            'hepsijet' => [
                'label' => 'HepsiJET',
                'aliases' => ['hepsijet', 'hepsi jet'],
            ],
            'kolay_gelsin' => [
                'label' => 'Kolay Gelsin',
                'aliases' => ['kolay gelsin'],
            ],
            'mng_kargo' => [
                'label' => 'MNG Kargo',
                'aliases' => ['mng', 'mng kargo'],
            ],
            'ptt_kargo' => [
                'label' => 'PTT Kargo',
                'aliases' => ['ptt', 'ptt kargo'],
            ],
            'sendeo' => [
                'label' => 'Sendeo',
                'aliases' => ['sendeo'],
            ],
            'surat_kargo' => [
                'label' => 'Sürat Kargo',
                'aliases' => ['surat', 'surat kargo', 'surat kargo marketplace', 'surat cargo', 's rat kargo', '341'],
                'tracking_url' => 'https://suratkargo.com.tr/Default/_KargoTakip?kargotakipno={tracking}',
            ],
            'trendyol_express' => [
                'label' => 'Trendyol Express',
                'aliases' => ['trendyol express'],
            ],
            'ups_kargo' => [
                'label' => 'UPS Kargo',
                'aliases' => ['ups', 'ups kargo'],
            ],
            'yurtici_kargo' => [
                'label' => 'Yurtiçi Kargo',
                'aliases' => ['yurtici', 'yurtici kargo', 'yurt ici kargo'],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function editableStatusOptions(): array
    {
        return [
            'New' => 'Yeni',
            'Received' => 'Sipariş alındı',
            'Pending' => 'Bekliyor',
            'Approved' => 'Onaylandı',
            'Processing' => 'Hazırlanıyor',
            'Shipping' => 'Kargolanıyor',
            'Shipped' => 'Kargolandı',
            'In transit' => 'Yolda',
            'Out for delivery' => 'Dağıtımda',
            'Delivered' => 'Teslim edildi',
            'Completed' => 'Tamamlandı',
            'Cancelled' => 'İptal edildi',
            'Returned' => 'İade edildi',
            'Rejected' => 'Reddedildi',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function filterStatusOptions(): array
    {
        return [
            'new' => 'Yeni',
            'received' => 'Sipariş alındı',
            'pending' => 'Bekliyor',
            'approved' => 'Onaylandı',
            'processing' => 'Hazırlanıyor',
            'shipping' => 'Kargolanıyor',
            'shipped' => 'Kargolandı',
            'in_transit' => 'Yolda',
            'out_for_delivery' => 'Dağıtımda',
            'delivered' => 'Teslim edildi',
            'completed' => 'Tamamlandı',
            'cancelled' => 'İptal edildi',
            'returned' => 'İade edildi',
            'rejected' => 'Reddedildi',
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function statusSqlNeedles(): array
    {
        return [
            'new' => ['created', 'new', 'yeni'],
            'received' => ['received', 'siparis alindi', 'sipariş alındı'],
            'pending' => ['pending', 'bekliyor', 'awaiting'],
            'approved' => ['approved', 'onay', 'open', 'confirmed', 'accept'],
            'processing' => ['processing', 'process', 'picking', 'packing', 'hazir', 'hazır', 'prepared', 'invoiced', 'invoice', 'fatura'],
            'shipping' => ['shipping', 'shipment', 'kargolaniyor', 'kargolanıyor'],
            'in_transit' => ['in transit', 'transit', 'yolda', 'tasima', 'taşıma', 'aktarma'],
            'out_for_delivery' => ['out for delivery', 'dagitim', 'dağıtım'],
            'shipped' => ['shipped', 'kargo', 'dispatch'],
            'delivered' => ['delivered', 'teslim'],
            'completed' => ['completed', 'tamamlan'],
            'cancelled' => ['cancel', 'iptal', 'refus'],
            'returned' => ['return', 'refund', 'iade'],
            'rejected' => ['reject', 'redd'],
        ];
    }

    protected function applyChannelStatusFilter(Builder $query, string $displayStatusSql): void
    {
        if ($this->statusFilter === '') {
            return;
        }

        $statusKey = $this->normalizeStatusKey($this->statusFilter);

        if (!array_key_exists($statusKey, $this->filterStatusOptions())) {
            $query->where('channel_orders.order_status', $this->statusFilter);

            return;
        }

        $query->whereRaw("{$displayStatusSql} = ?", [$statusKey]);
    }

    protected function applyTextStatusFilter(Builder $query, string $column): void
    {
        if ($this->statusFilter === '') {
            return;
        }

        $statusKey = $this->normalizeStatusKey($this->statusFilter);

        if (!array_key_exists($statusKey, $this->filterStatusOptions())) {
            $query->where($column, $this->statusFilter);

            return;
        }

        $query->whereRaw($this->statusSqlCondition($column, $statusKey));
    }

    protected function channelDisplayStatusSql(): string
    {
        $orderStatusColumn = 'channel_orders.order_status';
        $orderIdColumn = 'channel_orders.id';

        $orderReturned = $this->statusSqlCondition($orderStatusColumn, 'returned');
        $orderRejected = $this->statusSqlCondition($orderStatusColumn, 'rejected');
        $orderCancelled = $this->statusSqlCondition($orderStatusColumn, 'cancelled');
        $orderDelivered = $this->statusSqlCondition($orderStatusColumn, ['delivered', 'completed']);
        $orderCompleted = $this->statusSqlCondition($orderStatusColumn, 'completed');
        $orderOutForDelivery = $this->statusSqlCondition($orderStatusColumn, 'out_for_delivery');
        $orderInTransit = $this->statusSqlCondition($orderStatusColumn, 'in_transit');
        $orderShipped = $this->statusSqlCondition($orderStatusColumn, 'shipped');
        $orderShipping = $this->statusSqlCondition($orderStatusColumn, 'shipping');
        $orderProcessing = $this->statusSqlCondition($orderStatusColumn, 'processing');
        $orderApproved = $this->statusSqlCondition($orderStatusColumn, 'approved');
        $orderReceived = $this->statusSqlCondition($orderStatusColumn, 'received');
        $orderPending = $this->statusSqlCondition($orderStatusColumn, 'pending');
        $orderNew = $this->statusSqlCondition($orderStatusColumn, 'new');

        $packageReturned = $this->packageStatusExistsSql($orderIdColumn, 'returned');
        $packageRejected = $this->packageStatusExistsSql($orderIdColumn, 'rejected');
        $packageCancelled = $this->packageStatusExistsSql($orderIdColumn, 'cancelled');
        $packageDelivered = $this->packageStatusExistsSql($orderIdColumn, ['delivered', 'completed']);
        $packageOutForDelivery = $this->packageStatusExistsSql($orderIdColumn, 'out_for_delivery');
        $packageInTransit = $this->packageStatusExistsSql($orderIdColumn, 'in_transit');
        $packageShipped = $this->packageShippedExistsSql($orderIdColumn);
        $packageShipping = $this->packageStatusExistsSql($orderIdColumn, 'shipping');
        $packageProcessing = $this->packageStatusExistsSql($orderIdColumn, 'processing');
        $packageApproved = $this->packageStatusExistsSql($orderIdColumn, 'approved');
        $packageReceived = $this->packageStatusExistsSql($orderIdColumn, 'received');
        $packagePending = $this->packageStatusExistsSql($orderIdColumn, 'pending');
        $packageNew = $this->packageStatusExistsSql($orderIdColumn, 'new');

        return <<<SQL
CASE
    WHEN channel_orders.returned_at IS NOT NULL OR {$orderReturned} OR {$packageReturned} THEN 'returned'
    WHEN {$orderRejected} OR {$packageRejected} THEN 'rejected'
    WHEN channel_orders.cancelled_at IS NOT NULL OR {$orderCancelled} OR {$packageCancelled} THEN 'cancelled'
    WHEN channel_orders.delivered_at IS NOT NULL OR {$orderDelivered} OR {$packageDelivered}
        THEN CASE WHEN {$orderCompleted} THEN 'completed' ELSE 'delivered' END
    WHEN {$packageOutForDelivery} OR {$orderOutForDelivery} THEN 'out_for_delivery'
    WHEN {$packageInTransit} OR {$orderInTransit} THEN 'in_transit'
    WHEN {$packageShipped} OR {$orderShipped} THEN 'shipped'
    WHEN {$packageShipping} OR {$orderShipping} THEN 'shipping'
    WHEN {$packageProcessing} OR {$orderProcessing} THEN 'processing'
    WHEN channel_orders.approved_at IS NOT NULL OR {$packageApproved} OR {$orderApproved} THEN 'approved'
    WHEN {$orderReceived} OR {$packageReceived} THEN 'received'
    WHEN {$orderPending} OR {$packagePending} THEN 'pending'
    WHEN {$orderNew} OR {$packageNew} THEN 'new'
    ELSE CASE
        WHEN TRIM(COALESCE({$orderStatusColumn}, '')) = '' THEN 'missing'
        ELSE 'unknown'
    END
END
SQL;
    }

    protected function packageStatusExistsSql(string $orderIdColumn, string|array $statusKeys): string
    {
        $statusCondition = $this->statusSqlCondition('status_packages.package_status', $statusKeys);

        return "EXISTS (
            SELECT 1
            FROM channel_order_packages AS status_packages
            WHERE status_packages.channel_order_id = {$orderIdColumn}
              AND {$statusCondition}
        )";
    }

    protected function packageShippedExistsSql(string $orderIdColumn): string
    {
        $statusCondition = $this->statusSqlCondition('shipped_packages.package_status', 'shipped');

        return "EXISTS (
            SELECT 1
            FROM channel_order_packages AS shipped_packages
            WHERE shipped_packages.channel_order_id = {$orderIdColumn}
              AND (shipped_packages.shipped_at IS NOT NULL OR {$statusCondition})
        )";
    }

    protected function statusSqlCondition(string $column, string|array $statusKeys): string
    {
        $needles = collect((array) $statusKeys)
            ->flatMap(fn (string $key) => $this->statusSqlNeedles()[$key] ?? [])
            ->map(fn (string $needle) => mb_strtolower($needle))
            ->unique()
            ->values();

        if ($needles->isEmpty()) {
            return '0 = 1';
        }

        $conditions = $needles
            ->map(function (string $needle) use ($column): string {
                $escapedNeedle = str_replace("'", "''", $needle);

                return "LOWER(COALESCE({$column}, '')) LIKE '%{$escapedNeedle}%'";
            })
            ->implode(' OR ');

        return '(' . $conditions . ')';
    }

    protected function canonicalStatusValue(?string $status, string $default = 'New'): string
    {
        return match ($this->normalizeStatusKey($status)) {
            'new' => 'New',
            'received' => 'Received',
            'pending' => 'Pending',
            'approved' => 'Approved',
            'processing' => 'Processing',
            'shipping' => 'Shipping',
            'shipped' => 'Shipped',
            'in_transit' => 'In transit',
            'out_for_delivery' => 'Out for delivery',
            'delivered' => 'Delivered',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'returned' => 'Returned',
            'rejected' => 'Rejected',
            'missing' => $default,
            default => $this->nullableTrim($status) ?? $default,
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $packages
     */
    protected function resolveEditOrderStatus(?string $fallbackStatus, array $packages): string
    {
        $packageKeys = collect($packages)
            ->map(function (array $package): string {
                $resolvedPackageStatus = $this->resolveManualPackageStatus(
                    $package['package_status'] ?? null,
                    $this->nullableTrim($package['cargo_tracking_number'] ?? null),
                    $package['shipped_at'] ?? null,
                    $package['delivered_at'] ?? null,
                );

                return $this->normalizeStatusKey($resolvedPackageStatus);
            })
            ->filter(fn (string $key) => $key !== 'missing')
            ->values();

        $fallbackKey = $this->normalizeStatusKey($fallbackStatus);

        $resolvedKey = match (true) {
            $packageKeys->contains('returned') => 'returned',
            $packageKeys->contains('rejected') => 'rejected',
            $packageKeys->contains('cancelled') => 'cancelled',
            $packageKeys->contains('delivered') || $packageKeys->contains('completed') => $fallbackKey === 'completed' ? 'completed' : 'delivered',
            $packageKeys->contains('out_for_delivery') => 'out_for_delivery',
            $packageKeys->contains('in_transit') => 'in_transit',
            $packageKeys->contains('shipped') => 'shipped',
            $packageKeys->contains('shipping') => 'shipping',
            $packageKeys->contains('processing') => 'processing',
            $packageKeys->contains('approved') => 'approved',
            $packageKeys->contains('received') => 'received',
            $packageKeys->contains('pending') => 'pending',
            $packageKeys->contains('new') => 'new',
            default => $fallbackKey,
        };

        return $this->canonicalStatusValue($resolvedKey, 'New');
    }

    protected function cargoCompanyDefinitionFromValue(?string $value): ?array
    {
        $needle = $this->normalizeLookupValue($value);

        if ($needle === '') {
            return null;
        }

        foreach ($this->cargoCompanyDefinitions() as $definition) {
            foreach ($definition['aliases'] as $alias) {
                if ($needle === $this->normalizeLookupValue($alias)) {
                    return $definition;
                }
            }

            if ($needle === $this->normalizeLookupValue($definition['label'])) {
                return $definition;
            }
        }

        return null;
    }

    protected function normalizeCargoCompany(?string $value): ?string
    {
        $trimmed = $this->nullableTrim($value);

        if (!filled($trimmed)) {
            return null;
        }

        $definition = $this->cargoCompanyDefinitionFromValue($trimmed);

        return $definition['label'] ?? $trimmed;
    }

    protected function resolveManualCargoCompany(?string $cargoCompany, ?string $shipmentProvider): ?string
    {
        $normalizedCargoCompany = $this->normalizeCargoCompany($cargoCompany);
        $normalizedShipmentProvider = $this->normalizeCargoCompany($shipmentProvider);
        $providerIsKnownCarrier = $this->cargoCompanyDefinitionFromValue($shipmentProvider) !== null;

        if (
            (!filled($normalizedCargoCompany) || $this->isGenericCargoCompany($normalizedCargoCompany))
            && $providerIsKnownCarrier
            && filled($normalizedShipmentProvider)
        ) {
            return $normalizedShipmentProvider;
        }

        return $normalizedCargoCompany;
    }

    protected function normalizeShipmentProvider(?string $shipmentProvider, ?string $cargoCompany): ?string
    {
        $shipmentProvider = $this->nullableTrim($shipmentProvider);

        if (!filled($shipmentProvider)) {
            return null;
        }

        return $this->normalizeCargoCompany($shipmentProvider) ?? $shipmentProvider;
    }

    protected function resolveManualPackageStatus(
        mixed $packageStatus,
        ?string $trackingNumber,
        mixed $shippedAt,
        mixed $deliveredAt,
    ): string {
        $packageStatus = $this->nullableTrim($packageStatus);
        $statusKey = $this->normalizeStatusKey($packageStatus);

        if (filled($deliveredAt)) {
            return 'Delivered';
        }

        if (
            (filled($trackingNumber) || filled($shippedAt))
            && !in_array($statusKey, ['shipped', 'in_transit', 'out_for_delivery', 'delivered', 'completed', 'cancelled', 'returned', 'rejected'], true)
        ) {
            return 'Shipped';
        }

        return $packageStatus ?? 'new';
    }

    protected function isGenericCargoCompany(?string $value): bool
    {
        $normalized = $this->normalizeLookupValue($value);

        return in_array($normalized, [
            'ucretsiz gonderim',
            'free shipping',
            'standart teslimat',
            'standart gonderim',
            'standard shipping',
            'shipping',
            'manual',
        ], true);
    }

    protected function normalizeLookupValue(?string $value): string
    {
        return Str::of((string) $value)
            ->trim()
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->trim()
            ->value();
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
        $allOptions = $this->allBulkPackageActionOptions();
        $selectedIds = collect($this->selectedPackageIds)
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($selectedIds->isEmpty()) {
            return $allOptions;
        }

        $packages = ChannelOrderPackage::query()
            ->with(['order:id,store_id', 'store:id,marketplace'])
            ->whereIn('id', $selectedIds->all())
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', auth()->id()))
            ->get();

        if ($packages->isEmpty()) {
            return [];
        }

        $supportedActions = collect(array_keys($allOptions));

        foreach ($packages as $package) {
            $supportedActions = $supportedActions
                ->filter(fn (string $action) => $this->packageSupportsAction($package, $action))
                ->values();

            if ($supportedActions->isEmpty()) {
                return [];
            }
        }

        return $supportedActions
            ->mapWithKeys(fn (string $action) => [$action => $allOptions[$action]])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected function allBulkPackageActionOptions(): array
    {
        return [
            'cargo_create_surat_shipment' => 'Sürat gönderisi oluştur',
            'cargo_refresh_surat_tracking' => 'Sürat takibini yenile',
            'package_picking' => 'Kargola',
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

        if (filled(data_get($actionRun, 'response_json.shipment_no'))) {
            $trackingNumber = data_get($actionRun, 'response_json.tracking_number')
                ?: data_get($actionRun, 'response_json.barcode');

            return filled($trackingNumber)
                ? 'Gönderi ' . data_get($actionRun, 'response_json.shipment_no') . ' / Takip: ' . $trackingNumber
                : 'Gönderi ' . data_get($actionRun, 'response_json.shipment_no');
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
        if ($this->isSuratCargoPackageAction($actionType)) {
            return Schema::hasTable('shipments');
        }

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
            'cargo_create_surat_shipment', 'cargo_refresh_surat_tracking' => [
                'carrier' => 'surat',
                'source' => 'marketplace_orders',
            ],
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
            'cargo_create_surat_shipment', 'cargo_refresh_surat_tracking' => 'cargo_surat',
            'package_picking' => 'package_picking',
            'package_invoiced' => 'package_invoiced',
            'package_common_label_create' => 'package_common_label_create',
            'package_common_label_get' => 'package_common_label_get',
            'package_invoice_link' => 'package_invoice_link',
            default => null,
        };
    }

    protected function isSuratCargoPackageAction(string $actionType): bool
    {
        return in_array($actionType, [
            'cargo_create_surat_shipment',
            'cargo_refresh_surat_tracking',
        ], true);
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
        return $this->applyOrderSorting($this->buildChannelOrdersQuery())
            ->forPage($this->getPage(), $this->perPage)
            ->pluck('channel_orders.id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    public function render()
    {
        $ordersPaginator = $this->applyOrderSorting($this->buildChannelOrdersQuery())
            ->paginate($this->perPage);

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
            $displayStatus = $this->resolveOrderDisplayStatus($order);
            $snapshot = $this->applyDisplayProfitMetrics($order, $snapshot);

            $order->setAttribute('order_snapshot', $snapshot);
            $order->setAttribute('package_summary', $order->packages->first());
            $order->setAttribute(
                'legacy_operational_order',
                $legacyOperationalOrders->get($order->store_id . '|' . $order->order_number)
            );
            $order->setAttribute('display_status_key', $displayStatus['key']);
            $order->setAttribute('display_status_label', $displayStatus['label']);
            $order->setAttribute('display_status_tone', $displayStatus['tone']);

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
            'sortPresets' => static::$sortPresets,
            'hasConfiguredStores' => MarketplaceStore::query()->where('user_id', auth()->id())->exists(),
            'hasChannelData' => $this->hasChannelData(),
        ])->layout('layouts.app', ['title' => 'Pazaryeri Siparişleri']);
    }
}
