<?php

namespace App\Services\WhatsApp;

use App\Models\WaWebhookLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Başarısız webhook'lar için retry mekanizması.
 * Exponential backoff ile yeniden deneme.
 */
class WebhookRetryService
{
    private const MAX_RETRIES = 3;
    private const BASE_DELAY_SECONDS = 30;

    /**
     * Başarısız webhook'ları bul ve tekrar dene
     */
    public function retryFailedWebhooks(): array
    {
        $failedLogs = WaWebhookLog::where('status', 'failed')
            ->where('retry_count', '<', self::MAX_RETRIES)
            ->where(function ($query) {
                $query->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            })
            ->with('endpoint')
            ->limit(50)
            ->get();

        $results = [
            'total' => $failedLogs->count(),
            'retried' => 0,
            'succeeded' => 0,
            'failed_again' => 0,
        ];

        foreach ($failedLogs as $log) {
            $result = $this->retrySingle($log);
            $results['retried']++;

            if ($result) {
                $results['succeeded']++;
            } else {
                $results['failed_again']++;
            }
        }

        return $results;
    }

    /**
     * Tek bir webhook'u yeniden dene
     */
    public function retrySingle(WaWebhookLog $log): bool
    {
        $endpoint = $log->endpoint;
        if (!$endpoint) {
            return false;
        }

        // Retry sayısını artır
        $log->update([
            'retry_count' => ($log->retry_count ?? 0) + 1,
            'next_retry_at' => $this->calculateNextRetry($log->retry_count ?? 0),
            'status' => 'retrying',
        ]);

        try {
            $body = json_encode($log->payload_hash['payload'] ?? []);
            $secret = $endpoint->secret_encrypted ?? '';
            $timestamp = (string) time();
            $signature = 'sha256=' . hash_hmac('sha256', $body, $secret);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-Webhook-Event-ID' => $log->request_id ?: (string) Str::uuid(),
                'X-Webhook-Timestamp' => $timestamp,
                'X-Webhook-Signature' => $signature,
                'X-Webhook-Retry' => (string) ($log->retry_count ?? 0),
            ])->timeout(15)->post($endpoint->url, json_decode($body, true));

            if ($response->successful()) {
                $log->update([
                    'status' => 'sent',
                    'next_retry_at' => null,
                ]);
                return true;
            }

            $log->update([
                'status' => 'failed',
                'error_message' => 'HTTP ' . $response->status() . ' (retry #' . ($log->retry_count ?? 0) . ')',
            ]);
            return false;
        } catch (\Throwable $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage() . ' (retry #' . ($log->retry_count ?? 0) . ')',
            ]);
            return false;
        }
    }

    /**
     * Exponential backoff hesapla
     */
    public function calculateNextRetry(int $retryCount): \Carbon\Carbon
    {
        $delay = self::BASE_DELAY_SECONDS * pow(2, $retryCount);
        $maxDelay = 3600; // Maksimum 1 saat
        $delay = min($delay, $maxDelay);

        return now()->addSeconds($delay);
    }

    /**
     * Retry istatistiklerini getir
     */
    public function getRetryStats(): array
    {
        return [
            'pending_retry' => WaWebhookLog::where('status', 'failed')
                ->where('retry_count', '<', self::MAX_RETRIES)
                ->count(),
            'max_retries_reached' => WaWebhookLog::where('status', 'failed')
                ->where('retry_count', '>=', self::MAX_RETRIES)
                ->count(),
            'total_retries_today' => WaWebhookLog::where('status', 'retrying')
                ->whereDate('updated_at', today())
                ->count(),
        ];
    }
}
