<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureReturnFeatureEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('returns.enabled', true)) {
            abort(404);
        }

        return $next($request);
    }
}
