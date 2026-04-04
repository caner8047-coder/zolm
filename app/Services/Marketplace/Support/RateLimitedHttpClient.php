<?php

namespace App\Services\Marketplace\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Store ve provider bazlı rate limit koruması sağlayan HTTP client wrapper.
 *
 * Özellikler:
 * - Store bazlı istek sınırlama (aynı mağaza için eş zamanlı yoğun istek önleme)
 * - Provider bazlı global sınırlama (aynı pazaryerine yapılan toplam istek yönetimi)
 * - 429 durumunda exponential backoff (1s → 2s → 4s → 8s → max 60s)
 * - Jitter ekleme (0-2s arası rastgele gecikme, thundering herd önleme)
 * - Retry cap: max 5 deneme
 * - Retry-After header desteği
 */
class RateLimitedHttpClient
{
    protected int $maxRetries = 5;

    protected int $maxBackoffSeconds = 60;

    protected int $baseBackoffMs = 1000;

    protected int $jitterMaxMs = 2000;

    /**
     * Rate limit korumalı GET isteği.
     */
    public function get(
        PendingRequest $request,
        string $url,
        array $query = [],
        string $storeKey = 'default',
        string $provider = 'default',
    ): Response {
        return $this->executeWithRetry(
            fn () => $request->get($url, $query),
            $storeKey,
            $provider,
            "GET {$url}",
        );
    }

    /**
     * Rate limit korumalı POST isteği.
     */
    public function post(
        PendingRequest $request,
        string $url,
        array $data = [],
        string $storeKey = 'default',
        string $provider = 'default',
    ): Response {
        return $this->executeWithRetry(
            fn () => $request->post($url, $data),
            $storeKey,
            $provider,
            "POST {$url}",
        );
    }

    /**
     * Rate limit korumalı PUT isteği.
     */
    public function put(
        PendingRequest $request,
        string $url,
        array $data = [],
        string $storeKey = 'default',
        string $provider = 'default',
    ): Response {
        return $this->executeWithRetry(
            fn () => $request->put($url, $data),
            $storeKey,
            $provider,
            "PUT {$url}",
        );
    }

    /**
     * Retry mantığı ile isteği çalıştır.
     */
    protected function executeWithRetry(
        callable $callable,
        string $storeKey,
        string $provider,
        string $label,
    ): Response {
        $attempt = 0;

        while (true) {
            $this->waitIfThrottled($storeKey, $provider);

            /** @var Response $response */
            $response = $callable();

            if ($response->status() !== 429 && $response->status() < 500) {
                $this->recordSuccess($storeKey, $provider);

                return $response;
            }

            $attempt++;

            if ($attempt >= $this->maxRetries) {
                Log::warning('[RateLimitedHttpClient] Maksimum deneme sayısına ulaşıldı.', [
                    'store_key' => $storeKey,
                    'provider' => $provider,
                    'label' => $label,
                    'attempts' => $attempt,
                    'status' => $response->status(),
                ]);

                $this->recordRateLimitHit($storeKey, $provider);

                return $response;
            }

            $waitMs = $this->calculateBackoff($response, $attempt);

            Log::info('[RateLimitedHttpClient] Rate limit veya server hatası, bekleniyor.', [
                'store_key' => $storeKey,
                'provider' => $provider,
                'label' => $label,
                'attempt' => $attempt,
                'status' => $response->status(),
                'wait_ms' => $waitMs,
            ]);

            $this->recordRateLimitHit($storeKey, $provider);
            usleep($waitMs * 1000);
        }
    }

    /**
     * Daha önce rate limit yenmişse kısa bekleme.
     */
    protected function waitIfThrottled(string $storeKey, string $provider): void
    {
        $throttleUntil = Cache::get("marketplace_throttle:{$provider}:{$storeKey}");

        if ($throttleUntil && now()->timestamp < (int) $throttleUntil) {
            $waitSeconds = max(1, (int) $throttleUntil - now()->timestamp);
            $waitSeconds = min($waitSeconds, $this->maxBackoffSeconds);

            Log::debug('[RateLimitedHttpClient] Throttle bekleniyor.', [
                'store_key' => $storeKey,
                'provider' => $provider,
                'wait_seconds' => $waitSeconds,
            ]);

            sleep($waitSeconds);
        }
    }

    /**
     * Exponential backoff + jitter hesapla.
     */
    protected function calculateBackoff(Response $response, int $attempt): int
    {
        // Retry-After header desteği
        $retryAfter = $response->header('Retry-After');

        if ($retryAfter !== null && $retryAfter !== '') {
            $retryAfterSeconds = is_numeric($retryAfter)
                ? (int) $retryAfter
                : max(1, strtotime($retryAfter) - time());

            return min($retryAfterSeconds * 1000, $this->maxBackoffSeconds * 1000);
        }

        // Exponential backoff: 1s → 2s → 4s → 8s → 16s → max 60s
        $backoffMs = $this->baseBackoffMs * (2 ** ($attempt - 1));
        $backoffMs = min($backoffMs, $this->maxBackoffSeconds * 1000);

        // Jitter ekleme (0 - 2s arası rastgele)
        $jitterMs = random_int(0, $this->jitterMaxMs);

        return $backoffMs + $jitterMs;
    }

    /**
     * Rate limit isabetini kaydet ve throttle süresi oluştur.
     */
    protected function recordRateLimitHit(string $storeKey, string $provider): void
    {
        $cacheKey = "marketplace_throttle:{$provider}:{$storeKey}";

        // Mevcut hit sayısını al
        $hitCountKey = "marketplace_rate_hits:{$provider}:{$storeKey}";
        $hitCount = (int) Cache::get($hitCountKey, 0) + 1;
        Cache::put($hitCountKey, $hitCount, now()->addMinutes(10));

        // Hit sayısına göre throttle süresi artır
        $throttleSeconds = min(5 * $hitCount, $this->maxBackoffSeconds);
        Cache::put($cacheKey, now()->addSeconds($throttleSeconds)->timestamp, now()->addSeconds($throttleSeconds + 10));
    }

    /**
     * Başarılı istekten sonra rate limit sayacını sıfırla.
     */
    protected function recordSuccess(string $storeKey, string $provider): void
    {
        $hitCountKey = "marketplace_rate_hits:{$provider}:{$storeKey}";

        Cache::forget($hitCountKey);
    }

    /**
     * Bir store/provider ikilisi şu an throttle durumunda mı?
     */
    public function isThrottled(string $storeKey, string $provider): bool
    {
        $throttleUntil = Cache::get("marketplace_throttle:{$provider}:{$storeKey}");

        return $throttleUntil && now()->timestamp < (int) $throttleUntil;
    }

    /**
     * Belirli bir store/provider ikilisi için rate limit hit sayısını döndür.
     */
    public function hitCount(string $storeKey, string $provider): int
    {
        return (int) Cache::get("marketplace_rate_hits:{$provider}:{$storeKey}", 0);
    }
}
