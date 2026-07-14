<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceCustomerCareTls
{
    public function handle(Request $request, Closure $next): Response
    {
        $scoped = $request->is('customer-care/*') || $request->is('api/customer-care/*');
        if (!$scoped || !config('customer-care.force_https', false)) {
            return $next($request);
        }
        if (!$request->secure()) {
            if ($request->isMethod('GET') && !$request->is('api/*')) {
                return redirect()->secure($request->getRequestUri(), 301);
            }
            return response()->json(['error' => 'TLS zorunludur.'], 426);
        }
        $response = $next($request);
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        return $response;
    }
}
