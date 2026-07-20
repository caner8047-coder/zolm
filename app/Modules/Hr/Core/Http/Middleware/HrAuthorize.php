<?php

namespace App\Modules\Hr\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HrAuthorize
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(401);
        }

        foreach ($permissions as $permission) {
            if (!$user->hasHrPermission($permission)) {
                abort(403, 'Bu işlem için yetkiniz bulunmuyor.');
            }
        }

        return $next($request);
    }
}
