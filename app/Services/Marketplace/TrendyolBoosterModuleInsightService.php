<?php

namespace App\Services\Marketplace;

use App\Models\TrendyolBoosterKeyword;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TrendyolBoosterModuleInsightService
{
    public function __construct(
        protected TrendyolBoosterSellDecisionService $sellDecisionService,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function finance(
        array $input,
        float $targetRevenue,
        int $periodDays,
        float $fixedCosts = 0,
        float $safetyPercent = 0,
        ?float $observedDailySales = null,
    ): array {
        $financial = $this->sellDecisionService->calculateFinancial($input);
        $saleGross = (float) $financial['sale_gross'];
        $targetRevenue = max(0, $targetRevenue);
        $periodDays = max(1, min(365, $periodDays));
        $targetMarginPercent = max(0, min(95, (float) ($input['target_margin_percent'] ?? 0)));
        $orderCount = $saleGross > 0 && $targetRevenue > 0
            ? (int) ceil($targetRevenue / $saleGross)
            : 0;
        $targetNetProfit = round($targetRevenue * ($targetMarginPercent / 100), 2);
        $requiredUnitProfit = $orderCount > 0 ? round($targetNetProfit / $orderCount, 2) : 0.0;
        $maxPurchaseGross = null;
        $targetFinancial = $financial;

        if ($orderCount > 0) {
            $zeroCostFinancial = $this->sellDecisionService->calculateFinancial(array_replace($input, [
                'cogs' => 0,
                'packaging_cost' => 0,
                'advertising_rate' => 0,
                'return_rate' => 0,
            ]));

            if ((float) $zeroCostFinancial['net_profit'] >= $requiredUnitProfit) {
                $low = 0.0;
                $high = $saleGross;

                for ($iteration = 0; $iteration < 36; $iteration++) {
                    $middle = ($low + $high) / 2;
                    $candidate = $this->sellDecisionService->calculateFinancial(array_replace($input, [
                        'cogs' => $middle,
                        'packaging_cost' => 0,
                        'advertising_rate' => 0,
                        'return_rate' => 0,
                    ]));

                    if ((float) $candidate['net_profit'] >= $requiredUnitProfit) {
                        $low = $middle;
                    } else {
                        $high = $middle;
                    }
                }

                $maxPurchaseGross = round($low, 2);
                $targetFinancial = $this->sellDecisionService->calculateFinancial(array_replace($input, [
                    'cogs' => $maxPurchaseGross,
                    'packaging_cost' => 0,
                    'advertising_rate' => 0,
                    'return_rate' => 0,
                ]));
            } else {
                $maxPurchaseGross = 0.0;
                $targetFinancial = $zeroCostFinancial;
            }
        }

        $dailyUnits = $orderCount > 0 ? round($orderCount / $periodDays, 2) : null;
        $weeklyUnits = $orderCount > 0 ? round(($orderCount / $periodDays) * 7, 2) : null;
        $observedDailySales = $observedDailySales !== null && $observedDailySales > 0
            ? $observedDailySales
            : null;
        $feasibility = $dailyUnits !== null && $dailyUnits > 0 && $observedDailySales !== null
            ? round(($observedDailySales / $dailyUnits) * 100, 1)
            : null;
        $unreachable = $maxPurchaseGross === 0.0
            && (float) $targetFinancial['net_profit'] < $requiredUnitProfit;

        return [
            'financial' => $financial,
            'target' => [
                'target_revenue' => $targetRevenue,
                'target_margin_percent' => $targetMarginPercent,
                'target_profit' => $targetNetProfit,
                'period_days' => $periodDays,
                'planned_units' => $orderCount,
                'daily_units' => $dailyUnits,
                'weekly_units' => $weeklyUnits,
                'required_unit_profit' => $requiredUnitProfit,
                'max_purchase_gross' => $maxPurchaseGross,
                'max_purchase_net' => $maxPurchaseGross !== null
                    ? (float) $this->sellDecisionService->calculateFinancial(array_replace($input, [
                        'cogs' => $maxPurchaseGross,
                        'packaging_cost' => 0,
                    ]))['product_cost_net']
                    : null,
                'unit_financial' => $targetFinancial,
                'total_purchase_cost' => round((float) $targetFinancial['cogs_gross'] * $orderCount, 2),
                'total_commission' => round((float) $targetFinancial['commission_gross'] * $orderCount, 2),
                'total_cargo' => round((float) $targetFinancial['cargo_gross'] * $orderCount, 2),
                'total_payable_vat' => round((float) $targetFinancial['payable_vat'] * $orderCount, 2),
                'total_income_tax' => round((float) $targetFinancial['income_tax'] * $orderCount, 2),
                'total_withholding' => round((float) $targetFinancial['withholding'] * $orderCount, 2),
                'total_net_profit' => round((float) $targetFinancial['net_profit'] * $orderCount, 2),
                'observed_daily_sales' => $observedDailySales,
                'feasibility_percent' => $feasibility,
                'status' => match (true) {
                    $saleGross <= 0 || $targetRevenue <= 0 => 'missing_input',
                    $unreachable => 'unreachable',
                    $feasibility === null => 'reachable',
                    $feasibility >= 100 => 'feasible',
                    $feasibility >= 70 => 'stretch',
                    default => 'unrealistic',
                },
            ],
            'ledger' => [
                ['label' => 'KDV dahil satış', 'value' => $financial['sale_gross'], 'tone' => 'neutral'],
                ['label' => 'Ürün + ambalaj maliyeti', 'value' => -$financial['product_cost_gross'], 'tone' => 'cost'],
                ['label' => 'Brüt kâr', 'value' => $financial['gross_profit'], 'tone' => 'subtotal'],
                ['label' => 'Komisyon', 'value' => -$financial['commission_gross'], 'tone' => 'cost'],
                ['label' => 'Kargo', 'value' => -$financial['cargo_gross'], 'tone' => 'cost'],
                ['label' => 'Hizmet bedeli', 'value' => -$financial['service_fee_gross'], 'tone' => 'cost'],
                ['label' => 'İade rezervi', 'value' => -$financial['return_reserve_gross'], 'tone' => 'cost'],
                ['label' => 'Ödenecek KDV', 'value' => -$financial['payable_vat'], 'tone' => 'tax'],
                ['label' => 'Gelir vergisi', 'value' => -$financial['income_tax'], 'tone' => 'tax'],
                ['label' => 'Net kâr', 'value' => $financial['net_profit'], 'tone' => 'total'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function marketDashboard(string $module, int $userId, string $search = '', ?int $productId = null): array
    {
        $dashboard = $module === 'keyword_tracking'
            ? $this->keywordTracking($userId, $productId)
            : $this->emptyDashboard($module);
        $term = Str::lower(trim($search));
        $rows = collect($dashboard['rows'] ?? [])
            ->when($term !== '', fn (Collection $rows): Collection => $rows->filter(
                fn (array $row): bool => str_contains(
                    Str::lower(implode(' ', array_filter($row, 'is_scalar'))),
                    $term,
                )
            ))
            ->values();

        $dashboard['rows'] = $rows;
        $dashboard['filtered_count'] = $rows->count();

        return $dashboard;
    }

    /** @return array<string, mixed> */
    protected function keywordTracking(int $userId, ?int $productId = null): array
    {
        $rows = TrendyolBoosterKeyword::query()
            ->where('user_id', $userId)
            ->when($productId, fn ($query) => $query->where('trendyol_booster_product_id', $productId))
            ->with(['trackedProduct.latestSnapshot', 'observations'])
            ->latest('last_checked_at')
            ->get()
            ->map(function (TrendyolBoosterKeyword $keyword): array {
                $rank = $keyword->observed_rank;
                $checkedCount = (int) $keyword->checked_result_count;

                if ($checkedCount === 0) {
                    $checkedCount = match (true) {
                        $rank !== null => $rank,
                        $keyword->visibility_status === 'not_found' => min(120, (int) $keyword->result_count),
                        default => min(50, (int) $keyword->result_count),
                    };
                }

                $status = match ($keyword->visibility_status) {
                    'found' => $rank !== null && $rank <= (int) $keyword->target_rank ? 'visible' : 'low_visibility',
                    'not_found' => 'missing',
                    default => $keyword->visibility_status,
                };
                $rankBand = match (true) {
                    $rank === null => null,
                    $rank <= 10 => 10,
                    $rank <= 20 => 20,
                    $rank <= 50 => 50,
                    $rank <= 100 => 100,
                    default => null,
                };
                $score = match (true) {
                    $rank !== null && $rank <= 3 => 95,
                    $rank !== null && $rank <= 10 => 80,
                    $rank !== null && $rank <= (int) $keyword->target_rank => 65,
                    $rank !== null => 40,
                    default => 15,
                };

                $history = $keyword->observations
                    ->sortBy('created_at')
                    ->filter(fn ($obs): bool => $obs->observed_rank !== null && $obs->created_at !== null)
                    ->map(fn ($obs) => [
                        'rank' => (int) $obs->observed_rank,
                        'date' => $obs->created_at->format('d M'),
                    ])
                    ->values()
                    ->all();

                return [
                    'id' => $keyword->id,
                    'name' => $keyword->keyword,
                    'product' => $keyword->trackedProduct?->title ?: 'Ürün bulunamadı',
                    'product_id' => $keyword->trendyol_booster_product_id,
                    'target_rank' => (int) $keyword->target_rank,
                    'observed_rank' => $rank,
                    'history' => $history,
                    'result_count' => (int) $keyword->result_count,
                    'checked_count' => $checkedCount,
                    'status' => $status,
                    'note' => $keyword->visibility_note,
                    'is_active' => (bool) $keyword->is_active,
                    'last_checked_at' => $keyword->last_checked_at,
                    'score' => $score,
                    'rank_band' => $rankBand,
                    'rank_band_label' => match (true) {
                        $rankBand !== null => "İlk {$rankBand} içinde",
                        $rank !== null => '100. sıradan sonra',
                        $checkedCount > 0 => "İlk {$checkedCount} içinde yok",
                        default => 'Kontrol bekliyor',
                    },
                    'target_gap' => $rank !== null ? $rank - (int) $keyword->target_rank : null,
                    'search_url' => 'https://www.trendyol.com/sr?q='.rawurlencode((string) $keyword->keyword),
                ];
            })
            ->sortByDesc('score')
            ->values();

        return [
            'type' => 'keyword_tracking',
            'summary' => [
                'total' => $rows->count(),
                'active' => $rows->whereNotNull('observed_rank')->count(),
                'found' => $rows->whereNotNull('observed_rank')->count(),
                'top_fifty' => $rows->filter(
                    fn (array $row): bool => $row['observed_rank'] !== null && $row['observed_rank'] <= 50
                )->count(),
                'top_ten' => $rows->filter(
                    fn (array $row): bool => $row['observed_rank'] !== null && $row['observed_rank'] <= 10
                )->count(),
                'missing' => $rows->whereNull('observed_rank')->count(),
                'best_rank' => $rows->whereNotNull('observed_rank')->min('observed_rank'),
            ],
            'rows' => $rows,
        ];
    }

    /** @return array<string, mixed> */
    protected function emptyDashboard(string $module): array
    {
        return [
            'type' => $module,
            'summary' => ['total' => 0],
            'rows' => collect(),
        ];
    }
}
