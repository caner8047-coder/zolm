<?php

namespace App\Services\Marketplace;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TrendyolBoosterRetentionReportService
{
    /**
     * @return array<string, mixed>
     */
    public function report(?int $userId = null, ?int $overrideDays = null): array
    {
        $generatedAt = now();
        $datasets = array_map(
            fn (array $dataset): array => $this->datasetReport($dataset, $generatedAt, $userId, $overrideDays),
            $this->datasetDefinitions(),
        );

        $available = array_values(array_filter($datasets, fn (array $row): bool => (bool) $row['available']));

        return [
            'mode' => 'dry_run',
            'user_id' => $userId,
            'generated_at' => $generatedAt->toIso8601String(),
            'summary' => [
                'dataset_count' => count($datasets),
                'available_dataset_count' => count($available),
                'missing_dataset_count' => count($datasets) - count($available),
                'total_count' => array_sum(array_column($available, 'total_count')),
                'candidate_count' => array_sum(array_column($available, 'candidate_count')),
                'scope' => $userId ? 'user' : 'all_users',
            ],
            'datasets' => $datasets,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function datasetReport(array $dataset, Carbon $generatedAt, ?int $userId, ?int $overrideDays): array
    {
        $retentionDays = $this->retentionDays($dataset, $overrideDays);
        $cutoffAt = $generatedAt->copy()->subDays($retentionDays);
        $missingReason = $this->missingSchemaReason($dataset);
        $base = [
            'key' => $dataset['key'],
            'label' => $dataset['label'],
            'table' => $dataset['table'],
            'available' => $missingReason === null,
            'status' => $missingReason ? 'missing_schema' : 'ok',
            'missing_reason' => $missingReason,
            'retention_days' => $retentionDays,
            'cutoff_at' => $cutoffAt->toDateTimeString(),
            'scope' => $userId ? 'user' : 'all_users',
            'total_count' => 0,
            'candidate_count' => 0,
            'candidate_ratio' => 0.0,
            'oldest_at' => null,
            'latest_at' => null,
            'candidate_oldest_at' => null,
            'candidate_latest_at' => null,
        ];

        if ($missingReason !== null) {
            return $base;
        }

        $dateColumn = $this->qualifiedDateColumn($dataset);
        $query = $this->baseQuery($dataset);

        if ($userId) {
            $query->where($dataset['user_column'], $userId);
        }

        $totalCount = (clone $query)->count();
        $candidateQuery = (clone $query)->where($dateColumn, '<', $cutoffAt);
        $candidateCount = (clone $candidateQuery)->count();

        return array_merge($base, [
            'total_count' => $totalCount,
            'candidate_count' => $candidateCount,
            'candidate_ratio' => $totalCount > 0 ? round(($candidateCount / $totalCount) * 100, 2) : 0.0,
            'oldest_at' => $this->dateString((clone $query)->min($dateColumn)),
            'latest_at' => $this->dateString((clone $query)->max($dateColumn)),
            'candidate_oldest_at' => $this->dateString((clone $candidateQuery)->min($dateColumn)),
            'candidate_latest_at' => $this->dateString((clone $candidateQuery)->max($dateColumn)),
        ]);
    }

    protected function baseQuery(array $dataset): Builder
    {
        $query = DB::table($dataset['table'].' as '.$dataset['alias']);

        foreach ($dataset['joins'] ?? [] as $join) {
            $query->join($join['table'].' as '.$join['alias'], $join['first'], '=', $join['second']);
        }

        return $query;
    }

    protected function missingSchemaReason(array $dataset): ?string
    {
        foreach ($dataset['required_columns'] as $table => $columns) {
            if (! Schema::hasTable($table)) {
                return 'Eksik tablo: '.$table;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    return 'Eksik kolon: '.$table.'.'.$column;
                }
            }
        }

        return null;
    }

    protected function retentionDays(array $dataset, ?int $overrideDays): int
    {
        if ($overrideDays !== null) {
            return max(1, $overrideDays);
        }

        return max(1, (int) config(
            'marketplace.trendyol_booster.retention.'.$dataset['retention_key'],
            $dataset['default_days'],
        ));
    }

    protected function qualifiedDateColumn(array $dataset): string
    {
        return $dataset['alias'].'.'.$dataset['date_column'];
    }

    protected function dateString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->toDateTimeString();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function datasetDefinitions(): array
    {
        return [
            [
                'key' => 'snapshots',
                'label' => 'Ürün snapshot geçmişi',
                'table' => 'trendyol_booster_snapshots',
                'alias' => 'snapshots',
                'date_column' => 'checked_at',
                'user_column' => 'snapshots.user_id',
                'retention_key' => 'snapshots_days',
                'default_days' => 180,
                'required_columns' => [
                    'trendyol_booster_snapshots' => ['user_id', 'checked_at'],
                ],
            ],
            [
                'key' => 'stock_checks',
                'label' => 'Stok kontrol geçmişi',
                'table' => 'trendyol_booster_stock_checks',
                'alias' => 'stock_checks',
                'date_column' => 'checked_at',
                'user_column' => 'stock_checks.user_id',
                'retention_key' => 'stock_checks_days',
                'default_days' => 180,
                'cleanup_order' => 60,
                'required_columns' => [
                    'trendyol_booster_stock_checks' => ['user_id', 'checked_at'],
                ],
            ],
            [
                'key' => 'stock_sellers',
                'label' => 'Satıcı stok kırılım geçmişi',
                'table' => 'trendyol_booster_stock_sellers',
                'alias' => 'stock_sellers',
                'date_column' => 'created_at',
                'user_column' => 'stock_sellers.user_id',
                'retention_key' => 'stock_sellers_days',
                'default_days' => 180,
                'cleanup_order' => 10,
                'required_columns' => [
                    'trendyol_booster_stock_sellers' => ['user_id', 'created_at'],
                ],
            ],
            [
                'key' => 'keyword_lookups',
                'label' => 'Anahtar kelime arama geçmişi',
                'table' => 'trendyol_booster_keyword_lookups',
                'alias' => 'keyword_lookups',
                'date_column' => 'searched_at',
                'user_column' => 'keyword_lookups.user_id',
                'retention_key' => 'keyword_lookups_days',
                'default_days' => 180,
                'required_columns' => [
                    'trendyol_booster_keyword_lookups' => ['user_id', 'searched_at'],
                ],
            ],
            [
                'key' => 'keyword_observations',
                'label' => 'Anahtar kelime sıra gözlemleri',
                'table' => 'trendyol_booster_keyword_observations',
                'alias' => 'keyword_observations',
                'date_column' => 'created_at',
                'user_column' => 'keywords.user_id',
                'joins' => [[
                    'table' => 'trendyol_booster_keywords',
                    'alias' => 'keywords',
                    'first' => 'keyword_observations.trendyol_booster_keyword_id',
                    'second' => 'keywords.id',
                ]],
                'retention_key' => 'keyword_observations_days',
                'default_days' => 180,
                'required_columns' => [
                    'trendyol_booster_keyword_observations' => ['trendyol_booster_keyword_id', 'created_at'],
                    'trendyol_booster_keywords' => ['id', 'user_id'],
                ],
            ],
            [
                'key' => 'store_snapshots',
                'label' => 'Mağaza tarama snapshot geçmişi',
                'table' => 'trendyol_booster_store_watch_snapshots',
                'alias' => 'store_snapshots',
                'date_column' => 'checked_at',
                'user_column' => 'store_snapshots.user_id',
                'retention_key' => 'store_snapshots_days',
                'default_days' => 180,
                'required_columns' => [
                    'trendyol_booster_store_watch_snapshots' => ['user_id', 'checked_at'],
                ],
            ],
            [
                'key' => 'store_item_histories',
                'label' => 'Mağaza ürün geçmişi',
                'table' => 'trendyol_booster_store_item_histories',
                'alias' => 'store_item_histories',
                'date_column' => 'created_at',
                'user_column' => 'store_items.user_id',
                'joins' => [[
                    'table' => 'trendyol_booster_store_watch_items',
                    'alias' => 'store_items',
                    'first' => 'store_item_histories.trendyol_booster_store_watch_item_id',
                    'second' => 'store_items.id',
                ]],
                'retention_key' => 'store_item_histories_days',
                'default_days' => 180,
                'required_columns' => [
                    'trendyol_booster_store_item_histories' => ['trendyol_booster_store_watch_item_id', 'created_at'],
                    'trendyol_booster_store_watch_items' => ['id', 'user_id'],
                ],
            ],
            [
                'key' => 'supplier_offers',
                'label' => 'Tedarikçi teklif geçmişi',
                'table' => 'trendyol_booster_supplier_offers',
                'alias' => 'supplier_offers',
                'date_column' => 'observed_at',
                'user_column' => 'supplier_offers.user_id',
                'retention_key' => 'supplier_offers_days',
                'default_days' => 180,
                'required_columns' => [
                    'trendyol_booster_supplier_offers' => ['user_id', 'observed_at'],
                ],
            ],
            [
                'key' => 'bestseller_runs',
                'label' => 'Çok satan rapor çalıştırmaları',
                'table' => 'trendyol_bestseller_report_runs',
                'alias' => 'bestseller_runs',
                'date_column' => 'captured_at',
                'user_column' => 'bestseller_runs.user_id',
                'retention_key' => 'bestseller_runs_days',
                'default_days' => 365,
                'cleanup_order' => 70,
                'required_columns' => [
                    'trendyol_bestseller_report_runs' => ['user_id', 'captured_at'],
                ],
            ],
            [
                'key' => 'bestseller_items',
                'label' => 'Çok satan ürün geçmişi',
                'table' => 'trendyol_bestseller_report_items',
                'alias' => 'bestseller_items',
                'date_column' => 'captured_at',
                'user_column' => 'bestseller_items.user_id',
                'retention_key' => 'bestseller_items_days',
                'default_days' => 365,
                'cleanup_order' => 20,
                'required_columns' => [
                    'trendyol_bestseller_report_items' => ['user_id', 'captured_at'],
                ],
            ],
            [
                'key' => 'activity_logs',
                'label' => 'Booster aktivite kayıtları',
                'table' => 'trendyol_booster_activity_logs',
                'alias' => 'activity_logs',
                'date_column' => 'recorded_at',
                'user_column' => 'activity_logs.user_id',
                'retention_key' => 'activity_logs_days',
                'default_days' => 90,
                'required_columns' => [
                    'trendyol_booster_activity_logs' => ['user_id', 'recorded_at'],
                ],
            ],
        ];
    }
}
