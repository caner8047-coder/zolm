<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceStore;
use App\Models\WaWebhookEvent;
use App\Jobs\WhatsApp\ProcessBoosterEventJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BoosterWebhookController extends Controller
{
    public function handleEvent(Request $request): JsonResponse
    {
        // Header doğrulama
        $eventId = $request->header('X-ZOLM-Event-ID');
        $eventType = $request->header('X-ZOLM-Event-Type');
        $timestamp = $request->header('X-ZOLM-Timestamp');
        $signature = $request->header('X-ZOLM-Signature');
        $storeId = $request->header('X-ZOLM-Store-ID');
        $version = $request->header('X-ZOLM-Version');

        if (!$eventId || !$eventType || !$timestamp || !$signature || !$storeId) {
            return response()->json(['error' => 'Missing required headers'], 400);
        }

        // HMAC SHA-256 imza doğrulama
        $rawBody = $request->getContent();
        $appKey = config('app.key', '');
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $rawBody, $appKey);

        if (!hash_equals($expectedSignature, $signature)) {
            Log::warning('Booster webhook imza doğrulaması başarısız', ['event_id' => $eventId]);
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        // Timestamp yaşı kontrolü
        $maxAge = config('whatsapp.webhook.booster_max_age_seconds', 300);
        $eventTime = (int) $timestamp;
        if (abs(time() - $eventTime) > $maxAge) {
            return response()->json(['error' => 'Event too old'], 400);
        }

        // ── health.check: hemen dön, DB'ye yazma ──────────────
        if ($eventType === 'health.check') {
            $storeIdInt = (int) $storeId;

            if ($storeIdInt <= 0) {
                return response()->json(['error' => 'Invalid store_id'], 400);
            }

            $store = MarketplaceStore::where('id', $storeIdInt)
                ->where('marketplace', 'woocommerce')
                ->where('is_active', true)
                ->first();

            if (!$store) {
                return response()->json(['error' => 'WooCommerce store not found'], 404);
            }

            return response()->json(['ok' => true, 'store_id' => $store->id]);
        }

        // ── Normal event akışı ────────────────────────────────
        $existing = WaWebhookEvent::where('provider_event_key', $eventId)->first();

        if ($existing) {
            if ($existing->status === WaWebhookEvent::STATUS_FAILED
                || ($existing->status === WaWebhookEvent::STATUS_PENDING
                    && $existing->created_at < now()->subMinutes(5))
            ) {
                $existing->update([
                    'status' => WaWebhookEvent::STATUS_PENDING,
                    'duplicate_count' => $existing->duplicate_count + 1,
                    'error_message' => null,
                    'processed_at' => null,
                ]);
                ProcessBoosterEventJob::dispatch($existing->id);
            } else {
                $existing->update([
                    'duplicate_count' => $existing->duplicate_count + 1,
                ]);
            }

            return response()->json(['status' => 'duplicate']);
        }

        $event = WaWebhookEvent::create([
            'event_type' => $eventType,
            'request_id' => $eventId,
            'request_hash' => hash('sha256', $rawBody),
            'provider_event_key' => $eventId,
            'source' => 'booster',
            'payload' => $request->all(),
            'signature' => $signature,
            'verified_at' => now(),
            'status' => WaWebhookEvent::STATUS_PENDING,
        ]);

        ProcessBoosterEventJob::dispatch($event->id);

        return response()->json(['status' => 'ok']);
    }
}
