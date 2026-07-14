<?php

namespace App\Http\Controllers\CustomerCare;

use App\Http\Controllers\Controller;
use App\Models\IntegrationConnection;
use App\Models\MarketplaceStore;
use App\Models\SupportChannel;
use App\Models\SupportInboundWebhookReceipt;
use App\Services\Support\GoogleBusinessSupportChannelAdapter;
use App\Services\Support\Integration\CustomerCareIntegrationHubService;
use App\Services\Support\MetaSocialSupportChannelAdapter;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class InboundChannelWebhookController extends Controller
{
    private const PROVIDERS = ['meta', 'instagram', 'facebook', 'google_business', 'crm', 'erp'];

    public function verifyMeta(Request $request, MarketplaceStore $store)
    {
        $this->enforceFeatureEnabled('meta');
        $connection = $this->connection($store, 'meta');
        $expected = (string) (($connection->credentials_encrypted ?? [])['verify_token'] ?? '');
        abort_unless($expected !== '' && hash_equals($expected, (string) $request->query('hub_verify_token')), 403);
        abort_unless($request->query('hub_mode') === 'subscribe', 422);
        return response((string) $request->query('hub_challenge'), 200)->header('Content-Type', 'text/plain');
    }

    public function receive(Request $request, string $provider, MarketplaceStore $store, CustomerCareIntegrationHubService $hub): JsonResponse
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);
        $this->enforceFeatureEnabled($provider);
        abort_if(strlen($request->getContent()) > 1_048_576, 413, 'Webhook payload limiti aşıldı.');
        abort_unless(str_contains((string) $request->header('Content-Type'), 'application/json'), 415);
        $rateKey = 'cc-inbound:' . $store->id . ':' . $provider . ':' . hash('sha256', (string) $request->ip());
        abort_unless(RateLimiter::attempt($rateKey, 300, fn () => true, 60), 429);

        $connection = $this->connection($store, $provider);
        $raw = $request->getContent();
        $payload = json_decode($raw, true);
        abort_unless(is_array($payload), 422, 'Geçersiz JSON.');
        $timestamp = $this->verifySignatureAndTimestamp($request, $provider, $connection, $raw, $payload);

        [$eventId, $normalized, $channelKey] = $this->normalize($provider, $payload, $timestamp);
        abort_unless(preg_match('/^[A-Za-z0-9._:\/-]{1,190}$/', $eventId) === 1, 422, 'Webhook event kimliği geçersiz.');

        try {
            $receipt = SupportInboundWebhookReceipt::create([
                'store_id' => $store->id,
                'integration_connection_id' => $connection->id,
                'provider' => $provider,
                'event_id' => $eventId,
                'payload_hash' => hash('sha256', $raw),
                'status' => 'received',
                'received_at' => now(),
            ]);
        } catch (QueryException $e) {
            if (SupportInboundWebhookReceipt::where('store_id', $store->id)->where('provider', $provider)->where('event_id', $eventId)->exists()) {
                return response()->json(['success' => true, 'duplicate' => true]);
            }
            throw $e;
        }

        try {
            if (in_array($provider, ['meta', 'instagram', 'facebook'], true)) {
                $channel = $this->channel($store, $channelKey);
                $normalized['store_id'] = $store->id;
                $result = (new MetaSocialSupportChannelAdapter($channelKey))->projectInboundWebhook($channel, $normalized);
            } elseif ($provider === 'google_business') {
                $channel = $this->channel($store, 'google_business');
                $normalized['store_id'] = $store->id;
                $result = app(GoogleBusinessSupportChannelAdapter::class)->projectReview($channel, $normalized);
            } else {
                $event = $hub->recordInboundEvent($store->id, $provider, $eventId, $normalized);
                $result = ['success' => true, 'integration_event_id' => $event->id];
            }
            if (!($result['success'] ?? false)) throw new \RuntimeException((string) ($result['message'] ?? 'Webhook projeksiyonu başarısız.'));
            $receipt->update(['status' => 'processed', 'processed_at' => now()]);
            return response()->json(['success' => true, 'duplicate' => !($result['projected'] ?? true)], 202);
        } catch (\Throwable $e) {
            $receipt->update([
                'status' => 'failed',
                'last_error' => mb_substr(app(\App\Services\Support\Security\PiiRedactor::class)->maskPii($e->getMessage()), 0, 500),
            ]);
            return response()->json(['error' => 'Webhook güvenle alındı ancak işlenemedi.', 'receipt_id' => $receipt->id], 422);
        }
    }

    private function verifySignatureAndTimestamp(Request $request, string $provider, IntegrationConnection $connection, string $raw, array $payload): int
    {
        $credentials = $connection->credentials_encrypted ?? [];
        if (in_array($provider, ['meta', 'instagram', 'facebook'], true)) {
            $secret = (string) ($credentials['app_secret'] ?? $connection->webhook_secret ?? '');
            $provided = (string) $request->header('X-Hub-Signature-256');
            abort_unless($secret !== '' && str_starts_with($provided, 'sha256='), 401, 'Webhook imzası eksik.');
            abort_unless(hash_equals('sha256=' . hash_hmac('sha256', $raw, $secret), $provided), 401, 'Webhook imzası geçersiz.');
            $timestamp = (int) data_get($payload, 'entry.0.messaging.0.timestamp', data_get($payload, 'entry.0.time', $payload['timestamp'] ?? 0));
            if ($timestamp > 10_000_000_000) $timestamp = (int) floor($timestamp / 1000);
        } else {
            $secret = (string) ($connection->webhook_secret ?? '');
            $timestamp = (int) $request->header('X-Zolm-Timestamp');
            $provided = (string) $request->header('X-Zolm-Signature');
            abort_unless($secret !== '' && $timestamp > 0 && $provided !== '', 401, 'Webhook imzası veya zaman damgası eksik.');
            abort_unless(hash_equals(hash_hmac('sha256', $timestamp . '.' . $raw, $secret), $provided), 401, 'Webhook imzası geçersiz.');
        }
        abort_if(abs(now()->timestamp - $timestamp) > 600, 409, 'Webhook replay penceresi aşıldı.');
        return $timestamp;
    }

    private function normalize(string $provider, array $payload, int $timestamp): array
    {
        if (in_array($provider, ['meta', 'instagram', 'facebook'], true)) {
            if (isset($payload['event_id'], $payload['thread_id'], $payload['sender_id'])) {
                return [(string) $payload['event_id'], $payload, $provider === 'meta' ? 'instagram' : $provider];
            }
            $messaging = (array) data_get($payload, 'entry.0.messaging.0', []);
            $change = (array) data_get($payload, 'entry.0.changes.0.value', []);
            $object = (string) ($payload['object'] ?? 'instagram');
            $key = $provider === 'meta' ? ($object === 'page' ? 'facebook' : 'instagram') : $provider;
            if ($messaging) {
                $id = (string) data_get($messaging, 'message.mid', '');
                return [$id, [
                    'event_id' => $id,
                    'thread_id' => (string) data_get($messaging, 'sender.id', ''),
                    'thread_type' => 'dm',
                    'sender_id' => (string) data_get($messaging, 'sender.id', ''),
                    'body' => mb_substr(strip_tags((string) data_get($messaging, 'message.text', '')), 0, 5000),
                    'timestamp' => $timestamp,
                ], $key];
            }
            $id = (string) ($change['comment_id'] ?? $change['id'] ?? '');
            return [$id, [
                'event_id' => $id,
                'thread_id' => (string) ($change['post_id'] ?? $change['parent_id'] ?? $id),
                'thread_type' => 'comment',
                'sender_id' => (string) data_get($change, 'from.id', ''),
                'body' => mb_substr(strip_tags((string) ($change['message'] ?? '')), 0, 5000),
                'timestamp' => $timestamp,
            ], $key];
        }

        if ($provider === 'google_business') {
            $messageId = (string) data_get($payload, 'message.messageId', $payload['event_id'] ?? '');
            $decoded = data_get($payload, 'message.data');
            $review = is_string($decoded) ? json_decode((string) base64_decode($decoded, true), true) : $payload;
            abort_unless(is_array($review), 422, 'Google webhook verisi geçersiz.');
            $ratingRaw = $review['rating'] ?? $review['starRating'] ?? 5;
            $rating = is_numeric($ratingRaw) ? (int) $ratingRaw : (['ONE' => 1, 'TWO' => 2, 'THREE' => 3, 'FOUR' => 4, 'FIVE' => 5][$ratingRaw] ?? 5);
            $reviewId = basename((string) ($review['review_id'] ?? $review['reviewId'] ?? $review['name'] ?? ''));
            return [$messageId ?: $reviewId, [
                'review_id' => $reviewId,
                'rating' => max(1, min(5, $rating)),
                'reviewer_name' => mb_substr(strip_tags((string) data_get($review, 'reviewer.displayName', $review['reviewer_name'] ?? 'Google Kullanıcısı')), 0, 120),
                'comment' => mb_substr(strip_tags((string) ($review['comment'] ?? '')), 0, 5000),
                'location_id' => mb_substr((string) ($review['location_id'] ?? $review['locationId'] ?? ''), 0, 120),
            ], 'google_business'];
        }

        $eventId = (string) ($payload['event_id'] ?? $payload['idempotency_key'] ?? '');
        return [$eventId, $payload, $provider];
    }

    private function connection(MarketplaceStore $store, string $provider): IntegrationConnection
    {
        $aliases = in_array($provider, ['meta', 'instagram', 'facebook'], true) ? ['meta', 'meta_social', 'instagram', 'facebook'] : [$provider];
        return IntegrationConnection::where('store_id', $store->id)->whereIn('provider', $aliases)->where('status', 'active')->firstOrFail();
    }

    private function channel(MarketplaceStore $store, string $key): SupportChannel
    {
        return SupportChannel::where('store_id', $store->id)->where('key', $key)->where('status', 'active')->where('is_enabled', true)->firstOrFail();
    }

    private function enforceFeatureEnabled(string $provider): void
    {
        abort_unless(config('customer-care.enabled', false), 404);

        $providerEnabled = match ($provider) {
            'meta', 'instagram', 'facebook' => config('customer-care.meta_social_enabled', false),
            'google_business' => config('customer-care.google_reviews_enabled', false),
            'crm', 'erp' => config('customer-care.integration_hub_enabled', false),
            default => false,
        };
        abort_unless($providerEnabled, 404);
    }
}
