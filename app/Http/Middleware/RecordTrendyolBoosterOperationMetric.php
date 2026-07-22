<?php

namespace App\Http\Middleware;

use App\Models\TrendyolBoosterOperationMetric;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordTrendyolBoosterOperationMetric
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);

        try {
            $response = $next($request);
        } catch (\Throwable $exception) {
            $this->record($request, 500, $startedAt);

            throw $exception;
        }

        $this->record($request, $response->getStatusCode(), $startedAt);

        return $response;
    }

    protected function record(Request $request, int $statusCode, int $startedAt): void
    {
        if (! (bool) config('marketplace.trendyol_booster.observability.enabled', true)) {
            return;
        }

        try {
            TrendyolBoosterOperationMetric::query()->create([
                'user_id' => $request->user()?->id,
                'route_name' => (string) ($request->route()?->getName() ?: 'unresolved'),
                'http_method' => strtoupper($request->method()),
                'status_code' => $statusCode,
                'duration_ms' => max(0, (int) round((hrtime(true) - $startedAt) / 1_000_000)),
                'release_ring' => strtolower((string) config('marketplace.trendyol_booster.release.ring', 'ga')),
                'outcome' => $statusCode >= 500 ? 'error' : ($statusCode >= 400 ? 'rejected' : 'success'),
                'occurred_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
