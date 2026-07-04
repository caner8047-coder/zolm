<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Cache;

/**
 * WhatsApp mesaj gönderimi için rate limiting servisi.
 * Meta Cloud API 80 msg/saniye limitine uygun.
 */
class RateLimitService
{
    /**
     * Belirli bir telefon numarası için son gönderim zamanını kontrol et
     */
    public function canSendToNumber(string $phoneHash): bool
    {
        $key = "wa_rate_limit:number:{$phoneHash}";
        $lastSent = Cache::get($key);

        if ($lastSent === null) {
            return true;
        }

        // Aynı numaraya 1 saniyede en fazla 1 mesaj
        return (time() - $lastSent) >= 1;
    }

    /**
     * Gönderim sonrası zaman damgası kaydet
     */
    public function recordSent(string $phoneHash): void
    {
        $key = "wa_rate_limit:number:{$phoneHash}";
        Cache::put($key, time(), 60);
    }

    /**
     * hesap bazında rate limit kontrolü
     */
    public function canSendFromAccount(int $accountId): bool
    {
        $key = "wa_rate_limit:account:{$accountId}";
        $count = Cache::get($key, 0);

        // Meta 80/saniye, biz 50/saniye ile sınırlayalım (buffer)
        $limit = (int) config('whatsapp.rate_limit.per_account_per_second', 50);
        return $count < $limit;
    }

    /**
     * hesap bazında gönderim kaydı
     */
    public function recordAccountSent(int $accountId): void
    {
        $key = "wa_rate_limit:account:{$accountId}";
        $current = Cache::get($key, 0);

        Cache::put($key, $current + 1, 2);
    }

    /**
     * Store bazında toplam günlük limit kontrolü
     */
    public function canSendFromStoreToday(int $storeId): bool
    {
        $key = "wa_rate_limit:store:{$storeId}:" . date('Y-m-d');
        $count = Cache::get($key, 0);

        $limit = (int) config('whatsapp.rate_limit.per_store_per_day', 10000);
        return $count < $limit;
    }

    /**
     * Store günlük gönderim sayısını artır
     */
    public function recordStoreSent(int $storeId): void
    {
        $key = "wa_rate_limit:store:{$storeId}:" . date('Y-m-d');
        $current = Cache::get($key, 0);

        Cache::put($key, $current + 1, 86400);
    }

    /**
     * Toplu gönderim için batch rate limiti
     */
    public function getAvailableBatchSize(int $storeId): int
    {
        $remaining = $this->getRemainingDailyLimit($storeId);
        $maxBatch = (int) config('whatsapp.rate_limit.max_batch_size', 100);

        return min($remaining, $maxBatch);
    }

    /**
     * Kalan günlük limit
     */
    public function getRemainingDailyLimit(int $storeId): int
    {
        $key = "wa_rate_limit:store:{$storeId}:" . date('Y-m-d');
        $count = Cache::get($key, 0);
        $limit = (int) config('whatsapp.rate_limit.per_store_per_day', 10000);

        return max(0, $limit - $count);
    }
}
