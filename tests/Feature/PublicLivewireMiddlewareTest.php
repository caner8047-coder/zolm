<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureLivewireAuthenticatedUnlessPublic;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class PublicLivewireMiddlewareTest extends TestCase
{
    public function test_guest_can_update_allowlisted_public_livewire_component(): void
    {
        $request = Request::create('/livewire/update', 'POST', [
            'components' => [[
                'snapshot' => json_encode([
                    'memo' => ['name' => 'public-trendyol-profit-calculator'],
                ]),
            ]],
        ]);

        $response = app(EnsureLivewireAuthenticatedUnlessPublic::class)
            ->handle($request, fn () => new Response('ok'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', $response->getContent());
    }

    public function test_guest_is_redirected_for_non_public_livewire_component(): void
    {
        $request = Request::create('/livewire/update', 'POST', [
            'components' => [[
                'snapshot' => json_encode([
                    'memo' => ['name' => 'marketplace-pricing-simulator'],
                ]),
            ]],
        ]);

        $response = app(EnsureLivewireAuthenticatedUnlessPublic::class)
            ->handle($request, fn () => new Response('ok'));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringEndsWith('/login', (string) $response->headers->get('Location'));
    }

    public function test_guest_is_redirected_when_update_payload_has_no_component(): void
    {
        $request = Request::create('/livewire/update', 'POST');

        $response = app(EnsureLivewireAuthenticatedUnlessPublic::class)
            ->handle($request, fn () => new Response('ok'));

        $this->assertSame(302, $response->getStatusCode());
    }
}
