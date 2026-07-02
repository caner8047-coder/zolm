<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrendyolBoosterStoreItemHistory extends Model
{
    protected $fillable = [
        'trendyol_booster_store_watch_item_id',
        'trendyol_booster_store_watch_snapshot_id',
        'sale_price',
        'rank',
        'review_count',
        'favorite_count',
        'stock_quantity',
        'stock_status',
        'rating',
        'is_campaign',
    ];

    public $timestamps = false;

    protected $casts = [
        'sale_price' => 'decimal:2',
        'rating' => 'decimal:2',
        'favorite_count' => 'integer',
        'stock_quantity' => 'integer',
        'is_campaign' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function watchItem()
    {
        return $this->belongsTo(TrendyolBoosterStoreWatchItem::class, 'trendyol_booster_store_watch_item_id');
    }

    public function snapshot()
    {
        return $this->belongsTo(TrendyolBoosterStoreWatchSnapshot::class, 'trendyol_booster_store_watch_snapshot_id');
    }
}
