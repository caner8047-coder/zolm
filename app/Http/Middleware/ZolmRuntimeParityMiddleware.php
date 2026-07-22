<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ZolmRuntimeParityMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (Auth::check()) {
            $appEnv = app()->environment();

            try {
                $dbDriver = DB::connection()->getDriverName();
            } catch (\Exception $e) {
                $dbDriver = 'unknown';
            }

            $dbName = config('database.connections.'.config('database.default').'.database');
            $dbHost = config('database.connections.'.config('database.default').'.host');
            $hostname = gethostname();

            $dbDatabaseHash = substr(hash('sha256', (string) $dbName), 0, 12);
            $dbHostHash = substr(hash('sha256', (string) $dbHost), 0, 12);
            $hostnameHash = substr(hash('sha256', (string) $hostname), 0, 12);

            $runtimeId = "{$appEnv}_{$dbDriver}_{$dbDatabaseHash}_{$dbHostHash}_{$hostnameHash}";

            $response->headers->set('X-Zolm-Release', 'a7d8bd7');
            $response->headers->set('X-Zolm-Runtime-ID', $runtimeId);
        }

        return $response;
    }
}
