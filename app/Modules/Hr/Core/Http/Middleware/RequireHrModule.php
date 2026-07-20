<?php

namespace App\Modules\Hr\Core\Http\Middleware;

use App\Modules\Hr\Core\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireHrModule
{
    public function handle(Request $request, Closure $next, string $moduleKey): Response
    {
        $tenant = app(TenantContext::class)->get();

        $license = $tenant->hrLicenses()
            ->where('module_key', $moduleKey)
            ->where('is_active', true)
            ->first();

        if (!$license) {
            abort(403, 'Bu modül için lisans bulunmuyor veya modül pasif.');
        }

        if ($license->expires_at && $license->expires_at->isPast()) {
            abort(403, 'Modül lisansının süresi dolmuş.');
        }

        return $next($request);
    }
}
