<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\QueueBusy;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Services\WhatsApp\AiProviderInterface::class,
            \App\Services\WhatsApp\FakeAiProvider::class,
        );
        $this->app->bind(
            \App\Services\Support\AI\CustomerCareAiProviderInterface::class,
            \App\Services\Support\AI\GeminiCustomerCareAiAdapter::class,
        );
        $this->app->bind(
            \App\Services\Support\MetaSocialConnectorInterface::class,
            \App\Services\Support\HttpMetaSocialConnector::class,
        );
        $this->app->bind(
            \App\Services\Support\GoogleBusinessConnectorInterface::class,
            \App\Services\Support\HttpGoogleBusinessConnector::class,
        );
        $this->app->bind(
            \App\Services\Support\Integration\GenericCrmConnectorInterface::class,
            \App\Services\Support\Integration\GenericHttpCrmConnector::class,
        );
        $this->app->bind(
            \App\Services\Support\Integration\GenericErpConnectorInterface::class,
            \App\Services\Support\Integration\GenericHttpErpConnector::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureViteHotReloadFallback();
        $this->registerWhatsAppEvents();
        $this->registerQueueHealthEvents();
        RateLimiter::for('customer-care-enterprise', function (Request $request) {
            $tokenFingerprint = hash('sha256', (string) $request->bearerToken());
            return Limit::perMinute(120)->by($tokenFingerprint . '|' . $request->ip());
        });
        RateLimiter::for('booster-companion', function (Request $request) {
            return Limit::perMinute(120)->by('booster:'.($request->user()?->id ?: $request->ip()));
        });

        \Illuminate\Support\Facades\Gate::policy(
            \App\Models\SupportConversation::class,
            \App\Policies\SupportConversationPolicy::class
        );

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

    protected function registerWhatsAppEvents(): void
    {
        Event::listen(
            \App\Events\ShipmentStatusChanged::class,
            \App\Listeners\WhatsApp\SendShippingNotificationListener::class,
        );

        Event::listen(
            \App\Events\ProductStockChanged::class,
            \App\Listeners\WhatsApp\ProcessStockAlertListener::class,
        );

        Event::listen(
            \App\Events\OrderStatusChanged::class,
            \App\Listeners\WhatsApp\ProcessOrderNotificationListener::class,
        );

        Event::listen(
            \App\Events\ReturnStatusChanged::class,
            \App\Listeners\WhatsApp\ProcessReturnNotificationListener::class,
        );
    }

    protected function registerQueueHealthEvents(): void
    {
        Event::listen(QueueBusy::class, function (QueueBusy $event): void {
            Log::critical('[QueueHealth] Kuyruk eşiği aşıldı.', [
                'connection' => $event->connection,
                'queue' => $event->queue,
                'size' => $event->size,
                'threshold' => (int) config('queue.monitor.max_jobs', 100),
            ]);
        });
    }
}
