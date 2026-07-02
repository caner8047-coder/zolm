<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrendyolBoosterTrendKeyword extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'category_name',
        'keyword',
        'keyword_hash',
        'search_volume_min',
        'search_volume_max',
        'search_volume_label',
        'competition_level',
        'signal_score',
        'previous_signal_score',
        'product_count',
        'store_count',
        'total_favorite_count',
        'total_review_count',
        'average_rating',
        'campaign_product_count',
        'trend_direction',
        'recommended_bid',
        'best_bid',
        'source',
        'source_context',
        'first_seen_at',
        'last_seen_at',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'search_volume_min' => 'integer',
            'search_volume_max' => 'integer',
            'signal_score' => 'integer',
            'previous_signal_score' => 'integer',
            'product_count' => 'integer',
            'store_count' => 'integer',
            'total_favorite_count' => 'integer',
            'total_review_count' => 'integer',
            'average_rating' => 'decimal:2',
            'campaign_product_count' => 'integer',
            'recommended_bid' => 'decimal:2',
            'best_bid' => 'decimal:2',
            'source_context' => 'array',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'imported_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
