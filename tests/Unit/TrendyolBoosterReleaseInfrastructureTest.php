<?php

namespace Tests\Unit;

use App\Http\Middleware\EnsureTrendyolBoosterReleaseAccess;
use App\Models\Role;
use App\Models\TrendyolBoosterOperationMetric;
use App\Models\User;
use App\Services\Marketplace\TrendyolBoosterObservabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TrendyolBoosterReleaseInfrastructureTest extends TestCase
{
    public function test_ga_ring_allows_an_authenticated_operator(): void
    {
        config()->set('marketplace.trendyol_booster.release.ring', 'ga');

        $response = (new EnsureTrendyolBoosterReleaseAccess())->handle(
            $this->requestFor($this->operator(42)),
            fn (): Response => new Response('ok'),
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_beta_ring_allows_only_the_allowlist_for_non_admin_users(): void
    {
        config()->set('marketplace.trendyol_booster.release.ring', 'beta');
        config()->set('marketplace.trendyol_booster.release.beta_user_ids', [42]);

        $middleware = new EnsureTrendyolBoosterReleaseAccess();
        $this->assertSame(200, $middleware->handle(
            $this->requestFor($this->operator(42)),
            fn (): Response => new Response('ok'),
        )->getStatusCode());

        $this->expectException(HttpException::class);
        $middleware->handle(
            $this->requestFor($this->operator(43)),
            fn (): Response => new Response('unreachable'),
        );
    }

    public function test_companion_route_has_all_release_security_middleware(): void
    {
        $middleware = Route::getRoutes()
            ->getByName('mp.trendyol-booster.companion.product-analysis')
            ?->gatherMiddleware() ?? [];

        $this->assertContains('mp.feature:trendyol_booster_enabled', $middleware);
        $this->assertContains('throttle:booster-companion', $middleware);
        $this->assertContains('booster.release', $middleware);
        $this->assertContains('booster.metric', $middleware);
        $this->assertTrue(collect($middleware)->contains(
            fn (string $item): bool => str_contains($item, 'AdminMiddleware'),
        ));
        $this->assertNotNull(RateLimiter::limiter('booster-companion'));
    }

    public function test_observability_fails_open_before_the_metric_migration(): void
    {
        Schema::shouldReceive('hasTable')
            ->once()
            ->with('trendyol_booster_operation_metrics')
            ->andReturnFalse();

        $dashboard = app(TrendyolBoosterObservabilityService::class)->dashboard(42);

        $this->assertFalse($dashboard['available']);
        $this->assertFalse($dashboard['has_data']);
        $this->assertSame(0, $dashboard['request_count']);
    }

    public function test_metric_model_has_no_payload_or_url_fields(): void
    {
        $fillable = (new TrendyolBoosterOperationMetric())->getFillable();

        $this->assertNotContains('payload', $fillable);
        $this->assertNotContains('url', $fillable);
        $this->assertNotContains('query', $fillable);
        $this->assertContains('duration_ms', $fillable);
    }

    protected function requestFor(User $user): Request
    {
        $request = Request::create('/marketplace-trendyol-booster', 'GET');
        $request->setUserResolver(fn (): User => $user);

        return $request;
    }

    protected function operator(int $id): User
    {
        $user = new User(['name' => 'Pilot', 'email' => 'pilot-'.$id.'@example.test']);
        $user->id = $id;
        $user->setRelation('role', new Role(['slug' => 'operator']));

        return $user;
    }
}
