<?php

namespace App\Services\WhatsApp;

use App\Models\WaWebhookEndpoint;
use App\Models\WaWebhookLog;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

/**
 * Webhook alma ve gönderme servisi.
 * Gelen webhook'ları işler, giden webhook'ları retry mekanizmasıyla gönderir.
 */
class WebhookReceiverService
{
    private WebhookRetryService $retryService;

    public function __construct(WebhookRetryService $retryService)
    {
        $this->retryService = $retryService;
    }

    /**
     * Gelen webhook'u işle
     */
    public function receive(WaWebhookEndpoint $endpoint, array $payload, string $rawBody, ?string $signature = null): array
    {
        $startTime = microtime(true);

        // İmza doğrulama
        if ($endpoint->secret_encrypted && $signature) {
            $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $endpoint->secret_encrypted);
            if (!hash_equals($expected, $signature)) {
                $this->logEvent($endpoint, 'inbound', 'signature_failed', null, 'İmza doğrulaması başarısız');
                return ['success' => false, 'error' => 'Invalid signature'];
            }
        }

        $eventType = $payload['event_type'] ?? $payload['type'] ?? 'unknown';
        $payloadHash = hash('sha256', $rawBody);

        $log = WaWebhookLog::create([
            'endpoint_id' => $endpoint->id,
            'provider' => $endpoint->provider,
            'event_type' => $eventType,
            'direction' => 'inbound',
            'status' => 'received',
            'payload_hash' => ['hash' => $payloadHash, 'size' => strlen($rawBody)],
        ]);

        try {
            $result = $this->processEvent($endpoint, $eventType, $payload);

            $processingTime = round((microtime(true) - $startTime) * 1000, 2);

            $log->update([
                'status' => 'processed',
                'processing_time_ms' => $processingTime,
            ]);

            $endpoint->update(['last_received_at' => now()]);

            return ['success' => true, 'result' => $result, 'log_id' => $log->id];
        } catch (\Throwable $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => mb_substr($e->getMessage(), 0, 500),
            ]);

            Log::error('Webhook işleme hatası', [
                'endpoint_id' => $endpoint->id,
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage(), 'log_id' => $log->id];
        }
    }

    /**
     * Outbound webhook gönder - retry mekanizması ile
     */
    public function sendOutbound(WaWebhookEndpoint $endpoint, string $eventType, array $payload): array
    {
        $secret = $endpoint->secret_encrypted ?? '';
        $body = json_encode($payload);
        $timestamp = (string) time();
        $signature = 'sha256=' . hash_hmac('sha256', $body, $secret);
        $requestId = (string) Str::uuid();

        $log = WaWebhookLog::create([
            'endpoint_id' => $endpoint->id,
            'provider' => $endpoint->provider,
            'event_type' => $eventType,
            'direction' => 'outbound',
            'status' => 'sending',
            'request_id' => $requestId,
            'payload_hash' => ['payload' => $payload],
        ]);

        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-Webhook-Event-ID' => $requestId,
                'X-Webhook-Timestamp' => $timestamp,
                'X-Webhook-Signature' => $signature,
            ])->timeout(15)->post($endpoint->url, $payload);

            if ($response->successful()) {
                $log->update(['status' => 'sent']);
                return ['success' => true];
            }

            $log->update([
                'status' => 'failed',
                'error_message' => 'HTTP ' . $response->status(),
                'next_retry_at' => $this->retryService->calculateNextRetry(0),
            ]);

            return ['success' => false, 'error' => 'HTTP ' . $response->status()];
        } catch (\Throwable $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => mb_substr($e->getMessage(), 0, 500),
                'next_retry_at' => $this->retryService->calculateNextRetry(0),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function processEvent(WaWebhookEndpoint $endpoint, string $eventType, array $payload): array
    {
        // Event tipine göre işleme mantığı
        return ['processed' => true, 'event_type' => $eventType];
    }

    private function logEvent(WaWebhookEndpoint $endpoint, string $direction, string $status, ?array $payload, ?string $error): void
    {
        WaWebhookLog::create([
            'endpoint_id' => $endpoint->id,
            'provider' => $endpoint->provider,
            'event_type' => 'verification',
            'direction' => $direction,
            'status' => $status,
            'error_message' => $error,
        ]);
    }
}
