<?php

namespace Tests\Feature\WhatsApp;

use App\Http\Controllers\WhatsApp\WebhookController;
use Illuminate\Http\Request;

class WebhookVerifyTest extends WhatsAppTestCase
{
    public function test_verify_returns_challenge_on_valid_token(): void
    {
        config()->set('whatsapp.webhook.verify_token', 'my-secret-token');

        $controller = app(WebhookController::class);
        $request = new Request(
            ['hub.mode' => 'subscribe', 'hub.verify_token' => 'my-secret-token', 'hub.challenge' => 'CHALLENGE123'],
            [],
            [],
            [],
            [],
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/whatsapp/webhook']
        );

        $response = $controller->verify($request);

        $this->assertEquals(200, $response->status(), 'Expected 200 but got: ' . $response->content());
        $this->assertEquals('"CHALLENGE123"', $response->content());
    }

    public function test_verify_returns_403_on_invalid_token(): void
    {
        config()->set('whatsapp.webhook.verify_token', 'correct-token');

        $controller = app(WebhookController::class);
        $request = new Request(
            ['hub.mode' => 'subscribe', 'hub.verify_token' => 'wrong', 'hub.challenge' => 'CHALLENGE'],
            [],
            [],
            [],
            [],
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/whatsapp/webhook']
        );

        $response = $controller->verify($request);
        $this->assertEquals(403, $response->status());
    }

    public function test_verify_returns_403_on_missing_challenge(): void
    {
        config()->set('whatsapp.webhook.verify_token', 'token');

        $controller = app(WebhookController::class);
        $request = new Request(
            ['hub.mode' => 'subscribe', 'hub.verify_token' => 'token'],
            [],
            [],
            [],
            [],
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/whatsapp/webhook']
        );

        $response = $controller->verify($request);
        $this->assertEquals(403, $response->status());
    }
}
