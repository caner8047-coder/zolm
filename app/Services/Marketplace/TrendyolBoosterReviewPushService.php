<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceStore;
use App\Models\TrendyolBoosterReview;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TrendyolBoosterReviewPushService
{
    private const MAX_RETRIES = 3;

    private const BATCH_SIZE = 50;

    /**
     * Onaylı yorumları WooCommerce'e batch olarak gönderir.
     * WP REST API: POST /wp-json/zolm-booster/v1/reviews/batch
     */
    public function pushBatch(Collection $reviews): array
    {
        if ($reviews->isEmpty()) {
            return ['pushed' => 0, 'failed' => 0, 'errors' => []];
        }

        $userId = $reviews->first()->user_id;
        $connection = $this->getWooCommerceConnection($userId);

        if (! $connection) {
            $this->markAllFailed($reviews, 'WooCommerce bağlantısı bulunamadı.');

            return ['pushed' => 0, 'failed' => $reviews->count(), 'errors' => ['no_connection']];
        }
        if ($connection['zolm_booster_api_key'] === '') {
            $this->markAllFailed($reviews, 'WooCommerce ZOLM Booster API anahtarı tanımlı değil.');

            return ['pushed' => 0, 'failed' => $reviews->count(), 'errors' => ['missing_api_key']];
        }

        $apiUrl = rtrim($connection['base_url'], '/').'/wp-json/zolm-booster/v1/reviews/batch';
        $apiKey = $connection['zolm_booster_api_key'];

        $pushed = 0;
        $failed = 0;
        $errors = [];

        // Batch'ler halinde gönder
        foreach ($reviews->chunk(self::BATCH_SIZE) as $batch) {
            $payload = $this->buildBatchPayload($batch);

            $result = $this->sendWithRetry($apiUrl, $apiKey, $payload);

            if ($result['success']) {
                $pushed += $batch->count();
                foreach ($batch as $review) {
                    $review->update([
                        'wc_push_status' => 'pushed',
                        'wc_pushed_at' => now(),
                        'wc_push_error' => null,
                    ]);
                }
            } else {
                $failed += $batch->count();
                $errors[] = $result['error'];
                foreach ($batch as $review) {
                    $review->update([
                        'wc_push_status' => 'failed',
                        'wc_push_error' => $result['error'],
                    ]);
                }
                Log::error('WooCommerce review push batch failed', [
                    'error' => $result['error'],
                    'count' => $batch->count(),
                ]);
            }
        }

        return ['pushed' => $pushed, 'failed' => $failed, 'errors' => $errors];
    }

    /**
     * Tek bir yorumu WooCommerce'e gönderir.
     */
    public function pushSingle(TrendyolBoosterReview $review): array
    {
        if ($review->wc_push_status === 'pushed') {
            return ['success' => true, 'message' => 'already_pushed'];
        }

        $connection = $this->getWooCommerceConnection($review->user_id);
        if (! $connection) {
            return ['success' => false, 'error' => 'no_connection'];
        }
        if ($connection['zolm_booster_api_key'] === '') {
            return ['success' => false, 'error' => 'missing_api_key'];
        }

        $apiUrl = rtrim($connection['base_url'], '/').'/wp-json/zolm-booster/v1/reviews';
        $payload = $this->buildSinglePayload($review);

        $result = $this->sendWithRetry($apiUrl, $connection['zolm_booster_api_key'], $payload);

        if ($result['success']) {
            $review->update([
                'wc_push_status' => 'pushed',
                'wc_pushed_at' => now(),
                'wc_push_error' => null,
            ]);
        } else {
            $review->update([
                'wc_push_status' => 'failed',
                'wc_push_error' => $result['error'],
            ]);
        }

        return $result;
    }

    /**
     * Exponential backoff ile retry mantığı.
     */
    protected function sendWithRetry(string $url, string $apiKey, array $payload, int $attempt = 0): array
    {
        try {
            $response = Http::withHeaders([
                'X-ZOLM-API-Key' => $apiKey,
                'Accept' => 'application/json',
            ])->timeout(60)->post($url, $payload);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()];
            }

            $error = 'HTTP '.$response->status().': '.$response->body();
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }

        if ($attempt < self::MAX_RETRIES) {
            $backoff = min(5000 * pow(2, $attempt), 60000);
            usleep($backoff * 1000);

            return $this->sendWithRetry($url, $apiKey, $payload, $attempt + 1);
        }

        return ['success' => false, 'error' => $error];
    }

    /**
     * WooCommerce bağlantı bilgilerini getirir.
     */
    protected function getWooCommerceConnection(int $userId): ?array
    {
        $store = MarketplaceStore::where('user_id', $userId)
            ->where('marketplace', 'woocommerce')
            ->where('is_active', true)
            ->first();

        if (! $store || ! $store->connection) {
            return null;
        }

        $credentials = $store->connection->credentials_encrypted ?? [];
        $baseUrl = trim((string) ($store->connection->api_base_url ?? $credentials['store_url'] ?? ''));
        $wooApiKey = trim((string) ($credentials['api_key'] ?? $credentials['consumer_key'] ?? ''));
        $boosterApiKey = trim((string) ($credentials['zolm_booster_api_key'] ?? $credentials['booster_api_key'] ?? ''));

        if ($baseUrl === '') {
            return null;
        }

        return [
            'base_url' => $baseUrl,
            'api_key' => $wooApiKey,
            'api_secret' => trim((string) ($credentials['api_secret'] ?? $credentials['consumer_secret'] ?? '')),
            'zolm_booster_api_key' => $boosterApiKey !== ''
                ? $boosterApiKey
                : $this->legacyBoosterApiKey($wooApiKey),
            'wp_username' => trim((string) ($credentials['wp_username'] ?? $credentials['wordpress_username'] ?? '')),
            'wp_password' => trim((string) ($credentials['wp_password'] ?? $credentials['wordpress_password'] ?? $credentials['application_password'] ?? '')),
        ];
    }

    protected function legacyBoosterApiKey(string $wooApiKey): string
    {
        return str_starts_with($wooApiKey, 'ck_') ? '' : $wooApiKey;
    }

    protected function buildBatchPayload(Collection $reviews): array
    {
        return [
            'reviews' => $reviews->map(fn ($r) => $this->buildSinglePayload($r))->toArray(),
        ];
    }

    protected function buildSinglePayload(TrendyolBoosterReview $review): array
    {
        return [
            'zb_review_id' => $review->trendyol_review_id,
            'wc_product_id' => $review->wc_product_id,
            'wc_product_sku' => $review->wc_product_sku,
            'trendyol_product_id' => $review->trendyol_product_id,
            'trendyol_product_barcode' => $review->trendyol_product_barcode,
            'reviewer_name' => $review->reviewer_name_masked,
            'reviewer_avatar_url' => $review->reviewer_avatar_url,
            'rating' => $review->rating,
            'comment' => $review->comment,
            'comment_length' => $review->comment_length,
            'review_media' => $review->review_media,
            'helpful_count' => $review->helpful_count,
            'seller_name' => $review->seller_name,
            'reviewed_at' => $review->reviewed_at?->toIso8601String(),
            'is_featured' => $review->is_featured,
            'is_spam' => $review->is_spam,
            'spam_score' => $review->spam_score,
            'spam_flags' => $review->spam_flags,
            'status' => $review->status,
        ];
    }

    protected function markAllFailed(Collection $reviews, string $error): void
    {
        foreach ($reviews as $review) {
            $review->update([
                'wc_push_status' => 'failed',
                'wc_push_error' => $error,
            ]);
        }
    }
}
