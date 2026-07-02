<?php

namespace App\Livewire;

use App\Models\AppNotification;
use App\Models\MpProduct;
use App\Models\TrendyolBestsellerReport;
use App\Models\TrendyolBoosterActivityLog;
use App\Models\TrendyolBoosterCampaignScenario;
use App\Models\TrendyolBoosterCompetitor;
use App\Models\TrendyolBoosterCostPreset;
use App\Models\TrendyolBoosterKeyword;
use App\Models\TrendyolBoosterProduct;
use App\Models\TrendyolBoosterStoreWatchItem;
use App\Models\TrendyolBoosterTrendKeyword;
use App\Services\Marketplace\TrendyolBestsellerReader;
use App\Services\Marketplace\TrendyolBestsellerReportService;
use App\Services\Marketplace\TrendyolBoosterActivityLogger;
use App\Services\Marketplace\TrendyolBoosterAnalysisService;
use App\Services\Marketplace\TrendyolBoosterCampaignScenarioService;
use App\Services\Marketplace\TrendyolBoosterCommissionRateService;
use App\Services\Marketplace\TrendyolBoosterCompetitorService;
use App\Services\Marketplace\TrendyolBoosterCostPresetService;
use App\Services\Marketplace\TrendyolBoosterCostRecommendationService;
use App\Services\Marketplace\TrendyolBoosterEmailDigestService;
use App\Services\Marketplace\TrendyolBoosterKeywordLookupService;
use App\Services\Marketplace\TrendyolBoosterKeywordService;
use App\Services\Marketplace\TrendyolBoosterModuleConfig;
use App\Services\Marketplace\TrendyolBoosterModuleInsightService;
use App\Services\Marketplace\TrendyolBoosterMonitorService;
use App\Services\Marketplace\TrendyolBoosterProductAnalysisService;
use App\Services\Marketplace\TrendyolBoosterResearchService;
use App\Services\Marketplace\TrendyolBoosterScheduledAnalysisService;
use App\Services\Marketplace\TrendyolBoosterSellDecisionService;
use App\Services\Marketplace\TrendyolBoosterStockService;
use App\Services\Marketplace\TrendyolBoosterStoreWatchService;
use App\Services\Marketplace\TrendyolBoosterSupplierResearchService;
use App\Services\Marketplace\TrendyolBoosterTrendKeywordService;
use App\Services\Marketplace\TrendyolCategoryDictionary;
use App\Services\Marketplace\TrendyolCommissionPdfParser;
use App\Services\Marketplace\TrendyolProductPageReader;
use App\Services\Marketplace\TrendyolSearchResultReader;
use App\Services\MpSettingsService;
use App\Services\NotificationCenterService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class TrendyolBooster extends Component
{
    use WithFileUploads;

    public static array $bestsellerColumnDefinitions = [
        'rank' => 'Sıra',
        'product' => 'Ürün',
        'seller' => 'Satıcı',
        'price' => 'Fiyat',
        'stock' => 'Görünen stok',
        'campaign' => 'Kampanya / Kupon',
        'sales' => 'Satış sinyali',
        'rating' => 'Puan / Yorum',
        'actions' => 'Aksiyon',
    ];

    public static array $bestsellerSortableColumns = [
        'rank' => 'rank',
        'product' => 'title',
        'seller' => 'seller_name',
        'price' => 'price',
        'stock' => 'stock_quantity',
        'campaign' => 'campaign_count',
        'sales' => 'estimated_sales_3d',
        'rating' => 'rating',
    ];

    public static array $supplierOfferColumnDefinitions = [
        'platform' => 'Kanal',
        'seller' => 'Satıcı / Ürün',
        'price' => 'Fiyat',
        'stock' => 'Stok',
        'match' => 'Eşleşme',
        'radar' => 'Radar',
        'actions' => 'Aksiyon',
    ];

    public static array $sortableColumns = [
        'platform' => 'platform_label',
        'seller' => 'seller_name',
        'price' => 'sale_price',
        'stock' => 'stock',
        'match' => 'match_score',
        'radar' => 'estimated_sales',
    ];

    public string $activeModule = 'analysis';

    public string $productUrl = '';

    public ?int $selectedAnalysisProductId = null;

    public string $productSearch = '';

    public ?int $selectedProductId = null;

    public ?int $selectedListingId = null;

    public string $title = '';

    public string $brand = '';

    public string $categoryName = '';

    public $salePrice = 0;

    public $cogs = 0;

    public $packagingCost = 0;

    public $cargoCost = 0;

    public $returnCargoCost = 0;

    public $commissionRate = 0;

    public $serviceFeeRate = 0;

    public $advertisingRate = 0;

    public $returnRate = 0;

    public $vatRate = 20;

    public $costVatRate = 20;

    public $expenseVatRate = 20;

    public $withholdingRate = 1;

    public $incomeTaxRate = 25;

    public $targetMarginPercent = 20;

    public bool $vatEnabled = true;

    public bool $withholdingEnabled = true;

    public bool $watchPrice = true;

    public bool $watchStock = false;

    public bool $watchKeyword = false;

    public bool $favoritesOnly = false;

    public string $analysisSearch = '';

    public string $analysisCategory = 'all';

    public string $analysisSort = 'latest';

    public string $trackingStatus = 'active';

    /** @var array<int, string> */
    public array $comparisonUrls = ['', '', '', ''];

    /** @var array<int, array<string, mixed>> */
    public array $comparisonResults = [];

    /** @var array<string, mixed> */
    public array $comparisonSummary = [];

    public ?string $comparisonGroupKey = null;

    /** @var array<int, string> */
    public array $marketUrls = ['', '', '', ''];

    /** @var array<int, array<string, mixed>> */
    public array $marketResults = [];

    /** @var array<string, mixed> */
    public array $marketSummary = [];

    public ?string $marketGroupKey = null;

    /** @var array<int, string> */
    public array $trackingVisibleColumns = ['product', 'price', 'stock', 'sales', 'interest', 'risk', 'quality', 'updated', 'actions'];

    /** @var array<string, mixed> */
    public array $sellDecisionResult = [];

    public bool $sellDecisionUseMarketSearch = true;

    public $targetRevenue = 100000;

    public $targetPeriodDays = 30;

    public string $insightSearch = '';

    public string $keywordTrackingInput = '';

    public ?int $keywordTrackingProductId = null;

    public ?int $keywordTrackingCurrentProductId = null;

    public string $keywordTrackingKeyword = '';

    public $keywordTrackingTarget = 10;

    /** @var array<int, string> */
    public array $competitorUrls = [];

    /** @var array<int, string> */
    public array $keywordInputs = [];

    /** @var array<int, int> */
    public array $keywordTargets = [];

    /** @var array<int, string> */
    public array $campaignNames = [];

    /** @var array<int, mixed> */
    public array $campaignDiscountRates = [];

    /** @var array<int, mixed> */
    public array $campaignCommissionDiscountRates = [];

    /** @var array<int, mixed> */
    public array $campaignAdvertisingRates = [];

    /** @var array<int, mixed> */
    public array $campaignExpectedUnits = [];

    public ?int $selectedCostPresetId = null;

    public string $costPresetName = '';

    public string $stockUrl = '';

    public string $stockBarcode = '';

    public string $stockSellerName = '';

    public $stockTotalStock = null;

    public string $keywordLookupInput = '';

    public string $storeWatchUrl = '';

    public ?int $selectedStoreWatchId = null;

    public string $storeDetailFilter = 'all';

    public string $storeDetailSort = 'rank_asc';

    public ?int $storeDetailAnalysisItemId = null;

    /** @var array<string, mixed> */
    public array $storeDetailAnalysis = [];

    /** @var array<int, string> */
    public array $visibleColumns = ['platform', 'seller', 'price', 'stock', 'match', 'radar', 'actions'];

    public string $supplierOfferSort = 'platform';

    public string $supplierOfferDirection = 'asc';

    public string $trendSearch = '';

    public string $trendCompetition = 'all';

    public ?int $trendTargetProductId = null;

    public string $commissionSearch = '';

    public string $commissionSort = 'commission_rate';

    public string $commissionDirection = 'desc';

    /** Kategori filtresi (boş = tümü) */
    public string $commissionCategoryFilter = '';

    /** Komisyon aralığı: '', 'high' (≥%25), 'mid' (%15-24,9), 'low' (<%15) */
    public string $commissionRateRange = '';

    /** Vade filtresi: '', '14', '21' ... */
    public string $commissionMaturityFilter = '';

    /** PDF yükleme geçici dosyası */
    public $commissionPdfFile = null;

    /** PDF import sonucu mesajı: null | {status:'ok'|'error', message:string, count:int} */
    public ?array $commissionImportStatus = null;

    public $shippingPdfFile = null;

    public ?array $shippingImportStatus = null;

    /** @var array<string, mixed> */
    public array $costRecommendation = [];

    public string $historySearch = '';

    public string $historyType = 'all';

    public string $bestsellerSearch = '';

    public ?int $bestsellerMinPrice = null;

    public ?int $bestsellerMaxPrice = null;

    public array $bestsellerResults = [];

    public bool $isBestsellerLoading = false;

    public string $bestsellerMode = 'live';

    public string $bestsellerSourceUrl = '';

    public string $bestsellerMatchedLabel = '';

    public string $bestsellerResultSource = 'manual';

    public ?int $selectedBestsellerReportId = null;

    public array $bestsellerTrackedProductIds = [];

    public array $bestsellerVisibleColumns = ['rank', 'product', 'seller', 'price', 'stock', 'campaign', 'sales', 'rating', 'actions'];

    public string $bestsellerSortField = 'rank';

    public string $bestsellerSortDirection = 'asc';

    public string $message = '';

    public string $messageType = 'success';

    protected $queryString = [
        'activeModule' => ['except' => 'analysis', 'as' => 'booster'],
        'favoritesOnly' => ['except' => false, 'as' => 'favorites'],
        'productSearch' => ['except' => '', 'as' => 'q'],
        'selectedProductId' => ['except' => null, 'as' => 'product'],
    ];

    public function mount(): void
    {
        if (! in_array($this->activeModule, $this->availableBoosterModules(), true)) {
            $this->activeModule = 'analysis';
        }
        if ($this->activeModule !== 'tracking') {
            $this->favoritesOnly = false;
        }

        $settings = new MpSettingsService($this->userId());
        $this->vatEnabled = $settings->isKdvEnabled();
        $this->withholdingEnabled = $settings->isEstimatedWithholdingEnabled();
        $this->withholdingRate = $this->percentValue($settings->getStopajRate());
        $this->vatRate = $this->percentValue($settings->getDefaultProductVatRate()) ?: 20;
        $this->costVatRate = $this->vatRate;
        $this->expenseVatRate = $this->percentValue($settings->getExpenseVatRate()) ?: 20;

        if ($this->selectedProductId) {
            $this->loadProduct($this->selectedProductId);
        }
    }

    #[Computed]
    public function bestsellerTableRows(): array
    {
        $rows = collect($this->bestsellerResults)
            ->map(fn (array $item, int $index): array => $item + ['_result_index' => $index])
            ->values()
            ->all();
        $field = $this->bestsellerSortField;
        $direction = $this->bestsellerSortDirection;

        usort($rows, function (array $left, array $right) use ($field, $direction): int {
            $leftValue = data_get($left, $field);
            $rightValue = data_get($right, $field);

            if ($leftValue === null && $rightValue === null) {
                return 0;
            }
            if ($leftValue === null) {
                return 1;
            }
            if ($rightValue === null) {
                return -1;
            }

            $comparison = is_numeric($leftValue) && is_numeric($rightValue)
                ? (float) $leftValue <=> (float) $rightValue
                : strnatcasecmp((string) $leftValue, (string) $rightValue);

            return $direction === 'desc' ? -$comparison : $comparison;
        });

        return $rows;
    }

    #[Computed]
    public function bestsellerReportDashboard(): array
    {
        if ($this->activeModule !== 'bestseller' || ! $this->bestsellerReportsReady()) {
            return [
                'reports' => [],
                'selected' => null,
                'analysis' => [
                    'summary' => [
                        'run_count' => 0,
                        'unique_product_count' => 0,
                        'rising_count' => 0,
                        'falling_count' => 0,
                        'new_entry_count' => 0,
                        'exit_count' => 0,
                        'persistent_count' => 0,
                    ],
                    'run_labels' => [],
                    'runs' => [],
                    'products' => [],
                    'latest_items' => [],
                ],
            ];
        }

        return app(TrendyolBestsellerReportService::class)
            ->dashboard($this->userId(), $this->selectedBestsellerReportId);
    }

    #[Computed]
    public function bestsellerCurrentReport(): ?array
    {
        if ($this->activeModule !== 'bestseller' || ! $this->bestsellerReportsReady() || mb_strlen(trim($this->bestsellerSearch)) < 2) {
            return null;
        }

        $report = app(TrendyolBestsellerReportService::class)->findForQuery(
            $this->userId(),
            $this->bestsellerSearch,
            $this->bestsellerMinPrice,
            $this->bestsellerMaxPrice,
        );

        return $report instanceof TrendyolBestsellerReport ? [
            'id' => $report->id,
            'run_count' => (int) $report->run_count,
            'last_captured_at' => $report->last_captured_at?->toIso8601String(),
        ] : null;
    }

    public function analyzeBestsellerSeller(?string $sellerId, string $sellerName): array
    {
        if (! $sellerId) {
            return [
                'tracked_count' => '-',
                'seller_score' => '-',
                'success_score' => 'Bilinmiyor',
                'url' => '#',
            ];
        }

        $trackedProducts = TrendyolBoosterProduct::query()
            ->where('user_id', $this->userId())
            ->where('seller_id', $sellerId)
            ->with('latestSnapshot')
            ->get();

        $trackedCount = $trackedProducts->count();
        $avgScore = $trackedProducts->avg('seller_score');

        $c = 0;
        $totalSales = 0;
        foreach ($trackedProducts as $tp) {
            if ($tp->latestSnapshot) {
                $totalSales += (int) $tp->latestSnapshot->estimated_sales;
                $c++;
            }
        }

        return [
            'tracked_count' => $trackedCount > 0 ? $trackedCount.' ürün takibinizde' : 'Takibinizde ürün yok',
            'seller_score' => $avgScore > 0 ? number_format($avgScore, 1, ',', '.') : 'Bilinmiyor',
            'success_score' => $c > 0 ? 'Aylık ~'.number_format($totalSales, 0, '', '.').' takipçi satışı' : 'Henüz veri yok',
            'url' => 'https://www.trendyol.com/magaza/profil-m-'.$sellerId,
        ];
    }

    #[Computed]
    public function productOptions(): Collection
    {
        return MpProduct::query()
            ->where('user_id', $this->userId())
            ->when(trim($this->productSearch) !== '', function (Builder $query): void {
                $search = trim($this->productSearch);
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('product_name', 'like', "%{$search}%")
                        ->orWhere('stock_code', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%");
                });
            })
            ->orderBy('product_name')
            ->limit(50)
            ->get([
                'id',
                'product_name',
                'stock_code',
                'barcode',
                'brand',
                'category_name',
                'sale_price',
                'cogs',
                'packaging_cost',
                'cargo_cost',
                'commission_rate',
                'vat_rate',
                'cost_vat_rate',
                'return_rate',
            ]);
    }

    #[Computed]
    public function preview(): array
    {
        if (! $this->boosterTablesReady()) {
            return $this->emptyPreview();
        }

        return $this->booster()->preview($this->analysisInput());
    }

    #[Computed]
    public function productAnalysis(): array
    {
        if (! $this->productAnalysisTablesReady() || $this->selectedAnalysisProductId === null) {
            return [];
        }

        $product = TrendyolBoosterProduct::query()
            ->where('user_id', $this->userId())
            ->find($this->selectedAnalysisProductId);

        if (! $product) {
            return [];
        }

        $snapshots = $product->snapshots()
            ->whereNotNull('analysis_source')
            ->latest('checked_at')
            ->latest('id')
            ->limit(2)
            ->get();
        $current = $snapshots->get(0);

        if (! $current) {
            return [];
        }

        return app(TrendyolBoosterProductAnalysisService::class)
            ->present($product, $current, $snapshots->get(1));
    }

    #[Computed]
    public function financeDashboard(): array
    {
        $input = array_replace($this->sellDecisionInput(), [
            'vat_enabled' => true,
            'withholding_enabled' => true,
            'cost_vat_rate' => $this->vatRate,
        ]);

        return app(TrendyolBoosterModuleInsightService::class)->finance(
            $input,
            (float) $this->targetRevenue,
            (int) $this->targetPeriodDays,
            0,
            0,
            $this->observedDailySales(),
        );
    }

    #[Computed]
    public function marketInsightDashboard(): array
    {
        if (! $this->marketInsightReady()) {
            return [
                'type' => $this->activeModule,
                'summary' => ['total' => 0],
                'rows' => collect(),
                'filtered_count' => 0,
            ];
        }

        return app(TrendyolBoosterModuleInsightService::class)->marketDashboard(
            $this->activeModule,
            $this->userId(),
            $this->insightSearch,
            $this->activeModule === 'keyword_tracking' ? $this->keywordTrackingCurrentProductId : null
        );
    }

    #[Computed]
    public function trackedProductOptions(): Collection
    {
        if (! $this->boosterTablesReady()) {
            return collect();
        }

        return TrendyolBoosterProduct::query()
            ->where('user_id', $this->userId())
            ->whereNotNull('trendyol_product_id')
            ->where('trendyol_product_id', '!=', '')
            ->orderBy('title')
            ->limit(100)
            ->get(['id', 'title', 'brand', 'trendyol_product_id']);
    }

    #[Computed]
    public function dashboard(): array
    {
        if (! $this->boosterTablesReady()) {
            return [
                'total' => 0,
                'watching_price' => 0,
                'watching_stock' => 0,
                'favorite_count' => 0,
                'auto_refresh_count' => 0,
                'filtered_count' => 0,
                'strong_count' => 0,
                'risk_count' => 0,
                'average_score' => 0.0,
                'last_checked_at' => null,
                'products' => collect(),
            ];
        }

        return $this->booster()->dashboard(
            $this->userId(),
            $this->favoritesOnly,
            $this->analysisSearch,
            $this->analysisCategory,
            $this->analysisSort,
        );
    }

    #[Computed]
    public function keywordTrackingCurrentProduct(): ?TrendyolBoosterProduct
    {
        if (! $this->keywordTrackingCurrentProductId) {
            return null;
        }

        return TrendyolBoosterProduct::query()
            ->where('user_id', $this->userId())
            ->find($this->keywordTrackingCurrentProductId);
    }

    #[Computed]
    public function trackingDashboard(): array
    {
        if (! $this->boosterTablesReady()) {
            return $this->dashboard;
        }

        return $this->booster()->dashboard(
            $this->userId(),
            $this->favoritesOnly,
            $this->analysisSearch,
            $this->analysisCategory,
            $this->analysisSort,
            $this->trackingStatus,
        );
    }

    #[Computed]
    public function analysisCategories(): Collection
    {
        if (! $this->boosterTablesReady()) {
            return collect();
        }

        return TrendyolBoosterProduct::query()
            ->where('user_id', $this->userId())
            ->whereNotNull('category_name')
            ->where('category_name', '!=', '')
            ->distinct()
            ->orderBy('category_name')
            ->pluck('category_name');
    }

    #[Computed]
    public function costPresets(): Collection
    {
        if (! $this->boosterCostPresetTablesReady()) {
            return collect();
        }

        return app(TrendyolBoosterCostPresetService::class)->presets($this->userId());
    }

    #[Computed]
    public function stockDashboard(): array
    {
        if (! $this->boosterStockTablesReady()) {
            return [
                'total_checks' => 0,
                'last_total_stock' => 0,
                'estimated_sales' => 0,
                'seller_count' => 0,
                'latest_checks' => collect(),
            ];
        }

        return app(TrendyolBoosterStockService::class)->dashboard($this->userId());
    }

    #[Computed]
    public function keywordLookupDashboard(): array
    {
        if (! $this->boosterKeywordLookupTablesReady()) {
            return [
                'total' => 0,
                'last_result_count' => 0,
                'latest' => collect(),
                'unique_keywords' => 0,
            ];
        }

        return app(TrendyolBoosterKeywordLookupService::class)->dashboard($this->userId());
    }

    #[Computed]
    public function storeWatchDashboard(): array
    {
        if (! $this->boosterStoreWatchTablesReady()) {
            return [
                'total' => 0,
                'new_products' => 0,
                'price_changes' => 0,
                'latest' => collect(),
            ];
        }

        return app(TrendyolBoosterStoreWatchService::class)->dashboard($this->userId());
    }

    #[Computed]
    public function storeDetail(): ?array
    {
        if (! $this->selectedStoreWatchId || ! $this->boosterStoreWatchTablesReady()) {
            return null;
        }

        $sortParts = explode('_', $this->storeDetailSort);
        $sort = $sortParts[0] ?? 'rank';
        $direction = $sortParts[1] ?? 'asc';

        return app(TrendyolBoosterStoreWatchService::class)->storeDetail(
            $this->userId(),
            $this->selectedStoreWatchId,
            $sort,
            $direction,
            $this->storeDetailFilter
        );
    }

    #[Computed]
    public function supplierResearchDashboard(): array
    {
        if (! $this->supplierResearchTablesReady()) {
            return [
                'total' => 0,
                'latest' => null,
                'offers' => collect(),
                'trendyol_offers' => collect(),
                'external_offers' => collect(),
                'coverage' => collect(),
                'covered_platforms' => 0,
                'target_platforms' => 0,
            ];
        }

        $dashboard = app(TrendyolBoosterSupplierResearchService::class)->dashboard($this->userId());
        $sortField = self::$sortableColumns[$this->supplierOfferSort] ?? 'platform_label';
        $descending = $this->supplierOfferDirection === 'desc';
        $offers = collect($dashboard['offers'])->sortBy(
            fn ($offer) => data_get($offer, $sortField),
            SORT_REGULAR,
            $descending,
        )->values();

        $dashboard['offers'] = $offers;
        $dashboard['trendyol_offers'] = $offers->where('platform', 'trendyol')->values();
        $dashboard['external_offers'] = $offers->where('platform', '!=', 'trendyol')->values();

        return $dashboard;
    }

    #[Computed]
    public function trendDashboard(): array
    {
        if (! $this->boosterTrendKeywordTablesReady()) {
            return [
                'total' => 0,
                'rising_count' => 0,
                'opportunity_count' => 0,
                'source_product_count' => 0,
                'source_store_count' => 0,
                'last_scanned_at' => null,
                'rows' => collect(),
            ];
        }

        return app(TrendyolBoosterTrendKeywordService::class)
            ->dashboard($this->userId(), $this->trendSearch, $this->trendCompetition);
    }

    #[Computed]
    public function commissionDashboard(): array
    {
        if (! $this->boosterCommissionTablesReady()) {
            return [
                'total' => 0,
                'highest' => 0,
                'last_update' => null,
                'rows' => collect(),
                'categories' => [],
            ];
        }

        return app(TrendyolBoosterCommissionRateService::class)
            ->dashboard(
                $this->userId(),
                $this->commissionSearch,
                $this->commissionSort,
                $this->commissionDirection,
                $this->commissionCategoryFilter,
                $this->commissionRateRange,
                $this->commissionMaturityFilter,
            );
    }

    #[Computed]
    public function shippingDashboard(): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('trendyol_booster_shipping_rates')) {
            return [
                'total' => 0,
                'last_update' => null,
                'companies' => [],
                'pivot' => [],
            ];
        }

        return app(\App\Services\Marketplace\TrendyolBoosterShippingRateService::class)->dashboard($this->userId());
    }

    #[Computed]
    public function priceDashboard(): array
    {
        if (! $this->boosterTablesReady() || ! $this->boosterSnapshotTablesReady()) {
            return [
                'total' => 0,
                'dropped' => 0,
                'biggest_drop' => 0,
                'last_checked_at' => null,
                'products' => collect(),
            ];
        }

        $base = TrendyolBoosterProduct::query()
            ->where('user_id', $this->userId())
            ->where('watch_price', true);
        $products = (clone $base)
            ->with([
                'latestSnapshot',
                'snapshots' => fn ($query) => $query->latest('checked_at')->limit(12),
            ])
            ->latest('last_checked_at')
            ->limit(12)
            ->get();

        return [
            'total' => (clone $base)->count(),
            'dropped' => (int) $products->filter(fn ($product) => (float) ($product->latestSnapshot?->price_delta ?? 0) < 0)->count(),
            'biggest_drop' => (float) $products->min(fn ($product) => (float) ($product->latestSnapshot?->price_delta_percent ?? 0)),
            'last_checked_at' => $products->max('last_checked_at'),
            'products' => $products,
        ];
    }

    #[Computed]
    public function activityDashboard(): array
    {
        if (! $this->boosterActivityTablesReady()) {
            return [
                'total' => 0,
                'stock' => 0,
                'keyword' => 0,
                'last' => null,
                'logs' => collect(),
            ];
        }

        $base = TrendyolBoosterActivityLog::query()->where('user_id', $this->userId());
        $logs = (clone $base)
            ->when($this->historyType !== 'all', fn (Builder $query) => $query->where('activity_type', $this->historyType))
            ->when(trim($this->historySearch) !== '', function (Builder $query): void {
                $search = trim($this->historySearch);
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%")
                        ->orWhere('summary', 'like', "%{$search}%");
                });
            })
            ->latest('recorded_at')
            ->limit(50)
            ->get();

        return [
            'total' => (clone $base)->count(),
            'stock' => (clone $base)->where('activity_type', 'stock_check')->count(),
            'keyword' => (clone $base)->whereIn('activity_type', ['keyword_lookup', 'keyword_tracking'])->count(),
            'last' => (clone $base)->latest('recorded_at')->first()?->recorded_at,
            'logs' => $logs,
        ];
    }

    #[Computed]
    public function boosterNotificationPreferences(): array
    {
        $notificationCenter = app(NotificationCenterService::class);
        $available = $notificationCenter->isAvailable();
        $mutedTypes = $available ? $notificationCenter->mutedTypesForUser($this->userId()) : [];
        $groups = collect($this->boosterNotificationGroups())
            ->map(function (array $group, string $key) use ($mutedTypes): array {
                $types = $group['types'];
                $mutedCount = collect($types)->filter(fn (string $type): bool => in_array($type, $mutedTypes, true))->count();

                return $group + [
                    'key' => $key,
                    'active' => $mutedCount === 0,
                    'muted_count' => $mutedCount,
                ];
            })
            ->values();

        return [
            'available' => $available,
            'email_digest_enabled' => (bool) config('marketplace.trendyol_booster.email_digest_enabled', false),
            'email_digest_max_notifications' => (int) config('marketplace.trendyol_booster.email_digest_max_notifications', 100),
            'muted_types' => $mutedTypes,
            'active_groups' => $groups->where('active', true)->count(),
            'total_groups' => $groups->count(),
            'groups' => $groups->all(),
            'pending_email_count' => $this->pendingBoosterEmailDigestCount($mutedTypes),
        ];
    }

    public function updatedSelectedProductId(mixed $value): void
    {
        $this->loadProduct((int) $value);
    }

    public function setActiveModule(string $module): void
    {
        if (! in_array($module, $this->availableBoosterModules(), true)) {
            return;
        }

        $this->activeModule = $module;
        $this->favoritesOnly = false;
        $this->dispatchBoosterModuleChanged($module);
    }

    public function runStockQuery(): void
    {
        if (! $this->boosterStockTablesReady()) {
            $this->message = 'Stok sorgulama tabloları henüz hazır değil. Migration sonrası sorgu alınabilir.';
            $this->messageType = 'error';

            return;
        }

        $this->validate([
            'stockUrl' => $this->productUrlRulesFor('stockUrl'),
            'stockTotalStock' => ['nullable', 'integer', 'min:0'],
            'stockBarcode' => ['nullable', 'string', 'max:120'],
            'stockSellerName' => ['nullable', 'string', 'max:180'],
        ]);

        $result = app(TrendyolBoosterStockService::class)->check($this->userId(), [
            'source_url' => $this->stockUrl,
            'total_stock' => $this->stockTotalStock,
            'barcode' => $this->stockBarcode,
            'seller_name' => $this->stockSellerName,
        ]);

        $this->message = $result['message'];
        $this->messageType = $result['ok'] ? 'success' : 'error';

        if ($result['ok']) {
            $this->stockTotalStock = null;
        }

        unset($this->stockDashboard, $this->marketInsightDashboard, $this->activityDashboard);
    }

    public function stockBridgeCompleted(string $message, bool $ok): void
    {
        $this->message = Str::limit(trim($message), 1000, '');
        $this->messageType = $ok ? 'success' : 'error';

        if ($ok) {
            $this->stockTotalStock = null;
        }

        unset($this->stockDashboard, $this->marketInsightDashboard, $this->activityDashboard);
    }

    public function runKeywordLookup(): void
    {
        if (! $this->boosterKeywordLookupTablesReady()) {
            $this->message = 'Anahtar kelime aratma tablosu henüz hazır değil. Migration sonrası arama alınabilir.';
            $this->messageType = 'error';

            return;
        }

        $this->validate([
            'keywordLookupInput' => ['required', 'string', 'min:2', 'max:180'],
        ]);

        $result = app(TrendyolBoosterKeywordLookupService::class)->search($this->userId(), $this->keywordLookupInput);

        $this->message = $result['message'];
        $this->messageType = $result['ok'] ? 'success' : 'error';
        unset($this->keywordLookupDashboard, $this->activityDashboard);
    }

    public function trackKeywordFromTool(): void
    {
        if (! $this->boosterKeywordTablesReady()) {
            $this->message = 'Anahtar kelime takip tablosu hazır değil. Migration sonrası tekrar deneyin.';
            $this->messageType = 'error';

            return;
        }

        if ($this->keywordTrackingProductId !== null) {
            $validated = $this->validate([
                'keywordTrackingProductId' => ['required', 'integer', 'min:1'],
                'keywordTrackingKeyword' => ['required', 'string', 'min:2', 'max:1080'],
                'keywordTrackingTarget' => ['required', 'integer', 'min:1', 'max:500'],
            ]);
            $tracked = TrendyolBoosterProduct::query()
                ->where('user_id', $this->userId())
                ->findOrFail((int) $validated['keywordTrackingProductId']);
            $keywords = collect(preg_split('/[\r\n,;]+/u', (string) $validated['keywordTrackingKeyword']) ?: [])
                ->map(fn (string $keyword): string => trim($keyword))
                ->filter(fn (string $keyword): bool => mb_strlen($keyword) >= 2)
                ->unique(fn (string $keyword): string => mb_strtolower($keyword))
                ->take(6)
                ->values();

            if ($keywords->isEmpty()) {
                $this->addError('keywordTrackingKeyword', 'En az bir geçerli anahtar kelime girin.');

                return;
            }

            $results = $keywords->map(fn (string $keyword): array => app(TrendyolBoosterKeywordService::class)->addKeyword(
                $tracked,
                $keyword,
                (int) $validated['keywordTrackingTarget'],
            ));
            $successCount = $results->where('ok', true)->count();
            $this->keywordTrackingCurrentProductId = $tracked->id;
            $this->message = $successCount === $keywords->count()
                ? $successCount.' anahtar kelime takibe alındı ve ilk 50 sonuçta kontrol edildi.'
                : $successCount.'/'.$keywords->count().' anahtar kelime takibe alındı.';
            $this->messageType = $successCount > 0 ? 'success' : 'warning';
            $this->keywordTrackingKeyword = '';

            unset($this->marketInsightDashboard, $this->activityDashboard, $this->keywordTrackingCurrentProduct);

            return;
        }

        $validated = $this->validate([
            'keywordTrackingInput' => $this->productUrlRulesFor('keywordTrackingInput'),
            'keywordTrackingKeyword' => ['required', 'string', 'min:2', 'max:1080'],
            'keywordTrackingTarget' => ['required', 'integer', 'min:1', 'max:500'],
        ]);

        $keywords = collect(preg_split('/[\r\n,;]+/u', (string) $validated['keywordTrackingKeyword']) ?: [])
            ->map(fn (string $keyword): string => trim($keyword))
            ->filter(fn (string $keyword): bool => mb_strlen($keyword) >= 2)
            ->unique(fn (string $keyword): string => mb_strtolower($keyword))
            ->take(6)
            ->values()
            ->toArray();

        if (empty($keywords)) {
            $this->addError('keywordTrackingKeyword', 'En az bir geçerli anahtar kelime girin.');

            return;
        }

        $this->dispatch('booster:keyword-tracking-bridge',
            url: $validated['keywordTrackingInput'],
            keywords: $keywords,
            target_rank: (int) $validated['keywordTrackingTarget'],
        );
    }

    /**
     * @param  array<int, string>  $keywords
     */
    public function keywordTrackingServerFallback(string $sourceUrl, array $keywords, int $targetRank): void
    {
        if (! $this->boosterKeywordTablesReady()) {
            $this->message = 'Anahtar kelime takip tablosu hazır değil. Migration sonrası tekrar deneyin.';
            $this->messageType = 'error';

            return;
        }

        $sourceUrl = trim($sourceUrl);
        $targetRank = max(1, min(500, $targetRank));
        $keywords = collect($keywords)
            ->map(fn (mixed $keyword): string => trim((string) $keyword))
            ->filter(fn (string $keyword): bool => mb_strlen($keyword) >= 2)
            ->unique(fn (string $keyword): string => mb_strtolower($keyword))
            ->take(6)
            ->values()
            ->all();

        $validator = \Illuminate\Support\Facades\Validator::make([
            'sourceUrl' => $sourceUrl,
        ], [
            'sourceUrl' => $this->productUrlRulesFor('sourceUrl'),
        ]);

        if ($validator->fails()) {
            $this->message = $validator->errors()->first('sourceUrl') ?: 'Geçerli bir Trendyol ürün linki girin.';
            $this->messageType = 'error';

            return;
        }

        if ($keywords === []) {
            $this->message = 'En az bir geçerli anahtar kelime girin.';
            $this->messageType = 'error';

            return;
        }

        try {
            $pageResult = app(TrendyolProductPageReader::class)->fetch($sourceUrl);

            if (! $pageResult['ok']) {
                throw new \RuntimeException($pageResult['message']);
            }

            $page = (array) $pageResult['data'];
            $result = app(TrendyolBoosterProductAnalysisService::class)->store(
                $this->userId(),
                $this->researchPayload($page, $sourceUrl),
                'manual_refresh',
            );
            $tracked = $result['product'];
            $tracked->forceFill([
                'tracking_status' => $tracked->tracking_status ?: 'candidate',
                'tracking_sources' => collect((array) $tracked->tracking_sources)
                    ->push('keyword_tracking')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
                'analysis_auto_refresh_enabled' => (bool) $tracked->analysis_auto_refresh_enabled,
            ])->save();

            $service = app(TrendyolBoosterKeywordService::class);
            $successCount = 0;
            $lastMessage = '';

            foreach ($keywords as $keyword) {
                $keywordResult = $service->addKeyword($tracked, $keyword, $targetRank);
                $lastMessage = $keywordResult['message'];

                if ($keywordResult['ok']) {
                    $successCount++;
                }
            }

            $this->keywordTrackingCurrentProductId = $tracked->id;
            $this->keywordTrackingKeyword = '';
            $this->message = $successCount > 0
                ? $successCount.'/'.count($keywords).' anahtar kelime sunucu okuyucusu ile takibe alındı.'
                : ($lastMessage ?: 'Anahtar kelime takibi sunucu okuyucusu ile tamamlanamadı.');
            $this->messageType = $successCount > 0 ? 'success' : 'error';
            unset($this->marketInsightDashboard, $this->activityDashboard, $this->keywordTrackingCurrentProduct);
        } catch (\Throwable $exception) {
            report($exception);
            $this->message = 'Anahtar kelime takibi sunucu okuyucusu ile tamamlanamadı: '.Str::limit($exception->getMessage(), 300, '');
            $this->messageType = 'error';
        }
    }

    public function keywordTrackingBridgeCompleted(array $payload): void
    {
        if (! ($payload['ok'] ?? false)) {
            $this->message = $payload['message'] ?? 'Kelime takibi başarısız oldu.';
            $this->messageType = 'error';

            return;
        }

        try {
            $productPayload = $payload['product'] ?? null;
            if (! $productPayload) {
                throw new \RuntimeException('Ürün bilgisi okunamadı.');
            }

            // Create/update product
            $page = (array) ($productPayload['page'] ?? []);

            $existing = TrendyolBoosterProduct::query()
                ->where('user_id', $this->userId())
                ->where('trendyol_product_id', (string) ($page['product_id'] ?? $page['trendyol_product_id'] ?? ''))
                ->first();

            if (! $existing) {
                $result = app(TrendyolBoosterProductAnalysisService::class)->store(
                    $this->userId(),
                    $this->researchPayload($page, (string) ($payload['product']['source_url'] ?? '')),
                    'manual_refresh',
                );
                $existing = $result['product'];
                $existing->forceFill([
                    'tracking_status' => 'candidate',
                    'tracking_sources' => ['keyword_tracking'],
                    'analysis_auto_refresh_enabled' => false,
                    'next_analysis_refresh_at' => null,
                ])->save();
            }

            $this->keywordTrackingCurrentProductId = $existing->id;

            $keywords = $payload['keywords'] ?? [];
            $successCount = 0;

            foreach ($keywords as $kwData) {
                if ($kwData['status'] === 'error') {
                    continue;
                }

                $keywordText = trim((string) $kwData['keyword']);
                $keywordHash = hash('sha256', Str::lower($keywordText));
                $keyword = TrendyolBoosterKeyword::query()
                    ->where('user_id', $this->userId())
                    ->where('trendyol_booster_product_id', $existing->id)
                    ->where('keyword', $keywordText)
                    ->first();

                if ($keyword) {
                    $keyword->forceFill([
                        'target_rank' => $this->keywordTrackingTarget,
                        'is_active' => true,
                    ])->save();
                } else {
                    $keyword = TrendyolBoosterKeyword::query()->create([
                        'user_id' => $this->userId(),
                        'trendyol_booster_product_id' => $existing->id,
                        'keyword_hash' => $keywordHash,
                        'keyword' => $keywordText,
                        'target_rank' => $this->keywordTrackingTarget,
                        'is_active' => true,
                    ]);
                }

                $observedRank = isset($kwData['rank']) ? (int) $kwData['rank'] : null;
                $resultCount = max(0, (int) ($kwData['result_count'] ?? 0));
                $checkedResultCount = max(0, (int) ($kwData['checked_count'] ?? (
                    $observedRank !== null ? $observedRank : min(120, $resultCount)
                )));

                app(TrendyolBoosterKeywordService::class)->recordObservation(
                    $keyword,
                    $observedRank,
                    $resultCount,
                    $checkedResultCount,
                );
                $successCount++;
            }

            $this->message = $successCount === count($keywords)
                ? $successCount.' anahtar kelime takibe alındı ve arama sonuçlarında kontrol edildi.'
                : $successCount.'/'.count($keywords).' anahtar kelime başarıyla güncellendi.';
            $this->messageType = $successCount > 0 ? 'success' : 'warning';
            $this->keywordTrackingKeyword = '';

            unset($this->marketInsightDashboard, $this->activityDashboard, $this->keywordTrackingCurrentProduct);
        } catch (\Throwable $exception) {
            report($exception);
            $this->message = 'Kelime takibi kaydedilemedi: '.Str::limit($exception->getMessage(), 300, '');
            $this->messageType = 'error';
        }
    }

    public function scanStoreWatch(): void
    {
        if (! $this->boosterStoreWatchTablesReady()) {
            $this->message = 'Rakip mağaza takip tabloları henüz hazır değil. Migration sonrası tarama alınabilir.';
            $this->messageType = 'error';

            return;
        }

        $this->validate([
            'storeWatchUrl' => $this->productUrlRulesFor('storeWatchUrl'),
        ]);

        // Backend fallback taraması — eklenti olmasa da çalışır
        try {
            $backendResult = app(\App\Services\Marketplace\TrendyolBoosterStoreWatchService::class)
                ->scan($this->userId(), $this->storeWatchUrl);

            if ($backendResult['ok'] && (($backendResult['watch'] ?? null)?->items?->isNotEmpty() ?? false)) {
                $this->message = $backendResult['message'] ?? 'Rakip mağaza tarandı.';
                $this->messageType = 'success';
                unset($this->storeWatchDashboard, $this->marketInsightDashboard, $this->activityDashboard);
                // Chrome eklentisi varsa daha derin tarama için yine dispatch et
                $this->dispatch('booster:store-scan-bridge', url: $this->storeWatchUrl, optional: true);
                return;
            }
        } catch (\Throwable) {
            // Backend fallback başarısız, Chrome eklentisiyle devam et
        }

        $this->dispatch('booster:store-scan-bridge', url: $this->storeWatchUrl);
    }

    #[On('store-scan-bridge-completed')]
    public function storeScanBridgeCompleted(array $payload): void
    {
        if (isset($payload['ok']) && $payload['ok'] === false) {
            $this->message = $payload['message'] ?? 'Chrome Eklentisi mağazayı okuyamadı.';
            $this->messageType = 'error';
            return;
        }

        if (empty($payload['items'])) {
            $this->message = $payload['message'] ?? 'Chrome Companion mağazadan ürün döndürmedi; boş takip kaydı oluşturulmadı.';
            $this->messageType = 'warning';
            unset($this->storeWatchDashboard, $this->marketInsightDashboard, $this->activityDashboard);

            return;
        }

        try {
            $result = app(\App\Services\Marketplace\TrendyolBoosterStoreWatchService::class)->scan($this->userId(), $this->storeWatchUrl, $payload);
            $this->message = $result['message'] ?? 'Rakip mağaza tarandı.';
            $this->messageType = $result['ok'] ? 'success' : 'error';
            unset($this->storeWatchDashboard, $this->marketInsightDashboard, $this->activityDashboard);
        } catch (\Exception $e) {
             $this->message = 'Kaydedilirken hata oluştu: ' . $e->getMessage();
             $this->messageType = 'error';
        }
    }

    public function viewStoreDetail(int $watchId): void
    {
        $this->selectedStoreWatchId = $watchId;
        $this->storeDetailFilter = 'all';
        $this->storeDetailSort = 'rank_asc';
        $this->storeDetailAnalysisItemId = null;
        $this->storeDetailAnalysis = [];
    }

    public function closeStoreDetail(): void
    {
        $this->selectedStoreWatchId = null;
        $this->storeDetailAnalysisItemId = null;
        $this->storeDetailAnalysis = [];
    }

    public function closeStoreDetailAnalysis(): void
    {
        $this->storeDetailAnalysisItemId = null;
        $this->storeDetailAnalysis = [];
    }

    public function analyzeStoreWatchItem(int $itemId): void
    {
        if (! $this->productAnalysisTablesReady()) {
            $this->message = 'Ürün analizi tabloları hazır değil. Migration sonrası tekrar deneyin.';
            $this->messageType = 'error';

            return;
        }

        $item = TrendyolBoosterStoreWatchItem::query()
            ->where('user_id', $this->userId())
            ->when($this->selectedStoreWatchId, fn (Builder $query) => $query->where('trendyol_booster_store_watch_id', $this->selectedStoreWatchId))
            ->with('storeWatch')
            ->findOrFail($itemId);

        $this->storeDetailAnalysisItemId = $item->id;

        try {
            $page = $this->storeWatchItemAnalysisPage($item);

            if (! $this->hasUsableProductAnalysisData($page)) {
                throw new \RuntimeException('Bu ürün kartında analiz için geçerli fiyat yakalanamadı.');
            }

            $result = app(TrendyolBoosterProductAnalysisService::class)->store(
                $this->userId(),
                $this->researchPayload($page, (string) $page['source_url']),
                'manual_refresh',
            );
            $tracked = $result['product'];
            $tracked->forceFill([
                'tracking_status' => $tracked->tracking_status ?: 'candidate',
                'tracking_sources' => collect((array) $tracked->tracking_sources)
                    ->push('competitor_detail')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
                'analysis_auto_refresh_enabled' => false,
                'next_analysis_refresh_at' => null,
            ])->save();

            $this->selectedAnalysisProductId = $tracked->id;
            $this->storeDetailAnalysis = [
                'item_id' => $item->id,
                'ok' => true,
                'message' => 'Ürün analizi hazır.',
                'analysis' => $result['analysis'],
            ];
            $this->message = 'Rakip ürün analizi detay panelinde hazırlandı.';
            $this->messageType = 'success';
        } catch (\Throwable $exception) {
            report($exception);
            $this->storeDetailAnalysis = [
                'item_id' => $item->id,
                'ok' => false,
                'message' => 'Ürün analizi oluşturulamadı: '.Str::limit($exception->getMessage(), 180, ''),
                'analysis' => [],
            ];
            $this->message = (string) $this->storeDetailAnalysis['message'];
            $this->messageType = 'error';
        }

        unset($this->productAnalysis, $this->dashboard, $this->trackingDashboard, $this->activityDashboard);
    }

    public function runSupplierResearch(): void
    {
        if (! $this->supplierResearchTablesReady()) {
            $this->message = 'Tedarikçi Radar tabloları henüz hazır değil. Migration sonrası araştırma alınabilir.';
            $this->messageType = 'error';

            return;
        }

        $this->validate([
            'storeWatchUrl' => $this->productUrlRulesFor('storeWatchUrl'),
        ]);

        $result = app(TrendyolBoosterSupplierResearchService::class)->researchFromUrl($this->userId(), $this->storeWatchUrl);
        $this->message = $result['message'];
        $this->messageType = $result['ok'] ? 'success' : 'error';
        unset($this->supplierResearchDashboard, $this->storeWatchDashboard, $this->marketInsightDashboard, $this->activityDashboard);
    }

    public function supplierResearchBridgeCompleted(string $message, bool $ok): void
    {
        $this->message = Str::limit(trim($message), 1000, '');
        $this->messageType = $ok ? 'success' : 'error';
        unset($this->supplierResearchDashboard, $this->storeWatchDashboard, $this->marketInsightDashboard, $this->activityDashboard);
    }

    public function toggleColumn(string $column): void
    {
        if (! array_key_exists($column, self::$supplierOfferColumnDefinitions) || $column === 'seller') {
            return;
        }

        if (in_array($column, $this->visibleColumns, true)) {
            $this->visibleColumns = array_values(array_filter($this->visibleColumns, fn (string $item): bool => $item !== $column));
        } else {
            $this->visibleColumns[] = $column;
        }
    }

    public function sortTable(string $column): void
    {
        if (! array_key_exists($column, self::$sortableColumns)) {
            return;
        }

        if ($this->supplierOfferSort === $column) {
            $this->supplierOfferDirection = $this->supplierOfferDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->supplierOfferSort = $column;
            $this->supplierOfferDirection = 'asc';
        }

        unset($this->supplierResearchDashboard);
    }

    public function discoverTrendKeywords(): void
    {
        if (! $this->boosterTrendKeywordTablesReady()) {
            $this->message = 'Trend kelime tablosu henüz hazır değil. Migration sonrası keşif yapılabilir.';
            $this->messageType = 'error';

            return;
        }

        if (! $this->boosterStoreWatchTablesReady()) {
            $this->message = 'Rakip mağaza tabloları hazır değil. Önce Rakip Takibi modülünü çalıştırın.';
            $this->messageType = 'error';

            return;
        }

        $summary = app(TrendyolBoosterTrendKeywordService::class)->discoverFromCompetitors($this->userId());

        if ($summary['keywords'] === 0) {
            $this->message = 'Keşif için ürünleri okunmuş aktif bir rakip mağaza bulunamadı. Önce Rakip Takibi bölümünde mağazayı tarayın.';
            $this->messageType = 'warning';
        } else {
            $detail = $summary['products'].' rakip üründen '.$summary['keywords'].' kelime sinyali çıkarıldı.';
            $this->logActivity('trend_keywords', 'Trend Kelimeler', 'Rakiplerden kelime keşfi', $detail, 'kelime', $summary['keywords']);
            $this->message = $detail;
            $this->messageType = 'success';
        }

        unset($this->trendDashboard, $this->marketInsightDashboard, $this->activityDashboard);
    }

    public function seedTrendKeywords(): void
    {
        $this->discoverTrendKeywords();
    }

    public function trackTrendKeyword(int $trendKeywordId): void
    {
        if (! $this->boosterKeywordTablesReady()) {
            $this->message = 'Anahtar kelime takip tablosu hazır değil.';
            $this->messageType = 'error';

            return;
        }

        $validated = $this->validate([
            'trendTargetProductId' => ['required', 'integer', 'min:1'],
        ], [
            'trendTargetProductId.required' => 'Sırasını izleyeceğiniz ürünü seçin.',
        ]);
        $tracked = TrendyolBoosterProduct::query()
            ->where('user_id', $this->userId())
            ->findOrFail((int) $validated['trendTargetProductId']);
        $trendKeyword = TrendyolBoosterTrendKeyword::query()
            ->where('user_id', $this->userId())
            ->findOrFail($trendKeywordId);
        $keyword = (string) $trendKeyword->keyword;
        $result = app(TrendyolBoosterKeywordService::class)->addKeyword($tracked, $keyword, 20);

        $this->keywordTrackingProductId = $tracked->id;
        $this->keywordTrackingCurrentProductId = $tracked->id;
        $this->activeModule = 'keyword_tracking';
        $this->favoritesOnly = false;
        $this->message = $result['ok']
            ? '“'.$keyword.'” kelimesi takibe alındı ve ilk 50 sonuçta kontrol edildi.'
            : '“'.$keyword.'” takibe alındı; ilk sıra kontrolü şu anda tamamlanamadı.';
        $this->messageType = $result['ok'] ? 'success' : 'warning';
        $this->dispatchBoosterModuleChanged('keyword_tracking');

        unset($this->marketInsightDashboard, $this->activityDashboard, $this->keywordTrackingCurrentProduct);
    }

    public function sortCommissions(string $column): void
    {
        if ($this->commissionSort === $column) {
            $this->commissionDirection = $this->commissionDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->commissionSort = $column;
            $this->commissionDirection = 'desc';
        }

        unset($this->commissionDashboard);
    }

    public function resetCommissionFilters(): void
    {
        $this->commissionSearch = '';
        $this->commissionCategoryFilter = '';
        $this->commissionRateRange = '';
        $this->commissionMaturityFilter = '';
        unset($this->commissionDashboard);
    }

    public function seedCommissionRates(): void
    {
        if (! $this->boosterCommissionTablesReady()) {
            $this->message = 'Komisyon tablosu henüz hazır değil. Migration sonrası örnek set yüklenebilir.';
            $this->messageType = 'error';

            return;
        }

        $created = app(TrendyolBoosterCommissionRateService::class)->seedDefaults($this->userId());
        $this->logActivity('commission_rates', 'Komisyon Oranları', 'Örnek komisyon seti', $created.' yeni oran eklendi.', 'oran', $created);
        $this->message = $created > 0 ? $created.' komisyon oranı yüklendi.' : 'Komisyon oranı seti zaten güncel.';
        $this->messageType = 'success';
        unset($this->commissionDashboard, $this->activityDashboard);
    }

    public function updatedCommissionPdfFile()
    {
        $this->importCommissionPdf();
    }

    public function importCommissionPdf(): void
    {
        if (! $this->boosterCommissionTablesReady()) {
            $this->commissionImportStatus = [
                'status' => 'error',
                'message' => 'Komisyon tablosu henüz hazır değil. Migration çalıştırın.',
                'count' => 0,
            ];

            return;
        }

        $this->validate([
            'commissionPdfFile' => [
                'required',
                'file',
                'mimes:pdf',
                'max:20480', // 20 MB
            ],
        ], [
            'commissionPdfFile.required' => 'Lütfen bir PDF dosyası seçin.',
            'commissionPdfFile.mimes' => 'Yalnızca PDF dosyası yüklenebilir.',
            'commissionPdfFile.max' => 'Dosya boyutu 20 MB\'ı aşamaz.',
        ]);

        try {
            // Geçici dosyaya kaydet
            $tmpPath = $this->commissionPdfFile->getRealPath();

            // Parse et
            $result = app(TrendyolCommissionPdfParser::class)->parse($tmpPath);

            if (! $result['ok']) {
                $this->commissionImportStatus = [
                    'status' => 'error',
                    'message' => $result['error'] ?? 'PDF parse edilemedi.',
                    'count' => 0,
                ];

                return;
            }

            if (empty($result['rows'])) {
                $this->commissionImportStatus = [
                    'status' => 'error',
                    'message' => 'PDF\'de geçerli komisyon satırı bulunamadı. Doğru Trendyol komisyon tablosu PDF\'i mi yüklediniz?',
                    'count' => 0,
                ];

                return;
            }

            // DB'ye upsert
            $userId = $this->userId();
            $service = app(TrendyolBoosterCommissionRateService::class);
            $upserted = 0;

            foreach ($result['rows'] as $row) {
                $service->upsertFromParser($userId, $row);
                $upserted++;
            }

            $this->commissionImportStatus = [
                'status' => 'ok',
                'message' => $upserted.' komisyon oranı güncellendi.',
                'count' => $upserted,
            ];

            $this->commissionPdfFile = null;
            unset($this->commissionDashboard);

            $this->logActivity(
                'commission_rates',
                'Komisyon Oranları',
                'PDF Import',
                $upserted.' oran PDF\'den güncellendi.',
                'oran',
                $upserted
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('importCommissionPdf hatası', ['err' => $e->getMessage()]);
            $this->commissionImportStatus = [
                'status' => 'error',
                'message' => 'İçe aktarma sırasında hata: '.$e->getMessage(),
                'count' => 0,
            ];
        }
    }

    public function updatedShippingPdfFile()
    {
        $this->importShippingPdf();
    }

    public function importShippingPdf(): void
    {
        if (! Schema::hasTable('trendyol_booster_shipping_rates')) {
            $this->shippingImportStatus = [
                'status' => 'error',
                'message' => 'Kargo tablosu henüz hazır değil. Migration çalıştırın.',
                'count' => 0,
            ];

            return;
        }

        $this->validate([
            'shippingPdfFile' => [
                'required',
                'file',
                'mimes:pdf',
                'max:20480', // 20 MB
            ],
        ], [
            'shippingPdfFile.required' => 'Lütfen bir PDF dosyası seçin.',
            'shippingPdfFile.mimes' => 'Yalnızca PDF dosyası yüklenebilir.',
            'shippingPdfFile.max' => 'Dosya boyutu 20 MB\'ı aşamaz.',
        ]);

        try {
            // Geçici dosyaya kaydet
            $tmpPath = $this->shippingPdfFile->getRealPath();

            // Parse et
            $result = app(\App\Services\Marketplace\TrendyolShippingPdfParser::class)->parse($tmpPath);

            if (! $result['ok']) {
                $this->shippingImportStatus = [
                    'status' => 'error',
                    'message' => $result['error'] ?? 'PDF parse edilemedi.',
                    'count' => 0,
                ];

                return;
            }

            if (empty($result['rows'])) {
                $this->shippingImportStatus = [
                    'status' => 'error',
                    'message' => 'PDF\'de geçerli kargo satırı bulunamadı. Doğru Trendyol kargo fiyatları PDF\'i mi yüklediniz?',
                    'count' => 0,
                ];

                return;
            }

            // DB'ye upsert
            $userId = $this->userId();
            $service = app(\App\Services\Marketplace\TrendyolBoosterShippingRateService::class);
            $upserted = 0;

            foreach ($result['rows'] as $row) {
                $service->upsertFromParser($userId, $row);
                $upserted++;
            }

            $this->shippingImportStatus = [
                'status' => 'ok',
                'message' => $upserted.' kargo oranı (desi bazlı) güncellendi.',
                'count' => $upserted,
            ];

            $this->shippingPdfFile = null;

            $this->logActivity(
                'shipping_rates',
                'Kargo Fiyatları',
                'PDF Import',
                $upserted.' kargo fiyatı PDF\'den güncellendi.',
                'fiyat',
                $upserted
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('importShippingPdf hatası', ['err' => $e->getMessage()]);
            $this->shippingImportStatus = [
                'status' => 'error',
                'message' => 'İçe aktarma sırasında hata: '.$e->getMessage(),
                'count' => 0,
            ];
        }
    }

    public function fetchProductFromUrl(): void
    {
        $this->validate([
            'productUrl' => $this->productUrlRules(),
        ]);

        $result = app(TrendyolProductPageReader::class)->fetch($this->productUrl);

        if (! $result['ok']) {
            $this->message = $result['message'];
            $this->messageType = 'error';

            return;
        }

        $data = $result['data'];
        $this->productUrl = (string) ($data['source_url'] ?? $this->productUrl);
        $this->title = $this->filledText($data['title'] ?? null, $this->title);
        $this->brand = $this->filledText($data['brand'] ?? null, $this->brand);
        $this->categoryName = $this->filledText($data['category_name'] ?? null, $this->categoryName);

        if ((float) ($data['sale_price'] ?? 0) > 0) {
            $this->salePrice = (float) $data['sale_price'];
        }

        $this->refreshCostRecommendation($data, $this->sellDecisionTrackedProductFromUrl());

        $this->message = trim($result['message'].' '.$this->costRecommendationSummary());
        $this->messageType = (float) ($data['sale_price'] ?? 0) > 0 ? 'success' : 'warning';
        unset($this->preview, $this->financeDashboard);
    }

    public function financeProductBridgeCompleted(
        ?int $trackedProductId,
        ?float $salePrice,
        string $message,
        bool $ok,
    ): void {
        if (! $ok || $trackedProductId === null) {
            $this->message = $message ?: 'Chrome Companion canlı ürün fiyatını okuyamadı.';
            $this->messageType = 'error';

            return;
        }

        $tracked = TrendyolBoosterProduct::query()
            ->where('user_id', $this->userId())
            ->find($trackedProductId);

        if (! $tracked) {
            $this->message = 'Canlı ürün verisi alındı ancak ürün kaydı bulunamadı.';
            $this->messageType = 'error';

            return;
        }

        $livePrice = (float) ($salePrice ?: $tracked->sale_price);

        if ($livePrice <= 0) {
            $this->message = 'Chrome Companion ürünü okudu ancak geçerli satış fiyatı bulamadı.';
            $this->messageType = 'warning';

            return;
        }

        $this->selectedAnalysisProductId = $tracked->id;
        $this->selectedProductId = $tracked->mp_product_id;
        $this->selectedListingId = $tracked->channel_listing_id;
        $this->productUrl = (string) $tracked->source_url;
        $this->title = $this->filledText($tracked->title, $this->title);
        $this->brand = $this->filledText($tracked->brand, $this->brand);
        $this->categoryName = $this->filledText($tracked->category_name, $this->categoryName);
        $this->salePrice = $livePrice;
        $this->refreshCostRecommendation([], $tracked);

        $this->message = trim('Canlı Trendyol fiyatı Chrome Companion ile alındı: '
            .$this->formatMoney($livePrice).'. '.$this->costRecommendationSummary());
        $this->messageType = 'success';
        unset($this->financeDashboard, $this->preview, $this->productAnalysis, $this->dashboard);
    }

    public function applyCostScenario(string $scenario): void
    {
        if (! in_array($scenario, ['low', 'base', 'high'], true)) {
            return;
        }

        $row = collect((array) data_get($this->costRecommendation, 'shipping.scenarios', []))
            ->firstWhere('key', $scenario);

        if (! is_array($row) || ! is_numeric($row['cost_gross'] ?? null)) {
            $this->message = 'Bu senaryo için geçerli kargo tarifesi bulunamadı.';
            $this->messageType = 'warning';

            return;
        }

        $this->cargoCost = (float) $row['cost_gross'];
        data_set($this->costRecommendation, 'desi.billable_desi', (int) $row['desi']);
        data_set($this->costRecommendation, 'shipping.cost_gross', (float) $row['cost_gross']);
        data_set($this->costRecommendation, 'shipping.cost_net', (float) ($row['cost_net'] ?? 0));
        $this->message = ucfirst($scenario).' kargo senaryosu hesaplamaya uygulandı: '
            .$row['desi'].' desi, '.$this->formatMoney((float) $row['cost_gross']).'.';
        $this->messageType = 'success';
        unset($this->financeDashboard);
    }

    public function applyCommissionLevel(int $level): void
    {
        if ($level < 1 || $level > 5) {
            return;
        }

        $row = collect((array) data_get($this->costRecommendation, 'commission.alternatives', []))
            ->firstWhere('level', $level);

        if (! is_array($row) || ! is_numeric($row['rate'] ?? null)) {
            return;
        }

        $this->commissionRate = (float) $row['rate'];
        data_set($this->costRecommendation, 'commission.rate', (float) $row['rate']);
        data_set($this->costRecommendation, 'commission.seller_level', $level);
        $this->message = 'Seviye '.$level.' komisyon oranı hesaplamaya uygulandı: %'
            .number_format((float) $row['rate'], 2, ',', '.').'.';
        $this->messageType = 'success';
        unset($this->financeDashboard);
    }

    public function runSellDecision(): void
    {
        if (! $this->boosterTablesReady() || ! $this->productAnalysisTablesReady()) {
            $this->message = 'Sat veya Satma kararı için Booster analiz tabloları hazır olmalı. Migration sonrası tekrar deneyin.';
            $this->messageType = 'error';

            return;
        }

        $this->validateSellDecisionForm();

        try {
            $pageResult = app(TrendyolProductPageReader::class)->fetch($this->productUrl);

            if (! $pageResult['ok']) {
                throw new \RuntimeException($pageResult['message']);
            }

            $page = (array) $pageResult['data'];
            $this->applyProductPageData($page);
            $this->refreshCostRecommendation($page, $this->sellDecisionTrackedProductFromUrl());

            if ((float) $this->salePrice <= 0) {
                $tracked = $this->sellDecisionTrackedProductFromUrl();

                if (! $tracked || (float) $tracked->sale_price <= 0) {
                    throw new \RuntimeException('Trendyol sunucu erişimini sınırladı ve daha önce kaydedilmiş canlı fiyat bulunamadı. Chrome Companion ile tekrar deneyin veya satış fiyatını manuel girin.');
                }

                $this->applyTrackedProductData($tracked);
                $tracked = $this->booster()->store($this->userId(), $this->analysisInput());
            } else {
                $this->booster()->store($this->userId(), $this->analysisInput());
                $analysis = app(TrendyolBoosterProductAnalysisService::class)->store(
                    $this->userId(),
                    $this->researchPayload($page, $this->productUrl),
                    'manual_refresh',
                );
                $tracked = $analysis['product'];
            }

            $this->completeSellDecision($tracked);
        } catch (\Throwable $exception) {
            report($exception);
            $this->message = 'Sat veya Satma kararı üretilemedi: '.Str::limit($exception->getMessage(), 300, '');
            $this->messageType = 'error';
        }

        unset($this->preview, $this->productAnalysis, $this->dashboard, $this->trackingDashboard, $this->activityDashboard);
    }

    public function sellDecisionBridgeCompleted(?int $trackedProductId, string $message, bool $ok): void
    {
        if (! $ok || $trackedProductId === null) {
            $this->message = $message ?: 'Chrome Companion canlı ürün verisini okuyamadı.';
            $this->messageType = 'error';

            return;
        }

        try {
            $this->validateSellDecisionForm();
            $tracked = TrendyolBoosterProduct::query()
                ->where('user_id', $this->userId())
                ->find($trackedProductId);

            if (! $tracked || (float) $tracked->sale_price <= 0) {
                throw new \RuntimeException('Chrome Companion ürünü kaydetti ancak geçerli satış fiyatı bulunamadı.');
            }

            $this->applyTrackedProductData($tracked);
            $this->refreshCostRecommendation([], $tracked);
            $tracked = $this->booster()->store($this->userId(), $this->analysisInput());
            $this->completeSellDecision($tracked);
            $this->message = 'Canlı Trendyol verisi Chrome Companion ile okundu; Sat veya Satma kararı üretildi.';
        } catch (\Throwable $exception) {
            report($exception);
            $this->message = 'Sat veya Satma kararı üretilemedi: '.Str::limit($exception->getMessage(), 300, '');
            $this->messageType = 'error';
        }

        unset($this->preview, $this->productAnalysis, $this->dashboard, $this->trackingDashboard, $this->activityDashboard);
    }

    public function productAnalysisBridgeCompleted(?int $trackedProductId, string $message, bool $ok): void
    {
        $this->message = $message;
        $this->messageType = $ok ? 'success' : 'error';

        if (! $ok || $trackedProductId === null) {
            return;
        }

        $tracked = TrendyolBoosterProduct::query()
            ->where('user_id', $this->userId())
            ->find($trackedProductId);

        if (! $tracked) {
            $this->message = 'Ürün analizi kaydedildi ancak kayıt yenilenemedi.';
            $this->messageType = 'warning';

            return;
        }

        $this->selectedAnalysisProductId = $tracked->id;
        $this->productUrl = (string) $tracked->source_url;
        $this->title = (string) $tracked->title;
        $this->brand = (string) $tracked->brand;
        $this->categoryName = (string) $tracked->category_name;
        $this->salePrice = (float) $tracked->sale_price;
        unset($this->productAnalysis, $this->dashboard, $this->trackingDashboard, $this->preview);
    }

    public function toggleProductFavorite(int $trackedProductId): void
    {
        $tracked = TrendyolBoosterProduct::query()
            ->where('user_id', $this->userId())
            ->findOrFail($trackedProductId);

        $tracked->forceFill(['is_favorite' => ! $tracked->is_favorite])->save();
        $this->message = $tracked->is_favorite
            ? 'Ürün favorilere eklendi. Takip listesindeki Favoriler filtresinden ulaşabilirsiniz.'
            : 'Ürün favorilerden çıkarıldı.';
        $this->messageType = 'success';
        unset($this->productAnalysis, $this->dashboard, $this->trackingDashboard);
    }

    public function toggleFavoritesOnly(): void
    {
        $this->favoritesOnly = ! $this->favoritesOnly;
        $this->dispatchBoosterModuleChanged(
            $this->activeModule === 'tracking' && $this->favoritesOnly ? 'favorites' : $this->activeModule,
        );
        unset($this->dashboard, $this->trackingDashboard);
    }

    public function openFavorites(): void
    {
        $this->activeModule = 'tracking';
        $this->favoritesOnly = true;
        $this->dispatchBoosterModuleChanged('favorites');
        unset($this->dashboard, $this->trackingDashboard);
    }

    public function resetAnalysisFilters(): void
    {
        $this->analysisSearch = '';
        $this->analysisCategory = 'all';
        $this->analysisSort = 'latest';
        $this->favoritesOnly = false;
        $this->dispatchBoosterModuleChanged($this->activeModule);
        unset($this->dashboard, $this->trackingDashboard);
    }

    public function analyzeResearchProduct(): void
    {
        $this->validate(['productUrl' => $this->productUrlRules()]);

        try {
            $pageResult = app(TrendyolProductPageReader::class)->fetch($this->productUrl);
            if (! $pageResult['ok']) {
                throw new \RuntimeException($pageResult['message']);
            }

            $page = (array) $pageResult['data'];

            if (! $this->hasUsableProductAnalysisData($page)) {
                $this->selectedAnalysisProductId = null;
                $this->message = 'Ürün analiz edilemedi: Trendyol sunucu erişimini sınırladı veya canlı fiyat yayınlamadı. ZOLM Chrome Companion eklentisini yenileyip tekrar deneyin.';
                $this->messageType = 'error';
                unset($this->productAnalysis, $this->dashboard, $this->trackingDashboard);

                return;
            }

            $existing = TrendyolBoosterProduct::query()
                ->where('user_id', $this->userId())
                ->where('trendyol_product_id', (string) ($page['trendyol_product_id'] ?? ''))
                ->first();
            $result = app(TrendyolBoosterProductAnalysisService::class)->store(
                $this->userId(),
                $this->researchPayload($page, $this->productUrl),
                'manual_refresh',
            );
            $tracked = $result['product'];

            if (! $existing) {
                $tracked->forceFill([
                    'tracking_status' => 'candidate',
                    'tracking_sources' => ['product_analysis'],
                    'analysis_auto_refresh_enabled' => false,
                    'next_analysis_refresh_at' => null,
                ])->save();
            }

            $this->selectedAnalysisProductId = $tracked->id;
            $this->title = (string) $tracked->title;
            $this->brand = (string) $tracked->brand;
            $this->categoryName = (string) $tracked->category_name;
            $this->salePrice = (float) $tracked->sale_price;
            $this->message = 'Ürün analiz edildi. Sürekli ölçüm için Takibe Al düğmesini kullanın.';
            $this->messageType = 'success';
        } catch (\Throwable $exception) {
            report($exception);
            $this->message = 'Ürün analiz edilemedi: '.Str::limit($exception->getMessage(), 300, '');
            $this->messageType = 'error';
        }

        unset($this->productAnalysis, $this->dashboard, $this->trackingDashboard);
    }

    public function runProductComparison(): void
    {
        $this->runResearchComparison('comparison');
    }

    public function runMarketComparison(): void
    {
        $this->runResearchComparison('market');
    }

    /** @param array<int, array<string, mixed>> $payloads */
    public function comparisonBridgeCompleted(array $payloads, string $kind, string $message, bool $ok): void
    {
        if (! $ok || ! in_array($kind, ['comparison', 'market'], true)) {
            $this->message = $message ?: 'Tarayıcıdan karşılaştırma verisi alınamadı.';
            $this->messageType = 'error';

            return;
        }

        try {
            $comparison = app(TrendyolBoosterResearchService::class)->comparePayloads($payloads);
            $this->setResearchResults($kind, $comparison);
            $this->message = $kind === 'market'
                ? 'Pazar karşılaştırması tamamlandı.'
                : 'Ürün karşılaştırması tamamlandı.';
            $this->messageType = 'success';
        } catch (\Throwable $exception) {
            $this->message = Str::limit($exception->getMessage(), 300, '');
            $this->messageType = 'error';
        }
    }

    public function trackResearchProduct(string $kind, int $index): void
    {
        $results = $kind === 'market' ? $this->marketResults : $this->comparisonResults;
        $groupKey = $kind === 'market' ? $this->marketGroupKey : $this->comparisonGroupKey;

        if (! isset($results[$index]) || ! in_array($kind, ['comparison', 'market'], true)) {
            $this->message = 'Takibe alınacak araştırma ürünü bulunamadı.';
            $this->messageType = 'error';

            return;
        }

        $tracked = app(TrendyolBoosterResearchService::class)
            ->track($this->userId(), $results[$index], $kind, $groupKey)['product'];
        $this->message = ($tracked->title ?: 'Ürün').' Booster Radar takibine alındı.';
        $this->messageType = 'success';
        unset($this->dashboard, $this->trackingDashboard);
    }

    public function trackAllResearchProducts(string $kind): void
    {
        $results = $kind === 'market' ? $this->marketResults : $this->comparisonResults;
        $groupKey = $kind === 'market' ? $this->marketGroupKey : $this->comparisonGroupKey;

        if ($results === [] || ! in_array($kind, ['comparison', 'market'], true)) {
            return;
        }

        foreach ($results as $payload) {
            app(TrendyolBoosterResearchService::class)->track($this->userId(), $payload, $kind, $groupKey);
        }

        $this->message = count($results).' ürün Booster Radar takibine alındı.';
        $this->messageType = 'success';
        unset($this->dashboard, $this->trackingDashboard);
    }

    public function followTrackedProduct(int $trackedProductId, string $source = 'product_analysis'): void
    {
        $tracked = TrendyolBoosterProduct::query()
            ->where('user_id', $this->userId())
            ->findOrFail($trackedProductId);
        $sources = collect((array) $tracked->tracking_sources)->push($source)->filter()->unique()->values()->all();

        $tracked->forceFill([
            'tracking_status' => 'active',
            'tracking_sources' => $sources,
            'tracking_started_at' => $tracked->tracking_started_at ?: now(),
            'tracking_paused_at' => null,
            'watch_stock' => true,
            'analysis_auto_refresh_enabled' => true,
            'analysis_refresh_interval_minutes' => $this->trackingIntervalMinutes(),
            'next_analysis_refresh_at' => now(),
        ])->save();

        $this->message = 'Ürün Booster Radar takibine alındı. İlk otomatik tarama sıraya eklendi.';
        $this->messageType = 'success';
        unset($this->productAnalysis, $this->dashboard, $this->trackingDashboard);
    }

    public function pauseTrackedProduct(int $trackedProductId): void
    {
        $tracked = TrendyolBoosterProduct::query()
            ->where('user_id', $this->userId())
            ->findOrFail($trackedProductId);

        $tracked->forceFill([
            'tracking_status' => 'paused',
            'tracking_paused_at' => now(),
            'analysis_auto_refresh_enabled' => false,
            'next_analysis_refresh_at' => null,
        ])->save();

        $this->message = 'Ürün takibi duraklatıldı; geçmiş veriler korunuyor.';
        $this->messageType = 'success';
        unset($this->dashboard, $this->trackingDashboard);
    }

    public function toggleTrackingColumn(string $column): void
    {
        $allowed = array_keys($this->trackingColumnDefinitions());
        if (! in_array($column, $allowed, true) || $column === 'product') {
            return;
        }

        $this->trackingVisibleColumns = in_array($column, $this->trackingVisibleColumns, true)
            ? array_values(array_filter($this->trackingVisibleColumns, fn (string $value): bool => $value !== $column))
            : array_values(array_unique([...$this->trackingVisibleColumns, $column]));
    }

    public function sortTracking(string $column): void
    {
        $this->analysisSort = match ($column) {
            'price' => $this->analysisSort === 'price_desc' ? 'price_asc' : 'price_desc',
            'interest' => 'interest_desc',
            'risk' => 'risk_desc',
            'sales' => 'sales_desc',
            default => 'latest',
        };
        unset($this->trackingDashboard);
    }

    public function showProductAnalysis(int $trackedProductId): void
    {
        $tracked = TrendyolBoosterProduct::query()
            ->where('user_id', $this->userId())
            ->findOrFail($trackedProductId);

        $this->selectedAnalysisProductId = $tracked->id;
        unset($this->productAnalysis);
    }

    public function refreshProductAnalysisNow(int $trackedProductId): void
    {
        if (! $this->analysisRefreshReady()) {
            $this->message = 'Analiz yenileme alanları henüz hazır değil. Migration tamamlandıktan sonra tekrar deneyin.';
            $this->messageType = 'error';

            return;
        }

        $tracked = TrendyolBoosterProduct::query()
            ->where('user_id', $this->userId())
            ->findOrFail($trackedProductId);

        try {
            $result = app(TrendyolBoosterScheduledAnalysisService::class)->refresh($tracked);
            $this->selectedAnalysisProductId = $tracked->id;
            $this->message = $result['message'];
            $this->messageType = 'success';
        } catch (\Throwable $exception) {
            report($exception);
            $this->message = 'Anlık analiz yenilenemedi: '.Str::limit($exception->getMessage(), 300, '');
            $this->messageType = 'error';
        }

        unset($this->productAnalysis, $this->dashboard);
    }

    public function toggleAnalysisAutoRefresh(int $trackedProductId): void
    {
        if (! $this->analysisRefreshReady()) {
            $this->message = 'Otomatik analiz alanları henüz hazır değil.';
            $this->messageType = 'error';

            return;
        }

        $tracked = TrendyolBoosterProduct::query()
            ->where('user_id', $this->userId())
            ->findOrFail($trackedProductId);
        $enabled = ! (bool) $tracked->analysis_auto_refresh_enabled;

        $tracked->forceFill([
            'analysis_auto_refresh_enabled' => $enabled,
            'next_analysis_refresh_at' => $enabled ? now() : null,
            'last_analysis_refresh_error' => $enabled ? null : $tracked->last_analysis_refresh_error,
        ])->save();

        $this->message = $enabled
            ? 'Otomatik analiz açıldı. İlk yenileme sıraya alındı.'
            : 'Otomatik analiz kapatıldı.';
        $this->messageType = 'success';
        unset($this->dashboard, $this->trackingDashboard);
    }

    public function updateAnalysisRefreshInterval(int $trackedProductId, mixed $minutes): void
    {
        if (! $this->analysisRefreshReady()) {
            return;
        }

        $interval = (int) $minutes;
        abort_unless(in_array($interval, [60, 360, 720, 1440, 10080], true), 422);

        $tracked = TrendyolBoosterProduct::query()
            ->where('user_id', $this->userId())
            ->findOrFail($trackedProductId);

        $tracked->forceFill([
            'analysis_refresh_interval_minutes' => $interval,
            'next_analysis_refresh_at' => $tracked->analysis_auto_refresh_enabled
                ? now()->addMinutes($interval)
                : null,
        ])->save();

        $this->message = 'Otomatik analiz aralığı güncellendi.';
        $this->messageType = 'success';
        unset($this->dashboard);
    }

    protected function trackingIntervalMinutes(): int
    {
        return max(60, min(1440, (int) config('marketplace.trendyol_booster.tracking_interval_minutes', 60)));
    }

    #[Computed]
    public function trackingSchedulerState(): array
    {
        $value = Cache::get('marketplace:trendyol-booster:last-scheduler-run-at');

        try {
            $lastRunAt = filled($value) ? Carbon::parse((string) $value) : null;
        } catch (\Throwable) {
            $lastRunAt = null;
        }

        $healthy = $lastRunAt !== null && $lastRunAt->greaterThanOrEqualTo(now()->subMinutes(15));

        return [
            'healthy' => $healthy,
            'last_run_at' => $lastRunAt,
            'label' => $healthy ? 'Tarama motoru çalışıyor' : 'Tarama motoru bekliyor',
        ];
    }

    public function analyzeAndTrack(): void
    {
        if (! $this->boosterTablesReady()) {
            $this->message = 'Trendyol Booster tabloları henüz hazır değil. Migration çalıştırıldıktan sonra kayıt alınabilir.';
            $this->messageType = 'error';

            return;
        }

        $this->validate([
            'productUrl' => $this->productUrlRules(),
            'salePrice' => ['required', 'numeric', 'min:0.01'],
            'cogs' => ['nullable', 'numeric', 'min:0'],
            'packagingCost' => ['nullable', 'numeric', 'min:0'],
            'cargoCost' => ['nullable', 'numeric', 'min:0'],
            'commissionRate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'returnRate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'targetMarginPercent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $tracked = $this->booster()->store($this->userId(), $this->analysisInput());

        $this->message = $tracked->wasRecentlyCreated
            ? 'Booster takibine eklendi.'
            : 'Booster analizi güncellendi.';
        $this->messageType = 'success';
        $this->selectedAnalysisProductId = $tracked->id;
        $this->logActivity(
            'product_decision',
            'Ürün Karar Radarı',
            $tracked->title ?: $tracked->source_url,
            'Karar skoru '.$tracked->opportunity_score.'/100 olarak kaydedildi.',
            'skor',
            $tracked->opportunity_score,
            ['tracked_product_id' => $tracked->id, 'decision' => $tracked->decision_status],
            $tracked->id,
        );
        unset($this->dashboard, $this->preview, $this->activityDashboard);
    }

    public function loadTrackedProduct(int $trackedProductId): void
    {
        $tracked = TrendyolBoosterProduct::query()
            ->where('user_id', $this->userId())
            ->findOrFail($trackedProductId);

        $this->productUrl = (string) $tracked->source_url;
        $this->selectedAnalysisProductId = $tracked->id;
        $this->selectedProductId = $tracked->mp_product_id;
        $this->selectedListingId = $tracked->channel_listing_id;
        $this->title = (string) $tracked->title;
        $this->brand = (string) $tracked->brand;
        $this->categoryName = (string) $tracked->category_name;
        $this->salePrice = (float) $tracked->sale_price;
        $this->cogs = (float) $tracked->cogs;
        $this->packagingCost = (float) $tracked->packaging_cost;
        $this->cargoCost = (float) $tracked->cargo_cost;
        $this->returnCargoCost = (float) $tracked->cargo_cost;
        $this->commissionRate = (float) $tracked->commission_rate;
        $this->returnRate = (float) $tracked->return_rate;
        $this->vatRate = (float) $tracked->vat_rate;
        $this->costVatRate = (float) $tracked->cost_vat_rate;
        $this->watchPrice = (bool) $tracked->watch_price;
        $this->watchStock = (bool) $tracked->watch_stock;
        $this->watchKeyword = (bool) $tracked->watch_keyword;
        unset($this->preview);
    }

    public function removeTrackedProduct(int $trackedProductId): void
    {
        TrendyolBoosterProduct::query()
            ->where('user_id', $this->userId())
            ->whereKey($trackedProductId)
            ->delete();

        if ($this->selectedAnalysisProductId === $trackedProductId) {
            $this->selectedAnalysisProductId = null;
            unset($this->productAnalysis);
        }

        $this->message = 'Takip kaydı silindi.';
        $this->messageType = 'success';
        unset($this->dashboard);
    }

    public function checkTrackedProduct(int $trackedProductId): void
    {
        if (! $this->boosterTablesReady() || ! $this->boosterSnapshotTablesReady()) {
            $this->message = 'Trendyol Booster snapshot tablosu henüz hazır değil. Migration sonrası kontrol alınabilir.';
            $this->messageType = 'error';

            return;
        }

        $tracked = TrendyolBoosterProduct::query()
            ->where('user_id', $this->userId())
            ->findOrFail($trackedProductId);

        $result = app(TrendyolBoosterMonitorService::class)->check($tracked);

        $this->message = $result['message'];
        $this->messageType = $result['ok'] ? 'success' : 'error';

        if ($result['ok'] && $this->productUrl === $tracked->source_url) {
            $this->loadTrackedProduct((int) $result['product']->id);
        }

        if ($result['ok']) {
            $this->logActivity(
                'price_tracking',
                'Fiyat Takibi',
                $result['product']->title ?: $result['product']->source_url,
                $result['message'],
                'fiyat',
                $result['snapshot']?->sale_price,
                ['snapshot_id' => $result['snapshot']?->id, 'price_delta' => $result['snapshot']?->price_delta],
                $result['product']->id,
            );
        }

        unset($this->dashboard, $this->preview, $this->priceDashboard, $this->activityDashboard);
    }

    public function addCompetitor(int $trackedProductId): void
    {
        if (! $this->boosterCompetitorTablesReady()) {
            $this->message = 'Rakip radarı tablosu henüz hazır değil. Migration sonrası rakip eklenebilir.';
            $this->messageType = 'error';

            return;
        }

        $url = trim((string) ($this->competitorUrls[$trackedProductId] ?? ''));

        if (! $this->isValidTrendyolUrl($url)) {
            $this->message = 'Geçerli bir Trendyol rakip ürün linki girin.';
            $this->messageType = 'error';

            return;
        }

        $tracked = TrendyolBoosterProduct::query()
            ->where('user_id', $this->userId())
            ->findOrFail($trackedProductId);
        $result = app(TrendyolBoosterCompetitorService::class)->addFromUrl($tracked, $url);

        $this->message = $result['message'];
        $this->messageType = $result['ok'] ? 'success' : 'error';

        if ($result['ok']) {
            unset($this->competitorUrls[$trackedProductId]);
            $this->logActivity(
                'competitor_tracking',
                'Rakip Takibi',
                $result['competitor']?->title ?: $url,
                $result['competitor']?->opportunity_note,
                'fark',
                $result['competitor']?->price_delta_vs_own,
                ['competitor_id' => $result['competitor']?->id],
                $tracked->id,
            );
        }

        unset($this->dashboard, $this->marketInsightDashboard, $this->activityDashboard);
    }

    public function refreshCompetitor(int $competitorId): void
    {
        if (! $this->boosterCompetitorTablesReady()) {
            return;
        }

        $competitor = TrendyolBoosterCompetitor::query()
            ->where('user_id', $this->userId())
            ->findOrFail($competitorId);
        $result = app(TrendyolBoosterCompetitorService::class)->refresh($competitor);

        $this->message = $result['message'];
        $this->messageType = $result['ok'] ? 'success' : 'error';
        unset($this->dashboard, $this->marketInsightDashboard);
    }

    public function removeCompetitor(int $competitorId): void
    {
        TrendyolBoosterCompetitor::query()
            ->where('user_id', $this->userId())
            ->whereKey($competitorId)
            ->delete();

        $this->message = 'Rakip kaydı silindi.';
        $this->messageType = 'success';
        unset($this->dashboard, $this->marketInsightDashboard);
    }

    public function addKeyword(int $trackedProductId): void
    {
        if (! $this->boosterKeywordTablesReady()) {
            $this->message = 'Anahtar kelime radarı tablosu henüz hazır değil. Migration sonrası kelime eklenebilir.';
            $this->messageType = 'error';

            return;
        }

        $keyword = trim((string) ($this->keywordInputs[$trackedProductId] ?? ''));

        if (mb_strlen($keyword) < 2) {
            $this->message = 'Anahtar kelime en az 2 karakter olmalı.';
            $this->messageType = 'error';

            return;
        }

        $tracked = TrendyolBoosterProduct::query()
            ->where('user_id', $this->userId())
            ->findOrFail($trackedProductId);
        $targetRank = (int) ($this->keywordTargets[$trackedProductId] ?? 20);
        $result = app(TrendyolBoosterKeywordService::class)->addKeyword($tracked, $keyword, $targetRank);

        $this->message = $result['message'];
        $this->messageType = $result['ok'] ? 'success' : 'error';

        if ($result['ok']) {
            unset($this->keywordInputs[$trackedProductId], $this->keywordTargets[$trackedProductId]);
            $this->logActivity(
                'keyword_tracking',
                'Anahtar Kelime Takibi',
                $result['keyword']?->keyword ?: $keyword,
                $result['keyword']?->visibility_note,
                'sıra',
                $result['keyword']?->observed_rank,
                ['keyword_id' => $result['keyword']?->id],
                $tracked->id,
            );
        }

        unset($this->dashboard, $this->marketInsightDashboard, $this->activityDashboard);
    }

    public function refreshKeyword(int $keywordId): void
    {
        if (! $this->boosterKeywordTablesReady()) {
            return;
        }

        $keyword = TrendyolBoosterKeyword::query()
            ->where('user_id', $this->userId())
            ->findOrFail($keywordId);
        $result = app(TrendyolBoosterKeywordService::class)->refresh($keyword);

        $this->message = $result['message'];
        $this->messageType = $result['ok'] ? 'success' : 'error';
        unset($this->dashboard, $this->marketInsightDashboard);
    }

    public function removeKeyword(int $keywordId): void
    {
        TrendyolBoosterKeyword::query()
            ->where('user_id', $this->userId())
            ->whereKey($keywordId)
            ->delete();

        $this->message = 'Anahtar kelime kaydı silindi.';
        $this->messageType = 'success';
        unset($this->dashboard, $this->marketInsightDashboard);
    }

    public function saveCostPreset(): void
    {
        if (! $this->boosterCostPresetTablesReady()) {
            $this->message = 'Maliyet preset tablosu henüz hazır değil. Migration sonrası kayıt alınabilir.';
            $this->messageType = 'error';

            return;
        }

        $preset = app(TrendyolBoosterCostPresetService::class)->store($this->userId(), [
            'name' => $this->costPresetName ?: $this->categoryName ?: 'Trendyol Genel',
            'category_name' => $this->categoryName,
            'commission_rate' => $this->commissionRate,
            'cargo_cost' => $this->cargoCost,
            'return_cargo_cost' => $this->returnCargoCost,
            'packaging_cost' => $this->packagingCost,
            'service_fee_rate' => $this->serviceFeeRate,
            'advertising_rate' => $this->advertisingRate,
            'return_rate' => $this->returnRate,
            'vat_rate' => $this->vatRate,
            'cost_vat_rate' => $this->costVatRate,
            'expense_vat_rate' => $this->expenseVatRate,
        ]);

        $this->selectedCostPresetId = $preset->id;
        $this->costPresetName = '';
        $this->message = 'Maliyet preset kaydedildi.';
        $this->messageType = 'success';
        unset($this->costPresets);
    }

    public function applyCostPreset(): void
    {
        if (! $this->boosterCostPresetTablesReady() || ! $this->selectedCostPresetId) {
            $this->message = 'Uygulanacak maliyet preset seçin.';
            $this->messageType = 'error';

            return;
        }

        $preset = TrendyolBoosterCostPreset::query()
            ->where('user_id', $this->userId())
            ->findOrFail($this->selectedCostPresetId);

        foreach (app(TrendyolBoosterCostPresetService::class)->values($preset) as $property => $value) {
            $this->{$property} = $value;
        }

        $this->message = 'Maliyet preset uygulandı.';
        $this->messageType = 'success';
        unset($this->preview);
    }

    public function deleteCostPreset(int $presetId): void
    {
        TrendyolBoosterCostPreset::query()
            ->where('user_id', $this->userId())
            ->whereKey($presetId)
            ->delete();

        if ($this->selectedCostPresetId === $presetId) {
            $this->selectedCostPresetId = null;
        }

        $this->message = 'Maliyet preset silindi.';
        $this->messageType = 'success';
        unset($this->costPresets);
    }

    public function simulateCampaign(int $trackedProductId): void
    {
        if (! $this->boosterCampaignTablesReady()) {
            $this->message = 'Kampanya senaryo tablosu henüz hazır değil. Migration sonrası senaryo alınabilir.';
            $this->messageType = 'error';

            return;
        }

        $tracked = TrendyolBoosterProduct::query()
            ->where('user_id', $this->userId())
            ->findOrFail($trackedProductId);
        $scenario = app(TrendyolBoosterCampaignScenarioService::class)->simulateAndStore($tracked, [
            'name' => $this->campaignNames[$trackedProductId] ?? '',
            'discount_rate' => $this->campaignDiscountRates[$trackedProductId] ?? 0,
            'commission_discount_rate' => $this->campaignCommissionDiscountRates[$trackedProductId] ?? 0,
            'advertising_rate' => $this->campaignAdvertisingRates[$trackedProductId] ?? 0,
            'expected_units' => $this->campaignExpectedUnits[$trackedProductId] ?? 1,
        ]);

        unset(
            $this->campaignNames[$trackedProductId],
            $this->campaignDiscountRates[$trackedProductId],
            $this->campaignCommissionDiscountRates[$trackedProductId],
            $this->campaignAdvertisingRates[$trackedProductId],
            $this->campaignExpectedUnits[$trackedProductId]
        );

        $this->message = 'Kampanya senaryosu hesaplandı: '.$this->campaignDecisionLabel($scenario->decision_status);
        $this->messageType = $scenario->decision_status === 'reject' ? 'error' : 'success';
        $this->logActivity(
            'campaign_decision',
            'Kampanya Kararı',
            $scenario->name,
            $scenario->decision_note,
            'kâr farkı',
            $scenario->total_profit_delta,
            ['scenario_id' => $scenario->id, 'decision' => $scenario->decision_status],
            $tracked->id,
        );
        unset($this->dashboard, $this->activityDashboard);
    }

    public function deleteCampaignScenario(int $scenarioId): void
    {
        TrendyolBoosterCampaignScenario::query()
            ->where('user_id', $this->userId())
            ->whereKey($scenarioId)
            ->delete();

        $this->message = 'Kampanya senaryosu silindi.';
        $this->messageType = 'success';
        unset($this->dashboard);
    }

    public function toggleBoosterNotificationGroup(string $groupKey): void
    {
        $groups = $this->boosterNotificationGroups();

        if (! array_key_exists($groupKey, $groups)) {
            return;
        }

        $notificationCenter = app(NotificationCenterService::class);

        if (! $notificationCenter->isAvailable()) {
            $this->message = 'Bildirim merkezi tabloları hazır değil. Migration sonrası tercihler açılacak.';
            $this->messageType = 'error';

            return;
        }

        $types = $groups[$groupKey]['types'];
        $mutedTypes = collect($notificationCenter->mutedTypesForUser($this->userId()));
        $isActive = collect($types)->every(fn (string $type): bool => ! $mutedTypes->contains($type));
        $nextMuted = $isActive
            ? $mutedTypes->merge($types)
            : $mutedTypes->reject(fn (string $type): bool => in_array($type, $types, true));

        $notificationCenter->setMutedTypes(
            $this->userId(),
            $nextMuted->unique()->values()->all(),
        );

        $this->message = $groups[$groupKey]['label'].($isActive ? ' kapatıldı.' : ' açıldı.');
        $this->messageType = 'success';
        unset($this->boosterNotificationPreferences);
    }

    public function render()
    {
        return view('livewire.trendyol-booster', [
            'modules' => $this->boosterModules(),
            'moduleGroups' => $this->boosterModuleGroups(),
            'workspaceCopy' => TrendyolBoosterModuleConfig::getWorkspaceCopy($this->activeModule, $this->favoritesOnly),
            'preview' => $this->preview,
            'productAnalysis' => $this->productAnalysis,
            'financeDashboard' => $this->financeDashboard,
            'marketInsightDashboard' => $this->marketInsightDashboard,
            'trackedProductOptions' => $this->trackedProductOptions,
            'dashboard' => $this->dashboard,
            'stockDashboard' => $this->stockDashboard,
            'keywordLookupDashboard' => $this->keywordLookupDashboard,
            'storeWatchDashboard' => $this->storeWatchDashboard,
            'supplierResearchDashboard' => $this->supplierResearchDashboard,
            'trendDashboard' => $this->trendDashboard,
            'commissionDashboard' => $this->commissionDashboard,
            'priceDashboard' => $this->priceDashboard,
            'activityDashboard' => $this->activityDashboard,
            'boosterNotificationPreferences' => $this->boosterNotificationPreferences,
            'productOptions' => $this->productOptions,
            'bestsellerTableRows' => $this->bestsellerTableRows,
            'bestsellerReportDashboard' => $this->bestsellerReportDashboard,
            'bestsellerCurrentReport' => $this->bestsellerCurrentReport,
            'bestsellerColumnDefinitions' => self::$bestsellerColumnDefinitions,
            'bestsellerSortableColumns' => self::$bestsellerSortableColumns,
            'supplierOfferColumnDefinitions' => self::$supplierOfferColumnDefinitions,
            'sortableColumns' => self::$sortableColumns,
            'tablesReady' => $this->boosterTablesReady(),
            'snapshotsReady' => $this->boosterSnapshotTablesReady(),
            'productAnalysisReady' => $this->productAnalysisTablesReady(),
            'competitorsReady' => $this->boosterCompetitorTablesReady(),
            'keywordsReady' => $this->boosterKeywordTablesReady(),
            'costPresetsReady' => $this->boosterCostPresetTablesReady(),
            'costPresets' => $this->costPresets,
            'campaignsReady' => $this->boosterCampaignTablesReady(),
            'stockReady' => $this->boosterStockTablesReady(),
            'keywordLookupReady' => $this->boosterKeywordLookupTablesReady(),
            'storeWatchReady' => $this->boosterStoreWatchTablesReady(),
            'supplierResearchReady' => $this->supplierResearchTablesReady(),
            'trendReady' => $this->boosterTrendKeywordTablesReady(),
            'commissionReady' => $this->boosterCommissionTablesReady(),
            'activityReady' => $this->boosterActivityTablesReady(),
            'bestsellerReportsReady' => $this->bestsellerReportsReady(),
        ])->layout('layouts.app', ['title' => 'Trendyol Booster']);
    }

    protected function loadProduct(int $productId): void
    {
        if ($productId <= 0) {
            $this->selectedProductId = null;

            return;
        }

        $product = MpProduct::query()
            ->with(['channelListings.store', 'channelListings.channelProduct'])
            ->where('user_id', $this->userId())
            ->find($productId);

        if (! $product) {
            return;
        }

        $listing = $product->channelListings
            ->filter(fn ($listing) => $listing->store?->marketplace === 'trendyol')
            ->sortByDesc(fn ($listing) => $listing->last_synced_at?->timestamp ?? 0)
            ->first();

        $this->selectedProductId = $product->id;
        $this->selectedListingId = $listing?->id;
        $this->title = (string) $product->product_name;
        $this->brand = (string) $product->brand;
        $this->categoryName = (string) $product->category_name;
        $this->salePrice = (float) ($listing?->sale_price ?: $product->sale_price);
        $this->cogs = (float) $product->cogs;
        $this->packagingCost = (float) $product->packaging_cost;
        $this->cargoCost = (float) $product->cargo_cost;
        $this->returnCargoCost = (float) $product->cargo_cost;
        $this->commissionRate = (float) ($listing?->commission_rate ?: $product->commission_rate);
        $this->returnRate = (float) $product->return_rate;
        $this->vatRate = (float) ($listing?->channelProduct?->vat_rate ?: $product->vat_rate ?: $this->vatRate);
        $this->costVatRate = (float) ($product->cost_vat_rate ?: $this->vatRate);
        unset($this->preview);
    }

    /**
     * @return array<string, mixed>
     */
    protected function analysisInput(): array
    {
        return [
            'user_id' => $this->userId(),
            'source_url' => $this->productUrl,
            'mp_product_id' => $this->selectedProductId,
            'channel_listing_id' => $this->selectedListingId,
            'title' => $this->title,
            'brand' => $this->brand,
            'category_name' => $this->categoryName,
            'sale_price' => $this->salePrice,
            'cogs' => $this->cogs,
            'packaging_cost' => $this->packagingCost,
            'cargo_cost' => $this->cargoCost,
            'return_cargo_cost' => $this->returnCargoCost,
            'commission_rate' => $this->commissionRate,
            'service_fee_rate' => $this->serviceFeeRate,
            'advertising_rate' => $this->advertisingRate,
            'return_rate' => $this->returnRate,
            'vat_enabled' => $this->vatEnabled,
            'withholding_enabled' => $this->withholdingEnabled,
            'vat_rate' => $this->vatRate,
            'cost_vat_rate' => $this->costVatRate,
            'expense_vat_rate' => $this->expenseVatRate,
            'withholding_rate' => $this->withholdingRate,
            'target_margin_percent' => $this->targetMarginPercent,
            'watch_price' => $this->watchPrice,
            'watch_stock' => $this->watchStock,
            'watch_keyword' => $this->watchKeyword,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function sellDecisionInput(): array
    {
        return $this->analysisInput() + [
            'income_tax_rate' => $this->incomeTaxRate,
        ];
    }

    protected function completeSellDecision(TrendyolBoosterProduct $tracked): void
    {
        $sources = collect((array) $tracked->tracking_sources)
            ->push('sell_decision')
            ->filter()
            ->unique()
            ->values()
            ->all();
        $trackingUpdates = [
            'tracking_status' => 'active',
            'tracking_sources' => $sources,
            'tracking_started_at' => $tracked->tracking_started_at ?: now(),
            'tracking_paused_at' => null,
            'watch_stock' => true,
        ];

        if ($this->analysisRefreshReady()) {
            $trackingUpdates += [
                'analysis_auto_refresh_enabled' => true,
                'analysis_refresh_interval_minutes' => $this->trackingIntervalMinutes(),
                'next_analysis_refresh_at' => now(),
            ];
        }

        $tracked->forceFill($trackingUpdates)->save();

        $marketData = [];
        $marketMessage = '';
        if ($this->sellDecisionUseMarketSearch && trim($this->title) !== '') {
            try {
                $marketResult = app(TrendyolSearchResultReader::class)->fetch($this->sellDecisionKeyword());
                $marketData = (array) ($marketResult['data'] ?? []);
                $marketMessage = (string) ($marketResult['message'] ?? '');
            } catch (\Throwable $exception) {
                $marketMessage = 'Arama görünürlüğü okunamadı: '.Str::limit($exception->getMessage(), 180, '');
            }
        }

        $this->sellDecisionResult = app(TrendyolBoosterSellDecisionService::class)->decide(
            $tracked->fresh() ?: $tracked,
            $this->sellDecisionInput(),
            $marketData,
            $marketMessage,
        );
        $this->selectedAnalysisProductId = $tracked->id;
        $this->message = 'Sat veya Satma kararı üretildi; ürün Booster Radar takibine alındı.';
        $this->messageType = 'success';
        $this->logActivity(
            'sell_decision',
            'Sat veya Satma',
            $tracked->title ?: $tracked->source_url,
            $this->sellDecisionResult['expert_summary'] ?? 'Sat veya Satma kararı üretildi.',
            'skor',
            $this->sellDecisionResult['score'] ?? null,
            ['tracked_product_id' => $tracked->id, 'decision' => $this->sellDecisionResult['decision'] ?? null],
            $tracked->id,
        );
    }

    protected function validateSellDecisionForm(): void
    {
        $this->validate([
            'productUrl' => $this->productUrlRules(),
            'salePrice' => ['nullable', 'numeric', 'min:0'],
            'cogs' => ['nullable', 'numeric', 'min:0'],
            'packagingCost' => ['nullable', 'numeric', 'min:0'],
            'cargoCost' => ['nullable', 'numeric', 'min:0'],
            'returnCargoCost' => ['nullable', 'numeric', 'min:0'],
            'commissionRate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'returnRate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'serviceFeeRate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'advertisingRate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'vatRate' => ['required', 'numeric', 'min:0', 'max:100'],
            'costVatRate' => ['required', 'numeric', 'min:0', 'max:100'],
            'expenseVatRate' => ['required', 'numeric', 'min:0', 'max:100'],
            'withholdingRate' => ['required', 'numeric', 'min:0', 'max:100'],
            'incomeTaxRate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);
    }

    protected function sellDecisionTrackedProductFromUrl(): ?TrendyolBoosterProduct
    {
        $productId = preg_match('/-p-(\d+)/iu', $this->productUrl, $matches) ? (string) $matches[1] : '';

        return TrendyolBoosterProduct::query()
            ->where('user_id', $this->userId())
            ->when(
                $productId !== '',
                fn ($query) => $query->where('trendyol_product_id', $productId),
                fn ($query) => $query->where('source_url_hash', hash('sha256', trim($this->productUrl))),
            )
            ->latest('updated_at')
            ->first();
    }

    protected function applyTrackedProductData(TrendyolBoosterProduct $tracked): void
    {
        $this->productUrl = (string) $tracked->source_url;
        $this->title = $this->filledText($tracked->title, $this->title);
        $this->brand = $this->filledText($tracked->brand, $this->brand);
        $this->categoryName = $this->filledText($tracked->category_name, $this->categoryName);
        $this->salePrice = (float) $tracked->sale_price;
    }

    /** @param array<string, mixed> $data */
    protected function applyProductPageData(array $data): void
    {
        $this->productUrl = (string) ($data['source_url'] ?? $this->productUrl);
        $this->title = $this->filledText($data['title'] ?? null, $this->title);
        $this->brand = $this->filledText($data['brand'] ?? null, $this->brand);
        $this->categoryName = $this->filledText($data['category_name'] ?? null, $this->categoryName);

        if ((float) ($data['sale_price'] ?? 0) > 0) {
            $this->salePrice = (float) $data['sale_price'];
        }
    }

    /** @param array<string, mixed> $productData */
    protected function refreshCostRecommendation(
        array $productData,
        ?TrendyolBoosterProduct $tracked = null,
    ): void {
        $context = array_replace($productData, [
            'source_url' => $this->productUrl,
            'title' => $this->title,
            'brand' => $this->brand,
            'category_name' => $this->categoryName,
            'sale_price' => (float) $this->salePrice,
            'image_url' => $productData['image_url'] ?? $tracked?->image_url,
        ]);
        $this->costRecommendation = app(TrendyolBoosterCostRecommendationService::class)
            ->recommend($this->userId(), $context, $tracked);
        $commission = data_get($this->costRecommendation, 'commission.rate');
        $cargoGross = data_get($this->costRecommendation, 'shipping.cost_gross');

        if (is_numeric($commission) && (float) $commission > 0 && (float) $this->commissionRate <= 0) {
            $this->commissionRate = (float) $commission;
        }

        if (is_numeric($cargoGross) && (float) $cargoGross > 0 && (float) $this->cargoCost <= 0) {
            $this->cargoCost = (float) $cargoGross;

            if ((float) $this->returnCargoCost <= 0) {
                $this->returnCargoCost = (float) $cargoGross;
            }
        }

        unset($this->financeDashboard, $this->preview);
    }

    protected function costRecommendationSummary(): string
    {
        if ($this->costRecommendation === []) {
            return '';
        }

        $parts = collect();
        $commission = data_get($this->costRecommendation, 'commission.rate');
        $commissionConfidence = (float) data_get($this->costRecommendation, 'commission.confidence', 0);
        if (is_numeric($commission)) {
            $parts->push('Komisyon %'.number_format((float) $commission, 2, ',', '.')
                .' (güven %'.number_format($commissionConfidence, 0, ',', '.').')');
        }

        $desi = data_get($this->costRecommendation, 'desi.billable_desi');
        if (is_numeric($desi)) {
            $parts->push((int) $desi.' desi');
        }

        $cargo = data_get($this->costRecommendation, 'shipping.cost_gross');
        if (is_numeric($cargo)) {
            $parts->push('KDV dahil kargo '.$this->formatMoney((float) $cargo));
        }

        $sellerLevel = data_get($this->costRecommendation, 'commission.seller_level');
        if (is_numeric($sellerLevel)) {
            $parts->push('satıcı Seviye '.(int) $sellerLevel);
        }

        return $parts->isNotEmpty() ? $parts->implode(' · ').' otomatik uygulandı.' : '';
    }

    protected function sellDecisionKeyword(): string
    {
        $parts = collect([$this->brand, $this->title])
            ->filter(fn ($value): bool => trim((string) $value) !== '')
            ->map(fn ($value): string => trim((string) $value))
            ->implode(' ');

        return Str::limit($parts !== '' ? $parts : $this->categoryName, 160, '');
    }

    protected function observedDailySales(): ?float
    {
        if (! $this->boosterTablesReady()) {
            return null;
        }

        $productId = preg_match('/-p-(\d+)/iu', $this->productUrl, $matches) ? (string) $matches[1] : '';
        if ($this->selectedAnalysisProductId === null && $this->selectedProductId === null && $productId === '') {
            return null;
        }

        $tracked = TrendyolBoosterProduct::query()
            ->where('user_id', $this->userId())
            ->when(
                $this->selectedAnalysisProductId !== null,
                fn (Builder $query) => $query->whereKey($this->selectedAnalysisProductId),
                fn (Builder $query) => $query
                    ->when($this->selectedProductId !== null, fn (Builder $productQuery) => $productQuery->where('mp_product_id', $this->selectedProductId))
                    ->when($this->selectedProductId === null && $productId !== '', fn (Builder $productQuery) => $productQuery->where('trendyol_product_id', $productId)),
            )
            ->latest('updated_at')
            ->first();
        $dailySales = $tracked?->estimated_daily_sales;

        return $dailySales !== null && (float) $dailySales > 0 ? (float) $dailySales : null;
    }

    protected function marketInsightReady(): bool
    {
        if (! $this->boosterTablesReady()) {
            return false;
        }

        return match ($this->activeModule) {
            'keyword_tracking' => $this->boosterKeywordTablesReady(),
            default => false,
        };
    }

    /**
     * @return array<int, mixed>
     */
    protected function productUrlRules(): array
    {
        return [
            'required',
            'string',
            'max:1000',
            function (string $attribute, mixed $value, \Closure $fail): void {
                if (! $this->isValidTrendyolUrl((string) $value)) {
                    $fail('Geçerli bir Trendyol ürün linki girin.');
                }
            },
        ];
    }

    /**
     * @return array<int, mixed>
     */
    protected function productUrlRulesFor(string $property): array
    {
        return [
            'required',
            'string',
            'max:1000',
            function (string $attribute, mixed $value, \Closure $fail) use ($property): void {
                if (! $this->isValidTrendyolUrl((string) $value)) {
                    $field = match ($property) {
                        'storeWatchUrl' => 'Geçerli bir Trendyol mağaza veya ürün linki girin.',
                        default => 'Geçerli bir Trendyol ürün linki girin.',
                    };
                    $fail($field);
                }
            },
        ];
    }

    public function updatedBestsellerSearch(): void
    {
        $this->bestsellerSearch = trim($this->bestsellerSearch);
    }

    public function fetchBestsellerResults(): void
    {
        $this->bestsellerSearch = trim($this->bestsellerSearch);

        $response = app(TrendyolBestsellerReader::class)->fetch(
            $this->bestsellerSearch,
            $minPrice = is_numeric($this->bestsellerMinPrice) && $this->bestsellerMinPrice > 0 ? (int) $this->bestsellerMinPrice : null,
            $maxPrice = is_numeric($this->bestsellerMaxPrice) && $this->bestsellerMaxPrice > 0 ? (int) $this->bestsellerMaxPrice : null
        );
    }

    public function searchBestsellers(): void
    {
        $search = trim($this->bestsellerSearch);

        if (mb_strlen($search) < 2) {
            $this->message = 'Lütfen en az 2 karakterli bir kategori veya kelime girin.';
            $this->messageType = 'error';

            return;
        }

        $this->isBestsellerLoading = true;
        $reader = app(TrendyolBestsellerReader::class);

        $minPrice = is_numeric($this->bestsellerMinPrice) && $this->bestsellerMinPrice > 0 ? (int) $this->bestsellerMinPrice : null;
        $maxPrice = is_numeric($this->bestsellerMaxPrice) && $this->bestsellerMaxPrice > 0 ? (int) $this->bestsellerMaxPrice : null;

        $response = $reader->fetch($search, $minPrice, $maxPrice);
        $this->isBestsellerLoading = false;

        if (! $response['ok']) {
            $this->message = $response['message'];
            $this->messageType = 'error';
            $this->bestsellerResults = [];

            return;
        }

        $this->message = $response['message'];
        $this->messageType = 'success';
        $this->bestsellerResults = $response['data']['top_products'] ?? [];
        $this->bestsellerSourceUrl = (string) ($response['data']['source_url'] ?? '');
        $this->bestsellerMatchedLabel = (string) ($response['data']['matched_label'] ?? $search);
        $this->bestsellerResultSource = 'server_reader';
        $this->hydrateBestsellerTrackingState();
        unset($this->bestsellerTableRows, $this->bestsellerCurrentReport);
    }

    public function bestsellerBridgeCompleted(
        array $products,
        string $keyword,
        string $url,
        string $message,
        bool $ok,
        ?string $matchedLabel = null,
    ): void {
        $this->isBestsellerLoading = false;

        if (! $ok) {
            $this->message = $message ?: 'Chrome eklentisinden veri alınamadı.';
            $this->messageType = 'error';
            $this->bestsellerResults = [];

            return;
        }

        $reader = app(TrendyolBestsellerReader::class);
        $parsed = $reader->parseBridgeData($products, $keyword, $url);

        if (empty($parsed['top_products'])) {
            $this->message = 'Eklenti aracılığıyla sayfa çekildi ancak çok satanlar listesi bulunamadı.';
            $this->messageType = 'warning';
            $this->bestsellerResults = [];

            return;
        }

        $this->message = $message ?: 'Çok satanlar eklenti aracılığıyla başarıyla çekildi.';
        $this->messageType = 'success';
        $this->bestsellerResults = $parsed['top_products'];
        $this->bestsellerSourceUrl = $url;
        $this->bestsellerMatchedLabel = trim((string) $matchedLabel) ?: $keyword;
        $this->bestsellerResultSource = 'browser_companion';
        $this->hydrateBestsellerTrackingState();
        unset($this->bestsellerTableRows, $this->bestsellerCurrentReport);
    }

    public function addBestsellerToBooster(string $productId, string $sourceUrl): void
    {
        $index = collect($this->bestsellerResults)->search(
            fn (array $item): bool => (string) ($item['trendyol_product_id'] ?? '') === $productId
                && (string) ($item['source_url'] ?? '') === $sourceUrl
        );

        if ($index === false) {
            $this->message = 'Takibe alınacak çok satan ürün artık sonuç listesinde bulunmuyor.';
            $this->messageType = 'error';

            return;
        }

        $this->trackBestseller((int) $index);
    }

    public function setBestsellerMode(string $mode): void
    {
        if (! in_array($mode, ['live', 'reports'], true)) {
            return;
        }

        $this->bestsellerMode = $mode;
        unset($this->bestsellerReportDashboard);
    }

    public function sortBestsellerTable(string $column): void
    {
        if (! isset(self::$bestsellerSortableColumns[$column])) {
            return;
        }

        $field = self::$bestsellerSortableColumns[$column];
        if ($this->bestsellerSortField === $field) {
            $this->bestsellerSortDirection = $this->bestsellerSortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->bestsellerSortField = $field;
            $this->bestsellerSortDirection = in_array($column, ['rank', 'product', 'seller'], true) ? 'asc' : 'desc';
        }

        unset($this->bestsellerTableRows);
    }

    public function toggleBestsellerColumn(string $column): void
    {
        if (! isset(self::$bestsellerColumnDefinitions[$column])) {
            return;
        }

        if (in_array($column, $this->bestsellerVisibleColumns, true)) {
            if (count($this->bestsellerVisibleColumns) > 3) {
                $this->bestsellerVisibleColumns = array_values(array_diff($this->bestsellerVisibleColumns, [$column]));
            }

            return;
        }

        $this->bestsellerVisibleColumns[] = $column;
        $this->bestsellerVisibleColumns = array_values(array_unique($this->bestsellerVisibleColumns));
    }

    public function saveBestsellerReport(): void
    {
        if (! $this->bestsellerReportsReady()) {
            $this->message = 'Çok satan rapor tabloları hazır değil. Migration sonrasında rapor kaydedilebilir.';
            $this->messageType = 'error';

            return;
        }

        try {
            $result = app(TrendyolBestsellerReportService::class)->storeRun($this->userId(), [
                'query' => $this->bestsellerSearch,
                'matched_label' => $this->bestsellerMatchedLabel,
                'source_url' => $this->bestsellerSourceUrl,
                'min_price' => $this->bestsellerMinPrice,
                'max_price' => $this->bestsellerMaxPrice,
                'source' => $this->bestsellerResultSource,
            ], $this->bestsellerResults);

            $this->selectedBestsellerReportId = $result['report']->id;
            $this->bestsellerMode = 'reports';
            $this->message = $result['created']
                ? $result['report']->name.' raporu oluşturuldu ve ilk ölçüm kaydedildi.'
                : $result['report']->name.' serisine '.($result['report']->run_count).'. ölçüm eklendi.';
            $this->messageType = 'success';
            unset($this->bestsellerReportDashboard, $this->bestsellerCurrentReport);
        } catch (\Throwable $exception) {
            report($exception);
            $this->message = 'Rapor kaydedilemedi: '.Str::limit($exception->getMessage(), 260, '');
            $this->messageType = 'error';
        }
    }

    public function viewBestsellerReport(int $reportId): void
    {
        if (! $this->bestsellerReportsReady()) {
            return;
        }

        $exists = TrendyolBestsellerReport::query()
            ->where('user_id', $this->userId())
            ->whereKey($reportId)
            ->exists();
        if (! $exists) {
            return;
        }

        $this->selectedBestsellerReportId = $reportId;
        $this->bestsellerMode = 'reports';
        unset($this->bestsellerReportDashboard);
    }

    public function loadBestsellerReport(int $reportId): void
    {
        if (! $this->bestsellerReportsReady()) {
            return;
        }

        $report = TrendyolBestsellerReport::query()
            ->where('user_id', $this->userId())
            ->find($reportId);
        if (! $report) {
            return;
        }

        $this->bestsellerSearch = (string) $report->query;
        $this->bestsellerMinPrice = $report->min_price !== null ? (int) $report->min_price : null;
        $this->bestsellerMaxPrice = $report->max_price !== null ? (int) $report->max_price : null;
        $this->bestsellerMatchedLabel = (string) ($report->matched_label ?: $report->query);
        $this->bestsellerMode = 'live';
        $this->message = 'Rapor filtreleri canlı aramaya yüklendi. Yeni ölçüm için Ara düğmesini kullanın.';
        $this->messageType = 'success';
        unset($this->bestsellerCurrentReport);
    }

    public function trackBestseller(int $index): void
    {
        $item = $this->bestsellerResults[$index] ?? null;
        if (! is_array($item) || trim((string) ($item['trendyol_product_id'] ?? '')) === '') {
            $this->message = 'Takibe alınacak ürün bulunamadı.';
            $this->messageType = 'error';

            return;
        }

        try {
            $payload = $this->bestsellerTrackingPayload($item);
            $groupKey = app(TrendyolBestsellerReportService::class)->fingerprint(
                $this->bestsellerSearch,
                is_numeric($this->bestsellerMinPrice) ? (float) $this->bestsellerMinPrice : null,
                is_numeric($this->bestsellerMaxPrice) ? (float) $this->bestsellerMaxPrice : null,
            );
            $tracked = app(TrendyolBoosterResearchService::class)
                ->track($this->userId(), $payload, 'bestseller', $groupKey)['product'];
            $this->bestsellerTrackedProductIds[] = (string) $tracked->trendyol_product_id;
            $this->bestsellerTrackedProductIds = array_values(array_unique($this->bestsellerTrackedProductIds));
            $this->message = ($tracked->title ?: 'Ürün').' Booster Radar takibine alındı.';
            $this->messageType = 'success';
            unset($this->dashboard, $this->trackingDashboard, $this->bestsellerReportDashboard);
        } catch (\Throwable $exception) {
            report($exception);
            $this->message = 'Ürün takibe alınamadı: '.Str::limit($exception->getMessage(), 260, '');
            $this->messageType = 'error';
        }
    }

    public function bestsellerTrackingBridgeCompleted(
        ?int $trackedProductId,
        string $productId,
        string $message,
        bool $ok,
    ): void {
        if (! $ok || $trackedProductId === null) {
            $this->message = $message ?: 'Ürün Chrome eklentisiyle takibe alınamadı.';
            $this->messageType = 'error';

            return;
        }

        $tracked = TrendyolBoosterProduct::query()
            ->where('user_id', $this->userId())
            ->whereKey($trackedProductId)
            ->first();
        if (! $tracked) {
            $this->message = 'Takip kaydı oluşturuldu ancak ürün kullanıcı hesabında bulunamadı.';
            $this->messageType = 'error';

            return;
        }

        $this->bestsellerTrackedProductIds[] = $productId ?: (string) $tracked->trendyol_product_id;
        $this->bestsellerTrackedProductIds = array_values(array_unique($this->bestsellerTrackedProductIds));
        $this->message = $message ?: ($tracked->title ?: 'Ürün').' Booster Radar takibine alındı.';
        $this->messageType = 'success';
        unset($this->dashboard, $this->trackingDashboard, $this->bestsellerReportDashboard);
    }

    /** @param array<string, mixed> $item */
    protected function bestsellerTrackingPayload(array $item): array
    {
        $campaigns = array_values((array) ($item['campaigns'] ?? $item['promotions'] ?? []));

        return [
            'source_url' => (string) ($item['source_url'] ?? ''),
            'page' => [
                'trendyol_product_id' => (string) ($item['trendyol_product_id'] ?? ''),
                'title' => (string) ($item['title'] ?? ''),
                'brand' => (string) ($item['brand'] ?? ''),
                'category_name' => $this->bestsellerMatchedLabel ?: $this->bestsellerSearch,
                'sale_price' => (float) ($item['price'] ?? 0),
                'currency' => 'TRY',
                'image_url' => (string) ($item['image_url'] ?? ''),
                'availability' => (string) ($item['stock_status'] ?? ''),
                'stock_status' => (string) ($item['stock_status'] ?? 'unknown'),
                'total_stock' => $item['stock_quantity'] ?? $item['total_stock'] ?? null,
                'sellers' => array_values((array) ($item['sellers'] ?? [])),
                'seller_score' => $item['seller_score'] ?? null,
                'seller_id' => $item['seller_id'] ?? null,
                'campaign_count' => $item['campaign_count'] ?? count($campaigns),
                'promotions' => $campaigns,
                'data_sources' => ['bestseller', $this->bestsellerResultSource],
            ],
            'metrics' => [
                'evaluation_count' => $item['rating_count'] ?? null,
                'average_rating' => $item['rating'] ?? null,
                'favorite_count' => $item['favorite_count'] ?? null,
                'basket_count' => $item['basket_count'] ?? null,
                'view_count_24h' => $item['view_count_24h'] ?? null,
                'seller_score' => $item['seller_score'] ?? null,
                'campaign_count' => $item['campaign_count'] ?? count($campaigns),
            ],
            'recent_reviews' => [],
        ];
    }

    protected function hydrateBestsellerTrackingState(): void
    {
        if (! $this->boosterTablesReady() || $this->bestsellerResults === []) {
            $this->bestsellerTrackedProductIds = [];

            return;
        }

        $productIds = collect($this->bestsellerResults)
            ->pluck('trendyol_product_id')
            ->filter()
            ->map(fn ($id): string => (string) $id)
            ->values();
        $this->bestsellerTrackedProductIds = TrendyolBoosterProduct::query()
            ->where('user_id', $this->userId())
            ->where('tracking_status', 'active')
            ->whereIn('trendyol_product_id', $productIds)
            ->pluck('trendyol_product_id')
            ->map(fn ($id): string => (string) $id)
            ->values()
            ->all();
    }

    /** @return array<string, string> */
    public function trackingColumnDefinitions(): array
    {
        return [
            'product' => 'Ürün',
            'price' => 'Fiyat',
            'stock' => 'Stok',
            'sales' => 'Tahmini satış',
            'interest' => 'İlgi',
            'risk' => 'Risk',
            'quality' => 'Güven',
            'updated' => 'Son tarama',
            'actions' => 'Aksiyonlar',
        ];
    }

    protected function runResearchComparison(string $kind): void
    {
        $urls = collect($kind === 'market' ? $this->marketUrls : $this->comparisonUrls)
            ->map(fn ($url): string => trim((string) $url))
            ->filter()
            ->values();

        if ($urls->count() < 2 || $urls->count() > 4) {
            $this->message = 'Karşılaştırma için 2 ile 4 arasında Trendyol ürün linki girin.';
            $this->messageType = 'error';

            return;
        }

        foreach ($urls as $url) {
            if (! $this->isValidTrendyolUrl($url)) {
                $this->message = 'Karşılaştırma listesindeki linklerden biri geçerli bir Trendyol ürün linki değil.';
                $this->messageType = 'error';

                return;
            }
        }

        try {
            $comparison = app(TrendyolBoosterResearchService::class)->compareUrls($urls->all());
            $this->setResearchResults($kind, $comparison);
            $this->message = $kind === 'market'
                ? 'Pazar karşılaştırması tamamlandı.'
                : 'Ürün karşılaştırması tamamlandı.';
            $this->messageType = 'success';
        } catch (\Throwable $exception) {
            $this->message = 'Karşılaştırma tamamlanamadı: '.Str::limit($exception->getMessage(), 300, '');
            $this->messageType = 'error';
        }
    }

    /** @param array<string, mixed> $comparison */
    protected function setResearchResults(string $kind, array $comparison): void
    {
        if ($kind === 'market') {
            $this->marketResults = array_values((array) ($comparison['products'] ?? []));
            $this->marketSummary = (array) ($comparison['summary'] ?? []);
            $this->marketGroupKey = (string) ($comparison['group_key'] ?? '');

            return;
        }

        $this->comparisonResults = array_values((array) ($comparison['products'] ?? []));
        $this->comparisonSummary = (array) ($comparison['summary'] ?? []);
        $this->comparisonGroupKey = (string) ($comparison['group_key'] ?? '');
    }

    /** @param array<string, mixed> $page */
    protected function researchPayload(array $page, string $sourceUrl): array
    {
        $metrics = collect([
            'evaluation_count',
            'review_count',
            'average_rating',
            'favorite_count',
            'favorite_precision',
            'basket_count',
            'view_count_24h',
            'question_count',
            'category_rank',
            'seller_score',
            'seller_follower_count',
            'campaign_count',
        ])->mapWithKeys(fn (string $key): array => [$key => $page[$key] ?? null])->all();

        return [
            'source_url' => $sourceUrl,
            'page' => $page,
            'metrics' => $metrics,
            'recent_reviews' => [],
        ];
    }

    protected function storeWatchItemAnalysisPage(TrendyolBoosterStoreWatchItem $item): array
    {
        $sourceUrl = trim((string) $item->source_url);
        $categoryName = trim((string) $item->category_name);
        $categoryName = $categoryName !== '' ? $categoryName : $this->inferStoreWatchItemCategory((string) $item->title);
        $brand = trim((string) $item->brand);
        $campaignBadges = array_values(array_filter((array) $item->campaign_badges));

        return [
            'source_url' => $sourceUrl,
            'trendyol_product_id' => (string) $item->trendyol_product_id,
            'title' => (string) $item->title,
            'brand' => $brand !== '' ? $brand : $this->brandFromStoreWatchTitle((string) $item->title),
            'category_name' => $categoryName,
            'sale_price' => (float) $item->sale_price,
            'currency' => 'TRY',
            'image_url' => (string) $item->image_url,
            'stock_status' => $item->is_removed ? 'removed' : ((string) $item->stock_status ?: 'unknown'),
            'total_stock' => $item->stock_quantity,
            'seller_id' => (string) ($item->storeWatch?->store_id ?? ''),
            'seller_name' => (string) ($item->seller_name ?: $item->storeWatch?->store_name),
            'average_rating' => $item->rating !== null ? (float) $item->rating : null,
            'review_count' => $item->review_count,
            'evaluation_count' => $item->review_count,
            'favorite_count' => $item->favorite_count,
            'campaign_count' => count($campaignBadges),
            'data_sources' => ['store_watch_detail'],
        ];
    }

    protected function inferStoreWatchItemCategory(string $title): string
    {
        $title = trim($title);
        if ($title === '') {
            return '';
        }

        $ruleMatch = $this->storeWatchCategoryFromTitleRules($title);
        if ($ruleMatch !== '') {
            return $ruleMatch;
        }

        try {
            $resolved = app(TrendyolCategoryDictionary::class)->resolve($title);
            $productGroup = trim((string) ($resolved['product_group'] ?? ''));
            $subCategory = trim((string) ($resolved['sub_category'] ?? ''));
            $category = trim((string) ($resolved['category'] ?? ''));

            if ($productGroup !== '' && mb_strlen($productGroup) <= 80) {
                return Str::title($productGroup);
            }

            if ($subCategory !== '' && mb_strlen($subCategory) <= 80) {
                return Str::title($subCategory);
            }

            if ($category !== '' && mb_strlen($category) <= 80) {
                return Str::title($category);
            }
        } catch (\Throwable) {
            // Sözlük erişilemezse temel başlık kurallarına düş.
        }

        return '';
    }

    protected function storeWatchCategoryFromTitleRules(string $title): string
    {
        $normalized = Str::lower(Str::ascii($title));
        $rules = [
            'Puf & Bench' => ['puf', 'bench'],
            'Kanepe & Koltuk' => ['kanepe', 'koltuk', 'kose takimi', 'berjer'],
            'Sandalye' => ['sandalye'],
            'Sehpa' => ['sehpa'],
            'Çay Seti' => ['cay seti'],
            'Masa' => ['masa'],
            'Kurabiye Kalıbı' => ['kurabiye kalibi'],
            'Kozmetik' => ['kozmetik', 'makyaj', 'ruj', 'parfum', 'krem'],
        ];

        foreach ($rules as $category => $terms) {
            foreach ($terms as $term) {
                if (str_contains($normalized, Str::lower(Str::ascii($term)))) {
                    return $category;
                }
            }
        }

        return '';
    }

    protected function brandFromStoreWatchTitle(string $title): string
    {
        $firstToken = trim((string) Str::of($title)->before(' '));

        return mb_strlen($firstToken) >= 2 && mb_strlen($firstToken) <= 32 ? Str::title($firstToken) : '';
    }

    /**
     * Ürün Analizi modülü "slug + ürün ID" seviyesindeki URL fallback'i gerçek
     * analiz gibi kaydetmemeli. Fiyat ana sinyal olduğu için 0 TL'lik kayıtlar
     * kullanıcıyı yanıltır ve ledger'ı kirletir.
     *
     * @param  array<string, mixed>  $page
     */
    protected function hasUsableProductAnalysisData(array $page): bool
    {
        return (float) ($page['sale_price'] ?? 0) > 0;
    }

    /**
     * @return array<int, array{key: string, label: string, items: array<int, array{label: string, icon: string, module?: string, query?: array<string, mixed>, soon?: bool}>}>
     */
    public function boosterModuleGroups(): array
    {
        return TrendyolBoosterModuleConfig::getGroups();
    }

    /**
     * @return array<string, array{label: string, icon: string}>
     */
    public function boosterModules(): array
    {
        return TrendyolBoosterModuleConfig::getModules();
    }

    /** @return array<int, string> */
    protected function availableBoosterModules(): array
    {
        return array_keys($this->boosterModules());
    }

    protected function dispatchBoosterModuleChanged(string $item): void
    {
        $this->dispatch(
            'booster-module-changed',
            module: $this->activeModule,
            item: $item,
            group: $this->boosterModuleGroup($item),
        );
    }

    protected function boosterModuleGroup(string $item): string
    {
        return TrendyolBoosterModuleConfig::getGroupOfModule($item);
    }

    /**
     * @return array<string, array{label: string, description: string, types: array<int, string>}>
     */
    protected function boosterNotificationGroups(): array
    {
        return [
            'price' => [
                'label' => 'Fiyat uyarıları',
                'description' => 'Fiyat düşüşü ve artışı yakalandığında canlı bildirim ve e-posta özeti üretir.',
                'types' => ['booster_price_drop', 'booster_price_rise'],
            ],
            'stock' => [
                'label' => 'Stok uyarıları',
                'description' => 'Stok erimesi, satıcı stok farkı ve görünür stok değişimlerini bildirir.',
                'types' => ['booster_stock_sales', 'booster_stock_change'],
            ],
            'competitor' => [
                'label' => 'Rakip mağaza uyarıları',
                'description' => 'Rakip mağazada yeni ürün veya fiyat değişimi olduğunda sinyal üretir.',
                'types' => ['booster_store_change'],
            ],
            'keyword' => [
                'label' => 'Kelime sıra uyarıları',
                'description' => 'Takip edilen anahtar kelimede sıra veya görünürlük durumu değişince bildirir.',
                'types' => ['booster_keyword_change'],
            ],
        ];
    }

    /**
     * @param  array<int, string>  $mutedTypes
     */
    protected function pendingBoosterEmailDigestCount(array $mutedTypes): int
    {
        if (! Schema::hasTable('app_notifications') || ! Schema::hasColumn('app_notifications', 'email_digest_sent_at')) {
            return 0;
        }

        $types = array_values(array_diff(
            TrendyolBoosterEmailDigestService::BOOSTER_NOTIFICATION_TYPES,
            $mutedTypes,
        ));

        if ($types === []) {
            return 0;
        }

        return AppNotification::query()
            ->where('user_id', $this->userId())
            ->whereIn('type', $types)
            ->whereNull('email_digest_sent_at')
            ->count();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function logActivity(
        string $type,
        string $title,
        ?string $subject = null,
        ?string $summary = null,
        ?string $resultLabel = null,
        mixed $resultValue = null,
        array $payload = [],
        ?int $trackedProductId = null,
    ): void {
        if (! $this->boosterActivityTablesReady()) {
            return;
        }

        app(TrendyolBoosterActivityLogger::class)->log(
            $this->userId(),
            $type,
            $title,
            $subject,
            $summary,
            $resultLabel,
            $resultValue,
            $payload,
            $trackedProductId,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyPreview(): array
    {
        return [
            'normalized' => [],
            'simulation' => [
                'sale_price' => 0,
                'net_profit' => 0,
                'profit_margin_percent' => 0,
                'net_receivable' => 0,
                'break_even_price' => 0,
                'target_price' => 0,
                'warnings' => [],
                'breakdown' => [
                    'commission' => 0,
                    'cargo' => 0,
                    'service_fee' => 0,
                    'advertising' => 0,
                    'return_reserve' => 0,
                    'withholding' => 0,
                    'net_vat' => 0,
                    'total_deductions' => 0,
                ],
            ],
            'score' => 0,
            'decision' => 'watch',
            'reasons' => [],
        ];
    }

    public function formatMoney(mixed $value): string
    {
        return number_format((float) $value, 2, ',', '.').' TL';
    }

    public function decisionLabel(string $decision): string
    {
        return match ($decision) {
            'go' => 'Güçlü fırsat',
            'watch' => 'Takibe al',
            'risk' => 'Dikkat',
            'loss' => 'Zarar riski',
            default => 'İzle',
        };
    }

    public function decisionClasses(string $decision): string
    {
        return match ($decision) {
            'go' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            'watch' => 'border-sky-200 bg-sky-50 text-sky-700',
            'risk' => 'border-amber-200 bg-amber-50 text-amber-700',
            'loss' => 'border-rose-200 bg-rose-50 text-rose-700',
            default => 'border-slate-200 bg-slate-50 text-slate-700',
        };
    }

    public function stockStatusLabel(?string $status): string
    {
        return match ($status) {
            'in_stock' => 'Stokta',
            'out_of_stock' => 'Tükendi',
            'preorder' => 'Ön sipariş',
            default => 'Bilinmiyor',
        };
    }

    public function stockStatusClasses(?string $status): string
    {
        return match ($status) {
            'in_stock' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            'out_of_stock' => 'border-rose-200 bg-rose-50 text-rose-700',
            'preorder' => 'border-amber-200 bg-amber-50 text-amber-700',
            default => 'border-slate-200 bg-slate-50 text-slate-600',
        };
    }

    public function priceDeltaLabel(mixed $value): string
    {
        $delta = (float) $value;

        if ($delta === 0.0) {
            return '0,00 TL';
        }

        $prefix = $delta > 0 ? '+' : '-';

        return $prefix.number_format(abs($delta), 2, ',', '.').' TL';
    }

    public function priceDeltaClasses(mixed $value): string
    {
        $delta = (float) $value;

        return match (true) {
            $delta > 0 => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            $delta < 0 => 'border-rose-200 bg-rose-50 text-rose-700',
            default => 'border-slate-200 bg-slate-50 text-slate-600',
        };
    }

    public function competitorOpportunityLabel(?string $type): string
    {
        return match ($type) {
            'price_pressure' => 'Fiyat baskısı',
            'pricing_power' => 'Fiyat fırsatı',
            'stock_gap' => 'Stok fırsatı',
            'parity' => 'Yakın bant',
            default => 'Takip',
        };
    }

    public function competitorOpportunityClasses(?string $type): string
    {
        return match ($type) {
            'price_pressure' => 'border-rose-200 bg-rose-50 text-rose-700',
            'pricing_power', 'stock_gap' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            'parity' => 'border-sky-200 bg-sky-50 text-sky-700',
            default => 'border-slate-200 bg-slate-50 text-slate-600',
        };
    }

    public function keywordStatusLabel(?string $status): string
    {
        return match ($status) {
            'visible', 'found' => 'Listede',
            'near' => 'Yakın',
            'low_visibility' => 'Geride',
            'missing', 'not_found' => 'Bulunamadı',
            default => 'Takip',
        };
    }

    public function keywordStatusClasses(?string $status): string
    {
        return match ($status) {
            'visible', 'found' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            'near' => 'border-sky-200 bg-sky-50 text-sky-700',
            'low_visibility' => 'border-amber-200 bg-amber-50 text-amber-700',
            'missing', 'not_found' => 'border-rose-200 bg-rose-50 text-rose-700',
            default => 'border-slate-200 bg-slate-50 text-slate-600',
        };
    }

    public function campaignDecisionLabel(?string $status): string
    {
        return match ($status) {
            'approve' => 'Onaylanabilir',
            'watch' => 'İzle',
            'risk' => 'Riskli',
            'reject' => 'Reddet',
            default => 'Takip',
        };
    }

    public function campaignDecisionClasses(?string $status): string
    {
        return match ($status) {
            'approve' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            'watch' => 'border-sky-200 bg-sky-50 text-sky-700',
            'risk' => 'border-amber-200 bg-amber-50 text-amber-700',
            'reject' => 'border-rose-200 bg-rose-50 text-rose-700',
            default => 'border-slate-200 bg-slate-50 text-slate-600',
        };
    }

    protected function boosterTablesReady(): bool
    {
        return Schema::hasTable('trendyol_booster_products');
    }

    protected function boosterSnapshotTablesReady(): bool
    {
        return Schema::hasTable('trendyol_booster_snapshots');
    }

    protected function bestsellerReportsReady(): bool
    {
        return Schema::hasTable('trendyol_bestseller_reports')
            && Schema::hasTable('trendyol_bestseller_report_runs')
            && Schema::hasTable('trendyol_bestseller_report_items');
    }

    protected function productAnalysisTablesReady(): bool
    {
        return $this->boosterTablesReady()
            && $this->boosterSnapshotTablesReady()
            && Schema::hasColumn('trendyol_booster_products', 'image_url')
            && Schema::hasColumn('trendyol_booster_snapshots', 'analysis_source');
    }

    protected function analysisRefreshReady(): bool
    {
        return $this->productAnalysisTablesReady()
            && Schema::hasColumn('trendyol_booster_products', 'analysis_auto_refresh_enabled')
            && Schema::hasColumn('trendyol_booster_products', 'next_analysis_refresh_at');
    }

    public function formatAnalysisDate(mixed $value, bool $withTime = true): string
    {
        if (! $value) {
            return '-';
        }

        try {
            return Carbon::parse((string) $value)
                ->timezone('Europe/Istanbul')
                ->format($withTime ? 'd.m.Y H:i' : 'd.m.Y');
        } catch (\Throwable) {
            return '-';
        }
    }

    protected function boosterCompetitorTablesReady(): bool
    {
        return Schema::hasTable('trendyol_booster_competitors');
    }

    protected function boosterKeywordTablesReady(): bool
    {
        return Schema::hasTable('trendyol_booster_keywords');
    }

    protected function boosterCostPresetTablesReady(): bool
    {
        return Schema::hasTable('trendyol_booster_cost_presets');
    }

    protected function boosterCampaignTablesReady(): bool
    {
        return Schema::hasTable('trendyol_booster_campaign_scenarios');
    }

    protected function boosterStockTablesReady(): bool
    {
        return Schema::hasTable('trendyol_booster_stock_checks')
            && Schema::hasTable('trendyol_booster_stock_sellers');
    }

    protected function boosterKeywordLookupTablesReady(): bool
    {
        return Schema::hasTable('trendyol_booster_keyword_lookups');
    }

    protected function boosterStoreWatchTablesReady(): bool
    {
        return Schema::hasTable('trendyol_booster_store_watches')
            && Schema::hasTable('trendyol_booster_store_watch_items')
            && Schema::hasTable('trendyol_booster_store_watch_snapshots');
    }

    protected function supplierResearchTablesReady(): bool
    {
        return Schema::hasTable('trendyol_booster_supplier_researches')
            && Schema::hasTable('trendyol_booster_supplier_offers');
    }

    protected function boosterTrendKeywordTablesReady(): bool
    {
        return Schema::hasTable('trendyol_booster_trend_keywords');
    }

    protected function boosterCommissionTablesReady(): bool
    {
        return Schema::hasTable('trendyol_booster_commission_rates');
    }

    protected function boosterActivityTablesReady(): bool
    {
        return Schema::hasTable('trendyol_booster_activity_logs');
    }

    protected function booster(): TrendyolBoosterAnalysisService
    {
        return app(TrendyolBoosterAnalysisService::class);
    }

    protected function isValidTrendyolUrl(string $url): bool
    {
        $url = trim(preg_replace('/\s+/u', '', $url) ?: '');

        if (! Str::startsWith($url, ['http://', 'https://'])) {
            return false;
        }

        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));

        return $host === 'ty.gl'
            || Str::endsWith($host, '.ty.gl')
            || $host === 'trendyol.com'
            || Str::endsWith($host, '.trendyol.com');
    }

    protected function userId(): int
    {
        return (int) Auth::id();
    }

    protected function filledText(mixed $value, string $fallback): string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : $fallback;
    }

    protected function percentValue(mixed $value): float
    {
        $rate = (float) $value;

        return round($rate > 0 && $rate <= 1 ? $rate * 100 : $rate, 2);
    }
}
