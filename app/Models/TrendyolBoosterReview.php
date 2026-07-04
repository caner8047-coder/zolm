<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrendyolBoosterReview extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'trendyol_booster_reviews';

    protected $fillable = [
        'user_id',
        'review_source_id',
        'sync_run_id',
        'trendyol_product_id',
        'trendyol_review_id',
        'trendyol_product_barcode',
        'product_title',
        'product_image_url',
        'reviewer_name_masked',
        'reviewer_name_hash',
        'reviewer_avatar_url',
        'rating',
        'comment',
        'comment_length',
        'review_media',
        'helpful_count',
        'seller_name',
        'reviewed_at',
        'fetched_at',
        'mp_product_id',
        'wc_product_id',
        'wc_product_sku',
        'match_status',
        'match_score',
        'wc_push_status',
        'wc_pushed_at',
        'wc_push_error',
        'spam_score',
        'is_spam',
        'spam_flags',
        'status',
        'is_featured',
        'display_order',
        'audit_history',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'comment_length' => 'integer',
            'review_media' => 'array',
            'helpful_count' => 'integer',
            'reviewed_at' => 'datetime',
            'fetched_at' => 'datetime',
            'match_score' => 'float',
            'spam_score' => 'float',
            'is_spam' => 'boolean',
            'spam_flags' => 'array',
            'is_featured' => 'boolean',
            'display_order' => 'integer',
            'audit_history' => 'array',
            'wc_pushed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewSource(): BelongsTo
    {
        return $this->belongsTo(TrendyolBoosterReviewSource::class, 'review_source_id');
    }

    public function syncRun(): BelongsTo
    {
        return $this->belongsTo(TrendyolBoosterReviewSync::class, 'sync_run_id');
    }

    public function mpProduct(): BelongsTo
    {
        return $this->belongsTo(MpProduct::class, 'mp_product_id');
    }

    /**
     * Audit geçmişine yeni bir kayıt ekler (rollback için).
     */
    public function appendAudit(string $action, array $context = []): void
    {
        $history = $this->audit_history ?? [];
        $history[] = [
            'action' => $action,
            'previous_status' => $this->getOriginal('status'),
            'context' => $context,
            'at' => now()->toIso8601String(),
        ];
        $this->audit_history = $history;
        $this->saveQuietly();
    }

    /**
     * Soft-delete ile durumu 'deleted' olarak işaretler (hard-delete yok).
     */
    public function markDeleted(string $reason = 'manual'): void
    {
        $this->appendAudit('delete', ['reason' => $reason]);
        $this->status = 'deleted';
        $this->saveQuietly();
        $this->delete();
    }

    /**
     * Soft-delete'i geri alır ve durumu 'pending' yapar.
     */
    public function restoreReview(): bool
    {
        if ($this->trashed()) {
            $this->appendAudit('restore');
            $this->status = 'pending';
            $this->saveQuietly();

            return $this->restore();
        }

        return false;
    }
}
