<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMarketplaceFeatureEnabled
{
    public function handle(Request $request, Closure $next, ?string $feature = null): Response
    {
        if (!config('marketplace.features.v2_enabled', true)) {
            abort(404);
        }

        if ($feature && !config('marketplace.features.' . $feature, true)) {
            abort(404);
        }

        return $next($request);
    }
}
