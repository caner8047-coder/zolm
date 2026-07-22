<?php

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
        $middleware->prepend(\App\Http\Middleware\EnforceCustomerCareTls::class);
        $middleware->alias([
            'mp.feature' => \App\Http\Middleware\EnsureMarketplaceFeatureEnabled::class,
            'booster.release' => \App\Http\Middleware\EnsureTrendyolBoosterReleaseAccess::class,
            'booster.metric' => \App\Http\Middleware\RecordTrendyolBoosterOperationMetric::class,
            'customer-care.feature' => \App\Http\Middleware\EnsureCustomerCareFeatureEnabled::class,
            'hr.authorize' => \App\Modules\Hr\Core\Http\Middleware\HrAuthorize::class,
            'hr.tenant' => \App\Modules\Hr\Core\Http\Middleware\ResolveHrTenant::class,
            'hr.module' => \App\Modules\Hr\Core\Http\Middleware\RequireHrModule::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
