<?php

use App\Http\Middleware\EnforceCustomerCareTls;
use App\Http\Middleware\EnsureCustomerCareFeatureEnabled;
use App\Http\Middleware\EnsureMarketplaceFeatureEnabled;
use App\Http\Middleware\ZolmRuntimeParityMiddleware;
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
            'customer-care.feature' => EnsureCustomerCareFeatureEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
