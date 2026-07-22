<?php

namespace App\Services\Marketplace;

use App\Models\TrendyolBoosterOperationMetric;
use Illuminate\Support\Facades\Schema;

class TrendyolBoosterObservabilityService
{
    /** @return array<string, mixed> */
    public function dashboard(?int $userId = null, int $minutes = 60): array
    {
        $minutes = max(5, min(1440, $minutes));
        $ring = strtolower((string) config('marketplace.trendyol_booster.release.ring', 'ga'));

        if (! Schema::hasTable('trendyol_booster_operation_metrics')) {
            return $this->emptyDashboard($ring, $minutes, false);
        }

        $metrics = TrendyolBoosterOperationMetric::query()
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->where('occurred_at', '>=', now()->subMinutes($minutes))
            ->get(['route_name', 'status_code', 'duration_ms', 'outcome', 'occurred_at']);

        if ($metrics->isEmpty()) {
            return $this->emptyDashboard($ring, $minutes, true);
        }

        $durations = $metrics->pluck('duration_ms')->map(fn ($value): int => (int) $value)->sort()->values();
        $p95Index = max(0, (int) ceil($durations->count() * 0.95) - 1);
        $errors = $metrics->where('outcome', 'error')->count();
        $rejected = $metrics->where('outcome', 'rejected')->count();
        $errorRate = round(($errors / $metrics->count()) * 100, 2);
        $threshold = (float) config('marketplace.trendyol_booster.observability.error_rate_warning_percent', 5);
        $healthy = $errorRate <= $threshold;

        return [
            'available' => true,
            'has_data' => true,
            'healthy' => $healthy,
            'tone' => $healthy ? 'emerald' : 'rose',
            'label' => $healthy ? 'Companion akışı sağlıklı' : 'Companion hata oranı yüksek',
            'release_ring' => $ring,
            'window_minutes' => $minutes,
            'request_count' => $metrics->count(),
            'success_count' => $metrics->where('outcome', 'success')->count(),
            'rejected_count' => $rejected,
            'error_count' => $errors,
            'error_rate' => $errorRate,
            'p95_duration_ms' => (int) ($durations->get($p95Index) ?? 0),
            'last_seen_at' => $metrics->max('occurred_at'),
            'top_routes' => $metrics->groupBy('route_name')->map(fn ($rows, $route): array => [
                'route' => (string) $route,
                'count' => $rows->count(),
                'errors' => $rows->where('outcome', 'error')->count(),
            ])->sortByDesc('count')->take(5)->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    protected function emptyDashboard(string $ring, int $minutes, bool $available): array
    {
        return [
            'available' => $available,
            'has_data' => false,
            'healthy' => $available,
            'tone' => $available ? 'sky' : 'amber',
            'label' => $available ? 'İlk companion çağrısı bekleniyor' : 'Telemetri migration bekliyor',
            'release_ring' => $ring,
            'window_minutes' => $minutes,
            'request_count' => 0,
            'success_count' => 0,
            'rejected_count' => 0,
            'error_count' => 0,
            'error_rate' => 0.0,
            'p95_duration_ms' => 0,
            'last_seen_at' => null,
            'top_routes' => [],
        ];
    }
}
