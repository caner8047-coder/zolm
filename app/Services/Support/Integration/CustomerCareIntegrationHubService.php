<?php

namespace App\Services\Support\Integration;

use App\Models\MarketplaceStore;
use App\Models\SupportChannel;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\SupportIntegrationEvent;
use App\Models\SupportIntegrationDelivery;
use App\Services\Support\Security\PiiRedactor;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;

class CustomerCareIntegrationHubService
{
    protected PiiRedactor $redactor;

    public function __construct(PiiRedactor $redactor)
    {
        $this->redactor = $redactor;
    }

    /**
     * Integrasyon olayını tetikler ve teslimat kuyruğuna ekler.
     */
    public function dispatchEvent(int $storeId, string $eventType, array $data, ?string $idempotencyKey = null): ?SupportIntegrationEvent
    {
        // Hub check: Config check
        if (!config('customer-care.enabled', false)
            || !config('customer-care.integration_hub_enabled', false)) {
            return null;
        }

        $idempotencyKey = $idempotencyKey ?? 'idemp_' . Str::random(20);

        // Idempotency check: duplicate event check
        $existing = SupportIntegrationEvent::where('store_id', $storeId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
        if ($existing) {
            return $existing;
        }

        // Redact data to prevent PII leakage
        $redactedData = $this->redactPayload($data);

        $eventId = (string) Str::uuid();

        // 1. Create Event Record
        $event = SupportIntegrationEvent::create([
            'store_id' => $storeId,
            'event_id' => $eventId,
            'event_type' => $eventType,
            'payload_json' => [
                'event_id' => $eventId,
                'store_id' => $storeId,
                'occurred_at' => now()->toIso8601String(),
                'schema_version' => '1.0',
                'data' => $redactedData,
                'idempotency_key' => $idempotencyKey,
            ],
            'idempotency_key' => $idempotencyKey,
        ]);

        // Find webhook channels
        $channels = SupportChannel::where('store_id', $storeId)
            ->where('key', 'webhook_outbound')
            ->where('is_enabled', true)
            ->get();

        foreach ($channels as $channel) {
            $creds = $channel->config_json;
            $url = $creds['webhook_url'] ?? null;
            if ($url) {
                // 2. Create Delivery Item
                $delivery = SupportIntegrationDelivery::create([
                    'support_integration_event_id' => $event->id,
                    'webhook_url' => $url,
                    'status' => 'pending',
                    'attempts' => 0,
                ]);

                // P0-1: Decrypt webhook secret before passing to deliver
                $rawSecret = $creds['webhook_secret'] ?? '';
                if (empty($rawSecret)) {
                    $delivery->update([
                        'status' => 'failed',
                        'last_error' => 'Webhook imzalama anahtarı (secret) eksik veya boş. Gönderim iptal edildi.',
                    ]);
                    continue;
                }

                try {
                    $secret = \Illuminate\Support\Facades\Crypt::decryptString($rawSecret);
                } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                    $delivery->update([
                        'status' => 'failed',
                        'last_error' => 'Şifrelenmiş webhook anahtarı çözülemedi (Invalid/Plaintext Secret). Gönderim iptal edildi.',
                    ]);
                    continue;
                }

                // Gerçek HTTP çağrısı zamanlanmış outbox işleyicisine bırakılır.
            }
        }

        return $event;
    }

    /**
     * Teslimatı gerçekleştirir, imza ekler ve durumunu günceller.
     */
    public function queueConnectorOperation(int $storeId, string $provider, string $path, array $data, string $idempotencyKey): SupportIntegrationEvent
    {
        if (!config('customer-care.enabled', false) || !config('customer-care.integration_hub_enabled', false)) {
            throw new \RuntimeException('Müşteri iletişim merkezi veya entegrasyon hub devre dışı.');
        }
        $connection = app(CustomerCareHttpConnector::class)->connection($storeId, $provider);
        if (!str_starts_with($path, '/') || str_contains($path, '..')) {
            throw new \InvalidArgumentException('Entegrasyon endpoint yolu geçersiz.');
        }
        $existing = SupportIntegrationEvent::where('store_id', $storeId)->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) return $existing;

        $eventId = (string) Str::uuid();
        $event = SupportIntegrationEvent::create([
            'store_id' => $storeId,
            'event_id' => $eventId,
            'event_type' => $provider . '.outbound',
            'payload_json' => [
                'event_id' => $eventId,
                'store_id' => $storeId,
                'occurred_at' => now()->toIso8601String(),
                'schema_version' => '1.0',
                'data' => $this->redactPayload($data),
                'idempotency_key' => $idempotencyKey,
            ],
            'idempotency_key' => $idempotencyKey,
        ]);
        SupportIntegrationDelivery::create([
            'support_integration_event_id' => $event->id,
            'integration_connection_id' => $connection->id,
            'webhook_url' => rtrim((string) $connection->api_base_url, '/') . $path,
            'operation_path' => $path,
            'status' => 'pending',
            'attempts' => 0,
        ]);
        return $event;
    }

    public function recordInboundEvent(int $storeId, string $provider, string $externalEventId, array $data): SupportIntegrationEvent
    {
        if (!config('customer-care.enabled', false) || !config('customer-care.integration_hub_enabled', false)) {
            throw new \RuntimeException('Müşteri iletişim merkezi veya entegrasyon hub devre dışı.');
        }
        $idempotencyKey = 'inbound-' . $provider . '-' . hash('sha256', $storeId . ':' . $externalEventId);
        $existing = SupportIntegrationEvent::where('store_id', $storeId)->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) return $existing;
        $eventId = (string) Str::uuid();
        return SupportIntegrationEvent::create([
            'store_id' => $storeId,
            'event_id' => $eventId,
            'event_type' => $provider . '.inbound',
            'payload_json' => [
                'event_id' => $eventId,
                'external_event_id_hash' => hash('sha256', $externalEventId),
                'store_id' => $storeId,
                'occurred_at' => now()->toIso8601String(),
                'schema_version' => '1.0',
                'data' => $this->redactPayload($data),
            ],
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    public function processPending(int $limit = 100): array
    {
        if (!config('customer-care.enabled', false) || !config('customer-care.integration_hub_enabled', false)) {
            return ['processed' => 0, 'succeeded' => 0, 'failed' => 0];
        }
        $processed = $succeeded = $failed = 0;
        $deliveries = SupportIntegrationDelivery::whereIn('status', ['pending', 'failed'])
            ->where('attempts', '<', 3)->orderBy('id')->limit(max(1, min(500, $limit)))->get();
        foreach ($deliveries as $delivery) {
            $processed++;
            $this->deliver($delivery) ? $succeeded++ : $failed++;
        }
        return compact('processed', 'succeeded', 'failed');
    }

    public function deliver(SupportIntegrationDelivery $delivery, ?string $secret = null): bool
    {
        if (!config('customer-care.enabled', false) || !config('customer-care.integration_hub_enabled', false)) {
            $delivery->update([
                'status' => 'failed',
                'last_error' => 'Gönderim engellendi: müşteri iletişim merkezi veya entegrasyon hub devre dışı.',
            ]);
            return false;
        }

        $event = $delivery->event;
        if (!$event) {
            $delivery->update([
                'status' => 'failed',
                'last_error' => 'İlişkili event bulunamadı.',
            ]);
            return false;
        }

        if ($delivery->integration_connection_id) {
            try {
                $connection = $delivery->connection;
                if (!$connection || $connection->status !== 'active') throw new \RuntimeException('Entegrasyon bağlantısı aktif değil.');
                $delivery->increment('attempts');
                $delivery->update(['last_attempt_at' => now()]);
                app(CustomerCareHttpConnector::class)->post(
                    $connection,
                    (string) $delivery->operation_path,
                    (array) $event->payload_json,
                    (string) $event->idempotency_key,
                );
                $delivery->update(['status' => 'success', 'last_error' => null]);
                return true;
            } catch (\Throwable $e) {
                $this->handleFailure($delivery, $e->getMessage());
                return false;
            }
        }

        if ($secret === null) {
            $channel = SupportChannel::where('store_id', $event->store_id)->where('key', 'webhook_outbound')
                ->where('config_json->webhook_url', $delivery->webhook_url)->first();
            $encrypted = $channel?->config_json['webhook_secret'] ?? '';
            try { $secret = $encrypted !== '' ? Crypt::decryptString($encrypted) : null; } catch (\Throwable) { $secret = null; }
        }

        // P0-2: Fail-closed on empty/missing webhook secret
        if (empty($secret)) {
            $delivery->update([
                'status' => 'failed',
                'last_error' => 'Webhook imzalama anahtarı (secret) eksik veya boş. Gönderim iptal edildi.',
            ]);
            return false;
        }

        $body = json_encode($event->payload_json);
        $timestamp = time();

        // Calculate HMAC signature
        $signingPayload = $timestamp . '.' . $body;
        $signature = hash_hmac('sha256', $signingPayload, $secret);

        $delivery->increment('attempts');
        $delivery->update([
            'last_attempt_at' => now(),
        ]);

        try {
            $response = app(CustomerCareHttpConnector::class)->secureRequest((string) $delivery->webhook_url)
                ->withHeaders([
                'Content-Type' => 'application/json',
                'X-Zolm-Event-Id' => $event->event_id,
                'X-Zolm-Timestamp' => $timestamp,
                'X-Zolm-Signature' => $signature,
                'X-Zolm-Idempotency-Key' => $event->idempotency_key,
            ])->timeout(5)->post($delivery->webhook_url, $event->payload_json);

            if ($response->successful()) {
                $delivery->update([
                    'status' => 'success',
                    'last_error' => null,
                ]);
                return true;
            }

            $errorMsg = "HTTP Status: " . $response->status() . " | Body: " . mb_substr($response->body(), 0, 200);
            $this->handleFailure($delivery, $errorMsg);
            return false;

        } catch (\Throwable $e) {
            $this->handleFailure($delivery, $e->getMessage());
            return false;
        }
    }

    protected function handleFailure(SupportIntegrationDelivery $delivery, string $errorMessage)
    {
        $status = 'failed';
        if ($delivery->attempts >= 3) {
            $status = 'dead_letter';
            // Terminal log to alert operators - mask error message to prevent PII/secret leaks
            $maskedError = $this->redactor->maskPii($errorMessage);
            Log::alert("Webhook Delivery Terminal Failure (Dead Letter Queue):", [
                'delivery_id' => $delivery->id,
                'webhook_url' => $delivery->webhook_url,
                'error' => $maskedError
            ]);
        }

        $maskedError = $this->redactor->maskPii($errorMessage);
        $delivery->update([
            'status' => $status,
            'last_error' => mb_substr($maskedError, 0, 500),
        ]);
    }

    /**
     * Payload verilerindeki PII alanlarını maskeler.
     */
    protected function redactPayload(array $data): array
    {
        $redacted = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $redacted[$key] = $this->redactPayload($value);
            } elseif (is_string($value)) {
                // Confidential values must not be logged or leaked raw
                $redacted[$key] = $this->redactor->maskPii($value);
            } else {
                $redacted[$key] = $value;
            }
        }
        return $redacted;
    }
}
