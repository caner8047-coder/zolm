<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerCareFeatureEnabled
{
    /**
     * Gelen isteğin müşteri hizmetleri özellik bayraklarına göre yetkilendirilmesi.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $feature
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, ?string $feature = null): Response
    {
        // Master flag kontrolü: customer-care.enabled varsayılan olarak false
        if (!config('customer-care.enabled', false)) {
            abort(404);
        }

        // Alt özellik bayrağı kontrolü:
        if ($feature && !config('customer-care.' . $feature, false)) {
            abort(404);
        }

        return $next($request);
    }
}
