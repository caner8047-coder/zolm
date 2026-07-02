<?php

namespace App\Services;

use App\Models\OptimizationReport;
use App\Models\OptimizationReportItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CampaignDecisionCenterQueryService
{
    /**
     * @return array<string, array{label: string, short_label: string, route: string, tone: string}>
     */
    public function typeDefinitions(): array
    {
        return [
            'tariff' => [
                'label' => 'Ürün Komisyon Tarifesi',
                'short_label' => 'Ürün Komisyon',
                'route' => 'campaigns.product-commission',
                'tone' => 'slate',
            ],
            'plus' => [
                'label' => 'Plus Komisyon Tarifesi',
                'short_label' => 'Plus Komisyon',
                'route' => 'campaigns.plus-commission',
                'tone' => 'indigo',
            ],
            'badge' => [
                'label' => 'Avantajlı Ürün Etiketi',
                'short_label' => 'Avantajlı Ürün',
                'route' => 'campaigns.badge-pricing',
                'tone' => 'amber',
            ],
            'flash' => [
                'label' => 'Flaş Ürünler',
                'short_label' => 'Flaş Ürünler',
                'route' => 'campaigns.flash-products',
                'tone' => 'sky',
            ],
            'basket_discount' => [
                'label' => 'Sepet İndirimi',
                'short_label' => 'Sepet İndirimi',
                'route' => 'campaigns.basket-discount',
                'tone' => 'emerald',
            ],
        ];
    }

    /**
     * @return array<string, array{label: string, tone: string}>
     */
    public function decisionDefinitions(): array
    {
        return [
            'approve' => ['label' => 'Onaylanabilir', 'tone' => 'emerald'],
            'risk' => ['label' => 'İncelenmeli', 'tone' => 'rose'],
            'keep' => ['label' => 'Mevcut durumu koru', 'tone' => 'slate'],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function dashboard(int $userId, array $filters = [], int $queueLimit = 150): array
    {
        $reports = $this->latestReports($userId, (string) ($filters['campaign_type'] ?? ''));
        $rows = $reports
            ->flatMap(fn (OptimizationReport $report) => $report->items->map(
                fn (OptimizationReportItem $item) => $this->serializeItem($report, $item)
            ))
            ->values();

        $allRows = $rows;
        $filteredRows = $rows
            ->when(
                filled($filters['decision'] ?? null),
                fn (Collection $items) => $items->where('decision', (string) $filters['decision'])
            )
            ->when(
                filled($filters['search'] ?? null),
                function (Collection $items) use ($filters) {
                    $search = Str::lower(trim((string) $filters['search']));

                    return $items->filter(function (array $row) use ($search) {
                        return Str::contains(Str::lower($row['product_name']), $search)
                            || Str::contains(Str::lower($row['stock_code']), $search)
                            || Str::contains(Str::lower($row['barcode']), $search)
                            || Str::contains(Str::lower($row['report_name']), $search);
                    });
                }
            )
            ->values();

        $summary = $this->summary($reports, $allRows);
        $modules = $this->moduleSummaries($reports, $allRows);

        return [
            'summary' => $summary,
            'modules' => $modules,
            'decision_breakdown' => $this->decisionBreakdown($allRows),
            'queue' => $filteredRows
                ->sortByDesc(fn (array $row) => [
                    $row['decision_score'],
                    $row['decision'] === 'approve' ? $row['extra_profit'] : $row['risk_exposure'],
                    $row['report_id'],
                ])
                ->take($queueLimit)
                ->values()
                ->all(),
            'queue_total' => $filteredRows->count(),
            'recent_reports' => $this->recentReports($userId, (string) ($filters['campaign_type'] ?? '')),
            'trend' => $this->reportTrend($userId, (string) ($filters['campaign_type'] ?? '')),
            'latest_report_ids' => $reports->pluck('id')->map(fn ($id) => (int) $id)->all(),
        ];
    }

    /**
     * Kâr Merkezi için filtrelerden bağımsız, son raporlar bazlı kompakt etki özeti.
     *
     * @return array<string, mixed>
     */
    public function profitCenterImpact(int $userId): array
    {
        $dashboard = $this->dashboard($userId);
        $summary = $dashboard['summary'];
        $topModule = collect($dashboard['modules'])
            ->sortByDesc(fn (array $module) => max($module['potential_profit'], $module['risk_exposure']))
            ->first();

        return [
            'has_reports' => $summary['report_count'] > 0,
            'report_count' => $summary['report_count'],
            'potential_profit' => $summary['potential_profit'],
            'risk_exposure' => $summary['risk_exposure'],
            'approve_count' => $summary['approve_count'],
            'risk_count' => $summary['risk_count'],
            'latest_at' => $summary['latest_at'],
            'top_module' => $topModule,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function exportRows(int $userId, array $filters = []): array
    {
        $dashboard = $this->dashboard($userId, $filters, PHP_INT_MAX);

        return collect($dashboard['queue'])->map(fn (array $row) => [
            'Kampanya Türü' => $row['campaign_label'],
            'Rapor' => $row['report_name'],
            'Rapor Tarihi' => $row['report_date'],
            'Karar' => $row['decision_label'],
            'Risk Nedenleri' => implode(', ', $row['risk_reasons']),
            'Stok Kodu' => $row['stock_code'],
            'Barkod' => $row['barcode'],
            'Ürün' => $row['product_name'],
            'Mevcut Fiyat' => $row['current_price'],
            'Önerilen Fiyat' => $row['suggested_price'],
            'Mevcut Komisyon' => $row['current_commission'],
            'Önerilen Komisyon' => $row['suggested_commission'],
            'Mevcut Kâr' => $row['current_profit'],
            'Önerilen Kâr' => $row['suggested_profit'],
            'Ek Kâr Etkisi' => $row['extra_profit'],
            'Risk Tutarı' => $row['risk_exposure'],
            'Toplam Maliyet' => $row['total_cost'],
            'Seçili' => $row['selected'] ? 'Evet' : 'Hayır',
            'Önerilen Aksiyon' => $row['action_hint'],
        ])->all();
    }

    /**
     * @return Collection<int, OptimizationReport>
     */
    protected function latestReports(int $userId, string $campaignType = ''): Collection
    {
        $latestIds = OptimizationReport::query()
            ->where('user_id', $userId)
            ->when($campaignType !== '', fn ($query) => $query->where('campaign_type', $campaignType))
            ->selectRaw('MAX(id) as id')
            ->groupBy('campaign_type')
            ->pluck('id')
            ->filter()
            ->values();

        if ($latestIds->isEmpty()) {
            return collect();
        }

        return OptimizationReport::query()
            ->with('items')
            ->where('user_id', $userId)
            ->whereIn('id', $latestIds)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeItem(OptimizationReport $report, OptimizationReportItem $item): array
    {
        $type = (string) $report->campaign_type;
        $typeDefinition = $this->typeDefinitions()[$type] ?? [
            'label' => 'Kampanya',
            'short_label' => 'Kampanya',
            'route' => 'campaigns.index',
            'tone' => 'slate',
        ];
        $currentProfit = round((float) $item->current_net_profit, 2);
        $suggestedProfit = round((float) ($item->suggested_net_profit ?? $item->current_net_profit), 2);
        $extraProfit = round((float) $item->extra_profit, 2);
        $totalCost = round($item->totalCost(), 2);
        $matched = (bool) data_get($item->campaign_data, 'matched', true);
        $riskReasons = [];

        if (! $matched) {
            $riskReasons[] = 'Ürün eşleşmesi yok';
        }

        if ($totalCost <= 0) {
            $riskReasons[] = 'Maliyet verisi eksik';
        }

        if ($suggestedProfit < 0) {
            $riskReasons[] = 'Önerilen senaryo zarar üretiyor';
        }

        if ($extraProfit < 0 && (string) $item->action !== 'keep') {
            $riskReasons[] = 'Kampanya mevcut kârı azaltıyor';
        }

        if ((string) $item->action === 'warning' && $riskReasons === []) {
            $riskReasons[] = 'Kaynak analiz manuel kontrol istiyor';
        }

        $decision = $riskReasons !== []
            ? 'risk'
            : (((string) $item->action === 'update' && $extraProfit > 0) ? 'approve' : 'keep');
        $decisionDefinition = $this->decisionDefinitions()[$decision];
        $riskExposure = $decision === 'risk'
            ? round(max(0, -$extraProfit, -$suggestedProfit), 2)
            : 0.0;
        $proposedImpact = (string) $item->action === 'keep' ? 0.0 : $extraProfit;

        return [
            'id' => (int) $item->id,
            'report_id' => (int) $report->id,
            'report_name' => (string) $report->name,
            'report_date' => $report->created_at?->format('d.m.Y H:i') ?? '',
            'campaign_type' => $type,
            'campaign_label' => $typeDefinition['short_label'],
            'campaign_tone' => $typeDefinition['tone'],
            'route' => $typeDefinition['route'],
            'stock_code' => (string) $item->stock_code,
            'barcode' => (string) ($item->barcode ?? ''),
            'product_name' => (string) (($item->product_name ?: $item->stock_code) ?? ''),
            'current_price' => round((float) $item->current_price, 2),
            'suggested_price' => round((float) ($item->custom_price ?: $item->suggested_price ?: $item->current_price), 2),
            'current_commission' => round((float) $item->current_commission, 2),
            'suggested_commission' => round((float) ($item->suggested_commission ?? $item->current_commission), 2),
            'current_profit' => $currentProfit,
            'suggested_profit' => $suggestedProfit,
            'extra_profit' => $extraProfit,
            'proposed_impact' => $proposedImpact,
            'total_cost' => $totalCost,
            'selected' => (bool) $item->is_selected
                || $item->selected_tariff_index !== null
                || $item->custom_price !== null,
            'decision' => $decision,
            'decision_label' => $decisionDefinition['label'],
            'decision_tone' => $decisionDefinition['tone'],
            'decision_score' => match ($decision) {
                'risk' => 3,
                'approve' => 2,
                default => 1,
            },
            'risk_reasons' => $riskReasons,
            'risk_exposure' => $riskExposure,
            'action_hint' => $this->actionHint($decision, $riskReasons, $type),
        ];
    }

    /**
     * @param  Collection<int, OptimizationReport>  $reports
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    protected function summary(Collection $reports, Collection $rows): array
    {
        $approveRows = $rows->where('decision', 'approve');
        $riskRows = $rows->where('decision', 'risk');
        $selectedRows = $rows->where('selected', true);
        $currentProfit = round((float) $rows->sum('current_profit'), 2);
        $potentialProfit = round((float) $approveRows->sum('extra_profit'), 2);
        $rawImpact = round((float) $rows->sum('proposed_impact'), 2);
        $riskExposure = round((float) $riskRows->sum('risk_exposure'), 2);
        $costReadyCount = $rows->where('total_cost', '>', 0)->count();
        $totalRows = max(1, $rows->count());
        $riskRate = ($riskRows->count() / $totalRows) * 100;
        $costGapRate = (($rows->count() - $costReadyCount) / $totalRows) * 100;
        $score = round(max(0, min(100, 100 - ($riskRate * 0.65) - ($costGapRate * 0.25))), 1);

        return [
            'report_count' => $reports->count(),
            'campaign_type_count' => $reports->pluck('campaign_type')->unique()->count(),
            'product_count' => $rows->count(),
            'current_profit' => $currentProfit,
            'decision_profit' => round($currentProfit + $potentialProfit, 2),
            'potential_profit' => $potentialProfit,
            'raw_impact' => $rawImpact,
            'risk_exposure' => $riskExposure,
            'approve_count' => $approveRows->count(),
            'risk_count' => $riskRows->count(),
            'keep_count' => $rows->where('decision', 'keep')->count(),
            'selected_count' => $selectedRows->count(),
            'selected_impact' => round((float) $selectedRows->sum('extra_profit'), 2),
            'cost_coverage' => round(($costReadyCount / $totalRows) * 100, 1),
            'unmatched_count' => $rows->filter(
                fn (array $row) => in_array('Ürün eşleşmesi yok', $row['risk_reasons'], true)
            )->count(),
            'decision_score' => $score,
            'score_label' => $score >= 85 ? 'Güçlü' : ($score >= 65 ? 'Kontrollü' : ($score >= 45 ? 'İncelenmeli' : 'Kritik')),
            'latest_at' => $reports->max('created_at')?->format('d.m.Y H:i'),
        ];
    }

    /**
     * @param  Collection<int, OptimizationReport>  $reports
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function moduleSummaries(Collection $reports, Collection $rows): array
    {
        $reportsByType = $reports->keyBy('campaign_type');

        return collect($this->typeDefinitions())->map(function (array $definition, string $type) use ($reportsByType, $rows) {
            /** @var OptimizationReport|null $report */
            $report = $reportsByType->get($type);
            $moduleRows = $rows->where('campaign_type', $type);
            $approveRows = $moduleRows->where('decision', 'approve');
            $riskRows = $moduleRows->where('decision', 'risk');
            $total = max(1, $moduleRows->count());

            return [
                'campaign_type' => $type,
                'label' => $definition['label'],
                'short_label' => $definition['short_label'],
                'route' => $definition['route'],
                'tone' => $definition['tone'],
                'has_report' => $report !== null,
                'report_id' => $report?->id,
                'report_name' => $report?->name,
                'report_date' => $report?->created_at?->format('d.m.Y H:i'),
                'product_count' => $moduleRows->count(),
                'approve_count' => $approveRows->count(),
                'risk_count' => $riskRows->count(),
                'keep_count' => $moduleRows->where('decision', 'keep')->count(),
                'selected_count' => $moduleRows->where('selected', true)->count(),
                'potential_profit' => round((float) $approveRows->sum('extra_profit'), 2),
                'risk_exposure' => round((float) $riskRows->sum('risk_exposure'), 2),
                'raw_impact' => round((float) $moduleRows->sum('proposed_impact'), 2),
                'cost_coverage' => round(($moduleRows->where('total_cost', '>', 0)->count() / $total) * 100, 1),
            ];
        })->values()->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function decisionBreakdown(Collection $rows): array
    {
        return collect($this->decisionDefinitions())->map(function (array $definition, string $decision) use ($rows) {
            $decisionRows = $rows->where('decision', $decision);

            return [
                'key' => $decision,
                'label' => $definition['label'],
                'tone' => $definition['tone'],
                'count' => $decisionRows->count(),
                'amount' => $decision === 'approve'
                    ? round((float) $decisionRows->sum('extra_profit'), 2)
                    : ($decision === 'risk'
                        ? round((float) $decisionRows->sum('risk_exposure'), 2)
                        : round((float) $decisionRows->sum('current_profit'), 2)),
            ];
        })->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function recentReports(int $userId, string $campaignType = '', int $limit = 12): array
    {
        return OptimizationReport::query()
            ->where('user_id', $userId)
            ->when($campaignType !== '', fn ($query) => $query->where('campaign_type', $campaignType))
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function (OptimizationReport $report): array {
                $definition = $this->typeDefinitions()[$report->campaign_type] ?? [
                    'short_label' => 'Kampanya',
                    'route' => 'campaigns.index',
                    'tone' => 'slate',
                ];

                return [
                    'id' => (int) $report->id,
                    'name' => (string) $report->name,
                    'campaign_type' => (string) $report->campaign_type,
                    'campaign_label' => $definition['short_label'],
                    'tone' => $definition['tone'],
                    'route' => $definition['route'],
                    'total_products' => (int) $report->total_products,
                    'opportunity_count' => (int) $report->opportunity_count,
                    'total_extra_profit' => round((float) $report->total_extra_profit, 2),
                    'status' => (string) $report->status,
                    'created_at' => $report->created_at?->format('d.m.Y H:i') ?? '',
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function reportTrend(int $userId, string $campaignType = '', int $limit = 12): array
    {
        return collect($this->recentReports($userId, $campaignType, $limit))
            ->reverse()
            ->values()
            ->map(fn (array $report) => [
                'id' => $report['id'],
                'label' => $report['campaign_label'],
                'date' => $report['created_at'],
                'value' => $report['total_extra_profit'],
                'tone' => $report['tone'],
                'route' => $report['route'],
            ])
            ->all();
    }

    /**
     * @param  array<int, string>  $riskReasons
     */
    protected function actionHint(string $decision, array $riskReasons, string $campaignType): string
    {
        if ($decision === 'approve') {
            return 'Önerilen fiyat ve komisyon senaryosunu kaynak kampanya modülünde doğrulayıp seçime alın.';
        }

        if ($decision === 'keep') {
            return 'Mevcut fiyat ve komisyon kurgusunu koruyun; yeni kampanya dosyasında yeniden değerlendirin.';
        }

        return match (true) {
            in_array('Maliyet verisi eksik', $riskReasons, true) => 'Kampanya kararından önce ürün maliyetini tamamlayın.',
            in_array('Ürün eşleşmesi yok', $riskReasons, true) => 'Ürünü master katalogla eşleştirip analizi yenileyin.',
            in_array('Önerilen senaryo zarar üretiyor', $riskReasons, true) => 'Zarar üreten öneriyi uygulamayın; fiyat veya kampanya koşulunu değiştirin.',
            in_array('Kampanya mevcut kârı azaltıyor', $riskReasons, true) => $campaignType === 'basket_discount'
                ? 'Satıcı indirim payını ve sepet baremini düşürmeden kampanyayı onaylamayın.'
                : 'Ek kâr negatife dönüyor; mevcut senaryoyu koruyun.',
            default => 'Kaynak rapordaki uyarıyı ve senaryo detaylarını manuel olarak doğrulayın.',
        };
    }
}
