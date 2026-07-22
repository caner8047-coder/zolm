<?php

use App\Http\Middleware\EnforceCustomerCareTls;
use App\Http\Middleware\EnsureCustomerCareFeatureEnabled;
use App\Http\Middleware\EnsureMarketplaceFeatureEnabled;
use App\Http\Middleware\EnsureTrendyolBoosterReleaseAccess;
use App\Http\Middleware\RecordTrendyolBoosterOperationMetric;
use App\Http\Middleware\ZolmRuntimeParityMiddleware;
use App\Modules\Hr\Core\Http\Middleware\HrAuthorize;
use App\Modules\Hr\Core\Http\Middleware\RequireHrModule;
use App\Modules\Hr\Core\Http\Middleware\ResolveHrTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(EnforceCustomerCareTls::class);
        $middleware->web(append: ZolmRuntimeParityMiddleware::class);
        $middleware->alias([
            'mp.feature' => EnsureMarketplaceFeatureEnabled::class,
            'booster.release' => EnsureTrendyolBoosterReleaseAccess::class,
            'booster.metric' => RecordTrendyolBoosterOperationMetric::class,
            'customer-care.feature' => EnsureCustomerCareFeatureEnabled::class,
            'hr.authorize' => HrAuthorize::class,
            'hr.tenant' => ResolveHrTenant::class,
            'hr.module' => RequireHrModule::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
