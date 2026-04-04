<?php

namespace App\Services\Marketplace;

use App\Models\ChannelOrderItem;
use App\Models\OrderFinancialEvent;
use App\Models\OrderProfitSnapshot;
use Illuminate\Database\Eloquent\Builder;

class MarketplaceReconciliationQueryService
{
    public function itemAggregate(): Builder
    {
        return ChannelOrderItem::query()
            ->selectRaw('
                channel_order_id,
                COUNT(*) as item_lines_count,
                COALESCE(SUM(quantity), 0) as total_quantity,
                COALESCE(SUM(CASE WHEN is_matched = 1 THEN 1 ELSE 0 END), 0) as matched_lines_count,
                COALESCE(SUM(gross_amount), 0) as gross_items_total,
                COALESCE(SUM((
                    COALESCE(NULLIF(billable_amount, 0), NULLIF(gross_amount, 0), (COALESCE(unit_price, 0) * COALESCE(quantity, 0)))
                    * COALESCE(commission_rate, 0) / 100
                )), 0) as estimated_commission_total
            ')
            ->groupBy('channel_order_id');
    }

    public function financialAggregate(): Builder
    {
        $signedAmountSql = "CASE WHEN direction = 'credit' THEN ABS(amount) ELSE -ABS(amount) END";

        return OrderFinancialEvent::query()
            ->selectRaw("
                channel_order_id,
                COUNT(*) as financial_event_count,
                MAX(COALESCE(settlement_date, event_date)) as last_financial_event_at,
                COALESCE(SUM(CASE WHEN event_type = 'seller_revenue' THEN {$signedAmountSql} ELSE 0 END), 0) as seller_revenue_metric,
                COALESCE(SUM(CASE WHEN event_type = 'commission' THEN {$signedAmountSql} ELSE 0 END), 0) as commission_net_metric,
                COALESCE(SUM(CASE WHEN event_type = 'cargo' THEN {$signedAmountSql} ELSE 0 END), 0) as cargo_net_metric,
                COALESCE(SUM(CASE WHEN event_type IN ('service_fee', 'deduction_invoice') THEN {$signedAmountSql} ELSE 0 END), 0) as service_fee_net_metric,
                COALESCE(SUM(CASE WHEN event_type = 'withholding' THEN {$signedAmountSql} ELSE 0 END), 0) as withholding_net_metric
            ")
            ->groupBy('channel_order_id');
    }

    public function snapshotAggregate(): Builder
    {
        return OrderProfitSnapshot::query()
            ->select([
                'channel_order_id',
                'profit_state',
                'gross_revenue',
                'net_receivable',
                'commission_total',
                'cargo_total',
                'service_fee_total',
                'withholding_total',
                'estimated_profit',
                'confirmed_profit',
                'margin_percent',
                'return_effect',
                'calculated_at',
            ])
            ->whereNull('channel_order_item_id');
    }

    /**
     * @return array<string, string>
     */
    public function expressions(): array
    {
        $hasFinanceSql = 'COALESCE(fin_agg.financial_event_count, 0) > 0';
        $hasSnapshotSql = 'order_snapshot.channel_order_id IS NOT NULL';

        $grossRevenueSql = 'COALESCE(order_snapshot.gross_revenue, item_agg.gross_items_total, 0)';
        $netReceivableSql = 'COALESCE(order_snapshot.net_receivable, fin_agg.seller_revenue_metric + fin_agg.commission_net_metric + fin_agg.cargo_net_metric + fin_agg.service_fee_net_metric + fin_agg.withholding_net_metric, 0)';
        $estimatedCommissionSql = 'COALESCE(item_agg.estimated_commission_total, 0)';
        $commissionTotalSql = 'COALESCE(order_snapshot.commission_total, ABS(LEAST(COALESCE(fin_agg.commission_net_metric, 0), 0)), 0)';
        $cargoTotalSql = 'COALESCE(order_snapshot.cargo_total, ABS(LEAST(COALESCE(fin_agg.cargo_net_metric, 0), 0)), 0)';
        $serviceFeeTotalSql = 'COALESCE(order_snapshot.service_fee_total, ABS(LEAST(COALESCE(fin_agg.service_fee_net_metric, 0), 0)), 0)';
        $withholdingTotalSql = 'COALESCE(order_snapshot.withholding_total, ABS(LEAST(COALESCE(fin_agg.withholding_net_metric, 0), 0)), 0)';
        $deductionTotalSql = "({$commissionTotalSql} + {$cargoTotalSql} + {$serviceFeeTotalSql} + {$withholdingTotalSql})";
        $profitStateSql = "COALESCE(order_snapshot.profit_state, CASE WHEN {$hasFinanceSql} THEN 'confirmed' ELSE 'estimated' END)";
        $profitValueSql = "CASE WHEN {$profitStateSql} = 'confirmed' THEN COALESCE(order_snapshot.confirmed_profit, 0) ELSE COALESCE(order_snapshot.estimated_profit, 0) END";
        $profitDeltaSql = "CASE WHEN {$hasFinanceSql} AND {$hasSnapshotSql} THEN COALESCE(order_snapshot.confirmed_profit, 0) - COALESCE(order_snapshot.estimated_profit, 0) ELSE 0 END";
        $deductionDeltaSql = "CASE WHEN {$hasFinanceSql} THEN ({$deductionTotalSql}) - {$estimatedCommissionSql} ELSE 0 END";
        $minorThresholdSql = "GREATEST(50, ({$grossRevenueSql} * 0.03))";
        $reconciliationStateSql = "
            CASE
                WHEN NOT ({$hasFinanceSql}) THEN 'waiting'
                WHEN NOT ({$hasSnapshotSql}) THEN 'snapshot_missing'
                WHEN ABS({$profitDeltaSql}) <= 10 AND ABS({$deductionDeltaSql}) <= 10 THEN 'aligned'
                WHEN ABS({$profitDeltaSql}) <= {$minorThresholdSql} AND ABS({$deductionDeltaSql}) <= {$minorThresholdSql} THEN 'minor'
                ELSE 'material'
            END
        ";
        $reconciliationScoreSql = "
            CASE
                WHEN NOT ({$hasFinanceSql}) THEN 0
                WHEN NOT ({$hasSnapshotSql}) THEN 1
                WHEN ABS({$profitDeltaSql}) <= 10 AND ABS({$deductionDeltaSql}) <= 10 THEN 2
                WHEN ABS({$profitDeltaSql}) <= {$minorThresholdSql} AND ABS({$deductionDeltaSql}) <= {$minorThresholdSql} THEN 3
                ELSE 4
            END
        ";
        $reconciliationAbsDeltaSql = "GREATEST(ABS({$profitDeltaSql}), ABS({$deductionDeltaSql}))";

        return [
            'has_finance' => $hasFinanceSql,
            'has_snapshot' => $hasSnapshotSql,
            'gross_revenue' => $grossRevenueSql,
            'net_receivable' => $netReceivableSql,
            'estimated_commission' => $estimatedCommissionSql,
            'commission_total' => $commissionTotalSql,
            'cargo_total' => $cargoTotalSql,
            'service_fee_total' => $serviceFeeTotalSql,
            'withholding_total' => $withholdingTotalSql,
            'deduction_total' => $deductionTotalSql,
            'profit_state' => $profitStateSql,
            'profit_value' => $profitValueSql,
            'profit_delta' => $profitDeltaSql,
            'deduction_delta' => $deductionDeltaSql,
            'minor_threshold' => $minorThresholdSql,
            'reconciliation_state' => $reconciliationStateSql,
            'reconciliation_score' => $reconciliationScoreSql,
            'reconciliation_abs_delta' => $reconciliationAbsDeltaSql,
        ];
    }
}
