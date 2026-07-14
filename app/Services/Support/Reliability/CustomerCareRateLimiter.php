<?php

namespace App\Services\Support\Reliability;

use App\Models\SupportDispatch;
use Illuminate\Support\Facades\Log;

class CustomerCareRateLimiter
{
    /**
     * Store ve channel bazlı gönderim limitini kontrol eder.
     */
    public function checkLimit(int $storeId, string $channelKey): bool
    {
        $enabled = config('customer-care.reliability_enabled', false);
        if (!$enabled) {
            return true;
        }

        // Kanonik kanal alanı support_channels.key'dir; tabloda channel_type yoktur.
        $channel = \App\Models\SupportChannel::where('store_id', $storeId)
            ->where('key', $channelKey)
            ->first();

        // Determine canonical key
        $canonicalKey = null;
        if ($channel) {
            $canonicalKey = $this->getCanonicalLimitKey($channel->key);
        } else {
            // Fallback check if $channelKey itself is a canonical key type
            $canonicalKey = $this->getCanonicalLimitKey($channelKey);
        }

        if (!$canonicalKey) {
            Log::warning("Rate Limit Check Blocked: Unknown channel key/type.", [
                'store_id' => $storeId,
                'channel_key' => $channelKey,
            ]);
            return false;
        }

        $limits = config('customer-care.rate_limits', [
            'whatsapp' => ['max_attempts' => 100, 'decay_seconds' => 3600],
            'trendyol' => ['max_attempts' => 50, 'decay_seconds' => 3600],
            'hepsiburada' => ['max_attempts' => 50, 'decay_seconds' => 3600],
            'n11' => ['max_attempts' => 50, 'decay_seconds' => 3600],
            'meta' => ['max_attempts' => 100, 'decay_seconds' => 3600],
            'google_reviews' => ['max_attempts' => 30, 'decay_seconds' => 3600],
            'web_chat' => ['max_attempts' => 200, 'decay_seconds' => 3600],
        ]);

        if (!isset($limits[$canonicalKey])) {
            Log::warning("Rate Limit Check Blocked: Explicit config required for {$canonicalKey}.", [
                'store_id' => $storeId,
                'canonical_key' => $canonicalKey,
            ]);
            return false;
        }

        $limit = $limits[$canonicalKey];
        $since = now()->subSeconds($limit['decay_seconds'])->toDateTimeString();

        // Count dispatches for channels matching the canonical key
        $sentCount = SupportDispatch::whereHas('conversation', function ($q) use ($storeId) {
                $q->where('store_id', $storeId);
            })
            ->whereHas('channel', function ($q) use ($canonicalKey) {
                $this->applyChannelKeyScope($q, $canonicalKey);
            })
            ->where('created_at', '>=', $since)
            ->count();

        if ($sentCount >= $limit['max_attempts']) {
            Log::warning("Rate Limit Exceeded: Gönderim engellendi.", [
                'store_id' => $storeId,
                'canonical_key' => $canonicalKey,
                'sent_count' => $sentCount,
                'limit' => $limit['max_attempts']
            ]);
            return false;
        }

        return true;
    }

    protected function getCanonicalLimitKey(?string $key): ?string
    {
        $key = strtolower($key ?? '');

        if (str_starts_with($key, 'whatsapp')) {
            return 'whatsapp';
        }
        if (str_starts_with($key, 'trendyol')) {
            return 'trendyol';
        }
        if (str_starts_with($key, 'hepsiburada')) {
            return 'hepsiburada';
        }
        if ($key === 'n11' || str_starts_with($key, 'n11_')) {
            return 'n11';
        }
        if (in_array($key, ['meta', 'meta_social', 'instagram', 'facebook'], true) || str_starts_with($key, 'meta_')) {
            return 'meta';
        }
        if (str_starts_with($key, 'google_reviews') || str_starts_with($key, 'google_business') || $key === 'google') {
            return 'google_reviews';
        }
        if (str_starts_with($key, 'web_chat') || $key === 'chat') {
            return 'web_chat';
        }

        return null;
    }

    /** @return string[] */
    protected function channelKeysForCanonical(string $canonicalKey): array
    {
        return match ($canonicalKey) {
            'meta' => ['meta', 'meta_social', 'instagram', 'facebook'],
            'google_reviews' => ['google', 'google_reviews', 'google_business'],
            'web_chat' => ['web_chat', 'chat'],
            default => [$canonicalKey],
        };
    }

    protected function applyChannelKeyScope($query, string $canonicalKey): void
    {
        $keys = $this->channelKeysForCanonical($canonicalKey);
        $query->where(function ($keyQuery) use ($keys): void {
            foreach ($keys as $index => $key) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $keyQuery->{$method}(function ($candidate) use ($key): void {
                    $candidate->where('key', $key)
                        ->orWhere('key', 'like', $key . '_%');
                });
            }
        });
    }
}
