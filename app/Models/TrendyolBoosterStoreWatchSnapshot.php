<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrendyolBoosterStoreWatchSnapshot extends Model
{
    protected $fillable = [
        'trendyol_booster_store_watch_id',
        'user_id',
        'scan_number',
        'status',
        'message',
        'store_id',
        'store_name',
        'store_url',
        'total_products',
        'active_product_count',
        'new_product_count',
        'removed_product_count',
        'price_change_count',
        'campaign_count',
        'top_seller_count',
        'total_reviews',
        'total_favorites',
        'avg_price',
        'min_price',
        'max_price',
        'avg_rating',
        'store_rating',
        'brand_distribution',
        'category_distribution',
        'price_summary',
        'change_summary',
        'raw_payload',
        'scan_duration_ms',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'scan_number' => 'integer',
            'total_products' => 'integer',
            'active_product_count' => 'integer',
            'new_product_count' => 'integer',
            'removed_product_count' => 'integer',
            'price_change_count' => 'integer',
            'campaign_count' => 'integer',
            'top_seller_count' => 'integer',
            'total_reviews' => 'integer',
            'total_favorites' => 'integer',
            'avg_price' => 'decimal:2',
            'min_price' => 'decimal:2',
            'max_price' => 'decimal:2',
            'avg_rating' => 'decimal:1',
            'store_rating' => 'decimal:1',
            'brand_distribution' => 'array',
            'category_distribution' => 'array',
            'price_summary' => 'array',
            'change_summary' => 'array',
            'raw_payload' => 'array',
            'scan_duration_ms' => 'integer',
            'checked_at' => 'datetime',
        ];
    }

    public function storeWatch(): BelongsTo
    {
        return $this->belongsTo(TrendyolBoosterStoreWatch::class, 'trendyol_booster_store_watch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
