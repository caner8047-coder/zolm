<?php

namespace App\Services\Marketplace;

use App\Models\ChannelOrder;
use App\Models\ChannelOrderItem;
use App\Services\ProfitabilityMetric;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class MarketplaceProfitCenterQueryService
{
    public function __construct(
        protected MarketplaceReconciliationQueryService $reconciliation,
        protected MarketplaceProfitActionBlueprintService $blueprints,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function summary(int $userId, array $filters = []): array
    {
        $baseQuery = $this->baseQuery($userId, $filters);

        $row = DB::query()
            ->fromSub((clone $baseQuery)->reorder(), 'profit_base')
            ->selectRaw('
                COUNT(*) as total_orders,
                COALESCE(SUM(gross_revenue_metric), 0) as gross_revenue,
                COALESCE(SUM(net_receivable_metric), 0) as net_receivable,
                COALESCE(SUM(deduction_total_metric), 0) as total_deductions,
                COALESCE(SUM(commission_total_metric), 0) as commission_total,
                COALESCE(SUM(cargo_total_metric), 0) as cargo_total,
                COALESCE(SUM(service_fee_total_metric), 0) as service_fee_total,
                COALESCE(SUM(advertising_total_metric), 0) as advertising_total,
                COALESCE(SUM(penalty_total_metric), 0) as penalty_total,
                COALESCE(SUM(early_payment_total_metric), 0) as early_payment_total,
                COALESCE(SUM(discount_total_metric), 0) as discount_total,
                COALESCE(SUM(other_cost_total_metric), 0) as other_cost_total,
                COALESCE(SUM(withholding_total_metric), 0) as withholding_total,
                COALESCE(SUM(estimated_profit_metric), 0) as estimated_profit,
                COALESCE(SUM(confirmed_profit_metric), 0) as confirmed_profit,
                COALESCE(SUM(profit_value_metric), 0) as profit_value,
                COALESCE(SUM(profit_delta_metric), 0) as profit_delta,
                COALESCE(SUM(ABS(profit_delta_metric)), 0) as profit_delta_abs,
                COALESCE(SUM(deduction_delta_metric), 0) as deduction_delta,
                COALESCE(SUM(ABS(deduction_delta_metric)), 0) as deduction_delta_abs,
                SUM(CASE WHEN profit_value_metric < 0 THEN 1 ELSE 0 END) as loss_order_count,
                SUM(CASE WHEN profit_state_metric = "confirmed" THEN 1 ELSE 0 END) as confirmed_order_count,
                SUM(CASE WHEN profit_state_metric = "estimated" THEN 1 ELSE 0 END) as estimated_order_count,
                SUM(CASE WHEN financial_event_count > 0 THEN 1 ELSE 0 END) as finance_ready_order_count,
                SUM(CASE WHEN financial_event_count = 0 THEN 1 ELSE 0 END) as finance_waiting_order_count,
                SUM(CASE WHEN reconciliation_state_metric = "aligned" THEN 1 ELSE 0 END) as aligned_order_count,
                SUM(CASE WHEN reconciliation_state_metric = "minor" THEN 1 ELSE 0 END) as minor_variance_order_count,
                SUM(CASE WHEN reconciliation_state_metric = "material" THEN 1 ELSE 0 END) as material_variance_order_count,
                SUM(CASE WHEN reconciliation_state_metric = "snapshot_missing" THEN 1 ELSE 0 END) as snapshot_missing_order_count,
                SUM(CASE WHEN reconciliation_state_metric = "waiting" THEN 1 ELSE 0 END) as waiting_reconciliation_order_count
            ')
            ->first();

        $grossRevenue = (float) ($row->gross_revenue ?? 0);
        $profitValue = (float) ($row->profit_value ?? 0);

        return [
            'total_orders' => (int) ($row->total_orders ?? 0),
            'gross_revenue' => round($grossRevenue, 2),
            'net_receivable' => round((float) ($row->net_receivable ?? 0), 2),
            'total_deductions' => round((float) ($row->total_deductions ?? 0), 2),
            'commission_total' => round((float) ($row->commission_total ?? 0), 2),
            'cargo_total' => round((float) ($row->cargo_total ?? 0), 2),
            'service_fee_total' => round((float) ($row->service_fee_total ?? 0), 2),
            'advertising_total' => round((float) ($row->advertising_total ?? 0), 2),
            'penalty_total' => round((float) ($row->penalty_total ?? 0), 2),
            'early_payment_total' => round((float) ($row->early_payment_total ?? 0), 2),
            'discount_total' => round((float) ($row->discount_total ?? 0), 2),
            'other_cost_total' => round((float) ($row->other_cost_total ?? 0), 2),
            'withholding_total' => round((float) ($row->withholding_total ?? 0), 2),
            'estimated_profit' => round((float) ($row->estimated_profit ?? 0), 2),
            'confirmed_profit' => round((float) ($row->confirmed_profit ?? 0), 2),
            'profit_value' => round($profitValue, 2),
            'profit_margin_percent' => $grossRevenue > 0 ? round(($profitValue / $grossRevenue) * 100, 1) : 0.0,
            'profit_delta' => round((float) ($row->profit_delta ?? 0), 2),
            'profit_delta_abs' => round((float) ($row->profit_delta_abs ?? 0), 2),
            'deduction_delta' => round((float) ($row->deduction_delta ?? 0), 2),
            'deduction_delta_abs' => round((float) ($row->deduction_delta_abs ?? 0), 2),
            'loss_order_count' => (int) ($row->loss_order_count ?? 0),
            'confirmed_order_count' => (int) ($row->confirmed_order_count ?? 0),
            'estimated_order_count' => (int) ($row->estimated_order_count ?? 0),
            'finance_ready_order_count' => (int) ($row->finance_ready_order_count ?? 0),
            'finance_waiting_order_count' => (int) ($row->finance_waiting_order_count ?? 0),
            'aligned_order_count' => (int) ($row->aligned_order_count ?? 0),
            'minor_variance_order_count' => (int) ($row->minor_variance_order_count ?? 0),
            'material_variance_order_count' => (int) ($row->material_variance_order_count ?? 0),
            'snapshot_missing_order_count' => (int) ($row->snapshot_missing_order_count ?? 0),
            'waiting_reconciliation_order_count' => (int) ($row->waiting_reconciliation_order_count ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function executiveCommandSummary(int $userId, array $filters = []): array
    {
        $summary = $this->summary($userId, $filters);
        $readiness = $this->costReadiness($userId, $filters);
        $totalOrders = max(1, (int) $summary['total_orders']);
        $totalLines = max(1, (int) $readiness['total_lines']);
        $financeWaitingPercent = round(((int) $summary['finance_waiting_order_count'] / $totalOrders) * 100, 1);
        $financeCoveragePercent = round(((int) $summary['finance_ready_order_count'] / $totalOrders) * 100, 1);
        $lossPressurePercent = round(((int) $summary['loss_order_count'] / $totalOrders) * 100, 1);
        $materialVariancePercent = round(((int) $summary['material_variance_order_count'] / $totalOrders) * 100, 1);
        $costGapPercent = round((((int) $readiness['unmatched_lines'] + (int) $readiness['missing_cost_lines']) / $totalLines) * 100, 1);
        $profitMargin = (float) $summary['profit_margin_percent'];

        $score = 100.0;
        $score -= min(30, $lossPressurePercent * 0.75);
        $score -= min(20, $materialVariancePercent * 0.45);
        $score -= min(20, $financeWaitingPercent * 0.40);
        $score -= min(20, $costGapPercent * 0.35);
        $score += max(-8, min(8, $profitMargin * 0.10));
        $score = round(max(0, min(100, $score)), 1);

        $focus = $this->commandFocusCandidate([
            [
                'key' => 'loss_orders',
                'label' => 'Zarar baskısı',
                'score' => $lossPressurePercent * 1.10 + max(0, -1 * $profitMargin) * 0.20,
                'value' => $lossPressurePercent,
                'route' => 'mp.finance',
                'query' => ['sortField' => 'profit_value_metric', 'sortDirection' => 'asc'],
                'action_label' => 'Zararlı siparişleri aç',
                'hint' => 'Negatif kâr üreten siparişlerde maliyet, kesinti ve iade etkisini önce ayrıştırın.',
            ],
            [
                'key' => 'material_variance',
                'label' => 'Mutabakat farkı',
                'score' => $materialVariancePercent,
                'value' => $materialVariancePercent,
                'route' => 'mp.finance',
                'query' => ['deltaStateFilter' => 'material', 'sortField' => 'reconciliation_delta_abs_metric', 'sortDirection' => 'desc'],
                'action_label' => 'Farkları incele',
                'hint' => 'Tahmini snapshot ve kesin finans sonucunu karşılaştırıp fark kaynağını kapatın.',
            ],
            [
                'key' => 'finance_waiting',
                'label' => 'Finans kapsama',
                'score' => $financeWaitingPercent * 0.85,
                'value' => $financeWaitingPercent,
                'route' => 'mp.finance',
                'query' => ['financialStateFilter' => 'waiting'],
                'action_label' => 'Bekleyen finansı aç',
                'hint' => 'Hakediş/finans hareketi gelmeyen siparişleri net alacak kesinliği için takip edin.',
            ],
            [
                'key' => 'missing_cost',
                'label' => 'Maliyet hazırlığı',
                'score' => $costGapPercent * 0.80,
                'value' => $costGapPercent,
                'route' => 'mp.products',
                'query' => ['filterCostDefined' => 'no', 'sortField' => 'cogs', 'sortDirection' => 'asc'],
                'action_label' => 'Maliyet eksiklerini aç',
                'hint' => 'Ürün eşleşmesi ve COGS/ambalaj maliyeti eksiklerini tamamlayıp kâr güvenini yükseltin.',
            ],
        ]);

        return [
            'score' => $score,
            'score_label' => $this->commandScoreLabel($score),
            'score_tone' => $this->commandScoreTone($score),
            'headline' => $this->commandHeadline($score),
            'primary_focus' => $focus['label'],
            'primary_focus_value' => round((float) $focus['value'], 1),
            'primary_hint' => $focus['hint'],
            'primary_action' => [
                'label' => $focus['action_label'],
                'route' => $focus['route'],
                'query' => $focus['query'],
            ],
            'metrics' => [
                [
                    'key' => 'profit_margin',
                    'label' => 'Kâr marjı',
                    'value' => $profitMargin,
                    'tone' => $profitMargin >= 0 ? 'emerald' : 'rose',
                    'description' => 'Cirodan kalan kâr oranı.',
                ],
                [
                    'key' => 'finance_coverage',
                    'label' => 'Finans kapsama',
                    'value' => $financeCoveragePercent,
                    'tone' => $financeCoveragePercent >= 80 ? 'emerald' : ($financeCoveragePercent >= 60 ? 'amber' : 'rose'),
                    'description' => 'Finans/hakediş olayı oluşmuş sipariş oranı.',
                ],
                [
                    'key' => 'reconciliation_pressure',
                    'label' => 'Mutabakat baskısı',
                    'value' => $materialVariancePercent,
                    'tone' => $materialVariancePercent > 20 ? 'rose' : ($materialVariancePercent > 0 ? 'amber' : 'emerald'),
                    'description' => 'Yüksek fark üreten sipariş oranı.',
                ],
                [
                    'key' => 'cost_readiness',
                    'label' => 'Maliyet hazırlığı',
                    'value' => (float) $readiness['ready_percent'],
                    'tone' => ((float) $readiness['ready_percent']) >= 80 ? 'emerald' : (((float) $readiness['ready_percent']) >= 60 ? 'amber' : 'rose'),
                    'description' => 'Eşleşme ve maliyet bilgisi hazır satır oranı.',
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function marketplaceBreakdown(int $userId, array $filters = [], int $limit = 8): array
    {
        $baseQuery = $this->baseQuery($userId, $filters);

        return DB::query()
            ->fromSub((clone $baseQuery)->reorder(), 'profit_base')
            ->selectRaw('
                marketplace_alias as marketplace,
                COUNT(*) as order_count,
                COALESCE(SUM(gross_revenue_metric), 0) as gross_revenue,
                COALESCE(SUM(net_receivable_metric), 0) as net_receivable,
                COALESCE(SUM(profit_value_metric), 0) as profit_value,
                COALESCE(SUM(deduction_total_metric), 0) as total_deductions,
                SUM(CASE WHEN profit_value_metric < 0 THEN 1 ELSE 0 END) as loss_order_count,
                SUM(CASE WHEN reconciliation_state_metric = "material" THEN 1 ELSE 0 END) as material_variance_order_count,
                SUM(CASE WHEN financial_event_count = 0 THEN 1 ELSE 0 END) as finance_waiting_order_count
            ')
            ->groupBy('marketplace_alias')
            ->orderByDesc('gross_revenue')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'marketplace' => (string) ($row->marketplace ?? ''),
                'order_count' => (int) ($row->order_count ?? 0),
                'gross_revenue' => round((float) ($row->gross_revenue ?? 0), 2),
                'net_receivable' => round((float) ($row->net_receivable ?? 0), 2),
                'profit_value' => round((float) ($row->profit_value ?? 0), 2),
                'profit_margin_percent' => ((float) ($row->gross_revenue ?? 0)) > 0
                    ? round(((float) ($row->profit_value ?? 0) / (float) $row->gross_revenue) * 100, 1)
                    : 0.0,
                'total_deductions' => round((float) ($row->total_deductions ?? 0), 2),
                'loss_order_count' => (int) ($row->loss_order_count ?? 0),
                'material_variance_order_count' => (int) ($row->material_variance_order_count ?? 0),
                'finance_waiting_order_count' => (int) ($row->finance_waiting_order_count ?? 0),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function storeBreakdown(int $userId, array $filters = [], int $limit = 6): array
    {
        $baseQuery = $this->baseQuery($userId, $filters);

        return DB::query()
            ->fromSub((clone $baseQuery)->reorder(), 'profit_base')
            ->selectRaw('
                store_id_alias as store_id,
                store_name_alias as store_name,
                marketplace_alias as marketplace,
                legal_entity_name_alias as legal_entity_name,
                COUNT(*) as order_count,
                COALESCE(SUM(gross_revenue_metric), 0) as gross_revenue,
                COALESCE(SUM(profit_value_metric), 0) as profit_value,
                SUM(CASE WHEN profit_value_metric < 0 THEN 1 ELSE 0 END) as loss_order_count,
                SUM(CASE WHEN financial_event_count = 0 THEN 1 ELSE 0 END) as finance_waiting_order_count
            ')
            ->groupBy('store_id_alias', 'store_name_alias', 'marketplace_alias', 'legal_entity_name_alias')
            ->orderByDesc('gross_revenue')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'store_id' => (int) ($row->store_id ?? 0),
                'store_name' => (string) ($row->store_name ?? ''),
                'marketplace' => (string) ($row->marketplace ?? ''),
                'legal_entity_name' => (string) ($row->legal_entity_name ?? ''),
                'order_count' => (int) ($row->order_count ?? 0),
                'gross_revenue' => round((float) ($row->gross_revenue ?? 0), 2),
                'profit_value' => round((float) ($row->profit_value ?? 0), 2),
                'profit_margin_percent' => ((float) ($row->gross_revenue ?? 0)) > 0
                    ? round(((float) ($row->profit_value ?? 0) / (float) $row->gross_revenue) * 100, 1)
                    : 0.0,
                'loss_order_count' => (int) ($row->loss_order_count ?? 0),
                'finance_waiting_order_count' => (int) ($row->finance_waiting_order_count ?? 0),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function dailyTrend(int $userId, array $filters = [], int $limit = 45): array
    {
        $baseQuery = $this->baseQuery($userId, $filters);

        return DB::query()
            ->fromSub((clone $baseQuery)->reorder(), 'profit_base')
            ->selectRaw('
                DATE(ordered_at) as trend_date,
                COUNT(*) as order_count,
                COALESCE(SUM(gross_revenue_metric), 0) as gross_revenue,
                COALESCE(SUM(profit_value_metric), 0) as profit_value,
                COALESCE(SUM(deduction_total_metric), 0) as total_deductions,
                SUM(CASE WHEN profit_value_metric < 0 THEN 1 ELSE 0 END) as loss_order_count
            ')
            ->groupByRaw('DATE(ordered_at)')
            ->orderBy('trend_date')
            ->get()
            ->slice(-1 * max(1, $limit))
            ->values()
            ->map(fn ($row) => [
                'date' => (string) ($row->trend_date ?? ''),
                'order_count' => (int) ($row->order_count ?? 0),
                'gross_revenue' => round((float) ($row->gross_revenue ?? 0), 2),
                'profit_value' => round((float) ($row->profit_value ?? 0), 2),
                'total_deductions' => round((float) ($row->total_deductions ?? 0), 2),
                'loss_order_count' => (int) ($row->loss_order_count ?? 0),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function deductionBreakdown(int $userId, array $filters = []): array
    {
        $summary = $this->summary($userId, $filters);
        $total = max(0.01, (float) $summary['total_deductions']);

        return collect([
            ['key' => 'commission', 'label' => 'Komisyon', 'value' => $summary['commission_total'], 'tone' => 'amber'],
            ['key' => 'cargo', 'label' => 'Kargo', 'value' => $summary['cargo_total'], 'tone' => 'sky'],
            ['key' => 'service_fee', 'label' => 'Hizmet ve diğer', 'value' => $summary['service_fee_total'] - $summary['advertising_total'] - $summary['penalty_total'] - $summary['early_payment_total'] - $summary['discount_total'] - $summary['other_cost_total'], 'tone' => 'slate'],
            ['key' => 'advertising', 'label' => 'Reklam', 'value' => $summary['advertising_total'], 'tone' => 'indigo'],
            ['key' => 'penalty', 'label' => 'Ceza', 'value' => $summary['penalty_total'], 'tone' => 'red'],
            ['key' => 'early_payment', 'label' => 'Erken ödeme', 'value' => $summary['early_payment_total'], 'tone' => 'violet'],
            ['key' => 'discount', 'label' => 'Kampanya indirimi', 'value' => $summary['discount_total'], 'tone' => 'orange'],
            ['key' => 'other', 'label' => 'Diğer', 'value' => $summary['other_cost_total'], 'tone' => 'zinc'],
            ['key' => 'withholding', 'label' => 'Stopaj', 'value' => $summary['withholding_total'], 'tone' => 'rose'],
        ])
            ->map(fn (array $row) => array_merge($row, [
                'value' => round((float) $row['value'], 2),
                'percent' => round(((float) $row['value'] / $total) * 100, 1),
            ]))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function topLossOrders(int $userId, array $filters = [], int $limit = 8): array
    {
        $baseQuery = $this->baseQuery($userId, $filters);

        return (clone $baseQuery)
            ->havingRaw('profit_value_metric < 0')
            ->reorder('profit_value_metric')
            ->limit($limit)
            ->get()
            ->map(fn (ChannelOrder $order) => [
                'id' => (int) $order->id,
                'order_number' => (string) $order->order_number,
                'marketplace' => (string) ($order->marketplace_alias ?? ''),
                'store_name' => (string) ($order->store_name_alias ?? ''),
                'ordered_at' => $order->ordered_at,
                'gross_revenue' => round((float) ($order->gross_revenue_metric ?? 0), 2),
                'profit_value' => round((float) ($order->profit_value_metric ?? 0), 2),
                'profit_margin_percent' => ProfitabilityMetric::profitPercentFromMultiplier($order->margin_percent_metric ?? null) ?? 0.0,
                'deduction_total' => round((float) ($order->deduction_total_metric ?? 0), 2),
                'reconciliation_state' => (string) ($order->reconciliation_state_metric ?? 'waiting'),
                'financial_event_count' => (int) ($order->financial_event_count ?? 0),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function costReadiness(int $userId, array $filters = []): array
    {
        $query = $this->filteredItemQuery($userId, $filters);

        $row = DB::query()
            ->fromSub((clone $query)->reorder(), 'item_base')
            ->selectRaw('
                COUNT(*) as total_lines,
                SUM(CASE WHEN mp_product_id IS NULL THEN 1 ELSE 0 END) as unmatched_lines,
                SUM(CASE WHEN mp_product_id IS NOT NULL THEN 1 ELSE 0 END) as matched_lines,
                SUM(CASE WHEN mp_product_id IS NOT NULL AND product_cost_ready = 0 THEN 1 ELSE 0 END) as missing_cost_lines,
                COUNT(DISTINCT CASE WHEN mp_product_id IS NOT NULL THEN mp_product_id ELSE NULL END) as distinct_products,
                COUNT(DISTINCT CASE WHEN mp_product_id IS NOT NULL AND product_cost_ready = 0 THEN mp_product_id ELSE NULL END) as missing_cost_products
            ')
            ->first();

        $totalLines = (int) ($row->total_lines ?? 0);
        $readyLines = max(0, $totalLines - (int) ($row->unmatched_lines ?? 0) - (int) ($row->missing_cost_lines ?? 0));

        // Maliyetli Ciro: filteredOrderIdsQuery üzerinden revenue kırılımı hesapla
        // costGapImpact ile aynı revenue formülü kullanılır (billable_amount / gross_amount / unit_price*qty)
        $orderIds = $this->filteredOrderIdsQuery($userId, $filters);
        $revSql   = '(COALESCE(NULLIF(channel_order_items.billable_amount, 0), NULLIF(channel_order_items.gross_amount, 0), (COALESCE(channel_order_items.unit_price, 0) * COALESCE(channel_order_items.quantity, 0))) * COALESCE(filtered_orders.exchange_rate, 1.0))';
        $hasCogsSql = '(channel_order_items.mp_product_id IS NOT NULL AND COALESCE(mp_products.cogs, 0) > 0 AND COALESCE(mp_products.packaging_cost, 0) > 0)';

        $revRow = \App\Models\ChannelOrderItem::query()
            ->joinSub($orderIds, 'filtered_orders', fn ($join) => $join->on('filtered_orders.id', '=', 'channel_order_items.channel_order_id'))
            ->leftJoin('mp_products', 'mp_products.id', '=', 'channel_order_items.mp_product_id')
            ->selectRaw("
                COALESCE(SUM({$revSql}), 0) as total_revenue,
                COALESCE(SUM(CASE WHEN {$hasCogsSql} THEN {$revSql} ELSE 0 END), 0) as cogs_covered_revenue,
                COALESCE(SUM(CASE WHEN NOT ({$hasCogsSql}) THEN {$revSql} ELSE 0 END), 0) as missing_cost_revenue
            ")
            ->first();

        $totalRevenue       = (float) ($revRow->total_revenue ?? 0);
        $cogsCoveredRevenue = (float) ($revRow->cogs_covered_revenue ?? 0);

        return [
            'total_lines'                   => $totalLines,
            'matched_lines'                 => (int) ($row->matched_lines ?? 0),
            'unmatched_lines'               => (int) ($row->unmatched_lines ?? 0),
            'missing_cost_lines'            => (int) ($row->missing_cost_lines ?? 0),
            'distinct_products'             => (int) ($row->distinct_products ?? 0),
            'missing_cost_products'         => (int) ($row->missing_cost_products ?? 0),
            'ready_percent'                 => $totalLines > 0 ? round(($readyLines / $totalLines) * 100, 1) : 0.0,
            // Yeni: Maliyetli Ciro metrikleri
            'total_revenue'                 => round($totalRevenue, 2),
            'cogs_covered_revenue'          => round($cogsCoveredRevenue, 2),
            'missing_cost_revenue'          => round((float) ($revRow->missing_cost_revenue ?? 0), 2),
            'cogs_coverage_revenue_percent' => $totalRevenue > 0 ? round(($cogsCoveredRevenue / $totalRevenue) * 100, 1) : 0.0,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function riskSignals(int $userId, array $filters = []): array
    {
        $summary = $this->summary($userId, $filters);
        $readiness = $this->costReadiness($userId, $filters);

        return [
            [
                'key' => 'loss_orders',
                'label' => 'Zarar eden sipariş',
                'value' => $summary['loss_order_count'],
                'tone' => $summary['loss_order_count'] > 0 ? 'danger' : 'success',
                'description' => 'Seçili aralıkta kâr değeri sıfırın altına inen siparişler.',
                'route' => 'mp.finance',
                'query' => ['sortField' => 'profit_value_metric', 'sortDirection' => 'asc'],
            ],
            [
                'key' => 'material_variance',
                'label' => 'Materyal mutabakat farkı',
                'value' => $summary['material_variance_order_count'],
                'tone' => $summary['material_variance_order_count'] > 0 ? 'danger' : 'success',
                'description' => 'Tahmini snapshot ile kesin finans sonucu arasında yüksek fark var.',
                'route' => 'mp.finance',
                'query' => ['deltaStateFilter' => 'material'],
            ],
            [
                'key' => 'finance_waiting',
                'label' => 'Finans bekleyen sipariş',
                'value' => $summary['finance_waiting_order_count'],
                'tone' => $summary['finance_waiting_order_count'] > 0 ? 'warning' : 'success',
                'description' => 'Sipariş var ancak henüz finans/hakediş olayı oluşmamış.',
                'route' => 'mp.finance',
                'query' => ['financialStateFilter' => 'waiting'],
            ],
            [
                'key' => 'snapshot_missing',
                'label' => 'Kâr kaydı eksik',
                'value' => $summary['snapshot_missing_order_count'],
                'tone' => $summary['snapshot_missing_order_count'] > 0 ? 'warning' : 'success',
                'description' => 'Sipariş için order-level kâr snapshot kaydı bulunmuyor.',
                'route' => 'mp.finance',
                'query' => ['deltaStateFilter' => 'snapshot_missing'],
            ],
            [
                'key' => 'missing_cost',
                'label' => 'Maliyet hazır değil',
                'value' => $readiness['missing_cost_lines'] + $readiness['unmatched_lines'],
                'tone' => ($readiness['missing_cost_lines'] + $readiness['unmatched_lines']) > 0 ? 'warning' : 'success',
                'description' => 'Ürün eşleşmesi veya COGS/ambalaj maliyeti eksik satırlar.',
                'route' => 'mp.products',
                'query' => [],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function priorityRecommendations(int $userId, array $filters = [], int $limit = 4): array
    {
        $baseQuery = $this->baseQuery($userId, $filters);

        $row = DB::query()
            ->fromSub((clone $baseQuery)->reorder(), 'profit_base')
            ->selectRaw('
                SUM(CASE WHEN profit_value_metric < 0 THEN 1 ELSE 0 END) as loss_order_count,
                COALESCE(SUM(CASE WHEN profit_value_metric < 0 THEN ABS(profit_value_metric) ELSE 0 END), 0) as loss_profit_pressure,
                SUM(CASE WHEN financial_event_count = 0 THEN 1 ELSE 0 END) as finance_waiting_order_count,
                COALESCE(SUM(CASE WHEN financial_event_count = 0 THEN gross_revenue_metric ELSE 0 END), 0) as finance_waiting_revenue,
                SUM(CASE WHEN reconciliation_state_metric = "material" THEN 1 ELSE 0 END) as material_variance_order_count,
                COALESCE(SUM(CASE WHEN reconciliation_state_metric = "material" THEN reconciliation_delta_abs_metric ELSE 0 END), 0) as material_variance_impact,
                SUM(CASE WHEN reconciliation_state_metric = "snapshot_missing" THEN 1 ELSE 0 END) as snapshot_missing_order_count,
                COALESCE(SUM(CASE WHEN reconciliation_state_metric = "snapshot_missing" THEN gross_revenue_metric ELSE 0 END), 0) as snapshot_missing_revenue
            ')
            ->first();

        $costGap = $this->costGapImpact($userId, $filters);

        return collect([
            [
                'key' => 'material_variance',
                'label' => 'Mutabakat farkını kapat',
                'value' => (int) ($row->material_variance_order_count ?? 0),
                'impact' => round((float) ($row->material_variance_impact ?? 0), 2),
                'tone' => 'danger',
                'description' => 'Tahmini kâr ile kesin finans sonucu arasında yüksek fark olan siparişler.',
                'action_label' => 'Farkları incele',
                'route' => 'mp.finance',
                'query' => ['deltaStateFilter' => 'material', 'sortField' => 'reconciliation_delta_abs_metric', 'sortDirection' => 'desc'],
                'score' => ((float) ($row->material_variance_impact ?? 0)) + ((int) ($row->material_variance_order_count ?? 0) * 500),
            ],
            [
                'key' => 'loss_orders',
                'label' => 'Zarar baskısını azalt',
                'value' => (int) ($row->loss_order_count ?? 0),
                'impact' => round((float) ($row->loss_profit_pressure ?? 0), 2),
                'tone' => 'danger',
                'description' => 'Negatif kâr görünen siparişlerde maliyet, kesinti, iade ve finans etkisini kontrol edin.',
                'action_label' => 'Zararlı siparişleri aç',
                'route' => 'mp.finance',
                'query' => ['sortField' => 'profit_value_metric', 'sortDirection' => 'asc'],
                'score' => ((float) ($row->loss_profit_pressure ?? 0)) + ((int) ($row->loss_order_count ?? 0) * 300),
            ],
            [
                'key' => 'missing_cost',
                'label' => 'Maliyet hazırlığını tamamla',
                'value' => (int) $costGap['gap_lines'],
                'impact' => round((float) $costGap['affected_revenue'], 2),
                'tone' => 'warning',
                'description' => 'Ürün eşleşmesi veya maliyet bilgisi eksik satırlar kâr hesabının güvenilirliğini düşürür.',
                'action_label' => 'Maliyet eksiklerini aç',
                'route' => 'mp.products',
                'query' => ['filterCostDefined' => 'no', 'sortField' => 'cogs', 'sortDirection' => 'asc'],
                'score' => ((float) $costGap['affected_revenue'] * 0.12) + ((int) $costGap['gap_lines'] * 150),
            ],
            [
                'key' => 'finance_waiting',
                'label' => 'Finans bekleyenleri netleştir',
                'value' => (int) ($row->finance_waiting_order_count ?? 0),
                'impact' => round((float) ($row->finance_waiting_revenue ?? 0), 2),
                'tone' => 'warning',
                'description' => 'Hakediş/finans olayı gelmeyen siparişlerde net alacak ve kâr kesinleşmez.',
                'action_label' => 'Bekleyen finansı aç',
                'route' => 'mp.finance',
                'query' => ['financialStateFilter' => 'waiting'],
                'score' => ((float) ($row->finance_waiting_revenue ?? 0) * 0.15) + ((int) ($row->finance_waiting_order_count ?? 0) * 150),
            ],
            [
                'key' => 'snapshot_missing',
                'label' => 'Kâr kaydı eksiklerini üret',
                'value' => (int) ($row->snapshot_missing_order_count ?? 0),
                'impact' => round((float) ($row->snapshot_missing_revenue ?? 0), 2),
                'tone' => 'warning',
                'description' => 'Order-level kâr snapshot kaydı olmayan siparişler mutabakatta düşük güven üretir.',
                'action_label' => 'Eksik kayıtları aç',
                'route' => 'mp.finance',
                'query' => ['deltaStateFilter' => 'snapshot_missing'],
                'score' => ((float) ($row->snapshot_missing_revenue ?? 0) * 0.10) + ((int) ($row->snapshot_missing_order_count ?? 0) * 120),
            ],
        ])
            ->filter(fn (array $item) => (int) $item['value'] > 0)
            ->sortByDesc('score')
            ->take($limit)
            ->values()
            ->map(fn (array $item) => array_merge($item, [
                'score' => round((float) $item['score'], 1),
            ], $this->blueprints->forKey((string) $item['key'])))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function orderRiskFunnel(int $userId, array $filters = []): array
    {
        $summary = $this->summary($userId, $filters);
        $total = max(1, (int) $summary['total_orders']);

        return collect([
            [
                'key' => 'total',
                'label' => 'Toplam sipariş',
                'value' => $summary['total_orders'],
                'percent' => 100.0,
                'tone' => 'slate',
                'description' => 'Seçili filtrelerde analiz edilen sipariş havuzu.',
            ],
            [
                'key' => 'finance_ready',
                'label' => 'Finans hazır',
                'value' => $summary['finance_ready_order_count'],
                'percent' => round(((int) $summary['finance_ready_order_count'] / $total) * 100, 1),
                'tone' => 'emerald',
                'description' => 'Hakediş/finans olayı oluşmuş siparişler.',
            ],
            [
                'key' => 'finance_waiting',
                'label' => 'Finans bekliyor',
                'value' => $summary['finance_waiting_order_count'],
                'percent' => round(((int) $summary['finance_waiting_order_count'] / $total) * 100, 1),
                'tone' => 'amber',
                'description' => 'Sipariş var ancak finans olayı henüz gelmemiş.',
            ],
            [
                'key' => 'loss_orders',
                'label' => 'Zarar baskısı',
                'value' => $summary['loss_order_count'],
                'percent' => round(((int) $summary['loss_order_count'] / $total) * 100, 1),
                'tone' => 'rose',
                'description' => 'Kâr değeri negatif olan siparişler.',
            ],
            [
                'key' => 'material_variance',
                'label' => 'Mutabakat farkı',
                'value' => $summary['material_variance_order_count'],
                'percent' => round(((int) $summary['material_variance_order_count'] / $total) * 100, 1),
                'tone' => 'rose',
                'description' => 'Tahmini ve kesin finans sonucu arasında yüksek fark bulunan siparişler.',
            ],
            [
                'key' => 'snapshot_missing',
                'label' => 'Kâr kaydı eksik',
                'value' => $summary['snapshot_missing_order_count'],
                'percent' => round(((int) $summary['snapshot_missing_order_count'] / $total) * 100, 1),
                'tone' => 'amber',
                'description' => 'Order-level kâr snapshot kaydı olmayan siparişler.',
            ],
        ])->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function orderDecisionInsights(int $userId, array $filters = []): array
    {
        $summary = $this->summary($userId, $filters);
        $queue = $this->orderDecisionQueue($userId, $filters, 25);
        $queueCollection = collect($queue);
        $queueCount = count($queue);
        $totalOrders = max(1, (int) $summary['total_orders']);
        $reasonCounts = [];

        foreach ($queue as $order) {
            foreach (($order['reasons'] ?? []) as $reason) {
                $reasonCounts[$reason] = ($reasonCounts[$reason] ?? 0) + 1;
            }
        }

        arsort($reasonCounts);

        $reasonDistribution = collect($reasonCounts)
            ->map(fn (int $count, string $label) => [
                'label' => $label,
                'count' => $count,
                'percent' => count($queue) > 0 ? round(($count / count($queue)) * 100, 1) : 0.0,
                'tone' => $this->reasonTone($label),
            ])
            ->values()
            ->all();

        $topReason = $reasonDistribution[0]['label'] ?? 'Kritik neden yok';
        $highestScore = collect($queue)->max('risk_score') ?? 0;
        $averageScore = $queueCount > 0
            ? round((float) $queueCollection->avg('risk_score'), 1)
            : 0.0;
        $riskLaneRows = collect([
            [
                'key' => 'critical',
                'label' => 'Kritik',
                'tone' => 'rose',
                'items' => $queueCollection->filter(fn (array $order) => (float) ($order['risk_score'] ?? 0) >= 80),
            ],
            [
                'key' => 'high',
                'label' => 'Yüksek',
                'tone' => 'amber',
                'items' => $queueCollection->filter(fn (array $order) => (float) ($order['risk_score'] ?? 0) >= 50
                    && (float) ($order['risk_score'] ?? 0) < 80),
            ],
            [
                'key' => 'medium',
                'label' => 'İzleme',
                'tone' => 'slate',
                'items' => $queueCollection->filter(fn (array $order) => (float) ($order['risk_score'] ?? 0) < 50),
            ],
        ])->map(function (array $row) use ($queueCount) {
            $items = $row['items'];
            unset($row['items']);

            return array_merge($row, [
                'count' => $items->count(),
                'percent' => $this->percentOf($items->count(), $queueCount),
                'exposure' => round((float) $items->sum(fn (array $order) => max(0, -1 * (float) ($order['profit_value'] ?? 0))
                    + (float) ($order['reconciliation_delta_abs'] ?? 0)), 2),
                'average_score' => $items->count() > 0 ? round((float) $items->avg('risk_score'), 1) : 0.0,
            ]);
        })->values()->all();

        return [
            'queue_count' => $queueCount,
            'highest_score' => round((float) $highestScore, 1),
            'average_score' => $averageScore,
            'critical_order_count' => $queueCollection->filter(fn (array $order) => (float) ($order['risk_score'] ?? 0) >= 80)->count(),
            'top_reason' => $topReason,
            'top_reason_count' => $reasonDistribution[0]['count'] ?? 0,
            'reason_distribution' => $reasonDistribution,
            'risk_lane_rows' => $riskLaneRows,
            'risk_exposure' => round((float) collect($riskLaneRows)->sum('exposure'), 2),
            'dominant_risk_lane' => collect($riskLaneRows)->sortByDesc('count')->first(),
            'finance_waiting_percent' => round(((int) $summary['finance_waiting_order_count'] / $totalOrders) * 100, 1),
            'loss_pressure_percent' => round(((int) $summary['loss_order_count'] / $totalOrders) * 100, 1),
            'material_variance_percent' => round(((int) $summary['material_variance_order_count'] / $totalOrders) * 100, 1),
            'decision_hint' => $this->orderDecisionHint($topReason),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function orderDecisionQueue(int $userId, array $filters = [], int $limit = 10): array
    {
        $baseQuery = $this->baseQuery($userId, $filters);

        return DB::query()
            ->fromSub((clone $baseQuery)->reorder(), 'profit_base')
            ->selectRaw('
                id,
                order_number,
                ordered_at,
                marketplace_alias as marketplace,
                store_name_alias as store_name,
                gross_revenue_metric,
                profit_value_metric,
                deduction_total_metric,
                reconciliation_state_metric,
                reconciliation_delta_abs_metric,
                financial_event_count,
                item_lines_count,
                matched_lines_count,
                (
                    CASE WHEN profit_value_metric < 0 THEN 55 ELSE 0 END
                    + CASE WHEN reconciliation_state_metric = "material" THEN 35 ELSE 0 END
                    + CASE WHEN financial_event_count = 0 THEN 25 ELSE 0 END
                    + CASE WHEN reconciliation_state_metric = "snapshot_missing" THEN 20 ELSE 0 END
                    + CASE WHEN matched_lines_count < item_lines_count THEN 15 ELSE 0 END
                    + LEAST(25, reconciliation_delta_abs_metric / 100)
                ) as risk_score
            ')
            ->whereRaw('
                profit_value_metric < 0
                OR reconciliation_state_metric IN ("material", "snapshot_missing")
                OR financial_event_count = 0
                OR matched_lines_count < item_lines_count
            ')
            ->orderByDesc('risk_score')
            ->orderBy('ordered_at')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                $reasons = array_values(array_filter([
                    ((float) ($row->profit_value_metric ?? 0)) < 0 ? 'Negatif kâr' : null,
                    ($row->reconciliation_state_metric ?? '') === 'material' ? 'Yüksek mutabakat farkı' : null,
                    (int) ($row->financial_event_count ?? 0) === 0 ? 'Finans bekliyor' : null,
                    ($row->reconciliation_state_metric ?? '') === 'snapshot_missing' ? 'Kâr kaydı eksik' : null,
                    (int) ($row->matched_lines_count ?? 0) < (int) ($row->item_lines_count ?? 0) ? 'Eşleşme eksik' : null,
                ]));

                return [
                    'id' => (int) ($row->id ?? 0),
                    'order_number' => (string) ($row->order_number ?? ''),
                    'ordered_at' => $row->ordered_at,
                    'marketplace' => (string) ($row->marketplace ?? ''),
                    'store_name' => (string) ($row->store_name ?? ''),
                    'gross_revenue' => round((float) ($row->gross_revenue_metric ?? 0), 2),
                    'profit_value' => round((float) ($row->profit_value_metric ?? 0), 2),
                    'deduction_total' => round((float) ($row->deduction_total_metric ?? 0), 2),
                    'reconciliation_state' => (string) ($row->reconciliation_state_metric ?? 'waiting'),
                    'reconciliation_delta_abs' => round((float) ($row->reconciliation_delta_abs_metric ?? 0), 2),
                    'risk_score' => round((float) ($row->risk_score ?? 0), 1),
                    'risk_tone' => ((float) ($row->risk_score ?? 0)) >= 80 ? 'critical' : (((float) ($row->risk_score ?? 0)) >= 50 ? 'high' : 'medium'),
                    'primary_reason' => $reasons[0] ?? 'Kontrol',
                    'action_hint' => $this->orderActionHint($reasons[0] ?? 'Kontrol'),
                    'reasons' => $reasons,
                ];
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function productReadinessInsights(int $userId, array $filters = []): array
    {
        $readiness = $this->costReadiness($userId, $filters);
        $products = $this->productProfitability($userId, $filters, 25);
        $riskProducts = collect($products)->filter(fn (array $product) => (int) ($product['risk_count'] ?? 0) > 0);
        $negativeProducts = collect($products)->filter(fn (array $product) => (float) ($product['profit_value'] ?? 0) < 0);
        $missingCostProducts = collect($products)->filter(fn (array $product) => (int) ($product['missing_cost_lines'] ?? 0) > 0);
        $unmatchedProducts = collect($products)->filter(fn (array $product) => (int) ($product['unmatched_lines'] ?? 0) > 0);
        $affectedRevenue = $riskProducts->sum('gross_revenue');
        $negativePressure = $negativeProducts->sum(fn (array $product) => abs((float) ($product['profit_value'] ?? 0)));
        $productCount = count($products);
        $marginRows = collect([
            [
                'key' => 'loss',
                'label' => 'Negatif marj',
                'tone' => 'rose',
                'items' => collect($products)->filter(fn (array $product) => (float) ($product['profit_margin_percent'] ?? 0) < 0),
            ],
            [
                'key' => 'thin',
                'label' => 'Düşük marj',
                'tone' => 'amber',
                'items' => collect($products)->filter(fn (array $product) => (float) ($product['profit_margin_percent'] ?? 0) >= 0
                    && (float) ($product['profit_margin_percent'] ?? 0) < 15),
            ],
            [
                'key' => 'healthy',
                'label' => 'Sağlıklı marj',
                'tone' => 'emerald',
                'items' => collect($products)->filter(fn (array $product) => (float) ($product['profit_margin_percent'] ?? 0) >= 15),
            ],
        ])->map(function (array $row) use ($productCount) {
            $items = $row['items'];
            unset($row['items']);

            return array_merge($row, [
                'count' => $items->count(),
                'percent' => $this->percentOf($items->count(), $productCount),
                'revenue' => round((float) $items->sum('gross_revenue'), 2),
                'profit' => round((float) $items->sum('profit_value'), 2),
            ]);
        })->values()->all();
        $costRows = [
            [
                'key' => 'cogs',
                'label' => 'COGS',
                'value' => round((float) collect($products)->sum('cogs_cost'), 2),
                'tone' => 'slate',
            ],
            [
                'key' => 'packaging',
                'label' => 'Ambalaj',
                'value' => round((float) collect($products)->sum('packaging_cost'), 2),
                'tone' => 'amber',
            ],
            [
                'key' => 'commission',
                'label' => 'Komisyon',
                'value' => round((float) collect($products)->sum('estimated_commission'), 2),
                'tone' => 'rose',
            ],
        ];
        $totalCost = max(1, (float) collect($costRows)->sum('value'));

        $segments = [
            [
                'label' => 'Hazır',
                'value' => max(0, (int) $readiness['total_lines'] - (int) $readiness['unmatched_lines'] - (int) $readiness['missing_cost_lines']),
                'tone' => 'emerald',
            ],
            [
                'label' => 'Maliyet eksik',
                'value' => (int) $readiness['missing_cost_lines'],
                'tone' => 'amber',
            ],
            [
                'label' => 'Eşleşme eksik',
                'value' => (int) $readiness['unmatched_lines'],
                'tone' => 'rose',
            ],
            [
                'label' => 'Negatif kâr',
                'value' => $negativeProducts->count(),
                'tone' => 'rose',
            ],
        ];

        $totalSegmentValue = max(1, collect($segments)->sum('value'));

        return [
            'ready_percent' => (float) $readiness['ready_percent'],
            'risk_product_count' => $riskProducts->count(),
            'missing_cost_product_count' => $missingCostProducts->count(),
            'unmatched_product_count' => $unmatchedProducts->count(),
            'negative_product_count' => $negativeProducts->count(),
            'affected_revenue' => round((float) $affectedRevenue, 2),
            'negative_pressure' => round((float) $negativePressure, 2),
            'margin_rows' => $marginRows,
            'cost_composition_rows' => collect($costRows)
                ->map(fn (array $row) => $row + [
                    'percent' => $this->percentOf((float) $row['value'], $totalCost),
                ])
                ->all(),
            'top_loss_product' => $negativeProducts->sortBy('profit_value')->first(),
            'top_revenue_product' => collect($products)->sortByDesc('gross_revenue')->first(),
            'segments' => collect($segments)
                ->map(fn (array $segment) => $segment + [
                    'percent' => $this->percentOf((int) $segment['value'], $totalSegmentValue),
                ])
                ->all(),
            'decision_hint' => $this->productDecisionHint($readiness, $negativeProducts->count()),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function productProfitability(int $userId, array $filters = [], int $limit = 10): array
    {
        $orderIds = $this->filteredOrderIdsQuery($userId, $filters);
        $revenueSql = '(COALESCE(NULLIF(channel_order_items.billable_amount, 0), NULLIF(channel_order_items.gross_amount, 0), (COALESCE(channel_order_items.unit_price, 0) * COALESCE(channel_order_items.quantity, 0))) * COALESCE(filtered_orders.exchange_rate, 1.0))';
        $commissionRateSql = 'COALESCE(NULLIF(channel_order_items.commission_rate, 0), mp_products.commission_rate, 0)';
        $productIdSql = 'COALESCE(channel_order_items.mp_product_id, 0)';
        $productNameSql = "COALESCE(mp_products.product_name, channel_order_items.product_name, 'Eşleşmeyen ürün')";
        $stockCodeSql = "COALESCE(mp_products.stock_code, channel_order_items.stock_code, '')";
        $barcodeSql = "COALESCE(mp_products.barcode, channel_order_items.barcode, '')";
        $snapshotDeductionsSql = "CASE
            WHEN order_profit_snapshots.id IS NOT NULL AND COALESCE(order_profit_snapshots.gross_revenue, 0) > 0
            THEN (({$revenueSql}) / order_profit_snapshots.gross_revenue) * (
                COALESCE(order_profit_snapshots.commission_total, 0)
                + COALESCE(order_profit_snapshots.cargo_total, 0)
                + COALESCE(order_profit_snapshots.service_fee_total, 0)
                + COALESCE(order_profit_snapshots.withholding_total, 0)
                + COALESCE(order_profit_snapshots.own_cargo_cost, 0)
                + COALESCE(order_profit_snapshots.vat_effect, 0)
                + COALESCE(order_profit_snapshots.return_effect, 0)
            )
            ELSE ({$revenueSql}) * {$commissionRateSql} / 100
        END";

        return ChannelOrderItem::query()
            ->joinSub($orderIds, 'filtered_orders', fn ($join) => $join->on('filtered_orders.id', '=', 'channel_order_items.channel_order_id'))
            ->leftJoin('mp_products', 'mp_products.id', '=', 'channel_order_items.mp_product_id')
            ->leftJoin('order_profit_snapshots', function ($join) {
                $join->on('order_profit_snapshots.channel_order_id', '=', 'channel_order_items.channel_order_id')
                    ->whereNull('order_profit_snapshots.channel_order_item_id');
            })
            ->selectRaw("
                {$productIdSql} as product_id,
                {$productNameSql} as product_name,
                {$stockCodeSql} as stock_code,
                {$barcodeSql} as barcode,
                COUNT(DISTINCT channel_order_items.channel_order_id) as order_count,
                COUNT(*) as line_count,
                COALESCE(SUM(channel_order_items.quantity), 0) as quantity,
                COALESCE(SUM({$revenueSql}), 0) as gross_revenue,
                COALESCE(SUM(COALESCE(mp_products.cogs, 0) * COALESCE(channel_order_items.quantity, 0)), 0) as cogs_cost,
                COALESCE(SUM(COALESCE(mp_products.packaging_cost, 0) * COALESCE(channel_order_items.quantity, 0)), 0) as packaging_cost,
                COALESCE(SUM(({$revenueSql}) * {$commissionRateSql} / 100), 0) as estimated_commission,
                COALESCE(SUM({$snapshotDeductionsSql}), 0) as allocated_deductions,
                SUM(CASE WHEN channel_order_items.mp_product_id IS NULL THEN 1 ELSE 0 END) as unmatched_lines,
                SUM(CASE WHEN channel_order_items.mp_product_id IS NOT NULL AND (COALESCE(mp_products.cogs, 0) <= 0 OR COALESCE(mp_products.packaging_cost, 0) <= 0) THEN 1 ELSE 0 END) as missing_cost_lines
            ")
            ->groupByRaw("{$productIdSql}, {$productNameSql}, {$stockCodeSql}, {$barcodeSql}")
            ->orderByDesc('gross_revenue')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                $grossRevenue = (float) ($row->gross_revenue ?? 0);
                $totalCost = (float) ($row->cogs_cost ?? 0)
                    + (float) ($row->packaging_cost ?? 0)
                    + (float) ($row->allocated_deductions ?? 0);
                $profitValue = $grossRevenue - $totalCost;
                $riskCount = (int) ($row->unmatched_lines ?? 0) + (int) ($row->missing_cost_lines ?? 0);
                $readinessScore = max(0, min(100, 100 - ($riskCount * 25)));

                return [
                    'product_id' => (int) ($row->product_id ?? 0),
                    'product_name' => (string) ($row->product_name ?? ''),
                    'stock_code' => (string) ($row->stock_code ?? ''),
                    'barcode' => (string) ($row->barcode ?? ''),
                    'order_count' => (int) ($row->order_count ?? 0),
                    'line_count' => (int) ($row->line_count ?? 0),
                    'quantity' => (int) ($row->quantity ?? 0),
                    'gross_revenue' => round($grossRevenue, 2),
                    'estimated_commission' => round((float) ($row->estimated_commission ?? 0), 2),
                    'allocated_deductions' => round((float) ($row->allocated_deductions ?? 0), 2),
                    'cogs_cost' => round((float) ($row->cogs_cost ?? 0), 2),
                    'packaging_cost' => round((float) ($row->packaging_cost ?? 0), 2),
                    'profit_value' => round($profitValue, 2),
                    'profit_margin_percent' => $grossRevenue > 0 ? round(($profitValue / $grossRevenue) * 100, 1) : 0.0,
                    'unmatched_lines' => (int) ($row->unmatched_lines ?? 0),
                    'missing_cost_lines' => (int) ($row->missing_cost_lines ?? 0),
                    'risk_count' => $riskCount,
                    'readiness_score' => $readinessScore,
                    'readiness_label' => $riskCount === 0 ? 'Hazır' : 'Eksik',
                    'decision_hint' => $this->productRowDecisionHint($profitValue, $riskCount, (int) ($row->unmatched_lines ?? 0), (int) ($row->missing_cost_lines ?? 0)),
                ];
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function productCostGapExportRows(int $userId, array $filters = [], int $limit = 500): array
    {
        $orderIds = $this->filteredOrderIdsQuery($userId, $filters);
        $revenueSql = '(COALESCE(NULLIF(channel_order_items.billable_amount, 0), NULLIF(channel_order_items.gross_amount, 0), (COALESCE(channel_order_items.unit_price, 0) * COALESCE(channel_order_items.quantity, 0))) * COALESCE(filtered_orders.exchange_rate, 1.0))';
        $productIdSql = 'COALESCE(channel_order_items.mp_product_id, 0)';
        $productNameSql = "COALESCE(mp_products.product_name, channel_order_items.product_name, 'Eşleşmeyen ürün')";
        $stockCodeSql = "COALESCE(mp_products.stock_code, channel_order_items.stock_code, '')";
        $barcodeSql = "COALESCE(mp_products.barcode, channel_order_items.barcode, '')";
        $unmatchedSql = 'CASE WHEN channel_order_items.mp_product_id IS NULL THEN 1 ELSE 0 END';
        $cogsMissingSql = 'CASE WHEN channel_order_items.mp_product_id IS NOT NULL AND COALESCE(mp_products.cogs, 0) <= 0 THEN 1 ELSE 0 END';
        $packagingMissingSql = 'CASE WHEN channel_order_items.mp_product_id IS NOT NULL AND COALESCE(mp_products.packaging_cost, 0) <= 0 THEN 1 ELSE 0 END';

        $rows = ChannelOrderItem::query()
            ->joinSub($orderIds, 'filtered_orders', fn ($join) => $join->on('filtered_orders.id', '=', 'channel_order_items.channel_order_id'))
            ->leftJoin('mp_products', 'mp_products.id', '=', 'channel_order_items.mp_product_id')
            ->selectRaw("
                {$productIdSql} as product_id,
                {$productNameSql} as product_name,
                {$stockCodeSql} as stock_code,
                {$barcodeSql} as barcode,
                COUNT(DISTINCT channel_order_items.channel_order_id) as order_count,
                COUNT(*) as line_count,
                COALESCE(SUM(channel_order_items.quantity), 0) as quantity,
                COALESCE(SUM({$revenueSql}), 0) as affected_revenue,
                COALESCE(MAX(mp_products.cogs), 0) as cogs,
                COALESCE(MAX(mp_products.packaging_cost), 0) as packaging_cost,
                SUM({$unmatchedSql}) as unmatched_lines,
                SUM({$cogsMissingSql}) as cogs_missing_lines,
                SUM({$packagingMissingSql}) as packaging_missing_lines
            ")
            ->groupByRaw("{$productIdSql}, {$productNameSql}, {$stockCodeSql}, {$barcodeSql}")
            ->havingRaw("SUM({$unmatchedSql}) > 0 OR SUM({$cogsMissingSql}) > 0 OR SUM({$packagingMissingSql}) > 0")
            ->orderByDesc('affected_revenue')
            ->limit(max(1, min(1000, $limit)))
            ->get();

        if ($rows->isEmpty()) {
            return [[
                'Aksiyon' => 'Maliyet veya eşleşme eksiği yok',
                'Ürün' => '',
                'Stok Kodu' => '',
                'Barkod' => '',
                'Sipariş' => 0,
                'Satır' => 0,
                'Adet' => 0,
                'Etkilenen ciro' => 0,
                'Maliyet' => 0,
                'Ambalaj Gideri' => 0,
                'Eşleşmeyen satır' => 0,
                'COGS eksik satır' => 0,
                'Ambalaj eksik satır' => 0,
                'Öncelik' => '',
            ]];
        }

        return $rows
            ->map(fn ($row) => [
                'Aksiyon' => $this->productCostGapAction(
                    (int) ($row->unmatched_lines ?? 0),
                    (int) ($row->cogs_missing_lines ?? 0),
                    (int) ($row->packaging_missing_lines ?? 0),
                ),
                'Ürün' => (string) ($row->product_name ?? ''),
                'Stok Kodu' => (string) ($row->stock_code ?? ''),
                'Barkod' => (string) ($row->barcode ?? ''),
                'Sipariş' => (int) ($row->order_count ?? 0),
                'Satır' => (int) ($row->line_count ?? 0),
                'Adet' => (int) ($row->quantity ?? 0),
                'Etkilenen ciro' => round((float) ($row->affected_revenue ?? 0), 2),
                'Maliyet' => round((float) ($row->cogs ?? 0), 2),
                'Ambalaj Gideri' => round((float) ($row->packaging_cost ?? 0), 2),
                'Eşleşmeyen satır' => (int) ($row->unmatched_lines ?? 0),
                'COGS eksik satır' => (int) ($row->cogs_missing_lines ?? 0),
                'Ambalaj eksik satır' => (int) ($row->packaging_missing_lines ?? 0),
                'Öncelik' => $this->productCostGapPriority((float) ($row->affected_revenue ?? 0)),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array{name: string, data: array<int, array<string, mixed>>}>
     */
    public function managerAnalyticsExportSheets(int $userId, array $filters = []): array
    {
        $orderInsights = $this->orderDecisionInsights($userId, $filters);
        $orderQueue = $this->orderDecisionQueue($userId, $filters, 100);
        $productInsights = $this->productReadinessInsights($userId, $filters);
        $products = $this->productProfitability($userId, $filters, 100);
        $costGaps = $this->productCostGapExportRows($userId, $filters, 500);

        return [
            [
                'name' => 'Siparis Risk Yogunlugu',
                'data' => $this->orderRiskIntensityExportRows($orderInsights),
            ],
            [
                'name' => 'Siparis Karar Kuyrugu',
                'data' => $this->orderDecisionQueueExportRows($orderQueue),
            ],
            [
                'name' => 'Urun Marj Maliyet',
                'data' => $this->productMarginCostExportRows($productInsights),
            ],
            [
                'name' => 'Maliyet Eksikleri',
                'data' => $costGaps,
            ],
            [
                'name' => 'Urun Performans',
                'data' => $this->productProfitabilityExportRows($products),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function baseQuery(int $userId, array $filters = []): Builder
    {
        $itemAggregate = $this->reconciliation->itemAggregate();
        $financialAggregate = $this->reconciliation->financialAggregate();
        $snapshotAggregate = $this->reconciliation->snapshotAggregate();
        $expr = $this->reconciliation->expressions();

        $query = ChannelOrder::query()
            ->select([
                'channel_orders.*',
                'marketplace_stores.id as store_id_alias',
                'marketplace_stores.marketplace as marketplace_alias',
                'marketplace_stores.store_name as store_name_alias',
                'legal_entities.id as legal_entity_id_alias',
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
                DB::raw("{$expr['advertising_total']} as advertising_total_metric"),
                DB::raw("{$expr['penalty_total']} as penalty_total_metric"),
                DB::raw("{$expr['early_payment_total']} as early_payment_total_metric"),
                DB::raw("{$expr['discount_total']} as discount_total_metric"),
                DB::raw("{$expr['other_cost_total']} as other_cost_total_metric"),
                DB::raw("{$expr['withholding_total']} as withholding_total_metric"),
                DB::raw("{$expr['deduction_total']} as deduction_total_metric"),
                DB::raw("{$expr['profit_state']} as profit_state_metric"),
                DB::raw('COALESCE(order_snapshot.estimated_profit, 0) as estimated_profit_metric'),
                DB::raw('COALESCE(order_snapshot.confirmed_profit, 0) as confirmed_profit_metric'),
                DB::raw("{$expr['profitability_ratio']} as margin_percent_metric"),
                DB::raw("{$expr['profit_value']} as profit_value_metric"),
                DB::raw("{$expr['profit_delta']} as profit_delta_metric"),
                DB::raw("{$expr['deduction_delta']} as deduction_delta_metric"),
                DB::raw("{$expr['reconciliation_state']} as reconciliation_state_metric"),
                DB::raw("{$expr['reconciliation_score']} as reconciliation_score_metric"),
                DB::raw("{$expr['reconciliation_abs_delta']} as reconciliation_delta_abs_metric"),
            ])
            ->join('marketplace_stores', 'marketplace_stores.id', '=', 'channel_orders.store_id')
            ->join('legal_entities', 'legal_entities.id', '=', 'channel_orders.legal_entity_id')
            ->leftJoinSub($itemAggregate, 'item_agg', fn ($join) => $join->on('item_agg.channel_order_id', '=', 'channel_orders.id'))
            ->leftJoinSub($financialAggregate, 'fin_agg', fn ($join) => $join->on('fin_agg.channel_order_id', '=', 'channel_orders.id'))
            ->leftJoinSub($snapshotAggregate, 'order_snapshot', fn ($join) => $join->on('order_snapshot.channel_order_id', '=', 'channel_orders.id'))
            ->where('marketplace_stores.user_id', $userId);

        return $this->applyCommonFilters($query, $filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function filteredItemQuery(int $userId, array $filters = []): Builder
    {
        $orderIds = $this->filteredOrderIdsQuery($userId, $filters);

        return ChannelOrderItem::query()
            ->select([
                'channel_order_items.id',
                'channel_order_items.channel_order_id',
                'channel_order_items.mp_product_id',
                DB::raw('CASE WHEN COALESCE(mp_products.cogs, 0) > 0 AND COALESCE(mp_products.packaging_cost, 0) > 0 THEN 1 ELSE 0 END as product_cost_ready'),
            ])
            ->joinSub($orderIds, 'filtered_orders', fn ($join) => $join->on('filtered_orders.id', '=', 'channel_order_items.channel_order_id'))
            ->leftJoin('mp_products', 'mp_products.id', '=', 'channel_order_items.mp_product_id');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{gap_lines: int, affected_revenue: float}
     */
    protected function costGapImpact(int $userId, array $filters = []): array
    {
        $orderIds = $this->filteredOrderIdsQuery($userId, $filters);
        $revenueSql = '(COALESCE(NULLIF(channel_order_items.billable_amount, 0), NULLIF(channel_order_items.gross_amount, 0), (COALESCE(channel_order_items.unit_price, 0) * COALESCE(channel_order_items.quantity, 0))) * COALESCE(filtered_orders.exchange_rate, 1.0))';
        $gapSql = '(channel_order_items.mp_product_id IS NULL OR (channel_order_items.mp_product_id IS NOT NULL AND (COALESCE(mp_products.cogs, 0) <= 0 OR COALESCE(mp_products.packaging_cost, 0) <= 0)))';

        $row = ChannelOrderItem::query()
            ->joinSub($orderIds, 'filtered_orders', fn ($join) => $join->on('filtered_orders.id', '=', 'channel_order_items.channel_order_id'))
            ->leftJoin('mp_products', 'mp_products.id', '=', 'channel_order_items.mp_product_id')
            ->selectRaw("
                SUM(CASE WHEN {$gapSql} THEN 1 ELSE 0 END) as gap_lines,
                COALESCE(SUM(CASE WHEN {$gapSql} THEN {$revenueSql} ELSE 0 END), 0) as affected_revenue
            ")
            ->first();

        return [
            'gap_lines' => (int) ($row->gap_lines ?? 0),
            'affected_revenue' => round((float) ($row->affected_revenue ?? 0), 2),
        ];
    }

    protected function reasonTone(string $reason): string
    {
        return match ($reason) {
            'Negatif kâr', 'Yüksek mutabakat farkı' => 'rose',
            'Finans bekliyor', 'Kâr kaydı eksik', 'Eşleşme eksik' => 'amber',
            default => 'slate',
        };
    }

    protected function orderDecisionHint(string $reason): string
    {
        return match ($reason) {
            'Negatif kâr' => 'Önce negatif kâr üreten siparişlerde maliyet, kesinti ve iade etkisini ayrıştırın.',
            'Yüksek mutabakat farkı' => 'Tahmini snapshot ile kesin finans sonucunu karşılaştırıp farkın kaynağını kapatın.',
            'Finans bekliyor' => 'Hakediş/finans hareketi oluşmayan siparişleri pazaryeri ve mağaza bazında netleştirin.',
            'Kâr kaydı eksik' => 'Snapshot üretimi eksik siparişleri yeniden hesaplatıp mutabakata dahil edin.',
            'Eşleşme eksik' => 'Ürün eşleşmesi eksik satırları tamamlayıp maliyet güvenini yükseltin.',
            default => 'Seçili aralıkta kritik sipariş baskısı düşük; periyodik kontrol yeterli.',
        };
    }

    protected function orderActionHint(string $reason): string
    {
        return match ($reason) {
            'Negatif kâr' => 'Maliyet ve iade etkisini kontrol et',
            'Yüksek mutabakat farkı' => 'Finans farkını kapat',
            'Finans bekliyor' => 'Hakediş durumunu kontrol et',
            'Kâr kaydı eksik' => 'Snapshot üretimini yenile',
            'Eşleşme eksik' => 'Ürün eşleşmesini tamamla',
            default => 'Siparişi kontrol et',
        };
    }

    /**
     * @param  array<string, mixed>  $readiness
     */
    protected function productDecisionHint(array $readiness, int $negativeProductCount): string
    {
        if ((int) ($readiness['unmatched_lines'] ?? 0) > 0) {
            return 'Önce eşleşmeyen ürün satırlarını tamamlayın; aksi halde maliyet ve kâr güveni düşük kalır.';
        }

        if ((int) ($readiness['missing_cost_lines'] ?? 0) > 0) {
            return 'COGS veya ambalaj maliyeti eksik ürünleri tamamlayıp kâr hesabını kesinleştirin.';
        }

        if ($negativeProductCount > 0) {
            return 'Negatif kâr üreten ürünlerde fiyat, komisyon ve maliyet varsayımlarını yeniden kontrol edin.';
        }

        return 'Ürün maliyet hazırlığı sağlıklı; şimdi düşük marjlı ürünlerde fiyat optimizasyonuna odaklanabilirsiniz.';
    }

    protected function productRowDecisionHint(float $profitValue, int $riskCount, int $unmatchedLines, int $missingCostLines): string
    {
        if ($unmatchedLines > 0) {
            return 'Önce eşleşme aç';
        }

        if ($missingCostLines > 0) {
            return 'Maliyeti tamamla';
        }

        if ($profitValue < 0) {
            return 'Fiyat/maliyet gözden geçir';
        }

        if ($riskCount > 0) {
            return 'Veri kalitesini kontrol et';
        }

        return 'Hazır';
    }

    protected function productCostGapAction(int $unmatchedLines, int $cogsMissingLines, int $packagingMissingLines): string
    {
        if ($unmatchedLines > 0) {
            return 'Eşleştirme merkezinde master ürüne bağla';
        }

        if ($cogsMissingLines > 0 && $packagingMissingLines > 0) {
            return 'COGS ve ambalaj maliyetini tamamla';
        }

        if ($cogsMissingLines > 0) {
            return 'COGS maliyetini tamamla';
        }

        if ($packagingMissingLines > 0) {
            return 'Ambalaj maliyetini tamamla';
        }

        return 'Kontrol et';
    }

    protected function productCostGapPriority(float $affectedRevenue): string
    {
        if ($affectedRevenue >= 100000) {
            return 'Yüksek';
        }

        if ($affectedRevenue >= 25000) {
            return 'Orta';
        }

        return 'Düşük';
    }

    /**
     * @param  array<string, mixed>  $orderInsights
     * @return array<int, array<string, mixed>>
     */
    protected function orderRiskIntensityExportRows(array $orderInsights): array
    {
        $rows = [
            [
                'Kategori' => 'Özet',
                'Başlık' => 'Karar kuyruğu',
                'Adet' => (int) ($orderInsights['queue_count'] ?? 0),
                'Pay %' => '',
                'Skor' => (float) ($orderInsights['average_score'] ?? 0),
                'Baskı' => (float) ($orderInsights['risk_exposure'] ?? 0),
                'Açıklama' => (string) ($orderInsights['decision_hint'] ?? ''),
            ],
            [
                'Kategori' => 'Özet',
                'Başlık' => 'En yüksek skor',
                'Adet' => (int) ($orderInsights['critical_order_count'] ?? 0),
                'Pay %' => '',
                'Skor' => (float) ($orderInsights['highest_score'] ?? 0),
                'Baskı' => '',
                'Açıklama' => (string) ($orderInsights['top_reason'] ?? ''),
            ],
        ];

        foreach ((array) ($orderInsights['risk_lane_rows'] ?? []) as $row) {
            $rows[] = [
                'Kategori' => 'Risk bandı',
                'Başlık' => (string) ($row['label'] ?? ''),
                'Adet' => (int) ($row['count'] ?? 0),
                'Pay %' => (float) ($row['percent'] ?? 0),
                'Skor' => (float) ($row['average_score'] ?? 0),
                'Baskı' => (float) ($row['exposure'] ?? 0),
                'Açıklama' => 'Skor bandına göre karar kuyruğu yoğunluğu.',
            ];
        }

        foreach ((array) ($orderInsights['reason_distribution'] ?? []) as $row) {
            $rows[] = [
                'Kategori' => 'Kök neden',
                'Başlık' => (string) ($row['label'] ?? ''),
                'Adet' => (int) ($row['count'] ?? 0),
                'Pay %' => (float) ($row['percent'] ?? 0),
                'Skor' => '',
                'Baskı' => '',
                'Açıklama' => 'Karar kuyruğundaki risk nedeni.',
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $orderQueue
     * @return array<int, array<string, mixed>>
     */
    protected function orderDecisionQueueExportRows(array $orderQueue): array
    {
        if ($orderQueue === []) {
            return [[
                'Sipariş No' => 'Aksiyon gerektiren sipariş yok',
                'Pazaryeri' => '',
                'Mağaza' => '',
                'Risk skoru' => 0,
                'Risk seviyesi' => '',
                'Birincil neden' => '',
                'Aksiyon' => '',
                'Nedenler' => '',
                'Ciro' => 0,
                'Kâr' => 0,
                'Kesinti' => 0,
                'Mutabakat farkı' => 0,
            ]];
        }

        return array_map(fn (array $order) => [
            'Sipariş No' => (string) ($order['order_number'] ?? ''),
            'Pazaryeri' => (string) ($order['marketplace'] ?? ''),
            'Mağaza' => (string) ($order['store_name'] ?? ''),
            'Risk skoru' => (float) ($order['risk_score'] ?? 0),
            'Risk seviyesi' => (string) ($order['risk_tone'] ?? ''),
            'Birincil neden' => (string) ($order['primary_reason'] ?? ''),
            'Aksiyon' => (string) ($order['action_hint'] ?? ''),
            'Nedenler' => implode(' | ', is_array($order['reasons'] ?? null) ? $order['reasons'] : []),
            'Ciro' => (float) ($order['gross_revenue'] ?? 0),
            'Kâr' => (float) ($order['profit_value'] ?? 0),
            'Kesinti' => (float) ($order['deduction_total'] ?? 0),
            'Mutabakat farkı' => (float) ($order['reconciliation_delta_abs'] ?? 0),
        ], $orderQueue);
    }

    /**
     * @param  array<string, mixed>  $productInsights
     * @return array<int, array<string, mixed>>
     */
    protected function productMarginCostExportRows(array $productInsights): array
    {
        $rows = [
            [
                'Kategori' => 'Özet',
                'Başlık' => 'Riskli ürün',
                'Adet' => (int) ($productInsights['risk_product_count'] ?? 0),
                'Pay %' => '',
                'Ciro' => (float) ($productInsights['affected_revenue'] ?? 0),
                'Kâr' => -1 * (float) ($productInsights['negative_pressure'] ?? 0),
                'Maliyet' => '',
                'Açıklama' => (string) ($productInsights['decision_hint'] ?? ''),
            ],
        ];

        foreach ((array) ($productInsights['margin_rows'] ?? []) as $row) {
            $rows[] = [
                'Kategori' => 'Marj dağılımı',
                'Başlık' => (string) ($row['label'] ?? ''),
                'Adet' => (int) ($row['count'] ?? 0),
                'Pay %' => (float) ($row['percent'] ?? 0),
                'Ciro' => (float) ($row['revenue'] ?? 0),
                'Kâr' => (float) ($row['profit'] ?? 0),
                'Maliyet' => '',
                'Açıklama' => 'Ürün kâr marjı bandı.',
            ];
        }

        foreach ((array) ($productInsights['cost_composition_rows'] ?? []) as $row) {
            $rows[] = [
                'Kategori' => 'Maliyet bileşimi',
                'Başlık' => (string) ($row['label'] ?? ''),
                'Adet' => '',
                'Pay %' => (float) ($row['percent'] ?? 0),
                'Ciro' => '',
                'Kâr' => '',
                'Maliyet' => (float) ($row['value'] ?? 0),
                'Açıklama' => 'Listelenen ürünlerin maliyet/komisyon bileşeni.',
            ];
        }

        foreach ((array) ($productInsights['segments'] ?? []) as $row) {
            $rows[] = [
                'Kategori' => 'Veri hazırlığı',
                'Başlık' => (string) ($row['label'] ?? ''),
                'Adet' => (int) ($row['value'] ?? 0),
                'Pay %' => (float) ($row['percent'] ?? 0),
                'Ciro' => '',
                'Kâr' => '',
                'Maliyet' => '',
                'Açıklama' => 'Ürün maliyet/eşleşme hazırlığı.',
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @return array<int, array<string, mixed>>
     */
    protected function productProfitabilityExportRows(array $products): array
    {
        if ($products === []) {
            return [[
                'Ürün' => 'Ürün kârlılık verisi yok',
                'Stok' => '',
                'Barkod' => '',
                'Sipariş' => 0,
                'Satır' => 0,
                'Adet' => 0,
                'Ciro' => 0,
                'COGS' => 0,
                'Ambalaj' => 0,
                'Komisyon' => 0,
                'Tahmini kâr' => 0,
                'Marj %' => 0,
                'Eşleşmeyen' => 0,
                'Eksik maliyet' => 0,
                'Risk' => 0,
                'Hazırlık %' => 0,
                'Karar' => '',
            ]];
        }

        return array_map(fn (array $product) => [
            'Ürün' => (string) ($product['product_name'] ?? ''),
            'Stok' => (string) ($product['stock_code'] ?? ''),
            'Barkod' => (string) ($product['barcode'] ?? ''),
            'Sipariş' => (int) ($product['order_count'] ?? 0),
            'Satır' => (int) ($product['line_count'] ?? 0),
            'Adet' => (int) ($product['quantity'] ?? 0),
            'Ciro' => (float) ($product['gross_revenue'] ?? 0),
            'COGS' => (float) ($product['cogs_cost'] ?? 0),
            'Ambalaj' => (float) ($product['packaging_cost'] ?? 0),
            'Komisyon' => (float) ($product['estimated_commission'] ?? 0),
            'Tahmini kâr' => (float) ($product['profit_value'] ?? 0),
            'Marj %' => (float) ($product['profit_margin_percent'] ?? 0),
            'Eşleşmeyen' => (int) ($product['unmatched_lines'] ?? 0),
            'Eksik maliyet' => (int) ($product['missing_cost_lines'] ?? 0),
            'Risk' => (int) ($product['risk_count'] ?? 0),
            'Hazırlık %' => (float) ($product['readiness_score'] ?? 0),
            'Karar' => (string) ($product['decision_hint'] ?? ''),
        ], $products);
    }

    protected function commandScoreLabel(float $score): string
    {
        if ($score >= 80) {
            return 'Sağlıklı';
        }

        if ($score >= 65) {
            return 'İzlenmeli';
        }

        if ($score >= 50) {
            return 'Dikkat';
        }

        return 'Kritik';
    }

    protected function commandScoreTone(float $score): string
    {
        if ($score >= 80) {
            return 'emerald';
        }

        if ($score >= 65) {
            return 'slate';
        }

        if ($score >= 50) {
            return 'amber';
        }

        return 'rose';
    }

    protected function commandHeadline(float $score): string
    {
        if ($score >= 80) {
            return 'Kâr, finans ve maliyet hazırlığı kontrol altında.';
        }

        if ($score >= 65) {
            return 'Genel tablo yönetilebilir; birkaç odak başlığı izlenmeli.';
        }

        if ($score >= 50) {
            return 'Karar baskısı orta seviyede; öncelik sırası netleştirilmeli.';
        }

        return 'Karar baskısı yüksek; zarar, mutabakat veya maliyet kaynakları hızla ele alınmalı.';
    }

    /**
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array<string, mixed>
     */
    protected function commandFocusCandidate(array $candidates): array
    {
        usort($candidates, fn (array $first, array $second) => (float) $second['score'] <=> (float) $first['score']);

        return $candidates[0] ?? [
            'key' => 'profit_center',
            'label' => 'Genel takip',
            'score' => 0,
            'value' => 0,
            'route' => 'mp.finance',
            'query' => [],
            'action_label' => 'Finans listesini aç',
            'hint' => 'Seçili aralıkta kritik baskı düşük; periyodik kontrol yeterli.',
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function filteredOrderIdsQuery(int $userId, array $filters = []): Builder
    {
        $query = ChannelOrder::query()
            ->select('channel_orders.id', 'channel_orders.exchange_rate')
            ->join('marketplace_stores', 'marketplace_stores.id', '=', 'channel_orders.store_id')
            ->join('legal_entities', 'legal_entities.id', '=', 'channel_orders.legal_entity_id')
            ->where('marketplace_stores.user_id', $userId);

        return $this->applyCommonFilters($query, $filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function applyCommonFilters(Builder $query, array $filters): Builder
    {
        if ($this->filled($filters, 'marketplace')) {
            $query->where('marketplace_stores.marketplace', (string) $filters['marketplace']);
        }

        if ($this->filled($filters, 'store_id')) {
            $query->where('channel_orders.store_id', (int) $filters['store_id']);
        }

        if ($this->filled($filters, 'legal_entity_id')) {
            $query->where('channel_orders.legal_entity_id', (int) $filters['legal_entity_id']);
        }

        if ($this->filled($filters, 'date_from')) {
            $query->whereDate('channel_orders.ordered_at', '>=', (string) $filters['date_from']);
        }

        if ($this->filled($filters, 'date_to')) {
            $query->whereDate('channel_orders.ordered_at', '<=', (string) $filters['date_to']);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function filled(array $filters, string $key): bool
    {
        return array_key_exists($key, $filters)
            && $filters[$key] !== null
            && $filters[$key] !== '';
    }

    protected function percentOf(int|float $value, int|float $total): float
    {
        return $total > 0 ? round(((float) $value / (float) $total) * 100, 1) : 0.0;
    }
}
