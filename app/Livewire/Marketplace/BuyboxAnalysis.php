<?php

namespace App\Livewire\Marketplace;

use App\Jobs\PushMarketplaceBulkPriceActionsJob;
use App\Jobs\PushMarketplacePriceActionJob;
use App\Jobs\RollbackMarketplacePriceActionJob;
use App\Models\MarketplaceStore;
use App\Models\MpBuyboxListing;
use App\Models\MpPriceAction;
use App\Models\MpPriceRecommendation;
use App\Services\Marketplace\MarketplaceBuyboxRecommendationService;
use App\Services\Marketplace\MarketplacePricePolicyService;
use App\Services\Marketplace\MarketplacePricingSimulationService;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class BuyboxAnalysis extends Component
{
    use WithPagination;

    public int $selectedStoreId = 0;
    public string $activeTab = 'analysis'; // 'analysis' | 'recommendations' | 'actions' | 'policy'

    // Filters
    public string $filterBarcode = '';
    public string $filterProductName = '';
    public string $filterStatus = ''; // '' | 'winning' | 'losing'
    public string $filterStale = ''; // '' | 'fresh' | 'stale'
    public string $filterRecommendationType = '';
    public string $filterRiskLevel = '';
    public string $filterActionable = ''; // '' | 'actionable' | 'blocked'
    public ?string $filterDateFrom = null;
    public ?string $filterDateTo = null;
    public float $filterMinPriceDiff = 0.0;
    public float $filterMaxPriceDiff = 999999.0;
    public int $perPage = 25;

    // Selection & Modals
    public array $selectedRecommendationIds = [];
    public ?int $detailRecommendationId = null;
    public ?float $customRequestedPrice = null;
    public bool $showBulkPreviewModal = false;
    public bool $showPolicyModal = false;

    // Policy Form Data
    public array $policyForm = [];

    // Table Columns & Sorting
    public array $visibleColumns = [
        'barcode', 'buybox_price', 'my_price', 'minimum_safe_price',
        'recommended_price', 'price_diff', 'recommendation_type', 'risk_level', 'status',
    ];
    public string $sortBy = 'updated_at';
    public string $sortDir = 'desc';

    public static array $sortableColumns = [
        'barcode' => 'barcode',
        'buybox_price' => 'buybox_price',
        'my_price' => 'seller_price',
        'minimum_safe_price' => 'minimum_safe_price',
        'recommended_price' => 'recommended_price',
        'price_diff' => 'buybox_price',
        'seller_rank' => 'seller_rank',
        'last_updated' => 'updated_at',
    ];

    public static array $allColumnDefs = [
        'barcode' => 'Barkod / SKU',
        'buybox_price' => 'Buybox Fiyatı',
        'my_price' => 'Fiyatım',
        'minimum_safe_price' => 'Min Güvenli Fiyat',
        'recommended_price' => 'Önerilen Fiyat',
        'price_diff' => 'Fiyat Farkı',
        'expected_profit' => 'Beklenen Kâr',
        'expected_margin' => 'Beklenen Marj',
        'recommendation_type' => 'Öneri Türü',
        'risk_level' => 'Risk',
        'seller_rank' => 'Sıra',
        'second_price' => '2. Fiyat',
        'third_price' => '3. Fiyat',
        'status' => 'Durum',
        'last_updated' => 'Son Güncelleme',
    ];

    public function mount(): void
    {
        $store = MarketplaceStore::where('marketplace', 'trendyol')
            ->where('user_id', auth()->id())
            ->first();

        $this->selectedStoreId = $store?->id ?? 0;

        if ($store) {
            $policyService = app(MarketplacePricePolicyService::class);
            $this->policyForm = $policyService->getPolicy($store);
        }
    }

    public function updatedSelectedStoreId(): void
    {
        if ($this->selectedStoreId) {
            $store = $this->resolveStore();
            if (! $store) {
                $this->selectedStoreId = 0;
            } else {
                $policyService = app(MarketplacePricePolicyService::class);
                $this->policyForm = $policyService->getPolicy($store);
            }
        }

        $this->selectedRecommendationIds = [];
        $this->detailRecommendationId = null;
        $this->resetPage();
    }

    public function updatedFilterBarcode(): void { $this->resetPage(); }
    public function updatedFilterStatus(): void { $this->resetPage(); }
    public function updatedFilterRecommendationType(): void { $this->resetPage(); }
    public function updatedFilterRiskLevel(): void { $this->resetPage(); }
    public function updatedFilterActionable(): void { $this->resetPage(); }
    public function updatedFilterStale(): void { $this->resetPage(); }

    protected function resolveStore(): ?MarketplaceStore
    {
        if (! $this->selectedStoreId) {
            return null;
        }

        return MarketplaceStore::where('id', $this->selectedStoreId)
            ->where('user_id', auth()->id())
            ->first();
    }

    public function clearFilters(): void
    {
        $this->filterBarcode = '';
        $this->filterProductName = '';
        $this->filterStatus = '';
        $this->filterStale = '';
        $this->filterRecommendationType = '';
        $this->filterRiskLevel = '';
        $this->filterActionable = '';
        $this->filterDateFrom = null;
        $this->filterDateTo = null;
        $this->filterMinPriceDiff = 0.0;
        $this->filterMaxPriceDiff = 999999.0;
        $this->resetPage();
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

        $dbColumn = self::$sortableColumns[$column];

        if ($this->sortBy === $dbColumn) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $dbColumn;
            $this->sortDir = 'desc';
        }

        $this->resetPage();
    }

    /**
     * Generate / Refresh Price Recommendations for all listings in current store.
     */
    public function generateRecommendations(MarketplaceBuyboxRecommendationService $recommendationService): void
    {
        if (! auth()->user()?->isOperator()) {
            $this->dispatch('toast', ['type' => 'error', 'message' => 'Bu işlem için yetkiniz yok.']);
            return;
        }

        if (! config('marketplace.trendyol.price_recommendations_enabled', false)) {
            $this->dispatch('toast', ['type' => 'error', 'message' => 'Fiyat önerileri özelliği şu an devre dışı.']);
            return;
        }

        $store = $this->resolveStore();
        if (! $store) {
            $this->dispatch('toast', ['type' => 'error', 'message' => 'Mağaza bulunamadı.']);
            return;
        }

        $listings = MpBuyboxListing::where('store_id', $store->id)->get();
        $count = 0;

        foreach ($listings as $listing) {
            $recommendationService->generateForListing($listing);
            $count++;
        }

        $this->dispatch('toast', ['type' => 'success', 'message' => "{$count} ürün için fiyat önerileri hesaplandı."]);
    }

    /**
     * Open Product Detail Slide-Over
     */
    public function openDetailModal(int $recommendationId): void
    {
        $store = $this->resolveStore();
        $rec = MpPriceRecommendation::where('id', $recommendationId)
            ->where('store_id', $store?->id ?? 0)
            ->first();

        if ($rec) {
            $this->detailRecommendationId = $rec->id;
            $this->customRequestedPrice = (float) ($rec->recommended_price ?? $rec->current_price);
        }
    }

    public function closeDetailModal(): void
    {
        $this->detailRecommendationId = null;
        $this->customRequestedPrice = null;
    }

    /**
     * Apply a single price recommendation.
     */
    public function applySingleAction(int $recommendationId, ?float $customPrice = null): void
    {
        if (! auth()->user()?->isOperator()) {
            $this->dispatch('toast', ['type' => 'error', 'message' => 'Bu işlem için yetkiniz yok.']);
            return;
        }

        if (! config('marketplace.trendyol.manual_price_actions_enabled', false)) {
            $this->dispatch('toast', ['type' => 'error', 'message' => 'Manuel fiyat aksiyonu özelliği devre dışı.']);
            return;
        }

        $store = $this->resolveStore();
        $rec = MpPriceRecommendation::where('id', $recommendationId)
            ->where('store_id', $store?->id ?? 0)
            ->first();

        if (! $rec) {
            $this->dispatch('toast', ['type' => 'error', 'message' => 'Öneri bulunamadı.']);
            return;
        }

        $targetPrice = $customPrice !== null ? round((float) $customPrice, 2) : (float) $rec->recommended_price;

        if ($targetPrice <= 0) {
            $this->dispatch('toast', ['type' => 'error', 'message' => 'Geçerli bir fiyat giriniz.']);
            return;
        }

        // HARD SECURITY GUARD: Never allow price under minimum_safe_price
        if ($targetPrice < (float) $rec->minimum_safe_price) {
            $this->dispatch('toast', [
                'type' => 'error',
                'message' => "Girdiğiniz fiyat (₺{$targetPrice}) minimum güvenli fiyatın (₺{$rec->minimum_safe_price}) altındadır. Güvenlik nedeniyle engellendi.",
            ]);
            return;
        }

        $action = MpPriceAction::create([
            'store_id' => $store->id,
            'recommendation_id' => $rec->id,
            'barcode' => $rec->barcode,
            'old_price' => $rec->current_price,
            'requested_price' => $targetPrice,
            'action_type' => 'price_change',
            'trigger_type' => 'manual',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'status' => 'pending',
        ]);

        $rec->update(['status' => 'queued']);

        PushMarketplacePriceActionJob::dispatch($action->id);

        $this->closeDetailModal();
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Fiyat aksiyonu kuyruğa eklendi.']);
    }

    /**
     * Open Bulk Preview Modal
     */
    public function openBulkPreviewModal(): void
    {
        if (empty($this->selectedRecommendationIds)) {
            $this->dispatch('toast', ['type' => 'warning', 'message' => 'Lütfen en az bir ürün seçin.']);
            return;
        }

        $this->showBulkPreviewModal = true;
    }

    public function closeBulkPreviewModal(): void
    {
        $this->showBulkPreviewModal = false;
    }

    /**
     * Confirm Bulk Price Actions
     */
    public function confirmBulkActions(): void
    {
        $this->showBulkPreviewModal = false;

        if (! auth()->user()?->isOperator()) {
            $this->dispatch('toast', ['type' => 'error', 'message' => 'Bu işlem için yetkiniz yok.']);
            return;
        }

        if (! config('marketplace.trendyol.bulk_price_actions_enabled', false)) {
            $this->dispatch('toast', ['type' => 'error', 'message' => 'Toplu fiyat aksiyonu özelliği devre dışı.']);
            return;
        }

        $store = $this->resolveStore();
        if (! $store) {
            $this->dispatch('toast', ['type' => 'error', 'message' => 'Mağaza bulunamadı.']);
            return;
        }

        $recs = MpPriceRecommendation::whereIn('id', $this->selectedRecommendationIds)
            ->where('store_id', $store->id)
            ->get();

        $createdActionIds = [];

        foreach ($recs as $rec) {
            if (! $rec->isActionable()) {
                continue;
            }

            $targetPrice = (float) $rec->recommended_price;
            if ($targetPrice < (float) $rec->minimum_safe_price) {
                continue;
            }

            $action = MpPriceAction::create([
                'store_id' => $store->id,
                'recommendation_id' => $rec->id,
                'barcode' => $rec->barcode,
                'old_price' => $rec->current_price,
                'requested_price' => $targetPrice,
                'action_type' => 'price_change',
                'trigger_type' => 'bulk_manual',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'status' => 'pending',
            ]);

            $rec->update(['status' => 'queued']);
            $createdActionIds[] = $action->id;
        }

        if (empty($createdActionIds)) {
            $this->dispatch('toast', ['type' => 'warning', 'message' => 'Seçilen ürünler arasında uygulanabilir güvenli öneri bulunamadı.']);
            return;
        }

        PushMarketplaceBulkPriceActionsJob::dispatch($createdActionIds);

        $count = count($createdActionIds);
        $this->selectedRecommendationIds = [];
        $this->dispatch('toast', ['type' => 'success', 'message' => "{$count} adet fiyat aksiyonu toplu kuyruğa alındı."]);
    }

    /**
     * Rollback a previous price action
     */
    public function rollbackAction(int $actionId): void
    {
        if (! auth()->user()?->isOperator()) {
            $this->dispatch('toast', ['type' => 'error', 'message' => 'Bu işlem için yetkiniz yok.']);
            return;
        }

        if (! config('marketplace.trendyol.price_rollback_enabled', false)) {
            $this->dispatch('toast', ['type' => 'error', 'message' => 'Geri alma özelliği devre dışı.']);
            return;
        }

        $store = $this->resolveStore();
        $action = MpPriceAction::where('id', $actionId)
            ->where('store_id', $store?->id ?? 0)
            ->first();

        if (! $action || ! $action->canRollback()) {
            $this->dispatch('toast', ['type' => 'error', 'message' => 'Bu aksiyon geri alınamaz.']);
            return;
        }

        RollbackMarketplacePriceActionJob::dispatch($action->id, auth()->id());

        $this->dispatch('toast', ['type' => 'success', 'message' => 'Geri alma aksiyonu kuyruğa eklendi.']);
    }

    /**
     * Save store price policy settings
     */
    public function savePolicySettings(MarketplacePricePolicyService $policyService): void
    {
        if (! auth()->user()?->isOperator()) {
            $this->dispatch('toast', ['type' => 'error', 'message' => 'Bu işlem için yetkiniz yok.']);
            return;
        }

        $store = $this->resolveStore();
        if (! $store) {
            $this->dispatch('toast', ['type' => 'error', 'message' => 'Mağaza bulunamadı.']);
            return;
        }

        $this->policyForm = $policyService->savePolicy($store, $this->policyForm);
        $this->showPolicyModal = false;

        $this->dispatch('toast', ['type' => 'success', 'message' => 'Fiyat politikası kaydedildi.']);
    }

    public function render()
    {
        $flags = [
            'recommendations' => config('marketplace.trendyol.price_recommendations_enabled', false),
            'manual_push' => config('marketplace.trendyol.manual_price_actions_enabled', false),
            'bulk_push' => config('marketplace.trendyol.bulk_price_actions_enabled', false),
            'automatic_push' => config('marketplace.trendyol.automatic_price_actions_enabled', false),
            'rollback' => config('marketplace.trendyol.price_rollback_enabled', false),
        ];

        $stores = MarketplaceStore::where('marketplace', 'trendyol')
            ->where('user_id', auth()->id())
            ->get();

        $recommendations = collect();
        $recentActions = collect();
        $summary = [
            'total' => 0,
            'winners' => 0,
            'losers' => 0,
            'safe_drops' => 0,
            'safe_raises' => 0,
            'protect_margin' => 0,
            'missing_cost' => 0,
            'stale_data' => 0,
            'pending_push' => 0,
            'failed_push' => 0,
        ];

        $detailRec = null;
        $detailSimulation = null;

        $store = $this->resolveStore();

        if ($store) {
            // Aggregate summary counts
            $recQuery = MpPriceRecommendation::where('store_id', $store->id);

            $summary['total'] = (clone $recQuery)->count();
            $summary['safe_drops'] = (clone $recQuery)->whereIn('recommendation_type', ['LOWER_TO_WIN', 'MATCH_BUYBOX'])->count();
            $summary['safe_raises'] = (clone $recQuery)->where('recommendation_type', 'RAISE_WHILE_KEEPING_BUYBOX')->count();
            $summary['protect_margin'] = (clone $recQuery)->where('recommendation_type', 'PROTECT_MARGIN')->count();
            $summary['missing_cost'] = (clone $recQuery)->where('recommendation_type', 'MISSING_COST')->count();
            $summary['stale_data'] = (clone $recQuery)->where('recommendation_type', 'STALE_BUYBOX_DATA')->count();

            $summary['winners'] = MpBuyboxListing::where('store_id', $store->id)->where('seller_rank', 1)->count();
            $summary['losers'] = MpBuyboxListing::where('store_id', $store->id)->where(fn ($q) => $q->whereNull('seller_rank')->orWhere('seller_rank', '!=', 1))->count();

            $summary['pending_push'] = MpPriceAction::where('store_id', $store->id)->whereIn('status', ['pending', 'processing'])->count();
            $summary['failed_push'] = MpPriceAction::where('store_id', $store->id)->where('status', 'failed')->count();

            // Main Query
            $query = MpPriceRecommendation::where('store_id', $store->id)
                ->with(['buyboxListing']);

            if ($this->filterBarcode !== '') {
                $query->where('barcode', 'like', '%' . $this->filterBarcode . '%');
            }

            if ($this->filterRecommendationType !== '') {
                $query->where('recommendation_type', $this->filterRecommendationType);
            }

            if ($this->filterRiskLevel !== '') {
                $query->where('risk_level', $this->filterRiskLevel);
            }

            if ($this->filterActionable === 'actionable') {
                $query->whereNotIn('risk_level', ['blocked'])
                    ->whereNotNull('recommended_price')
                    ->whereRaw('recommended_price >= minimum_safe_price');
            } elseif ($this->filterActionable === 'blocked') {
                $query->where(fn ($q) => $q->where('risk_level', 'blocked')
                    ->orWhereNull('recommended_price')
                    ->orWhereRaw('recommended_price < minimum_safe_price'));
            }

            if ($this->filterStatus === 'winning') {
                $query->whereHas('buyboxListing', fn ($q) => $q->where('seller_rank', 1));
            } elseif ($this->filterStatus === 'losing') {
                $query->whereHas('buyboxListing', fn ($q) => $q->whereNull('seller_rank')->orWhere('seller_rank', '!=', 1));
            }

            $recommendations = $query->orderBy($this->sortBy, $this->sortDir)
                ->paginate($this->perPage);

            // Fetch recent price actions for history tab
            $recentActions = MpPriceAction::where('store_id', $store->id)
                ->orderByDesc('created_at')
                ->limit(20)
                ->get();

            // Prepare detail modal simulation if open
            if ($this->detailRecommendationId) {
                $detailRec = MpPriceRecommendation::where('id', $this->detailRecommendationId)
                    ->where('store_id', $store->id)
                    ->first();

                if ($detailRec && $this->customRequestedPrice > 0) {
                    $simService = app(MarketplacePricingSimulationService::class);
                    $snapshot = $detailRec->calculation_snapshot ?? [];
                    $simBase = $snapshot['sim_base'] ?? [
                        'cogs' => $detailRec->unit_cost,
                        'packaging_cost' => $detailRec->service_cost,
                        'cargo_cost' => $detailRec->cargo_cost,
                        'commission_rate' => 15,
                        'vat_rate' => 20,
                        'vat_enabled' => true,
                    ];

                    $detailSimulation = $simService->simulate($simBase + ['sale_price' => $this->customRequestedPrice]);
                }
            }
        }

        return view('livewire.marketplace.buybox-analysis', [
            'stores' => $stores,
            'recommendations' => $recommendations,
            'recentActions' => $recentActions,
            'summary' => $summary,
            'flags' => $flags,
            'detailRec' => $detailRec,
            'detailSimulation' => $detailSimulation,
        ])->layout('layouts.app');
    }
}
