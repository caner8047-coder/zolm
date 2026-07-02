<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrendyolBoosterStockCheck extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'trendyol_booster_product_id',
        'source_url',
        'source_url_hash',
        'trendyol_product_id',
        'barcode',
        'title',
        'brand',
        'image_url',
        'total_stock',
        'previous_total_stock',
        'stock_delta',
        'estimated_sales',
        'seller_count',
        'stock_status',
        'raw_payload',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'total_stock' => 'integer',
            'previous_total_stock' => 'integer',
            'stock_delta' => 'integer',
            'estimated_sales' => 'integer',
            'seller_count' => 'integer',
            'raw_payload' => 'array',
            'checked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trackedProduct(): BelongsTo
    {
        return $this->belongsTo(TrendyolBoosterProduct::class, 'trendyol_booster_product_id');
    }

    public function sellers(): HasMany
    {
        return $this->hasMany(TrendyolBoosterStockSeller::class);
    }
}
