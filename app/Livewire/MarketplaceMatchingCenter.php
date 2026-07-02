<?php

namespace App\Livewire;

use App\Models\IntegrationSyncRun;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\MpProduct;
use App\Models\ProductMatchIssue;
use App\Services\Marketplace\MarketplaceDiagnosticsGuidanceService;
use App\Services\Marketplace\MarketplaceManualSyncDispatchService;
use App\Services\Marketplace\MarketplaceManualMatchService;
use App\Services\Marketplace\MarketplaceProviderRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class MarketplaceMatchingCenter extends Component
{
    use WithPagination;

    public static array $allColumnDefs = [
        'urun' => 'Kanal Ürünü',
        'magaza' => 'Mağaza',
        'sebep' => 'Sorun',
        'aday' => 'Aday',
        'durum' => 'Durum',
        'aksiyon' => 'Aksiyon',
    ];

    public static array $sortableColumns = [
        'urun' => 'channel_title_alias',
        'magaza' => 'store_name_alias',
        'sebep' => 'match_reason',
        'aday' => 'candidate_count_metric',
        'durum' => 'match_status',
    ];

    public string $search = '';
    public string $marketplaceFilter = '';
    public string $storeFilter = '';
    public string $legalEntityFilter = '';
    public string $reasonFilter = '';
    public string $statusFilter = 'pending';
    public string $candidateStateFilter = '';
    public string $sortField = 'created_at';
    public string $sortDirection = 'desc';
    public int $perPage = 15;
    public bool $selectPage = false;
    public array $selectedIssueIds = [];
    public string $bulkIssueActionType = '';
    public array $visibleColumns = ['urun', 'magaza', 'sebep', 'aday', 'durum', 'aksiyon'];
    public array $issueSearchTerms = [];

    protected $queryString = [
        'search' => ['except' => ''],
        'marketplaceFilter' => ['except' => ''],
        'storeFilter' => ['except' => ''],
        'legalEntityFilter' => ['except' => ''],
        'reasonFilter' => ['except' => ''],
        'statusFilter' => ['except' => 'pending'],
        'candidateStateFilter' => ['except' => ''],
        'sortField' => ['except' => 'created_at'],
        'sortDirection' => ['except' => 'desc'],
    ];

    public function mount(): void
    {
        $this->visibleColumns = $this->normalizeVisibleColumns($this->visibleColumns);
    }

    public function updated($property): void
    {
        if (in_array($property, [
            'search',
            'marketplaceFilter',
            'storeFilter',
            'legalEntityFilter',
            'reasonFilter',
            'statusFilter',
            'candidateStateFilter',
            'perPage',
        ], true)) {
            $this->clearIssueSelection();
            $this->resetPage();
        }
    }

    public function updatedSelectPage(bool $value): void
    {
        $this->selectedIssueIds = $value ? $this->currentPageIssueIds() : [];
    }

    public function updatedSelectedIssueIds(): void
    {
        $selected = collect($this->selectedIssueIds)
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();

        $this->selectedIssueIds = $selected;

        $currentPageIds = $this->currentPageIssueIds();
        $this->selectPage = $currentPageIds !== []
            && count(array_intersect($selected, $currentPageIds)) === count($currentPageIds);
    }

    #[Computed]
    public function stats(): array
    {
        $base = ProductMatchIssue::query()
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', $this->userId()));

        return [
            'total_issues' => (clone $base)->count(),
            'pending_issues' => (clone $base)->where('match_status', 'pending')->count(),
            'resolved_issues' => (clone $base)->where('match_status', 'resolved')->count(),
            'ignored_issues' => (clone $base)->where('match_status', 'ignored')->count(),
            'with_candidates' => (clone $base)->whereRaw('COALESCE(JSON_LENGTH(candidate_ids_json), 0) > 0')->count(),
            'without_listing' => (clone $base)->whereNull('channel_listing_id')->count(),
        ];
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
    public function storeOptions()
    {
        return MarketplaceStore::query()
            ->where('user_id', $this->userId())
            ->orderBy('store_name')
            ->get(['id', 'store_name', 'marketplace', 'legal_entity_id']);
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
    public function reasonOptions()
    {
        return ProductMatchIssue::query()
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', $this->userId()))
            ->whereNotNull('match_reason')
            ->distinct()
            ->orderBy('match_reason')
            ->pluck('match_reason');
    }

    #[Computed]
    public function diagnosticsGuidance(): array
    {
        $legalEntityStoreIds = $this->legalEntityFilter !== ''
            ? MarketplaceStore::query()
                ->where('user_id', $this->userId())
                ->where('legal_entity_id', (int) $this->legalEntityFilter)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all()
            : [];

        $guidance = app(MarketplaceDiagnosticsGuidanceService::class)->guidanceForUser($this->userId(), [
            'hours' => 168,
            'limit' => 200,
        ]);

        $items = collect($guidance['items'])
            ->filter(fn (array $item) => in_array($item['category'] ?? '', [
                'product_matching',
                'legacy_financial_projection',
            ], true))
            ->when($this->marketplaceFilter !== '', fn ($collection) => $collection->where('marketplace', $this->marketplaceFilter))
            ->when($this->storeFilter !== '', fn ($collection) => $collection->where('store_id', (int) $this->storeFilter))
            ->when(
                $this->legalEntityFilter !== '',
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

    public function sortTable(string $columnKey): void
    {
        $dbColumn = static::$sortableColumns[$columnKey] ?? null;
        if (!$dbColumn) {
            return;
        }

        if ($this->sortField === $dbColumn) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $dbColumn;
            $this->sortDirection = in_array($dbColumn, ['candidate_count_metric', 'created_at', 'resolved_at'], true) ? 'desc' : 'asc';
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
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->marketplaceFilter = '';
        $this->storeFilter = '';
        $this->legalEntityFilter = '';
        $this->reasonFilter = '';
        $this->statusFilter = 'pending';
        $this->candidateStateFilter = '';
        $this->sortField = 'created_at';
        $this->sortDirection = 'desc';
        $this->clearIssueSelection();
        $this->resetPage();
    }

    public function clearIssueSelection(): void
    {
        $this->selectedIssueIds = [];
        $this->selectPage = false;
        $this->bulkIssueActionType = '';
    }

    public function exportDiagnosticsGuidanceCsv()
    {
        $filename = 'eslestirme_karar_destegi_' . now()->format('Ymd_His') . '.csv';
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

    public function manualMatch(int $issueId, int $productId): void
    {
        $issue = $this->resolveIssueForUser($issueId);
        $product = MpProduct::query()
            ->where('user_id', $this->userId())
            ->findOrFail($productId);

        try {
            $result = app(MarketplaceManualMatchService::class)->manualMatch($issue, $product, $this->userId());
            session()->flash('success', 'Eşleştirme tamamlandı. ' . $result['updated_items'] . ' sipariş satırı güncellendi, ' . $result['impacted_orders'] . ' sipariş yeniden hesaplandı.');
        } catch (\Throwable $exception) {
            session()->flash('warning', $exception->getMessage());
        }
    }

    public function manualMatchRecommended(int $issueId): void
    {
        $issue = $this->resolveIssueForUser($issueId);
        $candidateIds = collect((array) ($issue->candidate_ids_json ?? []))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $prefetchedProducts = $candidateIds->isNotEmpty()
            ? MpProduct::query()
                ->where('user_id', $this->userId())
                ->whereIn('id', $candidateIds->all())
                ->get()
                ->keyBy('id')
            : collect();

        $candidates = $this->resolveCandidatesForIssue($issue, $prefetchedProducts);
        $recommended = $candidates->first();

        if (!$this->canAutoRecommend($recommended)) {
            session()->flash('warning', 'Bu sorun için güvenilir bir öneri bulunamadı.');

            return;
        }

        $this->manualMatch($issueId, $recommended->id);
    }

    public function ignoreIssue(int $issueId): void
    {
        $issue = $this->resolveIssueForUser($issueId);
        app(MarketplaceManualMatchService::class)->ignore($issue, $this->userId());
        session()->flash('success', 'Sorun göz ardı edildi.');
    }

    public function reopenIssue(int $issueId): void
    {
        $issue = $this->resolveIssueForUser($issueId);
        app(MarketplaceManualMatchService::class)->reopen($issue);
        session()->flash('success', 'Sorun yeniden incelemeye açıldı.');
    }

    public function runBulkIssueAction(): void
    {
        $allowedActions = array_keys($this->bulkIssueActionOptions());

        if (!in_array($this->bulkIssueActionType, $allowedActions, true)) {
            session()->flash('warning', 'Toplu sorun işlemi için geçerli bir aksiyon seçin.');

            return;
        }

        $selectedIds = collect($this->selectedIssueIds)
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($selectedIds->isEmpty()) {
            session()->flash('warning', 'Önce en az bir sorun seçin.');

            return;
        }

        $issues = ProductMatchIssue::query()
            ->with(['store'])
            ->whereIn('id', $selectedIds->all())
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', $this->userId()))
            ->get();

        $processedCount = 0;
        $skippedCount = 0;
        $service = app(MarketplaceManualMatchService::class);

        foreach ($issues as $issue) {
            if ($this->bulkIssueActionType === 'ignore') {
                if ($issue->match_status !== 'pending') {
                    $skippedCount++;
                    continue;
                }

                $service->ignore($issue, $this->userId());
                $processedCount++;
                continue;
            }

            if ($this->bulkIssueActionType === 'reopen') {
                if ($issue->match_status === 'pending') {
                    $skippedCount++;
                    continue;
                }

                $service->reopen($issue);
                $processedCount++;
            }
        }

        if ($processedCount === 0) {
            session()->flash('warning', 'Seçili sorun kayıtlarında uygulanabilir bir toplu aksiyon bulunamadı.');

            return;
        }

        $message = $processedCount . ' sorun için "' . $this->bulkIssueActionOptions()[$this->bulkIssueActionType] . '" uygulandı.';

        if ($skippedCount > 0) {
            $message .= ' ' . $skippedCount . ' kayıt durum nedeniyle atlandı.';
        }

        session()->flash('success', $message);
        $this->clearIssueSelection();
    }

    public function humanMarketplace(?string $marketplace): string
    {
        return (string) (MarketplaceProviderRegistry::get((string) $marketplace)['label'] ?? ucfirst((string) $marketplace));
    }

    public function statusLabel(?string $status): string
    {
        $status = Str::of((string) $status)
            ->trim()
            ->lower()
            ->replaceMatches('/[\s-]+/', '_')
            ->value();

        return match (true) {
            $status === 'pending' => 'Bekliyor',
            $status === 'resolved' => 'Çözüldü',
            $status === 'ignored' => 'Göz ardı',
            in_array($status, ['active', 'approved', 'live', 'on_sale', 'onsale', 'published', 'publish', 'enabled'], true) => 'Yayında',
            in_array($status, ['inactive', 'suspended', 'passive', 'paused', 'disabled', 'closed', 'archived', 'trash', 'deleted'], true) => 'Pasif',
            in_array($status, ['draft', 'private'], true) => 'Taslak',
            in_array($status, ['pending_approval', 'awaiting_approval', 'waiting_approval', 'review', 'under_review'], true) => 'Onay bekliyor',
            in_array($status, ['rejected', 'reject', 'blocked', 'not_approved'], true) => 'Reddedildi',
            in_array($status, ['out_of_stock', 'sold_out'], true) => 'Tükendi',
            default => 'Bilinmeyen durum',
        };
    }

    public function statusTone(?string $status): string
    {
        return match ($status) {
            'pending' => 'warning',
            'resolved' => 'success',
            'ignored' => 'default',
            default => 'info',
        };
    }

    public function reasonLabel(?string $reason): string
    {
        return match ($reason) {
            'not_found' => 'Eşleşme bulunamadı',
            'candidate_found' => 'Aday ürün bulundu',
            'ambiguous_stock_code' => 'Stok kodu birden fazla ürüne gidiyor',
            'ambiguous_barcode' => 'Barkod birden fazla ürüne gidiyor',
            'auto_match_disabled' => 'Otomatik eşleşme kapalı',
            default => $reason ? Str::headline(str_replace('_', ' ', $reason)) : 'Sebep yok',
        };
    }

    public function reasonTone(?string $reason): string
    {
        return match ($reason) {
            'not_found', 'auto_match_disabled' => 'warning',
            'ambiguous_stock_code', 'ambiguous_barcode' => 'danger',
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
            'listing_completeness' => 'Listeleme tamlığı',
            'legacy_financial_projection' => 'Eski veri finans köprüsü',
            default => Str::headline((string) $category),
        };
    }

    public function guidanceRouteLabel(?string $route): string
    {
        return match ($route) {
            'mp.products' => 'Ürünler',
            'mp.integrations' => 'Entegrasyonlar',
            'mp.orders' => 'Siparişler',
            default => 'Eşleştirme Merkezi',
        };
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function guidanceRoute(array $item): string
    {
        $route = (string) ($item['route'] ?? 'mp.matching');
        $storeId = $item['store_id'] ?? null;
        $marketplace = $item['marketplace'] ?? null;

        return match ($route) {
            'mp.products' => route('mp.products', array_filter([
                'marketplaceFilter' => filled($marketplace) ? $marketplace : null,
            ], fn ($value) => $value !== null && $value !== '')),
            'mp.integrations' => route('mp.integrations', array_filter([
                'store' => $storeId,
            ], fn ($value) => $value !== null && $value !== '')),
            'mp.orders' => route('mp.orders', array_filter([
                'storeFilter' => $storeId,
            ], fn ($value) => $value !== null && $value !== '')),
            default => route('mp.matching', array_filter([
                'storeFilter' => $storeId,
                'statusFilter' => 'pending',
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
            default => 'Sorun listesini odakla',
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

    /**
     * @return array<string, string>
     */
    public function bulkIssueActionOptions(): array
    {
        return [
            'ignore' => 'Seçilileri göz ardı et',
            'reopen' => 'Seçilileri yeniden aç',
        ];
    }

    public function candidateScore(MpProduct $candidate): int
    {
        return (int) ($candidate->getAttribute('match_score_metric') ?? 0);
    }

    public function candidateScoreTone(MpProduct $candidate): string
    {
        $score = $this->candidateScore($candidate);

        return match (true) {
            $score >= 100 => 'success',
            $score >= 70 => 'info',
            $score >= 40 => 'warning',
            default => 'default',
        };
    }

    public function canAutoRecommend(?MpProduct $candidate): bool
    {
        return $candidate instanceof MpProduct && $this->candidateScore($candidate) >= 100;
    }

    /**
     * @return array<int, string>
     */
    public function candidateReasons(MpProduct $candidate): array
    {
        return array_values(array_filter((array) ($candidate->getAttribute('match_reasons_metric') ?? [])));
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

        $this->marketplaceFilter = filled($topItem['marketplace'] ?? null) ? (string) $topItem['marketplace'] : '';
        $this->storeFilter = filled($topItem['store_id'] ?? null) ? (string) $topItem['store_id'] : '';
        $this->statusFilter = 'pending';
        $this->candidateStateFilter = '';
        $this->clearIssueSelection();
        $this->resetPage();

        session()->flash('success', 'Sorun listesi en kritik tanı kaydına göre odaklandı.');
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
            'origin_screen' => 'matching',
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
        $issuesPaginator = $this->buildIssuesQuery()
            ->reorder($this->sortField, $this->sortDirection)
            ->orderByDesc('product_match_issues.created_at')
            ->paginate($this->perPage);

        $issues = $issuesPaginator->through(fn (ProductMatchIssue $issue) => $issue);
        $candidateMap = $this->buildCandidateMap($issues->getCollection());

        return view('livewire.marketplace-matching-center', [
            'issues' => $issues,
            'candidateMap' => $candidateMap,
            'stats' => $this->stats,
            'marketplaceOptions' => $this->marketplaceOptions,
            'storeOptions' => $this->storeOptions,
            'legalEntities' => $this->legalEntities,
            'reasonOptions' => $this->reasonOptions,
            'diagnosticsGuidance' => $this->diagnosticsGuidance,
            'activeFilters' => $this->getActiveFilters(),
            'columnDefs' => static::$allColumnDefs,
            'sortableColumns' => static::$sortableColumns,
        ])->layout('layouts.app', ['title' => 'Eşleştirme Merkezi']);
    }

    protected function buildIssuesQuery(): Builder
    {
        $query = ProductMatchIssue::query()
            ->select([
                'product_match_issues.*',
                'marketplace_stores.marketplace as marketplace_alias',
                'marketplace_stores.store_name as store_name_alias',
                'legal_entities.name as legal_entity_name_alias',
                'channel_listings.listing_id as listing_id_alias',
                'channel_listings.listing_status as listing_status_alias',
                'channel_products.stock_code as channel_stock_code_alias',
                'channel_products.barcode as channel_barcode_alias',
                'channel_products.title as channel_title_alias',
                'matched_products.product_name as matched_product_name_alias',
                DB::raw('COALESCE(JSON_LENGTH(product_match_issues.candidate_ids_json), 0) as candidate_count_metric'),
            ])
            ->join('marketplace_stores', 'marketplace_stores.id', '=', 'product_match_issues.store_id')
            ->join('legal_entities', 'legal_entities.id', '=', 'marketplace_stores.legal_entity_id')
            ->leftJoin('channel_listings', 'channel_listings.id', '=', 'product_match_issues.channel_listing_id')
            ->leftJoin('channel_products', 'channel_products.id', '=', 'channel_listings.channel_product_id')
            ->leftJoin('mp_products as matched_products', 'matched_products.id', '=', 'channel_listings.mp_product_id')
            ->with([
                'store:id,legal_entity_id,marketplace,store_name,store_code,is_active',
                'store.legalEntity:id,name,tax_number',
                'channelListing:id,store_id,channel_product_id,mp_product_id,listing_id,listing_status,sale_price,stock_quantity,last_synced_at',
                'channelListing.channelProduct:id,store_id,external_product_id,stock_code,barcode,title,brand,category_name,last_synced_at',
                'channelListing.product:id,product_name,stock_code,barcode,cogs,sale_price,stock_quantity',
                'resolver:id,name',
            ])
            ->where('marketplace_stores.user_id', $this->userId());

        if ($this->search !== '') {
            $searchTerm = trim($this->search);
            $query->where(function (Builder $builder) use ($searchTerm) {
                $builder->where('marketplace_stores.store_name', 'like', '%' . $searchTerm . '%')
                    ->orWhere('channel_products.stock_code', 'like', '%' . $searchTerm . '%')
                    ->orWhere('channel_products.barcode', 'like', '%' . $searchTerm . '%')
                    ->orWhere('channel_products.title', 'like', '%' . $searchTerm . '%')
                    ->orWhere('channel_listings.listing_id', 'like', '%' . $searchTerm . '%')
                    ->orWhere('product_match_issues.match_reason', 'like', '%' . $searchTerm . '%');
            });
        }

        if ($this->marketplaceFilter !== '') {
            $query->where('marketplace_stores.marketplace', $this->marketplaceFilter);
        }

        if ($this->storeFilter !== '') {
            $query->where('product_match_issues.store_id', $this->storeFilter);
        }

        if ($this->legalEntityFilter !== '') {
            $query->where('marketplace_stores.legal_entity_id', $this->legalEntityFilter);
        }

        if ($this->reasonFilter !== '') {
            $query->where('product_match_issues.match_reason', $this->reasonFilter);
        }

        if ($this->statusFilter !== '') {
            $query->where('product_match_issues.match_status', $this->statusFilter);
        }

        if ($this->candidateStateFilter === 'with_candidates') {
            $query->whereRaw('COALESCE(JSON_LENGTH(product_match_issues.candidate_ids_json), 0) > 0');
        } elseif ($this->candidateStateFilter === 'without_candidates') {
            $query->whereRaw('COALESCE(JSON_LENGTH(product_match_issues.candidate_ids_json), 0) = 0');
        } elseif ($this->candidateStateFilter === 'with_listing') {
            $query->whereNotNull('product_match_issues.channel_listing_id');
        } elseif ($this->candidateStateFilter === 'without_listing') {
            $query->whereNull('product_match_issues.channel_listing_id');
        }

        return $query;
    }

    /**
     * @param  Collection<int, ProductMatchIssue>  $issues
     * @return array<int, Collection<int, MpProduct>>
     */
    protected function buildCandidateMap(Collection $issues): array
    {
        $candidateIds = $issues
            ->flatMap(fn (ProductMatchIssue $issue) => (array) ($issue->candidate_ids_json ?? []))
            ->filter()
            ->unique()
            ->values();

        $prefetchedProducts = $candidateIds->isNotEmpty()
            ? MpProduct::query()
                ->where('user_id', $this->userId())
                ->whereIn('id', $candidateIds)
                ->get()
                ->keyBy('id')
            : collect();

        $map = [];

        foreach ($issues as $issue) {
            $map[$issue->id] = $this->resolveCandidatesForIssue($issue, $prefetchedProducts);
        }

        return $map;
    }

    /**
     * @param  Collection<int, MpProduct>  $prefetchedProducts
     * @return Collection<int, MpProduct>
     */
    protected function resolveCandidatesForIssue(ProductMatchIssue $issue, Collection $prefetchedProducts): Collection
    {
        $searchTerm = trim((string) ($this->issueSearchTerms[$issue->id] ?? ''));

        if ($searchTerm !== '') {
            $searchResults = $this->productPoolQuery()
                ->where(function (Builder $query) use ($searchTerm) {
                    $query->where('product_name', 'like', '%' . $searchTerm . '%')
                        ->orWhere('stock_code', 'like', '%' . $searchTerm . '%')
                        ->orWhere('barcode', 'like', '%' . $searchTerm . '%')
                        ->orWhere('model_code', 'like', '%' . $searchTerm . '%');
                })
                ->orderBy('product_name')
                ->limit(12)
                ->get();

            return $this->rankCandidatesForIssue($issue, $searchResults)->take(8)->values();
        }

        $issueCandidates = collect((array) ($issue->candidate_ids_json ?? []))
            ->map(fn ($id) => $prefetchedProducts->get($id))
            ->filter()
            ->values();

        if ($issueCandidates->isNotEmpty()) {
            return $this->rankCandidatesForIssue($issue, $issueCandidates)->values();
        }

        $fallbackQuery = $this->productPoolQuery();
        $channelProduct = $issue->channelListing?->channelProduct;
        $stockCode = trim((string) ($channelProduct?->stock_code ?? ''));
        $barcode = trim((string) ($channelProduct?->barcode ?? ''));
        $title = trim((string) ($channelProduct?->title ?? ''));

        if ($stockCode !== '' || $barcode !== '' || $title !== '') {
            $modelVariants = $this->modelCodeVariantsFromText($title);
            $titleTokens = $this->candidateLookupTokens($title);

            $fallbackQuery->where(function (Builder $query) use ($stockCode, $barcode, $title, $modelVariants, $titleTokens) {
                if ($stockCode !== '') {
                    $query->orWhere('stock_code', $stockCode);
                }

                if ($barcode !== '') {
                    $query->orWhere('barcode', $barcode);
                }

                if ($title !== '') {
                    $needle = Str::limit($title, 30, '');
                    $query->orWhere('product_name', 'like', '%' . $needle . '%');
                }

                foreach ($modelVariants as $variant) {
                    $query
                        ->orWhere('model_code', $variant)
                        ->orWhere('model_code', 'like', $variant . '%');
                }

                foreach (array_slice($titleTokens, 0, 6) as $token) {
                    $query
                        ->orWhere('product_name', 'like', '%' . $token . '%')
                        ->orWhere('model_code', 'like', '%' . $token . '%');
                }
            });
        } else {
            $fallbackQuery->whereRaw('1 = 0');
        }

        return $this->rankCandidatesForIssue(
            $issue,
            $fallbackQuery->orderBy('product_name')->limit(12)->get()
        )->take(8)->values();
    }

    protected function getActiveFilters(): array
    {
        return array_values(array_filter([
            $this->search !== '' ? 'Arama: ' . $this->search : null,
            $this->marketplaceFilter !== '' ? 'Pazaryeri: ' . $this->humanMarketplace($this->marketplaceFilter) : null,
            $this->storeFilter !== '' ? 'Mağaza filtresi aktif' : null,
            $this->legalEntityFilter !== '' ? 'Firma filtresi aktif' : null,
            $this->reasonFilter !== '' ? 'Sebep: ' . $this->reasonLabel($this->reasonFilter) : null,
            $this->statusFilter !== '' ? 'Durum: ' . $this->statusLabel($this->statusFilter) : null,
            $this->candidateStateFilter !== '' ? 'Aday: ' . match ($this->candidateStateFilter) {
                'with_candidates' => 'Aday var',
                'without_candidates' => 'Aday yok',
                'with_listing' => 'Listelemeye bağlı',
                'without_listing' => 'Listeleme yok',
                default => $this->candidateStateFilter,
            } : null,
        ]));
    }

    protected function resolveIssueForUser(int $issueId): ProductMatchIssue
    {
        return ProductMatchIssue::query()
            ->with([
                'store.syncProfile',
                'channelListing.channelProduct',
                'channelListing.product',
                'resolver:id,name',
            ])
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', $this->userId()))
            ->findOrFail($issueId);
    }

    /**
     * @return Builder<MpProduct>
     */
    protected function productPoolQuery(): Builder
    {
        return MpProduct::query()->where('user_id', $this->userId());
    }

    /**
     * @param  Collection<int, MpProduct>  $candidates
     * @return Collection<int, MpProduct>
     */
    protected function rankCandidatesForIssue(ProductMatchIssue $issue, Collection $candidates): Collection
    {
        $channelProduct = $issue->channelListing?->channelProduct;
        $channelStockCode = $this->normalizeToken($channelProduct?->stock_code);
        $channelBarcode = $this->normalizeToken($channelProduct?->barcode);
        $channelTitle = $this->normalizeText($channelProduct?->title);
        $channelBrand = $this->normalizeText($channelProduct?->brand);
        $channelCategory = $this->normalizeText($channelProduct?->category_name);
        $channelModelVariants = $this->modelCodeVariantsFromText($channelProduct?->title);

        return $candidates
            ->map(function (MpProduct $candidate) use ($channelStockCode, $channelBarcode, $channelTitle, $channelBrand, $channelCategory, $channelModelVariants) {
                $score = 0;
                $reasons = [];

                if ($channelStockCode !== '' && $this->normalizeToken($candidate->stock_code) === $channelStockCode) {
                    $score += 100;
                    $reasons[] = 'Stok kodu birebir eşleşiyor';
                }

                if ($channelBarcode !== '' && $this->normalizeToken($candidate->barcode) === $channelBarcode) {
                    $score += 120;
                    $reasons[] = 'Barkod birebir eşleşiyor';
                }

                if ($channelBrand !== '' && $this->normalizeText($candidate->brand) === $channelBrand) {
                    $score += 12;
                    $reasons[] = 'Marka aynı';
                }

                if ($channelCategory !== '' && $this->normalizeText($candidate->category_name) === $channelCategory) {
                    $score += 8;
                    $reasons[] = 'Kategori aynı';
                }

                $candidateModel = $this->normalizeCode($candidate->model_code);
                foreach ($channelModelVariants as $variant) {
                    if ($candidateModel === '') {
                        continue;
                    }

                    if ($candidateModel === $variant) {
                        $score += 90;
                        $reasons[] = 'Model kodu birebir uyumlu';
                        break;
                    }

                    if (str_starts_with($candidateModel, $variant) || str_starts_with($variant, $candidateModel)) {
                        $score += 70;
                        $reasons[] = 'Model kodu ailesi uyumlu';
                        break;
                    }
                }

                $titleOverlap = $this->titleTokenOverlapScore($channelTitle, $this->normalizeText($candidate->product_name));
                if ($titleOverlap > 0) {
                    $score += $titleOverlap;
                    $reasons[] = 'Ürün adı benzer';
                }

                $candidate->setAttribute('match_score_metric', $score);
                $candidate->setAttribute('match_reasons_metric', $reasons);

                return $candidate;
            })
            ->sortByDesc(fn (MpProduct $candidate) => [
                (int) $candidate->getAttribute('match_score_metric'),
                (float) ($candidate->sale_price ?? 0),
            ])
            ->values();
    }

    protected function titleTokenOverlapScore(string $left, string $right): int
    {
        if ($left === '' || $right === '') {
            return 0;
        }

        $leftTokens = collect(preg_split('/\s+/', $left) ?: [])
            ->map(fn ($token) => trim((string) $token))
            ->filter(fn ($token) => mb_strlen($token) >= 3)
            ->unique()
            ->values();

        $rightTokens = collect(preg_split('/\s+/', $right) ?: [])
            ->map(fn ($token) => trim((string) $token))
            ->filter(fn ($token) => mb_strlen($token) >= 3)
            ->unique()
            ->values();

        if ($leftTokens->isEmpty() || $rightTokens->isEmpty()) {
            return 0;
        }

        $overlap = $leftTokens->intersect($rightTokens)->count();

        return min(30, $overlap * 6);
    }

    protected function normalizeToken(?string $value): string
    {
        return Str::of((string) $value)
            ->upper()
            ->replace([' ', '-', '_'], '')
            ->trim()
            ->toString();
    }

    protected function normalizeText(?string $value): string
    {
        return Str::of((string) $value)
            ->lower()
            ->replaceMatches('/[^[:alnum:]\s]+/u', ' ')
            ->replaceMatches('/\s+/u', ' ')
            ->trim()
            ->toString();
    }

    /**
     * @return array<int, string>
     */
    protected function modelCodeVariantsFromText(?string $text): array
    {
        $upper = strtoupper((string) $text);
        preg_match_all('/ZEM[A-Z0-9]+/', $upper, $matches);

        $variants = [];

        foreach ($matches[0] ?? [] as $token) {
            $token = $this->normalizeCode($token);

            if ($token === '') {
                continue;
            }

            $variants[] = $token;
            $withoutDigits = preg_replace('/\d+$/', '', $token) ?: $token;
            $variants[] = $withoutDigits;

            while (strlen($withoutDigits) > 6) {
                $withoutDigits = substr($withoutDigits, 0, -1);
                $variants[] = $withoutDigits;
            }
        }

        return array_values(array_unique(array_filter($variants, fn (string $variant) => strlen($variant) >= 6)));
    }

    /**
     * @return array<int, string>
     */
    protected function candidateLookupTokens(?string $text): array
    {
        $normalized = $this->normalizeLookupText($text);

        if ($normalized === '') {
            return [];
        }

        $stopWords = [
            'adet', 'one', 'size', 'olan', 'icin', 'için', 'ile', 've', 'bir', 'iki',
            'tak', 'takim', 'takimi', 'takımı', 'urun', 'ürün', 'seti',
        ];

        return collect(preg_split('/\s+/', $normalized) ?: [])
            ->map(fn ($token) => trim((string) $token))
            ->filter(fn ($token) => mb_strlen($token) >= 4)
            ->reject(fn ($token) => in_array($token, $stopWords, true))
            ->unique()
            ->take(10)
            ->values()
            ->all();
    }

    protected function normalizeCode(?string $value): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $value)) ?: '';
    }

    protected function normalizeLookupText(?string $value): string
    {
        $value = mb_strtolower((string) $value, 'UTF-8');
        $value = str_replace(['ı', 'İ'], ['i', 'i'], $value);
        $value = preg_replace('/[^[:alnum:]\s]+/u', ' ', $value) ?: '';
        $value = preg_replace('/\s+/u', ' ', $value) ?: '';

        return trim($value);
    }

    protected function normalizeVisibleColumns(array $columns): array
    {
        $valid = array_keys(static::$allColumnDefs);
        $normalized = array_values(array_filter($columns, fn ($column) => in_array($column, $valid, true)));

        return $normalized !== [] ? $normalized : $this->visibleColumns;
    }

    protected function userId(): int
    {
        return Auth::id() ?? 1;
    }

    protected function cleanExportString(mixed $value): mixed
    {
        return app(\App\Services\ExcelService::class)->cleanString($value);
    }

    /**
     * @return array<int, string>
     */
    protected function currentPageIssueIds(): array
    {
        return $this->buildIssuesQuery()
            ->reorder($this->sortField, $this->sortDirection)
            ->orderByDesc('product_match_issues.created_at')
            ->forPage($this->getPage(), $this->perPage)
            ->pluck('product_match_issues.id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }
}
