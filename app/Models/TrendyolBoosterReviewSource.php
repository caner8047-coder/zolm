<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrendyolBoosterReviewSource extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'marketplace_store_id',
        'store_name',
        'store_url',
        'store_url_hash',
        'merchant_id',
        'is_active',
        'verified_at',
        'verified_product_count',
        'last_scanned_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'verified_at' => 'datetime',
            'last_scanned_at' => 'datetime',
            'verified_product_count' => 'integer',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function marketplaceStore(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class);
    }

    public function syncRuns(): HasMany
    {
        return $this->hasMany(TrendyolBoosterReviewSync::class, 'review_source_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(TrendyolBoosterReview::class, 'review_source_id');
    }
}
