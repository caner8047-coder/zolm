<?php

namespace App\Services\Marketplace;

use App\Models\IntegrationSyncRun;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MarketplaceDiagnosticsReportService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{totals: array<string, int>, rows: array<int, array<string, mixed>>}
     */
    public function summaryForUser(int $userId, array $filters = []): array
    {
        $runs = IntegrationSyncRun::query()
            ->with('store:id,store_name,marketplace')
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', $userId))
            ->when(isset($filters['store_id']), fn (Builder $query) => $query->where('store_id', $filters['store_id']))
            ->when(($filters['sync_type'] ?? 'all') !== 'all', fn (Builder $query) => $query->where('sync_type', $filters['sync_type']))
            ->when(($filters['smoke_only'] ?? false) === true, fn (Builder $query) => $query->where('trigger_type', 'smoke_test'))
            ->when(isset($filters['hours']), fn (Builder $query) => $query->where('created_at', '>=', now()->subHours((int) $filters['hours'])))
            ->latest('created_at')
            ->limit((int) ($filters['limit'] ?? 200))
            ->get()
            ->filter(fn (IntegrationSyncRun $run) => $run->diagnostics() !== []);

        return $this->summarize($runs);
    }

    /**
     * @param  iterable<int, IntegrationSyncRun>  $runs
     * @return array{totals: array<string, int>, rows: array<int, array<string, mixed>>}
     */
    public function summarize(iterable $runs): array
    {
        $collection = collect($runs)->filter(fn (IntegrationSyncRun $run) => $run->diagnostics() !== [])->values();

        $rows = $collection
            ->groupBy(fn (IntegrationSyncRun $run) => implode('|', [
                $run->store_id,
                $run->sync_type,
            ]))
            ->map(function (Collection $group): array {
                /** @var IntegrationSyncRun $first */
                $first = $group->first();

                $warningTexts = $group
                    ->flatMap(fn (IntegrationSyncRun $run) => $run->diagnosticsWarnings())
                    ->filter()
                    ->countBy()
                    ->sortDesc();

                return [
                    'store_id' => $first->store_id,
                    'store_name' => $first->store?->store_name,
                    'marketplace' => MarketplaceProviderRegistry::normalize((string) $first->store?->marketplace),
                    'sync_type' => $first->sync_type,
                    'total_runs' => $group->count(),
                    'smoke_runs' => $group->filter(fn (IntegrationSyncRun $run) => $run->isSmokeTest())->count(),
                    'warning_runs' => $group->filter(fn (IntegrationSyncRun $run) => $run->diagnosticWarningCount() > 0)->count(),
                    'total_warning_count' => $group->sum(fn (IntegrationSyncRun $run) => $run->diagnosticWarningCount()),
                    'latest_run_at' => optional($group->sortByDesc('created_at')->first()?->created_at)?->toDateTimeString(),
                    'package_count' => $group->sum(fn (IntegrationSyncRun $run) => (int) data_get($run->diagnostics(), 'package_count', 0)),
                    'order_count' => $group->sum(fn (IntegrationSyncRun $run) => (int) data_get($run->diagnostics(), 'order_count', 0)),
                    'item_count' => $group->sum(fn (IntegrationSyncRun $run) => (int) data_get($run->diagnostics(), 'item_count', 0)),
                    'product_count' => $group->sum(fn (IntegrationSyncRun $run) => (int) data_get($run->diagnostics(), 'product_count', 0)),
                    'event_count' => $group->sum(fn (IntegrationSyncRun $run) => (int) data_get($run->diagnostics(), 'event_count', 0)),
                    'missing_order_number_count' => $group->sum(fn (IntegrationSyncRun $run) => (int) data_get($run->diagnostics(), 'missing_order_number_count', 0)),
                    'missing_package_id_count' => $group->sum(fn (IntegrationSyncRun $run) => (int) data_get($run->diagnostics(), 'missing_package_id_count', 0)),
                    'missing_item_line_id_count' => $group->sum(fn (IntegrationSyncRun $run) => (int) data_get($run->diagnostics(), 'missing_item_line_id_count', 0)),
                    'missing_line_id_count' => $group->sum(fn (IntegrationSyncRun $run) => (int) data_get($run->diagnostics(), 'missing_line_id_count', 0)),
                    'missing_stock_code_count' => $group->sum(fn (IntegrationSyncRun $run) => (int) data_get($run->diagnostics(), 'missing_stock_code_count', 0)),
                    'missing_barcode_count' => $group->sum(fn (IntegrationSyncRun $run) => (int) data_get($run->diagnostics(), 'missing_barcode_count', 0)),
                    'missing_amount_count' => $group->sum(fn (IntegrationSyncRun $run) => (int) data_get($run->diagnostics(), 'missing_amount_count', 0)),
                    'missing_settlement_date_count' => $group->sum(fn (IntegrationSyncRun $run) => (int) data_get($run->diagnostics(), 'missing_settlement_date_count', 0)),
                    'missing_listing_id_count' => $group->sum(fn (IntegrationSyncRun $run) => (int) data_get($run->diagnostics(), 'missing_listing_id_count', 0)),
                    'missing_sale_price_count' => $group->sum(fn (IntegrationSyncRun $run) => (int) data_get($run->diagnostics(), 'missing_sale_price_count', 0)),
                    'missing_stock_quantity_count' => $group->sum(fn (IntegrationSyncRun $run) => (int) data_get($run->diagnostics(), 'missing_stock_quantity_count', 0)),
                    'top_warning' => $warningTexts->keys()->first(),
                ];
            })
            ->sortByDesc(fn (array $row) => [
                $row['warning_runs'],
                $row['total_warning_count'],
                $row['missing_stock_code_count'] + $row['missing_barcode_count'] + $row['missing_amount_count'],
            ])
            ->values()
            ->all();

        return [
            'totals' => [
                'groups' => count($rows),
                'runs' => $collection->count(),
                'smoke_runs' => $collection->filter(fn (IntegrationSyncRun $run) => $run->isSmokeTest())->count(),
                'warning_runs' => $collection->filter(fn (IntegrationSyncRun $run) => $run->diagnosticWarningCount() > 0)->count(),
                'total_warnings' => $collection->sum(fn (IntegrationSyncRun $run) => $run->diagnosticWarningCount()),
            ],
            'rows' => $rows,
        ];
    }
}
