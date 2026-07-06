<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdsAccessMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        if (!auth()->user()->canAccessAds()) {
            abort(403, 'Reklam Zekâsı modülüne erişim yetkiniz bulunmuyor.');
        }

        return $next($request);
    }
}
