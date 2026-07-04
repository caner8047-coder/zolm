<?php

namespace App\Services\Marketplace;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;

class TrendyolBoosterRetentionCleanupService
{
    public function __construct(
        protected TrendyolBoosterRetentionReportService $reportService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function cleanup(int $userId, ?int $batchSize = null): array
    {
        if (! (bool) config('marketplace.trendyol_booster.retention.cleanup_enabled', false)) {
            throw new LogicException('Trendyol Booster retention temizliği feature flag ile kapalı.');
        }

        if ($userId <= 0) {
            throw new LogicException('Retention temizliği için geçerli bir kullanıcı ID zorunludur.');
        }

        $generatedAt = now();
        $batchSize = $this->batchSize($batchSize);
        $maxDelete = $this->maxDeletePerRun();
        $before = $this->reportService->report($userId);
        $beforeRows = collect($before['datasets'])->keyBy('key');
        $deletedTotal = 0;
        $results = [];

        $datasets = collect($this->reportService->datasetDefinitions())
            ->sortBy(fn (array $dataset): int => (int) ($dataset['cleanup_order'] ?? 50));

        foreach ($datasets as $dataset) {
            $reportRow = $beforeRows->get($dataset['key'], []);
            $candidateCount = (int) ($reportRow['candidate_count'] ?? 0);
            $remainingBudget = max(0, $maxDelete - $deletedTotal);

            if (! ($reportRow['available'] ?? false)) {
                $results[] = $this->datasetResult($dataset, $candidateCount, 0, 'missing_schema', $reportRow['missing_reason'] ?? null);

                continue;
            }

            if ($candidateCount === 0) {
                $results[] = $this->datasetResult($dataset, 0, 0, 'no_candidates');

                continue;
            }

            if ($remainingBudget === 0) {
                $results[] = $this->datasetResult($dataset, $candidateCount, 0, 'limit_reached');

                continue;
            }

            $deleted = $this->cleanupDataset(
                $dataset,
                $userId,
                Carbon::parse($reportRow['cutoff_at']),
                $batchSize,
                $remainingBudget,
            );
            $deletedTotal += $deleted;
            $results[] = $this->datasetResult(
                $dataset,
                $candidateCount,
                $deleted,
                $deleted < $candidateCount ? 'limit_reached' : 'cleaned',
            );
        }

        $after = $this->reportService->report($userId);
        $remainingCandidates = (int) data_get($after, 'summary.candidate_count', 0);

        return [
            'mode' => 'execute',
            'user_id' => $userId,
            'generated_at' => $generatedAt->toIso8601String(),
            'batch_size' => $batchSize,
            'max_delete_per_run' => $maxDelete,
            'summary' => [
                'candidate_before' => (int) data_get($before, 'summary.candidate_count', 0),
                'deleted_count' => $deletedTotal,
                'candidate_remaining' => $remainingCandidates,
                'dataset_count' => count($results),
                'stopped_at_limit' => $deletedTotal >= $maxDelete && $remainingCandidates > 0,
                'scope' => 'user',
            ],
            'datasets' => $results,
        ];
    }

    protected function cleanupDataset(
        array $dataset,
        int $userId,
        Carbon $cutoffAt,
        int $batchSize,
        int $maxDelete,
    ): int {
        if (! Schema::hasColumn($dataset['table'], 'id')) {
            return 0;
        }

        $deletedTotal = 0;

        while ($deletedTotal < $maxDelete) {
            $limit = min($batchSize, $maxDelete - $deletedTotal);
            $ids = $this->candidateQuery($dataset, $userId, $cutoffAt)
                ->orderBy($dataset['alias'].'.id')
                ->limit($limit)
                ->pluck($dataset['alias'].'.id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();

            if ($ids === []) {
                break;
            }

            $deleted = DB::transaction(fn (): int => DB::table($dataset['table'])
                ->whereIn('id', $ids)
                ->delete());

            if ($deleted === 0) {
                break;
            }

            $deletedTotal += $deleted;
        }

        return $deletedTotal;
    }

    protected function candidateQuery(array $dataset, int $userId, Carbon $cutoffAt): Builder
    {
        $query = DB::table($dataset['table'].' as '.$dataset['alias']);

        foreach ($dataset['joins'] ?? [] as $join) {
            $query->join($join['table'].' as '.$join['alias'], $join['first'], '=', $join['second']);
        }

        return $query
            ->where($dataset['user_column'], $userId)
            ->where($dataset['alias'].'.'.$dataset['date_column'], '<', $cutoffAt)
            ->select($dataset['alias'].'.id');
    }

    /**
     * @return array<string, mixed>
     */
    protected function datasetResult(
        array $dataset,
        int $candidateCount,
        int $deletedCount,
        string $status,
        ?string $message = null,
    ): array {
        return [
            'key' => $dataset['key'],
            'label' => $dataset['label'],
            'table' => $dataset['table'],
            'candidate_count' => $candidateCount,
            'deleted_count' => $deletedCount,
            'remaining_count' => max(0, $candidateCount - $deletedCount),
            'status' => $status,
            'message' => $message,
        ];
    }

    protected function batchSize(?int $batchSize): int
    {
        return max(50, min(2000, $batchSize ?? (int) config(
            'marketplace.trendyol_booster.retention.cleanup_batch_size',
            500,
        )));
    }

    protected function maxDeletePerRun(): int
    {
        return max(1, min(100000, (int) config(
            'marketplace.trendyol_booster.retention.cleanup_max_delete_per_run',
            10000,
        )));
    }
}
