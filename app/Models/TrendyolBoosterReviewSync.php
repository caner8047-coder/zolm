<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrendyolBoosterReviewSync extends Model
{
    use HasFactory;

    protected $table = 'trendyol_booster_review_syncs';

    protected $fillable = [
        'user_id',
        'review_source_id',
        'status',
        'sync_type',
        'total_products',
        'processed_products',
        'total_reviews',
        'new_reviews',
        'updated_reviews',
        'deleted_reviews',
        'spam_detected',
        'started_at',
        'completed_at',
        'last_synced_at',
        'error_message',
        'meta',
        'progress_percent',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'meta' => 'array',
            'progress_percent' => 'integer',
            'total_products' => 'integer',
            'processed_products' => 'integer',
            'total_reviews' => 'integer',
            'new_reviews' => 'integer',
            'updated_reviews' => 'integer',
            'deleted_reviews' => 'integer',
            'spam_detected' => 'integer',
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

    public function reviews(): HasMany
    {
        return $this->hasMany(TrendyolBoosterReview::class, 'sync_run_id');
    }

    public function isRunning(): bool
    {
        return $this->status === 'running' || $this->status === 'queued';
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, ['completed', 'failed', 'partial'], true);
    }
}
