<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTrendyolBoosterReleaseAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $ring = strtolower((string) config('marketplace.trendyol_booster.release.ring', 'ga'));

        if ($ring === 'off') {
            abort(404);
        }

        if ($ring === 'beta') {
            $user = $request->user();
            $allowedIds = collect(config('marketplace.trendyol_booster.release.beta_user_ids', []))
                ->map(fn ($id): int => (int) $id)
                ->filter()
                ->all();
            $allowed = $user && ($user->isAdmin() || in_array((int) $user->id, $allowedIds, true));

            if (! $allowed) {
                abort(403, 'Trendyol Booster kontrollü beta halkasında.');
            }
        }

        return $next($request);
    }
}
