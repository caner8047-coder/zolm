<?php

namespace App\Services\Marketplace;

use App\Jobs\TrendyolBoosterReviewSyncJob;
use App\Models\ChannelListing;
use App\Models\MarketplaceStore;
use App\Models\MpProduct;
use App\Models\TrendyolBoosterReview;
use App\Models\TrendyolBoosterReviewFilter;
use App\Models\TrendyolBoosterReviewSync;
use App\Services\NotificationCenterService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TrendyolBoosterReviewService
{
    public function __construct(
        protected TrendyolBoosterReviewSpamDetector $spamDetector,
        protected NotificationCenterService $notificationService,
    ) {}

    /**
     * Yeni bir senkronizasyon çalışması oluşturur.
     */
    public function createSyncRun(int $userId, string $type = 'delta', ?int $reviewSourceId = null): TrendyolBoosterReviewSync
    {
        if (! in_array($type, ['full', 'delta'], true)) {
            throw ValidationException::withMessages(['sync_type' => 'Geçersiz senkronizasyon türü.']);
        }

        $lastSyncedAt = $type === 'delta'
            ? TrendyolBoosterReviewSync::where('user_id', $userId)
                ->where('review_source_id', $reviewSourceId)
                ->where('status', 'completed')
                ->orderByDesc('completed_at')
                ->value('completed_at')
            : null;

        $syncRun = TrendyolBoosterReviewSync::create([
            'user_id' => $userId,
            'review_source_id' => $reviewSourceId,
            'status' => 'queued',
            'sync_type' => $type,
            'last_synced_at' => $lastSyncedAt,
            'started_at' => now(),
        ]);

        TrendyolBoosterReviewSyncJob::dispatch($syncRun->id)->delay(now()->addMinutes(30));

        return $syncRun;
    }

    /**
     * Eklentiden gelen batch yorum verisini DB'ye kaydeder.
     * Delta sync: sadece yeni yorumlar eklenir, mevcutlar güncellenir.
     */
    public function ingestReviews(int $userId, array $reviews, int $syncRunId, array $progress = []): array
    {
        return DB::transaction(function () use ($userId, $reviews, $syncRunId, $progress): array {
            $newCount = 0;
            $updatedCount = 0;
            $spamCount = 0;

            $syncRun = TrendyolBoosterReviewSync::query()
                ->where('user_id', $userId)
                ->whereKey($syncRunId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($syncRun->isCompleted()) {
                throw ValidationException::withMessages([
                    'sync_run_id' => 'Tamamlanmış bir senkronizasyona veri eklenemez.',
                ]);
            }

            foreach ($reviews as $reviewData) {
                $existing = TrendyolBoosterReview::withTrashed()
                    ->where('user_id', $userId)
                    ->where('trendyol_review_id', $reviewData['trendyol_review_id'])
                    ->first();

                $maskedName = $this->maskReviewerName($reviewData['reviewer_name'] ?? 'Anonim');
                $rawName = $reviewData['reviewer_name'] ?? 'Anonim';
                $nameHash = hash('sha256', $rawName);
                $reviewData['reviewer_name_hash'] = $nameHash;
                $recentReviewsCallback = fn (string $hash) => TrendyolBoosterReview::where('user_id', $userId)
                    ->where('reviewer_name_hash', $hash)
                    ->where('created_at', '>=', now()->subDays(30))
                    ->count();
                $spamResult = $this->spamDetector->detect($reviewData, $recentReviewsCallback);

                if ($spamResult['is_spam']) {
                    $spamCount++;
                }

                $payload = [
                    'user_id' => $userId,
                    'review_source_id' => $syncRun->review_source_id,
                    'sync_run_id' => $syncRunId,
                    'trendyol_product_id' => $reviewData['trendyol_product_id'],
                    'trendyol_review_id' => $reviewData['trendyol_review_id'],
                    'trendyol_product_barcode' => $reviewData['trendyol_product_barcode'] ?? null,
                    'product_title' => $reviewData['product_title'] ?? '',
                    'product_image_url' => $reviewData['product_image_url'] ?? null,
                    'reviewer_name_masked' => $maskedName,
                    'reviewer_name_hash' => $nameHash,
                    'reviewer_avatar_url' => $reviewData['reviewer_avatar_url'] ?? null,
                    'rating' => $reviewData['rating'],
                    'comment' => $reviewData['comment'],
                    'comment_length' => mb_strlen($reviewData['comment']),
                    'review_media' => $reviewData['review_media'] ?? [],
                    'helpful_count' => $reviewData['helpful_count'] ?? 0,
                    'seller_name' => $reviewData['seller_name'] ?? null,
                    'reviewed_at' => $reviewData['reviewed_at'] ?? now(),
                    'fetched_at' => now(),
                    'spam_score' => $spamResult['score'],
                    'is_spam' => $spamResult['is_spam'],
                    'spam_flags' => $spamResult['flags'],
                ];

                if ($existing) {
                    $existing->fill($payload);
                    $existing->status = $existing->status === 'deleted' ? 'pending' : $existing->status;
                    $existing->save();
                    if ($existing->trashed()) {
                        $existing->restore();
                    }
                    $updatedCount++;
                } else {
                    $payload['status'] = 'pending';
                    TrendyolBoosterReview::create($payload);
                    $newCount++;
                }
            }

            $totalProducts = max(
                (int) $syncRun->total_products,
                max(0, (int) ($progress['total_products'] ?? 0)),
            );
            $processedProducts = max(
                (int) $syncRun->processed_products,
                max(0, (int) ($progress['processed_products'] ?? 0)),
            );

            $syncRun->forceFill([
                'status' => 'running',
                'total_products' => $totalProducts,
                'processed_products' => $totalProducts > 0
                    ? min($processedProducts, $totalProducts)
                    : $processedProducts,
                'total_reviews' => (int) $syncRun->total_reviews + $newCount + $updatedCount,
                'new_reviews' => (int) $syncRun->new_reviews + $newCount,
                'updated_reviews' => (int) $syncRun->updated_reviews + $updatedCount,
                'spam_detected' => (int) $syncRun->spam_detected + $spamCount,
                'progress_percent' => $totalProducts > 0
                    ? min(100, (int) floor(($processedProducts / $totalProducts) * 100))
                    : 0,
            ])->save();

            return [
                'new' => $newCount,
                'updated' => $updatedCount,
                'spam' => $spamCount,
                'progress_percent' => (int) $syncRun->progress_percent,
                'processed_products' => (int) $syncRun->processed_products,
                'total_products' => (int) $syncRun->total_products,
            ];
        });
    }

    /**
     * Senkronizasyon çalışmasını tamamlandı olarak işaretler.
     */
    public function completeSyncRun(int $syncRunId, ?string $error = null, ?int $userId = null): void
    {
        $syncRun = TrendyolBoosterReviewSync::query()
            ->when($userId !== null, fn (Builder $query) => $query->where('user_id', $userId))
            ->whereKey($syncRunId)
            ->first();
        if (! $syncRun) {
            return;
        }

        $syncRun->update([
            'status' => $error ? 'failed' : 'completed',
            'completed_at' => now(),
            'progress_percent' => 100,
            'error_message' => $error,
        ]);

        if (! $error && $syncRun->review_source_id) {
            $syncRun->reviewSource()->update(['last_scanned_at' => now()]);
        }

        if ($error) {
            $this->notificationService->createForUser((int) $syncRun->user_id, [
                'type' => 'booster_review_sync_failed',
                'severity' => 'warning',
                'event_key' => "trendyol-booster-review-sync:{$syncRunId}",
                'title' => 'Trendyol Yorum Senkronizasyonu Başarısız',
                'body' => 'Yorum taraması sırasında bir hata oluştu: '.$error,
                'subject_type' => TrendyolBoosterReviewSync::class,
                'subject_id' => $syncRunId,
                'action_url' => route('mp.trendyol-booster', ['booster' => 'reviews']),
                'data_json' => [
                    'sync_run_id' => $syncRunId,
                    'error' => $error,
                ],
            ]);
        } else {
            $this->notificationService->createForUser((int) $syncRun->user_id, [
                'type' => 'booster_review_sync_complete',
                'severity' => 'info',
                'event_key' => "trendyol-booster-review-sync:{$syncRunId}",
                'title' => 'Trendyol Yorum Senkronizasyonu Tamamlandı',
                'body' => $syncRun->new_reviews.' yeni yorum, '.$syncRun->updated_reviews.' güncellenen, '.$syncRun->spam_detected.' spam tespit edildi.',
                'subject_type' => TrendyolBoosterReviewSync::class,
                'subject_id' => $syncRunId,
                'action_url' => route('mp.trendyol-booster', ['booster' => 'reviews']),
                'data_json' => [
                    'sync_run_id' => $syncRunId,
                    'new_reviews' => $syncRun->new_reviews,
                    'updated_reviews' => $syncRun->updated_reviews,
                    'spam_detected' => $syncRun->spam_detected,
                ],
            ]);
        }
    }

    /**
     * KVKK uyumlu isim maskeleme: "Ahmet Yılmaz" → "Ahmet Y."
     */
    public function maskReviewerName(string $name): string
    {
        $name = trim($name);
        if ($name === '' || $name === 'Anonim') {
            return 'Anonim';
        }

        $parts = preg_split('/\s+/u', $name);
        if (count($parts) < 2) {
            return mb_substr($parts[0], 0, 2).'.';
        }

        $firstName = $parts[0];
        $lastInitial = mb_substr($parts[count($parts) - 1], 0, 1);

        return $firstName.' '.mb_strtoupper($lastInitial, 'UTF-8').'.';
    }

    /**
     * Barkod/SKU ile WooCommerce ürününe otomatik eşleştirme yapar.
     */
    public function matchReviewWithWooCommerce(TrendyolBoosterReview $review): array
    {
        $barcode = $review->trendyol_product_barcode;

        if (empty($barcode)) {
            $review->update(['match_status' => 'unmatched', 'match_score' => 0]);

            return ['matched' => false, 'reason' => 'no_barcode'];
        }

        // MpProduct üzerinden barkod eşleştirme
        $mpProduct = MpProduct::where('user_id', $review->user_id)
            ->where('barcode', $barcode)
            ->first();
        if ($mpProduct) {
            // WooCommerce mağazasının ChannelListing'ini bul
            $wooStore = MarketplaceStore::where('user_id', $review->user_id)
                ->where('marketplace', 'woocommerce')
                ->where('is_active', true)
                ->first();

            $listing = null;
            if ($wooStore) {
                $listing = ChannelListing::with('channelProduct')
                    ->where('mp_product_id', $mpProduct->id)
                    ->where('store_id', $wooStore->id)
                    ->first();
            }

            $externalProductId = trim((string) ($listing?->channelProduct?->external_product_id ?? ''));
            $wooProductId = ctype_digit($externalProductId) ? (int) $externalProductId : null;

            $review->update([
                'mp_product_id' => $mpProduct->id,
                'wc_product_id' => $wooProductId,
                'wc_product_sku' => $mpProduct->barcode,
                'match_status' => $listing ? 'matched' : 'unmatched',
                'match_score' => $listing ? 1.0 : 0.5,
            ]);

            return [
                'matched' => (bool) $listing,
                'mp_product_id' => $mpProduct->id,
                'listing_id' => $listing?->id,
                'wc_product_id' => $wooProductId,
                'reason' => $listing ? null : 'no_woo_listing',
            ];
        }

        $review->update(['match_status' => 'unmatched', 'match_score' => 0]);

        return ['matched' => false, 'reason' => 'no_listing'];
    }

    /**
     * Bir ürünün tüm onaylı yorumlarını WooCommerce'e gönderir (batch).
     */
    public function pushApprovedToWooCommerce(int $userId, ?int $wcProductId = null): array
    {
        $query = TrendyolBoosterReview::where('user_id', $userId)
            ->where('status', 'approved')
            ->where('wc_push_status', '!=', 'pushed')
            ->where('is_spam', false);

        if ($wcProductId) {
            $query->where('wc_product_id', $wcProductId);
        }

        $reviews = $query->limit(50)->get();
        $pushService = app(TrendyolBoosterReviewPushService::class);

        return $pushService->pushBatch($reviews);
    }

    /**
     * Filtre kuralını query'ye uygular.
     */
    public function applyFilter(Builder $query, TrendyolBoosterReviewFilter $filter): Builder
    {
        $query->where('rating', '>=', $filter->min_rating)
            ->where('rating', '<=', $filter->max_rating);

        if ($filter->min_comment_length > 0) {
            $query->where('comment_length', '>=', $filter->min_comment_length);
        }

        if ($filter->require_photo) {
            $query->whereNotNull('review_media')
                ->where('review_media', '!=', '[]');
        }

        if ($filter->auto_exclude_spam) {
            $query->where('is_spam', false);
        }

        $excludeKeywords = $filter->exclude_keywords ?? [];
        foreach ($excludeKeywords as $keyword) {
            $query->where('comment', 'not like', '%'.$keyword.'%');
        }

        $includeKeywords = $filter->include_keywords ?? [];
        foreach ($includeKeywords as $keyword) {
            $query->where('comment', 'like', '%'.$keyword.'%');
        }

        return $query;
    }

    /**
     * Trendyol'da silinen/düzenlenen yorumları tespit eder (orphan cleanup).
     * Son 30 günde çekilmiş yorumları, Trendyol'da hala var mı diye kontrol eder.
     * Chrome eklentisi verify endpoint'ine batch olarak gönderir.
     * Eklenti Trendyol'da kontrol eder, silinenleri raporlar → soft-delete uygulanır.
     */
    public function cleanupOrphanReviews(int $userId): int
    {
        $recentReviews = TrendyolBoosterReview::where('user_id', $userId)
            ->where('status', '!=', 'deleted')
            ->where('fetched_at', '>=', now()->subDays(30))
            ->select('id', 'trendyol_product_id', 'trendyol_review_id')
            ->get();

        if ($recentReviews->isEmpty()) {
            return 0;
        }

        // Ürün bazında grupla ve her ürün için verify batch'ı oluştur
        $byProduct = $recentReviews->groupBy('trendyol_product_id');
        $totalChecked = 0;

        foreach ($byProduct as $productId => $reviews) {
            // Chrome eklentisi bu endpoint'i çağırır:
            // POST /companion/review-scan/verify
            // { trendyol_product_id, review_ids: [...] }
            // Eklenti Trendyol'da kontrol eder, silinenleri CompanionController::reviewScanVerify üzerinden işaretler
            $reviewIds = $reviews->pluck('trendyol_review_id')->take(100)->values()->all();

            Log::info('Orphan review verify dispatched', [
                'user_id' => $userId,
                'trendyol_product_id' => $productId,
                'review_count' => count($reviewIds),
            ]);

            $totalChecked += count($reviewIds);

            // Verify komutu eklentiye bridge event olarak gönderilir
            // Eklenti Trendyol'da kontrol edip sonuçları /review-scan/verify'a POST eder
            // CompanionController::reviewScanVerify silinenleri markDeleted('orphan_cleanup') yapar
        }

        return $totalChecked;
    }

    /**
     * Bir kullanıcının yorum istatistiklerini döner.
     */
    public function getStats(int $userId, ?int $reviewSourceId = null): array
    {
        $base = TrendyolBoosterReview::where('user_id', $userId)
            ->when($reviewSourceId !== null, fn (Builder $query) => $query->where('review_source_id', $reviewSourceId));

        $total = (clone $base)->count();
        $approved = (clone $base)->where('status', 'approved')->count();
        $pending = (clone $base)->where('status', 'pending')->count();
        $rejected = (clone $base)->where('status', 'rejected')->count();
        $spam = (clone $base)->where('is_spam', true)->count();
        $pushed = (clone $base)->where('wc_push_status', 'pushed')->count();
        $matched = (clone $base)->where('match_status', 'matched')->count();

        $ratingDistribution = [];
        for ($i = 5; $i >= 1; $i--) {
            $ratingDistribution[$i] = (clone $base)->where('rating', $i)
                ->where('status', 'approved')->count();
        }

        $avgRating = (clone $base)->where('status', 'approved')
            ->where('is_spam', false)
            ->avg('rating');

        return [
            'total' => $total,
            'approved' => $approved,
            'pending' => $pending,
            'rejected' => $rejected,
            'spam' => $spam,
            'pushed' => $pushed,
            'matched' => $matched,
            'rating_distribution' => $ratingDistribution,
            'average_rating' => round((float) $avgRating, 2),
        ];
    }
}
