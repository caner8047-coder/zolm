<?php

namespace App\Livewire;

use App\Models\LegalEntity;
use App\Models\ChannelOrder;
use App\Models\MarketplaceStore;
use App\Models\MpProfitActionItem;
use App\Services\ExcelService;
use App\Services\CampaignDecisionCenterQueryService;
use App\Services\Marketplace\MarketplaceProfitActionService;
use App\Services\Marketplace\MarketplaceProfitCenterQueryService;
use App\Services\Marketplace\MarketplaceProviderRegistry;
use App\Services\Marketplace\MarketplaceRiskSignalService;
use App\Services\MpSettingsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

class MarketplaceProfitCenter extends Component
{
    public string $marketplaceFilter = '';

    public string $storeFilter = '';

    public string $legalEntityFilter = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $activePanel = 'finance';

    public string $actionDeskFilter = 'active';

    public string $actionPriorityFilter = '';

    public string $actionOwnerFilter = '';

    public string $actionFocusFilter = '';

    public string $actionSort = 'priority';

    public string $actionListDensity = 'detailed';

    public string $executiveRadarFocus = 'auto';

    /**
     * @var array<int, string>
     */
    public array $actionNotes = [];

    /**
     * @var array<int, string>
     */
    public array $actionPriorities = [];

    /**
     * @var array<int, string>
     */
    public array $actionDueDates = [];

    /**
     * @var array<int, string>
     */
    public array $actionOwners = [];

    /**
     * @var array<int, int|string>
     */
    public array $selectedActionIds = [];

    public ?int $timelineActionId = null;

    public array $deductionColorMap = [
        'amber' => 'bg-amber-500', // Komisyon
        'sky' => 'bg-sky-500', // Kargo
        'slate' => 'bg-slate-500', // Hizmet bedeli
        'indigo' => 'bg-indigo-500', // Reklam
        'red' => 'bg-red-500', // Ceza
        'violet' => 'bg-violet-500', // Erken ödeme
        'orange' => 'bg-orange-500', // İndirim
        'zinc' => 'bg-zinc-500', // Diğer
        'rose' => 'bg-rose-500', // Stopaj
    ];

    protected $queryString = [
        'marketplaceFilter' => ['except' => ''],
        'storeFilter' => ['except' => ''],
        'legalEntityFilter' => ['except' => ''],
        'dateFrom' => ['except' => ''],
        'dateTo' => ['except' => ''],
        'activePanel' => ['except' => 'finance', 'as' => 'panel'],
        'actionDeskFilter' => ['except' => 'active', 'as' => 'actions'],
    ];

    public function mount(): void
    {
        if ($this->dateFrom === '' && $this->dateTo === '') {
            [$this->dateFrom, $this->dateTo] = $this->defaultDateRange();
        }

        if (! in_array($this->activePanel, $this->allowedPanels(), true)) {
            $this->activePanel = 'finance';
        }

        if (! in_array($this->actionDeskFilter, $this->allowedActionDeskFilters(), true)) {
            $this->actionDeskFilter = 'active';
        }

        if ($this->actionPriorityFilter !== '' && ! array_key_exists($this->actionPriorityFilter, $this->actionPriorityOptions())) {
            $this->actionPriorityFilter = '';
        }

        if ($this->actionFocusFilter !== '' && ! array_key_exists($this->actionFocusFilter, $this->actionFocusOptions())) {
            $this->actionFocusFilter = '';
        }

        if (! array_key_exists($this->actionSort, $this->actionSortOptions())) {
            $this->actionSort = 'priority';
        }

        if (! array_key_exists($this->actionListDensity, $this->actionListDensityOptions())) {
            $this->actionListDensity = 'detailed';
        }

        if (! array_key_exists($this->executiveRadarFocus, $this->executiveRadarFocusOptions())) {
            $this->executiveRadarFocus = 'auto';
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
            ->when($this->marketplaceFilter !== '', fn (Builder $query) => $query->where('marketplace', $this->marketplaceFilter))
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
    public function summary(): array
    {
        return $this->profitCenter()->summary($this->userId(), $this->filters());
    }

    #[Computed]
    public function marketplaceBreakdown(): array
    {
        return $this->profitCenter()->marketplaceBreakdown($this->userId(), $this->filters());
    }

    #[Computed]
    public function storeBreakdown(): array
    {
        return $this->profitCenter()->storeBreakdown($this->userId(), $this->filters());
    }

    #[Computed]
    public function dailyTrend(): array
    {
        return $this->profitCenter()->dailyTrend($this->userId(), $this->filters());
    }

    #[Computed]
    public function deductionBreakdown(): array
    {
        return $this->profitCenter()->deductionBreakdown($this->userId(), $this->filters());
    }

    #[Computed]
    public function campaignImpact(): array
    {
        return app(CampaignDecisionCenterQueryService::class)->profitCenterImpact($this->userId());
    }

    #[Computed]
    public function topLossOrders(): array
    {
        return $this->profitCenter()->topLossOrders($this->userId(), $this->filters());
    }

    #[Computed]
    public function costReadiness(): array
    {
        return $this->profitCenter()->costReadiness($this->userId(), $this->filters());
    }

    #[Computed]
    public function riskSignals(): array
    {
        return $this->profitCenter()->riskSignals($this->userId(), $this->filters());
    }

    #[Computed]
    public function riskGuidance(): array
    {
        return app(MarketplaceRiskSignalService::class)->guidanceForContext($this->userId(), 'profit');
    }

    #[Computed]
    public function priorityRecommendations(): array
    {
        return $this->profitCenter()->priorityRecommendations($this->userId(), $this->filters());
    }

    #[Computed]
    public function executiveCommandSummary(): array
    {
        return $this->profitCenter()->executiveCommandSummary($this->userId(), $this->filters());
    }

    #[Computed]
    public function executiveDecisionRadar(): array
    {
        $calculationHealth = $this->calculationHealth;
        $summary = $this->summary;
        $orderInsights = $this->orderDecisionInsights;
        $productInsights = $this->productReadinessInsights;
        $actionSummary = $this->actionSummary;
        $actionReport = $this->actionReport;
        $nextMove = $this->actionNextMoveRecommendations()['primary'] ?? null;
        $activeActionCount = (int) ($actionSummary['open'] ?? 0)
            + (int) ($actionSummary['in_progress'] ?? 0)
            + (int) ($actionSummary['snoozed'] ?? 0);
        $financeScore = (float) ($calculationHealth['score'] ?? 0);
        $orderRiskScore = max(
            (float) ($orderInsights['highest_score'] ?? 0),
            min(100, ((int) ($orderInsights['queue_count'] ?? 0)) * 8)
        );
        $productReady = (float) ($productInsights['ready_percent'] ?? 0);
        $productRiskScore = max(
            0,
            100 - $productReady,
            min(100, ((int) ($productInsights['risk_product_count'] ?? 0)) * 12)
        );
        $actionHealth = $actionReport['action_health'] ?? [];
        $actionHealthScore = (float) ($actionHealth['score'] ?? 0);
        $actionRiskScore = max(
            0,
            100 - $actionHealthScore,
            ((int) ($actionSummary['overdue'] ?? 0)) > 0 ? 85 : 0,
            ((int) ($actionSummary['high_priority'] ?? 0)) > 0 ? 65 : 0
        );

        $cards = [
            [
                'key' => 'finance',
                'label' => 'Finans',
                'title' => 'Kâr hesabı güveni',
                'value' => $financeScore,
                'format' => 'percent',
                'metric_label' => 'Güven',
                'risk_score' => max(0, 100 - $financeScore),
                'tone' => (string) ($calculationHealth['score_tone'] ?? 'slate'),
                'headline' => (string) ($calculationHealth['score_label'] ?? 'Veri bekliyor'),
                'description' => (string) ($calculationHealth['headline'] ?? 'Finans, snapshot, maliyet ve ödeme kapsamını kontrol edin.'),
                'action_type' => 'panel',
                'action_target' => 'finance',
                'action_label' => 'Finansı aç',
            ],
            [
                'key' => 'orders',
                'label' => 'Sipariş',
                'title' => 'Risk kuyruğu',
                'value' => (int) ($orderInsights['queue_count'] ?? 0),
                'format' => 'count',
                'metric_label' => 'Kayıt',
                'risk_score' => $orderRiskScore,
                'tone' => ((int) ($orderInsights['critical_order_count'] ?? 0)) > 0
                    ? 'rose'
                    : (((int) ($orderInsights['queue_count'] ?? 0)) > 0 ? 'amber' : 'emerald'),
                'headline' => (string) ($orderInsights['top_reason'] ?? 'Kritik neden yok'),
                'description' => (string) ($orderInsights['decision_hint'] ?? 'Görev listesindeki siparişleri kâr ve ödeme etkisine göre okuyun.'),
                'action_type' => 'panel',
                'action_target' => 'orders',
                'action_label' => 'Siparişleri aç',
            ],
            [
                'key' => 'products',
                'label' => 'Ürün',
                'title' => 'Maliyet hazırlığı',
                'value' => $productReady,
                'format' => 'percent',
                'metric_label' => 'Hazır',
                'risk_score' => $productRiskScore,
                'tone' => $productReady >= 90
                    ? (((int) ($productInsights['risk_product_count'] ?? 0)) > 0 ? 'amber' : 'emerald')
                    : ($productReady >= 70 ? 'amber' : 'rose'),
                'headline' => ((int) ($productInsights['risk_product_count'] ?? 0)) . ' riskli ürün',
                'description' => (string) ($productInsights['decision_hint'] ?? 'Ürün eşleşmesi, COGS ve ambalaj maliyetlerini netleştirin.'),
                'action_type' => 'panel',
                'action_target' => 'products',
                'action_label' => 'Ürünleri aç',
            ],
            [
                'key' => 'actions',
                'label' => 'Aksiyon',
                'title' => 'Operasyon baskısı',
                'value' => $activeActionCount,
                'format' => 'count',
                'metric_label' => 'Aktif',
                'risk_score' => $actionRiskScore,
                'tone' => match ((string) ($actionHealth['tone'] ?? 'default')) {
                    'success' => 'emerald',
                    'warning' => 'amber',
                    'danger' => 'rose',
                    default => 'slate',
                },
                'headline' => (string) ($actionHealth['label'] ?? 'Veri yok'),
                'description' => $nextMove
                    ? (string) ($nextMove['description'] ?? '')
                    : 'Aksiyon sağlığı için gecikme, sahiplik ve plan kalitesini izleyin.',
                'action_type' => 'focus',
                'action_target' => (string) ($nextMove['focus_key'] ?? 'high_priority'),
                'action_label' => (string) ($nextMove['title'] ?? 'Aksiyonlara odaklan'),
            ],
        ];

        $autoKey = (string) (collect($cards)->sortByDesc('risk_score')->first()['key'] ?? 'finance');
        $selectedKey = $this->executiveRadarFocus === 'auto' ? $autoKey : $this->executiveRadarFocus;
        $primary = collect($cards)->firstWhere('key', $selectedKey) ?? $cards[0];

        return [
            'auto_key' => $autoKey,
            'selected_key' => $selectedKey,
            'cards' => $cards,
            'primary' => $primary,
        ];
    }

    #[Computed]
    public function calculationHealth(): array
    {
        $summary = $this->summary;
        $readiness = $this->costReadiness;
        $settings = new MpSettingsService($this->userId());

        $totalOrders = (int) ($summary['total_orders'] ?? 0);
        $orderDenominator = max(1, $totalOrders);
        $totalLines = max(1, (int) ($readiness['total_lines'] ?? 0));

        $financeCoverage = $totalOrders > 0
            ? round(((int) ($summary['finance_ready_order_count'] ?? 0) / $orderDenominator) * 100, 1)
            : 0.0;
        $snapshotCoverage = $totalOrders > 0
            ? round((($totalOrders - (int) ($summary['snapshot_missing_order_count'] ?? 0)) / $orderDenominator) * 100, 1)
            : 0.0;
        $costReadiness = (float) ($readiness['ready_percent'] ?? 0);
        $materialVariance = $totalOrders > 0
            ? round(((int) ($summary['material_variance_order_count'] ?? 0) / $orderDenominator) * 100, 1)
            : 0.0;

        $score = $totalOrders > 0
            ? round(($financeCoverage * 0.30) + ($snapshotCoverage * 0.25) + ($costReadiness * 0.25) + ((100 - $materialVariance) * 0.20), 1)
            : 0.0;

        $costGapLines = (int) ($readiness['unmatched_lines'] ?? 0) + (int) ($readiness['missing_cost_lines'] ?? 0);
        $gapActions = $this->calculationGapActions($summary, $readiness);

        return [
            'score' => $score,
            'score_label' => $this->calculationHealthLabel($score, $totalOrders),
            'score_tone' => $this->calculationHealthTone($score, $totalOrders),
            'headline' => $totalOrders > 0
                ? 'Seçili aralıktaki kâr rakamları finans, snapshot, maliyet ve ödeme güveniyle birlikte izleniyor.'
                : 'Seçili aralıkta hesaplama sağlığı üretilecek sipariş bulunmuyor.',
            'gaps' => $gapActions !== []
                ? array_column($gapActions, 'short_label')
                : ['Kritik hesaplama açığı yok'],
            'gap_actions' => $gapActions,
            'cards' => [
                [
                    'label' => 'Finans kesinliği',
                    'value' => $financeCoverage,
                    'detail' => number_format((int) ($summary['finance_ready_order_count'] ?? 0), 0, ',', '.') . ' / ' . number_format($totalOrders, 0, ',', '.') . ' sipariş kesinleşti',
                    'percent' => $financeCoverage,
                    'tone' => $this->percentTone($financeCoverage),
                ],
                [
                    'label' => 'Snapshot kapsamı',
                    'value' => $snapshotCoverage,
                    'detail' => number_format((int) ($summary['snapshot_missing_order_count'] ?? 0), 0, ',', '.') . ' siparişte kâr kaydı eksik',
                    'percent' => $snapshotCoverage,
                    'tone' => $this->percentTone($snapshotCoverage),
                ],
                [
                    'label' => 'Maliyet güveni',
                    'value' => $costReadiness,
                    'detail' => number_format($costGapLines, 0, ',', '.') . ' / ' . number_format($totalLines, 0, ',', '.') . ' satır hazırlık bekliyor',
                    'percent' => $costReadiness,
                    'tone' => $this->percentTone($costReadiness),
                ],
                [
                    'label' => 'Ödeme baskısı',
                    'value' => $materialVariance,
                    'detail' => number_format((int) ($summary['material_variance_order_count'] ?? 0), 0, ',', '.') . ' yüksek fark üreten sipariş',
                    'percent' => $materialVariance,
                    'tone' => $this->percentTone($materialVariance, false),
                    'inverse' => true,
                ],
            ],
            'assumptions' => [
                [
                    'label' => 'KDV modu',
                    'value' => $settings->isKdvEnabled() ? 'Aktif' : 'Kapalı',
                    'tone' => $settings->isKdvEnabled() ? 'emerald' : 'amber',
                    'description' => $settings->isKdvEnabled()
                        ? 'Net KDV yükü kâr hesabına dahil edilir.'
                        : 'Kâr hesabı KDV yükünü dışarıda bırakır.',
                ],
                [
                    'label' => 'Stopaj modu',
                    'value' => $settings->isEstimatedWithholdingEnabled() ? 'Tahmin açık' : 'Sadece gerçek',
                    'tone' => $settings->isEstimatedWithholdingEnabled() ? 'amber' : 'slate',
                    'description' => $settings->isEstimatedWithholdingEnabled()
                        ? 'Gerçek stopaj yoksa KDV hariç matrahla teorik kesinti üretilir.'
                        : 'Yalnızca finans hareketindeki gerçek stopaj kullanılır.',
                ],
            ],
        ];
    }

    #[Computed]
    public function trackedActions(): array
    {
        return $this->profitActions()->actionItems(
            $this->userId(),
            $this->filters(),
            8,
            $this->actionDeskFilter,
            $this->actionListFilters(),
            $this->actionSort,
            $this->currentActionSignals(),
            $this->calculationHealth
        );
    }

    #[Computed]
    public function actionOwnerOptions(): array
    {
        return $this->profitActions()->ownerOptions($this->userId(), $this->filters());
    }

    #[Computed]
    public function actionSummary(): array
    {
        return $this->profitActions()->summary($this->userId(), $this->filters());
    }

    #[Computed]
    public function actionReport(): array
    {
        return $this->profitActions()->managerReport($this->userId(), $this->filters());
    }

    #[Computed]
    public function managerReportPreview(): array
    {
        $calculationHealth = $this->calculationHealth;
        $actionSummary = $this->actionSummary;
        $actionReport = $this->actionReport;
        $orderInsights = $this->orderDecisionInsights;
        $productInsights = $this->productReadinessInsights;
        $costReadiness = $this->costReadiness;
        $activeActionCount = (int) ($actionSummary['open'] ?? 0)
            + (int) ($actionSummary['in_progress'] ?? 0)
            + (int) ($actionSummary['snoozed'] ?? 0);
        $sheetNames = [
            'Rapor Indeksi',
            'Hesaplama Guveni',
            'Yonetici Ozeti',
            'Aksiyonlar',
            'Komuta Kuyrugu',
            'Aksiyon Dagilimi',
            'Aksiyon Sagligi',
            'Sorumlu Yuku',
            'Kapanis Kalitesi',
            'Haftalik Trend',
            'Yaklasan Hedefler',
            'Son Hareketler',
            'Siparis Risk Yogunlugu',
            'Siparis Karar Kuyrugu',
            'Urun Marj Maliyet',
            'Urun Performans',
        ];

        return [
            'file_label' => 'kar-merkezi-yonetici-raporu',
            'sheet_count' => count($sheetNames),
            'readiness' => $this->managerReportReadiness(
                $calculationHealth,
                $actionSummary,
                $actionReport,
                $orderInsights,
                $productInsights,
                $costReadiness
            ),
            'scope' => [
                [
                    'label' => 'Tarih',
                    'value' => trim($this->dateFrom . ' - ' . $this->dateTo, ' -') ?: 'Varsayılan aralık',
                ],
                [
                    'label' => 'Pazaryeri',
                    'value' => $this->marketplaceFilter !== '' ? $this->humanMarketplace($this->marketplaceFilter) : 'Tümü',
                ],
                [
                    'label' => 'Mağaza',
                    'value' => $this->selectedStoreLabel(),
                ],
                [
                    'label' => 'Firma',
                    'value' => $this->selectedLegalEntityLabel(),
                ],
                [
                    'label' => 'Aksiyon sekmesi',
                    'value' => $this->actionDeskFilterLabel($this->actionDeskFilter),
                ],
                [
                    'label' => 'Sıralama',
                    'value' => $this->actionSortOptions()[$this->actionSort] ?? $this->actionSort,
                ],
            ],
            'highlights' => [
                [
                    'label' => 'Güven skoru',
                    'value' => (float) ($calculationHealth['score'] ?? 0),
                    'format' => 'percent',
                    'detail' => (string) ($calculationHealth['score_label'] ?? ''),
                    'tone' => (string) ($calculationHealth['score_tone'] ?? 'slate'),
                ],
                [
                    'label' => 'Aksiyon sağlığı',
                    'value' => (float) ($actionReport['action_health']['score'] ?? 0),
                    'format' => 'percent',
                    'detail' => (string) ($actionReport['action_health']['label'] ?? 'Veri yok') . ' · ' . $activeActionCount . ' aktif iş',
                    'tone' => match ((string) ($actionReport['action_health']['tone'] ?? 'default')) {
                        'success' => 'emerald',
                        'warning' => 'amber',
                        'danger' => 'rose',
                        default => 'slate',
                    },
                ],
                [
                    'label' => 'Risk kuyruğu',
                    'value' => (int) ($orderInsights['queue_count'] ?? 0),
                    'format' => 'count',
                    'detail' => (float) ($orderInsights['risk_exposure'] ?? 0) . '% risk baskısı',
                    'tone' => (int) ($orderInsights['queue_count'] ?? 0) > 0 ? 'amber' : 'emerald',
                ],
                [
                    'label' => 'Maliyet hazırlığı',
                    'value' => (float) ($costReadiness['ready_percent'] ?? 0),
                    'format' => 'percent',
                    'detail' => (int) ($productInsights['risk_product_count'] ?? 0) . ' riskli ürün',
                    'tone' => (float) ($costReadiness['ready_percent'] ?? 0) >= 90 ? 'emerald' : 'amber',
                ],
            ],
            'sheets' => array_map(fn (string $sheetName) => [
                'name' => $sheetName,
                'label' => $this->reportSheetLabel($sheetName),
                'description' => $this->reportSheetDescription($sheetName),
            ], $sheetNames),
        ];
    }

    #[Computed]
    public function actionTimeline(): ?array
    {
        if (! $this->timelineActionId) {
            return null;
        }

        return $this->profitActions()->actionTimeline($this->userId(), $this->timelineActionId, $this->filters());
    }

    #[Computed]
    public function orderRiskFunnel(): array
    {
        return $this->profitCenter()->orderRiskFunnel($this->userId(), $this->filters());
    }

    #[Computed]
    public function orderDecisionQueue(): array
    {
        return $this->profitCenter()->orderDecisionQueue($this->userId(), $this->filters());
    }

    #[Computed]
    public function orderDecisionInsights(): array
    {
        return $this->profitCenter()->orderDecisionInsights($this->userId(), $this->filters());
    }

    #[Computed]
    public function productProfitability(): array
    {
        return $this->profitCenter()->productProfitability($this->userId(), $this->filters());
    }

    #[Computed]
    public function productReadinessInsights(): array
    {
        return $this->profitCenter()->productReadinessInsights($this->userId(), $this->filters());
    }

    #[Computed]
    public function hasConfiguredStores(): bool
    {
        return MarketplaceStore::query()
            ->where('user_id', $this->userId())
            ->exists();
    }

    public function setPanel(string $panel): void
    {
        if (in_array($panel, $this->allowedPanels(), true)) {
            $this->activePanel = $panel;
        }
    }

    public function setActionDeskFilter(string $filter): void
    {
        if (! in_array($filter, $this->allowedActionDeskFilters(), true)) {
            return;
        }

        $this->actionDeskFilter = $filter;
        $this->selectedActionIds = [];
        $this->timelineActionId = null;
        $this->refreshActionComputed();
    }

    public function updatedActionPriorityFilter(): void
    {
        if ($this->actionPriorityFilter !== '' && ! array_key_exists($this->actionPriorityFilter, $this->actionPriorityOptions())) {
            $this->actionPriorityFilter = '';
        }

        $this->actionListFilterChanged();
    }

    public function updatedActionOwnerFilter(): void
    {
        $this->actionOwnerFilter = trim($this->actionOwnerFilter);
        $this->actionListFilterChanged();
    }

    public function updatedActionFocusFilter(): void
    {
        if ($this->actionFocusFilter !== '' && ! array_key_exists($this->actionFocusFilter, $this->actionFocusOptions())) {
            $this->actionFocusFilter = '';
        }

        $this->actionListFilterChanged();
    }

    public function updatedActionSort(): void
    {
        if (! array_key_exists($this->actionSort, $this->actionSortOptions())) {
            $this->actionSort = 'priority';
        }

        $this->actionListFilterChanged();
    }

    public function updatedActionListDensity(): void
    {
        if (! array_key_exists($this->actionListDensity, $this->actionListDensityOptions())) {
            $this->actionListDensity = 'detailed';
        }
    }

    public function updatedExecutiveRadarFocus(): void
    {
        if (! array_key_exists($this->executiveRadarFocus, $this->executiveRadarFocusOptions())) {
            $this->executiveRadarFocus = 'auto';
        }
    }

    public function setExecutiveRadarFocus(string $focus): void
    {
        if (array_key_exists($focus, $this->executiveRadarFocusOptions())) {
            $this->executiveRadarFocus = $focus;
        }
    }

    public function applyActionQuickFocus(string $focus): void
    {
        if (! array_key_exists($focus, $this->actionFocusOptions())) {
            return;
        }

        $this->actionDeskFilter = $focus === 'plan_gap' ? 'resolved' : 'active';
        $this->actionFocusFilter = $focus;
        $this->actionSort = match ($focus) {
            'overdue', 'due_soon' => 'due_date',
            'plan_gap' => 'updated',
            default => 'priority',
        };
        $this->actionListFilterChanged();
    }

    public function resetActionListFilters(): void
    {
        $this->actionPriorityFilter = '';
        $this->actionOwnerFilter = '';
        $this->actionFocusFilter = '';
        $this->actionSort = 'priority';
        $this->actionListFilterChanged();
    }

    public function exportActionReport()
    {
        $fileName = 'kar-merkezi-yonetici-raporu-' . now()->format('Ymd-His') . '.xlsx';
        $path = storage_path('app/temp/' . $fileName);
        $actionSheets = $this->profitActions()->managerReportExportSheets(
            $this->userId(),
            $this->filters(),
            $this->actionDeskFilter,
            $this->actionListFilters(),
            $this->actionSort,
            $this->currentActionSignals(),
            $this->calculationHealth
        );
        $analyticsSheets = $this->profitCenter()->managerAnalyticsExportSheets($this->userId(), $this->filters());
        $reportSheets = array_merge(
            [[
                'name' => 'Hesaplama Guveni',
                'data' => $this->calculationHealthExportRows($this->calculationHealth),
            ]],
            $actionSheets,
            $analyticsSheets
        );

        app(ExcelService::class)->exportToXlsx(
            array_merge(
                [[
                    'name' => 'Rapor Indeksi',
                    'data' => $this->managerReportIndexRows($reportSheets),
                ]],
                $reportSheets
            ),
            $path
        );

        return response()->download($path, $fileName)->deleteFileAfterSend(true);
    }

    public function resetFilters(): void
    {
        $this->marketplaceFilter = '';
        $this->storeFilter = '';
        $this->legalEntityFilter = '';
        [$this->dateFrom, $this->dateTo] = $this->defaultDateRange();
    }

    public function trackRecommendation(string $key): void
    {
        $recommendation = collect($this->priorityRecommendations)
            ->first(fn (array $item) => ($item['key'] ?? null) === $key);

        if (! $recommendation) {
            session()->flash('warning', 'Aksiyona alınacak öneri bulunamadı.');

            return;
        }

        $this->profitActions()->trackRecommendation($this->userId(), $this->recommendationWithHealthContext($recommendation), $this->filters());
        $this->refreshActionComputed();

        session()->flash('success', 'Öneri aksiyon masasına eklendi.');
    }

    public function trackCalculationGap(string $key): void
    {
        $recommendation = collect($this->profitCenter()->priorityRecommendations($this->userId(), $this->filters(), 8))
            ->first(fn (array $item) => ($item['key'] ?? null) === $key);

        if (! $recommendation) {
            session()->flash('warning', 'Aksiyona alınacak hesaplama açığı bulunamadı.');

            return;
        }

        $this->profitActions()->trackRecommendation($this->userId(), $this->recommendationWithHealthContext($recommendation), $this->filters());
        $this->refreshActionComputed();

        session()->flash('success', 'Hesaplama açığı aksiyon masasına eklendi.');
    }

    public function saveActionNote(int $id): void
    {
        $updated = $this->profitActions()->updateNote(
            $this->userId(),
            $id,
            $this->actionNotes[$id] ?? null
        );

        if (! $updated) {
            session()->flash('warning', 'Not kaydedilecek aksiyon bulunamadı.');

            return;
        }

        $this->actionNotes[$id] = (string) ($updated->note ?? '');
        $this->refreshActionComputed();

        session()->flash('success', 'Aksiyon notu kaydedildi.');
    }

    public function saveActionMeta(int $id): void
    {
        $updated = $this->profitActions()->updateMeta(
            $this->userId(),
            $id,
            $this->actionPriorities[$id] ?? MpProfitActionItem::PRIORITY_MEDIUM,
            $this->actionDueDates[$id] ?? null,
            $this->actionOwners[$id] ?? null
        );

        if (! $updated) {
            session()->flash('warning', 'Plan bilgisi kaydedilecek aksiyon bulunamadı.');

            return;
        }

        $this->actionPriorities[$id] = (string) $updated->priority;
        $this->actionDueDates[$id] = $updated->due_date instanceof Carbon ? $updated->due_date->toDateString() : '';
        $this->actionOwners[$id] = (string) ($updated->owner_label ?? '');
        $this->refreshActionComputed();

        session()->flash('success', 'Aksiyon planı güncellendi.');
    }

    public function toggleActionStep(int $id, int $stepIndex): void
    {
        $updated = $this->profitActions()->togglePlaybookStep($this->userId(), $id, $stepIndex);

        if (! $updated) {
            session()->flash('warning', 'Plan adımı güncellenecek aksiyon bulunamadı.');

            return;
        }

        $this->refreshActionComputed();

        session()->flash('success', 'Plan adımı güncellendi.');
    }

    public function bulkUpdateActions(string $status): void
    {
        $selectedCount = $this->selectedActionCount();
        $updatedCount = $this->profitActions()->bulkSetStatus(
            $this->userId(),
            $this->selectedActionIds,
            $status,
            $this->filters()
        );

        if ($updatedCount < 1) {
            session()->flash(
                'warning',
                $status === MpProfitActionItem::STATUS_RESOLVED && $selectedCount > 0
                    ? 'Çözüldü olarak kapatmak için önce seçili aksiyonlara karar notu ekleyin.'
                    : 'Toplu işlem için aksiyon seçin.'
            );

            return;
        }

        $this->selectedActionIds = [];
        $this->refreshActionComputed();

        if ($status === MpProfitActionItem::STATUS_RESOLVED && $updatedCount < $selectedCount) {
            session()->flash('warning', $updatedCount . ' aksiyon kapatıldı; notu olmayan aksiyonlar açık bırakıldı.');

            return;
        }

        session()->flash('success', $updatedCount . ' aksiyon güncellendi.');
    }

    public function bulkApplyActionRecommendation(string $recommendation): void
    {
        $selectedCount = $this->selectedActionCount();

        if (! array_key_exists($recommendation, $this->bulkActionRecommendationOptions())) {
            return;
        }

        $updatedCount = $this->profitActions()->bulkApplyRecommendation(
            $this->userId(),
            $this->selectedActionIds,
            $recommendation,
            $this->filters()
        );

        if ($updatedCount < 1) {
            session()->flash(
                'warning',
                $selectedCount > 0
                    ? $this->bulkRecommendationEmptyMessage($recommendation)
                    : 'Akıllı öneri uygulamak için önce aksiyon seçin.'
            );

            return;
        }

        $this->selectedActionIds = [];
        $this->refreshActionComputed();

        session()->flash('success', $updatedCount . ' aksiyonda akıllı öneri uygulandı.');
    }

    public function openActionTimeline(int $id): void
    {
        $this->timelineActionId = $id;
        unset($this->actionTimeline);
    }

    public function closeActionTimeline(): void
    {
        $this->timelineActionId = null;
        unset($this->actionTimeline);
    }

    public function startAction(int $id): void
    {
        $this->updateActionStatus($id, MpProfitActionItem::STATUS_IN_PROGRESS, 'Aksiyon incelemeye alındı.');
    }

    public function snoozeAction(int $id): void
    {
        $this->updateActionStatus($id, MpProfitActionItem::STATUS_SNOOZED, 'Aksiyon 3 gün ertelendi.');
    }

    public function resolveAction(int $id): void
    {
        if (! $this->profitActions()->hasClosureEvidence($this->userId(), $id) && trim((string) ($this->actionNotes[$id] ?? '')) !== '') {
            $this->profitActions()->updateNote($this->userId(), $id, $this->actionNotes[$id]);
        }

        if (! $this->profitActions()->hasClosureEvidence($this->userId(), $id)) {
            session()->flash('warning', 'Aksiyonu kapatmadan önce karar notu ekleyin.');

            return;
        }

        $this->updateActionStatus($id, MpProfitActionItem::STATUS_RESOLVED, 'Aksiyon çözüldü olarak işaretlendi.');
    }

    public function reopenAction(int $id): void
    {
        $this->updateActionStatus($id, MpProfitActionItem::STATUS_OPEN, 'Aksiyon yeniden açıldı.');
    }

    /**
     * @param  array<string, mixed>  $signal
     */
    public function riskUrl(array $signal): string
    {
        $route = (string) ($signal['route'] ?? 'mp.finance');
        $query = is_array($signal['query'] ?? null) ? $signal['query'] : [];

        return route($route, array_filter(array_merge($this->routeFiltersFor($route), $query), fn ($value) => $value !== null && $value !== ''));
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function financeUrl(array $query = []): string
    {
        return $this->filteredRoute('mp.finance', $query);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function ordersUrl(array $query = []): string
    {
        return $this->filteredRoute('mp.orders', $query);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function productsUrl(array $query = []): string
    {
        return $this->filteredRoute('mp.products', $query);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function matchingUrl(array $query = []): string
    {
        return $this->filteredRoute('mp.matching', $query);
    }

    /**
     * @param  array<string, mixed>  $order
     */
    public function financeOrderUrl(array $order): string
    {
        return $this->financeUrl([
            'search' => $order['order_number'] ?? null,
            'sortField' => 'profit_value_metric',
            'sortDirection' => 'asc',
        ]);
    }

    /**
     * @param  array<string, mixed>  $product
     */
    public function productFocusUrl(array $product): string
    {
        if ((int) ($product['product_id'] ?? 0) <= 0) {
            return $this->matchingUrl([
                'statusFilter' => 'pending',
            ]);
        }

        $search = (string) (($product['stock_code'] ?? '') ?: ($product['barcode'] ?? '') ?: ($product['product_name'] ?? '') ?: '');

        return $this->productsUrl([
            'search' => $search,
            'edit' => (int) $product['product_id'],
            'tab' => 'pricing',
        ]);
    }

    /**
     * @param  array<string, mixed>  $action
     */
    public function actionItemUrl(array $action): string
    {
        $route = (string) ($action['route'] ?? 'mp.finance');
        $query = is_array($action['query'] ?? null) ? $action['query'] : [];

        return $this->filteredRoute($route, $query);
    }

    public function humanMarketplace(?string $marketplace): string
    {
        $normalized = MarketplaceProviderRegistry::normalize((string) $marketplace);

        return (string) (MarketplaceProviderRegistry::get($normalized)['label'] ?? Str::headline((string) $marketplace));
    }

    public function reconciliationStateLabel(?string $state): string
    {
        return match ($state) {
            'aligned' => 'Uyumlu',
            'minor' => 'İzle',
            'material' => 'Sorunlu Sipariş',
            'snapshot_missing' => 'Kayıt Eksik',
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
            'snapshot_missing' => 'warning',
            'waiting' => 'default',
            default => 'default',
        };
    }

    public function signalToneClass(?string $tone): string
    {
        return match ($tone) {
            'danger' => 'border-rose-200 bg-rose-50 text-rose-800',
            'warning' => 'border-amber-200 bg-amber-50 text-amber-800',
            'success' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
            default => 'border-slate-200 bg-slate-50 text-slate-700',
        };
    }

    public function badgeToneClass(?string $tone): string
    {
        return match ($tone) {
            'danger' => 'border-rose-200 bg-rose-50 text-rose-700',
            'warning' => 'border-amber-200 bg-amber-50 text-amber-700',
            'success' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            default => 'border-slate-200 bg-slate-50 text-slate-600',
        };
    }

    public function actionStatusLabel(?string $status): string
    {
        return match ($status) {
            MpProfitActionItem::STATUS_IN_PROGRESS => 'İnceleniyor',
            MpProfitActionItem::STATUS_SNOOZED => 'Ertelendi',
            MpProfitActionItem::STATUS_RESOLVED => 'Çözüldü',
            default => 'Yeni',
        };
    }

    public function actionStatusClass(?string $status): string
    {
        return match ($status) {
            MpProfitActionItem::STATUS_IN_PROGRESS => 'border-sky-200 bg-sky-50 text-sky-700',
            MpProfitActionItem::STATUS_SNOOZED => 'border-amber-200 bg-amber-50 text-amber-700',
            MpProfitActionItem::STATUS_RESOLVED => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            default => 'border-slate-200 bg-slate-50 text-slate-700',
        };
    }

    public function selectedActionCount(): int
    {
        return count(array_unique(array_filter(array_map('intval', $this->selectedActionIds))));
    }

    /**
     * @param  array<string, mixed>  $health
     * @return array<int, array<string, mixed>>
     */
    protected function calculationHealthExportRows(array $health): array
    {
        $rows = [
            [
                'Kategori' => 'Özet',
                'Başlık' => 'Güven skoru',
                'Değer' => (float) ($health['score'] ?? 0),
                'Durum' => (string) ($health['score_label'] ?? ''),
                'Detay' => (string) ($health['headline'] ?? ''),
                'Aksiyon' => '',
            ],
        ];

        foreach ((array) ($health['cards'] ?? []) as $card) {
            $rows[] = [
                'Kategori' => 'Güven kartı',
                'Başlık' => (string) ($card['label'] ?? ''),
                'Değer' => (float) ($card['value'] ?? 0),
                'Durum' => (string) ($card['tone'] ?? ''),
                'Detay' => (string) ($card['detail'] ?? ''),
                'Aksiyon' => '',
            ];
        }

        foreach ((array) ($health['gap_actions'] ?? []) as $gapAction) {
            $rows[] = [
                'Kategori' => 'Hesaplama açığı',
                'Başlık' => (string) ($gapAction['label'] ?? ''),
                'Değer' => (int) ($gapAction['value'] ?? 0),
                'Durum' => (string) ($gapAction['tone'] ?? ''),
                'Detay' => (string) ($gapAction['description'] ?? ''),
                'Aksiyon' => (string) ($gapAction['action_label'] ?? ''),
            ];
        }

        foreach ((array) ($health['assumptions'] ?? []) as $assumption) {
            $rows[] = [
                'Kategori' => 'Varsayım',
                'Başlık' => (string) ($assumption['label'] ?? ''),
                'Değer' => (string) ($assumption['value'] ?? ''),
                'Durum' => (string) ($assumption['tone'] ?? ''),
                'Detay' => (string) ($assumption['description'] ?? ''),
                'Aksiyon' => '',
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $calculationHealth
     * @param  array<string, mixed>  $actionSummary
     * @param  array<string, mixed>  $actionReport
     * @param  array<string, mixed>  $orderInsights
     * @param  array<string, mixed>  $productInsights
     * @param  array<string, mixed>  $costReadiness
     * @return array<string, mixed>
     */
    protected function managerReportReadiness(
        array $calculationHealth,
        array $actionSummary,
        array $actionReport,
        array $orderInsights,
        array $productInsights,
        array $costReadiness
    ): array {
        $warnings = [];
        $score = (float) ($calculationHealth['score'] ?? 0);
        $highPriority = (int) ($actionSummary['high_priority'] ?? 0);
        $overdue = (int) ($actionSummary['overdue'] ?? 0);
        $unowned = (int) ($actionReport['unowned'] ?? 0);
        $queueCount = (int) ($orderInsights['queue_count'] ?? 0);
        $costReadyPercent = (float) ($costReadiness['ready_percent'] ?? 0);
        $riskProductCount = (int) ($productInsights['risk_product_count'] ?? 0);
        $planScopeCount = (int) ($actionReport['closure_quality']['plan_scope_count'] ?? 0);
        $withPlanComplete = (int) ($actionReport['closure_quality']['with_plan_complete'] ?? 0);
        $incompletePlanClosures = max(0, $planScopeCount - $withPlanComplete);

        if ($score > 0 && $score < 75) {
            $warnings[] = [
                'label' => 'Hesaplama güveni',
                'value' => '%' . number_format($score, 1, ',', '.'),
                'tone' => $score < 50 ? 'danger' : 'warning',
                'description' => 'Finans, snapshot, maliyet veya ödeme kapsamı rapor kararını etkileyebilir.',
            ];
        }

        if ($overdue > 0) {
            $warnings[] = [
                'label' => 'Geciken aksiyon',
                'value' => $overdue,
                'tone' => 'danger',
                'description' => 'Hedef tarihi geçmiş aksiyonlar raporda ayrıca takip edilmeli.',
            ];
        }

        if ($highPriority > 0) {
            $warnings[] = [
                'label' => 'Yüksek öncelik',
                'value' => $highPriority,
                'tone' => 'warning',
                'description' => 'Açık yüksek/kritik öncelikli işler yönetici raporunun ilk okuma alanıdır.',
            ];
        }

        if ($unowned > 0) {
            $warnings[] = [
                'label' => 'Sahipsiz iş',
                'value' => $unowned,
                'tone' => 'warning',
                'description' => 'Sorumlusu olmayan aksiyonlar kapanış takibini zayıflatır.',
            ];
        }

        if ($incompletePlanClosures > 0) {
            $warnings[] = [
                'label' => 'Plan eksik kapanış',
                'value' => $incompletePlanClosures . '/' . $planScopeCount,
                'tone' => 'warning',
                'description' => 'Bazı aksiyonlar playbook adımları tamamlanmadan kapatılmış; kapanış notlarını ve sıradaki adımı kontrol edin.',
            ];
        }

        if ($queueCount > 0) {
            $warnings[] = [
                'label' => 'Risk kuyruğu',
                'value' => $queueCount,
                'tone' => 'warning',
                'description' => 'Öncelikli sipariş karar kuyruğunda kontrol edilmesi gereken kayıt var.',
            ];
        }

        if ($costReadyPercent < 90) {
            $warnings[] = [
                'label' => 'Maliyet hazırlığı',
                'value' => '%' . number_format($costReadyPercent, 1, ',', '.'),
                'tone' => 'warning',
                'description' => 'Ürün eşleşmesi veya maliyet eksikleri kârlılık analizini etkileyebilir.',
            ];
        }

        if ($riskProductCount > 0) {
            $warnings[] = [
                'label' => 'Riskli ürün',
                'value' => $riskProductCount,
                'tone' => 'info',
                'description' => 'Ürün marj veya maliyet hazırlığı bölümünde detay kontrol önerilir.',
            ];
        }

        $hasDanger = collect($warnings)->contains(fn (array $warning) => ($warning['tone'] ?? '') === 'danger');

        if ($warnings === []) {
            return [
                'label' => 'İndirmeye hazır',
                'tone' => 'success',
                'score' => 100,
                'warning_count' => 0,
                'description' => 'Seçili kapsam için rapor indirmeden önce kritik uyarı bulunmuyor.',
                'warnings' => [],
            ];
        }

        $readinessScore = max(0, 100 - (count($warnings) * 10) - ($hasDanger ? 15 : 0));

        return [
            'label' => $hasDanger ? 'Önce kritik kontrol' : 'Kontrollü indirilebilir',
            'tone' => $hasDanger ? 'danger' : 'warning',
            'score' => $readinessScore,
            'warning_count' => count($warnings),
            'description' => $hasDanger
                ? 'Rapor indirilebilir; ancak gecikmiş veya düşük güvenli başlıkları önce okuyun.'
                : 'Rapor indirilebilir; uyarı başlıklarını yönetici özetinde ayrıca kontrol edin.',
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<int, array{name: string, data: array<int, array<string, mixed>>}>  $sheets
     * @return array<int, array<string, mixed>>
     */
    protected function managerReportIndexRows(array $sheets): array
    {
        $calculationHealth = $this->calculationHealth;
        $actionSummary = $this->actionSummary;
        $actionReport = $this->actionReport;
        $orderInsights = $this->orderDecisionInsights;
        $productInsights = $this->productReadinessInsights;
        $costReadiness = $this->costReadiness;
        $readiness = $this->managerReportReadiness(
            $calculationHealth,
            $actionSummary,
            $actionReport,
            $orderInsights,
            $productInsights,
            $costReadiness
        );
        $activeActionCount = (int) ($actionSummary['open'] ?? 0)
            + (int) ($actionSummary['in_progress'] ?? 0)
            + (int) ($actionSummary['snoozed'] ?? 0);

        $rows = [
            [
                'Kategori' => 'Rapor bilgisi',
                'Alan' => 'Rapor',
                'Değer' => 'Kar Merkezi Yönetici Raporu',
                'Açıklama' => 'Aksiyon masası, hesaplama güveni, sipariş riski ve ürün kârlılığı tek dosyada.',
            ],
            [
                'Kategori' => 'Rapor bilgisi',
                'Alan' => 'Oluşturma zamanı',
                'Değer' => now()->format('d.m.Y H:i'),
                'Açıklama' => 'Raporun üretildiği tarih ve saat.',
            ],
            [
                'Kategori' => 'Rapor hazırlığı',
                'Alan' => 'Durum',
                'Değer' => (string) ($readiness['label'] ?? ''),
                'Açıklama' => (string) ($readiness['description'] ?? ''),
            ],
            [
                'Kategori' => 'Rapor hazırlığı',
                'Alan' => 'Kontrol sayısı',
                'Değer' => (int) ($readiness['warning_count'] ?? 0),
                'Açıklama' => 'İndirme öncesi dikkat edilmesi gereken başlık adedi.',
            ],
            [
                'Kategori' => 'Kritik özet',
                'Alan' => 'Güven skoru',
                'Değer' => (float) ($calculationHealth['score'] ?? 0),
                'Açıklama' => (string) ($calculationHealth['score_label'] ?? ''),
            ],
            [
                'Kategori' => 'Kritik özet',
                'Alan' => 'Açık aksiyon',
                'Değer' => $activeActionCount,
                'Açıklama' => 'Yeni, incelenen ve ertelenen toplam aksiyon.',
            ],
            [
                'Kategori' => 'Kritik özet',
                'Alan' => 'Yüksek öncelik',
                'Değer' => (int) ($actionSummary['high_priority'] ?? 0),
                'Açıklama' => 'Açık yüksek/kritik öncelikli aksiyon.',
            ],
            [
                'Kategori' => 'Kritik özet',
                'Alan' => 'Risk kuyruğu',
                'Değer' => (int) ($orderInsights['queue_count'] ?? 0),
                'Açıklama' => 'Öncelikli sipariş karar kuyruğu.',
            ],
            [
                'Kategori' => 'Kritik özet',
                'Alan' => 'Sipariş risk baskısı',
                'Değer' => (float) ($orderInsights['risk_exposure'] ?? 0),
                'Açıklama' => (string) ($orderInsights['top_reason'] ?? 'Kritik neden yok'),
            ],
            [
                'Kategori' => 'Kritik özet',
                'Alan' => 'Riskli ürün',
                'Değer' => (int) ($productInsights['risk_product_count'] ?? 0),
                'Açıklama' => 'Maliyet, eşleşme veya negatif kâr baskısı olan ürünler.',
            ],
            [
                'Kategori' => 'Kritik özet',
                'Alan' => 'Maliyet hazırlığı',
                'Değer' => (float) ($costReadiness['ready_percent'] ?? 0),
                'Açıklama' => 'Ürün eşleşmesi ve maliyet verisi hazır olma oranı.',
            ],
            [
                'Kategori' => 'Filtre',
                'Alan' => 'Tarih aralığı',
                'Değer' => trim($this->dateFrom . ' - ' . $this->dateTo, ' -'),
                'Açıklama' => 'Sipariş tarihi filtresi.',
            ],
            [
                'Kategori' => 'Filtre',
                'Alan' => 'Pazaryeri',
                'Değer' => $this->marketplaceFilter !== '' ? $this->humanMarketplace($this->marketplaceFilter) : 'Tümü',
                'Açıklama' => 'Pazaryeri kapsamı.',
            ],
            [
                'Kategori' => 'Filtre',
                'Alan' => 'Mağaza',
                'Değer' => $this->selectedStoreLabel(),
                'Açıklama' => 'Mağaza kapsamı.',
            ],
            [
                'Kategori' => 'Filtre',
                'Alan' => 'Firma',
                'Değer' => $this->selectedLegalEntityLabel(),
                'Açıklama' => 'Yasal firma kapsamı.',
            ],
            [
                'Kategori' => 'Arayüz',
                'Alan' => 'Aktif panel',
                'Değer' => $this->activePanelLabel($this->activePanel),
                'Açıklama' => 'Rapor oluşturulurken açık olan Kar Merkezi paneli.',
            ],
            [
                'Kategori' => 'Aksiyon filtresi',
                'Alan' => 'Aksiyon sekmesi',
                'Değer' => $this->actionDeskFilterLabel($this->actionDeskFilter),
                'Açıklama' => 'Aksiyon masası görünümü.',
            ],
            [
                'Kategori' => 'Aksiyon filtresi',
                'Alan' => 'Öncelik',
                'Değer' => $this->actionPriorityFilter !== ''
                    ? ($this->actionPriorityOptions()[$this->actionPriorityFilter] ?? $this->actionPriorityFilter)
                    : 'Tümü',
                'Açıklama' => 'Aksiyon öncelik filtresi.',
            ],
            [
                'Kategori' => 'Aksiyon filtresi',
                'Alan' => 'Sorumlu',
                'Değer' => $this->actionOwnerFilterLabel(),
                'Açıklama' => 'Aksiyon sorumlu filtresi.',
            ],
            [
                'Kategori' => 'Aksiyon filtresi',
                'Alan' => 'Odak',
                'Değer' => $this->actionFocusFilter !== ''
                    ? ($this->actionFocusOptions()[$this->actionFocusFilter] ?? $this->actionFocusFilter)
                    : 'Tümü',
                'Açıklama' => 'Aksiyon odak filtresi.',
            ],
            [
                'Kategori' => 'Aksiyon filtresi',
                'Alan' => 'Sıralama',
                'Değer' => $this->actionSortOptions()[$this->actionSort] ?? $this->actionSort,
                'Açıklama' => 'Aksiyon listesi sıralaması.',
            ],
        ];

        foreach ((array) ($readiness['warnings'] ?? []) as $warning) {
            $rows[] = [
                'Kategori' => 'İndirme öncesi kontrol',
                'Alan' => (string) ($warning['label'] ?? ''),
                'Değer' => $warning['value'] ?? '',
                'Açıklama' => (string) ($warning['description'] ?? ''),
            ];
        }

        if (($readiness['warnings'] ?? []) === []) {
            $rows[] = [
                'Kategori' => 'İndirme öncesi kontrol',
                'Alan' => 'Kritik uyarı yok',
                'Değer' => 'Hazır',
                'Açıklama' => 'Seçili kapsam için rapor indirmeden önce öne çıkan kritik uyarı bulunmuyor.',
            ];
        }

        foreach ($sheets as $sheet) {
            $name = (string) ($sheet['name'] ?? '');

            $rows[] = [
                'Kategori' => 'Sayfa',
                'Alan' => $name,
                'Değer' => count((array) ($sheet['data'] ?? [])),
                'Açıklama' => $this->reportSheetDescription($name),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $trackedActions
     * @return array<string, mixed>
     */
    public function selectedActionClosureReadiness(array $trackedActions): array
    {
        $selectedIds = array_values(array_unique(array_filter(array_map('intval', $this->selectedActionIds))));
        $selectedCount = count($selectedIds);

        if ($selectedCount < 1) {
            return [
                'selected_count' => 0,
                'ready_count' => 0,
                'missing_note_count' => 0,
                'tone' => 'default',
                'label' => 'Aksiyon seçimi bekleniyor',
                'description' => 'Çözüldü için karar notu gerekir.',
            ];
        }

        $actionsById = collect($trackedActions)->keyBy('id');
        $missingNoteCount = collect($selectedIds)
            ->filter(function (int $id) use ($actionsById) {
                $action = $actionsById->get($id);
                $note = trim((string) ($this->actionNotes[$id] ?? ($action['note'] ?? '')));

                return $note === '';
            })
            ->count();
        $incompletePlanCount = collect($selectedIds)
            ->filter(function (int $id) use ($actionsById) {
                $action = $actionsById->get($id);
                $progress = is_array($action['playbook_progress'] ?? null) ? $action['playbook_progress'] : [];

                return (int) ($progress['total_steps'] ?? 0) > 0
                    && ! (bool) ($progress['is_complete'] ?? false);
            })
            ->count();
        $readyCount = max(0, $selectedCount - $missingNoteCount);
        $description = match (true) {
            $missingNoteCount > 0 && $incompletePlanCount > 0 => $missingNoteCount . ' aksiyonun karar notu eksik; ' . $incompletePlanCount . ' plan tamamlanmadan kapanacak.',
            $missingNoteCount > 0 => $missingNoteCount . ' aksiyonun karar notu eksik.',
            $incompletePlanCount > 0 => $incompletePlanCount . ' plan tamamlanmadan kapanacak.',
            default => 'Seçili aksiyonlar kapanış notuyla kapatılabilir.',
        };

        return [
            'selected_count' => $selectedCount,
            'ready_count' => $readyCount,
            'missing_note_count' => $missingNoteCount,
            'incomplete_plan_count' => $incompletePlanCount,
            'tone' => $missingNoteCount > 0 ? ($readyCount > 0 ? 'warning' : 'danger') : ($incompletePlanCount > 0 ? 'warning' : 'success'),
            'label' => $readyCount . ' kapanışa hazır',
            'description' => $description,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $trackedActions
     * @return array<int, array<string, mixed>>
     */
    public function actionCommandQueue(array $trackedActions): array
    {
        return $this->profitActions()->commandQueue($trackedActions, 3);
    }

    /**
     * @return array<string, string>
     */
    public function actionPriorityOptions(): array
    {
        return [
            MpProfitActionItem::PRIORITY_LOW => 'Düşük',
            MpProfitActionItem::PRIORITY_MEDIUM => 'Normal',
            MpProfitActionItem::PRIORITY_HIGH => 'Yüksek',
            MpProfitActionItem::PRIORITY_CRITICAL => 'Kritik',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function actionFocusOptions(): array
    {
        return [
            'overdue' => 'Gecikenler',
            'due_soon' => '3 gün içinde',
            'unowned' => 'Sahipsiz işler',
            'high_priority' => 'Yüksek öncelik',
            'plan_gap' => 'Plan açığı',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actionQuickFocusControls(): array
    {
        $summary = $this->actionSummary;
        $report = $this->actionReport;
        $planGapDriver = collect((array) ($report['action_health']['drivers'] ?? []))->firstWhere('key', 'plan_gap') ?? [];
        $planGapCount = (int) ($planGapDriver['count'] ?? 0);

        return [
            [
                'key' => 'overdue',
                'label' => 'Gecikenler',
                'count' => (int) ($summary['overdue'] ?? 0),
                'tone' => (int) ($summary['overdue'] ?? 0) > 0 ? 'danger' : 'default',
                'description' => 'Hedef tarihi geçmiş açık aksiyonlar.',
                'active' => $this->actionFocusFilter === 'overdue',
            ],
            [
                'key' => 'high_priority',
                'label' => 'Yüksek öncelik',
                'count' => (int) ($summary['high_priority'] ?? 0),
                'tone' => (int) ($summary['high_priority'] ?? 0) > 0 ? 'warning' : 'default',
                'description' => 'Yüksek veya kritik öncelikli işler.',
                'active' => $this->actionFocusFilter === 'high_priority',
            ],
            [
                'key' => 'due_soon',
                'label' => '3 gün içinde',
                'count' => (int) ($summary['due_soon'] ?? 0),
                'tone' => (int) ($summary['due_soon'] ?? 0) > 0 ? 'info' : 'default',
                'description' => 'Yaklaşan hedef tarihli işler.',
                'active' => $this->actionFocusFilter === 'due_soon',
            ],
            [
                'key' => 'unowned',
                'label' => 'Sahipsiz işler',
                'count' => (int) ($report['unowned'] ?? 0),
                'tone' => (int) ($report['unowned'] ?? 0) > 0 ? 'warning' : 'default',
                'description' => 'Sorumlusu atanmamış aksiyonlar.',
                'active' => $this->actionFocusFilter === 'unowned',
            ],
            [
                'key' => 'plan_gap',
                'label' => 'Plan açığı',
                'count' => $planGapCount,
                'tone' => $planGapCount > 0 ? 'warning' : 'default',
                'description' => 'Planı tamamlanmadan kapatılmış işler.',
                'active' => $this->actionFocusFilter === 'plan_gap',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function actionNextMoveRecommendations(): array
    {
        $nextMoves = $this->actionReport['action_health']['next_moves'] ?? [];

        return [
            'primary' => is_array($nextMoves['primary'] ?? null) ? $nextMoves['primary'] : null,
            'alternatives' => is_array($nextMoves['alternatives'] ?? null) ? $nextMoves['alternatives'] : [],
            'empty_label' => (string) ($nextMoves['empty_label'] ?? 'Aksiyon sağlığı temiz'),
            'empty_description' => (string) ($nextMoves['empty_description'] ?? 'Seçili kapsamda acil toplu hamle gerektiren risk sürücüsü görünmüyor.'),
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function bulkActionRecommendationOptions(): array
    {
        return [
            'assign_default_owner' => [
                'label' => 'Sorumlu öner',
                'description' => 'Sahipsiz seçili işleri önerilen ekibe ata.',
                'tone' => 'warning',
            ],
            'refresh_due_dates' => [
                'label' => 'Hedef yenile',
                'description' => 'Geciken veya hedefsiz seçili işlere yeni hedef ver.',
                'tone' => 'info',
            ],
            'reopen_plan_gaps' => [
                'label' => 'Plan için aç',
                'description' => 'Planı eksik kapanan seçili işleri yeniden aç.',
                'tone' => 'success',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function actionSortOptions(): array
    {
        return [
            'priority' => 'Öncelik ve gecikme',
            'due_date' => 'Hedef tarihe göre',
            'impact' => 'Finansal etkiye göre',
            'updated' => 'Son güncellenen',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function actionListDensityOptions(): array
    {
        return [
            'detailed' => 'Detaylı',
            'compact' => 'Kompakt',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function executiveRadarFocusOptions(): array
    {
        $this->deductionColorMap = [
            'amber' => 'bg-amber-500', // Komisyon
            'sky' => 'bg-sky-500', // Kargo
            'slate' => 'bg-slate-500', // Hizmet bedeli
            'indigo' => 'bg-indigo-500', // Reklam
            'red' => 'bg-red-500', // Ceza
            'violet' => 'bg-violet-500', // Erken ödeme
            'orange' => 'bg-orange-500', // İndirim
            'zinc' => 'bg-zinc-500', // Diğer
            'rose' => 'bg-rose-500', // Stopaj
        ];

        return [
            'auto' => 'Otomatik',
            'finance' => 'Finans',
            'orders' => 'Sipariş',
            'products' => 'Ürün',
            'actions' => 'Aksiyon',
        ];
    }

    public function actionFilterCount(): int
    {
        return count(array_filter([
            $this->actionPriorityFilter,
            $this->actionOwnerFilter,
            $this->actionFocusFilter,
        ], fn (string $value) => trim($value) !== ''));
    }

    protected function bulkRecommendationEmptyMessage(string $recommendation): string
    {
        return match ($recommendation) {
            'assign_default_owner' => 'Seçili aksiyonlarda önerilen sorumlu atanabilecek sahipsiz iş bulunamadı.',
            'refresh_due_dates' => 'Seçili aksiyonlarda hedef tarihi yenilenecek geciken veya hedefsiz iş bulunamadı.',
            'reopen_plan_gaps' => 'Seçili aksiyonlarda plan açığı nedeniyle yeniden açılacak kapanış bulunamadı.',
            default => 'Seçili aksiyonlara uygulanacak akıllı öneri bulunamadı.',
        };
    }

    protected function calculationHealthLabel(float $score, int $totalOrders): string
    {
        if ($totalOrders < 1) {
            return 'Veri bekliyor';
        }

        return match (true) {
            $score >= 85 => 'Yüksek güven',
            $score >= 70 => 'İzlenebilir',
            $score >= 50 => 'Kontrollü risk',
            default => 'Düşük güven',
        };
    }

    protected function calculationHealthTone(float $score, int $totalOrders): string
    {
        if ($totalOrders < 1) {
            return 'slate';
        }

        return $this->percentTone($score);
    }

    protected function percentTone(float $percent, bool $higherIsBetter = true): string
    {
        $score = $higherIsBetter ? $percent : 100 - $percent;

        return match (true) {
            $score >= 80 => 'emerald',
            $score >= 60 => 'amber',
            default => 'rose',
        };
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $readiness
     * @return array<int, array<string, mixed>>
     */
    protected function calculationGapActions(array $summary, array $readiness): array
    {
        $costGapLines = (int) ($readiness['unmatched_lines'] ?? 0) + (int) ($readiness['missing_cost_lines'] ?? 0);

        return array_values(array_filter([
            ((int) ($summary['finance_waiting_order_count'] ?? 0)) > 0 ? [
                'key' => 'finance_waiting',
                'label' => 'Finans bekleyenleri netleştir',
                'short_label' => number_format((int) $summary['finance_waiting_order_count'], 0, ',', '.') . ' finans bekliyor',
                'value' => (int) $summary['finance_waiting_order_count'],
                'tone' => 'amber',
                'description' => 'Ödeme/finans hareketi gelmeyen siparişler net alacak kesinliğini düşürür.',
                'action_label' => 'Finansı aç',
                'route' => 'mp.finance',
                'query' => ['financialStateFilter' => 'waiting'],
            ] : null,
            ((int) ($summary['snapshot_missing_order_count'] ?? 0)) > 0 ? [
                'key' => 'snapshot_missing',
                'label' => 'Kâr kaydı eksiklerini üret',
                'short_label' => number_format((int) $summary['snapshot_missing_order_count'], 0, ',', '.') . ' snapshot eksik',
                'value' => (int) $summary['snapshot_missing_order_count'],
                'tone' => 'amber',
                'description' => 'Snapshot eksikleri ödeme ve kâr güvenini zayıflatır.',
                'action_label' => 'Eksikleri aç',
                'route' => 'mp.finance',
                'query' => ['deltaStateFilter' => 'snapshot_missing'],
            ] : null,
            $costGapLines > 0 ? [
                'key' => 'missing_cost',
                'label' => 'Maliyet hazırlığını tamamla',
                'short_label' => number_format($costGapLines, 0, ',', '.') . ' maliyet satırı eksik',
                'value' => $costGapLines,
                'tone' => 'amber',
                'description' => 'Ürün eşleşmesi, COGS veya ambalaj maliyeti eksikleri kârı tahmini bırakır.',
                'action_label' => 'Maliyeti aç',
                'route' => 'mp.products',
                'query' => ['filterCostDefined' => 'no', 'sortField' => 'cogs', 'sortDirection' => 'asc'],
            ] : null,
            ((int) ($summary['material_variance_order_count'] ?? 0)) > 0 ? [
                'key' => 'material_variance',
                'label' => 'Ödeme farkını kapat',
                'short_label' => number_format((int) $summary['material_variance_order_count'], 0, ',', '.') . ' ödeme farkı',
                'value' => (int) $summary['material_variance_order_count'],
                'tone' => 'rose',
                'description' => 'Tahmini snapshot ve kesin finans sonucu arasındaki yüksek farkları inceleyin.',
                'action_label' => 'Farkları aç',
                'route' => 'mp.finance',
                'query' => ['deltaStateFilter' => 'material', 'sortField' => 'reconciliation_delta_abs_metric', 'sortDirection' => 'desc'],
            ] : null,
        ]));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function currentActionSignals(): array
    {
        return collect($this->profitCenter()->priorityRecommendations($this->userId(), $this->filters(), 8))
            ->keyBy(fn (array $recommendation) => (string) ($recommendation['key'] ?? ''))
            ->filter(fn ($recommendation, string $key) => $key !== '')
            ->all();
    }

    /**
     * @param  array<string, mixed>  $recommendation
     * @return array<string, mixed>
     */
    protected function recommendationWithHealthContext(array $recommendation): array
    {
        $health = $this->calculationHealth;
        $recommendation['calculation_health_at_tracking'] = [
            'score' => (float) ($health['score'] ?? 0),
            'score_label' => (string) ($health['score_label'] ?? ''),
            'gaps' => $health['gaps'] ?? [],
        ];

        return $recommendation;
    }

    public function render()
    {
        $trackedActions = $this->trackedActions;
        $this->syncActionControls($trackedActions);

        return view('livewire.marketplace-profit-center', [
            'summary' => $this->summary,
            'marketplaceOptions' => $this->marketplaceOptions,
            'storeOptions' => $this->storeOptions,
            'legalEntities' => $this->legalEntities,
            'marketplaceBreakdown' => $this->marketplaceBreakdown,
            'storeBreakdown' => $this->storeBreakdown,
            'dailyTrend' => $this->dailyTrend,
            'deductionBreakdown' => $this->deductionBreakdown,
            'campaignImpact' => $this->campaignImpact,
            'topLossOrders' => $this->topLossOrders,
            'costReadiness' => $this->costReadiness,
            'riskSignals' => $this->riskSignals,
            'priorityRecommendations' => $this->priorityRecommendations,
            'executiveCommandSummary' => $this->executiveCommandSummary,
            'executiveDecisionRadar' => $this->executiveDecisionRadar,
            'executiveRadarFocus' => $this->executiveRadarFocus,
            'executiveRadarFocusOptions' => $this->executiveRadarFocusOptions(),
            'calculationHealth' => $this->calculationHealth,
            'trackedActions' => $trackedActions,
            'actionSummary' => $this->actionSummary,
            'actionReport' => $this->actionReport,
            'managerReportPreview' => $this->managerReportPreview,
            'actionTimeline' => $this->actionTimeline,
            'orderRiskFunnel' => $this->orderRiskFunnel,
            'orderDecisionQueue' => $this->orderDecisionQueue,
            'orderDecisionInsights' => $this->orderDecisionInsights,
            'productProfitability' => $this->productProfitability,
            'productReadinessInsights' => $this->productReadinessInsights,
            'hasConfiguredStores' => $this->hasConfiguredStores,
            'activeFilters' => $this->activeFilters(),
            'activePanel' => $this->activePanel,
            'actionDeskFilter' => $this->actionDeskFilter,
            'selectedActionCount' => $this->selectedActionCount(),
            'selectedActionClosureReadiness' => $this->selectedActionClosureReadiness($trackedActions),
            'actionCommandQueue' => $this->actionCommandQueue($trackedActions),
            'actionQuickFocusControls' => $this->actionQuickFocusControls(),
            'actionNextMoveRecommendations' => $this->actionNextMoveRecommendations(),
            'bulkActionRecommendationOptions' => $this->bulkActionRecommendationOptions(),
            'actionPriorityOptions' => $this->actionPriorityOptions(),
            'actionOwnerOptions' => $this->actionOwnerOptions,
            'actionFocusOptions' => $this->actionFocusOptions(),
            'actionSortOptions' => $this->actionSortOptions(),
            'actionListDensityOptions' => $this->actionListDensityOptions(),
            'actionListDensity' => $this->actionListDensity,
            'actionFilterCount' => $this->actionFilterCount(),
            'timelineActionId' => $this->timelineActionId,
        ])->layout('layouts.app', ['title' => 'Kâr Kokpiti']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function filters(): array
    {
        return [
            'marketplace' => $this->marketplaceFilter,
            'store_id' => $this->storeFilter,
            'legal_entity_id' => $this->legalEntityFilter,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function actionListFilters(): array
    {
        return [
            'priority' => $this->actionPriorityFilter,
            'owner' => $this->actionOwnerFilter,
            'focus' => $this->actionFocusFilter,
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function activeFilters(): array
    {
        return array_values(array_filter([
            $this->marketplaceFilter !== '' ? 'Pazaryeri: ' . $this->humanMarketplace($this->marketplaceFilter) : null,
            $this->storeFilter !== '' ? 'Mağaza seçili' : null,
            $this->legalEntityFilter !== '' ? 'Firma seçili' : null,
            $this->dateFrom !== '' ? 'Başlangıç: ' . $this->dateFrom : null,
            $this->dateTo !== '' ? 'Bitiş: ' . $this->dateTo : null,
        ]));
    }

    /**
     * @return array<int, string>
     */
    protected function allowedPanels(): array
    {
        return ['finance', 'orders', 'products'];
    }

    /**
     * @return array<int, string>
     */
    protected function allowedActionDeskFilters(): array
    {
        return ['active', 'resolved', 'all'];
    }

    protected function activePanelLabel(string $panel): string
    {
        return match ($panel) {
            'orders' => 'Siparişler',
            'products' => 'Ürünler',
            default => 'Finans',
        };
    }

    protected function actionDeskFilterLabel(string $filter): string
    {
        return match ($filter) {
            'resolved' => 'Çözülen',
            'all' => 'Tümü',
            default => 'Aktif',
        };
    }

    protected function actionOwnerFilterLabel(): string
    {
        if ($this->actionOwnerFilter === '__unowned') {
            return 'Sahipsiz işler';
        }

        return $this->actionOwnerFilter !== '' ? $this->actionOwnerFilter : 'Tümü';
    }

    protected function selectedStoreLabel(): string
    {
        if ($this->storeFilter === '') {
            return 'Tümü';
        }

        return (string) (MarketplaceStore::query()
            ->where('user_id', $this->userId())
            ->whereKey((int) $this->storeFilter)
            ->value('store_name') ?: 'Seçili mağaza');
    }

    protected function selectedLegalEntityLabel(): string
    {
        if ($this->legalEntityFilter === '') {
            return 'Tümü';
        }

        return (string) (LegalEntity::query()
            ->where('user_id', $this->userId())
            ->whereKey((int) $this->legalEntityFilter)
            ->value('name') ?: 'Seçili firma');
    }

    protected function reportSheetLabel(string $sheetName): string
    {
        return match ($sheetName) {
            'Rapor Indeksi' => 'Rapor indeksi',
            'Hesaplama Guveni' => 'Hesaplama güveni',
            'Yonetici Ozeti' => 'Yönetici özeti',
            'Aksiyonlar' => 'Aksiyonlar',
            'Komuta Kuyrugu' => 'Komuta kuyruğu',
            'Aksiyon Dagilimi' => 'Aksiyon dağılımı',
            'Aksiyon Sagligi' => 'Aksiyon sağlığı',
            'Sorumlu Yuku' => 'Sorumlu yükü',
            'Kapanis Kalitesi' => 'Kapanış kalitesi',
            'Haftalik Trend' => 'Haftalık trend',
            'Yaklasan Hedefler' => 'Yaklaşan hedefler',
            'Son Hareketler' => 'Son hareketler',
            'Siparis Risk Yogunlugu' => 'Sipariş risk yoğunluğu',
            'Siparis Karar Kuyrugu' => 'Sipariş karar kuyruğu',
            'Urun Marj Maliyet' => 'Ürün marj maliyet',
            'Urun Performans' => 'Ürün performansı',
            default => $sheetName,
        };
    }

    protected function reportSheetDescription(string $sheetName): string
    {
        return match ($sheetName) {
            'Hesaplama Guveni' => 'Kâr hesabının finans, snapshot, maliyet ve ödeme güven skoru.',
            'Yonetici Ozeti' => 'Aksiyon masası genel özet ve seçili rapor kapsamı.',
            'Aksiyonlar' => 'Takibe alınan aksiyonların plan, sinyal ve kapanış durumları.',
            'Komuta Kuyrugu' => 'Gecikme, öncelik, finansal etki ve sorumlu durumuna göre ilk bakılacak aksiyonlar.',
            'Aksiyon Dagilimi' => 'Aksiyonların durum, öncelik ve hedef yaşlanması dağılımı.',
            'Aksiyon Sagligi' => 'Aksiyon sağlığı skoru, kapanış disiplini, risk sürücüleri ve haftalık tempo.',
            'Sorumlu Yuku' => 'Açık aksiyonların sorumlu bazında iş yükü.',
            'Kapanis Kalitesi' => 'Çözülen aksiyonlarda not, sorumlu, hedef, zamanında kapanış ve plan tamamlama kalitesi.',
            'Haftalik Trend' => 'Son haftalarda açılan ve kapanan aksiyon hareketi.',
            'Yaklasan Hedefler' => 'Yaklaşan hedef tarihli aktif aksiyonlar.',
            'Son Hareketler' => 'Aksiyon olay geçmişindeki son kayıtlar.',
            'Siparis Risk Yogunlugu' => 'Sipariş karar kuyruğunun risk bandı ve kök neden dağılımı.',
            'Siparis Karar Kuyrugu' => 'Öncelikli siparişlerin skor, neden ve finansal etkileri.',
            'Urun Marj Maliyet' => 'Ürün marj bandı, veri hazırlığı ve maliyet bileşimi.',
            'Urun Performans' => 'Ürün bazında ciro, maliyet, komisyon, kâr ve hazırlık skoru.',
            default => 'Kar Merkezi rapor sayfası.',
        };
    }

    protected function updateActionStatus(int $id, string $status, string $message): void
    {
        $updated = $this->profitActions()->setStatus($this->userId(), $id, $status);

        if (! $updated) {
            session()->flash('warning', 'Aksiyon kaydı bulunamadı.');

            return;
        }

        $this->refreshActionComputed();

        session()->flash('success', $message);
    }

    protected function actionListFilterChanged(): void
    {
        $this->selectedActionIds = [];
        $this->timelineActionId = null;
        $this->refreshActionComputed();
    }

    /**
     * @param  array<int, array<string, mixed>>  $trackedActions
     */
    protected function syncActionControls(array $trackedActions): void
    {
        foreach ($trackedActions as $action) {
            $id = (int) ($action['id'] ?? 0);

            if ($id <= 0) {
                continue;
            }

            if (! array_key_exists($id, $this->actionNotes)) {
                $this->actionNotes[$id] = (string) ($action['note'] ?? '');
            }

            if (! array_key_exists($id, $this->actionPriorities)) {
                $this->actionPriorities[$id] = (string) ($action['priority'] ?? MpProfitActionItem::PRIORITY_MEDIUM);
            }

            if (! array_key_exists($id, $this->actionDueDates)) {
                $this->actionDueDates[$id] = (string) ($action['due_date'] ?? '');
            }

            if (! array_key_exists($id, $this->actionOwners)) {
                $this->actionOwners[$id] = (string) ($action['owner_label'] ?? '');
            }
        }
    }

    protected function refreshActionComputed(): void
    {
        unset(
            $this->trackedActions,
            $this->actionSummary,
            $this->actionReport,
            $this->managerReportPreview,
            $this->actionTimeline,
            $this->actionOwnerOptions
        );
    }

    /**
     * @return array<string, string>
     */
    protected function routeFiltersFor(string $route): array
    {
        if ($route === 'mp.products') {
            return [
                'legalEntityFilter' => $this->legalEntityFilter,
                'marketplaceFilter' => $this->marketplaceFilter,
            ];
        }

        if ($route === 'mp.matching') {
            return [
                'marketplaceFilter' => $this->marketplaceFilter,
                'storeFilter' => $this->storeFilter,
                'legalEntityFilter' => $this->legalEntityFilter,
            ];
        }

        return [
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
            'marketplaceFilter' => $this->marketplaceFilter,
            'storeFilter' => $this->storeFilter,
            'legalEntityFilter' => $this->legalEntityFilter,
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function filteredRoute(string $route, array $query = []): string
    {
        return route($route, array_filter(array_merge($this->routeFiltersFor($route), $query), fn ($value) => $value !== null && $value !== ''));
    }

    protected function profitCenter(): MarketplaceProfitCenterQueryService
    {
        return app(MarketplaceProfitCenterQueryService::class);
    }

    protected function profitActions(): MarketplaceProfitActionService
    {
        return app(MarketplaceProfitActionService::class);
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function defaultDateRange(): array
    {
        $latestOrderedAt = ChannelOrder::query()
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', $this->userId()))
            ->max('ordered_at');

        $dateTo = $latestOrderedAt
            ? Carbon::parse($latestOrderedAt)->toDateString()
            : now()->toDateString();

        return [
            Carbon::parse($dateTo)->subDays(30)->toDateString(),
            $dateTo,
        ];
    }

    protected function userId(): int
    {
        return Auth::id() ?? 1;
    }
}
