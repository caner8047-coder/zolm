<?php

namespace App\Services\Marketplace;

use App\Models\TrendyolBoosterCompetitor;
use App\Models\TrendyolBoosterKeyword;
use App\Models\TrendyolBoosterProduct;
use App\Models\TrendyolBoosterStoreWatch;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class TrendyolBoosterSyncHealthService
{
    protected const LAST_RUN_CACHE_KEY = 'marketplace:trendyol-booster:last-scheduler-run-at';

    /**
     * @return array<string, mixed>
     */
    public function dashboard(int $userId): array
    {
        if (! Schema::hasTable('trendyol_booster_products')) {
            return $this->emptyDashboard();
        }

        $lastRunAt = $this->lastRunAt();
        $schedulerRecentMinutes = max(15, (int) config('marketplace.trendyol_booster.sync.scheduler_recent_minutes', 15));
        $schedulerHealthy = $lastRunAt !== null && $lastRunAt->greaterThanOrEqualTo(now()->subMinutes($schedulerRecentMinutes));
        $areas = collect([
            $this->productArea($userId),
            $this->analysisArea($userId),
            $this->competitorArea($userId),
            $this->keywordArea($userId),
            $this->storeArea($userId),
        ])->filter(fn (array $area): bool => (bool) ($area['available'] ?? false))->values();

        $trackedTotal = (int) $areas->sum('total_count');
        $dueTotal = (int) $areas->sum('due_count');
        $neverCheckedTotal = (int) $areas->sum('never_checked_count');
        $oldestDueAt = $areas
            ->pluck('oldest_due_at')
            ->filter()
            ->sortBy(fn (Carbon $date): int => $date->getTimestamp())
            ->first();

        return [
            'healthy' => $schedulerHealthy,
            'label' => $this->headline($schedulerHealthy, $trackedTotal, $dueTotal),
            'tone' => $this->tone($schedulerHealthy, $trackedTotal, $dueTotal),
            'last_run_at' => $lastRunAt,
            'last_run_age_minutes' => $lastRunAt?->diffInMinutes(now()),
            'recent_minutes' => $schedulerRecentMinutes,
            'tracked_total' => $trackedTotal,
            'due_total' => $dueTotal,
            'never_checked_total' => $neverCheckedTotal,
            'oldest_due_at' => $oldestDueAt,
            'areas' => $areas->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function productArea(int $userId): array
    {
        $query = TrendyolBoosterProduct::query()
            ->where('user_id', $userId)
            ->when(
                Schema::hasColumn('trendyol_booster_products', 'tracking_status'),
                fn (Builder $query) => $query->where('tracking_status', 'active'),
            )
            ->when(
                Schema::hasColumn('trendyol_booster_products', 'analysis_auto_refresh_enabled'),
                fn (Builder $query) => $query->where('analysis_auto_refresh_enabled', false),
            )
            ->where(function (Builder $query): void {
                $query->where('watch_price', true)->orWhere('watch_stock', true);
            });

        return $this->staleArea(
            key: 'product',
            label: 'Ürün fiyat/stok',
            detail: 'Snapshot kontrolü',
            query: $query,
            checkedColumn: 'last_checked_at',
            staleMinutes: $this->staleMinutes('product', 60),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function analysisArea(int $userId): array
    {
        if (! Schema::hasColumn('trendyol_booster_products', 'analysis_auto_refresh_enabled')
            || ! Schema::hasColumn('trendyol_booster_products', 'next_analysis_refresh_at')) {
            return $this->unavailableArea('analysis', 'Tam ürün analizi');
        }

        $query = TrendyolBoosterProduct::query()
            ->where('user_id', $userId)
            ->where('analysis_auto_refresh_enabled', true)
            ->when(
                Schema::hasColumn('trendyol_booster_products', 'tracking_status'),
                fn (Builder $query) => $query->where('tracking_status', 'active'),
            );

        $dueQuery = (clone $query)->where(function (Builder $query): void {
            $query->whereNull('next_analysis_refresh_at')
                ->orWhere('next_analysis_refresh_at', '<=', now());
        });

        return $this->areaPayload(
            key: 'analysis',
            label: 'Tam ürün analizi',
            detail: 'Companion/veri kalitesi yenileme',
            total: (int) (clone $query)->count(),
            due: (int) (clone $dueQuery)->count(),
            neverChecked: (int) (clone $query)->whereNull('last_analysis_refresh_at')->count(),
            staleMinutes: null,
            lastCheckedAt: $this->dateValue((clone $query)->max('last_analysis_refresh_at')),
            oldestDueAt: $this->dateValue((clone $dueQuery)->whereNotNull('next_analysis_refresh_at')->min('next_analysis_refresh_at')),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function competitorArea(int $userId): array
    {
        if (! Schema::hasTable('trendyol_booster_competitors')) {
            return $this->unavailableArea('competitor', 'Rakip ürün');
        }

        return $this->staleArea(
            key: 'competitor',
            label: 'Rakip ürün',
            detail: 'Rakip fiyat/katalog kontrolü',
            query: TrendyolBoosterCompetitor::query()->where('user_id', $userId)->where('is_active', true),
            checkedColumn: 'last_checked_at',
            staleMinutes: $this->staleMinutes('competitor', 240),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function keywordArea(int $userId): array
    {
        if (! Schema::hasTable('trendyol_booster_keywords')) {
            return $this->unavailableArea('keyword', 'Kelime takibi');
        }

        return $this->staleArea(
            key: 'keyword',
            label: 'Kelime takibi',
            detail: 'Sıralama görünürlüğü',
            query: TrendyolBoosterKeyword::query()->where('user_id', $userId)->where('is_active', true),
            checkedColumn: 'last_checked_at',
            staleMinutes: $this->staleMinutes('keyword', 360),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function storeArea(int $userId): array
    {
        if (! Schema::hasTable('trendyol_booster_store_watches')) {
            return $this->unavailableArea('store', 'Rakip mağaza');
        }

        return $this->staleArea(
            key: 'store',
            label: 'Rakip mağaza',
            detail: 'Mağaza katalog taraması',
            query: TrendyolBoosterStoreWatch::query()->where('user_id', $userId)->where('is_active', true),
            checkedColumn: 'last_checked_at',
            staleMinutes: $this->staleMinutes('store', 720),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function staleArea(
        string $key,
        string $label,
        string $detail,
        Builder $query,
        string $checkedColumn,
        int $staleMinutes,
    ): array {
        $dueQuery = (clone $query)->where(function (Builder $query) use ($checkedColumn, $staleMinutes): void {
            $query->whereNull($checkedColumn)
                ->orWhere($checkedColumn, '<=', now()->subMinutes($staleMinutes));
        });

        return $this->areaPayload(
            key: $key,
            label: $label,
            detail: $detail,
            total: (int) (clone $query)->count(),
            due: (int) (clone $dueQuery)->count(),
            neverChecked: (int) (clone $query)->whereNull($checkedColumn)->count(),
            staleMinutes: $staleMinutes,
            lastCheckedAt: $this->dateValue((clone $query)->max($checkedColumn)),
            oldestDueAt: $this->dateValue((clone $dueQuery)->whereNotNull($checkedColumn)->min($checkedColumn)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function areaPayload(
        string $key,
        string $label,
        string $detail,
        int $total,
        int $due,
        int $neverChecked,
        ?int $staleMinutes,
        ?Carbon $lastCheckedAt,
        ?Carbon $oldestDueAt,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'detail' => $detail,
            'available' => true,
            'total_count' => $total,
            'due_count' => $due,
            'never_checked_count' => $neverChecked,
            'stale_minutes' => $staleMinutes,
            'last_checked_at' => $lastCheckedAt,
            'oldest_due_at' => $oldestDueAt,
            'tone' => $this->areaTone($total, $due),
            'status_label' => $this->areaLabel($total, $due, $neverChecked),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function unavailableArea(string $key, string $label): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'available' => false,
            'total_count' => 0,
            'due_count' => 0,
            'never_checked_count' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyDashboard(): array
    {
        return [
            'healthy' => false,
            'label' => 'Tarama bilgisi bekliyor',
            'tone' => 'slate',
            'last_run_at' => null,
            'last_run_age_minutes' => null,
            'recent_minutes' => 15,
            'tracked_total' => 0,
            'due_total' => 0,
            'never_checked_total' => 0,
            'oldest_due_at' => null,
            'areas' => [],
        ];
    }

    protected function headline(bool $schedulerHealthy, int $trackedTotal, int $dueTotal): string
    {
        if ($trackedTotal === 0) {
            return 'Takip bekliyor';
        }

        if (! $schedulerHealthy) {
            return 'Tarama motoru bekliyor';
        }

        return $dueTotal > 0 ? 'Tarama motoru çalışıyor' : 'Tarama güncel';
    }

    protected function tone(bool $schedulerHealthy, int $trackedTotal, int $dueTotal): string
    {
        if ($trackedTotal === 0) {
            return 'slate';
        }

        if (! $schedulerHealthy) {
            return 'amber';
        }

        return $dueTotal > 0 ? 'sky' : 'emerald';
    }

    protected function areaTone(int $total, int $due): string
    {
        if ($total === 0) {
            return 'slate';
        }

        if ($due === 0) {
            return 'emerald';
        }

        return $due >= max(3, (int) ceil($total * 0.5)) ? 'amber' : 'sky';
    }

    protected function areaLabel(int $total, int $due, int $neverChecked): string
    {
        if ($total === 0) {
            return 'Kayıt yok';
        }

        if ($due === 0) {
            return 'Güncel';
        }

        if ($neverChecked > 0) {
            return "{$due} iş bekliyor · {$neverChecked} ilk tarama";
        }

        return "{$due} iş bekliyor";
    }

    protected function staleMinutes(string $area, int $default): int
    {
        return max(1, (int) config("marketplace.trendyol_booster.sync.{$area}_stale_minutes", $default));
    }

    protected function lastRunAt(): ?Carbon
    {
        $value = Cache::get(self::LAST_RUN_CACHE_KEY);

        return $this->dateValue($value);
    }

    protected function dateValue(mixed $value): ?Carbon
    {
        if (! filled($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
