<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrendyolBoosterStoreWatchItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'trendyol_booster_store_watch_id',
        'user_id',
        'trendyol_product_id',
        'source_url',
        'image_url',
        'title',
        'brand',
        'rating',
        'review_count',
        'favorite_count',
        'campaign_badges',
        'is_first_seller',
        'sale_price',
        'original_price',
        'discount_rate',
        'previous_sale_price',
        'previous_rating',
        'previous_review_count',
        'price_delta',
        'review_delta',
        'category_name',
        'seller_name',
        'stock_status',
        'stock_quantity',
        'rank',
        'is_new',
        'is_removed',
        'raw_payload',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'sale_price' => 'decimal:2',
            'original_price' => 'decimal:2',
            'discount_rate' => 'decimal:2',
            'previous_sale_price' => 'decimal:2',
            'price_delta' => 'decimal:2',
            'rating' => 'decimal:1',
            'previous_rating' => 'decimal:1',
            'review_count' => 'integer',
            'previous_review_count' => 'integer',
            'favorite_count' => 'integer',
            'review_delta' => 'integer',
            'stock_quantity' => 'integer',
            'rank' => 'integer',
            'is_new' => 'boolean',
            'is_removed' => 'boolean',
            'is_first_seller' => 'boolean',
            'campaign_badges' => 'array',
            'raw_payload' => 'array',
            'checked_at' => 'datetime',
        ];
    }

    public function storeWatch(): BelongsTo
    {
        return $this->belongsTo(TrendyolBoosterStoreWatch::class, 'trendyol_booster_store_watch_id');
    }

    public function histories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TrendyolBoosterStoreItemHistory::class, 'trendyol_booster_store_watch_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
