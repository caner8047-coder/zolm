<?php

namespace App\Http\Controllers;

use App\Services\Returns\ReturnBridgeSettingsService;
use App\Services\Returns\WhatsAppReturnBridgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WhatsAppReturnWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $settings = app(ReturnBridgeSettingsService::class);

        abort_unless((bool) $settings->get('enabled', false), 404);

        $mode = (string) $request->query('hub_mode', $request->query('hub.mode', ''));
        $verifyToken = (string) $request->query('hub_verify_token', $request->query('hub.verify_token', ''));
        $challenge = (string) $request->query('hub_challenge', $request->query('hub.challenge', ''));
        $expectedToken = trim((string) $settings->get('verify_token', ''));

        if ($mode !== 'subscribe' || $expectedToken === '' || !hash_equals($expectedToken, $verifyToken)) {
            abort(403);
        }

        return response($challenge, 200)->header('Content-Type', 'text/plain');
    }

    public function receive(Request $request, WhatsAppReturnBridgeService $bridgeService): JsonResponse
    {
        abort_unless((bool) app(ReturnBridgeSettingsService::class)->get('enabled', false), 404);

        if (!$bridgeService->verifySignature($request)) {
            return response()->json([
                'ok' => false,
                'message' => 'WhatsApp imzasi dogrulanamadi.',
            ], 403);
        }

        $summary = $bridgeService->handle($request->json()->all() ?: $request->all());

        return response()->json([
            'ok' => true,
            'summary' => $summary,
        ]);
    }
}
