<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TrendyolBoosterStoreWatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'store_url',
        'store_url_hash',
        'store_id',
        'store_name',
        'total_products',
        'best_seller_count',
        'new_product_count',
        'price_change_count',
        'scan_count',
        'last_scan_duration_ms',
        'brand_distribution',
        'store_rating',
        'top_seller_count',
        'campaign_count',
        'avg_price',
        'avg_rating',
        'total_reviews',
        'category_distribution',
        'raw_payload',
        'is_active',
        'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'total_products' => 'integer',
            'best_seller_count' => 'integer',
            'new_product_count' => 'integer',
            'price_change_count' => 'integer',
            'scan_count' => 'integer',
            'last_scan_duration_ms' => 'integer',
            'top_seller_count' => 'integer',
            'campaign_count' => 'integer',
            'total_reviews' => 'integer',
            'brand_distribution' => 'array',
            'category_distribution' => 'array',
            'raw_payload' => 'array',
            'store_rating' => 'decimal:1',
            'avg_price' => 'decimal:2',
            'avg_rating' => 'decimal:1',
            'is_active' => 'boolean',
            'last_checked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TrendyolBoosterStoreWatchItem::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(TrendyolBoosterStoreWatchSnapshot::class);
    }

    public function latestSnapshot(): HasOne
    {
        return $this->hasOne(TrendyolBoosterStoreWatchSnapshot::class)->latestOfMany('checked_at');
    }
}
