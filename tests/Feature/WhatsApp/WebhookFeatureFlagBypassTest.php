<?php

namespace Tests\Feature\WhatsApp;

class WebhookFeatureFlagBypassTest extends WhatsAppTestCase
{
    public function test_meta_webhook_route_always_registered(): void
    {
        $this->app['config']->set('whatsapp.features.whatsapp_enabled', false);

        // Route listesinde whatsapp/webhook GET route'unun tanımlı olduğunu doğrula
        $routes = app('router')->getRoutes();
        $found = false;
        foreach ($routes as $route) {
            $methods = array_map('strtoupper', $route->methods());
            $uri = $route->uri();

            if (in_array('GET', $methods) && str_contains($uri, 'whatsapp/webhook')) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Meta webhook GET route feature flag kapalıyken bile tanımlı olmalı');
    }

    public function test_admin_routes_blocked_when_feature_flag_disabled(): void
    {
        $this->app['config']->set('whatsapp.features.whatsapp_enabled', false);

        $user = \App\Models\User::create([
            'name' => 'Admin',
            'email' => 'admin-fb@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->actingAs($user);

        $response = $this->get(route('whatsapp.overview'));
        $response->assertStatus(404);
    }
}
