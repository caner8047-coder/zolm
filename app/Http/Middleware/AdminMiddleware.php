<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        // Kullanıcı aktif değilse
        if (!$user->is_active) {
            auth()->logout();
            return redirect()->route('login')
                ->withErrors(['email' => 'Hesabınız devre dışı bırakılmış.']);
        }

        // Operator veya üstü değilse erişim yok
        if (!$user->isOperator()) {
            abort(403, 'Bu sayfaya erişim yetkiniz yok.');
        }

        return $next($request);
    }
}
