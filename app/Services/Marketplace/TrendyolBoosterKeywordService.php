<?php

namespace App\Services\Marketplace;

use App\Models\TrendyolBoosterKeyword;
use App\Models\TrendyolBoosterProduct;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class TrendyolBoosterKeywordService
{
    public function __construct(
        protected TrendyolSearchResultReader $searchReader,
        protected TrendyolBoosterNotificationService $notificationService,
        protected TrendyolBoosterActivityLogger $activityLogger,
    ) {}

    /**
     * @return array{ok: bool, message: string, keyword: ?TrendyolBoosterKeyword}
     */
    public function addKeyword(TrendyolBoosterProduct $tracked, string $keyword, int $targetRank = 20): array
    {
        $keywordText = $this->normalizeKeyword($keyword);

        if (mb_strlen($keywordText) < 2) {
            return [
                'ok' => false,
                'message' => 'Anahtar kelime en az 2 karakter olmalı.',
                'keyword' => null,
            ];
        }

        $keywordModel = TrendyolBoosterKeyword::query()->updateOrCreate(
            [
                'trendyol_booster_product_id' => $tracked->id,
                'keyword_hash' => hash('sha256', Str::lower($keywordText)),
            ],
            [
                'user_id' => $tracked->user_id,
                'keyword' => $keywordText,
                'target_rank' => max(1, min(500, $targetRank)),
                'is_active' => true,
            ]
        );

        return $this->refresh($keywordModel);
    }

    /**
     * @return array{ok: bool, message: string, keyword: TrendyolBoosterKeyword}
     */
    public function refresh(TrendyolBoosterKeyword $keyword): array
    {
        $keyword->loadMissing('trackedProduct');
        $tracked = $keyword->trackedProduct;
        $result = $this->searchReader->fetch((string) $keyword->keyword);

        if (! $result['ok'] || ! $tracked) {
            $keyword->forceFill(['last_checked_at' => now()])->save();
            $this->logSyncIssue($keyword, 'sync_error', 'Anahtar kelime kontrolü başarısız', $result['message'] ?? 'Anahtar kelime yenilenemedi.');

            return [
                'ok' => false,
                'message' => $result['message'] ?? 'Anahtar kelime yenilenemedi.',
                'keyword' => $keyword->fresh() ?: $keyword,
            ];
        }

        return [
            'ok' => true,
            'message' => $result['message'],
            'keyword' => $this->persistVisibility($keyword, $tracked, $result['data']),
        ];
    }

    /**
     * @return array{processed: int, succeeded: int, failed: int, skipped: int, dry_run: bool}
     */
    public function refreshDue(int $limit = 50, ?int $userId = null, ?int $staleMinutes = null, bool $dryRun = false): array
    {
        $query = TrendyolBoosterKeyword::query()
            ->with('trackedProduct')
            ->where('is_active', true)
            ->when($userId !== null, fn (Builder $query) => $query->where('user_id', $userId))
            ->when($staleMinutes !== null && $staleMinutes > 0, function (Builder $query) use ($staleMinutes): void {
                $query->where(function (Builder $staleQuery) use ($staleMinutes): void {
                    $staleQuery
                        ->whereNull('last_checked_at')
                        ->orWhere('last_checked_at', '<=', now()->subMinutes($staleMinutes));
                });
            })
            ->orderBy('last_checked_at')
            ->orderBy('id')
            ->limit(max(1, min(500, $limit)));

        $summary = [
            'processed' => 0,
            'succeeded' => 0,
            'failed' => 0,
            'skipped' => 0,
            'dry_run' => $dryRun,
        ];

        foreach ($query->get() as $keyword) {
            $summary['processed']++;

            if ($dryRun) {
                $summary['skipped']++;

                continue;
            }

            try {
                $result = $this->refresh($keyword);
            } catch (\Throwable $exception) {
                $summary['failed']++;
                $this->logSyncIssue($keyword, 'sync_error', 'Anahtar kelime kontrolü hata verdi', $exception->getMessage(), [
                    'exception' => get_class($exception),
                ]);

                continue;
            }

            $summary[$result['ok'] ? 'succeeded' : 'failed']++;
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $searchData
     */
    protected function persistVisibility(TrendyolBoosterKeyword $keyword, TrendyolBoosterProduct $tracked, array $searchData): TrendyolBoosterKeyword
    {
        $productId = trim((string) $tracked->trendyol_product_id);
        $productIds = array_values((array) ($searchData['product_ids'] ?? []));
        $rank = $productId !== '' ? array_search($productId, $productIds, true) : false;
        $observedRank = $rank === false ? null : (int) $rank + 1;
        $checkedResultCount = (int) ($searchData['checked_result_count'] ?? count($productIds));

        return $this->persistObservation(
            $keyword,
            $productId,
            $observedRank,
            (int) ($searchData['result_count'] ?? count($productIds)),
            $checkedResultCount,
        );
    }

    public function recordObservation(
        TrendyolBoosterKeyword $keyword,
        ?int $observedRank,
        int $resultCount,
        int $checkedResultCount,
    ): TrendyolBoosterKeyword {
        $keyword->loadMissing('trackedProduct');

        return $this->persistObservation(
            $keyword,
            trim((string) $keyword->trackedProduct?->trendyol_product_id),
            $observedRank,
            $resultCount,
            $checkedResultCount,
        );
    }

    protected function persistObservation(
        TrendyolBoosterKeyword $keyword,
        string $productId,
        ?int $observedRank,
        int $resultCount,
        int $checkedResultCount,
    ): TrendyolBoosterKeyword {
        $checkedResultCount = max($observedRank ?? 0, $checkedResultCount);
        $visibility = $this->visibility($observedRank, (int) $keyword->target_rank, $productId, $checkedResultCount);
        $previousRank = $keyword->observed_rank;
        $previousStatus = $keyword->visibility_status;

        $keyword->forceFill([
            'observed_rank' => $observedRank,
            'result_count' => max(0, $resultCount),
            'checked_result_count' => max(0, min(65535, $checkedResultCount)),
            'visibility_status' => $visibility['status'],
            'visibility_note' => $visibility['note'],
            'last_checked_at' => now(),
        ])->save();

        $keyword->observations()->create([
            'observed_rank' => $observedRank,
            'result_count' => max(0, $resultCount),
            'checked_result_count' => max(0, min(65535, $checkedResultCount)),
            'visibility_status' => $visibility['status'],
        ]);

        $fresh = $keyword->fresh() ?: $keyword;
        $this->notificationService->notifyKeywordVisibility($fresh, $previousRank, $previousStatus);

        return $fresh;
    }

    /**
     * @return array{status: string, note: string}
     */
    protected function visibility(?int $rank, int $targetRank, string $productId, int $checkedResultCount = 0): array
    {
        if ($productId === '') {
            return [
                'status' => 'tracking',
                'note' => 'Ürün ID bilinmediği için sıralama eşleştirilemedi.',
            ];
        }

        if ($rank === null) {
            return [
                'status' => 'missing',
                'note' => $checkedResultCount > 0
                    ? "İlk {$checkedResultCount} ürün kontrol edildi; ürün bu aralıkta bulunamadı."
                    : 'Arama sonuçları kontrol edildi; ürün bulunamadı.',
            ];
        }

        if ($rank <= $targetRank) {
            return [
                'status' => 'visible',
                'note' => "Ürün hedef sıra içinde: {$rank}. sıra.",
            ];
        }

        if ($rank <= max($targetRank * 2, $targetRank + 10)) {
            return [
                'status' => 'near',
                'note' => "Ürün hedefe yakın: {$rank}. sıra.",
            ];
        }

        return [
            'status' => 'low_visibility',
            'note' => "Ürün görünür ama hedefin gerisinde: {$rank}. sıra.",
        ];
    }

    protected function normalizeKeyword(string $keyword): string
    {
        $keyword = html_entity_decode(strip_tags($keyword), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $keyword = preg_replace('/\s+/u', ' ', $keyword) ?: '';

        return trim(Str::limit($keyword, 180, ''));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function logSyncIssue(TrendyolBoosterKeyword $keyword, string $type, string $title, string $message, array $payload = []): void
    {
        $keyword->loadMissing('trackedProduct');

        $this->activityLogger->log(
            (int) $keyword->user_id,
            $type,
            $title,
            $keyword->keyword,
            Str::limit($message, 600, ''),
            'durum',
            null,
            $payload + [
                'source' => 'keyword_monitor',
                'keyword_id' => $keyword->id,
                'keyword' => $keyword->keyword,
            ],
            $keyword->trendyol_booster_product_id,
        );
    }
}
