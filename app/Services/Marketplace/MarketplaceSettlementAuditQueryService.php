<?php

namespace App\Services\Marketplace;

use App\Models\CargoInvoiceLine;
use App\Models\OrderFinancialEvent;
use App\Models\Shipment;
use App\Services\Marketplace\Support\FinancialEventClassifier;
use App\Services\MpSettingsService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MarketplaceSettlementAuditQueryService
{
    public function __construct(
        protected MarketplaceProfitCenterQueryService $profitCenter,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function audit(int $userId, array $filters = [], int $queueLimit = 120): array
    {
        $tolerances = $this->tolerances($userId);
        $rows = $this->rows($userId, $filters)
            ->map(fn ($row) => $this->classifyRow($row, $tolerances))
            ->filter(fn (array $row) => $row['risks'] !== [])
            ->when(
                $this->filled($filters, 'risk_type'),
                fn ($collection) => $collection->filter(
                    fn (array $row) => collect($row['risks'])->contains(
                        fn (array $risk) => $risk['key'] === (string) $filters['risk_type']
                    )
                )
            )
            ->sortByDesc(fn (array $row) => [
                $row['severity_score'],
                $row['potential_recovery'],
                $row['ordered_at'],
            ])
            ->values();

        $serviceFeeTrend = $this->serviceFeeTrend($userId, $filters, $tolerances);
        $orphanInvoices = $this->orphanInvoiceSummary($userId, $filters);
        $breakdown = $this->riskBreakdown($rows, $serviceFeeTrend, $orphanInvoices);

        return [
            'summary' => [
                'review_order_count' => $rows->count(),
                'critical_order_count' => $rows->where('severity', 'critical')->count(),
                'potential_recovery' => round((float) $rows->sum('potential_recovery'), 2),
                'waiting_settlement_count' => $this->riskCount($rows, 'waiting_settlement'),
                'commission_difference_count' => $this->riskCount($rows, 'commission_difference'),
                'cargo_difference_count' => $this->riskCount($rows, 'cargo_amount_difference'),
                'desi_difference_count' => $this->riskCount($rows, 'desi_difference'),
                'missing_shipment_count' => $this->riskCount($rows, 'missing_shipment'),
                'orphan_invoice_count' => (int) $orphanInvoices['count'],
                'service_fee_increase' => (bool) $serviceFeeTrend['is_increase'],
            ],
            'risk_breakdown' => $breakdown,
            'queue' => $rows->take($queueLimit)->all(),
            'queue_total' => $rows->count(),
            'tolerances' => $tolerances,
            'service_fee_trend' => $serviceFeeTrend,
            'orphan_invoices' => $orphanInvoices,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function appealExportRows(int $userId, array $filters = []): array
    {
        $audit = $this->audit($userId, $filters, PHP_INT_MAX);

        return collect($audit['queue'])
            ->map(function (array $row): array {
                return [
                    'Sipariş No' => $row['order_number'],
                    'Pazaryeri' => $row['marketplace'],
                    'Mağaza' => $row['store_name'],
                    'Firma' => $row['legal_entity_name'],
                    'Sipariş Tarihi' => $row['ordered_at'],
                    'Durum' => $row['order_status'],
                    'Önem' => $row['severity_label'],
                    'Riskler' => collect($row['risks'])->pluck('label')->implode(', '),
                    'Beklenen Komisyon' => $row['estimated_commission'],
                    'Kesilen Komisyon' => $row['actual_commission'],
                    'Komisyon Farkı' => $row['commission_delta'],
                    'Beklenen Kargo' => $row['expected_cargo'],
                    'Fatura Kargo' => $row['invoice_cargo'],
                    'Kargo Farkı' => $row['cargo_delta'],
                    'Beklenen Desi' => $row['expected_desi'],
                    'Fatura Desi' => $row['invoice_desi'],
                    'Desi Farkı' => $row['desi_delta'],
                    'Toplam Kesinti' => $row['deduction_total'],
                    'Kesinti Farkı' => $row['deduction_delta'],
                    'Ceza ve Diğer Fatura' => $row['penalty_other_total'],
                    'İade Kargo' => $row['return_cargo_total'],
                    'Potansiyel İade' => $row['potential_recovery'],
                    'Kargo Firması' => $row['carrier_name'],
                    'Takip No' => $row['tracking_number'],
                    'Fatura No' => $row['invoice_number'],
                    'Son Finans Tarihi' => $row['last_financial_event_at'],
                    'Önerilen Aksiyon' => $row['action_hint'],
                ];
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function rows(int $userId, array $filters)
    {
        $baseQuery = $this->profitCenter->baseQuery($userId, $filters);
        $shipmentAggregate = $this->shipmentAggregate();
        $invoiceAggregate = $this->invoiceAggregate();
        $exceptionAggregate = $this->exceptionFinancialAggregate();

        $query = DB::query()
            ->fromSub((clone $baseQuery)->reorder(), 'audit_base')
            ->leftJoinSub($shipmentAggregate, 'shipment_agg', function ($join) {
                $join->on('shipment_agg.channel_order_id', '=', 'audit_base.id');
            })
            ->leftJoinSub($invoiceAggregate, 'invoice_agg', function ($join) {
                $join->on('invoice_agg.channel_order_id', '=', 'audit_base.id');
            })
            ->leftJoinSub($exceptionAggregate, 'exception_agg', function ($join) {
                $join->on('exception_agg.channel_order_id', '=', 'audit_base.id');
            })
            ->select([
                'audit_base.id',
                'audit_base.order_number',
                'audit_base.order_status',
                'audit_base.ordered_at',
                'audit_base.store_id_alias',
                'audit_base.store_name_alias',
                'audit_base.marketplace_alias',
                'audit_base.legal_entity_id_alias',
                'audit_base.legal_entity_name_alias',
                'audit_base.financial_event_count',
                'audit_base.last_financial_event_at',
                'audit_base.gross_revenue_metric',
                'audit_base.estimated_commission_metric',
                'audit_base.commission_total_metric',
                'audit_base.cargo_total_metric',
                'audit_base.service_fee_total_metric',
                'audit_base.deduction_total_metric',
                'audit_base.deduction_delta_metric',
                'audit_base.reconciliation_state_metric',
                DB::raw('COALESCE(shipment_agg.shipment_count, 0) as shipment_count'),
                DB::raw('COALESCE(shipment_agg.expected_desi, 0) as expected_desi'),
                DB::raw('COALESCE(shipment_agg.expected_cargo, 0) as shipment_expected_cargo'),
                DB::raw('COALESCE(shipment_agg.actual_cargo, 0) as shipment_actual_cargo'),
                DB::raw('COALESCE(shipment_agg.carrier_name, "") as carrier_name'),
                DB::raw('COALESCE(shipment_agg.tracking_number, "") as tracking_number'),
                DB::raw('COALESCE(invoice_agg.invoice_count, 0) as invoice_count'),
                DB::raw('COALESCE(invoice_agg.invoice_desi, 0) as invoice_desi'),
                DB::raw('COALESCE(invoice_agg.invoice_cargo, 0) as invoice_cargo'),
                DB::raw('COALESCE(invoice_agg.invoice_number, "") as invoice_number'),
                DB::raw('COALESCE(exception_agg.penalty_other_total, 0) as penalty_other_total'),
                DB::raw('COALESCE(exception_agg.return_cargo_total, 0) as return_cargo_total'),
                DB::raw('COALESCE(exception_agg.actual_commission_total, 0) as actual_commission_total'),
            ]);

        if ($this->filled($filters, 'search')) {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $builder) use ($search) {
                $builder->where('audit_base.order_number', 'like', '%' . $search . '%')
                    ->orWhere('audit_base.store_name_alias', 'like', '%' . $search . '%')
                    ->orWhere('shipment_agg.tracking_number', 'like', '%' . $search . '%')
                    ->orWhere('invoice_agg.invoice_number', 'like', '%' . $search . '%');
            });
        }

        return $query->get();
    }

    protected function shipmentAggregate(): \Illuminate\Database\Eloquent\Builder
    {
        return Shipment::query()
            ->selectRaw('
                channel_order_id,
                COUNT(*) as shipment_count,
                COALESCE(SUM(total_desi), 0) as expected_desi,
                COALESCE(SUM(expected_cost), 0) as expected_cargo,
                COALESCE(SUM(CASE WHEN invoice_cost > 0 THEN invoice_cost WHEN actual_cost > 0 THEN actual_cost ELSE 0 END), 0) as actual_cargo,
                MAX(carrier_name) as carrier_name,
                MAX(tracking_number) as tracking_number
            ')
            ->whereNotNull('channel_order_id')
            ->groupBy('channel_order_id');
    }

    protected function invoiceAggregate(): \Illuminate\Database\Eloquent\Builder
    {
        return CargoInvoiceLine::query()
            ->join('shipments', 'shipments.id', '=', 'cargo_invoice_lines.shipment_id')
            ->selectRaw('
                shipments.channel_order_id,
                COUNT(cargo_invoice_lines.id) as invoice_count,
                COALESCE(SUM(cargo_invoice_lines.desi), 0) as invoice_desi,
                COALESCE(SUM(CASE WHEN cargo_invoice_lines.total_amount > 0 THEN cargo_invoice_lines.total_amount ELSE cargo_invoice_lines.amount END), 0) as invoice_cargo,
                MAX(cargo_invoice_lines.invoice_number) as invoice_number
            ')
            ->whereNotNull('shipments.channel_order_id')
            ->groupBy('shipments.channel_order_id');
    }

    protected function exceptionFinancialAggregate(): \Illuminate\Database\Eloquent\Builder
    {
        $settledSql = FinancialEventClassifier::settledEventSql();
        $penaltyTypes = FinancialEventClassifier::quotedTypes(array_merge(
            FinancialEventClassifier::PENALTY_EVENT_TYPES,
            ['deduction_invoice', 'other_invoice']
        ));
        $returnCargoTypes = FinancialEventClassifier::quotedTypes(['return_cargo']);
        $commissionTypes = FinancialEventClassifier::quotedTypes(FinancialEventClassifier::COMMISSION_EVENT_TYPES);
        $signedAmountSql = "CASE WHEN direction = 'credit' THEN ABS(amount) ELSE -ABS(amount) END";

        return OrderFinancialEvent::query()
            ->selectRaw("
                channel_order_id,
                COALESCE(SUM(CASE WHEN {$settledSql} AND event_type IN ({$penaltyTypes}) AND direction = 'debit' THEN ABS(amount) ELSE 0 END), 0) as penalty_other_total,
                COALESCE(SUM(CASE WHEN {$settledSql} AND event_type IN ({$returnCargoTypes}) AND direction = 'debit' THEN ABS(amount) ELSE 0 END), 0) as return_cargo_total,
                ABS(LEAST(COALESCE(SUM(CASE WHEN {$settledSql} AND event_type IN ({$commissionTypes}) THEN {$signedAmountSql} ELSE 0 END), 0), 0)) as actual_commission_total
            ")
            ->whereNotNull('channel_order_id')
            ->groupBy('channel_order_id');
    }

    /**
     * @param  object  $row
     * @param  array<string, float>  $tolerances
     * @return array<string, mixed>
     */
    protected function classifyRow(object $row, array $tolerances): array
    {
        $estimatedCommission = (float) $row->estimated_commission_metric;
        $actualCommission = (float) $row->actual_commission_total;
        $commissionDelta = round($actualCommission - $estimatedCommission, 2);
        $expectedCargo = (float) $row->shipment_expected_cargo;

        if ($expectedCargo <= 0) {
            $expectedCargo = (float) $row->cargo_total_metric;
        }

        $invoiceCargo = (float) $row->invoice_cargo;
        if ($invoiceCargo <= 0) {
            $invoiceCargo = (float) $row->shipment_actual_cargo;
        }

        $cargoDelta = round($invoiceCargo - $expectedCargo, 2);
        $desiDelta = round((float) $row->invoice_desi - (float) $row->expected_desi, 2);
        $deductionDelta = round((float) $row->deduction_delta_metric, 2);
        $risks = [];

        if ((int) $row->financial_event_count === 0 && ! $this->isCancelled((string) $row->order_status)) {
            $risks[] = $this->risk(
                'waiting_settlement',
                'Hakediş bekliyor',
                'Sipariş için kesinleşmiş finans/hakediş hareketi bulunmuyor.',
                0,
                2
            );
        }

        if ((int) $row->financial_event_count > 0 && abs($commissionDelta) > $tolerances['commission']) {
            $risks[] = $this->risk(
                'commission_difference',
                'Komisyon farkı',
                'Tahmini komisyon ile kesin kesinti toleransın dışında.',
                max(0, $commissionDelta),
                $commissionDelta > $tolerances['commission'] * 3 ? 3 : 2
            );
        }

        if ((int) $row->shipment_count === 0 && ! $this->isCancelled((string) $row->order_status)) {
            $risks[] = $this->risk(
                'missing_shipment',
                'Sevkiyat kaydı eksik',
                'Sipariş ile ilişkilendirilmiş sevkiyat kaydı bulunmuyor.',
                0,
                2
            );
        }

        if ((int) $row->invoice_count > 0 && abs($cargoDelta) > $tolerances['cargo']) {
            $risks[] = $this->risk(
                'cargo_amount_difference',
                'Kargo tutar farkı',
                'Kargo faturası ile beklenen kargo maliyeti toleransın dışında.',
                max(0, $cargoDelta),
                $cargoDelta > $tolerances['cargo'] * 3 ? 3 : 2
            );
        }

        if ((int) $row->invoice_count > 0 && abs($desiDelta) > $tolerances['desi']) {
            $risks[] = $this->risk(
                'desi_difference',
                'Desi farkı',
                'Fatura desisi ile sevkiyat desisi toleransın dışında.',
                0,
                abs($desiDelta) > $tolerances['desi'] * 3 ? 3 : 2
            );
        }

        $hasComponentDifference = collect($risks)->contains(
            fn (array $risk) => in_array($risk['key'], ['commission_difference', 'cargo_amount_difference'], true)
        );

        if (
            (string) $row->reconciliation_state_metric === 'material'
            && abs($deductionDelta) > $tolerances['settlement']
            && ! $hasComponentDifference
        ) {
            $risks[] = $this->risk(
                'settlement_difference',
                'Hakediş farkı',
                'Tahmini ve kesin kesinti toplamları arasında açıklanması gereken fark var.',
                max(0, $deductionDelta),
                $deductionDelta > $tolerances['settlement'] * 3 ? 3 : 2
            );
        }

        if ((float) $row->penalty_other_total > 0) {
            $risks[] = $this->risk(
                'penalty_other_invoice',
                'Ceza / diğer fatura',
                'Siparişte ceza, kesinti faturası veya diğer fatura hareketi bulunuyor.',
                (float) $row->penalty_other_total,
                3
            );
        }

        if ((float) $row->return_cargo_total > 0) {
            $risks[] = $this->risk(
                'return_cargo',
                'İade kargo maliyeti',
                'Siparişe yansıtılmış iade kargo kesintisi bulunuyor.',
                (float) $row->return_cargo_total,
                2
            );
        }

        $severityScore = (int) collect($risks)->max('severity_score');
        $potentialRecovery = round((float) collect($risks)->sum('recoverable_amount'), 2);

        return [
            'id' => (int) $row->id,
            'order_number' => (string) $row->order_number,
            'order_status' => (string) $row->order_status,
            'ordered_at' => $row->ordered_at ? Carbon::parse($row->ordered_at)->format('d.m.Y') : '',
            'marketplace' => (string) $row->marketplace_alias,
            'store_name' => (string) $row->store_name_alias,
            'legal_entity_name' => (string) $row->legal_entity_name_alias,
            'gross_revenue' => round((float) $row->gross_revenue_metric, 2),
            'estimated_commission' => round($estimatedCommission, 2),
            'actual_commission' => round($actualCommission, 2),
            'commission_delta' => $commissionDelta,
            'expected_cargo' => round($expectedCargo, 2),
            'invoice_cargo' => round($invoiceCargo, 2),
            'cargo_delta' => $cargoDelta,
            'expected_desi' => round((float) $row->expected_desi, 2),
            'invoice_desi' => round((float) $row->invoice_desi, 2),
            'desi_delta' => $desiDelta,
            'deduction_total' => round((float) $row->deduction_total_metric, 2),
            'deduction_delta' => $deductionDelta,
            'penalty_other_total' => round((float) $row->penalty_other_total, 2),
            'return_cargo_total' => round((float) $row->return_cargo_total, 2),
            'potential_recovery' => $potentialRecovery,
            'carrier_name' => (string) $row->carrier_name,
            'tracking_number' => (string) $row->tracking_number,
            'invoice_number' => (string) $row->invoice_number,
            'last_financial_event_at' => $row->last_financial_event_at
                ? Carbon::parse($row->last_financial_event_at)->format('d.m.Y')
                : '',
            'risks' => $risks,
            'primary_risk' => $risks[0] ?? null,
            'severity' => $severityScore >= 3 ? 'critical' : 'warning',
            'severity_score' => $severityScore,
            'severity_label' => $severityScore >= 3 ? 'Kritik' : 'İncelenmeli',
            'action_hint' => $this->actionHint($risks),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function risk(
        string $key,
        string $label,
        string $description,
        float $recoverableAmount,
        int $severityScore
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'description' => $description,
            'recoverable_amount' => round($recoverableAmount, 2),
            'severity_score' => $severityScore,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $serviceFeeTrend
     * @param  array<string, mixed>  $orphanInvoices
     * @return array<int, array<string, mixed>>
     */
    protected function riskBreakdown($rows, array $serviceFeeTrend, array $orphanInvoices): array
    {
        $definitions = [
            'waiting_settlement' => ['label' => 'Hakediş bekleyen', 'tone' => 'amber'],
            'settlement_difference' => ['label' => 'Hakediş farkı', 'tone' => 'rose'],
            'commission_difference' => ['label' => 'Komisyon farkı', 'tone' => 'rose'],
            'cargo_amount_difference' => ['label' => 'Kargo tutar farkı', 'tone' => 'amber'],
            'desi_difference' => ['label' => 'Desi farkı', 'tone' => 'sky'],
            'missing_shipment' => ['label' => 'Sevkiyat kaydı eksik', 'tone' => 'slate'],
            'penalty_other_invoice' => ['label' => 'Ceza / diğer fatura', 'tone' => 'rose'],
            'return_cargo' => ['label' => 'İade kargo', 'tone' => 'amber'],
            // Ported from V1 AuditEngine
            'stopaj_difference' => ['label' => 'Stopaj tutarsızlığı', 'tone' => 'amber'],
            'commission_refund_missing' => ['label' => 'Komisyon iade eksikliği', 'tone' => 'rose'],
            'transaction_discrepancy' => ['label' => 'Cari hesap uyumsuzluğu', 'tone' => 'rose'],
        ];

        $items = collect($definitions)
            ->map(function (array $definition, string $key) use ($rows): array {
                $matching = $rows->filter(
                    fn (array $row) => collect($row['risks'])->contains(fn (array $risk) => $risk['key'] === $key)
                );

                return [
                    'key' => $key,
                    'label' => $definition['label'],
                    'tone' => $definition['tone'],
                    'count' => $matching->count(),
                    'amount' => round((float) $matching->sum(function (array $row) use ($key) {
                        return (float) collect($row['risks'])->firstWhere('key', $key)['recoverable_amount'];
                    }), 2),
                ];
            })
            ->filter(fn (array $item) => $item['count'] > 0)
            ->values();

        if ((int) $orphanInvoices['count'] > 0) {
            $items->push([
                'key' => 'orphan_invoice',
                'label' => 'Eşleşmeyen kargo faturası',
                'tone' => 'rose',
                'count' => (int) $orphanInvoices['count'],
                'amount' => (float) $orphanInvoices['amount'],
            ]);
        }

        if ($serviceFeeTrend['is_increase']) {
            $items->push([
                'key' => 'service_fee_increase',
                'label' => 'Hizmet bedeli artışı',
                'tone' => 'amber',
                'count' => (int) $serviceFeeTrend['current_order_count'],
                'amount' => (float) $serviceFeeTrend['current_total'],
            ]);
        }

        return $items->sortByDesc(fn (array $item) => [$item['count'], $item['amount']])->values()->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, float>  $tolerances
     * @return array<string, mixed>
     */
    protected function serviceFeeTrend(int $userId, array $filters, array $tolerances): array
    {
        $current = $this->serviceFeeStats($userId, $filters);
        $dateFrom = Carbon::parse($filters['date_from'] ?? now()->subDays(30)->toDateString());
        $dateTo = Carbon::parse($filters['date_to'] ?? now()->toDateString());
        $days = max(1, $dateFrom->diffInDays($dateTo) + 1);
        $previousFilters = array_merge($filters, [
            'date_from' => $dateFrom->copy()->subDays($days)->toDateString(),
            'date_to' => $dateFrom->copy()->subDay()->toDateString(),
        ]);
        unset($previousFilters['risk_type'], $previousFilters['search']);
        $previous = $this->serviceFeeStats($userId, $previousFilters);
        $change = round($current['rate'] - $previous['rate'], 2);
        $minOrders = max(1, (new MpSettingsService($userId))->getInt('audit_tolerances.service_fee_increase_min_orders', 20));

        return [
            'current_rate' => $current['rate'],
            'previous_rate' => $previous['rate'],
            'change_points' => $change,
            'current_total' => $current['total'],
            'current_order_count' => $current['order_count'],
            'previous_order_count' => $previous['order_count'],
            'is_increase' => $current['order_count'] >= $minOrders
                && $previous['order_count'] >= $minOrders
                && $change >= $tolerances['service_fee_rate'],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{rate: float, total: float, order_count: int}
     */
    protected function serviceFeeStats(int $userId, array $filters): array
    {
        $row = DB::query()
            ->fromSub($this->profitCenter->baseQuery($userId, $filters)->reorder(), 'fee_base')
            ->selectRaw('
                COUNT(*) as order_count,
                COALESCE(SUM(gross_revenue_metric), 0) as gross_total,
                COALESCE(SUM(service_fee_total_metric), 0) as service_total
            ')
            ->first();

        $gross = (float) ($row->gross_total ?? 0);
        $total = (float) ($row->service_total ?? 0);

        return [
            'rate' => $gross > 0 ? round(($total / $gross) * 100, 2) : 0.0,
            'total' => round($total, 2),
            'order_count' => (int) ($row->order_count ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{count: int, amount: float}
     */
    protected function orphanInvoiceSummary(int $userId, array $filters): array
    {
        $query = CargoInvoiceLine::query()
            ->where('user_id', $userId)
            ->whereNull('shipment_id');

        if ($this->filled($filters, 'legal_entity_id')) {
            $query->where('legal_entity_id', (int) $filters['legal_entity_id']);
        }

        if ($this->filled($filters, 'date_from')) {
            $query->whereDate('invoice_date', '>=', (string) $filters['date_from']);
        }

        if ($this->filled($filters, 'date_to')) {
            $query->whereDate('invoice_date', '<=', (string) $filters['date_to']);
        }

        return [
            'count' => (int) (clone $query)->count(),
            'amount' => round((float) (clone $query)->sum(DB::raw('CASE WHEN total_amount > 0 THEN total_amount ELSE amount END')), 2),
        ];
    }

    /**
     * @return array<string, float>
     */
    protected function tolerances(int $userId): array
    {
        $settings = new MpSettingsService($userId);

        return [
            'commission' => round(max(0, $settings->getCommissionMatchTolerance()), 2),
            'cargo' => round(max(0, $settings->getCargoMatchTolerance()), 2),
            'desi' => round(max(0, (float) config('cargo.tolerances.desi', 2)), 2),
            'settlement' => round(max(0, $settings->getFloat('audit_tolerances.hakedis_tolerance', 1)), 2),
            'service_fee_rate' => round(max(0, $settings->getFloat('audit_tolerances.service_fee_increase_threshold', 0.5)), 2),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $rows
     */
    protected function riskCount($rows, string $key): int
    {
        return $rows->filter(
            fn (array $row) => collect($row['risks'])->contains(fn (array $risk) => $risk['key'] === $key)
        )->count();
    }

    /**
     * @param  array<int, array<string, mixed>>  $risks
     */
    protected function actionHint(array $risks): string
    {
        $keys = collect($risks)->pluck('key');

        return match (true) {
            $keys->contains('penalty_other_invoice') => 'Fatura belgesini ve kesinti gerekçesini doğrulayıp itiraz dosyasına ekleyin.',
            $keys->contains('commission_difference') => 'Kategori komisyon oranı ile finans kesintisini karşılaştırın.',
            $keys->contains('cargo_amount_difference'), $keys->contains('desi_difference') => 'Kargo faturası, desi ve takip numarası kanıtlarını karşılaştırın.',
            $keys->contains('settlement_difference') => 'Tahmini ve kesin finans satırlarını belge referanslarıyla mutabık hale getirin.',
            $keys->contains('waiting_settlement') => 'Pazaryeri finans hareketinin oluşmasını veya senkron durumunu kontrol edin.',
            $keys->contains('missing_shipment') => 'Sipariş ile sevkiyat/takip kaydını eşleştirin.',
            $keys->contains('stopaj_difference') => 'Hesaplanan stopaj matrahı ile fiili kesintiyi karşılaştırın.',
            $keys->contains('commission_refund_missing') => 'İade siparişinde komisyon iadesinin cari hesaba yansıyıp yansımadığını kontrol edin.',
            $keys->contains('transaction_discrepancy') => 'Cari hesap ektresi ile hakediş faturasındaki tutarsızlığı inceleyin.',
            default => 'Kesinti kaynağını doğrulayıp gerekli belgeyi hazırlayın.',
        };
    }

    protected function isCancelled(string $status): bool
    {
        return str_contains(mb_strtolower($status), 'cancel')
            || str_contains(mb_strtolower($status), 'iptal');
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
}
