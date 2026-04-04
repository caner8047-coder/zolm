<?php

namespace App\Livewire;

use App\Models\ChannelListing;
use App\Models\IntegrationPushRun;
use App\Models\IntegrationSyncRun;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\MpProduct;
use App\Models\ProductMatchIssue;
use App\Services\Marketplace\MarketplaceDiagnosticsGuidanceService;
use App\Services\Marketplace\MarketplaceManualSyncDispatchService;
use App\Services\Marketplace\MarketplaceListingPushService;
use App\Services\Marketplace\MarketplaceProviderRegistry;
use App\Services\MpProductImportService;
use App\Services\MpSettingsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class MpProductsManager extends Component
{
    use WithPagination;
    use WithFileUploads;

    public static array $allColumnDefs = [
        'urun' => 'Ürün',
        'kanal' => 'Kanal',
        'fiyat' => 'Fiyat',
        'cogs' => 'Maliyet',
        'kargo' => 'Kargo',
        'stok' => 'Stok',
        'kdv' => 'KDV',
        'roi' => 'Kâr Oranı',
        'durum' => 'Durum',
        'islem' => 'İşlem',
    ];

    public static array $sortableColumns = [
        'urun' => 'product_name',
        'kanal' => 'listing_count_metric',
        'fiyat' => 'sale_price',
        'cogs' => 'cogs',
        'kargo' => 'cargo_cost',
        'stok' => 'stock_quantity',
        'kdv' => 'vat_rate',
        'roi' => 'profit_metric',
        'durum' => 'status',
    ];

    public string $search = '';
    public string $filterStatus = 'all';
    public string $filterCategory = 'all';
    public string $filterBrand = 'all';
    public string $filterStockLevel = 'all';
    public string $filterCostDefined = 'all';
    public string $marketplaceFilter = 'all';
    public string $listingStatusFilter = 'all';
    public string $listingCoverageFilter = 'all';
    public string $legalEntityFilter = 'all';
    public ?int $edit = null;
    public string $tab = 'basic';
    public string $sortField = 'product_name';
    public string $sortDirection = 'asc';
    public int $perPage = 25;
    public array $visibleColumns = ['urun', 'fiyat', 'cogs', 'kargo', 'stok', 'kdv', 'roi', 'durum', 'islem'];

    public $importFile;
    public bool $showImportModal = false;
    public bool $importing = false;
    public ?array $importResult = null;

    public bool $showEditModal = false;
    public ?int $editingId = null;
    public string $editTab = 'basic';

    public string $f_barcode = '';
    public string $f_stock_code = '';
    public string $f_product_name = '';
    public string $f_model_code = '';
    public string $f_brand = '';
    public string $f_category_name = '';
    public string $f_color = '';
    public string $f_size = '';
    public string $f_variant = '';
    public $f_cogs = 0;
    public $f_packaging_cost = 0;
    public $f_cargo_cost = 0;
    public $f_vat_rate = 10;
    public $f_market_price = 0;
    public $f_sale_price = 0;
    public $f_commission_rate = 0;
    public $f_stock_quantity = 0;
    public $f_desi = 0;
    public $f_pieces = 1;
    public string $f_status = 'active';
    public string $f_platforms = '';
    public string $f_description = '';
    public string $f_image_url = '';
    public array $f_image_urls = [];
    public array $f_image_uploads = [];

    public array $selectedProducts = [];
    public bool $selectAll = false;

    protected $queryString = [
        'search' => ['except' => ''],
        'filterStatus' => ['except' => 'all'],
        'filterCategory' => ['except' => 'all'],
        'filterBrand' => ['except' => 'all'],
        'filterStockLevel' => ['except' => 'all'],
        'marketplaceFilter' => ['except' => 'all'],
        'listingStatusFilter' => ['except' => 'all'],
        'listingCoverageFilter' => ['except' => 'all'],
        'legalEntityFilter' => ['except' => 'all'],
        'edit' => ['except' => null],
        'tab' => ['except' => 'basic'],
        'sortField' => ['except' => 'product_name'],
        'sortDirection' => ['except' => 'asc'],
    ];

    protected function rules(): array
    {
        return [
            'f_barcode' => 'required|string|max:255',
            'f_product_name' => 'nullable|string|max:500',
            'f_cogs' => 'required|numeric|min:0',
            'f_packaging_cost' => 'required|numeric|min:0',
            'f_cargo_cost' => 'required|numeric|min:0',
            'f_vat_rate' => 'required|numeric|in:1,10,20',
            'f_stock_quantity' => 'required|integer|min:0',
            'f_sale_price' => 'required|numeric|min:0',
            'f_market_price' => 'required|numeric|min:0',
            'f_commission_rate' => 'required|numeric|min:0|max:100',
            'f_desi' => 'required|numeric|min:0',
            'f_pieces' => 'required|integer|min:1',
            'f_status' => 'required|in:active,out_of_stock,pending,suspended',
            'f_image_url' => 'nullable|string|max:2048',
            'f_image_urls.*' => 'nullable|string|max:2048',
            'f_image_uploads.*' => 'nullable|image|max:5120',
        ];
    }

    public function mount(): void
    {
        $this->visibleColumns = $this->normalizeVisibleColumns(
            app(MpSettingsService::class)->getArray('marketplace_products.v2.visible_columns', $this->visibleColumns)
        );

        $requestedTab = (string) request()->query('tab', $this->tab);
        $this->setEditTab($requestedTab);

        if ($this->edit && $this->edit > 0) {
            try {
                $this->editProduct($this->edit);
                $this->setEditTab($requestedTab);
            } catch (\Throwable) {
                $this->edit = null;
                session()->flash('warning', 'Düzenlenecek ürün bulunamadı.');
            }
        }
    }

    public function updatedTab($value): void
    {
        $this->setEditTab((string) $value);
    }

    public function updatedEdit($value): void
    {
        $editId = (int) $value;

        if ($editId <= 0) {
            return;
        }

        if ($this->editingId === $editId && $this->showEditModal) {
            return;
        }

        try {
            $this->editProduct($editId);
            $this->setEditTab($this->tab);
        } catch (\Throwable) {
            $this->edit = null;
            session()->flash('warning', 'Düzenlenecek ürün bulunamadı.');
        }
    }

    public function updatingSearch(): void
    {
        $this->resetTableForQueryChange();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetTableForQueryChange();
    }

    public function updatingFilterCategory(): void
    {
        $this->resetTableForQueryChange();
    }

    public function updatingFilterBrand(): void
    {
        $this->resetTableForQueryChange();
    }

    public function updatingFilterStockLevel(): void
    {
        $this->resetTableForQueryChange();
    }

    public function updatingFilterCostDefined(): void
    {
        $this->resetTableForQueryChange();
    }

    public function updatingMarketplaceFilter(): void
    {
        $this->resetTableForQueryChange();
    }

    public function updatingListingStatusFilter(): void
    {
        $this->resetTableForQueryChange();
    }

    public function updatingListingCoverageFilter(): void
    {
        $this->resetTableForQueryChange();
    }

    public function updatingLegalEntityFilter(): void
    {
        $this->resetTableForQueryChange();
    }

    public function updatingPerPage(): void
    {
        $this->resetTableForQueryChange();
    }

    public function updatedSelectAll($value): void
    {
        if ($value) {
            $this->selectedProducts = $this->currentPageProductIds();
        } else {
            $this->selectedProducts = [];
        }

        $this->syncSelectAllState();
    }

    public function updatedSelectedProducts(): void
    {
        $normalized = array_values(array_unique(array_map(static fn ($id) => (string) $id, $this->selectedProducts)));

        if ($normalized !== $this->selectedProducts) {
            $this->selectedProducts = $normalized;

            return;
        }

        $this->syncSelectAllState();
    }

    public function updatedPage(): void
    {
        $this->syncSelectAllState();
    }

    #[Computed]
    public function products()
    {
        return $this->buildProductsQuery()->paginate($this->perPage);
    }

    #[Computed]
    public function stats(): array
    {
        $productBase = MpProduct::query()->where('user_id', $this->userId());
        $listingBase = ChannelListing::query()->whereHas('store', fn (Builder $query) => $query->where('user_id', $this->userId()));
        $issueBase = ProductMatchIssue::query()->whereHas('store', fn (Builder $query) => $query->where('user_id', $this->userId()));

        return [
            'total_products' => (clone $productBase)->count(),
            'listed_products' => (clone $productBase)->has('channelListings')->count(),
            'unlisted_products' => (clone $productBase)->doesntHave('channelListings')->count(),
            'total_listings' => (clone $listingBase)->count(),
            'active_listings' => (clone $listingBase)->whereIn('listing_status', $this->activeListingStatuses())->count(),
            'multi_channel_products' => (clone $productBase)->has('channelListings', '>', 1)->count(),
            'stock_value' => round((float) (clone $productBase)->selectRaw('SUM(stock_quantity * cogs) as value')->value('value'), 2),
            'pending_match_issues' => (clone $issueBase)->where('match_status', 'pending')->count(),
            'queued_pushes' => IntegrationPushRun::query()
                ->whereHas('store', fn (Builder $query) => $query->where('user_id', $this->userId()))
                ->whereIn('status', ['queued', 'processing', 'retrying'])
                ->count(),
            'failed_pushes' => IntegrationPushRun::query()
                ->whereHas('store', fn (Builder $query) => $query->where('user_id', $this->userId()))
                ->where('status', 'failed')
                ->where('created_at', '>=', now()->subDay())
                ->count(),
        ];
    }

    #[Computed]
    public function categories()
    {
        return MpProduct::query()
            ->where('user_id', $this->userId())
            ->whereNotNull('category_name')
            ->where('category_name', '!=', '')
            ->distinct()
            ->pluck('category_name')
            ->sort()
            ->values();
    }

    #[Computed]
    public function brands()
    {
        return MpProduct::query()
            ->where('user_id', $this->userId())
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->distinct()
            ->pluck('brand')
            ->sort()
            ->values();
    }

    #[Computed]
    public function marketplaceOptions()
    {
        return MarketplaceStore::query()
            ->where('user_id', $this->userId())
            ->select('marketplace')
            ->distinct()
            ->orderBy('marketplace')
            ->pluck('marketplace');
    }

    #[Computed]
    public function legalEntities()
    {
        return LegalEntity::query()
            ->where('user_id', $this->userId())
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'tax_number']);
    }

    #[Computed]
    public function sidebarSummary(): array
    {
        $storeQuery = MarketplaceStore::query()->where('user_id', $this->userId());
        $syncQuery = IntegrationSyncRun::query()->whereHas('store', fn (Builder $query) => $query->where('user_id', $this->userId()));

        $lastCatalogSync = (clone $syncQuery)
            ->where('sync_type', 'products')
            ->where('status', 'completed')
            ->max('finished_at');

        return [
            'store_count' => (clone $storeQuery)->count(),
            'active_store_count' => (clone $storeQuery)->where('is_active', true)->count(),
            'price_push_ready' => (clone $storeQuery)->whereHas('syncProfile', fn (Builder $query) => $query->where('price_push_enabled', true))->count(),
            'stock_push_ready' => (clone $storeQuery)->whereHas('syncProfile', fn (Builder $query) => $query->where('stock_push_enabled', true))->count(),
            'orphan_listings' => ChannelListing::query()
                ->whereHas('store', fn (Builder $query) => $query->where('user_id', $this->userId()))
                ->whereNull('mp_product_id')
                ->count(),
            'latest_catalog_sync' => $lastCatalogSync,
            'failed_catalog_syncs' => (clone $syncQuery)
                ->where('sync_type', 'products')
                ->where('status', 'failed')
                ->where('created_at', '>=', now()->subDay())
                ->count(),
        ];
    }

    #[Computed]
    public function diagnosticsGuidance(): array
    {
        $guidance = app(MarketplaceDiagnosticsGuidanceService::class)->guidanceForUser($this->userId(), [
            'hours' => 168,
            'limit' => 200,
        ]);

        $legalEntityStoreIds = $this->legalEntityFilter !== 'all'
            ? MarketplaceStore::query()
                ->where('user_id', $this->userId())
                ->where('legal_entity_id', (int) $this->legalEntityFilter)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all()
            : [];

        $items = collect($guidance['items'])
            ->filter(fn (array $item) => in_array($item['category'] ?? '', [
                'product_matching',
                'listing_completeness',
                'legacy_financial_projection',
            ], true))
            ->when($this->marketplaceFilter !== 'all', fn ($collection) => $collection->where('marketplace', $this->marketplaceFilter))
            ->when(
                $this->legalEntityFilter !== 'all',
                fn ($collection) => $collection->filter(fn (array $item) => in_array((int) ($item['store_id'] ?? 0), $legalEntityStoreIds, true))
            )
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

    protected function buildProductsQuery(): Builder
    {
        $listingAggregate = ChannelListing::query()
            ->selectRaw('
                mp_product_id,
                COUNT(*) as listing_count_metric,
                SUM(CASE WHEN listing_status IN ("active", "approved", "live", "on_sale", "published") THEN 1 ELSE 0 END) as active_listing_count_metric,
                COALESCE(SUM(stock_quantity), 0) as channel_stock_total_metric,
                MAX(last_synced_at) as latest_listing_sync_at_metric
            ')
            ->whereNotNull('mp_product_id')
            ->groupBy('mp_product_id');

        $issueAggregate = ProductMatchIssue::query()
            ->join('channel_listings', 'channel_listings.id', '=', 'product_match_issues.channel_listing_id')
            ->selectRaw('
                channel_listings.mp_product_id,
                SUM(CASE WHEN product_match_issues.match_status = "pending" THEN 1 ELSE 0 END) as pending_issue_count_metric
            ')
            ->whereNotNull('channel_listings.mp_product_id')
            ->groupBy('channel_listings.mp_product_id');

        $query = MpProduct::query()
            ->select([
                'mp_products.*',
                \DB::raw('COALESCE(listing_agg.listing_count_metric, 0) as listing_count_metric'),
                \DB::raw('COALESCE(listing_agg.active_listing_count_metric, 0) as active_listing_count_metric'),
                \DB::raw('COALESCE(listing_agg.channel_stock_total_metric, 0) as channel_stock_total_metric'),
                \DB::raw('listing_agg.latest_listing_sync_at_metric as latest_listing_sync_at_metric'),
                \DB::raw('COALESCE(issue_agg.pending_issue_count_metric, 0) as pending_issue_count_metric'),
                \DB::raw('(
                    COALESCE(sale_price, 0)
                    - (COALESCE(cogs, 0) + COALESCE(packaging_cost, 0) + COALESCE(cargo_cost, 0))
                    - (COALESCE(sale_price, 0) * (COALESCE(commission_rate, 0) / 100))
                ) as profit_metric'),
            ])
            ->leftJoinSub($listingAggregate, 'listing_agg', function ($join) {
                $join->on('listing_agg.mp_product_id', '=', 'mp_products.id');
            })
            ->leftJoinSub($issueAggregate, 'issue_agg', function ($join) {
                $join->on('issue_agg.mp_product_id', '=', 'mp_products.id');
            })
            ->with([
                'channelListings' => fn ($query) => $query
                    ->with([
                        'store:id,user_id,legal_entity_id,marketplace,store_name,store_code,is_active',
                        'store.legalEntity:id,name,tax_number',
                        'store.syncProfile:id,store_id,price_push_enabled,stock_push_enabled',
                        'channelProduct:id,store_id,external_product_id,stock_code,barcode,title,brand,category_name,vat_rate,last_synced_at',
                        'matchIssues' => fn ($issueQuery) => $issueQuery->latest()->limit(3),
                        'pushRuns' => fn ($pushQuery) => $pushQuery->latest('created_at'),
                    ])
                    ->orderBy('store_id')
                    ->orderByDesc('last_synced_at'),
            ])
            ->where('mp_products.user_id', $this->userId());

        if ($this->search !== '') {
            $query->search($this->search);
        }

        if ($this->filterStatus !== 'all') {
            $query->byStatus($this->filterStatus);
        }

        if ($this->filterCategory !== 'all') {
            $query->byCategory($this->filterCategory);
        }

        if ($this->filterBrand !== 'all') {
            $query->byBrand($this->filterBrand);
        }

        if ($this->filterStockLevel !== 'all') {
            $query->byStockLevel($this->filterStockLevel);
        }

        if ($this->filterCostDefined === 'yes') {
            $query->withCost();
        } elseif ($this->filterCostDefined === 'no') {
            $query->withoutCost();
        }

        if ($this->marketplaceFilter !== 'all') {
            $query->whereHas('channelListings.store', function (Builder $listingQuery) {
                $listingQuery->where('marketplace', $this->marketplaceFilter);
            });
        }

        if ($this->legalEntityFilter !== 'all') {
            $query->whereHas('channelListings.store', function (Builder $listingQuery) {
                $listingQuery->where('legal_entity_id', $this->legalEntityFilter);
            });
        }

        if ($this->listingStatusFilter === 'active') {
            $query->whereHas('channelListings', function (Builder $listingQuery) {
                $listingQuery->whereIn('listing_status', $this->activeListingStatuses());
            });
        } elseif ($this->listingStatusFilter === 'passive') {
            $query->whereHas('channelListings', function (Builder $listingQuery) {
                $listingQuery->whereNotIn('listing_status', $this->activeListingStatuses());
            });
        } elseif ($this->listingStatusFilter === 'draft') {
            $query->whereHas('channelListings', fn (Builder $listingQuery) => $listingQuery->where('listing_status', 'draft'));
        }

        if ($this->listingCoverageFilter === 'listed') {
            $query->has('channelListings');
        } elseif ($this->listingCoverageFilter === 'unlisted') {
            $query->doesntHave('channelListings');
        } elseif ($this->listingCoverageFilter === 'multi_channel') {
            $query->has('channelListings', '>', 1);
        } elseif ($this->listingCoverageFilter === 'issues') {
            $query->whereRaw('COALESCE(issue_agg.pending_issue_count_metric, 0) > 0');
        }

        return $query->orderBy($this->sortField, $this->sortDirection)->orderBy('product_name');
    }

    public function sortTable(string $columnKey): void
    {
        $dbCol = static::$sortableColumns[$columnKey] ?? null;
        if (!$dbCol) {
            return;
        }

        if ($this->sortField === $dbCol) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $dbCol;
            $this->sortDirection = in_array($dbCol, [
                'listing_count_metric',
                'channel_stock_total_metric',
                'profit_metric',
                'sale_price',
                'cogs',
                'cargo_cost',
                'stock_quantity',
            ], true) ? 'desc' : 'asc';
        }

        $this->resetPage();
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

        app(MpSettingsService::class)->set('marketplace_products.v2.visible_columns', $this->visibleColumns);
    }

    public function openImportModal(): void
    {
        $this->importFile = null;
        $this->importResult = null;
        $this->importing = false;
        $this->showImportModal = true;
    }

    public function closeImportModal(): void
    {
        $this->showImportModal = false;
        $this->importFile = null;
        $this->importResult = null;
        $this->importing = false;
    }

    public function importExcel(): void
    {
        $this->validate([
            'importFile' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        $this->importing = true;

        try {
            $service = new MpProductImportService();
            $this->importResult = $service->import($this->importFile);
        } catch (\Throwable $e) {
            $this->importResult = [
                'success' => false,
                'message' => 'Hata: ' . $e->getMessage(),
                'imported' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => [],
            ];
        }

        $this->importing = false;
        $this->importFile = null;
    }

    public function exportExcel()
    {
        $service = new MpProductImportService();

        return $service->exportProducts([
            'search' => $this->search,
            'status' => $this->filterStatus !== 'all' ? $this->filterStatus : null,
            'category' => $this->filterCategory !== 'all' ? $this->filterCategory : null,
            'brand' => $this->filterBrand !== 'all' ? $this->filterBrand : null,
            'stock_level' => $this->filterStockLevel !== 'all' ? $this->filterStockLevel : null,
        ]);
    }

    public function exportDiagnosticsGuidanceCsv()
    {
        $filename = 'urunler_karar_destegi_' . now()->format('Ymd_His') . '.csv';
        $guidance = $this->diagnosticsGuidance();

        return response()->stream(function () use ($guidance) {
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF");

            fputcsv($file, ['Mağaza', 'Pazaryeri', 'Kategori', 'Seviye', 'Etkilenen', 'Başlık', 'Önerilen aksiyon', 'Yönlenecek ekran'], ';');

            foreach ($guidance['items'] as $item) {
                fputcsv($file, [
                    $this->cleanExportString($item['store_name']),
                    $this->cleanExportString($this->humanMarketplace($item['marketplace'])),
                    $this->cleanExportString($this->guidanceCategoryLabel($item['category'])),
                    $this->cleanExportString($this->guidanceSeverityLabel($item['severity'])),
                    $item['impact_count'],
                    $this->cleanExportString($item['title']),
                    $this->cleanExportString($item['recommended_action']),
                    $this->cleanExportString($this->guidanceRouteLabel($item['route'])),
                ], ';');
            }

            fclose($file);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->edit = null;
        $this->setEditTab('basic');
        $this->showEditModal = true;
    }

    public function editProduct(int $id): void
    {
        $product = MpProduct::where('user_id', $this->userId())->findOrFail($id);

        $this->editingId = $product->id;
        $this->f_barcode = $product->barcode ?? '';
        $this->f_stock_code = $product->stock_code ?? '';
        $this->f_product_name = $product->product_name ?? '';
        $this->f_model_code = $product->model_code ?? '';
        $this->f_brand = $product->brand ?? '';
        $this->f_category_name = $product->category_name ?? '';
        $this->f_color = $product->color ?? '';
        $this->f_size = $product->size ?? '';
        $this->f_variant = $product->variant ?? '';
        $this->f_cogs = $product->cogs;
        $this->f_packaging_cost = $product->packaging_cost;
        $this->f_cargo_cost = $product->cargo_cost;
        $this->f_vat_rate = $product->vat_rate;
        $this->f_market_price = $product->market_price;
        $this->f_sale_price = $product->sale_price;
        $this->f_commission_rate = $product->commission_rate;
        $this->f_stock_quantity = $product->stock_quantity;
        $this->f_desi = $product->desi;
        $this->f_pieces = $product->pieces;
        $this->f_status = $product->status ?? 'active';
        $this->f_platforms = $product->platforms ?? '';
        $this->f_description = $product->description ?? '';
        $this->f_image_url = $product->image_url ?? '';
        $galleryImages = collect($product->image_urls ?? [])
            ->filter(fn ($url) => is_string($url) && trim($url) !== '')
            ->values();
        if ($this->f_image_url !== '' && !$galleryImages->contains($this->f_image_url)) {
            $galleryImages->prepend($this->f_image_url);
        }
        $this->f_image_urls = $galleryImages->all();
        $this->f_image_uploads = [];
        $this->setEditTab('basic');
        $this->edit = $product->id;
        $this->showEditModal = true;
    }

    public function setEditTab(string $tab): void
    {
        if (!in_array($tab, ['basic', 'pricing', 'logistics', 'images'], true)) {
            return;
        }

        $this->editTab = $tab;
        $this->tab = $tab;
    }

    public function openEditProductTab(int $id, string $tab): void
    {
        $this->editProduct($id);
        $this->setEditTab($tab);
    }

    public function addImageUrlField(): void
    {
        $this->f_image_urls[] = '';
    }

    public function removeImageUrlField(int $index): void
    {
        if (!array_key_exists($index, $this->f_image_urls)) {
            return;
        }

        $removedUrl = trim((string) $this->f_image_urls[$index]);

        unset($this->f_image_urls[$index]);
        $this->f_image_urls = array_values($this->f_image_urls);

        if ($removedUrl !== '' && $this->f_image_url === $removedUrl) {
            $this->f_image_url = $this->f_image_urls[0] ?? '';
        }
    }

    public function useGalleryImageAsPrimary(int $index): void
    {
        $url = trim((string) ($this->f_image_urls[$index] ?? ''));

        if ($url === '') {
            return;
        }

        $this->f_image_url = $url;
    }

    public function removePendingImageUpload(int $index): void
    {
        if (!array_key_exists($index, $this->f_image_uploads)) {
            return;
        }

        unset($this->f_image_uploads[$index]);
        $this->f_image_uploads = array_values($this->f_image_uploads);
    }

    public function saveProduct(): void
    {
        $this->validate();

        $uploadedImageUrls = [];

        foreach ($this->f_image_uploads as $upload) {
            $path = $upload->store('mp-products', 'public');
            $uploadedImageUrls[] = Storage::disk('public')->url($path);
        }

        $galleryImages = collect(array_merge($this->f_image_urls, $uploadedImageUrls))
            ->map(fn ($url) => is_string($url) ? trim($url) : '')
            ->filter()
            ->unique()
            ->values();

        $primaryImage = trim($this->f_image_url);

        if ($primaryImage === '' && $galleryImages->isNotEmpty()) {
            $primaryImage = (string) $galleryImages->first();
        }

        if ($primaryImage !== '' && !$galleryImages->contains($primaryImage)) {
            $galleryImages->prepend($primaryImage);
        }

        $data = [
            'user_id' => $this->userId(),
            'barcode' => $this->f_barcode,
            'stock_code' => $this->f_stock_code ?: null,
            'product_name' => $this->f_product_name ?: null,
            'model_code' => $this->f_model_code ?: null,
            'brand' => $this->f_brand ?: null,
            'category_name' => $this->f_category_name ?: null,
            'color' => $this->f_color ?: null,
            'size' => $this->f_size ?: null,
            'variant' => $this->f_variant ?: null,
            'cogs' => $this->f_cogs,
            'packaging_cost' => $this->f_packaging_cost,
            'cargo_cost' => $this->f_cargo_cost,
            'vat_rate' => $this->f_vat_rate,
            'market_price' => $this->f_market_price,
            'sale_price' => $this->f_sale_price,
            'commission_rate' => $this->f_commission_rate,
            'stock_quantity' => $this->f_stock_quantity,
            'desi' => $this->f_desi,
            'pieces' => $this->f_pieces,
            'status' => $this->f_status,
            'platforms' => $this->f_platforms ?: null,
            'description' => $this->f_description ?: null,
            'image_url' => $primaryImage !== '' ? $primaryImage : null,
            'image_urls' => $galleryImages->isNotEmpty() ? $galleryImages->all() : null,
            'import_source' => 'manual_form',
        ];

        if ($this->editingId) {
            $product = MpProduct::where('user_id', $this->userId())->findOrFail($this->editingId);
            $product->update($data);
            session()->flash('success', 'Ürün başarıyla güncellendi.');
        } else {
            MpProduct::create($data);
            session()->flash('success', 'Yeni ürün başarıyla eklendi.');
        }

        $this->closeEditModal();
    }

    public function deleteProduct(int $id): void
    {
        MpProduct::where('user_id', $this->userId())->findOrFail($id)->delete();
        $this->selectedProducts = array_values(array_diff($this->selectedProducts, [(string) $id]));
        $this->syncSelectAllState();
        session()->flash('success', 'Ürün başarıyla silindi.');
    }

    public function duplicateProduct(int $id): void
    {
        $original = MpProduct::where('user_id', $this->userId())->findOrFail($id);
        $clone = $original->replicate();
        $clone->product_name = trim(($original->product_name ?? 'Ürün') . ' (Kopya)');
        $clone->barcode = $this->buildDuplicateUniqueValue('barcode', $original->barcode);

        if (!empty($original->stock_code)) {
            $clone->stock_code = $this->buildDuplicateUniqueValue('stock_code', $original->stock_code);
        }

        $clone->save();
        session()->flash('success', 'Ürün başarıyla çoğaltıldı.');
    }

    public function updateProductStatus(int $id, string $status): void
    {
        if (!in_array($status, ['active', 'out_of_stock', 'pending', 'suspended'], true)) {
            return;
        }

        $product = MpProduct::where('user_id', $this->userId())->findOrFail($id);
        $product->update(['status' => $status]);

        $statusLabel = match ($status) {
            'active' => 'Satışta',
            'out_of_stock' => 'Tükendi',
            'pending' => 'Onay bekliyor',
            'suspended' => 'Beklemede',
            default => $status,
        };

        session()->flash('success', "Ürün durumu {$statusLabel} olarak güncellendi.");
    }

    protected function buildDuplicateUniqueValue(string $field, ?string $originalValue): string
    {
        $base = trim((string) $originalValue);
        if ($base === '') {
            $base = 'KOPYA-' . now()->format('YmdHis');
        }

        $candidate = $base . '-KOPYA';
        $sequence = 2;

        while (
            MpProduct::query()
                ->where('user_id', $this->userId())
                ->where($field, $candidate)
                ->exists()
        ) {
            $candidate = $base . '-KOPYA-' . $sequence;
            $sequence++;
        }

        return $candidate;
    }

    public function updateSalePrice(int $id, $newPrice): void
    {
        $newPrice = max(0, (float) $newPrice);
        $product = MpProduct::where('user_id', $this->userId())->findOrFail($id);
        $product->update(['sale_price' => $newPrice]);
        session()->flash('success', 'Master satış fiyatı güncellendi.');
    }

    public function closeEditModal(): void
    {
        $this->showEditModal = false;
        $this->edit = null;
        $this->tab = 'basic';
        $this->resetForm();
    }

    public function syncListingPrice(int $listingId): void
    {
        if (!config('marketplace.features.listing_push_enabled', true)) {
            session()->flash('warning', 'Listeleme gönderim özelliği şu anda özellik bayrağı ile kapalı.');

            return;
        }

        $listing = $this->resolveListingForUser($listingId);
        $product = $listing->product;

        try {
            $result = app(MarketplaceListingPushService::class)->queuePricePush(
                $listing,
                (float) ($product?->sale_price ?? 0),
                [
                    'list_price' => (float) (($product?->market_price ?: $product?->sale_price) ?: 0),
                    'quantity' => (int) ($product?->stock_quantity ?? 0),
                ],
                $this->userId()
            );

            $feedback = app(MarketplaceListingPushService::class)->feedback(
                $result,
                'fiyat',
                $listing->store?->store_name,
            );

            session()->flash($feedback['tone'] === 'success' ? 'success' : 'warning', $feedback['message']);
        } catch (\Throwable $exception) {
            session()->flash('warning', $exception->getMessage());
        }
    }

    public function syncListingStock(int $listingId): void
    {
        if (!config('marketplace.features.listing_push_enabled', true)) {
            session()->flash('warning', 'Listeleme gönderim özelliği şu anda özellik bayrağı ile kapalı.');

            return;
        }

        $listing = $this->resolveListingForUser($listingId);
        $product = $listing->product;

        try {
            $result = app(MarketplaceListingPushService::class)->queueStockPush(
                $listing,
                (int) ($product?->stock_quantity ?? 0),
                [
                    'sale_price' => (float) (($product?->sale_price) ?: 0),
                    'list_price' => (float) (($product?->market_price ?: $product?->sale_price) ?: 0),
                ],
                $this->userId()
            );

            $feedback = app(MarketplaceListingPushService::class)->feedback(
                $result,
                'stok',
                $listing->store?->store_name,
            );

            session()->flash($feedback['tone'] === 'success' ? 'success' : 'warning', $feedback['message']);
        } catch (\Throwable $exception) {
            session()->flash('warning', $exception->getMessage());
        }
    }

    public function retryPushRun(int $pushRunId): void
    {
        if (!config('marketplace.features.listing_push_enabled', true)) {
            session()->flash('warning', 'Listeleme gönderim özelliği şu anda özellik bayrağı ile kapalı.');

            return;
        }

        $pushRun = $this->resolvePushRunForUser($pushRunId);
        $listing = $pushRun->listing;

        if (!$listing) {
            session()->flash('warning', 'Yeniden denenmek istenen gönderim kaydı için listeleme bulunamadı.');

            return;
        }

        try {
            if ($pushRun->push_type === 'price') {
                $result = app(MarketplaceListingPushService::class)->queuePricePush(
                    $listing,
                    (float) ($pushRun->target_price ?? 0),
                    $pushRun->request_context_json ?? [],
                    $this->userId()
                );
            } else {
                $result = app(MarketplaceListingPushService::class)->queueStockPush(
                    $listing,
                    (int) ($pushRun->target_quantity ?? 0),
                    $pushRun->request_context_json ?? [],
                    $this->userId()
                );
            }

            $feedback = app(MarketplaceListingPushService::class)->feedback(
                $result,
                $pushRun->push_type === 'price' ? 'fiyat' : 'stok',
                $listing->store?->store_name,
            );

            session()->flash($feedback['tone'] === 'success' ? 'success' : 'warning', $feedback['message']);
        } catch (\Throwable $exception) {
            session()->flash('warning', $exception->getMessage());
        }
    }

    public function bulkDelete(): void
    {
        if ($this->selectedProducts === []) {
            return;
        }

        MpProduct::where('user_id', $this->userId())
            ->whereIn('id', $this->selectedProducts)
            ->delete();

        $count = count($this->selectedProducts);
        $this->selectedProducts = [];
        $this->selectAll = false;
        session()->flash('success', "{$count} ürün başarıyla silindi.");
    }

    public function bulkUpdateStatus(string $status): void
    {
        if ($this->selectedProducts === []) {
            return;
        }

        MpProduct::where('user_id', $this->userId())
            ->whereIn('id', $this->selectedProducts)
            ->update(['status' => $status]);

        $count = count($this->selectedProducts);
        $this->selectedProducts = [];
        $this->selectAll = false;
        session()->flash('success', "{$count} ürünün durumu güncellendi.");
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->filterStatus = 'all';
        $this->filterCategory = 'all';
        $this->filterBrand = 'all';
        $this->filterStockLevel = 'all';
        $this->filterCostDefined = 'all';
        $this->marketplaceFilter = 'all';
        $this->listingStatusFilter = 'all';
        $this->listingCoverageFilter = 'all';
        $this->legalEntityFilter = 'all';
        $this->sortField = 'product_name';
        $this->sortDirection = 'asc';
        $this->clearSelection();
        $this->resetPage();
    }

    public function humanMarketplace(?string $marketplace): string
    {
        return (string) (MarketplaceProviderRegistry::get((string) $marketplace)['label'] ?? ucfirst((string) $marketplace));
    }

    public function listingStatusLabel(?string $status): string
    {
        $status = strtolower(trim((string) $status));

        return match (true) {
            $status === '' => 'Durum yok',
            in_array($status, ['active', 'approved', 'live', 'on_sale', 'published'], true) => 'Yayında',
            in_array($status, ['draft'], true) => 'Taslak',
            in_array($status, ['suspended', 'passive'], true) => 'Pasif',
            default => ucfirst((string) $status),
        };
    }

    public function listingStatusTone(?string $status): string
    {
        $status = strtolower(trim((string) $status));

        return match (true) {
            in_array($status, ['active', 'approved', 'live', 'on_sale', 'published'], true) => 'success',
            in_array($status, ['draft'], true) => 'warning',
            in_array($status, ['suspended', 'passive'], true) => 'danger',
            default => 'default',
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
            'listing_completeness' => 'Listeleme tamlığı',
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
            'mp.products' => 'Ürünler',
            'mp.orders' => 'Siparişler',
            default => 'Ürünler',
        };
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function guidanceRoute(array $item): string
    {
        $route = (string) ($item['route'] ?? 'mp.products');
        $storeId = $item['store_id'] ?? null;
        $marketplace = $item['marketplace'] ?? null;

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
            'mp.orders' => route('mp.orders', array_filter([
                'storeFilter' => $storeId,
            ], fn ($value) => $value !== null && $value !== '')),
            default => route('mp.products', array_filter([
                'marketplaceFilter' => filled($marketplace) ? $marketplace : null,
                'legalEntityFilter' => $this->legalEntityFilter !== 'all' ? $this->legalEntityFilter : null,
            ], fn ($value) => $value !== null && $value !== '')),
        };
    }

    public function guidanceFocusLabel(): string
    {
        $topItem = $this->diagnosticsGuidance['items'][0] ?? null;

        if (!$topItem) {
            return 'Odak yok';
        }

        return match ($topItem['category'] ?? null) {
            'legacy_financial_projection' => 'Siparişlere git',
            'product_matching' => 'Sorunlu ürünleri filtrele',
            'listing_completeness' => 'Listeleme boşluklarını filtrele',
            default => 'Listeyi odakla',
        };
    }

    public function guidanceSyncLabel(): string
    {
        $topItem = $this->diagnosticsGuidance['items'][0] ?? null;

        return match ($topItem['category'] ?? null) {
            'legacy_financial_projection' => 'Yansıtma ekranına git',
            default => 'Ürün senkronunu başlat',
        };
    }

    public function focusTopGuidance(): void
    {
        $topItem = $this->diagnosticsGuidance['items'][0] ?? null;

        if (!$topItem) {
            session()->flash('warning', 'Odaklanacak bir diagnostik öneri bulunamadı.');

            return;
        }

        if (($topItem['category'] ?? null) === 'legacy_financial_projection') {
            $this->redirect($this->guidanceRoute($topItem), navigate: true);

            return;
        }

        if (filled($topItem['marketplace'] ?? null)) {
            $this->marketplaceFilter = (string) $topItem['marketplace'];
        }

        $this->listingCoverageFilter = match ($topItem['category'] ?? null) {
            'product_matching' => 'issues',
            'listing_completeness' => 'listed',
            default => 'all',
        };

        $this->resetPage();

        session()->flash('success', 'Ürün listesi en kritik tanı kaydına göre odaklandı.');
    }

    public function syncTopGuidance(): void
    {
        $topItem = $this->diagnosticsGuidance['items'][0] ?? null;

        if (($topItem['category'] ?? null) === 'legacy_financial_projection') {
            $this->redirect($this->guidanceRoute($topItem), navigate: true);

            return;
        }

        $storeId = (int) ($topItem['store_id'] ?? 0);

        if ($storeId <= 0) {
            session()->flash('warning', 'Senkron başlatmak için mağaza bilgisi içeren bir tanı kaydı bulunamadı.');

            return;
        }

        $store = MarketplaceStore::query()
            ->with('connection')
            ->whereKey($storeId)
            ->where('user_id', $this->userId())
            ->first();

        if (!$store || !$store->connection || $store->connection->status === 'draft') {
            session()->flash('warning', 'Önce seçili mağazanın bağlantı bilgilerini tamamlayın.');

            return;
        }

        $result = app(MarketplaceManualSyncDispatchService::class)->dispatch($store, 'products', [
            'options' => [],
            'source' => 'guidance_shortcut',
            'category' => $topItem['category'] ?? null,
            'origin_screen' => 'products',
        ]);

        $feedback = app(MarketplaceManualSyncDispatchService::class)->feedback(
            $result,
            'ürün',
            $store->store_name,
        );

        session()->flash($feedback['tone'] === 'success' ? 'success' : 'warning', $feedback['message']);
    }

    public function render()
    {
        return view('livewire.mp-products-manager', [
            'diagnosticsGuidance' => $this->diagnosticsGuidance,
        ])->layout('layouts.app', [
            'title' => 'Pazaryeri Ürünlerim',
        ]);
    }

    protected function resolveListingForUser(int $listingId): ChannelListing
    {
        return ChannelListing::query()
            ->with(['store.connection', 'store.syncProfile', 'channelProduct', 'product'])
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', $this->userId()))
            ->findOrFail($listingId);
    }

    protected function resolvePushRunForUser(int $pushRunId): IntegrationPushRun
    {
        return IntegrationPushRun::query()
            ->with(['listing.store.syncProfile', 'listing.channelProduct', 'listing.product'])
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', $this->userId()))
            ->findOrFail($pushRunId);
    }

    protected function normalizeVisibleColumns(array $columns): array
    {
        $legacyMap = [
            'maliyet' => 'cogs',
            'karlilik' => 'roi',
        ];

        $hadLegacyPreference = collect($columns)->contains(
            static fn ($column) => array_key_exists((string) $column, $legacyMap)
        );

        $columns = array_map(static fn ($column) => $legacyMap[$column] ?? $column, $columns);
        $valid = array_keys(static::$allColumnDefs);
        $normalized = array_values(array_filter($columns, fn ($column) => in_array($column, $valid, true)));
        $normalized = array_values(array_unique($normalized));

        // Eski "maliyet/karlilik" düzeninden gelen kullanıcıları yeni profesyonel
        // varsayılan tabloya otomatik taşı: COGS/Kargo/KDV/ROI eksik kalmasın.
        if ($hadLegacyPreference) {
            $merged = array_values(array_unique(array_merge($this->visibleColumns, $normalized)));
            $normalized = array_values(array_filter($valid, fn ($column) => in_array($column, $merged, true)));
        }

        return $normalized !== [] ? $normalized : $this->visibleColumns;
    }

    protected function resetTableForQueryChange(): void
    {
        $this->clearSelection();
        $this->resetPage();
    }

    protected function clearSelection(): void
    {
        $this->selectedProducts = [];
        $this->selectAll = false;
    }

    protected function syncSelectAllState(): void
    {
        $currentPageIds = $this->currentPageProductIds();

        if ($currentPageIds === []) {
            $this->selectAll = false;

            return;
        }

        $selected = collect($this->selectedProducts)
            ->map(static fn ($id) => (string) $id)
            ->unique()
            ->values();

        $this->selectAll = collect($currentPageIds)->every(
            static fn (string $id) => $selected->contains($id)
        );
    }

    protected function currentPageProductIds(): array
    {
        return collect($this->products->items())
            ->pluck('id')
            ->map(static fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    protected function activeListingStatuses(): array
    {
        return ['active', 'approved', 'live', 'on_sale', 'published'];
    }

    protected function userId(): int
    {
        $userId = Auth::id();

        if (!$userId) {
            abort(401, 'Oturum bulunamadı. Lütfen tekrar giriş yapın.');
        }

        return (int) $userId;
    }

    protected function cleanExportString(mixed $value): mixed
    {
        return app(\App\Services\ExcelService::class)->cleanString($value);
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->f_barcode = '';
        $this->f_stock_code = '';
        $this->f_product_name = '';
        $this->f_model_code = '';
        $this->f_brand = '';
        $this->f_category_name = '';
        $this->f_color = '';
        $this->f_size = '';
        $this->f_variant = '';
        $this->f_cogs = 0;
        $this->f_packaging_cost = 0;
        $this->f_cargo_cost = 0;
        $this->f_vat_rate = 10;
        $this->f_market_price = 0;
        $this->f_sale_price = 0;
        $this->f_commission_rate = 0;
        $this->f_stock_quantity = 0;
        $this->f_desi = 0;
        $this->f_pieces = 1;
        $this->f_status = 'active';
        $this->f_platforms = '';
        $this->f_description = '';
        $this->f_image_url = '';
        $this->f_image_urls = [];
        $this->f_image_uploads = [];
    }
}
