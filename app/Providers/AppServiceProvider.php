<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureViteHotReloadFallback();

        // Livewire endpointlerini sabit path'e alarak tarayici cache/ad-block etkisini azalt.
        Livewire::setScriptRoute(function ($handle) {
            return Route::get('/livewire/livewire.js', $handle);
        });

        Livewire::setUpdateRoute(function ($handle) {
            return Route::post('/livewire/update', $handle)->middleware([
                'web',
                \App\Http\Middleware\EnsureLivewireAuthenticatedUnlessPublic::class,
            ]);
        });
    }

    protected function configureViteHotReloadFallback(): void
    {
        $hotFile = public_path('hot');

        if (!is_file($hotFile)) {
            return;
        }

        $hotUrl = trim((string) file_get_contents($hotFile));

        if ($hotUrl === '' || !$this->isViteDevServerReachable($hotUrl)) {
            Vite::useHotFile(storage_path('framework/vite.hot'));
        }
    }

    protected function isViteDevServerReachable(string $hotUrl): bool
    {
        $parts = parse_url($hotUrl);

        $host = $parts['host'] ?? null;
        $scheme = $parts['scheme'] ?? 'http';
        $port = isset($parts['port'])
            ? (int) $parts['port']
            : ($scheme === 'https' ? 443 : 80);

        if (!is_string($host) || $host === '' || $port <= 0) {
            return false;
        }

        $connection = @fsockopen($host, $port, $errno, $errorMessage, 0.2);

        if (!is_resource($connection)) {
            return false;
        }

        fclose($connection);

        return true;
    }
}
