<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLivewireAuthenticatedUnlessPublic
{
    private const PUBLIC_COMPONENTS = [
        'public-trendyol-profit-calculator',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() || $this->containsOnlyPublicComponents($request)) {
            return $next($request);
        }

        return redirect()->guest(route('login'));
    }

    protected function containsOnlyPublicComponents(Request $request): bool
    {
        $components = $request->input('components', []);

        if (! is_array($components) || $components === []) {
            return false;
        }

        foreach ($components as $component) {
            $snapshot = data_get($component, 'snapshot');

            if (is_string($snapshot)) {
                $snapshot = json_decode($snapshot, true);
            }

            $name = is_array($snapshot) ? data_get($snapshot, 'memo.name') : null;

            if (! is_string($name) || ! in_array($name, self::PUBLIC_COMPONENTS, true)) {
                return false;
            }
        }

        return true;
    }
}
