<?php

namespace App\Modules\Hr\Core\Http\Middleware;

use App\Models\LegalEntity;
use App\Modules\Hr\Core\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveHrTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(401);
        }

        $tenant = LegalEntity::where('is_active', true)
            ->where('user_id', $user->id)
            ->first();

        if (!$tenant) {
            abort(403, 'Aktif tüzel kişilik bulunamadı.');
        }

        app(TenantContext::class)->set($tenant);

        return $next($request);
    }
}
