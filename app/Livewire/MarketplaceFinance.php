<?php

namespace App\Livewire;

use App\Models\ChannelOrder;
use App\Models\IntegrationSyncRun;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\OrderFinancialEvent;
use App\Models\OrderProfitSnapshot;
use App\Services\Marketplace\MarketplaceDiagnosticsGuidanceService;
use App\Services\Marketplace\LegacyFinancialProjectionInsightsService;
use App\Services\Marketplace\MarketplaceManualSyncDispatchService;
use App\Services\Marketplace\MarketplaceProviderRegistry;
use App\Services\Marketplace\MarketplaceReconciliationQueryService;
use App\Services\MpSettingsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class MarketplaceFinance extends Component
{
    use WithPagination;

    public static array $allColumnDefs = [
        'siparis' => 'Sipariş',
        'magaza' => 'Mağaza',
        'ciro' => 'Ciro',
        'alacak' => 'Net Alacak',
        'kesinti' => 'Kesinti',
        'kar' => 'Kâr',
        'varyans' => 'Kâr Farkı',
        'durum' => 'Durum',
        'mutabakat' => 'Mutabakat',
        'sync' => 'Son Finans',
    ];

    public static array $sortableColumns = [
        'siparis' => 'order_number',
        'magaza' => 'store_name_alias',
        'ciro' => 'gross_revenue_metric',
        'alacak' => 'net_receivable_metric',
        'kesinti' => 'deduction_total_metric',
        'kar' => 'profit_value_metric',
        'varyans' => 'reconciliation_delta_abs_metric',
        'durum' => 'order_status',
        'mutabakat' => 'reconciliation_score_metric',
        'sync' => 'last_financial_event_at',
    ];

    public string $search = '';
    public string $marketplaceFilter = '';
    public string $storeFilter = '';
    public string $legalEntityFilter = '';
    public string $orderStatusFilter = '';
    public string $profitStateFilter = '';
    public string $financialStateFilter = '';
    public string $deltaStateFilter = '';
    public string $eventTypeFilter = '';
    public string $legacyProjectionFilter = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $sortField = 'ordered_at';
    public string $sortDirection = 'desc';
    public int $perPage = 20;
    public array $visibleColumns = ['siparis', 'magaza', 'ciro', 'alacak', 'kesinti', 'kar', 'varyans', 'durum', 'mutabakat', 'sync'];
    public string $actionMessage = '';
    public string $actionMessageTone = 'info';

    protected $queryString = [
        'search' => ['except' => ''],
        'marketplaceFilter' => ['except' => ''],
        'storeFilter' => ['except' => ''],
        'legalEntityFilter' => ['except' => ''],
        'orderStatusFilter' => ['except' => ''],
        'profitStateFilter' => ['except' => ''],
        'financialStateFilter' => ['except' => ''],
        'deltaStateFilter' => ['except' => ''],
        'eventTypeFilter' => ['except' => ''],
        'legacyProjectionFilter' => ['except' => ''],
        'dateFrom' => ['except' => ''],
        'dateTo' => ['except' => ''],
        'sortField' => ['except' => 'ordered_at'],
        'sortDirection' => ['except' => 'desc'],
    ];

    public function mount(): void
    {
        $this->visibleColumns = $this->normalizeVisibleColumns(
            app(MpSettingsService::class)->getArray('marketplace_finance.v2.visible_columns', $this->visibleColumns)
        );

        if ($this->dateFrom === '' && $this->dateTo === '') {
            $this->dateFrom = now()->subDays(30)->toDateString();
            $this->dateTo = now()->toDateString();
        }
    }

    public function updated($property): void
    {
        if (in_array($property, [
            'search',
            'marketplaceFilter',
            'storeFilter',
            'legalEntityFilter',
            'orderStatusFilter',
            'profitStateFilter',
            'financialStateFilter',
            'deltaStateFilter',
            'eventTypeFilter',
            'legacyProjectionFilter',
            'dateFrom',
            'dateTo',
            'perPage',
        ], true)) {
            $this->resetPage();
        }
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
            ->get(['id', 'store_name', 'marketplace', 'store_code', 'is_active']);
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
    public function eventTypeOptions()
    {
        return OrderFinancialEvent::query()
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', $this->userId()))
            ->select('event_type')
            ->distinct()
            ->orderBy('event_type')
            ->pluck('event_type');
    }

    #[Computed]
    public function stats(): array
    {
        $baseQuery = $this->buildFinanceBaseQuery();

        $rawStats = DB::query()
            ->fromSub((clone $baseQuery)->reorder(), 'finance_base')
            ->selectRaw('
                COUNT(DISTINCT id) as total_orders,
                COALESCE(SUM(gross_revenue_metric), 0) as total_revenue,
                COALESCE(SUM(net_receivable_metric), 0) as total_receivable,
                COALESCE(SUM(commission_total_metric), 0) as total_commission,
                COALESCE(SUM(cargo_total_metric), 0) as total_cargo,
                COALESCE(SUM(service_fee_total_metric), 0) as total_service_fee,
                COALESCE(SUM(withholding_total_metric), 0) as total_withholding,
                COALESCE(SUM(deduction_total_metric), 0) as total_deductions,
                COALESCE(SUM(CASE WHEN profit_state_metric = "confirmed" THEN profit_value_metric ELSE 0 END), 0) as confirmed_profit_total,
                COALESCE(SUM(CASE WHEN profit_state_metric = "estimated" THEN profit_value_metric ELSE 0 END), 0) as estimated_profit_total,
                COALESCE(SUM(profit_delta_metric), 0) as total_profit_delta,
                COALESCE(SUM(ABS(profit_delta_metric)), 0) as total_profit_delta_abs,
                COALESCE(SUM(deduction_delta_metric), 0) as total_deduction_delta,
                COALESCE(SUM(ABS(deduction_delta_metric)), 0) as total_deduction_delta_abs,
                SUM(CASE WHEN COALESCE(financial_event_count, 0) > 0 THEN 1 ELSE 0 END) as finance_ready_orders,
                SUM(CASE WHEN COALESCE(financial_event_count, 0) = 0 THEN 1 ELSE 0 END) as finance_waiting_orders,
                SUM(CASE WHEN profit_state_metric = "confirmed" THEN 1 ELSE 0 END) as confirmed_orders,
                SUM(CASE WHEN profit_state_metric = "estimated" THEN 1 ELSE 0 END) as estimated_orders,
                SUM(CASE WHEN reconciliation_state_metric = "aligned" THEN 1 ELSE 0 END) as aligned_orders,
                SUM(CASE WHEN reconciliation_state_metric = "minor" THEN 1 ELSE 0 END) as minor_variance_orders,
                SUM(CASE WHEN reconciliation_state_metric = "material" THEN 1 ELSE 0 END) as material_variance_orders,
                SUM(CASE WHEN reconciliation_state_metric = "snapshot_missing" THEN 1 ELSE 0 END) as snapshot_missing_orders,
                SUM(CASE WHEN reconciliation_state_metric = "waiting" THEN 1 ELSE 0 END) as waiting_reconciliation_orders
            ')
            ->first();

        return [
            'total_orders' => (int) ($rawStats->total_orders ?? 0),
            'total_revenue' => (float) ($rawStats->total_revenue ?? 0),
            'total_receivable' => (float) ($rawStats->total_receivable ?? 0),
            'total_commission' => (float) ($rawStats->total_commission ?? 0),
            'total_cargo' => (float) ($rawStats->total_cargo ?? 0),
            'total_service_fee' => (float) ($rawStats->total_service_fee ?? 0),
            'total_withholding' => (float) ($rawStats->total_withholding ?? 0),
            'total_deductions' => (float) ($rawStats->total_deductions ?? 0),
            'confirmed_profit_total' => (float) ($rawStats->confirmed_profit_total ?? 0),
            'estimated_profit_total' => (float) ($rawStats->estimated_profit_total ?? 0),
            'total_profit_delta' => (float) ($rawStats->total_profit_delta ?? 0),
            'total_profit_delta_abs' => (float) ($rawStats->total_profit_delta_abs ?? 0),
            'total_deduction_delta' => (float) ($rawStats->total_deduction_delta ?? 0),
            'total_deduction_delta_abs' => (float) ($rawStats->total_deduction_delta_abs ?? 0),
            'finance_ready_orders' => (int) ($rawStats->finance_ready_orders ?? 0),
            'finance_waiting_orders' => (int) ($rawStats->finance_waiting_orders ?? 0),
            'confirmed_orders' => (int) ($rawStats->confirmed_orders ?? 0),
            'estimated_orders' => (int) ($rawStats->estimated_orders ?? 0),
            'aligned_orders' => (int) ($rawStats->aligned_orders ?? 0),
            'minor_variance_orders' => (int) ($rawStats->minor_variance_orders ?? 0),
            'material_variance_orders' => (int) ($rawStats->material_variance_orders ?? 0),
            'snapshot_missing_orders' => (int) ($rawStats->snapshot_missing_orders ?? 0),
            'waiting_reconciliation_orders' => (int) ($rawStats->waiting_reconciliation_orders ?? 0),
        ];
    }

    #[Computed]
    public function sidebarSummary(): array
    {
        $storesQuery = MarketplaceStore::query()->where('user_id', $this->userId());
        $syncQuery = IntegrationSyncRun::query()->whereHas('store', fn (Builder $query) => $query->where('user_id', $this->userId()));

        return [
            'store_count' => (clone $storesQuery)->count(),
            'active_store_count' => (clone $storesQuery)->where('is_active', true)->count(),
            'latest_finance_sync' => (clone $syncQuery)
                ->where('sync_type', 'finance')
                ->where('status', 'completed')
                ->max('finished_at'),
            'failed_finance_syncs' => (clone $syncQuery)
                ->where('sync_type', 'finance')
                ->where('status', 'failed')
                ->where('created_at', '>=', now()->subDay())
                ->count(),
            'processing_finance_syncs' => (clone $syncQuery)
                ->where('sync_type', 'finance')
                ->whereIn('status', ['queued', 'processing'])
                ->count(),
            'pending_financial_events' => OrderFinancialEvent::query()
                ->whereHas('store', fn (Builder $query) => $query->where('user_id', $this->userId()))
                ->where(function (Builder $query) {
                    $query->whereNull('settlement_date')
                        ->orWhereNotIn('status', ['posted', 'completed', 'settled']);
                })
                ->count(),
            'estimated_snapshot_count' => OrderProfitSnapshot::query()
                ->whereHas('store', fn (Builder $query) => $query->where('user_id', $this->userId()))
                ->whereNull('channel_order_item_id')
                ->where('profit_state', 'estimated')
                ->count(),
        ];
    }

    #[Computed]
    public function marketplaceBreakdown()
    {
        $baseQuery = $this->buildFinanceBaseQuery();

        return DB::query()
            ->fromSub((clone $baseQuery)->reorder(), 'finance_base')
            ->selectRaw('
                marketplace_alias,
                COUNT(*) as order_count,
                COALESCE(SUM(gross_revenue_metric), 0) as total_revenue,
                COALESCE(SUM(net_receivable_metric), 0) as total_receivable,
                COALESCE(SUM(deduction_total_metric), 0) as total_deductions,
                COALESCE(SUM(CASE WHEN profit_state_metric = "confirmed" THEN profit_value_metric ELSE 0 END), 0) as confirmed_profit,
                COALESCE(SUM(profit_delta_metric), 0) as total_profit_delta,
                SUM(CASE WHEN COALESCE(financial_event_count, 0) = 0 THEN 1 ELSE 0 END) as waiting_orders,
                SUM(CASE WHEN reconciliation_state_metric = "material" THEN 1 ELSE 0 END) as material_orders,
                SUM(CASE WHEN reconciliation_state_metric = "snapshot_missing" THEN 1 ELSE 0 END) as snapshot_missing_orders
            ')
            ->groupBy('marketplace_alias')
            ->orderByDesc('total_revenue')
            ->get();
    }

    #[Computed]
    public function legacyProjectionInsights(): array
    {
        return app(LegacyFinancialProjectionInsightsService::class)->summaryForUser(
            $this->userId(),
            $this->storeFilter !== '' ? (int) $this->storeFilter : null,
            $this->legalEntityFilter !== '' ? (int) $this->legalEntityFilter : null,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function getLegacyProjectionGuidanceCard(): ?array
    {
        $service = app(LegacyFinancialProjectionInsightsService::class);

        $rows = MarketplaceStore::query()
            ->with('legalEntity:id,name')
            ->where('user_id', $this->userId())
            ->when($this->storeFilter !== '', fn (Builder $query) => $query->whereKey((int) $this->storeFilter))
            ->when($this->legalEntityFilter !== '', fn (Builder $query) => $query->where('legal_entity_id', (int) $this->legalEntityFilter))
            ->where('is_active', true)
            ->orderBy('store_name')
            ->get(['id', 'legal_entity_id', 'marketplace', 'store_name'])
            ->map(function (MarketplaceStore $store) use ($service): array {
                $summary = $service->summaryForUser($this->userId(), $store->id, $store->legal_entity_id);

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
                ? 'Eski veri muhasebe kuyruğu bu mağazada öne çıkıyor'
                : 'Eski veri yansıtmasının kesin etkisi bu mağazada görünüyor',
            'description' => $top['pending_rows'] > 0
                ? 'Yansıtma mağazasını sipariş ekranında hazırla, bekleyen eski veri satırlarını V2 kayıt defterine taşı ve ardından bu ekranda kesin etkisini kontrol et.'
                : 'Bu mağazada eski veri yansıtması tamamlanmış; kesin sipariş ve net alacak etkisini bu ekrandan takip edebilirsiniz.',
        ]);
    }

    #[Computed]
    public function diagnosticsGuidance(): array
    {
        $guidance = app(MarketplaceDiagnosticsGuidanceService::class)->guidanceForUser($this->userId(), [
            'store_id' => $this->storeFilter !== '' ? (int) $this->storeFilter : null,
            'sync_type' => 'finance',
            'hours' => 168,
            'limit' => 200,
        ]);

        $items = collect($guidance['items'])
            ->filter(fn (array $item) => in_array($item['category'] ?? '', [
                'finance_mapping',
                'order_identity',
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
            $this->sortDirection = in_array($dbColumn, [
                'gross_revenue_metric',
                'net_receivable_metric',
                'deduction_total_metric',
                'profit_value_metric',
                'financial_event_count',
                'last_financial_event_at',
                'ordered_at',
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

        app(MpSettingsService::class)->set('marketplace_finance.v2.visible_columns', $this->visibleColumns);
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->marketplaceFilter = '';
        $this->storeFilter = '';
        $this->legalEntityFilter = '';
        $this->orderStatusFilter = '';
        $this->profitStateFilter = '';
        $this->financialStateFilter = '';
        $this->deltaStateFilter = '';
        $this->eventTypeFilter = '';
        $this->legacyProjectionFilter = '';
        $this->dateFrom = now()->subDays(30)->toDateString();
        $this->dateTo = now()->toDateString();
        $this->sortField = 'ordered_at';
        $this->sortDirection = 'desc';
        $this->resetPage();
    }

    public function exportSummaryCsv()
    {
        $rows = $this->buildFinanceQuery()
            ->reorder($this->sortField, $this->sortDirection)
            ->orderByDesc('channel_orders.ordered_at')
            ->get()
            ->map(function (ChannelOrder $order) {
                $order->setAttribute('order_snapshot', $order->profitSnapshots->first());

                return $order;
            });

        $filename = 'pazaryeri_finans_ozet_v2_' . now()->format('Ymd_His') . '.csv';

        return response()->stream(function () use ($rows) {
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'Pazaryeri',
                'Mağaza',
                'Firma',
                'Sipariş No',
                'Sipariş Tarihi',
                'Durum',
                'Müşteri',
                'Ciro',
                'Net Alacak',
                'Komisyon',
                'Kargo',
                'Hizmet Bedeli',
                'Stopaj',
                'Toplam Kesinti',
                'Kâr Durumu',
                'Tahmini Kâr',
                'Kesin Kâr',
                'Kâr',
                'Kâr Farkı',
                'Kesinti Farkı',
                'Mutabakat Durumu',
                'Marj %',
                'Finans Olayı',
                'Son Finans Tarihi',
            ], ';');

            foreach ($rows as $row) {
                fputcsv($file, [
                    $this->cleanExportString($row->marketplace_alias),
                    $this->cleanExportString($row->store_name_alias),
                    $this->cleanExportString($row->legal_entity_name_alias),
                    $this->cleanExportString($row->order_number),
                    $row->ordered_at?->format('d/m/Y H:i'),
                    $this->cleanExportString($this->humanStatus($row->order_status)),
                    $this->cleanExportString($row->customer_name),
                    (float) ($row->gross_revenue_metric ?? 0),
                    (float) ($row->net_receivable_metric ?? 0),
                    (float) ($row->commission_total_metric ?? 0),
                    (float) ($row->cargo_total_metric ?? 0),
                    (float) ($row->service_fee_total_metric ?? 0),
                    (float) ($row->withholding_total_metric ?? 0),
                    (float) ($row->deduction_total_metric ?? 0),
                    $this->cleanExportString($this->profitStateLabel($row->profit_state_metric)),
                    (float) ($row->estimated_profit_metric ?? 0),
                    (float) ($row->confirmed_profit_metric ?? 0),
                    (float) ($row->profit_value_metric ?? 0),
                    (float) ($row->profit_delta_metric ?? 0),
                    (float) ($row->deduction_delta_metric ?? 0),
                    $this->cleanExportString($this->reconciliationStateLabel($row->reconciliation_state_metric)),
                    (float) ($row->margin_percent_metric ?? 0),
                    (int) ($row->financial_event_count ?? 0),
                    $row->last_financial_event_at ? \Illuminate\Support\Carbon::parse($row->last_financial_event_at)->format('d/m/Y H:i') : '',
                ], ';');
            }

            fclose($file);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function exportEventsCsv()
    {
        $rows = $this->buildFinanceQuery()
            ->reorder($this->sortField, $this->sortDirection)
            ->orderByDesc('channel_orders.ordered_at')
            ->get();

        $filename = 'pazaryeri_finans_hareketleri_v2_' . now()->format('Ymd_His') . '.csv';

        return response()->stream(function () use ($rows) {
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'Pazaryeri',
                'Mağaza',
                'Firma',
                'Sipariş No',
                'Müşteri',
                'Olay Türü',
                'Kaynak',
                'Referans',
                'Yön',
                'Tutar',
                'Durum',
                'Olay Tarihi',
                'Vade Tarihi',
                'Tahsil Tarihi',
                'Not',
            ], ';');

            foreach ($rows as $row) {
                foreach ($row->financialEvents as $event) {
                    fputcsv($file, [
                        $this->cleanExportString($row->marketplace_alias),
                        $this->cleanExportString($row->store_name_alias),
                        $this->cleanExportString($row->legal_entity_name_alias),
                        $this->cleanExportString($row->order_number),
                        $this->cleanExportString($row->customer_name),
                        $this->cleanExportString($this->humanEventType($event->event_type)),
                        $this->cleanExportString(Str::headline((string) $event->event_source)),
                        $this->cleanExportString($event->reference_number),
                        $this->cleanExportString($event->direction === 'credit' ? 'Alacak' : 'Borç'),
                        (float) ($event->amount ?? 0),
                        $this->cleanExportString($event->status),
                        $event->event_date?->format('d/m/Y H:i'),
                        $event->due_date?->format('d/m/Y H:i'),
                        $event->settlement_date?->format('d/m/Y H:i'),
                        $this->cleanExportString($event->notes),
                    ], ';');
                }
            }

            fclose($file);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function humanMarketplace(?string $marketplace): string
    {
        return (string) (MarketplaceProviderRegistry::get((string) $marketplace)['label'] ?? ucfirst((string) $marketplace));
    }

    public function humanStatus(?string $status): string
    {
        $normalized = Str::lower(trim((string) $status));

        return match (true) {
            $normalized === '' => 'Durum yok',
            str_contains($normalized, 'new') => 'Yeni',
            str_contains($normalized, 'approve'), str_contains($normalized, 'onay') => 'Onaylandı',
            str_contains($normalized, 'pack') => 'Hazırlanıyor',
            str_contains($normalized, 'ship'), str_contains($normalized, 'kargo') => 'Kargoda',
            str_contains($normalized, 'deliver'), str_contains($normalized, 'teslim') => 'Teslim Edildi',
            str_contains($normalized, 'cancel'), str_contains($normalized, 'iptal') => 'İptal',
            str_contains($normalized, 'return'), str_contains($normalized, 'iade') => 'İade',
            str_contains($normalized, 'reject'), str_contains($normalized, 'redd') => 'Reddedildi',
            default => Str::headline((string) $status),
        };
    }

    public function statusTone(?string $status): string
    {
        $normalized = Str::lower(trim((string) $status));

        return match (true) {
            $normalized === '' => 'default',
            str_contains($normalized, 'deliver'), str_contains($normalized, 'teslim') => 'success',
            str_contains($normalized, 'cancel'),
            str_contains($normalized, 'iptal'),
            str_contains($normalized, 'return'),
            str_contains($normalized, 'iade'),
            str_contains($normalized, 'reject'),
            str_contains($normalized, 'redd') => 'danger',
            str_contains($normalized, 'pack'),
            str_contains($normalized, 'ship'),
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

    public function humanEventType(?string $type): string
    {
        return match ($type) {
            'seller_revenue' => 'Satıcı Geliri',
            'sale' => 'Satış Tahsilatı',
            'capture' => 'Tahsilat',
            'refund' => 'İade',
            'authorization' => 'Provizyon',
            'void' => 'İptal',
            'commission' => 'Komisyon',
            'cargo' => 'Kargo',
            'service_fee' => 'Hizmet Bedeli',
            'fee' => 'İşlem Ücreti',
            'deduction_invoice' => 'Fatura Kesintisi',
            'withholding' => 'Stopaj',
            default => $type ? Str::headline(str_replace('_', ' ', $type)) : 'Diğer',
        };
    }

    public function financialStateLabel(?string $state): string
    {
        return match ($state) {
            'ready' => 'Hazır',
            'waiting' => 'Bekliyor',
            default => 'Bekliyor',
        };
    }

    public function legacyProjectionFilterLabel(?string $state): string
    {
        return match ($state) {
            'backlog' => 'Kuyruk var',
            'confirmed' => 'Kesin etkisi',
            default => 'Tümü',
        };
    }

    public function financialStateTone(?string $state): string
    {
        return match ($state) {
            'ready' => 'success',
            'waiting' => 'warning',
            default => 'default',
        };
    }

    public function reconciliationStateLabel(?string $state): string
    {
        return match ($state) {
            'aligned' => 'Uyumlu',
            'minor' => 'İzle',
            'material' => 'Materyal Fark',
            'snapshot_missing' => 'Anlık Kayıt Eksik',
            'waiting' => 'Bekliyor',
            default => 'Bekliyor',
        };
    }

    public function reconciliationStateTone(?string $state): string
    {
        return match ($state) {
            'aligned' => 'success',
            'minor' => 'warning',
            'material' => 'danger',
            'snapshot_missing' => 'info',
            'waiting' => 'default',
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
            'finance_mapping' => 'Finans eşleme',
            'order_identity' => 'Sipariş kimliği',
            'legacy_financial_projection' => 'Eski veri finans köprüsü',
            default => Str::headline((string) $category),
        };
    }

    public function guidanceRouteLabel(?string $route): string
    {
        return match ($route) {
            'mp.finance' => 'Finans',
            'mp.integrations' => 'Entegrasyonlar',
            'mp.orders' => 'Siparişler',
            default => 'Finans',
        };
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function guidanceRoute(array $item): string
    {
        $route = (string) ($item['route'] ?? 'mp.finance');
        $storeId = $item['store_id'] ?? null;

        return match ($route) {
            'mp.integrations' => route('mp.integrations', array_filter([
                'store' => $storeId,
            ], fn ($value) => $value !== null && $value !== '')),
            'mp.orders' => route('mp.orders', array_filter([
                'storeFilter' => $storeId,
            ], fn ($value) => $value !== null && $value !== '')),
            default => route('mp.finance', array_filter([
                'storeFilter' => $storeId,
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
            'finance_mapping' => 'Listeyi odakla',
            'order_identity' => 'Kimlik riskine odaklan',
            default => 'Listeyi odakla',
        };
    }

    public function guidanceSyncLabel(): string
    {
        $topItem = $this->diagnosticsGuidance['items'][0] ?? null;

        return match ($topItem['category'] ?? null) {
            'legacy_financial_projection' => 'Yansıtma ekranına git',
            default => 'Finans senkronunu başlat',
        };
    }

    public function focusTopGuidance(): void
    {
        $topItem = $this->diagnosticsGuidance['items'][0] ?? null;

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

        if (($topItem['category'] ?? null) === 'finance_mapping') {
            $this->financialStateFilter = 'waiting';
        } else {
            $this->financialStateFilter = '';
        }

        $this->resetPage();

        $this->actionMessage = 'Finans listesi en kritik tanı kaydına göre odaklandı.';
        $this->actionMessageTone = 'success';
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
            $this->actionMessage = 'Senkron başlatmak için mağaza bilgisi içeren bir tanı kaydı bulunamadı.';
            $this->actionMessageTone = 'warning';

            return;
        }

        $store = MarketplaceStore::query()
            ->with('connection')
            ->whereKey($storeId)
            ->where('user_id', $this->userId())
            ->first();

        if (!$store || !$store->connection || $store->connection->status === 'draft') {
            $this->actionMessage = 'Önce seçili mağazanın bağlantı bilgilerini tamamlayın.';
            $this->actionMessageTone = 'warning';

            return;
        }

        $result = app(MarketplaceManualSyncDispatchService::class)->dispatch($store, 'finance', [
            'options' => [],
            'source' => 'guidance_shortcut',
            'category' => $topItem['category'] ?? null,
            'origin_screen' => 'finance',
        ]);

        $feedback = app(MarketplaceManualSyncDispatchService::class)->feedback(
            $result,
            'finans',
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
        $this->financialStateFilter = '';
        $this->deltaStateFilter = '';
        $this->legacyProjectionFilter = $card['pending_rows'] > 0 ? 'backlog' : 'confirmed';
        $this->resetPage();

        $this->actionMessage = 'Eski veri kuyruğu odağı seçili mağaza için finans listesine taşındı.';
        $this->actionMessageTone = 'success';
    }

    public function focusLegacyConfirmedProjectionCard(): void
    {
        $card = $this->getLegacyProjectionGuidanceCard();

        if (!$card || (int) ($card['confirmed_orders'] ?? 0) <= 0) {
            $this->actionMessage = 'Gösterilecek eski veri kesin etkisi bulunamadı.';
            $this->actionMessageTone = 'warning';

            return;
        }

        $this->marketplaceFilter = filled($card['marketplace'] ?? null) ? (string) $card['marketplace'] : '';
        $this->storeFilter = (string) $card['store_id'];
        $this->legacyProjectionFilter = 'confirmed';
        $this->financialStateFilter = 'ready';
        $this->deltaStateFilter = '';
        $this->resetPage();

        $this->actionMessage = 'Eski veri yansıtmasının kesin etkisi seçili mağaza için odaklandı.';
        $this->actionMessageTone = 'success';
    }

    public function render()
    {
        $rowsPaginator = $this->buildFinanceQuery()
            ->reorder($this->sortField, $this->sortDirection)
            ->orderByDesc('channel_orders.ordered_at')
            ->paginate($this->perPage);

        $rows = $rowsPaginator->through(function (ChannelOrder $order) {
            $order->setAttribute('order_snapshot', $order->profitSnapshots->first());

            return $order;
        });

        return view('livewire.marketplace-finance', [
            'rows' => $rows,
            'stats' => $this->stats,
            'marketplaceOptions' => $this->marketplaceOptions,
            'storeOptions' => $this->storeOptions,
            'legalEntities' => $this->legalEntities,
            'eventTypeOptions' => $this->eventTypeOptions,
            'sidebarSummary' => $this->sidebarSummary,
            'marketplaceBreakdown' => $this->marketplaceBreakdown,
            'legacyProjectionInsights' => $this->legacyProjectionInsights,
            'legacyProjectionGuidanceCard' => $this->getLegacyProjectionGuidanceCard(),
            'diagnosticsGuidance' => $this->diagnosticsGuidance,
            'activeFilters' => $this->getActiveFilters(),
            'columnDefs' => static::$allColumnDefs,
            'sortableColumns' => static::$sortableColumns,
            'hasConfiguredStores' => MarketplaceStore::query()->where('user_id', $this->userId())->exists(),
            'hasChannelOrders' => ChannelOrder::query()->whereHas('store', fn (Builder $query) => $query->where('user_id', $this->userId()))->exists(),
            'hasFinancialEvents' => OrderFinancialEvent::query()->whereHas('store', fn (Builder $query) => $query->where('user_id', $this->userId()))->exists(),
        ])->layout('layouts.app', ['title' => 'Pazaryeri Finans V2']);
    }

    protected function buildFinanceQuery(): Builder
    {
        return $this->buildFinanceBaseQuery()->with([
            'store:id,legal_entity_id,marketplace,store_name,store_code,is_active',
            'store.legalEntity:id,name,tax_number',
            'packages:id,channel_order_id,package_number,package_status,cargo_company,cargo_tracking_number,shipped_at,delivered_at',
            'items:id,channel_order_id,mp_product_id,product_name,stock_code,barcode,quantity,billable_amount,gross_amount,commission_rate,line_status',
            'items.product:id,product_name,stock_code,barcode,cogs,packaging_cost,cargo_cost',
            'financialEvents' => fn ($query) => $query->orderByRaw('COALESCE(settlement_date, event_date) desc'),
            'profitSnapshots' => fn ($query) => $query
                ->whereNull('channel_order_item_id')
                ->latest('calculated_at'),
        ]);
    }

    protected function buildFinanceBaseQuery(): Builder
    {
        $reconciliation = app(MarketplaceReconciliationQueryService::class);
        $itemAggregate = $reconciliation->itemAggregate();
        $financialAggregate = $reconciliation->financialAggregate();
        $snapshotAggregate = $reconciliation->snapshotAggregate();
        $expr = $reconciliation->expressions();

        $query = ChannelOrder::query()
            ->select([
                'channel_orders.*',
                'marketplace_stores.marketplace as marketplace_alias',
                'marketplace_stores.store_name as store_name_alias',
                'legal_entities.name as legal_entity_name_alias',
                DB::raw('COALESCE(item_agg.item_lines_count, 0) as item_lines_count'),
                DB::raw('COALESCE(item_agg.total_quantity, 0) as total_quantity'),
                DB::raw('COALESCE(item_agg.matched_lines_count, 0) as matched_lines_count'),
                DB::raw("{$expr['estimated_commission']} as estimated_commission_metric"),
                DB::raw('COALESCE(fin_agg.financial_event_count, 0) as financial_event_count'),
                DB::raw('fin_agg.last_financial_event_at as last_financial_event_at'),
                DB::raw("{$expr['gross_revenue']} as gross_revenue_metric"),
                DB::raw("{$expr['net_receivable']} as net_receivable_metric"),
                DB::raw("{$expr['commission_total']} as commission_total_metric"),
                DB::raw("{$expr['cargo_total']} as cargo_total_metric"),
                DB::raw("{$expr['service_fee_total']} as service_fee_total_metric"),
                DB::raw("{$expr['withholding_total']} as withholding_total_metric"),
                DB::raw("{$expr['deduction_total']} as deduction_total_metric"),
                DB::raw("{$expr['profit_state']} as profit_state_metric"),
                DB::raw('COALESCE(order_snapshot.estimated_profit, 0) as estimated_profit_metric'),
                DB::raw('COALESCE(order_snapshot.confirmed_profit, 0) as confirmed_profit_metric'),
                DB::raw('COALESCE(order_snapshot.margin_percent, 0) as margin_percent_metric'),
                DB::raw("{$expr['profit_value']} as profit_value_metric"),
                DB::raw("{$expr['profit_delta']} as profit_delta_metric"),
                DB::raw("{$expr['deduction_delta']} as deduction_delta_metric"),
                DB::raw("{$expr['reconciliation_state']} as reconciliation_state_metric"),
                DB::raw("{$expr['reconciliation_score']} as reconciliation_score_metric"),
                DB::raw("{$expr['reconciliation_abs_delta']} as reconciliation_delta_abs_metric"),
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
            ->where('marketplace_stores.user_id', $this->userId());

        if ($this->search !== '') {
            $searchTerm = trim($this->search);
            $query->where(function (Builder $builder) use ($searchTerm) {
                $builder->where('channel_orders.order_number', 'like', '%' . $searchTerm . '%')
                    ->orWhere('channel_orders.external_order_id', 'like', '%' . $searchTerm . '%')
                    ->orWhere('channel_orders.customer_name', 'like', '%' . $searchTerm . '%')
                    ->orWhereHas('financialEvents', function (Builder $eventQuery) use ($searchTerm) {
                        $eventQuery->where('reference_number', 'like', '%' . $searchTerm . '%');
                    });
            });
        }

        if ($this->marketplaceFilter !== '') {
            $query->where('marketplace_stores.marketplace', $this->marketplaceFilter);
        }

        if ($this->storeFilter !== '') {
            $query->where('channel_orders.store_id', $this->storeFilter);
        }

        if ($this->legalEntityFilter !== '') {
            $query->where('channel_orders.legal_entity_id', $this->legalEntityFilter);
        }

        if ($this->orderStatusFilter !== '') {
            $query->where('channel_orders.order_status', $this->orderStatusFilter);
        }

        if ($this->dateFrom !== '') {
            $query->whereDate('channel_orders.ordered_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo !== '') {
            $query->whereDate('channel_orders.ordered_at', '<=', $this->dateTo);
        }

        if ($this->eventTypeFilter !== '') {
            $query->whereHas('financialEvents', fn (Builder $eventQuery) => $eventQuery->where('event_type', $this->eventTypeFilter));
        }

        if ($this->legacyProjectionFilter === 'backlog') {
            $query->whereExists(function ($subQuery) {
                $subQuery->selectRaw('1')
                    ->from('mp_orders')
                    ->join('mp_periods', 'mp_periods.id', '=', 'mp_orders.period_id')
                    ->whereColumn('mp_orders.order_number', 'channel_orders.order_number')
                    ->where('mp_periods.user_id', $this->userId())
                    ->whereNull('mp_orders.projected_at')
                    ->where(function ($inner) {
                        $inner->whereColumn('mp_orders.store_id', 'channel_orders.store_id')
                            ->orWhereNull('mp_orders.store_id');
                    });
            });
        } elseif ($this->legacyProjectionFilter === 'confirmed') {
            $query->whereHas('financialEvents', function (Builder $eventQuery) {
                $eventQuery->where('event_source', 'legacy_mp_order');
            })->whereHas('profitSnapshots', function (Builder $snapshotQuery) {
                $snapshotQuery->whereNull('channel_order_item_id')
                    ->where('profit_state', 'confirmed');
            });
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

        if ($this->deltaStateFilter !== '') {
            $query->whereRaw("{$expr['reconciliation_state']} = ?", [$this->deltaStateFilter]);
        }

        return $query;
    }

    protected function getActiveFilters(): array
    {
        return array_values(array_filter([
            $this->search !== '' ? 'Arama: ' . $this->search : null,
            $this->marketplaceFilter !== '' ? 'Pazaryeri: ' . $this->humanMarketplace($this->marketplaceFilter) : null,
            $this->storeFilter !== '' ? 'Mağaza seçildi' : null,
            $this->legalEntityFilter !== '' ? 'Firma filtresi' : null,
            $this->orderStatusFilter !== '' ? 'Sipariş: ' . $this->humanStatus($this->orderStatusFilter) : null,
            $this->profitStateFilter !== '' ? 'Kâr: ' . $this->profitStateLabel($this->profitStateFilter) : null,
            $this->financialStateFilter !== '' ? 'Finans: ' . $this->financialStateLabel($this->financialStateFilter) : null,
            $this->deltaStateFilter !== '' ? 'Mutabakat: ' . $this->reconciliationStateLabel($this->deltaStateFilter) : null,
            $this->legacyProjectionFilter !== '' ? 'Eski veri: ' . $this->legacyProjectionFilterLabel($this->legacyProjectionFilter) : null,
            $this->eventTypeFilter !== '' ? 'Olay: ' . $this->humanEventType($this->eventTypeFilter) : null,
        ]));
    }

    protected function normalizeVisibleColumns(array $columns): array
    {
        $valid = array_keys(static::$allColumnDefs);
        $normalized = array_values(array_filter($columns, fn ($column) => in_array($column, $valid, true)));

        return $normalized !== [] ? $normalized : $this->visibleColumns;
    }

    protected function cleanExportString(mixed $value): mixed
    {
        return app(\App\Services\ExcelService::class)->cleanString($value);
    }

    protected function userId(): int
    {
        return Auth::id() ?? 1;
    }
}
