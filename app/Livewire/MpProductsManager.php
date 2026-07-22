<?php

namespace App\Livewire;

use App\Models\ChannelListing;
use App\Models\ChannelOrderItem;
use App\Models\IntegrationPushRun;
use App\Models\IntegrationSyncRun;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\MpProduct;
use App\Models\MpProductChangeLog;
use App\Models\ProductSet;
use App\Models\ProductSetItem;
use App\Models\ProductMatchIssue;
use App\Models\Recipe;
use App\Models\TrendyolBoosterReview;
use App\Services\Marketplace\MarketplaceDiagnosticsGuidanceService;
use App\Services\Marketplace\MarketplaceListingQualityService;
use App\Services\Marketplace\MarketplaceManualMatchService;
use App\Services\Marketplace\MarketplaceManualSyncDispatchService;
use App\Services\Marketplace\MarketplaceListingPushService;
use App\Services\Marketplace\MarketplaceProviderRegistry;
use App\Services\Marketplace\MarketplaceRiskSignalService;
use App\Services\Marketplace\ProductCreativeStudioService;
use App\Services\MpProductChangeLogger;
use App\Services\MpProductImportService;
use App\Services\MpSettingsService;
use App\Services\ProfitabilityMetric;
use App\Services\ProductCompositionResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
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
        'ek_gider' => 'Ek Gider',
        'stok' => 'Stok',
        'kritik_stok' => 'Kritik Stok',
        'kdv' => 'KDV',
        'maliyet_kdv' => 'Maliyet KDV',
        'desi' => 'Desi',
        'iade' => 'İade',
        'teslimat' => 'Termin',
        'roi' => 'Kârlılık',
        'durum' => 'Durum',
        'islem' => 'İşlem',
    ];

    public static array $sortableColumns = [
        'urun' => 'product_name',
        'kanal' => 'listing_count_metric',
        'fiyat' => 'sale_price',
        'cogs' => 'cogs',
        'kargo' => 'cargo_cost',
        'ek_gider' => 'extra_cost_fixed',
        'stok' => 'stock_quantity',
        'kritik_stok' => 'critical_stock_threshold',
        'kdv' => 'vat_rate',
        'maliyet_kdv' => 'cost_vat_rate',
        'desi' => 'desi',
        'iade' => 'return_rate',
        'teslimat' => 'delivery_term_metric',
        'roi' => 'profit_margin_metric',
        'durum' => 'status',
    ];

    public string $search = '';
    public string $filterStatus = 'all';
    public string $filterCategory = 'all';
    public string $filterBrand = 'all';
    public string $filterStockLevel = 'all';
    public string $filterCostDefined = 'all';
    public string $recipeLinkFilter = 'all';
    public string $setContentFilter = 'all';
    public string $filterProfitComparison = 'all';
    public $filterProfitMargin = null;
    public $filterSalePriceMin = null;
    public $filterSalePriceMax = null;
    public $filterCostMin = null;
    public $filterCostMax = null;
    public $filterStockMin = null;
    public $filterStockMax = null;
    public $filterDesiMin = null;
    public $filterDesiMax = null;
    public $filterReturnRateMin = null;
    public $filterReturnRateMax = null;
    public string $marketplaceFilter = 'all';
    public string $listingStatusFilter = 'all';
    public string $listingCoverageFilter = 'all';
    public string $legalEntityFilter = 'all';
    public ?int $edit = null;
    public string $tab = 'basic';
    public string $sortField = 'product_name';
    public string $sortDirection = 'asc';
    public int $perPage = 25;
    public array $visibleColumns = ['urun', 'kanal', 'fiyat', 'cogs', 'kargo', 'stok', 'teslimat', 'iade', 'roi', 'durum', 'islem'];

    public $importFile;
    public bool $showImportModal = false;
    public bool $importing = false;
    public ?array $importResult = null;
    public $costUpdateFile;
    public bool $showCostUpdateModal = false;
    public bool $costUpdating = false;
    public bool $costUpdateApplyZeroValues = false;
    public ?array $costUpdateResult = null;

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
    public $f_extra_cost_fixed = 0;
    public $f_extra_cost_percentage = 0;
    public $f_vat_rate = 10;
    public $f_cost_vat_rate = null;
    public $f_market_price = 0;
    public $f_sale_price = 0;
    public $f_commission_rate = 0;
    public bool $f_profit_commission_override_enabled = false;
    public $f_stock_quantity = 0;
    public $f_critical_stock_threshold = null;
    public $f_return_rate = null;
    public $f_desi = 0;
    public $f_pieces = 1;
    public string $f_fast_delivery_type = '';
    public string $f_status = 'active';
    public string $f_platforms = '';
    public string $f_description = '';
    public string $f_image_url = '';
    public array $f_image_urls = [];
    public array $f_video_urls = [];
    public array $f_image_uploads = [];
    public array $listingQualityAnalysis = [];
    public string $listingQualityDraftTitle = '';
    public string $listingQualityDraftDescription = '';
    public string $listingQualityFeedback = '';
    public string $creativeStudioInstruction = '';
    public string $creativeStudioAspectRatio = '1:1';
    public array $creativeStudioImage = [];
    public string $creativeStudioFeedback = '';
    public string $creativeStudioVideoInstruction = '';
    public string $creativeStudioVideoAspectRatio = '9:16';
    public array $creativeStudioVideo = [];
    public string $creativeStudioVideoFeedback = '';
    public string $setSearch = '';
    public $setComponentProductId = null;
    public $setComponentQuantity = 1;
    public bool $setIncludeCost = true;
    public bool $setIncludePackaging = true;
    public bool $setIncludeLogistics = true;
    public $setCostOverride = null;
    public $setCargoCostOverride = null;
    public $setDesiOverride = null;
    public $setPiecesOverride = null;
    public string $setCostMode = ProductSet::MODE_SUM_COMPONENTS;
    public string $setLogisticsMode = ProductSet::MODE_SUM_COMPONENTS;

    public array $selectedProducts = [];
    public bool $selectAll = false;
    public $bulkPricePercent = null;
    public string $bulkPriceDirection = 'increase';
    public string $bulkPriceTarget = 'all';
    public $bulkProfitTargetMargin = null;
    public string $bulkProfitTarget = 'all';
    public $bulkPackagingCost = null;
    public $bulkCogs = null;
    public $bulkCargoCost = null;
    public $bulkDesi = null;
    public $bulkPieces = null;
    public $bulkStockQuantity = null;
    public string $bulkStockTarget = 'all';
    public $bulkCriticalStockThreshold = null;
    public bool $showQuickMatchModal = false;
    public ?int $quickMatchProductId = null;
    public string $quickMatchMarketplaceFilter = 'all';
    public string $quickMatchSearch = '';
    public array $currentStatusRefreshRunIds = [];
    public ?string $currentStatusRefreshPollingStartedAt = null;

    // ─── COGS Sihirbazı ──────────────────────────────────────────
    public bool $showCogsWizard = false;
    /** @var array<string, array{cogs: string|null, packaging_cost: string|null, count: int, product_ids: array<int>}> */
    public array $cogsWizardCategories = [];
    /** Kullanıcının her kategori için girdiği cogs ve packaging_cost değerleri */
    public array $cogsWizardInputs = [];
    public ?array $cogsWizardResult = null;
    // ─────────────────────────────────────────────────────────────

    // ─── Akıllı Eşleştirme (ProductMatcher) ──────────────────────
    public bool $showMatchWizard = false;
    /** @var array<int, array{order_stock_code: string, order_product_name: string, order_count: int, suggestions: array}> */
    public array $matchSuggestions = [];
    public bool $matchLoading = false;
    // ─────────────────────────────────────────────────────────────

    protected ?array $productProfitSettingsCache = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'filterStatus' => ['except' => 'all'],
        'filterCategory' => ['except' => 'all'],
        'filterBrand' => ['except' => 'all'],
        'filterStockLevel' => ['except' => 'all'],
        'filterProfitComparison' => ['except' => 'all'],
        'filterProfitMargin' => ['except' => null],
        'filterSalePriceMin' => ['except' => null],
        'filterSalePriceMax' => ['except' => null],
        'filterCostMin' => ['except' => null],
        'filterCostMax' => ['except' => null],
        'filterStockMin' => ['except' => null],
        'filterStockMax' => ['except' => null],
        'filterDesiMin' => ['except' => null],
        'filterDesiMax' => ['except' => null],
        'filterReturnRateMin' => ['except' => null],
        'filterReturnRateMax' => ['except' => null],
        'recipeLinkFilter' => ['except' => 'all'],
        'setContentFilter' => ['except' => 'all'],
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
            'f_extra_cost_fixed' => 'required|numeric|min:0',
            'f_extra_cost_percentage' => 'required|numeric|min:0|max:100',
            'f_vat_rate' => 'required|numeric|in:1,10,20',
            'f_cost_vat_rate' => 'nullable|numeric|min:0|max:100',
            'f_stock_quantity' => 'required|integer|min:0',
            'f_sale_price' => 'required|numeric|min:0',
            'f_market_price' => 'required|numeric|min:0',
            'f_commission_rate' => 'required|numeric|min:0|max:100',
            'f_profit_commission_override_enabled' => 'boolean',
            'f_desi' => 'required|numeric|min:0',
            'f_pieces' => 'required|integer|min:1',
            'f_critical_stock_threshold' => 'nullable|integer|min:0',
            'f_return_rate' => 'nullable|numeric|min:0|max:100',
            'f_fast_delivery_type' => 'nullable|string|max:80',
            'f_status' => 'required|in:active,out_of_stock,pending,suspended',
            'f_image_url' => 'nullable|string|max:2048',
            'f_image_urls.*' => 'nullable|string|max:2048',
            'f_video_urls.*' => 'nullable|string|max:2048',
            'f_image_uploads.*' => 'nullable|image|max:5120',
        ];
    }

    public function mount(): void
    {
        $settings = app(MpSettingsService::class);
        $this->visibleColumns = $this->normalizeVisibleColumns(
            $settings->getArray('marketplace_products.v2.visible_columns', $this->visibleColumns)
        );
        $this->perPage = $settings->getProductsPerPage();

        if (!$settings->getBool('marketplace_products.v2.delivery_terms_column_seeded', false)) {
            if (!in_array('teslimat', $this->visibleColumns, true)) {
                $this->visibleColumns[] = 'teslimat';
                $this->visibleColumns = $this->normalizeVisibleColumns($this->visibleColumns);
            }

            $settings->setMany([
                'marketplace_products.v2.visible_columns' => $this->visibleColumns,
                'marketplace_products.v2.delivery_terms_column_seeded' => true,
            ]);
        }

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

    public function updatingRecipeLinkFilter(): void
    {
        $this->resetTableForQueryChange();
    }

    public function updatingSetContentFilter(): void
    {
        $this->resetTableForQueryChange();
    }

    public function updatingFilterProfitComparison(): void
    {
        $this->resetTableForQueryChange();
    }

    public function updatingFilterProfitMargin(): void
    {
        $this->resetTableForQueryChange();
    }

    public function updatingFilterSalePriceMin(): void
    {
        $this->resetTableForQueryChange();
    }

    public function updatingFilterSalePriceMax(): void
    {
        $this->resetTableForQueryChange();
    }

    public function updatingFilterCostMin(): void
    {
        $this->resetTableForQueryChange();
    }

    public function updatingFilterCostMax(): void
    {
        $this->resetTableForQueryChange();
    }

    public function updatingFilterStockMin(): void
    {
        $this->resetTableForQueryChange();
    }

    public function updatingFilterStockMax(): void
    {
        $this->resetTableForQueryChange();
    }

    public function updatingFilterDesiMin(): void
    {
        $this->resetTableForQueryChange();
    }

    public function updatingFilterDesiMax(): void
    {
        $this->resetTableForQueryChange();
    }

    public function updatingFilterReturnRateMin(): void
    {
        $this->resetTableForQueryChange();
    }

    public function updatingFilterReturnRateMax(): void
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

    public function updatedPerPage(): void
    {
        $this->perPage = app(MpSettingsService::class)->normalizePerPage($this->perPage, 25);
        app(MpSettingsService::class)->set('ui.products_per_page', $this->perPage);
        $this->resetPage();
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
            // COGS Sihirbazı için: maliyeti tanımsız (0 veya null) ürün sayısı
            'missing_cost_products' => (clone $productBase)
                ->where(fn ($q) => $q->whereNull('cogs')->orWhere('cogs', 0))
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
    public function quickMatchProduct(): ?MpProduct
    {
        if (!$this->quickMatchProductId) {
            return null;
        }

        return MpProduct::query()
            ->where('user_id', $this->userId())
            ->find($this->quickMatchProductId);
    }

    #[Computed]
    public function setComponentOptions()
    {
        if (!$this->showEditModal || !$this->editingId) {
            return collect();
        }

        $search = trim($this->setSearch);

        return MpProduct::query()
            ->where('user_id', $this->userId())
            ->whereKeyNot($this->editingId)
            ->when($search !== '', fn (Builder $query) => $query->search($search))
            ->orderByRaw('CASE WHEN product_name IS NULL OR product_name = "" THEN 1 ELSE 0 END')
            ->orderBy('product_name')
            ->limit(25)
            ->get([
                'id',
                'barcode',
                'stock_code',
                'product_name',
                'cogs',
                'packaging_cost',
                'cargo_cost',
                'desi',
                'pieces',
                'stock_quantity',
                'product_type',
            ]);
    }

    #[Computed]
    public function editingSetDefinition(): ?ProductSet
    {
        if (!$this->editingId || !Schema::hasTable('product_sets')) {
            return null;
        }

        return ProductSet::query()
            ->with(['items.componentProduct'])
            ->where('user_id', $this->userId())
            ->where('parent_mp_product_id', $this->editingId)
            ->first();
    }

    #[Computed]
    public function editingSetSummary(): array
    {
        if (!$this->editingId) {
            return [];
        }

        $product = MpProduct::query()
            ->with(['productSet.items.componentProduct.productSet.items.componentProduct'])
            ->where('user_id', $this->userId())
            ->find($this->editingId);

        if (!$product) {
            return [];
        }

        return app(ProductCompositionResolver::class)->resolve($product);
    }

    #[Computed]
    public function quickMatchIssues()
    {
        if (!$this->quickMatchProductId || !$this->showQuickMatchModal) {
            return collect();
        }

        $product = $this->quickMatchProduct;

        if (!$product) {
            return collect();
        }

        $search = trim($this->quickMatchSearch);

        return ProductMatchIssue::query()
            ->with([
                'store:id,user_id,marketplace,store_name',
                'channelListing:id,store_id,channel_product_id,mp_product_id,listing_id,listing_status',
                'channelListing.channelProduct:id,title,stock_code,barcode,brand,category_name',
            ])
            ->where('match_status', 'pending')
            ->whereHas('store', function (Builder $query) {
                $query->where('user_id', $this->userId());

                if ($this->quickMatchMarketplaceFilter !== 'all') {
                    $query->where('marketplace', $this->quickMatchMarketplaceFilter);
                }
            })
            ->when($search !== '', function (Builder $query) use ($search, $product) {
                $query->where(function (Builder $searchQuery) use ($search, $product) {
                    $searchQuery
                        ->where('match_reason', 'like', '%' . $search . '%')
                        ->orWhereHas('channelListing.channelProduct', function (Builder $channelProductQuery) use ($search) {
                            $channelProductQuery
                                ->where('title', 'like', '%' . $search . '%')
                                ->orWhere('stock_code', 'like', '%' . $search . '%')
                                ->orWhere('barcode', 'like', '%' . $search . '%');
                        })
                        ->orWhereJsonContains('candidate_ids_json', (int) $product->id)
                        ->orWhereJsonContains('candidate_ids_json', (string) $product->id);
                });
            })
            ->latest()
            ->limit(100)
            ->get()
            ->filter(function (ProductMatchIssue $issue) use ($product) {
                $candidateIds = collect((array) $issue->candidate_ids_json)
                    ->map(static fn ($id) => (int) $id)
                    ->filter()
                    ->values();

                if ($candidateIds->contains((int) $product->id)) {
                    return true;
                }

                $channelProduct = $issue->channelListing?->channelProduct;

                return $this->normalizeToken($channelProduct?->stock_code) !== ''
                    && $this->normalizeToken($channelProduct?->stock_code) === $this->normalizeToken($product->stock_code);
            })
            ->take(20)
            ->values();
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

    #[Computed]
    public function riskGuidance(): array
    {
        return app(MarketplaceRiskSignalService::class)->guidanceForContext($this->userId(), 'products');
    }

    protected function buildProductsQuery(): Builder
    {
        $profitSettings = $this->productProfitSettings();
        $defaultMarketplace = $profitSettings['default_marketplace'];
        $woocommerceCommissionRate = $profitSettings['woocommerce_commission_rate'];
        $koctasCommissionRate = $profitSettings['koctas_commission_rate'];
        $usesProviderDefault = !in_array($defaultMarketplace, ['average', 'worst'], true);
        $listingCommissionExpression = "CASE WHEN LOWER(marketplace_stores.marketplace) = 'woocommerce' THEN ? WHEN LOWER(marketplace_stores.marketplace) = 'koctas' THEN ? ELSE channel_listings.commission_rate END";
        $hasListingShippingDays = Schema::hasColumn('channel_listings', 'shipping_days');
        $listingShippingDaysSelect = $hasListingShippingDays
            ? 'MIN(channel_listings.shipping_days) as min_shipping_days_metric'
            : 'NULL as min_shipping_days_metric';

        $listingAggregate = ChannelListing::query()
            ->leftJoin('marketplace_stores', 'marketplace_stores.id', '=', 'channel_listings.store_id')
            ->selectRaw("
                channel_listings.mp_product_id,
                COUNT(*) as listing_count_metric,
                SUM(CASE WHEN channel_listings.listing_status IN ('active', 'approved', 'live', 'on_sale', 'onsale', 'published', 'publish', 'enabled') THEN 1 ELSE 0 END) as active_listing_count_metric,
                COALESCE(SUM(channel_listings.stock_quantity), 0) as channel_stock_total_metric,
                MAX(channel_listings.last_synced_at) as latest_listing_sync_at_metric,
                {$listingShippingDaysSelect}
            ")
            ->selectRaw("AVG({$listingCommissionExpression}) as avg_commission_rate_metric", [$woocommerceCommissionRate, $koctasCommissionRate])
            ->selectRaw("MAX({$listingCommissionExpression}) as max_commission_rate_metric", [$woocommerceCommissionRate, $koctasCommissionRate])
            ->when($usesProviderDefault, function ($query) use ($defaultMarketplace, $listingCommissionExpression, $woocommerceCommissionRate, $koctasCommissionRate) {
                $query
                    ->selectRaw(
                        "AVG(CASE WHEN LOWER(marketplace_stores.marketplace) = ? THEN {$listingCommissionExpression} END) as selected_commission_rate_metric",
                        [$defaultMarketplace, $woocommerceCommissionRate, $koctasCommissionRate]
                    )
                    ->selectRaw(
                        'AVG(CASE WHEN LOWER(marketplace_stores.marketplace) = ? THEN channel_listings.sale_price END) as selected_sale_price_metric',
                        [$defaultMarketplace]
                    );
            })
            ->whereNotNull('channel_listings.mp_product_id')
            ->groupBy('channel_listings.mp_product_id');

        $commissionMetricBaseExpression = $usesProviderDefault
            ? 'COALESCE(listing_agg.selected_commission_rate_metric, listing_agg.avg_commission_rate_metric, mp_products.commission_rate, 0)'
            : 'COALESCE(listing_agg.avg_commission_rate_metric, mp_products.commission_rate, 0)';

        $salePriceMetricBaseExpression = $usesProviderDefault
            ? 'COALESCE(listing_agg.selected_sale_price_metric, mp_products.sale_price, 0)'
            : 'COALESCE(mp_products.sale_price, 0)';
        $deliveryTermMetricExpression = 'COALESCE(listing_agg.min_shipping_days_metric, mp_products.shipping_days, 9999)';

        $commissionMetricExpression = "CASE WHEN COALESCE(mp_products.profit_commission_override_enabled, 0) = 1 THEN COALESCE(mp_products.commission_rate, 0) ELSE {$commissionMetricBaseExpression} END";
        $salePriceMetricExpression = "CASE WHEN COALESCE(mp_products.profit_commission_override_enabled, 0) = 1 THEN COALESCE(mp_products.sale_price, 0) ELSE {$salePriceMetricBaseExpression} END";
        $profitMetricExpression = "(
            {$salePriceMetricExpression}
            - (
                COALESCE(mp_products.cogs, 0)
                + COALESCE(mp_products.packaging_cost, 0)
                + COALESCE(mp_products.cargo_cost, 0)
                + COALESCE(mp_products.extra_cost_fixed, 0)
                + ({$salePriceMetricExpression} * (COALESCE(mp_products.extra_cost_percentage, 0) / 100))
            )
            - ({$salePriceMetricExpression} * ({$commissionMetricExpression} / 100))
        )";
        $profitabilityCostMetricExpression = '(COALESCE(mp_products.cogs, 0) + COALESCE(mp_products.packaging_cost, 0))';
        $profitMarginMetricExpression = "CASE WHEN {$profitabilityCostMetricExpression} > 0 THEN (({$profitMetricExpression}) + {$profitabilityCostMetricExpression}) / {$profitabilityCostMetricExpression} ELSE NULL END";

        $issueAggregate = ProductMatchIssue::query()
            ->join('channel_listings', 'channel_listings.id', '=', 'product_match_issues.channel_listing_id')
            ->selectRaw('
                channel_listings.mp_product_id,
                SUM(CASE WHEN product_match_issues.match_status = "pending" THEN 1 ELSE 0 END) as pending_issue_count_metric
            ')
            ->whereNotNull('channel_listings.mp_product_id')
            ->groupBy('channel_listings.mp_product_id');

        $productRecipeAggregate = Recipe::query()
            ->selectRaw('
                user_id,
                mp_product_id,
                COUNT(*) as recipe_count_metric,
                MIN(id) as recipe_id_metric,
                MAX(updated_at) as recipe_updated_at_metric
            ')
            ->where('status', 'active')
            ->whereNotNull('mp_product_id')
            ->groupBy('user_id', 'mp_product_id');

        $hasRecipeStockCode = Schema::hasColumn('recipes', 'stock_code');
        $stockRecipeAggregate = null;

        if ($hasRecipeStockCode) {
            $stockRecipeAggregate = Recipe::query()
                ->selectRaw('
                    user_id,
                    stock_code,
                    COUNT(*) as recipe_count_metric,
                    MIN(id) as recipe_id_metric,
                    MAX(updated_at) as recipe_updated_at_metric
                ')
                ->where('status', 'active')
                ->whereNotNull('stock_code')
                ->where('stock_code', '!=', '')
                ->groupBy('user_id', 'stock_code');
        }

        $recipeCountMetricExpression = $hasRecipeStockCode
            ? 'CASE
                WHEN COALESCE(product_recipe_agg.recipe_count_metric, 0) >= COALESCE(stock_recipe_agg.recipe_count_metric, 0)
                    THEN COALESCE(product_recipe_agg.recipe_count_metric, 0)
                ELSE COALESCE(stock_recipe_agg.recipe_count_metric, 0)
              END'
            : 'COALESCE(product_recipe_agg.recipe_count_metric, 0)';
        $recipeIdMetricExpression = $hasRecipeStockCode
            ? 'COALESCE(product_recipe_agg.recipe_id_metric, stock_recipe_agg.recipe_id_metric)'
            : 'product_recipe_agg.recipe_id_metric';
        $recipeUpdatedAtMetricExpression = $hasRecipeStockCode
            ? 'COALESCE(product_recipe_agg.recipe_updated_at_metric, stock_recipe_agg.recipe_updated_at_metric)'
            : 'product_recipe_agg.recipe_updated_at_metric';

        $query = MpProduct::query()
            ->select([
                'mp_products.*',
                \DB::raw('COALESCE(listing_agg.listing_count_metric, 0) as listing_count_metric'),
                \DB::raw('COALESCE(listing_agg.active_listing_count_metric, 0) as active_listing_count_metric'),
                \DB::raw('COALESCE(listing_agg.channel_stock_total_metric, 0) as channel_stock_total_metric'),
                \DB::raw("{$commissionMetricExpression} as channel_commission_rate_metric"),
                \DB::raw("{$salePriceMetricExpression} as channel_sale_price_metric"),
                \DB::raw('listing_agg.max_commission_rate_metric as max_commission_rate_metric'),
                \DB::raw('listing_agg.latest_listing_sync_at_metric as latest_listing_sync_at_metric'),
                \DB::raw('listing_agg.min_shipping_days_metric as min_shipping_days_metric'),
                \DB::raw("{$deliveryTermMetricExpression} as delivery_term_metric"),
                \DB::raw('COALESCE(issue_agg.pending_issue_count_metric, 0) as pending_issue_count_metric'),
                \DB::raw("{$recipeCountMetricExpression} as active_recipe_count_metric"),
                \DB::raw("{$recipeIdMetricExpression} as active_recipe_id_metric"),
                \DB::raw("{$recipeUpdatedAtMetricExpression} as active_recipe_updated_at_metric"),
                \DB::raw("{$profitMetricExpression} as profit_metric"),
                \DB::raw("{$profitMarginMetricExpression} as profit_margin_metric"),
            ])
            ->leftJoinSub($listingAggregate, 'listing_agg', function ($join) {
                $join->on('listing_agg.mp_product_id', '=', 'mp_products.id');
            })
            ->leftJoinSub($issueAggregate, 'issue_agg', function ($join) {
                $join->on('issue_agg.mp_product_id', '=', 'mp_products.id');
            })
            ->leftJoinSub($productRecipeAggregate, 'product_recipe_agg', function ($join) {
                $join->on('product_recipe_agg.mp_product_id', '=', 'mp_products.id')
                    ->on('product_recipe_agg.user_id', '=', 'mp_products.user_id');
            })
            ->with([
                'productSet.items.componentProduct:id,barcode,stock_code,product_name,cogs,packaging_cost,cargo_cost,desi,pieces,stock_quantity,product_type',
                'channelListings' => fn ($query) => $query
                    ->with([
                        'store:id,user_id,legal_entity_id,marketplace,store_name,store_code,seller_id,is_active',
                        'store.legalEntity:id,name,tax_number',
                        'store.syncProfile:id,store_id,price_push_enabled,stock_push_enabled',
                        'channelProduct:id,store_id,external_product_id,external_parent_id,stock_code,barcode,title,brand,category_name,vat_rate,raw_payload,last_synced_at',
                        'matchIssues' => fn ($issueQuery) => $issueQuery->latest()->limit(3),
                        'pushRuns' => fn ($pushQuery) => $pushQuery->latest('created_at'),
                    ])
                    ->orderBy('store_id')
                    ->orderByDesc('last_synced_at'),
            ])
            ->where('mp_products.user_id', $this->userId());

        if ($stockRecipeAggregate) {
            $query->leftJoinSub($stockRecipeAggregate, 'stock_recipe_agg', function ($join) {
                $join->on('stock_recipe_agg.stock_code', '=', 'mp_products.stock_code')
                    ->on('stock_recipe_agg.user_id', '=', 'mp_products.user_id');
            });
        }

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

        if ($this->recipeLinkFilter === 'linked') {
            $query->whereRaw("{$recipeCountMetricExpression} > 0");
        } elseif ($this->recipeLinkFilter === 'unlinked') {
            $query->whereRaw("{$recipeCountMetricExpression} = 0");
        }

        if ($this->setContentFilter === 'defined') {
            $query->whereHas('productSet.items');
        } elseif ($this->setContentFilter === 'missing') {
            $query->where(function (Builder $query) {
                $query->whereIn('mp_products.product_type', ['set', 'bundle'])
                    ->orWhereHas('productSet');
            })->whereDoesntHave('productSet.items');
        }

        if ($this->filterProfitComparison !== 'all' && is_numeric($this->filterProfitMargin)) {
            $operator = $this->filterProfitComparison === 'above' ? '>=' : '<=';
            $profitabilityMultiplier = 1 + ((float) $this->filterProfitMargin / 100);
            $query->whereRaw("{$profitMarginMetricExpression} {$operator} ?", [$profitabilityMultiplier]);
        }

        if (is_numeric($this->filterSalePriceMin)) {
            $query->whereRaw("{$salePriceMetricExpression} >= ?", [(float) $this->filterSalePriceMin]);
        }

        if (is_numeric($this->filterSalePriceMax)) {
            $query->whereRaw("{$salePriceMetricExpression} <= ?", [(float) $this->filterSalePriceMax]);
        }

        if (is_numeric($this->filterCostMin)) {
            $query->where('mp_products.cogs', '>=', (float) $this->filterCostMin);
        }

        if (is_numeric($this->filterCostMax)) {
            $query->where('mp_products.cogs', '<=', (float) $this->filterCostMax);
        }

        if (is_numeric($this->filterStockMin)) {
            $query->where('mp_products.stock_quantity', '>=', (int) $this->filterStockMin);
        }

        if (is_numeric($this->filterStockMax)) {
            $query->where('mp_products.stock_quantity', '<=', (int) $this->filterStockMax);
        }

        if (is_numeric($this->filterDesiMin)) {
            $query->where('mp_products.desi', '>=', (float) $this->filterDesiMin);
        }

        if (is_numeric($this->filterDesiMax)) {
            $query->where('mp_products.desi', '<=', (float) $this->filterDesiMax);
        }

        if (is_numeric($this->filterReturnRateMin)) {
            $query->where('mp_products.return_rate', '>=', (float) $this->filterReturnRateMin);
        }

        if (is_numeric($this->filterReturnRateMax)) {
            $query->where('mp_products.return_rate', '<=', (float) $this->filterReturnRateMax);
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
                'profit_margin_metric',
                'profit_metric',
                'sale_price',
                'cogs',
                'cargo_cost',
                'extra_cost_fixed',
                'stock_quantity',
                'critical_stock_threshold',
                'cost_vat_rate',
                'desi',
                'return_rate',
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

    public function openCostUpdateModal(): void
    {
        $this->costUpdateFile = null;
        $this->costUpdateResult = null;
        $this->costUpdating = false;
        $this->costUpdateApplyZeroValues = false;
        $this->showCostUpdateModal = true;
    }

    public function closeCostUpdateModal(): void
    {
        $this->showCostUpdateModal = false;
        $this->costUpdateFile = null;
        $this->costUpdateResult = null;
        $this->costUpdating = false;
        $this->costUpdateApplyZeroValues = false;
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

    public function updateCostsFromExcel(): void
    {
        $this->validate([
            'costUpdateFile' => 'required|file|mimes:xlsx,xls|max:10240',
            'costUpdateApplyZeroValues' => 'boolean',
        ]);

        $this->costUpdating = true;

        try {
            $service = new MpProductImportService();
            $this->costUpdateResult = $service->importCostUpdates(
                $this->costUpdateFile,
                $this->costUpdateApplyZeroValues
            );
        } catch (\Throwable $e) {
            $this->costUpdateResult = [
                'success' => false,
                'type' => 'cost_update',
                'message' => 'Hata: ' . $e->getMessage(),
                'imported' => 0,
                'updated' => 0,
                'skipped' => 0,
                'matched' => 0,
                'not_found' => 0,
                'blank_cost' => 0,
                'zero_cost' => 0,
                'errors' => [],
            ];
        }

        $this->costUpdating = false;
        $this->costUpdateFile = null;
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
            'sale_price_min' => is_numeric($this->filterSalePriceMin) ? (float) $this->filterSalePriceMin : null,
            'sale_price_max' => is_numeric($this->filterSalePriceMax) ? (float) $this->filterSalePriceMax : null,
            'cost_min' => is_numeric($this->filterCostMin) ? (float) $this->filterCostMin : null,
            'cost_max' => is_numeric($this->filterCostMax) ? (float) $this->filterCostMax : null,
            'stock_min' => is_numeric($this->filterStockMin) ? (int) $this->filterStockMin : null,
            'stock_max' => is_numeric($this->filterStockMax) ? (int) $this->filterStockMax : null,
            'desi_min' => is_numeric($this->filterDesiMin) ? (float) $this->filterDesiMin : null,
            'desi_max' => is_numeric($this->filterDesiMax) ? (float) $this->filterDesiMax : null,
            'return_rate_min' => is_numeric($this->filterReturnRateMin) ? (float) $this->filterReturnRateMin : null,
            'return_rate_max' => is_numeric($this->filterReturnRateMax) ? (float) $this->filterReturnRateMax : null,
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

        $this->clearListingQualityAnalysis();
        $this->clearCreativeStudioImage();
        $this->clearCreativeStudioVideo();
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
        $this->f_extra_cost_fixed = $product->extra_cost_fixed;
        $this->f_extra_cost_percentage = $product->extra_cost_percentage;
        $this->f_vat_rate = $product->vat_rate;
        $this->f_cost_vat_rate = $product->cost_vat_rate;
        $this->f_market_price = $product->market_price;
        $this->f_sale_price = $product->sale_price;
        $this->f_commission_rate = $product->commission_rate;
        $this->f_profit_commission_override_enabled = (bool) $product->profit_commission_override_enabled;
        $this->f_stock_quantity = $product->stock_quantity;
        $this->f_critical_stock_threshold = $product->critical_stock_threshold;
        $this->f_return_rate = $product->return_rate;
        $this->f_desi = $product->desi;
        $this->f_pieces = $product->pieces;
        $this->f_fast_delivery_type = $product->fast_delivery_type ?? '';
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
        $this->f_video_urls = collect($product->video_urls ?? [])
            ->filter(fn ($url) => is_string($url) && trim($url) !== '')
            ->values()
            ->all();
        $this->f_image_uploads = [];
        $this->loadSetState($product);
        $this->setEditTab('basic');
        $this->edit = $product->id;
        $this->showEditModal = true;
    }

    public function setEditTab(string $tab): void
    {
        if (!in_array($tab, ['basic', 'pricing', 'logistics', 'set', 'images', 'listing_quality'], true)) {
            return;
        }

        if ($tab === 'listing_quality' && ! $this->editingId) {
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

    public function runListingQualityAnalysis(): void
    {
        if (! $this->editingId) {
            session()->flash('warning', 'Listing analizi için önce kayıtlı bir ürün seçin.');

            return;
        }

        $product = MpProduct::query()
            ->where('user_id', $this->userId())
            ->with([
                'channelListings.store:id,user_id,marketplace,store_name,is_active',
                'channelListings.channelProduct:id,store_id,title,brand,category_name,raw_payload',
            ])
            ->findOrFail($this->editingId);

        $reviews = Schema::hasTable('trendyol_booster_reviews')
            ? TrendyolBoosterReview::query()
                ->where('user_id', $this->userId())
                ->where('mp_product_id', $product->id)
                ->whereIn('status', ['approved', 'pending'])
                ->where('is_spam', false)
                ->whereNotNull('comment')
                ->latest('reviewed_at')
                ->limit(300)
                ->get()
            : collect();

        $this->listingQualityAnalysis = app(MarketplaceListingQualityService::class)->analyze(
            $product,
            $reviews,
            [
                'barcode' => $this->f_barcode,
                'stock_code' => $this->f_stock_code,
                'product_name' => $this->f_product_name,
                'model_code' => $this->f_model_code,
                'brand' => $this->f_brand,
                'category_name' => $this->f_category_name,
                'color' => $this->f_color,
                'size' => $this->f_size,
                'variant' => $this->f_variant,
                'description' => $this->f_description,
                'image_url' => $this->f_image_url,
                'image_urls' => $this->f_image_urls,
            ]
        );
        $this->listingQualityDraftTitle = (string) data_get($this->listingQualityAnalysis, 'draft.title', '');
        $this->listingQualityDraftDescription = (string) data_get($this->listingQualityAnalysis, 'draft.description', '');
        $this->listingQualityFeedback = 'Analiz güncellendi. Taslaklar henüz ürün kartına uygulanmadı.';
    }

    public function applyListingQualityTitleDraft(): void
    {
        if (trim($this->listingQualityDraftTitle) === '') {
            return;
        }

        $this->f_product_name = trim($this->listingQualityDraftTitle);
        $this->listingQualityFeedback = 'Başlık taslağı forma uygulandı. Kalıcı olması için ürünü güncelleyin.';
    }

    public function applyListingQualityDescriptionDraft(): void
    {
        if (trim($this->listingQualityDraftDescription) === '') {
            return;
        }

        $this->f_description = trim($this->listingQualityDraftDescription);
        $this->listingQualityFeedback = 'Açıklama taslağı forma uygulandı. Kalıcı olması için ürünü güncelleyin.';
    }

    public function clearListingQualityAnalysis(): void
    {
        $this->listingQualityAnalysis = [];
        $this->listingQualityDraftTitle = '';
        $this->listingQualityDraftDescription = '';
        $this->listingQualityFeedback = '';
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

    public function generateProductCreativeImage(): void
    {
        if (! $this->editingId) {
            $this->creativeStudioFeedback = 'Görsel üretmek için önce kayıtlı bir ürün seçin.';

            return;
        }

        $this->validate([
            'creativeStudioInstruction' => ['nullable', 'string', 'max:600'],
            'creativeStudioAspectRatio' => ['required', 'in:1:1,3:4,4:3,9:16,16:9'],
        ]);

        $product = MpProduct::query()
            ->where('user_id', $this->userId())
            ->findOrFail($this->editingId);

        try {
            $this->creativeStudioImage = app(ProductCreativeStudioService::class)->generateImage(
                $product,
                $this->creativeStudioInstruction,
                $this->creativeStudioAspectRatio,
            );
            $this->creativeStudioFeedback = 'Görsel üretildi. Önizleyin; ana görsele uygulamak için ayrıca onay verin.';
        } catch (\Throwable $exception) {
            report($exception);
            $this->creativeStudioImage = [];
            $this->creativeStudioFeedback = $exception->getMessage();
        }
    }

    public function applyCreativeStudioImage(): void
    {
        $url = trim((string) data_get($this->creativeStudioImage, 'url', ''));
        if ($url === '') {
            return;
        }

        $this->f_image_url = $url;
        $this->f_image_urls = collect($this->f_image_urls)
            ->prepend($url)
            ->filter()
            ->unique()
            ->values()
            ->all();
        $this->creativeStudioFeedback = 'Üretilen görsel forma ana görsel olarak uygulandı. Kalıcı olması için ürünü güncelleyin.';
    }

    public function clearCreativeStudioImage(): void
    {
        $this->creativeStudioImage = [];
        $this->creativeStudioFeedback = '';
    }

    public function generateProductCreativeVideo(): void
    {
        if (! $this->editingId) {
            $this->creativeStudioVideoFeedback = 'Video üretmek için önce kayıtlı bir ürün seçin.';

            return;
        }

        $this->validate([
            'creativeStudioVideoInstruction' => ['nullable', 'string', 'max:600'],
            'creativeStudioVideoAspectRatio' => ['required', 'in:9:16,16:9'],
        ]);
        $product = MpProduct::query()
            ->where('user_id', $this->userId())
            ->findOrFail($this->editingId);

        try {
            $this->creativeStudioVideo = app(ProductCreativeStudioService::class)->generateVideo(
                $product,
                $this->creativeStudioVideoInstruction,
                $this->creativeStudioVideoAspectRatio,
                data_get($this->creativeStudioImage, 'path'),
            );
            $this->creativeStudioVideoFeedback = data_get($this->creativeStudioVideo, 'used_reference_image')
                ? 'Video, üretilen ürün görseli referans alınarak hazırlandı. Kaydetmek için videoyu ürüne uygulayın.'
                : 'Video ürün bilgileriyle hazırlandı. Kaydetmek için videoyu ürüne uygulayın.';
        } catch (\Throwable $exception) {
            report($exception);
            $this->creativeStudioVideo = [];
            $this->creativeStudioVideoFeedback = $exception->getMessage();
        }
    }

    public function applyCreativeStudioVideo(): void
    {
        $url = trim((string) data_get($this->creativeStudioVideo, 'url', ''));
        if ($url === '') {
            return;
        }

        $this->f_video_urls = collect($this->f_video_urls)
            ->prepend($url)
            ->filter()
            ->unique()
            ->values()
            ->all();
        $this->creativeStudioVideoFeedback = 'Video ürün formuna eklendi. Kalıcı olması için ürünü güncelleyin.';
    }

    public function clearCreativeStudioVideo(): void
    {
        $this->creativeStudioVideo = [];
        $this->creativeStudioVideoFeedback = '';
    }

    public function removeProductVideoUrl(int $index): void
    {
        if (! array_key_exists($index, $this->f_video_urls)) {
            return;
        }

        unset($this->f_video_urls[$index]);
        $this->f_video_urls = array_values($this->f_video_urls);
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
            'extra_cost_fixed' => $this->f_extra_cost_fixed,
            'extra_cost_percentage' => $this->f_extra_cost_percentage,
            'vat_rate' => $this->f_vat_rate,
            'cost_vat_rate' => filled($this->f_cost_vat_rate) ? (float) $this->f_cost_vat_rate : null,
            'market_price' => $this->f_market_price,
            'sale_price' => $this->f_sale_price,
            'commission_rate' => $this->f_commission_rate,
            'profit_commission_override_enabled' => (bool) $this->f_profit_commission_override_enabled,
            'stock_quantity' => $this->f_stock_quantity,
            'critical_stock_threshold' => filled($this->f_critical_stock_threshold) ? (int) $this->f_critical_stock_threshold : null,
            'return_rate' => filled($this->f_return_rate) ? (float) $this->f_return_rate : null,
            'return_rate_source' => filled($this->f_return_rate) ? 'manual_form' : null,
            'return_rate_calculated_at' => filled($this->f_return_rate) ? now() : null,
            'desi' => $this->f_desi,
            'pieces' => $this->f_pieces,
            'fast_delivery_type' => $this->f_fast_delivery_type ?: null,
            'status' => $this->f_status,
            'platforms' => $this->f_platforms ?: null,
            'description' => $this->f_description ?: null,
            'image_url' => $primaryImage !== '' ? $primaryImage : null,
            'image_urls' => $galleryImages->isNotEmpty() ? $galleryImages->all() : null,
            'video_urls' => collect($this->f_video_urls)->map(fn ($url) => trim((string) $url))->filter()->unique()->values()->all() ?: null,
            'import_source' => 'manual_form',
        ];

        if ($this->editingId) {
            $product = MpProduct::where('user_id', $this->userId())->findOrFail($this->editingId);
            $logger = app(MpProductChangeLogger::class);
            $beforeSnapshot = $logger->productSnapshot($product);
            $product->update($data);
            $freshProduct = $product->fresh();

            if ($freshProduct) {
                $logger->logProductSnapshotChanges(
                    $freshProduct,
                    $beforeSnapshot,
                    'manual_form',
                    Auth::id(),
                    'Ürün düzenleme formu'
                );

                if (Schema::hasTable('product_sets') && $freshProduct->productSet) {
                    app(ProductCompositionResolver::class)->syncProductTotals($freshProduct);
                    $freshProduct = $freshProduct->fresh();
                }

                app(\App\Services\NotificationCenterService::class)->syncProductStockAlert($freshProduct);
            }

            session()->flash('success', 'Ürün başarıyla güncellendi.');
        } else {
            $product = MpProduct::create($data);
            app(MpProductChangeLogger::class)->logProductCreated(
                $product,
                'manual_create',
                Auth::id(),
                'Yeni ürün kaydı'
            );
            app(\App\Services\NotificationCenterService::class)->syncProductStockAlert($product);
            session()->flash('success', 'Yeni ürün başarıyla eklendi.');
        }

        $this->closeEditModal();
    }

    public function saveSetOptions(): void
    {
        if (!$this->editingId) {
            session()->flash('warning', 'Set içeriği tanımlamak için önce ürünü kaydedin.');

            return;
        }

        if (!in_array($this->setCostMode, [ProductSet::MODE_SUM_COMPONENTS, ProductSet::MODE_MANUAL_PARENT], true)) {
            $this->setCostMode = ProductSet::MODE_SUM_COMPONENTS;
        }

        if (!in_array($this->setLogisticsMode, [ProductSet::MODE_SUM_COMPONENTS, ProductSet::MODE_MANUAL_PARENT], true)) {
            $this->setLogisticsMode = ProductSet::MODE_SUM_COMPONENTS;
        }

        $product = MpProduct::query()
            ->where('user_id', $this->userId())
            ->findOrFail($this->editingId);

        $set = $this->ensureProductSet($product);
        $set->forceFill([
            'cost_mode' => $this->setCostMode,
            'logistics_mode' => $this->setLogisticsMode,
            'status' => ProductSet::STATUS_ACTIVE,
        ])->save();

        app(ProductCompositionResolver::class)->syncProductTotals($product->fresh() ?: $product);

        session()->flash('success', 'Set hesaplama ayarları güncellendi.');
    }

    public function addSetComponent(): void
    {
        if (!$this->editingId) {
            session()->flash('warning', 'Bileşen eklemek için önce ürünü kaydedin.');

            return;
        }

        $this->validate([
            'setComponentProductId' => 'required|integer',
            'setComponentQuantity' => 'required|numeric|min:0.001',
            'setCostOverride' => 'nullable|numeric|min:0',
            'setCargoCostOverride' => 'nullable|numeric|min:0',
            'setDesiOverride' => 'nullable|numeric|min:0',
            'setPiecesOverride' => 'nullable|integer|min:1',
        ], [], [
            'setComponentProductId' => 'bileşen ürün',
            'setComponentQuantity' => 'miktar',
            'setCostOverride' => 'maliyet override',
            'setCargoCostOverride' => 'kargo override',
            'setDesiOverride' => 'desi override',
            'setPiecesOverride' => 'parça override',
        ]);

        $parent = MpProduct::query()
            ->where('user_id', $this->userId())
            ->findOrFail($this->editingId);

        $component = MpProduct::query()
            ->where('user_id', $this->userId())
            ->whereKey((int) $this->setComponentProductId)
            ->firstOrFail();

        $resolver = app(ProductCompositionResolver::class);

        if ($resolver->wouldCreateCycle($parent, $component)) {
            session()->flash('warning', 'Bu bileşen set içinde döngü oluşturacağı için eklenmedi.');

            return;
        }

        $set = $this->ensureProductSet($parent);
        $quantity = round((float) $this->setComponentQuantity, 3);

        $item = $set->items()
            ->where('component_mp_product_id', $component->id)
            ->first();

        $data = [
            'quantity' => $item ? round((float) $item->quantity + $quantity, 3) : $quantity,
            'include_cost' => $this->setIncludeCost,
            'include_packaging' => $this->setIncludePackaging,
            'include_logistics' => $this->setIncludeLogistics,
            'cost_override' => filled($this->setCostOverride) ? (float) $this->setCostOverride : null,
            'cargo_cost_override' => filled($this->setCargoCostOverride) ? (float) $this->setCargoCostOverride : null,
            'desi_override' => filled($this->setDesiOverride) ? (float) $this->setDesiOverride : null,
            'pieces_override' => filled($this->setPiecesOverride) ? (int) $this->setPiecesOverride : null,
        ];

        if ($item) {
            $item->update($data);
        } else {
            $set->items()->create($data + [
                'component_mp_product_id' => $component->id,
                'sort_order' => ((int) $set->items()->max('sort_order')) + 10,
            ]);
        }

        $resolver->syncProductTotals($parent->fresh() ?: $parent);
        $this->resetSetComponentForm();

        session()->flash('success', 'Set bileşeni eklendi ve toplamlar güncellendi.');
    }

    public function updateSetItemQuantity(int $itemId, mixed $quantity): void
    {
        if (!$this->editingId) {
            return;
        }

        $quantity = max(0.001, round((float) $quantity, 3));
        $item = $this->resolveSetItemForEditingProduct($itemId);
        $item->update(['quantity' => $quantity]);

        $product = MpProduct::query()
            ->where('user_id', $this->userId())
            ->findOrFail($this->editingId);

        app(ProductCompositionResolver::class)->syncProductTotals($product);
        session()->flash('success', 'Bileşen miktarı güncellendi.');
    }

    public function removeSetComponent(int $itemId): void
    {
        if (!$this->editingId) {
            return;
        }

        $item = $this->resolveSetItemForEditingProduct($itemId);
        $item->delete();

        $product = MpProduct::query()
            ->where('user_id', $this->userId())
            ->findOrFail($this->editingId);

        app(ProductCompositionResolver::class)->syncProductTotals($product);
        session()->flash('success', 'Set bileşeni kaldırıldı.');
    }

    public function refreshSetTotals(): void
    {
        if (!$this->editingId) {
            return;
        }

        $product = MpProduct::query()
            ->where('user_id', $this->userId())
            ->findOrFail($this->editingId);

        app(ProductCompositionResolver::class)->syncProductTotals($product);
        session()->flash('success', 'Set toplamları bileşenlerden yeniden hesaplandı.');
    }

    public function clearSetDefinition(): void
    {
        if (!$this->editingId) {
            return;
        }

        $product = MpProduct::query()
            ->where('user_id', $this->userId())
            ->findOrFail($this->editingId);

        ProductSet::query()
            ->where('user_id', $this->userId())
            ->where('parent_mp_product_id', $product->id)
            ->delete();

        $product->forceFill([
            'product_type' => 'single',
            'cost_source' => $product->cost_source === 'set' ? 'manual' : $product->cost_source,
            'logistics_source' => $product->logistics_source === 'set' ? 'manual' : $product->logistics_source,
        ])->save();

        $this->loadSetState($product->fresh() ?: $product);
        session()->flash('success', 'Ürün tekil ürün moduna alındı.');
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

    public function refreshCurrentStatus(int $id): void
    {
        $productIds = MpProduct::query()
            ->where('user_id', $this->userId())
            ->whereKey($id)
            ->pluck('id')
            ->map(static fn ($productId) => (int) $productId)
            ->all();

        if ($productIds === []) {
            session()->flash('warning', 'Güncel durum alınacak ürün bulunamadı.');

            return;
        }

        $summary = $this->dispatchCurrentStatusRefresh($productIds, true);

        $this->trackCurrentStatusRefreshRuns($summary);
        $this->flashCurrentStatusRefreshSummary($summary);
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
        $logger = app(MpProductChangeLogger::class);
        $beforeSnapshot = $logger->productSnapshot($product);
        $product->update(['sale_price' => $newPrice]);
        $logger->logProductSnapshotChanges(
            $product->fresh() ?: $product,
            $beforeSnapshot,
            'inline_edit',
            Auth::id(),
            'Ana satış fiyatı hızlı düzenleme'
        );
        session()->flash('success', 'Ana satış fiyatı güncellendi.');
    }

    public function updateInlineField(int $id, string $field, mixed $value): void
    {
        $allowed = [
            'sale_price' => ['type' => 'decimal', 'label' => 'satış fiyatı'],
            'market_price' => ['type' => 'decimal', 'label' => 'piyasa fiyatı'],
            'cogs' => ['type' => 'decimal', 'label' => 'birim maliyet'],
            'packaging_cost' => ['type' => 'decimal', 'label' => 'ambalaj gideri'],
            'cargo_cost' => ['type' => 'decimal', 'label' => 'kargo maliyeti'],
            'extra_cost_fixed' => ['type' => 'decimal', 'label' => 'ek gider'],
            'extra_cost_percentage' => ['type' => 'percent', 'label' => 'ek gider oranı'],
            'vat_rate' => ['type' => 'percent', 'label' => 'KDV oranı'],
            'cost_vat_rate' => ['type' => 'nullable_percent', 'label' => 'maliyet KDV oranı'],
            'stock_quantity' => ['type' => 'integer', 'label' => 'stok adedi'],
            'critical_stock_threshold' => ['type' => 'nullable_integer', 'label' => 'kritik stok eşiği'],
            'desi' => ['type' => 'decimal', 'label' => 'desi'],
            'pieces' => ['type' => 'positive_integer', 'label' => 'parça sayısı'],
            'return_rate' => ['type' => 'nullable_percent', 'label' => 'iade oranı'],
            'fast_delivery_type' => ['type' => 'nullable_string', 'label' => 'teslimat tipi'],
        ];

        if (!isset($allowed[$field])) {
            session()->flash('warning', 'Bu alan hızlı düzenleme için açık değil.');

            return;
        }

        $product = MpProduct::query()
            ->where('user_id', $this->userId())
            ->findOrFail($id);

        $normalized = $this->normalizeInlineValue($value, $allowed[$field]['type']);

        $updates = [$field => $normalized];

        if ($field === 'return_rate') {
            $updates['return_rate_source'] = $normalized === null ? null : 'manual_inline';
            $updates['return_rate_calculated_at'] = $normalized === null ? null : now();
        }

        $logger = app(MpProductChangeLogger::class);
        $beforeSnapshot = $logger->productSnapshot($product);
        $product->update($updates);

        $freshProduct = $product->fresh();

        if ($freshProduct) {
            $logger->logProductSnapshotChanges(
                $freshProduct,
                $beforeSnapshot,
                'inline_edit',
                Auth::id(),
                'Satır içi hızlı düzenleme'
            );
        }

        if ($freshProduct && in_array($field, ['stock_quantity', 'critical_stock_threshold'], true)) {
            app(\App\Services\NotificationCenterService::class)->syncProductStockAlert($freshProduct);
        }

        session()->flash('success', 'Ürün ' . $allowed[$field]['label'] . ' güncellendi.');
    }

    public function refreshReturnRates(): void
    {
        $productIds = $this->selectedProductIdsForBulkAction();

        if ($productIds === []) {
            $productIds = $this->currentPageProductIds();
        }

        $productIds = collect($productIds)
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($productIds === []) {
            session()->flash('warning', 'İade oranı hesaplanacak ürün bulunamadı.');

            return;
        }

        $metrics = ChannelOrderItem::query()
            ->join('channel_orders', 'channel_orders.id', '=', 'channel_order_items.channel_order_id')
            ->whereIn('channel_order_items.mp_product_id', $productIds)
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', $this->userId()))
            ->groupBy('channel_order_items.mp_product_id')
            ->selectRaw('channel_order_items.mp_product_id')
            ->selectRaw('SUM(CASE WHEN COALESCE(channel_order_items.quantity, 1) > 1 THEN COALESCE(channel_order_items.quantity, 1) ELSE 1 END) as total_quantity')
            ->selectRaw("
                SUM(
                    CASE
                        WHEN channel_orders.returned_at IS NOT NULL
                            OR LOWER(COALESCE(channel_orders.order_status, '')) LIKE '%return%'
                            OR LOWER(COALESCE(channel_orders.order_status, '')) LIKE '%iade%'
                            OR LOWER(COALESCE(channel_order_items.line_status, '')) LIKE '%return%'
                            OR LOWER(COALESCE(channel_order_items.line_status, '')) LIKE '%iade%'
                        THEN CASE WHEN COALESCE(channel_order_items.quantity, 1) > 1 THEN COALESCE(channel_order_items.quantity, 1) ELSE 1 END
                        ELSE 0
                    END
                ) as returned_quantity
            ")
            ->get()
            ->keyBy('mp_product_id');

        $updated = 0;
        $logger = app(MpProductChangeLogger::class);
        $beforeSnapshots = $logger->productSnapshotsForIds($this->userId(), $productIds);
        $batchId = $logger->batchId('return_rate_refresh');

        foreach ($productIds as $productId) {
            $metric = $metrics->get($productId);

            if (!$metric || (int) $metric->total_quantity <= 0) {
                continue;
            }

            $returnRate = round(((float) $metric->returned_quantity / max(1, (float) $metric->total_quantity)) * 100, 2);

            MpProduct::query()
                ->where('user_id', $this->userId())
                ->whereKey($productId)
                ->update([
                    'return_rate' => $returnRate,
                    'return_rate_source' => 'orders',
                    'return_rate_calculated_at' => now(),
                ]);

            $freshProduct = MpProduct::query()
                ->where('user_id', $this->userId())
                ->whereKey($productId)
                ->first();

            if ($freshProduct) {
                $logger->logProductSnapshotChanges(
                    $freshProduct,
                    $beforeSnapshots[$productId] ?? [],
                    'return_rate_refresh',
                    Auth::id(),
                    'Sipariş geçmişinden iade oranı hesaplama',
                    $batchId
                );
            }

            $updated++;
        }

        $this->clearSelection();

        session()->flash(
            $updated > 0 ? 'success' : 'warning',
            $updated > 0
                ? "{$updated} ürün için iade oranı sipariş geçmişinden güncellendi."
                : 'Seçili ürünlerde iade oranı hesaplanacak sipariş geçmişi bulunamadı.'
        );
    }

    public function openQuickMatchModal(int $id): void
    {
        $product = MpProduct::query()
            ->where('user_id', $this->userId())
            ->findOrFail($id);

        $this->quickMatchProductId = $product->id;
        $this->quickMatchSearch = (string) ($product->stock_code ?: $product->barcode ?: $product->product_name);
        $this->quickMatchMarketplaceFilter = $this->marketplaceFilter !== 'all' ? $this->marketplaceFilter : 'all';
        $this->showQuickMatchModal = true;
    }

    public function closeQuickMatchModal(): void
    {
        $this->showQuickMatchModal = false;
        $this->quickMatchProductId = null;
        $this->quickMatchSearch = '';
        $this->quickMatchMarketplaceFilter = 'all';
    }

    public function quickMatchIssue(int $issueId): void
    {
        $product = $this->quickMatchProduct;

        if (!$product) {
            session()->flash('warning', 'Eşleştirilecek ürün bulunamadı.');

            return;
        }

        $issue = ProductMatchIssue::query()
            ->with(['store', 'channelListing.channelProduct'])
            ->whereKey($issueId)
            ->where('match_status', 'pending')
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', $this->userId()))
            ->firstOrFail();

        try {
            $result = app(MarketplaceManualMatchService::class)->manualMatch($issue, $product, $this->userId());
            session()->flash('success', 'Ürün eşleşmesi tamamlandı. ' . $result['updated_items'] . ' sipariş satırı güncellendi.');
            $this->closeQuickMatchModal();
        } catch (\Throwable $exception) {
            session()->flash('warning', $exception->getMessage());
        }
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

        $productIds = $this->selectedProductIdsForBulkAction();

        if ($productIds === []) {
            $this->clearSelection();
            session()->flash('warning', 'Seçili ürünler bulunamadı.');

            return;
        }

        $logger = app(MpProductChangeLogger::class);
        $beforeSnapshots = $logger->productSnapshotsForIds($this->userId(), $productIds);
        $batchId = $logger->batchId('bulk_status_update');

        MpProduct::where('user_id', $this->userId())
            ->whereIn('id', $productIds)
            ->update(['status' => $status]);

        $logger->logProductChangesForIds(
            $productIds,
            $beforeSnapshots,
            'bulk_status_update',
            Auth::id(),
            'Toplu ürün durumu güncelleme',
            $batchId
        );

        $count = count($productIds);
        $this->selectedProducts = [];
        $this->selectAll = false;
        session()->flash('success', "{$count} ürünün durumu güncellendi.");
    }

    public function bulkSetProfitCommissionOverride(bool $enabled): void
    {
        if ($this->selectedProducts === []) {
            return;
        }

        $productIds = $this->selectedProductIdsForBulkAction();

        if ($productIds === []) {
            $this->clearSelection();
            session()->flash('warning', 'Seçili ürünler bulunamadı.');

            return;
        }

        $logger = app(MpProductChangeLogger::class);
        $beforeSnapshots = $logger->productSnapshotsForIds($this->userId(), $productIds);
        $batchId = $logger->batchId('bulk_commission_override');

        MpProduct::where('user_id', $this->userId())
            ->whereIn('id', $productIds)
            ->update(['profit_commission_override_enabled' => $enabled]);

        $logger->logProductChangesForIds(
            $productIds,
            $beforeSnapshots,
            'bulk_commission_override',
            Auth::id(),
            'Toplu manuel komisyon kâr hesabı ayarı',
            $batchId
        );

        $count = count($productIds);
        $this->selectedProducts = [];
        $this->selectAll = false;

        $message = $enabled
            ? "{$count} üründe manuel komisyon kâr hesabına açıldı."
            : "{$count} üründe manuel komisyon kâr hesabından çıkarıldı.";

        session()->flash('success', $message);
    }

    public function bulkRefreshCurrentStatus(): void
    {
        if ($this->selectedProducts === []) {
            return;
        }

        $productIds = $this->selectedProductIdsForBulkAction();

        if ($productIds === []) {
            $this->clearSelection();
            session()->flash('warning', 'Güncel durum alınacak seçili ürün bulunamadı.');

            return;
        }

        $summary = $this->dispatchCurrentStatusRefresh($productIds);

        if (($summary['queued'] + $summary['completed'] + $summary['debounced'] + count($summary['skipped'])) > 0) {
            $this->clearSelection();
        }

        $this->trackCurrentStatusRefreshRuns($summary);
        $this->flashCurrentStatusRefreshSummary($summary);
    }

    public function pollCurrentStatusRefresh(): void
    {
        $runIds = collect($this->currentStatusRefreshRunIds)
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($runIds === []) {
            $this->currentStatusRefreshRunIds = [];
            $this->currentStatusRefreshPollingStartedAt = null;

            return;
        }

        if (
            filled($this->currentStatusRefreshPollingStartedAt)
            && now()->diffInMinutes(\Illuminate\Support\Carbon::parse($this->currentStatusRefreshPollingStartedAt)) >= 5
        ) {
            $this->currentStatusRefreshRunIds = [];
            $this->currentStatusRefreshPollingStartedAt = null;
            session()->flash('warning', 'Güncel durum işlemi hâlâ kuyrukta görünüyor. Senkron tamamlandığında tablo bir sonraki yenilemede güncellenecek.');

            return;
        }

        $runs = IntegrationSyncRun::query()
            ->whereIn('id', $runIds)
            ->get(['id', 'status']);

        $activeRunIds = $runs
            ->whereIn('status', ['queued', 'processing', 'retrying'])
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->values()
            ->all();

        $this->currentStatusRefreshRunIds = $activeRunIds;

        if ($activeRunIds === []) {
            $this->currentStatusRefreshPollingStartedAt = null;
            $this->resetPage();

            $failedCount = $runs->whereIn('status', ['failed', 'skipped'])->count();
            $message = $failedCount > 0
                ? "{$failedCount} mağazada güncel durum alınamadı; tamamlanan kayıtlar tabloya yansıtıldı."
                : 'Güncel durum tamamlandı. Tablo yenilendi.';

            session()->flash($failedCount > 0 ? 'warning' : 'success', $message);
        }
    }

    public function bulkSetStockQuantity(): void
    {
        if ($this->selectedProducts === []) {
            return;
        }

        $this->validate([
            'bulkStockQuantity' => ['required', 'integer', 'min:0'],
            'bulkStockTarget' => ['required', 'string', 'max:160'],
        ], [], [
            'bulkStockQuantity' => 'stok adedi',
            'bulkStockTarget' => 'stok hedefi',
        ]);

        $quantity = (int) $this->bulkStockQuantity;
        $target = trim((string) $this->bulkStockTarget);
        $productIds = $this->selectedProductIdsForBulkAction();

        if ($productIds === []) {
            $this->clearSelection();
            session()->flash('warning', 'Seçili ürünler bulunamadı.');

            return;
        }

        if ($target === 'all') {
            $listingQuery = $this->bulkListingsQuery($productIds);
            $listingIds = (clone $listingQuery)
                ->pluck('id')
                ->map(static fn ($id) => (int) $id)
                ->all();
            $logger = app(MpProductChangeLogger::class);
            $batchId = $logger->batchId('bulk_stock_update');
            $productBeforeSnapshots = $logger->productSnapshotsForIds($this->userId(), $productIds);
            $listingBeforeSnapshots = $logger->listingSnapshotsForIds($listingIds, $this->userId());

	            \DB::transaction(function () use ($productIds, $quantity, $listingQuery) {
	                MpProduct::query()
	                    ->where('user_id', $this->userId())
	                    ->whereIn('id', $productIds)
	                    ->update(['stock_quantity' => $quantity]);

	                $listingQuery->update(['stock_quantity' => $quantity]);
	            });

	            app(ProductCompositionResolver::class)->refreshParentSetsForProductIds($productIds);

            $logger->logProductChangesForIds(
                $productIds,
                $productBeforeSnapshots,
                'bulk_stock_update',
                Auth::id(),
                'Toplu ana ve kanal stok güncelleme',
                $batchId
            );
            $logger->logListingChangesForIds(
                $listingIds,
                $listingBeforeSnapshots,
                'bulk_stock_update',
                Auth::id(),
                'Toplu ana ve kanal stok güncelleme',
                $batchId
            );

            $productCount = count($productIds);
            $listingCount = count($listingIds);

            $this->syncBulkProductStockAlerts($productIds);
            $this->syncBulkListingStockAlerts($listingIds);
            $this->resetBulkStockForm();
            $this->clearSelection();

            session()->flash('success', "{$productCount} ürünün ana stoku ve {$listingCount} kanal stoku {$quantity} olarak güncellendi.");

            return;
        }

        if (!Str::startsWith($target, 'marketplace:')) {
            $this->addError('bulkStockTarget', 'Geçerli bir stok hedefi seçin.');

            return;
        }

        $marketplace = trim(Str::after($target, 'marketplace:'));

        if ($marketplace === '' || ! $this->userMarketplaceExists($marketplace)) {
            $this->addError('bulkStockTarget', 'Geçerli bir pazaryeri seçin.');

            return;
        }

        $listingQuery = $this->bulkListingsQuery($productIds, $marketplace);
        $listingIds = (clone $listingQuery)
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        if ($listingIds === []) {
            session()->flash('warning', 'Seçili ürünlerde bu pazaryerine bağlı stok kaydı bulunamadı.');

            return;
        }

        $logger = app(MpProductChangeLogger::class);
        $batchId = $logger->batchId('bulk_stock_update');
        $listingBeforeSnapshots = $logger->listingSnapshotsForIds($listingIds, $this->userId());

        $listingQuery->update(['stock_quantity' => $quantity]);

        $logger->logListingChangesForIds(
            $listingIds,
            $listingBeforeSnapshots,
            'bulk_stock_update',
            Auth::id(),
            'Toplu kanal stok güncelleme',
            $batchId,
            ['marketplace' => $marketplace]
        );

        $this->syncBulkListingStockAlerts($listingIds);
        $this->resetBulkStockForm();
        $this->clearSelection();

        session()->flash('success', count($listingIds) . ' ' . $this->humanMarketplace($marketplace) . " stok kaydı {$quantity} olarak güncellendi.");
    }

    public function bulkAdjustSalePrices(): void
    {
        if ($this->selectedProducts === []) {
            return;
        }

        $this->validate([
            'bulkPricePercent' => ['required', 'numeric', 'min:0', 'max:1000'],
            'bulkPriceDirection' => ['required', 'in:increase,decrease'],
            'bulkPriceTarget' => ['required', 'string', 'max:160'],
        ], [], [
            'bulkPricePercent' => 'fiyat yüzdesi',
            'bulkPriceDirection' => 'fiyat yönü',
            'bulkPriceTarget' => 'fiyat hedefi',
        ]);

        $percent = (float) $this->bulkPricePercent;

        if ($this->bulkPriceDirection === 'decrease' && $percent > 100) {
            $this->addError('bulkPricePercent', 'Fiyat düşürme oranı %100 değerini aşamaz.');

            return;
        }

        $target = trim((string) $this->bulkPriceTarget);
        $productIds = $this->selectedProductIdsForBulkAction();

        if ($productIds === []) {
            $this->clearSelection();
            session()->flash('warning', 'Seçili ürünler bulunamadı.');

            return;
        }

        $factor = $this->bulkPriceDirection === 'increase'
            ? 1 + ($percent / 100)
            : 1 - ($percent / 100);
        $directionLabel = $this->bulkPriceDirection === 'increase' ? 'artırıldı' : 'düşürüldü';
        $percentLabel = $this->formatBulkPercent($percent);

        if ($target === 'all') {
            $listingQuery = $this->bulkListingsQuery($productIds);
            $listingIds = (clone $listingQuery)
                ->pluck('id')
                ->map(static fn ($id) => (int) $id)
                ->all();
            $logger = app(MpProductChangeLogger::class);
            $batchId = $logger->batchId('bulk_price_update');
            $productBeforeSnapshots = $logger->productSnapshotsForIds($this->userId(), $productIds);
            $listingBeforeSnapshots = $logger->listingSnapshotsForIds($listingIds, $this->userId());

            \DB::transaction(function () use ($productIds, $listingQuery, $factor) {
                MpProduct::query()
                    ->where('user_id', $this->userId())
                    ->whereIn('id', $productIds)
                    ->update(['sale_price' => $this->percentagePriceExpression('sale_price', $factor)]);

                $listingQuery->update(['sale_price' => $this->percentagePriceExpression('sale_price', $factor)]);
            });

            $logger->logProductChangesForIds(
                $productIds,
                $productBeforeSnapshots,
                'bulk_price_update',
                Auth::id(),
                "Toplu fiyat %{$percentLabel} {$directionLabel}",
                $batchId
            );
            $logger->logListingChangesForIds(
                $listingIds,
                $listingBeforeSnapshots,
                'bulk_price_update',
                Auth::id(),
                "Toplu kanal fiyatı %{$percentLabel} {$directionLabel}",
                $batchId
            );

            $productCount = count($productIds);
            $listingCount = count($listingIds);

            $this->resetBulkPriceForm();
            $this->clearSelection();

            session()->flash('success', "{$productCount} ürünün ana satış fiyatı ve {$listingCount} kanal fiyatı %{$percentLabel} {$directionLabel}.");

            return;
        }

        if (!Str::startsWith($target, 'marketplace:')) {
            $this->addError('bulkPriceTarget', 'Geçerli bir fiyat hedefi seçin.');

            return;
        }

        $marketplace = trim(Str::after($target, 'marketplace:'));

        if ($marketplace === '' || ! $this->userMarketplaceExists($marketplace)) {
            $this->addError('bulkPriceTarget', 'Geçerli bir pazaryeri seçin.');

            return;
        }

        $listingQuery = $this->bulkListingsQuery($productIds, $marketplace);
        $listingIds = (clone $listingQuery)
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        if ($listingIds === []) {
            session()->flash('warning', 'Seçili ürünlerde bu pazaryerine bağlı fiyat kaydı bulunamadı.');

            return;
        }

        $logger = app(MpProductChangeLogger::class);
        $batchId = $logger->batchId('bulk_price_update');
        $listingBeforeSnapshots = $logger->listingSnapshotsForIds($listingIds, $this->userId());

        $listingQuery->update(['sale_price' => $this->percentagePriceExpression('sale_price', $factor)]);

        $logger->logListingChangesForIds(
            $listingIds,
            $listingBeforeSnapshots,
            'bulk_price_update',
            Auth::id(),
            "Toplu {$this->humanMarketplace($marketplace)} fiyatı %{$percentLabel} {$directionLabel}",
            $batchId,
            ['marketplace' => $marketplace]
        );

        $this->resetBulkPriceForm();
        $this->clearSelection();

        session()->flash('success', count($listingIds) . ' ' . $this->humanMarketplace($marketplace) . " fiyat kaydı %{$percentLabel} {$directionLabel}.");
    }

    public function bulkSetTargetProfitMargin(): void
    {
        if ($this->selectedProducts === []) {
            return;
        }

        $this->validate([
            'bulkProfitTargetMargin' => ['required', 'numeric', 'min:-99', 'max:1000'],
            'bulkProfitTarget' => ['required', 'string', 'max:160'],
        ], [], [
            'bulkProfitTargetMargin' => 'hedef kârlılık yüzdesi',
            'bulkProfitTarget' => 'kârlılık hedefi',
        ]);

        $targetMarginPercent = (float) $this->bulkProfitTargetMargin;
        $targetProfitabilityMultiplier = 1 + ($targetMarginPercent / 100);
        $target = trim((string) $this->bulkProfitTarget);
        $productIds = $this->selectedProductIdsForBulkAction();

        if ($productIds === []) {
            $this->clearSelection();
            session()->flash('warning', 'Seçili ürünler bulunamadı.');

            return;
        }

        $marketplace = null;

        if ($target !== 'all') {
            if (!Str::startsWith($target, 'marketplace:')) {
                $this->addError('bulkProfitTarget', 'Geçerli bir kârlılık hedefi seçin.');

                return;
            }

            $marketplace = trim(Str::after($target, 'marketplace:'));

            if ($marketplace === '' || ! $this->userMarketplaceExists($marketplace)) {
                $this->addError('bulkProfitTarget', 'Geçerli bir pazaryeri seçin.');

                return;
            }
        }

        $products = MpProduct::query()
            ->where('user_id', $this->userId())
            ->whereIn('id', $productIds)
            ->with(['channelListings.store'])
            ->get();

        $updatedProducts = 0;
        $updatedListings = 0;
        $skipped = 0;
        $matchedListings = 0;
        $userId = $this->userId();
        $logger = app(MpProductChangeLogger::class);
        $batchId = $logger->batchId('bulk_profit_target');
        $changedBy = Auth::id();

        \DB::transaction(function () use ($products, $targetProfitabilityMultiplier, $marketplace, $userId, $logger, $batchId, $changedBy, &$updatedProducts, &$updatedListings, &$skipped, &$matchedListings) {
            foreach ($products as $product) {
                if ($marketplace === null) {
                    $targetPrice = $this->targetSalePriceForProfitMargin(
                        $product,
                        (float) ($product->commission_rate ?? 0),
                        $targetProfitabilityMultiplier
                    );

                    if ($targetPrice !== null) {
                        $beforeSnapshot = $logger->productSnapshot($product);
                        $product->forceFill(['sale_price' => $targetPrice])->save();
                        $logger->logProductSnapshotChanges(
                            $product->fresh() ?: $product,
                            $beforeSnapshot,
                            'bulk_profit_target',
                            $changedBy,
                            'Hedef kârlılığa göre ana fiyat güncelleme',
                            $batchId,
                            ['target_marketplace' => $marketplace]
                        );
                        $updatedProducts++;
                    } else {
                        $skipped++;
                    }
                }

                $listings = ($product->channelListings ?? collect())
                    ->filter(function (ChannelListing $listing) use ($marketplace, $userId) {
                        $store = $listing->store;

                        if (! $store || (int) $store->user_id !== $userId) {
                            return false;
                        }

                        return $marketplace === null || (string) $store->marketplace === $marketplace;
                    });

                foreach ($listings as $listing) {
                    $matchedListings++;
                    $commissionRate = $this->resolvedListingCommissionRate($product, $listing)['rate'];
                    $targetPrice = $this->targetSalePriceForProfitMargin($product, $commissionRate, $targetProfitabilityMultiplier);

                    if ($targetPrice === null) {
                        $skipped++;

                        continue;
                    }

                    $beforeListingSnapshot = $logger->listingSnapshot($listing);
                    $listing->forceFill(['sale_price' => $targetPrice])->save();
                    $logger->logListingSnapshotChanges(
                        $listing->fresh() ?: $listing,
                        $beforeListingSnapshot,
                        'bulk_profit_target',
                        $changedBy,
                        'Hedef kârlılığa göre kanal fiyat güncelleme',
                        $batchId,
                        ['target_marketplace' => $marketplace]
                    );
                    $updatedListings++;
                }
            }
        });

        if ($marketplace !== null && $matchedListings === 0) {
            session()->flash('warning', 'Seçili ürünlerde bu pazaryerine bağlı kârlılık kaydı bulunamadı.');

            return;
        }

        if ($updatedProducts === 0 && $updatedListings === 0) {
            session()->flash('warning', 'Hedef kârlılık uygulanamadı. Maliyet veya komisyon verilerini kontrol edin.');

            return;
        }

        $targetLabel = $this->formatBulkPercent($targetMarginPercent);
        $message = $marketplace === null
            ? "{$updatedProducts} ana fiyat ve {$updatedListings} kanal fiyatı %{$targetLabel} kârlılığa göre güncellendi."
            : $this->humanMarketplace($marketplace) . " için {$updatedListings} kanal fiyatı %{$targetLabel} kârlılığa göre güncellendi.";

        if ($skipped > 0) {
            $message .= " {$skipped} kayıt atlandı.";
        }

        $this->resetBulkProfitForm();
        $this->clearSelection();

        session()->flash('success', $message);
    }

    public function bulkSetPackagingCost(): void
    {
        if ($this->selectedProducts === []) {
            return;
        }

        $this->validate([
            'bulkPackagingCost' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        ], [], [
            'bulkPackagingCost' => 'ambalaj fiyatı',
        ]);

        $productIds = $this->selectedProductIdsForBulkAction();

        if ($productIds === []) {
            $this->clearSelection();
            session()->flash('warning', 'Seçili ürünler bulunamadı.');

            return;
        }

        $packagingCost = round((float) $this->bulkPackagingCost, 2);
        $logger = app(MpProductChangeLogger::class);
        $beforeSnapshots = $logger->productSnapshotsForIds($this->userId(), $productIds);
        $batchId = $logger->batchId('bulk_packaging_update');

	        MpProduct::query()
	            ->where('user_id', $this->userId())
	            ->whereIn('id', $productIds)
	            ->update(['packaging_cost' => $packagingCost]);

	        app(ProductCompositionResolver::class)->refreshParentSetsForProductIds($productIds);

        $logger->logProductChangesForIds(
            $productIds,
            $beforeSnapshots,
            'bulk_packaging_update',
            Auth::id(),
            'Toplu ambalaj maliyeti güncelleme',
            $batchId
        );

        $count = count($productIds);
        $this->resetBulkPackagingCostForm();
        $this->clearSelection();

        session()->flash('success', "{$count} ürünün ambalaj fiyatı ₺" . number_format($packagingCost, 2, ',', '.') . ' olarak güncellendi.');
    }

    public function bulkSetCogs(): void
    {
        if ($this->selectedProducts === []) {
            return;
        }

        $this->validate([
            'bulkCogs' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        ], [], [
            'bulkCogs' => 'birim maliyet (COGS)',
        ]);

        $productIds = $this->selectedProductIdsForBulkAction();

        if ($productIds === []) {
            $this->clearSelection();
            session()->flash('warning', 'Seçili ürünler bulunamadı.');

            return;
        }

        $cogs = round((float) $this->bulkCogs, 2);
        $logger = app(MpProductChangeLogger::class);
        $beforeSnapshots = $logger->productSnapshotsForIds($this->userId(), $productIds);
        $batchId = $logger->batchId('bulk_cogs_update');

	        MpProduct::query()
	            ->where('user_id', $this->userId())
	            ->whereIn('id', $productIds)
	            ->update(['cogs' => $cogs, 'cost_source' => 'manual']);

	        app(ProductCompositionResolver::class)->refreshParentSetsForProductIds($productIds);

        $logger->logProductChangesForIds(
            $productIds,
            $beforeSnapshots,
            'bulk_cogs_update',
            Auth::id(),
            'Toplu COGS güncelleme',
            $batchId
        );

        $count = count($productIds);
        $this->resetBulkCogsForm();
        $this->clearSelection();

        session()->flash('success', "{$count} ürünün birim maliyeti (COGS) ₺" . number_format($cogs, 2, ',', '.') . ' olarak güncellendi.');
    }

    // ─── COGS Sihirbazı Metodları ────────────────────────────────

    // ─── Akıllı Eşleştirme Metodları ─────────────────────────────

    /**
     * Eşleşmeyen sipariş stok kodları için fuzzy match önerilerini yükle.
     */
    public function openMatchWizard(): void
    {
        $this->matchLoading    = true;
        $this->matchSuggestions = [];

        try {
            $suggestions = app(\App\Services\Accounting\ProductMatcherService::class)
                ->suggestBatch($this->userId(), 100);

            $this->matchSuggestions = $suggestions;
            $this->showMatchWizard  = true;
        } catch (\Throwable $e) {
            session()->flash('error', 'Eşleştirme önerileri yüklenirken hata: ' . $e->getMessage());
        } finally {
            $this->matchLoading = false;
        }
    }

    public function closeMatchWizard(): void
    {
        $this->showMatchWizard  = false;
        $this->matchSuggestions = [];
    }

    /**
     * Kullanıcı bir öneriyi onayladığında mp_orders'ı güncelle.
     */
    public function confirmProductMatch(string $orderStockCode, string $orderProductName, int $targetProductId, float $score, string $matchType): void
    {
        $updated = app(\App\Services\Accounting\ProductMatcherService::class)
            ->confirmMatch($orderStockCode, $orderProductName, $targetProductId, $this->userId(), $matchType);

        // Eşleştirilen satırı listeden kaldır
        $this->matchSuggestions = array_values(array_filter(
            $this->matchSuggestions,
            fn ($row) => !($row['order_stock_code'] === $orderStockCode && $row['order_product_name'] === $orderProductName)
        ));

        session()->flash('success', "Eşleştirme onaylandı ({$updated} sipariş güncellendi). Güven skoru: " . round($score * 100) . '%');
    }

    /**
     * Bir eşleştirme önerisini atla (listeden kaldır, kaydetme).
     */
    public function skipMatchSuggestion(string $orderStockCode, string $orderProductName): void
    {
        $this->matchSuggestions = array_values(array_filter(
            $this->matchSuggestions,
            fn ($row) => !($row['order_stock_code'] === $orderStockCode && $row['order_product_name'] === $orderProductName)
        ));
    }

    // ─────────────────────────────────────────────────────────────

    /**
     * COGS eksik ürünleri kategori bazında gruplayarak sihirbazı açar.
     */
    public function openCogsWizard(): void
    {
        $products = MpProduct::query()
            ->where('user_id', $this->userId())
            ->where(function ($q) {
                $q->whereNull('cogs')->orWhere('cogs', 0);
            })
            ->select('id', 'category_name', 'cogs', 'sale_price')
            ->orderBy('category_name')
            ->get();

        if ($products->isEmpty()) {
            session()->flash('info', 'Tüm ürünlerin maliyeti tanımlı. COGS sihirbazına gerek yok.');
            return;
        }

        // Kategori bazlı gruplama; null kategori "Kategorisiz" olarak işlenir
        $grouped = [];
        foreach ($products as $p) {
            $cat = $p->category_name ?: 'Kategorisiz';

            if (! isset($grouped[$cat])) {
                $grouped[$cat] = [
                    'count'        => 0,
                    'product_ids'  => [],
                    'avg_price'    => 0.0,
                    'total_price'  => 0.0,
                ];
            }

            $grouped[$cat]['count']++;
            $grouped[$cat]['product_ids'][] = $p->id;
            $grouped[$cat]['total_price'] += (float) ($p->sale_price ?? 0);
        }

        // Ortalama satış fiyatını hesapla — kullanıcıya COGS önerisi sunar
        foreach ($grouped as $cat => &$data) {
            $data['avg_price'] = $data['count'] > 0
                ? round($data['total_price'] / $data['count'], 2)
                : 0.0;
            unset($data['total_price']);
        }
        unset($data);

        $this->cogsWizardCategories = $grouped;

        // Her kategori için boş input başlat
        $this->cogsWizardInputs = [];
        foreach (array_keys($grouped) as $cat) {
            $this->cogsWizardInputs[$cat] = [
                'cogs'           => '',
                'packaging_cost' => '',
            ];
        }

        $this->cogsWizardResult  = null;
        $this->showCogsWizard    = true;
    }

    /**
     * Sihirbazda girilen kategori bazlı COGS/ambalaj maliyetlerini uygular.
     */
    public function applyCogsWizard(): void
    {
        $logger   = app(MpProductChangeLogger::class);
        $batchId  = $logger->batchId('cogs_wizard');
        $updated  = 0;
        $skipped  = 0;

        foreach ($this->cogsWizardCategories as $cat => $data) {
            $input           = $this->cogsWizardInputs[$cat] ?? [];
            $cogsRaw         = $input['cogs'] ?? '';
            $packagingRaw    = $input['packaging_cost'] ?? '';

            // Her iki alan da boşsa bu kategoriyi atla
            if (blank($cogsRaw) && blank($packagingRaw)) {
                $skipped += $data['count'];
                continue;
            }

            $productIds = $data['product_ids'] ?? [];
            if (empty($productIds)) {
                continue;
            }

            $updates = ['cost_source' => 'manual'];

            if (filled($cogsRaw) && is_numeric($cogsRaw)) {
                $updates['cogs'] = round((float) $cogsRaw, 2);
            }

            if (filled($packagingRaw) && is_numeric($packagingRaw)) {
                $updates['packaging_cost'] = round((float) $packagingRaw, 2);
            }

            $beforeSnapshots = $logger->productSnapshotsForIds($this->userId(), $productIds);

            MpProduct::query()
                ->where('user_id', $this->userId())
                ->whereIn('id', $productIds)
                ->update($updates);

            app(ProductCompositionResolver::class)->refreshParentSetsForProductIds($productIds);

            $logger->logProductChangesForIds(
                $productIds,
                $beforeSnapshots,
                'cogs_wizard',
                Auth::id(),
                "COGS Sihirbazı — {$cat} kategorisi",
                $batchId
            );

            $updated += count($productIds);
        }

        // Sonucu kaydet
        $this->cogsWizardResult = [
            'updated' => $updated,
            'skipped' => $skipped,
        ];

        // Güncel kategori listesini yenile (güncellenenler artık sihirbazda çıkmamalı)
        $this->openCogsWizard();

        if ($updated > 0) {
            session()->flash('success', "{$updated} ürünün maliyeti COGS Sihirbazı ile güncellendi.");
        }
    }

    public function closeCogsWizard(): void
    {
        $this->showCogsWizard    = false;
        $this->cogsWizardResult  = null;
        $this->cogsWizardCategories = [];
        $this->cogsWizardInputs  = [];
    }

    // ─────────────────────────────────────────────────────────────

    public function bulkSetLogisticsInfo(): void
    {
        if ($this->selectedProducts === []) {
            return;
        }

        $this->bulkCargoCost = blank($this->bulkCargoCost) ? null : $this->bulkCargoCost;
        $this->bulkDesi = blank($this->bulkDesi) ? null : $this->bulkDesi;
        $this->bulkPieces = blank($this->bulkPieces) ? null : $this->bulkPieces;

        $this->validate([
            'bulkCargoCost' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'bulkDesi' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'bulkPieces' => ['nullable', 'integer', 'min:1', 'max:999999'],
        ], [], [
            'bulkCargoCost' => 'lojistik tutarı',
            'bulkDesi' => 'desi',
            'bulkPieces' => 'parça sayısı',
        ]);

        $updates = [];

        if (filled($this->bulkCargoCost)) {
            $updates['cargo_cost'] = round((float) $this->bulkCargoCost, 2);
        }

        if (filled($this->bulkDesi)) {
            $updates['desi'] = round((float) $this->bulkDesi, 2);
        }

        if (filled($this->bulkPieces)) {
            $updates['pieces'] = (int) $this->bulkPieces;
        }

        if ($updates === []) {
            $this->addError('bulkCargoCost', 'En az bir lojistik alanı girin.');

            return;
        }

        $productIds = $this->selectedProductIdsForBulkAction();

        if ($productIds === []) {
            $this->clearSelection();
            session()->flash('warning', 'Seçili ürünler bulunamadı.');

            return;
        }

        $logger = app(MpProductChangeLogger::class);
        $beforeSnapshots = $logger->productSnapshotsForIds($this->userId(), $productIds);
        $batchId = $logger->batchId('bulk_logistics_update');

	        MpProduct::query()
	            ->where('user_id', $this->userId())
	            ->whereIn('id', $productIds)
	            ->update($updates);

	        app(ProductCompositionResolver::class)->refreshParentSetsForProductIds($productIds);

        $logger->logProductChangesForIds(
            $productIds,
            $beforeSnapshots,
            'bulk_logistics_update',
            Auth::id(),
            'Toplu lojistik bilgisi güncelleme',
            $batchId
        );

        $count = count($productIds);
        $this->resetBulkLogisticsForm();
        $this->clearSelection();

        session()->flash('success', "{$count} ürünün lojistik bilgisi güncellendi.");
    }

    public function bulkSetCriticalStockThreshold(): void
    {
        if ($this->selectedProducts === []) {
            return;
        }

        $this->validate([
            'bulkCriticalStockThreshold' => ['required', 'integer', 'min:0'],
        ], [], [
            'bulkCriticalStockThreshold' => 'kritik stok eşiği',
        ]);

        $threshold = (int) $this->bulkCriticalStockThreshold;
        $productIds = $this->selectedProductIdsForBulkAction();

        if ($productIds === []) {
            $this->clearSelection();
            session()->flash('warning', 'Seçili ürünler bulunamadı.');

            return;
        }

        $logger = app(MpProductChangeLogger::class);
        $beforeSnapshots = $logger->productSnapshotsForIds($this->userId(), $productIds);
        $batchId = $logger->batchId('bulk_stock_threshold');

        MpProduct::where('user_id', $this->userId())
            ->whereIn('id', $productIds)
            ->update(['critical_stock_threshold' => $threshold]);

        $logger->logProductChangesForIds(
            $productIds,
            $beforeSnapshots,
            'bulk_stock_threshold',
            Auth::id(),
            'Toplu kritik stok eşiği güncelleme',
            $batchId
        );

        $count = count($productIds);
        $this->bulkCriticalStockThreshold = null;
        $this->selectedProducts = [];
        $this->selectAll = false;

        session()->flash('success', "{$count} ürün için kritik stok eşiği {$threshold} olarak güncellendi.");
    }

    public function bulkClearCriticalStockThreshold(): void
    {
        if ($this->selectedProducts === []) {
            return;
        }

        $productIds = $this->selectedProductIdsForBulkAction();

        if ($productIds === []) {
            $this->clearSelection();
            session()->flash('warning', 'Seçili ürünler bulunamadı.');

            return;
        }

        $logger = app(MpProductChangeLogger::class);
        $beforeSnapshots = $logger->productSnapshotsForIds($this->userId(), $productIds);
        $batchId = $logger->batchId('bulk_stock_threshold');

        MpProduct::where('user_id', $this->userId())
            ->whereIn('id', $productIds)
            ->update(['critical_stock_threshold' => null]);

        $logger->logProductChangesForIds(
            $productIds,
            $beforeSnapshots,
            'bulk_stock_threshold',
            Auth::id(),
            'Toplu kritik stok eşiği kaldırma',
            $batchId
        );

        $count = count($productIds);
        $this->bulkCriticalStockThreshold = null;
        $this->selectedProducts = [];
        $this->selectAll = false;

        session()->flash('success', "{$count} ürünün kritik stok eşiği kaldırıldı.");
    }

    protected function dispatchCurrentStatusRefresh(array $productIds, bool $allowSingleProductStoreFallback = false): array
    {
        $productIds = collect($productIds)
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $summary = [
            'queued' => 0,
            'completed' => 0,
            'debounced' => 0,
            'skipped' => [],
            'run_ids' => [],
            'store_count' => 0,
            'listing_count' => 0,
            'product_count' => count($productIds),
        ];

        if ($productIds === []) {
            return $summary;
        }

        $refreshTargets = $this->currentStatusRefreshTargets($productIds, $allowSingleProductStoreFallback);
        $summary['store_count'] = $refreshTargets->count();
        $summary['listing_count'] = $refreshTargets->sum(fn (array $target) => $target['listings']->count());

        if ($refreshTargets->isEmpty()) {
            return $summary;
        }

        foreach ($refreshTargets as $target) {
            /** @var MarketplaceStore $store */
            $store = $target['store'];

            try {
                $products = $target['products'];
                $listings = $target['listings'];
                $options = $this->currentStatusRefreshOptions($products, $listings);

                $result = app(MarketplaceManualSyncDispatchService::class)->dispatch($store, 'products', [
                    'options' => $options,
                    'bypass_recent' => true,
                    'source' => 'current_status_refresh',
                    'origin_screen' => 'products',
                    'mp_product_ids' => $products->pluck('id')->map(static fn ($id) => (int) $id)->unique()->values()->all(),
                    'channel_listing_ids' => $listings->pluck('id')->map(static fn ($id) => (int) $id)->unique()->values()->all(),
                    'stock_codes' => $this->currentStatusIdentifierValues($products, $listings, 'stock_code'),
                    'barcodes' => $this->currentStatusIdentifierValues($products, $listings, 'barcode'),
                ]);

                if (isset($result['run'])) {
                    $summary['run_ids'][] = (int) $result['run']->id;
                }

                if ($result['created'] && $result['executed_inline'] && blank($result['inline_error'])) {
                    $summary['completed']++;
                } elseif ($result['created']) {
                    if (filled($result['inline_error'])) {
                        $summary['skipped'][] = $this->storeLabel($store) . ': ' . $result['inline_error'];
                    } else {
                        $summary['queued']++;
                    }
                } elseif ($result['debounced']) {
                    $summary['debounced']++;
                }
            } catch (\Throwable $exception) {
                $summary['skipped'][] = $this->storeLabel($store) . ': ' . $exception->getMessage();
            }
        }

        return $summary;
    }

    protected function currentStatusRefreshTargets(array $productIds, bool $allowSingleProductStoreFallback)
    {
        $userId = $this->userId();
        $listings = ChannelListing::query()
            ->with(['store.connection', 'store.syncProfile', 'product', 'channelProduct'])
            ->whereIn('mp_product_id', $productIds)
            ->whereHas('store', function (Builder $query) use ($userId) {
                $query->where('user_id', $userId)
                    ->where('is_active', true)
                    ->whereHas('connection', fn (Builder $connectionQuery) => $connectionQuery->whereIn('status', ['configured', 'connected', 'demo']));
            })
            ->get();

        $targets = $listings
            ->groupBy('store_id')
            ->map(function ($storeListings) {
                $store = $storeListings->first()?->store;

                if (!$store) {
                    return null;
                }

                return [
                    'store' => $store,
                    'listings' => $storeListings->values(),
                    'products' => $storeListings->pluck('product')->filter()->unique('id')->values(),
                ];
            })
            ->filter()
            ->values();

        if ($targets->isNotEmpty() || !$allowSingleProductStoreFallback || count($productIds) !== 1) {
            return $targets;
        }

        $product = MpProduct::query()
            ->where('user_id', $userId)
            ->whereKey($productIds[0])
            ->first();

        if (!$product) {
            return $targets;
        }

        return MarketplaceStore::query()
            ->with(['connection', 'syncProfile'])
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->whereHas('connection', fn (Builder $query) => $query->whereIn('status', ['configured', 'connected', 'demo']))
            ->orderBy('marketplace')
            ->orderBy('store_name')
            ->get()
            ->map(fn (MarketplaceStore $store) => [
                'store' => $store,
                'listings' => collect(),
                'products' => collect([$product]),
            ])
            ->values();
    }

    protected function currentStatusRefreshOptions($products, $listings): array
    {
        $now = now();
        $options = [
            'start_date' => $now->copy()->subDays(180)->toIso8601String(),
            'end_date' => $now->toIso8601String(),
            'page_size' => 50,
            'current_status_refresh' => true,
        ];

        if ($products->pluck('id')->filter()->unique()->count() === 1) {
            $product = $products->first();
            $channelProduct = $listings->pluck('channelProduct')->filter()->first();

            $options['stock_code'] = $this->firstFilledValue([
                $product?->stock_code,
                $channelProduct?->stock_code,
            ]);
            $options['barcode'] = $this->firstFilledValue([
                $product?->barcode,
                $channelProduct?->barcode,
            ]);
            $options['product_main_id'] = $this->firstFilledValue([
                $channelProduct?->external_parent_id,
            ]);
            $options['external_product_id'] = $this->firstFilledValue([
                $channelProduct?->external_product_id,
            ]);
        }

        return array_filter($options, static fn ($value) => $value !== null && $value !== '' && $value !== []);
    }

    protected function currentStatusIdentifierValues($products, $listings, string $field): array
    {
        return collect()
            ->merge($products->pluck($field))
            ->merge($listings->pluck("channelProduct.{$field}"))
            ->map(static fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function firstFilledValue(array $values): ?string
    {
        foreach ($values as $value) {
            $value = trim((string) $value);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    protected function trackCurrentStatusRefreshRuns(array $summary): void
    {
        $runIds = collect($summary['run_ids'] ?? [])
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($runIds === []) {
            return;
        }

        $activeRunIds = IntegrationSyncRun::query()
            ->whereIn('id', $runIds)
            ->whereIn('status', ['queued', 'processing', 'retrying'])
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        if ($activeRunIds === []) {
            return;
        }

        $this->currentStatusRefreshRunIds = $activeRunIds;
        $this->currentStatusRefreshPollingStartedAt = now()->toIso8601String();
    }

    protected function flashCurrentStatusRefreshSummary(array $summary): void
    {
        $started = (int) $summary['queued'] + (int) $summary['completed'];
        $debounced = (int) $summary['debounced'];
        $skipped = $summary['skipped'] ?? [];

        if ((int) $summary['store_count'] === 0) {
            session()->flash('warning', 'Güncel durum almak için bağlantısı tamamlanmış mağaza kaydı bulunamadı.');

            return;
        }

        $parts = [];

        if ((int) $summary['completed'] > 0) {
            $parts[] = $summary['completed'] . ' mağaza hemen işlendi';
        }

        if ((int) $summary['queued'] > 0) {
            $parts[] = $summary['queued'] . ' mağaza kuyruğa alındı';
        }

        if ($debounced > 0) {
            $parts[] = $debounced . ' mağazada zaten çalışan ürün sync var';
        }

        if ($skipped !== []) {
            $parts[] = count($skipped) . ' mağaza atlandı';
        }

        $message = $parts !== []
            ? 'Güncel durum alma başlatıldı: ' . implode(' · ', $parts) . '.'
            : 'Güncel durum için yeni bir işlem başlatılamadı.';

        if ($skipped !== []) {
            $message .= ' İlk atlanan: ' . $skipped[0];
        } elseif ($started > 0) {
            $message .= ' Fiyat, stok ve kanal bilgileri senkron tamamlanınca tabloya yansır.';
        }

        session()->flash($started > 0 ? 'success' : 'warning', $message);
    }

    protected function storeLabel(MarketplaceStore $store): string
    {
        return $store->store_name ?: $this->humanMarketplace($store->marketplace);
    }

    protected function selectedProductIdsForBulkAction(): array
    {
        return MpProduct::query()
            ->where('user_id', $this->userId())
            ->whereIn('id', $this->selectedProducts)
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();
    }

    protected function bulkListingsQuery(array $productIds, ?string $marketplace = null): Builder
    {
        $userId = $this->userId();

        return ChannelListing::query()
            ->whereIn('mp_product_id', $productIds)
            ->whereHas('store', function (Builder $query) use ($userId, $marketplace) {
                $query->where('user_id', $userId);

                if ($marketplace !== null) {
                    $query->where('marketplace', $marketplace);
                }
            });
    }

    protected function userMarketplaceExists(string $marketplace): bool
    {
        return MarketplaceStore::query()
            ->where('user_id', $this->userId())
            ->where('marketplace', $marketplace)
            ->exists();
    }

    protected function syncBulkProductStockAlerts(array $productIds): void
    {
        if ($productIds === []) {
            return;
        }

        MpProduct::query()
            ->where('user_id', $this->userId())
            ->whereIn('id', $productIds)
            ->get()
            ->each(fn (MpProduct $product) => app(\App\Services\NotificationCenterService::class)->syncProductStockAlert($product));
    }

    protected function syncBulkListingStockAlerts(array $listingIds): void
    {
        if ($listingIds === []) {
            return;
        }

        ChannelListing::query()
            ->whereIn('id', $listingIds)
            ->with(['store', 'product', 'channelProduct'])
            ->get()
            ->each(fn (ChannelListing $listing) => app(\App\Services\NotificationCenterService::class)->syncListingStockAlert($listing));
    }

    protected function resetBulkStockForm(): void
    {
        $this->bulkStockQuantity = null;
        $this->bulkStockTarget = 'all';
    }

    protected function resetBulkPriceForm(): void
    {
        $this->bulkPricePercent = null;
        $this->bulkPriceDirection = 'increase';
        $this->bulkPriceTarget = 'all';
    }

    protected function resetBulkProfitForm(): void
    {
        $this->bulkProfitTargetMargin = null;
        $this->bulkProfitTarget = 'all';
    }

    protected function resetBulkPackagingCostForm(): void
    {
        $this->bulkPackagingCost = null;
    }

    protected function resetBulkCogsForm(): void
    {
        $this->bulkCogs = null;
    }

    protected function resetBulkLogisticsForm(): void
    {
        $this->bulkCargoCost = null;
        $this->bulkDesi = null;
        $this->bulkPieces = null;
    }

    protected function targetSalePriceForProfitMargin(MpProduct $product, float $commissionRate, float $targetProfitabilityMultiplier): ?float
    {
        $productCost = (float) $product->cogs + (float) $product->packaging_cost;
        $deductedCost = (float) $product->cargo_cost
            + (float) $product->extra_cost_fixed;
        $denominator = 1 - ($commissionRate / 100) - ((float) $product->extra_cost_percentage / 100);

        if ($productCost <= 0 || $targetProfitabilityMultiplier <= 0 || $denominator <= 0) {
            return null;
        }

        return round((($targetProfitabilityMultiplier * $productCost) + $deductedCost) / $denominator, 2);
    }

    protected function percentagePriceExpression(string $column, float $factor): \Illuminate\Contracts\Database\Query\Expression
    {
        $safeFactor = number_format(max(0, $factor), 6, '.', '');

        return \DB::raw("ROUND(GREATEST(COALESCE({$column}, 0) * {$safeFactor}, 0), 2)");
    }

    protected function formatBulkPercent(float $percent): string
    {
        return rtrim(rtrim(number_format($percent, 2, ',', '.'), '0'), ',');
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->filterStatus = 'all';
        $this->filterCategory = 'all';
        $this->filterBrand = 'all';
        $this->filterStockLevel = 'all';
        $this->filterCostDefined = 'all';
        $this->recipeLinkFilter = 'all';
        $this->setContentFilter = 'all';
        $this->filterProfitComparison = 'all';
        $this->filterProfitMargin = null;
        $this->filterSalePriceMin = null;
        $this->filterSalePriceMax = null;
        $this->filterCostMin = null;
        $this->filterCostMax = null;
        $this->filterStockMin = null;
        $this->filterStockMax = null;
        $this->filterDesiMin = null;
        $this->filterDesiMax = null;
        $this->filterReturnRateMin = null;
        $this->filterReturnRateMax = null;
        $this->marketplaceFilter = 'all';
        $this->listingStatusFilter = 'all';
        $this->listingCoverageFilter = 'all';
        $this->legalEntityFilter = 'all';
        $this->sortField = 'product_name';
        $this->sortDirection = 'asc';
        $this->clearSelection();
        $this->resetPage();
    }

    public function refreshCommissionRates(): void
    {
        $stores = MarketplaceStore::query()
            ->with(['connection', 'syncProfile'])
            ->where('user_id', $this->userId())
            ->where('is_active', true)
            ->whereHas('connection', fn (Builder $query) => $query->whereIn('status', ['configured', 'connected', 'demo']))
            ->when(
                $this->marketplaceFilter !== 'all',
                fn (Builder $query) => $query->where('marketplace', $this->marketplaceFilter)
            )
            ->when(
                $this->legalEntityFilter !== 'all',
                fn (Builder $query) => $query->where('legal_entity_id', (int) $this->legalEntityFilter)
            )
            ->orderBy('marketplace')
            ->orderBy('store_name')
            ->get();

        if ($stores->isEmpty()) {
            session()->flash('warning', 'Komisyon tazelemek için aktif ve bağlantısı tamamlanmış mağaza bulunamadı.');

            return;
        }

        $queued = 0;
        $debounced = 0;
        $skipped = [];

        foreach ($stores as $store) {
            try {
                $result = app(MarketplaceManualSyncDispatchService::class)->dispatch($store, 'products', [
                    'options' => [
                        'refresh_commission_rates' => true,
                    ],
                    'force_queue' => true,
                    'source' => 'commission_refresh',
                    'origin_screen' => 'products',
                ]);

                if ($result['created']) {
                    $queued++;
                } elseif ($result['debounced']) {
                    $debounced++;
                }
            } catch (\Throwable $exception) {
                $skipped[] = ($store->store_name ?: $this->humanMarketplace($store->marketplace)) . ': ' . $exception->getMessage();
            }
        }

        $parts = [];

        if ($queued > 0) {
            $parts[] = "{$queued} mağaza kuyruğa alındı";
        }

        if ($debounced > 0) {
            $parts[] = "{$debounced} mağazada zaten çalışan/yakın tarihli ürün sync var";
        }

        if ($skipped !== []) {
            $parts[] = count($skipped) . ' mağaza atlandı';
        }

        $message = $parts !== []
            ? 'Komisyon tazeleme için ürün senkronu başlatıldı: ' . implode(' · ', $parts) . '.'
            : 'Komisyon tazeleme için yeni bir işlem başlatılamadı.';

        if ($skipped !== []) {
            $message .= ' İlk atlanan: ' . $skipped[0];
        }

        session()->flash($queued > 0 ? 'success' : 'warning', $message);
    }

    public function humanMarketplace(?string $marketplace): string
    {
        return (string) (MarketplaceProviderRegistry::get((string) $marketplace)['label'] ?? ucfirst((string) $marketplace));
    }

    public function listingDeliveryTermLabel(ChannelListing $listing): string
    {
        $parts = [];

        if ($listing->shipping_days !== null) {
            $parts[] = $this->deliveryDaysLabel((int) $listing->shipping_days);
        }

        foreach ([$listing->fast_delivery_type, $listing->shipping_type] as $label) {
            $label = trim((string) $label);

            if ($label !== '') {
                $parts[] = $label;
            }
        }

        $parts = array_values(array_unique($parts));

        return $parts !== [] ? implode(' · ', $parts) : 'Termin yok';
    }

    /**
     * @return array{label: string, short_label: string, detail: string, title: string, count: int, terms: array<int, array<string, mixed>>, has_channel_terms: bool}
     */
    public function productDeliverySummary(MpProduct $product): array
    {
        $listings = $product->relationLoaded('channelListings')
            ? $product->channelListings
            : $product->channelListings()->with('store:id,marketplace,store_name')->get();

        $terms = $listings
            ->map(function (ChannelListing $listing): ?array {
                $hasTerm = $listing->shipping_days !== null
                    || filled($listing->fast_delivery_type)
                    || filled($listing->shipping_type);

                if (!$hasTerm) {
                    return null;
                }

                $store = $listing->store;
                $storeName = (string) ($store?->store_name ?: $this->humanMarketplace($store?->marketplace));

                return [
                    'store_name' => $storeName,
                    'marketplace' => $this->humanMarketplace($store?->marketplace),
                    'days' => $listing->shipping_days !== null ? (int) $listing->shipping_days : null,
                    'label' => $this->listingDeliveryTermLabel($listing),
                ];
            })
            ->filter()
            ->values();

        if ($terms->isNotEmpty()) {
            $dayValues = $terms
                ->pluck('days')
                ->filter(static fn ($value) => $value !== null)
                ->map(static fn ($value) => (int) $value)
                ->values();

            $label = $dayValues->isNotEmpty()
                ? $this->deliveryRangeLabel((int) $dayValues->min(), (int) $dayValues->max())
                : (string) $terms->first()['label'];
            $shortLabel = $dayValues->isNotEmpty()
                ? $this->deliveryRangeShortLabel((int) $dayValues->min(), (int) $dayValues->max())
                : $this->compactDeliveryTermLabel((string) $terms->first()['label']);

            return [
                'label' => $label,
                'short_label' => $shortLabel,
                'detail' => $terms->count() . ' mağazadan',
                'title' => $terms
                    ->map(fn (array $term) => "{$term['store_name']} ({$term['marketplace']}): {$term['label']}")
                    ->implode(' | '),
                'count' => $terms->count(),
                'terms' => $terms->all(),
                'has_channel_terms' => true,
            ];
        }

        if ($product->shipping_days !== null) {
            $label = $this->deliveryDaysLabel((int) $product->shipping_days);

            return [
                'label' => $label,
                'short_label' => $this->deliveryDaysShortLabel((int) $product->shipping_days),
                'detail' => $product->shipping_type ?: 'Ana ürün',
                'title' => $label,
                'count' => 0,
                'terms' => [],
                'has_channel_terms' => false,
            ];
        }

        $manualLabel = trim((string) $product->fast_delivery_type);

        return [
            'label' => $manualLabel !== '' ? $manualLabel : 'Standart',
            'short_label' => $manualLabel !== '' ? $this->compactDeliveryTermLabel($manualLabel) : 'Std',
            'detail' => $manualLabel !== '' ? 'Ana ürün' : 'Termin yok',
            'title' => $manualLabel !== '' ? $manualLabel : 'Kanal termin bilgisi yok',
            'count' => 0,
            'terms' => [],
            'has_channel_terms' => false,
        ];
    }

    protected function deliveryDaysLabel(int $days): string
    {
        return $days <= 0 ? 'Aynı gün' : "{$days} gün";
    }

    protected function deliveryDaysShortLabel(int $days): string
    {
        return $days <= 0 ? 'Bugün' : "{$days}g";
    }

    protected function deliveryRangeLabel(int $minDays, int $maxDays): string
    {
        if ($minDays === $maxDays) {
            return $this->deliveryDaysLabel($minDays);
        }

        return "{$this->deliveryDaysLabel($minDays)} - {$this->deliveryDaysLabel($maxDays)}";
    }

    protected function deliveryRangeShortLabel(int $minDays, int $maxDays): string
    {
        if ($minDays === $maxDays) {
            return $this->deliveryDaysShortLabel($minDays);
        }

        if ($minDays <= 0) {
            return 'Bugün-' . $this->deliveryDaysShortLabel($maxDays);
        }

        return "{$minDays}-{$maxDays}g";
    }

    protected function compactDeliveryTermLabel(string $label): string
    {
        $label = trim(preg_replace('/\s+/u', ' ', $label) ?? '');

        if ($label === '') {
            return 'Std';
        }

        $normalized = Str::lower($label);

        if (in_array($normalized, ['standart', 'standard'], true)) {
            return 'Std';
        }

        if ($normalized === 'aynı gün') {
            return 'Bugün';
        }

        if (preg_match('/^(\d+)\s*g[üu]n$/iu', $label, $matches) === 1) {
            return $matches[1] . 'g';
        }

        return Str::limit($label, 9, '');
    }

    public function productProfitDefaultMarketplaceLabel(): string
    {
        $defaultMarketplace = $this->productProfitSettings()['default_marketplace'];

        return match ($defaultMarketplace) {
            'average' => 'Mağaza ortalaması',
            'worst' => 'En düşük kâr',
            default => $this->humanMarketplace($defaultMarketplace),
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function productCommissionScenarios(MpProduct $product): array
    {
        $listings = $product->relationLoaded('channelListings')
            ? $product->channelListings
            : $product->channelListings()
                ->with(['store:id,marketplace,store_name,is_active', 'channelProduct:id,title,stock_code,barcode'])
                ->get();

        $scenarios = $listings
            ->map(fn (ChannelListing $listing) => $this->buildListingProfitScenario($product, $listing))
            ->filter()
            ->sortByDesc(fn (array $scenario) => (($scenario['is_active'] ? 1 : 0) * 10000000000) + (int) ($scenario['synced_at_sort'] ?? 0))
            ->values()
            ->all();

        if ((bool) $product->profit_commission_override_enabled) {
            array_unshift($scenarios, $this->manualProductProfitScenario($product));
        }

        return $scenarios !== []
            ? $scenarios
            : [$this->fallbackProductProfitScenario($product)];
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $scenarios
     * @return array<string, mixed>|null
     */
    public function selectedProductCommissionScenario(MpProduct $product, ?array $scenarios = null): ?array
    {
        $scenarios ??= $this->productCommissionScenarios($product);

        if ($scenarios === []) {
            return null;
        }

        if (count($scenarios) === 1 && ($scenarios[0]['marketplace_key'] ?? null) === 'master') {
            return $scenarios[0];
        }

        if ((bool) $product->profit_commission_override_enabled) {
            return $this->manualProductProfitScenario($product);
        }

        $defaultMarketplace = $this->productProfitSettings()['default_marketplace'];

        if ($defaultMarketplace === 'worst') {
            $worst = collect($scenarios)->sortBy('profit')->first();

            return is_array($worst) ? array_merge($worst, [
                'selection_label' => 'En düşük kâr',
                'selection_key' => $worst['key'] ?? 'worst',
            ]) : null;
        }

        if ($defaultMarketplace === 'average') {
            return $this->aggregateProfitScenarios($product, $scenarios, 'average', 'Mağaza ortalaması');
        }

        $providerScenarios = array_values(array_filter(
            $scenarios,
            fn (array $scenario) => ($scenario['marketplace_key'] ?? null) === $defaultMarketplace
        ));

        if ($providerScenarios !== []) {
            return count($providerScenarios) === 1
                ? array_merge($providerScenarios[0], [
                    'selection_label' => $this->humanMarketplace($defaultMarketplace),
                    'selection_key' => 'provider:' . $defaultMarketplace,
                ])
                : $this->aggregateProfitScenarios(
                    $product,
                    $providerScenarios,
                    'provider:' . $defaultMarketplace,
                    $this->humanMarketplace($defaultMarketplace) . ' ortalaması'
                );
        }

        $missingMarketplaceLabel = $this->humanMarketplace($defaultMarketplace);

        return $this->aggregateProfitScenarios(
            $product,
            $scenarios,
            'fallback:average',
            "{$missingMarketplaceLabel} kaydı yok · mağaza ortalaması"
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function listingCommissionScenario(MpProduct $product, ChannelListing $listing): array
    {
        return $this->buildListingProfitScenario($product, $listing);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildListingProfitScenario(MpProduct $product, ChannelListing $listing): array
    {
        $store = $listing->store;
        $marketplaceKey = MarketplaceProviderRegistry::normalize((string) data_get($store, 'marketplace', ''));
        $marketplaceLabel = $this->humanMarketplace($marketplaceKey);
        $storeName = (string) (data_get($store, 'store_name') ?: $marketplaceLabel);
        $salePrice = (float) (($listing->sale_price ?? 0) > 0 ? $listing->sale_price : $product->sale_price);
        $commission = $this->resolvedListingCommissionRate($product, $listing);

        return $this->buildProfitScenarioPayload(
            key: 'listing:' . ($listing->id ?: spl_object_id($listing)),
            marketplaceKey: $marketplaceKey ?: 'unknown',
            marketplaceLabel: $marketplaceLabel,
            storeName: $storeName,
            salePrice: $salePrice,
            commissionRate: $commission['rate'],
            commissionSource: $commission['source'],
            product: $product,
            syncedAt: $listing->commission_synced_at ?: $listing->last_synced_at,
            isActive: in_array(strtolower((string) $listing->listing_status), $this->activeListingStatuses(), true),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function manualProductProfitScenario(MpProduct $product): array
    {
        return $this->buildProfitScenarioPayload(
            key: 'manual:' . ($product->id ?: spl_object_id($product)),
            marketplaceKey: 'manual',
            marketplaceLabel: 'Manuel ürün komisyonu',
            storeName: '% ' . number_format((float) ($product->commission_rate ?? 0), 2, ',', '.'),
            salePrice: (float) ($product->sale_price ?? 0),
            commissionRate: (float) ($product->commission_rate ?? 0),
            commissionSource: 'Ürün kartı manuel oran',
            product: $product,
            syncedAt: $product->updated_at,
            isActive: true,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function fallbackProductProfitScenario(MpProduct $product): array
    {
        return $this->buildProfitScenarioPayload(
            key: 'master:' . ($product->id ?: spl_object_id($product)),
            marketplaceKey: 'master',
            marketplaceLabel: 'ZOLM',
            storeName: 'Ürün kartı',
            salePrice: (float) ($product->sale_price ?? 0),
            commissionRate: (float) ($product->commission_rate ?? 0),
            commissionSource: 'Ürün yedek oranı',
            product: $product,
            syncedAt: $product->last_synced_at,
            isActive: true,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildProfitScenarioPayload(
        string $key,
        string $marketplaceKey,
        string $marketplaceLabel,
        string $storeName,
        float $salePrice,
        float $commissionRate,
        string $commissionSource,
        MpProduct $product,
        mixed $syncedAt = null,
        bool $isActive = false,
    ): array {
        $cogs = (float) ($product->cogs ?? 0);
        $packagingCost = (float) ($product->packaging_cost ?? 0);
        $cargoCost = (float) ($product->cargo_cost ?? 0);
        $extraCostFixed = (float) ($product->extra_cost_fixed ?? 0);
        $extraCostPercentage = (float) ($product->extra_cost_percentage ?? 0);
        $extraCostPercentageAmount = round($salePrice * ($extraCostPercentage / 100), 2);
        $totalUnitCost = $cogs + $packagingCost + $cargoCost + $extraCostFixed + $extraCostPercentageAmount;
        $costPlusPackaging = round($cogs + $packagingCost, 2);
        $commissionAmount = round($salePrice * ($commissionRate / 100), 2);
        $receivable = round($salePrice - $commissionAmount, 2);
        $profit = round($receivable - $totalUnitCost, 2);
        $profitMargin = ProfitabilityMetric::multiplier($profit, $costPlusPackaging);
        $syncedAtCarbon = $syncedAt ? \Illuminate\Support\Carbon::parse($syncedAt) : null;

        return [
            'key' => $key,
            'marketplace_key' => $marketplaceKey,
            'marketplace_label' => $marketplaceLabel,
            'store_name' => $storeName,
            'label' => trim($marketplaceLabel . ' · ' . $storeName),
            'sale_price' => round($salePrice, 2),
            'commission_rate' => round($commissionRate, 2),
            'commission_amount' => $commissionAmount,
            'receivable' => $receivable,
            'profit' => $profit,
            'profit_margin' => $profitMargin,
            'total_unit_cost' => round($totalUnitCost, 2),
            'cost_plus_packaging' => $costPlusPackaging,
            'cogs' => round($cogs, 2),
            'packaging_cost' => round($packagingCost, 2),
            'cargo_cost' => round($cargoCost, 2),
            'extra_cost_fixed' => round($extraCostFixed, 2),
            'extra_cost_percentage' => round($extraCostPercentage, 2),
            'extra_cost_percentage_amount' => $extraCostPercentageAmount,
            'commission_source' => $commissionSource,
            'synced_at_label' => $syncedAtCarbon?->format('d.m.Y H:i') ?: 'Henüz yok',
            'synced_at_sort' => $syncedAtCarbon?->timestamp ?? 0,
            'is_active' => $isActive,
            'selection_key' => $key,
            'selection_label' => $marketplaceLabel,
            'scenario_count' => 1,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $scenarios
     * @return array<string, mixed>
     */
    protected function aggregateProfitScenarios(MpProduct $product, array $scenarios, string $key, string $label): array
    {
        $count = max(1, count($scenarios));
        $average = static fn (string $field): float => round(array_sum(array_map(
            static fn (array $scenario) => (float) ($scenario[$field] ?? 0),
            $scenarios
        )) / $count, 2);

        $profit = $average('profit');
        $salePrice = $average('sale_price');
        $cogs = (float) ($product->cogs ?? 0);
        $packagingCost = (float) ($product->packaging_cost ?? 0);
        $cargoCost = (float) ($product->cargo_cost ?? 0);
        $extraCostFixed = (float) ($product->extra_cost_fixed ?? 0);
        $extraCostPercentage = (float) ($product->extra_cost_percentage ?? 0);
        $extraCostPercentageAmount = round($salePrice * ($extraCostPercentage / 100), 2);
        $totalUnitCost = $cogs + $packagingCost + $cargoCost + $extraCostFixed + $extraCostPercentageAmount;
        $profitMargin = ProfitabilityMetric::multiplier($profit, ProfitabilityMetric::productCost($cogs, $packagingCost));
        $latestScenario = collect($scenarios)->sortByDesc('synced_at_sort')->first();

        return [
            'key' => $key,
            'marketplace_key' => $key,
            'marketplace_label' => $label,
            'store_name' => $count . ' mağaza',
            'label' => $label,
            'sale_price' => $salePrice,
            'commission_rate' => $average('commission_rate'),
            'commission_amount' => $average('commission_amount'),
            'receivable' => $average('receivable'),
            'profit' => $profit,
            'profit_margin' => $profitMargin,
            'total_unit_cost' => round($totalUnitCost, 2),
            'cost_plus_packaging' => round($cogs + $packagingCost, 2),
            'cogs' => round($cogs, 2),
            'packaging_cost' => round($packagingCost, 2),
            'cargo_cost' => round($cargoCost, 2),
            'extra_cost_fixed' => round($extraCostFixed, 2),
            'extra_cost_percentage' => round($extraCostPercentage, 2),
            'extra_cost_percentage_amount' => $extraCostPercentageAmount,
            'commission_source' => 'Platform ortalaması',
            'synced_at_label' => is_array($latestScenario) ? ($latestScenario['synced_at_label'] ?? 'Henüz yok') : 'Henüz yok',
            'synced_at_sort' => collect($scenarios)->max('synced_at_sort') ?: 0,
            'is_active' => collect($scenarios)->contains(fn (array $scenario) => (bool) ($scenario['is_active'] ?? false)),
            'selection_key' => $key,
            'selection_label' => $label,
            'scenario_count' => $count,
        ];
    }

    /**
     * @return array{rate: float, source: string}
     */
    protected function resolvedListingCommissionRate(MpProduct $product, ChannelListing $listing): array
    {
        $marketplaceKey = MarketplaceProviderRegistry::normalize((string) data_get($listing->store, 'marketplace', ''));

        if ($marketplaceKey === 'woocommerce') {
            return [
                'rate' => $this->productProfitSettings()['woocommerce_commission_rate'],
                'source' => 'WooCommerce manuel ayar',
            ];
        }

        if ($marketplaceKey === 'koctas') {
            return [
                'rate' => $this->productProfitSettings()['koctas_commission_rate'],
                'source' => 'Koçtaş anlaşmalı oran',
            ];
        }

        if ($listing->commission_rate !== null) {
            return [
                'rate' => (float) $listing->commission_rate,
                'source' => $this->humanCommissionSource($listing->commission_source),
            ];
        }

        return [
            'rate' => (float) ($product->commission_rate ?? 0),
            'source' => 'Ürün yedek oranı',
        ];
    }

    protected function humanCommissionSource(?string $source): string
    {
        return match ($source) {
            'catalog' => 'Katalog API',
            'order_item' => 'Sipariş satırı',
            'financial_event' => 'Finans hareketi',
            'marketplace_default' => 'Kanal varsayılanı',
            'product_fallback' => 'Ürün kartı oranı',
            null, '' => 'Kanal verisi',
            default => Str::headline((string) $source),
        };
    }

    public function listingStatusLabel(?string $status): string
    {
        $status = Str::of((string) $status)
            ->trim()
            ->lower()
            ->replaceMatches('/[\s-]+/', '_')
            ->value();

        return match (true) {
            $status === '' => 'Durum yok',
            in_array($status, $this->activeListingStatuses(), true) => 'Yayında',
            in_array($status, ['pending', 'pending_approval', 'awaiting_approval', 'waiting_approval', 'review', 'under_review'], true) => 'Onay bekliyor',
            in_array($status, ['draft', 'private'], true) => 'Taslak',
            in_array($status, ['inactive', 'suspended', 'passive', 'paused', 'disabled', 'closed', 'archived', 'trash', 'deleted'], true) => 'Pasif',
            in_array($status, ['rejected', 'reject', 'blocked', 'not_approved'], true) => 'Reddedildi',
            in_array($status, ['out_of_stock', 'sold_out'], true) => 'Tükendi',
            default => 'Bilinmeyen durum',
        };
    }

    public function listingStatusTone(?string $status): string
    {
        $status = Str::of((string) $status)
            ->trim()
            ->lower()
            ->replaceMatches('/[\s-]+/', '_')
            ->value();

        return match (true) {
            in_array($status, $this->activeListingStatuses(), true) => 'success',
            in_array($status, ['pending', 'pending_approval', 'awaiting_approval', 'waiting_approval', 'review', 'under_review', 'draft', 'private'], true) => 'warning',
            in_array($status, ['inactive', 'suspended', 'passive', 'paused', 'disabled', 'closed', 'archived', 'trash', 'deleted', 'rejected', 'reject', 'blocked', 'not_approved', 'out_of_stock', 'sold_out'], true) => 'danger',
            default => 'default',
        };
    }

    public function marketplacePublicProductUrl(ChannelListing $listing): ?string
    {
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

    public function productChangeHistory(MpProduct $product, int $limit = 10): \Illuminate\Support\Collection
    {
        if (!Schema::hasTable('mp_product_change_logs')) {
            return collect();
        }

        $listingIds = ($product->channelListings ?? collect())
            ->pluck('id')
            ->filter()
            ->map(static fn ($id) => (int) $id)
            ->values()
            ->all();

        return MpProductChangeLog::query()
            ->with(['changedByUser:id,name', 'store:id,marketplace,store_name'])
            ->where(function (Builder $query) use ($product, $listingIds) {
                $query->where('mp_product_id', $product->id);

                if ($listingIds !== []) {
                    $query->orWhereIn('channel_listing_id', $listingIds);
                }
            })
            ->latest('changed_at')
            ->latest('id')
            ->limit($limit)
            ->get();
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
        // varsayılan tabloya otomatik taşı: COGS/Kargo/KDV/Kârlılık eksik kalmasın.
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

    protected function normalizeInlineValue(mixed $value, string $type): mixed
    {
        $value = is_string($value) ? trim($value) : $value;

        if (in_array($type, ['nullable_percent', 'nullable_integer', 'nullable_string'], true) && ($value === '' || $value === null)) {
            return null;
        }

        return match ($type) {
            'integer' => max(0, (int) $value),
            'positive_integer' => max(1, (int) $value),
            'nullable_integer' => max(0, (int) $value),
            'percent' => min(100, max(0, round((float) $value, 2))),
            'nullable_percent' => min(100, max(0, round((float) $value, 2))),
            'decimal' => max(0, round((float) $value, 2)),
            'string', 'nullable_string' => mb_substr((string) $value, 0, 80),
            default => $value,
        };
    }

    protected function normalizeToken(?string $value): string
    {
        return Str::lower(preg_replace('/\s+/', '', trim((string) $value)) ?? '');
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
        return ['active', 'approved', 'live', 'on_sale', 'onsale', 'published', 'publish', 'enabled'];
    }

    protected function productProfitSettings(): array
    {
        if ($this->productProfitSettingsCache !== null) {
            return $this->productProfitSettingsCache;
        }

        $settings = app(MpSettingsService::class);

        return $this->productProfitSettingsCache = [
            'default_marketplace' => $settings->getProductProfitDefaultMarketplace(),
            'woocommerce_commission_rate' => $settings->getProductProfitWooCommerceCommissionRate(),
            'koctas_commission_rate' => $settings->getProductProfitKoctasCommissionRate(),
        ];
    }

    protected function ensureProductSet(MpProduct $product): ProductSet
    {
        $set = ProductSet::query()->firstOrNew([
            'parent_mp_product_id' => $product->id,
        ]);

        if (!$set->exists) {
            $set->forceFill([
                'user_id' => $product->user_id,
                'name' => $product->product_name,
                'status' => ProductSet::STATUS_ACTIVE,
                'cost_mode' => $this->setCostMode,
                'logistics_mode' => $this->setLogisticsMode,
            ])->save();
        }

        $product->forceFill([
            'product_type' => 'set',
            'cost_source' => $set->cost_mode === ProductSet::MODE_SUM_COMPONENTS ? 'set' : 'manual',
            'logistics_source' => $set->logistics_mode === ProductSet::MODE_SUM_COMPONENTS ? 'set' : 'manual',
        ])->save();

        return $set->fresh(['items.componentProduct']) ?: $set;
    }

    protected function resolveSetItemForEditingProduct(int $itemId): ProductSetItem
    {
        return ProductSetItem::query()
            ->whereKey($itemId)
            ->whereHas('productSet', function (Builder $query) {
                $query->where('user_id', $this->userId())
                    ->where('parent_mp_product_id', $this->editingId);
            })
            ->firstOrFail();
    }

    protected function loadSetState(MpProduct $product): void
    {
        if (Schema::hasTable('product_sets')) {
            $set = ProductSet::query()
                ->where('user_id', $this->userId())
                ->where('parent_mp_product_id', $product->id)
                ->first();

            $this->setCostMode = $set?->cost_mode ?: ProductSet::MODE_SUM_COMPONENTS;
            $this->setLogisticsMode = $set?->logistics_mode ?: ProductSet::MODE_SUM_COMPONENTS;
        } else {
            $this->setCostMode = ProductSet::MODE_SUM_COMPONENTS;
            $this->setLogisticsMode = ProductSet::MODE_SUM_COMPONENTS;
        }

        $this->resetSetComponentForm();
    }

    protected function resetSetComponentForm(): void
    {
        $this->setComponentProductId = null;
        $this->setComponentQuantity = 1;
        $this->setIncludeCost = true;
        $this->setIncludePackaging = true;
        $this->setIncludeLogistics = true;
        $this->setCostOverride = null;
        $this->setCargoCostOverride = null;
        $this->setDesiOverride = null;
        $this->setPiecesOverride = null;
    }

    protected function refreshParentSetsForComponent(MpProduct $component): void
    {
        app(ProductCompositionResolver::class)->refreshParentSetsForComponent($component);
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
        $this->f_extra_cost_fixed = 0;
        $this->f_extra_cost_percentage = 0;
        $this->f_vat_rate = (int) round(app(MpSettingsService::class)->getDefaultProductVatRate() * 100);
        $this->f_cost_vat_rate = null;
        $this->f_market_price = 0;
        $this->f_sale_price = 0;
        $this->f_commission_rate = 0;
        $this->f_profit_commission_override_enabled = false;
        $this->f_stock_quantity = 0;
        $this->f_critical_stock_threshold = null;
        $this->f_return_rate = null;
        $this->f_desi = 0;
        $this->f_pieces = 1;
        $this->f_fast_delivery_type = '';
        $this->f_status = 'active';
        $this->f_platforms = '';
        $this->f_description = '';
        $this->f_image_url = '';
        $this->f_image_urls = [];
        $this->f_video_urls = [];
        $this->f_image_uploads = [];
        $this->clearListingQualityAnalysis();
        $this->creativeStudioInstruction = '';
        $this->creativeStudioAspectRatio = '1:1';
        $this->clearCreativeStudioImage();
        $this->creativeStudioVideoInstruction = '';
        $this->creativeStudioVideoAspectRatio = '9:16';
        $this->clearCreativeStudioVideo();
        $this->setSearch = '';
        $this->setCostMode = ProductSet::MODE_SUM_COMPONENTS;
        $this->setLogisticsMode = ProductSet::MODE_SUM_COMPONENTS;
        $this->resetSetComponentForm();
    }
}
