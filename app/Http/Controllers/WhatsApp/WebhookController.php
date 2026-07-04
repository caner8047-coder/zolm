<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Models\WaWebhookEvent;
use App\Models\WaAccount;
use App\Jobs\WhatsApp\ProcessMetaWebhookJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    public function verify(Request $request): JsonResponse
    {
        $mode = $request->query('hub.mode');
        $token = $request->query('hub.verify_token');
        $challenge = $request->query('hub.challenge');

        $expectedToken = config('whatsapp.webhook.verify_token', '');

        if ($mode === 'subscribe' && $token === $expectedToken && $challenge) {
            return response()->json((string) $challenge);
        }

        return response()->json(['error' => 'Verification failed'], 403);
    }

    public function handleMeta(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();

        // Body size limit
        $maxBytes = config('whatsapp.webhook.meta_max_body_bytes', 1048576);
        if (strlen($rawBody) > $maxBytes) {
            return response()->json(['error' => 'Payload too large'], 413);
        }

        // HMAC SHA-256 imza doğrulama
        $signature = $request->header('X-Hub-Signature-256', '');
        $appSecret = config('whatsapp.webhook.app_secret', '');

        if ($appSecret !== '') {
            $expectedSignature = 'sha256=' . hash_hmac('sha256', $rawBody, $appSecret);
            if (!hash_equals($expectedSignature, $signature)) {
                Log::warning('Meta webhook imza doğrulaması başarısız');
                return response()->json(['error' => 'Invalid signature'], 403);
            }
        }

        // Basit rate limit (IP bazlı)
        $rateLimiter = \Illuminate\Support\Facades\RateLimiter::limiter('meta_webhook');
        if ($rateLimiter && $rateLimiter->tooManyAttempts($request->ip(), 100)) {
            return response()->json(['error' => 'Rate limit exceeded'], 429);
        }
        if ($rateLimiter) {
            $rateLimiter->hit($request->ip(), 60);
        }

        $payload = $request->all();
        $requestHash = hash('sha256', $rawBody);

        // Meta batch payload parse — her entry/change için ayrı webhook_event kaydı
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];

                // Status güncellemeleri
                foreach ($value['statuses'] ?? [] as $status) {
                    $messageId = $status['id'] ?? null;
                    $statusValue = $status['status'] ?? null;
                    $timestamp = $status['timestamp'] ?? '';

                    if (!$messageId || !$statusValue) {
                        continue;
                    }

                    $providerEventKey = hash('sha256', $messageId . $statusValue . $timestamp);

                    $phone_number_id = $value['metadata']['phone_number_id'] ?? null;
                    $account = $phone_number_id
                        ? WaAccount::where('phone_number_id', $phone_number_id)->where('is_active', true)->first()
                        : null;

                    $this->createOrRedeliverWebhookEvent(
                        $providerEventKey,
                        'status',
                        $requestHash,
                        $status,
                        $account,
                        $request->header('X-Request-Id'),
                    );
                }

                // Inbound mesajlar
                foreach ($value['messages'] ?? [] as $message) {
                    $messageId = $message['id'] ?? null;

                    if (!$messageId) {
                        continue;
                    }

                    $providerEventKey = $messageId;

                    $phone_number_id = $value['metadata']['phone_number_id'] ?? null;
                    $account = $phone_number_id
                        ? WaAccount::where('phone_number_id', $phone_number_id)->where('is_active', true)->first()
                        : null;

                    $this->createOrRedeliverWebhookEvent(
                        $providerEventKey,
                        'message',
                        $requestHash,
                        $message,
                        $account,
                        $request->header('X-Request-Id'),
                    );
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }

    private function createOrRedeliverWebhookEvent(
        string $providerEventKey,
        string $eventType,
        string $requestHash,
        array $payload,
        ?WaAccount $account,
        ?string $requestId,
    ): void {
        // Bilinmeyen phone_number_id — audit log ve 200
        if (!$account) {
            Log::warning('Meta webhook: bilinmeyen phone_number_id', [
                'provider_event_key' => $providerEventKey,
                'event_type' => $eventType,
            ]);
            return;
        }

        $existing = WaWebhookEvent::where('provider_event_key', $providerEventKey)->first();

        if ($existing) {
            // Duplicate — failed veya stale pending ise yeniden queue'ya al
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

                ProcessMetaWebhookJob::dispatch($existing->id);
            } else {
                $existing->update([
                    'duplicate_count' => $existing->duplicate_count + 1,
                ]);
            }

            return;
        }

        $event = WaWebhookEvent::create([
            'event_type' => $eventType,
            'request_id' => $requestId,
            'request_hash' => $requestHash,
            'provider_event_key' => $providerEventKey,
            'source' => 'meta',
            'payload' => $payload,
            'status' => WaWebhookEvent::STATUS_PENDING,
        ]);

        ProcessMetaWebhookJob::dispatch($event->id);
    }
}
