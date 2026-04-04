<?php

namespace App\Services\Marketplace\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Store + syncType bazlı devre kesici (Circuit Breaker).
 *
 * Durumlar:
 * - closed: Normal çalışma, istekler geçer
 * - open: Devre açık, istekler bloklanır (belirli süre sonra half_open'a geçer)
 * - half_open: Tek deneme yapılır, başarılı ise closed'a döner
 *
 * Varsayılan eşikler:
 * - 5 ardışık hata → open durumu
 * - 5 dakika bekledikten sonra → half_open
 * - half_open'da başarılı → closed
 * - half_open'da başarısız → tekrar open (süre sıfırlanır)
 */
class CircuitBreaker
{
    protected int $failureThreshold;

    protected int $recoveryTimeSeconds;

    public function __construct(?int $failureThreshold = null, ?int $recoveryTimeSeconds = null)
    {
        $this->failureThreshold = $failureThreshold ?? (int) config('marketplace.circuit_breaker.failure_threshold', 5);
        $this->recoveryTimeSeconds = $recoveryTimeSeconds ?? (int) config('marketplace.circuit_breaker.recovery_time_seconds', 300);
    }

    /**
     * Belirli bir store+syncType ikilisi için devrenin durumunu döndür.
     *
     * @return string 'closed'|'open'|'half_open'
     */
    public function state(int $storeId, string $syncType): string
    {
        $key = $this->cacheKey($storeId, $syncType);
        $data = Cache::get($key);

        if ($data === null) {
            return 'closed';
        }

        if ($data['state'] === 'open') {
            $openedAt = (int) ($data['opened_at'] ?? 0);
            $elapsed = now()->timestamp - $openedAt;

            if ($elapsed >= $this->recoveryTimeSeconds) {
                return 'half_open';
            }

            return 'open';
        }

        return (string) ($data['state'] ?? 'closed');
    }

    /**
     * İstek yapmaya izin var mı?
     */
    public function isAllowed(int $storeId, string $syncType): bool
    {
        $state = $this->state($storeId, $syncType);

        return $state !== 'open';
    }

    /**
     * Başarılı istek kaydı — devreyi closed'a döndür.
     */
    public function recordSuccess(int $storeId, string $syncType): void
    {
        $key = $this->cacheKey($storeId, $syncType);
        $state = $this->state($storeId, $syncType);

        if ($state === 'half_open') {
            Log::info('[CircuitBreaker] Half-open başarılı, devre kapatıldı.', [
                'store_id' => $storeId,
                'sync_type' => $syncType,
            ]);
        }

        // Başarılıysa her durumda sıfırla
        Cache::forget($key);
    }

    /**
     * Başarısız istek kaydı — hata sayısını artır, eşik aşılırsa devreyi aç.
     */
    public function recordFailure(int $storeId, string $syncType, ?string $errorMessage = null): void
    {
        $key = $this->cacheKey($storeId, $syncType);
        $data = Cache::get($key, [
            'state' => 'closed',
            'failure_count' => 0,
            'opened_at' => null,
            'last_error' => null,
        ]);

        $currentState = $this->state($storeId, $syncType);

        // half_open durumunda hata → tekrar open
        if ($currentState === 'half_open') {
            $data['state'] = 'open';
            $data['opened_at'] = now()->timestamp;
            $data['last_error'] = $errorMessage;

            Cache::put($key, $data, now()->addMinutes(30));

            Log::warning('[CircuitBreaker] Half-open başarısız, devre tekrar açıldı.', [
                'store_id' => $storeId,
                'sync_type' => $syncType,
                'error' => $errorMessage,
            ]);

            return;
        }

        $data['failure_count'] = (int) ($data['failure_count'] ?? 0) + 1;
        $data['last_error'] = $errorMessage;

        if ($data['failure_count'] >= $this->failureThreshold) {
            $data['state'] = 'open';
            $data['opened_at'] = now()->timestamp;

            Log::error('[CircuitBreaker] Hata eşiği aşıldı, devre açıldı.', [
                'store_id' => $storeId,
                'sync_type' => $syncType,
                'failure_count' => $data['failure_count'],
                'error' => $errorMessage,
            ]);
        }

        Cache::put($key, $data, now()->addMinutes(30));
    }

    /**
     * Manuel olarak devreyi sıfırla (admin aksiyonu).
     */
    public function reset(int $storeId, string $syncType): void
    {
        Cache::forget($this->cacheKey($storeId, $syncType));

        Log::info('[CircuitBreaker] Devre manuel olarak sıfırlandı.', [
            'store_id' => $storeId,
            'sync_type' => $syncType,
        ]);
    }

    /**
     * Tüm bilgiyi döndür (diagnostics/UI için).
     *
     * @return array{state: string, failure_count: int, opened_at: int|null, last_error: string|null, seconds_until_half_open: int|null}
     */
    public function inspect(int $storeId, string $syncType): array
    {
        $key = $this->cacheKey($storeId, $syncType);
        $data = Cache::get($key, [
            'state' => 'closed',
            'failure_count' => 0,
            'opened_at' => null,
            'last_error' => null,
        ]);

        $state = $this->state($storeId, $syncType);
        $secondsUntilHalfOpen = null;

        if ($state === 'open' && $data['opened_at']) {
            $elapsed = now()->timestamp - (int) $data['opened_at'];
            $secondsUntilHalfOpen = max(0, $this->recoveryTimeSeconds - $elapsed);
        }

        return [
            'state' => $state,
            'failure_count' => (int) ($data['failure_count'] ?? 0),
            'opened_at' => $data['opened_at'] ?? null,
            'last_error' => $data['last_error'] ?? null,
            'seconds_until_half_open' => $secondsUntilHalfOpen,
        ];
    }

    protected function cacheKey(int $storeId, string $syncType): string
    {
        return "marketplace_circuit_breaker:{$storeId}:{$syncType}";
    }
}
